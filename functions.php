<?php
/**
 * Magpress v0.3 — tema clasica PHP.
 *
 * Filozofie: HTML emis 1:1 de noi, fara FSE. LiteSpeed face cache/minify, SEOPress face meta/schema.
 * Customizarea per-site se face prin /wp-content/themes/magpress-config/site-config.json
 */

if (!defined('ABSPATH')) { exit; }

define('MAGPRESS_VERSION', '0.9.228');
/**
 * Prag implicit pentru „Actualizat la": modificările ANTERIOARE acestei date nu se
 * afișează. E data la care a intrat regula (v0.9.188) — tot ce era înainte pe flotă
 * e istoric de operațiuni în masă, nu editare editorială. Se poate schimba per site
 * din Customizer (`magpress_updated_since`); valoarea `0` dezactivează pragul.
 */
define('MAGPRESS_UPDATED_SINCE_DEFAULT', '2026-08-06');
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
 * v0.9.208 — `global-styles-inline-css` (9,3 KB de CSS theme.json) ajungea TOTUSI in
 * <head> pe fiecare pagina, desi il scoteam din coada mai sus: WordPress il re-inregistreaza
 * din propriile hook-uri, dupa ce ruleaza dequeue-ul nostru. Le scoatem la sursa.
 * Tema e clasica (fara theme.json propriu, fara blocuri in template-uri), deci CSS-ul acela
 * nu stilizeaza nimic din ce afisam. Reactivare per site: filtrul de mai jos pe `false`.
 */
add_action('init', function () {
    if (is_admin()) return;
    if (!apply_filters('magpress_strip_global_styles', true)) return;
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles_custom_css');
    remove_action('wp_head', 'wp_enqueue_global_styles', 8);
}, 20);

/**
 * v0.9.208 — `Link: <...>; rel=shortlink` mai iesea in headerele HTTP
 * (in <head> era deja scos in magpress_remove_bloat).
 */
remove_action('template_redirect', 'wp_shortlink_header', 11);

/**
 * Enqueue CSS tema.
 */
function magpress_enqueue_assets() {
    // News font preset — dacă e setat (≠ default), override-uiește magpress_google_font + adaugă CSS pentru h1-h3/body
    $preset    = magpress_news_font_preset_config();
    $menu_font = magpress_menu_font_config();

    // UN SINGUR request Google Fonts (base preset/default + eventual fontul de meniu, combinate).
    $font_url = '';
    if ($preset) {
        $font_url = 'https://fonts.googleapis.com/css2?family=' . $preset['google'] . '&display=swap';
    } else {
        $google_font = trim((string) get_theme_mod('magpress_google_font', 'Open Sans:400,700'));
        if ($google_font !== '') {
            $font_url = 'https://fonts.googleapis.com/css2?' . http_build_query([
                'family'  => $google_font,
                'display' => 'swap',
            ], '', '&', PHP_QUERY_RFC3986);
            // http_build_query inlocuieste ":" cu "%3A" — corectam pentru sintaxa css2
            $font_url = str_replace('%3A', ':', $font_url);
        }
    }
    // Fontul de meniu intră în ACELAȘI request (nu mai e un al doilea hit la fonts.googleapis.com).
    if ($menu_font) {
        $font_url = ($font_url !== '')
            ? str_replace('&display=swap', '&family=' . $menu_font['google'] . '&display=swap', $font_url)
            : 'https://fonts.googleapis.com/css2?family=' . $menu_font['google'] . '&display=swap';
    }
    if ($font_url !== '') {
        wp_enqueue_style('magpress-google-font', $font_url, [], null);
    }

    // v0.9.220: URL cu versiunea in NUMELE fisierului, nu in `?ver` — LiteSpeed are
    // optiunea „Remove Query Strings" si taia query-ul, deci URL-ul ramanea identic
    // dupa fiecare deploy si Cloudflare servea la nesfarsit continutul vechi.
    list($css_url, $ver) = magpress_main_css_src();
    wp_enqueue_style('magpress-main', $css_url, [], $ver);

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

    // Font meniu (opțional, stil știri) — aplică fontul DOAR pe navigație (fontul e deja în request-ul combinat de mai sus).
    if ($menu_font) {
        // Fonturile de tip știri sunt condensate → citesc mic; mărim ușor pt prezență (stil Mediafax).
        // Fonturile condensate (Oswald etc.) la weight 700 + spațiere mică „își lipesc" literele pe
        // fundal colorat → weight 600 + letter-spacing dublu = vizibil mai lizibil, același look de știri.
        wp_add_inline_style('magpress-main', '.primary-nav .menu a,.mobile-menu .menu a{font-family:' . $menu_font['family'] . '!important;font-size:1rem!important;font-weight:600!important;letter-spacing:.08em!important}');
    }
}

/**
 * v0.9.219 — CSS-ul temei NU mai intra in combinarea LiteSpeed.
 *
 * De ce: LiteSpeed regenereaza fisierul combinat cu ACELASI nume si acelasi `?ver`
 * atunci cand se schimba doar CONTINUTUL surselor (hash-ul e peste lista de fisiere,
 * nu peste continut). Cloudflare tine acel URL cu max-age de un an, deci vizitatorii
 * primeau CSS vechi la nesfarsit, iar pe unele site-uri modificarile nu se vedeau
 * niciodata. Dovedit: acelasi URL, luat cu cheie unica, avea regulile noi; servit
 * din cache, nu le avea.
 *
 * `data-no-optimize="1"` e marcajul pe care LiteSpeed il respecta indiferent de
 * versiune. Asa, style.css se serveste de la URL-ul lui, cu `?ver=filemtime`, care
 * se schimba la FIECARE deploy -> niciun cache nu mai poate servi ceva vechi.
 * Cost: o cerere HTTP in plus (fisier cache-abil un an, pe HTTP/2).
 */
/**
 * URL-ul CSS-ului principal, cu versiunea in numele fisierului
 * (assets/css/style-0.9.220.css), copiat o singura data din style.css.
 *
 * Motivul: `?ver=` nu supravietuieste — LiteSpeed poate scoate query string-urile,
 * iar fisierul combinat isi pastra numele cand se schimba doar continutul. Ambele
 * duceau la acelasi lucru: URL neschimbat, deci Cloudflare (max-age un an) si
 * browserele serveau CSS vechi si modificarile nu se vedeau pe o parte din flota.
 * Cu versiunea in nume, fiecare release are alt URL, pe care niciun cache nu-l are.
 *
 * Daca directorul temei nu e scriabil, cadem inapoi pe style.css + ?ver=filemtime.
 */
function magpress_main_css_src() {
    static $cached = null;
    if ($cached !== null) return $cached;

    $dir  = MAGPRESS_DIR . '/assets/css';
    $src  = $dir . '/style.css';
    $ver  = file_exists($src) ? (string) filemtime($src) : MAGPRESS_VERSION;
    $name = 'style-' . MAGPRESS_VERSION . '.css';
    $dst  = $dir . '/' . $name;

    if (!file_exists($dst) || @filemtime($dst) < @filemtime($src)) {
        foreach (glob($dir . '/style-*.css') ?: [] as $old) { @unlink($old); } // variantele vechi
        if (!@copy($src, $dst)) {
            return ($cached = [MAGPRESS_URI . '/assets/css/style.css', $ver]);
        }
    }
    return ($cached = [MAGPRESS_URI . '/assets/css/' . $name, null]);
}

function magpress_style_no_optimize($html, $handle) {
    if ($handle !== 'magpress-main') return $html;
    if (strpos($html, 'data-no-optimize') !== false) return $html;
    return str_replace('<link ', '<link data-no-optimize="1" ', $html);
}
add_filter('style_loader_tag', 'magpress_style_no_optimize', 10, 2);

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

    // Marimea trebuie sa fie EXACT cea randata, altfel browserul descarca poza de doua ori:
    // single.php foloseste magpress-hero, homepage-ul magpress-feature.
    $preload_size = (is_singular('post') || is_page()) ? 'magpress-hero' : 'magpress-feature';

    $src = wp_get_attachment_image_url($img_id, $preload_size);
    if (!$src) return;

    $srcset = wp_get_attachment_image_srcset($img_id, $preload_size);
    $sizes  = wp_get_attachment_image_sizes($img_id, $preload_size);

    echo '<link rel="preload" as="image" href="' . esc_url($src) . '"';
    if ($srcset) echo ' imagesrcset="' . esc_attr($srcset) . '"';
    if ($sizes)  echo ' imagesizes="' . esc_attr($sizes) . '"';
    echo ' fetchpriority="high">' . "\n";
}
add_action('wp_head', 'magpress_preload_lcp_image', 2);
add_action('wp_enqueue_scripts', 'magpress_enqueue_assets');

/**
 * PRAG MINIM pentru intervalele de cron (v0.9.186).
 *
 * Unele pluginuri își declară joburi la 30–60 de secunde (ex. `nsi_check_pending_images`
 * la 30s, `pinpilot_tick` la 60s). Pe site-urile unde WP-cron NU e mutat pe cron de
 * server, WordPress încearcă să pornească cron-ul la vizitele reale → fiecare vizitator
 * plătește costul. Ridicăm orice interval sub 5 minute la 5 minute.
 *
 * Nu atinge intervalele WP core (hourly/twicedaily/daily/weekly, toate peste prag).
 * Evenimentele deja programate preiau noul interval după prima reprogramare.
 * Per site se poate ajusta cu filtrul `magpress_cron_min_interval` (0 = dezactivat).
 */
function magpress_cron_interval_floor($schedules) {
    $floor = (int) apply_filters('magpress_cron_min_interval', 5 * MINUTE_IN_SECONDS);
    if ($floor <= 0 || !is_array($schedules)) return $schedules;

    foreach ($schedules as $name => $s) {
        if (!empty($s['interval']) && (int) $s['interval'] > 0 && (int) $s['interval'] < $floor) {
            $schedules[$name]['interval'] = $floor;
        }
    }
    return $schedules;
}
add_filter('cron_schedules', 'magpress_cron_interval_floor', 9999);

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
        'menu_link_active' => '',
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

    // Link ACTIV în meniu (v0.9.166) — culoare + liniuță sub pagina curentă.
    // OPT-IN per site: emitem regula DOAR dacă site-ul a ales o culoare în Customizer,
    // altfel meniul rămâne exact ca înainte pe toată flota.
    $menu_active = magpress_color('menu_link_active');
    if ($menu_active) {
        $ma = esc_attr($menu_active);
        // Culoare pe desktop + mobil; liniuța = ::after DREPT (nu box-shadow, care
        // urmărește border-radius-ul link-ului și iese curbată la colțuri).
        $css .= '.primary-nav .menu > li.current-menu-item > a,'
              . '.primary-nav .menu > li.current-menu-ancestor > a,'
              . '.primary-nav .menu > li.current_page_item > a,'
              . '.mobile-menu .menu li.current-menu-item > a{'
              . 'color:' . $ma . ' !important}';
        $css .= '.primary-nav .menu > li.current-menu-item > a,'
              . '.primary-nav .menu > li.current-menu-ancestor > a,'
              . '.primary-nav .menu > li.current_page_item > a{position:relative}';
        $css .= '.primary-nav .menu > li.current-menu-item > a::after,'
              . '.primary-nav .menu > li.current-menu-ancestor > a::after,'
              . '.primary-nav .menu > li.current_page_item > a::after{'
              . 'content:"";position:absolute;left:.9rem;right:.9rem;bottom:1px;'
              . 'height:2px;background:' . $ma . '}';
    }

    // Logo max-height — SCALARE PROPORȚIONALĂ (v0.9.165). Valoarea e o ancoră: 60 =
    // mărimea standard. Defaulturile reale diferă pe layout (inline 48px, centered
    // 110px desktop / 54px mobil), deci emiterea valorii BRUTE făcea saltul 110→50
    // („foarte mic") la o schimbare de 10. Acum scalăm toate cele 3 mărimi cu
    // factorul valoare/60, deci 50 = ~17% mai mic peste tot, liniar, fără salt la 60.
    $logo_h = max(20, min(200, (int) get_theme_mod('magpress_logo_max_height', 60)));
    if ($logo_h !== 60) {
        $f = $logo_h / 60;
        $inline_h  = (int) round(48 * $f);
        $cent_desk = (int) round(110 * $f);
        $cent_mob  = (int) round(54 * $f);
        $css .= '.site-branding .site-logo{max-height:' . $inline_h . 'px !important}';
        $css .= '.site-header--centered .site-header__top .site-logo{max-height:' . $cent_desk . 'px !important}';
        // Mobilul centered are propriul default (54px) — media query DUPĂ regula de
        // desktop ca să câștige prin ordine (aceeași specificitate + !important).
        $css .= '@media (max-width:768px){.site-header--centered .site-header__top .site-logo{max-height:' . $cent_mob . 'px !important}}';
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
    /**
     * Buton „Resetează homepage-ul" (v0.9.190). Nu are setare proprie: doar randează
     * butonul, iar JS-ul din magpress_customize_controls_js() readuce cele 30 de sloturi
     * la valorile implicite. Nimic nu se salvează până la „Publică".
     */
    class Magpress_Reset_Home_Control extends WP_Customize_Control {
        public $type = 'magpress_reset_home';
        public function render_content() {
            // Discret, în stilul nativ WP pentru acțiuni distructive (link, nu buton mare):
            // explicația lungă stă în dialogul de confirmare, nu în bara laterală.
            ?>
            <div class="magpress-reset-home">
                <button type="button" class="button-link" id="magpress-reset-home">
                    <?php esc_html_e('↺ Resetează homepage-ul', 'magpress'); ?>
                </button>
                <span class="magpress-reset-home__hint"><?php esc_html_e('cele 30 de sloturi revin la valorile implicite', 'magpress'); ?></span>
                <p id="magpress-reset-home-msg"></p>
            </div>
            <style>
            .magpress-reset-home{margin:0 0 4px}
            .magpress-reset-home #magpress-reset-home{color:#b32d2e;font-weight:600;text-decoration:none;padding:0}
            .magpress-reset-home #magpress-reset-home:hover,
            .magpress-reset-home #magpress-reset-home:focus{color:#8a2424;text-decoration:underline}
            .magpress-reset-home__hint{display:block;color:#787c82;font-size:11px;line-height:1.5;margin-top:2px}
            .magpress-reset-home #magpress-reset-home-msg:empty{display:none}
            .magpress-reset-home #magpress-reset-home-msg{margin:6px 0 0;font-size:12px;line-height:1.5}
            </style>
            <?php
        }
    }

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
        initReset();
    });

    /**
     * v0.9.190 — „Resetează homepage-ul": readuce toate cele 30 de sloturi la valorile
     * din fabrică (primele 4 = categorie, restul goale). Resetarea se face DOAR în
     * interfață: nimic nu se schimbă pe site până nu apeși „Publică", deci poți ieși
     * din Customizer fără să salvezi și rămâne totul cum era.
     */
    function initReset() {
        var btn = document.getElementById('magpress-reset-home');
        if (!btn) return;
        var msg = document.getElementById('magpress-reset-home-msg');

        btn.addEventListener('click', function(e){
            e.preventDefault();
            if (!window.confirm(
                'Resetezi homepage-ul la valorile din fabrică?\n\n' +
                '• primele 4 sloturi pe „Categorie", fără categorii bifate\n' +
                '• sloturile 5-30 goale\n' +
                '• layout Grid, 8 articole, fără coduri de reclamă\n' +
                '• se șterge titlul H1 personalizat\n\n' +
                'Nimic nu se schimbă pe site până nu apeși „Publică". Dacă închizi Customizer-ul fără să publici, rămâne totul cum era.'
            )) return;

            var n = 0;
            function put(id, val) {
                var s = wp.customize(id);
                if (s && s.get() !== val) { s.set(val); n++; }
            }
            for (var i = 1; i <= SLOT_COUNT; i++) {
                put('magpress_slot_' + i + '_type', i <= 4 ? 'category' : 'none');
                put('magpress_slot_' + i + '_cat', '');
                put('magpress_slot_' + i + '_count', 8);
                put('magpress_slot_' + i + '_layout', 'grid');
                put('magpress_slot_' + i + '_ad', '');
                put('magpress_slot_' + i + '_html_title', '');
                put('magpress_slot_' + i + '_html_subtitle', '');
            }
            put('magpress_home_h1_text', '');
            put('magpress_home_h1_visible', 1);
            updateMarkers();

            if (msg) {
                msg.style.color = '#00a32a';
                msg.textContent = n === 0
                    ? 'Homepage-ul era deja la valorile implicite.'
                    : n + ' setări resetate. Apasă „Publică" ca să se aplice pe site.';
            }
        });
    }
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

    $wp_customize->add_setting('magpress_boxed_layout', [
        'default'           => magpress_boxed_default(),
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_boxed_layout', [
        'label'       => 'Latime site: boxed',
        'description' => 'Site-ul devine o foaie alba centrata pe fundal gri (90% din ecran, maxim 1560px), in loc sa se intinda pe tot ecranul. Sub 1400px se dezactiveaza singur, deci pe laptopurile mici, tablete si mobil arata exact ca acum.',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
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
        'description' => 'Afișează CTA pentru a marca site-ul ca sursă preferată în Google Discover. Apare pe homepage (poziția de mai jos) și pe articol (înainte de comentarii).',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_discover_position', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_discover_position', [
        'label'       => 'Box Google Discover — după ce slot apare (homepage)',
        'description' => '0 = poziția standard (sub titlul H1, deasupra sloturilor). 1 = după Slot 1, 2 = după Slot 2 etc. (sloturile de reclamă se numără și ele). Număr mai mare decât sloturile active = jos de tot.',
        'section'     => 'magpress_layout',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
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

    // Recomandate in categorie (v0.9.199)
    $wp_customize->add_setting('magpress_catfeat_enable', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_catfeat_enable', [
        'label'       => 'Articole recomandate în categorie',
        'description' => 'Afișează sus, în pagina categoriei, articolele bifate „Recomandat în categorie” din editor. Fără articole bifate nu se schimbă nimic.',
        'section'     => 'magpress_layout',
        'type'        => 'checkbox',
    ]);
    // Imagine implicita globala pentru articole fara poza (v0.9.205)
    $wp_customize->add_setting('magpress_default_thumb', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'magpress_default_thumb', [
        'label'       => 'Imagine implicita pentru articole fara poza',
        'description' => 'Rezerva globala. Daca o categorie are propria imagine implicita (Articole - Categorii), aceea are prioritate.',
        'section'     => 'magpress_layout',
        'mime_type'   => 'image',
    ]));

    $wp_customize->add_setting('magpress_catfeat_layout', [
        'default'           => 'big',
        'sanitize_callback' => function ($v) {
            return in_array($v, ['auto', 'grid', 'mosaic', 'big', 'strip', 'compact'], true) ? $v : 'auto';
        },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_catfeat_layout', [
        'label'       => 'Recomandate în categorie — așezare',
        'description' => 'Implicit Mare + lista: primul articol mare, restul intr-o lista compacta alaturi. Auto alege singur dupa cate articole sunt bifate (1-4 grila, 5-6 mozaic, 7-8 mare + lista, 9+ compact).',
        'section'     => 'magpress_layout',
        'type'        => 'select',
        'choices'     => [
            'auto'    => 'Auto (dupa cate articole sunt)',
            'grid'    => 'Grilă egală',
            'mosaic'  => 'Mozaic (1 mare + restul mici)',
            'big'     => 'Mare + listă',
            'strip'   => 'Bandă derulabilă',
            'compact' => 'Compact, două coloane',
        ],
    ]);

    $wp_customize->add_setting('magpress_catfeat_limit', [
        'default'           => 12,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_catfeat_limit', [
        'label'       => 'Recomandate în categorie — câte articole',
        'description' => 'Cate articole bifate urca sus, per categorie. Implicit 12 = practic toate. Pune 3 daca vrei un podium fix de 3 carduri; ce depaseste numarul ramane in grila normala.',
        'section'     => 'magpress_layout',
        'type'        => 'number',
        'input_attrs' => ['min' => 1, 'max' => 12, 'step' => 1],
    ]);

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
        'label'       => 'Mărime logo (60 = standard)',
        'description' => 'Scalare proporțională: 60 = mărimea standard a layoutului; sub 60 = mai mic, peste 60 = mai mare (ex. 50 ≈ −17%, 90 = +50%). Se aplică pe desktop și mobil, pe ambele layouturi.',
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
        'menu_link_active' => ['Meniu — link ACTIV (pagina curentă) + liniuță', ''],
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

    // Buton de resetare (v0.9.190) — sus, înaintea sloturilor. Setarea e „fantomă":
    // controlul nu salvează nimic, doar declanșează resetarea în interfață.
    $wp_customize->add_setting('magpress_reset_home_noop', [
        'default'           => '',
        'sanitize_callback' => '__return_empty_string',
        'transport'         => 'postMessage',
    ]);
    if (class_exists('Magpress_Reset_Home_Control')) {
        $wp_customize->add_control(new Magpress_Reset_Home_Control($wp_customize, 'magpress_reset_home_noop', [
            'section'  => 'magpress_homepage',
            'priority' => 1,
        ]));
    }

    // Titlu H1 homepage — editabil + vizibil (centrat) sau ascuns pentru SEO.
    $wp_customize->add_setting('magpress_home_h1_text', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_home_h1_text', [
        'label'       => 'Titlu H1 homepage',
        'description' => 'Textul titlului principal (H1) de pe prima pagină. Lasă gol = numele site-ului + descrierea.',
        'section'     => 'magpress_homepage',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_home_h1_visible', [
        'default'           => 1,
        'sanitize_callback' => function($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_home_h1_visible', [
        'label'       => 'Afișează titlul H1 vizibil (centrat)',
        'description' => 'Debifat = titlul rămâne DOAR pentru SEO (invizibil pe pagină).',
        'section'     => 'magpress_homepage',
        'type'        => 'checkbox',
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

        // 3c. EXTENSIE slot — inca un bloc de articole din ACELEASI categorii, sub acelasi
        //     titlu de sectiune (fara antet repetat). Se alege doar designul si offsetul.
        $wp_customize->add_setting("magpress_slot_{$i}_ext_layout", [
            'default'           => 'none',
            'sanitize_callback' => function($v) {
                return in_array($v, ['none', 'grid', 'asymmetric', 'hero', 'list'], true) ? $v : 'none';
            },
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_ext_layout", [
            'label'           => "Slot {$i} — Extensie (al doilea bloc)",
            'description'     => 'Inca un bloc de articole din aceleasi categorii, sub acelasi titlu — fara sa se repete antetul sectiunii. Numarul de articole vine din design: Grid 4, Asimetric 5, Hero 4, Lista 5.',
            'section'         => 'magpress_homepage',
            'type'            => 'select',
            'choices'         => [
                'none'       => '— Fara extensie —',
                'grid'       => 'Grid 4 coloane (carduri egale)',
                'asymmetric' => 'Asimetric (1 mare + 4 mici)',
                'hero'       => 'Hero (4 carduri portret cu overlay)',
                'list'       => 'Lista (imagine stanga + text dreapta)',
            ],
            'active_callback' => function() use ($i) {
                $default_type = $i <= 4 ? 'category' : 'none';
                return get_theme_mod("magpress_slot_{$i}_type", $default_type) === 'category';
            },
        ]);

        $wp_customize->add_setting("magpress_slot_{$i}_ext_offset", [
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ]);
        $wp_customize->add_control("magpress_slot_{$i}_ext_offset", [
            'label'           => "Slot {$i} — Extensie: sari peste primele N articole",
            'description'     => '0 = continua exact de unde s-a oprit blocul de sus (recomandat). Ex: 12 = extensia incepe de la al 13-lea articol al categoriei.',
            'section'         => 'magpress_homepage',
            'type'            => 'number',
            'input_attrs'     => ['min' => 0, 'max' => 200, 'step' => 1],
            'active_callback' => function() use ($i) {
                $default_type = $i <= 4 ? 'category' : 'none';
                return get_theme_mod("magpress_slot_{$i}_type", $default_type) === 'category'
                    && get_theme_mod("magpress_slot_{$i}_ext_layout", 'none') !== 'none';
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

    // ---------- Sectiune: Reclame ----------
    $wp_customize->add_section('magpress_article_ads', [
        'title'       => 'Magpress: Reclame',
        'description' => 'Coduri HTML/AdSense pentru paginile de categorie/tag și pentru articole. Lasă gol pentru a nu afișa.',
        'priority'    => 28,
    ]);

    // Reclama de sus de pe categorii/taguri: are COD PROPRIU. Lasata goala, cade
    // inapoi pe codul din „Reclamă după paragraful 3" (asa mergea pana la 0.9.196,
    // deci site-urile care aveau deja reclama pe categorii nu o pierd).
    $wp_customize->add_setting('magpress_category_ad_top', [
        'default'           => '',
        'sanitize_callback' => function($v) { return is_string($v) ? $v : ''; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_category_ad_top', [
        'label'       => 'Reclamă sus pe categorii',
        'description' => 'Cod AdSense / HTML afișat pe paginile de categorie și de tag, sub titlu și descriere, imediat înaintea grilei de articole. Lasă gol ca să se folosească automat codul de la „Reclamă după paragraful 3" (comportamentul de până acum). Scrie <code>nu</code> ca să nu apară nicio reclamă pe categorii.',
        'section'     => 'magpress_article_ads',
        'type'        => 'textarea',
        'priority'    => 5,
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
    // ---------- Sectiune: Bara newsletter (homepage) ----------
    $wp_customize->add_section('magpress_nlbar', [
        'title'    => 'Magpress: Bara newsletter',
        'priority' => 45,
    ]);
    $wp_customize->add_setting('magpress_nlbar_enabled', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_enabled', [
        'label'       => 'Afișează bara de newsletter pe homepage',
        'description' => 'Bară orizontală: plic + text + formular email, opțional cu butonul „sursă preferată în Google".',
        'section'     => 'magpress_nlbar',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_skin', [
        'default'           => 'dark',
        'sanitize_callback' => 'sanitize_key',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_skin', [
        'label'   => 'Aspect',
        'section' => 'magpress_nlbar',
        'type'    => 'select',
        'choices' => [
            'dark'  => 'Închis (fundal închis, text alb)',
            'light' => 'Deschis (fundal deschis, text închis)',
        ],
    ]);
    $wp_customize->add_setting('magpress_nlbar_text', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_text', [
        'label'       => 'Textul din bară',
        'description' => 'Gol = text implicit cu numele site-ului. Poți folosi &lt;strong&gt;.',
        'section'     => 'magpress_nlbar',
        'type'        => 'textarea',
    ]);
    $wp_customize->add_setting('magpress_nlbar_btn', [
        'default'           => 'Abonează-te',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_btn', [
        'label'   => 'Text buton abonare',
        'section' => 'magpress_nlbar',
        'type'    => 'text',
    ]);
    $wp_customize->add_setting('magpress_nlbar_mode', [
        'default'           => 'popup',
        'sanitize_callback' => 'sanitize_key',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_mode', [
        'label'       => 'Cum se abonează cititorul',
        'description' => 'Popup = buton care deschide o fereastră cu formularul temei (abonații se salvează în site: Aspect → Abonați). Inline = câmp de email direct în bară, trimis către un serviciu extern.',
        'section'     => 'magpress_nlbar',
        'type'        => 'select',
        'choices'     => [
            'popup'  => 'Buton + popup cu formularul temei (recomandat)',
            'inline' => 'Câmp de email direct în bară (serviciu extern)',
        ],
    ]);

    // --- Texte popup ---
    $wp_customize->add_setting('magpress_nlbar_pop_title', [
        'default'           => 'Abonează-te la newsletter',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_pop_title', [
        'label'   => 'Popup — titlu',
        'section' => 'magpress_nlbar',
        'type'    => 'text',
    ]);
    $wp_customize->add_setting('magpress_nlbar_pop_desc', [
        'default'           => 'Primești articolele noastre pe email. Fără spam, te poți dezabona oricând.',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_pop_desc', [
        'label'   => 'Popup — descriere',
        'section' => 'magpress_nlbar',
        'type'    => 'textarea',
    ]);
    $wp_customize->add_setting('magpress_nlbar_ask_name', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_ask_name', [
        'label'   => 'Popup — cere și numele',
        'section' => 'magpress_nlbar',
        'type'    => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_consent', [
        'default'           => magpress_nlbar_default_consent(),
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_consent', [
        'label'       => 'Popup — text acord (GDPR)',
        'description' => 'Vine completat cu textul standard (link către politica de confidențialitate). Șterge tot dacă nu vrei bifă.',
        'section'     => 'magpress_nlbar',
        'type'        => 'textarea',
    ]);
    $wp_customize->add_setting('magpress_nlbar_success', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_success', [
        'label'       => 'Popup — mesaj după abonare',
        'description' => 'Gol = mesaj implicit.',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);

    // --- Expeditor + email de bun venit ---
    $mgp_nl_host = preg_replace('/^www\./i', '', (string) parse_url(home_url(), PHP_URL_HOST));
    $wp_customize->add_setting('magpress_nlbar_from_email', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_from_email', [
        'label'       => 'Email expeditor (de pe ce adresă pleacă)',
        'description' => 'Gol = contact@' . $mgp_nl_host . '. Folosește o adresă de pe domeniul site-ului — una de Gmail/Yahoo pusă aici ajunge în spam sau e respinsă.',
        'section'     => 'magpress_nlbar',
        'type'        => 'email',
    ]);
    $wp_customize->add_setting('magpress_nlbar_from_name', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_from_name', [
        'label'       => 'Nume expeditor',
        'description' => 'Gol = numele site-ului.',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_nlbar_welcome', [
        'default'           => 1,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_welcome', [
        'label'       => 'Trimite email de bun venit la abonare',
        'description' => 'Confirmă abonatului că s-a înscris. Se trimite o singură dată, la prima abonare.',
        'section'     => 'magpress_nlbar',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_welcome_subject', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_welcome_subject', [
        'label'       => 'Bun venit — subiect',
        'description' => 'Gol = „Bine ai venit la newsletterul ' . get_bloginfo('name') . '".',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_nlbar_welcome_body', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_welcome_body', [
        'label'       => 'Bun venit — mesaj',
        'description' => 'Gol = text implicit. Poți folosi {nume}, {site}, {url}, {email} și {dezabonare} (linkul de dezabonare — dacă nu îl pui, se adaugă automat la final).',
        'section'     => 'magpress_nlbar',
        'type'        => 'textarea',
    ]);
    $wp_customize->add_setting('magpress_nlbar_autopost', [
        'default'           => 1,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_autopost', [
        'label'       => 'Trimite automat articolele noi către abonați',
        'description' => 'La publicarea unui articol, abonații primesc titlul, un rezumat și linkul. Trimiterea se face în fundal, în loturi.',
        'section'     => 'magpress_nlbar',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_send_mode', [
        'default'           => 'daily',
        'sanitize_callback' => 'sanitize_key',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_send_mode', [
        'label'       => 'Când pleacă emailurile',
        'description' => 'Rezumat zilnic = UN singur email pe zi, cu toate articolele publicate de la ultimul rezumat.',
        'section'     => 'magpress_nlbar',
        'type'        => 'select',
        'choices'     => [
            'instant' => 'La fiecare articol publicat',
            'daily'   => 'Un singur email pe zi (rezumat)',
        ],
    ]);
    $wp_customize->add_setting('magpress_nlbar_daily_hour', [
        'default'           => 8,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_daily_hour', [
        'label'       => 'Ora rezumatului zilnic',
        'description' => 'Ora locală a site-ului (0–23). Implicit 8 dimineața. Necesită modul „Un singur email pe zi".',
        'section'     => 'magpress_nlbar',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 23, 'step' => 1],
    ]);
    $wp_customize->add_setting('magpress_nlbar_digest_subject', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_digest_subject', [
        'label'       => 'Rezumat zilnic — subiect email',
        'description' => 'Gol = „' . get_bloginfo('name') . ' — 3 articole noi". Poți folosi {site}, {nr}, {data}, {titlu}.',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('magpress_nlbar_post_subject', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_post_subject', [
        'label'       => 'Articol nou — subiect email',
        'description' => 'Gol = titlul articolului. Poți folosi {titlu} și {site}, ex: „{site}: {titlu}".',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('magpress_nlbar_notify', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_notify', [
        'label'   => 'Anunță-mă pe email la fiecare abonat nou',
        'section' => 'magpress_nlbar',
        'type'    => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_notify_to', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_notify_to', [
        'label'       => 'Unde primesc anunțul',
        'description' => 'Gol = adresa de administrator a site-ului (' . esc_html(get_option('admin_email')) . ').',
        'section'     => 'magpress_nlbar',
        'type'        => 'email',
    ]);

    $wp_customize->add_setting('magpress_nlbar_action', [
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_action', [
        'label'       => 'Inline — URL formular (unde se trimite emailul)',
        'description' => 'Doar pentru modul „inline": adresa de „action" de la Mailchimp / Brevo / etc.',
        'section'     => 'magpress_nlbar',
        'type'        => 'url',
    ]);
    $wp_customize->add_setting('magpress_nlbar_field', [
        'default'           => 'EMAIL',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_field', [
        'label'       => 'Inline — numele câmpului de email',
        'description' => 'Mailchimp = EMAIL, Brevo = email.',
        'section'     => 'magpress_nlbar',
        'type'        => 'text',
    ]);
    $wp_customize->add_setting('magpress_nlbar_shortcode', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_shortcode', [
        'label'       => 'Inline — SAU shortcode formular (plugin newsletter)',
        'description' => 'Ex. [mc4wp_form id="123"]. Dacă e completat, înlocuiește câmpul simplu.',
        'section'     => 'magpress_nlbar',
        'type'        => 'textarea',
    ]);
    $wp_customize->add_setting('magpress_nlbar_discover', [
        'default'           => 1,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_discover', [
        'label'       => 'Include butonul „sursă preferată în Google"',
        'description' => 'Dacă îl bifezi aici, poți dezactiva boxul mare Google Discover din secțiunea Layout.',
        'section'     => 'magpress_nlbar',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_nlbar_position', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_nlbar_position', [
        'label'       => 'După ce slot apare',
        'description' => '0 = deasupra sloturilor. 1 = după Slot 1, 2 = după Slot 2 etc. Număr mai mare decât sloturile active = jos de tot.',
        'section'     => 'magpress_nlbar',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
    ]);

    // ---------- Sectiune: Categorii populare (grila carduri pe homepage) ----------
    $wp_customize->add_section('magpress_popcats', [
        'title'    => 'Magpress: Categorii populare',
        'priority' => 46,
    ]);
    $wp_customize->add_setting('magpress_popcats_enabled', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_popcats_enabled', [
        'label'       => 'Afișează grila de categorii pe homepage',
        'description' => 'Carduri simple cu numele categoriei (fără iconițe), hover în culoarea de accent.',
        'section'     => 'magpress_popcats',
        'type'        => 'checkbox',
    ]);
    $wp_customize->add_setting('magpress_popcats_title', [
        'default'           => 'Categorii populare',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_popcats_title', [
        'label'   => 'Titlul secțiunii',
        'section' => 'magpress_popcats',
        'type'    => 'text',
    ]);
    $wp_customize->add_setting('magpress_popcats_cat', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control(new Magpress_Slot_Cats_Control($wp_customize, 'magpress_popcats_cat', [
        'label'       => 'Categoriile afișate',
        'description' => 'Bifează categoriile care apar în grilă (în ordinea alfabetică a numelui).',
        'section'     => 'magpress_popcats',
    ]));
    $wp_customize->add_setting('magpress_popcats_position', [
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_popcats_position', [
        'label'       => 'După ce slot apare',
        'description' => '0 = deasupra sloturilor (sub articolele promovate). 1 = după Slot 1, 2 = după Slot 2 etc. (sloturile de reclamă se numără și ele). Număr mai mare decât sloturile active = jos de tot.',
        'section'     => 'magpress_popcats',
        'type'        => 'number',
        'input_attrs' => ['min' => 0, 'max' => 30, 'step' => 1],
    ]);

    $wp_customize->add_section('magpress_curs_bnr', [
        'title'       => 'Magpress: Curs valutar BNR',
        'description' => 'Bara subțire cu cursul oficial BNR, deasupra header-ului. Sursa: curs.bnr.ro (cache 6h). Există și widget („Magpress: Curs valutar BNR") + shortcode [curs_bnr] pentru alte poziții.',
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
        'default'           => 'București,Timișoara,Cluj-Napoca,Iași,Constanța',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_topbar_vreme_orase', [
        'label'       => 'Orașe afișate (separate prin virgulă)',
        'description' => 'Se afișează în ordinea listei. Disponibile: București, Timișoara, Cluj-Napoca, Iași, Constanța, Craiova, Sibiu, Arad, Botoșani, Râmnicu Vâlcea, Sulina. Gol = toate.',
        'section'     => 'magpress_vreme_bar',
        'type'        => 'text',
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
            'inchis'  => 'Colorat (preia culoarea site-ului, text alb)',
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
 * Golește cache-ul LiteSpeed la SALVAREA Customizer-ului.
 * Fără asta, schimbările (font meniu, culori, bare, titlu H1) NU se văd: CSS-ul generat de temă e
 * inline în HTML-ul paginii, deci rămâne „copt" în pagina din cache. Hook-urile de purge nu
 * regenerează mereu UCSS/CSS-combinat → ștergem și fizic directoarele generate (ca la deploy).
 */
function magpress_purge_litespeed() {
    if (defined('LSCWP_V') || has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
        do_action('litespeed_purge_all_cssjs');
        do_action('litespeed_purge_all_ccss');
        do_action('litespeed_purge_all_ucss');
    }
    foreach (['css', 'ucss', 'ccss'] as $ls_sub) {
        $ls_dir = WP_CONTENT_DIR . '/litespeed/' . $ls_sub;
        if (is_dir($ls_dir) && function_exists('_magpress_rrmdir')) {
            _magpress_rrmdir($ls_dir);
        }
    }
}
add_action('customize_save_after', 'magpress_purge_litespeed');

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
 * Meta de verificare a proprietății site-ului pentru servicii externe.
 * LinksIndexer folosește un token de CONT (identic pentru toate site-urile din
 * rețea), deci îl ținem hardcodat aici — altfel ar trebui lipit manual în head
 * pe fiecare din cele 74 de site-uri. Verificatorul lor NU rulează JavaScript,
 * deci meta trebuie să fie în HTML-ul brut al homepage-ului (aici e, la wp_head
 * prioritate 2 — înaintea oricărui alt cod și în interiorul bufferelor temei,
 * care nu ating decât <title> și blocurile JSON-LD).
 *
 * Per-site se poate suprascrie cu theme_mod `magpress_linksindexer_token`
 * (setabil remote), iar golirea lui scoate complet meta de pe acel site.
 */
define('MAGPRESS_LINKSINDEXER_TOKEN', 'cff50a973e73fb46d13ad422a74016226c0e2a3532ba0441f9610be7d47d698c');

function magpress_print_verification_metas() {
    $token = (string) get_theme_mod('magpress_linksindexer_token', MAGPRESS_LINKSINDEXER_TOKEN);
    $token = trim(apply_filters('magpress_linksindexer_token', $token));
    if ($token !== '') {
        echo '<meta name="linksindexer-site-verification" content="' . esc_attr($token) . '">' . "\n";
    }
}
add_action('wp_head', 'magpress_print_verification_metas', 2);

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
/**
 * Grila „Categorii populare" pe homepage (v0.9.168, poziționare pe slot din
 * v0.9.169) — carduri text fără iconițe. Config în Customizer (secțiunea
 * „Magpress: Categorii populare"): on/off (OFF implicit pe flotă), titlu,
 * categoriile bifate (CSV de ID-uri), „după ce slot apare".
 * Plasarea o face front-page.php prin magpress_homepage_after_slot().
 */
function magpress_render_popular_categories() {
    if (!get_theme_mod('magpress_popcats_enabled', 0)) return;

    $csv = (string) get_theme_mod('magpress_popcats_cat', '');
    $ids = [];
    foreach (preg_split('/[\s,]+/', $csv, -1, PREG_SPLIT_NO_EMPTY) as $v) {
        $id = (int) $v;
        if ($id > 0) $ids[] = $id;
    }
    if (!$ids) return;

    $cats = [];
    foreach ($ids as $id) {
        $c = get_category($id);
        if ($c && !is_wp_error($c)) $cats[] = $c;
    }
    if (!$cats) return;
    usort($cats, function ($a, $b) { return strcoll($a->name, $b->name); });

    $title = trim((string) get_theme_mod('magpress_popcats_title', 'Categorii populare'));
    ?>
    <section class="popcats">
      <?php if ($title !== ''): ?>
      <header class="section-header">
        <span class="section-header__icon" aria-hidden="true"></span>
        <h2 class="section-header__title"><?php echo esc_html($title); ?></h2>
      </header>
      <?php endif; ?>
      <div class="popcats__grid">
        <?php foreach ($cats as $c): ?>
        <a class="popcats__card" href="<?php echo esc_url(get_category_link($c)); ?>">
          <span class="popcats__name"><?php echo esc_html($c->name); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
}

/**
 * Inserții poziționate pe slot (v0.9.169): apelată de front-page.php după fiecare
 * slot randat ($i = 1..N, în ordinea afișării, incl. sloturile de reclamă), cu
 * $i = 0 la ancorele de dinaintea sloturilor și $total la final pentru „jos de tot"
 * (poziții mai mari decât sloturile active).
 */
function magpress_homepage_after_slot($i, $total = null) {
    $pop = (int) get_theme_mod('magpress_popcats_position', 0);
    $dis = (int) get_theme_mod('magpress_discover_position', 0);
    $nlb = (int) get_theme_mod('magpress_nlbar_position', 0);
    $has_nl = (int) get_theme_mod('magpress_nlbar_enabled', 0);
    if ($total !== null) {
        // finalul listei: randează tot ce țintea un slot inexistent
        if ($pop > $total) magpress_render_popular_categories();
        if ($dis > $total) get_template_part('template-parts/google-discover');
        if ($has_nl && $nlb > $total) get_template_part('template-parts/newsletter-bar');
        return;
    }
    if ($pop === $i) magpress_render_popular_categories();
    if ($dis === $i && $i > 0) get_template_part('template-parts/google-discover');
    if ($has_nl && $nlb === $i) get_template_part('template-parts/newsletter-bar');
}

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

// Pre-warm bare Curs BNR + Vreme: refetch în FUNDAL (cron) ÎNAINTE de expirarea cache-ului,
// ca vizitatorii să nu prindă niciodată cache rece. Doar pe site-urile unde bara e activată.
// Un transient-marker limitează refetch-ul (sub TTL-ul de serving), deși cronul e la 5 min.
add_action('magpress_auto_deploy_check', function () {
    if (get_theme_mod('magpress_topbar_curs', 0) && get_transient('magpress_bnr_prewarm') === false) {
        set_transient('magpress_bnr_prewarm', 1, 4 * HOUR_IN_SECONDS); // < 6h TTL serving
        magpress_bnr_rates(true);
    }
    if (get_theme_mod('magpress_topbar_vreme', 0) && get_transient('magpress_wx_prewarm') === false) {
        set_transient('magpress_wx_prewarm', 1, 2 * HOUR_IN_SECONDS); // < 3h TTL serving
        magpress_weather_data(true);
    }
}, 30);

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
         // Mobil (v0.9.178): logo STÂNGA, hamburger DREAPTA. Regula de mai sus e
         // necondiționată și cu !important, deci o anulăm explicit aici — altfel,
         // dacă LiteSpeed servește criticul după stylesheet, headerul rămâne centrat.
         . '@media(max-width:768px){'
         . '.site-header--centered .site-header__top{display:grid!important;grid-template-columns:1fr auto!important;align-items:center!important;text-align:left!important;padding:.85rem clamp(1rem,3vw,2rem)!important}'
         . '.site-header--centered .site-header__top .site-branding{order:1;justify-self:start!important;margin:0!important}'
         . '.site-header--centered .site-header__top .site-logo{margin:0!important}'
         . '.site-header--centered .site-header__top .menu-toggle--mobile{order:2;justify-self:end}'
         . '.site-header--centered .header-spacer{display:none!important}'
         . '}'
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
         // Slot fara reclama: il retragem complet, ca sa nu ramana o gaura alba de 280px.
         . 'ins.adsbygoogle[data-ad-status="unfilled"]{display:none!important}'
         . '.ad-slot--empty{min-height:0!important;height:0!important;margin:0!important;overflow:hidden}'
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
    $words = magpress_count_words($content);
    $minutes = max(1, (int) ceil($words / 200));
    return $minutes;
}

/**
 * Numara cuvintele corect pe text romanesc. `str_word_count()` nu stie UTF-8 si rupe
 * cuvintele cu diacritice („ingrijire" ajungea numarat de mai multe ori), asa ca
 * subestima si `wordCount` din schema, si timpul de citire.
 */
function magpress_count_words($text) {
    $text = strip_shortcodes(wp_strip_all_tags((string) $text));
    return (int) preg_match_all('/[\p{L}\p{N}]+/u', $text);
}

/**
 * Breadcrumbs simplu: Acasa / Categorie / Titlu (sau Acasa / Categorie).
 * SEOPress are propriul breadcrumbs schema — daca-l afiseaza el, comenteaza apelul.
 */
/**
 * Titlul arhivei. `single_term_title()` intoarce gol pe arhivele de autor si de data,
 * deci acele pagini ramaneau cu <h1> COMPLET GOL (indexate, dar fara titlu vizibil).
 */
function magpress_archive_title() {
    if (is_category() || is_tag() || is_tax()) {
        return single_term_title('', false);
    }
    if (is_author()) {
        // get_query_var('author') e gol cand arhiva vine pe slug (/author/nume/) -> obiectul interogat.
        $au = get_queried_object();
        return ($au instanceof WP_User) ? (string) $au->display_name : (string) get_the_author();
    }
    if (is_year())  { return 'Articole din ' . get_the_date('Y'); }
    if (is_month()) { return 'Articole din ' . get_the_date('F Y'); }
    if (is_day())   { return 'Articole din ' . get_the_date('j F Y'); }
    if (is_post_type_archive()) { return post_type_archive_title('', false); }
    return wp_strip_all_tags(get_the_archive_title());
}

function magpress_breadcrumbs() {
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    echo '<a href="' . esc_url(home_url('/')) . '">Acasă</a>';

    // Categoriile PĂRINTE, de sus în jos (ex. Afaceri / Piețe și industrii).
    $ancestors = function ($term) {
        if (!$term || empty($term->term_id)) return;
        foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy)) as $aid) {
            $a = get_term($aid, $term->taxonomy);
            if ($a && !is_wp_error($a)) {
                echo '<span class="sep">/</span>';
                echo '<a href="' . esc_url(get_term_link($a)) . '">' . esc_html($a->name) . '</a>';
            }
        }
    };

    if (is_singular('post')) {
        $cats = get_the_category();
        if ($cats) {
            $ancestors($cats[0]);
            echo '<span class="sep">/</span>';
            echo '<a href="' . esc_url(get_category_link($cats[0])) . '">' . esc_html($cats[0]->name) . '</a>';
        }
        // v0.9.181: titlul articolului lipsea, deși schema BreadcrumbList îl emitea
        // deja — firimiturile vizibile se opreau la categorie.
        echo '<span class="sep">/</span>';
        echo '<span class="current">' . esc_html(get_the_title()) . '</span>';
    } elseif (is_category() || is_tag() || is_tax()) {
        $ancestors(get_queried_object());
        echo '<span class="sep">/</span>';
        echo '<span class="current">' . esc_html(single_term_title('', false)) . '</span>';
    } elseif (is_author() || is_date()) {
        // v0.9.208: arhivele de autor si de data ramaneau doar cu „Acasa".
        echo '<span class="sep">/</span>';
        echo '<span class="current">' . esc_html(magpress_archive_title()) . '</span>';
    } elseif (is_page()) {
        foreach (array_reverse(get_post_ancestors(get_the_ID())) as $pid) {
            echo '<span class="sep">/</span>';
            echo '<a href="' . esc_url(get_permalink($pid)) . '">' . esc_html(get_the_title($pid)) . '</a>';
        }
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
 * Randeaza articolele unui slot de homepage in layout-ul cerut.
 * Extras din front-page.php (v0.9.209) ca sa fie refolosit si de extensia de slot.
 */
function magpress_render_slot_articles($q, $layout) {
    if (!$q || !$q->have_posts()) return;
    set_query_var('magpress_section_query', $q);
    if ($layout === 'asymmetric') {
        get_template_part('template-parts/layout-asymmetric');
    } elseif ($layout === 'hero') {
        get_template_part('template-parts/layout-hero');
    } elseif ($layout === 'list') {
        get_template_part('template-parts/layout-list');
    } else {
        echo '<div class="card-grid">';
        while ($q->have_posts()) { $q->the_post(); get_template_part('template-parts/card'); }
        echo '</div>';
    }
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
            $count = max(2, min(20, $count));
            // Extensie: acelasi slot, al doilea bloc de articole, alt design + offset propriu.
            $ext = null;
            $ext_layout = get_theme_mod("magpress_slot_{$i}_ext_layout", 'none');
            if (in_array($ext_layout, ['grid', 'asymmetric', 'hero', 'list'], true)) {
                // Numarul de articole al extensiei vine EXCLUSIV din design, nu din setarea
                // „Articole" a slotului: Grid 4 (un rand plin), Asimetric 5, Hero 4, Lista 5.
                // Altfel un slot Asimetric (5) dadea o extensie grid cu 4 carduri + 1 orfan.
                $ext_counts = $forced + ['grid' => 4];
                $ext_count  = $ext_counts[$ext_layout];
                $ext = [
                    'layout' => $ext_layout,
                    'count'  => $ext_count,
                    'offset' => max(0, min(200, (int) get_theme_mod("magpress_slot_{$i}_ext_offset", 0))),
                ];
            }
            $slots[] = ['type' => 'category', 'cat' => $primary_cat, 'cat_ids' => $cat_ids, 'count' => $count, 'layout' => $layout, 'ext' => $ext];
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
    // v0.9.208: marginim potrivirea. Varianta veche („.*?" cu /s peste tot articolul)
    // putea sterge un bloc intreg de continut daca „Articolul" aparea devreme in text,
    // si obliga motorul de regex la backtracking pe articolele lungi.
    $content = preg_replace('/Articolul(?:(?!<\/?p\b)[\s\S]){0,300}?apare prima dat[ăa] în(?:(?!<\/?p\b)[\s\S]){0,300}?\./isu', '', $content);
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
 * Reclama din paginile de categorie/tag. Din v0.9.224 se afișează DUPĂ primul rând
 * de articole (vezi archive.php), nu deasupra lor.
 * Cod propriu din Customizer; gol = refolosește codul mid-article (compatibilitate).
 */
function magpress_get_category_top_ad() {
    if (!is_category() && !is_tag()) return '';
    $ad = trim((string) get_theme_mod('magpress_category_ad_top', ''));
    // „nu" = oprit explicit pe categorii, fara sa umblam la reclama din articol.
    if (strtolower($ad) === 'nu') return '';
    // Gol = pastram comportamentul vechi: refolosim codul din mijlocul articolului.
    if ($ad === '') { $ad = trim((string) get_theme_mod('magpress_article_ad_mid', '')); }
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
    // „site-icon" = iconita site-ului din bara de administrare (doar utilizatori logati).
    // Trecuta prin lazy load isi pierde dimensiunile de 16x16 si se afiseaza la marimea
    // reala (150-300 px), impingand numele site-ului sub ea.
    $excludes[] = 'site-icon';
    return $excludes;
});

/**
 * Plasa de siguranta pentru iconita site-ului din bara de administrare: o tinem la 16x16
 * indiferent ce ii fac plugin-urile de optimizare atributelor width/height. Se incarca doar
 * cand bara e vizibila (deci doar pentru utilizatorii logati) si nu afecteaza vizitatorii.
 */
function magpress_adminbar_site_icon_css() {
    if (!is_admin_bar_showing()) { return; }
    echo '<style id="magpress-adminbar-icon">#wpadminbar .ab-item img.site-icon,#wpadminbar #wp-admin-bar-site-name img{width:16px!important;height:16px!important;max-width:16px!important;vertical-align:middle;display:inline-block}</style>' . "
";
}
add_action('wp_head', 'magpress_adminbar_site_icon_css', 99);
add_action('admin_head', 'magpress_adminbar_site_icon_css', 99);

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

  // Un slot pe care Google nu-l umple ramane o cutie goala de 280px in mijlocul
  // paginii. Dupa push verificam rezultatul si, daca n-a venit nicio reclama,
  // retragem spatiul. Google permite explicit colapsarea sloturilor neumplute.
  function collapseIfEmpty(slot, ins){
    if (!slot || slot.classList.contains('ad-slot--empty')) return;
    var unfilled = ins && ins.getAttribute('data-ad-status') === 'unfilled';
    var gol = !ins || (!ins.querySelector('iframe') && ins.getBoundingClientRect().height < 40);
    if (unfilled || gol) slot.classList.add('ad-slot--empty');
  }

  function watchFill(slot, ins){
    if (!ins) { slot.classList.add('ad-slot--empty'); return; }
    // Reactie imediata cand Google scrie data-ad-status pe <ins>.
    if (window.MutationObserver){
      var mo = new MutationObserver(function(){
        var st = ins.getAttribute('data-ad-status');
        if (st === 'unfilled') { slot.classList.add('ad-slot--empty'); mo.disconnect(); }
        else if (st === 'filled') { slot.classList.remove('ad-slot--empty'); mo.disconnect(); }
      });
      mo.observe(ins, {attributes:true, attributeFilter:['data-ad-status']});
      setTimeout(function(){ mo.disconnect(); }, 12000);
    }
    // Plasa de siguranta: unele raspunsuri goale nu seteaza deloc data-ad-status.
    setTimeout(function(){ collapseIfEmpty(slot, ins); }, 4000);
  }

  function activatePushSlot(slot){
    var ins = slot.querySelector('ins.adsbygoogle');
    if (ins) pushAdsense(ins);
    slot.classList.remove('ad-slot--lazy-push');
    slot.classList.add('ad-slot--loaded');
    watchFill(slot, ins);
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
 * BLOCHEAZĂ update-ul FALS de pe wordpress.org (coliziune de slug).
 * Pe wordpress.org există o temă STRĂINĂ numită tot „MagPress" (Sensational Theme,
 * v1.1.1). WP compară doar slug-ul folderului + versiunea → oferă „Actualizează acum"
 * care ar ÎNLOCUI tema noastră cu cea străină. Filtrul rulează la CITIREA
 * transient-ului, deci ascunde și ofertele deja cache-uite: orice ofertă de update
 * pentru slug-ul nostru care nu vine din repo-ul propriu GitHub este eliminată.
 */
function magpress_block_foreign_update($transient) {
    if (!is_object($transient) || empty($transient->response)) {
        return $transient;
    }
    $slug = get_stylesheet();
    if (isset($transient->response[$slug])) {
        $offer   = $transient->response[$slug];
        $package = is_array($offer) ? ($offer['package'] ?? '') : ($offer->package ?? '');
        if (false === strpos((string) $package, 'github.com/kreatorteam-collab/')) {
            unset($transient->response[$slug]);
        }
    }
    return $transient;
}
add_filter('site_transient_update_themes', 'magpress_block_foreign_update');

/**
 * INSTALĂRI NOI: dacă permalink-urile sunt pe „Plain" (structură goală, default-ul
 * unui WP proaspăt), TOT REST-ul pretty (/wp-json/*) dă 404 la nivel de server →
 * site-ul apare „offline" în dashboardul TargetSEO și Sync/legal-status nu merg.
 * La activarea temei setăm standardul flotei /%postname%/ + hard flush (.htaccess).
 * Nu atingem NICIODATĂ o structură deja setată (ex. /%category%/%postname%/).
 */
function magpress_setup_permalinks_on_activation() {
    if (get_option('permalink_structure')) {
        return; // structură deja aleasă — nu ne băgăm
    }
    global $wp_rewrite;
    update_option('permalink_structure', '/%postname%/');
    $wp_rewrite->init();
    $wp_rewrite->set_permalink_structure('/%postname%/');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    add_filter('filesystem_method', function () { return 'direct'; });
    WP_Filesystem();
    flush_rewrite_rules(true);
}
add_action('after_switch_theme', 'magpress_setup_permalinks_on_activation');

/**
 * AUTO-FLUSH REWRITE RULES la schimbarea structurii de permalink-uri.
 * Caz real din flotă: scoți „category/" din URL-urile de categorie (bază „."
 * sau opțiunea din SEOPress) → WP generează linkurile fără bază, dar regulile
 * de rewrite rămân cele vechi → TOATE paginile de categorie dau 404 până când
 * cineva intră în Setări → Permalink-uri și dă Salvează.
 * Ținem o semnătură a structurii; când se schimbă, facem UN singur soft flush
 * (rescrie doar opțiunea rewrite_rules, nu atinge .htaccess).
 */
function magpress_autoflush_permalinks() {
    if (!get_option('permalink_structure')) return; // Plain: nu există reguli de regenerat

    $sig = md5(implode('|', [
        (string) get_option('permalink_structure'),
        (string) get_option('category_base'),
        (string) get_option('tag_base'),
    ]));
    if (get_option('magpress_perma_sig') === $sig) return;

    // Marcăm ÎNAINTE de flush: dacă flush-ul pică, nu intrăm în buclă la fiecare request.
    update_option('magpress_perma_sig', $sig, false);
    flush_rewrite_rules(false);
}
add_action('init', 'magpress_autoflush_permalinks', 99);

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

/* ============================================================================
 * NEWSLETTER NATIV (v0.9.172) — formular propriu în popup, abonați în DB.
 * Fără plugin extern: tabel {prefix}magpress_subscribers, endpoint public REST
 * /magpress/v1/subscribe (FĂRĂ nonce — paginile sunt cache-uite de LiteSpeed și
 * un nonce cache-uit e mort; protecția e honeypot + rate-limit pe IP + validare
 * email + UNIQUE pe email), listă + export CSV în Aspect → Abonați.
 * ========================================================================== */

define('MAGPRESS_SUBS_DB_VERSION', 1);

function magpress_subs_table() {
    global $wpdb;
    return $wpdb->prefix . 'magpress_subscribers';
}

function magpress_subs_install() {
    global $wpdb;
    $table   = magpress_subs_table();
    $collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email varchar(190) NOT NULL,
        name varchar(120) NOT NULL DEFAULT '',
        ip varchar(45) NOT NULL DEFAULT '',
        source varchar(60) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email)
    ) {$collate};");
    update_option('magpress_subs_db_version', MAGPRESS_SUBS_DB_VERSION, false);
}
add_action('after_switch_theme', 'magpress_subs_install');

/** Creează tabelul la nevoie (site-urile primesc tema prin deploy, fără switch). */
function magpress_subs_maybe_install() {
    if ((int) get_option('magpress_subs_db_version', 0) === MAGPRESS_SUBS_DB_VERSION) return;
    if (!get_theme_mod('magpress_nlbar_enabled', 0)) return; // doar dacă bara e pornită
    magpress_subs_install();
}
add_action('init', 'magpress_subs_maybe_install', 20);

/** Textul standard de acord GDPR din popup (gol în Customizer = fără bifă). */
function magpress_nlbar_default_consent() {
    return 'Sunt de acord ca datele mele să fie folosite pentru trimiterea newsletterului, conform <a href="/politica-confidentialitate/" target="_blank" rel="noopener">politicii de confidențialitate</a>. Mă pot dezabona oricând.';
}

/**
 * Expeditorul emailurilor de newsletter: [email, nume].
 * Implicit contact@<domeniu> — adresa TREBUIE să fie pe domeniul site-ului,
 * altfel Gmail/Yahoo resping mesajul (SPF/DKIM al domeniului tău nu acoperă
 * adrese de gmail.com/yahoo.com puse ca From).
 */
function magpress_nlbar_from() {
    $host  = (string) parse_url(home_url(), PHP_URL_HOST);
    $host  = preg_replace('/^www\./i', '', $host);
    $email = trim((string) get_theme_mod('magpress_nlbar_from_email', ''));
    if ($email === '' || !is_email($email)) $email = 'contact@' . $host;
    $name  = trim((string) get_theme_mod('magpress_nlbar_from_name', ''));
    if ($name === '') $name = get_bloginfo('name');
    return [$email, $name];
}

/**
 * DEZABONARE (v0.9.174) — link semnat, fără cont și fără parolă.
 * Token = HMAC pe email cu sarea de auth a site-ului: nu stocăm nimic în plus,
 * nu poate fi ghicit și nimeni nu poate dezabona pe altcineva.
 */
function magpress_unsub_token($email) {
    return substr(hash_hmac('sha256', strtolower(trim((string) $email)), wp_salt('auth') . '|magpress-unsub'), 0, 32);
}

function magpress_unsub_url($email) {
    return add_query_arg([
        'mgp_unsub' => rawurlencode($email),
        'k'         => magpress_unsub_token($email),
    ], home_url('/'));
}

/** Interceptează linkul de dezabonare și șterge abonatul din listă. */
function magpress_handle_unsubscribe() {
    if (!isset($_GET['mgp_unsub'])) return;

    nocache_headers();
    do_action('litespeed_control_set_nocache', 'magpress unsubscribe');

    $email = sanitize_email((string) wp_unslash($_GET['mgp_unsub']));
    $token = isset($_GET['k']) ? sanitize_text_field((string) wp_unslash($_GET['k'])) : '';
    $ok    = ($email && is_email($email) && $token && hash_equals(magpress_unsub_token($email), $token));

    if ($ok) {
        global $wpdb;
        $table = magpress_subs_table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $wpdb->delete($table, ['email' => $email], ['%s']);
        }
    }

    get_header();
    ?>
    <div class="container site-content">
      <div class="nlunsub">
        <span class="nlunsub__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2.5" y="5" width="19" height="14" rx="2"/>
            <path d="m3 6.5 9 6.5 9-6.5"/>
          </svg>
        </span>
        <?php if ($ok): ?>
          <h1 class="nlunsub__title">Te-ai dezabonat</h1>
          <p class="nlunsub__desc">Adresa <strong><?php echo esc_html($email); ?></strong> a fost ștearsă din listă. Nu mai primești niciun email de la noi.</p>
        <?php else: ?>
          <h1 class="nlunsub__title">Link invalid</h1>
          <p class="nlunsub__desc">Linkul de dezabonare nu este valid sau a fost deja folosit. Dacă tot primești emailuri, scrie-ne și te scoatem manual din listă.</p>
        <?php endif; ?>
        <p><a class="nlunsub__btn" href="<?php echo esc_url(home_url('/')); ?>">Înapoi pe site</a></p>
      </div>
    </div>
    <?php
    get_footer();
    exit;
}
add_action('template_redirect', 'magpress_handle_unsubscribe');

/** Emailul de bun venit către un abonat nou (+ opțional notificare la admin). */
function magpress_nlbar_send_welcome($email, $name) {
    list($from_email, $from_name) = magpress_nlbar_from();
    $headers = [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_name . ' <' . $from_email . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    // Antet standard: Gmail/Outlook afișează un buton „Unsubscribe" lângă expeditor
    // și tratează mai bine reputația domeniului.
    $sub_headers = array_merge($headers, [
        'List-Unsubscribe: <' . magpress_unsub_url($email) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ]);

    if (get_theme_mod('magpress_nlbar_welcome', 1)) {
        $subject = trim((string) get_theme_mod('magpress_nlbar_welcome_subject', ''));
        if ($subject === '') $subject = 'Bine ai venit la newsletterul ' . get_bloginfo('name');

        $body = trim((string) get_theme_mod('magpress_nlbar_welcome_body', ''));
        if ($body === '') {
            $body = "Salut{nume},\n\nÎți mulțumim că te-ai abonat la newsletterul {site}. "
                  . "De acum primești pe email articolele noastre noi.\n\n"
                  . "{site}\n{url}";
        }
        $unsub_url = magpress_unsub_url($email);
        $has_token = (strpos($body, '{dezabonare}') !== false || strpos($body, '{unsubscribe}') !== false);
        $body = strtr($body, [
            '{nume}'        => $name !== '' ? ' ' . $name : '',
            '{name}'        => $name,
            '{site}'        => get_bloginfo('name'),
            '{url}'         => home_url('/'),
            '{email}'       => $email,
            '{dezabonare}'  => $unsub_url,
            '{unsubscribe}' => $unsub_url,
        ]);
        // Dacă textul nu conține deja linkul, îl adăugăm la final — dezabonarea
        // trebuie să existe în ORICE email, chiar dacă userul rescrie mesajul.
        if (!$has_token) {
            $body .= "\n\n---\nNu mai vrei aceste emailuri? Dezabonează-te aici:\n" . $unsub_url;
        }
        wp_mail($email, $subject, $body, $sub_headers);
    }

    if (get_theme_mod('magpress_nlbar_notify', 0)) {
        $to = trim((string) get_theme_mod('magpress_nlbar_notify_to', ''));
        if ($to === '' || !is_email($to)) $to = get_option('admin_email');
        wp_mail(
            $to,
            'Abonat nou la newsletter — ' . get_bloginfo('name'),
            "Adresă: {$email}\nNume: " . ($name !== '' ? $name : '—') . "\n\nToți abonații: " . admin_url('themes.php?page=magpress-subscribers'),
            $headers
        );
    }
}

function magpress_rest_subscribe(WP_REST_Request $request) {
    $data = $request->get_json_params();
    if (!is_array($data)) $data = $request->get_params();

    // Honeypot: câmp ascuns care trebuie să rămână gol (boții îl completează).
    if (!empty($data['website'])) {
        return new WP_REST_Response(['ok' => true, 'message' => 'Gata!'], 200);
    }

    $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
    $name  = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';
    if (!$email || !is_email($email)) {
        return new WP_REST_Response(['ok' => false, 'message' => 'Adresa de email nu pare validă.'], 400);
    }

    // Rate-limit: max 5 înscrieri / oră / IP.
    $ip   = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '';
    $key  = 'mgp_sub_' . md5($ip);
    $hits = (int) get_transient($key);
    if ($hits >= 5) {
        return new WP_REST_Response(['ok' => false, 'message' => 'Prea multe încercări. Reîncearcă mai târziu.'], 429);
    }
    set_transient($key, $hits + 1, HOUR_IN_SECONDS);

    global $wpdb;
    if ((int) get_option('magpress_subs_db_version', 0) !== MAGPRESS_SUBS_DB_VERSION) {
        magpress_subs_install();
    }
    $table    = magpress_subs_table();
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE email = %s", $email));
    if ($existing) {
        // Deja abonat: actualizăm doar numele dacă acum l-a completat. Fără email repetat.
        if ($name !== '') {
            $wpdb->update($table, ['name' => $name], ['id' => (int) $existing], ['%s'], ['%d']);
        }
    } else {
        $wpdb->insert($table, [
            'email'      => $email,
            'name'       => $name,
            'ip'         => $ip,
            'source'     => 'popup',
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s']);
        magpress_nlbar_send_welcome($email, $name);
    }

    $msg = trim((string) get_theme_mod('magpress_nlbar_success', ''));
    if ($msg === '') $msg = 'Gata! Ți-am notat adresa — urmează articolele noastre.';
    return new WP_REST_Response(['ok' => true, 'message' => $msg], 200);
}

/* ---------------------------------------------------------------------------
 * ARTICOLE NOI PE EMAIL (v0.9.175). La publicare trimitem articolul către
 * abonați, în loturi prin cron — ca să nu blocăm salvarea și să nu depășim
 * limita de trimitere a hostingului.
 * ------------------------------------------------------------------------- */

define('MAGPRESS_NL_BATCH', 40); // abonați per rulare

function magpress_nl_on_publish($new_status, $old_status, $post) {
    if ($new_status !== 'publish' || $old_status === 'publish') return;
    if (!($post instanceof WP_Post) || $post->post_type !== 'post') return;
    if (!get_theme_mod('magpress_nlbar_enabled', 0)) return;
    if (!get_theme_mod('magpress_nlbar_autopost', 1)) return;
    // În modul „rezumat zilnic" nu trimitem la publicare — articolul intră în
    // digestul de dimineață.
    if (get_theme_mod('magpress_nlbar_send_mode', 'daily') !== 'instant') return;
    if (get_post_meta($post->ID, '_magpress_nl_sent', true)) return;

    update_post_meta($post->ID, '_magpress_nl_sent', current_time('mysql'));
    update_post_meta($post->ID, '_magpress_nl_offset', 0);
    wp_schedule_single_event(time() + 30, 'magpress_nl_send_batch', [(int) $post->ID]);
}
add_action('transition_post_status', 'magpress_nl_on_publish', 10, 3);

/** Trimite un lot pentru articolul dat; se reprogramează dacă mai sunt abonați. */
function magpress_nl_send_batch($post_id) {
    global $wpdb;
    $post = get_post((int) $post_id);
    if (!$post || $post->post_status !== 'publish') return;

    $table = magpress_subs_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;

    $offset = (int) get_post_meta($post->ID, '_magpress_nl_offset', true);
    $rows   = $wpdb->get_results($wpdb->prepare(
        "SELECT email, name FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
        MAGPRESS_NL_BATCH, $offset
    ), ARRAY_A);
    if (!$rows) return;

    foreach ($rows as $r) {
        magpress_nl_send_post_to($post, $r['email'], $r['name']);
    }

    update_post_meta($post->ID, '_magpress_nl_offset', $offset + count($rows));
    if (count($rows) === MAGPRESS_NL_BATCH) {
        wp_schedule_single_event(time() + 120, 'magpress_nl_send_batch', [(int) $post->ID]);
    }
}
add_action('magpress_nl_send_batch', 'magpress_nl_send_batch', 10, 1);

/* ---------------------------------------------------------------------------
 * REZUMAT ZILNIC (v0.9.176) — UN singur email pe zi, la ora aleasă, cu toate
 * articolele publicate de la ultimul rezumat. Cron zilnic în fusul orar al
 * site-ului; trimiterea se face tot în loturi.
 * ------------------------------------------------------------------------- */

/** Următorul timestamp pentru ora dată, în fusul orar al site-ului. */
function magpress_nl_next_run_ts($hour) {
    $now = current_datetime();
    $run = $now->setTime((int) $hour, 0, 0);
    if ($run <= $now) $run = $run->modify('+1 day');
    return $run->getTimestamp();
}

/** Ține cronul zilnic sincronizat cu setările (pornit/oprit + ora). */
function magpress_nl_sync_daily_cron() {
    $on = get_theme_mod('magpress_nlbar_enabled', 0)
        && get_theme_mod('magpress_nlbar_autopost', 1)
        && get_theme_mod('magpress_nlbar_send_mode', 'daily') === 'daily';

    $scheduled = wp_next_scheduled('magpress_nl_daily');

    if (!$on) {
        if ($scheduled) wp_unschedule_event($scheduled, 'magpress_nl_daily');
        return;
    }

    $hour = max(0, min(23, (int) get_theme_mod('magpress_nlbar_daily_hour', 8)));
    if ($scheduled) {
        if ((int) wp_date('G', $scheduled) === $hour) return; // deja la ora corectă
        wp_unschedule_event($scheduled, 'magpress_nl_daily');
    }
    wp_schedule_event(magpress_nl_next_run_ts($hour), 'daily', 'magpress_nl_daily');
}
add_action('init', 'magpress_nl_sync_daily_cron', 21);

/** Rulează rezumatul: strânge articolele noi și pornește trimiterea în loturi. */
function magpress_nl_daily_digest($force = false) {
    if (!$force && get_theme_mod('magpress_nlbar_send_mode', 'daily') !== 'daily') return 0;

    $last  = (string) get_option('magpress_nl_last_digest', '');
    $after = $last !== '' ? $last : date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);

    $posts = get_posts([
        'post_status'      => 'publish',
        'numberposts'      => 25,
        'orderby'          => 'date',
        'order'            => 'ASC',
        'suppress_filters' => false,
        'date_query'       => [[ 'after' => $after, 'inclusive' => false, 'column' => 'post_date' ]],
    ]);

    update_option('magpress_nl_last_digest', current_time('mysql'), false);
    if (!$posts) return 0;

    $ids = array_map(function ($p) { return (int) $p->ID; }, $posts);
    update_option('magpress_nl_digest', ['ids' => $ids, 'offset' => 0], false);
    magpress_nl_digest_batch();
    return count($ids);
}
add_action('magpress_nl_daily', 'magpress_nl_daily_digest');

/** Trimite un lot din rezumatul curent; se reprogramează dacă mai sunt abonați. */
function magpress_nl_digest_batch() {
    global $wpdb;
    $run = get_option('magpress_nl_digest', []);
    if (empty($run['ids'])) return;

    $table = magpress_subs_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;

    $offset = (int) ($run['offset'] ?? 0);
    $rows   = $wpdb->get_results($wpdb->prepare(
        "SELECT email, name FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
        MAGPRESS_NL_BATCH, $offset
    ), ARRAY_A);
    if (!$rows) return;

    $posts = array_filter(array_map('get_post', $run['ids']));
    foreach ($rows as $r) {
        magpress_nl_send_digest_to($posts, $r['email'], $r['name']);
    }

    $run['offset'] = $offset + count($rows);
    update_option('magpress_nl_digest', $run, false);
    if (count($rows) === MAGPRESS_NL_BATCH) {
        wp_schedule_single_event(time() + 120, 'magpress_nl_digest_batch');
    }
}
add_action('magpress_nl_digest_batch', 'magpress_nl_digest_batch');

/** Emailul-rezumat cu mai multe articole, către un abonat. */
function magpress_nl_send_digest_to($posts, $email, $name = '') {
    if (!$posts) return false;
    list($from_email, $from_name) = magpress_nlbar_from();
    $unsub = magpress_unsub_url($email);
    $count = count($posts);

    $subject = trim((string) get_theme_mod('magpress_nlbar_digest_subject', ''));
    $subject = $subject !== ''
        ? strtr($subject, [
            '{site}'  => get_bloginfo('name'),
            '{nr}'    => $count,
            '{data}'  => wp_date('j F Y'),
            '{titlu}' => $posts[array_key_first($posts)]->post_title,
          ])
        : get_bloginfo('name') . ' — ' . ($count === 1 ? 'articolul de azi' : $count . ' articole noi');

    $body = 'Salut' . ($name !== '' ? ' ' . $name : '') . ",\n\n"
          . ($count === 1 ? "Articolul publicat azi pe {$from_name}:" : "Articolele publicate azi pe {$from_name}:") . "\n\n";
    // v0.9.180: DOAR titlu + link (fără rezumat) — cerință user: cu 20+ articole
    // pe zi, rezumatele făceau emailul interminabil.
    foreach ($posts as $p) {
        $body .= '• ' . $p->post_title . "\n" . get_permalink($p) . "\n\n";
    }
    $body .= get_bloginfo('name') . "\n" . home_url('/') . "\n\n"
          . "---\nNu mai vrei aceste emailuri? Dezabonează-te aici:\n" . $unsub;

    return wp_mail($email, $subject, $body, [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_name . ' <' . $from_email . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'List-Unsubscribe: <' . $unsub . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ]);
}

/** Emailul cu un articol, către un abonat. */
function magpress_nl_send_post_to(WP_Post $post, $email, $name = '') {
    list($from_email, $from_name) = magpress_nlbar_from();
    $unsub = magpress_unsub_url($email);

    $excerpt = has_excerpt($post)
        ? get_the_excerpt($post)
        : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45, '…');

    $subject = trim((string) get_theme_mod('magpress_nlbar_post_subject', ''));
    $subject = $subject !== ''
        ? strtr($subject, ['{titlu}' => $post->post_title, '{site}' => get_bloginfo('name')])
        : $post->post_title;

    $body = 'Salut' . ($name !== '' ? ' ' . $name : '') . ",\n\n"
          . $post->post_title . "\n\n"
          . $excerpt . "\n\n"
          . "Citește articolul:\n" . get_permalink($post) . "\n\n"
          . get_bloginfo('name') . "\n" . home_url('/') . "\n\n"
          . "---\nNu mai vrei aceste emailuri? Dezabonează-te aici:\n" . $unsub;

    return wp_mail($email, $subject, $body, [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_name . ' <' . $from_email . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'List-Unsubscribe: <' . $unsub . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ]);
}

/** Aspect → Abonați: listă + export CSV + ștergere. */
function magpress_subs_admin_menu() {
    add_theme_page('Abonați newsletter', 'Abonați', 'manage_options', 'magpress-subscribers', 'magpress_render_subs_page');
}
add_action('admin_menu', 'magpress_subs_admin_menu');

function magpress_subs_export_csv() {
    if (!current_user_can('manage_options')) wp_die('Nu ai permisiunea.');
    check_admin_referer('magpress_subs_export');
    global $wpdb;
    $table = magpress_subs_table();
    $rows  = $wpdb->get_results("SELECT email, name, created_at FROM {$table} ORDER BY id DESC", ARRAY_A);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=abonati-' . sanitize_file_name((string) parse_url(home_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM: Excel + diacritice
    fputcsv($out, ['Email', 'Nume', 'Data']);
    foreach ((array) $rows as $r) fputcsv($out, [$r['email'], $r['name'], $r['created_at']]);
    fclose($out);
    exit;
}
add_action('admin_post_magpress_subs_export', 'magpress_subs_export_csv');

function magpress_render_subs_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = magpress_subs_table();

    if (isset($_POST['mgp_del']) && check_admin_referer('magpress_subs_del')) {
        $wpdb->delete($table, ['id' => (int) $_POST['mgp_del']], ['%d']);
        echo '<div class="notice notice-success is-dismissible"><p>Abonat șters.</p></div>';
    }

    if (isset($_POST['mgp_test_to']) && check_admin_referer('magpress_subs_test')) {
        $to = sanitize_email((string) $_POST['mgp_test_to']);
        if (!$to || !is_email($to)) {
            echo '<div class="notice notice-error is-dismissible"><p>Adresă de test invalidă.</p></div>';
        } else {
            list($fe, $fn) = magpress_nlbar_from();
            $sent = wp_mail(
                $to,
                'Test newsletter — ' . get_bloginfo('name'),
                "Acesta e un email de test trimis de pe {$fe}.\n\nDacă l-ai primit (și NU în Spam), expeditorul e configurat corect.",
                ['From: ' . $fn . ' <' . $fe . '>', 'Reply-To: ' . $fn . ' <' . $fe . '>', 'Content-Type: text/plain; charset=UTF-8']
            );
            echo $sent
                ? '<div class="notice notice-success is-dismissible"><p>Test trimis către ' . esc_html($to) . ' de pe <code>' . esc_html($fe) . '</code>. Verifică și folderul Spam.</p></div>'
                : '<div class="notice notice-error is-dismissible"><p>Serverul nu a putut trimite emailul (wp_mail a eșuat). Verifică setările de mail ale hostingului sau folosește un plugin SMTP.</p></div>';
        }
    }

    // Trimitere manuală a unui articol (pentru articole publicate înainte de
    // activarea newsletterului sau pentru retrimitere).
    if (isset($_POST['mgp_send_post']) && check_admin_referer('magpress_subs_send')) {
        $pid  = (int) $_POST['mgp_send_post'];
        $post = get_post($pid);
        if (!$post || $post->post_status !== 'publish') {
            echo '<div class="notice notice-error is-dismissible"><p>Articol invalid.</p></div>';
        } else {
            update_post_meta($pid, '_magpress_nl_sent', current_time('mysql'));
            update_post_meta($pid, '_magpress_nl_offset', 0);
            magpress_nl_send_batch($pid);
            echo '<div class="notice notice-success is-dismissible"><p>Am trimis „' . esc_html($post->post_title) . '" către abonați (primii ' . (int) MAGPRESS_NL_BATCH . ' acum, restul în fundal).</p></div>';
        }
    }

    if (isset($_POST['mgp_send_digest']) && check_admin_referer('magpress_subs_digest')) {
        $n = magpress_nl_daily_digest(true);
        echo $n
            ? '<div class="notice notice-success is-dismissible"><p>Rezumat trimis cu ' . (int) $n . ' articole.</p></div>'
            : '<div class="notice notice-warning is-dismissible"><p>Niciun articol nou de la ultimul rezumat — nu am trimis nimic.</p></div>';
    }

    list($from_email, $from_name) = magpress_nlbar_from();

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    $rows   = $exists ? $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 500", ARRAY_A) : [];
    $total  = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;
    ?>
    <div class="wrap">
      <h1>Abonați newsletter</h1>
      <div class="notice notice-info inline" style="padding:.75rem 1rem;margin:1rem 0">
        <p style="margin:0 0 .5rem">
          Emailurile pleacă de pe <code><?php echo esc_html($from_email); ?></code> (<?php echo esc_html($from_name); ?>).
          Schimbi din <a href="<?php echo esc_url(admin_url('customize.php')); ?>">Personalizare → Magpress: Bara newsletter</a>.
        </p>
        <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:0">
          <?php wp_nonce_field('magpress_subs_test'); ?>
          <label for="mgp_test_to">Trimite un email de test către:</label>
          <input type="email" id="mgp_test_to" name="mgp_test_to" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" />
          <button class="button" type="submit">Trimite test</button>
        </form>
      </div>
      <?php
      $recent = get_posts(['numberposts' => 15, 'post_status' => 'publish']);
      if ($recent): ?>
      <div class="notice notice-info inline" style="padding:.75rem 1rem;margin:1rem 0">
        <p style="margin:0 0 .5rem"><strong>Trimite un articol acum</strong> — util pentru articolele publicate înainte de a porni newsletterul.</p>
        <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin:0" onsubmit="return confirm('Trimiți acest articol tuturor abonaților?')">
          <?php wp_nonce_field('magpress_subs_send'); ?>
          <select name="mgp_send_post">
            <?php foreach ($recent as $p): ?>
              <option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html($p->post_title); ?><?php echo get_post_meta($p->ID, '_magpress_nl_sent', true) ? ' (deja trimis)' : ''; ?></option>
            <?php endforeach; ?>
          </select>
          <button class="button button-primary" type="submit">Trimite abonaților</button>
        </form>
        <?php
        $mgp_mode = get_theme_mod('magpress_nlbar_send_mode', 'daily');
        $mgp_next = wp_next_scheduled('magpress_nl_daily');
        ?>
        <hr style="margin:.85rem 0" />
        <p style="margin:0 0 .5rem">
          <strong>Rezumat zilnic:</strong>
          <?php if ($mgp_mode === 'daily'): ?>
            pornit, ora <?php echo (int) get_theme_mod('magpress_nlbar_daily_hour', 8); ?>:00.
            <?php if ($mgp_next): ?>Următorul: <?php echo esc_html(wp_date('j M Y, H:i', $mgp_next)); ?>.<?php endif; ?>
          <?php else: ?>
            oprit — acum se trimite câte un email la fiecare articol. Schimbi din Personalizare.
          <?php endif; ?>
        </p>
        <form method="post" style="margin:0" onsubmit="return confirm('Trimiți acum rezumatul cu articolele noi?')">
          <?php wp_nonce_field('magpress_subs_digest'); ?>
          <input type="hidden" name="mgp_send_digest" value="1" />
          <button class="button" type="submit">Trimite rezumatul acum</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if (!$exists): ?>
        <p>Tabelul se creează automat când pornești bara din <a href="<?php echo esc_url(admin_url('customize.php')); ?>">Personalizare → Magpress: Bara newsletter</a>.</p>
      <?php else: ?>
        <p>
          <strong><?php echo (int) $total; ?></strong> abonați.
          <?php if ($total): ?>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=magpress_subs_export'), 'magpress_subs_export')); ?>">Descarcă CSV</a>
          <?php endif; ?>
        </p>
        <?php if (!$rows): ?>
          <p>Niciun abonat încă.</p>
        <?php else: ?>
        <table class="widefat striped">
          <thead><tr><th>Email</th><th>Nume</th><th>Data</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo esc_html($r['email']); ?></td>
              <td><?php echo esc_html($r['name']); ?></td>
              <td><?php echo esc_html($r['created_at']); ?></td>
              <td>
                <form method="post" style="margin:0" onsubmit="return confirm('Ștergi acest abonat?')">
                  <?php wp_nonce_field('magpress_subs_del'); ?>
                  <input type="hidden" name="mgp_del" value="<?php echo (int) $r['id']; ?>" />
                  <button class="button-link button-link-delete" type="submit">Șterge</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($total > 500): ?><p><em>Se afișează ultimii 500. Exportul CSV conține tot.</em></p><?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
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
            // Repo-ul stă PRIVAT între release-uri (politică asumată), deci verificarea
            // pe GitHub returnează 404 în mod normal. Nu e o defecțiune → mesaj neutru,
            // nu avertisment galben pe fiecare site din flotă. (v0.9.182)
            $message = 'ℹ️ Verificarea pe GitHub nu a răspuns — normal cât timp repo-ul e privat între release-uri. '
                     . 'Update-urile se livrează centralizat, nu din această pagină. Versiune instalată: <strong>'
                     . esc_html(wp_get_theme()->get('Version')) . '</strong>.';
            $message_type = 'info';
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
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_diag',
        'permission_callback' => '__return_true', // validăm HMAC manual (deploy_secret per-site)
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
    // Abonare newsletter (public; honeypot + rate-limit în callback, fără nonce
    // fiindcă paginile sunt cache-uite de LiteSpeed).
    register_rest_route('magpress/v1', '/subscribe', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_subscribe',
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
 * Auth: HMAC-SHA256 pe body cu deploy_secret PER-SITE (ca /errorlog, /deploy) — NU mai folosim
 * secretul comun hardcodat (care ajunge public la release). Folosit la debugging fleet multi-site.
 */
function magpress_rest_diag($req) {
    $body     = (string) $req->get_body();
    $sig      = (string) $req->get_header('X-Magpress-Signature');
    $expected = hash_hmac('sha256', $body, magpress_get_deploy_secret());
    if ($sig === '' || !hash_equals($expected, $sig)) {
        return new WP_REST_Response(['error' => 'bad_signature'], 403);
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
    // NU mai returnăm căi absolute / conținut de fișiere (evităm fingerprinting server dacă gate-ul e slab).
    $adv = WP_CONTENT_DIR . '/advanced-cache.php';

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
        'img_test'        => $img_test,
        'cache' => [
            'WP_CACHE'           => defined('WP_CACHE') ? (WP_CACHE ? 'true' : 'false') : 'undefined',
            'advanced_cache_php' => @file_exists($adv),
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
        // CREATE (v0.9.164): pe instalări noi pagina nu există deloc — o creăm direct
        // publicată cu slug-ul canonic + marcaj managed, în loc să refuzăm cu 404.
        $new_id = wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'meta_input'   => [
                '_magpress_managed' => '1',
            ],
        ], true);
        if (is_wp_error($new_id)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'wp_post_error',
                'msg'   => $new_id->get_error_message(),
            ], 500);
        }
        return new WP_REST_Response([
            'ok'      => true,
            'action'  => 'created',
            'page_id' => (int) $new_id,
            'slug'    => $slug,
            'url'     => get_permalink((int) $new_id),
        ], 200);
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
 * v0.9.192 — paginile legale nu au ce căuta în index.
 * Termeni, cookies și confidențialitate sunt obligatorii legal, dar nu aduc trafic;
 * indexate, doar diluează. Emitem `noindex, follow` — follow, ca linkurile din ele
 * (spre home, contact) să rămână utile. NU punem `nofollow` la nivel de pagină: ar
 * tăia degeaba fluxul intern, iar `rel="nofollow"` pe linkurile din footer e o
 * practică abandonată din 2009.
 * Identificare: marcajul propriu `_magpress_managed` SAU orice slug din lista
 * canonică + alternativele cunoscute (site-urile vechi au slug-uri diferite).
 */
function magpress_legal_slug_aliases() {
    return [
        'termeni-si-conditii', 'termeni', 'terms', 'terms-and-conditions', 't-c',
        'termeni-conditii', 'conditii-utilizare', 'termeni-si-conditii-de-utilizare',
        'termeni-de-utilizare',
        'politica-cookies', 'cookies', 'politica-de-cookies', 'cookie-policy', 'cookie',
        'cookies-policy', 'politica-cookie', 'politica-de-cookie',
        'politica-confidentialitate', 'confidentialitate', 'politica-de-confidentialitate',
        'privacy', 'privacy-policy', 'gdpr', 'politica-de-confidentialitate-gdpr',
        'politica-confidentialitate-gdpr', 'confidential',
    ];
}

function magpress_is_legal_page($post_id) {
    $post_id = (int) $post_id;
    if (!$post_id) return false;
    if (get_post_meta($post_id, '_magpress_managed', true)) return true;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'page') return false;
    return in_array($post->post_name, magpress_legal_slug_aliases(), true);
}

function magpress_legal_page_robots($robots) {
    if (!is_page() || !get_theme_mod('magpress_legal_noindex', 1)) return $robots;
    if (!magpress_is_legal_page(get_queried_object_id())) return $robots;

    unset($robots['index'], $robots['nofollow']);
    $robots['noindex'] = true;
    $robots['follow']  = true;
    return $robots;
}
add_filter('wp_robots', 'magpress_legal_page_robots');

/**
 * SEOPress emite PROPRIUL meta robots și îl scoate pe cel din nucleu (`wp_robots`),
 * deci filtrul de mai sus n-are efect pe site-urile cu SEOPress.
 * Prima încercare (v0.9.192) a folosit filtrele `seopress_titles_noindex` și
 * `seopress_robots_index` și a fost GREȘITĂ: valorile lor ajungeau CONCATENATE în
 * conținutul tag-ului, nu îl înlocuiau — ieșea `index, follow, 1, max-snippet:-1…`,
 * unde „1" e chiar `__return_true` turnat în șir.
 *
 * Ce funcționează: îi servim lui SEOPress exact valoarea pe care ar citi-o dacă bifa
 * „noindex" ar fi pusă manual în pagină — interceptăm CITIREA meta-cheii, fără să
 * scriem nimic în baza de date (reversibil din comutator). Dacă SEOPress lipsește,
 * nimeni nu cere cheia și filtrul e inert.
 */
function magpress_legal_page_seopress_meta($value, $object_id, $meta_key, $single) {
    if ($meta_key !== '_seopress_robots_index') return $value;
    if (!get_theme_mod('magpress_legal_noindex', 1)) return $value;
    if (!magpress_is_legal_page($object_id)) return $value;
    return $single ? 'yes' : ['yes']; // 'yes' = noindex, în convenția SEOPress
}
add_filter('get_post_metadata', 'magpress_legal_page_seopress_meta', 10, 4);

/**
 * Fuzzy find pentru pagini legale: caută prin slug exact, slug-uri alternative comune,
 * apoi prin title LIKE. Returnează prima pagină publish găsită sau null.
 */
function magpress_find_legal_page($target_slug) {
    // 1. Slug exact
    $page = get_page_by_path($target_slug, OBJECT, 'page');
    if ($page && $page->post_status === 'publish') return $page;

    // 2. Slug-uri alternative comune (vezi și magpress_legal_slug_aliases())
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
    // Gardă zip-slip: refuzăm orice intrare care iese din directorul țintă (../, cale absolută)
    // înainte de a extrage — o arhivă coruptă/malițioasă nu poate scrie în afara $tmp_extract.
    for ($zi = 0; $zi < $zip->numFiles; $zi++) {
        $entry = $zip->getNameIndex($zi);
        if ($entry === false || $entry === '') { continue; }
        $entry = str_replace('\\', '/', $entry);
        if (preg_match('#^(/|[A-Za-z]:)#', $entry) || strpos($entry, '../') !== false || $entry === '..') {
            $zip->close();
            @unlink($tmp_zip);
            return ['ok' => false, 'error' => 'zip_unsafe_entry', 'log' => $log];
        }
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

    // 6d. Cloudflare (v0.9.217). LiteSpeed regenereaza CSS-ul, dar Cloudflare tine copia
    //     VECHE a URL-urilor vechi cu max-age de un an, deci vizitatorii cu HTML vechi in
    //     browser primesc CSS vechi de la edge. Daca integrarea Cloudflare din LiteSpeed e
    //     configurata (cheie API in setarile pluginului), ii cerem ei purge-ul: noi nu tinem
    //     niciun token in tema. No-op daca nu e configurata.
    // Purge-ul chiar se intampla DOAR daca integrarea e pornita si are chei in LiteSpeed;
    // altfel purge_all() intoarce tacut si logul ar minti ca s-a golit ceva.
    $cf_on = false;
    if (class_exists('\LiteSpeed\Conf')) {
        try { $cf_on = (bool) \LiteSpeed\Conf::val('cdn-cloudflare'); } catch (\Throwable $e) { $cf_on = false; }
    }
    if (!$cf_on) {
        $cf_state = 'integrare neactivata in LiteSpeed (fara chei) — cache-ul de la edge NU se goleste de aici';
    } elseif (class_exists('\LiteSpeed\CDN\Cloudflare')) {
        try {
            $cf = method_exists('\LiteSpeed\CDN\Cloudflare', 'cls') ? \LiteSpeed\CDN\Cloudflare::cls() : null;
            if ($cf && method_exists($cf, 'purge_all')) {
                $cf->purge_all();
                $cf_state = 'purge cerut';
            } else {
                $cf_state = 'clasa exista, fara purge_all';
            }
        } catch (\Throwable $e) {
            $cf_state = 'eroare: ' . substr($e->getMessage(), 0, 80);
        }
    }
    $log_msg('Cloudflare: ' . $cf_state);

    // 6c. PIESA CARE LIPSEA (v0.9.215). LiteSpeed tine in baza o mapare URL -> fisier CSS
    //     generat ({prefix}litespeed_url_file). Stergeam fisierele de pe disc, dar maparea
    //     ramanea → paginile regenerate continuau sa trimita la un hash care nu mai exista.
    //     Fisierul mai traia doar in cache-ul Cloudflare/browser, asa ca modificarile de CSS
    //     apareau pe unele site-uri si pe altele nu, la nesfarsit. Golim maparea ca LiteSpeed
    //     sa reconstruiasca fisierele si sa emita hash-uri noi.
    global $wpdb;
    foreach (['litespeed_url_file', 'litespeed_url'] as $ls_tbl) {
        $t = $wpdb->prefix . $ls_tbl;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t) {
            $wpdb->query("TRUNCATE TABLE `{$t}`");
            $log_msg('LiteSpeed map golita: ' . $ls_tbl);
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

    // 4b. „Actualizat la <dată>" pe articole (v0.9.187)
    $wp_customize->add_setting('magpress_show_updated', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_show_updated', [
        'label'       => 'Afișează „Actualizat la <dată>"',
        'description' => 'Pe articolele modificate după publicare. Data afișată nu poate fi niciodată mai veche decât data publicării, iar modificările din primele 24h de la publicare (corecturi) nu se anunță.',
        'section'     => 'magpress_news',
        'type'        => 'checkbox',
    ]);

    // 4c. Pragul de la care contează o modificare (v0.9.188)
    $wp_customize->add_setting('magpress_updated_since', [
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_updated_since', [
        'label'       => 'Ignoră actualizările dinainte de (AAAA-LL-ZZ)',
        'description' => 'Operațiunile în masă (linkuri interne, migrări) au schimbat data de modificare pe mii de articole care n-au fost editate niciodată. Modificările anterioare acestei date nu se afișează. Gol = <code>' . esc_html(MAGPRESS_UPDATED_SINCE_DEFAULT) . '</code> (data introducerii regulii). Scrie <code>0</code> ca să afișezi orice modificare. Articolele împrospătate real de modulul de conținut se afișează întotdeauna.',
        'section'     => 'magpress_news',
        'type'        => 'text',
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
/**
 * Timestamp-ul de afișat pentru „Actualizat la", sau 0 dacă nu se afișează nimic.
 * v0.9.187. Reguli:
 *  - data afișată NU poate fi niciodată mai veche decât data publicării (dacă
 *    post_modified < post_date — se întâmplă la articole importate/backdatate —
 *    nu afișăm nimic, nu afișăm data publicării ca „actualizare");
 *  - o diferență sub prag (implicit 24h) e o corectură la publicare, nu o
 *    actualizare de conținut: n-o anunțăm.
 */
function magpress_updated_ts($post = null) {
    if (!get_theme_mod('magpress_show_updated', 1)) return 0;
    $pub = (int) get_post_time('U', true, $post);
    $mod = (int) get_post_modified_time('U', true, $post);
    if (!$pub || !$mod) return 0;
    if ($mod <= $pub) return 0;
    $min_gap = (int) apply_filters('magpress_updated_min_gap', DAY_IN_SECONDS);
    if (($mod - $pub) < $min_gap) return 0;

    // v0.9.188 — nu anunțăm ATINGERILE TEHNICE. Pe flotă, operațiuni în masă
    // (inserarea de linkuri interne, redenumiri de categorii, migrări) au schimbat
    // post_modified pe mii de articole care n-au fost editate editorial niciodată.
    // Afișăm data DOAR dacă: (a) articolul a fost chiar împrospătat de modulul de
    // conținut (meta _hzr_done), SAU (b) modificarea e ulterioară pragului de mai jos.
    $p = get_post($post);
    if ($p && get_post_meta($p->ID, '_hzr_done', true)) return $mod;

    $since = trim((string) get_theme_mod('magpress_updated_since', ''));
    if ($since === '') { $since = MAGPRESS_UPDATED_SINCE_DEFAULT; }
    if ($since === '0') return $mod; // prag dezactivat explicit: se afișează orice
    $cut = strtotime($since . ' 00:00:00 UTC');
    if ($cut && $mod < $cut) return 0;

    return $mod;
}

/** Fragmentul „Actualizat la <dată>" (gol dacă nu e cazul). */
function magpress_updated_html($post = null, $prefix = 'Actualizat la ') {
    $ts = magpress_updated_ts($post);
    if (!$ts) return '';
    return '<span class="article-meta__updated">' . esc_html($prefix)
         . '<time datetime="' . esc_attr(get_the_modified_date('c', $post)) . '">'
         . esc_html(get_the_modified_date('j F Y', $post)) . '</time></span>';
}

function magpress_render_article_meta() {
    if (!is_singular('post')) return '';
    $rich = (bool) get_theme_mod('magpress_news_author_byline', 0);

    if (!$rich) {
        // Comportament default (identic cu varianta veche): plain meta
        $updated = magpress_updated_html();
        ob_start();
        ?>
        <div class="article-meta">
            <?php echo magpress_article_meta_cat(); // categoria stă în rândul de meta, nu deasupra titlului ?>
            <span>De <strong><?php the_author(); ?></strong></span>
            <span class="sep">|</span>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
            <?php if ($updated !== '') : ?>
                <span class="sep">|</span>
                <?php echo $updated; // deja escapat în magpress_updated_html() ?>
            <?php endif; ?>
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
    // v0.9.187: aceeași regulă ca pe meta-ul simplu — data actualizării nu poate fi
    // mai veche decât publicarea, iar sub prag nu o anunțăm deloc.
    $updated = magpress_updated_html();
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
                    <?php if ($updated !== ''): ?>
                        <span class="sep">·</span>
                        <?php echo $updated; // deja escapat în magpress_updated_html() ?>
                    <?php endif; ?>
                    <span class="sep">·</span>
                    <span class="article-meta__reading"><?php echo $reading; ?> min citire</span>
                </div>
            </div>
        </div>
        <?php echo magpress_article_meta_cat(); // categoria stă în rândul de meta, nu deasupra titlului ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Insigna de categorie afișată în rândul de meta al articolului (v0.9.212).
 * Înainte stătea deasupra titlului; acum ordinea e firimituri → poză → titlu → meta.
 */
function magpress_article_meta_cat() {
    $cats = get_the_category();
    if (!$cats || is_wp_error($cats)) return '';
    $cat = $cats[0];
    return '<a class="badge badge--pill article-meta__cat ' . esc_attr(magpress_category_color_class($cat)) . '"'
         . ' href="' . esc_url(get_category_link($cat)) . '">' . esc_html($cat->name) . '</a>';
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
        'label'       => 'Adaugă Organization+WebSite pe homepage (setare veche)',
        'description' => 'Înlocuită de „Forțează Organization+WebSite" de mai jos. Nu mai are efect.',
        'section'     => 'magpress_schema',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_schema_org_force', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_org_force', [
        'label'       => 'Forțează Organization+WebSite pe homepage',
        'description' => 'Tema emite mereu entitatea site-ului (Organization/NewsMediaOrganization) + WebSite. Debifează doar dacă vezi schema dublată în Rich Results Test.',
        'section'     => 'magpress_schema',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_schema_override_seo', [
        'default'           => 1,
        'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        'transport'         => 'refresh',
    ]);
    $wp_customize->add_control('magpress_schema_override_seo', [
        'label'       => 'Tema are prioritate față de schema din SEOPress',
        'description' => 'Oprește blocurile echivalente emise de SEOPress (Person, Organization, WebSite, Breadcrumb), ca schema să fie gestionată dintr-un singur loc pe toate site-urile.',
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

/**
 * SEOPress emite propria schemă (pe unele site-uri din flotă un „Person" ca entitate
 * principală, care ținea locul lui Organization). Tema e sursa unică de adevăr pentru
 * schema, ca să poată fi gestionată centralizat pe toate site-urile → oprim blocurile
 * echivalente din SEOPress. Filtrele lipsă sunt inofensive: dacă pluginul nu e activ
 * sau nu are filtrul, nu se întâmplă nimic.
 * v0.9.183
 */
function magpress_suppress_seopress_schema() {
    if (!get_theme_mod('magpress_schema_enabled', 1)) return;
    if (!get_theme_mod('magpress_schema_override_seo', 1)) return;

    foreach ([
        'seopress_schemas_auto_website',
        'seopress_schemas_auto_organization',
        'seopress_schemas_auto_person',
        'seopress_schemas_auto_breadcrumb',
    ] as $f) {
        add_filter($f, '__return_empty_array', 99);
    }
}
add_action('init', 'magpress_suppress_seopress_schema', 5);

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
        // v0.9.183: Organization + WebSite se emit ACUM ÎNTOTDEAUNA. Cheia veche
        // `magpress_schema_homepage_extra` era salvată pe 0 pe TOATĂ flota (74/74 nu
        // aveau Organization), așa că folosim o cheie NOUĂ, ca zerourile vechi să nu
        // mai blocheze nimic. Opt-out per site: debifezi „Forțează Organization".
        if (get_theme_mod('magpress_schema_org_force', 1)) {
            $org = magpress_build_organization_schema();
            if ($org) $schemas[] = $org;
            $ws = magpress_build_website_schema();
            if ($ws) $schemas[] = $ws;
        }
        // ItemList pe homepage — featured posts (Rich Results Test va detecta acest schema)
        $itemlist = magpress_build_homepage_itemlist_schema();
        if ($itemlist) $schemas[] = $itemlist;
    }

    // v0.9.183: marcăm blocurile NOASTRE cu data-magpress. Filtrul de buffer de mai jos
    // identifica „schema străină" după slash-urile escapate (\/), dar emiterea de aici NU
    // folosea JSON_UNESCAPED_SLASHES → tema își ștergea PROPRIILE Organization și WebSite,
    // pe toate cele 74 de site-uri. Marcajul e determinist, nu depinde de escaping.
    foreach ($schemas as $s) {
        echo '<script type="application/ld+json" data-magpress="1">'
           . wp_json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
           . '</script>' . "\n";
    }
}
add_action('wp_head', 'magpress_emit_schema_jsonld', 30);

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
    // v0.9.183: identificarea se face pe MARCAJUL nostru (data-magpress), nu pe
    // escaping-ul slash-urilor — euristica veche ștergea și blocurile temei.
    $buffer = preg_replace_callback(
        '/<script\b[^>]*type="application\/ld\+json"[^>]*>\s*(\{[\s\S]*?\})\s*<\/script>/i',
        function ($m) {
            if (stripos($m[0], 'data-magpress') !== false) {
                return $m[0]; // blocul temei — sursa unică de adevăr
            }
            if (!get_theme_mod('magpress_schema_override_seo', 1)) {
                return $m[0]; // userul a ales să lase pluginul SEO să emită
            }
            // Entitatea site-ului emisă de alt plugin (SEOPress: Person/Organization/
            // WebSite) → o scoatem, ca să nu concureze cu cea a temei.
            $data = json_decode($m[1], true);
            $type = is_array($data) && isset($data['@type']) ? $data['@type'] : '';
            $dup  = ['Organization', 'NewsMediaOrganization', 'OnlineBusiness', 'LocalBusiness', 'WebSite', 'Person'];
            if (is_string($type) && in_array($type, $dup, true)) {
                return '';
            }
            return $m[0];
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
    // Fallback: fără imagine reprezentativă → logo-ul site-ului, ca Article schema să aibă mereu 'image'
    // (recomandat de Google pentru rich results).
    if ($image_url === '') {
        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id) {
            $limg = wp_get_attachment_image_src($logo_id, 'full');
            if ($limg) { $image_url = $limg[0]; $image_w = $limg[1]; $image_h = $limg[2]; }
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
        // v0.9.187: dateModified nu poate fi mai vechi decât datePublished (articolele
        // importate/backdatate au post_modified < post_date → validatoarele reclamă).
        'dateModified'     => ((int) get_post_modified_time('U', true, $post) < (int) get_post_time('U', true, $post))
                                ? get_the_date('c', $post)
                                : get_the_modified_date('c', $post),
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
    $word_count = magpress_count_words($post->post_content);
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
        // Categoriile părinte, de sus în jos — schema trebuie să reflecte exact
        // firimiturile vizibile (v0.9.181).
        foreach (array_reverse(get_ancestors($cat->term_id, 'category')) as $aid) {
            $a = get_term($aid, 'category');
            if ($a && !is_wp_error($a)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $a->name,
                    'item'     => get_category_link($a),
                ];
            }
        }
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
            $pos = 2;
            foreach (array_reverse(get_ancestors($term->term_id, 'category')) as $aid) {
                $a = get_term($aid, 'category');
                if ($a && !is_wp_error($a)) {
                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $pos++,
                        'name'     => $a->name,
                        'item'     => get_category_link($a),
                    ];
                }
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
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

/**
 * v0.9.228 — bara Google Discover trebuie sa apara pe TOATE site-urile.
 * Comutatorul are implicit „pornit", dar pe o parte din flota fusese salvat pe 0
 * din Customizer, iar un default nu bate o valoare salvata. Stergem O SINGURA DATA
 * valoarea salvata, ca sa revina la implicit; dupa aceea, daca o opresti de pe un
 * site, ramane oprita (migrarea nu se mai repeta).
 */
add_action('init', function () {
    if (get_option('magpress_discover_reset_done') === '1') return;
    remove_theme_mod('magpress_show_google_discover');
    update_option('magpress_discover_reset_done', '1', false);
}, 25);

/**
 * Layout „boxed" (v0.9.221): site-ul devine o foaie alba centrata pe fundal gri,
 * in loc sa se intinda pe toata latimea ecranului. Doar CSS, pe clasa de body.
 *
 * Implicit e PORNIT doar pe femeiaconteaza.ro (acolo l-a cerut userul) si OPRIT
 * pe restul flotei; se poate schimba per site din Customizer -> Magpress: Layout.
 * Sub 1400px se dezactiveaza singur (laptop mic, tableta, mobil).
 */
function magpress_boxed_default() {
    $host = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    return $host === 'femeiaconteaza.ro' ? 1 : 0;
}

function magpress_is_boxed() {
    return (bool) get_theme_mod('magpress_boxed_layout', magpress_boxed_default());
}

function magpress_body_classes($classes) {
    $clean = ['magpress'];
    if (magpress_is_boxed()) $clean[] = 'magpress-boxed';
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
function magpress_bnr_rates($force = false) {
    if (!$force) {
        $cached = get_transient('magpress_bnr_rates_v1');
        if (is_array($cached) && !empty($cached['dates'])) return $cached;
    }

    // v0.9.189: BNR a MUTAT feed-ul pe curs.bnr.ro. Vechiul www.bnr.ro/nbrfxrates10days.xml
    // nu dă 404, ci întoarce HTTP 200 cu PAGINA LOR HTML (catch-all) — de-aia parsarea
    // ieșea goală și bara dispărea. Încercăm sursele pe rând și acceptăm doar XML valid.
    $sources = apply_filters('magpress_bnr_sources', [
        'https://curs.bnr.ro/nbrfxrates10days.xml',
        'https://www.bnr.ro/nbrfxrates10days.xml',
    ]);

    $rates = [];
    foreach ($sources as $src) {
        $resp = wp_remote_get($src, ['timeout' => 10]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) continue;

        $body = wp_remote_retrieve_body($resp);
        // Un răspuns HTML (redirect spre homepage) nu e o eroare de rețea: îl respingem aici.
        if (stripos(ltrim($body), '<!doctype') === 0 || stripos(ltrim($body), '<html') === 0) continue;

        $xml = @simplexml_load_string($body);
        if (!$xml || empty($xml->Body->Cube)) continue;

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
        if ($rates) break; // sursă bună găsită
    }

    // Orice eșec (rețea, HTML în loc de XML, XML fără cursuri) păstrează ultimele date
    // bune în loc să golească bara. Reîncercăm peste 30 de minute.
    if (!$rates) {
        $stale = get_transient('magpress_bnr_rates_stale');
        if (is_array($stale) && !empty($stale['dates'])) {
            set_transient('magpress_bnr_rates_v1', $stale, 30 * MINUTE_IN_SECONDS);
            return $stale;
        }
        return ['dates' => [], 'rates' => []];
    }
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

/** Orașe care NU sunt în feed-ul ANM (nume => [lat, lon]); aduse din Open-Meteo. */
function magpress_weather_extra_cities() {
    return [
        'Timișoara' => [45.7489, 21.2087],
    ];
}

/** Iconiță din codul WMO (Open-Meteo). */
function magpress_weather_icon_wmo($code) {
    if ($code === 0)                    { return '☀️'; }
    if ($code === 1 || $code === 2)     { return '⛅'; }
    if ($code === 3)                    { return '☁️'; }
    if ($code === 45 || $code === 48)   { return '🌫️'; }
    if ($code >= 71 && $code <= 77)     { return '❄️'; }
    if ($code === 85 || $code === 86)   { return '❄️'; }
    if ($code >= 95)                    { return '⛈️'; }
    if ($code >= 51 && $code <= 82)     { return '🌧️'; }
    return '🌡️';
}

/** Descriere scurtă RO din codul WMO (pentru tooltip). */
function magpress_weather_desc_wmo($code) {
    if ($code === 0)                    { return 'Cer senin'; }
    if ($code === 1 || $code === 2)     { return 'Variabil'; }
    if ($code === 3)                    { return 'Înnorat'; }
    if ($code === 45 || $code === 48)   { return 'Ceață'; }
    if ($code >= 51 && $code <= 57)     { return 'Burniță'; }
    if ($code >= 61 && $code <= 67)     { return 'Ploaie'; }
    if ($code >= 71 && $code <= 77)     { return 'Ninsoare'; }
    if ($code >= 80 && $code <= 82)     { return 'Averse'; }
    if ($code === 85 || $code === 86)   { return 'Ninsoare'; }
    if ($code >= 95)                    { return 'Furtună'; }
    return '';
}

/** Prognoza de azi pentru orașele extra (Open-Meteo). Întoarce [nume => [min,max,icon,desc]]. */
function magpress_weather_openmeteo($extra) {
    $out = [];
    foreach ($extra as $name => $ll) {
        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $ll[0] . '&longitude=' . $ll[1]
             . '&daily=temperature_2m_max,temperature_2m_min,weather_code&timezone=Europe%2FBucharest&forecast_days=1';
        $resp = wp_remote_get($url, ['timeout' => 10]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) { continue; }
        $j = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!isset($j['daily']['temperature_2m_max'][0], $j['daily']['temperature_2m_min'][0])) { continue; }
        $code = isset($j['daily']['weather_code'][0]) ? (int) $j['daily']['weather_code'][0] : -1;
        $out[$name] = [
            'min'  => (int) round((float) $j['daily']['temperature_2m_min'][0]),
            'max'  => (int) round((float) $j['daily']['temperature_2m_max'][0]),
            'icon' => magpress_weather_icon_wmo($code),
            'desc' => magpress_weather_desc_wmo($code),
        ];
    }
    return $out;
}

/** Datele de vreme pentru azi (cache 3h + stale 24h): ANM + orașe extra (Open-Meteo). */
function magpress_weather_data($force = false) {
    if (!$force) {
        $cached = get_transient('magpress_weather_v1');
        if (is_array($cached)) { return $cached; }
    }

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
    // Orașe care NU sunt în feed-ul ANM (ex. Timișoara) — aduse din Open-Meteo.
    foreach (magpress_weather_openmeteo(magpress_weather_extra_cities()) as $n => $d) {
        $cities[$n] = $d;
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
        . '.mp-wx-bar{margin:2.75rem auto -.75rem}'  // auto=centrat orizontal; 2.75rem sus / -.75rem jos = centrat vertical (compenseaza padding-top .site-content 24px + margin sectiune 32px => gol egal 44px sus/jos, verificat live)
        . '.mp-wx-bar__inner{display:flex;align-items:center;justify-content:center;gap:1rem;background:var(--c-accent,#1f6fb2);color:#fff;font-family:var(--f-sans,system-ui,sans-serif);font-size:.82rem;line-height:1;border-radius:8px;padding:.55rem 1.1rem}'  // „Închis" = accent plin (culoarea brandului site-ului)
        . '.mp-wx-bar__label{color:rgba(255,255,255,.82);font-weight:700;letter-spacing:.04em;text-transform:uppercase;font-size:.72rem;white-space:nowrap;display:inline-flex;align-items:center;gap:.4rem;flex:0 0 auto}'
        . '.mp-wx-bar__ticker{flex:0 1 auto;min-width:0;overflow:hidden;-webkit-mask-image:linear-gradient(to right,transparent,#000 2.5%,#000 97.5%,transparent);mask-image:linear-gradient(to right,transparent,#000 2.5%,#000 97.5%,transparent)}'
        . '.mp-wx__track{display:inline-flex;align-items:center;white-space:nowrap;animation-name:mp-wx-scroll;animation-timing-function:linear;animation-iteration-count:infinite;will-change:transform}'
        . '.mp-wx-bar__ticker:hover .mp-wx__track{animation-play-state:paused}'
        . '.mp-wx-bar__ticker--static{overflow-x:auto;-webkit-overflow-scrolling:touch;-webkit-mask-image:none;mask-image:none;scrollbar-width:none}'
        . '.mp-wx-bar__ticker--static::-webkit-scrollbar{display:none}'
        . '.mp-wx__track--static{animation:none}'
        . '.mp-wx__item{display:inline-flex;align-items:center;gap:.35rem;margin-right:2rem}'
        . '.mp-wx__ic{font-size:.95rem}.mp-wx__nm{color:rgba(255,255,255,.92)}.mp-wx__mn{color:rgba(255,255,255,.6)}'
        . '.mp-wx__sep{color:rgba(255,255,255,.4);margin:0 .12rem}.mp-wx__mx{color:#fff;font-weight:700}'
        . '.mp-wx__link{flex:0 0 auto;color:#fff;text-decoration:none;font-weight:700;white-space:nowrap;font-size:.78rem}'
        . '.mp-wx__link:hover{color:rgba(255,255,255,.8)}'
        // Varianta DESCHISĂ (fundal gri deschis, text închis)
        . '.mp-wx-bar--light .mp-wx-bar__inner{background:#eef1f5;color:#374151;border:1px solid #e2e6ec}'
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

/**
 * Construiește orașele (track). Dacă e dată o listă, RESPECTĂ ordinea din listă
 * (nu ordinea feed-ului); listă goală = toate, în ordinea din date. '' dacă n-are date.
 */
function magpress_weather_items($cities_filter = []) {
    $data = magpress_weather_data();
    if (empty($data['cities'])) { return ''; }

    // Index pe nume normalizat, ca să potrivim intrările din listă cu orașele disponibile.
    $byNorm = [];
    foreach ($data['cities'] as $name => $c) {
        $byNorm[magpress_weather_norm($name)] = ['name' => $name, 'c' => $c];
    }

    // Ordinea de afișare: lista userului (în ordinea ei) sau toate orașele.
    $order = [];
    $filter = array_filter(array_map('trim', (array) $cities_filter), 'strlen');
    if ($filter) {
        foreach ($filter as $cf) {
            $n = magpress_weather_norm($cf);
            if (isset($byNorm[$n])) { $order[] = $byNorm[$n]; }
        }
    } else {
        foreach ($data['cities'] as $name => $c) { $order[] = ['name' => $name, 'c' => $c]; }
    }

    $items = '';
    foreach ($order as $e) {
        $name = $e['name']; $c = $e['c'];
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

/** Bara se randează ÎNTRE header și conținut (hook magpress_after_header din header.php). */
add_action('magpress_after_header', 'magpress_weather_topbar_output');
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
    $list   = get_theme_mod('magpress_topbar_vreme_orase', 'București,Timișoara,Cluj-Napoca,Iași,Constanța');
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
    echo '<div class="mp-wx-bar' . $light . ' container" role="complementary" aria-label="Vremea azi pe orașe">'
        . '<div class="mp-wx-bar__inner">'
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
        . '<div class="mp-wx-bar' . $light . '" role="complementary" aria-label="Vremea azi pe orașe">'
        . '<div class="mp-wx-bar__inner">'
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


/* =====================================================================
 * ARTICOLE RECOMANDATE ÎN CATEGORIE (v0.9.199)
 * Bifă per articol în editor → articolul rămâne SUS în arhiva categoriei
 * lui, într-un bloc cu design propriu (lead mare + listă laterală).
 * Articolele promovate sunt scoase din grila normală (fără dubluri) și
 * din paginile 2+, ca paginarea să rămână corectă.
 * Inert cât timp niciun articol nu e bifat. Cache-safe (transient + ver).
 * ===================================================================== */

define('MAGPRESS_CATFEAT_META', '_magpress_cat_featured');

// --- Meta + checkbox în editor -------------------------------------------
add_action('init', function () {
    register_post_meta('post', MAGPRESS_CATFEAT_META, [
        'type'          => 'boolean',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => function () { return current_user_can('edit_posts'); },
    ]);
});

add_action('add_meta_boxes', function () {
    add_meta_box('magpress_catfeat_box', 'Recomandat în categorie', 'magpress_catfeat_box_cb', 'post', 'side', 'high');
});

function magpress_catfeat_box_cb($post) {
    wp_nonce_field('magpress_catfeat_save', 'magpress_catfeat_nonce');
    $on = (bool) get_post_meta($post->ID, MAGPRESS_CATFEAT_META, true);
    echo '<label style="display:flex;gap:.5rem;align-items:flex-start;line-height:1.4">';
    echo '<input type="checkbox" name="magpress_catfeat" value="1" ' . checked($on, true, false) . '>';
    echo '<span><strong>Ține articolul sus</strong> în pagina categoriei, într-un bloc „Recomandate" cu design special.</span>';
    echo '</label>';
    echo '<p style="margin:.6rem 0 0;color:#666;font-size:12px">Se afișează maximum ' . (int) magpress_catfeat_limit()
       . ' articole recomandate per categorie (cele mai recente bifate). Setare în Personalizare → Magpress: Layout.</p>';
}

add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['magpress_catfeat_nonce']) || !wp_verify_nonce($_POST['magpress_catfeat_nonce'], 'magpress_catfeat_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, MAGPRESS_CATFEAT_META, empty($_POST['magpress_catfeat']) ? 0 : 1);
    magpress_catfeat_flush();
});

// Orice schimbare de status/ștergere invalidează cache-ul.
add_action('transition_post_status', function ($new, $old, $post) {
    if ($post && $post->post_type === 'post' && $new !== $old) magpress_catfeat_flush();
}, 10, 3);
add_action('deleted_post', function ($post_id) {
    if (get_post_type($post_id) === 'post') magpress_catfeat_flush();
});
// Bifa poate fi scrisa si din REST / WP-CLI / programatic: pe o cheie inexistenta
// WordPress declanseaza added_post_meta, NU updated_post_meta.
$magpress_catfeat_meta_cb = function ($mid, $post_id, $key) {
    if ($key === MAGPRESS_CATFEAT_META) magpress_catfeat_flush();
};
add_action('added_post_meta',   $magpress_catfeat_meta_cb, 10, 3);
add_action('updated_post_meta', $magpress_catfeat_meta_cb, 10, 3);
add_action('deleted_post_meta', $magpress_catfeat_meta_cb, 10, 3);
// Mutarea articolului in alta categorie (Quick Edit, bulk, REST) nu trece prin save_post.
add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy === 'category' && get_post_type($object_id) === 'post') magpress_catfeat_flush();
}, 10, 4);

function magpress_catfeat_flush() {
    update_option('magpress_catfeat_ver', (int) get_option('magpress_catfeat_ver', 1) + 1, false);
}

// --- Setări ---------------------------------------------------------------
function magpress_catfeat_enabled() {
    return (bool) get_theme_mod('magpress_catfeat_enable', 1);
}
/**
 * Ce model de asezare foloseste blocul de recomandate.
 * Implicit "big" (mare + lista). "auto" alege dupa cate articole sunt bifate:
 *   1-4 grila egala | 5-6 mozaic | 7-8 mare + lista | 9+ compact.
 */
function magpress_catfeat_layout($total) {
    $mode = (string) get_theme_mod('magpress_catfeat_layout', 'big');
    if (in_array($mode, ['grid', 'mosaic', 'big', 'strip', 'compact'], true)) {
        return $mode;
    }
    $total = (int) $total;
    if ($total <= 4) return 'grid';
    if ($total <= 6) return 'mosaic';
    if ($total <= 8) return 'big';
    return 'compact';
}

/** Steluta din eticheta "Recomandat". */
function magpress_catfeat_star($class = '') {
    return '<svg class="mp-featcat__star' . ($class ? ' ' . esc_attr($class) : '') . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 5.98 6.6.96-4.78 4.66 1.13 6.57L12 17.57l-5.9 3.1 1.13-6.57L2.45 9.44l6.6-.96L12 2.5z"/></svg>';
}

/**
 * Atributele imaginii. Prioritatea LCP se da PRIMEI imagini mari randate efectiv
 * (nu primului articol): daca articolul 1 n-are poza, o primeste urmatorul.
 * Miniaturile (rand de lista, compact) nu cer niciodata prioritate.
 */
function magpress_catfeat_img_args($sizes, $allow_priority = true) {
    static $used = false;
    $args = ['decoding' => 'async', 'sizes' => $sizes];
    if ($allow_priority && !$used) {
        $used = true;
        $args['loading']       = 'eager';
        $args['fetchpriority'] = 'high';
        $args['class']         = 'litespeed-no-lazy skip-lazy';
        $args['data-no-lazy']  = '1';
    } else {
        $args['loading'] = 'lazy';
    }
    return $args;
}

/**
 * Card cu poza mare (grila, mozaic, banda, capul de la "mare + lista").
 * Marimea sursei se alege dupa cat spatiu ocupa cardul in layout-ul curent.
 */
function magpress_catfeat_card($layout, $total, $is_first = false) {
    $big = ($is_first && in_array($layout, ['big', 'mosaic'], true)) || ($layout === 'grid' && $total === 1);

    if ($layout === 'grid' && $total === 1) {
        $size = 'magpress-feature'; $sizes = '100vw';
    } elseif ($big && $layout === 'big') {
        $size = 'magpress-feature'; $sizes = '(max-width: 900px) 100vw, 55vw';
    } elseif ($big) {
        $size = 'magpress-feature'; $sizes = '(max-width: 900px) 100vw, 50vw';
    } elseif ($layout === 'strip') {
        $size = 'magpress-card'; $sizes = '260px';
    } else {
        $size = 'magpress-card'; $sizes = '(max-width: 560px) 150px, (max-width: 1100px) 50vw, 25vw';
    }
    ?>
    <article class="mp-featcat__item<?php echo $big ? ' mp-featcat__item--big' : ''; ?>">
      <a class="mp-featcat__img" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail($size, magpress_catfeat_img_args($sizes));
        else: ?>
          <span class="card-image-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="mp-featcat__tag"><?php echo magpress_catfeat_star(); ?> Recomandat</span>
      </a>
      <div class="mp-featcat__body">
        <h3 class="mp-featcat__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <?php if ($big):
          $ex = wp_trim_words(get_the_excerpt(), 26, '...');
          if ($ex): ?>
            <p class="mp-featcat__exc"><?php echo esc_html($ex); ?></p>
          <?php endif;
        endif; ?>
      </div>
    </article>
    <?php
}

/** Rand din lista de la "mare + lista". */
function magpress_catfeat_row() {
    ?>
    <article class="mp-featcat__row">
      <a class="mp-featcat__rowimg" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail('magpress-card', magpress_catfeat_img_args('110px', false));
        else: ?>
          <span class="card-image-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="mp-featcat__tag mp-featcat__tag--mini" aria-hidden="true"><?php echo magpress_catfeat_star(); ?></span>
      </a>
      <h3 class="mp-featcat__rowtitle"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    </article>
    <?php
}

/** Rand dens, pentru modelul compact. */
function magpress_catfeat_crow() {
    ?>
    <article class="mp-featcat__crow">
      <a class="mp-featcat__crowimg" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if (has_post_thumbnail()):
          the_post_thumbnail('magpress-card', magpress_catfeat_img_args('80px', false));
        else: ?>
          <span class="card-image-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
      </a>
      <?php echo magpress_catfeat_star('mp-featcat__star--inline'); ?>
      <h3 class="mp-featcat__crowtitle"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    </article>
    <?php
}

function magpress_catfeat_limit() {
    $n = (int) get_theme_mod('magpress_catfeat_limit', 12);
    return max(1, min(12, $n));
}

// --- ID-urile recomandate dintr-o categorie -------------------------------
function magpress_catfeat_ids($term_id) {
    $term_id = (int) $term_id;
    if (!$term_id || !magpress_catfeat_enabled()) return [];

    $ver = (int) get_option('magpress_catfeat_ver', 1);
    $key = 'mp_catfeat_' . $term_id . '_' . $ver . '_' . magpress_catfeat_limit();
    $ids = get_transient($key);
    if (is_array($ids)) return $ids;

    $ids = get_posts([
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'numberposts'         => magpress_catfeat_limit(),
        'fields'              => 'ids',
        'cat'                 => $term_id,
        'meta_key'            => MAGPRESS_CATFEAT_META,
        'meta_value'          => '1',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => 1,
        'no_found_rows'       => true,
    ]);
    $ids = array_map('intval', (array) $ids);
    set_transient($key, $ids, 6 * HOUR_IN_SECONDS);
    return $ids;
}

// --- Scoate recomandatele din bucla normală a categoriei ------------------
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query() || $q->is_feed()) return;
    if (!$q->is_category() || !magpress_catfeat_enabled()) return;

    $term_id = 0;
    $cat = $q->get('cat');
    if ($cat) {
        $term_id = (int) $cat;
    } else {
        $slug = $q->get('category_name');
        if ($slug) {
            $t = get_category_by_slug(basename(trim((string) $slug, '/')));
            if ($t) $term_id = (int) $t->term_id;
        }
    }
    if (!$term_id) return;

    $ids = magpress_catfeat_ids($term_id);
    if (!$ids) return;

    $GLOBALS['magpress_catfeat_ids'] = $ids;
    $ex = $q->get('post__not_in');
    $q->set('post__not_in', array_merge(is_array($ex) ? $ex : [], $ids));
});

/**
 * Arhivele (categorie, eticheta, autor, data) afiseaza 9 articole pe pagina,
 * ca grila de 3 coloane sa iasa in 3 randuri pline. Numarul e filtrabil, iar
 * cautarea si homepage-ul raman pe setarea din WordPress.
 */
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query() || $q->is_feed()) return;
    if (!($q->is_category() || $q->is_tag() || $q->is_author() || $q->is_date())) return;
    $per_page = (int) apply_filters('magpress_archive_posts_per_page', 9);
    if ($per_page > 0) $q->set('posts_per_page', $per_page);
});

// ID-urile de afișat în blocul de sus (cu fallback dacă pre_get_posts n-a rulat).
function magpress_catfeat_current_ids() {
    if (!is_category() || !magpress_catfeat_enabled()) return [];
    if (!empty($GLOBALS['magpress_catfeat_ids']) && is_array($GLOBALS['magpress_catfeat_ids'])) {
        return $GLOBALS['magpress_catfeat_ids'];
    }
    return magpress_catfeat_ids(get_queried_object_id());
}

// --- Coloană „Recomandat" în lista de articole din admin ------------------
add_filter('manage_post_posts_columns', function ($cols) {
    $cols['magpress_catfeat'] = 'Recomandat';
    return $cols;
});
add_action('manage_post_posts_custom_column', function ($col, $post_id) {
    if ($col !== 'magpress_catfeat') return;
    echo get_post_meta($post_id, MAGPRESS_CATFEAT_META, true)
        ? '<span title="Recomandat în categorie" style="color:#d9a300;font-size:16px">&#9733;</span>'
        : '<span style="color:#ccc">—</span>';
}, 10, 2);

/* =====================================================================
 * IMAGINE IMPLICITA PENTRU ARTICOLE FARA POZA (v0.9.205)
 * Se alege una per CATEGORIE (ecranul de editare a categoriei) si,
 * optional, una globala de rezerva (Personalizare -> Magpress: Layout).
 * Articolele care n-au imagine reprezentativa proprie o folosesc automat
 * peste tot unde tema cere thumbnail-ul: carduri, arhive, articol, schema,
 * og:image. Nimic nu se schimba pana nu setezi prima imagine.
 * ===================================================================== */

define('MAGPRESS_CAT_THUMB_META', '_magpress_default_thumb');

add_action('init', function () {
    // Fara show_in_rest: selectorul e un formular clasic de admin, iar cheia fiind
    // protejata (prefix _) n-are ce cauta in raspunsul public /wp-json/wp/v2/categories.
    register_term_meta('category', MAGPRESS_CAT_THUMB_META, [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => false,
        'sanitize_callback' => function ($v) {
            $v = absint($v);
            return ($v && wp_attachment_is_image($v)) ? $v : 0;
        },
        'auth_callback'     => function () { return current_user_can('manage_categories'); },
    ]);
});

/* ---------- Selectorul de imagine din ecranul categoriei ---------- */

function magpress_cat_thumb_picker($id_suffix, $attachment_id) {
    $img_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
    wp_enqueue_media();
    ?>
    <div class="magpress-catthumb" data-mode="<?php echo esc_attr($id_suffix); ?>">
      <div class="magpress-catthumb__preview" style="margin-bottom:8px">
        <?php if ($img_url): ?>
          <img src="<?php echo esc_url($img_url); ?>" alt="" style="max-width:160px;height:auto;display:block;border-radius:4px" />
        <?php endif; ?>
      </div>
      <input type="hidden" name="magpress_cat_thumb_id" class="magpress-catthumb__input" value="<?php echo esc_attr($attachment_id); ?>" />
      <button type="button" class="button magpress-catthumb__pick">Alege imagine</button>
      <button type="button" class="button-link magpress-catthumb__clear" style="margin-left:8px;color:#b32d2e<?php echo $attachment_id ? '' : ';display:none'; ?>">Sterge</button>
      <p class="description">Se foloseste la articolele din aceasta categorie care n-au imagine reprezentativa proprie.</p>
      <script>
      (function () {
        var box = document.currentScript.closest('.magpress-catthumb');
        if (!box || box.dataset.bound) return;
        box.dataset.bound = '1';
        var input   = box.querySelector('.magpress-catthumb__input');
        var preview = box.querySelector('.magpress-catthumb__preview');
        var clear   = box.querySelector('.magpress-catthumb__clear');
        var frame;
        function reset() {
          input.value = '';
          preview.innerHTML = '';
          clear.style.display = 'none';
          if (frame) { frame.state().get('selection').reset(); }
        }
        box.querySelector('.magpress-catthumb__pick').addEventListener('click', function (e) {
          e.preventDefault();
          if (frame) { frame.open(); return; }
          frame = wp.media({
            title: 'Imagine implicita pentru categorie',
            button: { text: 'Foloseste imaginea' },
            library: { type: 'image' },
            multiple: false
          });
          frame.on('select', function () {
            var a = frame.state().get('selection').first().toJSON();
            var url = (a.sizes && (a.sizes.thumbnail || a.sizes.full)) ? (a.sizes.thumbnail || a.sizes.full).url : a.url;
            input.value = a.id;
            preview.innerHTML = '<img src="' + url + '" alt="" style="max-width:160px;height:auto;display:block;border-radius:4px" />';
            clear.style.display = '';
          });
          frame.open();
        });
        clear.addEventListener('click', function (e) { e.preventDefault(); reset(); });

        // Formularul de ADAUGARE se trimite prin AJAX si core-ul goleste doar campurile
        // vizibile — campul nostru e hidden, deci categoria urmatoare ar mosteni imaginea.
        if (box.dataset.mode === 'add' && window.jQuery) {
          jQuery(document).on('ajaxComplete', function (e, xhr, opts) {
            if (opts && opts.data && String(opts.data).indexOf('action=add-tag') !== -1) reset();
          });
        }
      })();
      </script>
    </div>
    <?php
}

add_action('category_add_form_fields', function () {
    ?>
    <div class="form-field">
      <label>Imagine implicita</label>
      <?php magpress_cat_thumb_picker('add', 0); ?>
    </div>
    <?php
});

add_action('category_edit_form_fields', function ($term) {
    $attachment_id = (int) get_term_meta($term->term_id, MAGPRESS_CAT_THUMB_META, true);
    ?>
    <tr class="form-field">
      <th scope="row"><label>Imagine implicita</label></th>
      <td><?php magpress_cat_thumb_picker('edit', $attachment_id); ?></td>
    </tr>
    <?php
});

function magpress_cat_thumb_save($term_id) {
    if (!isset($_POST['magpress_cat_thumb_id'])) return;
    if (!current_user_can('manage_categories') || !current_user_can('edit_term', $term_id)) return;
    // Pe ecranul de editare salvam doar termenul din formular.
    if (isset($_POST['tag_ID']) && (int) $_POST['tag_ID'] !== (int) $term_id) return;

    $id = absint($_POST['magpress_cat_thumb_id']);
    if ($id && !wp_attachment_is_image($id)) $id = 0;

    $before = (int) get_term_meta($term_id, MAGPRESS_CAT_THUMB_META, true);
    if ($id) {
        update_term_meta($term_id, MAGPRESS_CAT_THUMB_META, $id);
    } else {
        delete_term_meta($term_id, MAGPRESS_CAT_THUMB_META);
    }

    // Imaginea intra in HTML-ul articolelor (card, arhiva, single, og:image, JSON-LD),
    // deci paginile deja coapte in LiteSpeed ar arata in continuare vechiul continut.
    if ($before !== $id) magpress_purge_litespeed();
}
add_action('created_category', 'magpress_cat_thumb_save');
add_action('edited_category',  'magpress_cat_thumb_save');
add_action('delete_term', function ($term_id, $tt_id, $taxonomy) {
    if ($taxonomy === 'category') magpress_purge_litespeed();
}, 10, 3);

/* ---------- Coloana din lista de categorii ---------- */

add_filter('manage_edit-category_columns', function ($cols) {
    $cols['magpress_thumb'] = 'Imagine implicita';
    return $cols;
});
add_filter('manage_category_custom_column', function ($out, $col, $term_id) {
    if ($col !== 'magpress_thumb') return $out;
    $id = (int) get_term_meta($term_id, MAGPRESS_CAT_THUMB_META, true);
    if (!$id) return '<span style="color:#ccc">&mdash;</span>';
    $url = wp_get_attachment_image_url($id, 'thumbnail');
    return $url ? '<img src="' . esc_url($url) . '" alt="" style="width:56px;height:38px;object-fit:cover;border-radius:3px" />' : '';
}, 10, 3);

/* ---------- Rezolvarea imaginii de rezerva ---------- */

/**
 * Ce imagine primeste un articol fara poza proprie:
 * categoria lui principala -> parintii ei -> celelalte categorii -> globala.
 * Rezultatul e memorat pe durata request-ului.
 */
function magpress_fallback_thumb_id($post = null) {
    $post_id = (int) (is_object($post) ? $post->ID : ($post ?: get_the_ID()));
    if (!$post_id || get_post_type($post_id) !== 'post') return 0;

    static $cache = [];
    if (isset($cache[$post_id])) return $cache[$post_id];

    $cache[$post_id] = 0; // marcam intai, ca un apel reintrant sa nu intre in bucla
    $cats = get_the_category($post_id);
    if (!is_array($cats)) $cats = [];

    // Categoria principala din SEOPress, daca site-ul o foloseste.
    $primary = (int) get_post_meta($post_id, '_seopress_robots_primary_cat', true);
    if ($primary) {
        $ordered = [];
        foreach ($cats as $c) { if ((int) $c->term_id === $primary) $ordered[] = $c; }
        foreach ($cats as $c) { if ((int) $c->term_id !== $primary) $ordered[] = $c; }
        $cats = $ordered;
    }

    $id = 0;
    foreach ($cats as $c) {
        $term_id = (int) $c->term_id;
        $guard   = 0;
        while ($term_id && $guard++ < 10) {
            $found = (int) get_term_meta($term_id, MAGPRESS_CAT_THUMB_META, true);
            // Un ID mort (imagine stearsa din biblioteca) NU trebuie sa opreasca lantul:
            // trecem mai departe la parinte / urmatoarea categorie / rezerva globala.
            if ($found && wp_attachment_is_image($found)) { $id = $found; break 2; }
            $parent  = get_term($term_id, 'category');
            $term_id = ($parent && !is_wp_error($parent)) ? (int) $parent->parent : 0;
        }
    }

    if (!$id) $id = (int) get_theme_mod('magpress_default_thumb', 0);
    if ($id && !wp_attachment_is_image($id)) $id = 0;

    $cache[$post_id] = $id;
    return $id;
}

/**
 * Aplicam rezerva DOAR pe front-end: in wp-admin si in REST (editorul Gutenberg,
 * aplicatii externe) articolul trebuie sa arate exact ce are setat, ca sa nu para
 * ca are imagine cand n-are.
 */
function magpress_fallback_thumb_active() {
    if (is_admin()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (defined('DOING_CRON') && DOING_CRON) return false;
    return true;
}

add_filter('has_post_thumbnail', function ($has, $post, $thumbnail_id) {
    if ($has || !magpress_fallback_thumb_active()) return $has;
    return (bool) magpress_fallback_thumb_id($post);
}, 10, 3);

add_filter('post_thumbnail_id', function ($thumbnail_id, $post) {
    if ($thumbnail_id || !magpress_fallback_thumb_active()) return $thumbnail_id;
    $id = magpress_fallback_thumb_id($post);
    return $id ?: $thumbnail_id;
}, 10, 2);
