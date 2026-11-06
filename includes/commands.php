<?php
/**
 * Site-specific commands Valet adds on top of core's.
 *
 * Each command is an array with `name`, `label` and `url`, plus optional
 * `category` (command|view|edit|action) and `searchLabel`. Other plugins and
 * themes can add their own through the `wp_valet_commands` filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $context Either 'admin' or 'front'.
 * @return array<int, array<string, string>>
 */
function wp_valet_get_commands( string $context ): array {
	$commands = array_merge(
		'front' === $context ? wp_valet_edit_current_commands() : [],
		wp_valet_gravity_forms_commands()
	);

	/**
	 * Filters the commands Valet registers in the palette.
	 *
	 * @param array  $commands Commands with name, label, url and optional category/searchLabel.
	 * @param string $context  Either 'admin' or 'front'.
	 */
	$commands = apply_filters( 'wp_valet_commands', $commands, $context );

	return array_values(
		array_filter(
			(array) $commands,
			static function ( $command ): bool {
				return ! empty( $command['name'] ) && ! empty( $command['label'] ) && ! empty( $command['url'] );
			}
		)
	);
}

/**
 * "Edit this page" for the post or term being viewed on the front end.
 */
function wp_valet_edit_current_commands(): array {
	$object = get_queried_object();

	if ( $object instanceof WP_Post && current_user_can( 'edit_post', $object->ID ) ) {
		$type = get_post_type_object( $object->post_type );

		return [
			[
				'name'     => 'wp-valet/edit-current',
				/* translators: %s: post type singular name */
				'label'    => sprintf( __( 'Edit this %s', 'wp-valet' ), strtolower( $type ? $type->labels->singular_name : __( 'Page', 'wp-valet' ) ) ),
				'url'      => get_edit_post_link( $object->ID, 'raw' ),
				'category' => 'edit',
			],
		];
	}

	if ( $object instanceof WP_Term && current_user_can( 'edit_term', $object->term_id ) ) {
		return [
			[
				'name'     => 'wp-valet/edit-current',
				/* translators: %s: term name */
				'label'    => sprintf( __( 'Edit term: %s', 'wp-valet' ), $object->name ),
				'url'      => get_edit_term_link( $object, $object->taxonomy ),
				'category' => 'edit',
			],
		];
	}

	return [];
}

/**
 * Edit and Entries commands for each active Gravity Forms form.
 */
function wp_valet_gravity_forms_commands(): array {
	if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GFCommon' ) ) {
		return [];
	}

	$can_edit    = GFCommon::current_user_can_any( 'gravityforms_edit_forms' );
	$can_entries = GFCommon::current_user_can_any( 'gravityforms_view_entries' );

	if ( ! $can_edit && ! $can_entries ) {
		return [];
	}

	$commands = [];

	foreach ( GFAPI::get_forms( true, false ) as $form ) {
		$id    = (int) $form['id'];
		$title = $form['title'];

		if ( $can_edit ) {
			$commands[] = [
				'name'     => 'wp-valet/gf-edit-' . $id,
				/* translators: %s: form title */
				'label'    => sprintf( __( 'Forms > Edit: %s', 'wp-valet' ), $title ),
				'url'      => admin_url( 'admin.php?page=gf_edit_forms&id=' . $id ),
				'category' => 'edit',
			];
		}

		if ( $can_entries ) {
			$commands[] = [
				'name'     => 'wp-valet/gf-entries-' . $id,
				/* translators: %s: form title */
				'label'    => sprintf( __( 'Forms > Entries: %s', 'wp-valet' ), $title ),
				'url'      => admin_url( 'admin.php?page=gf_entries&id=' . $id ),
				'category' => 'view',
			];
		}
	}

	return $commands;
}
