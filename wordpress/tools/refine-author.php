<?php
/** Main-site author presentation; preserve manual edits and every unrelated record. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Production authority required.');
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Only sy3.ru is in scope.');
require_once __DIR__ . '/../content/home.php';
require_once __DIR__ . '/../content/pages.php';
$pages = sy3_page_definitions();
$changes = ['page:home' => sy3_home_content(), 'page:about' => $pages['about']['content'], 'page:contacts' => $pages['contacts']['content']];
$ids = [];
foreach ($changes as $key => $content) {
    $posts = get_posts(['post_type' => 'page', 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => $key, 'numberposts' => 2]);
    if (count($posts) !== 1) WP_CLI::error('Expected one owned page: ' . $key);
    $post = $posts[0];
    if (!hash_equals((string) get_post_meta($post->ID, '_sy3_seed_hash', true), hash('sha256', $post->post_content))) WP_CLI::error('Manual edits preserved: ' . $key);
    $ids[$key] = $post->ID;
}
if (getenv('SY3_IMPORT_DRY_RUN') === '1') { WP_CLI::success('Preflight: three owned pages, no writes.'); return; }
foreach ($changes as $key => $content) {
    $result = wp_update_post(wp_slash(['ID' => $ids[$key], 'post_content' => $content]), true);
    if (is_wp_error($result)) WP_CLI::error($result->get_error_message());
    update_post_meta($ids[$key], '_sy3_seed_hash', hash('sha256', get_post_field('post_content', $ids[$key])));
}
WP_CLI::success('Author presentation updated on home, about and contacts. Titles, excerpts, statuses and editorial queue preserved.');
