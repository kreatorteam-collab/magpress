<?php
/**
 * Header — 2 layout-uri selectabile din Customizer:
 *  - inline (default): logo stânga + meniu + search pe aceeași linie
 *  - centered: logo sus centrat, meniu pe rândul 2
 */
if (!defined('ABSPATH')) { exit; }
$layout = magpress_header_layout();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class('header-' . esc_attr($layout)); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main">Sari la conținut</a>

<header class="site-header site-header--<?php echo esc_attr($layout); ?>">

  <?php if ($layout === 'centered'): ?>

    <div class="site-header__top container">
      <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php if (has_custom_logo()):
          $logo_id = get_theme_mod('custom_logo');
          echo wp_get_attachment_image($logo_id, 'full', false, ['class' => 'site-logo', 'alt' => get_bloginfo('name')]);
        else: ?>
          <span class="site-title"><?php bloginfo('name'); ?></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="site-header__nav-wrap">
      <div class="site-header__nav container">
        <nav class="primary-nav" aria-label="Meniu principal">
          <?php magpress_render_primary_menu(); ?>
        </nav>
        <a class="search-toggle" href="<?php echo esc_url(home_url('/?s=')); ?>" aria-label="Caută">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </a>
      </div>
    </div>

  <?php else: /* inline */ ?>

    <div class="site-header__inner container">
      <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php if (has_custom_logo()):
          $logo_id = get_theme_mod('custom_logo');
          echo wp_get_attachment_image($logo_id, 'full', false, ['class' => 'site-logo', 'alt' => get_bloginfo('name')]);
        else: ?>
          <span class="site-title"><?php bloginfo('name'); ?></span>
        <?php endif; ?>
      </a>

      <nav class="primary-nav" aria-label="Meniu principal">
        <?php magpress_render_primary_menu(); ?>
      </nav>

      <div class="header-tools">
        <a class="search-toggle" href="<?php echo esc_url(home_url('/?s=')); ?>" aria-label="Caută">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </a>
      </div>
    </div>

  <?php endif; ?>

</header>

<main id="main" class="site-main">
