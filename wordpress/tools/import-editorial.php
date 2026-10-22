<?php
/** Import exactly 100 owned drafts. Repeated execution never overwrites an existing draft. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
if (wp_get_environment_type() === 'production' && getenv('SY3_ALLOW_PRODUCTION_IMPORT') !== '1') WP_CLI::error('Explicit production authority required.');
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Only sy3.ru is in scope.');
if (!defined('SY3_EDITORIAL_BATCH')) WP_CLI::error('Activate the editorial plugin first.');
require_once __DIR__ . '/../content/blocks.php';

function sy3_editorial_records(): array {
    $records = [];
    foreach (glob(__DIR__ . '/../editorial/articles/*.md') as $file) {
        $raw = file_get_contents($file);
        foreach (preg_split('/^@@ /m', $raw, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            [$metadata, $text] = explode("\n", trim($chunk), 2);
            $fields = explode('|', $metadata);
            if (count($fields) !== 5) WP_CLI::error('Invalid editorial metadata: ' . basename($file));
            [$number, $slug, $category, $cover, $sources] = $fields;
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) || !preg_match('/\A[a-z]+\z/', $cover)) WP_CLI::error('Unsafe editorial identifier.');
            $lines = explode("\n", trim($text));
            $title = preg_replace('/^# /', '', array_shift($lines));
            $body = trim(implode("\n", $lines));
            if (preg_match('/(?<!\p{L})сказк\p{L}*|skazka|parkops|AlekseiGunko|\/root\/|\/var\/|password|api[_-]?key|token\s*[:=]/iu', $body . $title)) WP_CLI::error('Restricted content: ' . $slug);
            if (mb_strlen($body) < 1400 || substr_count($body, '## ') < 2) WP_CLI::error('Incomplete article: ' . $slug);
            $records[] = ['number' => (int) $number, 'slug' => $slug, 'category' => $category, 'cover' => $cover, 'sources' => explode(',', $sources), 'title' => $title, 'body' => $body];
        }
    }
    usort($records, fn($a, $b) => $a['number'] <=> $b['number']);
    if (count($records) !== 100 || array_column($records, 'number') !== range(1, 100) || count(array_unique(array_column($records, 'slug'))) !== 100 || count(array_unique(array_column($records, 'title'))) !== 100) WP_CLI::error('Exactly 100 unique articles numbered 1–100 required.');
    return $records;
}

function sy3_editorial_blocks(string $text): string {
    $html = '';
    foreach (preg_split('/\n\s*\n/', trim($text)) as $part) {
        if (str_starts_with($part, '## ')) $html .= sy3_h(esc_html(substr($part, 3)));
        elseif (str_starts_with($part, '- ')) $html .= sy3_list(array_map(fn($s) => esc_html(preg_replace('/^- /', '', $s)), explode("\n", $part)));
        else $html .= sy3_p(esc_html(str_replace("\n", ' ', $part)));
    }
    return $html;
}

$records = sy3_editorial_records();
$media = json_decode(file_get_contents(__DIR__ . '/../editorial/media.json'), true, 512, JSON_THROW_ON_ERROR);
$sources = json_decode(file_get_contents(__DIR__ . '/../editorial/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$first = new DateTimeImmutable('2026-10-25 10:00:00', new DateTimeZone('Europe/Moscow'));
$existing_batch = get_posts(['post_type' => 'post', 'post_status' => ['draft', 'pending', 'future', 'publish', 'private', 'trash'], 'meta_key' => '_sy3_editorial_batch', 'meta_value' => SY3_EDITORIAL_BATCH, 'posts_per_page' => -1]);
$by_key = [];
foreach ($existing_batch as $post) {
    $key = get_post_meta($post->ID, '_sy3_editorial_key', true);
    if (isset($by_key[$key])) WP_CLI::error('Duplicate existing batch key: ' . $key);
    $by_key[$key] = $post->ID;
}
// Preflight the entire batch before the first write.
foreach ($records as &$record) {
    $key = sprintf('%03d', $record['number']);
    $record['key'] = $key;
    $collision = get_page_by_path($record['slug'], OBJECT, 'post');
    if ($collision && (!isset($by_key[$key]) || $collision->ID !== $by_key[$key])) WP_CLI::error('Unowned slug collision: ' . $record['slug']);
    if (!isset($media[$record['cover']])) WP_CLI::error('Missing cover: ' . $record['cover']);
    foreach ($record['sources'] as $source) if (!isset($sources[$source])) WP_CLI::error('Missing evidence source: ' . $source);
    $cover = $media[$record['cover']];
    $path = __DIR__ . '/../content/media/' . basename($cover['file']);
    if (!is_file($path) || !hash_equals($cover['sha256'], hash_file('sha256', $path))) WP_CLI::error('Cover hash mismatch.');
    // Alternate ten subject clusters: 1,11,21,...91,2,12,...; 100 slots, 72h apart.
    $position = (($record['number'] - 1) % 10) * 10 + intdiv($record['number'] - 1, 10);
    $record['at'] = $first->modify('+' . ($position * 3) . ' days')->getTimestamp();
}
unset($record);
if (getenv('SY3_IMPORT_DRY_RUN') === '1') { WP_CLI::success('Preflight: 100 unique articles; ' . count($by_key) . ' already imported; first 25.10.2026 10:00 MSK. No writes.'); return; }
require_once ABSPATH . 'wp-admin/includes/image.php';
$author = get_user_by('login', getenv('SY3_EDITORIAL_AUTHOR') ?: 'artem_admin');
if (!$author || !user_can($author, 'publish_posts')) WP_CLI::error('Expected site author missing or cannot publish.');
$attachments = []; $receipt = [];
foreach ($records as $record) {
    $key = $record['key'];
    if (isset($by_key[$key])) {
        $receipt[] = ['key' => $key, 'id' => $by_key[$key], 'action' => 'preserved'];
        continue;
    }
    $cover = $media[$record['cover']];
    if (!isset($attachments[$cover['sha256']])) {
        $prior = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sy3_media_hash', 'meta_value' => $cover['sha256'], 'posts_per_page' => 1]);
        if ($prior) $attachment = $prior[0]->ID;
        else {
            $file = basename($cover['file']); $upload = wp_upload_bits($file, null, file_get_contents(__DIR__ . '/../content/media/' . $file));
            if ($upload['error']) WP_CLI::error($upload['error']);
            $attachment = wp_insert_attachment(['post_mime_type' => 'image/webp', 'post_title' => $cover['alt'], 'post_excerpt' => 'Редакционная иллюстрация Сутри.', 'post_status' => 'inherit'], $upload['file'], 0, true);
            if (is_wp_error($attachment)) WP_CLI::error($attachment->get_error_message());
            wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $upload['file']));
            update_post_meta($attachment, '_sy3_media_hash', $cover['sha256']); update_post_meta($attachment, '_wp_attachment_image_alt', $cover['alt']);
        }
        $attachments[$cover['sha256']] = $attachment;
    }
    $body = sy3_editorial_blocks($record['body']);
    $related = [];
    foreach ($record['sources'] as $source) {
        $path = $sources[$source]['case'] ?? '';
        if (!$path) continue;
        $case = get_page_by_path($path, OBJECT, 'sy3_case');
        if ($case && $case->post_status === 'publish') $related[$case->ID] = $case;
    }
    if ($related) {
        $body .= sy3_h('Посмотреть работу в деталях'); $links = [];
        foreach (array_slice($related, 0, 2) as $case) $links[] = '<a href="' . esc_url(get_permalink($case)) . '">' . esc_html(get_the_title($case)) . '</a>';
        $body .= sy3_list($links);
    }
    $body .= sy3_p('<a href="' . esc_url(home_url('/services/')) . '">Направления автоматизации</a> · <a href="https://t.me/moi_imperator">Обсудить задачу с Артёмом</a>', 'editorial-contact');
    $excerpt = mb_substr(preg_split('/\n\s*\n/', $record['body'])[0], 0, 210);
    $term = term_exists($record['category'], 'category') ?: wp_insert_term($record['category'], 'category');
    if (is_wp_error($term)) WP_CLI::error($term->get_error_message());
    $id = wp_insert_post(wp_slash(['post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author->ID, 'post_name' => $record['slug'], 'post_title' => $record['title'], 'post_excerpt' => $excerpt, 'post_content' => $body, 'post_category' => [(int) $term['term_id']], 'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => [
        '_sy3_editorial_batch' => SY3_EDITORIAL_BATCH, '_sy3_editorial_key' => $key, '_sy3_editorial_enabled' => '1', '_sy3_editorial_at' => $record['at'], '_sy3_editorial_sources' => $record['sources'], '_sy3_editorial_seed_hash' => hash('sha256', $body), '_sy3_description' => $excerpt, '_sy3_label' => $record['category'],
    ]]), true);
    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
    set_post_thumbnail($id, $attachments[$cover['sha256']]);
    if ($related) update_post_meta($id, '_sy3_related', implode(',', array_slice(array_keys($related), 0, 3)));
    $receipt[] = ['key' => $key, 'id' => $id, 'action' => 'created', 'at' => $record['at']];
}
WP_CLI::log(wp_json_encode($receipt, JSON_UNESCAPED_UNICODE));
WP_CLI::success('100 draft slots imported/preserved. No article published.');
