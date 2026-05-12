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
    $google_font = trim((string) get_theme_mod('magpress_google_font', 'Open Sans:400,600,700,800'));
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
}

/**
 * Preconnect la Google Fonts (mic boost PageSpeed).
 */
function magpress_preconnect_google_fonts($urls, $relation_type) {
    if ($relation_type === 'preconnect' && get_theme_mod('magpress_google_font', 'Open Sans:400,600,700,800') !== '') {
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'magpress_preconnect_google_fonts', 10, 2);
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
        'primary'     => '#1a1a1a',
        'accent'      => '#6fa84a',
        'background'  => '#ffffff',
        'text'        => '#1a1a1a',
        'text_muted'  => '#6b6b6b',
        'border'      => '#e6e6e6',
        'surface'     => '#f6f7f5',
        'heading_h1'  => '#1a1a1a',
        'heading_h2'  => '#1a1a1a',
        'heading_h3'  => '#1a1a1a',
        'link'        => '#6fa84a',
        'link_hover'  => '#1a1a1a',
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
 * Injecteaza override-uri CSS pentru culori si font.
 */
function magpress_inject_site_branding() {
    $defaults = magpress_default_colors();
    $css = ':root{';
    foreach ($defaults as $key => $default) {
        $val = magpress_color($key);
        if ($val && $val !== $default) {
            $cssVar = $key === 'background' ? '--c-bg' : '--c-' . str_replace('_', '-', $key);
            $css .= $cssVar . ':' . esc_attr($val) . ';';
        }
    }
    $font = get_theme_mod('magpress_font_family');
    if (!$font) {
        $cfg = magpress_site_config();
        if (!empty($cfg['font_family'])) $font = $cfg['font_family'];
    }
    if ($font) $css .= '--f-sans:' . esc_attr($font) . ';';
    $css .= '}';

    if ($css !== ':root{}') {
        echo "<style id='magpress-branding'>{$css}</style>\n";
    }
}
add_action('wp_head', 'magpress_inject_site_branding', 5);

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
        'accent'      => ['Accent (highlight, butoane, badge-uri)',     '#6fa84a'],
        'primary'     => ['Primary (text închis, header bar)',          '#1a1a1a'],
        'text'        => ['Text principal',                              '#1a1a1a'],
        'text_muted'  => ['Text secundar (meta, sub-titluri)',           '#6b6b6b'],
        'background'  => ['Background pagină',                           '#ffffff'],
        'surface'     => ['Surface (newsletter, footer)',                '#f6f7f5'],
        'border'      => ['Border (linii subțiri, chenare)',             '#e6e6e6'],
        'heading_h1'  => ['Titluri H1 (articol, archive)',               '#1a1a1a'],
        'heading_h2'  => ['Titluri H2 (subtitluri, section headers)',    '#1a1a1a'],
        'heading_h3'  => ['Titluri H3 (sub-sub-titluri, carduri)',       '#1a1a1a'],
        'link'        => ['Link (text articol)',                         '#6fa84a'],
        'link_hover'  => ['Link hover',                                   '#1a1a1a'],
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

    // ---------- Sectiune: Homepage (sloturi categorii) ----------
    $wp_customize->add_section('magpress_homepage', [
        'title'       => 'Magpress: Homepage',
        'description' => 'Configurează ce categorii apar pe homepage și câte articole din fiecare. Lasă categoria goală ca să sari peste un slot.',
        'priority'    => 27,
    ]);

    // Construim alegerile pentru dropdown-uri
    $cat_choices = ['0' => '— Nicio categorie (sari peste slot) —'];
    foreach (get_categories(['hide_empty' => false, 'number' => 100, 'orderby' => 'name']) as $c) {
        $cat_choices[(string) $c->term_id] = $c->name;
    }

    for ($i = 1; $i <= 6; $i++) {
        $wp_customize->add_setting("magpress_homepage_cat_{$i}", [
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_homepage_cat_{$i}", [
            'label'   => "Slot {$i} — Categorie",
            'section' => 'magpress_homepage',
            'type'    => 'select',
            'choices' => $cat_choices,
        ]);

        $wp_customize->add_setting("magpress_homepage_count_{$i}", [
            'default'           => 8,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_homepage_count_{$i}", [
            'label'       => "Slot {$i} — Număr articole",
            'description' => 'Recomandat: 4 sau 8 (multipli de 4 pentru aspect curat). Min 2, max 20.',
            'section'     => 'magpress_homepage',
            'type'        => 'number',
            'input_attrs' => ['min' => 2, 'max' => 20, 'step' => 1],
        ]);
    }

    $wp_customize->add_setting('magpress_google_font', [
        'default'           => 'Open Sans:400,600,700,800',
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
 * Sloturi homepage configurate in Customizer.
 * Returns: array de [['cat' => WP_Term, 'count' => int], ...]
 * Fallback: top 4 categorii cu 8 articole fiecare.
 */
function magpress_homepage_slots() {
    $slots = [];
    for ($i = 1; $i <= 6; $i++) {
        $cat_id = (int) get_theme_mod("magpress_homepage_cat_{$i}", 0);
        $count  = (int) get_theme_mod("magpress_homepage_count_{$i}", 8);
        if ($cat_id <= 0) continue;
        $cat = get_category($cat_id);
        if (!$cat || is_wp_error($cat)) continue;
        $slots[] = ['cat' => $cat, 'count' => max(2, min(20, $count))];
    }
    if ($slots) return $slots;

    // Fallback: site-config.json
    $cfg = magpress_site_config();
    if (!empty($cfg['homepage_categories']) && is_array($cfg['homepage_categories'])) {
        foreach ($cfg['homepage_categories'] as $slug) {
            $c = get_category_by_slug($slug);
            if ($c) $slots[] = ['cat' => $c, 'count' => 8];
        }
        if ($slots) return $slots;
    }

    // Fallback final: top 4 categorii
    $top = get_categories(['orderby' => 'count', 'order' => 'DESC', 'number' => 4, 'hide_empty' => true]);
    foreach ($top as $c) $slots[] = ['cat' => $c, 'count' => 8];
    return $slots;
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
