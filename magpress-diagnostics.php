<?php
/**
 * Magpress Diagnostics — admin page la `Unelte → Magpress Diagnoză` pentru identificat
 * ce face admin-ul lent. 3 secțiuni:
 *  1. Log apeluri HTTP externe — identifică plugin-uri care fac API calls lente
 *  2. Atribuire JS/CSS per plugin — vezi cine încarcă cele 100+ JS files
 *  3. Timing pe hook-uri WP majore
 *
 * Activ DOAR în admin. Zero overhead pe frontend.
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// HTTP LOG — interceptează toate apelurile WP_Http către URL-uri externe
// ============================================================================

class Magpress_Diag_Http_Logger {
    const LOG_KEY = 'magpress_diag_http_log';
    const MAX_ENTRIES = 100;
    private static $current = null;

    public static function init() {
        if (!is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) return;
        add_filter('pre_http_request', [__CLASS__, 'before'], 999, 3);
        add_filter('http_response',    [__CLASS__, 'after'],  999, 3);
    }

    public static function before($preempt, $args, $url) {
        // PHP single-threaded → max 1 HTTP request în zbor la un moment dat.
        self::$current = [
            'url'    => $url,
            'method' => $args['method'] ?? 'GET',
            'start'  => microtime(true),
            'caller' => self::guess_caller(),
        ];
        return $preempt;
    }

    public static function after($response, $args, $url) {
        if (!self::$current) return $response;
        $duration = microtime(true) - self::$current['start'];
        $status = is_wp_error($response)
            ? 'ERR: ' . substr($response->get_error_message(), 0, 50)
            : (string) wp_remote_retrieve_response_code($response);

        self::log_entry([
            'time'        => date('H:i:s'),
            'url'         => substr($url, 0, 200),
            'method'      => self::$current['method'],
            'duration_ms' => round($duration * 1000),
            'status'      => $status,
            'page'        => substr($_SERVER['REQUEST_URI'] ?? '', 0, 100),
            'caller'      => self::$current['caller'],
        ]);
        self::$current = null;
        return $response;
    }

    /**
     * Caută în backtrace prima funcție din /wp-content/plugins/SLUG/
     * sau /wp-content/themes/SLUG/ pentru a atribui apelul corect.
     */
    private static function guess_caller() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        foreach ($trace as $t) {
            if (empty($t['file'])) continue;
            $file = $t['file'];
            if (preg_match('|/wp-content/plugins/([^/]+)/|', $file, $m)) return $m[1];
            if (preg_match('|/wp-content/themes/([^/]+)/|', $file, $m))  return 'theme:' . $m[1];
        }
        return 'wp-core?';
    }

    public static function log_entry($entry) {
        $log = get_option(self::LOG_KEY, []);
        if (!is_array($log)) $log = [];
        array_unshift($log, $entry);
        if (count($log) > self::MAX_ENTRIES) $log = array_slice($log, 0, self::MAX_ENTRIES);
        update_option(self::LOG_KEY, $log, false);
    }

    public static function get_log() {
        $log = get_option(self::LOG_KEY, []);
        return is_array($log) ? $log : [];
    }

    public static function clear() {
        delete_option(self::LOG_KEY);
    }
}
Magpress_Diag_Http_Logger::init();


// ============================================================================
// HOOK TIMING — măsoară timpul total petrecut în hook-urile majore
// ============================================================================

class Magpress_Diag_Hook_Timer {
    const KEY = 'magpress_diag_hook_timing';
    private static $hooks = ['init', 'admin_init', 'wp_loaded', 'admin_enqueue_scripts', 'admin_head', 'admin_footer'];
    private static $starts = [];

    public static function init() {
        if (!is_admin()) return;
        foreach (self::$hooks as $hook) {
            add_action($hook, [__CLASS__, 'mark_start'], -999999);
            add_action($hook, [__CLASS__, 'mark_end'],   999999);
        }
    }

    public static function mark_start() {
        self::$starts[current_action()] = microtime(true);
    }

    public static function mark_end() {
        $hook = current_action();
        if (!isset(self::$starts[$hook])) return;
        $d = microtime(true) - self::$starts[$hook];
        unset(self::$starts[$hook]);

        $data = get_option(self::KEY, []);
        if (!is_array($data)) $data = [];
        if (!isset($data[$hook])) $data[$hook] = ['count' => 0, 'total' => 0, 'max' => 0, 'last' => 0];
        $data[$hook]['count']++;
        $data[$hook]['total'] += $d;
        $data[$hook]['max']    = max($data[$hook]['max'], $d);
        $data[$hook]['last']   = $d;
        update_option(self::KEY, $data, false);
    }

    public static function get_data() {
        $data = get_option(self::KEY, []);
        return is_array($data) ? $data : [];
    }

    public static function clear() {
        delete_option(self::KEY);
    }
}
Magpress_Diag_Hook_Timer::init();


// ============================================================================
// JS/CSS ATTRIBUTION — runtime scan al $wp_scripts și $wp_styles
// ============================================================================

function magpress_diag_get_assets() {
    global $wp_scripts, $wp_styles;
    $by_plugin = [];

    foreach ([['s' => $wp_scripts, 'type' => 'js'], ['s' => $wp_styles, 'type' => 'css']] as $set) {
        if (!$set['s']) continue;
        // Doar handle-urile efectiv ENQUEUE-uite, nu doar registered
        $queue = array_unique(array_merge((array) $set['s']->queue, (array) $set['s']->done));
        foreach ($queue as $handle) {
            $obj = $set['s']->registered[$handle] ?? null;
            if (!$obj || empty($obj->src)) continue;
            $plugin = magpress_diag_guess_plugin_from_src($obj->src);
            if (!isset($by_plugin[$plugin])) $by_plugin[$plugin] = ['js' => [], 'css' => []];
            $by_plugin[$plugin][$set['type']][] = $handle;
        }
    }
    return $by_plugin;
}

function magpress_diag_guess_plugin_from_src($src) {
    if (preg_match('|/wp-content/plugins/([^/]+)/|', $src, $m)) return 'plugin: ' . $m[1];
    if (preg_match('|/wp-content/themes/([^/]+)/|',  $src, $m)) return 'theme: '  . $m[1];
    if (strpos($src, '/wp-includes/') !== false) return 'wp-core (includes)';
    if (strpos($src, '/wp-admin/')    !== false) return 'wp-core (admin)';
    if (preg_match('#^https?://([^/]+)/#', $src, $m)) return 'extern: ' . $m[1];
    return 'unknown';
}


// ============================================================================
// ADMIN MENU + RENDER PAGINĂ DIAGNOZĂ
// ============================================================================

function magpress_diag_register_menu() {
    add_management_page(
        'Magpress Diagnoză',
        '🔍 Magpress Diagnoză',
        'manage_options',
        'magpress-diagnostics',
        'magpress_diag_render_page'
    );
}
add_action('admin_menu', 'magpress_diag_register_menu');

/**
 * Export JSON cu toate datele de diagnoză — pentru trimis dezvoltatorului.
 * Hook admin_init (priority -1) ca să poată trimite header download înainte de orice output.
 */
function magpress_diag_handle_export() {
    if (!is_admin()) return;
    if (empty($_GET['page']) || $_GET['page'] !== 'magpress-diagnostics') return;
    if (empty($_GET['export'])) return;
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'magpress_diag_export')) {
        wp_die('Nonce invalid.');
    }

    $format = sanitize_key($_GET['export']);
    $payload = [
        'meta' => [
            'site_url'        => home_url(),
            'site_name'       => get_bloginfo('name'),
            'magpress_ver'    => wp_get_theme()->get('Version'),
            'wp_ver'          => get_bloginfo('version'),
            'php_ver'         => PHP_VERSION,
            'memory_limit'    => ini_get('memory_limit'),
            'max_execution'   => ini_get('max_execution_time'),
            'export_time'     => date('Y-m-d H:i:s'),
            'active_plugins'  => array_values((array) get_option('active_plugins', [])),
        ],
        'http_log'      => Magpress_Diag_Http_Logger::get_log(),
        'hook_timing'   => Magpress_Diag_Hook_Timer::get_data(),
        'current_page_assets' => magpress_diag_get_assets(),
    ];

    $slug = sanitize_title(parse_url(home_url(), PHP_URL_HOST));
    $ts   = date('Ymd-His');
    $fname = "magpress-diag-{$slug}-{$ts}";

    if ($format === 'csv') {
        // CSV doar pentru HTTP log (cea mai utilă vedere tabulară)
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM UTF-8 pentru Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Timp', 'Plugin/Caller', 'Metodă', 'URL', 'Durată (ms)', 'Status', 'Pagină']);
        foreach ($payload['http_log'] as $e) {
            fputcsv($out, [
                $e['time'] ?? '', $e['caller'] ?? '', $e['method'] ?? '',
                $e['url'] ?? '', $e['duration_ms'] ?? 0,
                $e['status'] ?? '', $e['page'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // Default: JSON complet
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '.json"');
    echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
add_action('admin_init', 'magpress_diag_handle_export', -1);

function magpress_diag_render_page() {
    if (!current_user_can('manage_options')) return;

    // Acțiuni
    if (isset($_POST['magpress_diag_clear']) && check_admin_referer('magpress_diag_clear')) {
        Magpress_Diag_Http_Logger::clear();
        Magpress_Diag_Hook_Timer::clear();
        echo '<div class="notice notice-success"><p>Log golit.</p></div>';
    }

    $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'http';
    $tabs = [
        'http'   => '🌐 HTTP Log',
        'assets' => '📦 JS/CSS per Plugin',
        'hooks'  => '⏱️ Hook Timing',
    ];
    ?>
    <div class="wrap">
        <h1>🔍 Magpress Diagnoză</h1>
        <p>Identifică ce face admin-ul lent. Navighează prin admin (Module, Editor, Setări) ca să acumulezi date, apoi revino aici.</p>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $k => $label): ?>
                <a href="?page=magpress-diagnostics&tab=<?php echo esc_attr($k); ?>" class="nav-tab <?php echo $tab === $k ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </h2>

        <div style="background:#fff;padding:20px;margin-top:0;border:1px solid #ccd0d4;border-top:0">
        <?php
        if     ($tab === 'http')   magpress_diag_render_http();
        elseif ($tab === 'assets') magpress_diag_render_assets();
        elseif ($tab === 'hooks')  magpress_diag_render_hooks();
        ?>
        </div>

        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <form method="post" style="margin:0">
                <?php wp_nonce_field('magpress_diag_clear'); ?>
                <button type="submit" name="magpress_diag_clear" class="button">🗑️ Golește toate datele</button>
            </form>

            <?php
            $export_json = wp_nonce_url(
                admin_url('tools.php?page=magpress-diagnostics&export=json'),
                'magpress_diag_export'
            );
            $export_csv = wp_nonce_url(
                admin_url('tools.php?page=magpress-diagnostics&export=csv'),
                'magpress_diag_export'
            );
            ?>
            <a href="<?php echo esc_url($export_json); ?>" class="button button-primary">📥 Export JSON (complet)</a>
            <a href="<?php echo esc_url($export_csv); ?>" class="button">📊 Export CSV (doar HTTP log)</a>

            <span style="color:#666;font-size:.9em">Trimite JSON-ul dezvoltatorului pentru analiză.</span>
        </div>
    </div>
    <?php
}

function magpress_diag_render_http() {
    $log = Magpress_Diag_Http_Logger::get_log();
    ?>
    <h2>Apeluri HTTP externe (ultimele <?php echo count($log); ?>)</h2>
    <p><strong>Caută rânduri ROȘII (>3 sec) sau PORTOCALII (>1 sec)</strong> — alea sunt vinovatele pentru admin lent.</p>
    <p>Plugin-uri ca <em>Next Stage Writer / Schedule / Social</em>, <em>Site Kit</em>, <em>Imunify</em> apelează API-uri externe — dacă API-ul lor e lent sau down, admin-ul devine lent.</p>

    <?php if (empty($log)): ?>
        <p style="padding:20px;background:#f0f0f1">📭 Niciun apel logat încă. Navighează prin admin (deschide editor articol, Setări, Module) timp de 30 sec, apoi revino.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width:70px">Timp</th>
                <th style="width:160px">Plugin / Caller</th>
                <th>URL</th>
                <th style="width:80px">Durată</th>
                <th style="width:70px">Status</th>
                <th style="width:180px">Pagină</th>
            </tr></thead>
            <tbody>
            <?php foreach ($log as $e):
                $verySlow = $e['duration_ms'] > 3000;
                $slow     = $e['duration_ms'] > 1000;
                $bg = $verySlow ? '#ffd6d6' : ($slow ? '#fff3cd' : '');
            ?>
                <tr style="background:<?php echo $bg; ?>">
                    <td><?php echo esc_html($e['time']); ?></td>
                    <td><strong><?php echo esc_html($e['caller']); ?></strong></td>
                    <td style="font-size:.85em;word-break:break-all"><?php echo esc_html($e['url']); ?></td>
                    <td><strong style="color:<?php echo $verySlow ? '#a00' : ($slow ? '#a60' : '#060'); ?>"><?php echo (int) $e['duration_ms']; ?> ms</strong></td>
                    <td><?php echo esc_html($e['status']); ?></td>
                    <td style="font-size:.85em"><?php echo esc_html($e['page']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:15px"><strong>Totaluri:</strong>
            Total apeluri: <?php echo count($log); ?> |
            Sumă durată: <?php echo number_format(array_sum(array_column($log, 'duration_ms'))); ?> ms |
            Cele cu &gt;1s: <?php echo count(array_filter($log, fn($e) => $e['duration_ms'] > 1000)); ?>
        </p>
    <?php endif;
}

function magpress_diag_render_assets() {
    $assets = magpress_diag_get_assets();
    uasort($assets, fn($a, $b) => (count($b['js']) + count($b['css'])) - (count($a['js']) + count($a['css'])));
    ?>
    <h2>JS/CSS încărcate pe ACEASTĂ pagină admin, grupate per plugin</h2>
    <p><strong>Caută plugin-urile cu cele mai multe handle-uri.</strong> Acelea încarcă bloat-ul de 100+ JS files. Dacă vezi un plugin cu 15-30 JS/CSS care nu îți e util → dezactivează-l.</p>

    <table class="wp-list-table widefat fixed striped">
        <thead><tr>
            <th>Plugin / Sursă</th>
            <th style="width:80px">JS</th>
            <th style="width:80px">CSS</th>
            <th style="width:80px">Total</th>
            <th>Handle-uri JS (primele 5)</th>
        </tr></thead>
        <tbody>
        <?php foreach ($assets as $plugin => $files):
            $total = count($files['js']) + count($files['css']);
            if ($total === 0) continue;
            $bg = $total > 15 ? '#ffd6d6' : ($total > 8 ? '#fff3cd' : '');
        ?>
            <tr style="background:<?php echo $bg; ?>">
                <td><strong><?php echo esc_html($plugin); ?></strong></td>
                <td><?php echo count($files['js']); ?></td>
                <td><?php echo count($files['css']); ?></td>
                <td><strong><?php echo $total; ?></strong></td>
                <td style="font-size:.85em;color:#666"><?php echo esc_html(implode(', ', array_slice($files['js'], 0, 5))); ?><?php if (count($files['js']) > 5) echo '...'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:15px"><em>NOTĂ: scanarea arată DOAR fișierele încărcate pe ACEASTĂ pagină (diagnoză). Pe alte pagini admin (editor articol, etc.) numărul poate fi mai mare.</em></p>
    <?php
}

function magpress_diag_render_hooks() {
    $data = Magpress_Diag_Hook_Timer::get_data();
    uasort($data, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
    ?>
    <h2>Timing acumulat pe hook-urile WP majore</h2>
    <p>Timpul TOTAL petrecut pe fiecare hook din toate request-urile admin de la ultimul „Golește". Hook-urile cu durată mare = punct de inserție pentru plugin-uri lente.</p>

    <?php if (empty($data)): ?>
        <p style="padding:20px;background:#f0f0f1">📭 Niciun timing încă. Navighează în admin câteva pagini și revino.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th>Hook</th>
                <th style="width:80px">Apeluri</th>
                <th style="width:100px">Total (sec)</th>
                <th style="width:100px">Medie (ms)</th>
                <th style="width:100px">Max (ms)</th>
                <th style="width:100px">Ultimul (ms)</th>
            </tr></thead>
            <tbody>
            <?php foreach ($data as $hook => $d):
                $avg = $d['count'] > 0 ? ($d['total'] / $d['count']) * 1000 : 0;
                $bg = $avg > 500 ? '#ffd6d6' : ($avg > 200 ? '#fff3cd' : '');
            ?>
                <tr style="background:<?php echo $bg; ?>">
                    <td><strong><?php echo esc_html($hook); ?></strong></td>
                    <td><?php echo (int) $d['count']; ?></td>
                    <td><?php echo number_format($d['total'], 3); ?></td>
                    <td><?php echo number_format($avg, 0); ?></td>
                    <td><?php echo number_format(($d['max']  ?? 0) * 1000, 0); ?></td>
                    <td><?php echo number_format(($d['last'] ?? 0) * 1000, 0); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
}
