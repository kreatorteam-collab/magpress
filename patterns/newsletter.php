<?php
/**
 * Title: Newsletter signup
 * Slug: magpress/newsletter
 * Categories: featured, magpress
 * Description: Box newsletter inline înainte de footer. Form HTML simplu — integrarea cu MailChimp/MailerLite se face per-site cu shortcode.
 * Keywords: newsletter, abonare, email
 * Viewport Width: 1400
 */
?>
<!-- wp:group {"className":"magpress-newsletter","layout":{"type":"constrained"}} -->
<div class="wp-block-group magpress-newsletter">

  <!-- wp:heading {"textAlign":"center","level":2} -->
  <h2 class="wp-block-heading has-text-align-center">Idei pentru casa ta, livrate în inbox</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph {"align":"center"} -->
  <p class="has-text-align-center">Abonează-te la newsletter și primește cele mai bune articole o dată pe săptămână.</p>
  <!-- /wp:paragraph -->

  <!-- wp:html -->
  <form action="#" method="post" novalidate>
    <input type="email" name="email" placeholder="adresa@email.ro" required>
    <button type="submit">Abonează-mă</button>
  </form>
  <!-- /wp:html -->

</div>
<!-- /wp:group -->
