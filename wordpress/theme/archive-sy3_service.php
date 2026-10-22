<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main">
    <header class="page-heading wrap"><p class="eyebrow">Сутри / Услуги для бизнеса</p><h1>Создать. Настроить.<br>Поддерживать и развивать.</h1><p class="lead">Сайты, CRM и рабочие процессы — от отдельной доработки до комплексной автоматизации. Начинаем с вашей задачи и согласуем проверяемый результат.</p><div class="topic-links" aria-label="Частые задачи"><a href="<?php echo esc_url(home_url('/services/website-support/')); ?>">Поддержать сайт</a><a href="<?php echo esc_url(home_url('/services/website-modernization/')); ?>">Доработать сайт</a><a href="<?php echo esc_url(home_url('/services/bitrix24/')); ?>">Внедрить CRM</a><a href="<?php echo esc_url(home_url('/services/business-analysis/')); ?>">Разобрать процессы</a></div></header>
    <div class="wrap archive-grid card-grid"><?php while (have_posts()): the_post(); sy3_post_card(get_post()); endwhile; ?></div>
    <section class="wrap service-help"><p class="eyebrow">Не знаете, какое направление выбрать?</p><h2>Начните с задачи.</h2><p>Расскажите Артёму, что сейчас не работает или что хотите запустить. Определим, нужен ли аудит, небольшая доработка или отдельный проект.</p><a class="button" href="<?php echo esc_url(sy3_contact_url()); ?>">Обсудить задачу с Артёмом</a></section>
</main>
<?php get_footer(); ?>
