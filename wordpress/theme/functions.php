<?php
defined('ABSPATH') || exit;

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
    wp_enqueue_style('sy3-site', get_theme_file_uri('assets/site.css'), [], wp_get_theme()->get('Version'));
    wp_enqueue_script('sy3-site', get_theme_file_uri('assets/site.js'), [], wp_get_theme()->get('Version'), true);
});

function sy3_personal(): bool { return get_option('sy3_site_mode') === 'personal'; }
function sy3_main_url(string $path = ''): string {
    return sy3_personal() ? rtrim(get_option('sy3_main_url', 'https://sy3.ru'), '/') . '/' . ltrim($path, '/') : home_url('/' . ltrim($path, '/'));
}
function sy3_label(): string { return sy3_personal() ? 'Артём Суязов' : 'сутри'; }
function sy3_contact_url(): string { return home_url('/contacts/'); }

function sy3_post_card(WP_Post $item): void {
    $kind = ['sy3_case' => 'Кейс', 'sy3_service' => 'Направление', 'post' => 'Разбор', 'sy3_legacy' => 'Архив проекта'][$item->post_type] ?? 'Материал';
    $topic = get_post_meta($item->ID, '_sy3_label', true) ?: $kind;
    $art = get_post_meta($item->ID, '_sy3_art', true);
    $safe_art = in_array($art, ['crm', 'integration', 'ai', 'antispam', 'matklass'], true) ? $art : '';
    ?>
    <article class="entry-card <?php echo esc_attr($item->post_type); ?>">
        <?php if ($safe_art): ?><a class="card-art art-<?php echo esc_attr($safe_art); ?>" tabindex="-1" aria-hidden="true" href="<?php echo esc_url(get_permalink($item)); ?>"><img loading="lazy" width="640" height="360" src="<?php echo esc_url(get_theme_file_uri('assets/' . $safe_art . '.svg')); ?>" alt=""></a><?php endif; ?>
        <div class="card-copy"><p class="eyebrow"><?php echo esc_html($topic); ?></p>
        <h3><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></h3>
        <p><?php echo esc_html(get_the_excerpt($item)); ?></p>
        <a class="text-link" href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo $item->post_type === 'sy3_case' ? 'Читать кейс' : 'Подробнее'; ?> <span aria-hidden="true">↗</span></a></div>
    </article>
    <?php
}

add_filter('excerpt_length', fn() => 27);
add_filter('excerpt_more', fn() => '…');

add_filter('document_title_parts', function ($parts) {
    if (is_front_page()) return ['title' => sy3_personal() ? 'Артём Суязов — IT-бизнес-партнёр' : 'CRM, интеграции и ИИ для бизнеса — Сутри'];
    return $parts;
});
