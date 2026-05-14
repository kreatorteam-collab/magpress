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
    // Google Fonts — Open Sans (400, 600, 700, 800) cu display=swap
    // Configurabil via Customizer (magpress_google_font); gol = nu se incarca
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

    $css_path = MAGPRESS_DIR . '/assets/css/style.css';
    $ver = file_exists($css_path) ? filemtime($css_path) : MAGPRESS_VERSION;
    wp_enqueue_style('magpress-main', MAGPRESS_URI . '/assets/css/style.css', [], $ver);

    // Branding CSS — atasat dupa style.css (cascade-ul il face sa castige peste defaults)
    $branding = magpress_get_branding_css();
    if ($branding) {
        wp_add_inline_style('magpress-main', $branding);
    }
}

/**
 * Preconnect la Google Fonts (mic boost PageSpeed) — atat CSS-ul cat si fisierele woff2.
 */
function magpress_preconnect_google_fonts($urls, $relation_type) {
    if (get_theme_mod('magpress_google_font', 'Open Sans:400,700') === '') return $urls;
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
        'footer_bg'       => '#f6f7f5',
        'footer_text'     => '#6b6b6b',
        'footer_link'     => '#1a1a1a',
        'footer_border'   => '#e6e6e6',
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
    return $css === ':root{}' ? '' : $css;
}

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
        'accent'          => ['Accent (highlight, butoane, badge-uri)',     '#6fa84a'],
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
        'footer_bg'       => ['Footer — background',                         '#f6f7f5'],
        'footer_text'     => ['Footer — text',                               '#6b6b6b'],
        'footer_link'     => ['Footer — linkuri',                            '#1a1a1a'],
        'footer_border'   => ['Footer — culoare linii (deasupra + sub coloane)', '#e6e6e6'],
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

    // ---------- Sectiune: Homepage (12 sloturi flexibile) ----------
    $wp_customize->add_section('magpress_homepage', [
        'title'       => 'Magpress: Homepage',
        'description' => '12 sloturi configurabile, în ordinea afișării. Fiecare slot poate fi: rând cu articole dintr-o categorie SAU bloc cu cod HTML/AdSense (banner, reclamă).',
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

    for ($i = 1; $i <= 12; $i++) {
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
        $wp_customize->add_control("magpress_slot_{$i}_cat", [
            'label'           => "Slot {$i} — Categorie",
            'section'         => 'magpress_homepage',
            'type'            => 'select',
            'choices'         => $cat_choices,
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
         . '.featured-card::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.85),rgba(0,0,0,.4) 50%,transparent 80%);z-index:2}'
         . '.featured-card__content{position:absolute;left:0;right:0;bottom:0;z-index:3;padding:clamp(1.25rem,3vw,2rem);color:#fff}'
         . '.featured-card__title{margin:.75rem 0 0;font-size:clamp(1.15rem,2vw,1.65rem);line-height:1.25;color:#fff;font-weight:800}'
         . '.skip-link{position:absolute;left:-9999px}';
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
 * Sloturi homepage configurate in Customizer (12 sloturi).
 * Fiecare slot poate fi: 'category' (rand articole) sau 'ad' (HTML/AdSense).
 * Returns: array de ['type' => 'category'|'ad', ...payload...]
 */
function magpress_homepage_slots() {
    $slots = [];
    for ($i = 1; $i <= 12; $i++) {
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

    $ad_html = '<div class="ad-slot ad-slot--mid-article">' . $ad . '</div>';
    $injected = implode('</p>', array_slice($parts, 0, 3)) . '</p>' . $ad_html . implode('</p>', array_slice($parts, 3));
    return $injected;
}
add_filter('the_content', 'magpress_inject_mid_article_ad', 20);

/**
 * Returneaza HTML-ul reclamei de la sfarsitul articolului (apelat din single.php).
 */
function magpress_get_end_article_ad() {
    $ad = trim((string) get_theme_mod('magpress_article_ad_end', ''));
    if ($ad === '') return '';
    return '<div class="ad-slot ad-slot--end-article">' . $ad . '</div>';
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
        // Force re-check: stergem cache-ul + transient-ul WP de update themes
        delete_transient('magpress_update_info');
        delete_site_transient('update_themes');
        wp_update_themes(); // declanseaza re-check

        $info = get_transient('magpress_update_info');
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

    $current_version = wp_get_theme()->get('Version');
    $last_modified   = magpress_last_modified_timestamp();
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
