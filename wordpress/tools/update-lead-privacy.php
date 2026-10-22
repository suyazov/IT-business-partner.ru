<?php
/** Exact owned-page update accompanying the new form, preserving all article/calendar data. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
if(get_option('sy3_site_mode')!=='expertise')WP_CLI::error('Main site required.');
if(wp_get_environment_type()==='production'&&getenv('SY3_ALLOW_PRODUCTION_IMPORT')!=='1')WP_CLI::error('Production authority required.');
require_once __DIR__.'/../content/blocks.php';require_once __DIR__.'/../content/lead-privacy.php';
$definition=sy3_lead_privacy_definition();$page=get_page_by_path('privacy',OBJECT,'page');
if(!$page||$page->post_status!=='publish'||get_post_meta($page->ID,'_sy3_seed',true)!=='page:privacy')WP_CLI::error('Expected owned privacy page missing.');
$old='714e683a4cf7564b98b4408dc8895f61045fbc6ea03b5d13852bd937d1ebf021';$new=hash('sha256',$definition['content']);$actual=hash('sha256',$page->post_content);
if(!in_array($actual,[$old,$new],true)||get_post_meta($page->ID,'_sy3_seed_hash',true)!==$actual)WP_CLI::error('Privacy page changed: preserve editor work and reconcile before publication.');
$expected_title=$actual===$old?'О данных на сайте':$definition['title'];
$expected_excerpt=$actual===$old?'Какие способы обращения предусмотрены в этой версии сайта.':$definition['excerpt'];
if($page->post_title!==$expected_title||$page->post_excerpt!==$expected_excerpt)WP_CLI::error('Privacy title/excerpt changed: preserve editor work before publication.');
if(getenv('SY3_IMPORT_DRY_RUN')==='1'){WP_CLI::success('Exact privacy preflight passed; no writes.');return;}
if($actual===$new){WP_CLI::success('Privacy already current; no writes.');return;}
$result=wp_update_post(wp_slash(['ID'=>$page->ID,'post_title'=>$definition['title'],'post_excerpt'=>$definition['excerpt'],'post_content'=>$definition['content'],'post_date'=>$page->post_date,'post_date_gmt'=>$page->post_date_gmt,'edit_date'=>true]),true);
if(is_wp_error($result))WP_CLI::error('Privacy update failed.');
update_post_meta($page->ID,'_sy3_seed_hash',$new);WP_CLI::success('Updated only the owned privacy page; article/calendar data untouched.');
