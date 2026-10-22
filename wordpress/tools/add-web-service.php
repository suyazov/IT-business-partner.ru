<?php
/** Main-site-only service addition; existing prose is protected by its seed hash. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Production authority required.');
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Only sy3.ru is in scope.');
require_once __DIR__ . '/../content/home.php';
require_once __DIR__ . '/../content/pages.php';
$service = array_values(array_filter(json_decode(file_get_contents(__DIR__ . '/../content/services.json'), true, 512, JSON_THROW_ON_ERROR), fn($r) => $r['slug'] === 'web-development'));
if (count($service) !== 1) WP_CLI::error('Expected one web service definition.');
$service = $service[0];
$definitions = sy3_page_definitions();
$changes = ['page:home' => ['page', sy3_home_content()], 'page:about' => ['page', $definitions['about']['content']]];
$ids = [];
foreach ($changes as $key => [$type, $body]) {
    $posts = get_posts(['post_type' => $type, 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => $key, 'numberposts' => 2]);
    if (count($posts) !== 1) WP_CLI::error('Expected one owned page: ' . $key);
    $post = $posts[0];
    if (!hash_equals((string) get_post_meta($post->ID, '_sy3_seed_hash', true), hash('sha256', $post->post_content))) WP_CLI::error('Manual edits preserved: ' . $key);
    if ($key === 'page:home' && !in_array($post->post_excerpt, ['Связываю заявки, CRM и рабочие сервисы в понятную систему.', 'Создание сайтов, веб-сервисов и автоматизация бизнеса: CRM, интеграции и ИИ.'], true)) WP_CLI::error('Manual home excerpt preserved.');
    $ids[$key] = $post->ID;
}
$existing = get_posts(['post_type' => 'sy3_service', 'post_status' => 'any', 'meta_key' => '_sy3_seed', 'meta_value' => 'service:web-development', 'numberposts' => 2]);
$collision = get_page_by_path('web-development', OBJECT, 'sy3_service');
if (count($existing) > 1 || ($collision && (!$existing || $collision->ID !== $existing[0]->ID))) WP_CLI::error('Unowned or duplicate service slug.');
if ($existing && !hash_equals((string) get_post_meta($existing[0]->ID, '_sy3_seed_hash', true), hash('sha256', $existing[0]->post_content))) WP_CLI::error('Manual service edits preserved.');
if ($existing && ($existing[0]->post_title !== $service['title'] || $existing[0]->post_excerpt !== $service['excerpt'])) WP_CLI::error('Manual service title/excerpt preserved.');
$cases = [];
foreach (json_decode(file_get_contents(__DIR__ . '/../content/cases.json'), true) as $record) {
    if (!in_array('service:web-development', $record['related'], true)) continue;
    $case = get_page_by_path($record['slug'], OBJECT, 'sy3_case');
    if (!$case) WP_CLI::error('Missing case: ' . $record['slug']);
    $cases[] = $case->ID;
}
if (getenv('SY3_IMPORT_DRY_RUN') === '1') { WP_CLI::success('Preflight: two owned pages, one web service, ' . count($cases) . ' case relations; no writes.'); return; }
$body = sy3_sections($service['sections']);
$args = ['post_type' => 'sy3_service', 'post_status' => $existing ? $existing[0]->post_status : 'publish', 'post_name' => 'web-development', 'post_title' => $service['title'], 'post_excerpt' => $service['excerpt'], 'post_content' => $body, 'menu_order' => 0, 'comment_status' => 'closed', 'ping_status' => 'closed'];
if ($existing) $args['ID'] = $existing[0]->ID;
$id = wp_insert_post(wp_slash($args), true);
if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
foreach (['_sy3_seed' => 'service:web-development', '_sy3_seed_hash' => hash('sha256', $body), '_sy3_label' => $service['label'], '_sy3_description' => $service['excerpt'], '_sy3_art' => $service['art']] as $key => $value) update_post_meta($id, $key, $value);
foreach ($changes as $key => [$type, $content]) {
    $result = wp_update_post(wp_slash(['ID' => $ids[$key], 'post_content' => $content]), true);
    if (is_wp_error($result)) WP_CLI::error($result->get_error_message());
    update_post_meta($ids[$key], '_sy3_seed_hash', hash('sha256', get_post_field('post_content', $ids[$key])));
}
wp_update_post(['ID' => $ids['page:home'], 'post_excerpt' => 'Создание сайтов, веб-сервисов и автоматизация бизнеса: CRM, интеграции и ИИ.']);
$related = [];
foreach (['chitayka', 'postavki26', 'glosscheck'] as $slug) { $case = get_page_by_path($slug, OBJECT, 'sy3_case'); if ($case) $related[] = $case->ID; }
update_post_meta($id, '_sy3_related', implode(',', $related));
// Only navigation metadata changes; case/article bodies, covers and statuses remain editor-owned.
foreach ($cases as $case_id) {
    $prior = array_filter(array_map('absint', explode(',', (string) get_post_meta($case_id, '_sy3_related', true))));
    update_post_meta($case_id, '_sy3_related', implode(',', array_slice(array_unique(array_merge([$id], $prior)), 0, 3)));
}
for ($n = 61; $n <= 70; $n++) {
    $posts = get_posts(['post_type' => 'post', 'post_status' => ['draft', 'publish', 'future', 'pending', 'private'], 'meta_query' => [['key' => '_sy3_editorial_batch', 'value' => 'sy3-expertise-20261022'], ['key' => '_sy3_editorial_key', 'value' => sprintf('%03d', $n)]], 'numberposts' => 1]);
    if (!$posts) continue;
    $prior = array_filter(array_map('absint', explode(',', (string) get_post_meta($posts[0]->ID, '_sy3_related', true))));
    update_post_meta($posts[0]->ID, '_sy3_related', implode(',', array_slice(array_unique(array_merge([$id], $prior)), 0, 3)));
}
WP_CLI::success('Web service, main/about pages and related navigation updated. Editorial schedule unchanged.');
