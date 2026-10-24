<?php
/**
 * Plugin Name: Сутри — защищённые заявки
 * Description: Форма с honeypot, подписанным токеном и закрытой очередью доставки в SY3 Support.
 * Version: 1.1.0
 */
defined('ABSPATH') || exit;

function sy3_leads_storage(): string {
    if (wp_get_environment_type() !== 'production' && getenv('SY3_LEADS_STORAGE')) return rtrim(getenv('SY3_LEADS_STORAGE'), '/');
    return '/var/lib/sy3-wordpress/leads';
}
function sy3_leads_ready(): bool {
    $root = sy3_leads_storage();
    foreach (['', '/queue', '/locks', '/limits', '/state'] as $suffix) if (is_link($root.$suffix) || !is_dir($root.$suffix)) return false;
    return is_writable($root.'/queue') && is_writable($root.'/locks') && is_writable($root.'/limits');
}
function sy3_leads_response(array $data, int $status = 200): WP_REST_Response {
    $response = new WP_REST_Response($data, $status);
    $response->header('Cache-Control', 'no-store, private, max-age=0');
    $response->header('X-Robots-Tag', 'noindex, nofollow');
    return $response;
}
function sy3_leads_error(string $code, string $message, int $status = 400): WP_REST_Response {
    return sy3_leads_response(['ok'=>false, 'code'=>$code, 'message'=>$message], $status);
}
function sy3_leads_page(string $path): string|false {
    if (strlen(home_url($path))>2048 || !str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\x00-\x20\x7f\\\\?#]/', $path)) return false;
    return $path;
}
function sy3_leads_issue(string $page, ?int $now = null): array {
    $data = ['at'=>$now ?? time(), 'nonce'=>bin2hex(random_bytes(16)), 'page'=>$page];
    $encoded = rtrim(strtr(base64_encode(wp_json_encode($data)), '+/', '-_'), '=');
    return ['token'=>$encoded.'.'.hash_hmac('sha256', $encoded, wp_salt('auth').'|sy3-leads-v1'), 'nonce'=>wp_create_nonce('sy3-leads')];
}
function sy3_leads_verify(string $token, int $now): array|false {
    if (strlen($token)>4096 || !preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{64})\z/', $token, $matches)) return false;
    if (!hash_equals(hash_hmac('sha256', $matches[1], wp_salt('auth').'|sy3-leads-v1'), $matches[2])) return false;
    $decoded = base64_decode(strtr($matches[1], '-_', '+/'), true);
    $data = $decoded === false ? null : json_decode($decoded, true);
    if (!is_array($data) || !is_int($data['at']??null) || $data['at']>$now+300 || $data['at']<$now-12*HOUR_IN_SECONDS) return false;
    if (!is_string($data['nonce']??null) || !preg_match('/\A[a-f0-9]{32}\z/', $data['nonce']) || !is_string($data['page']??null) || sy3_leads_page($data['page'])===false) return false;
    return $data;
}
function sy3_leads_same_origin(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $actual = wp_parse_url($origin); $expected = wp_parse_url(home_url('/'));
    return is_array($actual) && ($actual['scheme']??'')===($expected['scheme']??'') && ($actual['host']??'')===($expected['host']??'')
        && ($actual['port']??null)===($expected['port']??null) && empty($actual['user']) && empty($actual['pass']);
}
function sy3_leads_sync_directory(string $path): bool {
    if (!function_exists('fsync')) return false;
    $handle=@fopen($path,'r');
    if (!$handle) return false;
    $ok=@fsync($handle);fclose($handle);return $ok;
}
function sy3_leads_atomic_write(string $path, array $data): bool {
    $temporary = dirname($path).'/.pending-'.bin2hex(random_bytes(12));
    $handle = @fopen($temporary, 'x');
    if (!$handle) return false;
    @chmod($temporary, 0600);
    $raw = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok = is_string($raw) && fwrite($handle, $raw)===strlen($raw) && fflush($handle);
    if ($ok) $ok = function_exists('fsync') && fsync($handle);
    fclose($handle);
    if ($ok && !is_link($path)) $ok = @rename($temporary, $path); else $ok = false;
    if ($ok) $ok = sy3_leads_sync_directory(dirname($path));
    if (is_file($temporary)) @unlink($temporary);
    return $ok;
}
function sy3_leads_lock(string $path) {
    if (is_link($path)) return false;
    $handle = @fopen($path, 'c');
    if (!$handle) return false;
    @chmod($path, 0600);
    if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return false; }
    return $handle;
}
/** Validation precedes both replay consumption and the five-per-ten-minute limit. */
function sy3_leads_accept(array $data, string $ip, ?int $now = null): WP_REST_Response {
    $now ??= time();
    $allowed = ['name','contact','message','company_website','token','nonce','consent','utm'];
    if (array_diff(array_keys($data), $allowed)) return sy3_leads_error('fields', 'Проверьте поля формы.');
    foreach (['name','contact','message','company_website','token','nonce'] as $field) if (array_key_exists($field,$data) && !is_string($data[$field])) return sy3_leads_error('fields', 'Проверьте поля формы.');
    if (trim($data['company_website']??'')!=='') return sy3_leads_response(['ok'=>true,'message'=>'Спасибо! Сообщение принято.']);
    if (!wp_verify_nonce($data['nonce']??'', 'sy3-leads') || !($token=sy3_leads_verify($data['token']??'', $now))) return sy3_leads_error('token', 'Обновите защиту формы и попробуйте ещё раз.', 403);
    $name=trim($data['name']??''); $contact=trim($data['contact']??''); $message=trim(str_replace(["\r\n","\r"],"\n",$data['message']??''));
    if (!mb_check_encoding($name.$contact.$message,'UTF-8') || mb_strlen($name)>120 || mb_strlen($contact)>200 || mb_strlen($message)>2000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $name.$contact.$message)) return sy3_leads_error('length', 'Проверьте длину имени, контакта и описания задачи.');
    $digits=preg_replace('/\D/', '', $contact);
    $phone=(bool)preg_match('/\A[+0-9() .-]+\z/', $contact) && strlen($digits)>=10 && strlen($digits)<=15;
    if (!$phone && !is_email($contact)) return sy3_leads_error('contact', 'Укажите телефон с кодом страны или корректный email.');
    if (!in_array($data['consent']??null, [true,'1'], true)) return sy3_leads_error('consent', 'Подтвердите согласие на обработку данных для ответа на заявку.');
    $utm=$data['utm']??[];
    if (!is_array($utm) || array_diff(array_keys($utm), ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'])) return sy3_leads_error('fields', 'Проверьте поля формы.');
    foreach ($utm as $value) if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>120 || preg_match('/[\x00-\x1f\x7f]/u', $value)) return sy3_leads_error('fields', 'Проверьте поля формы.');
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !sy3_leads_ready()) return sy3_leads_error('unavailable', 'Форма временно недоступна. Напишите мне в Telegram или MAX.', 503);
    $root=sy3_leads_storage(); $id=hash('sha256', $token['nonce']);
    // REMOTE_ADDR is the peer supplied by the reviewed server, never a client-supplied forwarding header.
    $ip_key=hash_hmac('sha256', $ip, wp_salt('auth').'|sy3-lead-limit');
    // Fixed stripes bound lock-file growth even for rejected fresh-token spam.
    $token_lock=sy3_leads_lock($root.'/locks/token-'.substr($id,0,2).'.lock');
    if (!$token_lock) return sy3_leads_error('busy', 'Отправка уже выполняется. Повторите через несколько секунд.', 429);
    $ip_lock=false;
    try {
        $path=$root.'/queue/'.$id.'.json';
        if (is_link($path)) return sy3_leads_error('unavailable', 'Не удалось сохранить заявку. Напишите Артёму напрямую.', 503);
        if (is_file($path)) {
            if (!sy3_leads_sync_directory($root.'/queue')) return sy3_leads_error('unavailable','Не удалось подтвердить сохранение. Попробуйте ещё раз или напишите Артёму напрямую.',503);
            return sy3_leads_response(['ok'=>true,'reference'=>'SY3-'.strtoupper(substr($id,0,12)),'message'=>'Заявка уже сохранена. Повторная отправка не нужна.']);
        }
        $ip_lock=sy3_leads_lock($root.'/locks/ip-'.substr($ip_key,0,2).'.lock');
        if (!$ip_lock) return sy3_leads_error('busy', 'Повторите отправку через несколько секунд.', 429);
        $limit_path=$root.'/limits/'.$ip_key.'.json';
        if (is_link($limit_path)) return sy3_leads_error('unavailable', 'Форма временно недоступна.', 503);
        $limit=is_file($limit_path)?json_decode(file_get_contents($limit_path),true):null;
        if (!is_array($limit) || !is_int($limit['started']??null) || !is_int($limit['count']??null) || $limit['started']<=$now-600) $limit=['started'=>$now,'count'=>0];
        if ($limit['count']>=5) { $response=sy3_leads_error('rate', 'Слишком много заявок подряд. Попробуйте через десять минут или напишите Артёму напрямую.', 429); $response->header('Retry-After',(string)max(1,600-($now-$limit['started']))); return $response; }
        $job=['version'=>1,'id'=>$id,'created_at'=>$now,'name'=>sanitize_text_field($name),'contact'=>$contact,'message'=>sanitize_textarea_field($message),'page'=>home_url($token['page']),'utm'=>(object)array_map('sanitize_text_field',$utm),'consent'=>true];
        // Record the bounded rate slot before the durable job; a failed job write never consumes its replay token.
        $limit['count']++;
        if (!sy3_leads_atomic_write($limit_path,$limit) || !sy3_leads_atomic_write($path,$job)) return sy3_leads_error('unavailable', 'Не удалось сохранить заявку. Попробуйте снова или напишите Артёму напрямую.', 503);
        return sy3_leads_response(['ok'=>true,'reference'=>'SY3-'.strtoupper(substr($id,0,12)),'message'=>'Заявка сохранена. Я свяжусь с вами по указанному контакту.']);
    } finally {
        if ($ip_lock) { flock($ip_lock,LOCK_UN); fclose($ip_lock); }
        flock($token_lock,LOCK_UN); fclose($token_lock);
    }
}

add_action('rest_api_init', function () {
    if (get_option('sy3_site_mode')!=='expertise') return;
    register_rest_route('sy3-leads/v1','/challenge',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $request){
        $page=$request->get_param('page')??'/';
        if (!is_string($page) || sy3_leads_page($page)===false) return sy3_leads_error('page','Некорректный адрес страницы.');
        return sy3_leads_response(sy3_leads_issue($page));
    }]);
    register_rest_route('sy3-leads/v1','/submit',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function(WP_REST_Request $request){
        if (!sy3_leads_same_origin()) return sy3_leads_error('origin','Откройте форму на сайте Сутри.',403);
        if (strlen($request->get_body())>20000) return sy3_leads_error('length','Слишком большой запрос.',413);
        $data=$request->get_json_params();
        if (!is_array($data)) return sy3_leads_error('fields','Проверьте поля формы.');
        return sy3_leads_accept($data,$_SERVER['REMOTE_ADDR']??'');
    }]);
});

function sy3_leads_form(bool $standalone=false): void {
    $challenge=$standalone?sy3_leads_issue('/') : ['token'=>'','nonce'=>''];
    ?>
    <section class="sy3-lead-section" id="request-form" aria-labelledby="request-title"><div class="wrap sy3-lead-layout">
      <div class="sy3-lead-intro"><p class="eyebrow">Связаться со мной</p><h2 id="request-title">Обсудим задачу</h2><p class="sy3-lead-alternative"><a href="https://t.me/sy3_support_bot?start=site_sy3">Telegram</a> · <a href="https://max.ru/id263514840335_1_bot?start=site_sy3">MAX</a> · <a href="mailto:help@sy3.ru">help@sy3.ru</a></p></div>
      <form class="sy3-lead-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-challenge="<?php echo esc_url(rest_url('sy3-leads/v1/challenge')); ?>" data-submit="<?php echo esc_url(rest_url('sy3-leads/v1/submit')); ?>" <?php if($standalone)echo 'data-standalone="true"'; ?>>
        <input type="hidden" name="action" value="sy3_lead_submit"><input type="hidden" name="token" value="<?php echo esc_attr($challenge['token']); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr($challenge['nonce']); ?>">
        <label>Как к вам обращаться<input name="name" autocomplete="name" maxlength="120" placeholder="Ваше имя"></label>
        <label><span>Телефон или email <span aria-hidden="true">*</span></span><input name="contact" required autocomplete="email" maxlength="200" placeholder="Как с вами связаться"></label>
        <label class="sy3-lead-wide">Что нужно сделать<textarea name="message" rows="4" maxlength="2000" placeholder="Какая задача стоит перед бизнесом? Что уже работает и что хотите изменить?"></textarea></label>
        <div class="sy3-lead-honeypot" aria-hidden="true"><label>Не заполняйте это поле<input name="company_website" tabindex="-1" autocomplete="off" maxlength="200"></label></div>
        <label class="sy3-lead-consent sy3-lead-wide"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку данных заявки в Битрикс24 и передачу через Telegram и MAX для ответа на обращение. <a href="<?php echo esc_url(home_url('/privacy/')); ?>">Как обрабатываются данные</a></span></label>
        <div class="sy3-lead-wide sy3-lead-submit"><button class="button" type="submit">Отправить заявку</button><p class="sy3-lead-status" role="status" aria-live="polite" tabindex="-1"></p></div>
        <?php if(!$standalone): ?><noscript><p class="sy3-lead-wide">Чтобы отправить заявку без JavaScript, <a href="<?php echo esc_url(admin_url('admin-post.php?action=sy3_lead_form')); ?>">откройте отдельную форму</a>.</p></noscript><?php endif; ?>
      </form></div></section>
    <?php
}
add_action('sy3_before_footer',function(){if(get_option('sy3_site_mode')==='expertise')sy3_leads_form();});
add_action('wp_enqueue_scripts',function(){
    if(get_option('sy3_site_mode')!=='expertise')return;
    wp_enqueue_style('sy3-leads',plugins_url('sy3-leads-assets/leads.css',__FILE__),['sy3-direction'],'1.0.2');
    wp_enqueue_script('sy3-leads',plugins_url('sy3-leads-assets/leads.js',__FILE__),[],'1.0.2',true);
});
// Existing Telegram/MAX choices stay intact; the form is an additional contact path.

function sy3_leads_standalone(): void {
    if(get_option('sy3_site_mode')!=='expertise')wp_die('Not found','',404);
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');
    get_header();echo '<main id="main">';sy3_leads_form(true);echo '</main>';
    remove_all_actions('sy3_before_footer');get_footer();exit;
}
add_action('admin_post_nopriv_sy3_lead_form','sy3_leads_standalone');
add_action('admin_post_sy3_lead_form','sy3_leads_standalone');
function sy3_leads_native_submit(): void {
    if(get_option('sy3_site_mode')!=='expertise'||($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Not found','',404);
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');
    $data=wp_unslash($_POST);unset($data['action']);
    $result=sy3_leads_same_origin()?sy3_leads_accept($data,$_SERVER['REMOTE_ADDR']??''):sy3_leads_error('origin','Откройте форму на сайте Сутри.',403);
    $value=$result->get_data();status_header($result->get_status());
    get_header();echo '<main id="main" class="wrap sy3-lead-native"><h1>'.esc_html(!empty($value['ok'])?'Спасибо за обращение':'Не удалось отправить заявку').'</h1><p>'.esc_html($value['message']).'</p><p><a href="'.esc_url(admin_url('admin-post.php?action=sy3_lead_form')).'">Вернуться к форме</a> · <a href="https://t.me/sy3_support_bot?start=site_sy3">Написать в Telegram</a></p></main>';
    remove_all_actions('sy3_before_footer');get_footer();exit;
}
add_action('admin_post_nopriv_sy3_lead_submit','sy3_leads_native_submit');
add_action('admin_post_sy3_lead_submit','sy3_leads_native_submit');

add_action('admin_menu',function(){
    if(get_option('sy3_site_mode')!=='expertise')return;
    add_menu_page('Заявки с сайта','Заявки с сайта','manage_options','sy3-leads',function(){
        if(!current_user_can('manage_options'))return;
        echo '<div class="wrap"><h1>Заявки с сайта</h1><p>Сохранённые обращения и доставка в личные сообщения SY3 Support. При неоднозначном результате проверьте диалог перед повторной отправкой.</p>';
        $labels=['pending'=>'Ожидает отправки','retry'=>'Ожидает повторной отправки','sending'=>'Отправляется','sent'=>'Доставка подтверждена','failed'=>'Ошибка доставки','uncertain'=>'Нужно проверить диалог'];
        $files=glob(sy3_leads_storage().'/queue/*.json')?:[];
        usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));
        echo '<table class="widefat striped"><thead><tr><th>Обращение</th><th>Контакт</th><th>Задача</th><th>Telegram / MAX / CRM</th></tr></thead><tbody>';
        foreach(array_slice($files,0,100)as$file){
            if(is_link($file)||filesize($file)>16384||!preg_match('/\A[a-f0-9]{64}\.json\z/',basename($file)))continue;
            $job=json_decode(file_get_contents($file),true);if(!is_array($job)||!is_string($job['id']??null)||basename($file)!==$job['id'].'.json')continue;
            foreach(['name','contact','message','page']as$field)if(!is_string($job[$field]??null))$job[$field]='';
            $state_file=sy3_leads_storage().'/state/'.$job['id'].'.json';
            $state=is_file($state_file)&&!is_link($state_file)&&filesize($state_file)<16384?json_decode(file_get_contents($state_file),true):[];
            echo '<tr><td>'.esc_html('SY3-'.strtoupper(substr($job['id'],0,12))).'<br>'.esc_html(wp_date('d.m.Y H:i',(int)($job['created_at']??0))).'<br>'.esc_html($job['name']??'').'</td><td>'.esc_html($job['contact']??'').'</td><td>'.nl2br(esc_html($job['message']??'')).'<p>'.esc_html($job['page']??'').'</p></td><td>';
            foreach(['telegram','max']as$channel){$status=$state['channels'][$channel]['status']??'pending';echo esc_html(($channel==='telegram'?'Telegram':'MAX').': '.(is_string($status)?($labels[$status]??'Нужно проверить состояние'):'Нужно проверить состояние')).'<br>';}
            $crm_file=sy3_leads_storage().'/state/crm-job-'.hash('sha256','form:'.$job['id']).'.json';
            $crm=is_file($crm_file)&&!is_link($crm_file)&&filesize($crm_file)<16384?json_decode(file_get_contents($crm_file),true):[];
            $crm_labels=['sent'=>'Сохранено','retry'=>'Ожидает обработки','sending'=>'Сохраняется','uncertain'=>'Нужно проверить CRM','failed'=>'Ошибка сохранения'];
            $crm_status=is_string($crm['status']??null)?$crm['status']:'';
            echo esc_html('CRM: '.($crm_labels[$crm_status]??'Нет записи')).'<br>';
            if($crm_status==='sent'&&is_int($crm['deal_id']??null)&&$crm['deal_id']>0)echo '<a href="'.esc_url('https://sy3.bitrix24.ru/crm/deal/details/'.$crm['deal_id'].'/').'">Открыть сделку</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    },'dashicons-email-alt',26);
});
