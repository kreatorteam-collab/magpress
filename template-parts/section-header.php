<?php
/**
 * Header sectiune: patrate accent + titlu + "Vezi toate" link.
 *
 * Folosire:
 *   set_query_var('section_title', 'Baie');
 *   set_query_var('section_url', get_category_link($cat));
 *   get_template_part('template-parts/section-header');
 */
if (!defined('ABSPATH')) { exit; }
$title = get_query_var('section_title', '');
$url   = get_query_var('section_url', '');
?>
<header class="section-header">
  <span class="section-header__icon" aria-hidden="true"></span>
  <h2 class="section-header__title"><?php echo esc_html($title); ?></h2>
  <?php if ($url): ?>
    <a class="section-header__link" href="<?php echo esc_url($url); ?>">Vezi toate</a>
  <?php endif; ?>
</header>
