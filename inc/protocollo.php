<?php
/**
 * The signed exchange with Mador: pure functions, no WordPress calls, so
 * that `tests/run.php` can exercise them without a WordPress install.
 *
 * - Every request the site makes is signed with the site's own Ed25519
 *   secret key, generated here at connection time. Mador only ever
 *   receives the public half.
 * - Every answer from Mador is an envelope `{kid, firma, dati}`: `dati` is
 *   a JSON string and `firma` the Ed25519 signature of those exact bytes,
 *   made with Mador's key. The plugin checks the signature against the
 *   public key Mador gave at connection time BEFORE decoding anything.
 *
 * Sodium comes from PHP or, when the extension is missing, from the
 * sodium_compat copy that WordPress ships (wp-includes/sodium_compat).
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** How old (or how far ahead) a signed message may be, in seconds. */
const MADOR_FINESTRA = 600;

/** The path the plugin signs for its sync call: fixed, whatever the host. */
const MADOR_PERCORSO_SINCRONIZZA = '/api/wp/sincronizza';

/**
 * The string the site signs for a request: method, path, timestamp, nonce
 * and the SHA-256 of the body, one per line. Must match `daFirmare()` in
 * Mador's `lib/wp/firma.ts` byte for byte.
 *
 * @param string $metodo   HTTP method.
 * @param string $percorso Request path.
 * @param int    $istante  Unix timestamp.
 * @param string $nonce    One-time value.
 * @param string $corpo    Request body, exactly as sent.
 * @return string
 */
function mador_da_firmare( string $metodo, string $percorso, int $istante, string $nonce, string $corpo ): string {
	return implode( "\n", array( strtoupper( $metodo ), $percorso, (string) $istante, $nonce, hash( 'sha256', $corpo ) ) );
}

/** A fresh nonce: 24 random bytes in base64url. */
function mador_nonce(): string {
	return rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- random nonce bytes for transport, not code.
}

/**
 * The signature headers for a request, made with the site's secret key
 * (64 bytes, base64).
 *
 * @param string $sito               Site id given by Mador.
 * @param string $chiave_privata_b64 The site's secret key, base64.
 * @param string $corpo              Request body, exactly as sent.
 * @param int    $istante            Unix timestamp.
 * @param string $nonce              One-time value.
 * @return array<string,string>
 */
function mador_intestazioni_firmate( string $sito, string $chiave_privata_b64, string $corpo, int $istante, string $nonce ): array {
	$segreta = base64_decode( $chiave_privata_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Ed25519 secret key for transport, not code.
	if ( false === $segreta || 64 !== strlen( $segreta ) ) {
		return array();
	}
	$firma = sodium_crypto_sign_detached(
		mador_da_firmare( 'POST', MADOR_PERCORSO_SINCRONIZZA, $istante, $nonce, $corpo ),
		$segreta
	);
	return array(
		'Content-Type'    => 'application/json',
		'X-Mador-Sito'    => $sito,
		'X-Mador-Istante' => (string) $istante,
		'X-Mador-Nonce'   => $nonce,
		'X-Mador-Firma'   => base64_encode( $firma ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Ed25519 signature for transport, not code.
	);
}

/**
 * Opens an envelope from Mador.
 *
 * Returns the decoded `dati` only if ALL of these hold:
 * - the envelope has the three fields as strings;
 * - `kid` is the one received at connection time;
 * - the signature over the exact `dati` bytes verifies with Mador's key;
 * - `dati` names this site and is no more than ten minutes off.
 *
 * Anything else returns null and nothing in it is looked at.
 *
 * @param mixed  $busta            Decoded JSON body.
 * @param string $chiave_mador_b64 Mador's public key, base64, received at connection time.
 * @param string $kid              Key id received at connection time.
 * @param string $sito             This site's id.
 * @param int    $adesso           Current Unix timestamp.
 * @return array<string,mixed>|null
 */
function mador_apri_busta( $busta, string $chiave_mador_b64, string $kid, string $sito, int $adesso ) {
	if ( ! is_array( $busta ) || ! isset( $busta['kid'], $busta['firma'], $busta['dati'] ) ) {
		return null;
	}
	if ( ! is_string( $busta['kid'] ) || ! is_string( $busta['firma'] ) || ! is_string( $busta['dati'] ) ) {
		return null;
	}
	if ( ! hash_equals( $kid, $busta['kid'] ) ) {
		return null;
	}
	$pubblica = base64_decode( $chiave_mador_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Ed25519 public key for transport, not code.
	$firma    = base64_decode( $busta['firma'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Ed25519 signature for transport, not code.
	if ( false === $pubblica || 32 !== strlen( $pubblica ) || false === $firma || 64 !== strlen( $firma ) ) {
		return null;
	}
	try {
		if ( ! sodium_crypto_sign_verify_detached( $firma, $busta['dati'], $pubblica ) ) {
			return null;
		}
	} catch ( Exception $e ) {
		return null;
	}
	$dati = json_decode( $busta['dati'], true );
	if ( ! is_array( $dati ) || ! isset( $dati['sito'], $dati['istante'] ) ) {
		return null;
	}
	if ( $dati['sito'] !== $sito || ! is_int( $dati['istante'] ) || abs( $adesso - $dati['istante'] ) > MADOR_FINESTRA ) {
		return null;
	}
	return $dati;
}

/**
 * The fingerprint of a post body, to tell whether someone edited it.
 *
 * @param string $contenuto Post content.
 * @return string
 */
function mador_impronta( string $contenuto ): string {
	return hash( 'sha256', str_replace( "\r\n", "\n", $contenuto ) );
}

/**
 * What to do with one delivery, given the post as it is now. PURE.
 *
 * - `crea`: no post yet (or only one in the trash, which the site owner
 *   threw away: Mador only sends `crea` again after a new "yes").
 * - `aggiorna`: only if the post was not edited since Mador wrote it, or
 *   if the owner chose "use Mador's" (`forza`). Otherwise `modificata`:
 *   nothing is touched and Mador asks the owner.
 * - `ritira`: back to draft. Never deleted.
 *
 * @param array<string,mixed>      $consegna One delivery from Mador.
 * @param array<string,mixed>|null $post     `stato`, `contenuto`, `impronta` of the existing post, or null.
 * @return string One of `crea`, `aggiorna`, `modificata`, `ritira`, `salta`.
 */
function mador_decidi( array $consegna, $post ): string {
	$azione = isset( $consegna['azione'] ) ? $consegna['azione'] : '';
	if ( 'ritira' === $azione ) {
		return null === $post ? 'salta' : 'ritira';
	}
	if ( 'crea' !== $azione && 'aggiorna' !== $azione ) {
		return 'salta';
	}
	if ( null === $post || 'trash' === $post['stato'] ) {
		return 'crea';
	}
	$toccato = mador_impronta( (string) $post['contenuto'] ) !== (string) $post['impronta'];
	if ( $toccato && empty( $consegna['forza'] ) ) {
		return 'modificata';
	}
	return 'aggiorna';
}

/**
 * Structured data for a Mador page, ready for a `<script>`: Mador's graph
 * plus this page's URL and id. `JSON_HEX_TAG` turns `<` and `>` into
 * `<` / `>`, so no value can close the script tag.
 *
 * @param mixed  $schema Decoded `_mador_schema` meta.
 * @param string $url    The page's permalink.
 * @return string
 */
function mador_schema_pagina( $schema, string $url ): string {
	if ( ! is_array( $schema ) || ! isset( $schema['@graph'][0] ) || ! is_array( $schema['@graph'][0] ) ) {
		return '';
	}
	$schema['@graph'][0]['url'] = $url;
	$schema['@graph'][0]['@id'] = $url . '#pagina';
	$json                       = wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return false === $json ? '' : $json;
}
