<?php
/**
 * Options: three fields (report code, language, credit link) and every
 * address derived from them.
 *
 * One option, `mador_opzioni`, holding an array: one read, one sanitizer,
 * one row to delete on uninstall. All Mador URLs are built here, in one
 * place.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** Option name. `uninstall.php` repeats this literal. */
const MADOR_OPZIONE = 'mador_opzioni';

/**
 * Languages of the Mador site, in display order. These are the languages
 * the certificate exists in, not WordPress locales.
 */
const MADOR_LINGUE = array( 'it', 'en', 'es' );

/**
 * Alphabet and length of a report code. No 0/O and no 1/l/I: a code is
 * often typed by hand, and the check catches an "l" typed for a "1".
 */
const MADOR_ALFABETO         = 'abcdefghjkmnpqrstuvwxyz23456789';
const MADOR_LUNGHEZZA_CODICE = 10;

/** Longest code accepted at all, so a pasted paragraph cannot become a key. */
const MADOR_CODICE_MAX = 32;

/**
 * Badge size in CSS pixels. Must match the SVG served by Mador: without
 * width and height in the tag the badge shifts the layout while loading.
 */
const MADOR_BADGE_L = 260;
const MADOR_BADGE_H = 64;

/**
 * The options, always with every key.
 *
 * `fonte` is the credit link under `[mador-faq]`: off unless the
 * administrator turns it on (WordPress.org guideline 10, no links on the
 * public site without asking).
 *
 * @return array{codice: string, lingua: string, fonte: bool}
 */
function mador_opzioni(): array {
	$salvate = get_option( MADOR_OPZIONE, array() );
	if ( ! is_array( $salvate ) ) {
		$salvate = array();
	}
	return array(
		'codice' => isset( $salvate['codice'] ) ? (string) $salvate['codice'] : '',
		'lingua' => isset( $salvate['lingua'] ) ? (string) $salvate['lingua'] : 'it',
		'fonte'  => ! empty( $salvate['fonte'] ),
	);
}

/** The saved report code, cleaned, or an empty string. */
function mador_codice(): string {
	$o = mador_opzioni();
	return mador_codice_pulito( $o['codice'] );
}

/** The saved language, always one of the three. */
function mador_lingua(): string {
	$o = mador_opzioni();
	return mador_lingua_pulita( $o['lingua'] );
}

/** Whether the administrator turned the credit link on. */
function mador_fonte(): bool {
	$o = mador_opzioni();
	return $o['fonte'];
}

/**
 * A report code as accepted: lowercase, alphabet characters only, at most
 * `MADOR_CODICE_MAX` long.
 *
 * It strips instead of rejecting, because the usual input is a whole
 * report address pasted in, or a code with a trailing space. Whether what
 * is left looks right is `mador_codice_valido()`'s call, which warns
 * without blocking.
 *
 * @param string $grezzo The code or report address as typed.
 */
function mador_codice_pulito( string $grezzo ): string {
	$basso = strtolower( trim( $grezzo ) );

	// A whole address pasted in: keep the segment after `/r/`.
	if ( preg_match( '#/r/([^/?\#]+)#', $basso, $pezzi ) ) {
		$basso = $pezzi[1];
	}

	$pulito = (string) preg_replace( '/[^' . MADOR_ALFABETO . ']/', '', $basso );
	return substr( $pulito, 0, MADOR_CODICE_MAX );
}

/**
 * Whether the code has the shape of a report code.
 *
 * @param string $codice A code already through `mador_codice_pulito()`.
 */
function mador_codice_valido( string $codice ): bool {
	return strlen( $codice ) === MADOR_LUNGHEZZA_CODICE;
}

/**
 * A language as accepted: one of the three, otherwise Italian.
 *
 * @param string $grezza The language as received.
 */
function mador_lingua_pulita( string $grezza ): string {
	$bassa = strtolower( trim( $grezza ) );
	return in_array( $bassa, MADOR_LINGUE, true ) ? $bassa : 'it';
}

/**
 * Sanitize callback for the Settings API.
 *
 * An unticked checkbox is not posted at all, so a missing `fonte` means off.
 *
 * @param mixed $grezze The posted `mador_opzioni` array.
 */
function mador_sanifica( $grezze ): array {
	$grezze = is_array( $grezze ) ? $grezze : array();
	return array(
		'codice' => mador_codice_pulito( isset( $grezze['codice'] ) ? sanitize_text_field( (string) $grezze['codice'] ) : '' ),
		'lingua' => mador_lingua_pulita( isset( $grezze['lingua'] ) ? sanitize_key( (string) $grezze['lingua'] ) : '' ),
		'fonte'  => isset( $grezze['fonte'] ) && '1' === sanitize_key( (string) $grezze['fonte'] ),
	);
}

/**
 * Language prefix in Mador URLs: Italian has none, `en` and `es` do.
 *
 * @param string $lingua One of `MADOR_LINGUE`.
 */
function mador_prefisso_lingua( string $lingua ): string {
	return 'it' === $lingua ? '' : '/' . $lingua;
}

/**
 * Public address of the certificate.
 *
 * @param string $codice The report code.
 * @param string $lingua One of `MADOR_LINGUE`.
 */
function mador_url_certificato( string $codice, string $lingua ): string {
	return MADOR_ORIGINE . mador_prefisso_lingua( $lingua ) . '/r/' . rawurlencode( $codice ) . '/certificato';
}

/**
 * Address of the badge image.
 *
 * The badge stays outside the language prefix: it is one address forever,
 * because it ends up in an `<img src>` on someone else's site.
 *
 * `?giorno=` is a cache buster, not a parameter the route reads: the badge
 * is cached for a day, in visitors' browsers too, and a URL that changes
 * once a day expires when the measurement can.
 *
 * @param string $codice The report code.
 */
function mador_url_badge( string $codice ): string {
	return MADOR_ORIGINE . '/r/' . rawurlencode( $codice ) . '/badge.svg?giorno=' . gmdate( 'Y-m-d' );
}
