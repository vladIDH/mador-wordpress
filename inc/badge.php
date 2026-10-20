<?php
/**
 * The badge: `[mador-badge]`.
 *
 * An `<img>` inside an `<a>`, the same markup Mador offers to copy by
 * hand. The image is loaded from Mador on every view and never stored in
 * the media library: a saved copy would show an old measurement forever.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'mador-badge', 'mador_badge_shortcode' );

/**
 * `[mador-badge]` · `[mador-badge larghezza="180"]` ·
 * `[mador-badge codice="abcdefghjk" lingua="es"]`
 *
 * Without attributes the settings apply. The attributes are for a site
 * that shows more than one business, or another language.
 *
 * @param array|string $attributi Shortcode attributes; an empty string when there are none.
 */
function mador_badge_shortcode( $attributi ): string {
	$a = shortcode_atts(
		array(
			'codice'    => mador_codice(),
			'lingua'    => mador_lingua(),
			'larghezza' => MADOR_BADGE_L,
		),
		$attributi,
		'mador-badge'
	);

	$codice = mador_codice_pulito( (string) $a['codice'] );
	$lingua = mador_lingua_pulita( (string) $a['lingua'] );
	if ( '' === $codice ) {
		return mador_avviso_codice_mancante();
	}

	// Width and height are always written, so the badge does not shift the
	// layout while loading; the height follows the width in proportion.
	$larghezza = (int) $a['larghezza'];
	if ( $larghezza < 60 || $larghezza > 1000 ) {
		$larghezza = MADOR_BADGE_L;
	}
	$altezza = (int) round( $larghezza * MADOR_BADGE_H / MADOR_BADGE_L );

	$alt = sprintf( mador_testo( 'badge_alt', $lingua ), get_bloginfo( 'name' ) );

	return sprintf(
		'<a class="mador-badge" href="%1$s" target="_blank" rel="noopener" style="display:inline-block;line-height:0"><img src="%2$s" width="%3$d" height="%4$d" loading="lazy" decoding="async" alt="%5$s" style="max-width:100%%;height:auto" /></a>',
		esc_url( mador_url_certificato( $codice, $lingua ) ),
		esc_url( mador_url_badge( $codice ) ),
		$larghezza,
		$altezza,
		esc_attr( $alt )
	);
}

/**
 * What a shortcode prints when no report code is set.
 *
 * Visitors see nothing; administrators see why, in their admin language.
 */
function mador_avviso_codice_mancante(): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	return sprintf(
		'<em class="mador-avviso">%s</em>',
		esc_html__( 'Mador: enter your report code in Settings → Mador. Only administrators see this notice.', 'mador' )
	);
}
