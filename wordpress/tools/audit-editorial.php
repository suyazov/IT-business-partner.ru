<?php
/** Read-only batch audit. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
$posts = get_posts(['post_type' => 'post', 'post_status' => ['draft', 'pending', 'future', 'publish', 'private', 'trash'], 'posts_per_page' => -1, 'meta_key' => '_sy3_editorial_batch', 'meta_value' => SY3_EDITORIAL_BATCH]);
if (count($posts) !== 100) WP_CLI::error('Expected exactly 100 imported posts.');
$at = []; $ids = []; $statuses = []; $hashes = []; $drafts = 0;
foreach ($posts as $post) {
    $drafts += $post->post_status === 'draft' ? 1 : 0;
    $statuses[$post->post_status] = ($statuses[$post->post_status] ?? 0) + 1;
    $key = get_post_meta($post->ID, '_sy3_editorial_key', true);
    if (isset($ids[$key])) WP_CLI::error('Duplicate key.');
    $ids[$key] = $post->ID;
    $at[] = (int) get_post_meta($post->ID, '_sy3_editorial_at', true);
    if (!has_post_thumbnail($post)) WP_CLI::error('Missing cover.');
    if (!$post->post_excerpt || mb_strlen(wp_strip_all_tags($post->post_content)) < 1400) WP_CLI::error('Incomplete article.');
    if (preg_match('/(?<!\p{L})сказк\p{L}*|skazka|parkops|\/root\/|\/var\//iu', $post->post_content . $post->post_title)) WP_CLI::error('Restricted content.');
    $blocks = parse_blocks($post->post_content);
    if (count($blocks) < 10) WP_CLI::error('Unexpected article block structure.');
    if (isset($hashes[hash('sha256', $post->post_content)])) WP_CLI::error('Duplicate body.');
    $hashes[hash('sha256', $post->post_content)] = true;
}
sort($at);
for ($i = 1; $i < count($at); $i++) if ($at[$i] - $at[$i - 1] !== 3 * DAY_IN_SECONDS) WP_CLI::error('Calendar cadence mismatch.');
WP_CLI::log(wp_json_encode(['count' => count($posts), 'statuses' => $statuses, 'first_msk' => wp_date('Y-m-d H:i', $at[0]), 'last_msk' => wp_date('Y-m-d H:i', end($at)), 'unique_bodies' => count($hashes), 'covers' => 100, 'cadence_hours' => 72], JSON_UNESCAPED_UNICODE));
if (getenv('SY3_REQUIRE_ALL_DRAFTS') === '1' && $drafts !== 100) WP_CLI::error('Expected 100 drafts before first publication.');
WP_CLI::success('Read-only editorial audit passed.');
