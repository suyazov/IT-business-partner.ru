<?php
/** wp eval-file wordpress/tools/import-content.php --allow-root */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Production import requires separate exact rollout authority.');
require_once __DIR__ . '/../content/blocks.php';
require_once __DIR__ . '/../content/home.php';
require_once __DIR__ . '/../content/pages.php';
$personal = getenv('SY3_SITE_MODE') === 'personal';
$dry = getenv('SY3_IMPORT_DRY_RUN') === '1';
if ($dry) {
    foreach (['services', 'cases', 'articles'] as $file) {
        $records = json_decode(file_get_contents(__DIR__ . '/../content/' . $file . '.json'), true, 512, JSON_THROW_ON_ERROR);
        WP_CLI::log($file . ': ' . count($records));
    }
    WP_CLI::success('Dry run: no options, posts or menus changed.'); return;
}
$expected = $personal ? 'personal' : 'expertise';
$prior = get_option('sy3_site_mode');
if ($prior && $prior !== $expected) WP_CLI::error('Refusing to change site identity in place.');
if (!$prior && (get_option('show_on_front') === 'page' || get_posts(['post_type' => 'page', 'meta_key' => '_sy3_seed', 'numberposts' => 1]))) WP_CLI::error('Existing site needs an explicit migration plan before import.');
update_option('sy3_site_mode', $expected);
if ($personal) update_option('sy3_main_url', rtrim(getenv('SY3_MAIN_URL') ?: 'https://sy3.ru', '/'));
sy3_register_content();
$GLOBALS['sy3_import_ids'] = [];
$ids =& $GLOBALS['sy3_import_ids'];
function sy3_import_post(string $key, array $args): int {
    $ids =& $GLOBALS['sy3_import_ids'];
    $existing = get_posts(['post_type' => ['page', 'post', 'sy3_service', 'sy3_case', 'sy3_legacy'], 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => $key, 'numberposts' => 1]);
    $collision = get_page_by_path($args['post_name'], OBJECT, $args['post_type']);
    if (!$existing && $collision) WP_CLI::error('Unowned slug collision: ' . $key);
    $args['post_status'] = 'publish';
    $args['comment_status'] = 'closed';
    $args['ping_status'] = 'closed';
    if ($existing) {
        $id = $existing[0]->ID;
        if (getenv('SY3_IMPORT_UPDATE') !== '1') { $ids[$key] = $id; return $id; }
        $saved = get_post_meta($id, '_sy3_seed_hash', true);
        if (!$saved || !hash_equals($saved, hash('sha256', $existing[0]->post_content))) WP_CLI::error('Manual content edits preserved; cannot overwrite ' . $key);
        $args['ID'] = $id;
    }
    $id = wp_insert_post(wp_slash($args), true);
    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
    update_post_meta($id, '_sy3_seed', $key);
    update_post_meta($id, '_sy3_seed_hash', hash('sha256', get_post_field('post_content', $id)));
    $ids[$key] = $id;
    WP_CLI::log($key . ' → ' . $id); return $id;
}

$home = sy3_import_post('page:home', ['post_type' => 'page', 'post_name' => 'home', 'post_title' => $personal ? 'Артём Суязов — IT-бизнес-партнёр' : 'Автоматизация бизнеса: CRM, интеграции и ИИ', 'post_excerpt' => $personal ? 'Помогаю бизнесу разобраться с технологиями и довести решения до работы.' : 'Связываю заявки, CRM и рабочие сервисы в понятную систему.', 'post_content' => $personal ? sy3_personal_home() : sy3_home_content()]);
update_option('show_on_front', 'page'); update_option('page_on_front', $home);
update_option('blogname', $personal ? 'Артём Суязов — IT-бизнес-партнёр' : 'Сутри — автоматизация бизнеса');
update_option('blogdescription', 'CRM, интеграции и ИИ. Разбор процесса, внедрение и проверка рабочего сценария.');
update_option('permalink_structure', '/journal/%postname%/');
update_option('timezone_string', 'Europe/Moscow');
update_option('date_format', 'j F Y'); update_option('default_comment_status', 'closed');
update_option('posts_per_page', 9);
foreach (sy3_page_definitions() as $slug => $page) {
    sy3_import_post('page:' . $slug, ['post_type' => 'page', 'post_name' => $slug, 'post_title' => $page['title'], 'post_excerpt' => $page['excerpt'], 'post_content' => $page['content']]);
}
if (!$personal) {
    $journal = sy3_import_post('page:journal', ['post_type' => 'page', 'post_name' => 'journal', 'post_title' => 'Журнал', 'post_excerpt' => 'CRM, интеграции и ИИ через задачи бизнеса.', 'post_content' => '']);
    update_option('page_for_posts', $journal);
    foreach (['services' => 'sy3_service', 'cases' => 'sy3_case', 'articles' => 'post'] as $file => $type) {
        $items = json_decode(file_get_contents(__DIR__ . '/../content/' . $file . '.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($items as $index => $item) {
            $key = ($type === 'sy3_service' ? 'service:' : ($type === 'sy3_case' ? 'case:' : 'post:')) . $item['slug'];
            $id = sy3_import_post($key, ['post_type' => $type, 'post_name' => $item['slug'], 'post_title' => $item['title'], 'post_excerpt' => $item['excerpt'], 'post_content' => sy3_sections($item['sections']), 'menu_order' => $index + 1]);
            update_post_meta($id, '_sy3_label', $item['label'] ?? $item['category'] ?? 'Разбор');
            update_post_meta($id, '_sy3_art', $item['art']);
            update_post_meta($id, '_sy3_description', $item['excerpt']);
            if (isset($item['source'])) update_post_meta($id, '_sy3_case_source', esc_url_raw($item['source']));
            if (isset($item['category'])) {
                $term = term_exists($item['category'], 'category') ?: wp_insert_term($item['category'], 'category');
                if (!is_wp_error($term)) wp_set_post_categories($id, [(int) $term['term_id']]);
            }
        }
    }
    foreach (['services', 'cases', 'articles'] as $file) {
        foreach (json_decode(file_get_contents(__DIR__ . '/../content/' . $file . '.json'), true) as $item) {
            $prefix = $file === 'services' ? 'service:' : ($file === 'cases' ? 'case:' : 'post:');
            $related = array_values(array_filter(array_map(fn($key) => $ids[$key] ?? null, $item['related'] ?? [])));
            update_post_meta($ids[$prefix . $item['slug']], '_sy3_related', implode(',', $related));
        }
    }
} else {
    $records = json_decode(file_get_contents(__DIR__ . '/../content/cases.json'), true, 512, JSON_THROW_ON_ERROR);
    update_option('sy3_external_cases', array_map(fn($i) => ['slug' => $i['slug'], 'title' => $i['title'], 'label' => $i['label'], 'excerpt' => $i['excerpt'], 'url' => rtrim(get_option('sy3_main_url'), '/') . '/cases/' . $i['slug'] . '/'], $records));
    $selected = array_column($records, 'slug');
    foreach (json_decode(file_get_contents(__DIR__ . '/../content/legacy-cases.json'), true, 512, JSON_THROW_ON_ERROR) as $case) {
        if (in_array($case['slug'], $selected, true)) continue;
        $content = sy3_g(sy3_p('Сохранённый материал предыдущей версии сайта. Повторная проверка результатов в рамках переноса не проводилась.'), 'callout') . sy3_p(esc_html($case['description'])) . sy3_h('Работы по проекту') . sy3_list($case['services']) . sy3_h('Результаты, указанные в исходном материале') . sy3_list($case['results']) . sy3_h('Технологии') . sy3_p(esc_html(implode(' · ', $case['technologies'])));
        $id = sy3_import_post('legacy:' . $case['slug'], ['post_type' => 'sy3_legacy', 'post_name' => $case['slug'], 'post_title' => $case['title'], 'post_excerpt' => $case['excerpt'], 'post_content' => $content]);
        update_post_meta($id, '_sy3_label', 'Архив проекта');
    }
    sy3_import_post('page:portfolio', ['post_type' => 'page', 'post_name' => 'portfolio', 'post_title' => 'Выбранные работы', 'post_excerpt' => 'Подробные разборы двух проектов — на сайте Сутри. Остальные материалы сохранены в архиве.', 'post_content' => sy3_shortcode('[sy3_external_cases]') . sy3_h('Архив проектов') . sy3_shortcode('[sy3_cards type="sy3_legacy" count="12"]')]);
    $blog = sy3_import_post('page:blog', ['post_type' => 'page', 'post_name' => 'blog', 'post_title' => 'Архив публикаций', 'post_excerpt' => 'Публикации предыдущей версии сайта.', 'post_content' => '']);
    update_option('page_for_posts', $blog); update_option('permalink_structure', '/blog/%postname%/');
    foreach (json_decode(file_get_contents(__DIR__ . '/../content/legacy-posts.json'), true, 512, JSON_THROW_ON_ERROR) as $post) {
        $date = gmdate('Y-m-d H:i:s', strtotime($post['createdAt']));
        $id = sy3_import_post('legacy-post:' . $post['slug'], ['post_type' => 'post', 'post_name' => $post['slug'], 'post_title' => $post['title'], 'post_excerpt' => $post['excerpt'], 'post_date_gmt' => $date, 'post_date' => get_date_from_gmt($date), 'post_content' => sy3_g(sy3_p('Архивная публикация предыдущей версии сайта. Текст сохранён для доступности по прежнему адресу; его актуальность не проверялась при переносе.'), 'callout') . sy3_b('html', wp_kses_post($post['content']))]);
        update_post_meta($id, '_sy3_label', 'Архивная публикация');
    }
}

$primary = wp_get_nav_menu_object('Сутри: основное');
$menu = $primary ? $primary->term_id : wp_create_nav_menu('Сутри: основное');
if (!wp_get_nav_menu_items($menu)) {
    $links = $personal ? [['Подход', '/about/'], ['Работы', '/portfolio/'], ['Контакты', '/contacts/']] : [['Услуги', '/services/'], ['Кейсы', '/cases/'], ['Журнал', '/journal/'], ['Обо мне', '/about/']];
    foreach ($links as [$label, $url]) wp_update_nav_menu_item($menu, 0, ['menu-item-title' => $label, 'menu-item-url' => home_url($url), 'menu-item-status' => 'publish']);
}
$footer_object = wp_get_nav_menu_object('Сутри: подвал');
$footer = $footer_object ? $footer_object->term_id : wp_create_nav_menu('Сутри: подвал');
if (!wp_get_nav_menu_items($footer)) wp_update_nav_menu_item($footer, 0, ['menu-item-title' => 'О данных на сайте', 'menu-item-url' => home_url('/privacy/'), 'menu-item-status' => 'publish']);
if ($personal && count(wp_get_nav_menu_items($footer) ?: []) === 1) wp_update_nav_menu_item($footer, 0, ['menu-item-title' => 'Архив публикаций', 'menu-item-url' => home_url('/blog/'), 'menu-item-status' => 'publish']);
set_theme_mod('nav_menu_locations', ['primary' => $menu, 'footer' => $footer]);
flush_rewrite_rules();
WP_CLI::success('Content imported for ' . $expected . '. All content is editable in WordPress.');
