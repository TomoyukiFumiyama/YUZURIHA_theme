<?php
/**
 * Article schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_article' ) ) {
        function yzrh_structured_data_schema_article() {
                if ( ! is_singular( 'post' ) ) {
                        return null;
                }

                $post_id = get_queried_object_id();
                if ( ! $post_id ) {
                        return null;
                }

                $permalink = get_permalink( $post_id );
                $schema    = array(
                        '@context'         => 'https://schema.org',
                        '@type'            => 'BlogPosting',
                        'mainEntityOfPage' => array(
                                '@type' => 'WebPage',
                                '@id'   => $permalink,
                        ),
                        'headline'         => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
                        'description'      => YZRH_Structured_Data_Generator::get_post_description( $post_id ),
                        'datePublished'    => get_the_date( DATE_W3C, $post_id ),
                        'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
                        'author'           => YZRH_Structured_Data_Generator::get_author_schema( $post_id ),
                        'publisher'        => YZRH_Structured_Data_Generator::get_publisher_schema(),
                        'url'              => $permalink,
                        'isPartOf'         => array(
                                '@type' => 'Blog',
                                'name'  => get_bloginfo( 'name' ),
                                'url'   => home_url( '/' ),
                        ),
                );

                $primary_category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
                if ( $primary_category ) {
                        $schema['articleSection'] = $primary_category->name;
                }

                $image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
                if ( $image ) {
                        $schema['image'] = $image;
                }

                return apply_filters( 'yzrh_structured_data_article', $schema, $post_id );
        }
}
