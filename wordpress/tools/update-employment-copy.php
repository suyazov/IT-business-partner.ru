<?php
/** Apply the owner's approved employment text to two owned page bodies only. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
if (realpath(ABSPATH) !== '/var/www/sy3-wordpress' || home_url('/') !== 'https://sy3.ru/' || get_option('sy3_site_mode') !== 'expertise' || wp_get_environment_type() !== 'production' || !is_ssl()) WP_CLI::error('Unexpected site identity.');
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
if (!$dry && getenv('SY3_ALLOW_EMPLOYMENT_COPY_UPDATE') !== '1') WP_CLI::error('Explicit scoped apply required.');
require_once dirname(__DIR__) . '/content/home.php';
require_once dirname(__DIR__) . '/content/work-process.php';
$plan = [
    4 => ['slug' => 'home', 'before' => '3776851d743fee619cbcab724ec0e00d2b7ca1e24d06baee7c32cf1181e27c54', 'after' => 'c240f39932dff70b6378d390afcf7c8d080087e4e9719e4bde8eef9192530eda', 'content' => sy3_home_content()],
    5 => ['slug' => 'about', 'before' => 'c094863585a084c860f3df5e33763467d2db07946f78be619e1df2e3b8823972', 'after' => 'e532ba57bd8358bfeacfabe262bb939c9c648a1b39bf34a834197f319f209048', 'content' => sy3_work_process_content()],
];
foreach ($plan as $item) if (hash('sha256', $item['content']) !== $item['after']) WP_CLI::error('Unreviewed page definition.');
global $wpdb;
$read = static function (bool $lock = false) use ($wpdb, $plan): array {
    $records = []; $suffix = $lock ? ' FOR UPDATE' : '';
    foreach ($plan as $id => $item) {
        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d" . $suffix, $id), ARRAY_A);
        if ($wpdb->last_error || !$post || $post['post_type'] !== 'page' || $post['post_status'] !== 'publish' || $post['post_name'] !== $item['slug']) throw new RuntimeException('Owned page identity mismatch.');
        $meta = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id" . $suffix, $id), ARRAY_A);
        if ($wpdb->last_error || !is_array($meta)) throw new RuntimeException('Metadata read failed.');
        $hash = hash('sha256', $post['post_content']);
        if (!in_array($hash, [$item['before'], $item['after']], true)) throw new RuntimeException('Unknown manual edits preserved.');
        $records[$id] = ['post' => $post, 'meta' => $meta, 'hash' => $hash];
    }
    return $records;
};
$transaction = false;
try {
    $records = $read(); $needed = false; $report = ['dry_run' => $dry, 'scope' => ['post_content ID4/home', 'post_content ID5/about'], 'pages' => []];
    foreach ($plan as $id => $item) {
        $needed = $needed || $records[$id]['hash'] !== $item['after'];
        $report['pages'][$id] = ['before' => $records[$id]['hash'], 'after' => $item['after']];
    }
    if ($dry || !$needed) {
        $report['action'] = $needed ? 'would-update' : 'already-current';
        WP_CLI::line(wp_json_encode($report)); return;
    }
    $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->posts, $wpdb->postmeta), ARRAY_A);
    if ($wpdb->last_error || count($engines) !== 2 || array_filter($engines, static fn($row) => strcasecmp($row['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('Transactional tables required.');
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true; $records = $read(true);
    foreach ($plan as $id => $item) {
        if ($records[$id]['hash'] !== $item['after'] && $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=%d AND BINARY post_content=%s", $item['content'], $id, $records[$id]['post']['post_content'])) !== 1) throw new RuntimeException('Body compare-and-swap failed.');
    }
    $saved = $read(true);
    foreach ($plan as $id => $item) {
        $oldFields = $records[$id]['post']; $newFields = $saved[$id]['post'];
        unset($oldFields['post_content'], $newFields['post_content']);
        if ($saved[$id]['hash'] !== $item['after'] || $oldFields !== $newFields || $records[$id]['meta'] !== $saved[$id]['meta']) throw new RuntimeException('Protected fields or read-back mismatch.');
    }
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false;
    foreach (array_keys($plan) as $id) clean_post_cache($id);
    $report['action'] = 'updated'; $report['protected_fields_and_metadata_unchanged'] = true;
    WP_CLI::line(wp_json_encode($report));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
