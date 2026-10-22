<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(sy3_personal() ? 'brand-personal' : 'brand-sy3'); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<header class="site-header"><div class="header-inner">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(sy3_label() . ' — главная'); ?>"><span class="brand-mark" aria-hidden="true">с<span>↗</span></span><span><?php echo esc_html(sy3_label()); ?><small><?php echo sy3_personal() ? 'IT-бизнес-партнёр' : 'автоматизация бизнеса'; ?></small></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Меню <span aria-hidden="true">☰</span></button>
    <nav id="site-nav" aria-label="Основная навигация"><?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'fallback_cb' => false, 'depth' => 1]); ?></nav>
    <a class="button button-small header-cta" href="<?php echo esc_url(sy3_contact_url()); ?>">Обсудить задачу <span aria-hidden="true">↗</span></a>
</div></header>
