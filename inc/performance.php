<?php
/** Performance extension point. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Allows projects to disable emoji assets without changing theme code.
 *
 * Evaluated on the `wp` action (before wp_head, like the JSON-LD schema
 * registration) instead of when this file is loaded, so the filter works when
 * added from plugins, child themes, `after_setup_theme`, `init`, or `wp`
 * (priority below 10), and may depend on conditional tags:
 *
 *     add_filter( 'yzrh_disable_emoji_assets', '__return_true' );
 */
function yzrh_maybe_disable_emoji_assets() {
	if ( ! apply_filters( 'yzrh_disable_emoji_assets', false ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' ); // WordPress < 6.4.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ); // WordPress 6.4+.
}
add_action( 'wp', 'yzrh_maybe_disable_emoji_assets' );
