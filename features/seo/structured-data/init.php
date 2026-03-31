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

YZRH_Structured_Data_Generator::register_schema( 'yzrh_structured_data_schema_website' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_structured_data_schema_organization' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_structured_data_schema_breadcrumbs' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_jsonld_blogposting' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_structured_data_schema_service' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_structured_data_schema_blog' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_jsonld_blog_itemlist' );
YZRH_Structured_Data_Generator::register_schema( 'yzrh_jsonld_author_person' );

add_action( 'wp_head', 'yzrh_output_jsonld', 5 );
