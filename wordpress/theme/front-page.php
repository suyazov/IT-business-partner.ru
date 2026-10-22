<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" class="homepage"><?php while (have_posts()): the_post(); the_content(); endwhile; ?></main>
<?php get_footer(); ?>
