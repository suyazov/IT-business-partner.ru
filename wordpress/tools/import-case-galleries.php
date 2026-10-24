<?php
/** Native editable gallery blocks; preserve case copy, covers, dates and all article queues. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (home_url('/') !== 'https://sy3.ru/' || get_option('sy3_site_mode') !== 'expertise' || wp_get_environment_type() !== 'production') WP_CLI::error('Exact main site required.');
$source = dirname(__DIR__, 2);
$manifestFile = __DIR__ . '/../content/case-galleries.json';
$manifestHash = hash_file('sha256', $manifestFile);
$manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
$batches = json_decode(file_get_contents(__DIR__ . '/../docs/editorial/BATCH_INDEX.json'), true, 512, JSON_THROW_ON_ERROR);
$bodies = array_column($batches['candidates'], null, 'slug');
$apply = getenv('SY3_GALLERIES_APPLY') === '1';
global $wpdb;
require_once __DIR__.'/case-gallery-blocks.php';
try {
    $cases = []; $seen = []; $total = 0;
    foreach ($manifest['cases'] as $item) {
        $slug = $item['slug'];
        if (!preg_match('/\A[a-z0-9-]+\z/', $slug) || isset($seen[$slug])) throw new RuntimeException('Invalid or duplicate case.');
        $seen[$slug] = true;
        $post = get_page_by_path($slug, OBJECT, 'sy3_case');
        if (!$post || $post->post_status !== 'publish' || !$item['images'] || count($item['images']) > 3) throw new RuntimeException('Case or images unavailable.');
        $done = get_post_meta($post->ID, '_sy3_case_gallery_manifest', true) === $manifestHash
            && get_post_meta($post->ID, '_sy3_case_gallery_body', true) === hash('sha256', $post->post_content);
        if (!$done && (metadata_exists('post',$post->ID,'_sy3_case_gallery_manifest') || metadata_exists('post',$post->ID,'_sy3_case_gallery_body'))) throw new RuntimeException('Existing gallery edits require reconciliation.');
        if (!$done && (!isset($bodies[$slug]) || hash('sha256',$post->post_content) !== $bodies[$slug]['candidate_hash'])) throw new RuntimeException('Case body changed; preserve editor work.');
        foreach ($item['images'] as $image) {
            if (!preg_match('~\A' . preg_quote($slug,'~') . '/source-[0-9]{2}\.webp\z~', $image['file']) || !preg_match('/\A[a-f0-9]{64}\z/', $image['sha256']) || trim($image['alt']) === '' || ($image['visual_review'] ?? '') !== 'approved') throw new RuntimeException('Reviewed image definition required.');
            $path = __DIR__ . '/../content/media/case-galleries/' . $image['file'];
            if (is_link($path) || !is_file($path) || hash_file('sha256',$path) !== $image['sha256'] || (@getimagesize($path)['mime'] ?? '') !== 'image/webp') throw new RuntimeException('Image bytes conflict.');
            ++$total;
        }
        $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id",$post->ID),ARRAY_A);
        $cases[] = ['post'=>$post, 'item'=>$item, 'done'=>$done, 'meta'=>$meta];
    }
    if (!$apply) { WP_CLI::line(wp_json_encode(['status'=>'DRY_RUN_PASS','cases'=>count($cases),'images'=>$total,'already_current'=>count(array_filter($cases,fn($c)=>$c['done'])),'manifest_sha256'=>$manifestHash])); return; }
    $head = trim(shell_exec('git -C ' . escapeshellarg($source) . ' rev-parse HEAD'));
    $reviewFile='/root/preview/sy3-wordpress-20261022/private/finish-galleries-review-20261024.json';
    if(is_link($reviewFile)||!is_file($reviewFile)||fileowner($reviewFile)!==0||(fileperms($reviewFile)&0777)!==0600)throw new RuntimeException('Root-only independent receipt required.');
    $review = json_decode(file_get_contents($reviewFile),true,512,JSON_THROW_ON_ERROR);
    if (($review['verdict']??'') !== 'PASS' || ($review['exact_head']??'') !== $head || ($review['manifest_sha256']??'') !== $manifestHash || trim(shell_exec('git -C ' . escapeshellarg($source) . ' status --porcelain')) !== '') throw new RuntimeException('Independent clean exact-head review required.');
    $pid = getenv('SY3_GALLERIES_LOCK_PID'); $locks = file('/proc/locks',FILE_IGNORE_NEW_LINES);
    if (!ctype_digit((string)$pid)) throw new RuntimeException('Project lock owner required.');
    foreach (['sy3.ru','suyazov-IT-business-partner.ru','sy3-wordpress-content'] as $name) {
        $inode = (string)fileinode('/var/lock/kimi-projects/'.$name.'.lock'); $found=false;
        foreach ($locks as $line) if (preg_match('/ FLOCK\s+ADVISORY\s+WRITE\s+'.preg_quote($pid,'/').'\s+\S+:'.preg_quote($inode,'/').'\s/',$line)) $found=true;
        if (!$found) throw new RuntimeException('Shared project lock missing.');
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $engines=$wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)',$wpdb->posts,$wpdb->postmeta),ARRAY_A);
    if(count($engines)!==2 || array_filter($engines,fn($r)=>strcasecmp($r['Engine'],'InnoDB')!==0))throw new RuntimeException('Transactional tables required.');
    $uploads=wp_get_upload_dir(); if ($uploads['error'] || $uploads['basedir'] !== '/var/www/sy3-wordpress/wp-content/uploads' || $uploads['baseurl'] !== 'https://sy3.ru/wp-content/uploads') throw new RuntimeException('Exact uploads root required.');
    $owner=posix_getpwnam('php-sy3'); if(!$owner)throw new RuntimeException('Media owner unresolved.');
    $changed=0;$attachments=0;
    foreach($cases as $case){
        $post=$case['post'];$item=$case['item'];$images=[];
        foreach($item['images'] as $image){
            $relative='sy3-cases/'.$item['slug'].'/'.substr($image['sha256'],0,20).'.webp';
            $dest=$uploads['basedir'].'/'.$relative;$url=$uploads['baseurl'].'/'.$relative;
            foreach([$uploads['basedir'].'/sy3-cases',dirname($dest)]as$directory){
                if(is_link($directory))throw new RuntimeException('Unexpected media symlink.');
                if(!is_dir($directory)){if($case['done']||!mkdir($directory,0755)||!chown($directory,$owner['uid'])||!chgrp($directory,$owner['gid']))throw new RuntimeException('Cannot create case media directory.');}
            }
            if(is_link($dest))throw new RuntimeException('Unexpected media symlink.');
            if(is_file($dest)){if(hash_file('sha256',$dest)!==$image['sha256'])throw new RuntimeException('Existing media conflict.');}
            else{
                if($case['done'])throw new RuntimeException('Previously imported media missing.');
                $data=file_get_contents(__DIR__.'/../content/media/case-galleries/'.$image['file']);
                $f=fopen($dest,'x');if(!$f)throw new RuntimeException('Cannot create media.');
                try{$offset=0;while($offset<strlen($data)){$n=fwrite($f,substr($data,$offset));if($n===false||$n===0)throw new RuntimeException('Incomplete media write.');$offset+=$n;}if(!fflush($f)||!fsync($f))throw new RuntimeException('Media sync failed.');}finally{fclose($f);}
                if(!chmod($dest,0644)||!chown($dest,$owner['uid'])||!chgrp($dest,$owner['gid']))throw new RuntimeException('Media permissions failed.');
            }
            if(hash_file('sha256',$dest)!==$image['sha256'])throw new RuntimeException('Media readback failed.');
            $ids=$wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s",$relative));
            if(count($ids)>1)throw new RuntimeException('Ambiguous existing attachment.');
            if($ids){$attachment=(int)$ids[0];$p=get_post($attachment);if(!$p||$p->post_type!=='attachment'||(int)$p->post_parent!==$post->ID||$p->post_mime_type!=='image/webp')throw new RuntimeException('Attachment ownership conflict.');}
            else{if($case['done'])throw new RuntimeException('Previously imported attachment missing.');$attachment=wp_insert_attachment(['post_title'=>$image['alt'],'post_mime_type'=>'image/webp','post_status'=>'inherit','post_parent'=>$post->ID,'post_author'=>get_current_user_id(),'guid'=>$url],$dest,$post->ID,true);if(is_wp_error($attachment))throw new RuntimeException('Media registration failed.');++$attachments;}
            $metadata=wp_get_attachment_metadata($attachment);
            if(!$metadata){if($case['done'])throw new RuntimeException('Previously imported metadata missing.');$metadata=wp_generate_attachment_metadata($attachment,$dest);if(!is_array($metadata)||empty($metadata['width']))throw new RuntimeException('Metadata generation failed.');wp_update_attachment_metadata($attachment,$metadata);$stored=wp_get_attachment_metadata($attachment);if($stored!==$metadata)throw new RuntimeException('Metadata save readback failed.');$metadata=$stored;}
            if(($metadata['file']??'')!==$relative||(int)($metadata['width']??0)!==(int)$image['width']||(int)($metadata['height']??0)!==(int)$image['height'])throw new RuntimeException('Attachment metadata conflicts.');
            foreach($metadata['sizes']??[]as$size){$name=$size['file'];if(basename($name)!==$name)throw new RuntimeException('Generated media path conflict.');$file=dirname($dest).'/'.$name;if(is_link($file)||!is_file($file))throw new RuntimeException('Generated media missing or symlink.');if(!$case['done']&&(!chown($file,$owner['uid'])||!chgrp($file,$owner['gid'])))throw new RuntimeException('Derivative owner failed.');}
            $alt=get_post_meta($attachment,'_wp_attachment_image_alt',true);if(($case['done']||$alt!=='')&&$alt!==$image['alt'])throw new RuntimeException('Edited alt preserved.');if(!$case['done'])update_post_meta($attachment,'_wp_attachment_image_alt',$image['alt']);
            if(get_post_meta($attachment,'_wp_attachment_image_alt',true)!==$image['alt'])throw new RuntimeException('Alt save readback failed.');
            $images[]=sy3_case_gallery_image($attachment,$url,$image['alt'],$image['caption']);
        }
        if($case['done'])continue;
        $gallery=sy3_case_gallery_block($images);
        $before=$post->post_content;
        $after=sy3_case_gallery_insert($before,$gallery);
        $transaction=false;
        try{
            if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('Cannot start case transaction.');$transaction=true;
            $saved=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$post->ID),ARRAY_A);
            if(!$saved||$saved['post_type']!=='sy3_case'||$saved['post_status']!=='publish'||$saved['post_content']!==$before)throw new RuntimeException('Concurrent case edit preserved.');
            $savedMeta=$wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id FOR UPDATE",$post->ID),ARRAY_A);
            if($savedMeta!==$case['meta'])throw new RuntimeException('Concurrent case metadata edit preserved.');
            if($wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_content=%s WHERE ID=%d AND BINARY post_content=%s",$after,$post->ID,$before))!==1)throw new RuntimeException('Case CAS failed.');
            foreach(['_sy3_case_gallery_manifest'=>$manifestHash,'_sy3_case_gallery_body'=>hash('sha256',$after)]as$key=>$value){
                if($wpdb->insert($wpdb->postmeta,['post_id'=>$post->ID,'meta_key'=>$key,'meta_value'=>$value])===false)throw new RuntimeException('Gallery marker failed.');
            }
            $read=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$post->ID),ARRAY_A);if(!$read || $read['post_content']!==$after)throw new RuntimeException('Case body readback failed.');$old=$saved;unset($read['post_content'],$old['post_content']);if($read!==$old)throw new RuntimeException('Protected case fields changed.');
            $readMeta=$wpdb->get_results($wpdb->prepare("SELECT meta_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key NOT IN (%s,%s) ORDER BY meta_id",$post->ID,'_sy3_case_gallery_manifest','_sy3_case_gallery_body'),ARRAY_A);
            if($readMeta!==$savedMeta)throw new RuntimeException('Protected case metadata changed.');
            if($wpdb->query('COMMIT')===false)throw new RuntimeException('Case commit failed.');$transaction=false;clean_post_cache($post->ID);++$changed;
        }finally{if($transaction)$wpdb->query('ROLLBACK');}
    }
    WP_CLI::line(wp_json_encode(['status'=>'APPLIED_VERIFIED','cases_changed'=>$changed,'images'=>$total,'attachments_created'=>$attachments,'article_queue_untouched'=>true]));
}catch(Throwable$e){WP_CLI::error($e->getMessage());}
