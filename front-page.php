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
  // Featured posts — layout din Customizer
  $featured_layout = get_theme_mod('magpress_featured_layout', 'two');
  if ($featured_layout !== 'hidden'):
    $counts = ['two' => 2, 'three' => 3, 'one_two' => 3, 'mosaic' => 5, 'hero_banner' => 1, 'big_list' => 6];
    $count = $counts[$featured_layout] ?? 2;
    $big_first_layouts = ['one_two', 'mosaic', 'big_list'];

    // Categorii excluse din featured (slug-uri configurate în Customizer)
    $excluded_cat_ids = magpress_featured_excluded_cat_ids();

    // Sticky first; fallback la ultimele articole
    $featured_args = [
      'posts_per_page'      => $count,
      'post__in'            => get_option('sticky_posts'),
      'ignore_sticky_posts' => 1,
      'post_status'         => 'publish',
    ];
    if (!empty($excluded_cat_ids)) {
        $featured_args['category__not_in'] = $excluded_cat_ids;
    }
    $featured = new WP_Query($featured_args);
    if (!$featured->have_posts()) {
        wp_reset_postdata();
        $fallback_args = ['posts_per_page' => $count, 'post_status' => 'publish'];
        if (!empty($excluded_cat_ids)) {
            $fallback_args['category__not_in'] = $excluded_cat_ids;
        }
        $featured = new WP_Query($fallback_args);
    }
    if ($featured->have_posts()): ?>
      <section class="featured-grid featured-grid--<?php echo esc_attr($featured_layout); ?>">
        <?php
        // Layout 'big_list' = render special: primul ca card mare + restul ca listă text
        if ($featured_layout === 'big_list'):
          $idx = 0; while ($featured->have_posts()): $featured->the_post(); $idx++;
            if ($idx === 1) {
              // Primul = card mare
              set_query_var('magpress_featured_is_big', true);
              get_template_part('template-parts/featured');
            } else {
              if ($idx === 2) echo '<ol class="featured-list">';
              $cats = get_the_category();
              $cat_name = $cats ? $cats[0]->name : '';
              ?>
              <li class="featured-list__item">
                <a href="<?php the_permalink(); ?>" class="featured-list__link">
                  <span class="featured-list__num"><?php echo $idx - 1; ?></span>
                  <div class="featured-list__body">
                    <?php if ($cat_name): ?>
                      <span class="featured-list__cat"><?php echo esc_html($cat_name); ?></span>
                    <?php endif; ?>
                    <h3 class="featured-list__title"><?php the_title(); ?></h3>
                  </div>
                </a>
              </li>
              <?php
              if ($idx === $count) echo '</ol>';
            }
          endwhile;
        else:
          // Toate celelalte layouts → cardurile featured standard
          $idx = 0; while ($featured->have_posts()): $featured->the_post(); $idx++;
            set_query_var('magpress_featured_is_big', in_array($featured_layout, $big_first_layouts, true) && $idx === 1);
            get_template_part('template-parts/featured');
          endwhile;
        endif;
        ?>
      </section>
    <?php endif; wp_reset_postdata();
  endif; ?>

  <?php echo magpress_render_web_stories_carousel(8); // News feature toggle ?>

  <?php get_template_part('template-parts/google-discover'); ?>

  <?php echo magpress_render_most_read(5); // News feature toggle ?>

  <?php
  // Sloturi homepage — configurate in Customizer (12 max, mix category + ad)
  foreach (magpress_homepage_slots() as $slot):
      if ($slot['type'] === 'ad'):
        echo magpress_render_ad_html($slot['html'], 'homepage');
        continue;
      endif;

      // type === 'category'
      $cat    = $slot['cat'];
      $count  = $slot['count'];
      $layout = $slot['layout'] ?? 'grid';
      $q = new WP_Query([
          'posts_per_page'      => $count,
          'cat'                 => $cat->term_id,
          'post_status'         => 'publish',
          'ignore_sticky_posts' => 1,
      ]);

      set_query_var('section_title', $cat->name);
      set_query_var('section_url', get_category_link($cat));
      set_query_var('magpress_section_query', $q);
  ?>
    <section class="category-row category-row--<?php echo esc_attr($layout); ?>">
      <?php get_template_part('template-parts/section-header'); ?>
      <?php if ($q->have_posts()):
        if ($layout === 'asymmetric'):
          get_template_part('template-parts/layout-asymmetric');
        elseif ($layout === 'hero'):
          get_template_part('template-parts/layout-hero');
        elseif ($layout === 'list'):
          get_template_part('template-parts/layout-list');
        else: ?>
          <div class="card-grid">
            <?php while ($q->have_posts()): $q->the_post();
              get_template_part('template-parts/card');
            endwhile; ?>
          </div>
        <?php endif;
      else: ?>
        <p class="no-results-slot" style="padding:1rem;color:var(--c-text-muted);font-style:italic;">Categoria „<?php echo esc_html($cat->name); ?>" nu are articole publicate încă.</p>
      <?php endif; ?>
    </section>
  <?php wp_reset_postdata(); endforeach; ?>

</div>

<?php get_footer();
