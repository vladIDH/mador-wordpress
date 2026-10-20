<?php
/**
 * Questions and answers: `[mador-faq]`.
 *
 * Reads `/api/pubblicazioni/{code}.json` from Mador and prints the
 * business's questions and answers, with FAQPage structured data. A link
 * to the Mador page they come from follows only if the administrator turns
 * it on in the settings: off by default.
 *
 * This is the only part of the plugin that calls Mador from the server: a
 * public, read-only GET, with no key and nothing sent but the request.
 *
 * The HTML is built HERE, from data: the endpoint returns strings, and
 * every string is escaped before it reaches the page. No ready-made markup
 * from a remote server is printed.
 *
 * If the endpoint does not answer, nothing is printed: visitors see no
 * error, administrators see a notice saying why.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** How long a good answer is kept: a day, like the badge. */
const MADOR_FAQ_CACHE = DAY_IN_SECONDS;

/**
 * How long a failure is kept before trying again. Failures are cached too,
 * or a page with the shortcode and nothing published would call Mador on
 * every visit; five minutes, so a first publication shows up quickly.
 */
const MADOR_FAQ_ATTESA = 5 * MINUTE_IN_SECONDS;

/** Request timeout in seconds. Short: a page is rendering. */
const MADOR_FAQ_TIMEOUT = 5;

add_shortcode( 'mador-faq', 'mador_faq_shortcode' );

/**
 * `[mador-faq]` · `[mador-faq codice="abcdefghjk" lingua="en"]` ·
 * `[mador-faq titolo="no"]`
 *
 * @param array|string $attributi Shortcode attributes; an empty string when there are none.
 */
function mador_faq_shortcode( $attributi ): string {
	$a = shortcode_atts(
		array(
			'codice' => mador_codice(),
			'lingua' => mador_lingua(),
			'titolo' => 'si',
		),
		$attributi,
		'mador-faq'
	);

	$codice = mador_codice_pulito( (string) $a['codice'] );
	$lingua = mador_lingua_pulita( (string) $a['lingua'] );
	if ( '' === $codice ) {
		return mador_avviso_codice_mancante();
	}

	$dati = mador_faq_dati( $codice );
	if ( null === $dati ) {
		return mador_faq_avviso_vuoto();
	}

	// A definition list: each answer belongs to its question, and no
	// headings are added to someone else's document outline.
	$righe = '';
	foreach ( $dati['coppie'] as $coppia ) {
		$righe .= sprintf(
			'<dt>%s</dt><dd>%s</dd>',
			esc_html( $coppia['domanda'] ),
			esc_html( $coppia['risposta'] )
		);
	}

	// No styles: the block inherits the theme. The classes are there for
	// whoever wants to add their own.
	$titolo = 'no' === strtolower( trim( (string) $a['titolo'] ) )
		? ''
		: sprintf( '<h2>%s</h2>', esc_html( mador_testo( 'faq_titolo', $lingua ) ) );

	// The credit link, only when turned on in the settings. `nofollow`: a
	// link from a customer to their supplier.
	$link = mador_fonte()
		? sprintf(
			'<p class="mador-faq-fonte"><a href="%s" rel="nofollow noopener" target="_blank">%s</a></p>',
			esc_url( $dati['url'] ),
			esc_html( mador_testo( 'faq_fonte', $lingua ) )
		)
		: '';

	return sprintf(
		'<section class="mador-faq">%s<dl>%s</dl>%s</section>%s',
		$titolo,
		$righe,
		$link,
		mador_faq_schema( $dati )
	);
}

/**
 * The FAQPage JSON-LD, after the visible content.
 *
 * `JSON_HEX_TAG` and `JSON_HEX_AMP` encode `<` and `&`, so an answer that
 * contains `</script>` cannot close the tag early.
 *
 * @param array $dati What `mador_faq_dati()` returned.
 */
function mador_faq_schema( array $dati ): string {
	$domande = array();
	foreach ( $dati['coppie'] as $coppia ) {
		$domande[] = array(
			'@type'          => 'Question',
			'name'           => $coppia['domanda'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $coppia['risposta'],
			),
		);
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $domande,
	);

	return sprintf(
		'<script type="application/ld+json">%s</script>',
		wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
	);
}

/**
 * The data, from the cache or the network; `null` when there is none.
 *
 * Cached in a transient keyed by code (a site may show more than one
 * business). A failure is cached as the empty string, because
 * `get_transient()` returns `false` both for "missing" and for `false`.
 *
 * @param string $codice The report code.
 * @return array|null
 */
function mador_faq_dati( string $codice ) {
	$chiave   = 'mador_faq_' . $codice;
	$in_cache = get_transient( $chiave );

	if ( '' === $in_cache ) {
		return null;
	}
	if ( is_array( $in_cache ) ) {
		return $in_cache;
	}

	$dati = mador_faq_scarica( $codice );
	if ( null === $dati ) {
		set_transient( $chiave, '', MADOR_FAQ_ATTESA );
		return null;
	}

	set_transient( $chiave, $dati, MADOR_FAQ_CACHE );
	return $dati;
}

/**
 * The request itself. `null` on anything that is not a good answer.
 *
 * The shape is validated, not just the status code: a proxy or a hosting
 * error page can answer 200 with HTML in it. The link must point to Mador.
 *
 * @param string $codice The report code.
 * @return array|null
 */
function mador_faq_scarica( string $codice ) {
	$risposta = wp_remote_get(
		MADOR_ORIGINE . '/api/pubblicazioni/' . rawurlencode( $codice ) . '.json',
		array(
			'timeout'    => MADOR_FAQ_TIMEOUT,
			'user-agent' => 'mador-wp/' . MADOR_VERSIONE,
		)
	);

	if ( is_wp_error( $risposta ) || 200 !== (int) wp_remote_retrieve_response_code( $risposta ) ) {
		return null;
	}

	$corpo = json_decode( wp_remote_retrieve_body( $risposta ), true );
	if ( ! is_array( $corpo ) || empty( $corpo['url'] ) || ! is_string( $corpo['url'] ) || empty( $corpo['coppie'] ) || ! is_array( $corpo['coppie'] ) ) {
		return null;
	}
	if ( ! mador_url_di_mador( $corpo['url'] ) ) {
		return null;
	}

	$coppie = array();
	foreach ( $corpo['coppie'] as $coppia ) {
		if ( ! is_array( $coppia ) || empty( $coppia['domanda'] ) || empty( $coppia['risposta'] )
			|| ! is_string( $coppia['domanda'] ) || ! is_string( $coppia['risposta'] ) ) {
			continue;
		}
		$coppie[] = array(
			'domanda'  => $coppia['domanda'],
			'risposta' => $coppia['risposta'],
		);
	}
	if ( ! $coppie ) {
		return null;
	}

	return array(
		'url'    => $corpo['url'],
		'coppie' => $coppie,
	);
}

/**
 * Whether a URL is an http(s) address on Mador: the configured origin, or
 * mador.ai itself.
 *
 * @param string $url The address to check.
 */
function mador_url_di_mador( string $url ): bool {
	$schema = wp_parse_url( $url, PHP_URL_SCHEME );
	$host   = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! in_array( $schema, array( 'http', 'https' ), true ) || ! is_string( $host ) ) {
		return false;
	}
	$host = strtolower( $host );
	return in_array( $host, array( strtolower( (string) wp_parse_url( MADOR_ORIGINE, PHP_URL_HOST ) ), 'mador.ai' ), true );
}

/**
 * What the shortcode prints when there is nothing to show yet: nothing for
 * visitors, the reason for administrators.
 */
function mador_faq_avviso_vuoto(): string {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	return sprintf(
		'<em class="mador-avviso">%s</em>',
		esc_html__( 'Mador: there are no questions and answers to show yet. They appear once the article is published on Mador. Only administrators see this notice.', 'mador' )
	);
}
