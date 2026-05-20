<?php /* Template Name: Strona główna */
get_header(); ?>

<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php while ( have_posts() ) : the_post(); ?>
<?php the_content(); ?>
<?php comments_template(); ?>
<?php endwhile; ?>

<?php get_footer(); ?>
