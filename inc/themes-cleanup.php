<?php
/**
 * v0.9.280 — Teme inactive: listare + ștergere, comandată din app.targetseo.ro → „Teme inactive”
 * (pe toată flota deodată).
 *
 * Nu se șterg NICIODATĂ: tema activă (stylesheet), tema părinte a celei active (template),
 * tema Magpress însăși și temele din lista `keep`. Dacă o temă păstrată e copilul unei teme
 * de șters, părintele rămâne și el (altfel tema păstrată s-ar strica).
 * Pe multisite nu se șterge nimic (temele pot fi active pe alte site-uri din rețea).
 *
 * purge_mods = șterge și setările din Customizer ale temelor șterse (opțiunea
 * theme_mods_{slug}); implicit rămân, ca la o reinstalare tema să-și regăsească setările.
 *
 * REST: POST /wp-json/magpress/v1/themes, semnat HMAC cu deploy_secret (X-Magpress-Signature)
 *  {action:"list"}
 *  {action:"delete", keep?:[slug,...], only?:[slug,...], purge_mods?:bool}
 *  → {ok, theme_version, multisite, active:{stylesheet, template}, themes:[...] [, deleted, failed, skipped]}
 */
if (!defined('ABSPATH')) { exit; }

/** Slug-urile care nu se șterg pe acest site (active + părinte + Magpress). */
function magpress_tc_protected() {
    return array_values(array_unique(array_map('strtolower', [get_stylesheet(), get_template(), 'magpress'])));
}

/** Mărimea unui director în KB (oprită după 30.000 de fișiere ca să nu țină cererea). */
function magpress_tc_dir_kb($dir) {
    $bytes = 0;
    $n = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) $bytes += (int) $f->getSize();
            if (++$n > 30000) break;
        }
    } catch (Exception $e) {
        // director ilizibil: raportăm ce s-a putut număra
    }
    return (int) round($bytes / 1024);
}

/** Toate temele instalate (inclusiv cele stricate), cu starea fiecăreia. */
function magpress_tc_list() {
    $protected = magpress_tc_protected();
    $active    = strtolower(get_stylesheet());
    $parent    = strtolower(get_template());
    $out = [];
    foreach ((array) wp_get_themes(['errors' => null]) as $slug => $theme) {
        $slug = (string) $slug;
        $sl   = strtolower($slug);
        $err  = $theme->errors();
        $out[] = [
            'slug'      => $slug,
            'name'      => (string) $theme->get('Name') ?: $slug,
            'version'   => (string) $theme->get('Version'),
            'parent'    => (string) $theme->get_template() !== $slug ? (string) $theme->get_template() : '',
            'active'    => $sl === $active,
            'is_parent' => $sl === $parent && $parent !== $active,
            'broken'    => is_wp_error($err) ? $err->get_error_message() : '',
            'deletable' => !in_array($sl, $protected, true),
            'size_kb'   => magpress_tc_dir_kb($theme->get_stylesheet_directory()),
        ];
    }
    usort($out, function ($a, $b) {
        return [!$a['active'], !$a['is_parent'], $a['slug']] <=> [!$b['active'], !$b['is_parent'], $b['slug']];
    });
    return $out;
}

/**
 * Șterge temele inactive. $keep = slug-uri păstrate; $only = dacă e dat, doar acestea
 * (tot fără cele protejate). Return [deleted[], failed[slug => motiv], skipped[slug => motiv]].
 */
function magpress_tc_delete(array $keep, $only = null, $purge_mods = false) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';

    $protected = magpress_tc_protected();
    $keep      = array_map('strtolower', array_map('strval', $keep));
    $only      = is_array($only) ? array_map('strtolower', array_map('strval', $only)) : null;
    $themes    = (array) wp_get_themes(['errors' => null]);

    $targets = [];
    $skipped = [];
    foreach ($themes as $slug => $theme) {
        $sl = strtolower((string) $slug);
        if ($only !== null && !in_array($sl, $only, true)) continue;
        if (in_array($sl, $protected, true)) { $skipped[$slug] = 'activă / părinte / Magpress'; continue; }
        if (in_array($sl, $keep, true))      { $skipped[$slug] = 'păstrată'; continue; }
        $targets[(string) $slug] = $theme;
    }
    // Părintele unei teme care rămâne instalată nu se șterge (tema copil s-ar strica).
    foreach ($themes as $slug => $theme) {
        if (isset($targets[(string) $slug])) continue;
        $tpl = (string) $theme->get_template();
        if ($tpl !== '' && $tpl !== (string) $slug && isset($targets[$tpl])) {
            unset($targets[$tpl]);
            $skipped[$tpl] = 'părintele temei „' . $slug . '”, care rămâne';
        }
    }

    $deleted = [];
    $failed  = [];
    if (!$targets) return [$deleted, $failed, $skipped];

    add_filter('filesystem_method', function () { return 'direct'; });
    if (!WP_Filesystem()) {
        foreach (array_keys($targets) as $slug) $failed[$slug] = 'filesystem_unavailable';
        return [$deleted, $failed, $skipped];
    }
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) @set_time_limit(120);

    foreach (array_keys($targets) as $slug) {
        $r = delete_theme($slug);
        if (is_wp_error($r)) {
            $failed[$slug] = $r->get_error_message();
        } elseif ($r === true) {
            $deleted[] = $slug;
            if ($purge_mods) delete_option('theme_mods_' . $slug);
        } else {
            $failed[$slug] = 'delete_failed';
        }
    }
    if ($deleted) {
        delete_site_transient('update_themes');
        wp_clean_themes_cache();
    }
    return [$deleted, $failed, $skipped];
}

// =========================================================================
// REST: POST /wp-json/magpress/v1/themes, semnat HMAC cu deploy_secret
// =========================================================================
add_action('rest_api_init', function () {
    register_rest_route('magpress/v1', '/themes', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_themes',
        'permission_callback' => '__return_true', // validăm HMAC manual
    ]);
});

function magpress_rest_themes(WP_REST_Request $request) {
    $body = $request->get_body();
    $sig  = (string) $request->get_header('x_magpress_signature');
    if ($sig === '' || !hash_equals(hash_hmac('sha256', $body, magpress_get_deploy_secret()), $sig)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_signature'], 403);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) return new WP_REST_Response(['ok' => false, 'error' => 'invalid_json'], 400);
    if (isset($data['ts']) && abs(time() - (int) $data['ts']) > 3600) {
        return new WP_REST_Response(['ok' => false, 'error' => 'expired'], 403);
    }

    $action = (string) ($data['action'] ?? 'list');
    $out = [
        'ok'            => true,
        'theme_version' => MAGPRESS_VERSION,
        'multisite'     => is_multisite(),
        'active'        => ['stylesheet' => get_stylesheet(), 'template' => get_template()],
    ];

    if ($action === 'delete') {
        if (is_multisite()) {
            // 200 + error: app-ul (FleetWriter) afișează `message` doar la HTTP 200.
            return new WP_REST_Response(['ok' => false, 'error' => 'multisite', 'message' => 'multisite — temele nu se șterg de aici (pot fi active pe alte site-uri din rețea)'], 200);
        }
        $only = isset($data['only']) && is_array($data['only']) ? $data['only'] : null;
        list($deleted, $failed, $skipped) = magpress_tc_delete((array) ($data['keep'] ?? []), $only, !empty($data['purge_mods']));
        $out['deleted'] = $deleted;
        $out['failed']  = (object) $failed;
        $out['skipped'] = (object) $skipped;
    } elseif ($action !== 'list') {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    $out['themes'] = magpress_tc_list();
    return new WP_REST_Response($out, 200);
}
