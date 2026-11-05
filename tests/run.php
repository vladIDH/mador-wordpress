<?php
/**
 * Tests for the publishing code (1.2.0). Plain PHP with sodium:
 *
 *     php -d extension=sodium tests/run.php
 *
 * (Inside WordPress sodium is always there, from PHP or sodium_compat.)
 * Exits 1 on the first failure count above zero.
 *
 * @package Mador
 */

require __DIR__ . '/finto-wp.php';
require __DIR__ . '/../inc/protocollo.php';
require __DIR__ . '/../inc/pubblica.php';

$fallite = 0;
$passate = 0;
function prova( string $nome, callable $f ) {
	global $fallite, $passate;
	finto_reset();
	try {
		$f();
		$passate++;
		echo "ok   $nome\n";
	} catch ( Throwable $e ) {
		$fallite++;
		echo "FAIL $nome: " . $e->getMessage() . "\n";
	}
}
function vero( $c, string $msg = '' ) {
	if ( ! $c ) {
		throw new Exception( $msg ? $msg : 'atteso vero' );
	}
}
function uguale( $atteso, $vero, string $msg = '' ) {
	if ( $atteso !== $vero ) {
		throw new Exception( ( $msg ? "$msg: " : '' ) . 'atteso ' . var_export( $atteso, true ) . ', trovato ' . var_export( $vero, true ) );
	}
}

/*
 * An envelope made by Mador's Node code (lib/wp/firma.ts, imbusta()) from
 * the seed 32 x 0x07. If PHP and Node disagreed on what is signed, the
 * first test would fail.
 */
const NODE_PUBBLICA = '6kpsY+KcUgq+9VB7Ey7F+ZVHdq6+vnuSQh7qaRRG0iw=';
const NODE_KID      = 'fe812c12f3ab4ce6';
const NODE_SITO     = '11111111-2222-3333-4444-555555555555';
$NODE_BUSTA         = array(
	'kid'   => 'fe812c12f3ab4ce6',
	'firma' => 'wgVasrc2R68nO90SV2m34cqOL2v/Vf5HCFND3RGXmxoI1PnXOwrX+d5Dk5gxeYI2NZ/1I5aleyDcNNW5y4mqBA==',
	'dati'  => '{"sito":"11111111-2222-3333-4444-555555555555","istante":1800000000,"consegne":[{"id":"aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee","versione":1,"azione":"crea","forza":false,"tipo_post":"page","stato_post":"publish","titolo":"Dove? <b>","html":"<p>Ciao</p>"}]}',
);

prova(
	'una busta firmata da Mador (Node) si apre',
	function () use ( $NODE_BUSTA ) {
		$d = mador_apri_busta( $NODE_BUSTA, NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 );
		vero( is_array( $d ) );
		uguale( 'crea', $d['consegne'][0]['azione'] );
	}
);

prova(
	'contenuto con firma sbagliata: rifiutato',
	function () use ( $NODE_BUSTA ) {
		$falsa         = $NODE_BUSTA;
		$falsa['dati'] = str_replace( '<p>Ciao</p>', '<p>Compra qui</p>', $falsa['dati'] );
		uguale( null, mador_apri_busta( $falsa, NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 ), 'dati cambiati' );

		$altra          = sodium_crypto_sign_keypair();
		$falsa          = $NODE_BUSTA;
		$falsa['firma'] = base64_encode( sodium_crypto_sign_detached( $falsa['dati'], sodium_crypto_sign_secretkey( $altra ) ) );
		uguale( null, mador_apri_busta( $falsa, NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 ), 'firmata da un altro' );

		uguale( null, mador_apri_busta( $NODE_BUSTA, NODE_PUBBLICA, 'altrokid00000000', NODE_SITO, 1800000000 ), 'kid diverso' );
		uguale( null, mador_apri_busta( $NODE_BUSTA, NODE_PUBBLICA, NODE_KID, 'altro-sito', 1800000000 ), 'per un altro sito' );
		uguale( null, mador_apri_busta( $NODE_BUSTA, NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 + 601 ), 'vecchia' );
		uguale( null, mador_apri_busta( 'non una busta', NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 ), 'forma' );
	}
);

prova(
	'una busta rifiutata non crea niente: il giro si ferma su "firma"',
	function () use ( $NODE_BUSTA ) {
		$falsa         = $NODE_BUSTA;
		$falsa['dati'] = str_replace( 'Ciao', 'Truffa', $falsa['dati'] );
		$d             = mador_apri_busta( $falsa, NODE_PUBBLICA, NODE_KID, NODE_SITO, 1800000000 );
		if ( null !== $d ) {
			foreach ( $d['consegne'] as $c ) {
				mador_applica( $c, 1 );
			}
		}
		uguale( 0, count( $GLOBALS['finto']['post'] ) );
	}
);

$crea = array(
	'id'         => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
	'versione'   => 1,
	'azione'     => 'crea',
	'forza'      => false,
	'tipo_post'  => 'page',
	'stato_post' => 'publish',
	'titolo'     => 'Dove mangiare pesce?',
	'html'       => '<p>Pesce del giorno.</p><script>alert(1)</script><p onclick="x()">Ciao</p>',
	'schema'     => array(
		'@context' => 'https://schema.org',
		'@graph'   => array( array( '@type' => 'WebPage', 'name' => '</script><script>alert(1)</script>' ) ),
	),
);

prova(
	'crea: pagina pubblicata, ripulita, con id, versione, impronta e schema',
	function () use ( $crea ) {
		mador_applica( $crea, 7 );
		uguale( 1, count( $GLOBALS['finto']['post'] ) );
		$p = $GLOBALS['finto']['post'][1];
		uguale( 'publish', $p->post_status );
		uguale( 7, $p->post_author );
		vero( false === strpos( $p->post_content, '<script' ), 'script tolto' );
		vero( false === strpos( $p->post_content, 'onclick' ), 'handler tolto' );
		uguale( 'fatto', get_post_meta( 1, '_mador_esito' ) );
		uguale( mador_impronta( $p->post_content ), get_post_meta( 1, '_mador_impronta' ) );
	}
);

prova(
	'lo schema stampato non chiude lo script',
	function () use ( $crea ) {
		$json = mador_schema_pagina( $crea['schema'], 'https://sito.test/dove/' );
		vero( false === strpos( $json, '</script' ), 'nessun </script>' );
		$d = json_decode( $json, true );
		uguale( 'https://sito.test/dove/', $d['@graph'][0]['url'] );
		uguale( '</script><script>alert(1)</script>', $d['@graph'][0]['name'] );
	}
);

prova(
	'pagina modificata dal cliente: non sovrascritta, esito "modificata"',
	function () use ( $crea ) {
		mador_applica( $crea, 1 );
		$GLOBALS['finto']['post'][1]->post_content .= '<p>Aggiunto dal cliente</p>';
		$prima = $GLOBALS['finto']['post'][1]->post_content;

		$aggiorna             = $crea;
		$aggiorna['azione']   = 'aggiorna';
		$aggiorna['versione'] = 2;
		$aggiorna['html']     = '<p>Testo nuovo di Mador</p>';
		mador_applica( $aggiorna, 1 );

		uguale( $prima, $GLOBALS['finto']['post'][1]->post_content, 'contenuto intatto' );
		uguale( 'modificata', get_post_meta( 1, '_mador_esito' ) );
		uguale( 2, get_post_meta( 1, '_mador_versione' ) );
		$stati = mador_stati();
		uguale( true, $stati[0]['modificata'] );
		uguale( 'modificata', $stati[0]['esito'] );
	}
);

prova(
	'con «Metti quella di Mador» (forza) si sovrascrive',
	function () use ( $crea ) {
		mador_applica( $crea, 1 );
		$GLOBALS['finto']['post'][1]->post_content .= '<p>Aggiunto dal cliente</p>';
		$forza             = $crea;
		$forza['azione']   = 'aggiorna';
		$forza['versione'] = 3;
		$forza['forza']    = true;
		$forza['html']     = '<p>Testo nuovo di Mador</p>';
		mador_applica( $forza, 1 );
		uguale( '<p>Testo nuovo di Mador</p>', $GLOBALS['finto']['post'][1]->post_content );
		uguale( 'fatto', get_post_meta( 1, '_mador_esito' ) );
	}
);

prova(
	'aggiorna su una pagina non toccata: si aggiorna senza forza',
	function () use ( $crea ) {
		mador_applica( $crea, 1 );
		$a             = $crea;
		$a['azione']   = 'aggiorna';
		$a['versione'] = 2;
		$a['html']     = '<p>Seconda versione</p>';
		mador_applica( $a, 1 );
		uguale( '<p>Seconda versione</p>', $GLOBALS['finto']['post'][1]->post_content );
	}
);

prova(
	'ritira: la pagina torna in bozza e non sparisce',
	function () use ( $crea ) {
		mador_applica( $crea, 1 );
		mador_applica(
			array(
				'id'       => $crea['id'],
				'versione' => 2,
				'azione'   => 'ritira',
			),
			1
		);
		uguale( 1, count( $GLOBALS['finto']['post'] ) );
		uguale( 'draft', $GLOBALS['finto']['post'][1]->post_status );
		uguale( 'fatto', get_post_meta( 1, '_mador_esito' ) );
		$stati = mador_stati();
		uguale( 'draft', $stati[0]['stato_post'] );
		uguale( null, $stati[0]['permalink'] );
	}
);

prova(
	'rimessa dopo un ritiro: torna come l\'aveva scelta il cliente',
	function () use ( $crea ) {
		mador_applica( $crea, 1 );
		mador_applica(
			array(
				'id'       => $crea['id'],
				'versione' => 2,
				'azione'   => 'ritira',
			),
			1
		);
		$a             = $crea;
		$a['azione']   = 'aggiorna';
		$a['versione'] = 3;
		mador_applica( $a, 1 );
		uguale( 'publish', $GLOBALS['finto']['post'][1]->post_status );
	}
);

prova(
	'in bozza se il cliente ha scelto le bozze',
	function () use ( $crea ) {
		$b               = $crea;
		$b['stato_post'] = 'draft';
		mador_applica( $b, 1 );
		uguale( 'draft', $GLOBALS['finto']['post'][1]->post_status );
	}
);

prova(
	'una consegna con id storto non tocca niente',
	function () use ( $crea ) {
		$x       = $crea;
		$x['id'] = '../../etc';
		mador_applica( $x, 1 );
		uguale( 0, count( $GLOBALS['finto']['post'] ) );
	}
);

prova(
	'la firma del plugin: la stringa e le intestazioni',
	function () {
		$kp  = sodium_crypto_sign_seed_keypair( str_repeat( chr( 9 ), 32 ) );
		$i   = mador_intestazioni_firmate( 'sito-x', base64_encode( sodium_crypto_sign_secretkey( $kp ) ), '{"stati":[]}', 1800000000, 'nonce-dal-php-0123' );
		uguale( 'DOngGRPkCGMGkXuC5N97/S9h/XlOc0bZko2gXVffngK4s/Abu2ii2VBycBSB4lVQtVYNluvHePC04J3DFfZ7Bg==', $i['X-Mador-Firma'], 'la stessa firma che Node verifica in lib/wp/firma.test.ts' );
		uguale( array(), mador_intestazioni_firmate( 'sito-x', 'corta', '{}', 1, 'n' ), 'chiave storta' );
	}
);

prova(
	'decidi: i casi',
	function () {
		$post = array(
			'stato'     => 'publish',
			'contenuto' => 'a',
			'impronta'  => mador_impronta( 'a' ),
		);
		uguale( 'crea', mador_decidi( array( 'azione' => 'crea' ), null ) );
		uguale( 'crea', mador_decidi( array( 'azione' => 'crea' ), array_merge( $post, array( 'stato' => 'trash' ) ) ) );
		uguale( 'aggiorna', mador_decidi( array( 'azione' => 'aggiorna' ), $post ) );
		uguale( 'modificata', mador_decidi( array( 'azione' => 'aggiorna' ), array_merge( $post, array( 'contenuto' => 'b' ) ) ) );
		uguale( 'aggiorna', mador_decidi( array( 'azione' => 'aggiorna', 'forza' => true ), array_merge( $post, array( 'contenuto' => 'b' ) ) ) );
		uguale( 'ritira', mador_decidi( array( 'azione' => 'ritira' ), $post ) );
		uguale( 'salta', mador_decidi( array( 'azione' => 'ritira' ), null ) );
		uguale( 'salta', mador_decidi( array( 'azione' => 'cancella' ), $post ) );
	}
);

echo "\n$passate passate, $fallite fallite\n";
exit( $fallite > 0 ? 1 : 0 );
