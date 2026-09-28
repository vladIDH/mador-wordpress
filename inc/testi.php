<?php
/**
 * Texts visitors see on the site: five strings, three languages.
 *
 * These do not go through gettext on purpose. Admin strings follow the
 * WordPress language; these sit next to what the business publishes and
 * must match its language, which is the one chosen in Settings → Mador
 * (the certificate's language), not the admin's.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** Public strings: key, then language. */
function mador_testi(): array {
	return array(
		// `%s` is the site name, usually the business name.
		'badge_alt'          => array(
			'it' => '%s — misurato da Mador',
			'en' => '%s — measured by Mador',
			'es' => '%s — medido por Mador',
		),
		'certificato_titolo' => array(
			'it' => 'Certificato di misura',
			'en' => 'Measurement certificate',
			'es' => 'Certificado de medición',
		),
		'certificato_apri'   => array(
			'it' => 'Apri il certificato',
			'en' => 'Open the certificate',
			'es' => 'Abrir el certificado',
		),
		'faq_titolo'         => array(
			'it' => 'Domande frequenti',
			'en' => 'Frequently asked questions',
			'es' => 'Preguntas frecuentes',
		),
		// Same attribution Mador prints under the block copied by hand.
		'faq_fonte'          => array(
			'it' => 'Misurato e scritto con Mador',
			'en' => 'Measured and written with Mador',
			'es' => 'Medido y escrito con Mador',
		),
	);
}

/** A public string in the chosen language, falling back to Italian. */
function mador_testo( string $chiave, string $lingua ): string {
	$testi = mador_testi();
	if ( ! isset( $testi[ $chiave ] ) ) {
		return '';
	}
	return isset( $testi[ $chiave ][ $lingua ] ) ? $testi[ $chiave ][ $lingua ] : $testi[ $chiave ]['it'];
}
