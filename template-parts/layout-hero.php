<?php
/**
 * Layout hero: 4 carduri mari portret cu titlu overlay peste imagine + gradient.
 */
if (!defined('ABSPATH')) { exit; }
$q = get_query_var('magpress_section_query');
if (!$q || !$q->have_posts()) return;
?>
<div class="layout-hero">
  <?php while ($q->have_posts()): $q->the_post();
    $cats = get_the_category();
    $cat_name = $cats ? $cats[0]->name : '';
  ?>
    <article class="magpress-card-overlay magpress-card-overlay--hero">
      <a href="<?php the_permalink(); ?>" class="card-link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail('magpress-feature', [
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => '(max-width: 480px) 100vw, (max-width: 900px) 50vw, 25vw',
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
