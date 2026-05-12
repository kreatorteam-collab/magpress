<?php
/**
 * Page — pagina statica (Despre, Contact, GDPR).
 */
if (!defined('ABSPATH')) { exit; }
get_header();
the_post();
?>

<div class="container site-content">

  <header class="article-hero">
    <?php magpress_breadcrumbs(); ?>
    <h1 class="article-title"><?php the_title(); ?></h1>
  </header>

  <?php if (has_post_thumbnail()): ?>
    <figure class="article-image">
      <?php the_post_thumbnail('magpress-feature'); ?>
    </figure>
  <?php endif; ?>

  <article class="article-body">
    <div class="entry-content">
      <?php the_content(); ?>
    </div>
  </article>

  <?php if (comments_open() || get_comments_number()): ?>
    <section class="article-comments">
      <?php comments_template(); ?>
    </section>
  <?php endif; ?>

</div>

<?php get_footer();
