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

<?php if ($layout === 'centered'): ?>

  <input type="checkbox" id="magpress-menu-toggle" class="menu-toggle-checkbox" aria-hidden="true">

  <header class="site-header site-header--centered">
    <div class="site-header__top container">
      <label for="magpress-menu-toggle" class="menu-toggle menu-toggle--mobile" aria-label="Deschide meniul">
        <span></span><span></span><span></span>
      </label>
      <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php if (has_custom_logo()):
          $logo_id = get_theme_mod('custom_logo');
          echo wp_get_attachment_image($logo_id, 'full', false, ['class' => 'site-logo', 'alt' => get_bloginfo('name'), 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'data-no-lazy' => '1']);
        else: ?>
          <span class="site-title"><?php bloginfo('name'); ?></span>
        <?php endif; ?>
      </a>
      <span class="header-spacer" aria-hidden="true"></span>
    </div>
  </header>

  <?php /* Bara de meniu — SEPARATA de header (child direct al body) ca sa functioneze position:sticky */ ?>
  <div class="site-nav-bar site-nav-bar--centered">
    <div class="site-nav-bar__inner container">
      <nav class="primary-nav" aria-label="Meniu principal">
        <?php magpress_render_primary_menu(); ?>
      </nav>
      <label for="magpress-search-toggle" class="search-toggle" aria-label="Caută" role="button" tabindex="0">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </label>
    </div>
  </div>

  <?php /* Dropdown meniu mobil — apare cand hamburger e bifat */ ?>
  <nav class="mobile-menu" aria-label="Meniu mobil">
    <?php magpress_render_primary_menu(); ?>
  </nav>

<?php else: /* inline */ ?>

  <input type="checkbox" id="magpress-menu-toggle" class="menu-toggle-checkbox" aria-hidden="true">

  <header class="site-header site-header--inline">
    <div class="site-header__inner container">
      <label for="magpress-menu-toggle" class="menu-toggle menu-toggle--mobile" aria-label="Deschide meniul">
        <span></span><span></span><span></span>
      </label>

      <a class="site-branding" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php if (has_custom_logo()):
          $logo_id = get_theme_mod('custom_logo');
          echo wp_get_attachment_image($logo_id, 'full', false, ['class' => 'site-logo', 'alt' => get_bloginfo('name'), 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'data-no-lazy' => '1']);
        else: ?>
          <span class="site-title"><?php bloginfo('name'); ?></span>
        <?php endif; ?>
      </a>

      <nav class="primary-nav" aria-label="Meniu principal">
        <?php magpress_render_primary_menu(); ?>
      </nav>

      <div class="header-tools">
        <label for="magpress-search-toggle" class="search-toggle" aria-label="Caută" role="button" tabindex="0">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        </label>
      </div>
    </div>
  </header>

  <nav class="mobile-menu" aria-label="Meniu mobil">
    <?php magpress_render_primary_menu(); ?>
  </nav>

<?php endif; ?>

<main id="main" class="site-main">
