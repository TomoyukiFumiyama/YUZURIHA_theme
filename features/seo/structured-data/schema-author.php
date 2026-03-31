<?php
/**
 * Author profile schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_author_person' ) ) {
        /**
         * Outputs a Person schema for the requested author archive page.
         *
         * @return array|null
         */
        function yzrh_structured_data_schema_author_person() {
                if ( ! is_author() ) {
                        return null;
                }

                $author = get_queried_object();
                if ( ! $author instanceof WP_User ) {
                        return null;
                }

                $author_id     = (int) $author->ID;
                $person_schema = YZRH_Structured_Data_Generator::get_author_schema( null, $author_id );
                if ( empty( $person_schema ) || empty( $person_schema['name'] ) ) {
                        return null;
                }

                $schema = array_merge(
                        array(
                                '@context' => 'https://schema.org',
                        ),
                        $person_schema
                );

                return apply_filters( 'yzrh_structured_data_author_person', $schema, $author_id );
        }
}
