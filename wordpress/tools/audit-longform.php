<?php
/** Read-only inventory: substantive characters, artifacts and exact reviewed versions. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
if(get_option('sy3_site_mode')!=='expertise'||!function_exists('sy3_editorial_quality'))WP_CLI::error('Expected main site and reviewed editorial plugin.');
$slugs=array_column(json_decode(file_get_contents(__DIR__.'/../content/articles.json'),true),'slug');
$posts=get_posts(['post_type'=>'post','post_status'=>['draft','publish','pending','private','future'],'numberposts'=>-1,'meta_query'=>['relation'=>'OR',['key'=>'_sy3_editorial_batch','value'=>array_keys(sy3_editorial_series()),'compare'=>'IN'],['key'=>'_sy3_seed','value'=>array_map(fn($s)=>'post:'.$s,$slugs),'compare'=>'IN']]]);
$rows=[];$ready=0;$minimum=null;$counts=[];
foreach($posts as $post){
    $quality=sy3_editorial_quality($post->post_content);$reviewed=hash_equals((string)get_post_meta($post->ID,'_sy3_editorial_reviewed_hash',true),sy3_editorial_review_hash($post->ID));
    $cover=(bool)get_post_thumbnail_id($post->ID);$ok=$quality['complete']&&$reviewed&&$cover;
    if($ok){$ready++;$minimum=$minimum===null?$quality['characters']:min($minimum,$quality['characters']);}
    $counts[$post->post_status]=($counts[$post->post_status]??0)+1;
    $rows[]=['id'=>$post->ID,'slug'=>$post->post_name,'status'=>$post->post_status,...$quality,'reviewed'=>$reviewed,'cover'=>$cover,'ready'=>$ok];
}
$summary=['total'=>count($rows),'ready'=>$ready,'remaining'=>count($rows)-$ready,'ready_minimum_characters'=>$minimum,'statuses'=>$counts,'paused'=>(bool)get_option('sy3_editorial_paused')];
if($file=getenv('SY3_AUDIT_FILE')){if(!str_starts_with($file,'/root/preview/sy3-wordpress-20261022/private/'))WP_CLI::error('Audit file must be in the task evidence directory.');file_put_contents($file,wp_json_encode(['summary'=>$summary,'articles'=>$rows],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));}
WP_CLI::log(wp_json_encode($summary,JSON_UNESCAPED_UNICODE));
$expected=(int)(getenv('SY3_LONGFORM_EXPECTED_TOTAL')?:311);
if(getenv('SY3_LONGFORM_REQUIRE_ALL')==='1'&&(count($rows)!==$expected||$ready!==$expected))WP_CLI::error('All '.$expected.' articles must pass; incomplete content is not counted as delivered.');
