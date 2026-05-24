<?php
/**
 * Magpress v0.3 — tema clasica PHP.
 *
 * Filozofie: HTML emis 1:1 de noi, fara FSE. LiteSpeed face cache/minify, SEOPress face meta/schema.
 * Customizarea per-site se face prin /wp-content/themes/magpress-config/site-config.json
 */

if (!defined('ABSPATH')) { exit; }

define('MAGPRESS_VERSION', '0.3.0');
define('MAGPRESS_DIR', get_template_directory());
define('MAGPRESS_URI', get_template_directory_uri());

/**
 * Setup tema.
 */
function magpress_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 240,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', ['comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    set_post_thumbnail_size(600, 400, true);
    add_image_size('magpress-card',    600, 400,  true);
    add_image_size('magpress-feature', 1200, 750, true);
    add_image_size('magpress-thumb',   120, 120,  true);

    load_theme_textdomain('magpress', MAGPRESS_DIR . '/languages');
}
add_action('after_setup_theme', 'magpress_setup');

/**
 * Dezactivează New Relic Browser Agent (RUM script).
 * Acel script trimite trafic spre bam.nr-data.net + adaugă 4+s latență la PageSpeed.
 * Folosit doar dacă serverul are extension PHP New Relic activă.
 */
function magpress_disable_newrelic_browser() {
    if (function_exists('newrelic_disable_autorum')) {
        newrelic_disable_autorum();
    }
    if (function_exists('newrelic_ignore_apdex')) {
        // Optional: nu trimite metrics deloc spre New Relic Server
        // newrelic_ignore_transaction();
    }
}
add_action('init', 'magpress_disable_newrelic_browser', 1);

/**
 * Inlaturam bloat-ul WordPress default.
 */
function magpress_remove_bloat() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');

    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
    remove_action('wp_footer', 'wp_global_styles_render_svg_filters');
}
add_action('init', 'magpress_remove_bloat');

/**
 * Dezactivam jQuery pe frontend.
 */
function magpress_deregister_jquery() {
    if (!is_admin() && !is_user_logged_in()) {
        wp_deregister_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'magpress_deregister_jquery', 100);

/**
 * Dezactivam CSS-ul block-library default (nu folosim blocuri pe templateuri).
 */
function magpress_dequeue_block_styles() {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('wc-blocks-style');
}
add_action('wp_enqueue_scripts', 'magpress_dequeue_block_styles', 200);

/**
 * Enqueue CSS tema.
 */
function magpress_enqueue_assets() {
    // News font preset — dacă e setat (≠ default), override-uiește magpress_google_font + adaugă CSS pentru h1-h3/body
    $preset = magpress_news_font_preset_config();

    if ($preset) {
        // Preset preia controlul
        $url = 'https://fonts.googleapis.com/css2?family=' . $preset['google'] . '&display=swap';
        wp_enqueue_style('magpress-google-font', $url, [], null);
    } else {
        // Comportament default: Open Sans (sau ce e setat în Customizer)
        $google_font = trim((string) get_theme_mod('magpress_google_font', 'Open Sans:400,700'));
        if ($google_font !== '') {
            $url = 'https://fonts.googleapis.com/css2?' . http_build_query([
                'family'  => $google_font,
                'display' => 'swap',
            ], '', '&', PHP_QUERY_RFC3986);
            // http_build_query inlocuieste ":" cu "%3A" — corectam pentru sintaxa css2
            $url = str_replace('%3A', ':', $url);
            wp_enqueue_style('magpress-google-font', $url, [], null);
        }
    }

    $css_path = MAGPRESS_DIR . '/assets/css/style.css';
    $ver = file_exists($css_path) ? filemtime($css_path) : MAGPRESS_VERSION;
    wp_enqueue_style('magpress-main', MAGPRESS_URI . '/assets/css/style.css', [], $ver);

    // Branding CSS — atasat dupa style.css (cascade-ul il face sa castige peste defaults)
    $branding = magpress_get_branding_css();
    if ($branding) {
        wp_add_inline_style('magpress-main', $branding);
    }

    // News font preset override (heading + body family) — atașat după main ca să câștige cascada
    if ($preset) {
        $css_preset = 'body,.entry-content,.article-meta,.article-excerpt{font-family:' . $preset['body'] . '}'
                    . 'h1,h2,h3,h4,h5,h6,.article-title,.featured-card__title,.card-title,.section-header__title,.most-read-widget__title{font-family:' . $preset['heading'] . '}';
        wp_add_inline_style('magpress-main', $css_preset);
    }
}

/**
 * Returnează config-ul pentru news font preset (sau null dacă e default).
 * Folosit de enqueue + preconnect.
 */
function magpress_news_font_preset_config() {
    $key = get_theme_mod('magpress_news_font_preset', 'default');
    $map = [
        'editorial' => [
            'label'   => 'News editorial (Merriweather + Inter)',
            'google'  => 'Merriweather:wght@700;900&family=Inter:wght@400;500;700',
            'heading' => '"Merriweather",Georgia,"Times New Roman",serif',
            'body'    => '"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif',
        ],
        'magazine' => [
            'label'   => 'Magazine premium (Playfair Display + Lato)',
            'google'  => 'Playfair+Display:wght@700;900&family=Lato:wght@400;700',
            'heading' => '"Playfair Display",Georgia,"Times New Roman",serif',
            'body'    => '"Lato",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif',
        ],
        'digital' => [
            'label'   => 'Modern digital (Lora + Inter)',
            'google'  => 'Lora:wght@600;700&family=Inter:wght@400;500;700',
            'heading' => '"Lora",Georgia,"Times New Roman",serif',
            'body'    => '"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif',
        ],
        'compact' => [
            'label'   => 'News compact (Roboto Slab + Roboto)',
            'google'  => 'Roboto+Slab:wght@700;900&family=Roboto:wght@400;500;700',
            'heading' => '"Roboto Slab",Georgia,"Times New Roman",serif',
            'body'    => '"Roboto",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif',
        ],
    ];
    return isset($map[$key]) ? $map[$key] : null;
}

/**
 * Preconnect la Google Fonts (mic boost PageSpeed) — atat CSS-ul cat si fisierele woff2.
 */
function magpress_preconnect_google_fonts($urls, $relation_type) {
    // Preset news activ → preconnect sigur
    $has_preset = (bool) magpress_news_font_preset_config();
    $has_default = trim((string) get_theme_mod('magpress_google_font', 'Open Sans:400,700')) !== '';
    if (!$has_preset && !$has_default) return $urls;
    if ($relation_type === 'preconnect') {
        $urls[] = ['href' => 'https://fonts.googleapis.com'];
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'magpress_preconnect_google_fonts', 10, 2);

/**
 * Defer Google Fonts CSS: il facem non-blocking prin media="print" + onload swap.
 * Astfel render-ul nu mai e blocat de download-ul CSS-ului de la fonts.googleapis.com.
 */
function magpress_defer_google_fonts($html, $handle) {
    if ($handle === 'magpress-google-font') {
        // Inlocuim <link rel="stylesheet"> cu o varianta non-blocking
        $html = str_replace("rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $html);
        $html = str_replace('rel="stylesheet"', 'rel="stylesheet" media="print" onload="this.media=\'all\'"', $html);
    }
    return $html;
}
add_filter('style_loader_tag', 'magpress_defer_google_fonts', 10, 2);

/**
 * Preload imaginea LCP (cel mai mare element vizibil above-the-fold).
 * Homepage: prima imagine featured.
 * Single article: imaginea featured a articolului.
 * Imbunatateste LCP cu ~0.5-1.5s pe conexiuni lente.
 */
function magpress_preload_lcp_image() {
    $img_id = 0;

    if (is_front_page() || is_home()) {
        // Primul articol sticky sau cel mai recent
        $featured = get_posts([
            'posts_per_page'      => 1,
            'post__in'            => get_option('sticky_posts'),
            'ignore_sticky_posts' => 1,
            'post_status'         => 'publish',
            'no_found_rows'       => true,
            'fields'              => 'ids',
        ]);
        if (!$featured) {
            $featured = get_posts([
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]);
        }
        if ($featured) $img_id = get_post_thumbnail_id($featured[0]);
    } elseif (is_singular('post') || is_page()) {
        $img_id = get_post_thumbnail_id();
    }

    if (!$img_id) return;

    $src = wp_get_attachment_image_url($img_id, 'magpress-feature');
    if (!$src) return;

    $srcset = wp_get_attachment_image_srcset($img_id, 'magpress-feature');
    $sizes  = wp_get_attachment_image_sizes($img_id, 'magpress-feature');

    echo '<link rel="preload" as="image" href="' . esc_url($src) . '"';
    if ($srcset) echo ' imagesrcset="' . esc_attr($srcset) . '"';
    if ($sizes)  echo ' imagesizes="' . esc_attr($sizes) . '"';
    echo ' fetchpriority="high">' . "\n";
}
add_action('wp_head', 'magpress_preload_lcp_image', 2);
add_action('wp_enqueue_scripts', 'magpress_enqueue_assets');

/**
 * Inregistram meniurile.
 */
function magpress_register_menus() {
    register_nav_menus([
        'primary' => __('Meniu principal', 'magpress'),
        'footer'  => __('Meniu footer', 'magpress'),
    ]);
}
add_action('init', 'magpress_register_menus');

/**
 * Inregistram zone widget pentru footer.
 */
function magpress_register_sidebars() {
    for ($i = 1; $i <= 4; $i++) {
        register_sidebar([
            'name'          => sprintf(__('Footer Coloana %d', 'magpress'), $i),
            'id'            => 'footer-' . $i,
            'description'   => sprintf(__('Coloana %d din footer', 'magpress'), $i),
            'before_widget' => '<div class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
}
add_action('widgets_init', 'magpress_register_sidebars');

/**
 * Citeste configul per-site (culori, branding, categorii homepage).
 * Path: /wp-content/themes/magpress-config/site-config.json
 * Fallback secundar pentru cazurile cand Customizer nu e setat.
 */
function magpress_site_config() {
    static $config = null;
    if ($config !== null) { return $config; }

    $path = WP_CONTENT_DIR . '/themes/magpress-config/site-config.json';
    if (!file_exists($path)) { $config = []; return $config; }
    $json = file_get_contents($path);
    $config = json_decode($json, true) ?: [];
    return $config;
}

/**
 * Default-uri pentru culori (folosite cand Customizer nu e setat).
 */
function magpress_default_colors() {
    return [
        'primary'         => '#1a1a1a',
        'accent'          => '#6fa84a',
        'background'      => '#ffffff',
        'text'            => '#1a1a1a',
        'text_muted'      => '#6b6b6b',
        'border'          => '#e6e6e6',
        'surface'         => '#f6f7f5',
        'heading_h1'      => '#1a1a1a',
        'heading_h2'      => '#1a1a1a',
        'heading_h3'      => '#1a1a1a',
        'link'            => '#6fa84a',
        'link_hover'      => '#1a1a1a',
        'header_bg'       => '#ffffff',
        'header_border'   => '#e6e6e6',
        'menu_bg'         => '#ffffff',
        'menu_link'       => '#1a1a1a',
        'menu_link_hover' => '#6fa84a',
        'menu_hover_bg'   => '#f6f7f5',
        'search_color'    => '#1a1a1a',
        'badge_bg'        => '#6fa84a',
        'badge_text'      => '#ffffff',
        'footer_bg'       => '#f6f7f5',
        'footer_text'       => '#6b6b6b',
        'footer_link'       => '#1a1a1a',
        'footer_link_hover' => '#6fa84a',
        'footer_border'     => '#e6e6e6',
        'footer_heading'    => '#1a1a1a',
    ];
}

/**
 * Citeste o culoare: Customizer (theme_mod) → site-config.json → default.
 */
function magpress_color($key) {
    $val = get_theme_mod('magpress_color_' . $key);
    if ($val) return $val;
    $cfg = magpress_site_config();
    if (!empty($cfg['colors'][$key])) return $cfg['colors'][$key];
    $defaults = magpress_default_colors();
    return $defaults[$key] ?? '';
}

/**
 * Citeste header layout: 'inline' (default) sau 'centered'.
 */
function magpress_header_layout() {
    $layout = get_theme_mod('magpress_header_layout', 'inline');
    return in_array($layout, ['inline', 'centered'], true) ? $layout : 'inline';
}

/**
 * Genereaza CSS-ul de branding (culori + font) ca string.
 * Apelat din enqueue_assets cu wp_add_inline_style ca sa fie atasat AFTER style.css.
 */
function magpress_get_branding_css() {
    $defaults = magpress_default_colors();
    $css = ':root{';
    foreach ($defaults as $key => $default) {
        $val = magpress_color($key);
        if (!$val || $val === $default) continue;
        $cssVar = $key === 'background' ? '--c-bg' : '--c-' . str_replace('_', '-', $key);
        $css .= $cssVar . ':' . esc_attr($val) . ';';
    }
    $font = get_theme_mod('magpress_font_family');
    if (!$font) {
        $cfg = magpress_site_config();
        if (!empty($cfg['font_family'])) $font = $cfg['font_family'];
    }
    if ($font) $css .= '--f-sans:' . esc_attr($font) . ';';
    $css .= '}';

    // Header padding (separat sus + jos)
    $pad_top    = max(0, min(120, (int) get_theme_mod('magpress_header_padding_top', 32)));
    $pad_bottom = max(0, min(120, (int) get_theme_mod('magpress_header_padding_bottom', 20)));
    if ($pad_top !== 32 || $pad_bottom !== 20) {
        $css .= '.site-header--centered .site-header__top{padding-top:' . $pad_top . 'px !important;padding-bottom:' . $pad_bottom . 'px !important}';
        $css .= '.site-header--inline .site-header__inner{padding-top:' . $pad_top . 'px !important;padding-bottom:' . $pad_bottom . 'px !important}';
    }

    // Logo max-height
    $logo_h = max(20, min(200, (int) get_theme_mod('magpress_logo_max_height', 60)));
    if ($logo_h !== 60) {
        $css .= '.site-branding .site-logo{max-height:' . $logo_h . 'px !important}';
        // Pe centered, default e 110 — daca user a setat ceva, folosim valoarea lui
        $css .= '.site-header--centered .site-header__top .site-logo{max-height:' . $logo_h . 'px !important}';
    }

    return trim($css) === ':root{}' ? '' : $css;
}

/**
 * Custom Customizer control — checkbox list pentru selecție multiplă de categorii.
 * Stochează valoarea ca CSV de slug-uri (backward-compat cu setarea veche tip textarea).
 */
function magpress_register_customize_controls() {
    if (!class_exists('WP_Customize_Control')) return;
    if (class_exists('Magpress_Multi_Cat_Control')) return;
    class Magpress_Multi_Cat_Control extends WP_Customize_Control {
        public $type = 'magpress_multi_cat';
        public function render_content() {
            $categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'number' => 200]);
            $value = $this->value();
            $selected_slugs = [];
            if (is_string($value) && $value !== '') {
                $selected_slugs = array_map('sanitize_title', preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY));
            }
            $uid = 'magpress-mcc-' . esc_attr($this->id);
            ?>
            <span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
            <?php if ($this->description): ?>
                <span class="description customize-control-description"><?php echo wp_kses_post($this->description); ?></span>
            <?php endif; ?>
            <?php if (empty($categories)): ?>
                <p style="padding:8px;background:#fff3cd;border:1px solid #ffc107;border-radius:3px;margin-top:6px;font-size:.85em;">Nu există categorii. Creează din Articole → Categorii.</p>
            <?php else: ?>
                <div id="<?php echo $uid; ?>-wrap" style="max-height:280px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#fff;margin-top:6px;border-radius:3px;">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:block;padding:3px 0;cursor:pointer;">
                            <input type="checkbox" value="<?php echo esc_attr($cat->slug); ?>"
                                   <?php checked(in_array($cat->slug, $selected_slugs, true)); ?>>
                            <?php echo esc_html($cat->name); ?>
                            <span style="color:#999;font-size:.85em;">(<?php echo esc_html($cat->slug); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <input type="hidden" id="<?php echo $uid; ?>-input" <?php $this->link(); ?> value="<?php echo esc_attr(is_array($value) ? implode(',', $value) : (string) $value); ?>">
            <script>
            (function(){
                var wrap = document.getElementById('<?php echo $uid; ?>-wrap');
                var hidden = document.getElementById('<?php echo $uid; ?>-input');
                if (!wrap || !hidden) return;
                wrap.addEventListener('change', function(e){
                    if (!e.target || e.target.type !== 'checkbox') return;
                    var checked = wrap.querySelectorAll('input[type=checkbox]:checked');
                    var vals = Array.from(checked).map(function(c){return c.value;});
                    hidden.value = vals.join(',');
                    // Trigger jQuery change ca să-l prindă wp.customize
                    if (window.jQuery) {
                        jQuery(hidden).trigger('change');
                    } else {
                        hidden.dispatchEvent(new Event('change', {bubbles:true}));
                    }
                });
            })();
            </script>
            <?php
        }
    }
}
add_action('customize_register', 'magpress_register_customize_controls', 5);

/**
 * JS live update pentru marker „— folosit la slot N" în Customizer.
 * Când user schimbă tipul sau categoria într-un slot, marker-ele din toate
 * dropdown-urile celelalte se actualizează imediat (fără reload pagină Customizer).
 */
function magpress_customize_controls_js() {
    ?>
<script id="magpress-customize-slot-markers">
(function(){
    if (!window.wp || !wp.customize) return;
    var SLOT_COUNT = 24;
    var MARKER_RE = / — folosit la slot \d+$/;

    function updateMarkers() {
        // 1. Calculează ce categorii sunt folosite și în ce slot
        var used = {};
        for (var i = 1; i <= SLOT_COUNT; i++) {
            var typeSetting = wp.customize('magpress_slot_' + i + '_type');
            var catSetting  = wp.customize('magpress_slot_' + i + '_cat');
            if (!typeSetting || !catSetting) continue;
            if (typeSetting.get() !== 'category') continue;
            var cid = parseInt(catSetting.get(), 10);
            if (cid > 0 && !used[cid]) used[cid] = i;
        }

        // 2. Updatează toate dropdown-urile slot_X_cat
        for (var i = 1; i <= SLOT_COUNT; i++) {
            var ctrl = wp.customize.control('magpress_slot_' + i + '_cat');
            if (!ctrl) continue;
            var $select = ctrl.container.find('select');
            if (!$select.length) continue;
            $select.find('option').each(function() {
                var $opt = jQuery(this);
                var cid = parseInt($opt.val(), 10);
                // Stocăm textul original la primul pass
                var orig = $opt.data('magpressOriginal');
                if (!orig) {
                    orig = $opt.text().replace(MARKER_RE, '');
                    $opt.data('magpressOriginal', orig);
                }
                if (cid > 0 && used[cid] && used[cid] !== i) {
                    $opt.text(orig + ' — folosit la slot ' + used[cid]);
                } else {
                    $opt.text(orig);
                }
            });
        }
    }

    wp.customize.bind('ready', function(){
        // Bind change pe toate setările slot_X_type și slot_X_cat
        for (var i = 1; i <= SLOT_COUNT; i++) {
            var typeSetting = wp.customize('magpress_slot_' + i + '_type');
            var catSetting  = wp.customize('magpress_slot_' + i + '_cat');
            if (typeSetting) typeSetting.bind(updateMarkers);
            if (catSetting)  catSetting.bind(updateMarkers);
        }
        updateMarkers();
    });
})();
</script>
    <?php
}
add_action('customize_controls_print_footer_scripts', 'magpress_customize_controls_js');

/**
 * Customizer: secțiune Magpress cu culori + layout header.
 */
function magpress_customize_register($wp_customize) {

    // ---------- Sectiune: Layout ----------
    $wp_customize->add_section('magpress_layout', [
        'title'    => 'Magpress: Layout',
        'priority' => 25,
    ]);

    $wp_customize->add_setting('magpress_header_layout', [
        'default'           => 'inline',
        'sanitize_callback' => function($v) { return in_array($v, ['inline', 'centered'], true) ? $v : 'inline'; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_header_layout', [
        'label'       => 'Stil header',
        'description' => 'Inline = logo stânga + meniu pe aceeași linie. Centered = logo centrat sus + meniu sub.',
        'section'     => 'magpress_layout',
        'type'        => 'radio',
        'choices'     => [
            'inline'   => 'Inline (logo + meniu pe aceeași linie)',
            'centered' => 'Centered (logo sus centrat, meniu sub)',
        ],
    ]);

    // Google Discover follow box (homepage + single)
    $wp_customize->add_setting('magpress_show_google_discover', [
        'default'           => 1,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_show_google_discover', [
        'label'       => 'Box „Google Discover" (sursă preferată)',
        'description' => 'Afișează CTA pentru a marca site-ul ca sursă preferată în Google Discover. Apare pe homepage (după ultimul slot) și pe articol (înainte de comentarii).',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
    ]);

    // Featured posts — layout selector
    $wp_customize->add_setting('magpress_featured_layout', [
        'default'           => 'two',
        'sanitize_callback' => function($v) {
            return in_array($v, ['two', 'three', 'one_two', 'mosaic', 'hero_banner', 'big_list', 'hidden'], true) ? $v : 'two';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_featured_layout', [
        'label'       => 'Featured posts (sus pe homepage) — Layout',
        'description' => 'Stilul de afișare a articolelor featured din capul homepage-ului. Sticky posts (sau cele mai recente dacă nu există sticky).',
        'section'     => 'magpress_layout',
        'type'        => 'select',
        'choices'     => [
            'two'         => '2 carduri side-by-side (default)',
            'three'       => '3 carduri egale',
            'one_two'     => 'Asimetric (1 mare + 2 mici stivuit)',
            'mosaic'      => 'Mosaic 1+4 (1 mare + 4 mici grid 2×2)',
            'hero_banner' => 'Hero banner full-width (1 articol mare)',
            'big_list'    => 'Big + List (1 mare stânga + 5 articole listă dreapta)',
            'hidden'      => '— Ascuns —',
        ],
    ]);

    // Featured posts — categorii excluse (multi-checkbox din lista categoriilor)
    $wp_customize->add_setting('magpress_featured_excluded_cats', [
        'default'           => '',
        'sanitize_callback' => function ($v) {
            if (!is_string($v)) return '';
            $slugs = preg_split('/[\s,]+/', $v, -1, PREG_SPLIT_NO_EMPTY);
            $clean = array_filter(array_map('sanitize_title', $slugs));
            return implode(',', array_unique($clean));
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control(new Magpress_Multi_Cat_Control($wp_customize, 'magpress_featured_excluded_cats', [
        'label'       => 'Featured posts — Categorii excluse',
        'description' => 'Bifează categoriile care NU trebuie să apară în zona featured de sus (sticky sau fallback).',
        'section'     => 'magpress_layout',
    ]));

    // Padding header (separate top + bottom)
    $wp_customize->add_setting('magpress_header_padding_top', [
        'default'           => 32,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_header_padding_top', [
        'label'       => 'Padding header — SUS (px)',
        'description' => 'Spațiul deasupra logo-ului. Default 32px.',
        'section'     => 'magpress_layout',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 120, 'step' => 2],
    ]);

    $wp_customize->add_setting('magpress_header_padding_bottom', [
        'default'           => 20,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_header_padding_bottom', [
        'label'       => 'Padding header — JOS (px)',
        'description' => 'Spațiul sub logo (până la meniu / margine). Default 20px.',
        'section'     => 'magpress_layout',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 120, 'step' => 2],
    ]);

    // Logo max-height (înălțime maximă a logo-ului din header)
    $wp_customize->add_setting('magpress_logo_max_height', [
        'default'           => 60,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_logo_max_height', [
        'label'       => 'Înălțime maximă logo (px)',
        'description' => 'Limită superioară pentru logo. Default 60px. Pe centered layout default e 110px (override).',
        'section'     => 'magpress_layout',
        'type'        => 'number',
        'input_attrs' => ['min' => 20, 'max' => 200, 'step' => 2],
    ]);

    // Header sticky pe desktop
    $wp_customize->add_setting('magpress_header_sticky', [
        'default'           => 1,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_header_sticky', [
        'label'       => 'Header sticky (rămâne fix sus la scroll)',
        'description' => 'Doar pe desktop (≥ 768px). Pe mobile rămâne normal.',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
    ]);

    // ---------- Sectiune: Culori ----------
    $wp_customize->add_section('magpress_colors', [
        'title'    => 'Magpress: Culori',
        'priority' => 30,
    ]);

    $colors = [
        'accent'          => ['Accent (highlight, butoane)',                '#6fa84a'],
        'primary'         => ['Primary (text închis, header bar)',          '#1a1a1a'],
        'text'            => ['Text principal',                              '#1a1a1a'],
        'text_muted'      => ['Text secundar (meta, sub-titluri)',           '#6b6b6b'],
        'background'      => ['Background pagină',                           '#ffffff'],
        'surface'         => ['Surface (boxe newsletter/etc)',               '#f6f7f5'],
        'border'          => ['Border (linii subțiri, chenare)',             '#e6e6e6'],
        'heading_h1'      => ['Titluri H1 (articol, archive)',               '#1a1a1a'],
        'heading_h2'      => ['Titluri H2 (subtitluri, section headers)',    '#1a1a1a'],
        'heading_h3'      => ['Titluri H3 (sub-sub-titluri, carduri)',       '#1a1a1a'],
        'link'            => ['Link (text articol)',                         '#6fa84a'],
        'link_hover'      => ['Link hover',                                   '#1a1a1a'],
        'header_bg'       => ['Header — background (rândul cu logo)',         '#ffffff'],
        'header_border'   => ['Header — border jos (linie sub header)',      '#e6e6e6'],
        'menu_bg'         => ['Meniu — background',                          '#ffffff'],
        'menu_link'       => ['Meniu — culoare link',                        '#1a1a1a'],
        'menu_link_hover' => ['Meniu — culoare link hover',                  '#6fa84a'],
        'menu_hover_bg'   => ['Meniu — background link hover',               '#f6f7f5'],
        'search_color'    => ['Search — culoare icon + border',               '#1a1a1a'],
        'badge_bg'        => ['Badge categorie — background',                 '#6fa84a'],
        'badge_text'      => ['Badge categorie — text',                       '#ffffff'],
        'footer_bg'       => ['Footer — background',                         '#f6f7f5'],
        'footer_text'       => ['Footer — text',                                       '#6b6b6b'],
        'footer_link'       => ['Footer — linkuri (culoare default)',                  '#1a1a1a'],
        'footer_link_hover' => ['Footer — linkuri (culoare hover)',                    '#6fa84a'],
        'footer_border'     => ['Footer — culoare linii (deasupra + sub coloane)',     '#e6e6e6'],
        'footer_heading'    => ['Footer — titluri widget (REVISTA, INFO UTILE, etc.)', '#1a1a1a'],
    ];

    foreach ($colors as $key => $data) {
        $setting = 'magpress_color_' . $key;
        $wp_customize->add_setting($setting, [
            'default'           => $data[1],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $setting, [
            'label'   => $data[0],
            'section' => 'magpress_colors',
        ]));
    }

    // ---------- Sectiune: Font ----------
    $wp_customize->add_setting('magpress_font_family', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_font_family', [
        'label'       => 'Font family CSS (override)',
        'description' => 'Lasă gol pentru "Open Sans" (default). Ex: "Inter", sans-serif. Sau "Lora", serif pentru articole.',
        'section'     => 'magpress_colors',
        'type'        => 'text',
    ]);

    // ---------- Sectiune: Social media (footer) ----------
    $wp_customize->add_section('magpress_socials', [
        'title'       => 'Magpress: Social Media',
        'description' => 'URL-uri pentru rețelele sociale. Apar ca iconițe în footer, sub coloanele de widget-uri. Lasă gol pentru a nu afișa.',
        'priority'    => 29,
    ]);

    $socials_list = [
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'pinterest' => 'Pinterest',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'twitter'   => 'X (Twitter)',
        'linkedin'  => 'LinkedIn',
    ];
    foreach ($socials_list as $key => $name) {
        $wp_customize->add_setting("magpress_social_{$key}", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_social_{$key}", [
            'label'   => "URL {$name}",
            'section' => 'magpress_socials',
            'type'    => 'url',
        ]);
    }

    // ---------- Sectiune: Homepage (24 sloturi flexibile) ----------
    $wp_customize->add_section('magpress_homepage', [
        'title'       => 'Magpress: Homepage',
        'description' => '24 sloturi configurabile, în ordinea afișării. Fiecare slot poate fi: rând cu articole dintr-o categorie SAU bloc cu cod HTML/AdSense (banner, reclamă). Default: doar primele 4 sunt active (categorii); restul sunt „Gol".',
        'priority'    => 27,
    ]);

    $slot_types = [
        'none'     => '— Gol (sari peste slot) —',
        'category' => 'Categorie (rând articole)',
        'ad'       => 'Reclamă / AdSense (cod HTML)',
    ];

    $cat_choices = ['0' => '— Alege o categorie —'];
    foreach (get_categories(['hide_empty' => false, 'number' => 100, 'orderby' => 'name']) as $c) {
        $cat_choices[(string) $c->term_id] = $c->name;
    }

    // Map: cat_id => primul slot care folosește categoria (pentru marker „(folosit la slot N)")
    $used_in_slots = [];
    for ($k = 1; $k <= 24; $k++) {
        $k_type = get_theme_mod("magpress_slot_{$k}_type", $k <= 4 ? 'category' : 'none');
        if ($k_type !== 'category') continue;
        $k_cat = (int) get_theme_mod("magpress_slot_{$k}_cat", 0);
        if ($k_cat > 0 && !isset($used_in_slots[$k_cat])) {
            $used_in_slots[$k_cat] = $k;
        }
    }

    for ($i = 1; $i <= 24; $i++) {
        // 1. Tip slot
        $wp_customize->add_setting("magpress_slot_{$i}_type", [
            'default'           => $i <= 4 ? 'category' : 'none',
            'sanitize_callback' => function($v) use ($slot_types) {
                return isset($slot_types[$v]) ? $v : 'none';
            },
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_type", [
            'label'       => "▶ Slot {$i} — Tip",
            'description' => 'Ce afișează acest slot pe homepage.',
            'section'     => 'magpress_homepage',
            'type'        => 'select',
            'choices'     => $slot_types,
        ]);

        // 2. Categorie (vizibila daca tip = category)
        $wp_customize->add_setting("magpress_slot_{$i}_cat", [
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ]);
        // Choices per slot: marcăm categoriile deja folosite în alt slot
        $choices_for_slot = [];
        foreach ($cat_choices as $cid => $cname) {
            $cid_int = (int) $cid;
            if ($cid_int > 0 && isset($used_in_slots[$cid_int]) && $used_in_slots[$cid_int] !== $i) {
                $choices_for_slot[(string) $cid] = $cname . ' — folosit la slot ' . $used_in_slots[$cid_int];
            } else {
                $choices_for_slot[(string) $cid] = $cname;
            }
        }
        $wp_customize->add_control("magpress_slot_{$i}_cat", [
            'label'           => "Slot {$i} — Categorie",
            'section'         => 'magpress_homepage',
            'type'            => 'select',
            'choices'         => $choices_for_slot,
            'active_callback' => function() use ($i) {
                return get_theme_mod("magpress_slot_{$i}_type", 'none') === 'category';
            },
        ]);

        // 3. Numar articole (vizibila daca tip = category)
        $wp_customize->add_setting("magpress_slot_{$i}_count", [
            'default'           => 8,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_count", [
            'label'           => "Slot {$i} — Articole",
            'description'     => 'Recomandat: 4 sau 8. Min 2, max 20. (Layout-urile Asimetric/Hero/Listă forțează un număr fix.)',
            'section'         => 'magpress_homepage',
            'type'            => 'number',
            'input_attrs'     => ['min' => 2, 'max' => 20, 'step' => 1],
            'active_callback' => function() use ($i) {
                $default_type = $i <= 4 ? 'category' : 'none';
                return get_theme_mod("magpress_slot_{$i}_type", $default_type) === 'category';
            },
        ]);

        // 3b. Layout slot (vizibila daca tip = category)
        $wp_customize->add_setting("magpress_slot_{$i}_layout", [
            'default'           => 'grid',
            'sanitize_callback' => function($v) {
                return in_array($v, ['grid', 'asymmetric', 'hero', 'list'], true) ? $v : 'grid';
            },
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_layout", [
            'label'           => "Slot {$i} — Layout",
            'description'     => 'Stilul de afișare a cardurilor.',
            'section'         => 'magpress_homepage',
            'type'            => 'select',
            'choices'         => [
                'grid'       => 'Grid 4 coloane (8 carduri egale)',
                'asymmetric' => 'Asimetric (1 mare + 4 mici)',
                'hero'       => 'Hero (4 carduri portret cu overlay)',
                'list'       => 'Listă (imagine stânga + text dreapta)',
            ],
            'active_callback' => function() use ($i) {
                $default_type = $i <= 4 ? 'category' : 'none';
                return get_theme_mod("magpress_slot_{$i}_type", $default_type) === 'category';
            },
        ]);

        // 4. Cod reclama (vizibila daca tip = ad)
        $wp_customize->add_setting("magpress_slot_{$i}_ad", [
            'default'           => '',
            'sanitize_callback' => function($v) {
                // Admin-only (Customizer requires manage_options) — acceptam HTML/script complet
                return is_string($v) ? $v : '';
            },
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_ad", [
            'label'           => "Slot {$i} — Cod HTML / AdSense",
            'description'     => 'Lipește aici codul AdSense, banner image sau orice HTML/script. Va apărea centrat pe homepage între sloturile de categorii.',
            'section'         => 'magpress_homepage',
            'type'            => 'textarea',
            'active_callback' => function() use ($i) {
                return get_theme_mod("magpress_slot_{$i}_type", 'none') === 'ad';
            },
        ]);
    }

    // ---------- Sectiune: Reclame articol ----------
    $wp_customize->add_section('magpress_article_ads', [
        'title'       => 'Magpress: Reclame articol',
        'description' => 'Coduri HTML/AdSense pentru pagini de articol (single). Lasă gol pentru a nu afișa.',
        'priority'    => 28,
    ]);

    $wp_customize->add_setting('magpress_article_ad_mid', [
        'default'           => '',
        'sanitize_callback' => function($v) { return is_string($v) ? $v : ''; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_article_ad_mid', [
        'label'       => 'Reclamă după paragraful 3',
        'description' => 'Cod AdSense / HTML care apare în mijlocul articolului, după al 3-lea paragraf. Dacă articolul are <4 paragrafe, nu se afișează.',
        'section'     => 'magpress_article_ads',
        'type'        => 'textarea',
    ]);

    $wp_customize->add_setting('magpress_article_ad_end', [
        'default'           => '',
        'sanitize_callback' => function($v) { return is_string($v) ? $v : ''; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_article_ad_end', [
        'label'       => 'Reclamă sub articol',
        'description' => 'Cod AdSense / HTML care apare imediat sub conținutul articolului, înainte de „Articole similare".',
        'section'     => 'magpress_article_ads',
        'type'        => 'textarea',
    ]);

    $wp_customize->add_setting('magpress_google_font', [
        'default'           => 'Open Sans:400,700',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_google_font', [
        'label'       => 'Google Font de încărcat',
        'description' => 'Nume font + greutăți (format: "Open Sans:400,600,700,800"). Lasă gol pentru a nu încărca niciun Google Font.',
        'section'     => 'magpress_colors',
        'type'        => 'text',
    ]);
}
add_action('customize_register', 'magpress_customize_register');

/**
 * Inline critical CSS — minimul absolut pentru above-the-fold (header + container + featured).
 * Restul CSS-ului (style.css) ramane sa se incarce normal, dar critical apare instant
 * fara sa astepte download-ul fisierului → boost FCP/LCP.
 */
function magpress_inline_critical_css() {
    $css = '*,*::before,*::after{box-sizing:border-box}'
         . 'html{-webkit-text-size-adjust:100%;overflow-x:clip}'
         . 'body{margin:0;font-family:"Open Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif;font-size:16px;line-height:1.6;color:#1a1a1a;background:#fff;-webkit-font-smoothing:antialiased;overflow-x:clip;max-width:100vw}'
         . 'img,svg{max-width:100%;height:auto;display:block}'
         . 'a{color:#1a1a1a;text-decoration:none}'
         . '.container{max-width:1320px;margin:0 auto;padding:0 clamp(1rem,3vw,2rem);width:100%}'
         . '.site-header{background:#fff;border-bottom:1px solid #e6e6e6}'
         . '.site-header--inline .site-header__inner{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1rem clamp(1rem,3vw,2rem)}'
         . '.site-header--centered .site-header__top{display:flex!important;justify-content:center!important;align-items:center!important;padding:2rem 0 1.25rem!important;text-align:center!important}'
         . '.site-header--centered .site-header__top .site-logo{max-height:110px!important;width:auto!important;display:block!important;margin:0 auto}'
         . '.site-nav-bar{background:#fff;border-top:1px solid #e6e6e6;border-bottom:1px solid #e6e6e6}'
         . '.featured-grid{display:grid;grid-template-columns:1fr;gap:1.5rem;margin:2rem 0 3rem;width:100%}'
         . '.featured-grid>*{min-width:0;max-width:100%}'
         . '@media(min-width:768px){.featured-grid{grid-template-columns:1fr 1fr}}'
         . '.featured-card{position:relative;border-radius:12px;overflow:hidden;aspect-ratio:16/10;min-height:280px;background:#f6f7f5}'
         . '.featured-card__image{position:absolute;inset:0;display:block;z-index:1}'
         . '.featured-card__image img{width:100%;height:100%;object-fit:cover}'
         // Cards normale (category rows) — aspect-ratio 3/2 ca să rezerve spațiu corect de la primul paint (anti-CLS)
         . '.card{display:flex;flex-direction:column;background:#fff;border-radius:8px;overflow:hidden}'
         . '.card-image{display:block;aspect-ratio:3/2;overflow:hidden;background:#f6f7f5;position:relative}'
         . '.card-image img{width:100%;height:100%;object-fit:cover;display:block}'
         . '.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.25rem}'
         // Site logo dimensiuni explicit ca să nu shifteze layoutul header când imaginea încarcă
         . '.site-logo{display:block;max-height:48px;width:auto;height:auto}'
         . '.site-logo img{display:block;max-height:100%;width:auto;height:auto}'
         // Ad slot înălțime rezervată (anti-CLS la încărcare AdSense)
         . '.ad-slot{display:block;min-height:280px;margin:2rem auto;text-align:center}'
         . '.featured-card::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.85),rgba(0,0,0,.4) 50%,transparent 80%);z-index:2}'
         . '.featured-card__content{position:absolute;left:0;right:0;bottom:0;z-index:3;padding:clamp(1.25rem,3vw,2rem);color:#fff}'
         . '.featured-card__title{margin:.75rem 0 0;font-size:clamp(1.15rem,2vw,1.65rem);line-height:1.25;color:#fff;font-weight:800}'
         . '.skip-link{position:absolute;left:-9999px}'
         // Sticky header — inline ca să reziste la UCSS/Critical CSS removal de la LiteSpeed
         . '@media(min-width:768px){'
         . '.has-sticky-header .site-header--inline{position:sticky;top:0;z-index:100;background:var(--c-header-bg,#fff);border-bottom:1px solid var(--c-header-border,#e6e6e6);box-shadow:0 2px 8px rgba(0,0,0,.04)}'
         . '.has-sticky-header .site-nav-bar--centered{position:sticky;top:0;z-index:100;background:var(--c-menu-bg,#fff);border-bottom:1px solid var(--c-header-border,#e6e6e6);box-shadow:0 2px 8px rgba(0,0,0,.04)}'
         . '.has-sticky-header.admin-bar .site-header--inline,.has-sticky-header.admin-bar .site-nav-bar--centered{top:32px}'
         . '}'
         . '@media(min-width:601px) and (max-width:782px){.has-sticky-header.admin-bar .site-header--inline,.has-sticky-header.admin-bar .site-nav-bar--centered{top:46px}}';
    echo "<style id='magpress-critical'>{$css}</style>\n";
}
add_action('wp_head', 'magpress_inline_critical_css', 1);

/**
 * Calculeaza timpul de citire al unui articol (in minute).
 */
function magpress_reading_time($post_id = null) {
    $content = get_post_field('post_content', $post_id);
    $words = str_word_count(strip_tags($content));
    $minutes = max(1, (int) ceil($words / 200));
    return $minutes;
}

/**
 * Breadcrumbs simplu: Acasa / Categorie / Titlu (sau Acasa / Categorie).
 * SEOPress are propriul breadcrumbs schema — daca-l afiseaza el, comenteaza apelul.
 */
function magpress_breadcrumbs() {
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    echo '<a href="' . esc_url(home_url('/')) . '">Acasă</a>';

    if (is_singular('post')) {
        $cats = get_the_category();
        if ($cats) {
            echo '<span class="sep">/</span>';
            echo '<a href="' . esc_url(get_category_link($cats[0])) . '">' . esc_html($cats[0]->name) . '</a>';
        }
    } elseif (is_category() || is_tag() || is_tax()) {
        echo '<span class="sep">/</span>';
        echo '<span class="current">' . esc_html(single_term_title('', false)) . '</span>';
    } elseif (is_page()) {
        echo '<span class="sep">/</span>';
        echo '<span class="current">' . esc_html(get_the_title()) . '</span>';
    } elseif (is_search()) {
        echo '<span class="sep">/</span>';
        echo '<span class="current">Rezultate căutare</span>';
    } elseif (is_404()) {
        echo '<span class="sep">/</span>';
        echo '<span class="current">Pagină negăsită</span>';
    }
    echo '</nav>';
}

/**
 * Sloturi homepage configurate in Customizer (24 sloturi).
 * Fiecare slot poate fi: 'category' (rand articole) sau 'ad' (HTML/AdSense).
 * Returns: array de ['type' => 'category'|'ad', ...payload...]
 */
function magpress_homepage_slots() {
    $slots = [];
    for ($i = 1; $i <= 24; $i++) {
        // IMPORTANT: default identic cu cel din customize_register, altfel WP returneaza 'none'
        // pentru sloturile pe care user-ul le-a lasat pe default (nu-l salveaza in DB).
        $default_type = $i <= 4 ? 'category' : 'none';
        $type = get_theme_mod("magpress_slot_{$i}_type", $default_type);
        if ($type === 'category') {
            $cat_id = (int) get_theme_mod("magpress_slot_{$i}_cat", 0);
            if ($cat_id <= 0) continue;
            $cat = get_category($cat_id);
            if (!$cat || is_wp_error($cat)) continue;
            $count = (int) get_theme_mod("magpress_slot_{$i}_count", 8);
            $layout = get_theme_mod("magpress_slot_{$i}_layout", 'grid');
            if (!in_array($layout, ['grid', 'asymmetric', 'hero', 'list'], true)) $layout = 'grid';
            // Layout-urile fixe forteaza un numar de articole specific
            $forced = ['asymmetric' => 5, 'hero' => 4, 'list' => 5];
            if (isset($forced[$layout])) $count = $forced[$layout];
            $slots[] = ['type' => 'category', 'cat' => $cat, 'count' => max(2, min(20, $count)), 'layout' => $layout];
        } elseif ($type === 'ad') {
            $html = trim((string) get_theme_mod("magpress_slot_{$i}_ad", ''));
            if ($html === '') continue;
            $slots[] = ['type' => 'ad', 'html' => $html];
        }
    }
    if ($slots) return $slots;

    // Fallback: setari vechi (slot 1-6 din versiunea anterioara)
    for ($i = 1; $i <= 6; $i++) {
        $cat_id = (int) get_theme_mod("magpress_homepage_cat_{$i}", 0);
        if ($cat_id <= 0) continue;
        $cat = get_category($cat_id);
        if (!$cat || is_wp_error($cat)) continue;
        $count = (int) get_theme_mod("magpress_homepage_count_{$i}", 8);
        $slots[] = ['type' => 'category', 'cat' => $cat, 'count' => max(2, min(20, $count))];
    }
    if ($slots) return $slots;

    // Fallback: site-config.json
    $cfg = magpress_site_config();
    if (!empty($cfg['homepage_categories']) && is_array($cfg['homepage_categories'])) {
        foreach ($cfg['homepage_categories'] as $slug) {
            $c = get_category_by_slug($slug);
            if ($c) $slots[] = ['type' => 'category', 'cat' => $c, 'count' => 8];
        }
        if ($slots) return $slots;
    }

    // Fallback final: top 4 categorii
    $top = get_categories(['orderby' => 'count', 'order' => 'DESC', 'number' => 4, 'hide_empty' => true]);
    foreach ($top as $c) $slots[] = ['type' => 'category', 'cat' => $c, 'count' => 8];
    return $slots;
}

/**
 * Injecteaza reclama in mijlocul articolului — dupa al 3-lea paragraf <p>.
 * Aplicat doar pe single post.
 */
function magpress_inject_mid_article_ad($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    $ad = trim((string) get_theme_mod('magpress_article_ad_mid', ''));
    if ($ad === '') return $content;

    // Split la </p> si insereaza dupa al 3-lea
    $parts = explode('</p>', $content);
    if (count($parts) < 5) return $content; // <4 paragrafe — nu injectam

    $ad_html = magpress_render_ad_html($ad, 'mid-article');
    $injected = implode('</p>', array_slice($parts, 0, 3)) . '</p>' . $ad_html . implode('</p>', array_slice($parts, 3));
    return $injected;
}
add_filter('the_content', 'magpress_inject_mid_article_ad', 20);

/**
 * Strip atribuirea "Articolul X apare prima dată în Y." din conținut.
 * Aplicat în 3 locuri pentru a acoperi toate căile:
 *   1. wpeMatico_pre_insert_post — la import RSS (curăță înainte de DB insert; postări NOI)
 *   2. wpematico_pre_insert_post — variant lowercase (PHP filter names sunt case-sensitive)
 *   3. the_content — la afișare (curăță postările deja importate, fără modificare DB)
 */
function magpress_strip_source_attribution($content) {
    if (!is_string($content) || $content === '') return $content;
    // Linia "Articolul %POSTLINK% apare prima dată în %BLOGLINK%." (sau cu link-uri reale)
    $content = preg_replace('/Articolul.*?apare prima dat[ăa] în.*?\./isu', '', $content);
    // Curăță și paragrafele goale rămase
    $content = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $content);
    return $content;
}

// 1. La import WPeMatico (varianta camelCase din docs)
add_filter('wpeMatico_pre_insert_post', function ($post_data) {
    if (!empty($post_data['post_content'])) {
        $post_data['post_content'] = magpress_strip_source_attribution($post_data['post_content']);
    }
    return $post_data;
});
// 2. Variant lowercase (PHP filter names sunt case-sensitive)
add_filter('wpematico_pre_insert_post', function ($post_data) {
    if (!empty($post_data['post_content'])) {
        $post_data['post_content'] = magpress_strip_source_attribution($post_data['post_content']);
    }
    return $post_data;
});
// 3. La afișare (acoperă postările deja importate)
add_filter('the_content', 'magpress_strip_source_attribution', 5);

/**
 * Returneaza HTML-ul reclamei de la sfarsitul articolului (apelat din single.php).
 */
function magpress_get_end_article_ad() {
    $ad = trim((string) get_theme_mod('magpress_article_ad_end', ''));
    if ($ad === '') return '';
    return magpress_render_ad_html($ad, 'end-article');
}

/**
 * ============================================================
 * ERROR LOGGER pentru fișierele temei
 * ============================================================
 * Captează automat erori PHP din fișierele Magpress și le scrie
 * în /wp-content/magpress-errors.log. Vizibile in admin panel.
 */
const MAGPRESS_ERROR_LOG_FILE = WP_CONTENT_DIR . '/magpress-errors.log';
const MAGPRESS_ERROR_LOG_MAX_BYTES = 524288; // 512 KB — rotim când depășește

function magpress_log_error($severity, $message, $file, $line) {
    // Doar erori din folderul temei (sau apelate din tema)
    if (strpos($file, MAGPRESS_DIR) === false) return;

    // Rotește log-ul dacă e prea mare
    if (file_exists(MAGPRESS_ERROR_LOG_FILE) && filesize(MAGPRESS_ERROR_LOG_FILE) > MAGPRESS_ERROR_LOG_MAX_BYTES) {
        @rename(MAGPRESS_ERROR_LOG_FILE, MAGPRESS_ERROR_LOG_FILE . '.old');
    }

    $rel_file = str_replace(ABSPATH, '', $file);
    $entry = sprintf("[%s] [%s] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $severity,
        trim($message),
        $rel_file,
        $line
    );
    @file_put_contents(MAGPRESS_ERROR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

function magpress_error_handler($errno, $errstr, $errfile, $errline) {
    $severity_map = [
        E_WARNING       => 'WARNING',
        E_NOTICE        => 'NOTICE',
        E_USER_WARNING  => 'USER_WARNING',
        E_USER_NOTICE   => 'USER_NOTICE',
        E_DEPRECATED    => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
        E_STRICT        => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
    ];
    $severity = $severity_map[$errno] ?? 'UNKNOWN(' . $errno . ')';
    magpress_log_error($severity, $errstr, $errfile, $errline);
    return false; // continuă handler-ul nativ
}

function magpress_fatal_handler() {
    $error = error_get_last();
    if (!$error) return;
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatal_types, true)) return;
    magpress_log_error('FATAL', $error['message'], $error['file'], $error['line']);
}

function magpress_register_error_handlers() {
    // Doar pe frontend + admin (nu in cron sau cli)
    if (defined('DOING_CRON') && DOING_CRON) return;
    if (defined('WP_CLI') && WP_CLI) return;

    set_error_handler('magpress_error_handler', E_ALL & ~E_STRICT);
    register_shutdown_function('magpress_fatal_handler');
}
add_action('init', 'magpress_register_error_handlers');

/**
 * Citește ultimele N erori din log (pentru afișare în admin).
 */
function magpress_read_recent_errors($limit = 20) {
    if (!file_exists(MAGPRESS_ERROR_LOG_FILE)) return [];
    $content = @file_get_contents(MAGPRESS_ERROR_LOG_FILE);
    if (!$content) return [];
    $lines = array_filter(explode("\n", $content));
    return array_slice(array_reverse($lines), 0, $limit);
}

/**
 * Returnează numărul total de erori din log (pentru admin notice).
 */
function magpress_count_errors() {
    if (!file_exists(MAGPRESS_ERROR_LOG_FILE)) return 0;
    $content = @file_get_contents(MAGPRESS_ERROR_LOG_FILE);
    if (!$content) return 0;
    return substr_count($content, "\n");
}

/**
 * Admin notice când există erori în log.
 */
function magpress_admin_error_notice() {
    if (!current_user_can('manage_options')) return;
    $count = magpress_count_errors();
    if ($count === 0) return;
    $url = admin_url('themes.php?page=magpress-settings');
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>⚠️ Magpress:</strong> Tema a înregistrat <strong><?php echo $count; ?> erori</strong>. <a href="<?php echo esc_url($url); ?>">Vezi detalii →</a></p>
    </div>
    <?php
}
add_action('admin_notices', 'magpress_admin_error_notice');

/**
 * ============================================================
 * ADSENSE LAZY LOADING
 * ============================================================
 * Wrap ad HTML într-un container cu data-attribute. JS detectează când
 * slot-ul e aproape de viewport și inject-ează codul AdSense efectiv,
 * inclusiv script-urile (createContextualFragment execută script-uri inline).
 * Reduce TBT cu 200-400ms, LCP cu 500ms-1s, fără să strici venit AdSense.
 */
function magpress_is_adsense_lazy_enabled() {
    return (bool) get_theme_mod('magpress_adsense_lazy', 1);
}

function magpress_render_ad_html($html, $context = 'homepage') {
    $html = trim((string) $html);
    if ($html === '') return '';

    // Detect & cache Publisher ID — caută în scriptul async loader SAU în data-ad-client pe <ins>.
    // Îl emitem o singură dată în <head> ca să evităm race conditions per-slot.
    $pub_found = '';
    if (preg_match('/adsbygoogle\.js\?client=(ca-pub-\d+)/', $html, $pm)) {
        $pub_found = $pm[1];
    } elseif (preg_match('/data-ad-client=["\'](ca-pub-\d+)["\']/', $html, $pm)) {
        $pub_found = $pm[1];
    }
    if ($pub_found && get_option('magpress_adsense_publisher_id') !== $pub_found) {
        update_option('magpress_adsense_publisher_id', $pub_found, false);
    }

    if (!magpress_is_adsense_lazy_enabled()) {
        // Non-lazy: păstrăm comportament vechi (include scriptul + push() inline)
        return '<div class="ad-slot ad-slot--' . esc_attr($context) . '">' . $html . '</div>';
    }

    // Smart lazy: dacă găsim <ins class="adsbygoogle">, păstrăm DOAR <ins>-ul (curățat de scripturi).
    // Scriptul loader e emis global în <head>; push() se face din IO callback cu safety checks.
    if (preg_match('/(<ins\b[^>]*\badsbygoogle\b[^>]*(?:\/>|>.*?<\/ins>))/is', $html, $m)) {
        $ins_tag = $m[1];
        return '<div class="ad-slot ad-slot--lazy-push ad-slot--' . esc_attr($context) . '">'
             . $ins_tag
             . '</div>';
    }

    // Fallback: cod custom (banner image, etc.) — lazy total prin data-ad-html (păstrat din versiunea veche)
    return '<div class="ad-slot ad-slot--lazy-full ad-slot--' . esc_attr($context) . '" data-ad-html="' . esc_attr($html) . '">'
         . '<div class="ad-slot__placeholder" aria-hidden="true"></div>'
         . '</div>';
}

/**
 * Emite scriptul oficial adsbygoogle.js în <head> O SINGURĂ DATĂ.
 * Detectat automat la primul render de ad (cached în wp_options), sau setat manual din Customizer.
 * Evită race condition: push() din IO callback poate executa imediat fără să aștepte loading-ul scriptului.
 */
function magpress_enqueue_adsense_loader() {
    // Customizer override are prioritate
    $custom = trim((string) get_theme_mod('magpress_adsense_publisher_id', ''));
    $pub = $custom !== '' ? $custom : (string) get_option('magpress_adsense_publisher_id', '');

    // Fallback: scanează toate input-urile Customizer pentru AdSense code (în caz că render-ul body
    // nu a apucat să cache-ze încă, sau cache-ul e gol post-deploy).
    if (!$pub || !preg_match('/^ca-pub-\d+$/', $pub)) {
        $candidates = [
            (string) get_theme_mod('magpress_article_ad_mid', ''),
            (string) get_theme_mod('magpress_article_ad_end', ''),
        ];
        for ($i = 1; $i <= 24; $i++) {
            $candidates[] = (string) get_theme_mod("magpress_slot_{$i}_ad", '');
        }
        $all = implode("\n", $candidates);
        $found = '';
        if (preg_match('/data-ad-client=["\'](ca-pub-\d+)["\']/', $all, $pm)) {
            $found = $pm[1];
        } elseif (preg_match('/adsbygoogle\.js\?client=(ca-pub-\d+)/', $all, $pm)) {
            $found = $pm[1];
        }
        if ($found) {
            $pub = $found;
            update_option('magpress_adsense_publisher_id', $found, false);
        }
    }

    if (!$pub || !preg_match('/^ca-pub-\d+$/', $pub)) return;
    echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr($pub) . '" crossorigin="anonymous"></script>' . "\n";
}
add_action('wp_head', 'magpress_enqueue_adsense_loader', 5);

function magpress_adsense_lazy_script() {
    if (!magpress_is_adsense_lazy_enabled()) return;
    ?>
<script id="magpress-adsense-lazy">
(function(){
  // Auto-inject loader adsbygoogle.js dacă nu există deja (când sloturile vin din plugin extern).
  // Detectează publisher ID din data-ad-client al primului <ins> și încarcă scriptul în <head>.
  function ensureAdsenseLoader(){
    if (document.querySelector('script[src*="adsbygoogle.js"]')) return; // deja încărcat
    var firstIns = document.querySelector('ins.adsbygoogle[data-ad-client^="ca-pub-"]');
    if (!firstIns) return;
    var client = firstIns.getAttribute('data-ad-client');
    if (!/^ca-pub-\d+$/.test(client)) return;
    var s = document.createElement('script');
    s.async = true;
    s.crossOrigin = 'anonymous';
    s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(client);
    document.head.appendChild(s);
  }
  ensureAdsenseLoader();

  function executeScripts(container, html){
    container.insertAdjacentHTML('beforeend', html);
    container.querySelectorAll('script:not([data-magpress-executed])').forEach(function(oldS){
      var newS = document.createElement('script');
      Array.from(oldS.attributes).forEach(function(a){ newS.setAttribute(a.name, a.value); });
      newS.textContent = oldS.textContent;
      newS.setAttribute('data-magpress-executed', '1');
      oldS.parentNode.replaceChild(newS, oldS);
    });
  }

  // AdSense push() cu safety:
  //  - verifică data-adsbygoogle-status (previne TagError "ins already have ads")
  //  - try/catch (orice eroare nu blochează alte slots)
  //  - scriptul loader e deja în <head> (emis de magpress_enqueue_adsense_loader), deci push-ul
  //    se queue-uiește în window.adsbygoogle array și execută când loader-ul e gata
  function pushAdsense(ins){
    if (!ins || ins.dataset.adsbygoogleStatus) return;
    try {
      (window.adsbygoogle = window.adsbygoogle || []).push({});
    } catch(e) {
      if (window.console && console.warn) console.warn('Magpress AdSense push failed', e);
    }
  }

  function activatePushSlot(slot){
    var ins = slot.querySelector('ins.adsbygoogle');
    if (ins) pushAdsense(ins);
    slot.classList.remove('ad-slot--lazy-push');
    slot.classList.add('ad-slot--loaded');
  }

  function activateFullSlot(slot){
    // Slot fără <ins> (banner custom HTML/img) — inject totul
    var html = slot.dataset.adHtml || '';
    var ph = slot.querySelector('.ad-slot__placeholder');
    if (ph) ph.remove();
    executeScripts(slot, html);
    slot.classList.remove('ad-slot--lazy-full');
    slot.classList.add('ad-slot--loaded');
  }

  function activate(slot){
    if (slot.classList.contains('ad-slot--lazy-push')) activatePushSlot(slot);
    else if (slot.classList.contains('ad-slot--lazy-full')) activateFullSlot(slot);
  }

  var selectors = '.ad-slot--lazy-push, .ad-slot--lazy-full';

  if(!('IntersectionObserver' in window)){
    document.querySelectorAll(selectors).forEach(activate);
    return;
  }

  // rootMargin redus de la 400px la 200px — recomandarea Google:
  // declanșează push() înainte ca slot-ul să fie vizibil, dar nu prea devreme
  // (slot-uri unfilled because ad nu e încă "in viewable position")
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting){
        activate(e.target);
        io.unobserve(e.target);
      }
    });
  },{rootMargin:'200px 0px',threshold:0.01});

  document.querySelectorAll(selectors).forEach(function(s){ io.observe(s); });
})();
</script>
    <?php
}
add_action('wp_footer', 'magpress_adsense_lazy_script', 5);

/**
 * Customizer toggle.
 */
function magpress_register_adsense_lazy_setting($wp_customize) {
    $wp_customize->add_setting('magpress_adsense_lazy', [
        'default'           => 1,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_adsense_lazy', [
        'label'       => 'AdSense lazy load (recomandat)',
        'description' => 'Încarcă reclamele DOAR când userul scrolează aproape de ele (~200px). Reduce TBT/LCP cu 200-1000ms. Dezactivează doar dacă observi că ads nu se mai afișează deloc.',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
    ]);

    // Publisher ID override (manual fallback dacă detecția auto din cod nu funcționează)
    $wp_customize->add_setting('magpress_adsense_publisher_id', [
        'default'           => '',
        'sanitize_callback' => function ($v) {
            $v = trim((string) $v);
            return preg_match('/^ca-pub-\d+$/', $v) ? $v : '';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_adsense_publisher_id', [
        'label'       => 'AdSense Publisher ID (opțional)',
        'description' => 'Format: ca-pub-1234567890123456. Lasă gol — tema detectează automat din primul cod AdSense salvat. Setează manual doar dacă nu detectează corect.',
        'section'     => 'magpress_layout',
        'type'        => 'text',
        'input_attrs' => ['placeholder' => 'ca-pub-...'],
    ]);
}
add_action('customize_register', 'magpress_register_adsense_lazy_setting');

/**
 * ============================================================
 * PING CENTRAL — site-ul trimite zilnic status la app.targetseo.ro
 * ============================================================
 * Endpoint colector: https://app.targetseo.ro/api/magpress-ping.php
 * Vezi: app.targetseo.ro/magpress-sites.php (dashboard centralizat)
 */
const MAGPRESS_PING_ENDPOINT = 'https://app.targetseo.ro/api/magpress-ping.php';
const MAGPRESS_PING_SECRET   = 'magpress-monitor-b7f2e1a3c9d8'; // sincron cu api/magpress-ping.php pe targetseo.ro

/**
 * Deploy secret — per site, generat o singură dată și salvat în wp_options.
 * NU mai e hardcodat în cod (vezi GitGuardian). Push-script-ul citește secretul
 * fiecărui site din magpress_sites pe targetseo.ro (trimis prin ping).
 */
function magpress_get_deploy_secret() {
    $secret = get_option('magpress_deploy_secret');
    if (!$secret || strlen($secret) < 32) {
        $secret = wp_generate_password(48, false, false);
        update_option('magpress_deploy_secret', $secret, false); // autoload=false
    }
    return $secret;
}
// Asigură generarea la activare temă (nu așteptăm primul request REST)
add_action('after_switch_theme', 'magpress_get_deploy_secret');

function magpress_send_ping($blocking = false) {
    // Citește log-ul pentru recent_errors + counts
    $recent = magpress_read_recent_errors(20);
    $errors_count = magpress_count_errors();
    $fatal_count = 0;
    $log_content = file_exists(MAGPRESS_ERROR_LOG_FILE) ? @file_get_contents(MAGPRESS_ERROR_LOG_FILE) : '';
    if ($log_content) {
        $fatal_count = substr_count($log_content, '[FATAL]');
    }

    $data = [
        'site_url'      => home_url('/'),
        'site_name'     => get_bloginfo('name'),
        'theme_version' => wp_get_theme()->get('Version'),
        'wp_version'    => get_bloginfo('version'),
        'php_version'   => PHP_VERSION,
        'errors_count'  => $errors_count,
        'fatal_count'   => $fatal_count,
        'recent_errors' => implode("\n", $recent),
        'deploy_secret' => magpress_get_deploy_secret(), // pentru push-script (stocat only-once la primul ping)
    ];

    $body = wp_json_encode($data);
    $signature = hash_hmac('sha256', $body, MAGPRESS_PING_SECRET);

    wp_remote_post(MAGPRESS_PING_ENDPOINT, [
        'body'    => $body,
        'headers' => [
            'Content-Type'         => 'application/json',
            'X-Magpress-Signature' => $signature,
        ],
        'timeout'  => $blocking ? 15 : 10,
        'blocking' => (bool) $blocking,
    ]);
}

// Înregistrăm schedule 'weekly' (nu există nativ în WP)
function magpress_add_weekly_schedule($schedules) {
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = [
            'interval' => 7 * DAY_IN_SECONDS,
            'display'  => 'Săptămânal',
        ];
    }
    return $schedules;
}
add_filter('cron_schedules', 'magpress_add_weekly_schedule');

// Schedule săptămânal (re-schedule dacă găsește vechea variantă 'daily')
function magpress_schedule_ping() {
    // Detectează dacă există deja schedule cu interval != weekly și-l înlocuiește
    $next = wp_next_scheduled('magpress_daily_ping');
    if ($next) {
        $cron = _get_cron_array();
        if (is_array($cron)) {
            foreach ($cron as $ts => $events) {
                if (isset($events['magpress_daily_ping'])) {
                    foreach ($events['magpress_daily_ping'] as $args) {
                        if (($args['schedule'] ?? '') !== 'weekly') {
                            wp_clear_scheduled_hook('magpress_daily_ping');
                            $next = false;
                        }
                        break 2;
                    }
                }
            }
        }
    }
    if (!$next) {
        wp_schedule_event(time() + 600, 'weekly', 'magpress_daily_ping');
    }
}
add_action('after_switch_theme', 'magpress_schedule_ping');
add_action('init', 'magpress_schedule_ping');
add_action('magpress_daily_ping', 'magpress_send_ping');

/**
 * Forțează un ping imediat după update temă (când versiunea în stylesheet != versiunea ultimă raportată).
 * Asigură că deploy_secret + theme_version ajung la targetseo.ro imediat, nu peste o săptămână.
 */
add_action('init', function () {
    if (is_admin() || wp_doing_cron() || wp_doing_ajax()) return;
    $current = wp_get_theme()->get('Version');
    $reported = get_option('magpress_last_reported_version');
    if ($current !== $reported) {
        update_option('magpress_last_reported_version', $current, false);
        // Trimite ping pe un shutdown hook ca să nu blocheze request-ul
        add_action('shutdown', 'magpress_send_ping');
    }
}, 99);

// Cleanup la dezactivare
function magpress_unschedule_ping() {
    wp_clear_scheduled_hook('magpress_daily_ping');
}
add_action('switch_theme', 'magpress_unschedule_ping');

// Permite trigger manual din pagina Aspect → Magpress (vezi handle in render settings)
function magpress_handle_manual_ping() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['magpress_send_ping']) || !check_admin_referer('magpress_send_ping_action')) return;
    magpress_send_ping();
}
add_action('admin_init', 'magpress_handle_manual_ping');

/**
 * Render iconițe social media (folosit in footer).
 * Returneaza HTML sau string gol daca nu exista URL-uri.
 */
function magpress_render_socials() {
    $socials = [
        'facebook'  => ['Facebook',  '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>'],
        'instagram' => ['Instagram', '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>'],
        'pinterest' => ['Pinterest', '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>'],
        'youtube'   => ['YouTube',   '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'],
        'tiktok'    => ['TikTok',    '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005.8 20.1a6.34 6.34 0 0010.86-4.43V8.85a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1.84-.28z"/></svg>'],
        'twitter'   => ['X',         '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
        'linkedin'  => ['LinkedIn',  '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>'],
    ];

    $output_links = [];
    foreach ($socials as $key => $data) {
        $url = trim((string) get_theme_mod("magpress_social_{$key}", ''));
        if (empty($url)) continue;
        $output_links[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($data[0]) . '" class="footer-social footer-social--' . esc_attr($key) . '">' . $data[1] . '</a>';
    }
    if (empty($output_links)) return '';

    return '<div class="footer-socials">' . implode('', $output_links) . '</div>';
}

/**
 * Render meniul principal (cu fallback la categorii daca nu e configurat).
 */
function magpress_render_primary_menu() {
    if (has_nav_menu('primary')) {
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'menu',
            'depth'          => 2,
            'fallback_cb'    => false,
        ]);
        return;
    }
    echo '<ul class="menu">';
    foreach (get_categories(['hide_empty' => true, 'number' => 6]) as $c) {
        echo '<li><a href="' . esc_url(get_category_link($c)) . '">' . esc_html($c->name) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * ============================================================
 * AUTO-UPDATE THEME
 * ============================================================
 * Tema isi verifica singura update-urile, fara webhook per-site.
 * Citeste .update-info.json din repo public si daca versiunea
 * de pe GitHub > versiunea instalata, WP afiseaza "Update available"
 * exact ca pentru orice tema oficiala WP repo.
 */
const MAGPRESS_UPDATE_INFO_URL = 'https://raw.githubusercontent.com/kreatorteam-collab/magpress/main/.update-info.json';

function magpress_check_for_update($transient) {
    if (empty($transient->checked)) return $transient;
    $slug = get_stylesheet();
    if (!isset($transient->checked[$slug])) return $transient;

    $current_version = $transient->checked[$slug];

    // Cache 6 ore — nu polluam GitHub la fiecare load
    $info = get_transient('magpress_update_info');
    if (false === $info) {
        $response = wp_remote_get(MAGPRESS_UPDATE_INFO_URL, ['timeout' => 5]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_transient('magpress_update_info', 'error', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }
        $info = json_decode(wp_remote_retrieve_body($response), true);
        if (!$info || empty($info['version']) || empty($info['package'])) {
            set_transient('magpress_update_info', 'error', 30 * MINUTE_IN_SECONDS);
            return $transient;
        }
        set_transient('magpress_update_info', $info, 6 * HOUR_IN_SECONDS);
    }
    if ($info === 'error') return $transient;

    if (version_compare($current_version, $info['version'], '<')) {
        $transient->response[$slug] = [
            'theme'        => $slug,
            'new_version'  => $info['version'],
            'url'          => $info['url']         ?? 'https://github.com/kreatorteam-collab/magpress',
            'package'      => $info['package'],
            'requires'     => $info['requires']     ?? '6.0',
            'requires_php' => $info['requires_php'] ?? '8.0',
        ];
    }
    return $transient;
}
add_filter('pre_set_site_transient_update_themes', 'magpress_check_for_update');

/**
 * Fix nume folder la dezarhivare. GitHub ZIP-ul vine cu folderul "magpress-main"
 * dar site-urile pot avea folderul "magpress" sau "magpress-main". Redenumim
 * folderul extras ca sa match-uiasca tema activa, ca update-ul sa nu duplice.
 */
function magpress_fix_update_source($source, $remote_source, $upgrader, $hook_extra = []) {
    if (empty($hook_extra['theme'])) return $source;
    if (false === strpos(basename($source), 'magpress')) return $source;

    $expected_folder = $hook_extra['theme'];
    $actual_folder   = basename(rtrim($source, '/'));

    if ($actual_folder === $expected_folder) return $source;

    global $wp_filesystem;
    $new_source = trailingslashit($remote_source) . $expected_folder;
    if ($wp_filesystem && $wp_filesystem->move($source, $new_source)) {
        return $new_source;
    }
    return $source;
}
add_filter('upgrader_source_selection', 'magpress_fix_update_source', 10, 4);

/**
 * AUTO-UPDATE TEMĂ activat by default.
 * Site-ul instalează update-urile automat când le detectează — fără click.
 * Admin-ul poate dezactiva via filter sau din Aspect → Teme → click pe temă → Disable auto-updates.
 */
function magpress_enable_auto_update($update, $item) {
    if (isset($item->theme) && $item->theme === get_stylesheet()) {
        return true;
    }
    return $update;
}
add_filter('auto_update_theme', 'magpress_enable_auto_update', 10, 2);

/**
 * Pagină admin "Magpress" cu buton "Verifică update acum".
 * Aspect → Magpress.
 */
function magpress_admin_menu_page() {
    add_theme_page(
        'Magpress',
        'Magpress',
        'manage_options',
        'magpress-settings',
        'magpress_render_settings_page'
    );
}
add_action('admin_menu', 'magpress_admin_menu_page');

function magpress_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $message = '';
    $message_type = 'info';

    if (isset($_POST['magpress_check_update']) && check_admin_referer('magpress_check_update_action')) {
        // Force re-check: bypass GitHub raw CDN cache (max-age=300) cu cache-buster query string
        delete_transient('magpress_update_info');
        delete_site_transient('update_themes');
        // Sterge si cache-ul LiteSpeed Object dacă există
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('magpress_update_info', 'transient');
            wp_cache_delete('update_themes', 'site-transient');
        }

        // Fetch DIRECT din GitHub cu cache-buster ?_=timestamp ca să sărim peste CDN cache
        $bust_url = MAGPRESS_UPDATE_INFO_URL . '?_=' . time();
        $response = wp_remote_get($bust_url, [
            'timeout' => 8,
            'headers' => ['Cache-Control' => 'no-cache', 'Pragma' => 'no-cache'],
        ]);
        $info = false;
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $info = json_decode(wp_remote_retrieve_body($response), true);
            if ($info && !empty($info['version'])) {
                set_transient('magpress_update_info', $info, 6 * HOUR_IN_SECONDS);
            }
        }
        wp_update_themes(); // declanseaza re-check WP cu noua data
        $current = wp_get_theme()->get('Version');

        if (is_array($info) && !empty($info['version'])) {
            if (version_compare($current, $info['version'], '<')) {
                $message = '✅ <strong>Update disponibil:</strong> versiunea ' . esc_html($info['version']) . ' (instalată: ' . esc_html($current) . '). Tema se va instala automat în câteva minute. Sau mergi la <a href="' . admin_url('themes.php') . '">Aspect → Teme</a> și instalează manual.';
                $message_type = 'success';
            } else {
                $message = '✓ Tema e la zi. Versiune curentă: <strong>' . esc_html($current) . '</strong> (GitHub are tot ' . esc_html($info['version']) . ').';
                $message_type = 'success';
            }
        } else {
            $message = '⚠️ Nu am putut contacta GitHub. Reîncearcă în câteva minute.';
            $message_type = 'warning';
        }
    }

    // Handle clear errors button
    if (isset($_POST['magpress_clear_errors']) && check_admin_referer('magpress_clear_errors_action')) {
        if (file_exists(MAGPRESS_ERROR_LOG_FILE))        { @unlink(MAGPRESS_ERROR_LOG_FILE); }
        if (file_exists(MAGPRESS_ERROR_LOG_FILE . '.old')) { @unlink(MAGPRESS_ERROR_LOG_FILE . '.old'); }
        $message = '✓ Log-ul de erori a fost șters.';
        $message_type = 'success';
    }

    $current_version = wp_get_theme()->get('Version');
    $last_modified   = magpress_last_modified_timestamp();
    $errors          = magpress_read_recent_errors(50);
    $errors_count    = magpress_count_errors();
    ?>
    <div class="wrap">
        <h1>Magpress</h1>

        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width:720px;padding:1.5rem;margin-top:1rem">
            <h2 style="margin-top:0">Status temă</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Versiune instalată</th>
                    <td><code><?php echo esc_html($current_version); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Ultima actualizare</th>
                    <td>
                        <?php if ($last_modified): ?>
                            <?php echo esc_html(date_i18n('j F Y, H:i', $last_modified)); ?>
                            <em>(<?php echo esc_html(human_time_diff($last_modified, current_time('U'))); ?> în urmă)</em>
                        <?php else: ?>
                            <em>—</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Auto-update</th>
                    <td>✓ Activat (default). Update-urile se aplică automat când sunt detectate.</td>
                </tr>
                <tr>
                    <th scope="row">Repo</th>
                    <td><a href="https://github.com/kreatorteam-collab/magpress" target="_blank">github.com/kreatorteam-collab/magpress</a></td>
                </tr>
            </table>

            <h2>Verifică update manual</h2>
            <p>În mod normal, WordPress verifică update-uri la fiecare 12 ore automat. Click aici dacă vrei să forțezi o verificare instant:</p>

            <form method="post">
                <?php wp_nonce_field('magpress_check_update_action'); ?>
                <button type="submit" name="magpress_check_update" class="button button-primary button-large">
                    🔄 Verifică update acum
                </button>
            </form>

            <h2 style="margin-top:2rem">Trimite ping la monitor central</h2>
            <p>Site-ul trimite automat zilnic status la <code>app.targetseo.ro/magpress-sites.php</code> (dashboard centralizat cu toate site-urile). Force-trigger acum:</p>

            <form method="post">
                <?php wp_nonce_field('magpress_send_ping_action'); ?>
                <button type="submit" name="magpress_send_ping" class="button">
                    📡 Trimite ping acum
                </button>
            </form>
        </div>

        <div class="card" style="max-width:1100px;padding:1.5rem;margin-top:1rem">
            <h2 style="margin-top:0">
                <?php if ($errors_count > 0): ?>
                    ⚠️ Erori temă (<?php echo $errors_count; ?>)
                <?php else: ?>
                    ✓ Erori temă (zero)
                <?php endif; ?>
            </h2>
            <?php if ($errors_count > 0): ?>
                <p>Mai jos vezi ultimele 50 de erori PHP din fișierele Magpress. Trimite-mi screenshot la suport pentru fix rapid.</p>
                <pre style="background:#1e1e1e;color:#dcdcdc;padding:1rem;border-radius:6px;max-height:400px;overflow-y:auto;font-size:.8rem;line-height:1.5;font-family:'Consolas',monospace;white-space:pre-wrap;word-break:break-word"><?php
                foreach ($errors as $line) {
                    $color = '#dcdcdc';
                    if (strpos($line, '[FATAL]') !== false)       $color = '#ff6b6b';
                    elseif (strpos($line, '[WARNING]') !== false)  $color = '#ffd93d';
                    elseif (strpos($line, '[NOTICE]') !== false)   $color = '#6bcb77';
                    echo '<span style="color:' . $color . '">' . esc_html($line) . "</span>\n";
                }
                ?></pre>
                <p style="margin-top:1rem">
                    <strong>Fișier log:</strong> <code>/wp-content/magpress-errors.log</code>
                </p>
                <form method="post" style="margin-top:1rem">
                    <?php wp_nonce_field('magpress_clear_errors_action'); ?>
                    <button type="submit" name="magpress_clear_errors" class="button" onclick="return confirm('Ești sigur că vrei să ștergi log-ul de erori?')">
                        🗑️ Șterge log erori
                    </button>
                </form>
            <?php else: ?>
                <p>Niciun fișier al temei nu a generat erori. Totul funcționează curat. 🎉</p>
                <p><em>Log-ul se înregistrează automat când apar erori PHP din fișiere Magpress.</em></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Informatii suplimentare in pop-up-ul "View version X details" din admin.
 */
function magpress_theme_update_info($result, $action, $args) {
    if ('theme_information' !== $action || empty($args->slug) || $args->slug !== get_stylesheet()) {
        return $result;
    }
    $info = get_transient('magpress_update_info');
    if (!is_array($info)) return $result;

    return (object) [
        'name'         => $info['name']    ?? 'Magpress',
        'slug'         => get_stylesheet(),
        'version'      => $info['version'] ?? '',
        'author'       => '<a href="https://seodigital.ro">SEO Digital</a>',
        'requires'     => $info['requires']     ?? '6.0',
        'tested'       => $info['tested']       ?? '6.7',
        'requires_php' => $info['requires_php'] ?? '8.0',
        'download_link'=> $info['package']      ?? '',
        'sections'     => [
            'description' => 'Tema WordPress magazine ultra-performantă pentru rețeaua de site-uri SEO Digital.',
            'changelog'   => '<p>Vezi <a href="' . esc_url($info['changelog_url'] ?? 'https://github.com/kreatorteam-collab/magpress/commits/main') . '" target="_blank">istoricul commit-urilor pe GitHub</a>.</p>',
        ],
    ];
}
add_filter('themes_api', 'magpress_theme_update_info', 10, 3);

/**
 * Calculeaza timestamp-ul celui mai recent fisier modificat din temă
 * (cel mai bun indicator pentru "ultima actualizare via deploy automat").
 */
function magpress_last_modified_timestamp() {
    static $latest = null;
    if ($latest !== null) return $latest;

    $files = array_merge(
        glob(MAGPRESS_DIR . '/*.php')           ?: [],
        glob(MAGPRESS_DIR . '/*.css')           ?: [],
        glob(MAGPRESS_DIR . '/assets/css/*.css') ?: [],
        glob(MAGPRESS_DIR . '/template-parts/*.php') ?: []
    );
    $latest = 0;
    foreach ($files as $f) {
        $m = @filemtime($f);
        if ($m && $m > $latest) $latest = $m;
    }
    return $latest;
}

/**
 * Adaugă în panoul de detalii temă (Aspect → Teme) o linie cu "Ultima actualizare: ..."
 * Așa Eduard vede instant dacă deploy-ul automat a venit.
 */
function magpress_theme_details_last_update($themes) {
    $slug = get_stylesheet();
    if (!isset($themes[$slug])) return $themes;
    $ts = magpress_last_modified_timestamp();
    if (!$ts) return $themes;

    $human_date = date_i18n('j F Y, H:i', $ts);
    $relative   = human_time_diff($ts, current_time('U')) . ' în urmă';
    $appendix = '<br><br><strong>Ultima actualizare:</strong> ' . esc_html($human_date) . ' <em>(' . esc_html($relative) . ')</em>';
    $themes[$slug]['description'] .= $appendix;
    return $themes;
}
add_filter('wp_prepare_themes_for_js', 'magpress_theme_details_last_update');

/**
 * Stergem clase WP inutile de pe body (reduce HTML).
 */
/**
 * Parseaza textarea-ul cu slug-uri categorii excluse din featured si returneaza ID-uri.
 */
function magpress_featured_excluded_cat_ids() {
    $raw = get_theme_mod('magpress_featured_excluded_cats', '');
    if (!$raw) return [];
    $slugs = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    $ids = [];
    foreach ($slugs as $slug) {
        $t = get_term_by('slug', sanitize_title($slug), 'category');
        if ($t && !is_wp_error($t)) $ids[] = (int) $t->term_id;
    }
    return $ids;
}

/**
 * Avatar custom per utilizator — upload din profilul user (admin → Utilizatori).
 * Inlocuieste Gravatar-ul cu poza locala daca exista.
 */
function magpress_user_profile_avatar_field($user) {
    if (!current_user_can('upload_files', $user->ID)) return;
    $attachment_id = (int) get_user_meta($user->ID, 'magpress_avatar_id', true);
    $img_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
    wp_enqueue_media();
    ?>
    <h2>Avatar (Magpress)</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="magpress_avatar_id">Avatar custom</label></th>
            <td>
                <div id="magpress-avatar-preview" style="margin-bottom:10px;">
                    <?php if ($img_url): ?>
                        <img src="<?php echo esc_url($img_url); ?>" alt="" style="max-width:96px;height:auto;border-radius:50%;display:block;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="magpress_avatar_id" id="magpress_avatar_id" value="<?php echo esc_attr($attachment_id); ?>" />
                <button type="button" class="button" id="magpress-avatar-upload">Alege imagine</button>
                <button type="button" class="button" id="magpress-avatar-remove" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>">Șterge</button>
                <p class="description">Imaginea înlocuiește Gravatar-ul în tot site-ul (author box, comentarii etc.). Recomandat: pătrată, minim 200×200 px.</p>
                <script>
                (function(){
                    var frame;
                    var btnUpload = document.getElementById('magpress-avatar-upload');
                    var btnRemove = document.getElementById('magpress-avatar-remove');
                    var input     = document.getElementById('magpress_avatar_id');
                    var preview   = document.getElementById('magpress-avatar-preview');
                    btnUpload.addEventListener('click', function(e){
                        e.preventDefault();
                        if (frame) { frame.open(); return; }
                        frame = wp.media({ title: 'Alege avatar', button: { text: 'Folosește această imagine' }, multiple: false, library: { type: 'image' } });
                        frame.on('select', function(){
                            var att = frame.state().get('selection').first().toJSON();
                            input.value = att.id;
                            var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                            preview.innerHTML = '<img src="' + url + '" alt="" style="max-width:96px;height:auto;border-radius:50%;display:block;" />';
                            btnRemove.style.display = '';
                        });
                        frame.open();
                    });
                    btnRemove.addEventListener('click', function(e){
                        e.preventDefault();
                        input.value = '';
                        preview.innerHTML = '';
                        btnRemove.style.display = 'none';
                    });
                })();
                </script>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'magpress_user_profile_avatar_field');
add_action('edit_user_profile', 'magpress_user_profile_avatar_field');

function magpress_user_profile_avatar_save($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    if (!isset($_POST['magpress_avatar_id'])) return;
    $att_id = (int) $_POST['magpress_avatar_id'];
    if ($att_id > 0) {
        update_user_meta($user_id, 'magpress_avatar_id', $att_id);
    } else {
        delete_user_meta($user_id, 'magpress_avatar_id');
    }
}
add_action('personal_options_update', 'magpress_user_profile_avatar_save');
add_action('edit_user_profile_update', 'magpress_user_profile_avatar_save');

/**
 * Inlocuieste Gravatar cu avatar-ul custom in toate locurile (author box, comentarii, admin).
 */
function magpress_filter_avatar_data($args, $id_or_email) {
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int) $id_or_email;
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (!empty($id_or_email->comment_author_email)) {
            $u = get_user_by('email', $id_or_email->comment_author_email);
            if ($u) $user_id = $u->ID;
        }
    } elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $u = get_user_by('email', $id_or_email);
        if ($u) $user_id = $u->ID;
    } elseif ($id_or_email instanceof WP_Post) {
        $user_id = (int) $id_or_email->post_author;
    }
    if (!$user_id) return $args;
    $att_id = (int) get_user_meta($user_id, 'magpress_avatar_id', true);
    if (!$att_id) return $args;
    $size = isset($args['size']) ? (int) $args['size'] : 96;
    $url = wp_get_attachment_image_url($att_id, [$size, $size]);
    if ($url) {
        $args['url'] = $url;
        $args['found_avatar'] = true;
    }
    return $args;
}
add_filter('pre_get_avatar_data', 'magpress_filter_avatar_data', 10, 2);

/**
 * REST endpoint pentru deploy instant la push.
 *
 * URL: POST {site}/wp-json/magpress/v1/deploy
 * Auth: header X-Magpress-Signature = hash_hmac('sha256', body, deploy_secret per site din wp_options)
 * Body: JSON cu {ref: "main", sha: "..."} (informativ, nu obligatoriu)
 *
 * Flux: validare HMAC → descarcă zipball GitHub → backup → înlocuiește tema → purge LiteSpeed.
 * Rate limit: 1 deploy / 60 sec (transient).
 *
 * Apelat de PowerShell-ul de push după ce împinge commit-ul pe GitHub. Lista site-urilor o
 * citește din magpress_sites pe targetseo.ro.
 */
add_action('rest_api_init', function () {
    register_rest_route('magpress/v1', '/deploy', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_deploy',
        'permission_callback' => '__return_true', // validăm HMAC manual
    ]);
    register_rest_route('magpress/v1', '/version', [
        'methods'             => 'GET',
        'callback'            => function () {
            $t = wp_get_theme();
            return [
                'version' => $t->get('Version'),
                'name'    => $t->get('Name'),
                'slug'    => get_stylesheet(),
            ];
        },
        'permission_callback' => '__return_true',
    ]);
    // Force ping — apelat din dashboard-ul de pe targetseo.ro ("Ping now")
    register_rest_route('magpress/v1', '/send-ping', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_send_ping',
        'permission_callback' => '__return_true',
    ]);
    // Upsert pagini legale (terms / cookies / privacy) — apelat din magpress-legal.php pe targetseo.ro
    register_rest_route('magpress/v1', '/pages/upsert', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_pages_upsert',
        'permission_callback' => '__return_true',
    ]);
    // Status pagini legale (public, doar verificare existență — info nesensibilă, paginile sunt publice oricum)
    register_rest_route('magpress/v1', '/legal-status', [
        'methods'             => 'GET',
        'callback'            => 'magpress_rest_legal_status',
        'permission_callback' => '__return_true',
    ]);
});

function magpress_rest_legal_status() {
    $slugs = ['termeni-si-conditii', 'politica-cookies', 'politica-confidentialitate'];
    $result = [];
    foreach ($slugs as $slug) {
        $page = magpress_find_legal_page($slug);
        if ($page) {
            $result[$slug] = [
                'exists'  => true,
                'title'   => get_the_title($page),
                'url'     => get_permalink($page),
                'slug'    => $page->post_name, // poate fi diferit de canonic (fuzzy)
                'managed' => (bool) get_post_meta($page->ID, '_magpress_managed', true),
            ];
        } else {
            $result[$slug] = ['exists' => false];
        }
    }
    return new WP_REST_Response($result, 200);
}

function magpress_rest_send_ping(WP_REST_Request $request) {
    $body = $request->get_body();
    $sig_received = $request->get_header('x_magpress_signature');
    if (!$sig_received) {
        return new WP_REST_Response(['ok' => false, 'error' => 'missing_signature'], 403);
    }
    $sig_expected = hash_hmac('sha256', $body, magpress_get_deploy_secret());
    if (!hash_equals($sig_expected, $sig_received)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 403);
    }
    // Trimite ping sincron (blocking) ca să returnăm rezultatul
    magpress_send_ping(true);
    return new WP_REST_Response(['ok' => true, 'sent' => true, 'version' => wp_get_theme()->get('Version')], 200);
}

/**
 * Upsert pagină legală (terms / cookies / privacy) — apelat de targetseo.ro la sync.
 * Validare HMAC cu deploy_secret per site (același mecanism ca /deploy).
 * Slug-uri permise: termeni-si-conditii, politica-cookies, politica-confidentialitate.
 */
function magpress_rest_pages_upsert(WP_REST_Request $request) {
    $body = $request->get_body();
    $sig_received = $request->get_header('x_magpress_signature');
    if (!$sig_received) {
        return new WP_REST_Response(['ok' => false, 'error' => 'missing_signature'], 403);
    }
    $sig_expected = hash_hmac('sha256', $body, magpress_get_deploy_secret());
    if (!hash_equals($sig_expected, $sig_received)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 403);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_json'], 400);
    }

    $slug = isset($data['slug']) ? sanitize_title((string) $data['slug']) : '';
    $title = isset($data['title']) ? wp_strip_all_tags((string) $data['title']) : '';
    $content = isset($data['content']) ? (string) $data['content'] : '';

    $allowed_slugs = ['termeni-si-conditii', 'politica-cookies', 'politica-confidentialitate'];
    if (!in_array($slug, $allowed_slugs, true)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_slug'], 400);
    }
    if ($title === '' || $content === '') {
        return new WP_REST_Response(['ok' => false, 'error' => 'empty_title_or_content'], 400);
    }

    // Sanitize content (păstrăm HTML safe pentru paginile editate)
    $content = wp_kses_post($content);

    // Update-only cu fuzzy matching: găsim pagina existentă (chiar dacă are alt slug)
    // și o schimbăm la slug-ul canonic. WP păstrează automat _wp_old_slug → redirect 301 vechi → nou.
    $existing = magpress_find_legal_page($slug);
    if (!$existing) {
        return new WP_REST_Response([
            'ok'      => false,
            'error'   => 'page_not_found',
            'message' => 'Nu am găsit pagina pentru „' . $slug . '" pe acest site (nici cu slug variants, nici după titlu). Creați manual din WP-Admin → Pagini → Adaugă.',
            'slug'    => $slug,
        ], 404);
    }

    $old_slug = $existing->post_name;
    $needs_slug_change = ($old_slug !== $slug);

    $page_args = [
        'ID'           => $existing->ID,
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'page',
        // NU schimbăm post_status (lăsăm cum a fost setat de admin: publish/draft/private)
        'meta_input'   => [
            '_magpress_managed' => '1',
        ],
    ];
    if ($needs_slug_change) {
        $page_args['post_name'] = $slug;
        // WP va salva automat _wp_old_slug = $old_slug → redirect 301 funcționează
    }

    $result = wp_update_post($page_args, true);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'ok'    => false,
            'error' => 'wp_post_error',
            'msg'   => $result->get_error_message(),
        ], 500);
    }

    return new WP_REST_Response([
        'ok'           => true,
        'action'       => $needs_slug_change ? 'updated_with_slug_change' : 'updated',
        'page_id'      => (int) $result,
        'slug'         => $slug,
        'old_slug'     => $needs_slug_change ? $old_slug : null,
        'url'          => get_permalink((int) $result),
    ], 200);
}

/**
 * Fuzzy find pentru pagini legale: caută prin slug exact, slug-uri alternative comune,
 * apoi prin title LIKE. Returnează prima pagină publish găsită sau null.
 */
function magpress_find_legal_page($target_slug) {
    // 1. Slug exact
    $page = get_page_by_path($target_slug, OBJECT, 'page');
    if ($page && $page->post_status === 'publish') return $page;

    // 2. Slug-uri alternative comune
    $alternatives = [
        'termeni-si-conditii' => [
            'termeni', 'terms', 'terms-and-conditions', 't-c', 'termeni-conditii',
            'conditii-utilizare', 'termeni-si-conditii-de-utilizare', 'termeni-de-utilizare',
        ],
        'politica-cookies' => [
            'cookies', 'politica-de-cookies', 'cookie-policy', 'cookie', 'cookies-policy',
            'politica-cookie', 'politica-de-cookie',
        ],
        'politica-confidentialitate' => [
            'confidentialitate', 'politica-de-confidentialitate', 'privacy', 'privacy-policy',
            'gdpr', 'politica-de-confidentialitate-gdpr', 'politica-confidentialitate-gdpr',
            'confidential',
        ],
    ];
    if (isset($alternatives[$target_slug])) {
        foreach ($alternatives[$target_slug] as $alt) {
            $page = get_page_by_path($alt, OBJECT, 'page');
            if ($page && $page->post_status === 'publish') return $page;
        }
    }

    // 3. Title LIKE (fallback final)
    $title_patterns = [
        'termeni-si-conditii'         => ['%termeni%', '%conditi%'],
        'politica-cookies'            => ['%cookie%'],
        'politica-confidentialitate'  => ['%confidential%', '%privacy%', '%gdpr%'],
    ];
    if (isset($title_patterns[$target_slug])) {
        global $wpdb;
        foreach ($title_patterns[$target_slug] as $pattern) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page' AND post_status = 'publish' AND post_title LIKE %s
                 ORDER BY ID ASC LIMIT 1",
                $pattern
            ));
            if ($id > 0) {
                $page = get_post($id);
                if ($page) return $page;
            }
        }
    }

    return null;
}

function magpress_rest_deploy(WP_REST_Request $request) {
    // 1. Rate limit (1/min)
    if (get_transient('magpress_deploy_lock')) {
        return new WP_REST_Response(['ok' => false, 'error' => 'rate_limited'], 429);
    }

    // 2. HMAC validation
    $body = $request->get_body();
    $sig_received = $request->get_header('x_magpress_signature');
    if (!$sig_received) {
        return new WP_REST_Response(['ok' => false, 'error' => 'missing_signature'], 403);
    }
    $sig_expected = hash_hmac('sha256', $body, magpress_get_deploy_secret());
    if (!hash_equals($sig_expected, $sig_received)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 403);
    }

    set_transient('magpress_deploy_lock', time(), 120);
    @ignore_user_abort(true);
    @set_time_limit(180);

    $result = magpress_perform_deploy();

    delete_transient('magpress_deploy_lock');
    // Bust update-info cache ca să nu mai propună update vechi după deploy
    delete_transient('magpress_update_info');

    $status = !empty($result['ok']) ? 200 : 500;
    return new WP_REST_Response($result, $status);
}

function magpress_perform_deploy() {
    // wp_tempnam() trăiește în wp-admin/includes/file.php — REST nu îl încarcă by default
    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $log = [];
    $log_msg = function ($m) use (&$log) { $log[] = '[' . date('H:i:s') . '] ' . $m; };

    $themes_dir   = get_theme_root();
    $current_slug = get_stylesheet();
    $target_dir   = $themes_dir . '/' . $current_slug;
    $backups_dir  = WP_CONTENT_DIR . '/magpress-backups';
    if (!is_dir($backups_dir)) {
        @mkdir($backups_dir, 0755, true);
    }
    $backup_dir   = $backups_dir . '/' . $current_slug . '-bak-' . date('YmdHis');

    $tmp_zip     = wp_tempnam('magpress-deploy.zip');
    $tmp_extract = $backups_dir . '/_tmp-extract-' . uniqid();

    // 1. Descarcă zipball
    $url = 'https://api.github.com/repos/kreatorteam-collab/magpress/zipball/main';
    $log_msg("Download: {$url}");
    $resp = wp_remote_get($url, [
        'timeout' => 90,
        'headers' => [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'magpress-deploy/' . $current_slug,
        ],
    ]);
    if (is_wp_error($resp)) {
        return ['ok' => false, 'error' => 'download_failed', 'detail' => $resp->get_error_message(), 'log' => $log];
    }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return ['ok' => false, 'error' => 'download_http_' . $code, 'log' => $log];
    }
    file_put_contents($tmp_zip, wp_remote_retrieve_body($resp));
    $log_msg('Downloaded ' . filesize($tmp_zip) . ' bytes');

    // 2. Extract
    if (!class_exists('ZipArchive')) {
        @unlink($tmp_zip);
        return ['ok' => false, 'error' => 'no_ziparchive', 'log' => $log];
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp_zip) !== true) {
        @unlink($tmp_zip);
        return ['ok' => false, 'error' => 'zip_open_failed', 'log' => $log];
    }
    if (!is_dir($tmp_extract)) {
        @mkdir($tmp_extract, 0755, true);
    }
    $zip->extractTo($tmp_extract);
    $zip->close();
    @unlink($tmp_zip);

    $dirs = glob($tmp_extract . '/*', GLOB_ONLYDIR);
    if (!$dirs) {
        _magpress_rrmdir($tmp_extract);
        return ['ok' => false, 'error' => 'extract_empty', 'log' => $log];
    }
    $extracted = $dirs[0];
    $log_msg("Extracted to: " . basename($extracted));

    // 3. Backup tema existentă
    if (is_dir($target_dir)) {
        if (!_magpress_rcopy($target_dir, $backup_dir)) {
            _magpress_rrmdir($tmp_extract);
            return ['ok' => false, 'error' => 'backup_failed', 'log' => $log];
        }
        _magpress_rrmdir($target_dir);
        $log_msg('Backup: ' . basename($backup_dir));
    }

    // 4. Move new theme into place
    if (!_magpress_rcopy($extracted, $target_dir)) {
        // Rollback
        if (is_dir($backup_dir)) {
            _magpress_rrmdir($target_dir);
            _magpress_rcopy($backup_dir, $target_dir);
            $log_msg('ROLLBACK din backup');
        }
        _magpress_rrmdir($tmp_extract);
        return ['ok' => false, 'error' => 'copy_failed', 'log' => $log];
    }
    _magpress_rrmdir($tmp_extract);
    $log_msg('Theme deployed: ' . $current_slug);

    // 5. Păstrăm ultimele 3 backup-uri
    $old = glob($backups_dir . '/' . $current_slug . '-bak-*', GLOB_ONLYDIR);
    if ($old && count($old) > 3) {
        sort($old);
        foreach (array_slice($old, 0, count($old) - 3) as $b) {
            _magpress_rrmdir($b);
            $log_msg('Removed old backup: ' . basename($b));
        }
    }

    // 6. Purge LiteSpeed cache (dacă există)
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
        $log_msg('LiteSpeed cache purged');
    }

    // 7. Citim noua versiune (din style.css proaspăt copiat)
    $new_version = '?';
    $style_path = $target_dir . '/style.css';
    if (is_readable($style_path)) {
        $head = file_get_contents($style_path, false, null, 0, 1024);
        if (preg_match('/Version:\s*([0-9.]+)/i', $head, $m)) {
            $new_version = $m[1];
        }
    }

    // 8. Update transient cu versiunea proaspăt-deployată ca să eliminăm discrepanța
    //    "Versiune locală 0.9.8, GitHub are 0.9.7" cauzată de GitHub raw CDN cache (max 5 min).
    if ($new_version !== '?') {
        $update_info = [
            'name'         => 'Magpress',
            'version'      => $new_version,
            'tested'       => '6.7',
            'requires'     => '6.0',
            'requires_php' => '8.0',
            'package'      => 'https://github.com/kreatorteam-collab/magpress/archive/refs/heads/main.zip',
            'url'          => 'https://github.com/kreatorteam-collab/magpress',
        ];
        set_transient('magpress_update_info', $update_info, 6 * HOUR_IN_SECONDS);
    }

    return ['ok' => true, 'version' => $new_version, 'backup' => basename($backup_dir), 'log' => $log];
}

function _magpress_rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if (!$items) { @rmdir($dir); return; }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir . '/' . $item;
        if (is_dir($p)) _magpress_rrmdir($p);
        else @unlink($p);
    }
    @rmdir($dir);
}

function _magpress_rcopy($src, $dst) {
    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !@mkdir($dst, 0755, true)) return false;
    $items = @scandir($src);
    if (!$items) return true;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            if (!_magpress_rcopy($s, $d)) return false;
        } else {
            if (!@copy($s, $d)) return false;
        }
    }
    return true;
}

/**
 * ============================================================
 * NEWS FEATURES — opționale, toate default OFF
 * ============================================================
 * Setări specifice pentru site-uri de știri:
 *   - Most Read widget (top 5 articole în ultimele 48h)
 *   - Tags vizibile sub articol
 *   - Color-coded categories pe badges
 *
 * Toate funcțiile fac early return dacă toggle-ul respectiv e OFF.
 * Pe site-urile non-news, zero overhead în HTML/JS/CSS output.
 */

// --- Customizer section ---
function magpress_register_news_features($wp_customize) {
    $wp_customize->add_section('magpress_news', [
        'title'       => 'Magpress: News features',
        'description' => 'Setări specifice pentru site-uri de știri. TOATE default OFF — activează doar pe site-uri de news. Pe site-urile non-news (toggle OFF), zero overhead în pagină.',
        'priority'    => 40,
    ]);

    // 1. Most Read widget
    $wp_customize->add_setting('magpress_news_most_read', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_most_read', [
        'label'       => 'Widget „Cele mai citite" (sub articol + sub featured pe homepage)',
        'description' => 'Afișează top 5 articole vizualizate în ultimele 48h. Counter prin REST endpoint (bypass LiteSpeed cache).',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);

    // 2. Tags display
    $wp_customize->add_setting('magpress_news_post_tags', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_post_tags', [
        'label'       => 'Tags vizibile sub articol',
        'description' => 'Listează tags-urile postului ca pill-uri cu link la pagina de tag.',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);

    // 3. Color-coded categories — toggle
    $wp_customize->add_setting('magpress_news_category_colors', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_category_colors', [
        'label'       => 'Color-coded categories pe badges',
        'description' => 'Aplică culori distincte pe badge-urile categoriei (Politică = roșu, Sport = verde, etc.).',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);

    // 4. Color map (textarea)
    $wp_customize->add_setting('magpress_news_category_colors_map', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_category_colors_map', [
        'label'       => 'Mapare culori categorii',
        'description' => 'Una pe rând în formatul: <code>slug-categorie:#HEX</code>. Exemplu: <code>politica:#d32f2f</code>. Slug-urile le găsești în Articole → Categorii.',
        'section'     => 'magpress_news',
        'type'        => 'textarea',
    ]);

    // 5. Author byline rich
    $wp_customize->add_setting('magpress_news_author_byline', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_author_byline', [
        'label'       => 'Author byline puternic (E-E-A-T)',
        'description' => 'Înlocuiește meta-ul simplu de pe single cu byline rich: avatar + autor link + data publicării + „Actualizat acum X" dacă postul e modificat ulterior + reading time.',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);

    // 6. OneSignal push notifications
    $wp_customize->add_setting('magpress_news_push_enabled', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_push_enabled', [
        'label'       => 'Push notifications (OneSignal)',
        'description' => 'Activează SDK OneSignal pentru notificări browser. Necesită cont gratuit la <a href="https://onesignal.com" target="_blank">onesignal.com</a> + App ID configurat mai jos.',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_news_onesignal_app_id', [
        'default'           => '',
        'sanitize_callback' => function ($v) {
            $v = trim((string) $v);
            return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) ? strtolower($v) : '';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_onesignal_app_id', [
        'label'       => 'OneSignal App ID (UUID)',
        'description' => 'Format UUID (ex: 12345678-abcd-1234-abcd-1234567890ab). Îl găsești în Settings → Keys & IDs din OneSignal Dashboard.',
        'section'     => 'magpress_news',
        'type'        => 'text',
        'input_attrs' => ['placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'],
    ]);
    $wp_customize->add_setting('magpress_news_onesignal_safari_id', [
        'default'           => '',
        'sanitize_callback' => function ($v) {
            $v = trim((string) $v);
            return preg_match('/^web\.onesignal\.auto\.[a-z0-9-]+$/i', $v) ? $v : '';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_onesignal_safari_id', [
        'label'       => 'OneSignal Safari Web ID (opțional)',
        'description' => 'Format: <code>web.onesignal.auto.xxxxxxxx</code>. Doar dacă vrei suport Safari pe macOS.',
        'section'     => 'magpress_news',
        'type'        => 'text',
    ]);

    // 8. News font preset (combinație Google Fonts pereche headline + body)
    $wp_customize->add_setting('magpress_news_font_preset', [
        'default'           => 'default',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['default', 'editorial', 'magazine', 'digital', 'compact'], true) ? $v : 'default';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_font_preset', [
        'label'       => 'News font preset',
        'description' => 'Pereche font Google pentru news/editorial (headline serif + body sans). Override-uiește setarea „Google Font" din Culori. Lasă „Default" ca să folosești fontul curent.',
        'section'     => 'magpress_news',
        'type'        => 'select',
        'choices'     => [
            'default'   => 'Default (păstrează fontul actual)',
            'editorial' => 'News editorial — Merriweather + Inter',
            'magazine'  => 'Magazine premium — Playfair Display + Lato',
            'digital'   => 'Modern digital — Lora + Inter',
            'compact'   => 'News compact — Roboto Slab + Roboto',
        ],
    ]);

    // 7. Web Stories carousel pe homepage
    $wp_customize->add_setting('magpress_news_web_stories', [
        'default'           => 0,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_news_web_stories', [
        'label'       => 'Web Stories carousel pe homepage',
        'description' => 'Afișează carousel cu ultimele Web Stories pe homepage (sub featured grid). Necesită plugin <a href="https://wordpress.org/plugins/web-stories/" target="_blank">Web Stories de la Google</a> instalat.',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);
}
add_action('customize_register', 'magpress_register_news_features');


// =========================================================================
// MOST READ — counter views + getter + widget
// =========================================================================

// REST endpoint pentru tracking views (bypass LiteSpeed cache)
add_action('rest_api_init', function () {
    register_rest_route('magpress/v1', '/view/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'magpress_news_record_view',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => ['validate_callback' => function ($p) { return is_numeric($p) && (int) $p > 0; }],
        ],
    ]);
});

function magpress_news_record_view(WP_REST_Request $request) {
    if (!get_theme_mod('magpress_news_most_read', 0)) {
        return new WP_REST_Response(['ok' => false, 'reason' => 'disabled'], 200);
    }
    $id = (int) $request->get_param('id');
    $post = get_post($id);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') {
        return new WP_REST_Response(['ok' => false, 'reason' => 'invalid'], 200);
    }
    // Anti-spam basic: 1 view / IP / post / oră (transient)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip) {
        $key = 'magv_' . md5($ip . '_' . $id);
        if (get_transient($key)) {
            return new WP_REST_Response(['ok' => true, 'cached' => true], 200);
        }
        set_transient($key, 1, HOUR_IN_SECONDS);
    }
    $current = (int) get_post_meta($id, '_magpress_v_today', true);
    update_post_meta($id, '_magpress_v_today', $current + 1);
    return new WP_REST_Response(['ok' => true], 200);
}

// JS tracker (sendBeacon → REST endpoint) — emis doar pe single, când toggle ON
function magpress_news_track_view_script() {
    if (!is_singular('post')) return;
    if (!get_theme_mod('magpress_news_most_read', 0)) return;
    $id = (int) get_queried_object_id();
    if (!$id) return;
    $url = rest_url('magpress/v1/view/' . $id);
    ?>
<script id="magpress-news-view-tracker">
(function(){
    var url = <?php echo wp_json_encode($url); ?>;
    if (navigator.sendBeacon) {
        try { navigator.sendBeacon(url, ''); } catch(e){}
    } else if (window.fetch) {
        fetch(url, { method: 'POST', keepalive: true, credentials: 'same-origin' }).catch(function(){});
    }
})();
</script>
    <?php
}
add_action('wp_footer', 'magpress_news_track_view_script', 50);

// Cron daily: mută counter-ul today → yesterday, reset today
add_action('init', function () {
    if (!wp_next_scheduled('magpress_news_views_rotate')) {
        wp_schedule_event(strtotime('tomorrow 00:00'), 'daily', 'magpress_news_views_rotate');
    }
});
add_action('magpress_news_views_rotate', function () {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_magpress_v_yesterday'");
    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_key = '_magpress_v_yesterday' WHERE meta_key = '_magpress_v_today'");
    delete_transient('magpress_news_most_read_5');
    delete_transient('magpress_news_most_read_10');
});

// Cleanup la dezactivare temă
add_action('switch_theme', function () {
    wp_clear_scheduled_hook('magpress_news_views_rotate');
});

// Getter: top N articole în ultimele 48h (today + yesterday meta), cached 5 min
function magpress_get_most_read($limit = 5) {
    if (!get_theme_mod('magpress_news_most_read', 0)) return [];
    $limit = max(1, min(20, (int) $limit));
    $cache_key = 'magpress_news_most_read_' . $limit;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    global $wpdb;
    $sql = "
        SELECT p.ID, (COALESCE(t.meta_value+0,0) + COALESCE(y.meta_value+0,0)) AS views_48h
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_magpress_v_today'
        LEFT JOIN {$wpdb->postmeta} y ON y.post_id = p.ID AND y.meta_key = '_magpress_v_yesterday'
        WHERE p.post_type = 'post' AND p.post_status = 'publish'
        HAVING views_48h > 0
        ORDER BY views_48h DESC
        LIMIT %d
    ";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $limit));
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r->ID, 'views' => (int) $r->views_48h];
    }
    set_transient($cache_key, $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}

// Widget HTML
function magpress_render_most_read($limit = 5, $heading = 'Cele mai citite') {
    if (!get_theme_mod('magpress_news_most_read', 0)) return '';
    $items = magpress_get_most_read($limit);
    if (empty($items)) return '';

    ob_start();
    ?>
    <aside class="most-read-widget">
        <h3 class="most-read-widget__title"><?php echo esc_html($heading); ?></h3>
        <ol class="most-read-widget__list">
            <?php $rank = 0; foreach ($items as $item):
                $p = get_post($item['id']);
                if (!$p) continue;
                $rank++;
                $cat = get_the_category($p->ID);
                $cat_name = $cat ? $cat[0]->name : '';
            ?>
                <li class="most-read-widget__item">
                    <span class="most-read-widget__rank"><?php echo $rank; ?></span>
                    <div class="most-read-widget__body">
                        <?php if ($cat_name): ?>
                            <span class="most-read-widget__cat"><?php echo esc_html($cat_name); ?></span>
                        <?php endif; ?>
                        <a class="most-read-widget__link" href="<?php echo esc_url(get_permalink($p)); ?>">
                            <?php echo esc_html(get_the_title($p)); ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </aside>
    <?php
    return ob_get_clean();
}


// =========================================================================
// TAGS VIZIBILE SUB ARTICOL
// =========================================================================
function magpress_render_post_tags() {
    if (!get_theme_mod('magpress_news_post_tags', 0)) return '';
    if (!is_singular('post')) return '';
    $tags = get_the_tags();
    if (empty($tags) || is_wp_error($tags)) return '';

    ob_start();
    ?>
    <div class="post-tags">
        <span class="post-tags__label">Etichete</span>
        <ul class="post-tags__list">
            <?php foreach ($tags as $tag): ?>
                <li><a class="post-tags__pill" href="<?php echo esc_url(get_tag_link($tag)); ?>">#<?php echo esc_html($tag->name); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}


// =========================================================================
// COLOR-CODED CATEGORIES
// =========================================================================
function magpress_get_category_colors_map() {
    if (!get_theme_mod('magpress_news_category_colors', 0)) return [];
    static $cache = null;
    if ($cache !== null) return $cache;

    $raw = (string) get_theme_mod('magpress_news_category_colors_map', '');
    $cache = [];
    if (!$raw) return $cache;
    foreach (preg_split('/\r?\n/', $raw) as $line) {
        $line = trim($line);
        if (!$line || strpos($line, ':') === false) continue;
        list($slug, $color) = array_map('trim', explode(':', $line, 2));
        $slug = sanitize_title($slug);
        if (!$slug) continue;
        if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $m)) {
            $cache[$slug] = '#' . $m[1];
        }
    }
    return $cache;
}

// CSS inline per categorie (în <head>)
function magpress_news_inline_category_colors() {
    $map = magpress_get_category_colors_map();
    if (empty($map)) return;
    $css = '';
    foreach ($map as $slug => $color) {
        $slug_safe = sanitize_html_class($slug);
        $color_safe = esc_attr($color);
        $css .= ".badge--cat-{$slug_safe}{background-color:{$color_safe};color:#fff;border-color:{$color_safe}}";
    }
    if ($css) {
        echo "<style id='magpress-news-cat-colors'>{$css}</style>\n";
    }
}
add_action('wp_head', 'magpress_news_inline_category_colors', 20);

// Helper: returnează clasa CSS pentru o categorie (folosită în card.php, single.php)
function magpress_category_color_class($cat) {
    if (!$cat || empty($cat->slug)) return '';
    if (!get_theme_mod('magpress_news_category_colors', 0)) return '';
    return 'badge--cat-' . sanitize_html_class($cat->slug);
}


// =========================================================================
// AUTHOR BYLINE RICH (D)
// =========================================================================
function magpress_render_article_meta() {
    if (!is_singular('post')) return '';
    $rich = (bool) get_theme_mod('magpress_news_author_byline', 0);

    if (!$rich) {
        // Comportament default (identic cu varianta veche): plain meta
        ob_start();
        ?>
        <div class="article-meta">
            <span>De <strong><?php the_author(); ?></strong></span>
            <span class="sep">|</span>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
            <span class="sep">|</span>
            <span><?php echo (int) magpress_reading_time(); ?> min citire</span>
        </div>
        <?php
        return ob_get_clean();
    }

    // News byline rich
    $author_id = (int) get_the_author_meta('ID');
    $name = get_the_author_meta('display_name', $author_id);
    $url = get_author_posts_url($author_id);
    $published_ts = get_post_time('U', true);
    $modified_ts = get_post_modified_time('U', true);
    $was_modified = $modified_ts && ($modified_ts - $published_ts) > 6 * HOUR_IN_SECONDS; // afișăm Updated doar dacă >6h diferență
    $reading = (int) magpress_reading_time();

    ob_start();
    ?>
    <div class="article-meta article-meta--rich">
        <div class="article-meta__author">
            <a href="<?php echo esc_url($url); ?>" class="article-meta__avatar" aria-hidden="true" tabindex="-1">
                <?php echo get_avatar($author_id, 44); ?>
            </a>
            <div class="article-meta__author-body">
                <a href="<?php echo esc_url($url); ?>" class="article-meta__author-name"><?php echo esc_html($name); ?></a>
                <div class="article-meta__dates">
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y, H:i')); ?></time>
                    <?php if ($was_modified): ?>
                        <span class="sep">·</span>
                        <span class="article-meta__updated">Actualizat <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>"><?php echo esc_html(human_time_diff($modified_ts, current_time('timestamp', true))); ?> în urmă</time></span>
                    <?php endif; ?>
                    <span class="sep">·</span>
                    <span class="article-meta__reading"><?php echo $reading; ?> min citire</span>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// =========================================================================
// ONESIGNAL PUSH NOTIFICATIONS (E)
// =========================================================================
function magpress_news_onesignal_init() {
    if (!get_theme_mod('magpress_news_push_enabled', 0)) return;
    $app_id = trim((string) get_theme_mod('magpress_news_onesignal_app_id', ''));
    if (!$app_id || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $app_id)) return;
    $safari_id = trim((string) get_theme_mod('magpress_news_onesignal_safari_id', ''));

    $cfg = ['appId' => $app_id];
    if ($safari_id && preg_match('/^web\.onesignal\.auto\.[a-z0-9-]+$/i', $safari_id)) {
        $cfg['safari_web_id'] = $safari_id;
    }
    $cfg_json = wp_json_encode($cfg);
    ?>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script id="magpress-onesignal-init">
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init(<?php echo $cfg_json; ?>);
});
</script>
    <?php
}
add_action('wp_head', 'magpress_news_onesignal_init', 8);


// =========================================================================
// WEB STORIES CAROUSEL (F)
// =========================================================================
function magpress_render_web_stories_carousel($limit = 8, $heading = 'Stories') {
    if (!get_theme_mod('magpress_news_web_stories', 0)) return '';
    // Verificăm dacă plugin-ul Web Stories de la Google e activ (înregistrează post type 'web-story')
    if (!post_type_exists('web-story')) return '';

    $q = new WP_Query([
        'post_type'           => 'web-story',
        'posts_per_page'      => max(3, min(20, (int) $limit)),
        'post_status'         => 'publish',
        'ignore_sticky_posts' => 1,
    ]);
    if (!$q->have_posts()) { wp_reset_postdata(); return ''; }

    ob_start();
    ?>
    <section class="web-stories-carousel">
        <div class="web-stories-carousel__head">
            <h2 class="web-stories-carousel__title">
                <span class="web-stories-carousel__icon" aria-hidden="true">▶</span>
                <?php echo esc_html($heading); ?>
            </h2>
        </div>
        <div class="web-stories-carousel__track">
            <?php while ($q->have_posts()): $q->the_post();
                $thumb = get_the_post_thumbnail_url(get_the_ID(), 'magpress-card');
            ?>
                <a href="<?php the_permalink(); ?>" class="web-stories-carousel__card" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                    <?php if ($thumb): ?>
                        <span class="web-stories-carousel__img" style="background-image:url('<?php echo esc_url($thumb); ?>')"></span>
                    <?php else: ?>
                        <span class="web-stories-carousel__img web-stories-carousel__img--empty"></span>
                    <?php endif; ?>
                    <span class="web-stories-carousel__title-text"><?php the_title(); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    </section>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}


/**
 * Returnează cele 3 pagini legale (terms / cookies / privacy) DACĂ există (gestionate central de targetseo.ro).
 * Folosit în footer.php. Returnează gol dacă paginile nu sunt încă sync-uite — zero impact pe site-uri.
 */
function magpress_get_legal_page_links() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $slugs = ['termeni-si-conditii', 'politica-cookies', 'politica-confidentialitate'];
    foreach ($slugs as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if (!$page) continue;
        if ($page->post_status !== 'publish') continue;
        $cache[] = [
            'title' => get_the_title($page),
            'url'   => get_permalink($page),
            'slug'  => $slug,
        ];
    }
    return $cache;
}

/**
 * ============================================================
 * SCHEMA.ORG JSON-LD — articole + breadcrumb (+ homepage opțional)
 * ============================================================
 * SEOPress emite Organization + WebSite pe homepage, DAR nu emite Article schema pe single posts.
 * Magpress acoperă golul: NewsArticle/Article pe single + BreadcrumbList pe single+archive.
 * Toggle Customizer pentru a dezactiva (dacă altă sursă emite deja).
 */

function magpress_register_schema_settings($wp_customize) {
    $wp_customize->add_section('magpress_schema', [
        'title'       => 'Magpress: Schema.org JSON-LD',
        'description' => 'Adaugă schema.org structured data în <head>. Util pentru Google Discover, Top Stories, rich results. SEOPress emite deja Organization+WebSite pe homepage; Magpress completează cu Article schema pe single posts și BreadcrumbList.',
        'priority'    => 41,
    ]);

    $wp_customize->add_setting('magpress_schema_enabled', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_enabled', [
        'label'       => 'Activează Schema.org JSON-LD (recomandat)',
        'description' => 'Emite NewsArticle/Article pe single + BreadcrumbList pe single & archive. Dezactivează doar dacă alt plugin (Rank Math, Yoast, SEOPress Pro) emite deja Article schema (verifică în Search Console „Rich Results Test").',
        'section'     => 'magpress_schema',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_schema_article_type', [
        'default'           => 'Article',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['Article', 'NewsArticle', 'BlogPosting'], true) ? $v : 'Article';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_article_type', [
        'label'       => 'Tip schema pentru articole',
        'description' => '<code>NewsArticle</code> = site-uri news (eligibilitate Google News/Top Stories). <code>Article</code> = generic (sigur pentru blog/magazine). <code>BlogPosting</code> = blog personal.',
        'section'     => 'magpress_schema',
        'type'        => 'select',
        'choices'     => [
            'Article'     => 'Article (default — generic, sigur)',
            'NewsArticle' => 'NewsArticle (site-uri news/publicații)',
            'BlogPosting' => 'BlogPosting (blog personal)',
        ],
    ]);

    $wp_customize->add_setting('magpress_schema_homepage_extra', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_homepage_extra', [
        'label'       => 'Adaugă Organization+WebSite pe homepage (recomandat)',
        'description' => 'Magpress detectează automat dacă SEOPress emite deja Organization (verifică opțiunea „Knowledge graph type" din SEOPress) și nu duplică. Dezactivează doar dacă în Rich Results Test vezi 2× Organization schema.',
        'section'     => 'magpress_schema',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_schema_publisher_type', [
        'default'           => 'Organization',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['Organization', 'NewsMediaOrganization', 'OnlineBusiness'], true) ? $v : 'Organization';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_publisher_type', [
        'label'       => 'Tip publisher (schema entity)',
        'description' => '<code>Organization</code> = default sigur (lifestyle, magazine, blog). <code>NewsMediaOrganization</code> = site news/publicații (boost Google News + Discover, dar Google verifică mai strict). <code>OnlineBusiness</code> = site comercial cu servicii.',
        'section'     => 'magpress_schema',
        'type'        => 'select',
        'choices'     => [
            'Organization'          => 'Organization (default — sigur pe orice site)',
            'NewsMediaOrganization' => 'NewsMediaOrganization (site-uri news/publicații)',
            'OnlineBusiness'        => 'OnlineBusiness (site comercial/servicii)',
        ],
    ]);

    $wp_customize->add_setting('magpress_schema_publisher_logo', [
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_publisher_logo', [
        'label'       => 'URL logo publisher (opțional)',
        'description' => 'Pentru schema publisher.logo. Recomandat ≥600×60px PNG/SVG. Lasă gol → folosește custom logo din Customizer → Site Identity sau site name.',
        'section'     => 'magpress_schema',
        'type'        => 'url',
    ]);
}
add_action('customize_register', 'magpress_register_schema_settings');

function magpress_emit_schema_jsonld() {
    if (!get_theme_mod('magpress_schema_enabled', 1)) return;

    $schemas = [];

    if (is_singular('post')) {
        $article = magpress_build_article_schema();
        if ($article) $schemas[] = $article;
        $bc = magpress_build_breadcrumb_schema_single();
        if ($bc) $schemas[] = $bc;
    } elseif (is_category() || is_tag() || is_author() || is_archive()) {
        $bc = magpress_build_breadcrumb_schema_archive();
        if ($bc) $schemas[] = $bc;
    } elseif ((is_front_page() || is_home()) && get_theme_mod('magpress_schema_homepage_extra', 1)) {
        // Detectie SEOPress: dacă emite deja Organization, skip ca să nu duplicăm
        if (!magpress_seopress_emits_org_schema()) {
            $ws = magpress_build_website_schema();
            $org = magpress_build_organization_schema();
            if ($ws) $schemas[] = $ws;
            if ($org) $schemas[] = $org;
        }
    }

    foreach ($schemas as $s) {
        echo '<script type="application/ld+json">' . wp_json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
add_action('wp_head', 'magpress_emit_schema_jsonld', 30);

/**
 * Detectează dacă SEOPress emite deja Organization schema pe homepage.
 * Verifică opțiunile pluginului (Knowledge type = Organization).
 * Magpress emite Organization (potrivit pentru publisher) — dacă SEOPress emite Person (default
 * pentru blog personal), Magpress emite Organization peste (publisher-style mai potrivit).
 */
function magpress_seopress_emits_org_schema() {
    if (!function_exists('seopress_get_service') && !defined('SEOPRESS_VERSION')) return false;
    $opts = get_option('seopress_social_option_name');
    if (!is_array($opts)) return false;
    $type = $opts['seopress_social_knowledge_type'] ?? '';
    return $type === 'Organization';
}

function magpress_get_publisher_data() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $logo_url = trim((string) get_theme_mod('magpress_schema_publisher_logo', ''));
    if (!$logo_url) {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }
    }
    $type = get_theme_mod('magpress_schema_publisher_type', 'Organization');
    $cache = [
        '@type' => $type,
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];
    if ($logo_url) {
        $cache['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo_url,
        ];
    }
    return $cache;
}

function magpress_build_article_schema() {
    global $post;
    if (!$post) return null;
    $type = get_theme_mod('magpress_schema_article_type', 'Article');

    $author_id = (int) $post->post_author;
    $author_name = get_the_author_meta('display_name', $author_id);
    $author_url = get_author_posts_url($author_id);

    $image_url = '';
    $image_w = $image_h = null;
    if (has_post_thumbnail($post)) {
        $thumb_id = get_post_thumbnail_id($post);
        $img = wp_get_attachment_image_src($thumb_id, 'magpress-feature');
        if ($img) {
            $image_url = $img[0];
            $image_w = $img[1];
            $image_h = $img[2];
        }
    }

    $cats = get_the_category($post->ID);
    $section = $cats && !is_wp_error($cats) ? $cats[0]->name : '';

    $tags = get_the_tags($post->ID);
    $keywords = ($tags && !is_wp_error($tags)) ? wp_list_pluck($tags, 'name') : [];

    $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 35, '...');

    $schema = [
        '@context'         => 'https://schema.org',
        '@type'            => $type,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => get_permalink($post),
        ],
        'headline'         => mb_substr(wp_strip_all_tags(get_the_title($post)), 0, 110),
        'description'      => mb_substr($excerpt, 0, 300),
        'datePublished'    => get_the_date('c', $post),
        'dateModified'     => get_the_modified_date('c', $post),
        'author'           => [[
            '@type' => 'Person',
            'name'  => $author_name,
            'url'   => $author_url,
        ]],
        'publisher'        => magpress_get_publisher_data(),
    ];

    if ($image_url) {
        $img_obj = [
            '@type' => 'ImageObject',
            'url'   => $image_url,
        ];
        if ($image_w) $img_obj['width']  = (int) $image_w;
        if ($image_h) $img_obj['height'] = (int) $image_h;
        $schema['image'] = $img_obj;
    }

    if ($section) {
        $schema['articleSection'] = $section;
    }
    if (!empty($keywords)) {
        $schema['keywords'] = implode(', ', $keywords);
    }

    // wordCount — recomandat pentru NewsArticle
    $word_count = str_word_count(strip_shortcodes(strip_tags($post->post_content)));
    if ($word_count > 0) {
        $schema['wordCount'] = $word_count;
    }

    // inLanguage
    $schema['inLanguage'] = get_locale() ? str_replace('_', '-', get_locale()) : 'ro-RO';

    return $schema;
}

function magpress_build_breadcrumb_schema_single() {
    global $post;
    if (!$post) return null;

    $items = [];
    $position = 1;
    $items[] = [
        '@type'    => 'ListItem',
        'position' => $position++,
        'name'     => 'Acasă',
        'item'     => home_url('/'),
    ];

    $cats = get_the_category($post->ID);
    if ($cats && !is_wp_error($cats)) {
        $cat = $cats[0];
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $cat->name,
            'item'     => get_category_link($cat),
        ];
    }

    $items[] = [
        '@type'    => 'ListItem',
        'position' => $position++,
        'name'     => mb_substr(wp_strip_all_tags(get_the_title($post)), 0, 120),
        'item'     => get_permalink($post),
    ];

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}

function magpress_build_breadcrumb_schema_archive() {
    $items = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Acasă',
        'item'     => home_url('/'),
    ]];

    if (is_category()) {
        $term = get_queried_object();
        if ($term) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $term->name,
                'item'     => get_category_link($term),
            ];
        }
    } elseif (is_tag()) {
        $term = get_queried_object();
        if ($term) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $term->name,
                'item'     => get_tag_link($term),
            ];
        }
    } elseif (is_author()) {
        $author = get_queried_object();
        if ($author) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $author->display_name,
                'item'     => get_author_posts_url($author->ID),
            ];
        }
    }

    if (count($items) < 2) return null; // doar Acasă — fără breadcrumb

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}

function magpress_build_website_schema() {
    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        'name'            => get_bloginfo('name'),
        'url'             => home_url('/'),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => home_url('/') . '?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function magpress_build_organization_schema() {
    $pub = magpress_get_publisher_data();
    // Adăugăm sameAs din social media URL-uri setate în Customizer (doar URL-uri valide HTTP/HTTPS)
    $sameAs = [];
    $socials = ['facebook', 'instagram', 'pinterest', 'youtube', 'tiktok', 'twitter', 'linkedin'];
    foreach ($socials as $key) {
        $url = trim((string) get_theme_mod("magpress_social_{$key}", ''));
        if (!$url) continue;
        // Filtrăm placeholdere (# / javascript / hashes / relative URLs)
        if (!preg_match('#^https?://#i', $url)) continue;
        $sameAs[] = $url;
    }
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => $pub['@type'],
        'name'     => $pub['name'],
        'url'      => $pub['url'],
    ];
    if (!empty($pub['logo'])) $org['logo'] = $pub['logo'];
    if (!empty($sameAs))      $org['sameAs'] = $sameAs;
    return $org;
}

function magpress_body_classes($classes) {
    $clean = ['magpress'];
    if (is_front_page()) $clean[] = 'home';
    if (is_single())     $clean[] = 'single';
    if (is_archive())    $clean[] = 'archive';
    if (is_page())       $clean[] = 'page';
    if (is_search())     $clean[] = 'search';
    if (is_404())        $clean[] = 'error404';
    if (get_theme_mod('magpress_header_sticky', 1)) $clean[] = 'has-sticky-header';

    // Pastram clase WP esentiale care influenteaza layout (admin-bar offset, logged-in, embed responsive)
    foreach (['admin-bar', 'logged-in', 'wp-embed-responsive', 'rtl'] as $important) {
        if (in_array($important, $classes, true)) $clean[] = $important;
    }
    return $clean;
}
add_filter('body_class', 'magpress_body_classes');
