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
    add_image_size('magpress-hero',    1280, 0,   false); // hero articol single: full-width, proportional, FĂRĂ crop (existentele cad pe full)
    // NOTE: `magpress-thumb` (120x120) eliminat — neutilizat în temă, doar consuma memorie la upload.

    load_theme_textdomain('magpress', MAGPRESS_DIR . '/languages');
}
add_action('after_setup_theme', 'magpress_setup');

/**
 * Dezactivează mărimile WP default neutilizate în Magpress: medium (300), medium_large (768), large (1024).
 * Magpress folosește doar `thumbnail` (avatar autor), `magpress-card` (600x400), `magpress-feature` (1200x750) și `magpress-hero` (1280, fără crop).
 * Asta reduce numărul de procesări la upload de la ~8 la 4 → mai puțină memorie/CPU.
 * Fix critic pentru site-urile pe hosting cu memory_limit mic (medpress.ro, observatormedical.ro etc.).
 */
function magpress_remove_unused_image_sizes($sizes) {
    unset($sizes['medium']);
    unset($sizes['medium_large']);
    unset($sizes['large']);
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'magpress_remove_unused_image_sizes');

// NOTĂ: tema NU mai forțează editorul de imagini. WordPress alege nativ GD/Imagick (ambele
// active pe server), exact ca orice altă temă. Dacă un anumit host are Imagick prost
// configurat (policy.xml), se reglează server-side — nu e treaba temei.

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
 * Throttle heartbeat pe ecranul de editare articol: 15s -> 60s.
 * Pe hosturi cu puține procese PHP concurente (medpress, observatormedical), autosave +
 * heartbeat se ciocnesc cu upload-ul de imagini / generarea Claude Writer și le „înfometează"
 * → „Serverul nu poate procesa imaginea" / „Conexiune pierdută". Reducând frecvența cererilor
 * de fundal de 4x, lăsăm workeri liberi pentru upload/generare. Autosave-ul rămâne funcțional
 * (la 60s, oricum default-ul WP). NU atinge procesarea imaginilor — doar ritmul cererilor.
 */
function magpress_throttle_admin_heartbeat($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }
    $js = "(function(){function s(){try{if(window.wp&&wp.heartbeat&&wp.heartbeat.interval){wp.heartbeat.interval(60);}}catch(e){}}if(window.jQuery){jQuery(s);}else{document.addEventListener('DOMContentLoaded',s);}setInterval(s,30000);})();";
    wp_add_inline_script('heartbeat', $js);
}
add_action('admin_enqueue_scripts', 'magpress_throttle_admin_heartbeat');

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

    // Font meniu (opțional, stil știri) — încarcă fontul + îl aplică DOAR pe navigație.
    $menu_font = magpress_menu_font_config();
    if ($menu_font) {
        wp_enqueue_style('magpress-menu-font', 'https://fonts.googleapis.com/css2?family=' . $menu_font['google'] . '&display=swap', [], null);
        // Fonturile de tip știri sunt condensate → citesc mic; mărim ușor pt prezență (stil Mediafax).
        wp_add_inline_style('magpress-main', '.primary-nav .menu a,.mobile-menu .menu a{font-family:' . $menu_font['family'] . '!important;font-size:1rem!important;letter-spacing:.04em}');
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
 * Font DOAR pentru meniul de navigație (stil site de știri, ex. Mediafax).
 * Independent de preset-ul de headline/body. Returnează null dacă e 'default'.
 */
function magpress_menu_font_config() {
    $key = get_theme_mod('magpress_menu_font', 'default');
    $map = [
        'oswald'           => ['google' => 'Oswald:wght@500;600;700',      'family' => '"Oswald",Impact,"Arial Narrow",sans-serif'],
        'roboto-condensed' => ['google' => 'Roboto+Condensed:wght@500;700', 'family' => '"Roboto Condensed",Impact,"Arial Narrow",sans-serif'],
        'archivo-narrow'   => ['google' => 'Archivo+Narrow:wght@600;700',   'family' => '"Archivo Narrow","Arial Narrow",sans-serif'],
        'barlow-condensed' => ['google' => 'Barlow+Condensed:wght@600;700', 'family' => '"Barlow Condensed",Impact,"Arial Narrow",sans-serif'],
        'bebas'            => ['google' => 'Bebas+Neue',                    'family' => '"Bebas Neue",Impact,"Arial Narrow",sans-serif'],
        'anton'            => ['google' => 'Anton',                        'family' => '"Anton",Impact,"Arial Narrow",sans-serif'],
        'fjalla'           => ['google' => 'Fjalla+One',                   'family' => '"Fjalla One",Impact,"Arial Narrow",sans-serif'],
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
    $has_menu = (bool) magpress_menu_font_config();
    if (!$has_preset && !$has_default && !$has_menu) return $urls;
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
    if ($handle === 'magpress-google-font' || $handle === 'magpress-menu-font') {
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
    // Multi-select pentru slot — stochează ID-uri categorie (CSV).
    // Backward compat: dacă valoarea e un singur „12" tot funcționează (parsat ca [12]).
    class Magpress_Slot_Cats_Control extends WP_Customize_Control {
        public $type = 'magpress_slot_cats';
        public $current_slot = 0;
        public $used_in_slots = [];
        public function render_content() {
            $categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'number' => 200]);
            $value = (string) $this->value();
            $selected_ids = [];
            if ($value !== '') {
                foreach (preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $v) {
                    $id = (int) $v;
                    if ($id > 0) $selected_ids[] = $id;
                }
            }
            $uid = 'magpress-sct-' . esc_attr($this->id);
            $current = (int) $this->current_slot;
            $used = is_array($this->used_in_slots) ? $this->used_in_slots : [];
            ?>
            <span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
            <?php if ($this->description): ?>
                <span class="description customize-control-description"><?php echo wp_kses_post($this->description); ?></span>
            <?php endif; ?>
            <?php if (empty($categories)): ?>
                <p style="padding:8px;background:#fff3cd;border:1px solid #ffc107;border-radius:3px;margin-top:6px;font-size:.85em;">Nu există categorii. Creează din Articole → Categorii.</p>
            <?php else: ?>
                <div id="<?php echo $uid; ?>-wrap" style="max-height:260px;overflow-y:auto;border:1px solid #ddd;padding:8px 10px;background:#fff;margin-top:6px;border-radius:3px;">
                    <?php foreach ($categories as $cat):
                        $cid = (int) $cat->term_id;
                        // Marker „folosit la slot N" — excludem slot-ul curent.
                        $other_slots = [];
                        if (isset($used[$cid]) && is_array($used[$cid])) {
                            foreach ($used[$cid] as $sn) {
                                if ((int) $sn !== $current) $other_slots[] = (int) $sn;
                            }
                        }
                        $marker = '';
                        if ($other_slots) {
                            sort($other_slots);
                            $marker = ' — folosit la slot ' . implode(', ', $other_slots);
                        }
                    ?>
                        <label style="display:block;padding:3px 0;cursor:pointer;font-size:.92em;">
                            <input type="checkbox" value="<?php echo $cid; ?>"
                                   <?php checked(in_array($cid, $selected_ids, true)); ?>>
                            <?php echo esc_html($cat->name); ?>
                            <span style="color:#999;font-size:.85em;">(<?php echo (int) $cat->count; ?>)</span>
                            <span class="magpress-used-marker" style="color:#c47f00;font-size:.82em;font-style:italic;<?php echo $marker === '' ? 'display:none;' : ''; ?>"><?php echo esc_html($marker); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:.78em;color:#666;margin:4px 0 0;">Bifează 1 sau mai multe. Slot-ul agregă cele mai recente articole din toate categoriile bifate.</p>
            <?php endif; ?>
            <input type="hidden" id="<?php echo $uid; ?>-input" <?php $this->link(); ?> value="<?php echo esc_attr($value); ?>">
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
    var SLOT_COUNT = 30;

    function parseIds(csv) {
        if (!csv) return [];
        return String(csv).split(/[\s,]+/).map(function(s){return parseInt(s,10);}).filter(function(n){return n>0;});
    }

    function updateMarkers() {
        // 1. Build map: cat_id => [slot1, slot2, ...] (multi-select)
        var used = {};
        for (var i = 1; i <= SLOT_COUNT; i++) {
            var typeSetting = wp.customize('magpress_slot_' + i + '_type');
            var catSetting  = wp.customize('magpress_slot_' + i + '_cat');
            if (!typeSetting || !catSetting) continue;
            if (typeSetting.get() !== 'category') continue;
            parseIds(catSetting.get()).forEach(function(cid){
                if (!used[cid]) used[cid] = [];
                if (used[cid].indexOf(i) === -1) used[cid].push(i);
            });
        }

        // 2. Update marker span pe fiecare label din controlul multi-checkbox
        for (var i = 1; i <= SLOT_COUNT; i++) {
            var ctrl = wp.customize.control('magpress_slot_' + i + '_cat');
            if (!ctrl || !ctrl.container || !ctrl.container[0]) continue;
            var labels = ctrl.container[0].querySelectorAll('label');
            for (var j = 0; j < labels.length; j++) {
                var label = labels[j];
                var cb = label.querySelector('input[type=checkbox]');
                if (!cb) continue;
                var cid = parseInt(cb.value, 10);
                var marker = label.querySelector('.magpress-used-marker');
                if (!marker) continue; // ar trebui mereu să existe (PHP-ul o emite)
                var others = (used[cid] || []).filter(function(s){ return s !== i; }).sort(function(a,b){return a-b;});
                if (others.length === 0) {
                    marker.textContent = '';
                    marker.style.display = 'none';
                } else {
                    marker.textContent = ' — folosit la slot ' + others.join(', ');
                    marker.style.display = '';
                }
            }
        }
    }

    wp.customize.bind('ready', function(){
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

    // ---------- Sectiune: Homepage (30 sloturi flexibile) ----------
    $wp_customize->add_section('magpress_homepage', [
        'title'       => 'Magpress: Homepage',
        'description' => '30 sloturi configurabile, în ordinea afișării. Fiecare slot poate fi: rând cu articole dintr-o categorie SAU bloc cu cod HTML/AdSense (banner, reclamă). Default: doar primele 4 sunt active (categorii); restul sunt „Gol".',
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

    // Map: cat_id => [slot1, slot2, ...] (pentru marker „— folosit la slot N")
    // Multi-select: o categorie poate fi în mai multe sloturi simultan.
    $used_in_slots = [];
    for ($k = 1; $k <= 30; $k++) {
        $k_type = get_theme_mod("magpress_slot_{$k}_type", $k <= 4 ? 'category' : 'none');
        if ($k_type !== 'category') continue;
        $raw = (string) get_theme_mod("magpress_slot_{$k}_cat", '');
        foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $cid = (int) $part;
            if ($cid <= 0) continue;
            if (!isset($used_in_slots[$cid])) $used_in_slots[$cid] = [];
            if (!in_array($k, $used_in_slots[$cid], true)) $used_in_slots[$cid][] = $k;
        }
    }

    for ($i = 1; $i <= 30; $i++) {
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

        // 2. Categorie (vizibila daca tip = category) — multi-select CSV de ID-uri
        $wp_customize->add_setting("magpress_slot_{$i}_cat", [
            'default'           => '',
            'sanitize_callback' => function($v) {
                // Accept atât int legacy ("12") cât și CSV ("12,15,18")
                if (is_array($v)) $v = implode(',', $v);
                $v = (string) $v;
                $ids = [];
                foreach (preg_split('/[\s,]+/', $v, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $id = (int) $part;
                    if ($id > 0) $ids[] = $id;
                }
                $ids = array_values(array_unique($ids));
                return implode(',', $ids);
            },
            'transport'         => 'refresh',
        ]);
        $ctrl = new Magpress_Slot_Cats_Control($wp_customize, "magpress_slot_{$i}_cat", [
            'label'           => "Slot {$i} — Categorii",
            'description'     => 'Bifează una sau mai multe. Pentru o singură categorie cu sub-categorii (ex: „Lumea sportului") — WordPress include automat copiii.',
            'section'         => 'magpress_homepage',
            'active_callback' => function() use ($i) {
                return get_theme_mod("magpress_slot_{$i}_type", 'none') === 'category';
            },
        ]);
        $ctrl->current_slot  = $i;
        $ctrl->used_in_slots = $used_in_slots;
        $wp_customize->add_control($ctrl);

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

        // 4b. Titlu opțional H2 (vizibil daca tip = ad)
        $wp_customize->add_setting("magpress_slot_{$i}_html_title", [
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_html_title", [
            'label'           => "Slot {$i} — Titlu (h2) [opțional]",
            'description'     => 'Apare deasupra widget-ului ca h2. Lasă gol pentru a nu afișa.',
            'section'         => 'magpress_homepage',
            'type'            => 'text',
            'active_callback' => function() use ($i) {
                return get_theme_mod("magpress_slot_{$i}_type", 'none') === 'ad';
            },
        ]);

        // 4c. Subtitlu opțional (vizibil daca tip = ad)
        $wp_customize->add_setting("magpress_slot_{$i}_html_subtitle", [
            'default'           => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_html_subtitle", [
            'label'           => "Slot {$i} — Subtitlu / descriere [opțional]",
            'description'     => 'Apare sub titlu, ca paragraf descriptiv. Lasă gol pentru a nu afișa.',
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

    $wp_customize->add_setting('magpress_article_ad_mid2', [
        'default'           => '',
        'sanitize_callback' => function($v) { return is_string($v) ? $v : ''; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_article_ad_mid2', [
        'label'       => 'A doua reclamă în articol (pe la 60%)',
        'description' => 'Cod pentru a DOUA reclamă din corpul articolului, mai jos (doar pe articole lungi, ≥10 paragrafe). ATENȚIE la MGID/AdsKeeper: pune aici un widget cu ALT widget-id decât cel de sus, altfel rămâne gol. Lasă gol pentru a nu afișa a doua reclamă.',
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

    // ---------- Sectiune: Coduri personalizate (Header/Footer) ----------
    $wp_customize->add_section('magpress_custom_code', [
        'title'       => 'Magpress: Coduri Header/Footer',
        'description' => 'Adaugă coduri de urmărire (Google Analytics, Google Tag Manager, Facebook Pixel, verificări de proprietate, chat widgets etc.). Codul e inserat exact așa cum îl scrii — include tag-urile <script> / <style> / <meta> unde e nevoie.',
        'priority'    => 160,
    ]);

    // Cod în <head> — analytics, meta verification, GTM etc.
    $wp_customize->add_setting('magpress_code_head', [
        'default'           => '',
        'sanitize_callback' => 'magpress_sanitize_custom_code',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_code_head', [
        'label'       => 'Cod în <head>',
        'description' => 'Se inserează chiar înainte de închiderea </head>, pe toate paginile. Ideal pentru Google Analytics (gtag.js), Google Tag Manager, meta tag-uri de verificare (Search Console), Facebook Pixel etc.',
        'section'     => 'magpress_custom_code',
        'type'        => 'textarea',
        'input_attrs' => ['rows' => 8, 'placeholder' => "<!-- ex: Google Analytics -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-XXXX\"></script>"],
    ]);

    // Cod imediat după <body> — GTM noscript etc.
    $wp_customize->add_setting('magpress_code_body_open', [
        'default'           => '',
        'sanitize_callback' => 'magpress_sanitize_custom_code',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_code_body_open', [
        'label'       => 'Cod după deschiderea <body>',
        'description' => 'Se inserează imediat după tag-ul <body>, pe toate paginile. Folosit de obicei pentru fragmentul <noscript> al Google Tag Manager.',
        'section'     => 'magpress_custom_code',
        'type'        => 'textarea',
        'input_attrs' => ['rows' => 5, 'placeholder' => "<!-- ex: Google Tag Manager (noscript) -->"],
    ]);

    // Cod înainte de </body> — footer scripts, chat widgets etc.
    $wp_customize->add_setting('magpress_code_footer', [
        'default'           => '',
        'sanitize_callback' => 'magpress_sanitize_custom_code',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_code_footer', [
        'label'       => 'Cod în footer (înainte de </body>)',
        'description' => 'Se inserează chiar înainte de închiderea </body>, pe toate paginile. Ideal pentru scripturi care nu trebuie să blocheze randarea: chat widgets, remarketing, heatmaps etc.',
        'section'     => 'magpress_custom_code',
        'type'        => 'textarea',
        'input_attrs' => ['rows' => 8, 'placeholder' => "<!-- ex: chat widget, remarketing... -->"],
    ]);

    // ads.txt — servit automat de temă la /ads.txt (fără fișier manual per site).
    $wp_customize->add_setting('magpress_ads_txt', [
        'default'           => '',
        'sanitize_callback' => 'magpress_sanitize_custom_code',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_ads_txt', [
        'label'       => 'Conținut ads.txt',
        'description' => 'Liniile ads.txt cerute de rețelele de reclame (AdsKeeper, AdSense etc.). Tema le servește automat la adresa /ads.txt — NU trebuie să creezi fișier pe server. Copiază liniile din dashboard-ul fiecărei rețele. Lasă gol pentru a nu servi ads.txt din temă. Nu funcționează dacă există deja un fișier fizic ads.txt pe server (acela are prioritate).',
        'section'     => 'magpress_custom_code',
        'type'        => 'textarea',
        'input_attrs' => ['rows' => 8, 'placeholder' => "google.com, pub-XXXXXXXX, DIRECT, f08c47fec0942fa0\nadskeeper.com, 1103871, DIRECT"],
    ]);

    // ---------- Sectiune: Parteneri (feed din alte site-uri) ----------
    $wp_customize->add_section('magpress_partners', [
        'title'       => 'Magpress: Parteneri (feed extern)',
        'description' => 'Carduri cu ultimele articole de pe alte site-uri ale tale (stil „Parteneri" Libertatea). Completează URL-ul site-ului sursă; opțional, slug-ul unei categorii (ex: „sport"). Secțiunea apare doar dacă e configurat cel puțin un site.',
        'priority'    => 161,
    ]);

    $wp_customize->add_setting('magpress_partners_title', [
        'default'           => 'Parteneri',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_partners_title', [
        'label'   => 'Titlul secțiunii',
        'section' => 'magpress_partners',
        'type'    => 'text',
    ]);

    $wp_customize->add_setting('magpress_partners_on_home', [
        'default'           => 1,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_partners_on_home', [
        'label'   => 'Afișează pe homepage (jos, după sloturi)',
        'section' => 'magpress_partners',
        'type'    => 'checkbox',
    ]);

    for ($i = 1; $i <= 4; $i++) {
        $wp_customize->add_setting("magpress_partner_{$i}_url", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_partner_{$i}_url", [
            'label'       => "Sursa {$i} — URL site",
            'description' => $i === 1 ? 'Ex: https://revistacasamea.ro (orice site WordPress de-al tău)' : '',
            'section'     => 'magpress_partners',
            'type'        => 'url',
        ]);

        $wp_customize->add_setting("magpress_partner_{$i}_cat", [
            'default'           => '',
            'sanitize_callback' => 'magpress_sanitize_slug_list',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_partner_{$i}_cat", [
            'label'       => "Sursa {$i} — DOAR din categoriile (slug-uri, opțional)",
            'description' => $i === 1 ? 'Gol = cele mai noi articole din tot site-ul. Mai multe categorii separate prin virgulă (ex: sport, auto).' : '',
            'section'     => 'magpress_partners',
            'type'        => 'text',
        ]);

        $wp_customize->add_setting("magpress_partner_{$i}_cat_exclude", [
            'default'           => '',
            'sanitize_callback' => 'magpress_sanitize_slug_list',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_partner_{$i}_cat_exclude", [
            'label'       => "Sursa {$i} — EXCLUDE categoriile (slug-uri, opțional)",
            'description' => $i === 1 ? 'Articolele din aceste categorii NU apar (ex: uncategorized, promo). Separate prin virgulă.' : '',
            'section'     => 'magpress_partners',
            'type'        => 'text',
        ]);
    }

    // ---------- Sectiune: Curs valutar BNR (top bar) ----------
    $wp_customize->add_section('magpress_curs_bnr', [
        'title'       => 'Magpress: Curs valutar BNR',
        'description' => 'Bara subțire cu cursul oficial BNR, deasupra header-ului. Sursa: bnr.ro (cache 6h). Există și widget („Magpress: Curs valutar BNR") + shortcode [curs_bnr] pentru alte poziții.',
        'priority'    => 162,
    ]);
    $wp_customize->add_setting('magpress_topbar_curs', [
        'default'           => 0,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_curs', [
        'label'   => 'Afișează bara cu cursul BNR (deasupra header-ului)',
        'section' => 'magpress_curs_bnr',
        'type'    => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_topbar_curs_list', [
        'default'           => 'EUR,USD',
        'sanitize_callback' => function($v) { return strtoupper(preg_replace('/[^A-Za-z,\s]/', '', (string) $v)); },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_curs_list', [
        'label'       => 'Valute afișate (coduri ISO, separate prin virgulă)',
        'description' => 'Ex: EUR,USD,GBP,CHF — orice cod din feed-ul BNR.',
        'section'     => 'magpress_curs_bnr',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_topbar_curs_align', [
        'default'           => 'left',
        'sanitize_callback' => function($v) { return in_array($v, ['left', 'right'], true) ? $v : 'left'; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_curs_align', [
        'label'   => 'Aliniere (în lățimea conținutului)',
        'section' => 'magpress_curs_bnr',
        'type'    => 'radio',
        'choices' => ['left' => 'Stânga', 'right' => 'Dreapta'],
    ]);

    // ---------- Sectiune: Bara Vreme (top bar, sub Curs BNR) ----------
    $wp_customize->add_section('magpress_vreme_bar', [
        'title'       => 'Magpress: Bara Vreme',
        'description' => 'Bandă subțire care derulează vremea pe orașe (date oficiale ANM, cache 3h), imediat sub bara Curs BNR. Există și shortcode [vreme_bar] + widget pentru alte poziții.',
        'priority'    => 163,
    ]);
    $wp_customize->add_setting('magpress_topbar_vreme', [
        'default'           => 0,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme', [
        'label'   => 'Afișează banda cu vremea (sub bara Curs BNR)',
        'section' => 'magpress_vreme_bar',
        'type'    => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_topbar_vreme_orase', [
        'default'           => 'București,Cluj-Napoca,Iași,Constanța,Craiova,Sibiu',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme_orase', [
        'label'       => 'Orașe afișate (separate prin virgulă)',
        'description' => 'Disponibile din feed-ul ANM: București, Cluj-Napoca, Iași, Constanța, Craiova, Sibiu, Arad, Botoșani, Râmnicu Vâlcea, Sulina. Gol = toate.',
        'section'     => 'magpress_vreme_bar',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_topbar_vreme_pozitie', [
        'default'           => 'sub_header',
        'sanitize_callback' => function($v) { return in_array($v, ['sub_header', 'sub_curs'], true) ? $v : 'sub_header'; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme_pozitie', [
        'label'   => 'Poziția benzii',
        'section' => 'magpress_vreme_bar',
        'type'    => 'radio',
        'choices' => [
            'sub_header' => 'Sub header (înainte de conținut)',
            'sub_curs'   => 'În top bar, sub Curs BNR',
        ],
    ]);
    $wp_customize->add_setting('magpress_topbar_vreme_scroll', [
        'default'           => 0,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme_scroll', [
        'label'       => 'Derulează banda (ticker în mișcare)',
        'description' => 'Nebifat = orașele stau fixe (recomandat). Bifat = banda derulează lent.',
        'section'     => 'magpress_vreme_bar',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_topbar_vreme_tema', [
        'default'           => 'inchis',
        'sanitize_callback' => function($v) { return in_array($v, ['inchis', 'deschis'], true) ? $v : 'inchis'; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme_tema', [
        'label'   => 'Culoarea benzii',
        'section' => 'magpress_vreme_bar',
        'type'    => 'radio',
        'choices' => [
            'inchis'  => 'Închis (fundal întunecat, text alb)',
            'deschis' => 'Deschis (fundal gri deschis, text închis)',
        ],
    ]);
    $wp_customize->add_setting('magpress_search_in_topbar', [
        'default'           => 0,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_search_in_topbar', [
        'label'       => 'Mută căutarea în top bar (lângă dată)',
        'description' => 'Scoate lupa din meniu și pune un link „Caută" în top bar (deschide același modal). Eliberează spațiu în header pentru meniu mai mare. Necesită top bar-ul afișat.',
        'section'     => 'magpress_curs_bnr',
        'type'        => 'checkbox',
    ]);
}
add_action('customize_register', 'magpress_customize_register');

/**
 * Sanitizare pentru codurile personalizate (analytics/scripts) din Customizer.
 * Aceste câmpuri sunt setate doar de administratori cu acces la Customizer,
 * deci acceptăm HTML/JS brut (inclusiv <script>) — la fel ca alte plugin-uri de
 * tip „Insert Headers and Footers". Doar ne asigurăm că valoarea e string.
 */
function magpress_sanitize_custom_code($value) {
    return is_string($value) ? $value : '';
}

/**
 * Emite codurile personalizate în head / body-open / footer.
 */
function magpress_print_code_head() {
    $code = (string) get_theme_mod('magpress_code_head', '');
    if (trim($code) !== '') { echo "\n" . $code . "\n"; }
}
add_action('wp_head', 'magpress_print_code_head', 99);

function magpress_print_code_body_open() {
    $code = (string) get_theme_mod('magpress_code_body_open', '');
    if (trim($code) !== '') { echo "\n" . $code . "\n"; }
}
add_action('wp_body_open', 'magpress_print_code_body_open', 5);

function magpress_print_code_footer() {
    $code = (string) get_theme_mod('magpress_code_footer', '');
    if (trim($code) !== '') { echo "\n" . $code . "\n"; }
}
add_action('wp_footer', 'magpress_print_code_footer', 99);

/**
 * Servește /ads.txt din setarea Customizer (Authorized Digital Sellers).
 * Astfel nu trebuie creat fișier fizic pe fiecare din cele 64 de site-uri —
 * rețelele de reclame (AdsKeeper, AdSense) îl citesc de la {site}/ads.txt.
 * NB: dacă există un fișier fizic ads.txt în root, serverul îl servește pe
 * acela direct și PHP nu mai rulează — atunci setarea din temă e ignorată.
 */
function magpress_serve_ads_txt() {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (strtolower(trim($path, '/')) !== 'ads.txt') { return; }

    $content = trim((string) get_theme_mod('magpress_ads_txt', ''));
    if ($content === '') { return; } // lasă WP să dea 404 normal

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: max-age=86400');
    }
    echo $content . "\n";
    exit;
}
add_action('init', 'magpress_serve_ads_txt', 0);

/**
 * ============================================================
 * PARTENERI — feed de articole din alte site-uri (stil Libertatea)
 * ============================================================
 * Sursele (URL + categorie opțională) vin din Customizer. Articolele se iau
 * prin REST API-ul WordPress al site-ului sursă (wp-json/wp/v2), cu imagine
 * featured, și se țin în cache 30 min (transient). Cache-ul e reîmprospătat
 * și de cron-ul de 5 minute, ca vizitatorii să nu aștepte fetch-ul aproape
 * niciodată. La eroare → cache gol 10 min, pagina nu se blochează.
 */
/** Sanitizează o listă de slug-uri separate prin virgulă („Sport, AUTO" → „sport,auto"). */
function magpress_sanitize_slug_list($v) {
    if (!is_string($v)) return '';
    $slugs = preg_split('/[\s,]+/', $v, -1, PREG_SPLIT_NO_EMPTY);
    $clean = array_filter(array_map('sanitize_title', $slugs));
    return implode(',', array_unique($clean));
}

function magpress_partner_sources() {
    $out = [];
    for ($i = 1; $i <= 4; $i++) {
        $url = trim((string) get_theme_mod("magpress_partner_{$i}_url", ''));
        if ($url === '') continue;
        if (!preg_match('#^https?://#i', $url)) { $url = 'https://' . $url; }
        $out[] = [
            'url'     => rtrim($url, '/'),
            'cat'     => magpress_sanitize_slug_list((string) get_theme_mod("magpress_partner_{$i}_cat", '')),
            'exclude' => magpress_sanitize_slug_list((string) get_theme_mod("magpress_partner_{$i}_cat_exclude", '')),
        ];
    }
    return $out;
}

/** Rezolvă o listă de slug-uri („sport,auto") în ID-uri de categorii de pe site-ul sursă. */
function magpress_partner_resolve_cat_ids($site_url, $slug_list) {
    if ($slug_list === '') return [];
    $resp = wp_remote_get(
        $site_url . '/wp-json/wp/v2/categories?per_page=100&_fields=id&slug=' . rawurlencode($slug_list),
        ['timeout' => 4]
    );
    if (is_wp_error($resp) || 200 !== wp_remote_retrieve_response_code($resp)) return [];
    $cats = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($cats)) return [];
    $ids = [];
    foreach ($cats as $c) {
        if (!empty($c['id'])) { $ids[] = (int) $c['id']; }
    }
    return $ids;
}

function magpress_partner_fetch($src, $limit = 4) {
    $exclude = isset($src['exclude']) ? $src['exclude'] : '';
    $key     = 'magpress_partner_' . md5($src['url'] . '|' . $src['cat'] . '|' . $exclude . '|' . $limit);
    $cached  = get_transient($key);
    if ($cached !== false) { return is_array($cached) ? $cached : []; }

    $posts = [];
    $api   = $src['url'] . '/wp-json/wp/v2/posts?per_page=' . (int) $limit . '&_embed=1';

    $include_ids = magpress_partner_resolve_cat_ids($src['url'], $src['cat']);
    if ($include_ids) {
        $api .= '&categories=' . implode(',', $include_ids);
    }
    $exclude_ids = magpress_partner_resolve_cat_ids($src['url'], $exclude);
    if ($exclude_ids) {
        $api .= '&categories_exclude=' . implode(',', $exclude_ids);
    }

    $resp = wp_remote_get($api, ['timeout' => 4]);
    if (!is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp)) {
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (is_array($data)) {
            $host = preg_replace('/^www\./i', '', (string) parse_url($src['url'], PHP_URL_HOST));
            foreach ($data as $p) {
                if (empty($p['link']) || empty($p['title']['rendered'])) continue;
                $img   = '';
                $media = isset($p['_embedded']['wp:featuredmedia'][0]) ? $p['_embedded']['wp:featuredmedia'][0] : null;
                if (is_array($media)) {
                    $sizes = isset($media['media_details']['sizes']) ? $media['media_details']['sizes'] : [];
                    foreach (['medium_large', 'medium', 'large'] as $s) {
                        if (!empty($sizes[$s]['source_url'])) { $img = $sizes[$s]['source_url']; break; }
                    }
                    if ($img === '' && !empty($media['source_url'])) { $img = $media['source_url']; }
                }
                $posts[] = [
                    'title' => wp_strip_all_tags(html_entity_decode((string) $p['title']['rendered'], ENT_QUOTES)),
                    'link'  => (string) $p['link'],
                    'img'   => $img,
                    'host'  => $host,
                ];
            }
        }
        // Cache 12h (reglabil via filter). Reîmprospătat în fundal de cron.
        $ttl = (int) apply_filters('magpress_partners_cache_ttl', 12 * HOUR_IN_SECONDS);
        set_transient($key, $posts, max(300, $ttl));
    } else {
        // La eroare (sursă picată) reîncercăm mai des — 30 min.
        set_transient($key, [], 30 * MINUTE_IN_SECONDS);
    }
    return $posts;
}

/**
 * Randează secțiunea Parteneri: 4 carduri, round-robin câte un articol din
 * fiecare sursă (dacă e o singură sursă → ultimele ei 4 articole).
 * Apare DOAR pe homepage (toggle în Customizer). Scoasă de pe articole la cererea userului.
 */
function magpress_render_partners() {
    if (!get_theme_mod('magpress_partners_on_home', 1)) { return ''; }

    $sources = magpress_partner_sources();
    if (!$sources) { return ''; }

    $per_source = [];
    foreach ($sources as $i => $src) { $per_source[$i] = magpress_partner_fetch($src, 4); }

    $cards = [];
    $seen  = [];
    for ($round = 0; $round < 4 && count($cards) < 4; $round++) {
        foreach ($per_source as $list) {
            if (count($cards) >= 4) break;
            if (empty($list[$round])) continue;
            $p = $list[$round];
            if (isset($seen[$p['link']])) continue;
            $seen[$p['link']] = 1;
            $cards[] = $p;
        }
    }
    if (!$cards) { return ''; }

    $title = get_theme_mod('magpress_partners_title', 'Parteneri');
    ob_start(); ?>
    <section class="partners-row">
      <div class="section-header">
        <span class="section-header__icon" aria-hidden="true"></span>
        <h2 class="section-header__title"><?php echo esc_html($title); ?></h2>
      </div>
      <div class="partners-grid">
        <?php foreach ($cards as $c): ?>
          <a class="partner-card" href="<?php echo esc_url($c['link']); ?>" target="_blank" rel="noopener">
            <?php if ($c['img']): ?>
              <img class="partner-card__img" src="<?php echo esc_url($c['img']); ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="partner-card__host"><?php echo esc_html($c['host']); ?></span>
            <h3 class="partner-card__title"><?php echo esc_html($c['title']); ?></h3>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

// Încălzește cache-ul feed-urilor pe cron-ul de 5 minute (după check-ul de update).
add_action('magpress_auto_deploy_check', function () {
    foreach (magpress_partner_sources() as $src) { magpress_partner_fetch($src, 4); }
}, 20);

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
         . '.site-header--centered .site-header__top{display:flex!important;justify-content:center!important;align-items:center!important;padding:2rem clamp(1rem,3vw,2rem) 1.25rem!important;text-align:center!important}'
         . '.site-header--centered .site-header__top .site-logo{max-height:110px!important;width:auto!important;display:block!important;margin:0 auto}'
         . '.site-nav-bar{background:#fff;border-top:1px solid #e6e6e6;border-bottom:1px solid #e6e6e6}'
         // Meniu centered: aliniat cu .container (aceeași lățime ca meniul/conținutul). !important ca
         // să reziste la CSS combinat/UCSS stale de la LiteSpeed (regula veche nu avea !important).
         . '.site-nav-bar__inner{padding:.4rem clamp(1rem,3vw,2rem)!important}'
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
         // Checkbox căutare fix în viewport → deschiderea modalului NU derulează pagina la footer.
         // !important + în critical ca să bată CSS-ul combinat/UCSS stale de la LiteSpeed (admin logat).
         . '.search-modal-checkbox{position:fixed!important;top:0!important;left:0!important}'
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
 * Sloturi homepage configurate in Customizer (30 sloturi).
 * Fiecare slot poate fi: 'category' (rand articole) sau 'ad' (HTML/AdSense).
 * Returns: array de ['type' => 'category'|'ad', ...payload...]
 */
function magpress_homepage_slots() {
    // Static memo — apelată o singură dată per request
    static $cached_slots = null;
    if ($cached_slots !== null) return $cached_slots;

    $slots = [];
    for ($i = 1; $i <= 30; $i++) {
        // IMPORTANT: default identic cu cel din customize_register, altfel WP returneaza 'none'
        // pentru sloturile pe care user-ul le-a lasat pe default (nu-l salveaza in DB).
        $default_type = $i <= 4 ? 'category' : 'none';
        $type = get_theme_mod("magpress_slot_{$i}_type", $default_type);
        if ($type === 'category') {
            // _cat poate fi CSV de ID-uri (multi) sau un singur int (legacy).
            $raw = get_theme_mod("magpress_slot_{$i}_cat", '');
            $cat_ids = [];
            foreach (preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                $id = (int) $part;
                if ($id > 0) $cat_ids[] = $id;
            }
            $cat_ids = array_values(array_unique($cat_ids));
            if (empty($cat_ids)) continue;
            // Verifică prima categorie validă pentru titlu/URL secțiune
            $primary_cat = null;
            foreach ($cat_ids as $cid) {
                $c = get_category($cid);
                if ($c && !is_wp_error($c)) { $primary_cat = $c; break; }
            }
            if (!$primary_cat) continue;
            $count = (int) get_theme_mod("magpress_slot_{$i}_count", 8);
            $layout = get_theme_mod("magpress_slot_{$i}_layout", 'grid');
            if (!in_array($layout, ['grid', 'asymmetric', 'hero', 'list'], true)) $layout = 'grid';
            // Layout-urile fixe forteaza un numar de articole specific
            $forced = ['asymmetric' => 5, 'hero' => 4, 'list' => 5];
            if (isset($forced[$layout])) $count = $forced[$layout];
            $slots[] = ['type' => 'category', 'cat' => $primary_cat, 'cat_ids' => $cat_ids, 'count' => max(2, min(20, $count)), 'layout' => $layout];
        } elseif ($type === 'ad') {
            $html = trim((string) get_theme_mod("magpress_slot_{$i}_ad", ''));
            if ($html === '') continue;
            $title    = trim((string) get_theme_mod("magpress_slot_{$i}_html_title", ''));
            $subtitle = trim((string) get_theme_mod("magpress_slot_{$i}_html_subtitle", ''));
            $slots[] = ['type' => 'ad', 'html' => $html, 'title' => $title, 'subtitle' => $subtitle];
        }
    }
    if ($slots) return ($cached_slots = $slots);

    // Fallback: setari vechi (slot 1-6 din versiunea anterioara)
    for ($i = 1; $i <= 6; $i++) {
        $cat_id = (int) get_theme_mod("magpress_homepage_cat_{$i}", 0);
        if ($cat_id <= 0) continue;
        $cat = get_category($cat_id);
        if (!$cat || is_wp_error($cat)) continue;
        $count = (int) get_theme_mod("magpress_homepage_count_{$i}", 8);
        $slots[] = ['type' => 'category', 'cat' => $cat, 'cat_ids' => [$cat_id], 'count' => max(2, min(20, $count))];
    }
    if ($slots) return ($cached_slots = $slots);

    // Fallback: site-config.json
    $cfg = magpress_site_config();
    if (!empty($cfg['homepage_categories']) && is_array($cfg['homepage_categories'])) {
        foreach ($cfg['homepage_categories'] as $slug) {
            $c = get_category_by_slug($slug);
            if ($c) $slots[] = ['type' => 'category', 'cat' => $c, 'cat_ids' => [(int) $c->term_id], 'count' => 8];
        }
        if ($slots) return ($cached_slots = $slots);
    }

    // Fallback final: top 4 categorii
    $top = get_categories(['orderby' => 'count', 'order' => 'DESC', 'number' => 4, 'hide_empty' => true]);
    foreach ($top as $c) $slots[] = ['type' => 'category', 'cat' => $c, 'cat_ids' => [(int) $c->term_id], 'count' => 8];
    return ($cached_slots = $slots);
}

/**
 * Injecteaza reclama in mijlocul articolului — dupa al 3-lea paragraf <p>.
 * Aplicat doar pe single post.
 */
function magpress_inject_mid_article_ad($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
    $ad  = trim((string) get_theme_mod('magpress_article_ad_mid', ''));
    $ad2 = trim((string) get_theme_mod('magpress_article_ad_mid2', ''));
    if ($ad === '' && $ad2 === '') return $content;

    $parts = explode('</p>', $content);
    $n = count($parts);
    if ($n < 5) return $content; // <4 paragrafe — nicio reclamă

    // Poziția 1: după paragraful 3. Poziția 2: pe la 60%, doar pe articole lungi.
    // Map poziție→HTML (fiecare cu contextul lui). A doua reclamă e un COD SEPARAT
    // (widget-id diferit) dacă e configurată; altfel, pentru non-MGID, dublăm primul.
    $insertions = [];
    if ($ad !== '') { $insertions[3] = magpress_render_ad_html($ad, 'mid-article'); }

    if ($n >= 10) {
        $second = (int) floor($n * 0.6);
        if ($second >= 6 && $second <= $n - 2 && !isset($insertions[$second])) {
            if ($ad2 !== '') {
                // A doua reclamă distinctă (recomandat pt MGID/AdsKeeper — widget-id propriu).
                $insertions[$second] = magpress_render_ad_html($ad2, 'mid-article-2');
            } elseif ($ad !== '') {
                // Fără a doua reclamă configurată: dublăm primul DOAR dacă NU e MGID/AdsKeeper
                // (acelea nu se umplu de două ori cu același widget-id).
                $is_mgid = (bool) preg_match('/_mgwidget|_mgq\b|adskeeper|mgid\.com|jsc\.mgid/i', $ad);
                if (!$is_mgid) { $insertions[$second] = magpress_render_ad_html($ad, 'mid-article'); }
            }
        }
    }

    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $out .= $parts[$i];
        if ($i < $n - 1) {
            $out .= '</p>';
            if (isset($insertions[$i + 1])) { $out .= $insertions[$i + 1]; }
        }
    }
    return $out;
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
 * Reclamă în capul paginilor de categorie/tag (după descriere, înainte de articole).
 * Refolosește codul AdSense din slotul mid-article (zero config suplimentar per site).
 */
function magpress_get_category_top_ad() {
    if (!is_category() && !is_tag()) return '';
    $ad = trim((string) get_theme_mod('magpress_article_ad_mid', ''));
    if ($ad === '') return '';
    return magpress_render_ad_html($ad, 'category-top');
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
        // E_STRICT eliminat: constanta e deprecată în PHP 8.4+ și simpla ei referire
        // (chiar ca cheie de array) spama log-ul cu „Constant E_STRICT is deprecated".
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
    // SKIP pe producție când WP_DEBUG e off — error handler-ul adaugă overhead pe FIECARE request.
    // Pe producție WordPress nu emite warning-uri vizibile oricum.
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    // E_STRICT deprecated în PHP 8.4+ — folosim E_ALL direct (E_STRICT oricum nu mai e folosit)
    set_error_handler('magpress_error_handler', E_ALL);
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

    // MGID native widgets: integrarea lor oficială cere ca div-ul (data-type="_mgwidget")
    // + scriptul (_mgq.push(["_mgc.load"])) să fie în HTML-ul INIȚIAL — loader-ul MGID din
    // <head> procesează coada _mgq la load. Dacă le injectăm lazy (la scroll), ne bazăm pe un
    // re-scan al loader-ului care nu e garantat și widget-ul rămâne gol. Deci le emitem RAW,
    // indiferent de setarea de lazy-loading. (Scriptul e definit de administrator — sigur.)
    if (preg_match('/_mgwidget|_mgq\b|jsc\.mgid|servicer\.mgid|mgid\.com/i', $html)) {
        return '<div class="ad-slot ad-slot--mgid ad-slot--' . esc_attr($context) . '">' . $html . '</div>';
    }

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
        for ($i = 1; $i <= 30; $i++) {
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

/**
 * Fix LiteSpeed: feature-ul "Localize Resources" (optm-localize) servește scripturi
 * externe de pe domeniul propriu (/wp-content/litespeed/localres/...). Pentru
 * adsbygoogle.js, Google Analytics și GTM asta STRICĂ funcționarea (AdSense nu mai
 * servește reclame, tracking-ul se rupe), pentru că Google verifică originea reală.
 * Pe tema asta (fără biblioteci CDN externe) localizarea nu aduce niciun beneficiu,
 * deci o dezactivăm la runtime prin acțiunea oficială LiteSpeed.
 */
function magpress_litespeed_disable_localize() {
    if (!defined('LSCWP_V')) return; // LiteSpeed Cache nu e activ
    do_action('litespeed_conf_force', 'optm-localize', false);
}
add_action('litespeed_init', 'magpress_litespeed_disable_localize');
add_action('init', 'magpress_litespeed_disable_localize', 1);

/**
 * Exclude logo-ul din LiteSpeed lazy load — logo-ul e LCP, trebuie încărcat eager.
 * Filtrul `litespeed_media_lazy_img_excludes` primește array de string-uri care, dacă
 * apar în tag-ul <img>, sar peste lazy. „site-logo" e clasa pusă de header.php.
 */
add_filter('litespeed_media_lazy_img_excludes', function($excludes) {
    if (!is_array($excludes)) $excludes = [];
    $excludes[] = 'site-logo';
    return $excludes;
});

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

  // Injectează HTML custom + execută scripturile. Suportă și scripturi care
  // afișează bannerul prin document.write() (ex: profitshare.ro/ai), care altfel
  // nu se randează când scriptul e încărcat lazy (browserul ignoră document.write
  // după ce pagina s-a parsat). Capturăm output-ul și-l mutăm în slot.
  function executeScripts(container, html){
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    // Mută TOATE nodurile copil în container, păstrând structura (div-urile wrapper rămân).
    // Script-urile sunt inerte după innerHTML — le re-creăm IN PLACE mai jos.
    while (tmp.firstChild) container.appendChild(tmp.firstChild);

    // Detectează TOATE script-urile (inclusiv nested în div-uri wrapper — ex: Scoreaxis,
    // unde <script> e copil al <div id="scoreaxis-widget-X">; document.currentScript.parentNode
    // trebuie să fie wrapperul, NU ad-slot-ul direct).
    var scripts = Array.prototype.slice.call(container.querySelectorAll('script:not([data-magpress-executed])'));

    function runNext(i){
      if (i >= scripts.length) return;
      var oldS = scripts[i];
      var newS = document.createElement('script');
      Array.prototype.slice.call(oldS.attributes).forEach(function(a){ newS.setAttribute(a.name, a.value); });
      newS.textContent = oldS.textContent;
      newS.setAttribute('data-magpress-executed', '1');

      var parent = oldS.parentNode || container;

      // Shim document.write: inserăm SINCRON output-ul în parent-ul scriptului
      // (ex: profitshare face document.write(<div id=...>) și imediat după
      // document.getElementById(id) — dacă nu inserăm pe loc, găsesc null).
      var origWrite = document.write, origWriteln = document.writeln;
      document.write = document.writeln = function(s){
        var holder = document.createElement('div');
        holder.innerHTML = s;
        while (holder.firstChild) parent.appendChild(holder.firstChild);
      };
      function finish(){
        document.write = origWrite;
        document.writeln = origWriteln;
        runNext(i + 1);
      }

      if (newS.src){
        newS.onload = finish;
        newS.onerror = finish;
        parent.replaceChild(newS, oldS);
      } else {
        parent.replaceChild(newS, oldS);
        finish();
      }
    }
    runNext(0);
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

  // Reclamele cu dimensiune fixă (ex: profitshare 1200x628, 970x250) pot fi mai
  // late decât slotul (max ~1000px) și s-ar tăia. Le scalăm proporțional cu
  // transform:scale ca să încapă fără crop și fără distorsiune (rămân clare).
  function fitFixedAd(slot){
    var target = slot.querySelector('[data-magpress-fit]');
    var natW, natH;
    if (target){
      natW = parseInt(slot.getAttribute('data-ad-w'), 10);
      natH = parseInt(slot.getAttribute('data-ad-h'), 10);
    } else {
      target = slot.querySelector('div[id^="profitshare"]') || slot.querySelector('iframe[width]');
      if (!target) return false;
      natW = parseInt(target.style.width, 10) || parseInt(target.getAttribute('width'), 10) || 0;
      natH = parseInt(target.style.height, 10) || parseInt(target.getAttribute('height'), 10) || 0;
      if (!natW || !natH) return false;
      target.setAttribute('data-magpress-fit', '1');
      slot.setAttribute('data-ad-w', natW);
      slot.setAttribute('data-ad-h', natH);
    }
    // Reset ca să măsurăm spațiul real disponibil
    slot.style.width = ''; slot.style.height = ''; target.style.transform = '';
    var avail = slot.clientWidth || (slot.parentNode && slot.parentNode.clientWidth) || natW;
    var scale = Math.min(1, avail / natW);
    target.style.display = 'block';
    target.style.width = natW + 'px';
    target.style.height = natH + 'px';
    target.style.transformOrigin = 'top left';
    target.style.transform = 'scale(' + scale + ')';
    slot.style.display = 'block';
    slot.style.overflow = 'hidden';
    slot.style.maxWidth = '100%';
    slot.style.minHeight = '0';
    slot.style.width = Math.round(natW * scale) + 'px';
    slot.style.height = Math.round(natH * scale) + 'px';
    slot.style.marginLeft = 'auto';
    slot.style.marginRight = 'auto';
    return true;
  }

  function activateFullSlot(slot){
    // Slot fără <ins> (banner custom HTML/img) — inject totul
    var html = slot.dataset.adHtml || '';
    var ph = slot.querySelector('.ad-slot__placeholder');
    if (ph) ph.remove();
    executeScripts(slot, html);
    slot.classList.remove('ad-slot--lazy-full');
    slot.classList.add('ad-slot--loaded');
    fitFixedAd(slot);
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

  // Re-scalează bannerele cu dimensiune fixă când se schimbă lățimea ferestrei.
  var resizeT;
  window.addEventListener('resize', function(){
    clearTimeout(resizeT);
    resizeT = setTimeout(function(){
      document.querySelectorAll('.ad-slot[data-ad-w]').forEach(function(s){ fitFixedAd(s); });
    }, 200);
  });
})();
</script>
    <?php
}
add_action('wp_footer', 'magpress_adsense_lazy_script', 5);

/**
 * Lightbox pentru imagini din articol (.entry-content) — auto-detectează imaginile
 * cu link la full size (wp-block-image cu <a>, gallery, wp-block-gallery) și deschide
 * overlay cu navigare prev/next, ESC, click outside, swipe pe mobil.
 * Doar pe single post/page (nu pe homepage/archive).
 */
function magpress_lightbox_script() {
    if (!is_singular()) return;
    ?>
<script id="magpress-lightbox">
(function(){
  var content = document.querySelector('.entry-content');
  if (!content) return;

  // Verifică dacă un src e un placeholder lazy load (NU URL real de imagine).
  // LiteSpeed/lazy-loaders folosesc data URIs (data:image/svg+xml;base64,...) ca placeholder.
  function isPlaceholder(src){
    if (!src) return true;
    if (/^data:/.test(src)) return true;
    if (/lazy-?placeholder|blank\.gif|1x1|spacer\.gif/i.test(src)) return true;
    return false;
  }

  // Alege cea mai mare imagine din srcset (returnează URL-ul).
  function largestFromSrcset(srcset){
    if (!srcset) return '';
    var best = '', maxW = 0;
    srcset.split(',').forEach(function(s){
      var parts = s.trim().split(/\s+/);
      var w = parseInt(parts[1], 10) || 0;
      if (w > maxW){ maxW = w; best = parts[0]; }
    });
    return best;
  }

  // Detectează URL-ul full pentru o imagine, EVITÂND lazy load placeholders.
  // Prioritate: <a> părinte cu link media → srcset largest → data-srcset (LiteSpeed) → data-src → currentSrc/src.
  function getFullSrc(img){
    // 1. Link părinte la fișier media
    var a = img.closest('a');
    if (a && a.href && /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i.test(a.href)) return a.href;

    // 2. srcset normal (cea mai mare)
    var fromSrcset = largestFromSrcset(img.srcset || img.getAttribute('srcset'));
    if (fromSrcset && !isPlaceholder(fromSrcset)) return fromSrcset;

    // 3. data-srcset (LiteSpeed/lazy plugin-uri stochează aici originalul)
    var fromDataSrcset = largestFromSrcset(img.getAttribute('data-srcset'));
    if (fromDataSrcset) return fromDataSrcset;

    // 4. data-src (LiteSpeed original)
    var dataSrc = img.getAttribute('data-src');
    if (dataSrc && !isPlaceholder(dataSrc)) return dataSrc;

    // 5. data-lazy-src (alte lazy loaders)
    var lazySrc = img.getAttribute('data-lazy-src');
    if (lazySrc && !isPlaceholder(lazySrc)) return lazySrc;

    // 6. Fallback final — currentSrc/src (poate fi placeholder dar e tot ce avem)
    var cur = img.currentSrc || img.src || '';
    return cur;
  }

  // Colectează toate imaginile linkabile din articol: galerie + wp-block-image cu <a> la full size
  function collectImages(){
    var imgs = [];
    var nodes = content.querySelectorAll(
      '.wp-block-gallery img, .gallery img, .wp-block-image a > img, .entry-content figure a > img'
    );
    nodes.forEach(function(img){
      var full = getFullSrc(img);
      var caption = '';
      var fig = img.closest('figure');
      if (fig){
        var cap = fig.querySelector('figcaption');
        if (cap) caption = cap.textContent.trim();
      }
      imgs.push({ el: img, full: full, alt: img.alt || '', caption: caption });
    });
    return imgs;
  }

  var items = collectImages();
  if (!items.length) return;

  // Construiește overlay-ul o singură dată
  var lb = document.createElement('div');
  lb.className = 'magpress-lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.innerHTML = ''
    + '<button class="magpress-lightbox__btn magpress-lightbox__close" aria-label="Închide"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M6 18L18 6"/></svg></button>'
    + '<button class="magpress-lightbox__btn magpress-lightbox__prev" aria-label="Anterior"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button>'
    + '<button class="magpress-lightbox__btn magpress-lightbox__next" aria-label="Următor"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>'
    + '<div class="magpress-lightbox__counter" aria-live="polite"></div>'
    + '<img class="magpress-lightbox__img" alt="">'
    + '<div class="magpress-lightbox__caption" aria-live="polite" style="display:none"></div>';
  document.body.appendChild(lb);

  var lbImg = lb.querySelector('.magpress-lightbox__img');
  var lbCounter = lb.querySelector('.magpress-lightbox__counter');
  var lbCaption = lb.querySelector('.magpress-lightbox__caption');
  var lbPrev = lb.querySelector('.magpress-lightbox__prev');
  var lbNext = lb.querySelector('.magpress-lightbox__next');
  var currentIdx = 0;

  function show(idx){
    if (idx < 0) idx = items.length - 1;
    if (idx >= items.length) idx = 0;
    currentIdx = idx;
    var it = items[idx];
    // Re-derivă URL-ul la fiecare show — dacă LiteSpeed a încărcat imaginea între timp,
    // src/srcset sunt acum URL-uri reale (nu placeholder data:URI).
    var freshFull = getFullSrc(it.el);
    if (freshFull) it.full = freshFull;
    lbImg.src = it.full;
    lbImg.alt = it.alt;
    lbCounter.textContent = (idx + 1) + ' / ' + items.length;
    if (it.caption){
      lbCaption.textContent = it.caption;
      lbCaption.style.display = '';
    } else {
      lbCaption.style.display = 'none';
    }
    // Hide prev/next dacă e o singură imagine
    var multi = items.length > 1;
    lbPrev.style.display = multi ? '' : 'none';
    lbNext.style.display = multi ? '' : 'none';
  }

  function open(idx){
    show(idx);
    lb.classList.add('is-open');
    document.body.classList.add('magpress-lightbox-open');
  }
  function close(){
    lb.classList.remove('is-open');
    document.body.classList.remove('magpress-lightbox-open');
    lbImg.src = '';
  }

  // Bind click pe imagini
  items.forEach(function(it, idx){
    it.el.style.cursor = 'zoom-in';
    // Dacă imaginea e în <a>, prevent default și deschide lightbox-ul
    var a = it.el.closest('a');
    var target = a || it.el;
    target.addEventListener('click', function(e){
      e.preventDefault();
      open(idx);
    });
  });

  lb.querySelector('.magpress-lightbox__close').addEventListener('click', close);
  lbPrev.addEventListener('click', function(e){ e.stopPropagation(); show(currentIdx - 1); });
  lbNext.addEventListener('click', function(e){ e.stopPropagation(); show(currentIdx + 1); });
  // Click pe fundal (nu pe imagine/buton) → close
  lb.addEventListener('click', function(e){
    if (e.target === lb) close();
  });
  // Keyboard
  document.addEventListener('keydown', function(e){
    if (!lb.classList.contains('is-open')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(currentIdx - 1);
    else if (e.key === 'ArrowRight') show(currentIdx + 1);
  });
  // Swipe pe mobil
  var touchX = 0;
  lb.addEventListener('touchstart', function(e){ touchX = e.touches[0].clientX; }, {passive:true});
  lb.addEventListener('touchend', function(e){
    var dx = (e.changedTouches[0].clientX - touchX);
    if (Math.abs(dx) > 50){
      show(dx > 0 ? currentIdx - 1 : currentIdx + 1);
    }
  }, {passive:true});
})();
</script>
    <?php
}
add_action('wp_footer', 'magpress_lightbox_script', 6);

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
// DEZACTIVAT: site-urile NU mai verifică update prin WordPress — deploy-ul vine exclusiv prin
// push.ps1. Astfel WP nu mai detectează o versiune nouă și nu mai declanșează auto-instalarea
// (care intră în maintenance mode 503 → heartbeat-ul pică → „Conexiune pierdută").
// add_filter('pre_set_site_transient_update_themes', 'magpress_check_for_update');

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
// DEZACTIVAT: deploy-ul se face EXCLUSIV prin push.ps1 (REST /v1/deploy), care înlocuiește
// fișierele direct, fără maintenance mode. Nu mai forțăm auto-update-ul WP — el intra în
// maintenance mode (503) la fiecare instalare și rupea heartbeat-ul wp-admin.
// add_filter('auto_update_theme', 'magpress_enable_auto_update', 10, 2);

/**
 * ============================================================
 * AUTO-DEPLOY prin CRON (self-update) — fără maintenance mode
 * ============================================================
 * Restaurează comportamentul „push pe GitHub → site-ul se actualizează singur",
 * dar FĂRĂ bug-ul „Conexiune pierdută": nu folosim upgrader-ul WordPress (care
 * intră în maintenance mode 503 și rupe heartbeat-ul), ci apelăm DIRECT
 * magpress_perform_deploy() — aceeași funcție folosită de push.ps1, care
 * înlocuiește fișierele în loc, fără maintenance mode.
 *
 * Fluxul:
 *   1. Un cron la 5 minute citește .update-info.json de pe GitHub (cache-buster).
 *   2. Dacă versiunea de pe GitHub > versiunea instalată → rulează deploy-ul safe.
 *   3. BONUS instant: cât timp un admin e logat în wp-admin, la fiecare pagină
 *      încărcată (throttle 60s) se declanșează aceeași verificare în FUNDAL —
 *      site-ul la care lucrezi prinde update-ul în secunde, fără click.
 *   4. push.ps1 rămâne funcțional în paralel (deploy manual instant), ca fallback.
 *
 * Se poate dezactiva fără edit de cod: add_filter('magpress_auto_deploy_enabled', '__return_false');
 * sau definind constanta MAGPRESS_AUTO_DEPLOY_DISABLED = true în wp-config.php.
 */
function magpress_auto_deploy_enabled() {
    if (defined('MAGPRESS_AUTO_DEPLOY_DISABLED') && MAGPRESS_AUTO_DEPLOY_DISABLED) {
        return false;
    }
    return (bool) apply_filters('magpress_auto_deploy_enabled', true);
}

// Interval custom de 5 minute pentru verificarea de update.
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['magpress_5min'])) {
        $schedules['magpress_5min'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'La 5 minute (Magpress auto-deploy)',
        ];
    }
    return $schedules;
});

// Programează cron-ul la 5 minute (re-schedule idempotent, ca la ping).
function magpress_schedule_auto_deploy() {
    if (!magpress_auto_deploy_enabled()) {
        if (wp_next_scheduled('magpress_auto_deploy_check')) {
            wp_clear_scheduled_hook('magpress_auto_deploy_check');
        }
        return;
    }
    // Re-programează dacă intervalul s-a schimbat (ex: vechiul 'hourly' → 'magpress_5min').
    $ev = wp_get_scheduled_event('magpress_auto_deploy_check');
    if ($ev && $ev->schedule !== 'magpress_5min') {
        wp_clear_scheduled_hook('magpress_auto_deploy_check');
        $ev = false;
    }
    if (!$ev) {
        wp_schedule_event(time() + 60, 'magpress_5min', 'magpress_auto_deploy_check');
    }
}
add_action('after_switch_theme', 'magpress_schedule_auto_deploy');
add_action('init', 'magpress_schedule_auto_deploy');
add_action('magpress_auto_deploy_check', 'magpress_run_auto_deploy');
// Hook separat pentru trigger-ul instant din admin (hook diferit ca să nu fie
// dedupe-uit de wp_schedule_single_event — WP ignoră single events cu același
// hook la <10 min de un event recurent deja programat).
add_action('magpress_auto_deploy_instant', 'magpress_run_auto_deploy');

// Cleanup la schimbarea temei.
add_action('switch_theme', function () {
    wp_clear_scheduled_hook('magpress_auto_deploy_check');
    wp_clear_scheduled_hook('magpress_auto_deploy_instant');
});

/**
 * Trigger INSTANT: cât timp lucrezi în wp-admin, fiecare încărcare de pagină
 * (max 1/minut) pornește verificarea de update în FUNDAL — prin cron spawn,
 * ca să nu blocheze pagina de admin nici măcar o secundă. Efect: după un push
 * pe GitHub, site-ul la care lucrezi se actualizează în secunde, fără click.
 * Pe hosturile cu DISABLE_WP_CRON, event-ul e preluat de cron-ul de sistem.
 */
function magpress_instant_deploy_trigger() {
    if (!magpress_auto_deploy_enabled()) return;
    if (!current_user_can('manage_options')) return;
    if (get_transient('magpress_instant_check_throttle')) return;
    set_transient('magpress_instant_check_throttle', 1, MINUTE_IN_SECONDS);

    wp_schedule_single_event(time() - 1, 'magpress_auto_deploy_instant');
    if (function_exists('spawn_cron')) {
        spawn_cron();
    }
}
add_action('admin_init', 'magpress_instant_deploy_trigger');

/**
 * Notificare discretă în admin după un auto-deploy reușit (afișată o dată).
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $last = get_option('magpress_last_auto_deploy');
    if (!is_array($last) || empty($last['version'])) return;
    delete_option('magpress_last_auto_deploy');
    echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Magpress</strong> s-a actualizat automat la versiunea <strong>' . esc_html($last['version']) . '</strong>.</p></div>';
});

/**
 * Handler cron: verifică GitHub și, dacă e o versiune nouă, rulează deploy-ul safe.
 * Returnează array cu rezultatul (util și pentru trigger manual din admin).
 */
function magpress_run_auto_deploy() {
    if (!magpress_auto_deploy_enabled()) {
        return ['ok' => false, 'skipped' => 'disabled'];
    }

    // Nu ne suprapunem cu un deploy în curs (push.ps1 sau alt cron).
    if (get_transient('magpress_deploy_lock')) {
        return ['ok' => false, 'skipped' => 'deploy_in_progress'];
    }

    // 1. Versiunea instalată acum.
    $installed = (string) wp_get_theme()->get('Version');

    // 2. Versiunea de pe GitHub (cache-buster ca să sărim CDN-ul raw de 5 min).
    $resp = wp_remote_get(MAGPRESS_UPDATE_INFO_URL . '?_=' . time(), [
        'timeout' => 10,
        'headers' => ['User-Agent' => 'magpress-autodeploy/' . get_stylesheet()],
    ]);
    if (is_wp_error($resp) || 200 !== wp_remote_retrieve_response_code($resp)) {
        return ['ok' => false, 'error' => 'update_info_unreachable'];
    }
    $info = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($info) || empty($info['version'])) {
        return ['ok' => false, 'error' => 'update_info_invalid'];
    }
    $remote = (string) $info['version'];

    // 3. Deploy DOAR dacă GitHub e strict mai nou.
    if (version_compare($installed, $remote, '>=')) {
        return ['ok' => true, 'skipped' => 'up_to_date', 'installed' => $installed, 'remote' => $remote];
    }

    // 4. Rulează deploy-ul safe (fără maintenance mode). Lock 120s ca la REST.
    set_transient('magpress_deploy_lock', time(), 120);
    @ignore_user_abort(true);
    @set_time_limit(180);

    $result = magpress_perform_deploy();

    delete_transient('magpress_deploy_lock');
    delete_transient('magpress_update_info');

    // Reținem ultimul auto-deploy reușit → notificare discretă în admin.
    if (!empty($result['ok']) && !empty($result['version'])) {
        update_option('magpress_last_auto_deploy', ['version' => $result['version'], 'time' => time()], false);
    }

    $result['auto_deploy'] = true;
    $result['from']        = $installed;
    $result['to_target']   = $remote;
    return $result;
}

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
                if (magpress_auto_deploy_enabled()) {
                    // Deploy instant, safe (fără maintenance mode) — la fel ca push.ps1, dar cu un click.
                    $deploy = magpress_run_auto_deploy();
                    if (!empty($deploy['ok']) && !empty($deploy['version'])) {
                        $message = '✅ <strong>Actualizat automat la ' . esc_html($deploy['version']) . '</strong> (de la ' . esc_html($current) . '). Deploy fără maintenance mode — fără „Conexiune pierdută".';
                        $message_type = 'success';
                    } elseif (!empty($deploy['skipped']) && $deploy['skipped'] === 'deploy_in_progress') {
                        $message = '⏳ Un deploy e deja în curs. Reîncearcă în ~1 minut.';
                        $message_type = 'warning';
                    } else {
                        $err = $deploy['error'] ?? ($deploy['skipped'] ?? 'necunoscut');
                        $message = '⚠️ Am detectat versiunea ' . esc_html($info['version']) . ', dar deploy-ul automat a eșuat (' . esc_html($err) . '). Poți rula <code>push.ps1</code> ca fallback.';
                        $message_type = 'warning';
                    }
                } else {
                    $message = '✅ <strong>Versiune nouă pe GitHub:</strong> ' . esc_html($info['version']) . ' (instalată: ' . esc_html($current) . '). Auto-deploy e dezactivat — rulează <code>push.ps1</code> ca să se propage pe site-uri.';
                    $message_type = 'success';
                }
            } else {
                $message = '✓ Tema e la zi. Versiune curentă: <strong>' . esc_html($current) . '</strong> (GitHub are tot ' . esc_html($info['version']) . ').';
                $message_type = 'success';
            }
        } else {
            $message = '⚠️ Nu am putut contacta GitHub. Reîncearcă în câteva minute.';
            $message_type = 'warning';
        }
    }

    // Handle site type save
    if (isset($_POST['magpress_save_site_type']) && check_admin_referer('magpress_site_type_action')) {
        $type = sanitize_text_field($_POST['magpress_site_type'] ?? 'auto');
        if (in_array($type, ['auto', 'news', 'magazine', 'blog', 'business'], true)) {
            update_option('magpress_site_type', $type);
            $force = !empty($_POST['magpress_force_schema']) ? 1 : 0;
            update_option('magpress_force_schema', $force);
            $message = '✓ Setările schema au fost salvate.';
            $message_type = 'success';
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
                    <td>
                        <?php if (magpress_auto_deploy_enabled()):
                            $next = wp_next_scheduled('magpress_auto_deploy_check');
                            $next_txt = $next ? human_time_diff(time(), $next) : 'la următorul trafic';
                        ?>
                            ✓ <strong>Activat</strong> — verificare la 5 minute + instant cât timp ești în wp-admin. Deploy fără maintenance mode (fără „Conexiune pierdută"). Următoarea verificare programată: <?php echo esc_html($next_txt); ?>.
                        <?php else: ?>
                            ✗ Dezactivat. Deploy-ul se face manual prin <code>push.ps1</code>.
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Repo</th>
                    <td><a href="https://github.com/kreatorteam-collab/magpress" target="_blank">github.com/kreatorteam-collab/magpress</a></td>
                </tr>
            </table>

            <h2>Verifică update manual</h2>
            <p>Update-urile se aplică singure: instant cât timp ești în wp-admin, în maxim 5 minute în rest. Butonul e doar pentru confirmare imediată — verifică GitHub și, dacă e o versiune mai nouă, o instalează pe loc:</p>

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

            <h2 style="margin-top:2rem">Tip site (pentru schema.org JSON-LD homepage)</h2>
            <?php $current_site_type = get_option('magpress_site_type', 'auto'); ?>
            <p>Alege tipul de site — schema.org JSON-LD pe homepage va folosi tipul corespunzător. Influențează eligibilitatea pentru Google News, Discover, Knowledge Graph și rich results.</p>
            <form method="post">
                <?php wp_nonce_field('magpress_site_type_action'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="magpress_site_type">Tip site</label></th>
                        <td>
                            <select name="magpress_site_type" id="magpress_site_type" style="min-width:380px">
                                <option value="auto"     <?php selected('auto', $current_site_type); ?>>Auto-detect din domain (default)</option>
                                <option value="news"     <?php selected('news', $current_site_type); ?>>Site de știri / publicație news → <strong>NewsMediaOrganization</strong></option>
                                <option value="magazine" <?php selected('magazine', $current_site_type); ?>>Revistă / Magazine lifestyle → <strong>Organization</strong></option>
                                <option value="blog"     <?php selected('blog', $current_site_type); ?>>Blog (personal / specialist) → <strong>Organization</strong></option>
                                <option value="business" <?php selected('business', $current_site_type); ?>>Site business / servicii → <strong>OnlineBusiness</strong></option>
                            </select>
                            <p class="description">
                                <strong>News</strong> = pentru știri actuale, comunicate, evenimente curente. Eligibil pentru Google News + boost Discover.<br>
                                <strong>Magazine/Lifestyle</strong> = pentru reviste, sfaturi, ghiduri (casa, modă, sănătate, parenting).<br>
                                <strong>Blog</strong> = pentru content personal/specialist (specific autor).<br>
                                <strong>Business</strong> = pentru site de companie cu servicii / produse.
                            </p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="magpress_save_site_type" class="button button-primary">💾 Salvează tipul site-ului</button>
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
    register_rest_route('magpress/v1', '/diag', [
        'methods'             => 'GET',
        'callback'            => 'magpress_rest_diag',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('magpress/v1', '/errorlog', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_errorlog',
        'permission_callback' => '__return_true', // validăm HMAC manual (deploy_secret per-site)
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
    // Instalare/upgrade plugin fleet-wide — broadcast semnat HMAC (push-plugin.ps1).
    register_rest_route('magpress/v1', '/install-plugin', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_install_plugin',
        'permission_callback' => '__return_true', // validăm HMAC manual (deploy_secret per-site)
    ]);
});

/**
 * Diagnostic imagini — ce editor alege WP pentru JPEG, ce extensii există, memorie, opcache.
 * Gate slab cu ping secret (info puțin sensibilă). Folosit la debugging fleet multi-site.
 */
function magpress_rest_diag($req) {
    if ((string) $req->get_param('key') !== MAGPRESS_PING_SECRET) {
        return new WP_REST_Response(['error' => 'forbidden'], 403);
    }
    $args = ['mime_type' => 'image/jpeg'];
    $chosen = function_exists('_wp_image_editor_choose') ? _wp_image_editor_choose($args) : 'unknown';

    // Test REAL de procesare: generăm o imagine de mărime normală de articol (1600x1200)
    // și o redimensionăm cu editorul ales. Dacă pică aici, editorul chiar nu poate procesa
    // pe acest host (policy.xml restrictiv / delegate lipsă) — nu e concurență.
    $img_test = 'skip';
    $tmp = tempnam(sys_get_temp_dir(), 'mptest');
    try {
        if (strpos((string) $chosen, 'Imagick') !== false && class_exists('Imagick')) {
            $im = new Imagick();
            $im->newImage(1600, 1200, new ImagickPixel('white'));
            $im->setImageFormat('jpeg');
            $im->resizeImage(600, 400, Imagick::FILTER_LANCZOS, 1);
            $im->writeImage($tmp);
            $im->clear();
            $img_test = 'imagick_ok(' . (int) @filesize($tmp) . 'b)';
        } elseif (function_exists('imagecreatetruecolor')) {
            $src = imagecreatetruecolor(1600, 1200);
            $dst = imagecreatetruecolor(600, 400);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, 600, 400, 1600, 1200);
            imagejpeg($dst, $tmp);
            imagedestroy($src);
            imagedestroy($dst);
            $img_test = 'gd_ok(' . (int) @filesize($tmp) . 'b)';
        }
    } catch (\Throwable $e) {
        $img_test = 'FAIL: ' . substr($e->getMessage(), 0, 120);
    }
    @unlink($tmp);

    // Diagnostic cache: WP_CACHE + drop-in-uri + object cache (read-only).
    $adv = WP_CONTENT_DIR . '/advanced-cache.php';
    $adv_hint = '';
    if (@file_exists($adv)) {
        $adv_hint = trim(preg_replace('/\s+/', ' ', (string) @file_get_contents($adv, false, null, 0, 200)));
    }

    return [
        'theme_version'   => wp_get_theme()->get('Version'),
        'active_editor'   => $chosen ?: 'none',
        'imagick_loaded'  => extension_loaded('imagick'),
        'imagick_wp_test' => class_exists('WP_Image_Editor_Imagick') ? WP_Image_Editor_Imagick::test($args) : false,
        'gd_loaded'       => extension_loaded('gd'),
        'gd_wp_test'      => class_exists('WP_Image_Editor_GD') ? WP_Image_Editor_GD::test($args) : false,
        'memory_limit'    => ini_get('memory_limit'),
        'big_image_thr'   => (int) apply_filters('big_image_size_threshold', 2560),
        'opcache_reset'   => function_exists('opcache_reset'),
        'session_auto_start' => (int) ini_get('session.auto_start'),
        'session_status'  => function_exists('session_status') ? session_status() : -1, // 0=off,1=none,2=ACTIVA(lock)
        'session_save_path' => ini_get('session.save_path'),
        'img_test'        => $img_test,
        'cache' => [
            'WP_CACHE'           => defined('WP_CACHE') ? (WP_CACHE ? 'true' : 'false') : 'undefined',
            'advanced_cache_php' => @file_exists($adv),
            'advanced_cache_hint'=> $adv_hint,
            'object_cache_php'   => @file_exists(WP_CONTENT_DIR . '/object-cache.php'),
            'ext_object_cache'   => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache(),
            'object_cache_impl'  => (isset($GLOBALS['wp_object_cache']) && is_object($GLOBALS['wp_object_cache'])) ? get_class($GLOBALS['wp_object_cache']) : null,
            'redis_ext'          => extension_loaded('redis'),
            'memcached_ext'      => extension_loaded('memcached') || extension_loaded('memcache'),
            'litespeed_plugin'   => defined('LSCWP_V') ? LSCWP_V : false,
        ],
    ];
}

/**
 * Tail al unui fișier — ultimele $lines linii, citind cel mult $max_bytes de la final.
 */
function magpress_tail_file($path, $lines = 400, $max_bytes = 524288) {
    $size = @filesize($path);
    if ($size === false) { return null; }
    if ($size === 0) { return ''; }
    $read = (int) min($size, $max_bytes);
    $fp = @fopen($path, 'rb');
    if (!$fp) { return null; }
    @fseek($fp, -$read, SEEK_END);
    $data = @fread($fp, $read);
    @fclose($fp);
    if ($data === false) { return null; }
    $arr = explode("\n", $data);
    if (count($arr) > $lines) { $arr = array_slice($arr, -$lines); }
    return implode("\n", $arr);
}

/**
 * Citire error_log per-site pentru debugging fleet. POST semnat HMAC cu deploy_secret
 * (același mecanism ca /deploy — secret per-site, NU în repo public). Întoarce un sumar
 * cu semnăturile unice de erori + count; trimite {"raw":true} ca să adaugi coada brută.
 */
function magpress_rest_errorlog(WP_REST_Request $req) {
    $body     = (string) $req->get_body();
    $sig      = (string) $req->get_header('X-Magpress-Signature');
    $expected = hash_hmac('sha256', $body, magpress_get_deploy_secret());
    if ($sig === '' || !hash_equals($expected, $sig)) {
        return new WP_REST_Response(['error' => 'bad_signature'], 403);
    }
    $data  = json_decode($body, true);
    $lines = max(1, min(5000, (int) (is_array($data) && isset($data['lines']) ? $data['lines'] : 400)));
    $raw   = is_array($data) && !empty($data['raw']);
    $clear = is_array($data) && !empty($data['clear']);

    $candidates = [];
    $ini = ini_get('error_log');
    if ($ini) { $candidates[] = $ini; }
    if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG)) { $candidates[] = WP_DEBUG_LOG; }
    $candidates[] = WP_CONTENT_DIR . '/debug.log';
    $candidates[] = ABSPATH . 'error_log';
    $candidates[] = ABSPATH . 'wp-admin/error_log';
    $candidates[] = ABSPATH . 'wp-content/error_log';
    $candidates[] = dirname(ABSPATH) . '/error_log';
    $candidates[] = dirname(dirname(ABSPATH)) . '/error_log';

    // Golire log-uri (fresh start) — trunchiază TOATE fișierele de log găsite, nu doar primul.
    if ($clear) {
        $cleared = [];
        $failed  = [];
        $freed   = 0;
        foreach (array_values(array_unique(array_filter($candidates))) as $c) {
            if (@file_exists($c)) {
                $sz = (int) @filesize($c);
                if (@file_put_contents($c, '') !== false) {
                    $cleared[] = $c;
                    $freed += $sz;
                } else {
                    $failed[] = $c;
                }
            }
        }
        return ['site' => home_url('/'), 'cleared' => $cleared, 'failed' => $failed, 'freed_bytes' => $freed];
    }

    $found = null;
    foreach ($candidates as $c) {
        if ($c && @is_readable($c)) { $found = $c; break; }
    }
    if (!$found) {
        return ['site' => home_url('/'), 'wp' => get_bloginfo('version'), 'php' => PHP_VERSION, 'path' => null, 'checked' => $candidates];
    }

    $tail = magpress_tail_file($found, $lines);

    // Sumar: scoatem [timestamp]-ul din față și numărăm semnăturile unice.
    $counts = [];
    foreach (explode("\n", (string) $tail) as $ln) {
        $ln = trim($ln);
        if ($ln === '') { continue; }
        $norm = preg_replace('/^\[[^\]]+\]\s*/', '', $ln);
        $counts[$norm] = isset($counts[$norm]) ? $counts[$norm] + 1 : 1;
    }
    arsort($counts);
    $summary = [];
    $i = 0;
    foreach ($counts as $msg => $n) {
        $summary[] = ['n' => $n, 'msg' => $msg];
        if (++$i >= 40) { break; }
    }

    $out = [
        'site'    => home_url('/'),
        'wp'      => get_bloginfo('version'),
        'php'     => PHP_VERSION,
        'path'    => $found,
        'size'    => @filesize($found),
        'unique'  => count($counts),
        'summary' => $summary,
    ];
    if ($raw) { $out['tail'] = $tail; }
    return $out;
}

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

/**
 * REST: instalare/upgrade plugin fleet-wide.
 * POST {site}/wp-json/magpress/v1/install-plugin
 * Auth: X-Magpress-Signature = hash_hmac('sha256', body, deploy_secret) — la fel ca /deploy.
 * Body JSON: { slug, zip_b64 (ZIP-ul plugin-ului în base64), activate (bool) }
 * Instalează SAU upgradează (overwrite_package) prin Plugin_Upgrader, opțional activează.
 */
function magpress_rest_install_plugin(WP_REST_Request $request) {
    $body = $request->get_body();
    $sig  = $request->get_header('x_magpress_signature');
    if (!$sig) {
        return new WP_REST_Response(['ok' => false, 'error' => 'missing_signature'], 403);
    }
    if (!hash_equals(hash_hmac('sha256', $body, magpress_get_deploy_secret()), $sig)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 403);
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['zip_b64'])) {
        return new WP_REST_Response(['ok' => false, 'error' => 'no_zip'], 400);
    }
    $activate = !empty($data['activate']);
    $zip = base64_decode((string) $data['zip_b64'], true);
    if ($zip === false || strlen($zip) < 100) {
        return new WP_REST_Response(['ok' => false, 'error' => 'bad_zip'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $tmp = wp_tempnam('mp-plugin.zip');
    if (!$tmp || @file_put_contents($tmp, $zip) === false) {
        return new WP_REST_Response(['ok' => false, 'error' => 'tmp_write_failed'], 500);
    }

    add_filter('filesystem_method', function () { return 'direct'; });
    if (!WP_Filesystem()) {
        @unlink($tmp);
        return new WP_REST_Response(['ok' => false, 'error' => 'filesystem_unavailable'], 500);
    }

    @ignore_user_abort(true);
    @set_time_limit(180);

    $skin     = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $result   = $upgrader->install($tmp, ['overwrite_package' => true]);
    @unlink($tmp);

    if (is_wp_error($result)) {
        return new WP_REST_Response(['ok' => false, 'error' => $result->get_error_message()], 500);
    }
    if (!$result) {
        $errs = $skin->get_errors();
        $msg  = (is_wp_error($errs) && $errs->has_errors()) ? $errs->get_error_message() : 'install_failed';
        return new WP_REST_Response(['ok' => false, 'error' => $msg], 500);
    }

    $plugin_file = $upgrader->plugin_info(); // ex: "internal-link-inserter/internal-link-inserter.php"
    $version = '';
    if ($plugin_file && file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
        $hdr = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
        $version = $hdr['Version'] ?? '';
    }

    $activated = false;
    if ($activate && $plugin_file) {
        $act = activate_plugin($plugin_file);
        $activated = !is_wp_error($act);
    }

    if (function_exists('opcache_reset')) { @opcache_reset(); }

    return new WP_REST_Response([
        'ok'        => true,
        'plugin'    => $plugin_file,
        'version'   => $version,
        'activated' => $activated,
    ], 200);
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

    // 4b. Reset opcache — altfel PHP servește functions.php VECHI din cache pe hosturi cu
    //     opcache.validate_timestamps=0 (codul nou nu se activează până la restart PHP).
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $log_msg('opcache reset');
    }

    // 5. Păstrăm ultimele 3 backup-uri
    $old = glob($backups_dir . '/' . $current_slug . '-bak-*', GLOB_ONLYDIR);
    if ($old && count($old) > 3) {
        sort($old);
        foreach (array_slice($old, 0, count($old) - 3) as $b) {
            _magpress_rrmdir($b);
            $log_msg('Removed old backup: ' . basename($b));
        }
    }

    // 6. Purge LiteSpeed (dacă există). ATENȚIE: litespeed_purge_all golește DOAR page cache-ul;
    //    CSS/JS-ul combinat din /wp-content/litespeed/css/ rămâne stale după un update de temă,
    //    deci modificările de stil nu apar până la un purge CSS/JS manual. Purgem explicit și
    //    cssjs + critical/unique CSS ca deploy-ul să fie vizibil imediat.
    if (defined('LSCWP_V')) {
        do_action('litespeed_purge_all');
        do_action('litespeed_purge_all_cssjs');
        do_action('litespeed_purge_all_ccss');
        do_action('litespeed_purge_all_ucss');
        $log_msg('LiteSpeed purged: page + CSS/JS + CCSS/UCSS');
    } elseif (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
        $log_msg('LiteSpeed cache purged');
    }

    // 6b. Fallback: pe unele instalări (UCSS/QUIC.cloud) hook-urile de purge NU regenerează
    //     fișierele CSS generate — rămân stale și modificările de stil nu apar. Ștergem fizic
    //     directoarele generate; LiteSpeed le recreează la următorul request.
    foreach (['css', 'ucss', 'ccss'] as $ls_sub) {
        $ls_dir = WP_CONTENT_DIR . '/litespeed/' . $ls_sub;
        if (is_dir($ls_dir)) {
            _magpress_rrmdir($ls_dir);
            $log_msg('LiteSpeed dir purged: ' . $ls_sub);
        }
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

    // 8b. Font meniu (stil site de știri, ex. Mediafax) — DOAR navigația, independent de preset.
    $wp_customize->add_setting('magpress_menu_font', [
        'default'           => 'default',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['default', 'oswald', 'roboto-condensed', 'archivo-narrow', 'barlow-condensed', 'bebas', 'anton', 'fjalla'], true) ? $v : 'default';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_menu_font', [
        'label'       => 'Font meniu (stil știri)',
        'description' => 'Schimbă DOAR fontul meniului de navigație (nu titlurile/textul). Ideal pentru site-urile de știri — look tip Mediafax. Lasă „Default" pentru fontul temei.',
        'section'     => 'magpress_news',
        'type'        => 'select',
        'choices'     => [
            'default'          => 'Default (fontul temei)',
            'oswald'           => 'Oswald — condensat bold (stil Mediafax)',
            'roboto-condensed' => 'Roboto Condensed',
            'archivo-narrow'   => 'Archivo Narrow',
            'barlow-condensed' => 'Barlow Condensed',
            'bebas'            => 'Bebas Neue — majuscule display',
            'anton'            => 'Anton — gros, impact',
            'fjalla'           => 'Fjalla One',
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
        'default'           => 'auto',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['auto', 'Organization', 'NewsMediaOrganization', 'OnlineBusiness'], true) ? $v : 'auto';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_publisher_type', [
        'label'       => 'Tip publisher (schema entity)',
        'description' => '<code>Auto-detect</code> (default) = tema decide pe baza domain-ului (NewsMediaOrganization pentru site-uri news cunoscute, Organization pentru rest). <code>Organization</code> = override manual sigur. <code>NewsMediaOrganization</code> = override pentru publicații news.',
        'section'     => 'magpress_schema',
        'type'        => 'select',
        'choices'     => [
            'auto'                  => 'Auto-detect din domain (recomandat)',
            'Organization'          => 'Organization (manual override)',
            'NewsMediaOrganization' => 'NewsMediaOrganization (manual override)',
            'OnlineBusiness'        => 'OnlineBusiness (manual override)',
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

/**
 * FAQPage schema — DOAR dacă articolul are o secțiune reală de Întrebări frecvente.
 * Detectează heading-ul FAQ (h2/h3) + perechile h3 întrebare / paragraf răspuns.
 * Exclude disclaimerele (text italic / „rol pur educativ" / „nu înlocuiesc consultul").
 * Folosește wpautop pe conținutul brut (fără side-effects de la filtre the_content).
 */
function magpress_build_faq_schema($post) {
    if (!$post || empty($post->post_content)) return null;
    $raw = $post->post_content;
    if (stripos($raw, 'frecvente') === false && stripos($raw, 'FAQ') === false) return null;

    $html = wpautop($raw);
    // izolează zona de la heading-ul FAQ încolo
    if (!preg_match('/<h[23][^>]*>[^<]*(?:întrebări\s+frecvente|intrebari\s+frecvente|întrebări\s+și\s+răspunsuri|\bFAQ\b)[^<]*<\/h[23]>(.*)$/isu', $html, $mm)) {
        return null;
    }
    // taie la următorul h2 (sfârșit de secțiune)
    $region = preg_split('/<h2\b/i', $mm[1])[0];

    if (!preg_match_all('/<h[34][^>]*>(.*?)<\/h[34]>(.*?)(?=<h[34]\b|$)/isu', $region, $pairs, PREG_SET_ORDER)) {
        return null;
    }

    $entities = [];
    foreach ($pairs as $pr) {
        $q = trim(html_entity_decode(wp_strip_all_tags($pr[1]), ENT_QUOTES, 'UTF-8'));
        $ans = '';
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/isu', $pr[2], $ps)) {
            foreach ($ps[1] as $pp) {
                if (preg_match('/^\s*<(?:i|em)\b/i', $pp)) continue; // disclaimer italic
                $plain = trim(html_entity_decode(wp_strip_all_tags($pp), ENT_QUOTES, 'UTF-8'));
                if ($plain === '') continue;
                if (preg_match('/(rol pur educativ|nu înlocuie|consult(?:ă|ul)? (?:medic|de specialitate)|Informațiile prezentate)/iu', $plain)) continue;
                $ans .= ($ans !== '' ? ' ' : '') . $plain;
            }
        }
        if (mb_strlen($q) < 5 || mb_strlen($ans) < 15) continue;
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $ans],
        ];
    }
    if (count($entities) < 2) return null; // FAQPage are sens cu ≥2 perechi

    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

function magpress_emit_schema_jsonld() {
    if (!get_theme_mod('magpress_schema_enabled', 1)) return;

    $schemas = [];

    if (is_singular('post')) {
        $article = magpress_build_article_schema();
        if ($article) $schemas[] = $article;
        $bc = magpress_build_breadcrumb_schema_single();
        if ($bc) $schemas[] = $bc;
        $faq = magpress_build_faq_schema(get_post()); // doar dacă articolul are secțiune FAQ reală
        if ($faq) $schemas[] = $faq;
    } elseif (is_category() || is_tag() || is_author() || is_archive()) {
        $bc = magpress_build_breadcrumb_schema_archive();
        if ($bc) $schemas[] = $bc;
    } elseif (is_front_page() || is_home()) {
        if (get_theme_mod('magpress_schema_homepage_extra', 1)) {
            // Magpress emite MEREU schema sa Organization + WebSite (default).
            // Dacă alt plugin emite și el (SEOPress, Rank Math), Google reconciliează entity-uri prin
            // name+url. User-ul ar trebui să dezactiveze schema duplicat din celălalt plugin manual.
            $org = magpress_build_organization_schema();
            if ($org) $schemas[] = $org;
            $ws = magpress_build_website_schema();
            if ($ws) $schemas[] = $ws;
        }
        // ItemList pe homepage — featured posts (Rich Results Test va detecta acest schema)
        $itemlist = magpress_build_homepage_itemlist_schema();
        if ($itemlist) $schemas[] = $itemlist;
    }

    foreach ($schemas as $s) {
        echo '<script type="application/ld+json">' . wp_json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
add_action('wp_head', 'magpress_emit_schema_jsonld', 30);

/**
 * Detectează dacă alt plugin (SEOPress, Rank Math, Yoast, etc.) a emis deja Organization
 * sau WebSite schema în <head>. Folosim output buffer pentru a inspecta ce s-a emis înainte
 * de priority 30 (Magpress).
 *
 * Robust independent de internalele plugin-urilor — uită-te direct la output.
 */
function magpress_head_already_has_schema($type) {
    $buffer = magpress_get_head_buffer();
    if ($buffer === false) return false;
    // Pentru tipuri Organization-family (Organization, NewsMediaOrganization, OnlineBusiness, etc.),
    // detectie permissive — dacă există ORICE subtype Org, nu duplicăm.
    // User-ul poate dezactiva SEOPress Organization manual pe site-urile unde vrea Magpress să emite
    // un tip specific (ex: NewsMediaOrganization pe site-uri news).
    $org_family = ['Organization', 'NewsMediaOrganization', 'OnlineBusiness', 'LocalBusiness', 'Corporation', 'EducationalOrganization', 'GovernmentOrganization', 'NGO'];
    if (in_array($type, $org_family, true)) {
        $pattern = '/"@type"\s*:\s*"(' . implode('|', $org_family) . ')"/';
        return (bool) preg_match($pattern, $buffer);
    }
    return (bool) preg_match('/"@type"\s*:\s*"' . preg_quote($type, '/') . '"/', $buffer);
}

// Output buffer pe wp_head: pornim la priority 1, citim la priority 30 (Magpress), oprim la 999
function magpress_start_head_buffer() {
    if (!is_front_page() && !is_home()) return; // doar pe homepage e nevoie de detection
    ob_start();
}
add_action('wp_head', 'magpress_start_head_buffer', 1);

function magpress_get_head_buffer() {
    if (!is_front_page() && !is_home()) return false;
    if (!ob_get_level()) return false;
    return ob_get_contents();
}

function magpress_end_head_buffer() {
    if (!is_front_page() && !is_home()) return;
    if (!ob_get_level()) return;

    $buffer = ob_get_clean();

    // Strip schema duplicate emise de SEOPress (Organization, NewsMediaOrganization, WebSite).
    // Identificare: SEOPress folosește json_encode default cu escape slashes (\/), Magpress folosește
    // JSON_UNESCAPED_SLASHES (//). Astfel păstrăm DOAR schema Magpress fără confuzie pentru Google.
    $buffer = preg_replace_callback(
        '/<script\b[^>]*type="application\/ld\+json"[^>]*>\s*(\{[\s\S]*?\})\s*<\/script>/i',
        function ($m) {
            $json = $m[1];
            // Doar dacă e @type Organization/NewsMediaOrganization/WebSite
            if (!preg_match('/"@type"\s*:\s*"(Organization|NewsMediaOrganization|OnlineBusiness|LocalBusiness|WebSite)"/', $json)) {
                return $m[0]; // alt schema (Article, BreadcrumbList, ItemList, Person, etc.) — păstrăm
            }
            // SEOPress identification: escape slashes în URL-uri
            if (strpos($json, '\\/') !== false) {
                return ''; // strip — Magpress emite varianta corectă
            }
            return $m[0]; // Magpress block — păstrăm
        },
        $buffer
    );

    echo $buffer;
}
add_action('wp_head', 'magpress_end_head_buffer', 999);

/**
 * Fix titluri dublu-codate (SEOPress): separatorul salvat ca text „&raquo;"
 * mai e codat o dată la output → în tab/Google apare literal „&raquo;" în loc
 * de „»". Decodăm doar dublu-codările (&amp;raquo; → &raquo;), fără să atingem
 * ampersand-urile legitime (&amp; venit dintr-un „&" real rămâne intact).
 */
function magpress_fix_double_encoded_title($title) {
    if (!is_string($title) || $title === '') { return $title; }
    return preg_replace('/&amp;(#?[a-zA-Z0-9]+;)/', '&$1', $title);
}
// SEOPress scurtcircuitează titlul prin pre_get_document_title; rulăm după el.
add_filter('pre_get_document_title', 'magpress_fix_double_encoded_title', 9999);
// Fallback pentru titlurile generate de WP core.
add_filter('document_title', 'magpress_fix_double_encoded_title', 9999);
// SEOPress: filtrul lui de titlu (dacă pluginul e activ).
add_filter('seopress_titles_title', 'magpress_fix_double_encoded_title', 9999);

/**
 * BULLETPROOF: SEOPress escapează separatorul DUPĂ filtrele de mai sus, așa că
 * uneori „&amp;raquo;" ajunge totuși în <title>. Prindem tag-ul <title> direct
 * în output-ul din <head>, indiferent cine îl generează, și decodăm dublu-
 * codările DOAR în interiorul lui. Bufferul înfășoară tot <head>-ul: pornit
 * la prioritate -100 (înaintea oricărui alt buffer din head) și închis la 1000
 * (DUPĂ bufferul de schema homepage, care e 1→999), ca nesting-ul să rămână
 * corect (LIFO) pe toate paginile.
 */
function magpress_title_buffer_start() { ob_start(); }
add_action('wp_head', 'magpress_title_buffer_start', -100);

function magpress_title_buffer_end() {
    if (!ob_get_level()) { return; }
    $html = ob_get_clean();
    if ($html === false) { return; }
    $html = preg_replace_callback('#<title\b[^>]*>(.*?)</title>#is', function ($m) {
        $fixed = preg_replace('/&amp;(#?[a-zA-Z0-9]+;)/', '&$1', $m[1]);
        if ($fixed === $m[1]) { return $m[0]; }
        return str_replace('>' . $m[1] . '<', '>' . $fixed . '<', $m[0]);
    }, $html, 1);
    echo $html;
}
add_action('wp_head', 'magpress_title_buffer_end', 1000);

/**
 * Determină tipul de publisher schema pentru site-ul curent.
 * - Customizer setting (override manual) are prioritate
 * - Altfel, auto-detect din domain (NewsMediaOrganization pe domenii news cunoscute, Organization altfel)
 */
function magpress_resolve_publisher_type() {
    // 1. PRIORITATE: setarea din admin (Aspect → Magpress → Tip site)
    $site_type = (string) get_option('magpress_site_type', 'auto');
    $admin_map = [
        'news'     => 'NewsMediaOrganization',
        'magazine' => 'Organization',
        'blog'     => 'Organization',
        'business' => 'OnlineBusiness',
    ];
    if ($site_type && $site_type !== 'auto' && isset($admin_map[$site_type])) {
        return $admin_map[$site_type];
    }

    // 2. Customizer override (legacy)
    $setting = (string) get_theme_mod('magpress_schema_publisher_type', 'auto');
    if ($setting && $setting !== 'auto') return $setting;

    // 3. Auto-detect din domain. Lista poate fi extinsă via filter `magpress_news_domains`.
    $news_domains = apply_filters('magpress_news_domains', [
        'comunicateinpresa.ro',
        'centruldebusiness.ro',
        'ghidulnational.ro',
        'jurnaluldemedia.ro',
        'business-entrepreneur.ro',
        'blogdeantreprenor.ro',
        'revistafocus.ro',
    ]);

    $host = parse_url(home_url('/'), PHP_URL_HOST);
    $host = preg_replace('/^www\./i', '', strtolower((string) $host));

    return in_array($host, $news_domains, true) ? 'NewsMediaOrganization' : 'Organization';
}

/**
 * Default valori pentru câmpurile publisher schema.
 * Datele firmei (telefon, adresă, foundingDate) sunt hardcodate pentru SEO Digital S.R.L.
 * Restul (name, url, email, description) sunt derivate automat din WP/domain.
 * Extensibil via filter `magpress_publisher_defaults` (orice site care folosește tema poate modifica).
 */
function magpress_publisher_default($key) {
    $host = preg_replace('/^www\./i', '', parse_url(home_url(), PHP_URL_HOST));
    $defaults = [
        // Date auto din WP/domain
        'legal_name'          => get_bloginfo('name'),
        'alternate_name'      => '',
        'description'         => get_bloginfo('description'),
        'email'               => $host ? 'contact@' . $host : '',
        // Date firmă publisher (SEO Digital S.R.L. — Eduard) — comune pe toate site-urile
        'phone'               => '0747394863',
        'address_street'      => 'Strada Planetei',
        'address_locality'    => 'Giroc',
        'address_postal_code' => '307220',
        'address_country'     => 'RO',
        'founding_year'       => '2007',
    ];
    $defaults = apply_filters('magpress_publisher_defaults', $defaults);
    return $defaults[$key] ?? '';
}

/**
 * Citește un câmp publisher: dacă admin l-a setat, returnează acea valoare; altfel default.
 */
function magpress_get_publisher_field($key) {
    $val = get_option('magpress_publisher_' . $key, '');
    if ($val === '' || $val === false) return magpress_publisher_default($key);
    return $val;
}

/**
 * Publisher MINIMAL pentru Article schema — doar câmpurile Google-required (name, url, logo).
 * NU include telefon, address, foundingDate (acele date apar doar pe homepage Organization, nu per articol).
 */
function magpress_get_publisher_data_minimal() {
    $logo_url = trim((string) get_theme_mod('magpress_schema_publisher_logo', ''));
    if (!$logo_url) {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }
    }
    $data = [
        '@type' => magpress_resolve_publisher_type(),
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];
    if ($logo_url) {
        $data['logo'] = ['@type' => 'ImageObject', 'url' => $logo_url];
    }
    return $data;
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
    $type = magpress_resolve_publisher_type();
    $cache = [
        '@type' => $type,
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];

    // Câmpuri extra publisher (legalName, description, address, etc.) — citite din admin Magpress
    $legal = magpress_get_publisher_field('legal_name');
    if ($legal && $legal !== $cache['name']) $cache['legalName'] = $legal;

    $alt = magpress_get_publisher_field('alternate_name');
    if ($alt) $cache['alternateName'] = $alt;

    $desc = magpress_get_publisher_field('description');
    if ($desc) $cache['description'] = $desc;

    $email = magpress_get_publisher_field('email');
    if ($email && is_email($email)) $cache['email'] = $email;

    $phone = magpress_get_publisher_field('phone');
    if ($phone) $cache['telephone'] = $phone;

    // PostalAddress (compus din mai multe câmpuri)
    $street   = magpress_get_publisher_field('address_street');
    $locality = magpress_get_publisher_field('address_locality');
    $postal   = magpress_get_publisher_field('address_postal_code');
    $country  = magpress_get_publisher_field('address_country');
    if ($street || $locality) {
        $addr = ['@type' => 'PostalAddress'];
        if ($street)   $addr['streetAddress']   = $street;
        if ($locality) $addr['addressLocality'] = $locality;
        if ($postal)   $addr['postalCode']      = $postal;
        if ($country)  $addr['addressCountry']  = $country;
        $cache['address'] = $addr;
    }

    $year = magpress_get_publisher_field('founding_year');
    if ($year && preg_match('/^\d{4}$/', $year)) {
        $cache['foundingDate'] = $year;
    }

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
    // description ≤160 caractere (tăiat la limită de cuvânt), recomandat de validatoare.
    $desc = wp_strip_all_tags($excerpt);
    if (mb_strlen($desc) > 160) {
        $desc = mb_substr($desc, 0, 157);
        $sp = mb_strrpos($desc, ' ');
        if ($sp !== false && $sp > 100) { $desc = mb_substr($desc, 0, $sp); }
        $desc = rtrim($desc, " ,.;:–-") . '…';
    }

    // Autor = Person îmbogățit (E-E-A-T): jobTitle, image (avatar), worksFor (publisher), sameAs (site autor).
    $author = [
        '@type' => 'Person',
        'name'  => $author_name,
        'url'   => $author_url,
    ];
    $job = apply_filters('magpress_author_job_title', 'Editor', $author_id); // schimbabil via filter / per autor
    if ($job) { $author['jobTitle'] = $job; }
    $avatar = get_avatar_url($author_id, ['size' => 192]);
    if ($avatar) { $author['image'] = $avatar; }
    $author['worksFor'] = [
        '@type' => 'Organization',
        'name'  => get_bloginfo('name'),
        'url'   => home_url('/'),
    ];
    $author_site = trim((string) get_the_author_meta('user_url', $author_id));
    if ($author_site && $author_site !== $author_url) { $author['sameAs'] = [$author_site]; }

    $schema = [
        '@context'         => 'https://schema.org',
        '@type'            => $type,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id'   => get_permalink($post),
        ],
        'headline'         => mb_substr(wp_strip_all_tags(get_the_title($post)), 0, 110),
        'description'      => $desc,
        'datePublished'    => get_the_date('c', $post),
        'dateModified'     => get_the_modified_date('c', $post),
        // un singur autor → obiect Person îmbogățit (vezi $author mai sus).
        'author'           => $author,
        // Publisher MINIMAL pe Article (doar Google-required: name + logo). Datele complete (address,
        // telephone, foundingDate) apar DOAR pe homepage Organization, nu duplicate per articol.
        'publisher'        => magpress_get_publisher_data_minimal(),
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

/**
 * ItemList schema pe homepage — lista articolelor featured (sticky sau ultimele).
 * Asta produce rich result detectabil de Rich Results Test (categoria Carousels/ItemList).
 */
function magpress_build_homepage_itemlist_schema() {
    $count = 10;
    // Caut sticky posts primii (la fel ca front-page.php)
    $sticky_ids = get_option('sticky_posts');
    $args = [
        'posts_per_page'      => $count,
        'post_status'         => 'publish',
        'ignore_sticky_posts' => 1,
    ];
    if (!empty($sticky_ids)) {
        $args['post__in'] = $sticky_ids;
    }
    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        wp_reset_postdata();
        $q = new WP_Query(['posts_per_page' => $count, 'post_status' => 'publish']);
    }
    if (!$q->have_posts()) { wp_reset_postdata(); return null; }

    $items = [];
    $pos = 1;
    while ($q->have_posts()) {
        $q->the_post();
        $item = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'url'      => get_permalink(),
            'name'     => mb_substr(wp_strip_all_tags(get_the_title()), 0, 120),
        ];
        if (has_post_thumbnail()) {
            $thumb = wp_get_attachment_image_url(get_post_thumbnail_id(), 'magpress-feature');
            if ($thumb) $item['image'] = $thumb;
        }
        $items[] = $item;
    }
    wp_reset_postdata();

    if (empty($items)) return null;

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Articole featured — ' . get_bloginfo('name'),
        'numberOfItems'   => count($items),
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
    // sameAs deliberat omis — social URLs din Customizer pot fi cross-contaminate între site-uri
    // (user setează același cont social pe mai multe site-uri); mai sigur să nu emit decât să confundăm Google.
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => $pub['@type'],
        'name'     => $pub['name'],
        'url'      => $pub['url'],
    ];
    // Câmpuri publisher complete din magpress_get_publisher_data (legalName, description, address, etc.)
    foreach (['legalName', 'alternateName', 'description', 'email', 'telephone', 'address', 'foundingDate', 'logo'] as $k) {
        if (!empty($pub[$k])) $org[$k] = $pub[$k];
    }
    // image: Google Organization rich result recomandă să existe. Folosim logo-ul ca image generic.
    if (!empty($pub['logo']['url'])) {
        $org['image'] = $pub['logo']['url'];
    }
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
    if (function_exists('magpress_is_age_gated') && magpress_is_age_gated()) $clean[] = 'has-age-gate';

    // Pastram clase WP esentiale care influenteaza layout (admin-bar offset, logged-in, embed responsive)
    foreach (['admin-bar', 'logged-in', 'wp-embed-responsive', 'rtl'] as $important) {
        if (in_array($important, $classes, true)) $clean[] = $important;
    }
    return $clean;
}
add_filter('body_class', 'magpress_body_classes');

/* =====================================================================
 * SECURITY HARDENING — central, pe toate site-urile.
 * Răspuns la rapoarte beg-bounty:
 *   - XML-RPC: pingback.ping (SSRF orb) + system.multicall (brute-force).
 *   - Enumerare utilizatori: REST /wp/v2/users, ?_embed, oEmbed, ?author=N.
 * NOTĂ: tema expune INTENȚIONAT autorul (byline + /author/slug/ + schema
 * E-E-A-T), deci slug-ul public rămâne vizibil by design. Aici ascundem
 * LISTA de utilizatori și vectorii machine-readable, nu existența autorilor.
 * Deploy-ul Magpress folosește REST (magpress/v1), NU XML-RPC.
 * rsd/wlw/generator sunt deja scoase în magpress_remove_bloat().
 * ===================================================================== */

// 1) XML-RPC complet oprit. xmlrpc.php definește XMLRPC_REQUEST înainte de
//    a încărca tema → blocăm direct accesul la fișier (403).
if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
    http_response_code(403);
    exit('XML-RPC disabled.');
}
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', '__return_empty_array');
add_filter('wp_headers', function ($headers) {
    unset($headers['X-Pingback'], $headers['x-pingback']);
    return $headers;
});

// 2) Anti user-enumeration (fără a strica paginile /author/slug/).
//    a) ascunde lista REST de utilizatori pentru neautentificați.
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});
//    b) scoate slug-ul (username) din payload-ul REST de user — acoperă și ?_embed.
add_filter('rest_prepare_user', function ($response) {
    if (!is_user_logged_in() && is_object($response) && method_exists($response, 'get_data')) {
        $data = $response->get_data();
        unset($data['slug']);
        $response->set_data($data);
    }
    return $response;
});
//    c) scoate autorul din oEmbed.
add_filter('oembed_response_data', function ($data) {
    unset($data['author_name'], $data['author_url']);
    return $data;
});
//    d) omoară enumerarea numerică ?author=N înainte de canonical redirect.
add_action('parse_request', function ($wp) {
    if (!is_user_logged_in() && isset($wp->query_vars['author'])
        && is_numeric($wp->query_vars['author'])) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});
//    e) mesaj de login generic (nu mai distinge user greșit vs parolă greșită);
//       excepție: dacă IP-ul e blocat de throttle, arătăm mesajul de blocare.
add_filter('login_errors', function () {
    if (!empty($GLOBALS['magpress_login_locked'])) {
        return __('<strong>Prea multe încercări eșuate.</strong> Reîncearcă peste 15 minute.', 'magpress');
    }
    return __('Date de autentificare incorecte.', 'magpress');
});
//    f) defense-in-depth: scoate providerul "users" din sitemap-ul WP core.
//       (No-op dacă SEOPress servește sitemap-ul — verifică setarea Authors din SEOPress.)
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    return ('users' === $name) ? false : $provider;
}, 10, 2);

/* =====================================================================
 * SECURITY HARDENING 2 — headere + reducere amprentă (theme-fixable).
 * Restul (login rate-limit, deny fișiere .log/.bak/.sql, HSTS, directory
 * listing) e la nivel de server LiteSpeed/.htaccess, NU din temă.
 * ===================================================================== */

// Defense-in-depth: nu afișa erori PHP la vizitator (anti full-path disclosure).
@ini_set('display_errors', '0');

// Reduce spam-ul din debug.log: nu mai loga warnings/notices/deprecations
// (ex. "Undefined array key width/height/file" din wp-includes/media.php pe
// articole cu metadata de imagine stricată). Erorile REALE (fatal/parse) rămân.
// WordPress forțează E_ALL când WP_DEBUG e on; revenim la nivel de producție
// DOAR dacă nu ești în debug activ (WP_DEBUG_DISPLAY off).
if (!defined('WP_DEBUG_DISPLAY') || !WP_DEBUG_DISPLAY) {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
}

// Dezactivează Application Passwords (deploy-ul folosește HMAC, nu app passwords).
add_filter('wp_is_application_passwords_available', '__return_false');

// Security response headers + ascunde X-Powered-By (versiunea PHP).
add_action('send_headers', function () {
    if (headers_sent()) {
        return;
    }
    @header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Blocăm doar funcții device-sensitive; NU atingem features de ads (browsing-topics etc.).
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()');
}, 1);

// Ascunde versiunea WordPress din ?ver= pe asset-urile core
// (păstrează cache-busting pentru temă/plugin-uri, care au alt ver).
function magpress_hide_wp_version_assets($src) {
    global $wp_version;
    if (!$src) {
        return $src;
    }
    $parts = wp_parse_url($src);
    if (empty($parts['query'])) {
        return $src;
    }
    parse_str($parts['query'], $q);
    if (isset($q['ver']) && $q['ver'] === $wp_version) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'magpress_hide_wp_version_assets', 9999);
add_filter('script_loader_src', 'magpress_hide_wp_version_assets', 9999);

/* =====================================================================
 * SECURITY HARDENING 3 — injector .htaccess (server-level, via temă).
 * Scrie un bloc între markeri în .htaccess-ul site-ului, O SINGURĂ DATĂ
 * per versiune, cu BACKUP + SELF-HEAL: dacă după scriere homepage-ul cade
 * în 5xx (ex. host fără AllowOverride), restaurează automat .htaccess.
 * Gardat de un option + transient lock (evită recursia din loopback).
 * ===================================================================== */
add_action('init', 'magpress_inject_htaccess_hardening', 99);
function magpress_inject_htaccess_hardening() {
    $target = '0.9.84';
    if (get_option('magpress_htaccess_hardened') === $target) {
        return;
    }
    if (get_transient('magpress_htaccess_lock')) {
        return; // request-ul de loopback (sau un al doilea load) nu reintră
    }

    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Doar unde .htaccess contează (Apache/LiteSpeed cu mod_rewrite).
    if (function_exists('got_url_rewrite') && !got_url_rewrite()) {
        update_option('magpress_htaccess_hardened', $target); // nu se aplică aici
        return;
    }

    $file = get_home_path() . '.htaccess';
    if (!file_exists($file) || !is_writable($file)) {
        return; // reîncearcă la următorul load
    }

    set_transient('magpress_htaccess_lock', 1, 2 * MINUTE_IN_SECONDS);

    $rules = array(
        'Options -Indexes',
        '<Files "xmlrpc.php">',
        '    Require all denied',
        '</Files>',
        '<Files "wp-config.php">',
        '    Require all denied',
        '</Files>',
        '<Files "debug.log">',
        '    Require all denied',
        '</Files>',
        '<FilesMatch "(?i)\.(bak|save|old|orig|swp|swo|tmp|sql|sql\.gz|log|env|sh)$">',
        '    Require all denied',
        '</FilesMatch>',
        '<FilesMatch "(?i)^(wp-config\.php\..*|\.env|\.htpasswd|readme\.html|license\.txt)$">',
        '    Require all denied',
        '</FilesMatch>',
        '<IfModule mod_headers.c>',
        '    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"',
        '</IfModule>',
    );

    $backup = $file . '.magpress-bak';
    @copy($file, $backup);

    if (!insert_with_markers($file, 'Magpress Security', $rules)) {
        delete_transient('magpress_htaccess_lock');
        return; // n-a putut scrie; reîncearcă
    }

    // Self-heal: verifică homepage prin loopback; rollback DOAR la 5xx.
    $resp = wp_remote_get(home_url('/'), array('timeout' => 10, 'sslverify' => false, 'redirection' => 0));
    $code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);

    if ($code >= 500) {
        if (file_exists($backup)) {
            @copy($backup, $file); // restaurează .htaccess original
        } else {
            insert_with_markers($file, 'Magpress Security', array()); // scoate blocul
        }
        update_option('magpress_htaccess_hardened', 'FAILED-' . $target);
    } else {
        update_option('magpress_htaccess_hardened', $target);
    }
    delete_transient('magpress_htaccess_lock');
}

/* =====================================================================
 * SECURITY HARDENING 4 — login throttle nativ (anti brute-force).
 * Blochează IP-ul după 5 încercări eșuate, timp de 15 min. Reset la login
 * reușit. Cheia = REMOTE_ADDR (IP real pe LiteSpeed direct, nespoofabil).
 * ===================================================================== */
define('MAGPRESS_LOGIN_MAX_FAILS', 5);
define('MAGPRESS_LOGIN_LOCK_MINUTES', 15);

function magpress_login_throttle_key() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    return 'magpress_lla_' . md5($ip);
}

// Blochează ÎNAINTE de verificarea parolei dacă IP-ul a depășit limita.
add_filter('authenticate', function ($user, $username) {
    if (empty($username)) {
        return $user;
    }
    if ((int) get_transient(magpress_login_throttle_key()) >= MAGPRESS_LOGIN_MAX_FAILS) {
        $GLOBALS['magpress_login_locked'] = true;
        return new WP_Error('magpress_locked', __('Prea multe încercări eșuate. Reîncearcă mai târziu.', 'magpress'));
    }
    return $user;
}, 30, 2);

// Incrementează contorul la fiecare login eșuat.
add_action('wp_login_failed', function () {
    $key   = magpress_login_throttle_key();
    $fails = (int) get_transient($key);
    set_transient($key, $fails + 1, MAGPRESS_LOGIN_LOCK_MINUTES * MINUTE_IN_SECONDS);
});

// Reset la login reușit.
add_action('wp_login', function () {
    delete_transient(magpress_login_throttle_key());
});

/* =====================================================================
 * FIX PUNCTUAL — business-entrepreneur.ro: WP Rocket rula în paralel cu
 * LiteSpeed (dublu page-cache). Dezactivăm WP Rocket (LiteSpeed rămâne
 * singurul cache). Gardat pe HOST + o singură dată. No-op pe restul flotei.
 * ===================================================================== */
add_action('init', 'magpress_fix_be_wprocket', 99);
function magpress_fix_be_wprocket() {
    $host = preg_replace('/^www\./i', '', strtolower((string) parse_url(home_url('/'), PHP_URL_HOST)));
    if ($host !== 'business-entrepreneur.ro') {
        return;
    }
    if (get_option('magpress_be_wprocket_fixed') === 'done') {
        return;
    }
    if (!function_exists('deactivate_plugins') || !function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $pf = 'wp-rocket/wp-rocket.php';
    if (is_plugin_active($pf)) {
        deactivate_plugins($pf); // silent=false → rulează hook-ul de cleanup al WP Rocket
    }
    // Fallback: dacă advanced-cache.php a rămas și e al WP Rocket, îl scoatem.
    $adv = WP_CONTENT_DIR . '/advanced-cache.php';
    if (@file_exists($adv)) {
        $c = (string) @file_get_contents($adv, false, null, 0, 400);
        if (strpos($c, 'WP_ROCKET') !== false) {
            @unlink($adv);
        }
    }
    update_option('magpress_be_wprocket_fixed', 'done');
}

/* =====================================================================
 * AGE GATE 18+ — overlay de confirmare vârstă pe articole MARCATE.
 * Pentru subiecte cu restricție de vârstă (alcool, tutun etc.).
 * 100% client-side (cookie) → compatibil cu full-page cache LiteSpeed.
 * Conținutul rămâne în DOM (SEO-safe). Overlay vizibil DEFAULT, ascuns de
 * JS dacă există cookie-ul de consimțământ. 0 bytes pe articolele nemarcate.
 * ===================================================================== */

// Per-articol: meta + checkbox în editor.
add_action('init', function () {
    register_post_meta('post', '_magpress_adult', [
        'type'          => 'boolean',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => function () { return current_user_can('edit_posts'); },
    ]);
});
add_action('add_meta_boxes', function () {
    add_meta_box('magpress_adult_box', 'Restricție vârstă 18+', 'magpress_adult_box_cb', 'post', 'side', 'default');
});
function magpress_adult_box_cb($post) {
    wp_nonce_field('magpress_adult_save', 'magpress_adult_nonce');
    $on = (bool) get_post_meta($post->ID, '_magpress_adult', true);
    echo '<label style="display:flex;gap:.5rem;align-items:flex-start;line-height:1.4">';
    echo '<input type="checkbox" name="magpress_adult" value="1" ' . checked($on, true, false) . '>';
    echo '<span>Articol <strong>18+</strong> — afișează confirmare de vârstă înainte de conținut (alcool, tutun etc.).</span>';
    echo '</label>';
}
add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['magpress_adult_nonce']) || !wp_verify_nonce($_POST['magpress_adult_nonce'], 'magpress_adult_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, '_magpress_adult', empty($_POST['magpress_adult']) ? 0 : 1);
});

// Articolul curent e marcat 18+?
function magpress_is_age_gated() {
    if (!is_singular('post')) return false;
    if ((bool) get_post_meta(get_queried_object_id(), '_magpress_adult', true)) return true;
    return false;
}

// Anti-flash: în <head>, dacă există cookie-ul, marcăm <html> ca age-ok ÎNAINTE de paint.
add_action('wp_head', function () {
    if (!magpress_is_age_gated()) return;
    ?>
<style id="magpress-age-gate-css">
body.has-age-gate{overflow:hidden}
html.age-ok body.has-age-gate{overflow:visible}
.magpress-age-gate{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:1.25rem;background:rgba(15,16,20,.6);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
html.age-ok .magpress-age-gate{display:none}
.magpress-age-gate__panel{background:#fff;width:min(560px,100%);border-radius:14px;text-align:center;padding:2.5rem 2rem;box-shadow:0 24px 80px rgba(0,0,0,.45);font-family:var(--f-sans,system-ui,sans-serif);animation:mpAgeIn .4s ease}
@keyframes mpAgeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.magpress-age-gate__panel p{margin:0 0 1.8rem;color:#333;font-size:1.05rem;line-height:1.6}
.magpress-age-gate__actions{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;width:100%}
.magpress-age-gate__btn{cursor:pointer;border-radius:6px;padding:.9rem 1.5rem;font-size:1rem;font-weight:700;font-family:inherit;min-width:210px;transition:filter .15s,transform .1s}
.magpress-age-gate__btn:hover{filter:brightness(1.07)}
.magpress-age-gate__btn:active{transform:scale(.98)}
.magpress-age-gate__btn--yes{background:var(--c-accent,#6fa84a);color:#fff;border:2px solid transparent}
.magpress-age-gate__btn--no{background:#fff;color:#333;border:2px solid #d7d7d7}
.magpress-age-gate__btn--no:hover{border-color:#aaa;filter:none}
@media (max-width:620px){.magpress-age-gate__btn{width:100%;min-width:0}}
</style>
<script>(function(){try{if(document.cookie.indexOf('magpress_adult_ok=1')>-1){document.documentElement.className+=' age-ok';}}catch(e){}})();</script>
    <?php
}, 2);

// Overlay-ul propriu-zis, imediat după <body> (conținutul rămâne în DOM dedesubt).
add_action('wp_body_open', function () {
    if (!magpress_is_age_gated()) return;
    $text = apply_filters('magpress_age_gate_text', 'Pentru a continua, vă rugăm să vă confirmați vârsta. Aveți peste 18 ani?');
    ?>
<div class="magpress-age-gate" id="magpress-age-gate" role="dialog" aria-modal="true" aria-label="Confirmare vârstă 18+">
  <div class="magpress-age-gate__panel">
    <p><?php echo esc_html($text); ?></p>
    <div class="magpress-age-gate__actions">
      <button type="button" class="magpress-age-gate__btn magpress-age-gate__btn--yes" data-age="yes">DA, am peste 18 ani</button>
      <button type="button" class="magpress-age-gate__btn magpress-age-gate__btn--no" data-age="no">NU, nu am peste 18 ani</button>
    </div>
  </div>
</div>
    <?php
});

// JS pentru butoane (doar pe articolele marcate).
add_action('wp_footer', function () {
    if (!magpress_is_age_gated()) return;
    $home = esc_js(home_url('/'));
    ?>
<script>(function(){var g=document.getElementById('magpress-age-gate');if(!g)return;
function ok(){document.cookie='magpress_adult_ok=1;path=/;max-age=15552000;SameSite=Lax;Secure';document.documentElement.className+=' age-ok';g.style.display='none';document.body.classList.remove('has-age-gate');}
function no(){window.location.href='<?php echo $home; ?>';}
var y=g.querySelector('[data-age="yes"]'),n=g.querySelector('[data-age="no"]');
if(y)y.addEventListener('click',ok);if(n)n.addEventListener('click',no);})();</script>
    <?php
});

/* =========================================================================
 * CURS VALUTAR BNR — widget + shortcode [curs_bnr]
 * Sursa: feed-ul oficial BNR (10 zile, ca să putem arăta direcția față de
 * ziua precedentă). Cache transient 6h; complet inert până e plasat widgetul.
 * ========================================================================= */

/**
 * Cursurile BNR pe ultimele zile publicate.
 * @return array ['dates' => ['2026-07-16', ...desc], 'rates' => ['2026-07-16' => ['EUR' => ['value'=>5.2414,'multiplier'=>1], ...]]]
 */
function magpress_bnr_rates() {
    $cached = get_transient('magpress_bnr_rates_v1');
    if (is_array($cached) && !empty($cached['dates'])) return $cached;

    $resp = wp_remote_get('https://www.bnr.ro/nbrfxrates10days.xml', ['timeout' => 10]);
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        // Nu suprascriem cache-ul bun cu un eșec; reîncercăm peste 30 min.
        $stale = get_transient('magpress_bnr_rates_stale');
        if (is_array($stale)) { set_transient('magpress_bnr_rates_v1', $stale, 30 * MINUTE_IN_SECONDS); return $stale; }
        return ['dates' => [], 'rates' => []];
    }

    $xml = @simplexml_load_string(wp_remote_retrieve_body($resp));
    if (!$xml || empty($xml->Body->Cube)) return ['dates' => [], 'rates' => []];

    $rates = [];
    foreach ($xml->Body->Cube as $cube) {
        $date = (string) $cube['date'];
        if (!$date) continue;
        foreach ($cube->Rate as $r) {
            $cur = strtoupper((string) $r['currency']);
            $mul = max(1, (int) $r['multiplier']);
            $val = (float) $r;
            if ($cur && $val > 0) $rates[$date][$cur] = ['value' => $val, 'multiplier' => $mul];
        }
    }
    if (!$rates) return ['dates' => [], 'rates' => []];
    krsort($rates); // cele mai noi primele
    $out = ['dates' => array_keys($rates), 'rates' => $rates];
    set_transient('magpress_bnr_rates_v1', $out, 6 * HOUR_IN_SECONDS);
    set_transient('magpress_bnr_rates_stale', $out, 7 * DAY_IN_SECONDS); // fallback la eșec de fetch
    return $out;
}

/**
 * Randează lista de cursuri (stil rânduri gri + săgeată direcție vs ziua anterioară).
 * $currencies: array de coduri ISO (ex. ['EUR','USD','GBP','CHF']).
 */
function magpress_render_curs_bnr($currencies = ['CHF', 'EUR', 'GBP', 'USD'], $show_date = true) {
    $data = magpress_bnr_rates();
    if (empty($data['dates'])) return '';
    $today = $data['dates'][0];
    $prev  = isset($data['dates'][1]) ? $data['dates'][1] : null;

    static $css_done = false;
    $css = '';
    if (!$css_done) {
        $css_done = true;
        $css = '<style id="mp-curs-css">.mp-curs{font-family:var(--f-sans,system-ui,sans-serif)}'
            . '.mp-curs__row{display:flex;align-items:center;justify-content:space-between;gap:.75rem;background:#f5f5f5;padding:.85rem 1.1rem;margin-bottom:2px;font-size:1rem;color:#1a1a1a}'
            . '.mp-curs__row b{font-weight:800}'
            . '.mp-curs__dir{font-weight:800;font-size:1.05rem;line-height:1}'
            . '.mp-curs__dir--up{color:#1a8a1a}.mp-curs__dir--down{color:#d33}.mp-curs__dir--flat{color:#999}'
            . '.mp-curs__date{margin-top:.5rem;font-size:.8rem;color:#888;text-align:right}</style>';
    }

    $rows = '';
    foreach ($currencies as $cur) {
        $cur = strtoupper(trim($cur));
        if (!isset($data['rates'][$today][$cur])) continue;
        $now = $data['rates'][$today][$cur];
        $dir = 'flat'; $sym = '→';
        if ($prev && isset($data['rates'][$prev][$cur])) {
            $old = $data['rates'][$prev][$cur];
            if ($now['value'] > $old['value'])      { $dir = 'up';   $sym = '↑'; }
            elseif ($now['value'] < $old['value'])  { $dir = 'down'; $sym = '↓'; }
        }
        $val = rtrim(rtrim(number_format($now['value'], 4, '.', ''), '0'), '.');
        $rows .= '<div class="mp-curs__row"><span>' . (int) $now['multiplier'] . ' ' . esc_html($cur)
            . ' = <b>' . esc_html($val) . '</b></span>'
            . '<span class="mp-curs__dir mp-curs__dir--' . $dir . '" aria-hidden="true">' . $sym . '</span></div>';
    }
    if ($rows === '') return '';

    $date_html = '';
    if ($show_date) {
        $date_html = '<div class="mp-curs__date">Curs BNR — ' . esc_html(date_i18n('j F Y', strtotime($today))) . '</div>';
    }
    return $css . '<div class="mp-curs">' . $rows . $date_html . '</div>';
}

// Shortcode: [curs_bnr currencies="EUR,USD,GBP,CHF" date="1"]
add_shortcode('curs_bnr', function ($atts) {
    $atts = shortcode_atts(['currencies' => 'CHF,EUR,GBP,USD', 'date' => '1'], $atts, 'curs_bnr');
    return magpress_render_curs_bnr(array_filter(array_map('trim', explode(',', $atts['currencies']))), $atts['date'] !== '0');
});

// Widget clasic (Aspect -> Widgets).
class Magpress_Curs_BNR_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('magpress_curs_bnr', 'Magpress: Curs valutar BNR', [
            'description' => 'Cursul oficial BNR cu direcția față de ziua precedentă (cache 6h).',
        ]);
    }
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Curs valutar';
        $curs  = !empty($instance['currencies']) ? $instance['currencies'] : 'CHF,EUR,GBP,USD';
        $html  = magpress_render_curs_bnr(array_filter(array_map('trim', explode(',', $curs))));
        if ($html === '') return; // feed indisponibil -> nu afișăm nimic stricat
        echo $args['before_widget'];
        if ($title) echo $args['before_title'] . esc_html($title) . $args['after_title'];
        echo $html;
        echo $args['after_widget'];
    }
    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : 'Curs valutar';
        $curs  = isset($instance['currencies']) ? $instance['currencies'] : 'CHF,EUR,GBP,USD';
        ?>
        <p><label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Titlu:</label>
        <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>"></p>
        <p><label for="<?php echo esc_attr($this->get_field_id('currencies')); ?>">Valute (coduri ISO, separate prin virgulă):</label>
        <input class="widefat" id="<?php echo esc_attr($this->get_field_id('currencies')); ?>" name="<?php echo esc_attr($this->get_field_name('currencies')); ?>" type="text" value="<?php echo esc_attr($curs); ?>">
        <small>Ex: EUR,USD,GBP,CHF — orice cod din feed-ul BNR (HUF, PLN, TRY, XAU...).</small></p>
        <?php
    }
    public function update($new, $old) {
        return [
            'title'      => sanitize_text_field($new['title']),
            'currencies' => strtoupper(preg_replace('/[^A-Za-z,\s]/', '', (string) $new['currencies'])),
        ];
    }
}
add_action('widgets_init', function () { register_widget('Magpress_Curs_BNR_Widget'); });

/**
 * Top bar cu cursul BNR — bandă subțire deasupra header-ului (ambele layout-uri).
 * Activare din Customizer -> „Magpress: Curs valutar BNR" (implicit OFF).
 */
add_action('wp_body_open', 'magpress_curs_topbar_output', 5);

/* ============================================================
 * BARA VREME — top bar (sub Curs BNR) + widget + shortcode [vreme_bar]
 * Sursa: feed-ul oficial ANM (prognoza pe orașe), cache 3h + fallback stale 24h.
 * Ticker care derulează lent orașele; link către ultimul articol de vreme.
 * ============================================================ */

/** Iconiță dedusă din descrierea ANM (text ASCII, fără diacritice). */
function magpress_weather_icon($desc) {
    $d = strtolower($desc);
    if (strpos($d, 'descarcari electrice') !== false || strpos($d, 'oraj') !== false) { return '⛈️'; }
    if (strpos($d, 'nins') !== false)                                                  { return '❄️'; }
    if (strpos($d, 'ploaie') !== false || strpos($d, 'averse') !== false || strpos($d, 'burnita') !== false) { return '🌧️'; }
    if (strpos($d, 'ceata') !== false)                                                 { return '🌫️'; }
    if (strpos($d, 'senin') !== false)                                                 { return '☀️'; }
    if (strpos($d, 'noros') !== false || strpos($d, 'innorat') !== false || strpos($d, 'acoperit') !== false) { return '☁️'; }
    if (strpos($d, 'variabil') !== false || strpos($d, 'partial') !== false)           { return '⛅'; }
    return '🌡️';
}

/** Diacritice în descrierea ANM (strtr, fără mbstring). */
function magpress_weather_beautify($d) {
    return strtr($d, [
        ' si ' => ' și ', 'descarcari' => 'descărcări', 'slaba' => 'slabă',
        'ceata' => 'ceață', 'innorat' => 'înnorat', 'burnita' => 'burniță',
        'vant' => 'vânt', 'insemnate' => 'însemnate', 'moderata' => 'moderată',
        'puternica' => 'puternică', 'ridicata' => 'ridicată', 'scazuta' => 'scăzută',
    ]);
}

/** Normalizează un nume de oraș pentru potrivire (fără diacritice, lowercase). */
function magpress_weather_norm($s) {
    $s = strtr(trim($s), [
        'ă'=>'a','Ă'=>'a','â'=>'a','Â'=>'a','î'=>'i','Î'=>'i',
        'ș'=>'s','Ș'=>'s','ş'=>'s','Ş'=>'s','ț'=>'t','Ț'=>'t','ţ'=>'t','Ţ'=>'t',
    ]);
    return strtolower($s);
}

/** Link către ultimul articol de vreme; fallback pe categoria „Vremea". */
function magpress_weather_latest_link() {
    $q = new WP_Query([
        'post_type' => 'post', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_key' => '_hz_weather', 'orderby' => 'date', 'order' => 'DESC',
        'no_found_rows' => true, 'ignore_sticky_posts' => true, 'suppress_filters' => true,
    ]);
    if (!empty($q->posts)) { return get_permalink($q->posts[0]); }
    $term = get_term_by('slug', 'vremea', 'category');
    if (!$term) { $term = get_term_by('name', 'Vremea', 'category'); }
    if ($term && !is_wp_error($term)) {
        $link = get_term_link($term);
        if (!is_wp_error($link)) { return $link; }
    }
    return '';
}

/** Datele de vreme pentru azi (cache 3h + stale 24h), din feed-ul ANM. */
function magpress_weather_data() {
    $cached = get_transient('magpress_weather_v1');
    if (is_array($cached)) { return $cached; }

    $resp = wp_remote_get('https://www.meteoromania.ro/anm/prognoza-orase-xml.php', ['timeout' => 10]);
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        $stale = get_transient('magpress_weather_stale');
        if (is_array($stale)) { set_transient('magpress_weather_v1', $stale, 30 * MINUTE_IN_SECONDS); return $stale; }
        return ['date' => '', 'cities' => [], 'link' => ''];
    }
    $body = (string) wp_remote_retrieve_body($resp);
    $prev = libxml_use_internal_errors(true);
    $xml  = simplexml_load_string($body);
    libxml_use_internal_errors($prev);
    if ($xml === false || !isset($xml->tara->localitate)) {
        $stale = get_transient('magpress_weather_stale');
        if (is_array($stale)) { return $stale; }
        return ['date' => '', 'cities' => [], 'link' => ''];
    }

    $names = [
        'Bucuresti' => 'București', 'Iasi' => 'Iași', 'Botosani' => 'Botoșani',
        'Cluj-Napoca' => 'Cluj-Napoca', 'Sibiu' => 'Sibiu', 'Arad' => 'Arad',
        'Craiova' => 'Craiova', 'Rm. Valcea' => 'Râmnicu Vâlcea',
        'Constanta' => 'Constanța', 'Sulina' => 'Sulina',
    ];
    $today  = current_time('Y-m-d');
    $cities = [];
    foreach ($xml->tara->localitate as $loc) {
        $rawn = trim((string) $loc['nume']);
        if (!isset($names[$rawn])) { continue; }
        $entry = null;
        foreach ($loc->prognoza as $p) {
            if ((string) $p['data'] === $today) { $entry = $p; break; }
        }
        if ($entry === null && isset($loc->prognoza[0])) { $entry = $loc->prognoza[0]; }
        if ($entry === null) { continue; }
        $desc = trim((string) $entry->fenomen_descriere);
        $cities[$names[$rawn]] = [
            'min'  => (int) $entry->temp_min,
            'max'  => (int) $entry->temp_max,
            'icon' => magpress_weather_icon($desc),
            'desc' => $desc !== '' ? magpress_weather_beautify($desc) : '',
        ];
    }
    $out = ['date' => $today, 'cities' => $cities, 'link' => magpress_weather_latest_link()];
    if ($cities) {
        set_transient('magpress_weather_v1', $out, 3 * HOUR_IN_SECONDS);
        set_transient('magpress_weather_stale', $out, DAY_IN_SECONDS);
    }
    return $out;
}

/** CSS-ul barei (o singură dată pe request). */
function magpress_weather_css() {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return '<style id="mp-wx-css">'
        . '.mp-wx-bar{background:#191c24;color:#cfd3dc;font-family:var(--f-sans,system-ui,sans-serif);font-size:.82rem;line-height:1;border-top:1px solid rgba(255,255,255,.06)}'
        . '.mp-wx-bar__inner{display:flex;align-items:center;gap:1rem;padding-top:.42rem;padding-bottom:.42rem}'
        . '.mp-wx-bar__label{color:#8b91a0;font-weight:700;letter-spacing:.04em;text-transform:uppercase;font-size:.72rem;white-space:nowrap;display:inline-flex;align-items:center;gap:.4rem;flex:0 0 auto}'
        . '.mp-wx-bar__ticker{flex:1 1 auto;overflow:hidden;-webkit-mask-image:linear-gradient(to right,transparent,#000 2.5%,#000 97.5%,transparent);mask-image:linear-gradient(to right,transparent,#000 2.5%,#000 97.5%,transparent)}'
        . '.mp-wx__track{display:inline-flex;align-items:center;white-space:nowrap;animation-name:mp-wx-scroll;animation-timing-function:linear;animation-iteration-count:infinite;will-change:transform}'
        . '.mp-wx-bar__ticker:hover .mp-wx__track{animation-play-state:paused}'
        . '.mp-wx-bar__ticker--static{overflow-x:auto;-webkit-overflow-scrolling:touch;-webkit-mask-image:none;mask-image:none;scrollbar-width:none}'
        . '.mp-wx-bar__ticker--static::-webkit-scrollbar{display:none}'
        . '.mp-wx__track--static{animation:none}'
        . '.mp-wx__item{display:inline-flex;align-items:center;gap:.35rem;margin-right:2rem}'
        . '.mp-wx__ic{font-size:.95rem}.mp-wx__nm{color:#c7ccda}.mp-wx__mn{color:#8b91a0}'
        . '.mp-wx__sep{color:#3a4054;margin:0 .12rem}.mp-wx__mx{color:#fff;font-weight:700}'
        . '.mp-wx__link{flex:0 0 auto;color:#6db3f2;text-decoration:none;font-weight:700;white-space:nowrap;font-size:.78rem}'
        . '.mp-wx__link:hover{color:#8ec5f5}'
        // Varianta DESCHISĂ (fundal gri deschis, text închis)
        . '.mp-wx-bar--light{background:#eef1f5;color:#374151;border-top-color:#dfe3ea}'
        . '.mp-wx-bar--light .mp-wx-bar__label{color:#6b7280}'
        . '.mp-wx-bar--light .mp-wx__nm{color:#374151}'
        . '.mp-wx-bar--light .mp-wx__mn{color:#6b7280}'
        . '.mp-wx-bar--light .mp-wx__mx{color:#111827}'
        . '.mp-wx-bar--light .mp-wx__sep{color:#c2c8d2}'
        . '.mp-wx-bar--light .mp-wx__link{color:#1f6fb2}'
        . '.mp-wx-bar--light .mp-wx__link:hover{color:#12507f}'
        . '@keyframes mp-wx-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}'
        . '@media(prefers-reduced-motion:reduce){.mp-wx__track{animation:none}.mp-wx-bar__ticker{overflow-x:auto}}'
        . '@media(max-width:768px){.mp-wx-bar__label{display:none}.mp-wx-bar__inner{gap:.6rem}}'
        . '</style>';
}

/** Construiește orașele (track) după o listă opțională de nume. Întoarce '' dacă n-are date. */
function magpress_weather_items($cities_filter = []) {
    $data = magpress_weather_data();
    if (empty($data['cities'])) { return ''; }

    $want = [];
    foreach ((array) $cities_filter as $c) {
        $c = trim($c);
        if ($c !== '') { $want[magpress_weather_norm($c)] = true; }
    }
    $items = '';
    foreach ($data['cities'] as $name => $c) {
        if ($want && !isset($want[magpress_weather_norm($name)])) { continue; }
        $items .= '<span class="mp-wx__item"' . ($c['desc'] !== '' ? ' title="' . esc_attr($c['desc']) . '"' : '') . '>'
            . '<span class="mp-wx__ic" aria-hidden="true">' . $c['icon'] . '</span>'
            . '<span class="mp-wx__nm">' . esc_html($name) . '</span> '
            . '<span class="mp-wx__mn">' . (int) $c['min'] . '°</span>'
            . '<span class="mp-wx__sep">/</span>'
            . '<span class="mp-wx__mx">' . (int) $c['max'] . '°</span>'
            . '</span>';
    }
    return $items;
}

/**
 * Poziția barei, aleasă din Customizer:
 *  - 'sub_header' (default): sub header (înainte de conținut), via hook-ul magpress_after_header
 *  - 'sub_curs': în top bar, imediat sub Curs BNR, via wp_body_open prioritate 6
 * Renderul e același; doar hook-ul care rulează diferă.
 */
add_action('wp_body_open', function () {
    if (get_theme_mod('magpress_topbar_vreme_pozitie', 'sub_header') === 'sub_curs') {
        magpress_weather_topbar_output();
    }
}, 6);
add_action('magpress_after_header', function () {
    if (get_theme_mod('magpress_topbar_vreme_pozitie', 'sub_header') === 'sub_header') {
        magpress_weather_topbar_output();
    }
});
/**
 * Blocul ticker. $scroll=true → derulează (track dublat + animație, ~8s/oraș);
 * $scroll=false → FIX (statice, fără animație; scroll orizontal manual la overflow).
 */
function magpress_weather_ticker($items, $scroll) {
    if ($scroll) {
        $count = max(1, substr_count($items, 'class="mp-wx__item"'));
        $dur   = max(40, $count * 8);
        return '<div class="mp-wx-bar__ticker"><div class="mp-wx__track" style="animation-duration:' . (int) $dur . 's">' . $items . $items . '</div></div>';
    }
    return '<div class="mp-wx-bar__ticker mp-wx-bar__ticker--static"><div class="mp-wx__track mp-wx__track--static">' . $items . '</div></div>';
}

function magpress_weather_topbar_output() {
    if (!get_theme_mod('magpress_topbar_vreme', 0)) { return; }
    $list   = get_theme_mod('magpress_topbar_vreme_orase', 'București,Cluj-Napoca,Iași,Constanța,Craiova,Sibiu');
    $cities = array_filter(array_map('trim', explode(',', $list)));

    $items = magpress_weather_items($cities);
    if ($items === '') { return; }

    $scroll = (int) get_theme_mod('magpress_topbar_vreme_scroll', 0);
    $light  = get_theme_mod('magpress_topbar_vreme_tema', 'inchis') === 'deschis' ? ' mp-wx-bar--light' : '';
    $data   = magpress_weather_data();
    $link   = !empty($data['link'])
        ? '<a class="mp-wx__link" href="' . esc_url($data['link']) . '">Prognoza pe regiuni →</a>'
        : '';

    echo magpress_weather_css();
    echo '<div class="mp-wx-bar' . $light . '" role="complementary" aria-label="Vremea azi pe orașe">'
        . '<div class="mp-wx-bar__inner container">'
        . '<span class="mp-wx-bar__label">⛅ Vremea azi</span>'
        . magpress_weather_ticker($items, $scroll)
        . $link
        . '</div></div>';
}

// Shortcode: [vreme_bar orase="București,Cluj-Napoca,..." link="1" scroll="0" tema="inchis"]
add_shortcode('vreme_bar', function ($atts) {
    $atts = shortcode_atts(['orase' => '', 'link' => '1', 'scroll' => '0', 'tema' => 'inchis'], $atts, 'vreme_bar');
    $cities = array_filter(array_map('trim', explode(',', $atts['orase'])));
    $items  = magpress_weather_items($cities);
    if ($items === '') { return ''; }
    $light = $atts['tema'] === 'deschis' ? ' mp-wx-bar--light' : '';
    $link  = '';
    if ($atts['link'] !== '0') {
        $data = magpress_weather_data();
        if (!empty($data['link'])) {
            $link = '<a class="mp-wx__link" href="' . esc_url($data['link']) . '">Prognoza pe regiuni →</a>';
        }
    }
    return magpress_weather_css()
        . '<div class="mp-wx-bar' . $light . '" role="complementary" aria-label="Vremea azi pe orașe" style="border-radius:8px;overflow:hidden">'
        . '<div class="mp-wx-bar__inner" style="padding-left:1rem;padding-right:1rem">'
        . '<span class="mp-wx-bar__label">⛅ Vremea azi</span>'
        . magpress_weather_ticker($items, $atts['scroll'] === '1')
        . $link . '</div></div>';
});

// Widget clasic (Aspect -> Widgets).
class Magpress_Vreme_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct('magpress_vreme', 'Magpress: Bara Vreme', [
            'description' => 'Vremea pe orașe (ticker), date oficiale ANM (cache 3h).',
        ]);
    }
    public function widget($args, $instance) {
        $orase = !empty($instance['orase']) ? $instance['orase'] : '';
        $html  = do_shortcode('[vreme_bar orase="' . esc_attr($orase) . '"]');
        if ($html === '') { return; }
        echo $args['before_widget'];
        echo $html;
        echo $args['after_widget'];
    }
    public function form($instance) {
        $orase = isset($instance['orase']) ? $instance['orase'] : '';
        ?>
        <p><label for="<?php echo esc_attr($this->get_field_id('orase')); ?>">Orașe (separate prin virgulă, gol = toate):</label>
        <input class="widefat" id="<?php echo esc_attr($this->get_field_id('orase')); ?>" name="<?php echo esc_attr($this->get_field_name('orase')); ?>" type="text" value="<?php echo esc_attr($orase); ?>">
        <small>București, Cluj-Napoca, Iași, Constanța, Craiova, Sibiu, Arad, Botoșani, Râmnicu Vâlcea, Sulina.</small></p>
        <?php
    }
    public function update($new, $old) {
        return ['orase' => sanitize_text_field($new['orase'])];
    }
}
add_action('widgets_init', function () { register_widget('Magpress_Vreme_Widget'); });
function magpress_curs_topbar_output() {
    $show_curs   = (bool) get_theme_mod('magpress_topbar_curs', 0);
    $show_search = (bool) get_theme_mod('magpress_search_in_topbar', 0);
    if (!$show_curs && !$show_search) return;

    // Cursurile (doar dacă e activat + feed disponibil).
    $items = '';
    if ($show_curs) {
        $list = get_theme_mod('magpress_topbar_curs_list', 'EUR,USD');
        $currencies = array_filter(array_map('trim', explode(',', strtoupper($list))));
        $data = magpress_bnr_rates();
        if ($currencies && !empty($data['dates'])) {
            $today = $data['dates'][0];
            $prev  = isset($data['dates'][1]) ? $data['dates'][1] : null;
            foreach ($currencies as $cur) {
                if (!isset($data['rates'][$today][$cur])) continue;
                $now = $data['rates'][$today][$cur];
                $dir = 'flat'; $sym = '';
                if ($prev && isset($data['rates'][$prev][$cur])) {
                    $old = $data['rates'][$prev][$cur];
                    if ($now['value'] > $old['value'])     { $dir = 'up';   $sym = '↑'; }
                    elseif ($now['value'] < $old['value']) { $dir = 'down'; $sym = '↓'; }
                }
                $val = rtrim(rtrim(number_format($now['value'], 4, '.', ''), '0'), '.');
                $mul = (int) $now['multiplier'] > 1 ? (int) $now['multiplier'] . ' ' : '';
                $items .= '<span class="mp-curs-bar__item">' . esc_html($mul . $cur) . ' <b>' . esc_html($val) . '</b>'
                    . ($sym !== '' ? ' <span class="mp-curs-bar__dir mp-curs-bar__dir--' . $dir . '" aria-hidden="true">' . $sym . '</span>' : '')
                    . '</span>';
            }
        }
    }
    // Nimic de arătat (curs pornit dar feed gol, și fără search).
    if ($items === '' && !$show_search) return;

    // Data de azi (cu ziua săptămânii, în română).
    $today_str = date_i18n('l, j F Y', current_time('timestamp'));
    $today_str = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($today_str, 0, 1)) . mb_substr($today_str, 1)
        : ucfirst($today_str);

    $align = esc_attr(get_theme_mod('magpress_topbar_curs_align', 'left'));

    // Link de căutare (deschide același modal ca lupa din header — label pt #magpress-search-toggle).
    $search_html = '';
    if ($show_search) {
        $search_html = '<label for="magpress-search-toggle" class="mp-curs-bar__search" role="button" tabindex="0" aria-label="Caută">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>'
            . '<span>Caută</span></label>';
    }

    $group_html = $items !== ''
        ? '<span class="mp-curs-bar__group"><span class="mp-curs-bar__label">Curs BNR</span>' . $items . '</span>'
        : '<span class="mp-curs-bar__group"></span>';
    ?>
<style id="mp-curs-bar-css">
.mp-curs-bar{background:#14161c;color:#cfd3dc;font-family:var(--f-sans,system-ui,sans-serif);font-size:.82rem;line-height:1}
.mp-curs-bar__inner{display:flex;align-items:center;justify-content:space-between;gap:1.5rem;padding-top:.45rem;padding-bottom:.45rem;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.mp-curs-bar--right .mp-curs-bar__inner{flex-direction:row-reverse}
.mp-curs-bar__inner::-webkit-scrollbar{display:none}
.mp-curs-bar__group{display:inline-flex;align-items:center;gap:1.1rem}
.mp-curs-bar__label{color:#8b91a0;font-weight:700;letter-spacing:.04em;text-transform:uppercase;font-size:.72rem;white-space:nowrap}
.mp-curs-bar__item{white-space:nowrap}
.mp-curs-bar__item b{color:#fff;font-weight:700}
.mp-curs-bar__dir--up{color:#4cc26a}.mp-curs-bar__dir--down{color:#ef5b5b}
.mp-curs-bar__end{display:inline-flex;align-items:center;gap:1rem}
.mp-curs-bar__date{color:#8b91a0;white-space:nowrap}
.mp-curs-bar__search{display:inline-flex;align-items:center;gap:.35rem;cursor:pointer;color:#cfd3dc;font-weight:600;white-space:nowrap;transition:color .15s}
.mp-curs-bar__search:hover{color:#fff}
.mp-curs-bar__search svg{width:14px;height:14px}
<?php if ($show_search): // scoate lupa din header/meniu (ambele layout-uri) ?>
.site-header .search-toggle,.site-nav-bar .search-toggle{display:none!important}
<?php endif; ?>
@media(max-width:768px){.mp-curs-bar{display:none!important}}
</style>
<div class="mp-curs-bar mp-curs-bar--<?php echo $align; ?>" role="complementary" aria-label="Bară informativă">
  <div class="mp-curs-bar__inner container">
    <?php echo $group_html; // curs, construit doar din valori escapate ?>
    <span class="mp-curs-bar__end">
      <span class="mp-curs-bar__date"><?php echo esc_html($today_str); ?></span>
      <?php echo $search_html; ?>
    </span>
  </div>
</div>
    <?php
}
