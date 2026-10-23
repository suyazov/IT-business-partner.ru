<?php
/** Native editor blocks, with no page-builder dependency. */
function sy3_b(string $name, string $html, array $attrs = []): string {
    return '<!-- wp:' . $name . ($attrs ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') . ' -->' . $html . '<!-- /wp:' . $name . ' -->' . "\n";
}
function sy3_p(string $text, string $class = ''): string {
    return sy3_b('paragraph', '<p' . ($class ? ' class="' . esc_attr($class) . '"' : '') . '>' . $text . '</p>', $class ? ['className' => $class] : []);
}
function sy3_h(string $text, int $level = 2): string {
    return sy3_b('heading', '<h' . $level . ' class="wp-block-heading">' . $text . '</h' . $level . '>', ['level' => $level]);
}
function sy3_g(string $content, string $class = '', string $tag = 'div'): string {
    return sy3_b('group', '<' . $tag . ' class="wp-block-group' . ($class ? ' ' . esc_attr($class) : '') . '">' . $content . '</' . $tag . '>', array_filter(['tagName' => $tag, 'className' => $class]));
}
function sy3_section(string $content, string $class = ''): string { return sy3_g(sy3_g($content, 'section-inner'), 'section ' . $class, 'section'); }
function sy3_cols(array $columns, string $class): string {
    $html = implode('', array_map(fn($c) => sy3_b('column', '<div class="wp-block-column">' . $c . '</div>'), $columns));
    return sy3_b('columns', '<div class="wp-block-columns ' . esc_attr($class) . '">' . $html . '</div>', ['className' => $class]);
}
function sy3_buttons(array $items): string {
    $html = '';
    foreach ($items as $i => $item) {
        $class = !empty($item[2]) ? ' is-style-outline' : '';
        $html .= sy3_b('button', '<div class="wp-block-button' . $class . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a></div>', $class ? ['className' => 'is-style-outline'] : []);
    }
    return sy3_b('buttons', '<div class="wp-block-buttons">' . $html . '</div>');
}
function sy3_image(string $file, string $alt, string $class = '', string $caption = ''): string {
    return sy3_b('image', '<figure class="wp-block-image size-full ' . esc_attr($class) . '"><img src="' . esc_url(get_theme_file_uri('assets/' . $file)) . '" alt="' . esc_attr($alt) . '"/>' . ($caption !== '' ? '<figcaption class="wp-element-caption">' . esc_html($caption) . '</figcaption>' : '') . '</figure>', ['sizeSlug' => 'full', 'className' => $class]);
}
function sy3_list(array $items, bool $ordered = false): string {
    $tag = $ordered ? 'ol' : 'ul'; $html = '';
    foreach ($items as $item) $html .= sy3_b('list-item', '<li>' . wp_kses_post($item) . '</li>');
    return sy3_b('list', '<' . $tag . ' class="wp-block-list">' . $html . '</' . $tag . '>', $ordered ? ['ordered' => true] : []);
}
function sy3_shortcode(string $value): string { return sy3_b('shortcode', $value); }
function sy3_faq(string $question, string $answer): string {
    return sy3_b('details', '<details class="wp-block-details"><summary>' . esc_html($question) . '</summary>' . sy3_p(wp_kses_post($answer)) . '</details>');
}
function sy3_sections(array $sections): string {
    $html = '';
    foreach ($sections as $s) {
        if (!empty($s['heading'])) $html .= sy3_h(esc_html($s['heading']));
        if (!empty($s['image'])) $html .= sy3_image(basename($s['image']), $s['image_alt'] ?? '', '', $s['image_caption'] ?? '');
        foreach ($s['paragraphs'] ?? [] as $p) $html .= sy3_p(wp_kses_post($p));
        if (!empty($s['items'])) $html .= sy3_list($s['items'], !empty($s['ordered']));
        if (!empty($s['callout'])) $html .= sy3_g(sy3_p(wp_kses_post($s['callout'])), 'callout');
    }
    return $html;
}
