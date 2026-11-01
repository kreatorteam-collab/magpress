<?php
/**
 * v0.9.276 — Scanare „SEO Digital” pe site (doar citire, nu modifică nimic).
 *
 * Caută în baza de date a site-ului unde apare:
 *  - textul „SEO Digital” / „seodigital” (orice formă: spațiu, &nbsp;, cratimă);
 *  - linkuri către seodigital.ro — separat cele către https://seodigital.ro/advertoriale/;
 *  - linkuri către o pagină de advertoriale (URL care conține „advertorial”) și
 *    paginile / articolele care au „advertorial” în titlu sau slug.
 *
 * Locuri verificate: articole, pagini și orice tip de conținut (toate statusurile, fără
 * revizii), meta (Elementor, câmpuri custom, URL-urile din meniuri), opțiuni (widget-uri,
 * theme_mods / Customizer, setări pluginuri — fără tranziente), descrieri de categorii,
 * comentarii aprobate, profiluri de utilizator.
 *
 * REST pentru app.targetseo.ro → „SEO Digital pe site-uri”:
 * POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret (X-Magpress-Signature).
 */
if (!defined('ABSPATH')) { exit; }

const MAGPRESS_BS_MAX_ITEMS = 400;   // locuri raportate (articole / opțiuni / meniuri...)
const MAGPRESS_BS_MAX_HITS  = 15;    // potriviri raportate per loc

/** Fragmente pentru filtrul SQL (LIKE e case-insensitive pe colațiile WP). */
function magpress_bs_like_terms() {
    return ['seodigital', 'seo digital', 'seo&nbsp;digital', 'seo-digital', 'seo_digital', 'seo.digital', 'advertorial'];
}

/** „SEO Digital” în orice formă (inclusiv domeniul seodigital.ro). */
function magpress_bs_mention_re() {
    return '~seo(?:\s|&nbsp;|&#160;|\x{00A0}|[-_.])?digital~iu';
}

/** WHERE (col LIKE %t1% OR col LIKE %t2% ...) pentru coloanele date. */
function magpress_bs_where(array $cols) {
    global $wpdb;
    $or = [];
    foreach ($cols as $c) {
        foreach (magpress_bs_like_terms() as $t) {
            $or[] = $wpdb->prepare("$c LIKE %s", '%' . $wpdb->esc_like($t) . '%');
        }
    }
    return '(' . implode(' OR ', $or) . ')';
}

function magpress_bs_clip($s, $len = 160) {
    $s = trim(preg_replace('~\s+~u', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
}

/** Fragment de text în jurul poziției $pos (în octeți) din $text. */
function magpress_bs_snippet($text, $pos, $len) {
    $start = max(0, $pos - 90);
    $chunk = substr($text, $start, $len + 180);
    $chunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8'); // taie caracterele rupte la margini
    return ($start > 0 ? '…' : '') . magpress_bs_clip($chunk, 220);
}

/** E link către seodigital.ro? */
function magpress_bs_is_seodigital_url($href) {
    $h = html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8');
    $host = strtolower((string) parse_url(preg_match('~^//~', $h) ? 'https:' . $h : $h, PHP_URL_HOST));
    if ($host !== '' && preg_match('~(^|\.)seo-?digital\.~', $host)) return true;
    return (bool) preg_match('~^(?:https?:)?//(?:www\.)?seo-?digital\.~i', $h);
}

/**
 * Tipul unui URL: link_sd_advertoriale (seodigital.ro/advertoriale — cel mai important),
 * link_seodigital (altă pagină seodigital.ro), link_advertorial (altă adresă cu
 * „advertorial”), '' (irelevant).
 */
function magpress_bs_url_kind($href) {
    if (magpress_bs_is_seodigital_url($href)) {
        return stripos((string) $href, 'advertorial') !== false ? 'link_sd_advertoriale' : 'link_seodigital';
    }
    return stripos((string) $href, 'advertorial') !== false ? 'link_advertorial' : '';
}

/**
 * Analizează un text (HTML, JSON, serializat) și întoarce potrivirile:
 *  link_sd_advertoriale — <a href> către seodigital.ro/advertoriale/
 *  link_seodigital  — <a href> către altă pagină seodigital.ro
 *  link_advertorial — <a href> către o adresă care conține „advertorial”
 *  text             — „SEO Digital” / „seodigital” în afara linkurilor de mai sus
 *                     (inclusiv ancore „SEO Digital” spre alte adrese, atribute, cod)
 */
function magpress_bs_analyze($raw) {
    $raw = (string) $raw;
    if ($raw === '') return [];
    // JSON (Elementor, blocuri) are ghilimelele și slash-urile escapate.
    $html = str_replace(['\\"', '\\/'], ['"', '/'], $raw);
    $hits = [];
    $rest = $html;

    if (preg_match_all('~<a\b([^>]*)>(.*?)</a\s*>~isu', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            if (!preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $a[1], $hm)
                && !preg_match('~\bhref\s*=\s*([^\s>]+)~is', $a[1], $hm2)) continue;
            $href = isset($hm[2]) ? $hm[2] : $hm2[1];
            $kind = magpress_bs_url_kind($href);
            if ($kind === '') continue;
            $rel = preg_match('~\brel\s*=\s*(["\'])(.*?)\1~is', $a[1], $rm) ? strtolower(trim($rm[2])) : '';
            $hits[] = [
                'kind'   => $kind,
                'href'   => mb_substr(html_entity_decode($href, ENT_QUOTES, 'UTF-8'), 0, 300),
                'anchor' => magpress_bs_clip($a[2], 100),
                'rel'    => $rel,
            ];
            $rest = str_replace($a[0], ' ', $rest); // ca să nu fie numărat și ca text
        }
    }

    // URL-uri fără <a> (meta _menu_item_url, atribute de bloc/Elementor, text simplu)
    if (preg_match_all('~(?:https?:)?//[^\s"\'<>()\\\\]+~i', $rest, $um)) {
        foreach (array_unique($um[0]) as $u) {
            $kind = magpress_bs_url_kind($u);
            if ($kind === '' || ($kind === 'link_advertorial' && !preg_match('~^(?:https?:)?//~i', $u))) continue;
            $hits[] = ['kind' => $kind, 'href' => mb_substr(html_entity_decode($u, ENT_QUOTES, 'UTF-8'), 0, 300), 'anchor' => '', 'rel' => '', 'field_note' => 'URL fără ancoră (meniu / cod / text)'];
            $rest = str_replace($u, ' ', $rest);
        }
    }

    if (preg_match_all(magpress_bs_mention_re(), $rest, $tm, PREG_OFFSET_CAPTURE)) {
        $last = -1000;
        foreach ($tm[0] as $t) {
            if ($t[1] - $last < 120) continue; // aceeași frază — un singur rezultat
            $last = $t[1];
            $hits[] = ['kind' => 'text', 'snippet' => magpress_bs_snippet($rest, $t[1], strlen($t[0]))];
            if (count($hits) >= MAGPRESS_BS_MAX_HITS) break;
        }
    }
    return array_slice($hits, 0, MAGPRESS_BS_MAX_HITS);
}

function magpress_bs_scan() {
    global $wpdb;
    $t0 = microtime(true);
    $items = [];
    $truncated = false;
    $add = function (array $item) use (&$items, &$truncated) {
        if (empty($item['hits'])) return;
        if (count($items) >= MAGPRESS_BS_MAX_ITEMS) { $truncated = true; return; }
        $items[] = $item;
    };

    // ---- 1. articole / pagini / orice tip de conținut ----
    $ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type NOT IN ('revision','customize_changeset','oembed_cache','nav_menu_item')
           AND post_status NOT IN ('auto-draft')
           AND (" . magpress_bs_where(['post_content', 'post_title', 'post_excerpt']) . "
                OR post_name LIKE '%advertorial%')
         ORDER BY ID DESC LIMIT 3000"
    );
    foreach (array_chunk(array_map('intval', $ids), 100) as $chunk) {
        $rows = $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_name, post_content, post_excerpt
            FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $chunk) . ")");
        foreach ($rows as $p) {
            $hits = magpress_bs_analyze($p->post_content);
            foreach (magpress_bs_analyze($p->post_excerpt) as $h) $hits[] = $h + ['field' => 'rezumat'];
            foreach (magpress_bs_analyze($p->post_title) as $h) $hits[] = $h + ['field' => 'titlu'];
            if (stripos($p->post_title . ' ' . $p->post_name, 'advertorial') !== false) {
                $hits[] = ['kind' => 'page_advertorial', 'snippet' => 'Titlu/slug: ' . magpress_bs_clip($p->post_title, 80) . ' (/' . $p->post_name . '/)'];
            }
            $add([
                'where'  => 'post',
                'id'     => (int) $p->ID,
                'type'   => $p->post_type,
                'status' => $p->post_status,
                'title'  => magpress_bs_clip($p->post_title, 120),
                'url'    => $p->post_status === 'publish' ? (string) get_permalink($p) : '',
                'edit'   => admin_url('post.php?post=' . (int) $p->ID . '&action=edit'),
                'hits'   => array_slice($hits, 0, MAGPRESS_BS_MAX_HITS),
            ]);
        }
    }

    // ---- 2. meta (meniuri, Elementor, câmpuri custom) ----
    $skip_meta = "'_edit_lock','_edit_last','_wp_old_slug','_wp_attachment_metadata','_magpress_ai_auto','_magpress_ai_vision'";
    $metas = $wpdb->get_results(
        "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_status, p.post_title
         FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key NOT IN ($skip_meta)
           AND p.post_type NOT IN ('revision') AND p.post_status NOT IN ('auto-draft')
           AND " . magpress_bs_where(['m.meta_value']) . "
         ORDER BY m.post_id DESC LIMIT 600"
    );
    foreach ($metas as $mrow) {
        $hits = magpress_bs_analyze($mrow->meta_value);
        if ($mrow->post_type === 'nav_menu_item') {
            $item  = wp_setup_nav_menu_item(get_post((int) $mrow->post_id));
            $menus = wp_get_object_terms((int) $mrow->post_id, 'nav_menu');
            $menu  = (!is_wp_error($menus) && $menus) ? $menus[0] : null;
            $title = $item && isset($item->title) ? $item->title : $mrow->post_title;
            foreach ($hits as &$h) { if (isset($h['anchor']) && $h['anchor'] === '') $h['anchor'] = magpress_bs_clip($title, 100); }
            unset($h);
            $add([
                'where'  => 'menu',
                'id'     => (int) $mrow->post_id,
                'type'   => 'nav_menu_item',
                'status' => $mrow->post_status,
                'title'  => 'Meniu „' . ($menu ? $menu->name : '?') . '” → ' . magpress_bs_clip($title, 80),
                'url'    => '',
                'edit'   => admin_url('nav-menus.php' . ($menu ? '?action=edit&menu=' . (int) $menu->term_id : '')),
                'field'  => $mrow->meta_key,
                'hits'   => $hits,
            ]);
            continue;
        }
        $add([
            'where'  => 'meta',
            'id'     => (int) $mrow->post_id,
            'type'   => $mrow->post_type,
            'status' => $mrow->post_status,
            'title'  => magpress_bs_clip($mrow->post_title, 120),
            'url'    => $mrow->post_status === 'publish' ? (string) get_permalink((int) $mrow->post_id) : '',
            'edit'   => admin_url('post.php?post=' . (int) $mrow->post_id . '&action=edit'),
            'field'  => $mrow->meta_key,
            'hits'   => $hits,
        ]);
    }

    // ---- 3. opțiuni (widget-uri, Customizer, pluginuri) ----
    $opts = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options}
         WHERE option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%'
           AND option_name NOT IN ('rewrite_rules','cron','magpress_deploy_secret','recently_edited')
           AND " . magpress_bs_where(['option_value']) . "
         LIMIT 300"
    );
    foreach ($opts as $o) {
        $name = $o->option_name;
        $val  = maybe_unserialize($o->option_value);
        if (strpos($name, 'widget_') === 0 && is_array($val)) {
            foreach ($val as $idx => $inst) {
                if (!is_array($inst)) continue;
                $hits = magpress_bs_analyze(serialize($inst));
                $wt = isset($inst['title']) && $inst['title'] !== '' ? ' „' . magpress_bs_clip($inst['title'], 60) . '”' : '';
                $add([
                    'where' => 'widget', 'id' => 0, 'type' => $name, 'status' => '',
                    'title' => 'Widget ' . substr($name, 7) . '-' . $idx . $wt,
                    'url' => '', 'edit' => admin_url('widgets.php'), 'field' => $name, 'hits' => $hits,
                ]);
            }
            continue;
        }
        $label = strpos($name, 'theme_mods_') === 0 ? 'Customizer (' . $name . ')' : 'Setare: ' . $name;
        $add([
            'where' => 'option', 'id' => 0, 'type' => $name, 'status' => '',
            'title' => $label, 'url' => '',
            'edit'  => strpos($name, 'theme_mods_') === 0 ? admin_url('customize.php') : admin_url('options.php'),
            'field' => $name, 'hits' => magpress_bs_analyze($o->option_value),
        ]);
    }

    // ---- 4. descrieri de categorii / etichete ----
    $terms = $wpdb->get_results(
        "SELECT t.term_id, t.name, tt.taxonomy, tt.description FROM {$wpdb->term_taxonomy} tt
         JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
         WHERE " . magpress_bs_where(['tt.description', 't.name']) . " LIMIT 100"
    );
    foreach ($terms as $t) {
        $hits = array_merge(magpress_bs_analyze($t->description), magpress_bs_analyze($t->name));
        $link = get_term_link((int) $t->term_id, $t->taxonomy);
        $add([
            'where' => 'term', 'id' => (int) $t->term_id, 'type' => $t->taxonomy, 'status' => '',
            'title' => ucfirst($t->taxonomy) . ': ' . magpress_bs_clip($t->name, 80),
            'url'   => is_wp_error($link) ? '' : (string) $link,
            'edit'  => admin_url('term.php?taxonomy=' . rawurlencode($t->taxonomy) . '&tag_ID=' . (int) $t->term_id),
            'hits'  => $hits,
        ]);
    }

    // ---- 5. comentarii aprobate ----
    $comments = $wpdb->get_results(
        "SELECT comment_ID, comment_post_ID, comment_author, comment_author_url, comment_content FROM {$wpdb->comments}
         WHERE comment_approved = '1' AND " . magpress_bs_where(['comment_content', 'comment_author_url', 'comment_author']) . "
         ORDER BY comment_ID DESC LIMIT 100"
    );
    foreach ($comments as $c) {
        $hits = magpress_bs_analyze($c->comment_content);
        if ($c->comment_author_url !== '') {
            foreach (magpress_bs_analyze('<a href="' . $c->comment_author_url . '">' . $c->comment_author . '</a>') as $h) $hits[] = $h + ['field' => 'site autor'];
        }
        $add([
            'where' => 'comment', 'id' => (int) $c->comment_ID, 'type' => 'comment', 'status' => 'aprobat',
            'title' => 'Comentariu de ' . magpress_bs_clip($c->comment_author, 60) . ' la „' . magpress_bs_clip(get_the_title((int) $c->comment_post_ID), 60) . '”',
            'url'   => (string) get_comment_link((int) $c->comment_ID),
            'edit'  => admin_url('comment.php?action=editcomment&c=' . (int) $c->comment_ID),
            'hits'  => $hits,
        ]);
    }

    // ---- 6. utilizatori (site + biografie, apar în caseta autorului) ----
    $users = $wpdb->get_results(
        "SELECT u.ID, u.display_name, u.user_url, COALESCE(d.meta_value, '') AS bio FROM {$wpdb->users} u
         LEFT JOIN {$wpdb->usermeta} d ON d.user_id = u.ID AND d.meta_key = 'description'
         WHERE " . magpress_bs_where(['u.user_url', 'u.display_name', 'd.meta_value']) . " LIMIT 50"
    );
    foreach ($users as $u) {
        $hits = magpress_bs_analyze($u->bio);
        foreach (magpress_bs_analyze($u->display_name) as $h) $hits[] = $h + ['field' => 'nume afișat'];
        if ($u->user_url !== '') {
            foreach (magpress_bs_analyze('<a href="' . $u->user_url . '">' . $u->display_name . '</a>') as $h) $hits[] = $h + ['field' => 'site autor'];
        }
        $add([
            'where' => 'user', 'id' => (int) $u->ID, 'type' => 'user', 'status' => '',
            'title' => 'Autor: ' . magpress_bs_clip($u->display_name, 80),
            'url'   => (string) get_author_posts_url((int) $u->ID),
            'edit'  => admin_url('user-edit.php?user_id=' . (int) $u->ID),
            'hits'  => $hits,
        ]);
    }

    $counts = ['link_sd_advertoriale' => 0, 'text' => 0, 'link_seodigital' => 0, 'link_advertorial' => 0, 'page_advertorial' => 0];
    foreach ($items as $it) {
        foreach ($it['hits'] as $h) {
            if (isset($counts[$h['kind']])) $counts[$h['kind']]++;
        }
    }

    return [
        'counts'    => $counts,
        'items'     => $items,
        'truncated' => $truncated,
        'took_ms'   => (int) round((microtime(true) - $t0) * 1000),
    ];
}

// =========================================================================
// REST: POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret
// {action:"scan"} → {ok, theme_version, home, counts, items[], truncated, took_ms}
// =========================================================================
add_action('rest_api_init', function () {
    register_rest_route('magpress/v1', '/brand-scan', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_brand_scan',
        'permission_callback' => '__return_true', // validăm HMAC manual
    ]);
});

function magpress_rest_brand_scan(WP_REST_Request $request) {
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
    if (function_exists('set_time_limit')) @set_time_limit(60);

    $out = ['ok' => true, 'theme_version' => MAGPRESS_VERSION, 'home' => home_url('/')];
    return new WP_REST_Response($out + magpress_bs_scan(), 200);
}
