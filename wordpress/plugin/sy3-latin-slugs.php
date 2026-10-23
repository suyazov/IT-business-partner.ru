<?php
/** Internal URL policy module; loaded by sy3-content, not a separate plugin. */
defined('ABSPATH') || exit;

function sy3_latin_text(string $text): string {
    static $map;
    if (!isset($map)) {
        $lower = preg_split('//u', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяіїєґў', -1, PREG_SPLIT_NO_EMPTY);
        $upper = preg_split('//u', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯІЇЄҐЎ', -1, PREG_SPLIT_NO_EMPTY);
        $latin = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','shch','','y','','e','yu','ya','i','yi','ye','g','u'];
        $map = array_combine(array_merge($lower, $upper), array_merge($latin, $latin));
    }
    return strtr($text, $map);
}

add_filter('sanitize_title', function ($title, $raw, $context) {
    if ($context !== 'save' || get_option('sy3_site_mode') !== 'expertise') return $title;
    $decoded = rawurldecode($title);
    return preg_match('/[А-Яа-яЁёІіЇїЄєҐґЎў]/u', $decoded) ? sy3_latin_text($decoded) : $title;
}, 9, 3);

add_filter('sanitize_file_name', function ($name) {
    return get_option('sy3_site_mode') === 'expertise' ? sy3_latin_text($name) : $name;
});

function sy3_valid_legacy_term_route($entry): bool {
    if (!is_array($entry) || count($entry) !== 5) return false;
    foreach (['path','taxonomy','old','new'] as $key) if (!isset($entry[$key]) || !is_string($entry[$key]) || $entry[$key] === '') return false;
    if (!isset($entry['term_id']) || !is_int($entry['term_id']) || $entry['term_id'] < 1) return false;
    if (!in_array($entry['taxonomy'], ['category','post_tag','sy3_case_topic'], true)) return false;
    if (!preg_match('#^/(?!/)[^\x00-\x20?\#\\\\]*$#uD', $entry['path']) || !preg_match('/^[a-z0-9_-]+$/D', $entry['new'])) return false;
    $base = rtrim($entry['path'], '/');
    return substr($base, strrpos($base, '/') + 1) === rawurldecode($entry['old']);
}

add_action('template_redirect', function () {
    if (get_option('sy3_site_mode') !== 'expertise' || is_admin() || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)) return;
    $path = rawurldecode((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    $map = get_option('sy3_legacy_term_urls', []);
    if (!is_array($map)) return;
    foreach ($map as $entry) {
        if (!sy3_valid_legacy_term_route($entry)) continue;
        $base = rtrim($entry['path'], '/');
        if (rtrim($path, '/') === $base) $suffix = '';
        elseif (str_starts_with($path, $base . '/')) {
            $suffix = substr($path, strlen($base));
            if (!preg_match('#^/(?:page/[1-9][0-9]*|feed(?:/(?:feed|rdf|rss|rss2|atom))?)/?$#D', $suffix)) continue;
        } else continue;
        $term = get_term((int) $entry['term_id'], $entry['taxonomy']);
        if (!$term || is_wp_error($term)) continue;
        $url = get_term_link($term);
        if (is_wp_error($url)) continue;
        if ($suffix !== '') $url = trailingslashit(rtrim($url, '/') . $suffix);
        if (rtrim(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)), '/') === rtrim($path, '/')) continue;
        $query = wp_unslash($_SERVER['QUERY_STRING'] ?? '');
        if ($query !== '') { $args = []; wp_parse_str($query, $args); $url = add_query_arg($args, $url); }
        wp_safe_redirect($url, 301, 'SY3 Latin URLs');
        exit;
    }
}, -20);
