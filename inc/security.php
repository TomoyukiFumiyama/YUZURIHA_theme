<?php
/**
 * Theme-wide security hardening helpers.
 *
 * @package yzrh
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security hardening utility class.
 */
class YZRH_Security_Hardening {
	/**
	 * Bootstrap the hardening measures.
	 */
	public static function init() {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'style_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );
		add_filter( 'script_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_x_pingback_header' ) );
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );

		add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );
		add_action( 'init', array( __CLASS__, 'disable_comment_support' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'disable_comment_support' ) );
		add_action( 'admin_menu', array( __CLASS__, 'hide_comment_admin_menu' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'hide_comment_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'hide_comment_dashboard_widget' ) );
	}

	/** Remove comment support from every registered public content type. */
	public static function disable_comment_support() {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/** Remove the Comments screen from the administration menu. */
	public static function hide_comment_admin_menu() {
		remove_menu_page( 'edit-comments.php' );
	}

	/** Remove the Comments shortcut from the administration toolbar. */
	public static function hide_comment_admin_bar() {
		global $wp_admin_bar;
		if ( $wp_admin_bar ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}
	}

	/** Remove the recent comments dashboard widget. */
	public static function hide_comment_dashboard_widget() {
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}

	/**
	 * Mask the WordPress core version in enqueued asset URLs.
	 *
	 * Only a `ver` value equal to the running core version is replaced, and it
	 * is replaced with a site-specific hash instead of being removed, so the URL
	 * still changes after a core update (cache busting keeps working). Theme and
	 * plugin versions are left untouched, and the rest of the URL is never
	 * re-encoded (duplicate parameters such as Google Fonts `family` survive).
	 *
	 * @param string $src    Asset URL.
	 * @param string $handle Asset handle.
	 * @return string
	 */
	public static function remove_asset_version( $src, $handle ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_string( $src ) || false === strpos( $src, 'ver=' ) ) {
			return $src;
		}

		$core_version = get_bloginfo( 'version' );
		if ( '' === $core_version ) {
			return $src;
		}

		$pattern = '/([?&]|&#038;|&amp;)ver=' . preg_quote( rawurlencode( $core_version ), '/' ) . '(?=&|#|$)/';

		return preg_replace( $pattern, '${1}ver=' . self::get_masked_core_version( $core_version ), $src );
	}

	/**
	 * Site-specific, non-reversible stand-in for the core version.
	 *
	 * @param string $core_version Running WordPress version.
	 * @return string
	 */
	protected static function get_masked_core_version( $core_version ) {
		static $masked = array();

		if ( ! isset( $masked[ $core_version ] ) ) {
			$masked[ $core_version ] = substr( wp_hash( 'yzrh-asset-' . $core_version ), 0, 10 );
		}

		return $masked[ $core_version ];
	}

	/**
	 * Remove the X-Pingback header from responses.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public static function remove_x_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	}

	/**
	 * Send a set of recommended security headers with each response.
	 *
	 * Allows filtering via {@see 'yzrh_security_headers'}.
	 */
	public static function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		$headers = apply_filters( 'yzrh_security_headers', self::get_default_security_headers() );

		foreach ( self::prepare_header_lines( $headers ) as $line ) {
			header( $line, true );
		}
	}

	/**
	 * Validates filtered headers and builds "Name: value" lines.
	 *
	 * Filters may return anything, so invalid entries are skipped instead of
	 * causing a fatal error: names must be HTTP token characters, values must
	 * be scalars (cast to string) without CR/LF, and empty values are dropped.
	 *
	 * @param mixed $headers Filtered header map.
	 * @return string[]
	 */
	public static function prepare_header_lines( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}

		$lines = array();
		foreach ( $headers as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name ) ) {
				continue;
			}

			if ( is_bool( $value ) || ! is_scalar( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value || preg_match( '/[\r\n\0]/', $value ) ) {
				continue;
			}

			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}


	/**
	 * Default security header map used by {@see self::send_security_headers()}.
	 *
	 * @return array<string, string>
	 */
	protected static function get_default_security_headers() {
		$headers = array();

		$hsts = self::get_hsts_header_value();
		if ( '' !== $hsts ) {
			$headers['Strict-Transport-Security'] = $hsts;
		}

		return $headers + array(
			'X-Frame-Options'                   => 'SAMEORIGIN',
			'X-Content-Type-Options'            => 'nosniff',
			'X-Permitted-Cross-Domain-Policies' => 'none',
			'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
			'Permissions-Policy'                => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
		);
	}

	/**
	 * Builds the Strict-Transport-Security value.
	 *
	 * Sent only over HTTPS. The theme default is `max-age=31536000` without
	 * `includeSubDomains` or `preload`: browsers remember HSTS per domain for
	 * the whole max-age, so those directives must be an explicit per-site
	 * decision (every subdomain on HTTPS / hstspreload.org submission).
	 *
	 * Enable them per site through {@see 'yzrh_hsts_directives'}:
	 *
	 *     add_filter( 'yzrh_hsts_directives', function ( $directives ) {
	 *         $directives['includeSubDomains'] = true;
	 *         $directives['preload']           = true; // requires includeSubDomains
	 *         return $directives;
	 *     } );
	 *
	 * Return false from the filter to stop sending the header (for example
	 * when the web server or CDN already sets it). A max-age of 0 sends
	 * `max-age=0`, which tells browsers to forget a previously sent policy.
	 *
	 * Behind a TLS-terminating proxy, is_ssl() is true only when WordPress is
	 * configured to trust the forwarded protocol.
	 *
	 * @return string Header value, or '' when the header should not be sent.
	 */
	public static function get_hsts_header_value() {
		if ( ! is_ssl() ) {
			return '';
		}

		$defaults = array(
			'max-age'           => 31536000,
			'includeSubDomains' => false,
			'preload'           => false,
		);

		$directives = apply_filters( 'yzrh_hsts_directives', $defaults );
		if ( false === $directives ) {
			return '';
		}
		$directives = is_array( $directives ) ? array_merge( $defaults, $directives ) : $defaults;

		$max_age = absint( $directives['max-age'] );
		$value   = 'max-age=' . $max_age;

		if ( 0 === $max_age ) {
			return $value;
		}

		if ( ! empty( $directives['includeSubDomains'] ) ) {
			$value .= '; includeSubDomains';

			// preload is only valid together with includeSubDomains.
			if ( ! empty( $directives['preload'] ) ) {
				$value .= '; preload';
			}
		}

		return $value;
	}
}

YZRH_Security_Hardening::init();
