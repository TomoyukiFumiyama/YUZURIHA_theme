<?php
/**
 * JSON-LD compatibility function layer.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yzrh_jsonld_blogposting' ) ) {
	/**
	 * Returns BlogPosting schema for single post pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blogposting() {
		return yzrh_structured_data_schema_article();
	}
}

if ( ! function_exists( 'yzrh_jsonld_blog_itemlist' ) ) {
	/**
	 * Returns ItemList schema for blog list/archive pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blog_itemlist() {
		return yzrh_structured_data_schema_blog_itemlist();
	}
}

if ( ! function_exists( 'yzrh_jsonld_author_person' ) ) {
	/**
	 * Returns Person schema for author archive pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_author_person() {
		return yzrh_structured_data_schema_author_person();
	}
}

if ( ! function_exists( 'yzrh_output_jsonld' ) ) {
	/**
	 * Outputs all registered JSON-LD schemas to wp_head.
	 *
	 * @return void
	 */
	function yzrh_output_jsonld() {
		YZRH_Structured_Data_Generator::render();
	}
}
