<?php
/** Read-only source renderer. Outputs candidate copy, never imports or approves it. */
if (!defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') exit(1);
if (get_option('sy3_site_mode') !== 'expertise') WP_CLI::error('Main-site context required.');
require_once dirname(__DIR__) . '/content/home.php';
require_once dirname(__DIR__) . '/content/pages.php';
$pages = sy3_page_definitions();
$result = [
    ['type' => 'page', 'slug' => 'home', 'source' => 'wordpress/content/home.php', 'content' => sy3_home_content()],
    ['type' => 'page', 'slug' => 'about', 'source' => 'wordpress/content/work-process.php', 'content' => $pages['about']['content']],
    ['type' => 'page', 'slug' => 'contacts', 'source' => 'wordpress/content/contact-copy.php', 'content' => $pages['contacts']['content']],
];
foreach (['services' => 'sy3_service', 'cases' => 'sy3_case'] as $collection => $type) {
    $items = json_decode(file_get_contents(dirname(__DIR__) . '/content/' . $collection . '.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($items as $item) {
        if (!in_array($item['slug'], ['bitrix24', 'integrations', 'ai-automation', 'libertygas'], true)) continue;
        $row = ['type' => $type, 'slug' => $item['slug'], 'source' => 'wordpress/content/' . $collection . '.json', 'content' => sy3_sections($item['sections'])];
        if ($type === 'sy3_service') $row['excerpt'] = $item['excerpt'];
        $result[] = $row;
    }
}
WP_CLI::line(wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
