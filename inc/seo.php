<?php
/** SEO metadata, breadcrumbs, and extensible JSON-LD output. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Core JSON-LD structured data generator.
 */

if ( ! class_exists( 'YZRH_Structured_Data_Generator' ) ) {
        class YZRH_Structured_Data_Generator {
                /**
                 * Registered schema callbacks.
                 *
                 * @var array
                 */
                protected static $schema_callbacks = array();

                /**
                 * Registers a schema generator callback.
                 *
                 * @param callable $callback Schema generator callback.
                 */
                public static function register_schema( $callback ) {
                        if ( ! is_callable( $callback ) ) {
                                return;
                        }

                        if ( ! in_array( $callback, self::$schema_callbacks, true ) ) {
                                self::$schema_callbacks[] = $callback;
                        }
                }

                /**
                 * Outputs JSON-LD script tags to the head.
                 */
                public static function render() {
                        if ( is_admin() ) {
                                return;
                        }

                        $schemas = array();
                        foreach ( self::get_schema_callbacks() as $callback ) {
                                $schema = call_user_func( $callback );
                                if ( ! empty( $schema ) ) {
                                        $schemas[] = $schema;
                                }
                        }

                        if ( empty( $schemas ) ) {
                                return;
                        }

                        foreach ( $schemas as $schema ) {
                                // JSON_HEX_TAG / JSON_HEX_AMP write the characters "<", ">" and "&" as JSON
                                // unicode escapes (backslash-u003C, -u003E, -u0026), so no value (including
                                // ones added through filters) can close the <script> element or be read as an
                                // HTML entity. Decoded JSON data is unchanged.
                                echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                }

                /**
                 * Returns the registered schema callbacks.
                 *
                 * @return array
                 */
                protected static function get_schema_callbacks() {
                        $callbacks = self::$schema_callbacks;

                        /**
                         * Filters the callbacks used to build JSON-LD schemas.
                         *
                         * @param array $callbacks Registered callbacks.
                         */
                        return apply_filters( 'yzrh_structured_data_schema_callbacks', $callbacks );
                }

		/**
		 * Provides a short text summary for the given post.
		 *
		 * JSON-LD uses only a manually entered excerpt, read from the raw
		 * field: generating one would run the full `the_content` filter chain
		 * inside wp_head for every listed post (shortcodes, view counters and
		 * other plugin side effects). Pass $allow_generated = true where a
		 * generated summary is wanted (OGP on a single page). Password-
		 * protected posts never expose a summary.
		 *
		 * @param int  $post_id         Post ID.
		 * @param bool $allow_generated Fall back to WordPress' generated excerpt.
		 * @return string Plain text, or '' when there is no summary.
		 */
		public static function get_post_description( $post_id, $allow_generated = false ) {
			if ( post_password_required( $post_id ) ) {
				return '';
			}

			if ( has_excerpt( $post_id ) ) {
				return self::sanitize_text( get_post_field( 'post_excerpt', $post_id ) );
			}

			if ( ! $allow_generated ) {
				return '';
			}

			return wp_trim_words( self::sanitize_text( get_the_excerpt( $post_id ) ), 55 );
		}

		/**
		 * Returns how many items precede the first post of the current page.
		 *
		 * ListItem positions are numbered from this offset by counting only
		 * the items actually emitted, so skipped posts leave no gaps. An
		 * explicit `offset` query var is honoured (WordPress ignores `paged`
		 * when `offset` is set). Sticky posts prepended to the first home
		 * page can still overlap with page 2; that comes from the query itself.
		 *
		 * @param WP_Query $query Query object.
		 * @return int
		 */
		public static function get_list_start_offset( $query ) {
			$explicit_offset = $query->get( 'offset', '' );
			if ( '' !== $explicit_offset && null !== $explicit_offset ) {
				return max( 0, (int) $explicit_offset );
			}

			$paged    = max( 1, (int) $query->get( 'paged', 1 ) );
			$per_page = (int) $query->get( 'posts_per_page', count( $query->posts ) );

			return $per_page > 0 ? ( $paged - 1 ) * $per_page : 0;
		}

		/**
		 * Returns the schema.org ItemListOrder matching the query's sort order.
		 *
		 * Reads the query itself, so `?order=ASC` on the interview archive
		 * (applied in pre_get_posts) and any project-specific ordering are
		 * described correctly. Random ordering is reported as unordered.
		 *
		 * @param WP_Query $query Query object.
		 * @return string schema.org ItemListOrderType URL.
		 */
		public static function get_item_list_order( $query ) {
			$orderby = $query->get( 'orderby', '' );
			if ( is_string( $orderby ) && 0 === strpos( strtolower( $orderby ), 'rand' ) ) {
				return 'https://schema.org/ItemListUnordered';
			}

			$order = $query->get( 'order', '' );
			$order = is_string( $order ) ? strtoupper( trim( $order ) ) : '';

			// WP_Query sorts DESC unless ASC is requested.
			return 'ASC' === $order ? 'https://schema.org/ItemListOrderAscending' : 'https://schema.org/ItemListOrderDescending';
		}

		/**
		 * Normalizes arbitrary text for JSON-LD usage.
		 *
		 * WordPress stores or displays many values entity-encoded (blog name,
		 * term names, display names, wptexturize'd titles such as `&#8217;`).
		 * JSON-LD needs plain text, so every string goes through this single
		 * function: decode all entities first, then strip tags, so encoded
		 * markup like `&lt;script&gt;` cannot survive as a tag.
		 *
		 * @param string $text Raw text.
		 * @return string
		 */
		public static function sanitize_text( $text ) {
			$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = wp_strip_all_tags( $text );

			return trim( $text );
		}

                /**
                 * Retrieves the primary category for breadcrumb trails.
                 *
                 * @param int $post_id Post ID.
                 * @return WP_Term|null
                 */
                public static function get_primary_category( $post_id ) {
                        $categories = get_the_category( $post_id );
                        if ( empty( $categories ) ) {
                                return null;
                        }

                        $primary = $categories[0];

                        /**
                         * Filters the primary category used in structured data breadcrumbs.
                         *
                         * @param WP_Term $primary Primary category.
                         * @param int     $post_id Post ID.
                         */
                        return apply_filters( 'yzrh_structured_data_primary_category', $primary, $post_id );
                }

		/**
		 * Returns author information for Article schemas or profile pages.
		 *
		 * @param int|null $post_id   Post ID when resolving the author from a post.
		 * @param int|null $author_id Optional author ID when no post context is available.
		 * @return array
		 */
		public static function get_author_schema( $post_id = null, $author_id = null ) {
			if ( null === $author_id && $post_id ) {
				$author_id = get_post_field( 'post_author', $post_id );
			}

			if ( ! $author_id ) {
				return array();
			}

			$schema = array(
				'@type' => 'Person',
				'name'  => self::sanitize_text( get_the_author_meta( 'display_name', $author_id ) ),
			);

			$profile_url = get_author_posts_url( $author_id );
			if ( $profile_url ) {
				$schema['url'] = $profile_url;
			}

			$bio = get_the_author_meta( 'description', $author_id );
			if ( $bio ) {
				$schema['description'] = self::sanitize_text( $bio );
			}

			/*
			 * No `image`: get_avatar_url() usually returns a Gravatar URL that
			 * embeds a hash of the author's e-mail address (AGENTS.md 3-4).
			 * Projects with self-hosted author photos can add one through the
			 * `yzrh_structured_data_author` filter below.
			 */

			$same_as = self::get_author_same_as_profiles( $author_id );
			if ( ! empty( $same_as ) ) {
				$schema['sameAs'] = $same_as;
			}

			return apply_filters( 'yzrh_structured_data_author', $schema, $post_id, $author_id );
		}

                /**
                 * Returns the publisher structure for articles and services.
                 *
                 * @return array
                 */
                public static function get_publisher_schema() {
                        $schema = array(
                                '@type' => 'Organization',
                                'name'  => self::sanitize_text( get_bloginfo( 'name' ) ),
                                'url'   => home_url( '/' ),
                        );

                        $logo = self::get_logo_object();
                        if ( $logo ) {
                                $schema['logo'] = $logo;
                        }

                        return apply_filters( 'yzrh_structured_data_publisher', $schema );
                }

                /**
                 * Returns an ImageObject representing the site logo.
                 *
                 * @return array|null
                 */
                public static function get_logo_object() {
                        $logo_id = get_theme_mod( 'custom_logo' );
                        if ( $logo_id ) {
                                $image = wp_get_attachment_image_src( $logo_id, 'full' );
                                if ( $image ) {
                                        return array(
                                                '@type'  => 'ImageObject',
                                                'url'    => $image[0],
                                                'width'  => isset( $image[1] ) ? (int) $image[1] : null,
                                                'height' => isset( $image[2] ) ? (int) $image[2] : null,
                                        );
                                }
                        }

                        $site_icon = get_site_icon_url();
                        if ( $site_icon ) {
                                return array(
                                        '@type' => 'ImageObject',
                                        'url'   => $site_icon,
                                );
                        }

                        return null;
                }

		/**
		 * Returns an ImageObject for the post thumbnail, if available.
		 *
		 * @param int $post_id Post ID.
		 * @return array|null
		 */
		public static function get_post_image_object( $post_id ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( ! $thumbnail_id ) {
				return null;
			}

			$image = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			if ( ! $image ) {
				return null;
			}

			return array(
				'@type'  => 'ImageObject',
				'url'    => $image[0],
				'width'  => isset( $image[1] ) ? (int) $image[1] : null,
				'height' => isset( $image[2] ) ? (int) $image[2] : null,
			);
		}

		/**
		 * Determines whether the current request displays a blog list/archive.
		 *
		 * @return bool
		 */
		public static function is_blog_listing_context() {
			if ( is_admin() || is_author() ) {
				return false;
			}

			if ( is_home() || is_post_type_archive( 'post' ) ) {
				return true;
			}

			if ( is_category() || is_tag() || is_date() ) {
				return true;
			}

			return false;
		}

		/**
		 * Determines whether the current page represents a service.
		 *
		 * @return bool
		 */
		public static function is_service_page() {
			if ( ! is_page() ) {
				return false;
			}

                        $post_id = get_queried_object_id();
                        if ( ! $post_id ) {
                                return false;
                        }

                        $is_service = false;

                        $slug = get_post_field( 'post_name', $post_id );
                        if ( 'services' === $slug ) {
                                $is_service = true;
                        }

                        $template = get_page_template_slug( $post_id );
                        if ( ! $is_service && $template && false !== strpos( $template, 'service' ) ) {
                                $is_service = true;
                        }

			/**
			 * Filters whether the current page should be treated as a service page.
			 *
			 * @param bool $is_service Detected state.
			 * @param int  $post_id    Page ID.
			 */
			return (bool) apply_filters( 'yzrh_structured_data_is_service_page', $is_service, $post_id );
		}

		/**
		 * Collects social profile links for inclusion in sameAs arrays.
		 *
		 * @param int $author_id Author ID.
		 * @return array
		 */
		protected static function get_author_same_as_profiles( $author_id ) {
			$profiles = array();

			$website = get_the_author_meta( 'user_url', $author_id );
			if ( $website ) {
				$profiles[] = esc_url_raw( $website );
			}

			$social_keys = array( 'facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest', 'tiktok' );
			foreach ( $social_keys as $key ) {
				$link = get_user_meta( $author_id, $key, true );
				if ( $link ) {
					$profiles[] = esc_url_raw( $link );
				}
			}

			$profiles = array_filter( $profiles );
			$profiles = array_unique( $profiles );

			return array_values( $profiles );
		}
	}
}
/**
 * WebSite schema generator.
 */

if ( ! function_exists( 'yzrh_structured_data_schema_website' ) ) {
        function yzrh_structured_data_schema_website() {
                $site_name = YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) );
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
/**
 * Organization schema generator.
 */

if ( ! function_exists( 'yzrh_structured_data_schema_organization' ) ) {
        function yzrh_structured_data_schema_organization() {
                $site_name = YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) );
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
/**
 * Article schema generator.
 */

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
                        'datePublished'    => get_the_date( DATE_W3C, $post_id ),
                        'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
                        'author'           => YZRH_Structured_Data_Generator::get_author_schema( $post_id ),
                        'publisher'        => YZRH_Structured_Data_Generator::get_publisher_schema(),
                        'url'              => $permalink,
                        'isPartOf'         => array(
                                '@type' => 'Blog',
                                'name'  => YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) ),
                                'url'   => home_url( '/' ),
                        ),
                );

                $description = YZRH_Structured_Data_Generator::get_post_description( $post_id );
                if ( '' !== $description ) {
                        $schema['description'] = $description;
                }

                $primary_category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
                if ( $primary_category ) {
                        $schema['articleSection'] = YZRH_Structured_Data_Generator::sanitize_text( $primary_category->name );
                }

                $image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
                if ( $image ) {
                        $schema['image'] = $image;
                }

                return apply_filters( 'yzrh_structured_data_article', $schema, $post_id );
        }
}
/**
 * Service schema generator.
 */

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
                        'name'        => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
                        'serviceType' => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post_id ) ),
                        'provider'    => YZRH_Structured_Data_Generator::get_publisher_schema(),
                );

                $description = YZRH_Structured_Data_Generator::get_post_description( $post_id );
                if ( '' !== $description ) {
                        $schema['description'] = $description;
                }

                $image = YZRH_Structured_Data_Generator::get_post_image_object( $post_id );
                if ( $image ) {
                        $schema['image'] = $image;
                }

                return apply_filters( 'yzrh_structured_data_service', $schema, $post_id );
        }
}
/**
 * Blog and blog listing schema generators.
 */

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

                $items    = array();
                $posts    = $wp_query->posts;
                $paged    = max( 1, (int) $wp_query->get( 'paged', 1 ) );
                $position = YZRH_Structured_Data_Generator::get_list_start_offset( $wp_query );

                foreach ( $posts as $post ) {
                        if ( 'post' !== get_post_type( $post ) ) {
                                continue;
                        }

                        $position++;
                        $item = array(
                                '@type'    => 'ListItem',
                                'position' => $position,
                                'url'      => get_permalink( $post ),
                                'name'     => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
                        );

                        $description = YZRH_Structured_Data_Generator::get_post_description( $post->ID );
                        if ( '' !== $description ) {
                                $item['description'] = $description;
                        }

                        $items[] = $item;
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
                        'url'            => get_pagenum_link( $paged, false ),
                        'itemListOrder'  => YZRH_Structured_Data_Generator::get_item_list_order( $wp_query ),
                        'numberOfItems'  => count( $items ),
                        'itemListElement'=> array_values( $items ),
                );

                if ( $archive_description ) {
                        $schema['description'] = YZRH_Structured_Data_Generator::sanitize_text( $archive_description );
                }

                return apply_filters( 'yzrh_structured_data_blog_itemlist', $schema, $wp_query );
        }
}
/**
 * Author profile schema generator.
 */

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
/**
 * Interview archive CollectionPage schema generator.
 */

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
				'name'  => YZRH_Structured_Data_Generator::sanitize_text( get_bloginfo( 'name' ) ),
				'url'   => home_url( '/' ),
			),
		);

		global $wp_query;
		if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
			$items    = array();
			$position = YZRH_Structured_Data_Generator::get_list_start_offset( $wp_query );

			foreach ( $wp_query->posts as $post ) {
				if ( 'interview' !== get_post_type( $post ) ) {
					continue;
				}

				$position++;
				$item = array(
					'@type'    => 'ListItem',
					'position' => $position,
					'url'      => get_permalink( $post ),
					'name'     => YZRH_Structured_Data_Generator::sanitize_text( get_the_title( $post ) ),
				);

				$description = YZRH_Structured_Data_Generator::get_post_description( $post->ID );
				if ( '' !== $description ) {
					$item['description'] = $description;
				}

				$items[] = $item;
			}

			if ( ! empty( $items ) ) {
				$schema['mainEntity'] = array(
					'@type'           => 'ItemList',
					'itemListOrder'   => YZRH_Structured_Data_Generator::get_item_list_order( $wp_query ),
					'numberOfItems'   => count( $items ),
					'itemListElement' => array_values( $items ),
				);
			}
		}

		return apply_filters( 'yzrh_structured_data_interview_collection', $schema );
	}
}
/**
 * JSON-LD compatibility function layer.
 */

if ( ! function_exists( 'yzrh_jsonld_blogposting' ) ) {
	/**
	 * Returns BlogPosting schema for single post pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blogposting() {
		return yzrh_structured_data_schema_article();
	}
}

if ( ! function_exists( 'yzrh_jsonld_blog_itemlist' ) ) {
	/**
	 * Returns ItemList schema for blog list/archive pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_blog_itemlist() {
		return yzrh_structured_data_schema_blog_itemlist();
	}
}

if ( ! function_exists( 'yzrh_jsonld_author_person' ) ) {
	/**
	 * Returns Person schema for author archive pages.
	 *
	 * @return array|null
	 */
	function yzrh_jsonld_author_person() {
		return yzrh_structured_data_schema_author_person();
	}
}

if ( ! function_exists( 'yzrh_output_jsonld' ) ) {
	/**
	 * Outputs all registered JSON-LD schemas to wp_head.
	 *
	 * @return void
	 */
function yzrh_output_jsonld() {
		YZRH_Structured_Data_Generator::render();
	}
}

/**
 * Builds the single breadcrumb source used by HTML and JSON-LD.
 *
 * @return array<int,array{name:string,url:string}>
 */
function yzrh_get_breadcrumb_items() {
	$items = array( array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );

	if ( is_front_page() ) {
		return apply_filters( 'yzrh_breadcrumb_items', $items );
	}

	if ( is_singular() ) {
		$post_id   = get_queried_object_id();
		$post_type = get_post_type( $post_id );
		if ( 'post' === $post_type ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$items[] = array( 'name' => get_the_title( $posts_page ), 'url' => get_permalink( $posts_page ) );
			}
			$category = YZRH_Structured_Data_Generator::get_primary_category( $post_id );
			if ( $category ) {
				$link = get_term_link( $category );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( 'name' => $category->name, 'url' => $link );
				}
			}
		} elseif ( 'page' !== $post_type ) {
			$object = get_post_type_object( $post_type );
			$link   = get_post_type_archive_link( $post_type );
			if ( $object && $link ) {
				$items[] = array( 'name' => $object->labels->name, 'url' => $link );
			}
		}
		foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor_id ) {
			$items[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
		}
		$items[] = array( 'name' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) );
	} elseif ( is_category() || is_tax() || is_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				$link     = get_term_link( $ancestor );
				if ( ! is_wp_error( $link ) ) {
					$items[] = array( 'name' => $ancestor->name, 'url' => $link );
				}
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array( 'name' => $term->name, 'url' => $link );
			}
		}
	} elseif ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$object    = get_post_type_object( $post_type );
		if ( $object ) {
			$items[] = array( 'name' => $object->labels->name, 'url' => get_post_type_archive_link( $post_type ) );
		}
	} elseif ( is_search() ) {
		$items[] = array( 'name' => sprintf( __( 'Search results for "%s"', 'yzrh' ), get_search_query() ), 'url' => get_search_link() );
	} elseif ( is_404() ) {
		$items[] = array( 'name' => __( 'Page not found', 'yzrh' ), 'url' => home_url( add_query_arg( array(), $GLOBALS['wp']->request ) ) );
	} elseif ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		$items[] = array( 'name' => $posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'yzrh' ), 'url' => $posts_page ? get_permalink( $posts_page ) : home_url( '/' ) );
	}

	return apply_filters( 'yzrh_breadcrumb_items', $items );
}

/** Outputs accessible HTML breadcrumbs. */
function yzrh_breadcrumb() {
	$items = yzrh_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}
	echo '<nav class="yzrh-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'yzrh' ) . '"><ol>';
	foreach ( $items as $index => $item ) {
		$is_last = count( $items ) - 1 === $index;
		echo '<li>';
		if ( $is_last ) {
			echo '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>';
		} else {
			echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

/** Returns BreadcrumbList generated from the HTML breadcrumb source. */
function yzrh_structured_data_schema_breadcrumbs() {
	$list = array();
	foreach ( yzrh_get_breadcrumb_items() as $index => $item ) {
		$list[] = array( '@type' => 'ListItem', 'position' => $index + 1, 'name' => YZRH_Structured_Data_Generator::sanitize_text( $item['name'] ), 'item' => esc_url_raw( $item['url'] ) );
	}
	return array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list );
}

/** Provides filterable Open Graph values before they are rendered. */
function yzrh_get_ogp_tags() {
	$post_id = get_queried_object_id();
	$tags = array(
		'og:site_name' => get_bloginfo( 'name' ),
		'og:title'     => wp_get_document_title(),
		'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
		'og:url'       => is_singular() ? get_permalink( $post_id ) : yzrh_get_current_list_url(),
		'og:locale'    => str_replace( '_', '-', get_locale() ),
	);
	if ( is_singular() ) {
		$tags['og:description'] = YZRH_Structured_Data_Generator::get_post_description( $post_id, true );
		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) { $tags['og:image'] = $image; }
	}
	return apply_filters( 'yzrh_ogp_tags', array_filter( $tags ) );
}

/** Renders OGP metadata through the yzrh_ogp_tags extension hook. */
function yzrh_output_ogp() {
	foreach ( yzrh_get_ogp_tags() as $property => $content ) {
		printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
	}
}
add_action( 'wp_head', 'yzrh_output_ogp', 4 );

/**
 * Returns the canonical URL for the interview archive.
 *
 * The archive accepts `?order=ASC|DESC`, which renders the same posts in a
 * different order. The canonical always points at the plain archive URL for
 * the current page number, so sort variants and tracking parameters are not
 * indexed as separate pages. Like every theme-generated list URL it has no
 * trailing slash (see yzrh_get_current_list_url()).
 *
 * @return string Canonical URL, or '' when none should be output.
 */
function yzrh_get_interview_archive_canonical() {
	if ( ! is_post_type_archive( 'interview' ) ) {
		return '';
	}

	$url = get_post_type_archive_link( 'interview' );
	if ( ! $url ) {
		return '';
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		global $wp_rewrite;

		// Same construction as get_pagenum_link(): the configured pagination
		// base (`$wp_rewrite->pagination_base`, e.g. "page" or a translated
		// slug) with pretty permalinks, `?paged=` otherwise.
		if ( $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			$url = trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
		} else {
			$url = add_query_arg( 'paged', $paged, untrailingslashit( $url ) );
		}
	}

	return (string) apply_filters( 'yzrh_interview_archive_canonical', untrailingslashit( $url ) );
}

/**
 * Returns the URL of the current non-singular (list) page for og:url.
 *
 * The interview archive reuses its canonical so both always match. Other
 * lists use the requested path without query string. The theme writes list
 * URLs without a trailing slash; set the permalink structure without a
 * trailing slash (e.g. `/%postname%`) so WordPress does not redirect them.
 *
 * @return string
 */
function yzrh_get_current_list_url() {
	$canonical = yzrh_get_interview_archive_canonical();
	if ( '' !== $canonical ) {
		return $canonical;
	}

	$request = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ? (string) $GLOBALS['wp']->request : '';

	return untrailingslashit( home_url( $request ) );
}

/**
 * Prints the interview archive canonical link.
 *
 * WordPress core prints canonical links only for singular views, so the theme
 * covers this archive. Skipped when a major SEO plugin manages canonicals.
 */
function yzrh_output_interview_archive_canonical() {
	$seo_plugin_active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	if ( apply_filters( 'yzrh_output_archive_canonical', ! $seo_plugin_active ) ) {
		$url = yzrh_get_interview_archive_canonical();
		if ( '' !== $url ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
		}
	}
}
add_action( 'wp_head', 'yzrh_output_interview_archive_canonical', 4 );

/* WordPress core owns title-tag, singular canonical output, 404 status and pagination links. */

/**
 * Registers the JSON-LD schema callbacks for the current request.
 *
 * Runs on the `wp` action (after the main query is set up, before
 * `template_redirect` and `wp_head`) instead of when this file is loaded, so
 * `yzrh_jsonld_schema_map`, `yzrh_core_default_settings` and the stored
 * settings may be changed from plugins, child themes, `after_setup_theme`,
 * `init` or `wp` (priority below 10), and callbacks defined after the theme
 * loads still pass the is_callable() check.
 */
function yzrh_register_structured_data_schemas() {
	$schema_map = apply_filters( 'yzrh_jsonld_schema_map', array(
		'seo_jsonld_website'              => 'yzrh_structured_data_schema_website',
		'seo_jsonld_organization'         => 'yzrh_structured_data_schema_organization',
		'seo_jsonld_breadcrumbs'          => 'yzrh_structured_data_schema_breadcrumbs',
		'seo_jsonld_blogposting'          => 'yzrh_jsonld_blogposting',
		'seo_jsonld_service'              => 'yzrh_structured_data_schema_service',
		'seo_jsonld_blog'                 => 'yzrh_structured_data_schema_blog',
		'seo_jsonld_blog_itemlist'        => 'yzrh_jsonld_blog_itemlist',
		'seo_jsonld_author'               => 'yzrh_jsonld_author_person',
		'seo_jsonld_interview_collection' => 'yzrh_structured_data_schema_interview_collection',
	) );

	foreach ( (array) $schema_map as $setting => $callback ) {
		if ( yzrh_get_setting( $setting, true ) ) {
			YZRH_Structured_Data_Generator::register_schema( $callback );
		}
	}

	if ( yzrh_get_setting( 'seo_jsonld_enabled', true ) ) {
		add_action( 'wp_head', 'yzrh_output_jsonld', 5 );
	}
}
add_action( 'wp', 'yzrh_register_structured_data_schemas' );
