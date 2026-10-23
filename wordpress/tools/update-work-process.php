<?php
/** Update only the owned about page body and seed hash; preserve all other fields. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
$production = wp_get_environment_type() === 'production' && realpath(ABSPATH) === '/var/www/sy3-wordpress' && home_url('/') === 'https://sy3.ru/';
$preview = wp_get_environment_type() !== 'production' && realpath(ABSPATH) === '/root/preview/sy3-wordpress-20261022/public' && home_url('/') === 'http://127.0.0.1:8186/';
if (get_option('sy3_site_mode') !== 'expertise' || (!$production && !$preview)) WP_CLI::error('Unexpected site identity.');
if ($production && (getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1' || !is_ssl())) WP_CLI::error('Production authority and HTTPS CLI context required.');
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
require_once dirname(__DIR__).'/content/work-process.php';
$content = sy3_work_process_content();
$beforeHash = $production ? 'cad7432502bf64e1064c65d9656ac7ba55face40e16ecfaa0118a29eced7a9b6' : '39114f8a7f794e6f2a0f16b2824c6e5508dae07ec52604713458e431e7c10cf5';
$afterHash = $production ? '767433e8e3efec0337ca321078bcc515665f692a29bd2349131e6dfa50912d6e' : '4716db914c937c184124fe285598de03f4b35e653d38310bddaa5b2240e9c7f1';
if (hash('sha256', $content) !== $afterHash) WP_CLI::error('Page definition differs from reviewed hash.');
global $wpdb;
$read = static function (bool $lock = false) use ($wpdb, $beforeHash, $afterHash): array {
    $suffix = $lock ? ' FOR UPDATE' : '';
    $post = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=5".$suffix, ARRAY_A);
    if ($wpdb->last_error || !$post || $post['post_type'] !== 'page' || $post['post_status'] !== 'publish' || $post['post_name'] !== 'about') throw new RuntimeException('About page identity mismatch.');
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=5 ORDER BY meta_id".$suffix, ARRAY_A);
    if ($wpdb->last_error || !is_array($meta)) throw new RuntimeException('Cannot inspect page metadata.');
    $seed = array_values(array_filter($meta, static fn($row) => $row['meta_key'] === '_sy3_seed'));
    $hash = array_values(array_filter($meta, static fn($row) => $row['meta_key'] === '_sy3_seed_hash'));
    $actual = hash('sha256', $post['post_content']);
    if (count($seed) !== 1 || count($hash) !== 1 || $seed[0]['meta_value'] !== 'page:about' || !hash_equals($hash[0]['meta_value'], $actual) || !in_array($actual, [$beforeHash,$afterHash], true)) throw new RuntimeException('Unknown or manually edited about content preserved.');
    return ['post'=>$post,'meta'=>$meta,'hash'=>$actual,'hash_meta_id'=>(int)$hash[0]['meta_id']];
};
$otherMeta = static fn($record) => array_values(array_filter($record['meta'], static fn($row) => (int)$row['meta_id'] !== $record['hash_meta_id']));
$transaction = false;
try {
    $record = $read();
    $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->posts,$wpdb->postmeta), ARRAY_A);
    if ($wpdb->last_error || count($engines) !== 2 || array_filter($engines,static fn($row)=>strcasecmp($row['Engine']??'','InnoDB')!==0)) throw new RuntimeException('Transactional tables required.');
    $report = ['id'=>5,'slug'=>'about','dry_run'=>$dry,'before'=>$record['hash'],'after'=>$afterHash,'scope'=>['post_content','_sy3_seed_hash']];
    if ($dry || $record['hash'] === $afterHash) {
        $report['action'] = $record['hash'] === $afterHash ? 'already-current' : 'would-update';
        WP_CLI::line(wp_json_encode($report)); return;
    }
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true;
    $record = $read(true);
    if ($record['hash'] !== $afterHash) {
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=5 AND BINARY post_content=%s",$content,$record['post']['post_content']));
        if ($result !== 1) throw new RuntimeException('Page compare-and-swap failed.');
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE meta_id=%d AND post_id=5 AND meta_key='_sy3_seed_hash' AND BINARY meta_value=%s",$afterHash,$record['hash_meta_id'],$record['hash']));
        if ($result !== 1) throw new RuntimeException('Seed compare-and-swap failed.');
    }
    $saved = $read(true); $oldFields = $record['post']; $newFields = $saved['post'];
    unset($oldFields['post_content'],$newFields['post_content']);
    if ($saved['hash'] !== $afterHash || $oldFields !== $newFields || $otherMeta($record) !== $otherMeta($saved)) throw new RuntimeException('Read-back or protected fields mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false; clean_post_cache(5);
    $report['action'] = 'updated'; WP_CLI::line(wp_json_encode($report));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
