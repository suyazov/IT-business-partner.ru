<?php
/** Append reviewed longform material to the 110 owned articles; preserve every existing block and status. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
if(wp_get_environment_type()==='production'&&getenv('SY3_ALLOW_PRODUCTION_IMPORT')!=='1')WP_CLI::error('Production authority required.');
if(get_option('sy3_site_mode')!=='expertise')WP_CLI::error('Only sy3.ru is in scope.');
if(!function_exists('sy3_editorial_review_hash'))WP_CLI::error('Activate the reviewed editorial plugin before any import.');
require_once __DIR__.'/search-content-lib.php';
$base=__DIR__.'/../longform/';
$records=json_decode(file_get_contents($base.'manifest.json'),true,512,JSON_THROW_ON_ERROR);
$published=array_column(json_decode(file_get_contents(__DIR__.'/../content/articles.json'),true),'slug');
$expected=array_merge(array_map(fn($n)=>'article-'.$n,range(1,100)),array_map(fn($s)=>'published-'.$s,$published));
$actual=array_column($records,'key');sort($expected);sort($actual);
$partial=getenv('SY3_LONGFORM_PARTIAL_IMPORT')==='1';
if(!$actual||count($actual)!==count(array_unique($actual))||array_diff($actual,$expected)||(!$partial&&$actual!==$expected))WP_CLI::error('Expected the 110 owned articles, or an explicitly authorized nonempty subset.');
$plans=[];
foreach($records as $r){
    $key=$r['key'];$numeric=$r['kind']==='expanded';
    if(!preg_match('/\A[a-z0-9-]+\z/',$key)||$r['file']!==$key.'.md')WP_CLI::error('Unsafe longform key.');
    $query=['post_type'=>'post','post_status'=>'any','numberposts'=>2];
    if($numeric){$query['meta_key']='_sy3_editorial_key';$query['meta_value']=sprintf('%03d',$r['id']);}
    else{$query['meta_key']='_sy3_seed';$query['meta_value']='post:'.$r['slug'];}
    $found=get_posts($query);
    if(count($found)!==1||$found[0]->post_name!==$r['slug']||$found[0]->post_title!==$r['title'])WP_CLI::error('Owned article identity mismatch: '.$key);
    $post=$found[0];$hash_meta=$numeric?'_sy3_editorial_seed_hash':'_sy3_seed_hash';
    if($numeric&&get_post_meta($post->ID,'_sy3_editorial_batch',true)!==SY3_EDITORIAL_BATCH)WP_CLI::error('Wrong original series: '.$key);
    $revision=hash('sha256',wp_json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).file_get_contents($base.'articles/'.$r['file']));
    $prior=get_post_meta($post->ID,'_sy3_longform_revision',true);
    if($prior){if(!hash_equals($prior,$revision))WP_CLI::error('A different longform revision already exists: '.$key);$plans[]=['id'=>$post->ID,'key'=>$key,'action'=>'preserved'];continue;}
    if(!hash_equals((string)get_post_meta($post->ID,$hash_meta,true),hash('sha256',$post->post_content)))WP_CLI::error('Manual edits preserved: '.$key);
    $raw=file_get_contents($base.'articles/'.$r['file']);
    if(!str_starts_with($raw,'# '.$r['title']."\n"))WP_CLI::error('Title mismatch: '.$key);
    $addition=trim(substr($raw,strpos($raw,"\n")+1));
    if(!preg_match('/^\s*\|(?:\s*:?-+:?\s*\|)+\s*$/m',$addition)||count($r['infographic']['steps']??[])<5)WP_CLI::error('Missing table or infographic: '.$key);
    if(preg_match('/(?<!\p{L})сказк\p{L}*|skazka|parkops|BEGIN[^\n]*PRIVATE KEY/iu',$raw))WP_CLI::error('Restricted content: '.$key);
    foreach(['.svg','.csv'] as $suffix){$asset=__DIR__.'/../theme/assets/infographics/'.$key.$suffix;if(!is_file($asset)||!hash_equals($r['artifacts'][$suffix]??'',hash_file('sha256',$asset)))WP_CLI::error('Missing/changed artifact: '.$key);}
    $added=parse_blocks(sy3_search_blocks($addition));
    // Count substantive text before adding the diagram, source list or downloads.
    $substantive=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags($post->post_content.serialize_blocks($added)),ENT_QUOTES|ENT_HTML5,'UTF-8')));
    if(mb_strlen($substantive)<10000)WP_CLI::error('Fewer than 10000 visible characters: '.$key);
    array_splice($added,max(1,(int)floor(count($added)/3)),0,parse_blocks(sy3_process_artifact($r['infographic'],$key)));
    $links=[];foreach($r['sources'] as $s){if(!filter_var($s['url'],FILTER_VALIDATE_URL)||wp_parse_url($s['url'],PHP_URL_SCHEME)!=='https')WP_CLI::error('Invalid source: '.$key);$links[]='<a href="'.esc_url($s['url']).'">'.esc_html($s['title']).'</a>';}
    if(!$links)WP_CLI::error('Primary sources required: '.$key);
    $added=array_merge($added,parse_blocks(sy3_g(sy3_h('Документация и источники',3).sy3_list($links),'editorial-sources')));
    $blocks=parse_blocks($post->post_content);$insert=count($blocks);
    foreach($blocks as $index=>$block){if(($block['attrs']['className']??'')==='editorial-contact'||(($block['blockName']??'')==='core/heading'&&str_contains(wp_strip_all_tags($block['innerHTML']),'Посмотреть работу в деталях'))){$insert=$index;break;}}
    array_splice($blocks,$insert,0,$added);$content=serialize_blocks($blocks);
    // Preserve the exact serialized original blocks, even if the editor changes its canonical format.
    $restored=$blocks;array_splice($restored,$insert,count($added));
    if(serialize_blocks($restored)!==$post->post_content)WP_CLI::error('Original block round-trip differs: '.$key);
    $plans[]=['id'=>$post->ID,'key'=>$key,'action'=>'expanded','content'=>$content,'revision'=>$revision,'hash_meta'=>$hash_meta,'before'=>hash('sha256',$post->post_content),'visible_characters'=>mb_strlen($substantive)];
}
if(getenv('SY3_IMPORT_DRY_RUN')==='1'){WP_CLI::success('Preflight: '.count($plans).' owned articles; '.count(array_filter($plans,fn($p)=>$p['action']==='expanded')).' additions; original blocks/statuses/metadata preserved; no writes.');return;}
// Recheck every source hash immediately before the first write.
foreach($plans as $p)if($p['action']==='expanded'&&!hash_equals($p['before'],hash('sha256',get_post_field('post_content',$p['id']))))WP_CLI::error('Concurrent edit preserved.');
$receipt=[];
foreach($plans as $p){
    if($p['action']==='expanded'){
        $result=wp_update_post(wp_slash(['ID'=>$p['id'],'post_content'=>$p['content']]),true);if(is_wp_error($result))WP_CLI::error($result->get_error_message());
        $hash=hash('sha256',get_post_field('post_content',$p['id']));update_post_meta($p['id'],$p['hash_meta'],$hash);update_post_meta($p['id'],'_sy3_longform_revision',$p['revision']);update_post_meta($p['id'],'_sy3_longform_characters',$p['visible_characters']);
        update_post_meta($p['id'],'_sy3_editorial_reviewed_hash',sy3_editorial_review_hash($p['id']));
    }
    $receipt[]=['id'=>$p['id'],'key'=>$p['key'],'action'=>$p['action']];
}
WP_CLI::log(wp_json_encode($receipt,JSON_UNESCAPED_UNICODE));WP_CLI::success(count($plans).' owned articles expanded/preserved; original statuses, schedules, covers and related links unchanged.');
