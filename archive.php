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

    <h1 class="archive-title"><?php single_term_title(); ?></h1>

    <?php $desc = term_description();
    if (!empty($desc)): ?>
      <div class="archive-description"><?php echo wp_kses_post($desc); ?></div>
    <?php endif; ?>
  </header>

  <?php echo magpress_get_category_top_ad(); // reclamă sus, după descriere, înainte de articole ?>

  <?php if (is_category() && !is_paged()) { get_template_part('template-parts/featured-cat'); } ?>

  <?php if (have_posts()): ?>
    <div class="card-grid card-grid--3">
      <?php while (have_posts()): the_post();
        get_template_part('template-parts/card');
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
