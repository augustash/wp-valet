# WP Valet

The WordPress counterpart to the [Valet](https://github.com/jacerider/valet) Drupal module. Instead of shipping its own launcher, it extends the core command palette (WordPress 6.9+).

## What it adds

- **Shift+Space** opens the palette anywhere outside a text field. Core's Cmd/Ctrl+K still works, but the admin bar button shows ⇧Space everywhere for consistency.
- **Front-end palette** for logged-in users with `edit_posts`. It offers the admin menu (saved from the user's last wp-admin visit), a content search across all post types, and an admin bar button.
- **Site commands:**
  - "Edit this page" or "Edit term" on the front end.
  - "Edit" and "Entries" for each Gravity Forms form.
- **Cmd/Ctrl+Enter** (or Cmd/Ctrl+click) opens a Valet command in a new tab.

Core already provides "Recently used" (shown when the input is empty), admin menu navigation inside wp-admin, and post and page search.

## Hooks

```php
// Who can use Valet (default: edit_posts).
add_filter( 'wp_valet_capability', fn() => 'manage_options' );

// Add commands. $context is 'admin' or 'front'.
add_filter( 'wp_valet_commands', function ( array $commands, string $context ) {
	$commands[] = [
		'name'     => 'my-theme/settings',
		'label'    => 'Theme Settings',
		'url'      => admin_url( 'admin.php?page=theme-general-settings' ),
		'category' => 'view', // command|view|edit|action
		'icon'     => 'page', // optional: pencil|page|post (edit defaults to pencil)
	];
	return $commands;
}, 10, 2 );
```

## Notes

- Front-end menu commands come from a per-user snapshot. It is refreshed on each wp-admin page load and only written when it changes. Until a user has visited wp-admin once, the front end shows only the site commands and content search.
- The front-end content search uses `/wp/v2/search`, which only returns published content.
