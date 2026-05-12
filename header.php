<?php
/**
 * Header — logo + meniu principal.
 */
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main">Sari la conținut</a>

<header class="site-header">
  <div class="site-header__inner container">

    <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php if (has_custom_logo()):
        $logo_id = get_theme_mod('custom_logo');
        $logo = wp_get_attachment_image($logo_id, 'full', false, ['class' => 'site-logo', 'alt' => get_bloginfo('name')]);
        echo $logo;
      else: ?>
        <span class="site-title"><?php bloginfo('name'); ?></span>
      <?php endif; ?>
    </a>

    <nav class="primary-nav" aria-label="Meniu principal">
      <?php if (has_nav_menu('primary')):
        wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'menu_class'     => 'menu',
          'depth'          => 2,
          'fallback_cb'    => false,
        ]);
      else: ?>
        <ul class="menu">
          <?php
          $cats = get_categories(['hide_empty' => true, 'number' => 6]);
          foreach ($cats as $c) {
              echo '<li><a href="' . esc_url(get_category_link($c)) . '">' . esc_html($c->name) . '</a></li>';
          }
          ?>
        </ul>
      <?php endif; ?>
    </nav>

    <div class="header-tools">
      <a class="search-toggle" href="<?php echo esc_url(home_url('/?s=')); ?>" aria-label="Caută">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </a>
    </div>

  </div>
</header>

<main id="main" class="site-main">
