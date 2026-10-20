<?php
/**
 * Uninstall: removes everything the plugin stored.
 *
 * - the `mador_opzioni` option (report code, language, credit link);
 * - the `mador_faq_*` transients that cache the questions and answers.
 *
 * On multisite the option and the transients live per site, and
 * `uninstall.php` runs once, so every site is visited here.
 *
 * @package Mador
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Cleans the current site.
 *
 * Transients are found by prefix in the options table and deleted through
 * the API, which also removes their timeout rows. With a persistent object
 * cache they never reach the options table: there they expire on their
 * own, within a day.
 */
function mador_uninstall_site() {
	global $wpdb;

	delete_option( 'mador_opzioni' );

	// A direct query because there is no API to list transients by prefix;
	// the rows are then deleted through the API, and nothing is cached
	// because the plugin is being removed.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_mador_faq_' ) . '%'
		)
	);
	foreach ( $names as $name ) {
		delete_transient( substr( $name, strlen( '_transient_' ) ) );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $mador_site_id ) {
		switch_to_blog( (int) $mador_site_id );
		mador_uninstall_site();
		restore_current_blog();
	}
} else {
	mador_uninstall_site();
}
