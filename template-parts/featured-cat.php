<?php
/**
 * Bloc „Recomandate de redacție” — articolele marcate ca recomandate, sus în
 * arhiva categoriei. Așezarea vine din Customizer: implicit „Mare + listă",
 * dar se poate alege grilă / mozaic / bandă / compact sau „Auto" (după câte
 * articole sunt bifate). Culorile vin din tokenii temei.
 */
if (!defined('ABSPATH')) { exit; }

$ids = magpress_catfeat_current_ids();
if (empty($ids)) return;

$q = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'post__in'            => $ids,
    'cat'                 => get_queried_object_id(), // lista poate fi cache-uită: re-validăm categoria
    'orderby'             => 'post__in',
    'posts_per_page'      => count($ids),
    'ignore_sticky_posts' => 1,
    'no_found_rows'       => true,
]);
if (!$q->have_posts()) { wp_reset_postdata(); return; }

$GLOBALS['magpress_catfeat_rendered'] = true;
$total  = $q->post_count;
$layout = magpress_catfeat_layout($total);
$i      = 0;
?>
<section class="mp-featcat" data-n="<?php echo (int) $total; ?>" aria-labelledby="mp-featcat-title">
  <div class="mp-featcat__head">
    <h2 class="mp-featcat__label" id="mp-featcat-title">Recomandate de redacție</h2>
  </div>

  <div class="mp-featcat__lay mp-featcat__lay--<?php echo esc_attr($layout); ?>" data-n="<?php echo (int) $total; ?>">
    <?php while ($q->have_posts()): $q->the_post(); $i++;

      // „Mare + listă": primul e card mare, restul rânduri într-un container.
      if ($layout === 'big') {
          if ($i === 1) {
              magpress_catfeat_card($layout, $total, true);
              if ($total > 1) echo '<div class="mp-featcat__list">';
          } else {
              magpress_catfeat_row();
          }
          continue;
      }

      // „Compact": doar rânduri dense.
      if ($layout === 'compact') {
          magpress_catfeat_crow();
          continue;
      }

      // Grilă / mozaic / bandă: carduri; primul e mai mare la mozaic.
      magpress_catfeat_card($layout, $total, $i === 1);
    endwhile;

    if ($layout === 'big' && $total > 1) echo '</div>'; ?>
  </div>

  <?php if ($layout === 'strip' && $total > 1): ?>
    <p class="mp-featcat__hintscroll">Trage lateral pentru restul articolelor</p>
  <?php endif; ?>
</section>
<?php wp_reset_postdata(); ?>
