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
 * REST pentru app.targetseo.ro → „SEO Digital pe site-uri”:
 * POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret (X-Magpress-Signature)
 *  {action:"scan", terms?:[...]}
 *  {action:"set_rel", rel, kinds:[...], terms?:[...], targets?:[key,...]}
 */
if (!defined('ABSPATH')) { exit; }

const MAGPRESS_BS_MAX_ITEMS = 400;   // locuri raportate la scanare
const MAGPRESS_BS_MAX_HITS  = 15;    // potriviri raportate per loc
const MAGPRESS_BS_LINK_KINDS = ['link_sd_advertoriale', 'link_seodigital', 'link_advertorial', 'link_match'];

/** Termenii custom ai cererii curente ([] = căutarea implicită „SEO Digital”). */
function magpress_bs_terms($set = null) {
    static $terms = [];
    if ($set !== null) {
        $clean = [];
        foreach ((array) $set as $t) {
            $t = trim(wp_strip_all_tags((string) $t));
            if (mb_strlen($t) >= 3 && mb_strlen($t) <= 100) $clean[mb_strtolower($t)] = $t;
        }
        $terms = array_slice(array_values($clean), 0, 5);
    }
    return $terms;
}

/** Fragmente pentru filtrul SQL (LIKE e case-insensitive pe colațiile WP). */
function magpress_bs_like_terms() {
    $t = magpress_bs_terms();
    return $t ?: ['seodigital', 'seo digital', 'seo&nbsp;digital', 'seo-digital', 'seo_digital', 'seo.digital', 'advertorial'];
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

/** Tipul unui URL ('' = irelevant) — după termenii custom sau regulile implicite. */
function magpress_bs_url_kind($href) {
    $h = html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8');
    $terms = magpress_bs_terms();
    if ($terms) {
        foreach ($terms as $t) {
            if (mb_stripos($h, $t) !== false) return 'link_match';
        }
        return '';
    }
    if (magpress_bs_is_seodigital_url($h)) {
        return stripos($h, 'advertorial') !== false ? 'link_sd_advertoriale' : 'link_seodigital';
    }
    return stripos($h, 'advertorial') !== false ? 'link_advertorial' : '';
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
            if ($kind === '') continue;
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

/** Cheia unui loc (folosită de app ca să aplice rel doar pe locurile alese). */
function magpress_bs_key(array $it) {
    return $it['where'] . '|' . (int) ($it['id'] ?? 0) . '|' . (string) ($it['field'] ?? '') . '|' . (int) ($it['meta_id'] ?? 0);
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
        if ($mrow->post_type === 'nav_menu_item') {
            $item  = wp_setup_nav_menu_item(get_post((int) $mrow->post_id));
            $menus = wp_get_object_terms((int) $mrow->post_id, 'nav_menu');
            $menu  = (!is_wp_error($menus) && $menus) ? $menus[0] : null;
            $title = $item && isset($item->title) ? $item->title : $mrow->post_title;
            $xfn   = strtolower(trim((string) get_post_meta((int) $mrow->post_id, '_menu_item_xfn', true)));
            foreach ($hits as &$h) {
                if (isset($h['anchor']) && $h['anchor'] === '') $h['anchor'] = magpress_bs_clip($title, 100);
                if (isset($h['href'])) { $h['rel'] = $xfn; unset($h['field_note']); }
            }
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
                    'where' => 'widget', 'id' => (int) $idx, 'type' => $name, 'status' => '',
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

    $counts = array_fill_keys(['link_sd_advertoriale', 'link_seodigital', 'link_advertorial', 'page_advertorial', 'link_match', 'page_match', 'text'], 0);
    foreach ($items as $it) {
        foreach ($it['hits'] as $h) {
            if (isset($counts[$h['kind']])) $counts[$h['kind']]++;
        }
    }

    return [
        'terms'     => magpress_bs_terms(),
        'counts'    => $counts,
        'items'     => $items,
        'truncated' => $truncated,
        'took_ms'   => (int) round((microtime(true) - $t0) * 1000),
    ];
}

// =========================================================================
// rel sponsored / nofollow pe linkurile găsite
// =========================================================================

/** Noua valoare a atributului rel. $rel: sponsored | nofollow | sponsored nofollow | dofollow (scoate). */
function magpress_bs_rel_value($existing, $rel) {
    $t = preg_split('~\s+~', strtolower(trim((string) $existing)), -1, PREG_SPLIT_NO_EMPTY);
    $t = array_values(array_diff($t, ['dofollow', 'follow']));
    if ($rel === 'dofollow') {
        $t = array_diff($t, ['nofollow', 'sponsored', 'ugc']);
    } else {
        foreach (explode(' ', $rel) as $x) if (!in_array($x, $t, true)) $t[] = $x;
    }
    return implode(' ', array_values(array_unique($t)));
}

/** Pune rel pe <a>-urile din HTML care duc la URL-uri de tipurile $kinds. */
function magpress_bs_html_set_rel($html, array $kinds, $rel, &$n) {
    if (stripos($html, '<a') === false) return $html;
    return preg_replace_callback('~<a\b[^>]*>~i', function ($m) use ($kinds, $rel, &$n) {
        $tag = $m[0];
        if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $tag, $hm)) $href = $hm[2];
        elseif (preg_match('~\bhref\s*=\s*([^\s>]+)~is', $tag, $hm)) $href = $hm[1];
        else return $tag;
        if (!in_array(magpress_bs_url_kind($href), $kinds, true)) return $tag;

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
    }, $html);
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
            && in_array(magpress_bs_url_kind($v['url']), $kinds, true)) {
            $want = $rel === 'dofollow' ? '' : 'on';
            if ((string) $v['nofollow'] !== $want) { $v['nofollow'] = $want; $n++; }
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
    if (!$kinds) return [0, 0, 0];
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
                $url = (string) get_post_meta((int) $it['id'], '_menu_item_url', true);
                if (!in_array(magpress_bs_url_kind($url), $kinds, true)) break;
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
// REST: POST /wp-json/magpress/v1/brand-scan, semnat HMAC cu deploy_secret
// {action:"scan", terms?} | {action:"set_rel", rel, kinds, terms?, targets?}
// → {ok, theme_version, home, terms, counts, items[], truncated, took_ms [, set_rel]}
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

    magpress_bs_terms((array) ($data['terms'] ?? []));
    $out = ['ok' => true, 'theme_version' => MAGPRESS_VERSION, 'home' => home_url('/')];
    $action = (string) ($data['action'] ?? 'scan');

    if ($action === 'set_rel') {
        $rel = (string) ($data['rel'] ?? '');
        if (!in_array($rel, ['sponsored', 'nofollow', 'sponsored nofollow', 'dofollow'], true)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'invalid_rel'], 400);
        }
        $targets = isset($data['targets']) && is_array($data['targets']) ? array_map('strval', $data['targets']) : null;
        list($links, $places, $skipped) = magpress_bs_set_rel($rel, (array) ($data['kinds'] ?? []), $targets);
        $out['set_rel'] = ['rel' => $rel, 'links' => $links, 'places' => $places, 'skipped' => $skipped];
    } elseif ($action !== 'scan') {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    return new WP_REST_Response($out + magpress_bs_scan(), 200);
}
