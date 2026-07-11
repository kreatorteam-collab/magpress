// Instant fleet deploy — fired by GitHub Actions on push to main.
//
// For each site it computes the same HMAC signature the theme's /deploy
// endpoint expects (hash_hmac('sha256', body, deploy_secret)) and POSTs to
// {site}/wp-json/magpress/v1/deploy. The site then pulls the freshly-pushed
// main zipball from GitHub and swaps files WITHOUT maintenance mode.
//
// Secrets never appear in logs. The fleet list comes from the encrypted
// MAGPRESS_FLEET GitHub secret; nothing sensitive lives in this file.

import crypto from 'node:crypto';

const FLEET_RAW = process.env.MAGPRESS_FLEET || '';
const SHA = process.env.GITHUB_SHA || 'main';
const CONCURRENCY = 8;          // câte site-uri în paralel (65 site-uri → ~9 batch-uri)
const TIMEOUT_MS = 120000;      // deploy-ul poate dura (download + swap)
const SETTLE_MS = 8000;         // lăsăm GitHub să reflecte commit-ul nou înainte de fan-out

function log(msg) { process.stdout.write(msg + '\n'); }

// --- Parse fleet -----------------------------------------------------------
if (!FLEET_RAW.trim()) {
  log('::warning::MAGPRESS_FLEET secret nu e setat — sar peste deploy.');
  log('Adaugă un GitHub Secret numit MAGPRESS_FLEET cu un JSON de forma:');
  log('  [{"url":"https://site1.ro","secret":"<deploy_secret>"}, ...]');
  process.exit(0); // soft-skip: comitul care adaugă workflow-ul rămâne verde
}

let fleet;
try {
  fleet = JSON.parse(FLEET_RAW);
} catch (e) {
  log('::error::MAGPRESS_FLEET nu e JSON valid: ' + e.message);
  process.exit(1);
}

// Acceptăm fie [{url,secret}], fie {url:secret}
if (!Array.isArray(fleet) && fleet && typeof fleet === 'object') {
  fleet = Object.entries(fleet).map(([url, secret]) => ({ url, secret }));
}
if (!Array.isArray(fleet) || fleet.length === 0) {
  log('::error::MAGPRESS_FLEET trebuie să fie o listă ne-goală de {url, secret}.');
  process.exit(1);
}

// Normalizează + validează
const sites = [];
for (const entry of fleet) {
  const url = String(entry.url || '').trim().replace(/\/+$/, '');
  const secret = String(entry.secret || '');
  if (!/^https?:\/\//.test(url) || secret.length < 16) {
    log(`::warning::Intrare invalidă în fleet (url sau secret lipsă/scurt): ${url || '(fără url)'} — sar peste.`);
    continue;
  }
  sites.push({ url, secret });
}
if (sites.length === 0) {
  log('::error::Niciun site valid în MAGPRESS_FLEET.');
  process.exit(1);
}

log(`Deploy instant către ${sites.length} site-uri (commit ${SHA.slice(0, 7)}).`);
log(`Aștept ${SETTLE_MS / 1000}s ca GitHub să servească noul zipball...`);
await new Promise((r) => setTimeout(r, SETTLE_MS));

// --- Deploy către un site --------------------------------------------------
async function deployOne(site) {
  const body = JSON.stringify({ ref: 'main', sha: SHA });
  const sig = crypto.createHmac('sha256', site.secret).update(body).digest('hex');
  const endpoint = site.url + '/wp-json/magpress/v1/deploy';

  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Magpress-Signature': sig,
        'User-Agent': 'magpress-ci-deploy',
      },
      body,
      signal: ctrl.signal,
    });
    const text = await res.text();
    let json = null;
    try { json = JSON.parse(text); } catch { /* non-JSON (ex: 403 HTML de la firewall) */ }

    if (res.ok && json && json.ok) {
      return { site: site.url, ok: true, version: json.version || '?' };
    }
    const reason = json ? (json.error || JSON.stringify(json).slice(0, 120))
                        : `HTTP ${res.status} (răspuns non-JSON)`;
    return { site: site.url, ok: false, reason };
  } catch (e) {
    const reason = e.name === 'AbortError' ? `timeout după ${TIMEOUT_MS / 1000}s` : e.message;
    return { site: site.url, ok: false, reason };
  } finally {
    clearTimeout(t);
  }
}

// --- Rulează în batch-uri --------------------------------------------------
const results = [];
for (let i = 0; i < sites.length; i += CONCURRENCY) {
  const batch = sites.slice(i, i + CONCURRENCY);
  const batchRes = await Promise.all(batch.map(deployOne));
  for (const r of batchRes) {
    if (r.ok) log(`  ✓ ${r.site} → ${r.version}`);
    else      log(`  ✗ ${r.site} — ${r.reason}`);
    results.push(r);
  }
}

// --- Sumar -----------------------------------------------------------------
const ok = results.filter((r) => r.ok);
const failed = results.filter((r) => !r.ok);
log('');
log(`Rezultat: ${ok.length} reușite, ${failed.length} eșuate din ${results.length}.`);

// Job summary (apare frumos în UI-ul Actions)
if (process.env.GITHUB_STEP_SUMMARY) {
  const fs = await import('node:fs');
  const lines = [
    `### Deploy instant — commit \`${SHA.slice(0, 7)}\``,
    '',
    `**${ok.length}** reușite · **${failed.length}** eșuate · ${results.length} total`,
    '',
  ];
  if (failed.length) {
    lines.push('| Site | Motiv |', '| --- | --- |');
    for (const f of failed) lines.push(`| ${f.site} | ${f.reason} |`);
  }
  fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n') + '\n');
}

// Eșec vizibil dacă vreun site n-a primit deploy-ul (cron-ul orar îl prinde ca fallback).
process.exit(failed.length > 0 ? 1 : 0);
