<?php
/**
 * Bara newsletter (v0.9.171, popup nativ din v0.9.172) — plic + text + abonare,
 * optional cu butonul „sursa preferata in Google" in aceeasi bara.
 * Config in Customizer („Magpress: Bara newsletter"); OFF implicit pe flota.
 *
 * Doua moduri:
 *  - popup  (implicit): buton care deschide o fereastra cu formularul TEMEI;
 *           emailurile se salveaza in site (REST /magpress/v1/subscribe →
 *           tabel magpress_subscribers, vizibil in Aspect → Abonati).
 *  - inline: shortcode de la un plugin de newsletter SAU input+buton care
 *           posteaza catre un serviciu extern (Mailchimp/Brevo).
 * Formularul e static (fara nonce) ca sa functioneze si din pagini cache-uite.
 */
if (!defined('ABSPATH')) { exit; }

$skin      = get_theme_mod('magpress_nlbar_skin', 'dark') === 'light' ? 'light' : 'dark';
$mode      = get_theme_mod('magpress_nlbar_mode', 'popup') === 'inline' ? 'inline' : 'popup';
$text      = trim((string) get_theme_mod('magpress_nlbar_text', ''));
$btn       = trim((string) get_theme_mod('magpress_nlbar_btn', 'Abonează-te'));
$action    = trim((string) get_theme_mod('magpress_nlbar_action', ''));
$field     = trim((string) get_theme_mod('magpress_nlbar_field', 'EMAIL'));
$shortcode = trim((string) get_theme_mod('magpress_nlbar_shortcode', ''));
$show_gd   = (int) get_theme_mod('magpress_nlbar_discover', 1);

$pop_title = trim((string) get_theme_mod('magpress_nlbar_pop_title', 'Abonează-te la newsletter'));
$pop_desc  = trim((string) get_theme_mod('magpress_nlbar_pop_desc', 'Primești articolele noastre pe email. Fără spam, te poți dezabona oricând.'));
$ask_name  = (int) get_theme_mod('magpress_nlbar_ask_name', 0);
$consent   = trim((string) get_theme_mod('magpress_nlbar_consent', ''));

$site_name = get_bloginfo('name');
if ($text === '') {
    $text = 'Lasă-ne emailul tău ca să-ți trimitem zilnic cele mai importante articole de pe ' . $site_name;
}
if ($btn === '') { $btn = 'Abonează-te'; }
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
    <?php if ($mode === 'popup'): ?>
      <button type="button" class="nlbar__btn" data-nlbar-open><?php echo esc_html($btn); ?></button>
    <?php elseif ($shortcode !== ''): ?>
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

<?php if ($mode === 'popup'): ?>
<div class="nlpop" id="nlpop" hidden>
  <div class="nlpop__backdrop" data-nlbar-close></div>
  <div class="nlpop__box" role="dialog" aria-modal="true" aria-labelledby="nlpop-title">
    <button type="button" class="nlpop__x" data-nlbar-close aria-label="Închide">&times;</button>
    <span class="nlpop__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2.5" y="5" width="19" height="14" rx="2"/>
        <path d="m3 6.5 9 6.5 9-6.5"/>
      </svg>
    </span>
    <h3 class="nlpop__title" id="nlpop-title"><?php echo esc_html($pop_title); ?></h3>
    <?php if ($pop_desc !== ''): ?><p class="nlpop__desc"><?php echo wp_kses_post($pop_desc); ?></p><?php endif; ?>
    <form class="nlpop__form" id="nlpop-form">
      <?php if ($ask_name): ?>
        <label class="screen-reader-text" for="nlpop-name">Numele tău</label>
        <input class="nlpop__input" type="text" id="nlpop-name" name="name" placeholder="Numele tău" autocomplete="name" />
      <?php endif; ?>
      <label class="screen-reader-text" for="nlpop-email">Adresa ta de email</label>
      <input class="nlpop__input" type="email" id="nlpop-email" name="email" placeholder="adresa@email.ro" autocomplete="email" required />
      <div class="nlpop__hp" aria-hidden="true">
        <label for="nlpop-website">Lasă gol</label>
        <input type="text" id="nlpop-website" name="website" tabindex="-1" autocomplete="off" />
      </div>
      <?php if ($consent !== ''): ?>
        <label class="nlpop__consent"><input type="checkbox" id="nlpop-consent" required /> <span><?php echo wp_kses_post($consent); ?></span></label>
      <?php endif; ?>
      <button class="nlpop__submit" type="submit"><?php echo esc_html($btn); ?></button>
      <p class="nlpop__msg" id="nlpop-msg" role="status"></p>
    </form>
  </div>
</div>
<script>
(function(){
  var pop = document.getElementById('nlpop');
  if (!pop) return;
  var form = document.getElementById('nlpop-form');
  var msg  = document.getElementById('nlpop-msg');
  var last = null;
  function open(e){ last = e.currentTarget; pop.hidden = false; document.body.style.overflow = 'hidden';
    var f = pop.querySelector('input:not([tabindex="-1"])'); if (f) f.focus(); }
  function close(){ pop.hidden = true; document.body.style.overflow = ''; if (last) last.focus(); }
  document.querySelectorAll('[data-nlbar-open]').forEach(function(b){ b.addEventListener('click', open); });
  pop.querySelectorAll('[data-nlbar-close]').forEach(function(b){ b.addEventListener('click', close); });
  document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape' && !pop.hidden) close(); });
  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    var btn = form.querySelector('.nlpop__submit');
    btn.disabled = true; msg.className = 'nlpop__msg'; msg.textContent = 'Se trimite…';
    var body = { email: (document.getElementById('nlpop-email')||{}).value || '', website: (document.getElementById('nlpop-website')||{}).value || '' };
    var n = document.getElementById('nlpop-name'); if (n) body.name = n.value;
    fetch('<?php echo esc_url_raw(rest_url('magpress/v1/subscribe')); ?>', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
    }).then(function(r){ return r.json().then(function(d){ return { ok: r.ok, d: d }; }); })
      .then(function(res){
        msg.textContent = (res.d && res.d.message) ? res.d.message : (res.ok ? 'Gata!' : 'Ceva n-a mers. Reîncearcă.');
        msg.className = 'nlpop__msg ' + (res.ok && res.d && res.d.ok ? 'is-ok' : 'is-err');
        if (res.ok && res.d && res.d.ok) { form.reset(); setTimeout(close, 2500); } else { btn.disabled = false; }
      })
      .catch(function(){ msg.textContent = 'Conexiune eșuată. Reîncearcă.'; msg.className = 'nlpop__msg is-err'; btn.disabled = false; });
  });
})();
</script>
<?php endif; ?>
