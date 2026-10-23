<?php
/**
 * Search results.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>

<div class="container site-content">

  <header class="archive-header">
    <?php magpress_breadcrumbs(); ?>
    <h1 class="archive-title">Rezultate pentru: „<?php echo esc_html(get_search_query()); ?>”</h1>

    <form class="search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get" role="search">
      <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Caută..." aria-label="Caută">
      <button type="submit">Caută</button>
    </form>
  </header>

  <?php if (have_posts()): ?>
    <div class="card-grid">
      <?php while (have_posts()): the_post();
        get_template_part('template-parts/card', null, ['lcp' => $wp_query->current_post === 0]);
      endwhile; ?>
    </div>

    <?php the_posts_pagination(['mid_size' => 2, 'class' => 'magpress-pagination', 'screen_reader_text' => ' ', 'aria_label' => 'Paginare']); ?>
  <?php else: ?>
    <p class="no-results">Niciun rezultat. Încearcă alți termeni.</p>
  <?php endif; ?>

</div>

<?php get_footer();
