<?php
/**
 * Structured data bootstrap.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-structured-data-generator.php';
require_once __DIR__ . '/schema-website.php';
require_once __DIR__ . '/schema-organization.php';
require_once __DIR__ . '/schema-breadcrumbs.php';
require_once __DIR__ . '/schema-article.php';
require_once __DIR__ . '/schema-service.php';
require_once __DIR__ . '/schema-blog.php';
require_once __DIR__ . '/schema-author.php';
require_once __DIR__ . '/jsonld-functions.php';

$schema_map = array(
	'seo_jsonld_website'       => 'yzrh_structured_data_schema_website',
	'seo_jsonld_organization'  => 'yzrh_structured_data_schema_organization',
	'seo_jsonld_breadcrumbs'   => 'yzrh_structured_data_schema_breadcrumbs',
	'seo_jsonld_blogposting'   => 'yzrh_jsonld_blogposting',
	'seo_jsonld_service'       => 'yzrh_structured_data_schema_service',
	'seo_jsonld_blog'          => 'yzrh_structured_data_schema_blog',
	'seo_jsonld_blog_itemlist' => 'yzrh_jsonld_blog_itemlist',
	'seo_jsonld_author'        => 'yzrh_jsonld_author_person',
);

foreach ( $schema_map as $setting_key => $callback ) {
	if ( yzrh_get_setting( $setting_key, true ) ) {
		YZRH_Structured_Data_Generator::register_schema( $callback );
	}
}

if ( yzrh_get_setting( 'seo_jsonld_enabled', true ) ) {
	add_action( 'wp_head', 'yzrh_output_jsonld', 5 );
}
