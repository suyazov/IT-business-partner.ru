<?php
/** Replace only the owned home FAQ section and seed hash; preserve other fields. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
$production = wp_get_environment_type() === 'production' && realpath(ABSPATH) === '/var/www/sy3-wordpress' && home_url('/') === 'https://sy3.ru/';
$preview = wp_get_environment_type() !== 'production' && realpath(ABSPATH) === '/root/preview/sy3-wordpress-20261022/public' && home_url('/') === 'http://127.0.0.1:8186/';
if (get_option('sy3_site_mode') !== 'expertise' || (!$production && !$preview)) WP_CLI::error('Unexpected site identity.');
if ($production && (getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1' || !is_ssl())) WP_CLI::error('Production authority and HTTPS CLI context required.');
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
require_once dirname(__DIR__).'/content/home-faq.php';
$section = serialize_block(parse_blocks(sy3_home_faq_section())[0]);
if (hash('sha256', $section) !== '1dc28e93d73ce7726cedf9089812d90f574cb2c6787ce17121b9948988c0cf45') WP_CLI::error('FAQ definition differs from reviewed hash.');
$beforeHash = $production ? 'f15b168e4d014f2fd5b79887e870ad3e9e3a4ff1ffdcbd290efa54768060bccd' : '687c10eb9505b1208b2e947493b2344931b58f8d9cb6d5b3e7f7ba649cdccc5a';
$afterHash = $production ? '5d4535fc03f8dec3eba29a02f27d4f2cbbd005abda8583a4af1413f36942cab8' : 'acc03fd85ced92a907871c25575be512aa109d4defd6c1132a60a6a03fa3f125';

global $wpdb;
$read = static function (bool $lock = false) use ($wpdb, $beforeHash, $afterHash): array {
    $suffix = $lock ? ' FOR UPDATE' : '';
    $post = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID=4".$suffix, ARRAY_A);
    if ($wpdb->last_error || !$post || $post['post_type'] !== 'page' || $post['post_status'] !== 'publish' || $post['post_name'] !== 'home') throw new RuntimeException('Home page identity mismatch.');
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id=4 ORDER BY meta_id".$suffix, ARRAY_A);
    if ($wpdb->last_error || !is_array($meta)) throw new RuntimeException('Cannot inspect page metadata.');
    $seed = array_values(array_filter($meta, static fn($row) => $row['meta_key'] === '_sy3_seed'));
    $hash = array_values(array_filter($meta, static fn($row) => $row['meta_key'] === '_sy3_seed_hash'));
    $actual = hash('sha256', $post['post_content']);
    if (count($seed) !== 1 || count($hash) !== 1 || $seed[0]['meta_value'] !== 'page:home' || !hash_equals($hash[0]['meta_value'], $actual) || !in_array($actual, [$beforeHash,$afterHash], true)) throw new RuntimeException('Unknown or manually edited home content preserved.');
    return ['post'=>$post,'meta'=>$meta,'hash'=>$actual,'hash_meta_id'=>(int)$hash[0]['meta_id']];
};
$otherMeta = static fn($record) => array_values(array_filter($record['meta'], static fn($row) => (int)$row['meta_id'] !== $record['hash_meta_id']));
$transaction = false;
try {
    $record = $read();
    if ((int) get_option('page_on_front') !== 4 || get_option('show_on_front') !== 'page') throw new RuntimeException('Unexpected front page.');
    $content = $record['post']['post_content'];
    if ($record['hash'] !== $afterHash) {
        $matches = [];
        foreach (parse_blocks($content) as $block) {
            $raw = serialize_block($block);
            if (str_contains($raw, 'faq-list')) $matches[] = $raw;
        }
        if (count($matches) !== 1 || hash('sha256', $matches[0]) !== '39b58034d0b962c4063322fee95607027ba64f7f161ac2fb34f056386f500467' || substr_count($content, $matches[0]) !== 1) throw new RuntimeException('Unexpected FAQ section preserved.');
        $content = str_replace($matches[0], $section, $content);
    }
    if (hash('sha256', $content) !== $afterHash) throw new RuntimeException('Expected FAQ-only replacement hash mismatch.');
    $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)', $wpdb->posts,$wpdb->postmeta), ARRAY_A);
    if ($wpdb->last_error || count($engines) !== 2 || array_filter($engines,static fn($row)=>strcasecmp($row['Engine']??'','InnoDB')!==0)) throw new RuntimeException('Transactional tables required.');
    $report = ['id'=>4,'slug'=>'home','dry_run'=>$dry,'before'=>$record['hash'],'after'=>$afterHash,'scope'=>['post_content','_sy3_seed_hash']];
    if ($dry || $record['hash'] === $afterHash) {
        $report['action'] = $record['hash'] === $afterHash ? 'already-current' : 'would-update';
        WP_CLI::line(wp_json_encode($report)); return;
    }
    if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('Cannot begin transaction.');
    $transaction = true;
    $record = $read(true);
    if ($record['hash'] !== $afterHash) {
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=4 AND BINARY post_content=%s",$content,$record['post']['post_content']));
        if ($result !== 1) throw new RuntimeException('Page compare-and-swap failed.');
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE meta_id=%d AND post_id=4 AND meta_key='_sy3_seed_hash' AND BINARY meta_value=%s",$afterHash,$record['hash_meta_id'],$record['hash']));
        if ($result !== 1) throw new RuntimeException('Seed compare-and-swap failed.');
    }
    $saved = $read(true); $oldFields = $record['post']; $newFields = $saved['post'];
    unset($oldFields['post_content'],$newFields['post_content']);
    if ($saved['hash'] !== $afterHash || $oldFields !== $newFields || $otherMeta($record) !== $otherMeta($saved)) throw new RuntimeException('Read-back or protected fields mismatch.');
    if ($wpdb->query('COMMIT') === false) throw new RuntimeException('Cannot commit transaction.');
    $transaction = false; clean_post_cache(4);
    $report['action'] = 'updated'; WP_CLI::line(wp_json_encode($report));
} catch (Throwable $error) {
    if ($transaction) $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
