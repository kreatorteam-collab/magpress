<?php
/**
 * Homepage:
 *  - 2 featured posts (sticky sau ultimele daca nu exista sticky)
 *  - 3-4 randuri categorii (8 articole/rand) — categoriile configurabile via site-config.json
 *  - Newsletter
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>

<div class="container site-content">

  <?php
  // Featured (sticky first; fallback la ultimele 2 articole).
  $featured_args = [
    'posts_per_page'      => 2,
    'post__in'            => get_option('sticky_posts'),
    'ignore_sticky_posts' => 1,
    'post_status'         => 'publish',
  ];
  $featured = new WP_Query($featured_args);
  if (!$featured->have_posts()) {
      wp_reset_postdata();
      $featured = new WP_Query(['posts_per_page' => 2, 'post_status' => 'publish']);
  }
  if ($featured->have_posts()): ?>
    <section class="featured-grid">
      <?php while ($featured->have_posts()): $featured->the_post();
        get_template_part('template-parts/featured');
      endwhile; ?>
    </section>
  <?php endif; wp_reset_postdata(); ?>

  <?php
  // Sloturi homepage — configurate in Customizer (12 max, mix category + ad)
  foreach (magpress_homepage_slots() as $slot):
      if ($slot['type'] === 'ad'): ?>
        <div class="ad-slot">
          <?php echo $slot['html']; // intentional: cod AdSense/HTML salvat de admin ?>
        </div>
      <?php continue; endif;

      // type === 'category'
      $cat   = $slot['cat'];
      $count = $slot['count'];
      $q = new WP_Query([
          'posts_per_page'      => $count,
          'cat'                 => $cat->term_id,
          'post_status'         => 'publish',
          'ignore_sticky_posts' => 1,
      ]);
      if (!$q->have_posts()) { wp_reset_postdata(); continue; }

      set_query_var('section_title', $cat->name);
      set_query_var('section_url', get_category_link($cat));
  ?>
    <section class="category-row">
      <?php get_template_part('template-parts/section-header'); ?>
      <div class="card-grid">
        <?php while ($q->have_posts()): $q->the_post();
          get_template_part('template-parts/card');
        endwhile; ?>
      </div>
    </section>
  <?php wp_reset_postdata(); endforeach; ?>

</div>

<?php get_footer();
