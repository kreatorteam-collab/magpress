# Magpress v0.3

Temă WordPress magazine **clasică PHP** (NU FSE), ultra-performantă, optimizată pentru PageSpeed 100/100 și Core Web Vitals.

Construită pentru rebrand-uirea a ~65 site-uri ale autorului din același cod (template-uri identice peste tot, doar culorile + logo + categoriile homepage diferă per site).

Design inspirat din [povesteacasei.ro](https://www.povesteacasei.ro/).

## Caracteristici

- **Zero JavaScript** custom (doar `wp_head` / `wp_footer`)
- **Zero jQuery**, zero emoji script, zero oEmbed bloat
- Compatibil **LiteSpeed Cache** (nu duplicăm minify/critical CSS)
- Compatibil **SEOPress** (nu duplicăm meta tags, OG, schema, sitemap)
- HTML semantic curat: `<article>`, `<nav>`, `<aside>`, `<header>`, `<footer>`, `<section>`
- Imagini cu `aspect-ratio` declarat → CLS zero
- Branding per-site prin `site-config.json` extern

## Structură

```
magpress/
├── style.css                          # Header WP (numele temei)
├── functions.php                      # Setup + dezactivare bloat + branding loader
├── header.php                         # Logo + meniu + search
├── footer.php                         # 4 coloane widgets + copyright
├── front-page.php                     # Homepage: featured + category rows + newsletter
├── archive.php                        # Categorie/tag: breadcrumbs + grid + paginare
├── single.php                         # Articol: hero + content + related + comments
├── page.php                           # Pagină statică
├── search.php                         # Rezultate căutare
├── 404.php
├── index.php                          # Fallback blog
├── comments.php                       # Listare + form comentarii
├── assets/css/style.css               # Tot CSS-ul temei (~14KB necomprimat)
└── template-parts/
    ├── card.php                       # Card articol (imagine + titlu)
    ├── featured.php                   # Card featured (cu overlay gradient)
    ├── section-header.php             # Header secțiune (icon + titlu + "Vezi toate")
    └── newsletter.php                 # Newsletter signup
```

## Branding per site

Fiecare instalare WP are propriul fișier (NU în git):

```
/wp-content/themes/magpress-config/site-config.json
```

Conținut exemplu:

```json
{
  "colors": {
    "primary":     "#1a1a1a",
    "accent":      "#6fa84a",
    "background":  "#ffffff",
    "text":        "#1a1a1a",
    "text_muted":  "#6b6b6b",
    "border":      "#e6e6e6",
    "surface":     "#f6f7f5"
  },
  "font_family": "'Inter', -apple-system, sans-serif",
  "homepage_categories": ["amenajari", "gradina-si-plante", "constructii", "sfaturi-practice"]
}
```

CSS-ul folosește variabilele `--c-primary`, `--c-accent`, etc. Funcția `magpress_inject_site_branding()` din `functions.php` injectează override-urile pe `wp_head`.

## Categorii homepage

Implicit: top 4 categorii după numărul de articole.
Override: setează `homepage_categories` în `site-config.json` cu slug-urile categoriilor.

## Deploy

Update centralizat din git pe toate site-urile:

```
GitHub push → webhook → fiecare site face git pull în /wp-content/themes/magpress/
```

Webhook + endpoint `deploy.php` configurate per site (separat de temă).

## Licență

GPL-2.0-or-later
