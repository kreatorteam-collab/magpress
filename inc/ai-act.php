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
 * Fotografiile cu date de cameră (EXIF: marcă/model, expunere) și fără marcaj AI sunt
 * „foto reală”; cele fără niciun indiciu sunt „necunoscute” (fără iconiță, decât dacă
 * site-ul pornește „Imaginile fără date de cameră = AI”).
 * Rezultatul se cache-uiește în meta atașamentului. Scanare: la upload, la publicare
 * (imaginile articolului), în fundal pentru toată biblioteca (cron, se oprește singur
 * la final) și la prima afișare. Corecție manuală: Media → imagine → „AI Act”.
 *
 * Setări: Customizer → „Magpress: AI Act (transparență AI)” sau, pe toată flota,
 * app.targetseo.ro → AI Act (REST magpress/v1/ai-act, semnat HMAC).
 */
if (!defined('ABSPATH')) { exit; }

const MAGPRESS_AI_DETECT_VER = 1;          // crește-l când se schimbă regulile → rescanare
const MAGPRESS_AI_META_AUTO  = '_magpress_ai_auto';     // "ver|clasă|motiv" (clasă: 1 AI, c cameră, 0 necunoscut)
const MAGPRESS_AI_META_FORCE = '_magpress_ai_override'; // "" (auto) / "yes" / "no"

function magpress_ai_default_note() {
    return '<strong>Informare privind utilizarea inteligenței artificiale.</strong> Imaginile și textele publicate pe acest site pot fi generate sau modificate cu ajutorul instrumentelor de inteligență artificială. Conținutul are caracter exclusiv informativ, imaginile au rol ilustrativ, iar materialele nu constituie consultanță juridică, medicală, financiară sau de altă natură. Pentru sesizări sau corecturi, ne puteți contacta la adresa de contact din pagina site-ului.';
}

// =========================================================================
// Setări (aceeași schemă pentru Customizer și pentru endpointul din app.targetseo.ro)
// =========================================================================
function magpress_ai_settings_schema() {
    $bool = function ($v) { return $v ? 1 : 0; };
    return [
        'magpress_ai_badge' => [
            'default' => 1, 'sanitize' => $bool, 'type' => 'checkbox',
            'label'   => 'Iconiță peste imaginile generate cu AI',
        ],
        'magpress_ai_badge_style' => [
            'default' => 'mini', 'type' => 'select',
            'sanitize' => function ($v) { return in_array($v, ['mini', 'pill', 'split'], true) ? $v : 'mini'; },
            'label'   => 'Stil iconiță',
            'choices' => ['mini' => '✦ IA (mică, închisă la culoare)', 'pill' => '✦ Generat cu AI (pilulă mov)', 'split' => 'IA | generat (două culori)'],
        ],
        'magpress_ai_badge_text' => [
            'default' => '', 'sanitize' => 'sanitize_text_field', 'type' => 'text',
            'label'   => 'Text iconiță',
            'description' => 'Gol = „Generat cu AI” (pilulă) / „generat” (două culori). Stilul mic afișează doar textul scurt.',
        ],
        'magpress_ai_badge_short' => [
            'default' => '', 'sanitize' => 'sanitize_text_field', 'type' => 'text',
            'label'   => 'Text scurt (imagini mici + partea colorată)',
            'description' => 'Gol = „IA” (stil mic, două culori) / „AI” (pilulă).',
        ],
        'magpress_ai_all_images' => [
            'default' => 0, 'sanitize' => $bool, 'type' => 'checkbox',
            'label'   => 'Marchează TOATE imaginile ca generate cu AI',
            'description' => 'Pentru site-urile care folosesc doar imagini AI. Imaginile marcate manual „Nu” în Media rămân nemarcate.',
        ],
        'magpress_ai_unknown_as_ai' => [
            'default' => 0, 'sanitize' => $bool, 'type' => 'checkbox',
            'label'   => 'Imaginile fără date de cameră = AI',
            'description' => 'Pozele fără niciun indiciu (nici marcaj AI, nici date de cameră) primesc și ele iconița. Pornește doar pe site-urile unde pozele reale vin cu date EXIF.',
        ],
        'magpress_ai_note' => [
            'default' => 1, 'sanitize' => $bool, 'type' => 'checkbox',
            'label'   => 'Notă de informare AI sub toate articolele',
        ],
        'magpress_ai_note_pages' => [
            'default' => 0, 'sanitize' => $bool, 'type' => 'checkbox',
            'label'   => 'Afișează nota și pe pagini (nu doar pe articole)',
        ],
        'magpress_ai_note_text' => [
            'default' => magpress_ai_default_note(), 'type' => 'textarea',
            'sanitize' => function ($v) {
                return trim(wp_kses((string) $v, ['strong' => [], 'b' => [], 'em' => [], 'i' => [], 'br' => [], 'a' => ['href' => [], 'target' => [], 'rel' => []]]));
            },
            'label'   => 'Textul notei',
            'description' => 'HTML permis: &lt;strong&gt;, &lt;em&gt;, &lt;a&gt;, &lt;br&gt;. Gol = textul implicit.',
        ],
    ];
}

function magpress_register_ai_act_settings($wp_customize) {
    $wp_customize->add_section('magpress_ai_act', [
        'title'       => 'Magpress: AI Act (transparență AI)',
        'description' => 'Conformare AI Act (art. 50): iconiță peste imaginile generate cu AI și notă de informare sub articole. Imaginile sunt recunoscute automat după metadatele din fișier; corectezi manual din Media → imagine. Setările se pot trimite pe toată flota din app.targetseo.ro → AI Act.',
        'priority'    => 41,
    ]);
    foreach (magpress_ai_settings_schema() as $key => $f) {
        $wp_customize->add_setting($key, ['default' => $f['default'], 'sanitize_callback' => $f['sanitize']]);
        $ctl = ['label' => $f['label'], 'section' => 'magpress_ai_act', 'type' => $f['type']];
        if (!empty($f['description'])) $ctl['description'] = $f['description'];
        if (!empty($f['choices']))     $ctl['choices']     = $f['choices'];
        $wp_customize->add_control($key, $ctl);
    }
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

/**
 * Fotografie reală? Camerele și telefoanele scriu în EXIF marca/modelul, diafragma,
 * timpul de expunere, ISO. WordPress le extrage deja la upload (image_meta), deci de
 * obicei nu mai citim fișierul.
 */
function magpress_ai_camera_reason($id) {
    $meta = wp_get_attachment_metadata($id);
    $im   = is_array($meta) && isset($meta['image_meta']) && is_array($meta['image_meta']) ? $meta['image_meta'] : null;
    if ($im === null && !function_exists('wp_read_image_metadata') && is_readable(ABSPATH . 'wp-admin/includes/image.php')) {
        require_once ABSPATH . 'wp-admin/includes/image.php'; // lipsește pe front / în cron
    }
    if ($im === null && function_exists('wp_read_image_metadata')) {
        $file = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($id) : '';
        if (!$file || !is_readable($file)) $file = get_attached_file($id);
        $im = ($file && is_readable($file)) ? wp_read_image_metadata($file) : null;
    }
    if (!is_array($im)) return '';
    $camera = trim((string) ($im['camera'] ?? ''));
    if ($camera !== '') return 'cameră: ' . $camera;
    if ((float) ($im['aperture'] ?? 0) > 0 && (float) ($im['shutter_speed'] ?? 0) > 0) return 'date de expunere (diafragmă, timp)';
    return '';
}

/**
 * Rezultatul detecției automate, cache-uit în meta „ver|clasă|motiv”: [clasă, motiv].
 * Clasă: '1' = generat cu AI, 'c' = fotografie cu date de cameră, '0' = necunoscut
 * (fără niciun indiciu — ex. poze redimensionate / de pe WhatsApp / de pe alte site-uri,
 * cărora li s-au șters metadatele).
 */
function magpress_ai_auto_result($id, $allow_scan = true) {
    $raw = (string) get_post_meta($id, MAGPRESS_AI_META_AUTO, true);
    $p   = explode('|', $raw, 3);
    if (count($p) === 3 && (int) $p[0] === MAGPRESS_AI_DETECT_VER) return [$p[1], $p[2]];
    if (!$allow_scan) return null;
    // Marcajul AI are prioritate: o fotografie editată cu AI (ex. Pixel / Samsung / Photoshop
    // Generative Fill) are și date de cameră, dar și DigitalSourceType „composite...”.
    $reason = magpress_ai_detect_attachment($id);
    $cls    = '1';
    if ($reason === '') {
        $reason = magpress_ai_camera_reason($id);
        $cls    = $reason !== '' ? 'c' : '0';
    }
    update_post_meta($id, MAGPRESS_AI_META_AUTO, MAGPRESS_AI_DETECT_VER . '|' . $cls . '|' . str_replace('|', '/', $reason));
    return [$cls, $reason];
}

/** Clasa detectată → iconiță? (necunoscutele doar dacă site-ul o cere explicit) */
function magpress_ai_class_is_ai($cls) {
    return $cls === '1' || ($cls === '0' && get_theme_mod('magpress_ai_unknown_as_ai', 0));
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
        $is = magpress_ai_class_is_ai($res[0]);
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
    $auto  = ['1' => 'AI — ' . $res[1], 'c' => 'fotografie reală — ' . $res[1], '0' => 'necunoscut, fără metadate'][$res[0]] ?? 'necunoscut';
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
    $style = get_theme_mod('magpress_ai_badge_style', 'mini');
    if (!in_array($style, ['mini', 'pill', 'split'], true)) $style = 'mini';
    $split = $style === 'split';
    $long  = trim((string) get_theme_mod('magpress_ai_badge_text', '')) ?: ($split ? 'generat' : 'Generat cu AI');
    $short = trim((string) get_theme_mod('magpress_ai_badge_short', '')) ?: ($style === 'pill' ? 'AI' : 'IA');
    // Textul vine din CSS (attr), nu din DOM: nu intră în anchor text-ul cardurilor
    // și nici în textul indexat al articolului. Numele accesibil e în aria-label.
    $star = $split ? '' : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.5c.4 4.9 2.2 8.1 5.4 9.6 1.3.6 2.9.9 5.1 1-4.9.4-8.1 2.2-9.6 5.4-.6 1.3-.9 2.9-1 5.1-.4-4.9-2.2-8.1-5.4-9.6-1.3-.6-2.9-.9-5.1-1 4.9-.4 8.1-2.2 9.6-5.4.6-1.3.9-2.9 1-5.1z"/></svg>';
    return '<span class="mp-ai-badge mp-ai-badge--' . $style . '" role="img" aria-label="Imagine generată cu inteligență artificială" data-l="'
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
              . '.mp-ai-badge--mini{background:#0f1b2d;border:2px solid #fff;border-radius:999px;padding:.4em .75em;font-size:11px;font-weight:700;gap:.35em;letter-spacing:.03em}'
              . '.mp-ai-badge--mini svg{color:#3ddbc1}'
              . '.mp-ai-badge--mini::after{content:attr(data-s)}'
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

// =========================================================================
// Scanare: la publicare (instant) + în fundal (toată biblioteca, imaginile vechi)
// =========================================================================

/** ID-urile imaginilor unui articol: reprezentativa + cele din conținut (wp-image-N). */
function magpress_ai_post_image_ids($post) {
    $post = get_post($post);
    if (!$post) return [];
    $ids = [(int) get_post_thumbnail_id($post)];
    if (preg_match_all('/\bwp-image-(\d+)\b/', (string) $post->post_content, $m)) {
        $ids = array_merge($ids, array_map('intval', $m[1]));
    }
    return array_values(array_unique(array_filter($ids)));
}

// La publicare: imaginile articolului se verifică pe loc, ca iconița să fie acolo de la
// prima afișare (cele încărcate acum sunt deja scanate la upload; aici prindem imaginile
// vechi din bibliotecă refolosite în articolul nou).
add_action('transition_post_status', function ($new, $old, $post) {
    if ($new !== 'publish' || !in_array($post->post_type, ['post', 'page'], true)) return;
    foreach (magpress_ai_post_image_ids($post) as $id) {
        if (wp_attachment_is_image($id)) magpress_ai_auto_result($id);
    }
}, 10, 3);
// Imaginea reprezentativă e uneori setată DUPĂ publicare (REST / pluginuri de import).
add_action('updated_post_meta', function ($mid, $post_id, $key, $value) {
    if ($key === '_thumbnail_id' && (int) $value && get_post_status($post_id) === 'publish') {
        magpress_ai_auto_result((int) $value);
    }
}, 10, 4);
add_action('added_post_meta', function ($mid, $post_id, $key, $value) {
    if ($key === '_thumbnail_id' && (int) $value && get_post_status($post_id) === 'publish') {
        magpress_ai_auto_result((int) $value);
    }
}, 10, 4);

/** Imaginile care încă nu au rezultat cu versiunea curentă a regulilor. */
function magpress_ai_unscanned_ids($limit) {
    global $wpdb;
    return array_map('intval', $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
         WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%%'
           AND (m.meta_value IS NULL OR m.meta_value NOT LIKE %s)
         ORDER BY p.ID DESC LIMIT %d",
        MAGPRESS_AI_META_AUTO, $wpdb->esc_like(MAGPRESS_AI_DETECT_VER . '|') . '%', (int) $limit
    )));
}

/** Scanează un lot (plafonat la $seconds). Return [scanned, ai_found, remaining]. */
function magpress_ai_scan_batch($limit = 150, $seconds = 15) {
    $t0 = microtime(true);
    $scanned = $found = 0;
    foreach (magpress_ai_unscanned_ids($limit) as $id) {
        $res = magpress_ai_auto_result($id);
        $scanned++;
        if ($res[0] === '1') $found++;
        if (microtime(true) - $t0 > $seconds) break;
    }
    $remaining = count(magpress_ai_unscanned_ids(1));
    if ($found) update_option('magpress_ai_scan_dirty', 1, false); // golim cache-ul la final
    if (!$remaining) {
        update_option('magpress_ai_scan_ver', MAGPRESS_AI_DETECT_VER, false);
        wp_clear_scheduled_hook('magpress_ai_scan');
        // Paginile din cache au fost generate fără iconițe — o singură golire, la final.
        if (get_option('magpress_ai_scan_dirty') && function_exists('magpress_purge_litespeed')) {
            magpress_purge_litespeed();
        }
        delete_option('magpress_ai_scan_dirty');
    }
    return [$scanned, $found, $remaining];
}

// Scanarea de fundal pornește singură după deploy (și din nou dacă se schimbă regulile)
// și se oprește când a terminat biblioteca; upload-urile noi se scanează pe loc.
add_filter('cron_schedules', function ($s) {
    if (!isset($s['magpress_5min'])) $s['magpress_5min'] = ['interval' => 300, 'display' => 'La 5 minute (Magpress)'];
    return $s;
});
add_action('init', function () {
    if ((int) get_option('magpress_ai_scan_ver', 0) === MAGPRESS_AI_DETECT_VER) return;
    if (!wp_next_scheduled('magpress_ai_scan')) wp_schedule_event(time() + 60, 'magpress_5min', 'magpress_ai_scan');
});
add_action('magpress_ai_scan', function () { magpress_ai_scan_batch(); });
add_action('switch_theme', function () { wp_clear_scheduled_hook('magpress_ai_scan'); });

function magpress_ai_stats() {
    global $wpdb;
    $ver = $wpdb->esc_like(MAGPRESS_AI_DETECT_VER . '|');
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
    $scanned = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", MAGPRESS_AI_META_AUTO, $ver . '%'));
    $classes = $wpdb->get_results($wpdb->prepare("SELECT SUBSTRING(meta_value, %d, 1) cls, COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s GROUP BY cls", strlen(MAGPRESS_AI_DETECT_VER . '|') + 1, MAGPRESS_AI_META_AUTO, $ver . '%'), OBJECT_K);
    $cnt = function ($c) use ($classes) { return isset($classes[$c]) ? (int) $classes[$c]->c : 0; };
    $forced = $wpdb->get_results($wpdb->prepare("SELECT meta_value v, COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key = %s GROUP BY meta_value", MAGPRESS_AI_META_FORCE), OBJECT_K);
    $recent = [];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 8", MAGPRESS_AI_META_AUTO, $ver . '1|%'));
    foreach ($rows as $r) {
        $p = explode('|', $r->meta_value, 3);
        $recent[] = ['id' => (int) $r->post_id, 'url' => (string) wp_get_attachment_image_url((int) $r->post_id, 'thumbnail'), 'reason' => $p[2] ?? ''];
    }
    return [
        'images_total'   => $total,
        'images_scanned' => min($scanned, $total),
        'images_ai'      => $cnt('1'),
        'images_camera'  => $cnt('c'),
        'images_unknown' => $cnt('0'),
        'forced_yes'     => isset($forced['yes']) ? (int) $forced['yes']->c : 0,
        'forced_no'      => isset($forced['no']) ? (int) $forced['no']->c : 0,
        'scan_running'   => (bool) wp_next_scheduled('magpress_ai_scan'),
        'recent_ai'      => $recent,
    ];
}

// =========================================================================
// REST pentru app.targetseo.ro → AI Act (bulk pe toată flota)
// POST /wp-json/magpress/v1/ai-act, semnat HMAC cu deploy_secret (X-Magpress-Signature)
// {action:"get"} | {action:"set", settings:{cheie:valoare}} | {action:"scan"}
// =========================================================================
add_action('rest_api_init', function () {
    register_rest_route('magpress/v1', '/ai-act', [
        'methods'             => 'POST',
        'callback'            => 'magpress_rest_ai_act',
        'permission_callback' => '__return_true', // validăm HMAC manual
    ]);
});

function magpress_rest_ai_act(WP_REST_Request $request) {
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

    $schema = magpress_ai_settings_schema();
    $action = (string) ($data['action'] ?? 'get');
    $out    = ['ok' => true, 'theme_version' => MAGPRESS_VERSION];

    if ($action === 'set') {
        $changed = [];
        foreach ((array) ($data['settings'] ?? []) as $key => $val) {
            if (!isset($schema[$key])) continue;
            $val = call_user_func($schema[$key]['sanitize'], $val);
            if (get_theme_mod($key, $schema[$key]['default']) !== $val) $changed[] = $key;
            set_theme_mod($key, $val);
        }
        if ($changed && function_exists('magpress_purge_litespeed')) magpress_purge_litespeed();
        $out['changed'] = $changed;
    } elseif ($action === 'scan') {
        list($s, $f, $r) = magpress_ai_scan_batch(400, 20);
        $out['scan'] = ['scanned' => $s, 'ai_found' => $f, 'remaining' => $r];
    } elseif ($action !== 'get') {
        return new WP_REST_Response(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    $settings = [];
    foreach ($schema as $key => $f) $settings[$key] = get_theme_mod($key, $f['default']);
    $out['settings'] = $settings;
    $out['stats']    = magpress_ai_stats();
    return new WP_REST_Response($out, 200);
}
