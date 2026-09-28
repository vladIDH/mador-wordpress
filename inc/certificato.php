<?php
/**
 * The embedded certificate: `[mador-certificato]`.
 *
 * An `<iframe>` on the public page `/r/{code}/certificato`, plus a link
 * that opens it in full (for when the frame is blocked or skipped).
 *
 * An iframe and not a copy: the certificate's numbers must be today's, and
 * a copy stored in WordPress would age while the original changes.
 *
 * The height is a fixed attribute: resizing it automatically would need a
 * script on the site, which this plugin does not add.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** Default iframe height in pixels. */
const MADOR_CERTIFICATO_H = 1400;

add_shortcode( 'mador-certificato', 'mador_certificato_shortcode' );

/**
 * `[mador-certificato]` · `[mador-certificato altezza="1800"]` ·
 * `[mador-certificato codice="abcdefghjk" lingua="en"]`
 */
function mador_certificato_shortcode( $attributi ): string {
	$a = shortcode_atts(
		array(
			'codice'  => mador_codice(),
			'lingua'  => mador_lingua(),
			'altezza' => MADOR_CERTIFICATO_H,
		),
		$attributi,
		'mador-certificato'
	);

	$codice = mador_codice_pulito( (string) $a['codice'] );
	$lingua = mador_lingua_pulita( (string) $a['lingua'] );
	if ( '' === $codice ) {
		return mador_avviso_codice_mancante();
	}

	$altezza = (int) $a['altezza'];
	if ( $altezza < 300 || $altezza > 5000 ) {
		$altezza = MADOR_CERTIFICATO_H;
	}

	$url    = mador_url_certificato( $codice, $lingua );
	$titolo = mador_testo( 'certificato_titolo', $lingua );

	return sprintf(
		'<div class="mador-certificato">'
			. '<iframe src="%1$s" title="%2$s" width="100%%" height="%3$d" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" style="display:block;width:100%%;max-width:100%%;border:0;border-radius:12px;background:#0A0B0D"></iframe>'
			. '<p class="mador-certificato-link" style="margin-top:.75em"><a href="%1$s" target="_blank" rel="noopener">%4$s</a></p>'
			. '</div>',
		esc_url( $url ),
		esc_attr( $titolo ),
		$altezza,
		esc_html( mador_testo( 'certificato_apri', $lingua ) )
	);
}
