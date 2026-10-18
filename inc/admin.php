<?php
/** Administration and query behavior. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Normalizes an interview sort order value to "ASC" or "DESC".
 *
 * Only strings are accepted: `?order[]=x` arrives as an array, and passing it
 * to string functions is a fatal TypeError on PHP 8.
 *
 * @param mixed  $value   Raw value.
 * @param string $default Value used for missing or invalid input.
 * @return string "ASC" or "DESC".
 */
function yzrh_normalize_interview_order( $value, $default = 'DESC' ) {
	if ( ! is_string( $value ) ) {
		return $default;
	}

	$order = strtoupper( trim( $value ) );

	return in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : $default;
}

/**
 * Returns the requested interview archive sort order from `?order=`.
 *
 * Shared by the query hook and the archive template's sort control so both
 * always agree.
 *
 * @return string "ASC" or "DESC".
 */
function yzrh_get_interview_order() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display option.
	$raw = isset( $_GET['order'] ) ? wp_unslash( $_GET['order'] ) : null;

	return yzrh_normalize_interview_order( $raw );
}

/**
 * Applies archive ordering for interview list requests.
 *
 * @param WP_Query $query Query object.
 */
function yzrh_interview_archive_ordering( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'interview' ) ) {
		return;
	}

	$query->set( 'orderby', 'date' );
	$query->set( 'order', yzrh_get_interview_order() );
}
add_action( 'pre_get_posts', 'yzrh_interview_archive_ordering' );
