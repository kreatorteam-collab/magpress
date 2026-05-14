<?php
/**
 * Card featured (folosit pe homepage in sectiunea featured).
 */
if (!defined('ABSPATH')) { exit; }
$cats = get_the_category();
$is_big = (bool) get_query_var('magpress_featured_is_big', false);
?>
<article class="featured-card<?php echo $is_big ? ' featured-card--big' : ''; ?>">
  <a class="featured-card__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
    <?php if (has_post_thumbnail()):
      the_post_thumbnail('magpress-feature', [
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'async',
        'sizes'         => '(max-width: 768px) 100vw, 50vw',
        'class'         => 'litespeed-no-lazy skip-lazy',
        'data-no-lazy'  => '1',
      ]);
    endif; ?>
  </a>
  <div class="featured-card__content">
    <?php if ($cats): ?>
      <a class="badge" href="<?php echo esc_url(get_category_link($cats[0])); ?>"><?php echo esc_html($cats[0]->name); ?></a>
    <?php endif; ?>
    <h2 class="featured-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
  </div>
</article>
