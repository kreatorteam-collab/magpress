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

    <div class="footer-bottom">
      <p>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>. Toate drepturile rezervate.</p>
    </div>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
