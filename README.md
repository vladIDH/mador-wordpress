# Mador for WordPress

Connects a WordPress site to [Mador](https://mador.ai): the site verification
tag, the business's questions and answers with FAQ structured data, the
measurement badge and the certificate.

Mador measures how often AI assistants — ChatGPT, Gemini, Perplexity, Claude
and others — name a business when a customer asks, and writes the pages that
help them name it. This plugin brings that work onto the business's own site.

**Download:** [mador.ai/plugin](https://mador.ai/plugin) · License: GPLv2 or later

## What it does

| | How |
|---|---|
| Site verification | `<meta name="mador-verifica" content="{report code}">` in `wp_head`, on every page |
| Questions and answers | `[mador-faq]` → a `<dl>` plus `FAQPage` JSON-LD, from `GET /api/pubblicazioni/{code}.json`, cached for a day |
| Badge | `[mador-badge]` → `<a><img></a>` pointing to `/r/{code}/badge.svg` |
| Certificate | `[mador-certificato]` → `<iframe>` on `/r/{code}/certificato` |

What it does **not** do: no API keys or secrets, no JavaScript, no
stylesheets, no tracking, no dependencies (no Composer, no build step). The
only setting that identifies anything is the report code, which is already
public: it is part of the badge URL.

## Install

1. Download the zip from [mador.ai/plugin](https://mador.ai/plugin).
2. WordPress: **Plugins → Add New Plugin → Upload Plugin** → choose the zip →
   **Install Now** → **Activate**.

Requires WordPress 6.0+ and PHP 7.4+. Tested up to WordPress 7.1.

## Connect it to Mador

1. **Settings → Mador**: paste your report code — the ten characters at the
   end of your report address, `mador.ai/r/XXXXXXXXXX` (the whole address
   works too) — pick the language (Italian, English, Spanish) and save. The
   settings page shows your real badge: if it appears, the code is right.
2. In the Mador area, under "Connect your site", press **Check the meta tag**.
   The tag is already on every page.
3. Add a **Shortcode** block with `[mador-faq]` on the page that should carry
   your questions and answers. It fills in once your article is published on
   Mador, and updates on its own.
4. Optional: `[mador-badge]` (usually in the footer) and `[mador-certificato]`.

### Shortcode attributes

```
[mador-badge larghezza="180"]                 width in px, 60–1000
[mador-certificato altezza="1800"]            height in px, 300–5000
[mador-faq titolo="no"]                       without the section heading
[mador-faq codice="n7q4kx2mbd" lingua="en"]   another business or language
```

## Languages

- **Admin screens** follow the WordPress language, through gettext:
  `languages/mador.pot`, with Italian and Spanish included. English is the
  source language.
- **What visitors see** — the badge's `alt`, the certificate's title and link,
  the FAQ heading and attribution — follows the language chosen in the
  settings, because it sits next to content written in that language. Those
  strings are in [`inc/testi.php`](inc/testi.php).

After changing a translatable string, update the `.pot` and the `.po` files,
then compile:

```
node tools/po-to-mo.mjs
```

The script refuses to write the `.mo` files if a string in the code is missing
from a `.po` file, a translation is empty, or a file carries a string the code
no longer uses. `node tools/po-to-mo.mjs --check` only checks.

## Testing against another Mador instance

The plugin points to `https://mador.ai`. To point it elsewhere, in
`wp-config.php`:

```php
define( 'MADOR_ORIGINE', 'https://your-mador-instance.example' );
```

When switching between cases, clear the FAQ cache:
`delete_transient( 'mador_faq_' . $code )`.

## Uninstall

Deleting the plugin removes its option (`mador_opzioni`) and its cached
questions and answers (`mador_faq_*` transients), on every site of a network.

## Files

```
mador.php          plugin header, bootstrap
uninstall.php      cleanup
inc/opzioni.php    settings storage, URLs
inc/impostazioni.php  Settings → Mador
inc/verifica.php   meta tag
inc/faq.php        [mador-faq]
inc/badge.php      [mador-badge]
inc/certificato.php  [mador-certificato]
inc/testi.php      visitor-facing strings
languages/         .pot, it_IT, es_ES
tools/po-to-mo.mjs .po → .mo compiler and checker (not shipped in the zip)
```

## License

GPLv2 or later. See [LICENSE](LICENSE). © 2026 Khlumen.
