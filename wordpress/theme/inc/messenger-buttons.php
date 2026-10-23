<?php
/** Public presentation only; editor-owned bodies and review hashes stay intact. */
defined('ABSPATH') || exit;

function sy3_is_support_messenger(string $url): bool {
    $parts = wp_parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https') return false;
    $host = strtolower($parts['host'] ?? '');
    $path = rtrim($parts['path'] ?? '', '/');
    return ($host === 't.me' && in_array($path, ['/sy3_support_bot', '/moi_imperator'], true))
        || ($host === 'max.ru' && $path === '/id263514840335_1_bot');
}

function sy3_split_messenger_buttons(string $html): string {
    if (!str_contains($html, 'wp-block-buttons')) return $html;
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET)) return $html;
    } finally {
        libxml_clear_errors(); libxml_use_internal_errors($previous);
    }
    $group = $document->documentElement;
    if (!$group || !in_array('wp-block-buttons', preg_split('/\s+/', $group->getAttribute('class')), true)) return $html;
    $targets = [];
    foreach ($group->childNodes as $child) {
        if (!$child instanceof DOMElement || !in_array('wp-block-button', preg_split('/\s+/', $child->getAttribute('class')), true)) continue;
        $links = $child->getElementsByTagName('a');
        if ($links->length === 1 && sy3_is_support_messenger($links->item(0)->getAttribute('href'))) $targets[] = $child;
    }
    if (!$targets) return $html;
    $replacement = $document->createDocumentFragment();
    if (!$replacement->appendXML(sy3_messenger_choice('content-messengers'))) return $html;
    $group->replaceChild($replacement, $targets[0]);
    foreach (array_slice($targets, 1) as $target) $group->removeChild($target);
    return $document->saveHTML($group);
}

function sy3_remove_paired_max_alternative(string $html): string {
    if (!str_contains($html, 'messenger-choice')) return $html;
    // Only the old standalone MAX paragraph beside an already converted button.
    return preg_replace('~<p\b(?=[^>]*\bclass=["\x27][^"\x27]*\bcontact-alternative\b)[^>]*>\s*<a\b[^>]*\bhref=["\x27]https://max\.ru/id263514840335_1_bot\?start=site_sy3["\x27][^>]*>[^<]*</a>\s*</p>~u', '', $html) ?? $html;
}

function sy3_public_messenger_buttons(): bool {
    return !sy3_personal() && !is_admin() && !is_feed() && !(defined('REST_REQUEST') && REST_REQUEST)
        && !wp_doing_ajax() && in_the_loop() && is_main_query() && !post_password_required();
}

add_filter('render_block_core/buttons', static function (string $html): string {
    return sy3_public_messenger_buttons() ? sy3_split_messenger_buttons($html) : $html;
});
add_filter('render_block_core/column', static function (string $html): string {
    return sy3_public_messenger_buttons() ? sy3_remove_paired_max_alternative($html) : $html;
});
