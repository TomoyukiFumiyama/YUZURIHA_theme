<?php
/**
 * Interview archive CollectionPage schema generator.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'yzrh_structured_data_schema_interview_collection' ) ) {
	/**
	 * Returns a CollectionPage schema for the interview custom post type archive.
	 *
	 * @return array|null
	 */
	function yzrh_structured_data_schema_interview_collection() {
		if ( ! is_post_type_archive( 'interview' ) ) {
			return null;
		}

		$archive_url = get_post_type_archive_link( 'interview' );
		if ( ! $archive_url ) {
			$archive_url = home_url( '/interviews/' );
		}

		$description = get_the_archive_description();
		if ( ! $description ) {
			$description = __( 'マーケティング、デザイン、開発、経営の現場で働く人々への取材記事一覧。', 'yzrh' );
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'name'        => __( 'Interviews ── 取材一覧', 'yzrh' ),
			'description' => YZRH_Structured_Data_Generator::sanitize_text( $description ),
			'url'         => $archive_url,
			'inLanguage'  => get_bloginfo( 'language' ),
			'isPartOf'    => array(
				'@type' => 'WebSite',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'breadcrumb'  => array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => get_bloginfo( 'name' ),
						'item'     => home_url( '/' ),
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => __( 'Interviews', 'yzrh' ),
						'item'     => $archive_url,
					),
				),
			),
		);

		global $wp_query;
		if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
			$items      = array();
			$paged      = max( 1, (int) $wp_query->get( 'paged', 1 ) );
			$per_page   = (int) $wp_query->get( 'posts_per_page', count( $wp_query->posts ) );
			$offset     = $per_page > 0 ? ( ( $paged - 1 ) * $per_page ) : 0;

			foreach ( $wp_query->posts as $index => $post ) {
				if ( 'interview' !== get_post_type( $post ) ) {
					continue;
				}

				$items[] = array(
					'@type'       => 'ListItem',
					'position'    => $offset + $index + 1,
					'url'         => get_permalink( $post ),
					'name'        => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
					'description' => YZRH_Structured_Data_Generator::get_post_description( $post->ID ),
				);
			}

			if ( ! empty( $items ) ) {
				$schema['mainEntity'] = array(
					'@type'           => 'ItemList',
					'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
					'numberOfItems'   => count( $items ),
					'itemListElement' => array_values( $items ),
				);
			}
		}

		return apply_filters( 'yzrh_structured_data_interview_collection', $schema );
	}
}
