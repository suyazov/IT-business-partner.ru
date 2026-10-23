<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/inc/article-support.php';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_theme_support('align-wide');
    add_theme_support('wp-block-styles');
    add_post_type_support('page', 'excerpt');
    add_editor_style('assets/editor.css');
    register_nav_menus(['primary' => 'Основное меню', 'footer' => 'Меню в подвале']);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('sy3-site', get_theme_file_uri('assets/design.css'), [], wp_get_theme()->get('Version'));
    if (!sy3_personal()) {
        wp_enqueue_style('sy3-refinement', get_theme_file_uri('assets/refinement.css'), ['sy3-site'], wp_get_theme()->get('Version'));
        wp_enqueue_style('sy3-direction', get_theme_file_uri('assets/direction.css'), ['sy3-refinement'], wp_get_theme()->get('Version'));
        wp_enqueue_style('sy3-process-maps', get_theme_file_uri('assets/process-maps.css'), ['sy3-direction'], wp_get_theme()->get('Version'));
        wp_enqueue_script('sy3-motion', get_theme_file_uri('assets/motion.js'), ['sy3-site'], wp_get_theme()->get('Version'), true);
    }
    wp_enqueue_script('sy3-site', get_theme_file_uri('assets/site.js'), [], wp_get_theme()->get('Version'), true);
    if (is_front_page() && sy3_personal()) wp_enqueue_script('sy3-core', get_theme_file_uri('assets/core-visual.js'), [], wp_get_theme()->get('Version'), true);
});

add_shortcode('sy3_featured_cases', function (): string {
    ob_start();
    echo '<div class="card-grid cases-grid curated-cases">';
    foreach (['chitayka', 'stilspace', 'glosscheck', 'libertygas', 'matklass', 'bitrix24-doors'] as $slug) {
        $item = get_page_by_path($slug, OBJECT, 'sy3_case');
        if ($item && $item->post_status === 'publish') sy3_post_card($item);
    }
    echo '</div>';
    return ob_get_clean();
});

add_shortcode('sy3_core_services', function (): string {
    ob_start(); echo '<div class="card-grid core-services">';
    foreach (['web-development','bitrix24','integrations','ai-automation','1c-integration','bi-dashboards'] as $slug) {
        $item=get_page_by_path($slug,OBJECT,'sy3_service');
        if ($item && $item->post_status==='publish') sy3_post_card($item);
    }
    echo '</div>'; return ob_get_clean();
});

// Presentation only: leave editor-owned blocks and seed hashes intact.
add_filter('the_content',fn($content)=>sy3_personal()?$content:str_replace(['↗','↓'],'',$content),20);

add_shortcode('sy3_visual', function ($attributes): string {
    $a = shortcode_atts(['mode' => 'business'], $attributes);
    $mode = $a['mode'] === 'personal' ? 'personal' : 'business';
    return '<figure class="system-visual" data-mode="' . $mode . '">'
        . '<div class="visual-glow" aria-hidden="true"></div>'
        . '<img class="visual-fallback" src="' . esc_url(get_theme_file_uri('assets/system-core.svg')) . '" alt="" aria-hidden="true" width="640" height="640">'
        . '<canvas role="img" aria-label="Анимированная объёмная композиция: связанные элементы одной системы" width="640" height="640"></canvas>'
        . '<span class="visual-coordinate coordinate-top" aria-hidden="true">СВЯЗЬ / 01—03</span>'
        . '<span class="visual-node node-crm" aria-hidden="true"><i></i> CRM</span>'
        . '<span class="visual-node node-api" aria-hidden="true"><i></i> Интеграции</span>'
        . '<span class="visual-node node-ai" aria-hidden="true"><i></i> ИИ</span>'
        . '<figcaption>' . ($mode === 'personal' ? 'На стыке бизнеса и технологий' : 'От отдельных сервисов — к одной системе') . '</figcaption>'
        . '<button class="visual-pause" type="button" aria-pressed="false" aria-label="Остановить анимацию">Пауза <span aria-hidden="true">Ⅱ</span></button>'
        . '</figure>';
});

function sy3_personal(): bool { return get_option('sy3_site_mode') === 'personal'; }
function sy3_main_url(string $path = ''): string {
    return sy3_personal() ? rtrim(get_option('sy3_main_url', 'https://sy3.ru'), '/') . '/' . ltrim($path, '/') : home_url('/' . ltrim($path, '/'));
}
function sy3_label(): string { return sy3_personal() ? 'Артём Суязов' : 'сутри'; }
function sy3_contact_url(): string { return sy3_personal() ? home_url('/contacts/') : (function_exists('sy3_leads_form') ? '#request-form' : 'https://t.me/sy3_support_bot?start=site_sy3'); }

// Keep editor-owned article bodies intact while routing public contact links to the inbox.
add_filter('the_content', fn($content) => sy3_personal() ? $content : str_replace(
    ['https://t.me/moi_imperator', 'Обсудить задачу с Артёмом', 'artem.suyazov@gmail.com'],
    ['https://t.me/sy3_support_bot?start=site_sy3', 'Обсудить проект', 'help@sy3.ru'],
    $content
), 19);

function sy3_post_card(WP_Post $item): void {
    $kind = ['sy3_case' => 'Кейс', 'sy3_service' => 'Направление', 'post' => 'Разбор', 'sy3_legacy' => 'Архив проекта'][$item->post_type] ?? 'Материал';
    $topic = get_post_meta($item->ID, '_sy3_label', true) ?: $kind;
    $art = get_post_meta($item->ID, '_sy3_art', true);
    $safe_art = in_array($art, ['crm', 'integration', 'ai', 'antispam', 'matklass'], true) ? $art : '';
    ?>
    <article class="entry-card <?php echo esc_attr($item->post_type); ?>">
        <?php if (has_post_thumbnail($item)): ?><a class="card-art portfolio-cover" tabindex="-1" aria-hidden="true" href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo get_the_post_thumbnail($item, 'large', ['alt' => '', 'loading' => 'lazy']); ?></a>
        <?php elseif ($safe_art): ?><a class="card-art art-<?php echo esc_attr($safe_art); ?>" tabindex="-1" aria-hidden="true" href="<?php echo esc_url(get_permalink($item)); ?>"><img loading="lazy" width="640" height="360" src="<?php echo esc_url(get_theme_file_uri('assets/' . $safe_art . '.svg')); ?>" alt=""></a><?php endif; ?>
        <div class="card-copy"><p class="eyebrow"><?php echo esc_html($topic); ?></p>
        <h3><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></h3>
        <p><?php echo esc_html(get_the_excerpt($item)); ?></p>
        <?php $proof = get_post_meta($item->ID, '_sy3_proof', true); if (is_array($proof) && $proof): ?><div class="case-proof"><?php foreach ($proof as $fact): ?><span><?php echo esc_html($fact); ?></span><?php endforeach; ?></div><?php endif; ?>
        <a class="text-link" href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo $item->post_type === 'sy3_case' ? 'Читать кейс' : 'Подробнее'; ?></a></div>
    </article>
    <?php
}

add_filter('excerpt_length', fn() => 27);
add_filter('excerpt_more', fn() => '…');

add_filter('document_title_parts', function ($parts) {
    if (is_front_page()) return ['title' => sy3_personal() ? 'Артём Суязов — IT-бизнес-партнёр' : 'Создание сайтов, веб-сервисов и автоматизация бизнеса — Сутри'];
    return $parts;
});
