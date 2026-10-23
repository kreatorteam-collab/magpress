<?php
/**
 * Archive — categorie, tag, autor, data.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>

<div class="container site-content">

  <header class="archive-header">
    <?php magpress_breadcrumbs(); ?>

    <h1 class="archive-title"><?php echo esc_html(magpress_archive_title()); ?></h1>

    <?php $desc = term_description();
    if (!empty($desc)): ?>
      <div class="archive-description"><?php echo wp_kses_post($desc); ?></div>
    <?php endif; ?>
  </header>

  <?php if (is_category() && !is_paged()) { get_template_part('template-parts/featured-cat'); } ?>

  <?php if (have_posts()): ?>
    <div class="card-grid card-grid--3">
      <?php
      /* v0.9.224: reclama nu mai stă deasupra articolelor, ci după PRIMUL RÂND
         (3 carduri pe desktop). Intră ca element de grilă pe toată lățimea, ca să nu
         rupă grila în două containere. */
      $mp_ad_row = magpress_get_category_top_ad();
      $mp_i = 0;
      while (have_posts()): the_post(); $mp_i++;
        get_template_part('template-parts/card', null, ['lcp' => $mp_i === 1 && empty($GLOBALS['magpress_catfeat_rendered'])]);
        if ($mp_i === 3 && $mp_ad_row !== '' && $wp_query->post_count > 3):
          echo '<div class="card-grid__ad">' . $mp_ad_row . '</div>';
        endif;
      endwhile; ?>
    </div>

    <?php
    the_posts_pagination([
        'mid_size'           => 2,
        'prev_text'          => '«',
        'next_text'          => '»',
        'class'              => 'magpress-pagination',
        'screen_reader_text' => ' ', // Goleste label-ul "Paginatie articole"
        'aria_label'         => 'Paginare',
    ]);
    ?>
  <?php elseif (empty($GLOBALS['magpress_catfeat_rendered'])): ?>
    <p class="no-results">Nu există articole în această categorie.</p>
  <?php endif; ?>

</div>

<?php get_footer();
