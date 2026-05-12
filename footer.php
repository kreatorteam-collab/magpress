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
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="footer-col">
          <?php if (is_active_sidebar('footer-' . $i)):
            dynamic_sidebar('footer-' . $i);
          else:
            // Fallback simplu daca nu sunt widget-uri configurate inca.
            if ($i === 1):
              echo '<h3 class="widget-title">' . esc_html(get_bloginfo('name')) . '</h3>';
              echo '<p>' . esc_html(get_bloginfo('description')) . '</p>';
            elseif ($i === 2):
              echo '<h3 class="widget-title">Categorii</h3><ul>';
              foreach (get_categories(['hide_empty' => true, 'number' => 6]) as $c) {
                  echo '<li><a href="' . esc_url(get_category_link($c)) . '">' . esc_html($c->name) . '</a></li>';
              }
              echo '</ul>';
            elseif ($i === 3):
              echo '<h3 class="widget-title">Linkuri utile</h3>';
              if (has_nav_menu('footer')) {
                  wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'depth' => 1]);
              }
            elseif ($i === 4):
              echo '<h3 class="widget-title">Urmărește-ne</h3>';
              echo '<p>Adaugă widget Social Links în Footer Coloana 4 din Apperance → Widgets.</p>';
            endif;
          endif; ?>
        </div>
      <?php endfor; ?>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>. Toate drepturile rezervate.</p>
    </div>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
