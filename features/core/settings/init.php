<?php
/**
 * Core settings bootstrap.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/defaults.php';

if ( ! function_exists( 'yzrh_core_get_settings' ) ) {
	/**
	 * Returns merged settings from DB + defaults.
	 *
	 * @return array
	 */
	function yzrh_core_get_settings() {
		$defaults = yzrh_core_get_default_settings();
		$stored   = get_option( 'yzrh_settings', array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored = array_intersect_key( $stored, $defaults );

		return wp_parse_args( $stored, $defaults );
	}
}

if ( ! function_exists( 'yzrh_get_setting' ) ) {
	/**
	 * Returns one setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback default.
	 * @return mixed
	 */
	function yzrh_get_setting( $key, $default = null ) {
		$settings = yzrh_core_get_settings();
		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}
}
