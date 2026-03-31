<?php
/**
 * WebSite schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_website' ) ) {
        function yzrh_structured_data_schema_website() {
                $site_name = get_bloginfo( 'name' );
                $site_url  = home_url( '/' );

                if ( empty( $site_name ) ) {
                        return null;
                }

                $schema = array(
                        '@context' => 'https://schema.org',
                        '@type'    => 'WebSite',
                        'name'     => $site_name,
                        'url'      => $site_url,
                );

                $search_url = add_query_arg( 's', '{search_term_string}', $site_url );
                $schema['potentialAction'] = array(
                        '@type'       => 'SearchAction',
                        'target'      => $search_url,
                        'query-input' => 'required name=search_term_string',
                );

                return apply_filters( 'yzrh_structured_data_website', $schema );
        }
}
