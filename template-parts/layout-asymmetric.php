<?php
/**
 * Layout asimetric: 1 card mare (stânga, înălțime dublă) + 4 mici (dreapta, 2×2).
 * Folosește set_query_var pentru WP_Query primit din front-page.php.
 */
if (!defined('ABSPATH')) { exit; }
$q = get_query_var('magpress_section_query');
if (!$q || !$q->have_posts()) return;
?>
<div class="layout-asymmetric">
  <?php $idx = 0; while ($q->have_posts()): $q->the_post(); $idx++;
    $is_big = $idx === 1;
    $cats = get_the_category();
    $cat_name = $cats ? $cats[0]->name : '';
    $cat_link = $cats ? get_category_link($cats[0]) : '#';
  ?>
    <article class="magpress-card-overlay magpress-card-overlay--<?php echo $is_big ? 'big' : 'small'; ?>">
      <a href="<?php the_permalink(); ?>" class="card-link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail($is_big ? 'magpress-feature' : 'magpress-card', [
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => $is_big ? '(max-width: 768px) 100vw, 50vw' : '(max-width: 768px) 50vw, 25vw',
          ]);
        endif; ?>
        <div class="card-overlay">
          <?php if ($cat_name): ?>
            <span class="badge <?php echo esc_attr(magpress_category_color_class($cats[0])); ?>"><?php echo esc_html($cat_name); ?></span>
          <?php endif; ?>
          <h3 class="magpress-card-overlay__title"><?php the_title(); ?></h3>
        </div>
      </a>
    </article>
  <?php endwhile; ?>
</div>
