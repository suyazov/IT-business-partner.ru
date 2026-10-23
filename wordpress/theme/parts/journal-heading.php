<?php
defined('ABSPATH') || exit;
$topics = sy3_journal_topics();
$active = sy3_journal_topic(get_query_var('journal_topic'));
if (is_category()) foreach ($topics as $key => $topic) {
    if (in_array(get_queried_object_id(), $topic['ids'], true)) { $active = $key; break; }
}
$search = get_search_query(false);
global $wp_query;
?>
<header class="wrap journal-heading">
    <div class="journal-heading-top">
        <div class="journal-heading-copy">
            <p class="eyebrow">Журнал Сутри</p>
            <h1><?php echo esc_html((is_category() || is_tag()) ? single_term_title('', false) : 'Автоматизация бизнеса'); ?></h1>
            <p class="journal-intro">Разборы проектов, рабочие процессы и практические инструкции.</p>
        </div>
        <form role="search" aria-label="Поиск по журналу" class="journal-search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label for="journal-search-input">Поиск по статьям</label>
            <div class="journal-search-controls">
                <input id="journal-search-input" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Что хотите найти?">
                <input type="hidden" name="post_type" value="post">
                <?php if ($active !== 'all'): ?><input type="hidden" name="journal_topic" value="<?php echo esc_attr($active); ?>"><?php endif; ?>
                <button class="button" type="submit">Найти</button>
            </div>
        </form>
    </div>
    <nav class="journal-topics" aria-label="Темы журнала">
        <span class="journal-topics-label">Темы</span>
        <ul><?php foreach ($topics as $key => $topic): if ($key !== 'all' && !$topic['count'] && $key !== $active) continue; ?>
            <li><a href="<?php echo esc_url(sy3_journal_url($key, $search)); ?>"<?php if ($active === $key) echo ' aria-current="page"'; ?>><?php echo esc_html($topic['label']); ?><span class="journal-topic-count"><?php echo (int) $topic['count']; ?></span></a></li>
        <?php endforeach; ?></ul>
    </nav>
    <?php if ($search !== '' || $active !== 'all' || is_category() || is_tag()): ?>
        <div class="journal-results"><p><?php if ($search !== ''): ?>По запросу «<?php echo esc_html($search); ?>»: <?php else: ?>Статей в подборке: <?php endif; ?><strong><?php echo (int) $wp_query->found_posts; ?></strong></p>
        <?php if ($search !== ''): ?><a href="<?php echo esc_url(sy3_journal_url($active)); ?>">Сбросить поиск</a><?php endif; ?></div>
    <?php endif; ?>
</header>
