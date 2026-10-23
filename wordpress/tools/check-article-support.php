<?php
/** Read-only: validate the actual journal corpus and presentation invariants. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
require_once dirname(__DIR__).'/theme/inc/article-support.php';
global $wpdb;
$fingerprint = static function () use ($wpdb): string {
    $data = [];
    foreach ([$wpdb->posts, $wpdb->postmeta] as $table) $data[] = $wpdb->get_results("SELECT * FROM $table ORDER BY 1", ARRAY_A);
    return hash('sha256', wp_json_encode($data));
};
$before = $fingerprint();
$posts = get_posts(['post_type'=>'post','post_status'=>['publish','draft'],'numberposts'=>-1]);
$checked = 0; $public = 0; $faqCounts = [];
foreach ($posts as $post) {
    if (!get_post_meta($post->ID, '_sy3_editorial_reviewed_hash', true)) continue; // Ignore isolated editor test fixtures.
    $content = $post->post_content;
    $output = sy3_article_support_content($content, $post);
    $items = sy3_article_faq_items($post, sy3_article_sections(parse_blocks($content)));
    if (substr_count($output, 'class="article-inline-cta"') !== 2 || substr_count($output, 'class="article-faq"') !== 1 || count($items) < 2) WP_CLI::error('Missing article component: '.$post->post_name);
    if (sy3_article_support_content($output, $post) !== $output) WP_CLI::error('Presentation must be idempotent.');
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?><div id="fixture">'.$output.'</div>', LIBXML_NONET);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//aside[contains(@class,"article-inline-cta")]') as $cta) {
        if ($cta->parentNode->nodeName !== 'div' || $cta->parentNode->getAttribute('id') !== 'fixture') WP_CLI::error('CTA must be inserted between complete top-level blocks.');
    }
    foreach ($items as [$q, $a]) if (trim($q)==='' || mb_strlen($a)<60 || preg_match('~<[^>]+>~', $a)) WP_CLI::error('Invalid FAQ copy: '.$post->post_name);
    if ($post->post_status === 'publish') {
        $public++;
        if (!sy3_article_reviewed_copy($post) || count($items)<4) WP_CLI::error('Published article requires reviewed individual copy: '.$post->post_name);
    }
    $faqCounts[count($items)] = ($faqCounts[count($items)]??0)+1;
    $checked++;
}
$example = get_page_by_path('crm-est-zayavki-teryayutsya', OBJECT, 'post');
$protected = clone $example; $protected->post_password = 'synthetic-check';
if (sy3_article_support_content($example->post_content, $protected) !== $example->post_content) WP_CLI::error('Password-protected content must not be exposed.');
$edited = clone $example; $edited->post_content .= '<p>Changed editorial content.</p>';
if (sy3_article_reviewed_copy($edited) !== []) WP_CLI::error('Changed article must not reuse stale reviewed copy.');
// Existing editor-owned FAQ must remain the only FAQ; adding the helpers twice must not duplicate CTAs.
$withFaq = $example->post_content.'<section class="faq-list"><h2>Частые вопросы</h2></section>';
if (str_contains(sy3_article_support_content($withFaq,$example),'class="article-faq"')) WP_CLI::error('Existing FAQ must be preserved.');
$withDisclosure = $example->post_content.'<details><summary>Посмотреть пример</summary><p>Дополнительный пример.</p></details>';
if (!str_contains(sy3_article_support_content($withDisclosure,$example),'class="article-faq"')) WP_CLI::error('Unrelated disclosure must not suppress FAQ.');
if ($before !== $fingerprint()) WP_CLI::error('Read-only checks changed database content.');
ksort($faqCounts);
WP_CLI::line(wp_json_encode(['status'=>'PASS','articles'=>$checked,'public_individual_copy'=>$public,'faq_counts'=>$faqCounts,'database_unchanged'=>true,'assertions'=>['two CTAs between complete blocks','one FAQ','idempotence','password guard','existing FAQ guard']]));
