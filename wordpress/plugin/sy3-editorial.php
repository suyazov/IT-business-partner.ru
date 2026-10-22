<?php
/**
 * Plugin Name: Сутри — редакционный календарь
 * Description: Две очереди черновиков: экспертная раз в три дня и поисковая раз в день, с общей паузой и снятием отдельных материалов.
 * Version: 1.1.0
 */
defined('ABSPATH') || exit;
const SY3_EDITORIAL_BATCH = 'sy3-expertise-20261022';
const SY3_EDITORIAL_INTERVAL = 3 * DAY_IN_SECONDS;
const SY3_EDITORIAL_SEARCH_BATCH = 'sy3-search-20261022';

function sy3_editorial_series(): array {
    return [
        SY3_EDITORIAL_BATCH => ['interval'=>SY3_EDITORIAL_INTERVAL,'option'=>'sy3_editorial_last_publication','label'=>'Экспертная серия'],
        SY3_EDITORIAL_SEARCH_BATCH => ['interval'=>DAY_IN_SECONDS,'option'=>'sy3_search_last_publication','label'=>'SEO-серия'],
    ];
}

function sy3_editorial_eligible(int $id): bool {
    return get_option('sy3_site_mode') === 'expertise'
        && get_post_type($id) === 'post'
        && get_post_status($id) === 'draft'
        && isset(sy3_editorial_series()[get_post_meta($id, '_sy3_editorial_batch', true)])
        && (int) get_post_meta($id, '_sy3_editorial_at', true) > 0
        && get_post_meta($id, '_sy3_editorial_enabled', true) === '1';
}

/** Count article text without process graphics, downloads and source/contact panels. */
function sy3_editorial_quality(string $content): array {
    $text='';$table=false;$diagram=false;
    $walk=function($blocks)use(&$walk,&$text,&$table,&$diagram){foreach($blocks as $block){
        $class=explode(' ',$block['attrs']['className']??'');
        if(in_array('process-map',$class,true)){$diagram=true;continue;}
        if(array_intersect($class,['editorial-sources','editorial-contact']))continue;
        if(($block['blockName']??'')==='core/table')$table=true;
        if($block['innerBlocks'])$walk($block['innerBlocks']);else $text.=' '.html_entity_decode(wp_strip_all_tags($block['innerHTML']),ENT_QUOTES|ENT_HTML5,'UTF-8');
    }};
    $walk(parse_blocks($content));$characters=mb_strlen(trim(preg_replace('/\s+/u',' ',$text)));
    return ['characters'=>$characters,'table'=>$table,'diagram'=>$diagram,'complete'=>$characters>=10000&&$table&&$diagram];
}

/** Call only from WP-CLI. MySQL advisory lock serializes concurrent timer/manual runs. */
function sy3_editorial_publish_due(?int $now = null): array {
    global $wpdb;
    if (get_option('sy3_site_mode') !== 'expertise') return ['status' => 'wrong_site'];
    if (get_option('sy3_editorial_paused', false)) return ['status' => 'paused'];
    $now ??= time();
    $lock = 'sy3-editorial-' . md5(DB_NAME . ':' . $wpdb->prefix);
    $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock));
    if ($acquired !== 1) return ['status' => 'locked'];
    try {
        $candidates = []; $next = [];
        foreach (sy3_editorial_series() as $batch => $series) {
            $last = (int) get_option($series['option'], 0);
            // Recover this series independently if publication preceded an interrupted receipt write.
            $recent = get_posts(['post_type'=>'post','post_status'=>'publish','meta_key'=>'_sy3_editorial_batch','meta_value'=>$batch,'posts_per_page'=>1,'orderby'=>'date','order'=>'DESC']);
            if ($recent) $last = max($last, (int) get_post_timestamp($recent[0]));
            if ($last && $now < $last + $series['interval']) { $next[] = $last + $series['interval']; continue; }
            $posts = get_posts(['post_type'=>'post','post_status'=>'draft','posts_per_page'=>1,'meta_query'=>[
                ['key'=>'_sy3_editorial_batch','value'=>$batch], ['key'=>'_sy3_editorial_enabled','value'=>'1'],
                ['key'=>'_sy3_editorial_at','value'=>$now,'compare'=>'<=','type'=>'NUMERIC'],
            ],'meta_key'=>'_sy3_editorial_at','orderby'=>['meta_value_num'=>'ASC','ID'=>'ASC']]);
            if ($posts) $candidates[] = ['post'=>$posts[0],'batch'=>$batch,'series'=>$series];
        }
        usort($candidates, fn($a,$b)=>(int)get_post_meta($a['post']->ID,'_sy3_editorial_at',true)<=>(int)get_post_meta($b['post']->ID,'_sy3_editorial_at',true) ?: $a['post']->ID<=>$b['post']->ID);
        $blocked = null;
        foreach ($candidates as $candidate) {
            $id = $candidate['post']->ID;
            if (!sy3_editorial_eligible($id)) { $blocked ??= ['status'=>'changed']; continue; }
            if (wp_check_post_lock($id)) { $blocked ??= ['status'=>'being_edited','id'=>$id]; continue; }
            if (!get_post_thumbnail_id($id) || !sy3_editorial_quality($candidate['post']->post_content)['complete']) { $blocked ??= ['status'=>'incomplete','id'=>$id]; continue; }
            if(!hash_equals((string)get_post_meta($id,'_sy3_editorial_reviewed_hash',true),hash('sha256',$candidate['post']->post_content))){$blocked??=['status'=>'review_required','id'=>$id];continue;}
            $result = wp_update_post(['ID'=>$id,'post_status'=>'publish','edit_date'=>true,'post_date'=>wp_date('Y-m-d H:i:s',$now),'post_date_gmt'=>gmdate('Y-m-d H:i:s',$now)],true);
            if (is_wp_error($result)) return ['status'=>'error','message'=>$result->get_error_message()];
            if (get_post_status($id)!=='publish') return ['status'=>'error','message'=>'Publication status was not persisted.'];
            update_option($candidate['series']['option'],$now,false);
            update_post_meta($id,'_sy3_editorial_enabled','0'); update_post_meta($id,'_sy3_editorial_published_at',$now);
            return ['status'=>'published','id'=>$id,'at'=>$now,'series'=>$candidate['batch']];
        }
        return $blocked ?? ($next ? ['status'=>'interval','next_at'=>min($next)] : ['status'=>'nothing_due']);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

add_filter('manage_post_posts_columns', function ($columns) { $columns['sy3_calendar'] = 'Автопубликация'; return $columns; });
add_action('manage_post_posts_custom_column', function ($column, $id) {
    $series = sy3_editorial_series()[get_post_meta($id, '_sy3_editorial_batch', true)] ?? null;
    if ($column !== 'sy3_calendar' || !$series) return;
    $at = (int) get_post_meta($id, '_sy3_editorial_at', true);
    echo esc_html($series['label'] . ': ' . (sy3_editorial_eligible((int) $id) ? wp_date('d.m.Y H:i', $at) . ' МСК' : 'Снято с очереди / опубликовано'));
}, 10, 2);

add_action('add_meta_boxes_post', function ($post) {
    if (!isset(sy3_editorial_series()[get_post_meta($post->ID, '_sy3_editorial_batch', true)])) return;
    add_meta_box('sy3-calendar', 'Редакционный календарь', function ($post) {
        $at = (int) get_post_meta($post->ID, '_sy3_editorial_at', true);
        wp_nonce_field('sy3-calendar-' . $post->ID, 'sy3_calendar_nonce');
        $series = sy3_editorial_series()[get_post_meta($post->ID, '_sy3_editorial_batch', true)];
        echo '<p>' . esc_html($series['label']) . ': ' . esc_html(wp_date('d.m.Y H:i', $at)) . ' МСК.</p><p>Публикуется проверенная версия: от 10 000 знаков, таблица и схема процесса. После изменения текста нужна повторная проверка. Интервал серии — ' . ($series['interval']===DAY_IN_SECONDS?'один день':'три дня') . '.</p>';
        if ($query=get_post_meta($post->ID,'_sy3_search_query',true)) echo '<p>Поисковый вопрос: <strong>'.esc_html($query).'</strong></p>';
        echo '<label><input type="checkbox" name="sy3_editorial_enabled" value="1" ' . checked(get_post_meta($post->ID, '_sy3_editorial_enabled', true), '1', false) . '> Публиковать автоматически</label>';
        echo '<p><label><input type="checkbox" name="sy3_editorial_reviewed" value="1"> Подтверждаю проверку текста и фактов после правок</label></p>';
    }, 'post', 'side');
});
add_action('save_post_post', function ($id) {
    if (!isset($_POST['sy3_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sy3_calendar_nonce'])), 'sy3-calendar-' . $id)) return;
    if (!current_user_can('edit_post', $id) || wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    if (!isset(sy3_editorial_series()[get_post_meta($id, '_sy3_editorial_batch', true)])) return;
    update_post_meta($id, '_sy3_editorial_enabled', isset($_POST['sy3_editorial_enabled']) ? '1' : '0');
    $content=get_post_field('post_content',$id);
    if(isset($_POST['sy3_editorial_reviewed'])&&sy3_editorial_quality($content)['complete'])update_post_meta($id,'_sy3_editorial_reviewed_hash',hash('sha256',$content));
});

add_action('admin_menu', function () {
    add_submenu_page('edit.php', 'Редакционный календарь', 'Календарь публикаций', 'manage_options', 'sy3-editorial', function () {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['sy3_calendar_toggle'])) {
            check_admin_referer('sy3-calendar-toggle');
            update_option('sy3_editorial_paused', !get_option('sy3_editorial_paused', false), false);
        }
        $paused = (bool) get_option('sy3_editorial_paused', false);
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'draft', 'posts_per_page' => -1, 'meta_query' => [
            ['key' => '_sy3_editorial_batch', 'value' => array_keys(sy3_editorial_series()), 'compare'=>'IN'], ['key' => '_sy3_editorial_enabled', 'value' => '1'],
        ], 'meta_key' => '_sy3_editorial_at', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        echo '<div class="wrap"><h1>Календарь публикаций</h1><p>Статус: <strong>' . ($paused ? 'пауза' : 'автопубликация включена') . '</strong>. В очереди: ' . count($posts) . '. Экспертная серия — раз в три дня, SEO-серия — раз в день. Время московское.</p><p>Статьи остаются черновиками до выпуска. Редактируйте их как обычные записи. Снять отдельный материал можно в блоке «Редакционный календарь» редактора.</p>';
        echo '<form method="post">'; wp_nonce_field('sy3-calendar-toggle');
        echo '<button class="button" name="sy3_calendar_toggle" value="1">' . ($paused ? 'Возобновить очередь' : 'Приостановить всю очередь') . '</button></form>';
        $check = get_option('sy3_editorial_last_check', []);
        $labels = ['nothing_due' => 'время выпуска ещё не наступило', 'interval' => 'выдерживается интервал между публикациями', 'published' => 'статья опубликована', 'paused' => 'очередь приостановлена', 'locked' => 'проверка уже выполняется', 'being_edited' => 'статья открыта в редакторе', 'incomplete' => 'нужны 10 000 знаков, таблица, схема и обложка', 'review_required'=>'текст изменён или ещё не проверен', 'changed' => 'состояние статьи изменилось', 'error' => 'публикация требует проверки', 'wrong_site' => 'проверьте настройки сайта'];
        if ($check) echo '<p>Последняя проверка: ' . esc_html(wp_date('d.m.Y H:i', (int) $check['at'])) . '. Результат: ' . esc_html($labels[$check['status']] ?? 'проверьте очередь') . '.</p>';
        echo '<p>После простоя каждая серия выдерживает свой интервал и не выпускает накопленное залпом. Даты ниже — плановые места; после задержки фактический выпуск может быть позже.</p><table class="widefat striped"><thead><tr><th>Плановое место</th><th>Серия</th><th>Статья</th></tr></thead><tbody>';
        foreach ($posts as $post) echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i', (int) get_post_meta($post->ID, '_sy3_editorial_at', true))) . '</td><td>' . esc_html(sy3_editorial_series()[get_post_meta($post->ID,'_sy3_editorial_batch',true)]['label']) . '</td><td><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a></td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    });
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sy3-editorial publish-due', function () {
        if (wp_get_environment_type() !== 'production' && getenv('SY3_EDITORIAL_TEST') !== '1') WP_CLI::error('Non-production publisher requires test mode.');
        require_once ABSPATH . 'wp-admin/includes/post.php';
        $result = sy3_editorial_publish_due();
        update_option('sy3_editorial_last_check', ['at' => time(), 'status' => $result['status']], false);
        WP_CLI::log(wp_json_encode($result, JSON_UNESCAPED_UNICODE));
        if (in_array($result['status'], ['error', 'incomplete', 'review_required', 'wrong_site'], true)) WP_CLI::halt(1);
    });
}
