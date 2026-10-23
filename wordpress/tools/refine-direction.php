<?php
/** Publish only the reviewed visual direction and twelve new service descriptions. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
if(wp_get_environment_type()==='production'&&getenv('SY3_ALLOW_PRODUCTION_IMPORT')!=='1')WP_CLI::error('Production authority required.');
if(get_option('sy3_site_mode')!=='expertise')WP_CLI::error('Only sy3.ru is in scope.');
require_once __DIR__.'/../content/home.php';
$base=__DIR__.'/../search-content/';
$services=array_merge(json_decode(file_get_contents($base.'services.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents($base.'partner-services.json'),true,512,JSON_THROW_ON_ERROR));
if(count($services)!==12||count(array_unique(array_column($services,'slug')))!==12)WP_CLI::error('Twelve distinct services required.');
$home=get_posts(['post_type'=>'page','post_status'=>'any','meta_key'=>'_sy3_seed','meta_value'=>'page:home','numberposts'=>2]);
if(count($home)!==1||!hash_equals((string)get_post_meta($home[0]->ID,'_sy3_seed_hash',true),hash('sha256',$home[0]->post_content)))WP_CLI::error('Home manual edits preserved.');
$author=get_user_by('login',getenv('SY3_EDITORIAL_AUTHOR')?:'artem_admin');
if(!$author||!user_can($author,'publish_posts'))WP_CLI::error('Expected author missing.');
$existing=[];$bodies=[];$related=[];
foreach($services as $s){
    if(!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',$s['slug'])||count($s['sections'])<4||count($s['faqs'])<2)WP_CLI::error('Incomplete service.');
    $prior=get_posts(['post_type'=>'sy3_service','post_status'=>'any','meta_key'=>'_sy3_seed','meta_value'=>'service:'.$s['slug'],'numberposts'=>2]);
    $collision=get_page_by_path($s['slug'],OBJECT,'sy3_service');
    if(count($prior)>1||($collision&&(!$prior||$prior[0]->ID!==$collision->ID)))WP_CLI::error('Unowned service slug: '.$s['slug']);
    if($prior)$existing[$s['slug']]=$prior[0]->ID;
    $body=sy3_sections($s['sections']);$links=[];$ids=[];
    foreach($s['case_slugs'] as $slug){$case=get_page_by_path($slug,OBJECT,'sy3_case');if(!$case||$case->post_status!=='publish')WP_CLI::error('Missing approved case: '.$slug);$ids[]=$case->ID;$links[]='<a href="'.esc_url(get_permalink($case)).'">'.esc_html(get_the_title($case)).'</a>';}
    if($links)$body.=sy3_h('Практика по теме').sy3_list($links);
    $body.=sy3_h('Частые вопросы');foreach($s['faqs'] as [$q,$a])$body.=sy3_faq($q,$a);
    $body.=sy3_buttons([['Обсудить проект','https://t.me/sy3_support_bot?start=site_sy3']]);
    $bodies[$s['slug']]=$body;$related[$s['slug']]=$ids;
}
$new_home=sy3_home_content();
if(getenv('SY3_IMPORT_DRY_RUN')==='1'){WP_CLI::success('Preflight: twelve services, one protected home; '.count($existing).' services already owned; no writes.');return;}
$receipt=[];
foreach($services as $n=>$s){
    if(isset($existing[$s['slug']])){$receipt[]=['slug'=>$s['slug'],'id'=>$existing[$s['slug']],'action'=>'preserved'];continue;}
    $id=wp_insert_post(wp_slash(['post_type'=>'sy3_service','post_status'=>'publish','post_author'=>$author->ID,'post_name'=>$s['slug'],'post_title'=>$s['title'],'post_excerpt'=>$s['excerpt'],'post_content'=>$bodies[$s['slug']],'menu_order'=>10+$n,'comment_status'=>'closed','ping_status'=>'closed','meta_input'=>['_sy3_seed'=>'service:'.$s['slug'],'_sy3_seed_hash'=>hash('sha256',$bodies[$s['slug']]),'_sy3_label'=>$s['label'],'_sy3_art'=>$s['art'],'_sy3_description'=>$s['excerpt'],'_sy3_search_query'=>$s['primary_query'],'_sy3_related'=>implode(',',$related[$s['slug']])]]),true);
    if(is_wp_error($id))WP_CLI::error($id->get_error_message());$receipt[]=['slug'=>$s['slug'],'id'=>$id,'action'=>'created'];
}
$result=wp_update_post(wp_slash(['ID'=>$home[0]->ID,'post_content'=>$new_home]),true);
if(is_wp_error($result))WP_CLI::error($result->get_error_message());
update_post_meta($home[0]->ID,'_sy3_seed_hash',hash('sha256',get_post_field('post_content',$home[0]->ID)));
WP_CLI::log(wp_json_encode($receipt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
WP_CLI::success('Main home updated and twelve services imported/preserved; articles, cases, schedule and personal site unchanged.');
