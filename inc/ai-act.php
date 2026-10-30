<?php
/**
 * v0.9.254 — AI Act (Regulamentul UE 2024/1689, art. 50): transparență privind
 * conținutul generat cu inteligență artificială.
 *
 * 1. Iconiță „Generat cu AI” peste imaginile recunoscute ca fiind făcute cu AI —
 *    imaginea reprezentativă (articol, carduri, homepage) și imaginile din conținut.
 * 2. Nota `<aside class="ai-note">` sub textul TUTUROR articolelor (nu doar al celor
 *    generate de noi), opțional și pe pagini.
 *
 * Recunoașterea imaginilor (pe TOATE imaginile din bibliotecă, inclusiv cele vechi):
 *  - generate de pluginul Horoscop Zilnic (meta `_hz_imagegen`);
 *  - metadate standard: IPTC/XMP DigitalSourceType „trainedAlgorithmicMedia” /
 *    „compositeWithTrainedAlgorithmicMedia” (ChatGPT, Gemini, Firefly, Midjourney,
 *    Meta etc. le scriu în fișier, inclusiv în manifestul C2PA);
 *  - semnături de generator în fișier (DALL·E, Midjourney, Stable Diffusion,
 *    ComfyUI, Firefly, „Made with Google AI”...);
 *  - numele fișierului / titlul (ex. „ChatGPT Image...”, „Gemini_Generated_Image...”).
 * Rezultatul se cache-uiește în meta atașamentului (fișierul se citește o singură
 * dată: la upload sau, pentru imaginile vechi, la prima afișare). Detecția automată
 * se poate corecta manual din Media → imagine → „AI Act: imagine generată cu AI?”.
 *
 * Totul se configurează din Customizer → „Magpress: AI Act (transparență AI)”.
 */
if (!defined('ABSPATH')) { exit; }

const MAGPRESS_AI_DETECT_VER = 1;          // crește-l când se schimbă regulile → rescanare
const MAGPRESS_AI_META_AUTO  = '_magpress_ai_auto';     // "ver|0/1|motiv"
const MAGPRESS_AI_META_FORCE = '_magpress_ai_override'; // "" (auto) / "yes" / "no"

function magpress_ai_default_note() {
    return '<strong>Informare privind utilizarea inteligenței artificiale.</strong> Imaginile și textele publicate pe acest site pot fi generate sau modificate cu ajutorul instrumentelor de inteligență artificială. Conținutul are caracter exclusiv informativ, imaginile au rol ilustrativ, iar materialele nu constituie consultanță juridică, medicală, financiară sau de altă natură. Pentru sesizări sau corecturi, ne puteți contacta la adresa de contact din pagina site-ului.';
}

// =========================================================================
// Customizer
// =========================================================================
function magpress_register_ai_act_settings($wp_customize) {
    $wp_customize->add_section('magpress_ai_act', [
        'title'       => 'Magpress: AI Act (transparență AI)',
        'description' => 'Conformare AI Act (art. 50): iconiță peste imaginile generate cu AI și notă de informare sub articole. Imaginile sunt recunoscute automat după metadatele din fișier; corectezi manual din Media → imagine.',
        'priority'    => 41,
    ]);
    $bool = function ($v) { return $v ? 1 : 0; };

    $wp_customize->add_setting('magpress_ai_badge', ['default' => 1, 'sanitize_callback' => $bool]);
    $wp_customize->add_control('magpress_ai_badge', [
        'label'   => 'Iconiță peste imaginile generate cu AI',
        'section' => 'magpress_ai_act',
        'type'    => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_ai_badge_style', [
        'default'           => 'pill',
        'sanitize_callback' => function ($v) { return in_array($v, ['pill', 'split'], true) ? $v : 'pill'; },
    ]);
    $wp_customize->add_control('magpress_ai_badge_style', [
        'label'   => 'Stil iconiță',
        'section' => 'magpress_ai_act',
        'type'    => 'select',
        'choices' => [
            'pill'  => '✦ Generat cu AI (pilulă mov)',
            'split' => 'IA | generat (două culori)',
        ],
    ]);

    $wp_customize->add_setting('magpress_ai_badge_text', ['default' => '', 'sanitize_callback' => 'sanitize_text_field']);
    $wp_customize->add_control('magpress_ai_badge_text', [
        'label'       => 'Text iconiță',
        'description' => 'Gol = „Generat cu AI” (pilulă) / „generat” (două culori).',
        'section'     => 'magpress_ai_act',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('magpress_ai_badge_short', ['default' => '', 'sanitize_callback' => 'sanitize_text_field']);
    $wp_customize->add_control('magpress_ai_badge_short', [
        'label'       => 'Text scurt (imagini mici + partea colorată)',
        'description' => 'Gol = „AI” (pilulă) / „IA” (două culori).',
        'section'     => 'magpress_ai_act',
        'type'        => 'text',
    ]);

    $wp_customize->add_setting('magpress_ai_all_images', ['default' => 0, 'sanitize_callback' => $bool]);
    $wp_customize->add_control('magpress_ai_all_images', [
        'label'       => 'Marchează TOATE imaginile ca generate cu AI',
        'description' => 'Pentru site-urile care folosesc doar imagini AI. Imaginile marcate manual „Nu” în Media rămân nemarcate.',
        'section'     => 'magpress_ai_act',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_ai_note', ['default' => 1, 'sanitize_callback' => $bool]);
    $wp_customize->add_control('magpress_ai_note', [
        'label'       => 'Notă de informare AI sub toate articolele',
        'section'     => 'magpress_ai_act',
        'type'        => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_ai_note_pages', ['default' => 0, 'sanitize_callback' => $bool]);
    $wp_customize->add_control('magpress_ai_note_pages', [
        'label'   => 'Afișează nota și pe pagini (nu doar pe articole)',
        'section' => 'magpress_ai_act',
        'type'    => 'checkbox',
    ]);

    $wp_customize->add_setting('magpress_ai_note_text', [
        'default'           => magpress_ai_default_note(),
        'sanitize_callback' => function ($v) {
            return trim(wp_kses((string) $v, ['strong' => [], 'b' => [], 'em' => [], 'i' => [], 'br' => [], 'a' => ['href' => [], 'target' => [], 'rel' => []]]));
        },
    ]);
    $wp_customize->add_control('magpress_ai_note_text', [
        'label'       => 'Textul notei',
        'description' => 'HTML permis: &lt;strong&gt;, &lt;em&gt;, &lt;a&gt;, &lt;br&gt;. Gol = textul implicit.',
        'section'     => 'magpress_ai_act',
        'type'        => 'textarea',
    ]);
}
add_action('customize_register', 'magpress_register_ai_act_settings');

// =========================================================================
// Detecție
// =========================================================================

/**
 * Analizează fișierul imaginii. Întoarce motivul (string) dacă e AI, '' dacă nu.
 */
function magpress_ai_detect_attachment($id) {
    if (get_post_meta($id, '_hz_imagegen', true) !== '') return 'generator Horoscop Zilnic';

    $file = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($id) : '';
    if (!$file || !is_readable($file)) $file = get_attached_file($id);

    $names = strtolower(($file ? wp_basename($file) : '') . ' ' . get_the_title($id));
    if (preg_match('/dall[\W_]?e|midjourney|chatgpt[\W_]+image|gemini[\W_]+generated|firefly|stable[\W_]?diffusion|openai|ai[\W_]+generated|generat[a-z]*[\W_]+cu[\W_]+ai|ideogram|leonardo[\W_]+ai|nano[\W_]+banana/', $names, $m)) {
        return 'nume fișier: ' . $m[0];
    }

    if (!$file || !is_readable($file)) return '';
    $size = (int) @filesize($file);
    $fh   = @fopen($file, 'rb');
    if (!$fh) return '';
    // Metadatele (EXIF/XMP/IPTC/C2PA) stau la începutul fișierului; la PNG/WebP
    // unele chunk-uri pot fi la final, deci citim și coada.
    $buf = (string) fread($fh, 512 * 1024);
    if ($size > 512 * 1024) {
        fseek($fh, max(512 * 1024, $size - 128 * 1024));
        $buf .= (string) fread($fh, 128 * 1024);
    }
    fclose($fh);

    if (stripos($buf, 'algorithmicMedia') !== false) return 'metadate IPTC/C2PA: generat cu AI';
    $sigs = [
        'DALL-E', "DALL\xC2\xB7E", 'Midjourney', 'Stable Diffusion', 'Adobe Firefly', 'Made with Google AI',
        'OpenAI', 'ChatGPT', 'Ideogram', 'Leonardo.Ai', 'NovelAI', 'ComfyUI', 'Imagen by Google',
    ];
    foreach ($sigs as $s) {
        if (strpos($buf, $s) !== false) return 'semnătură generator: ' . $s;
    }
    // Stable Diffusion (Automatic1111) scrie parametrii generării în PNG tEXt „parameters”;
    // ComfyUI scrie graful de noduri în „prompt”/„workflow”.
    if (strpos($buf, 'parameters') !== false && strpos($buf, 'Steps: ') !== false && strpos($buf, 'Sampler: ') !== false) {
        return 'parametri Stable Diffusion';
    }
    if (strpos($buf, '"class_type"') !== false) return 'graf ComfyUI';
    return '';
}

/** Rezultatul detecției automate, cache-uit în meta: [bool, motiv]. */
function magpress_ai_auto_result($id, $allow_scan = true) {
    $raw = (string) get_post_meta($id, MAGPRESS_AI_META_AUTO, true);
    $p   = explode('|', $raw, 3);
    if (count($p) === 3 && (int) $p[0] === MAGPRESS_AI_DETECT_VER) return [$p[1] === '1', $p[2]];
    if (!$allow_scan) return null;
    $reason = magpress_ai_detect_attachment($id);
    update_post_meta($id, MAGPRESS_AI_META_AUTO, MAGPRESS_AI_DETECT_VER . '|' . ($reason !== '' ? '1' : '0') . '|' . $reason);
    return [$reason !== '', $reason];
}

function magpress_ai_image_is_ai($id) {
    static $cache = [], $scans = 0;
    $id = (int) $id;
    if ($id <= 0) return false;
    if (isset($cache[$id])) return $cache[$id];

    $force = (string) get_post_meta($id, MAGPRESS_AI_META_FORCE, true);
    if ($force === 'yes' || $force === 'no') {
        $is = $force === 'yes';
    } elseif (get_theme_mod('magpress_ai_all_images', 0)) {
        $is = true;
    } else {
        // Imaginile vechi se scanează la prima afișare; plafon per request ca o
        // pagină cu multe imagini nescanate să nu întârzie (restul, la vizita următoare).
        $res = magpress_ai_auto_result($id, $scans < 12);
        if ($res === null) return false;
        $scans++;
        $is = $res[0];
    }
    return $cache[$id] = (bool) apply_filters('magpress_ai_image_is_ai', $is, $id);
}

// Scanare la upload (inclusiv imaginile descărcate de pluginuri prin sideload).
add_action('add_attachment', function ($id) {
    if (wp_attachment_is_image($id)) magpress_ai_auto_result($id);
});

// =========================================================================
// Media Library: corecție manuală
// =========================================================================
add_filter('attachment_fields_to_edit', function ($fields, $post) {
    if (!wp_attachment_is_image($post->ID)) return $fields;
    $force = (string) get_post_meta($post->ID, MAGPRESS_AI_META_FORCE, true);
    $res   = magpress_ai_auto_result($post->ID);
    $auto  = $res[0] ? 'Da — ' . $res[1] : 'Nu';
    $name  = 'attachments[' . $post->ID . '][magpress_ai]';
    $opts  = ['' => 'Automat (detectat: ' . $auto . ')', 'yes' => 'Da — afișează iconița AI', 'no' => 'Nu — fără iconiță'];
    $html  = '<select name="' . esc_attr($name) . '">';
    foreach ($opts as $v => $label) {
        $html .= '<option value="' . esc_attr($v) . '"' . selected($force, $v, false) . '>' . esc_html($label) . '</option>';
    }
    $html .= '</select>';
    $fields['magpress_ai'] = [
        'label' => 'AI Act: imagine generată cu AI?',
        'input' => 'html',
        'html'  => $html,
        'helps' => 'Hotărăște dacă peste imagine apare iconița „Generat cu AI”.',
    ];
    return $fields;
}, 10, 2);

add_filter('attachment_fields_to_save', function ($post, $attachment) {
    if (isset($attachment['magpress_ai'])) {
        $v = in_array($attachment['magpress_ai'], ['yes', 'no'], true) ? $attachment['magpress_ai'] : '';
        if ($v === '') delete_post_meta($post['ID'], MAGPRESS_AI_META_FORCE);
        else update_post_meta($post['ID'], MAGPRESS_AI_META_FORCE, $v);
    }
    return $post;
}, 10, 2);

// =========================================================================
// Afișare iconiță
// =========================================================================
function magpress_ai_frontend() {
    if (is_admin() || is_feed() || wp_doing_ajax()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    return (bool) get_theme_mod('magpress_ai_badge', 1);
}

function magpress_ai_badge_html() {
    $split = get_theme_mod('magpress_ai_badge_style', 'pill') === 'split';
    $long  = trim((string) get_theme_mod('magpress_ai_badge_text', '')) ?: ($split ? 'generat' : 'Generat cu AI');
    $short = trim((string) get_theme_mod('magpress_ai_badge_short', '')) ?: ($split ? 'IA' : 'AI');
    // Textul vine din CSS (attr), nu din DOM: nu intră în anchor text-ul cardurilor
    // și nici în textul indexat al articolului. Numele accesibil e în aria-label.
    $star = $split ? '' : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.5c.4 4.9 2.2 8.1 5.4 9.6 1.3.6 2.9.9 5.1 1-4.9.4-8.1 2.2-9.6 5.4-.6 1.3-.9 2.9-1 5.1-.4-4.9-2.2-8.1-5.4-9.6-1.3-.6-2.9-.9-5.1-1 4.9-.4 8.1-2.2 9.6-5.4.6-1.3.9-2.9 1-5.1z"/></svg>';
    return '<span class="mp-ai-badge mp-ai-badge--' . ($split ? 'split' : 'pill') . '" role="img" aria-label="Imagine generată cu inteligență artificială" data-l="'
        . esc_attr($long) . '" data-s="' . esc_attr($short) . '">' . $star . '</span>';
}

// Imaginea reprezentativă: iconița e sora imaginii, în containerul ei (card, figure).
add_filter('post_thumbnail_html', function ($html, $post_id, $thumb_id) {
    if ($html === '' || !magpress_ai_frontend() || !magpress_ai_image_is_ai($thumb_id)) return $html;
    return $html . magpress_ai_badge_html();
}, 20, 3);

// Imaginile din conținut (WP 6.0+): învelim <img> ca iconița să stea pe colțul pozei.
add_filter('wp_content_img_tag', function ($img, $context, $attachment_id) {
    if ($context !== 'the_content' || !$attachment_id || !magpress_ai_frontend() || !magpress_ai_image_is_ai($attachment_id)) return $img;
    $cls = 'mp-ai';
    // Float-ul trece de pe imagine pe înveliș, altfel textul nu mai curge pe lângă ea.
    if (preg_match('/\bclass="([^"]*)"/', $img, $m) && preg_match('/\balign(left|right)\b/', $m[1], $a)) {
        $cls .= ' mp-ai--' . $a[1];
        $img  = str_replace($m[0], 'class="' . trim(preg_replace('/\balign(left|right)\b/', '', $m[1])) . '"', $img);
    }
    return '<span class="' . $cls . '">' . $img . magpress_ai_badge_html() . '</span>';
}, 20, 3);

// =========================================================================
// Nota de informare sub text
// =========================================================================
function magpress_ai_note_html() {
    $txt = trim((string) get_theme_mod('magpress_ai_note_text', ''));
    if ($txt === '') $txt = magpress_ai_default_note();
    return '<aside class="ai-note"><p>' . $txt . '</p></aside>';
}

add_filter('the_content', function ($content) {
    if (!get_theme_mod('magpress_ai_note', 1) || is_admin() || is_feed()) return $content;
    if (!in_the_loop() || !is_main_query()) return $content;
    $ok = is_singular('post') || (get_theme_mod('magpress_ai_note_pages', 0) && is_page() && !is_front_page());
    if (!$ok || get_the_ID() !== get_queried_object_id()) return $content;
    return $content . magpress_ai_note_html();
}, 99);

// =========================================================================
// CSS (inline, doar dacă vreo funcție e activă)
// =========================================================================
add_action('wp_enqueue_scripts', function () {
    $badge = get_theme_mod('magpress_ai_badge', 1);
    $note  = get_theme_mod('magpress_ai_note', 1);
    if (!$badge && !$note) return;
    $css = '';
    if ($badge) {
        // Containerele de imagini statice primesc position:relative ca iconița să se
        // ancoreze pe ele; restul (card-image, featured-card, overlay) sunt deja poziționate.
        $css .= ':is(.article-image,.card-list__image,.mp-featcat__rowimg,.mp-featcat__crowimg):has(>.mp-ai-badge){position:relative}'
              . '.mp-ai-badge{position:absolute;top:.6rem;right:.6rem;z-index:3;display:inline-flex;align-items:center;gap:.4em;'
              . 'font:600 12px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif;letter-spacing:.01em;color:#fff;'
              . 'pointer-events:none;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.25)}'
              . '.mp-ai-badge--pill{background:#5b4fe0;padding:.5em .9em;border-radius:999px}'
              . '.mp-ai-badge--pill::after{content:attr(data-l)}'
              . '.mp-ai-badge svg{width:1.05em;height:1.05em;fill:currentColor;display:block;flex:none}'
              . '.mp-ai-badge--split{background:#5f6670;border-radius:6px;overflow:hidden;gap:0}'
              . '.mp-ai-badge--split::before{content:attr(data-s);background:#5b4fe0;padding:.5em .65em}'
              . '.mp-ai-badge--split::after{content:attr(data-l);padding:.5em .75em}'
              // Imagini mici (liste, rânduri laterale): doar textul scurt.
              . ':is(.card-list__image,.mp-featcat__rowimg,.mp-featcat__crowimg)>.mp-ai-badge{font-size:10px;top:.35rem;right:.35rem}'
              . ':is(.card-list__image,.mp-featcat__rowimg,.mp-featcat__crowimg)>.mp-ai-badge--pill::after{content:attr(data-s)}'
              . ':is(.card-list__image,.mp-featcat__rowimg,.mp-featcat__crowimg)>.mp-ai-badge--split::after{content:none}'
              . '.mp-ai{position:relative;display:block;width:fit-content;max-width:100%;margin:1.5rem auto}'
              . '.mp-ai>img{margin-top:0!important;margin-bottom:0!important}'
              . '.mp-ai--left{float:left;margin:.5rem 1.5rem 1rem 0}'
              . '.mp-ai--right{float:right;margin:.5rem 0 1rem 1.5rem}'
              . 'figure .mp-ai{margin-top:0;margin-bottom:0}'
              . '@media(max-width:640px){.mp-ai-badge{font-size:11px;top:.45rem;right:.45rem}}';
    }
    if ($note) {
        $css .= '.ai-note{clear:both;margin:2rem 0 0;padding:.9rem 1.1rem;border:1px solid var(--c-border,#e6e6e6);border-left:3px solid #5b4fe0;'
              . 'border-radius:var(--radius,6px);background:var(--c-surface,#f7f7f8);font-size:.82rem;line-height:1.55;color:var(--c-text-muted,#555)}'
              . '.ai-note p,.entry-content .ai-note p{margin:0;font-size:inherit;line-height:inherit}.ai-note strong{color:var(--c-text,#1a1a1a)}';
    }
    wp_add_inline_style('magpress-main', $css);
}, 20);
