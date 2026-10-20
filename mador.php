<?php
/**
 * Plugin Name:       Mador
 * Plugin URI:        https://mador.ai/plugin
 * Description:       Connects your WordPress site to Mador: the site verification tag, your questions and answers with FAQ structured data, the measurement badge and the certificate.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Khlumen
 * Author URI:        https://mador.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mador
 * Domain Path:       /languages
 *
 * @package Mador
 */

/*
 * What this plugin does — four things, nothing else:
 *
 * 1. `<meta name="mador-verifica">` in the head of every page, so Mador can
 *    check that the site belongs to the account that declared it;
 * 2. `[mador-faq]`: the business's questions and answers, read from
 *    `/api/pubblicazioni/{code}.json`, with FAQPage structured data (the
 *    credit link under them is off unless turned on in the settings);
 * 3. `[mador-badge]`: the measurement badge (an `<img>` inside an `<a>`);
 * 4. `[mador-certificato]`: the measurement certificate (an `<iframe>`).
 *
 * No secret keys, no tokens, no JavaScript, no dependencies. The only
 * setting that identifies anything is the report code, which is already
 * public (it is part of the badge URL). The plugin reads public addresses
 * on mador.ai and sends nothing but the request itself.
 */

defined( 'ABSPATH' ) || exit;

/** Plugin version. Keep in sync with the header above and `readme.txt`. */
define( 'MADOR_VERSIONE', '1.1.0' );

/** Main plugin file, for `plugin_basename()`. */
define( 'MADOR_FILE', __FILE__ );

/**
 * Public origin of Mador, without a trailing slash.
 *
 * Can be overridden in `wp-config.php` to test the plugin against another
 * Mador instance; `wp-config.php` loads before plugins, so a constant
 * defined there wins over this line.
 */
defined( 'MADOR_ORIGINE' ) || define( 'MADOR_ORIGINE', 'https://mador.ai' );

require_once __DIR__ . '/inc/opzioni.php';
require_once __DIR__ . '/inc/testi.php';
require_once __DIR__ . '/inc/verifica.php';
require_once __DIR__ . '/inc/badge.php';
require_once __DIR__ . '/inc/certificato.php';
require_once __DIR__ . '/inc/faq.php';

if ( is_admin() ) {
	require_once __DIR__ . '/inc/impostazioni.php';
}

/*
 * Admin translations need no loading call: WordPress loads the `mador`
 * text domain on demand, from the language packs of WordPress.org and
 * from `languages/` (the `Domain Path` above).
 */

/* The "Settings" link in the plugin row. */
add_filter(
	'plugin_action_links_' . plugin_basename( MADOR_FILE ),
	static function ( $link ) {
		$link[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=mador' ) ),
			esc_html__( 'Settings', 'mador' )
		);
		return $link;
	}
);
