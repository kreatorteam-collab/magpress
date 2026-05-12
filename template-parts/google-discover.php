<?php
/**
 * Google Discover follow box — CTA pentru a marca site-ul ca sursa preferata in Google Discover.
 * Toggle din Customizer: magpress_show_google_discover (default ON).
 */
if (!defined('ABSPATH')) { exit; }
if (!get_theme_mod('magpress_show_google_discover', 1)) { return; }

$site_name = get_bloginfo('name');
$site_url  = home_url('/');
$follow_url = 'https://www.google.com/preferences/source?q=' . rawurlencode($site_url);
?>
<aside class="gdiscover" aria-label="Adaugă <?php echo esc_attr($site_name); ?> ca sursă preferată Google Discover">
  <div class="gdiscover__icon" aria-hidden="true">
    <svg width="36" height="36" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
      <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
      <path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
      <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
      <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
    </svg>
  </div>
  <div class="gdiscover__body">
    <p class="gdiscover__title">Vrei <strong><?php echo esc_html($site_name); ?></strong> direct în Google Discover?</p>
    <p class="gdiscover__desc">Marchează-ne ca <strong>sursă preferată</strong> și articolele noastre apar mai des în feed-ul tău.</p>
  </div>
  <a class="gdiscover__btn" href="<?php echo esc_url($follow_url); ?>" target="_blank" rel="noopener nofollow">ADAUGĂ <span aria-hidden="true">→</span></a>
</aside>
