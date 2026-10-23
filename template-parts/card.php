<?php
/**
 * Card articol (folosit in grid-uri: homepage category-rows, archive, related, search).
 *
 * Folosire: get_template_part('template-parts/card');
 *          get_template_part('template-parts/card', null, ['lcp' => true]); // primul din arhivă
 */
if (!defined('ABSPATH')) { exit; }
?>
<article class="card">
  <a class="card-image" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
    <?php if (has_post_thumbnail()):
      $mp_img_args = [
        'loading'  => 'lazy',
        'decoding' => 'async',
        // Mobil: 100vw, tabletă: 50vw, desktop: 25vw (4 carduri per rând)
        'sizes'    => '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 25vw',
      ];
      // v0.9.247: primul card dintr-o arhivă fără „Recomandat” deasupra e imaginea LCP
      // a paginii (Lighthouse: „LCP image was lazily loaded”). Pleacă imediat, cu
      // prioritate, și sare peste lazy-load-ul LiteSpeed — ca la featured.php.
      if (!empty($args['lcp'])) {
        $mp_img_args['loading']       = 'eager';
        $mp_img_args['fetchpriority'] = 'high';
        $mp_img_args['class']         = 'litespeed-no-lazy skip-lazy';
        $mp_img_args['data-no-lazy']  = '1';
      }
      // v0.9.208: dacă atașamentul nu are alt text propriu, folosim titlul articolului —
      // altfel ies imagini fără alt (accesibilitate + Google Images).
      $mp_alt = trim((string) get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_alt', true));
      if ($mp_alt === '') { $mp_img_args['alt'] = get_the_title(); }
      the_post_thumbnail('magpress-card', $mp_img_args);
    else: ?>
      <span class="card-image-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
  </div>
</article>
