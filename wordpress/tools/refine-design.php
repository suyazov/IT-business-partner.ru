<?php
/** Scoped design update: wp eval-file wordpress/tools/refine-design.php --allow-root */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Production authority required.');
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('This update is only for sy3.ru.');
require_once __DIR__ . '/../content/home.php';
$baseline = json_decode(file_get_contents(__DIR__ . '/../content/design-baseline.json'), true, 512, JSON_THROW_ON_ERROR);
$records = [];
$records['page:home'] = ['content' => sy3_home_content()];
foreach (['articles' => 'post:', 'cases' => 'case:'] as $file => $prefix) {
    foreach (json_decode(file_get_contents(__DIR__ . '/../content/' . $file . '.json'), true, 512, JSON_THROW_ON_ERROR) as $record) {
        if ($file === 'cases' && !in_array($record['slug'], ['bitrix24-doors', 'matklass', 'glosscheck', 'libertygas'], true)) continue;
        $records[$prefix . $record['slug']] = $record;
    }
}
// Validate the entire update before writing any content or media.
foreach ($records as $key => &$record) {
    $posts = get_posts(['post_type' => ['page', 'post', 'sy3_case'], 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => $key, 'numberposts' => 2]);
    if (count($posts) !== 1) WP_CLI::error('Expected one owned post: ' . $key);
    $record['post'] = $posts[0];
    $saved = get_post_meta($posts[0]->ID, '_sy3_seed_hash', true);
    if (!$saved || !hash_equals($saved, hash('sha256', $posts[0]->post_content))) WP_CLI::error('Manual content edit preserved: ' . $key);
    if (str_starts_with($key, 'case:')) {
        foreach (['title' => 'post_title', 'excerpt' => 'post_excerpt'] as $field => $property) {
            if (!in_array($posts[0]->$property, [$baseline[$key][$field], $record[$field]], true)) WP_CLI::error('Manual ' . $field . ' preserved: ' . $key);
        }
    }
    if (!empty($record['cover'])) {
        $path = __DIR__ . '/../content/media/' . basename($record['cover']['file']);
        if (!is_file($path) || !hash_equals($record['cover']['sha256'], hash_file('sha256', $path))) WP_CLI::error('Invalid cover: ' . $key);
        $thumbnail = get_post_thumbnail_id($posts[0]);
        $current_hash = $thumbnail ? get_post_meta($thumbnail, '_sy3_media_hash', true) : '';
        if (($thumbnail && !$current_hash) || !in_array($current_hash, [$baseline[$key]['cover_sha256'], $record['cover']['sha256']], true)) WP_CLI::error('Manual cover preserved: ' . $key);
    }
}
unset($record);
if (getenv('SY3_IMPORT_DRY_RUN') === '1') { WP_CLI::success('Preflight passed: ' . count($records) . ' owned records; no writes.'); return; }
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ($records as $key => $record) {
    $id = $record['post']->ID;
    if (isset($record['content'])) {
        $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $record['content']]), true);
        if (is_wp_error($result)) WP_CLI::error($result->get_error_message());
        update_post_meta($id, '_sy3_seed_hash', hash('sha256', get_post_field('post_content', $id)));
    }
    if (str_starts_with($key, 'case:')) {
        $result = wp_update_post(wp_slash(['ID' => $id, 'post_title' => $record['title'], 'post_excerpt' => $record['excerpt']]), true);
        if (is_wp_error($result)) WP_CLI::error($result->get_error_message());
        update_post_meta($id, '_sy3_description', $record['excerpt']);
        if (isset($record['proof'])) update_post_meta($id, '_sy3_proof', $record['proof']);
    }
    if (!empty($record['cover'])) {
        $cover = $record['cover'];
        $prior = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sy3_media_hash', 'meta_value' => $cover['sha256'], 'numberposts' => 1]);
        if ($prior) $attachment = $prior[0]->ID;
        else {
            $file = basename($cover['file']);
            $upload = wp_upload_bits($file, null, file_get_contents(__DIR__ . '/../content/media/' . $file));
            if ($upload['error']) WP_CLI::error($upload['error']);
            $attachment = wp_insert_attachment(['post_mime_type' => 'image/webp', 'post_title' => $cover['alt'], 'post_excerpt' => $cover['caption'] ?? '', 'post_status' => 'inherit'], $upload['file'], $id, true);
            if (is_wp_error($attachment)) WP_CLI::error($attachment->get_error_message());
            wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $upload['file']));
            update_post_meta($attachment, '_sy3_media_hash', $cover['sha256']);
            update_post_meta($attachment, '_wp_attachment_image_alt', $cover['alt']);
        }
        set_post_thumbnail($id, $attachment);
    }
    WP_CLI::log($key . ' updated');
}
WP_CLI::success('Main-site design content updated; statuses, article bodies and personal site preserved.');
