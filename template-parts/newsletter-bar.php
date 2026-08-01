<?php
/**
 * Bara newsletter (v0.9.171) — plic + text + formular email, optional cu butonul
 * „sursa preferata in Google" in aceeasi bara. Config in Customizer
 * (sectiunea „Magpress: Bara newsletter"); OFF implicit pe flota.
 *
 * Formularul: (1) daca e completat un shortcode/HTML de la un plugin de newsletter,
 * il randam pe acela; (2) altfel, daca e setat un URL de actiune, randam input+buton
 * simplu care posteaza acolo; (3) daca niciunul, bara ramane doar text (+ Google).
 */
if (!defined('ABSPATH')) { exit; }

$skin      = get_theme_mod('magpress_nlbar_skin', 'dark') === 'light' ? 'light' : 'dark';
$text      = trim((string) get_theme_mod('magpress_nlbar_text', ''));
$btn       = trim((string) get_theme_mod('magpress_nlbar_btn', 'Abonează-te'));
$action    = trim((string) get_theme_mod('magpress_nlbar_action', ''));
$field     = trim((string) get_theme_mod('magpress_nlbar_field', 'EMAIL'));
$shortcode = trim((string) get_theme_mod('magpress_nlbar_shortcode', ''));
$show_gd   = (int) get_theme_mod('magpress_nlbar_discover', 1);

$site_name = get_bloginfo('name');
if ($text === '') {
    $text = 'Lasă-ne emailul tău ca să-ți trimitem zilnic cele mai importante articole de pe ' . $site_name;
}
if ($field === '') { $field = 'EMAIL'; }
$follow_url = 'https://www.google.com/preferences/source?q=' . rawurlencode(home_url('/'));
?>
<aside class="nlbar nlbar--<?php echo esc_attr($skin); ?>" aria-label="Abonare newsletter">
  <span class="nlbar__icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <rect x="2.5" y="5" width="19" height="14" rx="2"/>
      <path d="m3 6.5 9 6.5 9-6.5"/>
    </svg>
  </span>
  <p class="nlbar__text"><?php echo wp_kses_post($text); ?></p>
  <div class="nlbar__actions">
    <?php if ($shortcode !== ''): ?>
      <div class="nlbar__form nlbar__form--custom"><?php echo do_shortcode($shortcode); ?></div>
    <?php elseif ($action !== ''): ?>
      <form class="nlbar__form" action="<?php echo esc_url($action); ?>" method="post" target="_blank" rel="noopener">
        <label class="screen-reader-text" for="nlbar-email">Adresa ta de email</label>
        <input class="nlbar__input" type="email" id="nlbar-email" name="<?php echo esc_attr($field); ?>" placeholder="adresa@email.ro" required />
        <button class="nlbar__btn" type="submit"><?php echo esc_html($btn); ?></button>
      </form>
    <?php endif; ?>
    <?php if ($show_gd): ?>
      <a class="nlbar__gbtn" href="<?php echo esc_url($follow_url); ?>" target="_blank" rel="noopener nofollow">
        <span class="nlbar__gicon" aria-hidden="true">
          <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
            <path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
            <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
          </svg>
        </span>
        Adaugă-ne ca sursă preferată în Google
      </a>
    <?php endif; ?>
  </div>
</aside>
