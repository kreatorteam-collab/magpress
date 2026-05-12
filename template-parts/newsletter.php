<?php
/**
 * Newsletter signup — inline inainte de footer (homepage / archive / single).
 * Form-ul e static; integrarea cu MailerLite/MailChimp se face per-site cu shortcode in viitor.
 */
if (!defined('ABSPATH')) { exit; }
?>
<section class="newsletter">
  <h2 class="newsletter__title">Idei pentru casa ta, livrate în inbox</h2>
  <p class="newsletter__desc">Abonează-te la newsletter și primește cele mai bune articole o dată pe săptămână.</p>
  <form class="newsletter__form" action="#" method="post" novalidate>
    <input type="email" name="email" placeholder="adresa@email.ro" required aria-label="Adresă email">
    <button type="submit">Abonează-mă</button>
  </form>
</section>
