<?php
/**
 * Organization schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_organization' ) ) {
        function yzrh_structured_data_schema_organization() {
                $site_name = get_bloginfo( 'name' );
                $site_url  = home_url( '/' );

                if ( empty( $site_name ) ) {
                        return null;
                }

                $schema = array(
                        '@context' => 'https://schema.org',
                        '@type'    => 'Organization',
                        'name'     => $site_name,
                        'url'      => $site_url,
                );

                $logo = YZRH_Structured_Data_Generator::get_logo_object();
                if ( $logo ) {
                        $schema['logo'] = $logo;
                }

                return apply_filters( 'yzrh_structured_data_organization', $schema );
        }
}
