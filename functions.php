<?php
/**
 * Magpress — funcții temă
 *
 * Filozofie: minimal. LiteSpeed se ocupă de cache/minify, SEOPress de meta/schema.
 * Tema doar emite HTML semantic curat și dezactivează bloat-ul WP default.
 */

if (!defined('ABSPATH')) { exit; }

define('MAGPRESS_VERSION', '0.1.0');
define('MAGPRESS_DIR', get_template_directory());
define('MAGPRESS_URI', get_template_directory_uri());

/**
 * Setup temă.
 */
function magpress_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets']);
    add_theme_support('editor-styles');
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');

    // Dimensiuni imagini optimizate pentru layout-ul magazine.
    add_image_size('magpress-card',    600, 400, true);   // grid 3 coloane
    add_image_size('magpress-feature', 1200, 675, true);  // slider hero (16:9)
    add_image_size('magpress-thumb',   120, 120, true);   // sidebar widgets

    load_theme_textdomain('magpress', MAGPRESS_DIR . '/languages');
}
add_action('after_setup_theme', 'magpress_setup');

/**
 * Înlăturăm bloat-ul WordPress default care strică PageSpeed.
 */
function magpress_remove_bloat() {
    // Emoji script (13KB inutili)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    // oEmbed (~7KB JS dacă nu folosim embed-uri)
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');

    // RSD link + wlwmanifest (XML-RPC era)
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');

    // WP generator (info versiune leak-uită)
    remove_action('wp_head', 'wp_generator');

    // Shortlink (folosim permalink-ul real)
    remove_action('wp_head', 'wp_shortlink_wp_head');

    // Adjacent posts (rel=prev/next în head — duplica SEOPress dacă există)
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

    // Global styles SVG filter (inline pe fiecare pagină dacă nu folosim duotone)
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
    remove_action('wp_footer', 'wp_global_styles_render_svg_filters');
}
add_action('init', 'magpress_remove_bloat');

/**
 * Dezactivăm jQuery pe frontend (nu folosim nimic care îl cere).
 * Dacă vreun plugin îl cere, îl reactivăm condiționat.
 */
function magpress_deregister_jquery() {
    if (!is_admin() && !is_user_logged_in()) {
        wp_deregister_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'magpress_deregister_jquery', 100);

/**
 * Dezactivăm CSS-ul block library default dacă nu folosim blocuri pe pagina curentă.
 * (Pe homepage cu pattern-urile noastre, multe nu sunt necesare.)
 */
function magpress_remove_block_library_css() {
    // Ne bazăm pe theme.json + CSS-ul nostru critical. Dacă vreun bloc necesită stilul,
    // se reactivează manual per template.
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
}
// add_action('wp_enqueue_scripts', 'magpress_remove_block_library_css', 200);
// ↑ comentat momentan, reactivăm după ce testăm că nimic vital nu se sparge.

/**
 * Enqueue CSS-ul temei.
 */
function magpress_enqueue_assets() {
    wp_enqueue_style(
        'magpress-base',
        MAGPRESS_URI . '/assets/css/base.css',
        [],
        MAGPRESS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'magpress_enqueue_assets');

/**
 * Înregistrăm meniurile.
 */
function magpress_register_menus() {
    register_nav_menus([
        'primary'   => __('Meniu principal', 'magpress'),
        'top'       => __('Meniu top (deasupra header-ului)', 'magpress'),
        'footer'    => __('Meniu footer', 'magpress'),
    ]);
}
add_action('init', 'magpress_register_menus');

/**
 * Citește config-ul per-site (culori custom, opțiuni branding).
 * Fișier: /wp-content/themes/magpress-config/site-config.json (NU în git, per-site)
 */
function magpress_site_config() {
    static $config = null;
    if ($config !== null) { return $config; }

    $path = WP_CONTENT_DIR . '/themes/magpress-config/site-config.json';
    if (!file_exists($path)) {
        $config = [];
        return $config;
    }
    $json = file_get_contents($path);
    $config = json_decode($json, true) ?: [];
    return $config;
}

/**
 * Injectează override-uri CSS din site-config.json (culori, font).
 */
function magpress_inject_site_branding() {
    $cfg = magpress_site_config();
    if (empty($cfg['colors']) && empty($cfg['font_family'])) { return; }

    $css = ':root{';
    if (!empty($cfg['colors']['primary']))    { $css .= '--wp--preset--color--primary:' . esc_attr($cfg['colors']['primary']) . ';'; }
    if (!empty($cfg['colors']['accent']))     { $css .= '--wp--preset--color--accent:' . esc_attr($cfg['colors']['accent']) . ';'; }
    if (!empty($cfg['colors']['background'])) { $css .= '--wp--preset--color--background:' . esc_attr($cfg['colors']['background']) . ';'; }
    if (!empty($cfg['colors']['text']))       { $css .= '--wp--preset--color--text:' . esc_attr($cfg['colors']['text']) . ';'; }
    if (!empty($cfg['font_family']))          { $css .= '--wp--preset--font-family--system:' . esc_attr($cfg['font_family']) . ';'; }
    $css .= '}';

    echo "<style id='magpress-branding'>{$css}</style>\n";
}
add_action('wp_head', 'magpress_inject_site_branding', 5);
