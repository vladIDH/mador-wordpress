<?php
/**
 * The site verification tag: `<meta name="mador-verifica">`.
 *
 * In the Mador area the owner declares their site; Mador then fetches the
 * declared address and looks for this tag with the report code in it.
 *
 * The content is the report code, not a separate token: the code is
 * already public (it is in the badge URL). Verification works in one
 * direction only: whoever holds the account declares a domain, and Mador
 * checks that the domain exposes that account's code. Copying someone
 * else's code onto another site declares nothing.
 *
 * On every page, not only the home page: the declared address can be an
 * inner page.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** Name of the meta tag. Mador reads exactly this name. */
const MADOR_META_VERIFICA = 'mador-verifica';

/* Priority 1: near the top of the head, where a reader that fetches only
 * the first kilobytes of the page still finds it. */
add_action( 'wp_head', 'mador_meta_verifica', 1 );

function mador_meta_verifica(): void {
	$codice = mador_codice();
	if ( '' === $codice ) {
		return;
	}

	printf(
		'<meta name="%1$s" content="%2$s" />' . "\n",
		esc_attr( MADOR_META_VERIFICA ),
		esc_attr( $codice )
	);
}

/** The tag as text, to show it on the settings page (escaped there). */
function mador_meta_verifica_html( string $codice ): string {
	return sprintf( '<meta name="%1$s" content="%2$s" />', MADOR_META_VERIFICA, $codice );
}
