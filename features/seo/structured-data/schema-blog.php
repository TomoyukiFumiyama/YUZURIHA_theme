<?php
/**
 * Blog and blog listing schema generators.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_blog' ) ) {
        /**
         * Outputs the Blog entity schema describing the site's blog.
         *
         * @return array|null
         */
        function yzrh_structured_data_schema_blog() {
                if ( ! YZRH_Structured_Data_Generator::is_blog_listing_context() ) {
                        return null;
                }

                $site_name = get_bloginfo( 'name' );
                if ( empty( $site_name ) ) {
                        return null;
                }

                $posts_page_id = (int) get_option( 'page_for_posts' );
                $blog_url      = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );

                $schema = array(
                        '@context'    => 'https://schema.org',
                        '@type'       => 'Blog',
                        'name'        => YZRH_Structured_Data_Generator::sanitize_text( $site_name ),
                        'url'         => $blog_url,
                        'publisher'   => YZRH_Structured_Data_Generator::get_publisher_schema(),
                        'inLanguage'  => get_bloginfo( 'language' ),
                );

                $description = get_bloginfo( 'description' );
                if ( $description ) {
                        $schema['description'] = YZRH_Structured_Data_Generator::sanitize_text( $description );
                }

                return apply_filters( 'yzrh_structured_data_blog', $schema );
        }
}

if ( ! function_exists( 'yzrh_structured_data_schema_blog_itemlist' ) ) {
        /**
         * Outputs an ItemList schema for the current blog archive/list view.
         *
         * @return array|null
         */
        function yzrh_structured_data_schema_blog_itemlist() {
                if ( ! YZRH_Structured_Data_Generator::is_blog_listing_context() ) {
                        return null;
                }

                global $wp_query;
                if ( ! $wp_query instanceof WP_Query ) {
                        return null;
                }

                if ( empty( $wp_query->posts ) ) {
                        return null;
                }

                $items      = array();
                $posts      = $wp_query->posts;
                $paged      = max( 1, (int) $wp_query->get( 'paged', 1 ) );
                $per_page   = (int) $wp_query->get( 'posts_per_page', count( $posts ) );
                $offset     = $per_page > 0 ? ( ( $paged - 1 ) * $per_page ) : 0;

                foreach ( $posts as $index => $post ) {
                        if ( 'post' !== get_post_type( $post ) ) {
                                continue;
                        }

                        $position = $offset + $index + 1;
                        $items[]  = array(
                                '@type'       => 'ListItem',
                                'position'    => $position,
                                'url'         => get_permalink( $post ),
                                'name'        => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
                                'description' => YZRH_Structured_Data_Generator::get_post_description( $post->ID ),
                        );
                }

                if ( empty( $items ) ) {
                        return null;
                }

                $archive_title = '';
                if ( is_home() ) {
                        $posts_page_id = (int) get_option( 'page_for_posts' );
                        $archive_title = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'yzrh' );
                } else {
                        $archive_title = get_the_archive_title();
                }

                $archive_description = is_home() ? get_bloginfo( 'description' ) : get_the_archive_description();

                $schema = array(
                        '@context'       => 'https://schema.org',
                        '@type'          => 'ItemList',
                        'name'           => YZRH_Structured_Data_Generator::sanitize_text( $archive_title ),
                        'url'            => get_pagenum_link( $paged ),
                        'itemListOrder'  => 'https://schema.org/ItemListOrderDescending',
                        'numberOfItems'  => count( $items ),
                        'itemListElement'=> array_values( $items ),
                );

                if ( $archive_description ) {
                        $schema['description'] = YZRH_Structured_Data_Generator::sanitize_text( $archive_description );
                }

                return apply_filters( 'yzrh_structured_data_blog_itemlist', $schema, $wp_query );
        }
}
