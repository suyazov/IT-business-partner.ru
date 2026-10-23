<?php
/** Plan, exercise-with-rollback or apply the exact accepted editorial transfer. No publisher calls. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
global $wpdb, $sy3TransferPrivate;
$sy3TransferPrivate = '/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/';
$root = dirname(__DIR__, 2);
$mode = getenv('SY3_EDITORIAL_TRANSFER_MODE') ?: 'plan';
$production = realpath(ABSPATH) === '/var/www/sy3-wordpress' && home_url('/') === 'https://sy3.ru/' && wp_get_environment_type() === 'production';
$preview = realpath(ABSPATH) === '/root/preview/sy3-wordpress-20261022/public' && home_url('/') === 'http://127.0.0.1:8186/' && defined('SY3_PREVIEW') && SY3_PREVIEW && wp_get_environment_type() !== 'production';
if (get_option('sy3_site_mode') !== 'expertise' || !in_array($mode, ['plan','apply','exercise'], true) || (!$production && !$preview)) WP_CLI::error('Exact site and transfer mode required.');
if (($mode === 'exercise') !== $preview || ($production && !is_ssl())) WP_CLI::error('Exercise is restricted to the isolated preview; production requires HTTPS.');

function sy3_transfer_json(string $path): array {
    global $sy3TransferPrivate;
    $real = realpath($path);
    if (!$real || dirname($real).'/' !== $sy3TransferPrivate || !is_file($real) || fileowner($real) !== 0 || (fileperms($real) & 0077)) throw new RuntimeException('Private root-owned input with mode0600 required.');
    return json_decode(file_get_contents($real), true, 512, JSON_THROW_ON_ERROR);
}
function sy3_transfer_hash(array $value): string {
    return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
function sy3_transfer_query(string $sql) {
    global $wpdb;
    $result = $wpdb->query($sql);
    if ($result === false) throw new RuntimeException('Database operation failed.');
    return $result;
}
function sy3_transfer_read(array $ids, bool $lock): array {
    global $wpdb;
    $list = implode(',', array_map('intval', $ids));
    $suffix = $lock ? ' FOR UPDATE' : '';
    $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN ($list) ORDER BY ID".$suffix, ARRAY_A);
    if ($wpdb->last_error || count($posts) !== count($ids)) throw new RuntimeException('Missing or unreadable target records.');
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ($list) ORDER BY meta_id".$suffix, ARRAY_A);
    if ($wpdb->last_error) throw new RuntimeException('Cannot read target metadata.');
    $records = [];
    foreach ($posts as $post) $records[(int)$post['ID']] = ['post'=>array_map('strval',$post),'meta'=>[]];
    foreach ($meta as $row) $records[(int)$row['post_id']]['meta'][] = array_map('strval',$row);
    return $records;
}
function sy3_transfer_fingerprints(array $records): array {
    $out = [];
    foreach ($records as $id=>$record) {
        // New description rows receive an auto-increment ID only at apply time.
        $meta=array_map(static fn($row)=>[$row['post_id'],$row['meta_key'],$row['meta_value']],$record['meta']);
        sort($meta);
        $out[$id] = ['post'=>sy3_transfer_hash($record['post']),'meta'=>sy3_transfer_hash($meta)];
    }
    return $out;
}
function sy3_transfer_state(array $actual, array $plan, string $fileHash, array $support): string {
    if ($actual===$plan['after'] && $fileHash===$support['after_sha256']) return 'AFTER';
    if ($actual===$plan['before'] && $fileHash===$support['before_sha256']) return 'BEFORE';
    if ($actual===$plan['before'] && $fileHash===$support['after_sha256']) return 'DB_BEFORE_FILE_AFTER';
    return 'CONFLICT';
}
function sy3_transfer_options(bool $lock, bool $requirePause=true): array {
    global $wpdb;
    $keys = ['home','siteurl','stylesheet','template','show_on_front','page_on_front','sy3_site_mode','sy3_editorial_paused','sy3_editorial_last_publication','sy3_search_last_publication'];
    $quoted = implode(',',array_fill(0,count($keys),'%s'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ($quoted) ORDER BY option_name".($lock?' FOR UPDATE':''), ...$keys), ARRAY_A);
    if ($wpdb->last_error) throw new RuntimeException('Cannot read protected options.');
    $map = array_column($rows,'option_value','option_name');
    if ($requirePause && ($map['sy3_editorial_paused']??'') !== '1') throw new RuntimeException('Existing publication pause must be active.');
    return $map;
}
function sy3_transfer_expected(array $records, array $intent, bool $baselineCheck): array {
    $expected = $records; $updates = []; $metaUpdates = []; $conflicts = [];
    $changeMeta = static function(int $id, string $key, string $after) use (&$expected,&$metaUpdates): void {
        $matches = array_keys(array_filter($expected[$id]['meta'],static fn($m)=>$m['meta_key']===$key));
        if (count($matches)>1) throw new RuntimeException('Duplicate target metadata row: '.$id.' '.$key);
        if (!$matches) {
            if ($after==='') return;
            $metaUpdates[]=['id'=>null,'post_id'=>$id,'key'=>$key,'before'=>null,'after'=>$after];
            $expected[$id]['meta'][]=['meta_id'=>'NEW','post_id'=>(string)$id,'meta_key'=>$key,'meta_value'=>$after];
            return;
        }
        $index = $matches[0]; $meta = $expected[$id]['meta'][$index];
        if ($meta['meta_value'] === $after) return;
        $metaUpdates[] = ['id'=>(int)$meta['meta_id'],'post_id'=>$id,'key'=>$key,'before'=>$meta['meta_value'],'after'=>$after];
        $expected[$id]['meta'][$index]['meta_value'] = $after;
    };
    foreach ($intent['posts'] as $item) {
        $id = $item['id']; $post = $records[$id]['post'];
        if ($post['post_type']!==$item['type'] || $post['post_name']!==$item['slug']) throw new RuntimeException('Identity conflict: '.$id);
        if ($baselineCheck) {
            if (hash('sha256',$post['post_content'])!==$item['baseline_body_hash']) $conflicts[] = ['id'=>$id,'field'=>'post_content'];
            foreach ($item['baseline_fields'] as $key=>$before) {
                $actual = $key==='_sy3_description' ? array_values(array_filter($records[$id]['meta'],static fn($m)=>$m['meta_key']===$key)) : null;
                if ($key==='_sy3_description') {
                    if (count($actual)>1 || (string)($actual[0]['meta_value']??'')!==$before) $conflicts[] = ['id'=>$id,'field'=>$key];
                } elseif ($post[$key]!==$before) $conflicts[] = ['id'=>$id,'field'=>$key];
            }
        }
        $changes = [];
        foreach ($item['changes'] as $key=>$after) {
            if (!is_string($after) || !in_array($key,['post_content','post_title','post_excerpt','_sy3_description'],true)) throw new RuntimeException('Unexpected write field.');
            if ($key==='_sy3_description') $changeMeta($id,$key,$after);
            elseif ($post[$key]!==$after) { $changes[$key]=$after; $expected[$id]['post'][$key]=$after; }
        }
        if ($changes) $updates[$id]=$changes;
        if ($item['type']==='post' && !sy3_editorial_quality($expected[$id]['post']['post_content'])['complete']) throw new RuntimeException('Article does not meet unchanged quality gate: '.$id);
    }
    foreach ($intent['covers'] as $cover) {
        $id = $cover['attachment_id'];
        if ($records[$id]['post']['post_type']!=='attachment') throw new RuntimeException('Attachment identity conflict.');
        if ($baselineCheck) {
            if (wp_get_attachment_url($id)!==$cover['url'] || $records[$id]['post']['post_excerpt']!==$cover['baseline_caption']) $conflicts[]=['id'=>$id,'field'=>'attachment_identity'];
            $asset = get_attached_file($id);
            if (!$asset || !is_file($asset) || !hash_equals($cover['asset_sha256'],hash_file('sha256',$asset))) $conflicts[]=['id'=>$id,'field'=>'asset_bytes'];
            $alt = array_values(array_filter($records[$id]['meta'],static fn($m)=>$m['meta_key']==='_wp_attachment_image_alt'));
            if (count($alt)>1 || (string)($alt[0]['meta_value']??'')!==$cover['baseline_alt']) $conflicts[]=['id'=>$id,'field'=>'alt'];
        }
        if ($cover['action']==='REWRITE') $changeMeta($id,'_wp_attachment_image_alt',$cover['candidate_alt']);
    }
    if ($conflicts) throw new RuntimeException('Conflicting baseline fields: '.json_encode($conflicts));
    return ['records'=>$expected,'posts'=>$updates,'meta'=>$metaUpdates];
}
function sy3_transfer_write(array &$changes): void {
    global $wpdb;
    foreach ($changes['posts'] as $id=>$fields) {
        if ($wpdb->update($wpdb->posts,$fields,['ID'=>$id],array_fill(0,count($fields),'%s'),['%d'])!==1) throw new RuntimeException('Post update failed: '.$id);
    }
    foreach ($changes['meta'] as $row) {
        if ($row['id']===null) {
            if ($wpdb->insert($wpdb->postmeta,['post_id'=>$row['post_id'],'meta_key'=>$row['key'],'meta_value'=>$row['after']],['%d','%s','%s'])!==1) throw new RuntimeException('Metadata insert failed.');
            foreach ($changes['records'][$row['post_id']]['meta'] as &$expected) {
                if ($expected['meta_id']==='NEW' && $expected['meta_key']===$row['key']) $expected['meta_id']=(string)$wpdb->insert_id;
            }
            unset($expected);
            continue;
        }
        if ($wpdb->update($wpdb->postmeta,['meta_value'=>$row['after']],['meta_id'=>$row['id'],'post_id'=>$row['post_id'],'meta_key'=>$row['key']],['%s'],['%d','%d','%s'])!==1) throw new RuntimeException('Metadata update failed.');
    }
}

$transaction=false; $fileChanged=false; $temporary=null; $advisory=false; $contentLock=null;
try {
    $intentPath = getenv('SY3_EDITORIAL_INTENT') ?: $sy3TransferPrivate.'transfer-intent.json';
    $intent = sy3_transfer_json($intentPath);
    if (($intent['schema']??null)!==1 || $intent['source_commit']!=='f9904de5763c57fe127ed00c8f713a82f69125ed' || count($intent['posts'])!==360 || count($intent['covers'])!==37) throw new RuntimeException('Unexpected accepted corpus.');
    $baseline = $sy3TransferPrivate.'runtime-baseline.json';
    if (hash_file('sha256',$baseline)!==$intent['baseline_sha256']) throw new RuntimeException('Original audit baseline unavailable.');
    $support = $intent['support'];
    $gitBlob = static function(string $commit) use ($root,$support): string {
        $command = 'git -C '.escapeshellarg($root).' show '.escapeshellarg($commit.':'.$support['source_path']);
        $bytes = shell_exec($command);
        if (!is_string($bytes)) throw new RuntimeException('Accepted Git blob unavailable.');
        return $bytes;
    };
    $oldSupport=$gitBlob($support['old_commit']); $newSupport=$gitBlob($intent['source_commit']);
    if (hash('sha256',$oldSupport)!==$support['before_sha256'] || hash('sha256',$newSupport)!==$support['after_sha256']) throw new RuntimeException('Support Git hash mismatch.');
    $compiled = json_decode($newSupport,true,512,JSON_THROW_ON_ERROR); $articles=0;
    foreach ($intent['posts'] as $item) if ($item['type']==='post') {
        $articles++;
        if (($compiled[$item['slug']]['body_hash']??'')!==hash('sha256',$item['changes']['post_content']??'')) throw new RuntimeException('Body/support mismatch.');
    }
    if ($articles!==311 || count($compiled)!==311) throw new RuntimeException('Unexpected support corpus.');
    $ids = array_merge(array_column($intent['posts'],'id'),array_column($intent['covers'],'attachment_id')); sort($ids);
    if (count($ids)!==397 || count(array_unique($ids))!==397) throw new RuntimeException('Duplicate target IDs.');
    if ($preview) {
        // Fixture IDs differ. Map unique type/slug and actual asset bytes in memory only;
        // never create records or treat this fixture mapping as production authority.
        $existing=$wpdb->get_results("SELECT ID,post_type,post_name FROM {$wpdb->posts}",ARRAY_A);
        $identities=[]; $assets=[];
        foreach ($existing as $row) {
            $identities[$row['post_type'].':'.$row['post_name']][]=(int)$row['ID'];
            if ($row['post_type']==='attachment') {
                $path=get_attached_file((int)$row['ID']);
                if ($path && is_file($path)) $assets[hash_file('sha256',$path)][]=(int)$row['ID'];
            }
        }
        $fixturePosts=[]; $fixtureCovers=[]; $fixtureMapping=[];
        foreach ($intent['posts'] as $item) {
            $matches=$identities[$item['type'].':'.$item['slug']]??[];
            if (count($matches)!==1) continue;
            $fixtureMapping[]=['source'=>$item['id'],'fixture'=>$matches[0]];
            $item['id']=$matches[0]; $fixturePosts[]=$item;
        }
        foreach ($intent['covers'] as $item) {
            $matches=$assets[$item['asset_sha256']]??[];
            if (count($matches)!==1) continue;
            $item['attachment_id']=$matches[0]; $fixtureCovers[]=$item;
        }
        $intent['posts']=$fixturePosts; $intent['covers']=$fixtureCovers;
        $ids=array_merge(array_column($intent['posts'],'id'),array_column($intent['covers'],'attachment_id')); sort($ids);
        if (count($intent['posts'])<350 || count(array_unique($ids))!==count($ids)) throw new RuntimeException('Insufficient unique fixture identities.');
    }
    $target = '/var/www/sy3-wordpress/wp-content/themes/sy3/inc/article-support-copy.json';
    if ($production && (is_link($target) || !is_file($target))) throw new RuntimeException('Expected regular production support file.');
    $options = sy3_transfer_options(false,$production); $optionsHash=sy3_transfer_hash($options);
    $records = sy3_transfer_read($ids,false);
    if ($mode==='plan') {
        $changes=sy3_transfer_expected($records,$intent,true);
        if (hash_file('sha256',$target)!==$support['before_sha256']) throw new RuntimeException('Live support file changed.');
        $plan=['schema'=>1,'intent_sha256'=>hash_file('sha256',$intentPath),'updater_sha256'=>hash_file('sha256',__FILE__),'source_commit'=>$intent['source_commit'],'options_hash'=>$optionsHash,
            'before'=>sy3_transfer_fingerprints($records),'after'=>sy3_transfer_fingerprints($changes['records']),
            'counts'=>['body_candidates'=>357,'articles'=>311,'target_records'=>397,'changed_post_rows'=>count($changes['posts']),'changed_meta_rows'=>count($changes['meta']),'support_files'=>1],
            'created_meta'=>array_values(array_map(static fn($row)=>['post_id'=>$row['post_id'],'key'=>$row['key']],array_filter($changes['meta'],static fn($row)=>$row['id']===null))),
            'conflicts'=>[],'status'=>'READY_FOR_REVIEW_AND_ROLLOUT','production_written'=>false];
        WP_CLI::line(json_encode($plan,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); return;
    }
    if ($mode==='apply') {
        if (getenv('SY3_ALLOW_PRODUCTION_IMPORT')!=='1') throw new RuntimeException('Separate exact production authority required.');
        $planPath=getenv('SY3_EDITORIAL_TRANSFER_PLAN')?:$sy3TransferPrivate.'transfer-plan.json';
        $plan=sy3_transfer_json($planPath);
        if (!hash_equals(hash_file('sha256',$planPath),(string)getenv('SY3_EDITORIAL_APPROVED_PLAN_SHA256')) || $plan['intent_sha256']!==hash_file('sha256',$intentPath) || $plan['updater_sha256']!==hash_file('sha256',__FILE__) || $plan['options_hash']!==$optionsHash) throw new RuntimeException('Approved plan/input/options changed.');
        $state=sy3_transfer_state(sy3_transfer_fingerprints($records),$plan,hash_file('sha256',$target),$support);
        if ($state==='CONFLICT') throw new RuntimeException('Plan concurrency conflict.');
        // The operator owns the two project locks. Their holder PID is bound to this invocation.
        $pid=(int)getenv('SY3_EDITORIAL_PROJECT_LOCK_PID');
        $fdPath='/proc/'.$pid.'/fd'; $held=[];
        foreach (glob($fdPath.'/*')?:[] as $fd) {
            $link=@readlink($fd);
            if (in_array($link,['/run/lock/kimi-projects/sy3.ru.lock','/run/lock/kimi-projects/suyazov-IT-business-partner.ru.lock','/var/lock/kimi-projects/sy3.ru.lock','/var/lock/kimi-projects/suyazov-IT-business-partner.ru.lock'],true)) {
                $info=@file_get_contents('/proc/'.$pid.'/fdinfo/'.basename($fd));
                if ($info && str_contains($info,'FLOCK') && str_contains($info,'WRITE')) $held[basename($link)]=true;
            }
        }
        if (count($held)!==2) throw new RuntimeException('Exact project locks are not held.');
        $contentLock=fopen('/var/lock/kimi-projects/sy3-wordpress-content.lock','c');
        if (!$contentLock || !flock($contentLock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Project content lock busy; no writes.');
        $lock='sy3-editorial-'.md5(DB_NAME.':'.$wpdb->prefix);
        $advisory=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))===1;
        if (!$advisory) throw new RuntimeException('Publisher lock busy; no writes.');
    }
    if (!($preview && get_class($wpdb)==='WP_SQLite_DB')) {
        $engines=$wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS WHERE Name IN (%s,%s,%s)',$wpdb->posts,$wpdb->postmeta,$wpdb->options),ARRAY_A);
        if ($wpdb->last_error || count($engines)!==3 || array_filter($engines,static fn($row)=>strcasecmp($row['Engine']??'','InnoDB')!==0)) throw new RuntimeException('Transactional tables required before any writing mode.');
    }
    $fault=$preview?(getenv('SY3_EDITORIAL_EXERCISE_FAULT')?:''):'';
    $faultFile=$sy3TransferPrivate.'transfer-fault-support.json';
    $faultPlanFile=$sy3TransferPrivate.'transfer-fault-plan.json';
    if ($fault==='recover') {
        $faultPlan=sy3_transfer_json($faultPlanFile);
        if (sy3_transfer_state(sy3_transfer_fingerprints($records),$faultPlan,hash_file('sha256',$faultFile),$support)!=='DB_BEFORE_FILE_AFTER') throw new RuntimeException('Fault recovery fixture did not retain exactly DB-before/file-after.');
    } elseif ($fault!=='' && $fault!=='crash') throw new RuntimeException('Unknown isolated fault exercise.');
    sy3_transfer_query('START TRANSACTION'); $transaction=true;
    $locked=sy3_transfer_read($ids,$production); $lockedOptions=sy3_transfer_options($production,$production);
    if (sy3_transfer_fingerprints($locked)!==sy3_transfer_fingerprints($records) || sy3_transfer_hash($lockedOptions)!==$optionsHash) throw new RuntimeException('State changed while locking.');
    if ($production) {
        $state=sy3_transfer_state(sy3_transfer_fingerprints($locked),$plan,hash_file('sha256',$target),$support);
        if ($state==='AFTER') {
            sy3_transfer_query('COMMIT'); $transaction=false;
            // A prior process may have died just after COMMIT but before cache invalidation.
            foreach ($ids as $id) clean_post_cache($id);
            if (sy3_transfer_fingerprints(sy3_transfer_read($ids,false))!==$plan['after'] || sy3_transfer_options(false)!==$options || hash_file('sha256',$target)!==$support['after_sha256']) throw new RuntimeException('Already-applied state changed; inspect without overwriting.');
            WP_CLI::line(json_encode(['status'=>'ALREADY_APPLIED','writes'=>0,'cleared_post_caches'=>count($ids)])); return;
        }
        if (!in_array($state,['BEFORE','DB_BEFORE_FILE_AFTER'],true)) throw new RuntimeException('Locked file/state conflict.');
        $stat=stat($target);
        // A previous process may have died after rename but before COMMIT. The immutable plan
        // identifies this exact state, so retry completes it under the same locks and authority.
        $fileChanged=$state==='DB_BEFORE_FILE_AFTER';
    }
    $changes=sy3_transfer_expected($locked,$intent,$production);
    sy3_transfer_write($changes);
    $saved=sy3_transfer_read($ids,$production);
    if ($saved!==$changes['records'] || sy3_transfer_options(false,$production)!==$options) throw new RuntimeException('Read-back or protected state mismatch.');
    if ($preview) {
        if ($fault==='crash') {
            if (file_exists($faultFile) || file_exists($faultPlanFile)) throw new RuntimeException('Do not overwrite a fault receipt.');
            $fixturePlan=['before'=>sy3_transfer_fingerprints($records),'after'=>sy3_transfer_fingerprints($changes['records'])];
            if (file_put_contents($faultPlanFile,json_encode($fixturePlan))===false || !chmod($faultPlanFile,0600)) throw new RuntimeException('Cannot write fault receipt.');
            $stage=$faultFile.'.stage';
            if (file_put_contents($stage,$newSupport)!==strlen($newSupport) || !chmod($stage,0600) || !rename($stage,$faultFile)) throw new RuntimeException('Cannot exercise file handoff.');
            // Deliberately bypass catch/finally after file activation; closing SQLite rolls back SQL.
            exit(79);
        }
        sy3_transfer_query('ROLLBACK'); $transaction=false;
        if (sy3_transfer_read($ids,false)!==$records) throw new RuntimeException('Exercise rollback mismatch.');
        if ($fault==='recover' && (sy3_transfer_fingerprints($saved)!==$faultPlan['after'] || hash_file('sha256',$faultFile)!==$support['after_sha256'])) throw new RuntimeException('Mixed-state recovery exercise mismatch.');
        WP_CLI::line(json_encode(['status'=>$fault==='recover'?'MIXED_RECOVERY_EXERCISE_ROLLED_BACK':'EXERCISE_ROLLED_BACK','post_rows'=>count($changes['posts']),'meta_rows'=>count($changes['meta']),'fixture_objects'=>count($intent['posts']),'fixture_articles'=>count(array_filter($intent['posts'],static fn($p)=>$p['type']==='post')),'fixture_covers'=>count($intent['covers']),'mapping'=>$fixtureMapping,'all_311_source_articles_checked'=>true,'protected_fields_preserved'=>true,'persistent_writes'=>0])); return;
    }
    $fileHash=hash_file('sha256',$target);
    if (sy3_transfer_fingerprints($saved)!==$plan['after'] || !in_array($fileHash,[$support['before_sha256'],$support['after_sha256']],true)) throw new RuntimeException('Final plan/file concurrency mismatch.');
    if ($fileHash===$support['before_sha256']) {
    $temporary=dirname($target).'/.sy3-editorial-transfer-'.getmypid();
    $stream=fopen($temporary,'x');
    if (!$stream || fwrite($stream,$newSupport)!==strlen($newSupport) || !fflush($stream)) throw new RuntimeException('Cannot stage support copy.');
    fclose($stream);
    if (!chmod($temporary,$stat['mode']&0777) || !chown($temporary,$stat['uid']) || !chgrp($temporary,$stat['gid']) || hash_file('sha256',$temporary)!==$support['after_sha256']) throw new RuntimeException('Staged support verification failed.');
    if (!rename($temporary,$target)) throw new RuntimeException('Cannot activate support copy.');
    $temporary=null; $fileChanged=true;
    }
    sy3_transfer_query('COMMIT'); $transaction=false;
    foreach ($ids as $id) clean_post_cache($id);
    if (sy3_transfer_fingerprints(sy3_transfer_read($ids,false))!==$plan['after'] || sy3_transfer_options(false)!==$options || hash_file('sha256',$target)!==$support['after_sha256']) throw new RuntimeException('Post-commit verification failed; preserve receipt and inspect.');
    WP_CLI::line(json_encode(['status'=>'APPLIED_VERIFIED','plan_sha256'=>hash_file('sha256',$planPath),'counts'=>$plan['counts'],'protected_fields_preserved'=>true,'queues_unchanged'=>true,'publisher_approval_created'=>false,'backups_created'=>false]));
} catch (Throwable $error) {
    if ($transaction) {
        $wpdb->query('ROLLBACK'); $transaction=false;
        if ($fileChanged && isset($target,$support,$oldSupport) && hash_file('sha256',$target)===$support['after_sha256']) {
            $recovery=dirname($target).'/.sy3-editorial-recovery-'.getmypid();
            $ok=file_put_contents($recovery,$oldSupport)===strlen($oldSupport) && chmod($recovery,$stat['mode']&0777) && chown($recovery,$stat['uid']) && chgrp($recovery,$stat['gid']) && rename($recovery,$target);
            if (!$ok) $error=new RuntimeException('Database rollback attempted; support recovery needs inspection.');
        }
    }
    if ($temporary && is_file($temporary)) {
        $cleanupLock=fopen('/var/lock/kimi-projects/bridge-sy9-storage-mutation.lock','c');
        if ($cleanupLock && flock($cleanupLock,LOCK_EX|LOCK_NB)) { unlink($temporary); flock($cleanupLock,LOCK_UN); }
        else $error=new RuntimeException($error->getMessage().' Owned staging file retained: '.$temporary.'; cleanup review due '.gmdate('c',time()+DAY_IN_SECONDS));
        if (is_resource($cleanupLock)) fclose($cleanupLock);
    }
    WP_CLI::error($error->getMessage());
} finally {
    if ($advisory) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
    if (is_resource($contentLock)) { flock($contentLock,LOCK_UN); fclose($contentLock); }
}
