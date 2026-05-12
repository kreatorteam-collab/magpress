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
      the_post_thumbnail('magpress-card', ['loading' => 'lazy', 'decoding' => 'async']);
    else: ?>
      <span class="card-image-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
  </div>
</article>
