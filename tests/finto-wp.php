<?php
/**
 * A tiny in-memory stand-in for the WordPress functions the publishing
 * code calls, so `tests/run.php` runs with plain PHP. It is NOT WordPress:
 * it models posts, post meta and options, and nothing else. The real
 * round on a WordPress install is described in README.md.
 *
 * @package Mador
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MADOR_ORIGINE', 'https://mador.test' );
define( 'MADOR_VERSIONE', '1.2.0' );

class WP_Post {
	public $ID;
	public $post_type    = 'page';
	public $post_status  = 'publish';
	public $post_title   = '';
	public $post_content = '';
	public $post_author  = 0;
}

class WP_Error {
}

$GLOBALS['finto'] = array(
	'post'     => array(),
	'meta'     => array(),
	'opzioni'  => array(),
	'prossimo' => 1,
);

function finto_reset() {
	$GLOBALS['finto_http'] = null;
	$GLOBALS['finto']      = array(
		'post'     => array(),
		'meta'     => array(),
		'opzioni'  => array(),
		'prossimo' => 1,
	);
}

// The HTTP answer `wp_remote_post()` returns: a callable, set by a test.
$GLOBALS['finto_http'] = null;

function get_transient( $k ) {
	return isset( $GLOBALS['finto']['opzioni'][ '_t_' . $k ] ) ? $GLOBALS['finto']['opzioni'][ '_t_' . $k ] : false;
}
function set_transient( $k, $v ) {
	$GLOBALS['finto']['opzioni'][ '_t_' . $k ] = $v;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['finto']['opzioni'][ '_t_' . $k ] );
	return true;
}
function wp_remote_post( $url, $args ) {
	$f = $GLOBALS['finto_http'];
	return $f ? $f( $url, $args ) : new WP_Error();
}
function wp_remote_retrieve_response_code( $r ) {
	return is_array( $r ) ? $r['code'] : 0;
}
function wp_remote_retrieve_body( $r ) {
	return is_array( $r ) ? $r['body'] : '';
}

function add_action() {}
function add_filter() {}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function wp_json_encode( $x, $flags = 0 ) {
	return json_encode( $x, $flags );
}
function wp_slash( $x ) {
	return addslashes( $x );
}
function sanitize_text_field( $x ) {
	return trim( strip_tags( (string) $x ) );
}
/** Stand-in for kses: drops script tags and inline handlers, like the real one would. */
function wp_kses_post( $x ) {
	$x = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $x );
	return preg_replace( '/\son\w+="[^"]*"/i', '', $x );
}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['finto']['opzioni'] ) ? $GLOBALS['finto']['opzioni'][ $k ] : $d;
}
function update_option( $k, $v ) {
	$GLOBALS['finto']['opzioni'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['finto']['opzioni'][ $k ] );
}
function get_post_meta( $id, $k, $uno = true ) {
	return isset( $GLOBALS['finto']['meta'][ $id ][ $k ] ) ? $GLOBALS['finto']['meta'][ $id ][ $k ] : '';
}
function update_post_meta( $id, $k, $v ) {
	$GLOBALS['finto']['meta'][ $id ][ $k ] = is_string( $v ) ? stripslashes( $v ) : $v;
}
function delete_post_meta( $id, $k ) {
	unset( $GLOBALS['finto']['meta'][ $id ][ $k ] );
}
function get_post_field( $campo, $id ) {
	return $GLOBALS['finto']['post'][ $id ]->$campo;
}
function wp_insert_post( $d ) {
	$p               = new WP_Post();
	$p->ID           = $GLOBALS['finto']['prossimo']++;
	$p->post_type    = $d['post_type'];
	$p->post_status  = $d['post_status'];
	$p->post_title   = $d['post_title'];
	$p->post_content = $d['post_content'];
	$p->post_author  = $d['post_author'];
	$GLOBALS['finto']['post'][ $p->ID ] = $p;
	return $p->ID;
}
function wp_update_post( $d ) {
	$p = $GLOBALS['finto']['post'][ $d['ID'] ];
	foreach ( $d as $k => $v ) {
		if ( 'ID' !== $k ) {
			$p->$k = $v;
		}
	}
	return $p->ID;
}
function get_posts( $q ) {
	$out = array();
	foreach ( $GLOBALS['finto']['post'] as $p ) {
		$id = get_post_meta( $p->ID, '_mador_id' );
		if ( '' === $id ) {
			continue;
		}
		if ( isset( $q['meta_value'] ) && $q['meta_value'] !== $id ) {
			continue;
		}
		$out[] = $p;
	}
	return $out;
}
function get_permalink( $p ) {
	$id = is_object( $p ) ? $p->ID : $p;
	return 'https://sito.test/?page_id=' . $id;
}
