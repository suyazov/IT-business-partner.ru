<?php
/**
 * Plugin Name: Сутри — контент и навигация
 * Description: Услуги, кейсы, связи материалов, SEO и фиды. Контент сохраняется при смене темы.
 * Version: 1.0.0
 * Requires PHP: 8.1
 */
defined('ABSPATH') || exit;

function sy3_register_content(): void {
    foreach (['sy3_service' => ['Услуги', 'Услуга', 'services'], 'sy3_case' => ['Кейсы', 'Кейс', 'cases']] as $type => $args) {
        register_post_type($type, [
            'labels' => ['name' => $args[0], 'singular_name' => $args[1], 'add_new_item' => 'Добавить: ' . $args[1], 'edit_item' => 'Редактировать: ' . $args[1]],
            'public' => !sy3_is_personal(), 'show_ui' => true, 'show_in_rest' => true,
            'has_archive' => $args[2], 'rewrite' => ['slug' => $args[2], 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
            'menu_icon' => $type === 'sy3_case' ? 'dashicons-portfolio' : 'dashicons-networking',
        ]);
    }
    register_taxonomy('sy3_case_topic', 'sy3_case', ['label' => 'Направления кейсов', 'public' => !sy3_is_personal(), 'show_ui' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'case-topics', 'with_front' => false]]);
    if (sy3_is_personal()) register_post_type('sy3_legacy', [
        'labels' => ['name' => 'Архив проектов', 'singular_name' => 'Архивный проект'],
        'public' => true, 'show_in_rest' => true, 'has_archive' => false,
        'rewrite' => ['slug' => 'portfolio', 'with_front' => false],
        'supports' => ['title', 'editor', 'excerpt', 'revisions'], 'menu_icon' => 'dashicons-archive',
    ]);
    register_post_meta('', '_sy3_description', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => fn() => current_user_can('edit_posts')]);
    add_rewrite_rule('^llms\.txt$', 'index.php?sy3_feed=llms', 'top');
    add_rewrite_rule('^sales/portfolio-feed\.json$', 'index.php?sy3_feed=portfolio', 'top');
}
function sy3_is_personal(): bool { return get_option('sy3_site_mode') === 'personal'; }
add_action('init', 'sy3_register_content');
add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_post_type_archive('sy3_service')) {
        $query->set('orderby', ['menu_order' => 'ASC', 'ID' => 'ASC']);
        $query->set('posts_per_page', 50);
    }
    if (!is_admin() && $query->is_main_query() && ($query->is_post_type_archive('sy3_case') || $query->is_tax('sy3_case_topic'))) {
        $query->set('orderby', ['menu_order' => 'ASC', 'ID' => 'ASC']);
    }
});
register_activation_hook(__FILE__, function () { sy3_register_content(); flush_rewrite_rules(); });
add_filter('query_vars', function ($vars) { $vars[] = 'sy3_feed'; return $vars; });

// Keep the editor's author selector available without exposing the user collection.
// Check capability at REST dispatch so a JSON body cannot override a query guard.
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if (strcasecmp(untrailingslashit($request->get_route()), '/wp/v2/users') === 0 && !current_user_can('list_users')) {
        return new WP_Error('sy3_user_list_forbidden', 'Недостаточно прав для просмотра пользователей.', ['status' => rest_authorization_required_code()]);
    }
    return $result;
}, 10, 3);

add_shortcode('sy3_cards', function ($atts): string {
    $a = shortcode_atts(['type' => 'post', 'count' => 3, 'class' => '', 'ids' => ''], $atts);
    if (!in_array($a['type'], ['post', 'sy3_service', 'sy3_case', 'sy3_legacy'], true)) return '';
    $query = ['post_type' => $a['type'], 'post_status' => 'publish', 'posts_per_page' => min(12, max(1, (int) $a['count'])), 'orderby' => $a['type'] === 'post' ? 'date' : 'menu_order', 'order' => $a['type'] === 'post' ? 'DESC' : 'ASC', 'no_found_rows' => true];
    if ($a['ids']) { $query['post__in'] = array_filter(array_map('absint', explode(',', $a['ids']))); $query['orderby'] = 'post__in'; }
    $items = get_posts($query);
    ob_start();
    echo '<div class="card-grid ' . esc_attr(sanitize_html_class($a['class'])) . '">';
    foreach ($items as $item) if (function_exists('sy3_post_card')) sy3_post_card($item);
    echo '</div>';
    return ob_get_clean();
});

add_shortcode('sy3_external_cases', function ($atts): string {
    $items = get_option('sy3_external_cases', []);
    $a = shortcode_atts(['count' => 4], $atts);
    $items = array_slice($items, 0, min(100, max(1, (int) $a['count'])));
    ob_start(); echo '<div class="card-grid cases-grid">';
    foreach ($items as $item) { ?>
        <article class="entry-card"><div class="card-copy"><p class="eyebrow"><?php echo esc_html($item['label']); ?></p><h3><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a></h3><p><?php echo esc_html($item['excerpt']); ?></p><a class="text-link" href="<?php echo esc_url($item['url']); ?>">Читать на сайте Сутри ↗</a></div></article>
    <?php }
    echo '</div>'; return ob_get_clean();
});

function sy3_related(int $id): void {
    $ids = array_filter(array_map('absint', explode(',', (string) get_post_meta($id, '_sy3_related', true))));
    if (!$ids) return;
    $items = get_posts(['post__in' => $ids, 'post_type' => ['sy3_service', 'sy3_case', 'post'], 'orderby' => 'post__in', 'post_status' => 'publish', 'posts_per_page' => 3]);
    if (!$items) return;
    echo '<section class="related-section wrap"><p class="eyebrow">Продолжить разбор</p><h2>По теме</h2><div class="card-grid">';
    foreach ($items as $item) if (function_exists('sy3_post_card')) sy3_post_card($item);
    echo '</div></section>';
}

add_action('wp_head', function () {
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) return;
    $description = is_singular() ? (get_post_meta(get_queried_object_id(), '_sy3_description', true) ?: get_the_excerpt(get_queried_object_id())) : get_bloginfo('description');
    if (!$description) $description = get_bloginfo('description');
    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink() : get_pagenum_link(max(1, (int) get_query_var('paged')), false);
    echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($description)) . '">' . "\n";
    foreach (['og:locale' => 'ru_RU', 'og:type' => is_singular('post') ? 'article' : 'website', 'og:site_name' => get_bloginfo('name'), 'og:title' => $title, 'og:description' => wp_strip_all_tags($description), 'og:url' => $url] as $property => $value) echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($value) . '">' . "\n";
    echo '<meta name="theme-color" content="#202923">' . "\n";
    if (!is_singular() && !is_404() && !is_search()) echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    if (is_singular('post')) {
        $data = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => get_the_title(), 'description' => wp_strip_all_tags($description), 'mainEntityOfPage' => get_permalink(), 'datePublished' => get_the_date(DATE_W3C), 'dateModified' => get_the_modified_date(DATE_W3C), 'author' => ['@type' => 'Person', 'name' => 'Артём Суязов', 'url' => home_url('/about/')]];
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
    if (is_singular() && has_post_thumbnail()) echo '<meta property="og:image" content="' . esc_url(get_the_post_thumbnail_url(null, 'large')) . '">' . "\n";
    if (is_singular('sy3_service')) {
        $data = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => get_the_title(), 'description' => wp_strip_all_tags($description), 'url' => get_permalink(), 'provider' => ['@type' => 'Person', 'name' => 'Артём Суязов', 'url' => home_url('/about/')]];
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}, 5);

add_action('template_redirect', function () {
    $feed = get_query_var('sy3_feed');
    if (!$feed) return;
    if (sy3_is_personal()) {
        $items = get_posts(['post_type' => 'sy3_legacy', 'posts_per_page' => -1, 'post_status' => 'publish']);
        $cases = array_merge(get_option('sy3_external_cases', []), array_map(fn($p) => ['slug' => $p->post_name, 'title' => get_the_title($p), 'url' => get_permalink($p), 'excerpt' => get_the_excerpt($p)], $items));
        if ($feed === 'portfolio') wp_send_json(['site' => home_url('/'), 'contact' => home_url('/contacts/'), 'cases' => $cases], 200, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($feed === 'llms') {
            header('Content-Type: text/plain; charset=utf-8');
            echo '# Артём Суязов — IT-бизнес-партнёр' . "\n\n";
            foreach ($cases as $item) echo '- [' . $item['title'] . '](' . $item['url'] . ")\n";
            exit;
        }
    }
    $items = get_posts(['post_type' => 'sy3_case', 'posts_per_page' => -1, 'post_status' => 'publish']);
    if ($feed === 'portfolio') {
        $cases = array_map(fn($p) => ['slug' => $p->post_name, 'title' => get_the_title($p), 'url' => get_permalink($p), 'excerpt' => get_the_excerpt($p)], $items);
        wp_send_json(['site' => home_url('/'), 'contact' => home_url('/contacts/'), 'cases' => $cases], 200, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($feed === 'llms') {
        header('Content-Type: text/plain; charset=utf-8');
        echo '# ' . get_bloginfo('name') . "\n\n> Сайты, веб-сервисы, CRM, интеграции и ИИ для бизнеса. Автор: Артём Суязов.\n\n";
        foreach (['Услуги' => '/services/', 'Кейсы' => '/cases/', 'Журнал' => '/journal/', 'Контакты' => '/contacts/'] as $label => $path) echo '- [' . $label . '](' . home_url($path) . ")\n";
        echo "\n## Услуги\n\n";
        foreach (get_posts(['post_type' => 'sy3_service', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']) as $service) echo '- [' . get_the_title($service) . '](' . get_permalink($service) . '): ' . get_the_excerpt($service) . "\n";
        echo "\n## Кейсы\n\n";
        foreach ($items as $item) echo '- [' . get_the_title($item) . '](' . get_permalink($item) . '): ' . get_the_excerpt($item) . "\n";
        exit;
    }
}, 0);

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) return;
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $redirects = ['index.html' => '/', 'services.html' => '/services/', 'portfolio.html' => '/cases/', 'about.html' => '/about/', 'contact.html' => '/contacts/', 'bitrix24.html' => '/services/bitrix24/', 'sitemap.xml' => '/wp-sitemap.xml'];
    $key = ltrim((string) $path, '/');
    if (sy3_is_personal()) {
        $base = rtrim(get_option('sy3_main_url', 'https://sy3.ru'), '/');
        if (preg_match('#^blog/([a-z0-9-]+)/?$#', $key, $old)) {
            $map = get_option('sy3_legacy_post_redirects', []);
            if (isset($map[$old[1]])) { wp_redirect($base . '/journal/' . $map[$old[1]] . '/', 301, 'SY3 journal migration'); exit; }
        }
        if (rtrim($key, '/') === 'blog') { wp_redirect($base . '/journal/', 301, 'SY3 journal migration'); exit; }
    }
    if (isset($redirects[$key])) { wp_safe_redirect(home_url($redirects[$key]), 301); exit; }
    if (sy3_is_personal() && preg_match('#^portfolio/([a-z0-9-]+)/?$#', $key, $m) && in_array($m[1], array_column(get_option('sy3_external_cases', []), 'slug'), true)) {
        $base = rtrim(get_option('sy3_main_url', 'https://sy3.ru'), '/');
        wp_redirect($base . '/cases/' . $m[1] . '/', 301, 'SY3 portfolio migration'); exit;
    }
}, 1);

add_filter('wp_robots', function ($robots) {
    if (defined('SY3_PREVIEW') && SY3_PREVIEW) { $robots['noindex'] = true; $robots['nofollow'] = true; unset($robots['index'], $robots['follow']); }
    return $robots;
});
