<?php
/**
 * Plugin Name: Сутри — редакционный календарь
 * Description: Очередь черновиков: одна публикация каждые три дня, с возможностью снять отдельную статью с очереди.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;
const SY3_EDITORIAL_BATCH = 'sy3-expertise-20261022';
const SY3_EDITORIAL_INTERVAL = 3 * DAY_IN_SECONDS;

function sy3_editorial_eligible(int $id): bool {
    return get_option('sy3_site_mode') === 'expertise'
        && get_post_type($id) === 'post'
        && get_post_status($id) === 'draft'
        && get_post_meta($id, '_sy3_editorial_batch', true) === SY3_EDITORIAL_BATCH
        && get_post_meta($id, '_sy3_editorial_enabled', true) === '1';
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
        $last = (int) get_option('sy3_editorial_last_publication', 0);
        // Recover publication followed by process failure before updating the option.
        $recent = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'meta_key' => '_sy3_editorial_batch', 'meta_value' => SY3_EDITORIAL_BATCH, 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        if ($recent) $last = max($last, (int) get_post_timestamp($recent[0]));
        if ($last && $now < $last + SY3_EDITORIAL_INTERVAL) return ['status' => 'interval', 'next_at' => $last + SY3_EDITORIAL_INTERVAL];
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'draft', 'posts_per_page' => 1, 'meta_query' => [
            ['key' => '_sy3_editorial_batch', 'value' => SY3_EDITORIAL_BATCH],
            ['key' => '_sy3_editorial_enabled', 'value' => '1'],
            ['key' => '_sy3_editorial_at', 'value' => $now, 'compare' => '<=', 'type' => 'NUMERIC'],
        ], 'meta_key' => '_sy3_editorial_at', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        if (!$posts) return ['status' => 'nothing_due'];
        $id = $posts[0]->ID;
        if (!sy3_editorial_eligible($id)) return ['status' => 'changed'];
        if (wp_check_post_lock($id)) return ['status' => 'being_edited', 'id' => $id];
        if (!get_post_thumbnail_id($id) || mb_strlen(wp_strip_all_tags($posts[0]->post_content)) < 1200) return ['status' => 'incomplete', 'id' => $id];
        $result = wp_update_post(['ID' => $id, 'post_status' => 'publish', 'edit_date' => true, 'post_date' => wp_date('Y-m-d H:i:s', $now), 'post_date_gmt' => gmdate('Y-m-d H:i:s', $now)], true);
        if (is_wp_error($result)) return ['status' => 'error', 'message' => $result->get_error_message()];
        if (get_post_status($id) !== 'publish') return ['status' => 'error', 'message' => 'Publication status was not persisted.'];
        update_option('sy3_editorial_last_publication', $now, false);
        update_post_meta($id, '_sy3_editorial_enabled', '0');
        update_post_meta($id, '_sy3_editorial_published_at', $now);
        return ['status' => 'published', 'id' => $id, 'at' => $now];
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

add_filter('manage_post_posts_columns', function ($columns) { $columns['sy3_calendar'] = 'Автопубликация'; return $columns; });
add_action('manage_post_posts_custom_column', function ($column, $id) {
    if ($column !== 'sy3_calendar' || get_post_meta($id, '_sy3_editorial_batch', true) !== SY3_EDITORIAL_BATCH) return;
    $at = (int) get_post_meta($id, '_sy3_editorial_at', true);
    echo esc_html(sy3_editorial_eligible((int) $id) ? wp_date('d.m.Y H:i', $at) . ' МСК' : 'Снято с очереди / опубликовано');
}, 10, 2);

add_action('add_meta_boxes_post', function ($post) {
    if (get_post_meta($post->ID, '_sy3_editorial_batch', true) !== SY3_EDITORIAL_BATCH) return;
    add_meta_box('sy3-calendar', 'Редакционный календарь', function ($post) {
        $at = (int) get_post_meta($post->ID, '_sy3_editorial_at', true);
        wp_nonce_field('sy3-calendar-' . $post->ID, 'sy3_calendar_nonce');
        echo '<p>Место в очереди: ' . esc_html(wp_date('d.m.Y H:i', $at)) . ' МСК.</p><p>Публикуется актуальная сохранённая версия. После перерыва очередь продолжает выходить с интервалом три дня.</p>';
        echo '<label><input type="checkbox" name="sy3_editorial_enabled" value="1" ' . checked(get_post_meta($post->ID, '_sy3_editorial_enabled', true), '1', false) . '> Публиковать автоматически</label>';
    }, 'post', 'side');
});
add_action('save_post_post', function ($id) {
    if (!isset($_POST['sy3_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sy3_calendar_nonce'])), 'sy3-calendar-' . $id)) return;
    if (!current_user_can('edit_post', $id) || wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    if (get_post_meta($id, '_sy3_editorial_batch', true) !== SY3_EDITORIAL_BATCH) return;
    update_post_meta($id, '_sy3_editorial_enabled', isset($_POST['sy3_editorial_enabled']) ? '1' : '0');
});

add_action('admin_menu', function () {
    add_submenu_page('edit.php', 'Редакционный календарь', 'Календарь публикаций', 'manage_options', 'sy3-editorial', function () {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['sy3_calendar_toggle'])) {
            check_admin_referer('sy3-calendar-toggle');
            update_option('sy3_editorial_paused', !get_option('sy3_editorial_paused', false), false);
        }
        $paused = (bool) get_option('sy3_editorial_paused', false);
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'draft', 'posts_per_page' => 100, 'meta_query' => [
            ['key' => '_sy3_editorial_batch', 'value' => SY3_EDITORIAL_BATCH], ['key' => '_sy3_editorial_enabled', 'value' => '1'],
        ], 'meta_key' => '_sy3_editorial_at', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        echo '<div class="wrap"><h1>Календарь публикаций</h1><p>Статус: <strong>' . ($paused ? 'пауза' : 'автопубликация включена') . '</strong>. В очереди: ' . count($posts) . '. Интервал — три дня; время московское.</p><p>Статьи остаются черновиками до выпуска. Редактируйте их как обычные записи. Снять отдельный материал можно в блоке «Редакционный календарь» редактора.</p>';
        echo '<form method="post">'; wp_nonce_field('sy3-calendar-toggle');
        echo '<button class="button" name="sy3_calendar_toggle" value="1">' . ($paused ? 'Возобновить очередь' : 'Приостановить всю очередь') . '</button></form>';
        $check = get_option('sy3_editorial_last_check', []);
        $labels = ['nothing_due' => 'время выпуска ещё не наступило', 'interval' => 'выдерживается интервал между публикациями', 'published' => 'статья опубликована', 'paused' => 'очередь приостановлена', 'locked' => 'проверка уже выполняется', 'being_edited' => 'статья открыта в редакторе', 'incomplete' => 'нужно проверить текст и обложку', 'changed' => 'состояние статьи изменилось', 'error' => 'публикация требует проверки', 'wrong_site' => 'проверьте настройки сайта'];
        if ($check) echo '<p>Последняя проверка: ' . esc_html(wp_date('d.m.Y H:i', (int) $check['at'])) . '. Результат: ' . esc_html($labels[$check['status']] ?? 'проверьте очередь') . '.</p>';
        echo '<p>При простое пропущенные статьи не выходят залпом: очередь продолжается с интервалом три дня. Даты ниже — плановые места; после задержки фактический выпуск может быть позже.</p><table class="widefat striped"><thead><tr><th>Плановое место</th><th>Статья</th></tr></thead><tbody>';
        foreach ($posts as $post) echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i', (int) get_post_meta($post->ID, '_sy3_editorial_at', true))) . '</td><td><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a></td></tr>';
        echo '</tbody></table></div>';
    });
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sy3-editorial publish-due', function () {
        if (wp_get_environment_type() !== 'production' && getenv('SY3_EDITORIAL_TEST') !== '1') WP_CLI::error('Non-production publisher requires test mode.');
        require_once ABSPATH . 'wp-admin/includes/post.php';
        $result = sy3_editorial_publish_due();
        update_option('sy3_editorial_last_check', ['at' => time(), 'status' => $result['status']], false);
        WP_CLI::log(wp_json_encode($result, JSON_UNESCAPED_UNICODE));
        if (in_array($result['status'], ['error', 'incomplete', 'wrong_site'], true)) WP_CLI::halt(1);
    });
}
