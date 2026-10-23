<?php
/** Reading helpers only: never rewrite editor-owned posts or approval hashes. */
defined('ABSPATH') || exit;

function sy3_article_support_copy(): array {
    static $copy;
    if ($copy === null) {
        $copy = json_decode((string) file_get_contents(__DIR__ . '/article-support-copy.json'), true) ?: [];
    }
    return $copy;
}

function sy3_article_reviewed_copy(WP_Post $post): array {
    $copy = sy3_article_support_copy()[$post->post_name] ?? [];
    $expected = [$copy['body_hash'] ?? ''];
    if (defined('SY3_PREVIEW') && SY3_PREVIEW && wp_get_environment_type() !== 'production') $expected[] = $copy['preview_body_hash'] ?? '';
    return in_array(hash('sha256', $post->post_content), $expected, true) ? $copy : [];
}

function sy3_article_sections(array $blocks): array {
    $sections = [];
    foreach ($blocks as $index => $block) {
        $html = $block['innerHTML'] ?? '';
        if ($block['blockName'] === 'core/heading' && preg_match('~<h2\b[^>]*>(.*?)</h2>~su', $html, $match)) {
            $heading = trim(html_entity_decode(wp_strip_all_tags($match[1]), ENT_QUOTES, 'UTF-8'));
            $sections[] = ['index' => $index, 'heading' => $heading, 'paragraphs' => []];
        } elseif ($sections && $block['blockName'] === 'core/paragraph') {
            $text = trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
            if (mb_strlen($text) >= 70) $sections[array_key_last($sections)]['paragraphs'][] = $text;
        }
    }
    return array_values(array_filter($sections, static fn($section) => $section['paragraphs'] && !preg_match('/источник|документац|если нужна помощь|обсудить задачу/ui', $section['heading'])));
}

function sy3_article_excerpt_answer(string $text): string {
    // Complete sentences from this article, never invented facts or shared service copy.
    $sentences = preg_split('/(?<=[.!?])\s+(?=[А-ЯЁA-Z0-9«])/u', $text) ?: [$text];
    $answer = '';
    foreach ($sentences as $sentence) {
        if ($answer !== '' && mb_strlen($answer . ' ' . $sentence) > 850) break;
        $answer .= ($answer === '' ? '' : ' ') . $sentence;
        if (mb_strlen($answer) >= 350) break;
    }
    return $answer;
}

function sy3_article_faq_items(WP_Post $post, array $sections): array {
    $copy = sy3_article_reviewed_copy($post)['faq'] ?? [];
    if ($copy) return $copy;
    // Draft/future material: derive short answers from the actual section, not its topic label.
    $items = [];
    foreach ($sections as $section) {
        $question = preg_replace('/^\d+[.)]\s*/u', '', $section['heading']);
        if (!preg_match('/^(Как|Что|Почему|Когда|Где|Зачем|Можно|Нужно|Какие|Какой|Сколько|Чем|Кто|Стоит|Нужен|Нужна|С чего)\b/ui', $question)) continue;
        $items[] = [rtrim($question, '.:?!') . '?', sy3_article_excerpt_answer($section['paragraphs'][0])];
        if (count($items) === 4) return $items;
    }
    $fallbacks = [
        ['С чего начать?', null],
        ['Как проверить результат?', '/при[её]мк|результат|критери|провер|готовност/ui'],
        ['Какие ограничения нужно учесть?', '/риск|огранич|ошиб|безопас|доступ|границ/ui'],
        ['Как организовать работу?', '/процесс|маршрут|порядок|сценари|этап|артефакт|паспорт/ui'],
    ];
    foreach ($fallbacks as [$question, $pattern]) {
        foreach ($sections as $section) {
            if ($pattern && !preg_match($pattern, $section['heading'])) continue;
            $answer = sy3_article_excerpt_answer($section['paragraphs'][0]);
            if (in_array($answer, array_column($items, 1), true) || in_array($question, array_column($items, 0), true)) continue;
            $items[] = [$question, $answer];
            break;
        }
        if (count($items) === 4) break;
    }
    return $items;
}

function sy3_article_inline_cta(WP_Post $post, int $slot): string {
    $copy = sy3_article_reviewed_copy($post)['ctas'][$slot] ?? [
        'title' => $slot === 0 ? 'Хотите применить это в своей компании?' : 'Перейдём от разбора к первому шагу',
        'text' => $slot === 0
            ? 'Напишите, какая часть задачи из статьи «' . $post->post_title . '» актуальна для вас и какими системами пользуется команда. Обсудим, что проверить сначала.'
            : 'Опишите конкретное затруднение и нужный результат. Согласуем границы первого этапа и способ проверить, что задача решена.',
        'label' => $slot === 0 ? 'Разобрать мою задачу' : 'Обсудить первый этап',
    ];
    return '<aside class="article-inline-cta" aria-labelledby="article-cta-' . $post->ID . '-' . $slot . '">'
        . '<p class="article-cta-kicker">От разбора к вашей задаче</p>'
        . '<h3 id="article-cta-' . $post->ID . '-' . $slot . '">' . esc_html($copy['title']) . '</h3>'
        . '<p>' . esc_html($copy['text']) . '</p><div class="article-cta-actions">'
        . '<a class="button" href="https://t.me/sy3_support_bot?start=site_sy3">' . esc_html($copy['label']) . '<span class="screen-reader-text"> в Telegram</span></a>'
        . '<a class="article-cta-alt" href="https://max.ru/id263514840335_1_bot?start=site_sy3">Написать в MAX</a></div></aside>';
}

function sy3_article_faq(WP_Post $post, array $items): string {
    if (!$items) return '';
    $html = '<section class="article-faq" id="article-faq-' . $post->ID . '" aria-labelledby="article-faq-heading-' . $post->ID . '">'
        . '<p class="eyebrow">Короткие ответы</p><h2 id="article-faq-heading-' . $post->ID . '">Вопросы по теме</h2>';
    foreach ($items as $index => [$question, $answer]) {
        $html .= '<details' . ($index === 0 ? ' open' : '') . '><summary>' . esc_html($question) . '</summary><p>' . esc_html($answer) . '</p></details>';
    }
    return $html . '</section>';
}

function sy3_article_support_content(string $content, WP_Post $post): string {
    if (str_contains($content, 'article-inline-cta') || post_password_required($post)) return $content;
    $blocks = parse_blocks($content);
    $sections = sy3_article_sections($blocks);
    if (count($sections) < 4) return $content;
    $positions = [$sections[2]['index'], $sections[max(3, (int) floor(count($sections) * .7))]['index']];
    if ($post->post_name === 'crm-est-zayavki-teryayutsya') {
        foreach ($sections as $section) {
            if (str_starts_with($section['heading'], 'Как подготовить контрольное обращение')) $positions[0] = $section['index'];
            if (str_starts_with($section['heading'], 'Очередь исключений')) $positions[1] = $section['index'];
        }
    }
    $faq_exists = (bool) preg_match('/article-faq|faq-list|<h[23]\b[^>]*>\s*(?:FAQ|Частые вопросы|Вопросы и ответы|Вопросы по теме)\s*<\//ui', $content);
    $items = $faq_exists ? [] : sy3_article_faq_items($post, $sections);
    $output = $items ? '<p class="article-faq-jump"><a href="#article-faq-' . $post->ID . '">К вопросам по статье</a></p>' : '';
    foreach ($blocks as $index => $block) {
        $slot = array_search($index, $positions, true);
        if ($slot !== false) $output .= sy3_article_inline_cta($post, $slot);
        $output .= serialize_block($block);
    }
    return $output . sy3_article_faq($post, $items);
}

add_filter('the_content', function (string $content): string {
    if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()
        || sy3_personal() || !is_singular('post') || !in_the_loop() || !is_main_query()
        || get_the_ID() !== get_queried_object_id()) return $content;
    return sy3_article_support_content($content, get_post());
}, 8);
