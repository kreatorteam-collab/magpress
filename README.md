# Magpress

Temă WordPress de tip magazine, **FSE (block theme)**, ultra-performantă, optimizată pentru PageSpeed 100/100 și Core Web Vitals verzi.

Construită pentru a rebrand-ui ~65 site-uri ale autorului din același cod (cod identic peste tot, doar culorile + logo + structura homepage diferă per site).

## Caracteristici

- **Zero JavaScript** pe homepage (slider CSS-only cu scroll-snap)
- **Zero jQuery**, zero emoji script, zero oEmbed bloat
- Compatibil **LiteSpeed Cache** (nu duplicăm minify/critical CSS)
- Compatibil **SEOPress** (nu duplicăm meta tags, OG, schema, sitemap)
- HTML semantic curat (`<article>`, `<nav>`, `<aside>`, `<header>`, `<footer>`)
- Imagini cu `aspect-ratio` declarat → CLS zero
- Branding per-site prin `site-config.json` extern (nu în git)

## Structură

```
magpress/
├── style.css                  # header WP (numele temei, versiune)
├── theme.json                 # design tokens (culori, fonturi, spacing)
├── functions.php              # bootstrap + remove bloat + branding loader
├── assets/css/base.css        # CSS-ul minim al temei
├── templates/                 # FSE templates (HTML cu blocuri Gutenberg)
│   ├── index.html             # blog default
│   ├── front-page.html        # homepage (slider + 3 rânduri categorii)
│   ├── single.html            # articol + related + comments
│   ├── archive.html           # categorie/tag/autor
│   ├── page.html              # pagină statică
│   ├── search.html
│   └── 404.html
├── parts/
│   ├── header.html            # logo + nav
│   ├── footer.html            # 4 coloane
│   └── sidebar.html           # popular posts + categorii
└── patterns/
    ├── hero-slider.php        # slider CSS-pur
    └── category-row.php       # rând cu 6 articole dintr-o categorie
```

## Branding per site

Fiecare instalare WP are propriul fișier (NU în git):

```
/wp-content/themes/magpress-config/site-config.json
```

Conținut:

```json
{
  "colors": {
    "primary":    "#1a1a1a",
    "accent":     "#d4a017",
    "text":       "#1a1a1a",
    "background": "#ffffff",
    "border":     "#e5e5e5"
  },
  "font_family": "Georgia, serif"
}
```

CSS-ul folosește variabilele `--wp--preset--color--*` peste tot, iar `functions.php` injectează override-urile pe `wp_head`.

## Deploy

Update centralizat din git pe toate site-urile:

```
GitHub push → webhook → fiecare site face git pull în /wp-content/themes/magpress/
```

Configurarea webhook + endpoint deploy.php se face la nivel de site (separat de tema).

## Licență

GPL-2.0-or-later
