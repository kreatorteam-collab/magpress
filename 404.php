<?php
/**
 * 404.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>

<div class="container site-content">
  <section class="error-404">
    <h1>404 — Pagină negăsită</h1>
    <p>Pagina pe care o căutați nu există sau a fost mutată.</p>

    <form class="search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get" role="search">
      <input type="search" name="s" placeholder="Încearcă o căutare..." aria-label="Caută">
      <button type="submit">Caută</button>
    </form>

    <p><a class="btn" href="<?php echo esc_url(home_url('/')); ?>">Înapoi la pagina principală</a></p>
  </section>
</div>

<?php get_footer();
