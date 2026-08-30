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
  // Articolele deja afisate pe homepage (featured + sloturi) — pentru optiunea „Fara dubluri" per slot
  $mp_shown_ids = [];

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
      'no_found_rows'       => true, // v0.9.208: fara paginare aici -> fara SQL_CALC_FOUND_ROWS
    ];
    if (!empty($excluded_cat_ids)) {
        $featured_args['category__not_in'] = $excluded_cat_ids;
    }
    $featured = new WP_Query($featured_args);
    if (!$featured->have_posts()) {
        wp_reset_postdata();
        $fallback_args = ['posts_per_page' => $count, 'post_status' => 'publish', 'no_found_rows' => true];
        if (!empty($excluded_cat_ids)) {
            $fallback_args['category__not_in'] = $excluded_cat_ids;
        }
        $featured = new WP_Query($fallback_args);
    }
    if ($featured->have_posts()) {
        foreach ($featured->posts as $mp_fp) { $mp_shown_ids[] = (int) $mp_fp->ID; }
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
            set_query_var('magpress_featured_idx', $idx);
            get_template_part('template-parts/featured');
          endwhile;
        endif;
        ?>
      </section>
    <?php endif; wp_reset_postdata();
  endif; ?>

  <?php echo magpress_render_web_stories_carousel(8); // News feature toggle ?>

  <?php
  /* Titlu H1 homepage — plasat DEASUPRA bannerului Google Discover (cerință user).
     Text editabil din Customizer (gol = nume site + descriere); vizibil+centrat SAU ascuns pt SEO. */
  $mp_h1 = trim((string) get_theme_mod('magpress_home_h1_text', ''));
  if ($mp_h1 === '') {
      $mp_name = get_bloginfo('name');
      $mp_desc = trim((string) get_bloginfo('description'));
      $mp_h1 = ($mp_desc && strcasecmp($mp_desc, $mp_name) !== 0) ? $mp_name . ' - ' . $mp_desc : $mp_name;
  }
  if (get_theme_mod('magpress_home_h1_visible', 1)):
      // Stil IDENTIC cu linkurile din meniu (font, mărime, majuscule, spațiere).
      // Inline ca să reziste la UCSS / CSS combinat stale de la LiteSpeed.
      $mp_mf = function_exists('magpress_menu_font_config') ? magpress_menu_font_config() : null;
      $mp_h1_style = 'text-align:center;color:#2c2c2c;text-transform:uppercase;line-height:1.4;margin:.75rem 0 1.25rem;'
          . ($mp_mf
              ? 'font-family:' . $mp_mf['family'] . ';font-size:1rem;font-weight:600;letter-spacing:.08em'
              : 'font-family:inherit;font-size:.9rem;font-weight:700;letter-spacing:.04em');
  ?>
  <h1 class="home-h1" style="<?php echo esc_attr($mp_h1_style); ?>"><?php echo esc_html($mp_h1); ?></h1>
  <?php else: /* ascuns pentru SEO — hiding INLINE (rezistă la UCSS, care poate strippa .screen-reader-text) */ ?>
  <h1 class="home-h1" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0"><?php echo esc_html($mp_h1); ?></h1>
  <?php endif; ?>

  <?php if ((int) get_theme_mod('magpress_discover_position', 0) === 0) get_template_part('template-parts/google-discover'); ?>

  <?php echo magpress_render_most_read(5); // News feature toggle ?>

  <?php magpress_homepage_after_slot(0); // insertii pozitionate PE 0 = deasupra sloturilor ?>

  <?php
  // Sloturi homepage — configurate in Customizer (24 max, mix category + ad)
  $mp_slot_i = 0;
  foreach (magpress_homepage_slots() as $slot):
      if ($slot['type'] === 'ad'):
        $ad_title    = $slot['title']    ?? '';
        $ad_subtitle = $slot['subtitle'] ?? '';
        if ($ad_title !== '' || $ad_subtitle !== ''): ?>
          <div class="ad-slot-header">
            <?php if ($ad_title !== ''): ?><h2 class="ad-slot-header__title"><?php echo esc_html($ad_title); ?></h2><?php endif; ?>
            <?php if ($ad_subtitle !== ''): ?><p class="ad-slot-header__subtitle"><?php echo esc_html($ad_subtitle); ?></p><?php endif; ?>
          </div>
        <?php endif;
        echo magpress_render_ad_html($slot['html'], 'homepage');
        $mp_slot_i++;
        magpress_homepage_after_slot($mp_slot_i);
        continue;
      endif;

      // type === 'category'
      $cat     = $slot['cat'];
      $cat_ids = $slot['cat_ids'] ?? [$cat->term_id];
      $count   = $slot['count'];
      $layout  = $slot['layout'] ?? 'grid';
      $offset  = (int) ($slot['offset'] ?? 0);
      $q_args = [
          'posts_per_page'      => $count,
          'post_status'         => 'publish',
          'ignore_sticky_posts' => 1,
          'no_found_rows'       => true, // sloturile nu se pagineaza
      ];
      if ($offset > 0) $q_args['offset'] = $offset;
      // „Fara dubluri": sare peste articolele deja afisate mai sus (featured + sloturile anterioare)
      if (!empty($slot['nodup']) && !empty($mp_shown_ids)) {
          $q_args['post__not_in'] = array_values(array_unique($mp_shown_ids));
      }
      if (count($cat_ids) === 1) {
          // O singură categorie: folosim `cat` (include automat sub-categorii)
          $q_args['cat'] = (int) $cat_ids[0];
      } else {
          // Multi-categorie: agregăm cu category__in (NU include sub-categorii — user a ales explicit ce vrea)
          $q_args['category__in'] = array_map('intval', $cat_ids);
      }
      $q = new WP_Query($q_args);
      foreach ($q->posts as $mp_sp) { $mp_shown_ids[] = (int) $mp_sp->ID; }

      // Titlu secțiune: dacă e o singură categorie → numele ei + link.
      // Multi: agregăm numele („Sport, Fitness, Atletism") cu link la prima.
      if (count($cat_ids) === 1) {
          set_query_var('section_title', $cat->name);
          set_query_var('section_url', get_category_link($cat));
      } else {
          $names = [];
          foreach ($cat_ids as $cid) {
              $c = get_category($cid);
              if ($c && !is_wp_error($c)) $names[] = $c->name;
          }
          set_query_var('section_title', implode(', ', $names));
          set_query_var('section_url', get_category_link($cat));
      }
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
  <?php wp_reset_postdata(); $mp_slot_i++; magpress_homepage_after_slot($mp_slot_i); endforeach; ?>

  <?php magpress_homepage_after_slot(-1, $mp_slot_i); // pozitii peste numarul de sloturi = jos de tot ?>

  <?php echo magpress_render_partners(); ?>

</div>

<?php get_footer();
