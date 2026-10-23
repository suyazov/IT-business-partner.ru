<?php
/** Reviewed about page selection; preserve all other fields and metadata. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
if (realpath(ABSPATH) !== '/var/www/sy3-wordpress' || home_url('/') !== 'https://sy3.ru/' || get_option('sy3_site_mode') !== 'expertise' || wp_get_environment_type() !== 'production' || !is_ssl()) WP_CLI::error('Unexpected site identity.');
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
if (!$dry && getenv('SY3_ALLOW_ABOUT_EXPERIENCE_UPDATE') !== '1') WP_CLI::error('Explicit scoped apply required.');
require_once dirname(__DIR__) . '/content/work-process.php';
$before = '177c08838902d93942c5f059f2a5f8d9f26ff269d3a6d03abc9aea68a948bf49';
$after = 'c094863585a084c860f3df5e33763467d2db07946f78be619e1df2e3b8823972';
$content = sy3_work_process_content();
if (hash('sha256', $content) !== $after) WP_CLI::error('Unreviewed page definition.');
global $wpdb;
$read = static function (bool $lock = false) use ($wpdb, $before, $after): array {
    $suffix = $lock ? ' FOR UPDATE' : '';
    $post = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=5" . $suffix, ARRAY_A);
    if ($wpdb->last_error || !$post || $post['post_type'] !== 'page' || $post['post_status'] !== 'publish' || $post['post_name'] !== 'about') throw new RuntimeException('About identity mismatch.');
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=5 ORDER BY meta_id" . $suffix, ARRAY_A);
    if ($wpdb->last_error || !is_array($meta)) throw new RuntimeException('Metadata read failed.');
    $hash = hash('sha256', $post['post_content']);
    if (!in_array($hash, [$before, $after], true)) throw new RuntimeException('Unknown manual edits preserved.');
    return ['post' => $post, 'meta' => $meta, 'hash' => $hash];
};
$transaction = false;
try {
    $record = $read();
    $report = ['id' => 5, 'slug' => 'about', 'before' => $record['hash'], 'after' => $after, 'scope' => ['post_content'], 'dry_run' => $dry];
    if ($dry || $record['hash'] === $after) {
        $report['action'] = $record['hash'] === $after ? 'already-current' : 'would-update';
        WP_CLI::line(wp_json_encode($report)); return;
    }
    $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->posts, $wpdb->postmeta), ARRAY_A);
    if ($wpdb->last_error || count($engines) !== 2 || array_filter($engines, static fn($row) => strcasecmp($row['Engine'] ?? '', 'InnoDB') !== 0)) throw new RuntimeException('Transactional tables required.');
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true;
    $record = $read(true);
    if ($record['hash'] !== $after && $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=5 AND BINARY post_content=%s", $content, $record['post']['post_content'])) !== 1) throw new RuntimeException('Body compare-and-swap failed.');
    $saved = $read(true);
    $oldFields = $record['post']; $newFields = $saved['post'];
    unset($oldFields['post_content'], $newFields['post_content']);
    if ($saved['hash'] !== $after || $oldFields !== $newFields || $record['meta'] !== $saved['meta']) throw new RuntimeException('Protected fields or read-back mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false;
    clean_post_cache(5);
    $report['action'] = 'updated';
    $report['protected_fields_and_metadata_unchanged'] = true;
    WP_CLI::line(wp_json_encode($report));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
