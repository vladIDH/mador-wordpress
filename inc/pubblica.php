<?php
/**
 * Publishing Mador pages on this site (Growth, version 1.2.0).
 *
 * Off until the site owner pastes a one-time connection code from their
 * Mador account into Settings › Mador. Then:
 *
 * - once an hour (and right away when Mador rings the doorbell) the plugin
 *   asks Mador, with a signed request, whether there is anything to
 *   publish, update or take down;
 * - what Mador sends is signed with Mador's key and checked before use;
 * - pages are created as regular WordPress pages or posts, owned by the
 *   user who connected the site, filtered through `wp_kses_post()`;
 * - a page the owner edited inside WordPress is never overwritten unless
 *   they choose "use Mador's" in their Mador account;
 * - taking a page down puts it back to draft. The plugin never deletes a
 *   post, and uninstalling it leaves every post where it is.
 *
 * The site's secret key stays in this site's options (not autoloaded).
 * Mador only has the public half.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** The option with the connection: site id, keys, user. Not autoloaded. */
const MADOR_OPZIONE_SITO = 'mador_sito';

/** The hourly event. */
const MADOR_EVENTO = 'mador_giro';

/** Meta keys on the posts the plugin writes. */
const MADOR_META_ID       = '_mador_id';
const MADOR_META_VERSIONE = '_mador_versione';
const MADOR_META_IMPRONTA = '_mador_impronta';
const MADOR_META_SCHEMA   = '_mador_schema';
const MADOR_META_ESITO    = '_mador_esito';
const MADOR_META_RITIRATA = '_mador_ritirata';

/** Ids of Mador pages deleted for good, still to be reported. */
const MADOR_OPZIONE_ASSENTI = 'mador_assenti';

/**
 * The connection, or null.
 *
 * @return array<string,mixed>|null
 */
function mador_sito() {
	$s = get_option( MADOR_OPZIONE_SITO, null );
	if ( ! is_array( $s ) || empty( $s['sito'] ) || empty( $s['privata'] ) || empty( $s['chiave_mador'] ) || empty( $s['kid'] ) ) {
		return null;
	}
	return $s;
}

/**
 * Connects the site with a one-time code from the Mador account.
 *
 * @param string $codice One-time code shown in the Mador account.
 * @return true|string True, or a short reason for the admin notice.
 */
function mador_collega( string $codice ) {
	$codice = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $codice ) );
	if ( 8 !== strlen( $codice ) ) {
		return 'codice';
	}
	$coppia   = sodium_crypto_sign_keypair();
	$segreta  = sodium_crypto_sign_secretkey( $coppia );
	$pubblica = sodium_crypto_sign_publickey( $coppia );

	$risposta = wp_remote_post(
		MADOR_ORIGINE . '/api/wp/collega',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'codice'   => $codice,
					'chiave'   => base64_encode( $pubblica ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Ed25519 public key for transport, not code.
					'home'     => home_url( '/' ),
					'versione' => MADOR_VERSIONE,
				)
			),
		)
	);
	if ( is_wp_error( $risposta ) ) {
		return 'rete';
	}
	$stato = (int) wp_remote_retrieve_response_code( $risposta );
	$dati  = json_decode( (string) wp_remote_retrieve_body( $risposta ), true );
	if ( 200 !== $stato || ! is_array( $dati ) || empty( $dati['sito'] ) || empty( $dati['chiave_mador'] ) || empty( $dati['kid'] ) ) {
		return is_array( $dati ) && isset( $dati['errore'] ) && is_string( $dati['errore'] ) ? sanitize_key( $dati['errore'] ) : 'rete';
	}
	update_option(
		MADOR_OPZIONE_SITO,
		array(
			'sito'         => sanitize_text_field( $dati['sito'] ),
			'privata'      => base64_encode( $segreta ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Ed25519 secret key stored as text, not code.
			'chiave_mador' => sanitize_text_field( $dati['chiave_mador'] ),
			'kid'          => sanitize_text_field( $dati['kid'] ),
			'utente'       => get_current_user_id(),
			'collegato_il' => time(),
			'ultimo_giro'  => 0,
			'ultimo_esito' => '',
		),
		false
	);
	// Only the native extension can wipe memory: the sodium_compat copy that
	// WordPress ships throws a SodiumException here, which used to end the
	// connection with a critical error right after it was saved.
	if ( extension_loaded( 'sodium' ) ) {
		sodium_memzero( $segreta );
	}
	mador_pianifica();
	mador_giro();
	return true;
}

/** Disconnects: forgets the keys and stops the hourly round. Posts stay. */
function mador_scollega(): void {
	delete_option( MADOR_OPZIONE_SITO );
	wp_clear_scheduled_hook( MADOR_EVENTO );
}

/** Schedules the hourly round if the site is connected. */
function mador_pianifica(): void {
	if ( mador_sito() && ! wp_next_scheduled( MADOR_EVENTO ) ) {
		wp_schedule_event( time() + 60, 'hourly', MADOR_EVENTO );
	}
}

add_action( MADOR_EVENTO, 'mador_giro' );
add_action( 'init', 'mador_pianifica' );

/**
 * The Mador page posts, any status.
 *
 * @return WP_Post[]
 */
function mador_post_di_mador(): array {
	$tutti  = array();
	$pagina = 1;
	do {
		$blocco = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'any,trash',
				'meta_key'       => MADOR_META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'posts_per_page' => 100,
				'paged'          => $pagina,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$quanti = count( $blocco );
		$tutti  = array_merge( $tutti, $blocco );
		++$pagina;
	} while ( 100 === $quanti && $pagina <= 50 );
	return $tutti;
}

/**
 * The post carrying a Mador page id, or null.
 *
 * @param string $id Mador page id.
 * @return WP_Post|null
 */
function mador_post_per_id( string $id ) {
	$trovati = get_posts(
		array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'any,trash',
			'meta_key'       => MADOR_META_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	return $trovati ? $trovati[0] : null;
}

/**
 * What the plugin tells Mador about its pages.
 *
 * @return array<int,array<string,mixed>>
 */
function mador_stati(): array {
	$stati = array();
	foreach ( mador_post_di_mador() as $post ) {
		$stati[] = array(
			'id'         => (string) get_post_meta( $post->ID, MADOR_META_ID, true ),
			'versione'   => (int) get_post_meta( $post->ID, MADOR_META_VERSIONE, true ),
			'esito'      => (string) get_post_meta( $post->ID, MADOR_META_ESITO, true ) ? (string) get_post_meta( $post->ID, MADOR_META_ESITO, true ) : 'nessuno',
			'post_id'    => (int) $post->ID,
			'stato_post' => (string) $post->post_status,
			'permalink'  => 'publish' === $post->post_status ? (string) get_permalink( $post ) : null,
			'modificata' => mador_impronta( (string) $post->post_content ) !== (string) get_post_meta( $post->ID, MADOR_META_IMPRONTA, true ),
		);
	}
	$assenti = get_option( MADOR_OPZIONE_ASSENTI, array() );
	foreach ( is_array( $assenti ) ? $assenti : array() as $id ) {
		$stati[] = array(
			'id'         => (string) $id,
			'versione'   => 0,
			'esito'      => 'nessuno',
			'post_id'    => null,
			'stato_post' => 'assente',
			'permalink'  => null,
			'modificata' => false,
		);
	}
	return $stati;
}

/* A Mador page deleted for good: remember to tell Mador, so it stops pointing Google here. */
add_action(
	'before_delete_post',
	static function ( $post_id ) {
		$id = (string) get_post_meta( (int) $post_id, MADOR_META_ID, true );
		if ( '' === $id ) {
			return;
		}
		$assenti   = get_option( MADOR_OPZIONE_ASSENTI, array() );
		$assenti   = is_array( $assenti ) ? $assenti : array();
		$assenti[] = $id;
		update_option( MADOR_OPZIONE_ASSENTI, array_values( array_unique( $assenti ) ), false );
	}
);

/**
 * One round: report, receive, apply, report again.
 *
 * @return string `ok`, or a short reason.
 */
function mador_giro(): string {
	$sito = mador_sito();
	if ( ! $sito ) {
		return 'scollegato';
	}
	// One round at a time: the hourly event and the doorbell can overlap.
	if ( get_transient( 'mador_giro_in_corso' ) ) {
		return 'in-corso';
	}
	set_transient( 'mador_giro_in_corso', 1, 2 * MINUTE_IN_SECONDS );

	$esito = 'ok';
	for ( $passo = 0; $passo < 2; $passo++ ) {
		$assenti_inviati = get_option( MADOR_OPZIONE_ASSENTI, array() );
		$dati            = mador_chiama( $sito, array( 'stati' => mador_stati() ) );
		if ( ! is_array( $dati ) ) {
			$esito = is_string( $dati ) ? $dati : 'rete';
			break;
		}
		// Reported once, the deleted ids are Mador's to remember.
		if ( is_array( $assenti_inviati ) && $assenti_inviati ) {
			update_option( MADOR_OPZIONE_ASSENTI, array_values( array_diff( (array) get_option( MADOR_OPZIONE_ASSENTI, array() ), $assenti_inviati ) ), false );
		}
		$consegne = isset( $dati['consegne'] ) && is_array( $dati['consegne'] ) ? $dati['consegne'] : array();
		if ( ! $consegne ) {
			break;
		}
		foreach ( array_slice( $consegne, 0, 20 ) as $consegna ) {
			if ( is_array( $consegna ) ) {
				mador_applica( $consegna, (int) $sito['utente'] );
			}
		}
	}

	// "Last check with Mador" moves only on an answer that passed the
	// signature check; a refused or missing answer keeps the previous date,
	// and `ultimo_esito` carries the reason for the admin notice.
	if ( 'ok' === $esito ) {
		$sito['ultimo_giro'] = time();
	}
	$sito['ultimo_esito'] = $esito;
	update_option( MADOR_OPZIONE_SITO, $sito, false );
	delete_transient( 'mador_giro_in_corso' );
	return $esito;
}

/**
 * The signed call to Mador. Returns the checked `dati`, or a reason.
 *
 * @param array<string,mixed> $sito  The connection.
 * @param array<string,mixed> $corpo The body.
 * @return array<string,mixed>|string
 */
function mador_chiama( array $sito, array $corpo ) {
	$testo        = (string) wp_json_encode( $corpo );
	$intestazioni = mador_intestazioni_firmate( (string) $sito['sito'], (string) $sito['privata'], $testo, time(), mador_nonce() );
	if ( ! $intestazioni ) {
		return 'chiave';
	}
	$r = wp_remote_post(
		MADOR_ORIGINE . MADOR_PERCORSO_SINCRONIZZA,
		array(
			'timeout' => 20,
			'headers' => $intestazioni,
			'body'    => $testo,
		)
	);
	if ( is_wp_error( $r ) ) {
		return 'rete';
	}
	$stato = (int) wp_remote_retrieve_response_code( $r );
	if ( 401 === $stato ) {
		return 'non-autorizzato';
	}
	if ( 200 !== $stato ) {
		return 'rete';
	}
	$dati = mador_apri_busta(
		json_decode( (string) wp_remote_retrieve_body( $r ), true ),
		(string) $sito['chiave_mador'],
		(string) $sito['kid'],
		(string) $sito['sito'],
		time()
	);
	return null === $dati ? 'firma' : $dati;
}

/**
 * Applies one delivery. The result is written on the post
 * (`_mador_esito`) and reported on the next call.
 *
 * @param array<string,mixed> $c      The delivery.
 * @param int                 $utente The user who connected the site.
 */
function mador_applica( array $c, int $utente ): void {
	$id = isset( $c['id'] ) && is_string( $c['id'] ) && preg_match( '/^[0-9a-f-]{36}$/', $c['id'] ) ? $c['id'] : '';
	if ( '' === $id ) {
		return;
	}
	$versione = isset( $c['versione'] ) ? (int) $c['versione'] : 0;
	$post     = mador_post_per_id( $id );
	$attuale  = $post ? array(
		'stato'     => $post->post_status,
		'contenuto' => $post->post_content,
		'impronta'  => (string) get_post_meta( $post->ID, MADOR_META_IMPRONTA, true ),
	) : null;

	$cosa = mador_decidi( $c, $attuale );

	if ( 'salta' === $cosa ) {
		return;
	}
	if ( 'modificata' === $cosa ) {
		update_post_meta( $post->ID, MADOR_META_VERSIONE, $versione );
		update_post_meta( $post->ID, MADOR_META_ESITO, 'modificata' );
		return;
	}
	if ( 'ritira' === $cosa ) {
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			)
		);
		update_post_meta( $post->ID, MADOR_META_RITIRATA, 1 );
		update_post_meta( $post->ID, MADOR_META_VERSIONE, $versione );
		update_post_meta( $post->ID, MADOR_META_ESITO, 'fatto' );
		return;
	}

	$tipo      = isset( $c['tipo_post'] ) && 'post' === $c['tipo_post'] ? 'post' : 'page';
	$stato     = isset( $c['stato_post'] ) && 'draft' === $c['stato_post'] ? 'draft' : 'publish';
	$titolo    = isset( $c['titolo'] ) && is_string( $c['titolo'] ) ? sanitize_text_field( $c['titolo'] ) : '';
	$contenuto = isset( $c['html'] ) && is_string( $c['html'] ) ? wp_kses_post( $c['html'] ) : '';
	if ( '' === $titolo || '' === $contenuto ) {
		return;
	}

	if ( 'crea' === $cosa ) {
		$nuovo = wp_insert_post(
			array(
				'post_type'    => $tipo,
				'post_status'  => $stato,
				'post_title'   => $titolo,
				'post_content' => $contenuto,
				'post_author'  => $utente,
			),
			true
		);
		if ( is_wp_error( $nuovo ) ) {
			return;
		}
		$post_id = (int) $nuovo;
		update_post_meta( $post_id, MADOR_META_ID, $id );
	} else {
		$dati = array(
			'ID'           => $post->ID,
			'post_title'   => $titolo,
			'post_content' => $contenuto,
		);
		// Taken down earlier and now sent again: back to what the owner chose.
		if ( get_post_meta( $post->ID, MADOR_META_RITIRATA, true ) ) {
			$dati['post_status'] = $stato;
			delete_post_meta( $post->ID, MADOR_META_RITIRATA );
		}
		$fatto = wp_update_post( $dati, true );
		if ( is_wp_error( $fatto ) ) {
			return;
		}
		$post_id = (int) $post->ID;
	}

	// The fingerprint of what WordPress actually saved, after its filters.
	update_post_meta( $post_id, MADOR_META_IMPRONTA, mador_impronta( (string) get_post_field( 'post_content', $post_id ) ) );
	update_post_meta( $post_id, MADOR_META_VERSIONE, $versione );
	update_post_meta( $post_id, MADOR_META_ESITO, 'fatto' );
	if ( isset( $c['schema'] ) && is_array( $c['schema'] ) ) {
		update_post_meta( $post_id, MADOR_META_SCHEMA, wp_slash( (string) wp_json_encode( $c['schema'] ) ) );
	}
}

/*
 * In the head of a Mador page: the mark Mador looks for before it points
 * Google here, and the structured data. Nothing on any other page.
 */
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( array( 'page', 'post' ) ) ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		$id      = (string) get_post_meta( $post_id, MADOR_META_ID, true );
		if ( '' === $id ) {
			return;
		}
		printf( '<meta name="mador-pagina" content="%s" />' . "\n", esc_attr( $id ) );
		$json = mador_schema_pagina( json_decode( (string) get_post_meta( $post_id, MADOR_META_SCHEMA, true ), true ), (string) get_permalink( $post_id ) );
		if ( '' !== $json ) {
			// Encoded with JSON_HEX_TAG in mador_schema_pagina(): it cannot close the tag.
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	5
);

/*
 * The doorbell: Mador says "there is something for you". The request
 * carries no content and is checked like any envelope; a valid one only
 * brings the next round forward.
 */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'mador/v1',
			'/campanello',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Authenticated by Mador's signature below.
				'callback'            => static function ( WP_REST_Request $richiesta ) {
					$sito = mador_sito();
					if ( ! $sito ) {
						return new WP_REST_Response( null, 404 );
					}
					$dati = mador_apri_busta( $richiesta->get_json_params(), (string) $sito['chiave_mador'], (string) $sito['kid'], (string) $sito['sito'], time() );
					if ( null === $dati || ! isset( $dati['tipo'] ) || 'campanello' !== $dati['tipo'] ) {
						return new WP_REST_Response( null, 401 );
					}
					if ( ! get_transient( 'mador_campanello' ) ) {
						set_transient( 'mador_campanello', 1, 30 );
						wp_schedule_single_event( time(), 'mador_giro_subito' );
					}
					return new WP_REST_Response( null, 202 );
				},
			)
		);
	}
);

add_action( 'mador_giro_subito', 'mador_giro' );
