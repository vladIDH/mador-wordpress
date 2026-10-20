<?php
/**
 * Settings → Mador: three fields (report code, language, credit link), and
 * a panel that
 * shows what the plugin is doing with them — the real badge, the
 * verification tag, the shortcodes to copy.
 *
 * Under Settings and not in a top-level menu: a plugin with three fields
 * does not need a place in the sidebar.
 *
 * The form goes through the Settings API: `settings_fields()` prints the
 * nonce, `options.php` checks it and the `manage_options` capability, and
 * `mador_sanifica()` cleans the input.
 *
 * @package Mador
 */

defined( 'ABSPATH' ) || exit;

/** Settings API group. */
const MADOR_GRUPPO = 'mador_gruppo';

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			'Mador',
			'Mador',
			'manage_options',
			'mador',
			'mador_pagina_impostazioni'
		);
	}
);

add_action( 'admin_init', 'mador_registra_impostazioni' );

/** Registers the option, the section and the fields. */
function mador_registra_impostazioni(): void {
	register_setting(
		MADOR_GRUPPO,
		MADOR_OPZIONE,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mador_sanifica',
			'default'           => array(
				'codice' => '',
				'lingua' => 'it',
				'fonte'  => false,
			),
		)
	);

	add_settings_section( 'mador_sezione', '', '__return_false', 'mador' );

	add_settings_field(
		'mador_codice',
		esc_html__( 'Report code', 'mador' ),
		'mador_campo_codice',
		'mador',
		'mador_sezione',
		array( 'label_for' => 'mador_codice' )
	);

	add_settings_field(
		'mador_lingua',
		esc_html__( 'Language', 'mador' ),
		'mador_campo_lingua',
		'mador',
		'mador_sezione',
		array( 'label_for' => 'mador_lingua' )
	);

	add_settings_field(
		'mador_fonte',
		esc_html__( 'Credit link', 'mador' ),
		'mador_campo_fonte',
		'mador',
		'mador_sezione',
		array( 'label_for' => 'mador_fonte' )
	);
}

/** The report code field. */
function mador_campo_codice(): void {
	$o = mador_opzioni();
	printf(
		'<input type="text" id="mador_codice" name="%1$s[codice]" value="%2$s" class="regular-text code" spellcheck="false" autocomplete="off" />',
		esc_attr( MADOR_OPZIONE ),
		esc_attr( $o['codice'] )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'The ten characters at the end of your report address: mador.ai/r/XXXXXXXXXX. You can paste the whole address — the plugin keeps the code.', 'mador' )
	);
}

/** The language field. */
function mador_campo_lingua(): void {
	$scelta = mador_lingua();
	$nomi   = array(
		'it' => esc_html__( 'Italian', 'mador' ),
		'en' => esc_html__( 'English', 'mador' ),
		'es' => esc_html__( 'Spanish', 'mador' ),
	);

	printf( '<select id="mador_lingua" name="%s[lingua]">', esc_attr( MADOR_OPZIONE ) );
	foreach ( MADOR_LINGUE as $lingua ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $lingua ),
			selected( $scelta, $lingua, false ),
			esc_html( $nomi[ $lingua ] )
		);
	}
	echo '</select>';

	printf(
		'<p class="description">%s</p>',
		esc_html__( 'The language of the certificate: the page the badge links to, and the one the shortcode embeds. The badge image itself stays in Italian, like the brand.', 'mador' )
	);
}

/**
 * The credit link field: a checkbox, off by default.
 *
 * WordPress.org does not let a plugin add links to the public site without
 * the administrator's consent, so the link exists only once this is ticked.
 * The badge and the certificate need no such switch: they appear only
 * where the administrator places their shortcodes.
 */
function mador_campo_fonte(): void {
	printf(
		'<label for="mador_fonte"><input type="checkbox" id="mador_fonte" name="%1$s[fonte]" value="1"%2$s /> %3$s</label>',
		esc_attr( MADOR_OPZIONE ),
		checked( mador_fonte(), true, false ),
		esc_html__( 'Show a “Measured and written with Mador” link under the questions and answers', 'mador' )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'Off by default. When on, a small nofollow link to the page on Mador your questions and answers come from appears under the [mador-faq] block. The plugin adds no other link to your site on its own: the badge and the certificate appear only where you place their shortcodes.', 'mador' )
	);
}

/** The page. */
function mador_pagina_impostazioni(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$codice = mador_codice();
	$lingua = mador_lingua();

	echo '<div class="wrap">';
	echo '<h1>Mador</h1>';

	// No settings_errors() here: pages under Settings get it from
	// wp-admin/options-head.php, and a second call prints every notice twice.

	// The warning stays as long as the code is wrong, not only right after
	// saving: whoever comes back the next day finds the reason again.
	if ( '' !== $codice && ! mador_codice_valido( $codice ) ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'That does not look like a report code: ten characters, letters and digits, without “0”, “1”, “i”, “l” or “o”.', 'mador' )
		);
	}

	echo '<form action="options.php" method="post">';
	settings_fields( MADOR_GRUPPO );
	do_settings_sections( 'mador' );
	submit_button();
	echo '</form>';

	mador_riquadro_stato( $codice, $lingua );

	echo '</div>';
}

/**
 * The "what the plugin is doing" panel: real badge, shortcodes, verification tag.
 *
 * @param string $codice The saved report code.
 * @param string $lingua The saved language.
 */
function mador_riquadro_stato( string $codice, string $lingua ): void {
	echo '<hr />';
	printf( '<h2>%s</h2>', esc_html__( 'What the plugin is doing', 'mador' ) );

	if ( '' === $codice ) {
		printf(
			'<p>%s</p>',
			esc_html__( 'Enter the report code above: until then the plugin adds nothing to the site.', 'mador' )
		);
		return;
	}

	// The real badge, loaded from Mador: the proof that the code is right.
	printf( '<h3>%s</h3>', esc_html__( 'Badge', 'mador' ) );
	printf(
		'<p><a href="%1$s" target="_blank" rel="noopener"><img src="%2$s" width="%3$d" height="%4$d" alt="%5$s" style="max-width:100%%;height:auto" /></a></p>',
		esc_url( mador_url_certificato( $codice, $lingua ) ),
		esc_url( mador_url_badge( $codice ) ),
		(int) MADOR_BADGE_L,
		(int) MADOR_BADGE_H,
		esc_attr( sprintf( mador_testo( 'badge_alt', $lingua ), get_bloginfo( 'name' ) ) )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'If you see no image here, the code is wrong or the measurement is not published yet.', 'mador' )
	);
	printf(
		'<p>%s <code>[mador-badge]</code></p>',
		esc_html__( 'Paste this where you want the badge:', 'mador' )
	);

	// The certificate.
	printf( '<h3>%s</h3>', esc_html__( 'Certificate', 'mador' ) );
	printf(
		'<p>%s <code>[mador-certificato]</code></p>',
		esc_html__( 'Paste this where you want the full certificate:', 'mador' )
	);
	printf(
		'<p class="description">%1$s <a href="%2$s" target="_blank" rel="noopener">%2$s</a></p>',
		esc_html__( 'It embeds this page:', 'mador' ),
		esc_url( mador_url_certificato( $codice, $lingua ) )
	);

	// Questions and answers.
	printf( '<h3>%s</h3>', esc_html__( 'Questions and answers', 'mador' ) );
	printf(
		'<p>%s <code>[mador-faq]</code></p>',
		esc_html__( 'Paste this on the page that should carry your questions and answers:', 'mador' )
	);
	// Say that the block appears only after publication, or an empty page
	// looks like a broken plugin.
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'It shows nothing until the article is published on Mador and the questions and answers are written. Then it appears on its own.', 'mador' )
	);

	// The meta tag: already there, nothing to paste.
	printf( '<h3>%s</h3>', esc_html__( 'Verification tag', 'mador' ) );
	printf(
		'<p><code>%s</code></p>',
		esc_html( mador_meta_verifica_html( $codice ) )
	);
	printf(
		'<p class="description">%s</p>',
		esc_html__( 'This tag is already in the head of every page of this site. Leave it there: it is how Mador checks that this site is yours.', 'mador' )
	);
}
