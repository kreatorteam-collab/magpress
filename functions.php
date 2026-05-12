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
    wp_enqueue_style('magpress-main', MAGPRESS_URI . '/assets/css/style.css', [], MAGPRESS_VERSION);
}
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
 * Injecteaza override-uri CSS din site-config.json (culori, font).
 */
function magpress_inject_site_branding() {
    $cfg = magpress_site_config();
    if (empty($cfg['colors']) && empty($cfg['font_family'])) { return; }

    $css = ':root{';
    if (!empty($cfg['colors']['primary']))    $css .= '--c-primary:'   . esc_attr($cfg['colors']['primary']) . ';';
    if (!empty($cfg['colors']['accent']))     $css .= '--c-accent:'    . esc_attr($cfg['colors']['accent']) . ';';
    if (!empty($cfg['colors']['background'])) $css .= '--c-bg:'        . esc_attr($cfg['colors']['background']) . ';';
    if (!empty($cfg['colors']['text']))       $css .= '--c-text:'      . esc_attr($cfg['colors']['text']) . ';';
    if (!empty($cfg['colors']['text_muted'])) $css .= '--c-text-muted:'. esc_attr($cfg['colors']['text_muted']) . ';';
    if (!empty($cfg['colors']['border']))     $css .= '--c-border:'    . esc_attr($cfg['colors']['border']) . ';';
    if (!empty($cfg['colors']['surface']))    $css .= '--c-surface:'   . esc_attr($cfg['colors']['surface']) . ';';
    if (!empty($cfg['font_family']))          $css .= '--f-sans:'      . esc_attr($cfg['font_family']) . ';';
    $css .= '}';

    echo "<style id='magpress-branding'>{$css}</style>\n";
}
add_action('wp_head', 'magpress_inject_site_branding', 5);

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
 * Categoriile afisate pe homepage (din site-config sau top 3 by post count).
 */
function magpress_homepage_categories() {
    $cfg = magpress_site_config();
    if (!empty($cfg['homepage_categories']) && is_array($cfg['homepage_categories'])) {
        $cats = [];
        foreach ($cfg['homepage_categories'] as $slug) {
            $c = get_category_by_slug($slug);
            if ($c) $cats[] = $c;
        }
        if ($cats) return $cats;
    }
    return get_categories(['orderby' => 'count', 'order' => 'DESC', 'number' => 4, 'hide_empty' => true]);
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
    return $clean;
}
add_filter('body_class', 'magpress_body_classes');
