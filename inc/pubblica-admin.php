<?php
/**
 * Settings › Mador: the "Publish Mador pages on this site" box.
 *
 * Three buttons, each an `admin-post.php` form with its own nonce and the
 * `manage_options` check: connect (with the one-time code), check now,
 * disconnect.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** The box, printed by `mador_pagina_impostazioni()`. */
function mador_riquadro_pubblica(): void {
	echo '<hr />';
	printf( '<h2>%s</h2>', esc_html__( 'Publish Mador pages on this site', 'mador' ) );
	printf(
		'<p>%s</p>',
		esc_html__( 'With the Growth plan, the pages Mador writes for your business can also be published here, as your own pages. Nothing is published until you connect the site, and you choose in your Mador account which pages go out.', 'mador' )
	);

	$esito = isset( $_GET['mador_esito'] ) ? sanitize_key( wp_unslash( $_GET['mador_esito'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $esito ) {
		$messaggi = array(
			'collegato'       => array( 'success', __( 'Connected. Mador pages will appear here within the hour.', 'mador' ) ),
			'scollegato'      => array( 'success', __( 'Disconnected. The pages already published stay on your site.', 'mador' ) ),
			'ok'              => array( 'success', __( 'Checked: everything is up to date.', 'mador' ) ),
			'codice'          => array( 'error', __( 'That code is not valid or has expired. Get a new one from your Mador account: it lasts 15 minutes and works once.', 'mador' ) ),
			'host-diverso'    => array( 'error', __( 'This site is not the one listed in your Mador account.', 'mador' ) ),
			'senza-sito'      => array( 'error', __( 'Your Mador account has no website listed yet. Add it there, then try again.', 'mador' ) ),
			'piano'           => array( 'error', __( 'Publishing on your site is part of the Growth plan.', 'mador' ) ),
			'non-autorizzato' => array( 'error', __( 'Mador no longer recognizes this site. Disconnect and connect again with a new code.', 'mador' ) ),
			'firma'           => array( 'error', __( 'The answer did not come from Mador and was ignored.', 'mador' ) ),
		);
		$m        = isset( $messaggi[ $esito ] ) ? $messaggi[ $esito ] : array( 'warning', __( 'Mador could not be reached. The plugin will try again within the hour.', 'mador' ) );
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $m[0] ), esc_html( $m[1] ) );
	}

	$sito = mador_sito();
	if ( ! $sito ) {
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		wp_nonce_field( 'mador_collega' );
		echo '<input type="hidden" name="action" value="mador_collega" />';
		printf(
			'<p><label for="mador_codice_collega">%1$s</label><br /><input type="text" id="mador_codice_collega" name="codice" class="regular-text code" placeholder="XXXX-XXXX" autocomplete="off" spellcheck="false" /></p>',
			esc_html__( 'Connection code from your Mador account', 'mador' )
		);
		submit_button( __( 'Connect', 'mador' ), 'secondary', 'submit', false );
		echo '</form>';
		return;
	}

	printf(
		'<p>%s</p>',
		esc_html(
			$sito['ultimo_giro']
				/* translators: %s: date and time of the last check. */
				? sprintf( __( 'Connected. Last check with Mador: %s.', 'mador' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $sito['ultimo_giro'] ) )
				: __( 'Connected. No check with Mador yet.', 'mador' )
		)
	);
	echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" style="display:inline-block;margin-right:8px">';
	wp_nonce_field( 'mador_controlla' );
	echo '<input type="hidden" name="action" value="mador_controlla" />';
	submit_button( __( 'Check now', 'mador' ), 'secondary', 'submit', false );
	echo '</form>';
	echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" style="display:inline-block">';
	wp_nonce_field( 'mador_scollega' );
	echo '<input type="hidden" name="action" value="mador_scollega" />';
	submit_button( __( 'Disconnect', 'mador' ), 'delete', 'submit', false );
	echo '</form>';
	printf( '<p class="description">%s</p>', esc_html__( 'Disconnecting leaves every published page on your site.', 'mador' ) );
}

/**
 * Back to the settings page with a result.
 *
 * @param string $esito Result key for the admin notice.
 * @return void
 */
function mador_torna( string $esito ): void {
	wp_safe_redirect( add_query_arg( 'mador_esito', $esito, admin_url( 'options-general.php?page=mador' ) ) );
	exit;
}

add_action(
	'admin_post_mador_collega',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'mador_collega' );
		$codice = isset( $_POST['codice'] ) ? sanitize_text_field( wp_unslash( $_POST['codice'] ) ) : '';
		$e      = mador_collega( $codice );
		mador_torna( true === $e ? 'collegato' : $e );
	}
);

add_action(
	'admin_post_mador_controlla',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'mador_controlla' );
		mador_torna( mador_giro() );
	}
);

add_action(
	'admin_post_mador_scollega',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'mador_scollega' );
		mador_scollega();
		mador_torna( 'scollegato' );
	}
);
