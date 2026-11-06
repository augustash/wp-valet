<?php
/**
 * Admin menu snapshot.
 *
 * Core builds its "Go to" commands from the global $menu and $submenu, which
 * only exist on admin requests. Each admin page load saves the current user's
 * menu (with absolute URLs) so the front-end palette can offer the same
 * commands.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WP_VALET_MENU_OPTION = 'wp_valet_menu_commands';

add_action( 'admin_enqueue_scripts', 'wp_valet_save_menu_snapshot', 20 );

function wp_valet_save_menu_snapshot(): void {
	if ( ! wp_valet_user_can() || is_network_admin() || is_user_admin() ) {
		return;
	}

	$commands = wp_valet_build_menu_commands();
	$user_id  = get_current_user_id();

	if ( get_user_option( WP_VALET_MENU_OPTION, $user_id ) !== $commands ) {
		update_user_option( $user_id, WP_VALET_MENU_OPTION, $commands );
	}
}

/**
 * The current user's saved menu commands.
 *
 * @return array<int, array{name: string, label: string, url: string}>
 */
function wp_valet_get_menu_commands(): array {
	$commands = get_user_option( WP_VALET_MENU_OPTION, get_current_user_id() );

	return is_array( $commands ) ? $commands : [];
}

/**
 * Mirrors wp_enqueue_command_palette_assets(), but with absolute URLs and the
 * "Go to:" prefix core's JS adds. Names match core's so "Recently used" is
 * shared between admin and front end.
 *
 * @return array<int, array{name: string, label: string, url: string}>
 */
function wp_valet_build_menu_commands(): array {
	global $menu, $submenu;

	if ( empty( $menu ) ) {
		return [];
	}

	$commands = [];

	foreach ( $menu as $menu_item ) {
		if ( empty( $menu_item[0] ) || ! is_string( $menu_item[0] ) || ( ! empty( $menu_item[1] ) && ! current_user_can( $menu_item[1] ) ) ) {
			continue;
		}

		$menu_label = wp_valet_menu_label( $menu_item[0] );
		$menu_slug  = $menu_item[2];
		$menu_url   = wp_valet_menu_url( $menu_slug );

		if ( $menu_url ) {
			$commands[] = wp_valet_menu_command( $menu_slug, $menu_label, $menu_url );
		}

		if ( empty( $submenu[ $menu_slug ] ) ) {
			continue;
		}

		foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
			if ( empty( $submenu_item[0] ) || ( ! empty( $submenu_item[1] ) && ! current_user_can( $submenu_item[1] ) ) ) {
				continue;
			}

			$submenu_url = wp_valet_menu_url( $submenu_item[2] );

			if ( $submenu_url ) {
				$commands[] = wp_valet_menu_command(
					$menu_slug . '-' . $submenu_item[2],
					sprintf( '%1$s > %2$s', $menu_label, wp_valet_menu_label( $submenu_item[0] ) ),
					$submenu_url
				);
			}
		}
	}

	return $commands;
}

function wp_valet_menu_command( string $name, string $label, string $url ): array {
	return [
		'name'  => $name,
		/* translators: %s: menu label */
		'label' => sprintf( __( 'Go to: %s', 'wp-valet' ), $label ),
		'url'   => $url,
	];
}

function wp_valet_menu_url( string $slug ): string {
	if ( wp_http_validate_url( $slug ) ) {
		return $slug;
	}

	if ( preg_match( '/\.php($|\?)/', $slug ) ) {
		return admin_url( $slug );
	}

	$url = menu_page_url( $slug, false );

	return $url ? WP_HTML_Decoder::decode_attribute( $url ) : '';
}

/**
 * Root-level text of a menu label, dropping badges such as update counts.
 */
function wp_valet_menu_label( string $label ): string {
	$processor  = new WP_HTML_Tag_Processor( $label );
	$text_parts = [];
	$depth      = 0;

	while ( $processor->next_token() ) {
		$token_type = $processor->get_token_type();

		if ( '#text' === $token_type ) {
			if ( 0 === $depth ) {
				$text_parts[] = $processor->get_modifiable_text();
			}
			continue;
		}

		if ( '#tag' !== $token_type ) {
			continue;
		}

		if ( $processor->is_tag_closer() ) {
			$depth = max( 0, $depth - 1 );
			continue;
		}

		$tag = $processor->get_tag();
		if ( $tag && ! WP_HTML_Processor::is_void( $tag ) ) {
			++$depth;
		}
	}

	return trim( implode( '', $text_parts ) );
}
