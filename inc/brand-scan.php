<?php
/**
 * v0.9.276 — Scanare „SEO Digital” pe site.
 * v0.9.277 — căutare custom (termenii tăi) + rel sponsored / nofollow pe linkurile găsite (bulk).
 *
 * Caută în baza de date a site-ului:
 *  Implicit („SEO Digital”):
 *   - link_sd_advertoriale — linkuri către https://seodigital.ro/advertoriale/
 *   - link_seodigital      — linkuri către alte pagini seodigital.ro
 *   - link_advertorial     — linkuri către alte adrese care conțin „advertorial”
 *   - page_advertorial     — pagini / articole cu „advertorial” în titlu sau slug (fără imagini)
 *   - text                 — textul „SEO Digital” / „seodigital” (orice formă)
 *  Custom (până la 5 termeni, ex. „exemplu.ro”, „/promo/”, „Firma X”):
 *   - link_match — linkuri al căror URL conține un termen
 *   - anchor_match — linkuri al căror text vizibil (ancora) conține un termen (v0.9.278)
 *   - page_match — pagini / articole cu termenul în titlu sau slug
 *   - text       — termenul în text (în afara linkurilor de mai sus)
 *
 * Locuri: articole, pagini și orice tip de conținut (toate statusurile, fără revizii), meta
 * (Elementor, câmpuri custom, URL-urile din meniuri), opțiuni (widget-uri, Customizer,
 * setări pluginuri — fără tranziente), descrieri de categorii, comentarii aprobate, autori.
 *
 * set_rel: pune rel="sponsored" / "nofollow" / "sponsored nofollow" (sau „dofollow” = le
 * scoate) pe linkurile de tipurile alese, în: conținut + rezumat, meta (inclusiv Elementor —
 * acolo există doar „nofollow”), meniuri (_menu_item_xfn), widget-uri, opțiuni, categorii.
 * Scrie direct în DB (fără wp_update_post: fără user, kses ar tăia din conținut), după ce
 * salvează o revizie a articolului (backup). Comentariile și autorii nu se modifică.
 *
 * v0.9.280 — set_rel: `domains` (rel doar pe linkurile spre acele domenii) + rel „-sponsored”
 * / „-nofollow” (scoate doar acel cuvânt, restul rel-ului rămâne).
 *
 * v0.9.281 — REGULA DE AUR: linkurile spre alte domenii (ex. linkurile clienților din
 * advertoriale) nu se ating niciodată. set_rel fără `domains` e refuzat (domains_required) și
 * nu modifică nimic; rel-ul se pune doar pe linkurile spre domeniile alese. Dacă un regex
 * eșuează (conținut foarte mare), textul rămâne neschimbat (nu se mai poate goli un articol).
 *
 * v0.9.279 — termeni separați: `terms` pentru URL-ul linkului, `anchor_terms` pentru ancoră,
 * `match` = any (URL SAU ancoră) | both (URL ȘI ancoră, în același link). Text și titlu/slug: toți.
 *
 * v0.9.278 — replace_url: înlocuiește un link vechi cu unul nou (href în conținut, meta /
 * Elementor, meniuri, widget-uri, opțiuni, categorii). Potrivire fără http/https, www și
 * „/” final (ex. https://site.ro/pagina-veche/ = http://www.site.ro/pagina-veche).
 * rel-ul și ancora rămân neschimbate.
 *
 * REST pentru app.targetseo.ro → „Căutare pe site-uri”:
 * POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret (X-Magpress-Signature)
 *  {action:"scan", terms?:[...]}
 *  {action:"set_rel", rel, kinds:[...], terms?:[...], targets?:[key,...]}
 *  {action:"replace_url", from, to, terms?:[...], targets?:[key,...]}
 *  {action:"restore_rel", keep:[domenii], apply:bool}   (v0.9.281)
 */
if (!defined('ABSPATH')) { exit; }

const MAGPRESS_BS_MAX_ITEMS = 400;   // locuri raportate la scanare
const MAGPRESS_BS_MAX_HITS  = 15;    // potriviri raportate per loc
const MAGPRESS_BS_LINK_KINDS = ['link_sd_advertoriale', 'link_seodigital', 'link_advertorial', 'link_match', 'anchor_match'];

/** Max 5 termeni curățați (3-100 caractere). */
function magpress_bs_clean_terms($set) {
    $clean = [];
    foreach ((array) $set as $t) {
        $t = trim(wp_strip_all_tags((string) $t));
        if (mb_strlen($t) >= 3 && mb_strlen($t) <= 100) $clean[mb_strtolower($t)] = $t;
    }
    return array_slice(array_values($clean), 0, 5);
}

/**
 * Căutarea curentă: termeni pentru URL-ul linkului, termeni pentru ancoră și modul
 * (any = linkul se potrivește după URL SAU după ancoră; both = după URL ȘI după ancoră).
 * Fără termeni = căutarea implicită „SEO Digital”.
 */
function magpress_bs_search($url = null, $anchor = null, $mode = null) {
    static $ctx = ['url' => [], 'anchor' => [], 'mode' => 'any'];
    if ($url !== null)    $ctx['url'] = magpress_bs_clean_terms($url);
    if ($anchor !== null) $ctx['anchor'] = magpress_bs_clean_terms($anchor);
    if ($mode !== null)   $ctx['mode'] = $mode === 'both' ? 'both' : 'any';
    return $ctx;
}

/**
 * Toți termenii căutării (URL + ancoră), folosiți pentru filtrul SQL, text și titlu/slug.
 * Cu $set: aceiași termeni pentru URL și ancoră, mod „oricare” (compatibil cu v0.9.278).
 */
function magpress_bs_terms($set = null) {
    if ($set !== null) magpress_bs_search($set, $set, 'any');
    $c = magpress_bs_search();
    $all = [];
    foreach (array_merge($c['url'], $c['anchor']) as $t) $all[mb_strtolower($t)] = $t;
    return array_values($all);
}

/** Fragmente pentru filtrul SQL (LIKE e case-insensitive pe colațiile WP). */
function magpress_bs_like_terms() {
    $t = magpress_bs_terms();
    if (!$t) return ['seodigital', 'seo digital', 'seo&nbsp;digital', 'seo-digital', 'seo_digital', 'seo.digital', 'advertorial'];
    $out = [];
    foreach ($t as $x) {
        $out[] = $x;
        if (strpos($x, '/') !== false) $out[] = str_replace('/', '\\/', $x); // JSON (Elementor): „\/”
    }
    return array_values(array_unique($out));
}

/** Regex pentru text: termenii custom sau „SEO Digital” în orice formă. */
function magpress_bs_mention_re() {
    $t = magpress_bs_terms();
    if ($t) {
        return '~' . implode('|', array_map(function ($x) { return preg_quote($x, '~'); }, $t)) . '~iu';
    }
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

/** Tipul unui URL ('' = irelevant) — după termenii de URL sau regulile implicite. */
function magpress_bs_url_kind($href) {
    $h = html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8');
    if (magpress_bs_terms()) {
        foreach (magpress_bs_search()['url'] as $t) {
            if (mb_stripos($h, $t) !== false) return 'link_match';
        }
        return '';
    }
    if (magpress_bs_is_seodigital_url($h)) {
        return stripos($h, 'advertorial') !== false ? 'link_sd_advertoriale' : 'link_seodigital';
    }
    return stripos($h, 'advertorial') !== false ? 'link_advertorial' : '';
}

/** v0.9.278: link găsit după ancoră — textul vizibil al linkului conține un termen custom. */
function magpress_bs_anchor_kind($anchor_html) {
    $terms = magpress_bs_search()['anchor'];
    if (!$terms) return '';
    $txt = html_entity_decode(wp_strip_all_tags((string) $anchor_html), ENT_QUOTES, 'UTF-8');
    foreach ($terms as $t) {
        if (mb_stripos($txt, $t) !== false) return 'anchor_match';
    }
    return '';
}

/** Domeniul unui link (fără www., litere mici); '' pentru linkuri relative. */
function magpress_bs_host($href) {
    $h = strtolower(trim(html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8')));
    if (!preg_match('~^(?:https?:)?//([^/?#:]+)~', $h, $m)) return '';
    return preg_replace('~^www\.~', '', $m[1]);
}

/**
 * v0.9.280: limitare la domenii (doar pentru set_rel) — rel-ul se pune DOAR pe linkurile
 * spre aceste domenii (ex. seodigital.ro), chiar dacă textul căutat apare și în alte URL-uri
 * (ex. psk.ro/advertoriale/). [] = fără limitare.
 */
function magpress_bs_domains($set = null) {
    static $d = [];
    if ($set !== null) {
        $d = [];
        foreach ((array) $set as $x) {
            $x = magpress_bs_host('//' . preg_replace('~^(?:https?:)?//~i', '', trim((string) $x)));
            if ($x !== '') $d[] = $x;
        }
        $d = array_values(array_unique($d));
    }
    return $d;
}

/** Tipul unui link: după URL, apoi după ancoră; în modul „both” trebuie să se potrivească amândouă. */
function magpress_bs_link_kind($href, $anchor_html) {
    $doms = magpress_bs_domains();
    if ($doms && !in_array(magpress_bs_host($href), $doms, true)) return '';
    $c = magpress_bs_search();
    $k = magpress_bs_url_kind($href);
    if ($c['mode'] === 'both' && $c['url'] && $c['anchor']) {
        return ($k !== '' && magpress_bs_anchor_kind($anchor_html) !== '') ? 'link_match' : '';
    }
    return $k !== '' ? $k : magpress_bs_anchor_kind($anchor_html);
}

/** Tipul „pagină” pentru un titlu/slug ('' = nu e). */
function magpress_bs_page_kind($title, $slug) {
    $s = $title . ' ' . $slug;
    $terms = magpress_bs_terms();
    if ($terms) {
        foreach ($terms as $t) {
            if (mb_stripos($s, $t) !== false) return 'page_match';
        }
        return '';
    }
    return stripos($s, 'advertorial') !== false ? 'page_advertorial' : '';
}

/**
 * Analizează un text (HTML, JSON, serializat) și întoarce potrivirile:
 * linkuri (<a href> și URL-uri fără ancoră) de tipurile de mai sus + text.
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
            if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $a[1], $hm)) $href = $hm[2];
            elseif (preg_match('~\bhref\s*=\s*([^\s>]+)~is', $a[1], $hm)) $href = $hm[1];
            else continue;
            $kind = magpress_bs_link_kind($href, $a[2]);
            if ($kind === '') {
                // link care nu se potrivește: rămâne doar textul lui (pentru căutarea în text)
                $rest = str_replace($a[0], ' ' . $a[2] . ' ', $rest);
                continue;
            }
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
            $kind = magpress_bs_link_kind($u, '');
            if ($kind === '') continue;
            $hits[] = ['kind' => $kind, 'href' => mb_substr(html_entity_decode($u, ENT_QUOTES, 'UTF-8'), 0, 300), 'anchor' => '', 'rel' => '', 'field_note' => 'URL fără ancoră (meniu / cod / text)'];
            $rest = str_replace($u, ' ', $rest);
        }
    }

    // Text: doar ce se vede (fără tag-uri / atribute HTML).
    $rest = preg_replace('~<[^>]*>~', ' ', $rest);
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

/** Cheia unui loc (folosită de app ca să aplice rel doar pe locurile alese). */
function magpress_bs_key(array $it) {
    return $it['where'] . '|' . (int) ($it['id'] ?? 0) . '|' . (string) ($it['field'] ?? '') . '|' . (int) ($it['meta_id'] ?? 0);
}

/** Numele zonei (sidebar / footer) în care e pus widget-ul; '' = inactiv; '#id' = zonă inexistentă în tema activă. */
function magpress_bs_widget_zone($widget_id) {
    global $wp_registered_sidebars;
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach ((array) get_option('sidebars_widgets', []) as $sb => $ws) {
            if ($sb === 'wp_inactive_widgets' || $sb === 'array_version' || !is_array($ws)) continue;
            foreach ($ws as $w) $map[$w] = $sb;
        }
    }
    if (!isset($map[$widget_id])) return '';
    $sb = $map[$widget_id];
    // Zonă care nu mai există în tema activă (ex. sidebar-ul unei teme vechi) = nu se afișează.
    return isset($wp_registered_sidebars[$sb]['name']) ? (string) $wp_registered_sidebars[$sb]['name'] : '#' . $sb;
}

/**
 * Dacă opțiunea ține de o temă instalată care NU e activă (theme_mods_jnews, trendymag_theme_option...),
 * întoarce numele temei; altfel ''.
 */
function magpress_bs_inactive_theme_option($name) {
    static $themes = null;
    if ($themes === null) {
        $themes = [];
        $active = [get_stylesheet(), get_template()];
        foreach (array_keys((array) wp_get_themes()) as $slug) {
            if (!in_array($slug, $active, true)) $themes[] = (string) $slug;
        }
    }
    $n = strtolower($name);
    if (strpos($n, 'theme_mods_') === 0) {
        $slug = substr($n, 11);
        return in_array($slug, [strtolower(get_stylesheet()), strtolower(get_template())], true) ? '' : $slug;
    }
    foreach ($themes as $slug) {
        $sl = strtolower($slug);
        if (strlen($sl) >= 4 && (strpos($n, $sl . '_') === 0 || strpos($n, $sl . '-') === 0 || strpos($n, str_replace('-', '_', $sl) . '_') === 0)) return $slug;
    }
    return '';
}

function magpress_bs_scan($max_items = MAGPRESS_BS_MAX_ITEMS) {
    global $wpdb;
    $t0 = microtime(true);
    $items = [];
    $truncated = false;
    $add = function (array $item) use (&$items, &$truncated, $max_items) {
        if (empty($item['hits'])) return;
        if (count($items) >= $max_items) { $truncated = true; return; }
        $item['key'] = magpress_bs_key($item);
        $items[] = $item;
    };

    // ---- 1. articole / pagini / orice tip de conținut ----
    $slug_or = [];
    foreach (magpress_bs_terms() ?: ['advertorial'] as $t) {
        $slug_or[] = $wpdb->prepare('post_name LIKE %s', '%' . $wpdb->esc_like(sanitize_title($t)) . '%');
    }
    $ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type NOT IN ('revision','customize_changeset','oembed_cache','nav_menu_item')
           AND post_status NOT IN ('auto-draft')
           AND (" . magpress_bs_where(['post_content', 'post_title', 'post_excerpt']) . ' OR ' . implode(' OR ', $slug_or) . ")
         ORDER BY ID DESC LIMIT 3000"
    );
    foreach (array_chunk(array_map('intval', $ids), 100) as $chunk) {
        $rows = $wpdb->get_results("SELECT ID, post_type, post_status, post_title, post_name, post_content, post_excerpt
            FROM {$wpdb->posts} WHERE ID IN (" . implode(',', $chunk) . ")");
        foreach ($rows as $p) {
            $hits = magpress_bs_analyze($p->post_content);
            foreach (magpress_bs_analyze($p->post_excerpt) as $h) $hits[] = $h + ['field' => 'rezumat'];
            foreach (magpress_bs_analyze($p->post_title) as $h) $hits[] = $h + ['field' => 'titlu'];
            // Imaginile (ex. bannerul „advertoriale728x90”) nu sunt pagini.
            $pk = $p->post_type === 'attachment' ? '' : magpress_bs_page_kind($p->post_title, $p->post_name);
            if ($pk !== '') {
                $hits[] = ['kind' => $pk, 'snippet' => 'Titlu/slug: ' . magpress_bs_clip($p->post_title, 80) . ' (/' . $p->post_name . '/)'];
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
    $menu_ids = [];
    $skip_meta = "'_edit_lock','_edit_last','_wp_old_slug','_wp_attachment_metadata','_magpress_ai_auto','_magpress_ai_vision','_menu_item_xfn','_elementor_css'";
    $metas = $wpdb->get_results(
        "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_status, p.post_title
         FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
         WHERE m.meta_key NOT IN ($skip_meta)
           AND p.post_type NOT IN ('revision') AND p.post_status NOT IN ('auto-draft')
           AND " . magpress_bs_where(['m.meta_value']) . "
         ORDER BY m.post_id DESC LIMIT 1500"
    );
    foreach ($metas as $mrow) {
        $hits = magpress_bs_analyze($mrow->meta_value);
        if ($mrow->post_type === 'nav_menu_item') { $menu_ids[(int) $mrow->post_id] = true; continue; }
        $add([
            'where'   => 'meta',
            'id'      => (int) $mrow->post_id,
            'meta_id' => (int) $mrow->meta_id,
            'type'    => $mrow->post_type,
            'status'  => $mrow->post_status,
            'title'   => magpress_bs_clip($mrow->post_title, 120),
            'url'     => $mrow->post_status === 'publish' ? (string) get_permalink((int) $mrow->post_id) : '',
            'edit'    => admin_url('post.php?post=' . (int) $mrow->post_id . '&action=edit'),
            'field'   => $mrow->meta_key,
            'hits'    => $hits,
        ]);
    }

    // ---- 2b. elemente de meniu: după URL (din meta de mai sus) sau după textul afișat ----
    if (magpress_bs_terms()) {
        foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item' AND " . magpress_bs_where(['post_title']) . " LIMIT 300") as $mid) {
            $menu_ids[(int) $mid] = true;
        }
    }
    $loc_all = (array) get_nav_menu_locations();
    $reg     = get_registered_nav_menus();
    foreach (array_keys($menu_ids) as $mid) {
        $mp = get_post($mid);
        if (!$mp) continue;
        $item  = wp_setup_nav_menu_item($mp);
        $title = isset($item->title) ? (string) $item->title : (string) $mp->post_title;
        $url   = isset($item->url) ? (string) $item->url : (string) get_post_meta($mid, '_menu_item_url', true);
        $kind  = magpress_bs_link_kind($url, $title);
        if ($kind === '') continue;
        $menus = wp_get_object_terms($mid, 'nav_menu');
        $menu  = (!is_wp_error($menus) && $menus) ? $menus[0] : null;
        $locs  = [];
        if ($menu) {
            foreach ($loc_all as $l => $tid) if ((int) $tid === (int) $menu->term_id && isset($reg[$l])) $locs[] = $reg[$l];
        }
        $add([
            'where'  => 'menu',
            'id'     => $mid,
            'type'   => 'nav_menu_item',
            'status' => $mp->post_status,
            'note'   => $locs ? 'Meniul e afișat în: ' . implode(', ', $locs) . '.'
                : 'Meniul nu e pus în nicio poziție a temei — nu se vede pe site (decât dacă e folosit într-un widget „Meniu”).',
            'title'  => 'Meniu „' . ($menu ? $menu->name : '?') . '” → ' . magpress_bs_clip($title, 80),
            'url'    => '',
            'edit'   => admin_url('nav-menus.php' . ($menu ? '?action=edit&menu=' . (int) $menu->term_id : '')),
            'field'  => '_menu_item_url',
            'hits'   => [[
                'kind'   => $kind,
                'href'   => mb_substr($url, 0, 300),
                'anchor' => magpress_bs_clip($title, 100),
                'rel'    => strtolower(trim((string) get_post_meta($mid, '_menu_item_xfn', true))),
            ]],
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
                $zone = magpress_bs_widget_zone(substr($name, 7) . '-' . $idx);
                $add([
                    'where' => 'widget', 'id' => (int) $idx, 'type' => $name,
                    'status' => ($zone === '' || $zone[0] === '#') ? 'inactiv' : '',
                    'note'   => $zone === '' ? 'Widget inactiv (nu e pus în nicio zonă) — nu se vede pe site.'
                        : ($zone[0] === '#' ? 'Pus în zona „' . substr($zone, 1) . '”, care nu există în tema activă (rămas de la o temă veche) — nu se vede pe site.'
                        : 'În zona „' . $zone . '” — se vede pe site.'),
                    'title' => 'Widget ' . substr($name, 7) . '-' . $idx . $wt,
                    'url' => '', 'edit' => admin_url('widgets.php'), 'field' => $name, 'hits' => $hits,
                ]);
            }
            continue;
        }
        $label = strpos($name, 'theme_mods_') === 0 ? 'Customizer (' . $name . ')' : 'Setare: ' . $name;
        $inactive = magpress_bs_inactive_theme_option($name);
        $add([
            'where' => 'option', 'id' => 0, 'type' => $name, 'status' => $inactive ? 'temă inactivă' : '',
            'note'  => $inactive ? 'Setare a temei „' . $inactive . '”, care nu e activă — de regulă nu se afișează pe site.' : '',
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
            'field' => $t->taxonomy,
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

    $counts = array_fill_keys(['link_sd_advertoriale', 'link_seodigital', 'link_advertorial', 'page_advertorial', 'link_match', 'anchor_match', 'page_match', 'text'], 0);
    foreach ($items as $it) {
        foreach ($it['hits'] as $h) {
            if (isset($counts[$h['kind']])) $counts[$h['kind']]++;
        }
    }

    return [
        'terms'        => magpress_bs_search()['url'],
        'anchor_terms' => magpress_bs_search()['anchor'],
        'match'        => magpress_bs_search()['mode'],
        'counts'    => $counts,
        'items'     => $items,
        'truncated' => $truncated,
        'took_ms'   => (int) round((microtime(true) - $t0) * 1000),
    ];
}

// =========================================================================
// rel sponsored / nofollow pe linkurile găsite
// =========================================================================

/**
 * Noua valoare a atributului rel. $rel: sponsored | nofollow | sponsored nofollow |
 * dofollow (scoate nofollow, sponsored, ugc) | -sponsored (scoate doar sponsored) | -nofollow (scoate doar nofollow).
 */
function magpress_bs_rel_value($existing, $rel) {
    $t = preg_split('~\s+~', strtolower(trim((string) $existing)), -1, PREG_SPLIT_NO_EMPTY);
    $t = array_values(array_diff($t, ['dofollow', 'follow']));
    if ($rel === 'dofollow') {
        $t = array_diff($t, ['nofollow', 'sponsored', 'ugc']);
    } elseif ($rel === '-sponsored' || $rel === '-nofollow') {
        $t = array_diff($t, [substr($rel, 1)]);
    } else {
        foreach (explode(' ', $rel) as $x) if (!in_array($x, $t, true)) $t[] = $x;
    }
    return implode(' ', array_values(array_unique($t)));
}

/** Pune rel pe <a>-urile din HTML care duc la URL-uri de tipurile $kinds. */
function magpress_bs_html_set_rel($html, array $kinds, $rel, &$n) {
    if (stripos($html, '<a') === false) return $html;
    $before = $n;
    $out = preg_replace_callback('~(<a\b[^>]*>)(.*?)(</a\s*>)~is', function ($m) use ($kinds, $rel, &$n) {
        $tag = magpress_bs_tag_set_rel($m[1], $m[2], $kinds, $rel, $n);
        return $tag . $m[2] . $m[3];
    }, $html);
    if (!is_string($out)) { $n = $before; return $html; } // eroare PCRE: nu atingem textul
    return $out;
}

/** Tag-ul de deschidere <a ...> cu rel-ul nou (dacă linkul e de tipurile $kinds). */
function magpress_bs_tag_set_rel($tag, $anchor_html, array $kinds, $rel, &$n) {
    if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $hm)) $href = $hm[2];
    elseif (preg_match('~\bhref\s*=\s*([^\s>]+)~is', $tag, $hm)) $href = $hm[1];
    else return $tag;
    // Regula de aur: fără domenii alese nu se atinge niciun link; altfel doar cele spre ele.
    $doms = magpress_bs_domains();
    if (!$doms || !in_array(magpress_bs_host($href), $doms, true)) return $tag;
    if (!in_array(magpress_bs_link_kind($href, $anchor_html), $kinds, true)) return $tag;

    if (preg_match('~\s+rel\s*=\s*(["\'])(.*?)\1~is', $tag, $rm) || preg_match('~\s+rel\s*=\s*([^\s"\'>]+)~is', $tag, $rm)) {
        $old = isset($rm[2]) ? $rm[2] : $rm[1];
        $new = magpress_bs_rel_value($old, $rel);
        if (strtolower(trim($old)) === $new) return $tag;
        $n++;
        return str_replace($rm[0], $new === '' ? '' : ' rel="' . esc_attr($new) . '"', $tag);
    }
    $new = magpress_bs_rel_value('', $rel);
    if ($new === '') return $tag;
    $n++;
    return preg_replace('~\s*(/?)>$~', ' rel="' . esc_attr($new) . '"$1>', $tag, 1);
}

/**
 * Parcurge o valoare (string HTML / JSON / serializat, array) și pune rel. Elementor:
 * controalele de link {url, is_external, nofollow} primesc nofollow="on" (Elementor nu
 * are „sponsored”); HTML-ul din widget-urile text primește rel normal.
 */
function magpress_bs_walk_set_rel($v, array $kinds, $rel, &$n) {
    if (is_string($v)) {
        $s = ltrim($v);
        if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
            $dec = json_decode($v, true);
            if (is_array($dec)) {
                $before = $n;
                $dec = magpress_bs_walk_set_rel($dec, $kinds, $rel, $n);
                return $n > $before ? wp_json_encode($dec) : $v;
            }
        }
        if (is_serialized($v)) {
            $un = @unserialize($v, ['allowed_classes' => false]);
            if (is_array($un)) {
                $before = $n;
                $un = magpress_bs_walk_set_rel($un, $kinds, $rel, $n);
                return $n > $before ? serialize($un) : $v;
            }
            return $v;
        }
        return magpress_bs_html_set_rel($v, $kinds, $rel, $n);
    }
    if (is_array($v)) {
        if (isset($v['url']) && is_string($v['url']) && array_key_exists('nofollow', $v)
            && magpress_bs_domains() && in_array(magpress_bs_host($v['url']), magpress_bs_domains(), true)
            && in_array(magpress_bs_link_kind($v['url'], ''), $kinds, true)) {
            // Elementor are doar „nofollow”: -sponsored nu-l atinge
            if ($rel !== '-sponsored') {
                $want = ($rel === 'dofollow' || $rel === '-nofollow') ? '' : 'on';
                if ((string) $v['nofollow'] !== $want) { $v['nofollow'] = $want; $n++; }
            }
        }
        foreach ($v as $k => $x) {
            if (is_string($x) || is_array($x)) $v[$k] = magpress_bs_walk_set_rel($x, $kinds, $rel, $n);
        }
    }
    return $v;
}

/**
 * Aplică rel pe locurile găsite la scanare (toate sau doar cheile din $targets).
 * @return array [changed_links, changed_places, skipped]
 */
function magpress_bs_set_rel($rel, array $kinds, $targets = null) {
    global $wpdb;
    $kinds = array_values(array_intersect($kinds, MAGPRESS_BS_LINK_KINDS));
    if (!$kinds || !magpress_bs_domains()) return [0, 0, 0]; // regula de aur: doar domeniile alese
    $scan = magpress_bs_scan(5000);
    $links = 0; $places = 0; $skipped = 0;
    $elementor = false;

    foreach ($scan['items'] as $it) {
        if (is_array($targets) && !in_array($it['key'], $targets, true)) continue;
        $has = false;
        foreach ($it['hits'] as $h) if (in_array($h['kind'], $kinds, true)) { $has = true; break; }
        if (!$has) continue;

        $n = 0;
        switch ($it['where']) {
            case 'post':
                $p = get_post((int) $it['id']);
                if (!$p) break;
                $content = magpress_bs_html_set_rel($p->post_content, $kinds, $rel, $n);
                $excerpt = magpress_bs_html_set_rel($p->post_excerpt, $kinds, $rel, $n);
                if ($n) {
                    if (function_exists('wp_save_post_revision') && $p->post_type !== 'attachment') wp_save_post_revision($p->ID);
                    $wpdb->update($wpdb->posts, ['post_content' => $content, 'post_excerpt' => $excerpt], ['ID' => $p->ID]);
                    clean_post_cache($p->ID);
                }
                break;

            case 'meta':
                $row = $wpdb->get_row($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", (int) $it['meta_id']));
                if (!$row) break;
                $new = magpress_bs_walk_set_rel($row->meta_value, $kinds, $rel, $n);
                if ($n) {
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $new], ['meta_id' => (int) $it['meta_id']]);
                    wp_cache_delete((int) $row->post_id, 'post_meta');
                    if ($row->meta_key === '_elementor_data') {
                        delete_post_meta((int) $row->post_id, '_elementor_css');
                        $elementor = true;
                    }
                }
                break;

            case 'menu':
                $mi  = wp_setup_nav_menu_item(get_post((int) $it['id']));
                $url = $mi && !empty($mi->url) ? (string) $mi->url : (string) get_post_meta((int) $it['id'], '_menu_item_url', true);
                if (!in_array(magpress_bs_host($url), magpress_bs_domains(), true)) break;
                if (!in_array(magpress_bs_link_kind($url, $mi->title ?? ''), $kinds, true)) break;
                $old = (string) get_post_meta((int) $it['id'], '_menu_item_xfn', true);
                $new = magpress_bs_rel_value($old, $rel);
                if (strtolower(trim($old)) !== $new) { update_post_meta((int) $it['id'], '_menu_item_xfn', $new); $n++; }
                break;

            case 'widget':
                $opt = get_option($it['field']);
                if (!is_array($opt) || !isset($opt[$it['id']]) || !is_array($opt[$it['id']])) break;
                $opt[$it['id']] = magpress_bs_walk_set_rel($opt[$it['id']], $kinds, $rel, $n);
                if ($n) update_option($it['field'], $opt);
                break;

            case 'option':
                $opt = get_option($it['field']);
                $new = magpress_bs_walk_set_rel($opt, $kinds, $rel, $n);
                if ($n) update_option($it['field'], $new);
                break;

            case 'term':
                $desc = (string) $wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", (int) $it['id'], $it['field']));
                $new = magpress_bs_html_set_rel($desc, $kinds, $rel, $n);
                if ($n) {
                    $wpdb->update($wpdb->term_taxonomy, ['description' => $new], ['term_id' => (int) $it['id'], 'taxonomy' => $it['field']]);
                    clean_term_cache((int) $it['id'], $it['field']);
                }
                break;

            default: // comentarii, autori: nu le modificăm
                $skipped++;
        }
        if ($n) { $links += $n; $places++; }
    }

    if ($elementor && class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    if ($places && function_exists('magpress_purge_litespeed')) magpress_purge_litespeed();
    return [$links, $places, $skipped];
}

// =========================================================================
// Înlocuire link vechi → link nou
// =========================================================================

/** URL normalizat pentru comparație: fără schemă, www., „/” final; litere mici. */
function magpress_bs_norm_url($u) {
    $u = strtolower(trim(html_entity_decode((string) $u, ENT_QUOTES, 'UTF-8')));
    $u = preg_replace('~^(?:https?:)?//~', '', $u);
    $u = preg_replace('~^www\.~', '', $u);
    return rtrim($u, '/');
}

/** Înlocuiește href-ul <a>-urilor care duc la $from_n (normalizat) cu $to. */
function magpress_bs_html_replace_url($html, $from_n, $to, &$n) {
    if (stripos($html, '<a') === false) return $html;
    $before = $n;
    $out = preg_replace_callback('~<a\b[^>]*>~i', function ($m) use ($from_n, $to, &$n) {
        $tag = $m[0];
        if (!preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $hm) && !preg_match('~\bhref\s*=\s*([^\s"\'>]+)~is', $tag, $hm)) return $tag;
        $href = isset($hm[2]) ? $hm[2] : $hm[1];
        if (magpress_bs_norm_url($href) !== $from_n) return $tag;
        $n++;
        return str_replace($hm[0], 'href="' . esc_attr($to) . '"', $tag);
    }, $html);
    if (!is_string($out)) { $n = $before; return $html; } // eroare PCRE: nu atingem textul
    return $out;
}

/** Ca magpress_bs_walk_set_rel: JSON / serializat / array; un string care e doar un URL se înlocuiește întreg. */
function magpress_bs_walk_replace_url($v, $from_n, $to, &$n) {
    if (is_string($v)) {
        $s = trim($v);
        if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
            $dec = json_decode($v, true);
            if (is_array($dec)) {
                $before = $n;
                $dec = magpress_bs_walk_replace_url($dec, $from_n, $to, $n);
                return $n > $before ? wp_json_encode($dec) : $v;
            }
        }
        if (is_serialized($v)) {
            $un = @unserialize($v, ['allowed_classes' => false]);
            if (is_array($un)) {
                $before = $n;
                $un = magpress_bs_walk_replace_url($un, $from_n, $to, $n);
                return $n > $before ? serialize($un) : $v;
            }
            return $v;
        }
        if (preg_match('~^(?:https?:)?//\S+$~i', $s)) {
            if (magpress_bs_norm_url($s) === $from_n) { $n++; return $to; }
            return $v;
        }
        return magpress_bs_html_replace_url($v, $from_n, $to, $n);
    }
    if (is_array($v)) {
        foreach ($v as $k => $x) {
            if (is_string($x) || is_array($x)) $v[$k] = magpress_bs_walk_replace_url($x, $from_n, $to, $n);
        }
    }
    return $v;
}

/**
 * Înlocuiește linkul $from cu $to pe tot site-ul (sau doar în locurile din $targets).
 * Caută singur după $from, indiferent de termenii căutării din app.
 * @return array [changed_links, changed_places]
 */
function magpress_bs_replace_url($from, $to, $targets = null) {
    global $wpdb;
    $from_n = magpress_bs_norm_url($from);
    $prev = magpress_bs_search();
    magpress_bs_terms([$from_n]);
    $scan = magpress_bs_scan(5000);
    magpress_bs_search($prev['url'], $prev['anchor'], $prev['mode']);

    $links = 0; $places = 0; $elementor = false;
    foreach ($scan['items'] as $it) {
        if (is_array($targets) && !in_array($it['key'], $targets, true)) continue;
        $has = false;
        foreach ($it['hits'] as $h) if (isset($h['href']) && magpress_bs_norm_url($h['href']) === $from_n) { $has = true; break; }
        if (!$has) continue;

        $n = 0;
        switch ($it['where']) {
            case 'post':
                $p = get_post((int) $it['id']);
                if (!$p) break;
                $content = magpress_bs_html_replace_url($p->post_content, $from_n, $to, $n);
                $excerpt = magpress_bs_html_replace_url($p->post_excerpt, $from_n, $to, $n);
                if ($n) {
                    if (function_exists('wp_save_post_revision') && $p->post_type !== 'attachment') wp_save_post_revision($p->ID);
                    $wpdb->update($wpdb->posts, ['post_content' => $content, 'post_excerpt' => $excerpt], ['ID' => $p->ID]);
                    clean_post_cache($p->ID);
                }
                break;

            case 'meta':
                $row = $wpdb->get_row($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", (int) $it['meta_id']));
                if (!$row) break;
                $new = magpress_bs_walk_replace_url($row->meta_value, $from_n, $to, $n);
                if ($n) {
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $new], ['meta_id' => (int) $it['meta_id']]);
                    wp_cache_delete((int) $row->post_id, 'post_meta');
                    if ($row->meta_key === '_elementor_data') {
                        delete_post_meta((int) $row->post_id, '_elementor_css');
                        $elementor = true;
                    }
                }
                break;

            case 'menu':
                $url = (string) get_post_meta((int) $it['id'], '_menu_item_url', true);
                if (magpress_bs_norm_url($url) === $from_n) { update_post_meta((int) $it['id'], '_menu_item_url', esc_url_raw($to)); $n++; }
                break;

            case 'widget':
                $opt = get_option($it['field']);
                if (!is_array($opt) || !isset($opt[$it['id']]) || !is_array($opt[$it['id']])) break;
                $opt[$it['id']] = magpress_bs_walk_replace_url($opt[$it['id']], $from_n, $to, $n);
                if ($n) update_option($it['field'], $opt);
                break;

            case 'option':
                $opt = get_option($it['field']);
                $new = magpress_bs_walk_replace_url($opt, $from_n, $to, $n);
                if ($n) update_option($it['field'], $new);
                break;

            case 'term':
                $desc = (string) $wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", (int) $it['id'], $it['field']));
                $new = magpress_bs_html_replace_url($desc, $from_n, $to, $n);
                if ($n) {
                    $wpdb->update($wpdb->term_taxonomy, ['description' => $new], ['term_id' => (int) $it['id'], 'taxonomy' => $it['field']]);
                    clean_term_cache((int) $it['id'], $it['field']);
                }
                break;
        }
        if ($n) { $links += $n; $places++; }
    }

    if ($elementor && class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    if ($places && function_exists('magpress_purge_litespeed')) magpress_purge_litespeed();
    return [$links, $places];
}

// =========================================================================
// v0.9.281 — Reparare: rel-ul linkurilor spre ALTE domenii (ex. clienții din advertoriale),
// atins din greșeală de set_rel (v0.9.277–0.9.279 nu limitau domeniile), revine la valoarea
// din ultima revizie a articolului. set_rel scrie direct în DB și nu creează revizie după
// modificare, deci diferența de rel dintre ultima revizie și conținutul de acum e a noastră.
// Se schimbă DOAR atributul rel (sau nofollow-ul din Elementor), doar dacă diferă numai prin
// sponsored / nofollow / ugc. Linkurile spre domeniile din $keep (ale noastre) nu se ating.
// =========================================================================

const MAGPRESS_BS_REL_TOKENS = ['sponsored', 'nofollow', 'ugc', 'dofollow', 'follow'];

/** Cheia unui link pentru potrivirea cu revizia: URL normalizat + textul ancorei. */
function magpress_bs_restore_key($href, $anchor_html) {
    return magpress_bs_norm_url($href) . '|' . mb_strtolower(trim(preg_replace('~\s+~u', ' ', html_entity_decode(wp_strip_all_tags((string) $anchor_html), ENT_QUOTES, 'UTF-8'))));
}

/** <a>-urile dintr-un HTML: [[tag, href, anchor_html, rel], ...]; null la eroare PCRE. */
function magpress_bs_restore_links($html) {
    $html = (string) $html;
    if (stripos($html, '<a') === false) return [];
    if (preg_match_all('~(<a\b[^>]*>)(.*?)</a\s*>~is', $html, $m, PREG_SET_ORDER) === false) return null;
    $out = [];
    foreach ($m as $a) {
        if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $a[1], $hm)) $href = $hm[2];
        elseif (preg_match('~\bhref\s*=\s*([^\s"\'>]+)~is', $a[1], $hm)) $href = $hm[1];
        else continue;
        $rel = preg_match('~\srel\s*=\s*(["\'])(.*?)\1~is', $a[1], $rm) ? $rm[2] : (preg_match('~\srel\s*=\s*([^\s"\'>]+)~is', $a[1], $rm) ? $rm[1] : '');
        $out[] = [$a[1], $href, $a[2], $rel];
    }
    return $out;
}

/** rel-urile diferă doar prin sponsored / nofollow / ugc (adică le-a schimbat set_rel)? */
function magpress_bs_rel_only_tokens_differ($a, $b) {
    $ta = preg_split('~\s+~', strtolower(trim((string) $a)), -1, PREG_SPLIT_NO_EMPTY);
    $tb = preg_split('~\s+~', strtolower(trim((string) $b)), -1, PREG_SPLIT_NO_EMPTY);
    sort($ta); sort($tb);
    if ($ta === $tb) return false;
    $ra = array_values(array_diff($ta, MAGPRESS_BS_REL_TOKENS));
    $rb = array_values(array_diff($tb, MAGPRESS_BS_REL_TOKENS));
    return $ra === $rb;
}

/** Linkul e spre unul din domeniile noastre (care NU se repară)? */
function magpress_bs_restore_is_keep($href, array $keep) {
    $h = magpress_bs_host($href);
    return $h === '' || in_array($h, $keep, true) || ($h === magpress_bs_host(home_url('/')));
}

/**
 * Readuce rel-ul din $old_html pe linkurile din $html (doar spre alte domenii decât $keep).
 * $log primește [href, anchor, rel_acum, rel_refăcut] pentru fiecare link reparat.
 */
function magpress_bs_html_restore_rel($html, $old_html, array $keep, &$n, array &$log) {
    $html = (string) $html;
    if (stripos($html, '<a') === false || (string) $old_html === $html) return $html;
    $old = magpress_bs_restore_links($old_html);
    if (!$old) return $html;
    $map = [];
    foreach ($old as $l) $map[magpress_bs_restore_key($l[1], $l[2])][] = $l[3];
    $before = $n; $log_before = count($log);
    $out = preg_replace_callback('~(<a\b[^>]*>)(.*?)(</a\s*>)~is', function ($m) use (&$map, $keep, &$n, &$log) {
        $tag = $m[1];
        if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $hm)) $href = $hm[2];
        elseif (preg_match('~\bhref\s*=\s*([^\s"\'>]+)~is', $tag, $hm)) $href = $hm[1];
        else return $m[0];
        $key = magpress_bs_restore_key($href, $m[2]);
        if (empty($map[$key])) return $m[0];
        $old_rel = array_shift($map[$key]); // aceeași ordine ca în revizie
        if (magpress_bs_restore_is_keep($href, $keep)) return $m[0];
        $has = preg_match('~\s+rel\s*=\s*(["\'])(.*?)\1~is', $tag, $rm) || preg_match('~\s+rel\s*=\s*([^\s"\'>]+)~is', $tag, $rm);
        $cur_rel = $has ? (isset($rm[2]) ? $rm[2] : $rm[1]) : '';
        if (!magpress_bs_rel_only_tokens_differ($cur_rel, $old_rel)) return $m[0];
        $new_attr = trim($old_rel) === '' ? '' : ' rel="' . esc_attr(trim($old_rel)) . '"';
        $tag = $has ? str_replace($rm[0], $new_attr, $tag) : preg_replace('~\s*(/?)>$~', $new_attr . '$1>', $tag, 1);
        $n++;
        $log[] = [mb_substr(html_entity_decode($href, ENT_QUOTES, 'UTF-8'), 0, 300), magpress_bs_clip($m[2], 100), strtolower(trim($cur_rel)), strtolower(trim($old_rel))];
        return $tag . $m[2] . $m[3];
    }, $html);
    if (!is_string($out)) { $n = $before; $log = array_slice($log, 0, $log_before); return $html; }
    return $out;
}

/** Ca magpress_bs_html_restore_rel, în paralel pe structura curentă și cea din revizie (Elementor JSON). */
function magpress_bs_walk_restore_rel($cur, $old, array $keep, &$n, array &$log) {
    if (is_string($cur)) {
        if (!is_string($old) || $cur === $old) return $cur;
        $s = ltrim($cur);
        if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
            $dc = json_decode($cur, true); $do = json_decode($old, true);
            if (is_array($dc)) {
                if (!is_array($do)) return $cur;
                $before = $n;
                $dc = magpress_bs_walk_restore_rel($dc, $do, $keep, $n, $log);
                return $n > $before ? wp_json_encode($dc) : $cur;
            }
        }
        if (is_serialized($cur)) {
            $uc = @unserialize($cur, ['allowed_classes' => false]);
            $uo = @unserialize($old, ['allowed_classes' => false]);
            if (is_array($uc) && is_array($uo)) {
                $before = $n;
                $uc = magpress_bs_walk_restore_rel($uc, $uo, $keep, $n, $log);
                return $n > $before ? serialize($uc) : $cur;
            }
            return $cur;
        }
        return magpress_bs_html_restore_rel($cur, $old, $keep, $n, $log);
    }
    if (!is_array($cur) || !is_array($old)) return $cur;
    // Control de link Elementor {url, nofollow}: nofollow ca în revizie, dacă e același URL.
    if (isset($cur['url'], $old['url']) && is_string($cur['url']) && array_key_exists('nofollow', $cur) && array_key_exists('nofollow', $old)
        && magpress_bs_norm_url($cur['url']) === magpress_bs_norm_url($old['url'])
        && !magpress_bs_restore_is_keep($cur['url'], $keep) && (string) $cur['nofollow'] !== (string) $old['nofollow']) {
        $log[] = [mb_substr((string) $cur['url'], 0, 300), '(Elementor)', $cur['nofollow'] === 'on' ? 'nofollow' : '', $old['nofollow'] === 'on' ? 'nofollow' : ''];
        $cur['nofollow'] = $old['nofollow'];
        $n++;
    }
    // Liste de elemente Elementor: potrivire după id (dacă ordinea s-a schimbat).
    $by_id = [];
    foreach ($old as $x) if (is_array($x) && isset($x['id']) && is_scalar($x['id'])) $by_id[(string) $x['id']] = $x;
    foreach ($cur as $k => $x) {
        if (!is_string($x) && !is_array($x)) continue;
        if (is_array($x) && isset($x['id']) && is_scalar($x['id']) && $by_id) {
            if (isset($by_id[(string) $x['id']])) $cur[$k] = magpress_bs_walk_restore_rel($x, $by_id[(string) $x['id']], $keep, $n, $log);
        } elseif (array_key_exists($k, $old)) {
            $cur[$k] = magpress_bs_walk_restore_rel($x, $old[$k], $keep, $n, $log);
        }
    }
    return $cur;
}

/**
 * Repară pe tot site-ul: articole / pagini (conținut + rezumat) și Elementor (_elementor_data),
 * față de ultima revizie (fără autosave). $apply=false: doar lista, fără modificări.
 * @return array {links, places, items:[{title,url,edit,where,links:[[href,anchor,acum,refăcut]]}], truncated}
 */
function magpress_bs_restore_rel(array $keep, $apply) {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT p.ID, x.rid FROM {$wpdb->posts} p
         JOIN (SELECT post_parent, MAX(ID) rid FROM {$wpdb->posts}
               WHERE post_type = 'revision' AND post_name NOT LIKE '%autosave%' GROUP BY post_parent) x ON x.post_parent = p.ID
         WHERE p.post_type NOT IN ('revision','customize_changeset','oembed_cache','nav_menu_item','attachment')
           AND p.post_status NOT IN ('auto-draft','trash')"
    );
    $links = 0; $places = 0; $items = []; $truncated = false; $elementor = false;
    foreach ((array) $rows as $r) {
        $p   = get_post((int) $r->ID);
        $rev = $wpdb->get_row($wpdb->prepare("SELECT post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", (int) $r->rid));
        if (!$p || !$rev) continue;
        $log = []; $n = 0;
        $content = $p->post_content; $excerpt = $p->post_excerpt;
        if ($p->post_content !== $rev->post_content || $p->post_excerpt !== $rev->post_excerpt) {
            $content = magpress_bs_html_restore_rel($p->post_content, $rev->post_content, $keep, $n, $log);
            $excerpt = magpress_bs_html_restore_rel($p->post_excerpt, $rev->post_excerpt, $keep, $n, $log);
        }
        $n_post = $n;

        $meta = $wpdb->get_row($wpdb->prepare("SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1", (int) $p->ID));
        $old_meta = $meta ? $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1", (int) $r->rid)) : null;
        $new_meta = null;
        if ($meta && is_string($old_meta) && $old_meta !== $meta->meta_value) {
            $new_meta = magpress_bs_walk_restore_rel($meta->meta_value, $old_meta, $keep, $n, $log);
        }
        $n_meta = $n - $n_post;

        if ($n && $apply) {
            // backup: starea de acum (cu rel-ul greșit) ca revizie, ca reparația să se poată anula
            if (function_exists('wp_save_post_revision')) wp_save_post_revision($p->ID);
            if ($n_post) {
                $wpdb->update($wpdb->posts, ['post_content' => $content, 'post_excerpt' => $excerpt], ['ID' => $p->ID]);
                clean_post_cache($p->ID);
            }
            if ($n_meta) {
                $wpdb->update($wpdb->postmeta, ['meta_value' => $new_meta], ['meta_id' => (int) $meta->meta_id]);
                wp_cache_delete((int) $p->ID, 'post_meta');
                delete_post_meta((int) $p->ID, '_elementor_css');
                $elementor = true;
            }
            // starea reparată devine ultima revizie: o rulare nouă nu mai vede nimic de „reparat”
            if ($n_post && function_exists('wp_save_post_revision')) wp_save_post_revision($p->ID);
        }

        if (!$n) continue;
        $links += $n; $places++;
        if (count($items) < 300) {
            $items[] = [
                'id' => (int) $p->ID, 'title' => magpress_bs_clip(get_the_title($p), 120), 'url' => get_permalink($p),
                'edit' => admin_url('post.php?post=' . (int) $p->ID . '&action=edit'), 'links' => array_slice($log, 0, 30),
            ];
        } else {
            $truncated = true;
        }
    }
    if ($apply && $elementor && class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    if ($apply && $places && function_exists('magpress_purge_litespeed')) magpress_purge_litespeed();
    $res = ['applied' => (bool) $apply, 'keep' => $keep, 'links' => $links, 'places' => $places, 'items' => $items, 'truncated' => $truncated, 'at' => current_time('mysql')];
    if ($apply) update_option('magpress_bs_restore_log', $res, false);
    return $res;
}

/** Domeniile noastre la reparația automată: pe ele rel-ul rămâne cum e. */
const MAGPRESS_BS_RESTORE_KEEP = ['seodigital.ro', 'targetseo.ro'];

// v0.9.281: reparația rulează singură, O DATĂ pe fiecare site, la primul cron după update.
add_action('init', function () {
    if (get_option('magpress_bs_restore_281_done')) return;
    if (!wp_next_scheduled('magpress_bs_restore_281')) wp_schedule_single_event(time() + 30, 'magpress_bs_restore_281');
});
add_action('magpress_bs_restore_281', function () {
    if (get_option('magpress_bs_restore_281_done')) return;
    update_option('magpress_bs_restore_281_done', current_time('mysql'), false); // înainte: nu rulează de două ori
    if (function_exists('set_time_limit')) @set_time_limit(300);
    magpress_bs_restore_rel(MAGPRESS_BS_RESTORE_KEEP, true);
});

// =========================================================================
// REST: POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret
// {action:"scan", terms?} | {action:"set_rel", rel, kinds, terms?, targets?}
// | {action:"replace_url", from, to, terms?, targets?}
// → {ok, theme_version, home, terms, counts, items[], truncated, took_ms [, set_rel | replace]}
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
    if (function_exists('set_time_limit')) @set_time_limit(90);

    // v0.9.281: reparare rel pe linkurile spre alte domenii (fără scanare în răspuns).
    if ((string) ($data['action'] ?? '') === 'restore_rel') {
        $keep = magpress_bs_domains((array) ($data['keep'] ?? []));
        magpress_bs_domains([]);
        if (!$keep) return new WP_REST_Response(['ok' => false, 'error' => 'keep_required'], 400);
        $t0 = microtime(true);
        $res = magpress_bs_restore_rel($keep, !empty($data['apply']));
        return new WP_REST_Response(['ok' => true, 'theme_version' => MAGPRESS_VERSION, 'home' => home_url('/'),
            'restore' => $res, 'auto' => get_option('magpress_bs_restore_log', null), 'took_ms' => (int) round((microtime(true) - $t0) * 1000)], 200);
    }

    // v0.9.279: terms = URL-ul linkului, anchor_terms = ancora, match = any | both.
    // Fără anchor_terms (app mai vechi): aceiași termeni pentru URL și ancoră.
    $t_url = (array) ($data['terms'] ?? []);
    magpress_bs_search($t_url, array_key_exists('anchor_terms', $data) ? (array) $data['anchor_terms'] : $t_url, (string) ($data['match'] ?? 'any'));
    $out = ['ok' => true, 'theme_version' => MAGPRESS_VERSION, 'home' => home_url('/')];
    $action = (string) ($data['action'] ?? 'scan');

    if ($action === 'set_rel') {
        $rel = (string) ($data['rel'] ?? '');
        if (!in_array($rel, ['sponsored', 'nofollow', 'sponsored nofollow', 'dofollow', '-sponsored', '-nofollow'], true)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_rel'], 400);
        }
        $targets = isset($data['targets']) && is_array($data['targets']) ? array_map('strval', $data['targets']) : null;
        // Regula de aur: rel doar pe linkurile spre domeniile alese explicit; fără ele, refuz.
        if (!magpress_bs_domains((array) ($data['domains'] ?? []))) {
            return new WP_REST_Response(['ok' => false, 'error' => 'domains_required'], 400);
        }
        list($links, $places, $skipped) = magpress_bs_set_rel($rel, (array) ($data['kinds'] ?? []), $targets);
        magpress_bs_domains([]); // scanarea din răspuns arată toate rezultatele căutării
        $out['set_rel'] = ['rel' => $rel, 'links' => $links, 'places' => $places, 'skipped' => $skipped, 'domains' => (array) ($data['domains'] ?? [])];
    } elseif ($action === 'replace_url') {
        $from = trim((string) ($data['from'] ?? ''));
        $to   = esc_url_raw(trim((string) ($data['to'] ?? '')), ['http', 'https']);
        $fn   = magpress_bs_norm_url($from);
        if ($to === '' || strpos($fn, '.') === false || strlen($fn) < 4 || $fn === magpress_bs_norm_url($to)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_url'], 400);
        }
        $targets = isset($data['targets']) && is_array($data['targets']) ? array_map('strval', $data['targets']) : null;
        list($links, $places) = magpress_bs_replace_url($from, $to, $targets);
        $out['replace'] = ['from' => $from, 'to' => $to, 'links' => $links, 'places' => $places];
    } elseif ($action !== 'scan') {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    return new WP_REST_Response($out + magpress_bs_scan(), 200);
}
