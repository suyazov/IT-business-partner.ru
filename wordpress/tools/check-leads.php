<?php
/**
 * Preview-only integration checks. Run with the already activated real plugin:
 * SY3_LEADS_STORAGE=/root/preview/sy3-wordpress-20261022/private/leads-preview \
 * wp --allow-root --path=/root/preview/sy3-wordpress-20261022/public eval-file /absolute/path/check-leads.php
 * No transport worker, HTTP request, email, database fixture or option update is used.
 */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') {
    throw new RuntimeException('This check requires preview WP-CLI.');
}
$preview = '/root/preview/sy3-wordpress-20261022';
$base = $preview.'/private/leads-preview';
$originalEnv = getenv('SY3_LEADS_STORAGE');
$home = wp_parse_url(home_url('/'));
if (wp_get_environment_type() === 'production'
    || realpath(ABSPATH) !== realpath($preview.'/public')
    || ($home['scheme'] ?? '') !== 'http'
    || !in_array($home['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || ($home['port'] ?? null) !== 8186
    || $originalEnv === false || realpath($originalEnv) !== realpath($base)
    || !is_dir($base) || is_link($base)
    || str_starts_with(realpath($base).'/', realpath(ABSPATH).'/')) {
    throw new RuntimeException('Refusing a non-preview environment or storage.');
}
foreach (['sy3_leads_accept','sy3_leads_issue','sy3_leads_verify','sy3_leads_storage'] as $function) {
    if (!function_exists($function)) throw new RuntimeException('Activate the preview leads plugin first.');
}
$plugin = realpath(ABSPATH.'wp-content/plugins/sy3-leads.php');
if (!$plugin || realpath((new ReflectionFunction('sy3_leads_accept'))->getFileName()) !== $plugin) {
    throw new RuntimeException('Unexpected plugin implementation.');
}

$marker = bin2hex(random_bytes(24));
$fixture = $base.'/.check-leads-'.bin2hex(random_bytes(10));
if (!mkdir($fixture, 0700)) throw new RuntimeException('Cannot create isolated fixture.');
if (file_put_contents($fixture.'/.sy3-leads-check-marker', $marker, LOCK_EX) !== strlen($marker)) {
    throw new RuntimeException('Cannot mark isolated fixture.');
}
chmod($fixture.'/.sy3-leads-check-marker', 0600);
$cleaned = false;
$cleanup = static function () use ($fixture, $base, $marker, &$cleaned): void {
    if ($cleaned) return;
    if (!file_exists($fixture) && !is_link($fixture)) { $cleaned = true; return; }
    if (is_link($fixture) || dirname($fixture) !== $base
        || !preg_match('/\A\.check-leads-[a-f0-9]{20}\z/', basename($fixture))
        || !is_file($fixture.'/.sy3-leads-check-marker')
        || is_link($fixture.'/.sy3-leads-check-marker')
        || file_get_contents($fixture.'/.sy3-leads-check-marker') !== $marker) {
        throw new RuntimeException('Cleanup refused: fixture identity changed.');
    }
    $remove = static function (string $path) use (&$remove, $fixture): void {
        if (!str_starts_with($path, $fixture.'/')) throw new RuntimeException('Cleanup escaped fixture.');
        if (is_link($path) || !is_dir($path)) {
            if (!unlink($path)) throw new RuntimeException('Cannot remove own fixture entry.');
            return;
        }
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) $remove($entry->getPathname());
        if (!rmdir($path)) throw new RuntimeException('Cannot remove own fixture directory.');
    };
    foreach (new FilesystemIterator($fixture, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->getFilename() !== '.sy3-leads-check-marker') $remove($entry->getPathname());
    }
    if (!unlink($fixture.'/.sy3-leads-check-marker') || !rmdir($fixture)) throw new RuntimeException('Cannot finish own fixture cleanup.');
    $cleaned = true;
};
$originalUser = get_current_user_id();
register_shutdown_function(static function () use ($cleanup, $originalEnv, $originalUser): void {
    putenv('SY3_LEADS_STORAGE='.$originalEnv);
    wp_set_current_user($originalUser);
    try { $cleanup(); } catch (Throwable $error) { error_log('Lead preview fixture cleanup requires inspection: '.$error->getMessage()); }
});

$assertions = 0;
$results = [];
$httpAttempts = 0;
$mailAttempts = 0;
$ips = ['192.0.2.31', '198.51.100.42', '2001:db8::19'];
$httpBlock = static function () use (&$httpAttempts) { $httpAttempts++; return new WP_Error('preview_delivery_blocked', 'Transport is disabled in this check.'); };
$mailBlock = static function () use (&$mailAttempts) { $mailAttempts++; return false; };
add_filter('pre_http_request', $httpBlock, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', $mailBlock, PHP_INT_MAX, 2);
$assert = static function (bool $condition, string $label) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($label);
};
$responds = static function ($response, int $status, ?string $code = null) use ($assert): array {
    $assert($response instanceof WP_REST_Response, 'Response must be WP_REST_Response.');
    $assert($response->get_status() === $status, 'Unexpected HTTP status: '.$response->get_status().' instead of '.$status.'.');
    $body = $response->get_data();
    $assert(is_array($body) && ($body['ok'] ?? null) === ($status === 200), 'Unexpected response body.');
    if ($code !== null) $assert(($body['code'] ?? null) === $code, 'Unexpected validation code.');
    return $body;
};
$files = static fn(string $dir): array => glob($dir.'/*.json') ?: [];
$valid = static function (int $now, string $page = '/services/'): array {
    return array_merge(sy3_leads_issue($page, $now), [
        'name'=>'Preview Example', 'contact'=>'preview@example.org',
        'message'=>"CRM integration request.\nPlease explain the next step.",
        'company_website'=>'', 'consent'=>true, 'utm'=>[],
    ]);
};
$jobId = static function (array $data, int $now): string {
    $token = sy3_leads_verify($data['token'], $now);
    if (!is_array($token)) throw new RuntimeException('Invalid test challenge.');
    return hash('sha256', $token['nonce']);
};
$snapshot = static function (string $root): array {
    $result = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $entry) {
        $path = $entry->getPathname();
        $result[substr($path, strlen($root)+1)] = is_link($path) ? 'symlink:'.readlink($path) : hash_file('sha256', $path);
    }
    ksort($result);
    return $result;
};
$run = static function (string $label, callable $test) use (&$results, $fixture, $assert): void {
    $dir = $fixture.'/case-'.(count($results)+1);
    foreach (['','/queue','/locks','/limits','/state'] as $suffix) {
        if (!mkdir($dir.$suffix, 0700)) throw new RuntimeException('Cannot create case directory.');
    }
    putenv('SY3_LEADS_STORAGE='.$dir);
    try {
        $assert(sy3_leads_storage() === $dir && sy3_leads_ready(), 'Case storage isolation failed.');
        $test($dir);
        $results[] = ['test'=>$label, 'passed'=>true];
    } catch (Throwable $error) {
        $results[] = ['test'=>$label, 'passed'=>false, 'failure'=>$error->getMessage()];
    }
};
$now = time();
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    wp_set_current_user(0);
    $run('durable schema, permissions, outside webroot and empty UTM object', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files,$jobId): void {
        $data = $valid($now);
        $data['name'] = ' <b>Preview Example</b> ';
        $data['message'] = "<script>bad()</script>Describe CRM.\nSecond line.";
        $response = sy3_leads_accept($data, $ips[0], $now);
        $body = $responds($response, 200);
        $assert(($response->get_headers()['Cache-Control'] ?? '') === 'no-store, private, max-age=0', 'Responses must forbid caching.');
        $paths = $files($dir.'/queue');
        $assert(count($paths) === 1, 'Exactly one durable job expected.');
        $path = $paths[0]; clearstatcache(true, $path);
        $assert((fileperms($path) & 0777) === 0600, 'Job must be mode 0600.');
        $assert(!str_starts_with(realpath($path), realpath(ABSPATH).'/'), 'Job must be outside webroot.');
        $raw = file_get_contents($path);
        $job = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $object = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        $keys = array_keys($job); sort($keys);
        $expected = ['version','id','created_at','name','contact','message','page','utm','consent']; sort($expected);
        $assert($keys === $expected, 'Job schema must have only reviewed fields.');
        $id = $jobId($data, $now);
        $assert($job['version'] === 1 && $job['id'] === $id && basename($path) === $id.'.json', 'Version or durable identity mismatch.');
        $assert($job['created_at'] === $now && $job['consent'] === true, 'Timestamp and consent types must be exact.');
        $assert($job['name'] === 'Preview Example' && !str_contains($job['message'], '<'), 'Stored free text must be sanitized.');
        $assert($job['contact'] === $data['contact'] && $job['page'] === home_url('/services/'), 'Contact or signed page mismatch.');
        $assert($object->utm instanceof stdClass && count(get_object_vars($object->utm)) === 0, 'Empty UTM must serialize to {}, not [].');
        $assert($body['reference'] === 'SY3-'.strtoupper(substr($id, 0, 12)), 'Public reference must match saved job.');
        $assert(count($files($dir.'/limits')) === 1, 'One rate bucket expected.');
        $assert((glob($dir.'/queue/.pending-*') ?: []) === [], 'No pending write must remain.');
    });

    $run('honeypot including literal zero creates no job or rate state', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach (['bot', '0', ' 0 '] as $value) {
            $data = $valid($now); $data['company_website'] = $value; $data['token'] = 'invalid';
            $body = $responds(sy3_leads_accept($data, $ips[0], $now), 200);
            $assert(!isset($body['reference']), 'Honeypot must not claim a saved job reference.');
        }
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits') && !(glob($dir.'/locks/*') ?: []), 'Honeypot must not consume token or rate resources.');
    });

    $run('strict scalar fields reject arrays, booleans, numbers and explicit null', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach (['name','contact','message','company_website','token','nonce'] as $field) {
            foreach ([[], false, 123, null] as $invalid) {
                $data = $valid($now); $data[$field] = $invalid;
                $responds(sy3_leads_accept($data, $ips[0], $now), 400, 'fields');
            }
        }
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'), 'Scalar validation must have no durable effects.');
    });

    foreach (['contact','consent'] as $field) {
        $run('invalid '.$field.' leaves challenge reusable and rate untouched', static function ($dir) use ($field,$valid,$now,$ips,$responds,$assert,$files): void {
            $data = $valid($now); $invalid = $data; $invalid[$field] = $field === 'contact' ? 'not-a-contact' : false;
            for ($i=0; $i<7; $i++) $responds(sy3_leads_accept($invalid, $ips[0], $now), 400, $field);
            $assert(!$files($dir.'/queue') && !$files($dir.'/limits') && !(glob($dir.'/locks/*') ?: []), 'Invalid fields must not consume challenge or limiter.');
            $responds(sy3_leads_accept($data, $ips[0], $now), 200);
            $limit = json_decode(file_get_contents($files($dir.'/limits')[0]), true, 512, JSON_THROW_ON_ERROR);
            $assert($limit['count'] === 1 && count($files($dir.'/queue')) === 1, 'Corrected request must consume exactly one slot.');
        });
    }

    $run('contact formats and consent require explicit reviewed values', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach (['123','123456789','1234567890123456','name@example','email@example.org\nBcc: evil@example.org'] as $contact) {
            $data=$valid($now); $data['contact']=$contact; $responds(sy3_leads_accept($data,$ips[0],$now),400,'contact');
        }
        foreach ([0,1,'true','yes',[],null] as $consent) {
            $data=$valid($now); $data['consent']=$consent; $responds(sy3_leads_accept($data,$ips[0],$now),400,'consent');
        }
        $data=$valid($now); $data['contact']='+7 (900) 123-45-67'; $data['consent']='1';
        $responds(sy3_leads_accept($data,$ips[0],$now),200);
        $assert(count($files($dir.'/queue'))===1,'String checkbox and formatted phone must be accepted.');
    });

    $run('bad, tampered, expired and future signatures plus wrong WP nonce', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        $variants=[];
        $data=$valid($now); $data['token']='not-a-token'; $variants[]=$data;
        $data=$valid($now); $data['token']=substr($data['token'],0,-1).(substr($data['token'],-1)==='a'?'b':'a'); $variants[]=$data;
        $data=$valid($now-12*HOUR_IN_SECONDS-1); $variants[]=$data;
        $data=$valid($now+301); $variants[]=$data;
        $data=$valid($now); $data['nonce']='not-a-wp-nonce'; $variants[]=$data;
        $data=$valid($now); [$payload,$signature]=explode('.',$data['token']);
        $decoded=json_decode(base64_decode(strtr($payload,'-_','+/')),true); $decoded['page']='/forged/';
        $data['token']=rtrim(strtr(base64_encode(wp_json_encode($decoded)),'+/','-_'),'=').'.'.$signature; $variants[]=$data;
        foreach($variants as $variant) $responds(sy3_leads_accept($variant,$ips[0],$now),403,'token');
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'), 'Invalid protection must not write durable state.');
        $assert(is_array(sy3_leads_verify($valid($now-12*HOUR_IN_SECONDS)['token'],$now)), 'Exact oldest permitted signature should verify.');
        $assert(is_array(sy3_leads_verify($valid($now+300)['token'],$now)), 'Exact permitted clock skew should verify.');
    });

    $run('signed external or malformed page cannot become a saved URL', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach(['https://outside.example/','//outside.example/','/bad?x=1','/bad#part','/bad\\path',"/bad\npath"] as $page) {
            $assert(sy3_leads_page($page)===false,'Unsafe page accepted by page validator.');
            $responds(sy3_leads_accept($valid($now,$page),$ips[0],$now),403,'token');
        }
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'),'Unsafe page must have no durable side effects.');
    });

    $run('same-token replay across IP addresses preserves one job and reference', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        $data=$valid($now);
        $first=$responds(sy3_leads_accept($data,$ips[0],$now),200);
        $path=$files($dir.'/queue')[0]; $original=file_get_contents($path);
        foreach([$ips[0],$ips[1],$ips[2]] as $ip) {
            $replay=$data; $replay['message']='Different replay content';
            $body=$responds(sy3_leads_accept($replay,$ip,$now+1),200);
            $assert($body['reference']===$first['reference'],'Replay reference must remain stable.');
        }
        $assert(count($files($dir.'/queue'))===1 && file_get_contents($path)===$original,'Replay must neither duplicate nor overwrite the saved job.');
        $limits=$files($dir.'/limits');
        $assert(count($limits)===1 && json_decode(file_get_contents($limits[0]),true)['count']===1,'Replay from a new IP must not acquire another rate slot.');
    });

    $run('five per fixed ten-minute window, sixth rejected, exact expiry resets', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach([0,50,150,300,599] as $i=>$offset) {
            $responds(sy3_leads_accept($valid($now+$offset),$ips[0],$now+$offset),200);
            $limit=json_decode(file_get_contents($files($dir.'/limits')[0]),true);
            $assert($limit===['started'=>$now,'count'=>$i+1],'Later requests must not slide or extend the rate window.');
        }
        $sixth=$valid($now+599);
        $response=sy3_leads_accept($sixth,$ips[0],$now+599); $responds($response,429,'rate');
        $assert(($response->get_headers()['Retry-After']??null)==='1','Retry-After must reflect the original fixed expiry.');
        $assert(count($files($dir.'/queue'))===5,'Rejected sixth request must not create a job.');
        $responds(sy3_leads_accept($sixth,$ips[0],$now+600),200);
        $limit=json_decode(file_get_contents($files($dir.'/limits')[0]),true);
        $assert($limit===['started'=>$now+600,'count'=>1] && count($files($dir.'/queue'))===6,'Same previously rejected token must succeed at exact window expiry.');
    });

    $run('unknown fields and recipient or address overrides are rejected', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        $data=$valid($now);
        foreach(['recipient','chat_id','owner_id','user_id','telegram','channels','MAX_OWNER_ID','TELEGRAM_BOT_TOKEN','page','ip','remote_addr'] as $field) {
            $variant=$data; $variant[$field]='preview-override';
            $responds(sy3_leads_accept($variant,$ips[0],$now),400,'fields');
        }
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'),'Overrides must have no durable effects.');
        $responds(sy3_leads_accept($data,$ips[0],$now),200);
    });

    $run('bounded UTM map and text reject invalid input before rate usage', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        foreach(['scalar',false,12,['unknown'=>'x'],['utm_source'=>null],['utm_source'=>[]],['utm_source'=>str_repeat('x',121)],["utm_source"=>"bad\nvalue"]] as $utm) {
            $data=$valid($now); $data['utm']=$utm; $responds(sy3_leads_accept($data,$ips[0],$now),400,'fields');
        }
        foreach(['name'=>121,'contact'=>201,'message'=>2001] as $field=>$length) {
            $data=$valid($now); $data[$field]=str_repeat('я',$length); $responds(sy3_leads_accept($data,$ips[0],$now),400,'length');
        }
        $data=$valid($now); $data['message']="control\x01byte"; $responds(sy3_leads_accept($data,$ips[0],$now),400,'length');
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'),'Invalid UTM or length must not consume rate slots.');
        $data=$valid($now); $data['utm']=['utm_source'=>'<b>newsletter</b>','utm_campaign'=>'crm'];
        $responds(sy3_leads_accept($data,$ips[0],$now),200);
        $job=json_decode(file_get_contents($files($dir.'/queue')[0]),true);
        $assert($job['utm']===['utm_source'=>'newsletter','utm_campaign'=>'crm'],'Reviewed UTM keys must be sanitized and preserved.');
    });

    $run('native CRLF and CR textarea messages persist normalized LF', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$jobId): void {
        foreach(["First line\r\nSecond line\r\nThird line", "First line\rSecond line\rThird line"] as $message) {
            $data=$valid($now); $data['message']=$message;
            $responds(sy3_leads_accept($data,$ips[0],$now),200);
            $job=json_decode(file_get_contents($dir.'/queue/'.$jobId($data,$now).'.json'),true,512,JSON_THROW_ON_ERROR);
            $assert($job['message']==="First line\nSecond line\nThird line",'Native textarea line endings must normalize to LF while preserving separate lines.');
            $assert(!str_contains($job['message'],"\r"),'Saved textarea must not retain CR bytes.');
        }
    });

    $run('DEL and malformed UTF-8 rejected before persistence or rate consumption', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        $data=$valid($now);
        foreach(['name','contact','message'] as $field) {
            foreach(["before\x7fafter", "before\xc3\x28after"] as $invalid) {
                $variant=$data; $variant[$field]=$invalid;
                $responds(sy3_leads_accept($variant,$ips[0],$now),400,'length');
            }
        }
        foreach(["before\x7fafter", "before\xc3\x28after"] as $invalid) {
            $variant=$data; $variant['utm']=['utm_source'=>$invalid];
            $responds(sy3_leads_accept($variant,$ips[0],$now),400,'fields');
        }
        $assert(sy3_leads_page("/before\x7fafter")===false,'Page must reject DEL too.');
        $responds(sy3_leads_accept($valid($now,"/before\x7fafter"),$ips[0],$now),403,'token');
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits') && !(glob($dir.'/locks/*')?:[]),'Invalid character encodings must not consume persistent resources.');
        $responds(sy3_leads_accept($data,$ips[0],$now),200);
        $assert(count($files($dir.'/queue'))===1,'The original challenge must remain reusable after encoding errors.');
    });

    $run('complete saved URL includes origin in the 2048-byte limit', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files,$jobId): void {
        $path='/'.str_repeat('a',2048-strlen(home_url('/')));
        $assert(strlen(home_url($path))===2048,'URL boundary fixture must include the complete site origin.');
        $assert(sy3_leads_page($path)===$path,'Exact full-URL boundary must remain accepted.');
        $tooLong=$path.'b';
        $assert(strlen($tooLong)<2048 && strlen(home_url($tooLong))===2049,'Regression fixture must distinguish path length from full URL length.');
        $assert(sy3_leads_page($tooLong)===false,'Origin plus path exceeding 2048 bytes must be rejected.');
        $responds(sy3_leads_accept($valid($now,$tooLong),$ips[0],$now),403,'token');
        $assert(!$files($dir.'/queue') && !$files($dir.'/limits'),'Oversized signed URL must not consume resources.');
        $data=$valid($now,$path); $responds(sy3_leads_accept($data,$ips[0],$now),200);
        $job=json_decode(file_get_contents($dir.'/queue/'.$jobId($data,$now).'.json'),true,512,JSON_THROW_ON_ERROR);
        $assert($job['page']===home_url($path) && strlen($job['page'])===2048,'Accepted boundary URL must be stored completely without truncation.');
    });

    $run('fresh-token quota spam has bounded lock inodes and no extra jobs', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files): void {
        for($i=0;$i<5;$i++)$responds(sy3_leads_accept($valid($now),$ips[0],$now),200);
        $limitPaths=$files($dir.'/limits');
        $assert(count($limitPaths)===1,'Initial requests must use one rate bucket.');
        $originalLimit=file_get_contents($limitPaths[0]);
        // More fresh challenges than all 256 token stripes: full-token lock names fail this check.
        $rejected=1024;
        for($i=0;$i<$rejected;$i++)$responds(sy3_leads_accept($valid($now+1),$ips[0],$now+1),429,'rate');
        $tokenLocks=glob($dir.'/locks/token-*.lock')?:[];
        $ipLocks=glob($dir.'/locks/ip-*.lock')?:[];
        $allLocks=glob($dir.'/locks/*')?:[];
        $assert(count($tokenLocks)<=256 && count($ipLocks)<=256 && count($allLocks)<=512,'Fresh-token quota spam must stay within fixed lock stripe counts.');
        $assert(count($ipLocks)===1 && count($allLocks)<$rejected,'Same-IP rejected requests must reuse stripe files instead of creating one inode per token.');
        foreach($allLocks as $path)$assert((bool)preg_match('/\A(?:token|ip)-[a-f0-9]{2}\.lock\z/',basename($path)),'Lock filename must identify one fixed two-hex-digit stripe.');
        $assert(count($files($dir.'/queue'))===5 && count($files($dir.'/limits'))===1,'Rejected fresh challenges must create neither extra jobs nor rate buckets.');
        $assert(file_get_contents($limitPaths[0])===$originalLimit,'Rejected spam must not mutate or extend the already-full rate window.');
        $assert(!(glob($dir.'/queue/.pending-*')?:[]) && !(glob($dir.'/limits/.pending-*')?:[]),'Quota rejection must leave no pending write artifacts.');
    });

    $run('invalid peer address is rejected and queue symlink cannot overwrite target', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files,$jobId,$fixture): void {
        $data=$valid($now);
        $responds(sy3_leads_accept($data,'invalid-peer',$now),503,'unavailable');
        $assert(!$files($dir.'/limits'),'Invalid IP must not consume a rate slot.');
        $target=$fixture.'/own-symlink-canary'; $content='Own fixture canary; must remain unchanged.';
        file_put_contents($target,$content); chmod($target,0600);
        $path=$dir.'/queue/'.$jobId($data,$now).'.json';
        $assert(symlink($target,$path),'Cannot create own symlink fixture.');
        $responds(sy3_leads_accept($data,$ips[0],$now),503,'unavailable');
        $assert(is_link($path) && file_get_contents($target)===$content,'Queue symlink must not be followed or overwritten.');
        $assert(!$files($dir.'/limits') && !(glob($dir.'/queue/.pending-*') ?: []),'Queue symlink refusal must precede rate update and leave no pending write.');
    });

    $run('admin read tolerates missing, partial and malformed delivery state', static function ($dir) use ($valid,$now,$ips,$responds,$assert,$files,$plugin,$snapshot,$fixture): void {
        if (!function_exists('add_menu_page')) require_once ABSPATH.'wp-admin/includes/plugin.php';
        $assert(get_option('sy3_site_mode')==='expertise','Preview must already use expertise mode; test will not change options.');
        $users=get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);
        $assert(count($users)===1,'An existing preview administrator is required.');
        $savedUser=get_current_user_id();
        $globals=[]; foreach(['menu','admin_page_hooks','_registered_pages','_parent_pages'] as $key) $globals[$key]=['exists'=>array_key_exists($key,$GLOBALS),'value'=>$GLOBALS[$key]??null];
        $hook='toplevel_page_sy3-leads';
        $oldHook=isset($GLOBALS['wp_filter'][$hook]) ? clone $GLOBALS['wp_filter'][$hook] : null;
        try {
            wp_set_current_user((int)$users[0]);
            $assert(current_user_can('manage_options'),'Existing preview admin lacks expected capability.');
            foreach(['menu','admin_page_hooks','_registered_pages','_parent_pages'] as $key) if(!isset($GLOBALS[$key]))$GLOBALS[$key]=[];
            $registrations=[];
            foreach(($GLOBALS['wp_filter']['admin_menu']->callbacks??[]) as $callbacks) foreach($callbacks as $registered) {
                $callback=$registered['function'];
                if($callback instanceof Closure && realpath((new ReflectionFunction($callback))->getFileName())===$plugin)$registrations[]=$callback;
            }
            $assert(count($registrations)===1,'Capture exactly this plugin admin menu registration, never all menu callbacks.');
            $registrations[0]();
            $renders=[];
            foreach(($GLOBALS['wp_filter'][$hook]->callbacks??[]) as $callbacks) foreach($callbacks as $registered) {
                $callback=$registered['function'];
                if($callback instanceof Closure && realpath((new ReflectionFunction($callback))->getFileName())===$plugin)$renders[]=$callback;
            }
            $assert(count($renders)===1,'Capture exactly this plugin admin renderer.');
            $data=$valid($now); $responds(sy3_leads_accept($data,$ips[0],$now),200);
            $path=$files($dir.'/queue')[0]; $job=json_decode(file_get_contents($path),true);
            $job['name']='<b>Admin & example</b>'; $job['message']='<script>unsafe()</script>';
            file_put_contents($path,wp_json_encode($job)); chmod($path,0600);
            $statePath=$dir.'/state/'.$job['id'].'.json';
            $render=static function()use($renders):string{ob_start();try{$renders[0]();return ob_get_contents();}finally{ob_end_clean();}};
            $states=[null, '{}', '{"channels":{}}', '{"channels":null}', '{"channels":{"telegram":{"status":"sent"}}}', '{not-json'];
            foreach($states as $state) {
                if(file_exists($statePath))unlink($statePath);
                if($state!==null){file_put_contents($statePath,$state);chmod($statePath,0600);}
                $before=$snapshot($dir); $html=$render();
                $assert($snapshot($dir)===$before,'Admin rendering must not mutate queue or delivery state.');
                $assert(str_contains($html,esc_html($job['name'])) && !str_contains($html,'<script>unsafe()'),'Admin must escape stored free text.');
                $assert(str_contains($html,'MAX: Ожидает отправки'),'Absent channel must remain pending without warnings.');
                $assert(str_contains($html,$state!==null && str_contains($state,'"sent"')?'Telegram: Доставка подтверждена':'Telegram: Ожидает отправки'),'Partial state must preserve confirmed channel status.');
            }
            if(file_exists($statePath))unlink($statePath);
            $canary=$fixture.'/own-admin-state-canary';file_put_contents($canary,'{"channels":{"max":{"status":"CANARY-MUST-NOT-READ"}}}');chmod($canary,0600);
            $assert(symlink($canary,$statePath),'Cannot create own state symlink fixture.');
            $html=$render();
            $assert(str_contains($html,'MAX: Ожидает отправки') && !str_contains($html,'CANARY-MUST-NOT-READ'),'Admin must ignore state symlinks.');
            wp_set_current_user(0);
            $assert($render()==='','Anonymous user must not render the admin queue.');
        } finally {
            wp_set_current_user($savedUser);
            foreach($globals as $key=>$saved) { if($saved['exists'])$GLOBALS[$key]=$saved['value'];else unset($GLOBALS[$key]); }
            if($oldHook!==null)$GLOBALS['wp_filter'][$hook]=$oldHook;else unset($GLOBALS['wp_filter'][$hook]);
        }
    });

    $run('no raw peer addresses in persisted files or filenames; no transport called', static function ($dir) use ($fixture,$ips,$assert,&$httpAttempts,&$mailAttempts): void {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS)) as $entry) {
            $path=$entry->getPathname(); if(is_link($path))continue;
            $contents=file_get_contents($path);
            foreach($ips as $ip)$assert(!str_contains($contents,$ip) && !str_contains($path,$ip),'Raw peer IP must never be persisted.');
        }
        $assert($httpAttempts===0 && $mailAttempts===0,'Pure queue checks must not attempt HTTP or email delivery.');
    });
} finally {
    restore_error_handler();
    remove_filter('pre_http_request',$httpBlock,PHP_INT_MAX);
    remove_filter('pre_wp_mail',$mailBlock,PHP_INT_MAX);
    wp_set_current_user($originalUser);
    putenv('SY3_LEADS_STORAGE='.$originalEnv);
    $cleanup();
}

$failures=count(array_filter($results,static fn($result)=>!$result['passed']));
$summary=[
    'passed'=>$failures===0,'cases'=>count($results),'assertions'=>$assertions,'failed_cases'=>$failures,
    'cleanup_complete'=>$cleaned,'storage_restored'=>getenv('SY3_LEADS_STORAGE')===$originalEnv,
    'http_attempts'=>$httpAttempts,'mail_attempts'=>$mailAttempts,'plugin_sha256'=>hash_file('sha256',$plugin),
    'results'=>$results,
    'limits'=>['Direct PHP integration only; browser/native redirect and concurrent-process checks are separate.','No transport worker or sender endpoint is invoked; downstream delivery is outside this check.','No database fixtures, option writes or database stubs are used.'],
];
WP_CLI::line(wp_json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
if($failures)WP_CLI::error('Preview leads integration checks failed.');
WP_CLI::success('Preview leads integration checks passed; isolated fixtures removed.');
