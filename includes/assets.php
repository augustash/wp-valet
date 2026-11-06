<?php
/**
 * Script loading and the front-end admin bar button.
 *
 * In wp-admin, core already mounts the palette, so Valet only adds its hotkey
 * and commands. On the front end, Valet mounts core's CommandMenu itself and
 * registers the saved menu commands plus a content search, because core's
 * own loaders use admin-relative URLs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'wp_valet_enqueue_admin', 20 );
add_action( 'wp_enqueue_scripts', 'wp_valet_enqueue_front' );
add_action( 'admin_bar_menu', 'wp_valet_admin_bar_button', 56 );

function wp_valet_enqueue_admin(): void {
	if ( ! wp_valet_user_can() || ! wp_script_is( 'wp-core-commands', 'enqueued' ) ) {
		return;
	}

	wp_valet_enqueue(
		[
			'commands' => wp_valet_get_commands( 'admin' ),
		]
	);
}

function wp_valet_enqueue_front(): void {
	if ( ! wp_valet_user_can() ) {
		return;
	}

	wp_enqueue_style( 'wp-commands' );

	wp_valet_enqueue(
		[
			'mount'        => true,
			'menuCommands' => wp_valet_get_menu_commands(),
			'commands'     => wp_valet_get_commands( 'front' ),
			'editUrl'      => admin_url( 'post.php?post=%d&action=edit' ),
		]
	);
}

function wp_valet_enqueue( array $settings ): void {
	$path = 'assets/wp-valet.js';

	wp_enqueue_script(
		'wp-valet',
		plugins_url( $path, WP_VALET_FILE ),
		[ 'wp-api-fetch', 'wp-commands', 'wp-data', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-primitives', 'wp-url' ],
		WP_VALET_VERSION . '.' . filemtime( WP_VALET_DIR . $path ),
		true
	);

	wp_add_inline_script(
		'wp-valet',
		'window.wpValetSettings = ' . wp_json_encode( $settings, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
		'before'
	);
}

/**
 * Shows Valet's Shift+Space shortcut on the palette button everywhere.
 *
 * In wp-admin this replaces core's ⌘K/Ctrl+K node (Cmd/Ctrl+K still works);
 * on the front end, where core adds no button, it adds one. Reusing core's
 * node ID keeps core's admin bar styles.
 */
function wp_valet_admin_bar_button( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! wp_valet_user_can() ) {
		return;
	}

	if ( is_admin() ) {
		if ( ! $wp_admin_bar->get_node( 'command-palette' ) ) {
			return;
		}
		$wp_admin_bar->remove_node( 'command-palette' );
	}

	$wp_admin_bar->add_node(
		[
			'id'    => 'command-palette',
			'title' => sprintf(
				'<span class="ab-icon" aria-hidden="true"></span><span class="ab-label"><kbd>%s</kbd><span class="screen-reader-text"> %s</span></span>',
				esc_html( wp_valet_shortcut_label() ),
				esc_html__( 'Open command palette', 'wp-valet' )
			),
			'href'  => '#',
			'meta'  => [
				'class'   => 'hide-if-no-js',
				'onclick' => 'wp.data.dispatch( "core/commands" ).open(); return false;',
			],
		]
	);
}

/**
 * "⇧Space" on Apple platforms, "Shift+Space" elsewhere, mirroring how core
 * labels ⌘K/Ctrl+K.
 */
function wp_valet_shortcut_label(): string {
	$is_apple_os = (bool) preg_match( '/Macintosh|Mac OS X|Mac_PowerPC|iPhone|iPad/i', $_SERVER['HTTP_USER_AGENT'] ?? '' );

	return $is_apple_os
		? _x( '⇧Space', 'keyboard shortcut to open the command palette', 'wp-valet' )
		: _x( 'Shift+Space', 'keyboard shortcut to open the command palette', 'wp-valet' );
}
