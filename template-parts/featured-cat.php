<?php
/**
 * Bloc „Recomandate de redacție” — articolele marcate ca recomandate, sus în
 * arhiva categoriei. Podium: carduri egale, fiecare cu eticheta „Recomandat”
 * în colțul din stânga-jos al imaginii. Culorile vin din tokenii temei
 * (Personalizare → Culori), nimic hardcodat.
 */
if (!defined('ABSPATH')) { exit; }

$ids = magpress_catfeat_current_ids();
if (empty($ids)) return;

$q = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__in'            => $ids,
    'orderby'             => 'post__in',
    'posts_per_page'      => count($ids),
    'ignore_sticky_posts' => 1,
    'no_found_rows'       => true,
]);
if (!$q->have_posts()) { wp_reset_postdata(); return; }

$GLOBALS['magpress_catfeat_rendered'] = true;
$total = $q->post_count;

// Data celui mai recent articol recomandat = când s-a schimbat ultima dată blocul.
$stamps = [];
foreach ($q->posts as $p) { $stamps[] = (int) get_post_time('U', false, $p); }
$newest = $stamps ? date_i18n(get_option('date_format'), max($stamps)) : '';

$star = '<svg class="mp-featcat__star" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 5.98 6.6.96-4.78 4.66 1.13 6.57L12 17.57l-5.9 3.1 1.13-6.57L2.45 9.44l6.6-.96L12 2.5z"/></svg>';
$i = 0;
?>
<section class="mp-featcat" aria-label="Articole recomandate">
  <div class="mp-featcat__head">
    <span class="mp-featcat__label">Recomandate de redacție</span>
    <?php if ($newest): ?><span class="mp-featcat__hint">actualizat <?php echo esc_html($newest); ?></span><?php endif; ?>
  </div>

  <div class="mp-featcat__grid mp-featcat__grid--n<?php echo (int) $total; ?>">
    <?php while ($q->have_posts()): $q->the_post(); $i++;
      // Prima imagine e candidatul LCP al paginii de categorie → eager, restul lazy.
      $img_args = [
          'decoding' => 'async',
          'sizes'    => '(max-width: 760px) 150px, (max-width: 1100px) 50vw, 33vw',
      ];
      if ($i === 1) {
          $img_args['loading']       = 'eager';
          $img_args['fetchpriority'] = 'high';
          $img_args['class']         = 'litespeed-no-lazy skip-lazy';
          $img_args['data-no-lazy']  = '1';
      } else {
          $img_args['loading'] = 'lazy';
      }
    ?>
    <article class="mp-featcat__item">
      <a class="mp-featcat__img" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail('magpress-card', $img_args);
        else: ?>
          <span class="card-image-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="mp-featcat__tag"><?php echo $star; ?> Recomandat</span>
      </a>
      <div class="mp-featcat__body">
        <h2 class="mp-featcat__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <span class="mp-featcat__meta"><?php echo esc_html(get_the_date()); ?></span>
      </div>
    </article>
    <?php endwhile; ?>
  </div>
</section>
<?php wp_reset_postdata(); ?>
