<?php
/** Import one additional expertise article, 100 search drafts and twelve new services. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Production authority required.');
if (get_option('sy3_site_mode') !== 'expertise' || !defined('SY3_EDITORIAL_BATCH')) WP_CLI::error('Main site and editorial plugin required.');
if(!function_exists('sy3_editorial_review_hash'))WP_CLI::error('Activate the reviewed editorial plugin before any import.');
require_once __DIR__ . '/search-content-lib.php';
[$records, $services, $media] = sy3_search_load(getenv('SY3_CONTENT_COLLECTION')?:'search');
$owned = []; $service_ids = []; $case_ids = [];
foreach ($services as $service) {
    $seed = 'service:' . $service['slug'];
    $prior = get_posts(['post_type' => 'sy3_service', 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => $seed, 'numberposts' => 2]);
    $collision = get_page_by_path($service['slug'], OBJECT, 'sy3_service');
    if (count($prior) > 1 || ($collision && (!$prior || $prior[0]->ID !== $collision->ID))) WP_CLI::error('Unowned service slug: ' . $service['slug']);
    if ($prior) $service_ids[$service['slug']] = $prior[0]->ID;
}
foreach ($records as $record) {
    $batch = $record['id'] === 101 ? SY3_EDITORIAL_BATCH : SY3_SEARCH_BATCH;
    $prior = get_posts(['post_type' => 'post', 'post_status' => ['draft','pending','future','publish','private','trash'], 'numberposts' => 2, 'meta_query' => [['key' => '_sy3_editorial_batch', 'value' => $batch], ['key' => '_sy3_editorial_key', 'value' => (string) $record['id']]]]);
    $collision = get_page_by_path($record['slug'], OBJECT, 'post');
    if (count($prior) > 1 || ($collision && (!$prior || $prior[0]->ID !== $collision->ID))) WP_CLI::error('Unowned article slug: ' . $record['slug']);
    if ($prior) $owned[$record['id']] = $prior[0]->ID;
    if (!isset($service_ids[$record['service']]) && !in_array($record['service'], array_column($services, 'slug'), true)) {
        $s = get_page_by_path($record['service'], OBJECT, 'sy3_service');
        if (!$s || $s->post_status !== 'publish') WP_CLI::error('Missing published target service.');
        $service_ids[$record['service']] = $s->ID;
    }
}
foreach (array_unique(array_filter(array_merge(array_column($records, 'case'), ...array_column($services, 'case_slugs')))) as $slug) {
    $case = get_page_by_path($slug, OBJECT, 'sy3_case');
    if (!$case || $case->post_status !== 'publish') WP_CLI::error('Missing published case: ' . $slug);
    $case_ids[$slug] = $case->ID;
}
$author = get_user_by('login', getenv('SY3_EDITORIAL_AUTHOR') ?: 'artem_admin');
if (!$author || !user_can($author, 'publish_posts')) WP_CLI::error('Expected site author missing.');
// Reuse only existing, hash-identified covers; this task does not duplicate media.
$covers = [];
foreach (array_unique(array_column($records, 'cover')) as $alias) {
    $prior = get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_sy3_media_hash','meta_value'=>$media[$alias]['sha256'],'numberposts'=>1]);
    if (!$prior || !is_file(get_attached_file($prior[0]->ID))) WP_CLI::error('Expected imported cover missing: ' . $alias);
    $covers[$alias] = $prior[0]->ID;
}
if (getenv('SY3_IMPORT_DRY_RUN') === '1') { WP_CLI::success('Preflight: '.count($records).' articles, 12 services; ' . count($owned) . ' articles already owned; existing content preserved; no writes.'); return; }
$receipt = ['services'=>[], 'articles'=>[]];
foreach ($services as $n => $service) {
    if (isset($service_ids[$service['slug']])) { $receipt['services'][] = ['slug'=>$service['slug'],'id'=>$service_ids[$service['slug']],'action'=>'preserved']; continue; }
    $body = sy3_sections($service['sections']);
    $links = [];
    foreach ($service['case_slugs'] as $slug) $links[] = '<a href="' . esc_url(get_permalink($case_ids[$slug])) . '">' . esc_html(get_the_title($case_ids[$slug])) . '</a>';
    if ($links) $body .= sy3_h('Практика по теме') . sy3_list($links);
    $body .= sy3_h('Частые вопросы');
    foreach ($service['faqs'] as [$question,$answer]) $body .= sy3_faq($question,$answer);
    $body .= sy3_buttons([['Обсудить проект','https://t.me/sy3_support_bot?start=site_sy3']]);
    $id = wp_insert_post(wp_slash(['post_type'=>'sy3_service','post_status'=>'publish','post_author'=>$author->ID,'post_name'=>$service['slug'],'post_title'=>$service['title'],'post_excerpt'=>$service['excerpt'],'post_content'=>$body,'menu_order'=>10+$n,'comment_status'=>'closed','ping_status'=>'closed','meta_input'=>['_sy3_seed'=>'service:'.$service['slug'],'_sy3_seed_hash'=>hash('sha256',$body),'_sy3_label'=>$service['label'],'_sy3_art'=>$service['art'],'_sy3_description'=>$service['excerpt'],'_sy3_search_query'=>$service['primary_query'],'_sy3_related'=>implode(',',array_map(fn($s)=>$case_ids[$s],array_slice($service['case_slugs'],0,3)))]]),true);
    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
    $service_ids[$service['slug']] = $id; $receipt['services'][] = ['slug'=>$service['slug'],'id'=>$id,'action'=>'created'];
}
foreach ($records as $record) {
    if (isset($owned[$record['id']])) { $receipt['articles'][]=['key'=>$record['id'],'id'=>$owned[$record['id']],'action'=>'preserved']; continue; }
    $body = $record['blocks'];
    $source_links = array_map(fn($s)=>'<a href="'.esc_url($s['url']).'">'.esc_html($s['title']).'</a>',$record['sources']);
    $body .= sy3_g(sy3_h('Документация и источники',3).sy3_list($source_links),'editorial-sources');
    $service_id = $service_ids[$record['service']];
    $body .= sy3_h('Если нужна помощь с этой задачей') . sy3_p('<a href="'.esc_url(get_permalink($service_id)).'">'.esc_html(get_the_title($service_id)).'</a> — обсудим исходную ситуацию, объём и способ проверки результата. <a href="https://t.me/sy3_support_bot?start=site_sy3">Написать мне</a>');
    if (!empty($record['case'])) $body .= sy3_p('Практика по теме: <a href="'.esc_url(get_permalink($case_ids[$record['case']])).'">'.esc_html(get_the_title($case_ids[$record['case']])).'</a>.');
    $term = term_exists($record['cluster'],'category') ?: wp_insert_term($record['cluster'],'category');
    if (is_wp_error($term)) WP_CLI::error($term->get_error_message());
    $meta = ['_sy3_editorial_batch'=>$record['id']===101?SY3_EDITORIAL_BATCH:SY3_SEARCH_BATCH,'_sy3_editorial_key'=>(string)$record['id'],'_sy3_editorial_enabled'=>empty($record['standalone'])?'1':'0','_sy3_editorial_at'=>(new DateTimeImmutable($record['planned_at'],new DateTimeZone('Europe/Moscow')))->getTimestamp(),'_sy3_editorial_seed_hash'=>hash('sha256',$body),'_sy3_search_query'=>$record['primary_query'],'_sy3_search_variants'=>$record['related_queries'],'_sy3_search_sources'=>$record['sources'],'_sy3_label'=>$record['cluster'],'_sy3_description'=>$record['excerpt'],'_sy3_related'=>implode(',',array_filter([$service_id,!empty($record['case'])?$case_ids[$record['case']]:null]))];
    if(!empty($record['standalone']))$meta['_sy3_editorial_standalone']='1';
    $id = wp_insert_post(wp_slash(['post_type'=>'post','post_status'=>'draft','post_author'=>$author->ID,'post_name'=>$record['slug'],'post_title'=>$record['title'],'post_excerpt'=>$record['excerpt'],'post_content'=>$body,'post_category'=>[(int)$term['term_id']],'comment_status'=>'closed','ping_status'=>'closed','meta_input'=>$meta]),true);
    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
    set_post_thumbnail($id,$covers[$record['cover']]); $owned[$record['id']]=$id;
    update_post_meta($id,'_sy3_editorial_reviewed_hash',sy3_editorial_review_hash($id));
    $receipt['articles'][]=['key'=>$record['id'],'id'=>$id,'action'=>'created'];
}
WP_CLI::log(wp_json_encode($receipt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
WP_CLI::success(count($records).' reviewed drafts imported/preserved; twelve services published/preserved. No article published; timer unchanged.');
