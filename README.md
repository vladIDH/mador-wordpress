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

No links on the public site unless the administrator places them: the badge
and the certificate appear only where their shortcodes are, and the
"Measured and written with Mador" credit link under `[mador-faq]` is off until
**Settings → Mador → Credit link** is ticked (WordPress.org guideline 10).

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
   Tick **Credit link** in the settings if you want the "Measured and written
   with Mador" link under it.
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
  the language packs of translate.wordpress.org. English is the source
  language. The `.pot` and the Italian and Spanish `.po` files in
  `languages/` are the source for those packs and are not shipped in the zip
  (since 1.1.1).
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

### translate.wordpress.org

Once the plugin is approved on WordPress.org (slug `mador`), its strings show
up on translate.wordpress.org, and WordPress installs Italian and Spanish as
language packs. To carry the existing
translations over instead of retyping them:

1. Wait until the project exists:
   `https://translate.wordpress.org/projects/wp-plugins/mador/`. It is created
   from the plugin's SVN (`Stable tag`), usually within a day of the first
   commit there.
2. Log in as `khlumen` and open the locale's **Stable (latest release)**
   sub-project:
   - Italian: `https://translate.wordpress.org/projects/wp-plugins/mador/stable/it/default/`
   - Spanish: `https://translate.wordpress.org/projects/wp-plugins/mador/stable/es/default/`
3. At the bottom of the string list, **Import Translations**: choose
   `languages/mador-it_IT.po` for Italian, `languages/mador-es_ES.po` for
   Spanish, format **Portable Object Message Catalog (.po/.pot)**, **Import**.
4. Without editor rights, the imported strings arrive as *waiting*
   suggestions, and a locale's editors approve them. To approve them yourself,
   ask to be Project Translation Editor (PTE) for `mador` in Italian and
   Spanish, with a post on
   [make.wordpress.org/polyglots](https://make.wordpress.org/polyglots/) (the
   request template is pinned there), then import again: as PTE the strings go
   in as *current*.
5. The language pack is built once a locale's Stable sub-project reaches 90%
   translated; with every string imported it is at 100%. The readme has its own
   sub-project (**Stable Readme**): it is translated there, not from the
   `.po` files.

The `.po` files in `languages/` stay the source: change a string here first,
check with `node tools/po-to-mo.mjs`, release, then import the updated `.po`
again.

## Testing against another Mador instance

The plugin points to `https://mador.ai`. To point it elsewhere, in
`wp-config.php`:

```php
define( 'MADOR_ORIGINE', 'https://your-mador-instance.example' );
```

When switching between cases, clear the FAQ cache:
`delete_transient( 'mador_faq_' . $code )`.

## Checks

Before a release, both must come back clean:

```
phpcs --standard=WordPress,PHPCompatibilityWP --runtime-set testVersion 7.4-   --runtime-set text_domain mador --runtime-set prefixes mador   --extensions=php --ignore=tools .
wp plugin check mador     # Plugin Check, on a site with the plugin installed
```

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
languages/         .pot, it_IT, es_ES (not shipped in the zip)
tools/po-to-mo.mjs .po → .mo compiler and checker (not shipped in the zip)
```

## License

GPLv2 or later. See [LICENSE](LICENSE). © 2026 Khlumen.
