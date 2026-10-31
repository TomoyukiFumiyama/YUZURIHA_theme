<?php
/**
 * Minimal WordPress stubs for running theme regression tests without a
 * WordPress install or database:  php tests/run.php
 *
 * Each stub implements only the behaviour the tests rely on. State that a test
 * needs to control lives in $GLOBALS['yzrh_test'].
 *
 * @package yzrh
 */

// Never runnable over HTTP, even if tests/ is deployed with the theme.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'YZRH_THEME_DIR', dirname( __DIR__ ) );

$GLOBALS['yzrh_test'] = array();

/** Resets per-test state. */
function yzrh_test_reset( array $state = array() ) {
	$GLOBALS['yzrh_test'] = array_merge(
		array(
			'filters'            => array(),
			'meta'               => array(),
			'theme_mods'         => array(),
			'options'            => array(),
			'bloginfo'           => array(
				'name'        => 'Example Site',
				'description' => 'Tagline',
				'charset'     => 'UTF-8',
				'version'     => '6.6.2',
				'language'    => 'ja',
			),
			'conditionals'       => array(),
			'password_required'  => false,
			'has_post_thumbnail' => false,
			'template_dir'       => YZRH_THEME_DIR,
			'loop'               => 0,
			'caps'               => array(),
		),
		$state
	);
}
yzrh_test_reset();

// Hooks.
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['yzrh_test']['filters'][ $tag ][] = $callback;
	return true;
}
function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $tag, $callback, $priority, $args );
}
function remove_action( $tag = '', $callback = '', $priority = 10 ) {
	$GLOBALS['yzrh_test']['removed'][] = array( $tag, $callback );
	return true;
}
function do_action( $tag, ...$args ) {
	$callbacks = isset( $GLOBALS['yzrh_test']['filters'][ $tag ] ) ? $GLOBALS['yzrh_test']['filters'][ $tag ] : array();
	foreach ( $callbacks as $callback ) {
		call_user_func_array( $callback, $args );
	}
}
function __return_true() {
	return true;
}
function __return_false() {
	return false;
}
function __return_empty_array() {
	return array();
}
function __return_empty_string() {
	return '';
}
function apply_filters( $tag, $value, ...$args ) {
	$callbacks = isset( $GLOBALS['yzrh_test']['filters'][ $tag ] ) ? $GLOBALS['yzrh_test']['filters'][ $tag ] : array();
	foreach ( $callbacks as $callback ) {
		if ( is_callable( $callback ) ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
	}
	return $value;
}

// Escaping and sanitizing (simplified but faithful where tests depend on it).
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_url( $s ) {
	return esc_html( $s );
}
function esc_url_raw( $s ) {
	return (string) $s;
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function wp_kses( $s, $allowed ) {
	return (string) $s;
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_html_class( $s ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s );
}
function wp_strip_all_tags( $s ) {
	$s = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s );
	return trim( strip_tags( $s ) );
}
function wp_trim_words( $s, $n = 55 ) {
	return (string) $s;
}
function wp_json_encode( $data, $flags = 0 ) {
	return json_encode( $data, $flags );
}
function absint( $value ) {
	return abs( (int) $value );
}
function is_ssl() {
	return ! empty( $GLOBALS['yzrh_test']['ssl'] );
}
function wp_hash( $data ) {
	return md5( 'test-salt' . $data );
}

// i18n.
function __( $s, $d = '' ) {
	return $s;
}
function esc_html__( $s, $d = '' ) {
	return esc_html( $s );
}
function esc_attr__( $s, $d = '' ) {
	return esc_attr( $s );
}
function esc_html_e( $s, $d = '' ) {
	echo esc_html( $s );
}
function esc_attr_e( $s, $d = '' ) {
	echo esc_attr( $s );
}
function _n( $single, $plural, $n, $d = '' ) {
	return 1 === (int) $n ? $single : $plural;
}

// Site and theme.
function get_bloginfo( $key = 'name' ) {
	return isset( $GLOBALS['yzrh_test']['bloginfo'][ $key ] ) ? $GLOBALS['yzrh_test']['bloginfo'][ $key ] : '';
}
function bloginfo( $key = 'name' ) {
	echo esc_html( get_bloginfo( $key ) );
}
function home_url( $path = '' ) {
	// Same joining rule as core: a non-empty path is appended after a single "/".
	return 'https://example.test' . ( '' !== (string) $path ? '/' . ltrim( $path, '/' ) : '' );
}
function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['yzrh_test']['options'] ) ? $GLOBALS['yzrh_test']['options'][ $key ] : $default;
}
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_string( $args ) ) {
		parse_str( $args, $parsed );
		$args = $parsed;
	}
	return array_merge( $defaults, (array) $args );
}
function get_site_icon_url() {
	return '';
}
function get_theme_mod( $key, $default = false ) {
	return isset( $GLOBALS['yzrh_test']['theme_mods'][ $key ] ) ? $GLOBALS['yzrh_test']['theme_mods'][ $key ] : $default;
}
function current_user_can( $capability, ...$args ) {
	return in_array( $capability, $GLOBALS['yzrh_test']['caps'], true );
}
function get_template_directory() {
	return $GLOBALS['yzrh_test']['template_dir'];
}
function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/yzrh';
}
function get_template() {
	return 'yzrh';
}
function wp_get_theme( $stylesheet = null ) {
	return new class() {
		public function get( $key ) {
			return '1.0.0';
		}
	};
}
function wp_date( $format ) {
	return gmdate( $format );
}

// Conditionals: any is_*() listed in $GLOBALS['yzrh_test']['conditionals'] returns true.
foreach ( array( 'is_admin', 'is_author', 'is_home', 'is_front_page', 'is_category', 'is_tag', 'is_date', 'is_page', 'is_singular', 'is_tax', 'is_search', 'is_404', 'is_post_type_archive', 'is_page_template', 'is_customize_preview' ) as $yzrh_conditional ) {
	eval( 'function ' . $yzrh_conditional . '( ...$args ) { return in_array( "' . $yzrh_conditional . '", $GLOBALS["yzrh_test"]["conditionals"], true ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}
unset( $yzrh_conditional );

// Posts and loop.
function post_password_required( $post = null ) {
	return (bool) $GLOBALS['yzrh_test']['password_required'];
}
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['yzrh_test']['meta'][ $key ] ) ? $GLOBALS['yzrh_test']['meta'][ $key ] : '';
}
function get_the_ID() {
	return 1;
}
function get_queried_object_id() {
	return 1;
}
function have_posts() {
	return $GLOBALS['yzrh_test']['loop'] < 1;
}
function the_post() {
	$GLOBALS['yzrh_test']['loop']++;
}
function the_ID() {
	echo 1;
}
function get_the_title( $post = 0 ) {
	if ( is_int( $post ) && isset( $GLOBALS['yzrh_test']['titles'][ $post ] ) ) {
		return $GLOBALS['yzrh_test']['titles'][ $post ];
	}
	return isset( $GLOBALS['yzrh_test']['title'] ) ? $GLOBALS['yzrh_test']['title'] : 'Post Title';
}
function the_title( $before = '', $after = '' ) {
	echo $before . get_the_title() . $after; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
function get_the_excerpt( $post = 0 ) {
	$GLOBALS['yzrh_test']['calls']['get_the_excerpt'] = ( isset( $GLOBALS['yzrh_test']['calls']['get_the_excerpt'] ) ? $GLOBALS['yzrh_test']['calls']['get_the_excerpt'] : 0 ) + 1;
	return isset( $GLOBALS['yzrh_test']['excerpt'] ) ? $GLOBALS['yzrh_test']['excerpt'] : 'Excerpt';
}
function has_excerpt( $post = 0 ) {
	return ! empty( $GLOBALS['yzrh_test']['manual_excerpt'] );
}
function get_post_field( $field, $post = 0 ) {
	if ( 'post_excerpt' === $field ) {
		return isset( $GLOBALS['yzrh_test']['manual_excerpt'] ) ? $GLOBALS['yzrh_test']['manual_excerpt'] : '';
	}
	return 'Body';
}
function get_post_thumbnail_id( $post = null ) {
	return isset( $GLOBALS['yzrh_test']['thumbnail_id'] ) ? $GLOBALS['yzrh_test']['thumbnail_id'] : 0;
}
function get_post_ancestors( $post ) {
	return isset( $GLOBALS['yzrh_test']['ancestors'] ) ? $GLOBALS['yzrh_test']['ancestors'] : array();
}
// Posts listed in 'non_public_posts' are private / draft / trashed.
function is_post_publicly_viewable( $post = null ) {
	return ! in_array( $post, isset( $GLOBALS['yzrh_test']['non_public_posts'] ) ? $GLOBALS['yzrh_test']['non_public_posts'] : array(), true );
}
function status_header( $code ) {
	$GLOBALS['yzrh_test']['calls']['status_header'] = $code;
}
function nocache_headers() {
	$GLOBALS['yzrh_test']['calls']['nocache_headers'] = true;
}

// Taxonomies / archive helpers used by archive-interview.php.
function get_object_taxonomies( $object_type, $output = 'names' ) {
	return isset( $GLOBALS['yzrh_test']['object_taxonomies'] ) ? $GLOBALS['yzrh_test']['object_taxonomies'] : array();
}
function get_terms( $args ) {
	$terms = isset( $GLOBALS['yzrh_test']['terms'][ $args['taxonomy'] ] ) ? $GLOBALS['yzrh_test']['terms'][ $args['taxonomy'] ] : array();
	return isset( $args['number'] ) ? array_slice( $terms, 0, $args['number'] ) : $terms;
}
function wp_count_terms( $args = array() ) {
	return isset( $GLOBALS['yzrh_test']['terms'][ $args['taxonomy'] ] ) ? (string) count( $GLOBALS['yzrh_test']['terms'][ $args['taxonomy'] ] ) : '0';
}
function get_the_terms( $post, $taxonomy ) {
	$terms = isset( $GLOBALS['yzrh_test']['terms'][ $taxonomy ] ) ? $GLOBALS['yzrh_test']['terms'][ $taxonomy ] : array();
	return $terms ? array( $terms[0] ) : false;
}
function get_term_link( $term ) {
	return 'https://example.test/' . $term->taxonomy . '/' . $term->slug . '/';
}
function is_wp_error( $thing ) {
	return false;
}
function get_lastpostdate( $timezone = 'server', $post_type = 'any' ) {
	return isset( $GLOBALS['yzrh_test']['lastpostdate'] ) ? $GLOBALS['yzrh_test']['lastpostdate'] : false;
}
function mysql2date( $format, $date ) {
	return gmdate( $format, strtotime( $date ) );
}
function post_type_archive_title( $prefix = '', $display = true ) {
	return 'Interviews';
}
function has_nav_menu( $location ) {
	return false;
}
function wp_nav_menu( $args = array() ) {}
function number_format_i18n( $number ) {
	return number_format( (float) $number );
}
function selected( $a, $b, $display = true ) {
	$out = (string) $a === (string) $b ? ' selected="selected"' : '';
	if ( $display ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	return $out;
}
function paginate_links( $args = array() ) {
	return '';
}
function get_the_privacy_policy_link() {
	return '';
}
function get_the_post_thumbnail( $post = null, $size = '' ) {
	return '';
}
function get_the_author_meta( $field = '', $user_id = 0 ) {
	return 'Author';
}

// Asset registration (records calls for inspection).
foreach ( array( 'wp_register_style', 'wp_register_script', 'wp_enqueue_style', 'wp_enqueue_script' ) as $yzrh_asset_fn ) {
	eval( 'function ' . $yzrh_asset_fn . '( $handle, ...$args ) { $GLOBALS["yzrh_test"]["assets"]["' . $yzrh_asset_fn . '"][ $handle ] = $args; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}
unset( $yzrh_asset_fn );
function get_permalink( $post = 0 ) {
	return 'https://example.test/post/';
}
function get_post_type( $post = null ) {
	if ( $post instanceof WP_Post && $post->post_type ) {
		return $post->post_type;
	}
	return isset( $GLOBALS['yzrh_test']['post_type'] ) ? $GLOBALS['yzrh_test']['post_type'] : 'post';
}
function get_post_type_archive_link( $post_type ) {
	return 'https://example.test/interviews/';
}
function get_query_var( $key, $default = '' ) {
	return isset( $GLOBALS['yzrh_test']['query_vars'][ $key ] ) ? $GLOBALS['yzrh_test']['query_vars'][ $key ] : $default;
}
function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}
function user_trailingslashit( $value, $type = '' ) {
	return trailingslashit( $value );
}
function wp_unslash( $value ) {
	return $value;
}
function untrailingslashit( $value ) {
	return rtrim( $value, '/\\' );
}

// Theme setup / blocks / OGP / author helpers.
function add_theme_support( $feature, ...$args ) {
	$GLOBALS['yzrh_test']['theme_support'][ $feature ] = $args ? $args[0] : true;
}
function load_theme_textdomain( $domain, $path = '' ) {
	return true;
}
function register_nav_menus( $locations ) {}
function register_block_type( $name, $args = array() ) {
	$GLOBALS['yzrh_test']['blocks'][ $name ] = $args;
}
function wp_get_document_title() {
	return 'Document Title';
}
function get_locale() {
	return isset( $GLOBALS['yzrh_test']['locale'] ) ? $GLOBALS['yzrh_test']['locale'] : 'ja';
}
function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
	return isset( $GLOBALS['yzrh_test']['attachments'][ $attachment_id ] ) ? $GLOBALS['yzrh_test']['attachments'][ $attachment_id ] : false;
}
function get_the_post_thumbnail_url( $post = null, $size = '' ) {
	return isset( $GLOBALS['yzrh_test']['thumbnail_url'] ) ? $GLOBALS['yzrh_test']['thumbnail_url'] : false;
}
function get_author_posts_url( $author_id ) {
	return 'https://example.test/author/a';
}
function get_user_meta( $user_id, $key = '', $single = false ) {
	return '';
}
function get_avatar_url( $id_or_email, $args = null ) {
	$GLOBALS['yzrh_test']['calls']['get_avatar_url'] = true;
	return 'https://secure.gravatar.com/avatar/0123456789abcdef?s=512';
}
function get_the_author() {
	return 'Author';
}
function get_the_date( $format = '', $post = 0 ) {
	return '2026.10';
}
function has_post_thumbnail( $post = null ) {
	return (bool) $GLOBALS['yzrh_test']['has_post_thumbnail'];
}
function the_post_thumbnail( $size = 'post-thumbnail', $attr = '' ) {
	echo '<img class="test-thumbnail" src="https://example.test/thumb.jpg">';
}
function get_pagenum_link( $pagenum = 1, $escape = true ) {
	$url = 'https://example.test/?cat=3&paged=' . (int) $pagenum;
	return $escape ? str_replace( '&', '&#038;', $url ) : $url;
}
function get_the_archive_title() {
	return 'Category: News &amp; Updates';
}
function get_the_archive_description() {
	return isset( $GLOBALS['yzrh_test']['archive_description'] ) ? $GLOBALS['yzrh_test']['archive_description'] : '';
}

// Output-only template tags.
foreach ( array( 'wp_head', 'wp_footer', 'wp_body_open', 'the_content', 'wp_link_pages', 'language_attributes', 'body_class', 'post_class' ) as $yzrh_noop ) {
	eval( 'function ' . $yzrh_noop . '( ...$args ) {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}
unset( $yzrh_noop );

/** Minimal WP_Query stand-in. */
class WP_Query {
	public $posts         = array();
	public $vars          = array();
	public $found_posts   = 0;
	public $max_num_pages = 0;
	public $is_404        = false;
	public function get( $key, $default = '' ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : $default;
	}
	public function set_404() {
		$this->is_404 = true;
	}
}

/** Minimal WP_Rewrite stand-in: pretty permalinks follow the permalink_structure option. */
class WP_Rewrite {
	public $pagination_base = 'page';
	public function using_permalinks() {
		return (bool) get_option( 'permalink_structure' );
	}
}
$GLOBALS['wp_rewrite'] = new WP_Rewrite();

/** Minimal WP_Post stand-in. */
class WP_Post {
	public $ID;
	public $post_type;
	public function __construct( $id, $post_type = 'post' ) {
		$this->ID        = $id;
		$this->post_type = $post_type;
	}
}

/** Renders a theme template and returns its output. */
function yzrh_test_render( $template ) {
	$GLOBALS['yzrh_test']['loop'] = 0;
	ob_start();
	try {
		include YZRH_THEME_DIR . '/' . $template;
	} catch ( Throwable $e ) {
		ob_end_clean();
		throw $e;
	}
	return ob_get_clean();
}
