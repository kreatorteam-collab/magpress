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

  <?php if (have_posts()): ?>
    <div class="card-grid">
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
  <?php else: ?>
    <p class="no-results">Nu există articole în această categorie.</p>
  <?php endif; ?>

  <?php get_template_part('template-parts/newsletter'); ?>

</div>

<?php get_footer();
