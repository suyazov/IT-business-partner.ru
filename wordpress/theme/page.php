<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main"><?php while (have_posts()): the_post(); ?>
    <header class="page-heading wrap"><p class="eyebrow"><?php echo esc_html(sy3_label()); ?> / <?php the_title(); ?></p><h1><?php the_title(); ?></h1><?php if (has_excerpt()): ?><p class="lead"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?></header>
    <div class="page-content wrap"><?php the_content(); ?></div>
<?php endwhile; ?></main><?php get_footer(); ?>
