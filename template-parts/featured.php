<?php
/**
 * Card featured (folosit pe homepage in sectiunea featured).
 */
if (!defined('ABSPATH')) { exit; }
$cats = get_the_category();
$is_big = (bool) get_query_var('magpress_featured_is_big', false);
$idx = (int) get_query_var('magpress_featured_idx', 1);

// Doar PRIMUL card primește prioritate maximă (LCP). Restul se încarcă lazy normal.
// Multiple imagini eager+fetchpriority=high concurează pentru bandwidth și întârzie LCP-ul real.
$is_first = ($idx === 1);
$img_args = [
    'decoding' => 'async',
    'sizes'    => '(max-width: 768px) 100vw, 50vw',
];
if ($is_first) {
    $img_args['loading']       = 'eager';
    $img_args['fetchpriority'] = 'high';
    $img_args['class']         = 'litespeed-no-lazy skip-lazy';
    $img_args['data-no-lazy']  = '1';
} else {
    $img_args['loading'] = 'lazy';
}
?>
<article class="featured-card<?php echo $is_big ? ' featured-card--big' : ''; ?>">
  <a class="featured-card__image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
    <?php if (has_post_thumbnail()):
      the_post_thumbnail('magpress-feature', $img_args);
    endif; ?>
  </a>
  <div class="featured-card__content">
    <?php if ($cats): ?>
      <a class="badge <?php echo esc_attr(magpress_category_color_class($cats[0])); ?>" href="<?php echo esc_url(get_category_link($cats[0])); ?>"><?php echo esc_html($cats[0]->name); ?></a>
    <?php endif; ?>
    <h2 class="featured-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
  </div>
</article>
