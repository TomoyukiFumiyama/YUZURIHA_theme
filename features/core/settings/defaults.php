<?php
/**
 * Core theme default settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yzrh_core_get_default_settings' ) ) {
	/**
	 * Returns default settings used by theme features.
	 *
	 * @return array
	 */
	function yzrh_core_get_default_settings() {
		$defaults = array(
			// Display defaults.
			'display_show_sidebar_post'    => true,
			'display_show_sidebar_archive' => true,
			'display_show_author_box'      => true,

			// SEO / JSON-LD defaults.
			'seo_jsonld_enabled'           => true,
			'seo_jsonld_website'           => true,
			'seo_jsonld_organization'      => true,
			'seo_jsonld_breadcrumbs'       => true,
			'seo_jsonld_blogposting'       => true,
			'seo_jsonld_service'           => true,
			'seo_jsonld_blog'              => true,
			'seo_jsonld_blog_itemlist'     => true,
			'seo_jsonld_author'            => true,
		);

		return apply_filters( 'yzrh_core_default_settings', $defaults );
	}
}
