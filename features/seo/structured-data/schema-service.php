<?php
/**
 * Service schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_service' ) ) {
        function yzrh_structured_data_schema_service() {
                if ( ! YZRH_Structured_Data_Generator::is_service_page() ) {
                        return null;
                }

                $post_id = get_queried_object_id();
                if ( ! $post_id ) {
                        return null;
                }

                $schema = array(
                        '@context'    => 'https://schema.org',
                        '@type'       => 'Service',
                        'name'        => get_the_title( $post_id ),
                        'serviceType' => get_the_title( $post_id ),
                        'description' => YZRH_Structured_Data_Generator::get_post_description( $post_id ),
                        'provider'    => YZRH_Structured_Data_Generator::get_publisher_schema(),
                );

                $image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
                if ( $image ) {
                        $schema['image'] = $image;
                }

                return apply_filters( 'yzrh_structured_data_service', $schema, $post_id );
        }
}
