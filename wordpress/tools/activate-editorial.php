<?php
/** Reviewed queue activation; never rewrites article text or bypasses publisher checks. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (home_url('/') !== 'https://sy3.ru/' || wp_get_environment_type() !== 'production' || get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Exact production site required.');
$mode = getenv('SY3_QUEUE_MODE') ?: 'plan';
$directory = '/root/preview/sy3-wordpress-20261022/private/';
$path = $directory . 'finish-queue-plan-20261024.json';
$source = dirname(__DIR__, 2);
global $wpdb;
function sy3_queue_capture(int $id, bool $lock = false): array {
    global $wpdb;
    $suffix = $lock ? ' FOR UPDATE' : '';
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d" . $suffix, $id), ARRAY_A);
    $meta = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id" . $suffix, $id), ARRAY_A);
    if ($wpdb->last_error || !$post) throw new RuntimeException('Read failed.');
    return ['post' => $post, 'meta' => $meta];
}
function sy3_queue_fingerprint(array $state): string { return hash('sha256', wp_json_encode($state)); }
function sy3_queue_locks(): void {
    $pid = getenv('SY3_QUEUE_LOCK_PID');
    if (!ctype_digit((string)$pid)) throw new RuntimeException('Project lock owner required.');
    $rows = file('/proc/locks', FILE_IGNORE_NEW_LINES);
    foreach (['sy3.ru', 'suyazov-IT-business-partner.ru', 'sy3-wordpress-content'] as $name) {
        $inode = (string)fileinode('/var/lock/kimi-projects/' . $name . '.lock');
        $found = false;
        foreach ($rows as $row) if (preg_match('/ FLOCK\s+ADVISORY\s+WRITE\s+' . preg_quote($pid, '/') . '\s+\S+:' . preg_quote($inode, '/') . '\s/', $row)) $found = true;
        if (!$found) throw new RuntimeException('Project lock missing.');
    }
}
try {
    if ($mode === 'plan') {
        if (file_exists($path)) throw new RuntimeException('Existing queue plan preserved.');
        if (!get_option('sy3_editorial_paused')) throw new RuntimeException('Queue must remain paused during review.');
        $bodies = json_decode(file_get_contents(__DIR__ . '/../docs/editorial/BATCH_INDEX.json'), true, 512, JSON_THROW_ON_ERROR);
        $surfaces = json_decode(file_get_contents(__DIR__ . '/../docs/editorial/SURFACE_INDEX.json'), true, 512, JSON_THROW_ON_ERROR);
        $bodyMap = array_column($bodies['candidates'], null, 'id');
        $surfaceMap = array_column($surfaces['candidates'], null, 'id');
        $posts = get_posts(['post_type'=>'post', 'post_status'=>'draft', 'numberposts'=>-1, 'meta_query'=>[['key'=>'_sy3_editorial_batch', 'value'=>array_keys(sy3_editorial_series()), 'compare'=>'IN']], 'meta_key'=>'_sy3_editorial_at', 'orderby'=>['meta_value_num'=>'ASC','ID'=>'ASC']]);
        if (count($posts) !== 300) throw new RuntimeException('Unexpected draft inventory.');
        $start = time(); $offsets = []; $rows = [];
        foreach ($posts as $post) {
            $id = $post->ID; $body = $bodyMap[$id] ?? null; $surface = $surfaceMap[$id] ?? null;
            if (!$body || !$surface || $body['type'] !== 'post' || $body['slug'] !== $post->post_name || hash('sha256', $post->post_content) !== $body['candidate_hash']) throw new RuntimeException('Article differs from accepted editorial corpus.');
            $manifest = json_decode(file_get_contents($source . '/' . $surface['manifest']), true, 512, JSON_THROW_ON_ERROR);
            $candidate = null;
            foreach ($manifest['candidates'] as $item) if ($item['id'] === $id) $candidate = $item;
            if (!$candidate) throw new RuntimeException('Reviewed metadata source missing.');
            foreach (['post_title','post_excerpt'] as $field) if ($post->$field !== $candidate['after'][$field]) throw new RuntimeException('Metadata changed after editorial review.');
            if ((string)get_post_meta($id, '_sy3_description', true) !== $candidate['after']['_sy3_description']) throw new RuntimeException('Description conflict.');
            $quality = sy3_editorial_quality($post->post_content);
            $cover = get_post_thumbnail_id($id); $file = get_attached_file($cover);
            if (!$quality['complete'] || !$cover || !is_file($file) || !@getimagesize($file)) throw new RuntimeException('Article artifacts incomplete.');
            $batch = get_post_meta($id, '_sy3_editorial_batch', true); $series = sy3_editorial_series()[$batch];
            $number = $offsets[$batch] ?? 0; $offsets[$batch] = $number + 1;
            $rows[] = ['id'=>$id, 'slug'=>$post->post_name, 'batch'=>$batch, 'body_sha256'=>$body['candidate_hash'], 'metadata_source'=>$surface['manifest'], 'quality'=>$quality, 'cover_sha256'=>hash_file('sha256',$file), 'review_hash'=>sy3_editorial_review_hash($id), 'before_fingerprint'=>sy3_queue_fingerprint(sy3_queue_capture($id)), 'at'=>$start + ($batch === SY3_EDITORIAL_SEARCH_BATCH ? 300 : 0) + $number * $series['interval']];
        }
        $plan = ['schema'=>1, 'site'=>home_url('/'), 'head'=>trim(shell_exec('git -C ' . escapeshellarg($source) . ' rev-parse HEAD')), 'updater_sha256'=>hash_file('sha256',__FILE__), 'paused_before'=>true, 'rows'=>$rows, 'series_counts'=>$offsets, 'starts_at'=>$start, 'authority'=>'Owner requested completion and queue launch; exact corpus requires independent content review before approval hashes.'];
        $raw = wp_json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $handle = fopen($path, 'x'); if (!$handle) throw new RuntimeException('Cannot create plan.'); chmod($path,0600); fwrite($handle,$raw); fflush($handle); fsync($handle); fclose($handle);
        WP_CLI::line(wp_json_encode(['status'=>'PLANNED','articles'=>count($rows),'series'=>$offsets,'sha256'=>hash('sha256',$raw)])); return;
    }
    if ($mode !== 'apply' || getenv('SY3_QUEUE_ALLOW_ACTIVATION') !== '1') throw new RuntimeException('Exact activation authority required.');
    sy3_queue_locks();
    if (hash_file('sha256',$path) !== getenv('SY3_QUEUE_APPROVED_PLAN_SHA256')) throw new RuntimeException('Approved plan mismatch.');
    $plan = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $head = trim(shell_exec('git -C ' . escapeshellarg($source) . ' rev-parse HEAD'));
    if ($head !== $plan['head'] || hash_file('sha256',__FILE__) !== $plan['updater_sha256']) throw new RuntimeException('Reviewed source changed.');
    $review = json_decode(file_get_contents($directory . 'finish-queue-review-20261024.json'),true,512,JSON_THROW_ON_ERROR);
    if (($review['verdict']??'') !== 'PASS' || ($review['plan_sha256']??'') !== hash_file('sha256',$path) || ($review['exact_head']??'') !== $head) throw new RuntimeException('Independent exact corpus review required.');
    $key = 'sy3-editorial-' . md5(DB_NAME . ':' . $wpdb->prefix);
    if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$key)) !== 1) throw new RuntimeException('Publisher active.');
    $active = false;
    try {
        if (!get_option('sy3_editorial_paused')) throw new RuntimeException('Unexpected queue state.');
        $engines = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s)',$wpdb->posts,$wpdb->postmeta),ARRAY_A);
        if(count($engines)!==2 || array_filter($engines,fn($r)=>strcasecmp($r['Engine'],'InnoDB')!==0))throw new RuntimeException('Transactional tables required.');
        if($wpdb->query('START TRANSACTION')===false)throw new RuntimeException('Transaction failed.');$active=true;
        foreach($plan['rows'] as $r){
            $state=sy3_queue_capture($r['id'],true);
            if(sy3_queue_fingerprint($state)!==$r['before_fingerprint'])throw new RuntimeException('Concurrent article edits preserved.');
        }
        foreach($plan['rows'] as $r){
            foreach(['_sy3_editorial_reviewed_hash'=>$r['review_hash'],'_sy3_editorial_enabled'=>'1','_sy3_editorial_at'=>(string)$r['at']] as $k=>$v){
                $existing=$wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",$r['id'],$k));
                if(count($existing)>1)throw new RuntimeException('Duplicate owned metadata.');
                $ok=$existing?$wpdb->update($wpdb->postmeta,['meta_value'=>$v],['meta_id'=>(int)$existing[0]]):$wpdb->insert($wpdb->postmeta,['post_id'=>$r['id'],'meta_key'=>$k,'meta_value'=>$v]);
                if($ok===false)throw new RuntimeException('Metadata update failed.');
            }
        }
        if($wpdb->query('COMMIT')===false)throw new RuntimeException('Commit failed.');$active=false;
        foreach($plan['rows']as$r){clean_post_cache($r['id']);if(!hash_equals($r['review_hash'],sy3_editorial_review_hash($r['id'])))throw new RuntimeException('Review readback failed.');}
        update_option('sy3_editorial_paused',false,false);
        WP_CLI::line(wp_json_encode(['status'=>'ACTIVATED','approved_articles'=>count($plan['rows']),'series'=>$plan['series_counts'],'publisher_quality_gate_preserved'=>true]));
    }finally{if($active)$wpdb->query('ROLLBACK');$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$key));}
}catch(Throwable $e){WP_CLI::error($e->getMessage());}
