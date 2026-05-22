<?php
/**
 * Layout listă: imagine pătrată în stânga + titlu + meta în dreapta. Vertical, 5 articole.
 */
if (!defined('ABSPATH')) { exit; }
$q = get_query_var('magpress_section_query');
if (!$q || !$q->have_posts()) return;
?>
<div class="layout-list">
  <?php while ($q->have_posts()): $q->the_post();
    $cats = get_the_category();
    $cat_name = $cats ? $cats[0]->name : '';
    $cat_link = $cats ? get_category_link($cats[0]) : '#';
  ?>
    <article class="magpress-card-list">
      <a href="<?php the_permalink(); ?>" class="card-list__image" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail('magpress-card', [
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => '(max-width: 640px) 100px, 160px',
          ]);
        endif; ?>
      </a>
      <div class="card-list__body">
        <?php if ($cat_name): ?>
          <a class="card-list__category <?php echo esc_attr(magpress_category_color_class($cats[0])); ?>" href="<?php echo esc_url($cat_link); ?>"><?php echo esc_html($cat_name); ?></a>
        <?php endif; ?>
        <h3 class="card-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <div class="card-list__meta">
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
          <span class="sep">·</span>
          <span><?php echo (int) magpress_reading_time(); ?> min citire</span>
        </div>
      </div>
    </article>
  <?php endwhile; ?>
</div>
