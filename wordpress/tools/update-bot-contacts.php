<?php
/** Exact, idempotent contact migration. Only post_content and its owned seed hash may change. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Only the expertise site is in scope.');
$root = realpath(ABSPATH);
$environment = wp_get_environment_type();
$home = home_url('/');
$previewRoot = '/root/preview/sy3-wordpress-20261022/public';
$production = $environment === 'production' && $root === '/var/www/sy3-wordpress' && $home === 'https://sy3.ru/';
$preview = $environment !== 'production' && $root === $previewRoot && in_array($home, ['http://127.0.0.1:8186/', 'http://localhost:8186/'], true);
if (!$production && !$preview) WP_CLI::error('Unexpected site root, home URL or environment.');
if ($production && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Exact production authority flag is required.');
if ($production && !is_ssl()) WP_CLI::error('Production page definitions require HTTPS=on SERVER_PORT=443.');
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';

$source = dirname(__DIR__).'/content/pages.php';
if (!is_file($source)) WP_CLI::error('Reviewed page definitions were not found.');
if (function_exists('sy3_page_definitions') && realpath((new ReflectionFunction('sy3_page_definitions'))->getFileName()) !== realpath($source)) {
    WP_CLI::error('A different page definition is already loaded.');
}
require_once $source;
$telegram = 'https://t.me/sy3_support_bot?start=site_sy3';
$max = 'https://max.ru/id263514840335_1_bot?start=site_sy3';
$contacts = sy3_page_definitions()['contacts']['content'];
$known = $production ? [
    4 => ['before'=>'82bc12cb9b6434f74f11afbfd29a75964f6a85afecb2b1ff6d6319e39c666770', 'after'=>'f15b168e4d014f2fd5b79887e870ad3e9e3a4ff1ffdcbd290efa54768060bccd'],
    6 => ['before'=>'b35e5323f9e8032023fe8cd4f18b8b890eed25c02caa53134f10cdb5657966c8', 'after'=>'fb66406983c71066aea5b2e35a0111706036e7bee37a207770b9f40997411293'],
] : [
    4 => ['before'=>'8afd340d4468bd9427a879db1c121e10b5b7b1efb7f616bf6d32f2fc511bc10b', 'after'=>'687c10eb9505b1208b2e947493b2344931b58f8d9cb6d5b3e7f7ba649cdccc5a'],
    6 => ['before'=>'6998c0d5ee9e90c90676dffd72b9810b5201d7696555c2667850e20ef8a71092', 'after'=>'48f00d6ff0b89272a4cd83c20e4d3513567a015a5c6592b482b83fd9c25f3c87'],
];
// Preview hashes bind the existing 127.0.0.1 fixture; localhost is never silently substituted.
if (hash('sha256', $contacts) !== $known[6]['after']) WP_CLI::error('Contacts definition differs from the reviewed after hash.');

global $wpdb;
$identities = [4=>['slug'=>'home','seed'=>'page:home'], 6=>['slug'=>'contacts','seed'=>'page:contacts']];
$read = static function (int $id, bool $lock = false) use ($wpdb): array {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID = %d".($lock?' FOR UPDATE':''), $id), ARRAY_A);
    if ($wpdb->last_error !== '' || !$row) throw new RuntimeException('Expected page missing or unreadable: '.$id);
    $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('_sy3_seed','_sy3_seed_hash') ORDER BY meta_id".($lock?' FOR UPDATE':''), $id), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($meta) || count($meta) !== 2 || count(array_unique(array_column($meta,'meta_key'))) !== 2) throw new RuntimeException('Missing, unreadable or duplicate ownership metadata: '.$id);
    $mapped = [];
    foreach ($meta as $item) $mapped[$item['meta_key']] = $item;
    return ['post'=>$row, 'seed'=>$mapped['_sy3_seed']['meta_value'], 'hash'=>$mapped['_sy3_seed_hash']['meta_value'], 'meta_id'=>(int)$mapped['_sy3_seed_hash']['meta_id']];
};
$plan = static function (int $id, array $record) use ($identities,$known,$contacts,$telegram,$max): array {
    $post = $record['post']; $identity = $identities[$id];
    if ($post['post_type'] !== 'page' || $post['post_status'] !== 'publish' || $post['post_name'] !== $identity['slug'] || $record['seed'] !== $identity['seed']) throw new RuntimeException('Unexpected page identity: '.$id);
    $actual = hash('sha256', $post['post_content']);
    if (!hash_equals($record['hash'], $actual) || !in_array($actual, [$known[$id]['before'],$known[$id]['after']], true)) throw new RuntimeException('Manual or unknown content preserved; reconcile page '.$id.'.');
    if ($id === 4 && $actual === $known[$id]['before']) {
        $old = '<p class="contact-alternative"><a href="tel:+79064790552">+7 (906) 479-05-52</a></p>';
        $new = '<p class="contact-alternative"><a href="'.$max.'">Написать в MAX</a></p>';
        $content = str_replace($old, $new, $post['post_content'], $phoneCount);
        $content = str_replace('https://t.me/moi_imperator', $telegram, $content, $telegramCount);
        if ($phoneCount !== 1 || $telegramCount !== 3) throw new RuntimeException('Unexpected surgical replacement count on page 4.');
    } else {
        $content = $id === 6 ? $contacts : $post['post_content'];
    }
    if (hash('sha256', $content) !== $known[$id]['after'] || str_contains($content,'tel:') || str_contains($content,'moi_imperator') || str_contains($content,'79064790552')) throw new RuntimeException('Reviewed replacement content failed validation: '.$id);
    if (!str_contains($content,$telegram) || !str_contains($content,$max)) throw new RuntimeException('Both bot links are required: '.$id);
    return ['record'=>$record, 'content'=>$content, 'actual'=>$actual, 'after'=>$known[$id]['after'], 'action'=>$actual===$known[$id]['after']?'already-current':'update'];
};
$metaFingerprint = static function (int $id, int $excluded) use ($wpdb): array {
    // Only the two owned pages are read; raw values stay in memory and never enter receipts.
    $rows = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_id <> %d ORDER BY meta_id", $id, $excluded), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($rows)) throw new RuntimeException('Cannot inspect metadata fingerprint.');
    return array_map(static fn($row)=>['meta_id'=>$row['meta_id'],'meta_key'=>$row['meta_key'],'digest'=>hash('sha256',wp_json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))],$rows);
};
$report = static function (array $plans, bool $dry) use ($environment): void {
    $rows=[];
    foreach($plans as $id=>$item)$rows[]=['id'=>$id,'slug'=>$item['record']['post']['post_name'],'before'=>$item['actual'],'after'=>$item['after'],'action'=>$item['action']];
    WP_CLI::line(wp_json_encode(['dry_run'=>$dry,'environment'=>$environment,'current_front_id'=>(int)get_option('page_on_front'),'records'=>$rows,'write_scope'=>['post_content','_sy3_seed_hash'],'untouched_ids'=>[7,108]],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
};

$inTransaction = false;
try {
    $plans=[];
    foreach(array_keys($identities) as $id)$plans[$id]=$plan($id,$read($id));
    $engines=$wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)',$wpdb->posts,$wpdb->postmeta),ARRAY_A);
    if($wpdb->last_error!==''||!is_array($engines)||count($engines)!==2||array_filter($engines,static fn($row)=>strcasecmp($row['Engine']??'','InnoDB')!==0))throw new RuntimeException('Both target tables must support InnoDB transactions.');
    if($dry){$report($plans,true);WP_CLI::success('Exact contact preflight passed; no database writes.');return;}
    if(!array_filter($plans,static fn($item)=>$item['action']==='update')){$report($plans,false);WP_CLI::success('Both pages already current; no writes.');return;}
    if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('Cannot start contact transaction.');
    $inTransaction=true;
    $unchangedMeta=[];
    // Re-read under row locks; preflight cannot authorize overwriting a later editor change.
    foreach(array_keys($identities) as $id){$plans[$id]=$plan($id,$read($id,true));$unchangedMeta[$id]=$metaFingerprint($id,$plans[$id]['record']['meta_id']);}
    foreach($plans as $id=>$item){
        if($item['action']==='already-current')continue;
        $postResult=$wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=%d AND BINARY post_content=%s",
            $item['content'],$id,$item['record']['post']['post_content']
        ));
        if($postResult!==1)throw new RuntimeException('Post compare-and-swap failed: '.$id);
        $metaResult=$wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value=%s WHERE meta_id=%d AND post_id=%d AND meta_key='_sy3_seed_hash' AND BINARY meta_value=%s",
            $item['after'],$item['record']['meta_id'],$id,$item['actual']
        ));
        if($metaResult!==1)throw new RuntimeException('Seed-hash compare-and-swap failed: '.$id);
    }
    foreach($plans as $id=>$item){
        $saved=$read($id,true);$before=$item['record']['post'];$after=$saved['post'];
        if(hash('sha256',$after['post_content'])!==$item['after']||$saved['hash']!==$item['after']||$saved['seed']!==$item['record']['seed'])throw new RuntimeException('Contact read-back mismatch: '.$id);
        unset($before['post_content'],$after['post_content']);
        if($before!==$after||$metaFingerprint($id,$saved['meta_id'])!==$unchangedMeta[$id])throw new RuntimeException('Unexpected non-content change: '.$id);
    }
    if($wpdb->query('COMMIT')===false)throw new RuntimeException('Cannot commit contact transaction.');
    $inTransaction=false;
    foreach(array_keys($plans) as $id)clean_post_cache($id);
    $report($plans,false);
    WP_CLI::success('Updated only two owned page bodies and seed hashes; unrelated fields and records preserved.');
} catch(Throwable $error) {
    if($inTransaction)$wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
