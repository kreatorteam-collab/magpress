<?php
/**
 * Fallback — blog listing (cand homepage e setata pe "Latest posts").
 * front-page.php are prioritate cand exista.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>

<div class="container site-content">

  <header class="archive-header">
    <h1 class="archive-title"><?php is_home() ? bloginfo('name') : single_post_title(); ?></h1>
  </header>

  <?php if (have_posts()): ?>
    <div class="card-grid">
      <?php while (have_posts()): the_post();
        get_template_part('template-parts/card', null, ['lcp' => $wp_query->current_post === 0]);
      endwhile; ?>
    </div>
    <?php the_posts_pagination(['mid_size' => 2, 'class' => 'magpress-pagination', 'screen_reader_text' => ' ', 'aria_label' => 'Paginare']); ?>
  <?php else: ?>
    <p>Nu există articole publicate încă.</p>
  <?php endif; ?>

</div>

<?php get_footer();
