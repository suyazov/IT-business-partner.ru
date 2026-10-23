<?php
defined('ABSPATH') || exit;

/** Navigation groups leave editorial categories and post assignments intact. */
function sy3_journal_topics(): array {
    static $topics;
    if (isset($topics)) return $topics;
    $definitions = [
        'crm' => ['CRM', ['CRM', 'Битрикс24']],
        'integrations' => ['Интеграции', ['Интеграции']],
        'ai' => ['ИИ', ['ИИ', 'ИИ и база знаний', 'ИИ-шлюз и прикладной ИИ']],
        'processes' => ['Бизнес-процессы', ['Процессы', 'Бизнес-процессы', 'Автоматизация', 'Документы', 'Бронирование', 'Онлайн-запись', 'Фоновые задачи и автоматизация']],
        'web' => ['Сайты и поддержка', ['Сайты', 'Веб-сервисы', 'Интернет-магазины', 'Поддержка сайта', 'Техническое SEO']],
        'infrastructure' => ['Инфраструктура', ['Владение инфраструктурой', 'Домены и деловая почта', 'Наблюдение, доступы и защита', 'Поставка и эксплуатация сервисов', 'Размещение и перенос сайта', 'Файлы, S3 и доставка']],
        'management' => ['Управление и аналитика', ['Базы данных и аналитика', 'Внутренние инструменты и обучение', 'Стратегия', 'Стратегия автоматизации', 'Управление', 'Управление IT', 'Финансы']],
    ];
    $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    $topics = [];
    foreach ($definitions as $key => [$label, $names]) {
        $ids = [];
        if (!is_wp_error($terms)) foreach ($terms as $term) {
            if (in_array($term->name, $names, true)) $ids[] = (int) $term->term_id;
        }
        $topics[$key] = ['label' => $label, 'ids' => $ids, 'posts' => []];
    }
    // Distinct public articles: drafts and repeated category assignments do not count.
    global $wpdb;
    $rows = $wpdb->get_results("SELECT p.ID, tt.term_id FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
        LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
        WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_password = ''");
    $all = [];
    foreach ($rows as $row) {
        $all[(int) $row->ID] = true;
        foreach ($topics as &$topic) {
            if (in_array((int) $row->term_id, $topic['ids'], true)) $topic['posts'][(int) $row->ID] = true;
        }
        unset($topic);
    }
    foreach ($topics as &$topic) {
        $topic['count'] = count($topic['posts']);
        unset($topic['posts']);
    }
    unset($topic);
    return $topics = ['all' => ['label' => 'Все статьи', 'ids' => [], 'count' => count($all)]] + $topics;
}

function sy3_journal_topic($value): string {
    return is_string($value) && isset(sy3_journal_topics()[$value]) ? $value : 'all';
}

add_filter('query_vars', function ($vars) { $vars[] = 'journal_topic'; return $vars; });

add_action('pre_get_posts', function (WP_Query $query) {
    if (is_admin() || !$query->is_main_query() || sy3_personal()) return;
    if (!$query->is_home() && !$query->is_category() && !$query->is_tag() && !($query->is_search() && $query->get('post_type') === 'post')) return;
    $query->set('has_password', false);
    // Existing category URLs retain their original category selection.
    if ($query->is_category() || $query->is_tag()) return;
    $key = sy3_journal_topic($query->get('journal_topic'));
    if ($key === 'all') return;
    $ids = sy3_journal_topics()[$key]['ids'];
    if ($ids) $query->set('category__in', $ids);
    else $query->set('post__in', [0]);
});

function sy3_journal_context(): bool {
    return !sy3_personal() && (is_home() || is_category() || is_tag() || (is_search() && get_query_var('post_type') === 'post'));
}

function sy3_journal_url(string $key = 'all', string $search = ''): string {
    $args = [];
    if ($key !== 'all') $args['journal_topic'] = $key;
    if ($search !== '') $args += ['s' => $search, 'post_type' => 'post'];
    return add_query_arg($args, home_url($search === '' ? '/journal/' : '/'));
}

/** Prefer editor-owned tags; until assigned, show the article's actual categories. */
function sy3_article_tags(int $post_id): void {
    if (sy3_personal() || get_post_type($post_id) !== 'post') return;
    $terms = get_the_terms($post_id, 'post_tag');
    if (!$terms || is_wp_error($terms)) {
        $terms = get_the_terms($post_id, 'category');
        if (is_array($terms)) $terms = array_filter($terms, fn($term) => $term->slug !== 'uncategorized');
    }
    if (!$terms || is_wp_error($terms)) return;
    $links = [];
    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (!is_wp_error($url)) $links[] = '<li><a href="' . esc_url($url) . '" rel="tag">' . esc_html($term->name) . '</a></li>';
    }
    if ($links) echo '<ul class="article-tags" aria-label="Темы статьи">' . implode('', $links) . '</ul>';
}
