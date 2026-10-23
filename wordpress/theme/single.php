<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="main">
<?php while (have_posts()): the_post();
    $kind = get_post_type();
    $back = match ($kind) {
        'sy3_legacy' => ['portfolio/', 'Работы'],
        'sy3_case' => ['cases/', 'Кейсы'],
        'sy3_service' => ['services/', 'Услуги'],
        default => sy3_personal() ? ['blog/', 'Архив публикаций'] : ['journal/', 'Журнал'],
    };
    $words = preg_match_all('/[\p{L}\p{N}]+/u', wp_strip_all_tags(get_the_content()));
    $minutes = max(1, (int) ceil($words / 170));
    ?>
    <header class="page-heading wrap">
        <a class="eyebrow back-link" href="<?php echo esc_url(home_url('/' . $back[0])); ?>"><?php echo esc_html($back[1]); ?></a>
        <h1><?php the_title(); ?></h1>
        <p class="lead"><?php echo esc_html(get_the_excerpt()); ?></p>
        <?php if ($kind === 'post'): ?>
            <?php if (sy3_personal()): ?><p class="byline">Артём Суязов · <?php echo esc_html(wp_date('d.m.Y', get_post_timestamp())); ?> · <?php echo esc_html($minutes); ?> мин чтения</p>
            <?php else: ?><div class="byline author-byline"><img src="<?php echo esc_url(get_theme_file_uri('assets/artem-suyazov.jpg')); ?>" alt="" width="48" height="48"><p><a href="<?php echo esc_url(home_url('/about/')); ?>">Артём Суязов</a><span><?php echo esc_html(wp_date('d.m.Y', get_post_timestamp())); ?> · <?php echo esc_html($minutes); ?> мин чтения</span></p></div><?php endif; ?>
        <?php endif; ?>
    </header>
    <div class="article-layout wrap">
        <article class="article-body"><?php if ($kind === 'post' && !sy3_personal()) sy3_article_tags(get_the_ID()); ?><?php if (in_array($kind, ['sy3_case', 'post'], true) && has_post_thumbnail()): ?><figure class="case-cover"><?php the_post_thumbnail('large', ['loading' => 'eager']); ?><?php if ($caption = sy3_cover_caption(get_post_thumbnail_id())): ?><figcaption><?php echo esc_html($caption); ?></figcaption><?php endif; ?></figure><?php endif; ?><?php the_content(); ?></article>
        <aside class="article-aside">
            <?php if (!sy3_personal()): ?><a class="aside-profile" href="<?php echo esc_url(home_url('/about/')); ?>"><img src="<?php echo esc_url(get_theme_file_uri('assets/artem-suyazov.jpg')); ?>" alt="" width="64" height="64" loading="lazy"><span>Артём Суязов<small>IT-бизнес-партнёр</small></span></a><?php endif; ?>
            <p class="eyebrow">Есть похожая задача?</p>
            <h2><?php echo sy3_personal() ? 'Начнём<br>с вашего процесса.' : 'Обсудим<br>вашу задачу.'; ?></h2>
            <p><?php echo sy3_personal() ? 'Расскажите, где теряются заявки или время команды. Разберём исходную ситуацию и следующий шаг.' : 'Расскажите, что хотите запустить или улучшить: сайт, CRM, интеграцию или работу команды. Предложу понятный первый шаг.'; ?></p>
            <?php if (sy3_personal()): ?><a class="button" href="<?php echo esc_url(sy3_contact_url()); ?>">Обсудить задачу</a><?php else: echo sy3_messenger_choice('aside-messengers'); endif; ?>
            <p class="aside-author"><?php echo sy3_personal() ? 'Артём Суязов<br><span>CRM · интеграции · ИИ</span>' : 'Вы пишете мне напрямую.<br><span>Начнём с задачи — готовое ТЗ не нужно.</span>'; ?></p>
        </aside>
    </div>
    <?php if (function_exists('sy3_related')) sy3_related(get_the_ID()); ?>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
