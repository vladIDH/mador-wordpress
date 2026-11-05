<?php
/**
 * Plugin Name:       Mador
 * Plugin URI:        https://mador.ai/plugin
 * Description:       Connects your WordPress site to Mador: the site verification tag, your questions and answers with FAQ structured data, the measurement badge, the certificate and, with the Growth plan, the pages Mador writes for you.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Khlumen
 * Author URI:        https://mador.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mador
 *
 * @package Mador
 */

/*
 * What this plugin does, five things and nothing else:
 *
 * 1. `<meta name="mador-verifica">` in the head of every page, so Mador can
 *    check that the site belongs to the account that declared it;
 * 2. `[mador-faq]`: the business's questions and answers, read from
 *    `/api/pubblicazioni/{code}.json`, with FAQPage structured data (the
 *    credit link under them is off unless turned on in the settings);
 * 3. `[mador-badge]`: the measurement badge (an `<img>` inside an `<a>`);
 * 4. `[mador-certificato]`: the measurement certificate (an `<iframe>`);
 * 5. since 1.2.0, off until the owner connects the site with a one-time
 *    code: publishing the pages Mador writes as WordPress pages or posts
 *    (`inc/pubblica.php`).
 *
 * No JavaScript, no dependencies. Items 1-4 read public addresses on
 * mador.ai with the report code, which is already public. Item 5 is the
 * only one that sends anything: signed requests with the state of the
 * pages it wrote. Its key pair is generated on this site; the secret half
 * never leaves it, Mador only holds the public half, and everything Mador
 * sends back is checked against Mador's own signature before use.
 */

defined( 'ABSPATH' ) || exit;

/** Plugin version. Keep in sync with the header above and `readme.txt`. */
define( 'MADOR_VERSIONE', '1.2.0' );

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
require_once __DIR__ . '/inc/protocollo.php';
require_once __DIR__ . '/inc/pubblica.php';

if ( is_admin() ) {
	require_once __DIR__ . '/inc/impostazioni.php';
	require_once __DIR__ . '/inc/pubblica-admin.php';
}

/*
 * Admin translations need no loading call: WordPress loads the `mador`
 * text domain on demand, from the language packs of translate.wordpress.org.
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
