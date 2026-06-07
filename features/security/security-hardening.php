<?php
/**
 * Theme-wide security hardening helpers.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YZRH_Security_Hardening {
    /**
     * Bootstrap the hardening measures.
     */
    public static function init() {
        add_filter( 'the_generator', '__return_empty_string' );
        add_filter( 'style_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );
        add_filter( 'script_loader_src', array( __CLASS__, 'remove_asset_version' ), 10, 2 );

        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'xmlrpc_methods', array( __CLASS__, 'disable_pingback_method' ) );
        add_filter( 'wp_headers', array( __CLASS__, 'remove_x_pingback_header' ) );
        add_filter( 'pings_open', '__return_false' );

        add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );
    }

    /**
     * Strip the version query arg from enqueued assets.
     *
     * @param string $src    Asset URL.
     * @param string $handle Asset handle.
     * @return string
     */
    public static function remove_asset_version( $src, $handle ) {
        if ( false === strpos( $src, 'ver=' ) ) {
            return $src;
        }

        $parts = explode( '?', $src, 2 );

        if ( 2 !== count( $parts ) ) {
            return $src;
        }

        $base   = $parts[0];
        $params = wp_parse_args( $parts[1] );

        if ( isset( $params['ver'] ) ) {
            unset( $params['ver'] );
        }

        $query = http_build_query( $params );

        return $query ? $base . '?' . $query : $base;
    }

    /**
     * Remove the pingback method from XML-RPC.
     *
     * @param array $methods Registered XML-RPC methods.
     * @return array
     */
    public static function disable_pingback_method( $methods ) {
        unset( $methods['pingback.ping'] );

        return $methods;
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

        if ( empty( $headers ) || ! is_array( $headers ) ) {
            return;
        }

        foreach ( $headers as $name => $value ) {
            if ( empty( $name ) || '' === trim( $value ) ) {
                continue;
            }

            header( sprintf( '%s: %s', $name, $value ), true );
        }
    }

    /**
     * Default security header map used by {@see self::send_security_headers()}.
     *
     * @return array<string, string>
     */
    protected static function get_default_security_headers() {
        $headers = array(
            'Strict-Transport-Security'         => 'max-age=63072000; includeSubDomains; preload',
            'X-Frame-Options'                   => 'SAMEORIGIN',
            'X-Content-Type-Options'            => 'nosniff',
            'Content-Security-Policy'           => "default-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self';", // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled -- Header name.
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            'Permissions-Policy'                => "accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()",
        );

        if ( self::should_skip_content_security_policy() ) {
            unset( $headers['Content-Security-Policy'] );
        }

        return $headers;
    }

    /**
     * Determine whether the theme should avoid sending its CSP header.
     *
     * WordPress admin and authentication screens intentionally output inline
     * scripts, inline styles, data fonts, and dynamic editor styles. A strict
     * frontend-oriented CSP would block those core assets and break editing.
     *
     * @return bool
     */
    protected static function should_skip_content_security_policy() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return true;
        }

        $pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

        return in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ), true );
    }
}
