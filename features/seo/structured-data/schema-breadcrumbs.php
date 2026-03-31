<?php
/**
 * BreadcrumbList schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_breadcrumbs' ) ) {
        function yzrh_structured_data_schema_breadcrumbs() {
                $items    = array();
                $position = 1;

                $items[] = array(
                        '@type'    => 'ListItem',
                        'position' => $position++,
                        'name'     => get_bloginfo( 'name' ),
                        'item'     => home_url( '/' ),
                );

                if ( is_front_page() ) {
                        return apply_filters( 'yzrh_structured_data_breadcrumbs', array(
                                '@context'        => 'https://schema.org',
                                '@type'           => 'BreadcrumbList',
                                'itemListElement' => $items,
                        ) );
                }

                if ( is_home() && ! is_front_page() ) {
                        $posts_page_id = get_option( 'page_for_posts' );
                        $items[]       = array(
                                '@type'    => 'ListItem',
                                'position' => $position++,
                                'name'     => $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Blog', 'yzrh' ),
                                'item'     => $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' ),
                        );
                } elseif ( is_singular() ) {
                        $post_id = get_queried_object_id();

                        if ( is_singular( 'post' ) ) {
                                $posts_page_id = get_option( 'page_for_posts' );
                                if ( $posts_page_id ) {
                                        $items[] = array(
                                                '@type'    => 'ListItem',
                                                'position' => $position++,
                                                'name'     => get_the_title( $posts_page_id ),
                                                'item'     => get_permalink( $posts_page_id ),
                                        );
                                }

                                $primary_category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
                                if ( $primary_category ) {
                                        $category_link = get_term_link( $primary_category );
                                        if ( ! is_wp_error( $category_link ) ) {
                                                $items[] = array(
                                                        '@type'    => 'ListItem',
                                                        'position' => $position++,
                                                        'name'     => $primary_category->name,
                                                        'item'     => $category_link,
                                                );
                                        }
                                }
                        }

                        $ancestors = array_reverse( get_post_ancestors( $post_id ) );
                        foreach ( $ancestors as $ancestor_id ) {
                                $items[] = array(
                                        '@type'    => 'ListItem',
                                        'position' => $position++,
                                        'name'     => get_the_title( $ancestor_id ),
                                        'item'     => get_permalink( $ancestor_id ),
                                );
                        }

                        $items[] = array(
                                '@type'    => 'ListItem',
                                'position' => $position++,
                                'name'     => get_the_title( $post_id ),
                                'item'     => get_permalink( $post_id ),
                        );
                } elseif ( is_category() ) {
                        $term = get_queried_object();
                        if ( $term && ! is_wp_error( $term ) ) {
                                if ( $term->parent ) {
                                        $ancestors = array_reverse( get_ancestors( $term->term_id, 'category' ) );
                                        foreach ( $ancestors as $ancestor_id ) {
                                                $ancestor = get_term( $ancestor_id, 'category' );
                                                if ( $ancestor && ! is_wp_error( $ancestor ) ) {
                                                        $ancestor_link = get_term_link( $ancestor );
                                                        if ( is_wp_error( $ancestor_link ) ) {
                                                                continue;
                                                        }

                                                        $items[] = array(
                                                                '@type'    => 'ListItem',
                                                                'position' => $position++,
                                                                'name'     => $ancestor->name,
                                                                'item'     => $ancestor_link,
                                                        );
                                                }
                                        }
                                }

                                $term_link = get_term_link( $term );
                                if ( ! is_wp_error( $term_link ) ) {
                                        $items[] = array(
                                                '@type'    => 'ListItem',
                                                'position' => $position++,
                                                'name'     => $term->name,
                                                'item'     => $term_link,
                                        );
                                }
                        }
                } elseif ( is_search() ) {
                        $items[] = array(
                                '@type'    => 'ListItem',
                                'position' => $position++,
                                'name'     => sprintf( __( 'Search results for "%s"', 'yzrh' ), get_search_query() ),
                                'item'     => get_search_link(),
                        );
                }

                return apply_filters( 'yzrh_structured_data_breadcrumbs', array(
                        '@context'        => 'https://schema.org',
                        '@type'           => 'BreadcrumbList',
                        'itemListElement' => $items,
                ) );
        }
}
