<?php
/**
 * Footer — 4 coloane widget-uri + bottom bar.
 */
if (!defined('ABSPATH')) { exit; }
?>
</main><!-- /#main -->

<footer class="site-footer">
  <div class="site-footer__inner container">

    <div class="footer-cols">

      <div class="footer-col">
        <?php if (is_active_sidebar('footer-1')) { dynamic_sidebar('footer-1'); } else { ?>
          <h3 class="widget-title"><?php bloginfo('name'); ?></h3>
          <p><?php bloginfo('description'); ?></p>
        <?php } ?>
      </div>

      <div class="footer-col">
        <?php if (is_active_sidebar('footer-2')) { dynamic_sidebar('footer-2'); } else { ?>
          <h3 class="widget-title">Categorii</h3>
          <ul>
            <?php
            $cats = get_categories(['hide_empty' => true, 'number' => 6]);
            foreach ($cats as $c) {
                echo '<li><a href="' . esc_url(get_category_link($c)) . '">' . esc_html($c->name) . '</a></li>';
            }
            ?>
          </ul>
        <?php } ?>
      </div>

      <div class="footer-col">
        <?php if (is_active_sidebar('footer-3')) { dynamic_sidebar('footer-3'); } else { ?>
          <h3 class="widget-title">Linkuri utile</h3>
          <?php if (has_nav_menu('footer')) {
              wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'depth' => 1]);
          } else { ?>
            <p>Configurează un meniu „Footer" din Apperance → Menus.</p>
          <?php } ?>
        <?php } ?>
      </div>

      <div class="footer-col">
        <?php if (is_active_sidebar('footer-4')) { dynamic_sidebar('footer-4'); } else { ?>
          <h3 class="widget-title">Urmărește-ne</h3>
          <p>Adaugă widget Social Links în Footer Coloana 4 din Apperance → Widgets.</p>
        <?php } ?>
      </div>

    </div>

    <?php echo magpress_render_socials(); ?>

    <div class="footer-bottom">
      <p>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>. Toate drepturile rezervate.</p>
    </div>

  </div>
</footer>

<?php /* Modal căutare — deschis prin .search-toggle (checkbox-driven) */ ?>
<input type="checkbox" id="magpress-search-toggle" class="search-modal-checkbox" aria-hidden="true">
<div class="search-modal" role="dialog" aria-modal="true" aria-label="Căutare">
  <label for="magpress-search-toggle" class="search-modal__backdrop" aria-label="Închide căutarea"></label>
  <div class="search-modal__panel">
    <form action="<?php echo esc_url(home_url('/')); ?>" method="get" class="search-modal__form" role="search">
      <svg class="search-modal__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="search" name="s" placeholder="Caută articole..." class="search-modal__input" aria-label="Caută articole">
      <label for="magpress-search-toggle" class="search-modal__close" aria-label="Închide căutarea">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </label>
    </form>
    <p class="search-modal__hint">Scrie minim 2 caractere pentru a căuta</p>
  </div>
</div>

<?php /* Back-to-top — href="#" = fallback fără JS (sare sus); JS-ul de mai jos face scroll lin
     ȘI previne adăugarea fragmentului în URL (înainte era "#main", care oprea sub header). */ ?>
<a class="back-to-top" href="#" aria-label="Înapoi sus" title="Înapoi sus">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
</a>
<script>(function(){var b=document.querySelector('.back-to-top');if(!b)return;b.addEventListener('click',function(e){e.preventDefault();window.scrollTo({top:0,behavior:'smooth'});});})();</script>

<?php wp_footer(); ?>
</body>
</html>
