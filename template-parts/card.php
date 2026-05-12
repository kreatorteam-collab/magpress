<?php
/**
 * Card articol (folosit in grid-uri: homepage category-rows, archive, related, search).
 *
 * Folosire: get_template_part('template-parts/card');
 */
if (!defined('ABSPATH')) { exit; }
?>
<article class="card">
  <a class="card-image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
    <?php if (has_post_thumbnail()):
      the_post_thumbnail('magpress-card', [
        'loading'  => 'lazy',
        'decoding' => 'async',
        // Mobil: 100vw, tabletă: 50vw, desktop: 25vw (4 carduri per rând)
        'sizes'    => '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 25vw',
      ]);
    else: ?>
      <span class="card-image-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
  </div>
</article>
