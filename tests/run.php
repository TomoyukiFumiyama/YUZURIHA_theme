<?php
/**
 * Theme regression tests (no WordPress install required).
 *
 * Usage: php tests/run.php
 *
 * @package yzrh
 */

// Never runnable over HTTP, even if tests/ is deployed with the theme.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

require __DIR__ . '/bootstrap.php';
require YZRH_THEME_DIR . '/inc/helpers.php';
require YZRH_THEME_DIR . '/inc/security.php';
require YZRH_THEME_DIR . '/inc/assets.php';
require YZRH_THEME_DIR . '/inc/seo.php';
require YZRH_THEME_DIR . '/inc/admin.php';
require YZRH_THEME_DIR . '/inc/customizer.php';
require YZRH_THEME_DIR . '/inc/performance.php';
require YZRH_THEME_DIR . '/inc/setup.php';

// Hooks registered and schema callbacks present right after the theme files load.
$yzrh_boot_filters = $GLOBALS['yzrh_test']['filters'];
$yzrh_boot_schemas = ( function () {
	$property = new ReflectionProperty( 'YZRH_Structured_Data_Generator', 'schema_callbacks' );
	$property->setAccessible( true );
	return $property->getValue();
} )();

$yzrh_tests = array();

function test( $name, callable $fn ) {
	$GLOBALS['yzrh_tests'][ $name ] = $fn;
}

class YZRH_Test_Skipped extends Exception {}

function assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new Exception( $message . "\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true ) );
	}
}

function assert_contains( $needle, $haystack, $message = '' ) {
	if ( false === strpos( $haystack, $needle ) ) {
		throw new Exception( $message . "\n    missing: " . $needle );
	}
}

function assert_not_contains( $needle, $haystack, $message = '' ) {
	if ( false !== strpos( $haystack, $needle ) ) {
		throw new Exception( $message . "\n    unexpected: " . $needle );
	}
}

/* -------------------------------------------------------------------------
 * BUG-001/002: asset versions (inc/security.php, inc/assets.php)
 * ---------------------------------------------------------------------- */

function yzrh_test_masked( $version ) {
	return substr( wp_hash( 'yzrh-asset-' . $version ), 0, 10 );
}

test( 'core version in asset URLs is masked, not removed', function () {
	$src = 'https://example.test/wp-includes/js/jquery.js?ver=6.6.2';
	$out = YZRH_Security_Hardening::remove_asset_version( $src, 'jquery' );
	assert_same( 'https://example.test/wp-includes/js/jquery.js?ver=' . yzrh_test_masked( '6.6.2' ), $out );
} );

test( 'masked core version changes after a core update (cache busting)', function () {
	$src = 'https://example.test/a.js?ver=6.6.2';
	$before = YZRH_Security_Hardening::remove_asset_version( $src, 'a' );
	$GLOBALS['yzrh_test']['bloginfo']['version'] = '6.7';
	$after = YZRH_Security_Hardening::remove_asset_version( 'https://example.test/a.js?ver=6.7', 'a' );
	if ( $before === $after ) {
		throw new Exception( 'masked version did not change between core versions' );
	}
} );

test( 'other query parameters are kept byte-for-byte', function () {
	$src = 'https://fonts.googleapis.com/css2?family=Fraunces:wght@300&family=Noto+Serif+JP:wght@400&a.b=1&load[]=x&ver=6.6.2&display=swap';
	$out = YZRH_Security_Hardening::remove_asset_version( $src, 'fonts' );
	assert_same( str_replace( 'ver=6.6.2', 'ver=' . yzrh_test_masked( '6.6.2' ), $src ), $out );
} );

test( 'theme/plugin versions and look-alike values are untouched', function () {
	foreach ( array(
		'https://example.test/t.css?ver=1730000000',
		'https://example.test/t.css?ver=6.6.20',
		'https://example.test/t.css?server=6.6.2',
		'https://example.test/t.css',
	) as $src ) {
		assert_same( $src, YZRH_Security_Hardening::remove_asset_version( $src, 't' ), $src );
	}
} );

test( 'asset version follows file modification time', function () {
	$dir = sys_get_temp_dir() . '/yzrh-theme-test-' . getmypid();
	@mkdir( $dir . '/css', 0777, true );
	$file = $dir . '/css/test.css';
	file_put_contents( $file, 'a{}' );
	touch( $file, 1700000000 );
	clearstatcache();
	$GLOBALS['yzrh_test']['template_dir'] = $dir;

	$first = yzrh_asset_version( '/css/test.css' );
	assert_same( '1700000000', $first, 'version must be the file mtime' );
	assert_same( $first, yzrh_asset_version( '/css/test.css' ), 'version must be stable while the file is unchanged' );

	touch( $file, 1700000100 );
	clearstatcache();
	assert_same( '1700000100', yzrh_asset_version( '/css/test.css' ), 'version must change when the file changes' );
	assert_same( '1.0.0', yzrh_asset_version( '/css/missing.css' ), 'missing files fall back to the theme version' );

	unlink( $file );
	rmdir( $dir . '/css' );
	rmdir( $dir );
} );

/* -------------------------------------------------------------------------
 * BUG-009 / BUG-014: JSON-LD text decoding and script-safe encoding
 * ---------------------------------------------------------------------- */

test( 'sanitize_text decodes all entities and strips (encoded) tags', function () {
	assert_same( 'It’s & Co “quoted”', YZRH_Structured_Data_Generator::sanitize_text( 'It&#8217;s &amp; Co &#8220;quoted&#8221;' ) );
	assert_same( 'x', YZRH_Structured_Data_Generator::sanitize_text( '&lt;script&gt;alert(1)&lt;/script&gt;x' ) );
	assert_same( 'a < b', YZRH_Structured_Data_Generator::sanitize_text( 'a &lt; b' ) );
} );

test( 'site name is decoded consistently across WebSite/Organization/publisher', function () {
	$GLOBALS['yzrh_test']['bloginfo']['name'] = 'A &amp; B';
	assert_same( 'A & B', yzrh_structured_data_schema_website()['name'] );
	assert_same( 'A & B', yzrh_structured_data_schema_organization()['name'] );
	assert_same( 'A & B', YZRH_Structured_Data_Generator::get_publisher_schema()['name'] );
} );

test( 'BreadcrumbList names are decoded plain text', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_front_page' );
	add_filter(
		'yzrh_breadcrumb_items',
		function ( $items ) {
			$items[] = array( 'name' => 'It&#8217;s <em>new</em>', 'url' => 'https://example.test/new/' );
			return $items;
		}
	);
	$schema = yzrh_structured_data_schema_breadcrumbs();
	assert_same( 'It’s new', $schema['itemListElement'][1]['name'] );
} );

test( 'ItemList url is not HTML-escaped and name is decoded', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	$query        = new WP_Query();
	$query->posts = array( new WP_Post( 5 ) );
	$query->vars  = array( 'paged' => 2, 'posts_per_page' => 10 );
	$GLOBALS['wp_query'] = $query;

	$schema = yzrh_structured_data_schema_blog_itemlist();
	assert_same( 'https://example.test/?cat=3&paged=2', $schema['url'] );
	assert_same( 'Category: News & Updates', $schema['name'] );
	assert_same( 11, $schema['itemListElement'][0]['position'] );
} );

test( 'JSON-LD output cannot close the script element', function () {
	add_filter(
		'yzrh_structured_data_schema_callbacks',
		function () {
			return array(
				function () {
					return array( '@type' => 'Thing', 'name' => 'x</script><script>alert(1)</script>' );
				},
			);
		}
	);
	ob_start();
	YZRH_Structured_Data_Generator::render();
	$html = ob_get_clean();
	assert_same( 1, substr_count( strtolower( $html ), '</script' ), 'only the closing tag of the JSON-LD block may appear' );
	$json = json_decode( preg_replace( '#^<script type="application/ld\+json">|</script>\s*$#', '', $html ), true );
	assert_same( 'x</script><script>alert(1)</script>', $json['name'], 'value must round-trip as data' );
} );

test( 'JSON-LD output escapes "&" so no value is read as an HTML entity', function () {
	add_filter(
		'yzrh_structured_data_schema_callbacks',
		function () {
			return array(
				function () {
					return array( '@type' => 'Thing', 'name' => 'A & B &lt;b&gt; <!-- x -->' );
				},
			);
		}
	);
	ob_start();
	YZRH_Structured_Data_Generator::render();
	$html = ob_get_clean();
	$body = preg_replace( '#^<script type="application/ld\+json">|</script>\s*$#', '', $html );
	assert_not_contains( '&', $body, 'raw "&" must be \\u0026' );
	assert_not_contains( '<', $body, 'raw "<" must be \\u003C' );
	assert_contains( chr( 92 ) . 'u0026', $body, '"&" must be encoded as a JSON unicode escape' );
	assert_same( 'A & B &lt;b&gt; <!-- x -->', json_decode( $body, true )['name'], 'value must round-trip as data' );
} );

/* -------------------------------------------------------------------------
 * BUG-017: schema registration happens on the `wp` action
 * ---------------------------------------------------------------------- */

test( 'loading the theme registers no schemas and hooks registration to wp', function () {
	global $yzrh_boot_filters, $yzrh_boot_schemas;
	assert_same( array(), $yzrh_boot_schemas, 'no schema callbacks may be registered at file load' );
	assert_contains( 'yzrh_register_structured_data_schemas', implode( ',', array_filter( $yzrh_boot_filters['wp'], 'is_string' ) ) );
	$head = isset( $yzrh_boot_filters['wp_head'] ) ? array_filter( $yzrh_boot_filters['wp_head'], 'is_string' ) : array();
	assert_same( false, in_array( 'yzrh_output_jsonld', $head, true ), 'JSON-LD output is hooked on wp, not at load' );
} );

test( 'yzrh_jsonld_schema_map filters added after load (e.g. on init) are honoured', function () {
	// Simulates a project adding a schema and disabling one from an init callback.
	add_filter(
		'yzrh_jsonld_schema_map',
		function ( $map ) {
			$map['project_product'] = 'yzrh_test_schema_product';
			return $map;
		}
	);
	add_filter(
		'yzrh_core_default_settings',
		function ( $defaults ) {
			$defaults['project_product']    = true;
			$defaults['seo_jsonld_website'] = false;
			return $defaults;
		}
	);
	// The callback is defined only after the theme loaded.
	if ( ! function_exists( 'yzrh_test_schema_product' ) ) {
		eval( 'function yzrh_test_schema_product() { return array( "@context" => "https://schema.org", "@type" => "Product", "name" => "Widget" ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}

	add_action( 'wp', 'yzrh_register_structured_data_schemas' );
	do_action( 'wp' );

	assert_contains( 'yzrh_output_jsonld', implode( ',', array_filter( $GLOBALS['yzrh_test']['filters']['wp_head'], 'is_string' ) ) );

	ob_start();
	YZRH_Structured_Data_Generator::render();
	$html = ob_get_clean();
	assert_contains( '"@type":"Product"', $html, 'schema added via the map filter after load must be output' );
	assert_contains( '"@type":"Organization"', $html, 'default schemas are still registered' );
	assert_not_contains( '"@type":"WebSite"', $html, 'settings changed after load must be honoured' );
} );

/* -------------------------------------------------------------------------
 * BUG-007: password-protected interview posts
 * ---------------------------------------------------------------------- */

function yzrh_test_interview_state( $protected ) {
	yzrh_test_reset(
		array(
			'password_required'  => $protected,
			'has_post_thumbnail' => true,
			'excerpt'            => 'SECRET-EXCERPT',
			'meta'               => array(
				'interview_issue'           => 'SECRET-ISSUE',
				'interview_kicker'          => 'SECRET-KICKER',
				'interview_lead'            => 'SECRET-LEAD',
				'interview_hero_title_html' => 'SECRET-TITLE',
				'interview_byline_meta'     => 'SECRET-BYLINE',
				'interview_toc'             => "a|SECRET-TOC",
				'interview_marginalia'      => "L|SECRET-STAT|SECRET-NOTE",
				'interview_closing_title'   => 'SECRET-CLOSING',
				'interview_closing_text'    => 'SECRET-CLOSING-TEXT',
				'interview_closing_url'     => 'https://example.test/secret',
			),
		)
	);
}

test( 'protected interview hides custom fields, lead and thumbnail', function () {
	yzrh_test_interview_state( true );
	$html = yzrh_test_render( 'single-interview.php' );
	assert_not_contains( 'SECRET', $html, 'custom field / excerpt content leaked' );
	assert_not_contains( 'test-thumbnail', $html, 'featured image leaked' );
	assert_contains( 'Post Title', $html, 'title should still be shown' );
} );

test( 'unprotected interview still shows custom fields and thumbnail', function () {
	yzrh_test_interview_state( false );
	$html = yzrh_test_render( 'single-interview.php' );
	foreach ( array( 'SECRET-ISSUE', 'SECRET-LEAD', 'SECRET-TITLE', 'SECRET-TOC', 'SECRET-STAT', 'SECRET-CLOSING', 'test-thumbnail' ) as $needle ) {
		assert_contains( $needle, $html );
	}
} );

/* -------------------------------------------------------------------------
 * BUG-004: 404 dataLayer must not carry query strings (needs Node.js)
 * ---------------------------------------------------------------------- */

/**
 * Runs the 404.php dataLayer script in Node.js and returns the pushed object.
 *
 * @param string $href     Requested URL.
 * @param string $referrer document.referrer.
 * @return array
 */
function yzrh_test_404_datalayer( $href, $referrer ) {
	$node = trim( (string) shell_exec( 'node -v 2>&1' ) );
	if ( 0 !== strpos( $node, 'v' ) ) {
		throw new YZRH_Test_Skipped( 'node not available' );
	}
	$template = file_get_contents( YZRH_THEME_DIR . '/404.php' );
	if ( ! preg_match( '#<script>(.*?)</script>#s', $template, $m ) ) {
		throw new Exception( 'dataLayer script not found in 404.php' );
	}
	$harness = 'const window = { location: new URL(' . json_encode( $href ) . ') };'
		. 'const document = { referrer: ' . json_encode( $referrer ) . ', title: "Not found" };'
		. $m[1]
		. ';process.stdout.write(JSON.stringify(window.dataLayer[0]));';
	$file = tempnam( sys_get_temp_dir(), 'yzrh404' ) . '.js';
	file_put_contents( $file, $harness );
	$out = shell_exec( 'node ' . escapeshellarg( $file ) . ' 2>&1' );
	unlink( $file );
	$data = json_decode( (string) $out, true );
	if ( ! is_array( $data ) ) {
		throw new Exception( 'script failed: ' . $out );
	}
	return $data;
}

test( '404 dataLayer strips query strings and hashes', function () {
	$data = yzrh_test_404_datalayer( 'https://example.test/missing/?email=a%40b.test&token=xyz#frag', 'https://example.test/from/?session=123' );
	assert_same( 'https://example.test/missing/', $data['requested_url'] );
	assert_same( '/missing/', $data['requested_path'] );
	assert_same( 'https://example.test/from/', $data['referrer'] );
} );

test( '404 dataLayer redacts PII-like path segments', function () {
	$cases = array(
		'/users/taro%40example.com/profile/'                         => '/users/[redacted]/profile/',
		'/users/taro@example.com'                                    => '/users/[redacted]',
		'/tel/090-1234-5678/'                                        => '/tel/[redacted]/',
		'/reset/AbCdEf0123456789XyZ0123/'                            => '/reset/[redacted]/',
		'/o/123e4567-e89b-12d3-a456-426614174000'                    => '/o/[redacted]',
		'/t/eyJhbGciOiJIUzI1.eyJzdWIiOiIxMjM0.SflKxwRJSMeKKF2QT4fw' => '/t/[redacted]',
		'/%E0%A4%A/'                                                 => '/[redacted]/',
	);
	foreach ( $cases as $path => $expected ) {
		$data = yzrh_test_404_datalayer( 'https://example.test' . $path, '' );
		assert_same( $expected, $data['requested_path'], $path );
		assert_same( 'https://example.test' . $expected, $data['requested_url'], $path );
	}
} );

test( '404 dataLayer keeps ordinary slugs and dates', function () {
	foreach ( array(
		'/2026-10-17/how-to-build-a-wordpress-theme-guide-for-beginners/',
		'/interviews/page/2/',
		'/%E3%82%A4%E3%83%B3%E3%82%BF%E3%83%93%E3%83%A5%E3%83%BC/',
	) as $path ) {
		$data = yzrh_test_404_datalayer( 'https://example.test' . $path, '' );
		assert_same( $path, $data['requested_path'] );
	}
} );

test( '404 dataLayer sends only the origin of external referrers', function () {
	$data = yzrh_test_404_datalayer( 'https://example.test/missing/', 'https://ref.test/members/taro@example.com/page?session=1' );
	assert_same( 'https://ref.test/', $data['referrer'] );

	$data = yzrh_test_404_datalayer( 'https://example.test/missing/', 'https://example.test/old/taro@example.com/?x=1' );
	assert_same( 'https://example.test/old/[redacted]/', $data['referrer'], 'internal referrer keeps its redacted path' );

	$data = yzrh_test_404_datalayer( 'https://example.test/missing/', '' );
	assert_same( '', $data['referrer'] );
} );

/* -------------------------------------------------------------------------
 * BUG-006: HSTS defaults (inc/security.php)
 * ---------------------------------------------------------------------- */

function yzrh_test_default_headers() {
	$method = new ReflectionMethod( 'YZRH_Security_Hardening', 'get_default_security_headers' );
	$method->setAccessible( true );
	return $method->invoke( null );
}

test( 'HSTS is not sent over plain HTTP', function () {
	$GLOBALS['yzrh_test']['ssl'] = false;
	assert_same( '', YZRH_Security_Hardening::get_hsts_header_value() );
	assert_same( false, array_key_exists( 'Strict-Transport-Security', yzrh_test_default_headers() ) );
} );

test( 'HSTS default over HTTPS is one year without includeSubDomains/preload', function () {
	$GLOBALS['yzrh_test']['ssl'] = true;
	assert_same( 'max-age=31536000', YZRH_Security_Hardening::get_hsts_header_value() );
	$headers = yzrh_test_default_headers();
	assert_same( 'max-age=31536000', $headers['Strict-Transport-Security'] );
	assert_same( 'nosniff', $headers['X-Content-Type-Options'], 'other headers are kept' );
} );

test( 'yzrh_hsts_directives enables includeSubDomains and preload per site', function () {
	$GLOBALS['yzrh_test']['ssl'] = true;
	add_filter(
		'yzrh_hsts_directives',
		function ( $directives ) {
			$directives['includeSubDomains'] = true;
			$directives['preload']           = true;
			return $directives;
		}
	);
	assert_same( 'max-age=31536000; includeSubDomains; preload', YZRH_Security_Hardening::get_hsts_header_value() );
} );

test( 'preload is ignored without includeSubDomains', function () {
	$GLOBALS['yzrh_test']['ssl'] = true;
	add_filter(
		'yzrh_hsts_directives',
		function ( $directives ) {
			$directives['preload'] = true;
			return $directives;
		}
	);
	assert_same( 'max-age=31536000', YZRH_Security_Hardening::get_hsts_header_value() );
} );

test( 'yzrh_hsts_directives can disable or retract HSTS', function () {
	$GLOBALS['yzrh_test']['ssl'] = true;
	add_filter( 'yzrh_hsts_directives', '__return_false' );
	assert_same( '', YZRH_Security_Hardening::get_hsts_header_value(), 'false disables the header' );

	yzrh_test_reset( array( 'ssl' => true ) );
	add_filter(
		'yzrh_hsts_directives',
		function ( $directives ) {
			return array( 'max-age' => 0, 'includeSubDomains' => true );
		}
	);
	assert_same( 'max-age=0', YZRH_Security_Hardening::get_hsts_header_value(), 'max-age=0 retracts a previous policy' );
} );

/* -------------------------------------------------------------------------
 * BUG-008: interview sort order normalization (inc/admin.php)
 * ---------------------------------------------------------------------- */

test( 'interview order accepts only ASC/DESC strings', function () {
	$cases = array(
		array( 'asc', 'ASC' ),
		array( ' DESC ', 'DESC' ),
		array( 'Asc', 'ASC' ),
		array( 'random', 'DESC' ),
		array( '', 'DESC' ),
		array( null, 'DESC' ),
		array( array( 'ASC' ), 'DESC' ),
		array( array( 'x' => array( 'y' ) ), 'DESC' ),
		array( 1, 'DESC' ),
	);
	foreach ( $cases as $case ) {
		assert_same( $case[1], yzrh_normalize_interview_order( $case[0] ), var_export( $case[0], true ) );
	}
} );

test( '?order[]= no longer reaches string functions', function () {
	$_GET['order'] = array( 'x' );
	assert_same( 'DESC', yzrh_get_interview_order() );
	$_GET['order'] = 'asc';
	assert_same( 'ASC', yzrh_get_interview_order() );
	unset( $_GET['order'] );
	assert_same( 'DESC', yzrh_get_interview_order() );
} );

test( 'query hook and template share the same order helper', function () {
	$query = new class() {
		public $set = array();
		public function is_main_query() {
			return true;
		}
		public function is_post_type_archive( $type ) {
			return 'interview' === $type;
		}
		public function set( $key, $value ) {
			$this->set[ $key ] = $value;
		}
	};
	$_GET['order'] = array( 'ASC' );
	yzrh_interview_archive_ordering( $query );
	assert_same( 'DESC', $query->set['order'] );
	unset( $_GET['order'] );

	$template = file_get_contents( YZRH_THEME_DIR . '/archive-interview.php' );
	assert_not_contains( '$_GET', $template, 'template must read the order through yzrh_get_interview_order()' );
	assert_same( 2, substr_count( $template, 'yzrh_get_interview_order()' ) );
} );

/* -------------------------------------------------------------------------
 * BUG-010: canonical for the interview archive (inc/seo.php)
 * ---------------------------------------------------------------------- */

test( 'interview archive canonical drops ?order and keeps the page number', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	$GLOBALS['yzrh_test']['options']      = array( 'permalink_structure' => '/%postname%/' );

	assert_same( 'https://example.test/interviews', yzrh_get_interview_archive_canonical(), 'no trailing slash' );

	$GLOBALS['yzrh_test']['query_vars'] = array( 'paged' => 3 );
	assert_same( 'https://example.test/interviews/page/3', yzrh_get_interview_archive_canonical(), 'no trailing slash' );

	$GLOBALS['wp_rewrite']->pagination_base = 'seite';
	assert_same( 'https://example.test/interviews/seite/3', yzrh_get_interview_archive_canonical(), 'custom pagination base is respected' );
	$GLOBALS['wp_rewrite']->pagination_base = 'page';

	$GLOBALS['yzrh_test']['options'] = array( 'permalink_structure' => '' );
	assert_same( 'https://example.test/interviews?paged=3', yzrh_get_interview_archive_canonical() );

	$_GET['order'] = 'ASC';
	assert_not_contains( 'order', yzrh_get_interview_archive_canonical() );
	unset( $_GET['order'] );
} );

test( 'interview archive canonical is printed only on that archive', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	ob_start();
	yzrh_output_interview_archive_canonical();
	assert_same( "<link rel=\"canonical\" href=\"https://example.test/interviews\">\n", ob_get_clean() );

	$GLOBALS['yzrh_test']['conditionals'] = array();
	ob_start();
	yzrh_output_interview_archive_canonical();
	assert_same( '', ob_get_clean(), 'no canonical outside the interview archive' );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	add_filter( 'yzrh_output_archive_canonical', '__return_false' );
	ob_start();
	yzrh_output_interview_archive_canonical();
	assert_same( '', ob_get_clean(), 'filter can hand canonicals to an SEO plugin' );
} );

/* -------------------------------------------------------------------------
 * BUG-011 / BUG-012: CollectionPage breadcrumb and ListItem positions
 * ---------------------------------------------------------------------- */

function yzrh_test_list_query( array $posts, array $vars ) {
	$query        = new WP_Query();
	$query->posts = $posts;
	$query->vars  = $vars;
	$GLOBALS['wp_query'] = $query;
	return $query;
}

function yzrh_test_positions( $schema ) {
	return array_map(
		function ( $item ) {
			return $item['position'];
		},
		$schema['itemListElement']
	);
}

test( 'CollectionPage no longer carries a second BreadcrumbList', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	yzrh_test_list_query( array( new WP_Post( 1, 'interview' ) ), array( 'paged' => 1, 'posts_per_page' => 10 ) );
	$schema = yzrh_structured_data_schema_interview_collection();
	assert_same( false, array_key_exists( 'breadcrumb', $schema ) );
	assert_same( 'CollectionPage', $schema['@type'] );
} );

test( 'CollectionPage description comes from the archive description and is omitted when empty', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	yzrh_test_list_query( array( new WP_Post( 1, 'interview' ) ), array( 'paged' => 1, 'posts_per_page' => 10 ) );

	$schema = yzrh_structured_data_schema_interview_collection();
	assert_same( false, array_key_exists( 'description', $schema ), 'no description without an archive description' );

	$GLOBALS['yzrh_test']['archive_description'] = '<p>Talks &amp; essays</p>';
	$schema = yzrh_structured_data_schema_interview_collection();
	assert_same( 'Talks & essays', $schema['description'] );
	assert_same( 'Talks &amp; essays', yzrh_interview_archive_description(), 'the template helper returns the same source text' );

	foreach ( array( 'inc/seo.php', 'archive-interview.php' ) as $file ) {
		assert_not_contains( 'マーケティング、デザイン', file_get_contents( YZRH_THEME_DIR . '/' . $file ), $file . ' must not carry project-specific copy' );
	}
} );

test( 'ListItem positions have no gaps when other post types are skipped', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	yzrh_test_list_query(
		array( new WP_Post( 1 ), new WP_Post( 2, 'page' ), new WP_Post( 3 ), new WP_Post( 4, 'attachment' ), new WP_Post( 5 ) ),
		array( 'paged' => 2, 'posts_per_page' => 10 )
	);
	assert_same( array( 11, 12, 13 ), yzrh_test_positions( yzrh_structured_data_schema_blog_itemlist() ) );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	yzrh_test_list_query(
		array( new WP_Post( 1, 'interview' ), new WP_Post( 2, 'post' ), new WP_Post( 3, 'interview' ) ),
		array( 'paged' => 3, 'posts_per_page' => 5 )
	);
	assert_same( array( 11, 12 ), yzrh_test_positions( yzrh_structured_data_schema_interview_collection()['mainEntity'] ) );
} );

test( 'ListItem positions honour an explicit offset query var', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	yzrh_test_list_query( array( new WP_Post( 1 ), new WP_Post( 2 ) ), array( 'paged' => 4, 'posts_per_page' => 10, 'offset' => 5 ) );
	assert_same( array( 6, 7 ), yzrh_test_positions( yzrh_structured_data_schema_blog_itemlist() ) );

	yzrh_test_list_query( array( new WP_Post( 1 ), new WP_Post( 2 ) ), array( 'paged' => 1, 'posts_per_page' => -1 ) );
	assert_same( array( 1, 2 ), yzrh_test_positions( yzrh_structured_data_schema_blog_itemlist() ), 'posts_per_page=-1 starts at 1' );
} );

/* -------------------------------------------------------------------------
 * BUG-018 / BUG-019: interview archive taxonomy and stats
 * ---------------------------------------------------------------------- */

function yzrh_test_terms( $taxonomy, $count ) {
	$terms = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$terms[] = (object) array( 'name' => "Term {$i}", 'slug' => "term-{$i}", 'taxonomy' => $taxonomy, 'count' => $i );
	}
	return $terms;
}

function yzrh_test_render_interview_archive( array $state ) {
	yzrh_test_reset( $state );
	$query                = new WP_Query();
	$query->posts         = array( new WP_Post( 1, 'interview' ), new WP_Post( 2, 'interview' ) );
	$query->vars          = array( 'posts_per_page' => 10 );
	$query->found_posts   = 2;
	$query->max_num_pages = 1;
	$GLOBALS['wp_query']  = $query;
	return yzrh_test_render( 'archive-interview.php' );
}

test( 'interview archive never falls back to blog categories', function () {
	$html = yzrh_test_render_interview_archive(
		array(
			'object_taxonomies' => array(),
			'terms'             => array( 'category' => yzrh_test_terms( 'category', 3 ) ),
		)
	);
	assert_not_contains( '/category/', $html, 'blog category links must not appear' );
	assert_not_contains( 'Categories', $html, 'category stat is hidden without an interview taxonomy' );
	assert_contains( 'Interview', $html, 'cards fall back to the generic label' );
} );

test( 'interview archive uses the interview taxonomy and counts every term', function () {
	$html = yzrh_test_render_interview_archive(
		array(
			'object_taxonomies' => array( 'interview_category' => (object) array( 'public' => true ) ),
			'terms'             => array( 'interview_category' => yzrh_test_terms( 'interview_category', 9 ) ),
		)
	);
	assert_contains( '/interview_category/term-1/', $html );
	assert_contains( '9<span class="unit">', $html, 'category stat must count all 9 terms, not the 7 chips' );
} );

test( 'interview top bar highlights the current item of a wp_nav_menu() menu', function () {
	$css = file_get_contents( YZRH_THEME_DIR . '/css/page/interview-archive.css' );
	foreach ( array(
		'.interview-archive-topbar__nav a[aria-current="page"],',
		'.interview-archive-topbar__nav .current-menu-item > a,',
		'.interview-archive-topbar__nav .current-menu-item > a::after,',
		'.interview-archive-topbar__nav a[aria-current="page"]::after,',
	) as $selector ) {
		assert_contains( $selector, $css );
	}
} );

test( 'interview archive picks another public taxonomy attached to interviews', function () {
	$html = yzrh_test_render_interview_archive(
		array(
			'object_taxonomies' => array(
				'post_format' => (object) array( 'public' => true ),
				'topic'       => (object) array( 'public' => true ),
			),
			'terms'             => array( 'topic' => yzrh_test_terms( 'topic', 2 ) ),
		)
	);
	assert_contains( '/topic/term-1/', $html );
} );

test( 'interview archive shows the newest publish date instead of the current year', function () {
	$html = yzrh_test_render_interview_archive( array( 'lastpostdate' => '2024-03-05 10:00:00' ) );
	assert_contains( '2024.03.05', $html );
	assert_not_contains( '>' . gmdate( 'Y' ) . '<span class="unit">', $html );

	$html = yzrh_test_render_interview_archive( array( 'lastpostdate' => false ) );
	assert_not_contains( 'stat__label">Latest<', $html, 'stat is hidden when nothing is published' );
} );

/* -------------------------------------------------------------------------
 * BUG-020: Swiper loading
 * ---------------------------------------------------------------------- */

test( 'Swiper is registered deferred in the footer but not enqueued by default', function () {
	yzrh_enqueue_assets();
	$assets = $GLOBALS['yzrh_test']['assets'];
	assert_same( array( 'in_footer' => true, 'strategy' => 'defer' ), $assets['wp_register_script']['yzrh-swiper'][3] );
	assert_same( false, isset( $assets['wp_enqueue_script']['yzrh-swiper'] ), 'script must not load on every page' );
	assert_same( false, isset( $assets['wp_enqueue_style']['yzrh-swiper'] ), 'style must not load on every page' );
} );

test( 'yzrh_use_swiper loads Swiper on the pages that need it', function () {
	add_filter( 'yzrh_use_swiper', '__return_true' );
	yzrh_enqueue_assets();
	$assets = $GLOBALS['yzrh_test']['assets'];
	assert_same( true, isset( $assets['wp_enqueue_script']['yzrh-swiper'] ) );
	assert_same( true, isset( $assets['wp_enqueue_style']['yzrh-swiper'] ) );
} );

/* -------------------------------------------------------------------------
 * BUG-021: dead code, direct-access guards, SNS icon styles
 * ---------------------------------------------------------------------- */

test( 'dead templates and unused code are gone', function () {
	foreach ( array( 'lp/lp1.php', 'lp/lp2.php', 'lp/lp3.php', 'page-templates/template-1.php' ) as $file ) {
		assert_same( false, file_exists( YZRH_THEME_DIR . '/' . $file ), $file );
	}
	assert_same( false, method_exists( 'YZRH_Structured_Data_Generator', 'init' ), 'unused init() would double the JSON-LD output' );
	assert_same( 1, substr_count( file_get_contents( YZRH_THEME_DIR . '/inc/seo.php' ), "defined( 'ABSPATH' )" ) );
	assert_not_contains( 'add_image_size(', preg_replace( '#/\*.*?\*/#s', '', file_get_contents( YZRH_THEME_DIR . '/inc/images.php' ) ) );
} );

test( 'every PHP file outside tests has a direct-access guard', function () {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( YZRH_THEME_DIR, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		$path = str_replace( '\\', '/', $file->getPathname() );
		if ( 'php' !== $file->getExtension() || false !== strpos( $path, '/tests/' ) || false !== strpos( $path, '/.git/' ) ) {
			continue;
		}
		assert_contains( "defined( 'ABSPATH' )", file_get_contents( $path ), $path );
	}
} );

test( 'test files refuse to run outside the CLI', function () {
	foreach ( array( 'tests/run.php', 'tests/bootstrap.php' ) as $file ) {
		$source = file_get_contents( YZRH_THEME_DIR . '/' . $file );
		$guard  = strpos( $source, "if ( 'cli' !== PHP_SAPI )" );
		assert_same( true, false !== $guard, $file );
		preg_match( '/^(require|define)\b/m', $source, $first, PREG_OFFSET_CAPTURE );
		assert_same( true, $guard < $first[0][1], $file . ': guard must precede the first statement' );
	}
} );

test( 'SNS icon styles reference bundled SVGs that exist', function () {
	$css = file_get_contents( YZRH_THEME_DIR . '/css/component/sns-icons.css' );
	preg_match_all( '#url\("\.\./\.\./([^"]+)"\)#', $css, $m );
	assert_same( 5, count( $m[1] ) );
	foreach ( $m[1] as $relative ) {
		assert_same( true, file_exists( YZRH_THEME_DIR . '/' . $relative ), $relative );
	}
	yzrh_enqueue_assets();
	assert_same( true, isset( $GLOBALS['yzrh_test']['assets']['wp_enqueue_style']['yzrh-sns-icons'] ) );

	$GLOBALS['yzrh_test']['theme_mods'] = array( 'sns_x_url' => 'https://x.example.test/a' );
	ob_start();
	yzrh_output_sns_icons();
	$html = ob_get_clean();
	assert_contains( 'rel="noopener noreferrer"', $html );
	assert_contains( 'aria-hidden="true"', $html );
} );

/* -------------------------------------------------------------------------
 * BUG-022: JSON-LD descriptions come only from manual excerpts
 * ---------------------------------------------------------------------- */

test( 'JSON-LD list items omit description without a manual excerpt and never generate one', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	yzrh_test_list_query( array( new WP_Post( 1 ), new WP_Post( 2 ) ), array( 'paged' => 1, 'posts_per_page' => 10 ) );
	$schema = yzrh_structured_data_schema_blog_itemlist();
	assert_same( false, array_key_exists( 'description', $schema['itemListElement'][0] ) );
	assert_same( false, isset( $GLOBALS['yzrh_test']['calls']['get_the_excerpt'] ), 'get_the_excerpt() (the_content filters) must not run' );
} );

test( 'JSON-LD uses the manual excerpt as plain text', function () {
	$GLOBALS['yzrh_test']['manual_excerpt'] = 'Hand &amp; <b>written</b>';
	$GLOBALS['yzrh_test']['conditionals']   = array( 'is_category' );
	yzrh_test_list_query( array( new WP_Post( 1 ) ), array( 'paged' => 1, 'posts_per_page' => 10 ) );
	$schema = yzrh_structured_data_schema_blog_itemlist();
	assert_same( 'Hand & written', $schema['itemListElement'][0]['description'] );
} );

test( 'protected posts expose no description; OGP may still use a generated one', function () {
	$GLOBALS['yzrh_test']['manual_excerpt']    = 'Secret summary';
	$GLOBALS['yzrh_test']['password_required'] = true;
	assert_same( '', YZRH_Structured_Data_Generator::get_post_description( 1 ) );
	assert_same( '', YZRH_Structured_Data_Generator::get_post_description( 1, true ) );

	yzrh_test_reset( array( 'excerpt' => 'Generated summary' ) );
	assert_same( '', YZRH_Structured_Data_Generator::get_post_description( 1 ), 'JSON-LD default: manual only' );
	assert_same( 'Generated summary', YZRH_Structured_Data_Generator::get_post_description( 1, true ), 'OGP: generated allowed' );
} );

/* -------------------------------------------------------------------------
 * BUG-024: custom-logo theme support
 * ---------------------------------------------------------------------- */

test( 'custom-logo theme support is registered with flexible size', function () {
	yzrh_setup();
	$support = $GLOBALS['yzrh_test']['theme_support'];
	assert_same( true, isset( $support['custom-logo'] ), 'custom-logo support missing' );
	assert_same( true, $support['custom-logo']['flex-width'] );
	assert_same( true, $support['custom-logo']['flex-height'] );
} );

/* -------------------------------------------------------------------------
 * BUG-025: og:url matches canonical, no trailing slash
 * ---------------------------------------------------------------------- */

test( 'og:url on the interview archive equals its canonical', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	$GLOBALS['yzrh_test']['options']      = array( 'permalink_structure' => '/%postname%/' );
	$GLOBALS['yzrh_test']['query_vars']   = array( 'paged' => 2 );
	$GLOBALS['wp']                        = (object) array( 'request' => 'interviews/page/2' );

	$tags = yzrh_get_ogp_tags();
	assert_same( 'https://example.test/interviews/page/2', $tags['og:url'] );
	assert_same( yzrh_get_interview_archive_canonical(), $tags['og:url'] );
} );

test( 'og:url on other list pages has no trailing slash and no query', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	$GLOBALS['wp']                        = (object) array( 'request' => 'category/news/page/2/' );
	$_GET['utm_source']                   = 'x';
	assert_same( 'https://example.test/category/news/page/2', yzrh_get_ogp_tags()['og:url'] );
	unset( $_GET['utm_source'] );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	$GLOBALS['wp']                        = (object) array( 'request' => '' );
	assert_same( 'https://example.test', yzrh_get_ogp_tags()['og:url'] );
} );

/* -------------------------------------------------------------------------
 * BUG-026: security header values from filters
 * ---------------------------------------------------------------------- */

test( 'security headers skip non-string, empty and injected values without fatal errors', function () {
	$lines = YZRH_Security_Hardening::prepare_header_lines(
		array(
			'X-Valid'          => 'ok',
			'X-Int'            => 10,
			'X-Array'          => array( 'a' ),
			'X-Object'         => new stdClass(),
			'X-Null'           => null,
			'X-Bool'           => true,
			'X-Empty'          => '   ',
			'X-Injected'       => "a\r\nSet-Cookie: x=1",
			'Bad Name'         => 'x',
			'X-Bad:Name'       => 'x',
			0                  => 'numeric key',
		)
	);
	assert_same( array( 'X-Valid: ok', 'X-Int: 10' ), $lines );
	assert_same( array(), YZRH_Security_Hardening::prepare_header_lines( 'not an array' ) );
	assert_same( array(), YZRH_Security_Hardening::prepare_header_lines( null ) );
} );

/* -------------------------------------------------------------------------
 * BUG-028: block API version aligned (v3) in PHP and JS
 * ---------------------------------------------------------------------- */

test( 'interview blocks use Block API v3 on both PHP and JS sides', function () {
	yzrh_register_interview_blocks();
	foreach ( array( 'yzrh/interview-speech', 'yzrh/interview-pullquote' ) as $name ) {
		assert_same( 3, $GLOBALS['yzrh_test']['blocks'][ $name ]['api_version'], $name );
	}

	$js = file_get_contents( YZRH_THEME_DIR . '/js/interview-blocks.js' );
	assert_contains( 'const API_VERSION = 3;', $js );
	assert_same( 2, substr_count( $js, 'apiVersion: API_VERSION' ), 'both blocks declare apiVersion' );
	assert_same( 2, substr_count( $js, 'useBlockProps({' ), 'v2+ blocks must use useBlockProps()' );

	$style = file_get_contents( YZRH_THEME_DIR . '/style.css' );
	assert_contains( 'Requires at least: 6.3', $style, 'Block API v3 needs WordPress 6.3+' );
} );

/* -------------------------------------------------------------------------
 * BUG-013: no Gravatar URL in Person schema
 * ---------------------------------------------------------------------- */

test( 'Person schema contains no Gravatar URL', function () {
	$schema = YZRH_Structured_Data_Generator::get_author_schema( null, 7 );
	assert_same( false, array_key_exists( 'image', $schema ) );
	assert_same( false, isset( $GLOBALS['yzrh_test']['calls']['get_avatar_url'] ), 'get_avatar_url() must not be called' );
	assert_not_contains( 'gravatar', json_encode( $schema ) );

	add_filter(
		'yzrh_structured_data_author',
		function ( $schema ) {
			$schema['image'] = 'https://example.test/uploads/author.jpg';
			return $schema;
		}
	);
	assert_same( 'https://example.test/uploads/author.jpg', YZRH_Structured_Data_Generator::get_author_schema( null, 7 )['image'], 'projects can add a self-hosted photo' );
} );

/* -------------------------------------------------------------------------
 * BUG-010: itemListOrder follows the query
 * ---------------------------------------------------------------------- */

test( 'itemListOrder follows the query order', function () {
	$cases = array(
		array( array( 'order' => 'ASC' ), 'https://schema.org/ItemListOrderAscending' ),
		array( array( 'order' => 'asc' ), 'https://schema.org/ItemListOrderAscending' ),
		array( array( 'order' => 'DESC' ), 'https://schema.org/ItemListOrderDescending' ),
		array( array(), 'https://schema.org/ItemListOrderDescending' ),
		array( array( 'order' => array( 'x' ) ), 'https://schema.org/ItemListOrderDescending' ),
		array( array( 'orderby' => 'rand', 'order' => 'ASC' ), 'https://schema.org/ItemListUnordered' ),
	);
	foreach ( $cases as $case ) {
		$query       = new WP_Query();
		$query->vars = $case[0];
		assert_same( $case[1], YZRH_Structured_Data_Generator::get_item_list_order( $query ), var_export( $case[0], true ) );
	}

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	yzrh_test_list_query( array( new WP_Post( 1, 'interview' ) ), array( 'paged' => 1, 'posts_per_page' => 10, 'order' => 'ASC' ) );
	assert_same( 'https://schema.org/ItemListOrderAscending', yzrh_structured_data_schema_interview_collection()['mainEntity']['itemListOrder'] );
} );

/* -------------------------------------------------------------------------
 * BUG-017: emoji filter evaluated on the wp action
 * ---------------------------------------------------------------------- */

test( 'emoji filter is evaluated on wp, not at file load', function () {
	global $yzrh_boot_filters;
	assert_contains( 'yzrh_maybe_disable_emoji_assets', implode( ',', array_filter( $yzrh_boot_filters['wp'], 'is_string' ) ) );

	// Added after load (e.g. on init): must still take effect.
	add_filter( 'yzrh_disable_emoji_assets', '__return_true' );
	yzrh_maybe_disable_emoji_assets();
	$removed = array_map(
		function ( $r ) {
			return $r[1];
		},
		$GLOBALS['yzrh_test']['removed']
	);
	foreach ( array( 'print_emoji_detection_script', 'print_emoji_styles', 'wp_enqueue_emoji_styles' ) as $callback ) {
		assert_same( true, in_array( $callback, $removed, true ), $callback );
	}

	yzrh_test_reset();
	yzrh_maybe_disable_emoji_assets();
	assert_same( false, isset( $GLOBALS['yzrh_test']['removed'] ), 'default keeps emoji assets' );
} );

/* -------------------------------------------------------------------------
 * 2026-10-18: inc/ コメント付与時に見つけた不具合の回帰テスト
 * ---------------------------------------------------------------------- */

test( 'og:locale keeps the OGP language_TERRITORY format', function () {
	$GLOBALS['yzrh_test']['locale']       = 'ja_JP';
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	$GLOBALS['wp']                        = (object) array( 'request' => '' );
	assert_same( 'ja_JP', yzrh_get_ogp_tags()['og:locale'] );
} );

test( 'OGP output can be turned off with yzrh_output_ogp', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	$GLOBALS['wp']                        = (object) array( 'request' => '' );
	ob_start();
	yzrh_output_ogp();
	assert_contains( 'og:site_name', ob_get_clean(), 'printed by default' );

	add_filter( 'yzrh_output_ogp', '__return_false' );
	ob_start();
	yzrh_output_ogp();
	assert_same( '', ob_get_clean() );
} );

test( 'theme OGP is skipped only when wp_head already printed Open Graph tags', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	$GLOBALS['wp']                        = (object) array( 'request' => '' );

	// An SEO plugin that prints its own OGP in wp_head: the theme adds nothing.
	ob_start();
	yzrh_start_ogp_detection();
	echo "<meta property=\"og:title\" content=\"Plugin\">\n";
	yzrh_output_ogp();
	$html = ob_get_clean();
	assert_contains( '<meta property="og:title" content="Plugin">', $html, 'plugin output is kept' );
	assert_not_contains( 'og:site_name', $html );

	// An SEO plugin with its OGP feature disabled (only other head tags): the theme prints OGP.
	ob_start();
	yzrh_start_ogp_detection();
	echo "<meta name=\"description\" content=\"x\">\n";
	yzrh_output_ogp();
	$html = ob_get_clean();
	assert_contains( '<meta name="description" content="x">', $html );
	assert_contains( 'og:site_name', $html );

	assert_same( true, yzrh_has_ogp_markup( "<meta content='x' property='og:image'>" ) );
	assert_same( false, yzrh_has_ogp_markup( '<meta name="twitter:card" content="summary">' ) );
} );

test( 'OGP detection leaves output buffers alone when their nesting changed', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	$GLOBALS['wp']                        = (object) array( 'request' => '' );
	$base = ob_get_level();
	ob_start();
	yzrh_start_ogp_detection();
	ob_start(); // A callback that opened a buffer and never closed it.
	echo '<meta property="og:title" content="Plugin">';
	yzrh_output_ogp();
	assert_same( $base + 3, ob_get_level(), 'no buffer closed by the theme' );
	$html = ob_get_clean() . ob_get_clean();
	$html = ob_get_clean() . $html;
	assert_contains( 'og:site_name', $html, 'falls back to printing the theme OGP' );
} );

test( 'code outside YZRH_Structured_Data_Generator only uses its original public API', function () {
	// The class can be replaced by a child theme (class_exists()); a replacement written against
	// the earlier API must keep working, so new shared helpers live outside the class.
	$original_api = array(
		'get_author_schema', 'get_item_list_order', 'get_list_start_offset', 'get_logo_object',
		'get_post_description', 'get_post_image_object', 'get_primary_category', 'get_publisher_schema',
		'is_blog_listing_context', 'is_service_page', 'register_schema', 'render', 'sanitize_text',
	);
	$files = array_merge( glob( YZRH_THEME_DIR . '/*.php' ), glob( YZRH_THEME_DIR . '/inc/*.php' ), glob( YZRH_THEME_DIR . '/*/*.php' ) );
	foreach ( $files as $file ) {
		if ( false !== strpos( $file, '/tests/' ) ) {
			continue;
		}
		preg_match_all( '/YZRH_Structured_Data_Generator::([A-Za-z_]+)\s*\(/', file_get_contents( $file ), $m );
		foreach ( $m[1] as $method ) {
			assert_same( true, in_array( $method, $original_api, true ), basename( $file ) . ' calls ' . $method . '()' );
		}
	}
} );

test( 'BreadcrumbList is omitted when the trail has only the home item, like the HTML breadcrumb', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_front_page' );
	assert_same( null, yzrh_structured_data_schema_breadcrumbs() );
	ob_start();
	yzrh_breadcrumb();
	assert_same( '', ob_get_clean() );
} );

test( 'ImageObject omits unknown width and height instead of printing null or 0', function () {
	$GLOBALS['yzrh_test']['attachments'] = array(
		10 => array( 'https://example.test/logo.svg', 0, 0, false ),
		11 => array( 'https://example.test/photo.jpg', 1200, 630, false ),
	);
	assert_same( array( '@type' => 'ImageObject', 'url' => 'https://example.test/logo.svg' ), YZRH_Structured_Data_Generator::get_attachment_image_object( 10 ) );
	assert_same( array( '@type' => 'ImageObject', 'url' => 'https://example.test/photo.jpg', 'width' => 1200, 'height' => 630 ), YZRH_Structured_Data_Generator::get_attachment_image_object( 11 ) );
	assert_same( null, YZRH_Structured_Data_Generator::get_attachment_image_object( 12 ) );

	$GLOBALS['yzrh_test']['theme_mods'] = array( 'custom_logo' => 10 );
	assert_same( 'https://example.test/logo.svg', YZRH_Structured_Data_Generator::get_logo_object()['url'] );
} );

test( 'Blog url is shared by the Blog schema and BlogPosting isPartOf', function () {
	assert_same( 'https://example.test/', yzrh_get_blog_url(), 'front page lists posts' );
	$GLOBALS['yzrh_test']['options'] = array( 'page_for_posts' => 7 );
	assert_same( get_permalink( 7 ), yzrh_get_blog_url(), 'posts page' );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_home' );
	assert_same( yzrh_get_blog_url(), yzrh_structured_data_schema_blog()['url'] );
	assert_contains( "'url'   => yzrh_get_blog_url()", file_get_contents( YZRH_THEME_DIR . '/inc/seo.php' ), 'BlogPosting isPartOf' );
} );

test( 'CollectionPage name and url match the archive heading and canonical', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_post_type_archive' );
	$GLOBALS['yzrh_test']['options']      = array( 'permalink_structure' => '/%postname%/' );
	$GLOBALS['yzrh_test']['query_vars']   = array( 'paged' => 2 );
	yzrh_test_list_query( array( new WP_Post( 1, 'interview' ) ), array( 'paged' => 2, 'posts_per_page' => 10 ) );

	$schema = yzrh_structured_data_schema_interview_collection();
	assert_same( post_type_archive_title( '', false ), $schema['name'] );
	assert_same( yzrh_get_interview_archive_canonical(), $schema['url'] );
	assert_not_contains( '取材一覧', file_get_contents( YZRH_THEME_DIR . '/inc/seo.php' ), 'no project-specific archive name' );
} );

test( 'SNS links and customizer controls come from one list; mailto opens without a new tab', function () {
	$GLOBALS['yzrh_test']['theme_mods'] = array(
		'sns_x_url'       => 'https://x.example.test/a',
		'sns_contact_url' => 'mailto:info@example.test',
	);
	ob_start();
	yzrh_output_sns_icons();
	$html = ob_get_clean();
	assert_contains( '<a href="https://x.example.test/a" target="_blank" rel="noopener noreferrer">', $html );
	assert_contains( '<a href="mailto:info@example.test">', $html );

	$customize = new class() {
		public $settings = array();
		public function add_section( $id, $args ) {}
		public function add_setting( $id, $args ) {
			$this->settings[] = $id;
		}
		public function add_control( $id, $args ) {}
	};
	yzrh_customize_register_sns( $customize );
	$expected = array_map(
		function ( $key ) {
			return "sns_{$key}_url";
		},
		array_keys( yzrh_get_sns_services() )
	);
	$expected[] = 'sns_rss_enable';
	assert_same( $expected, $customize->settings );

	$GLOBALS['yzrh_test']['theme_mods'] = array();
	ob_start();
	yzrh_output_sns_icons();
	assert_same( '', ob_get_clean(), 'no empty list without any URL' );
} );

test( 'GTM code is saved only by unfiltered_html users and printed raw in head and body', function () {
	$customize = new class() {
		public $sections = array();
		public $settings = array();
		public function add_section( $id, $args ) {
			$this->sections[ $id ] = $args;
		}
		public function add_setting( $id, $args ) {
			$this->settings[ $id ] = $args;
		}
		public function add_control( $id, $args ) {}
	};
	yzrh_customize_register_tag_manager( $customize );
	assert_same( 'unfiltered_html', $customize->sections['tag_manager_section']['capability'] );
	assert_same( array( 'gtm_head_code', 'gtm_body_code' ), array_keys( $customize->settings ) );
	foreach ( $customize->settings as $args ) {
		assert_same( 'unfiltered_html', $args['capability'] );
	}

	$head = "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];})(window,document,'script','dataLayer','GTM-XXXX');</script>\n<!-- End Google Tag Manager -->";
	$body = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
	assert_same( '', yzrh_sanitize_tag_manager_code( $head ), 'rejected without unfiltered_html' );
	$GLOBALS['yzrh_test']['caps'] = array( 'unfiltered_html' );
	assert_same( $head, yzrh_sanitize_tag_manager_code( "  {$head}\n" ) );
	assert_same( '', yzrh_sanitize_tag_manager_code( array( $head ) ) );

	$GLOBALS['yzrh_test']['theme_mods'] = array(
		'gtm_head_code' => $head,
		'gtm_body_code' => $body,
	);
	ob_start();
	yzrh_output_tag_manager_head();
	assert_same( $head . "\n", ob_get_clean() );
	ob_start();
	yzrh_output_tag_manager_body();
	assert_same( $body . "\n", ob_get_clean() );
	$source = file_get_contents( YZRH_THEME_DIR . '/inc/customizer.php' );
	assert_contains( "add_action( 'wp_head', 'yzrh_output_tag_manager_head', 1 );", $source );
	assert_contains( "add_action( 'wp_body_open', 'yzrh_output_tag_manager_body', 1 );", $source );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_customize_preview' );
	ob_start();
	yzrh_output_tag_manager_head();
	yzrh_output_tag_manager_body();
	assert_same( '', ob_get_clean(), 'not printed in the customizer preview' );

	$GLOBALS['yzrh_test']['conditionals'] = array();
	$GLOBALS['yzrh_test']['theme_mods']   = array( 'gtm_head_code' => "  \n" );
	ob_start();
	yzrh_output_tag_manager_head();
	yzrh_output_tag_manager_body();
	assert_same( '', ob_get_clean(), 'nothing printed when empty' );
} );

test( 'speech block falls back to the host style when speakerStyle sanitizes to empty', function () {
	$html = yzrh_render_interview_speech_block( array( 'speakerStyle' => '!!!' ) );
	assert_contains( 'yzrh-interview-speaker is-host', $html );
	assert_not_contains( 'is- ', $html );
} );

/* -------------------------------------------------------------------------
 * 2026-10-31 セキュリティ診断 F-01〜F-04（.agents/findings.md）
 * ---------------------------------------------------------------------- */

test( 'F-01: author archives without posts become 404; authors with posts and the opt-out are untouched', function () {
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_author' );
	$GLOBALS['yzrh_test']['loop']         = 1; // have_posts() === false
	$GLOBALS['wp_query']                  = new WP_Query();
	YZRH_Security_Hardening::disable_empty_author_archives();
	assert_same( true, $GLOBALS['wp_query']->is_404, 'empty author archive' );
	assert_same( 404, $GLOBALS['yzrh_test']['calls']['status_header'] );
	assert_same( true, $GLOBALS['yzrh_test']['calls']['nocache_headers'] );

	$GLOBALS['yzrh_test']['calls'] = array();
	$GLOBALS['wp_query']           = new WP_Query();
	$GLOBALS['yzrh_test']['loop']  = 0; // have_posts() === true
	YZRH_Security_Hardening::disable_empty_author_archives();
	assert_same( false, $GLOBALS['wp_query']->is_404, 'author with posts' );
	assert_same( false, isset( $GLOBALS['yzrh_test']['calls']['status_header'] ) );

	$GLOBALS['yzrh_test']['loop']         = 1;
	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_category' );
	YZRH_Security_Hardening::disable_empty_author_archives();
	assert_same( false, $GLOBALS['wp_query']->is_404, 'empty category archives are left to WordPress' );

	$GLOBALS['yzrh_test']['conditionals'] = array( 'is_author' );
	add_filter( 'yzrh_404_empty_author_archives', '__return_false' );
	YZRH_Security_Hardening::disable_empty_author_archives();
	assert_same( false, $GLOBALS['wp_query']->is_404, 'opt-out filter' );

	assert_contains( "add_action( 'template_redirect', array( __CLASS__, 'disable_empty_author_archives' ) );", file_get_contents( YZRH_THEME_DIR . '/inc/security.php' ) );
} );

test( 'F-02: password-protected posts expose no cover image in JSON-LD or og:image', function () {
	$GLOBALS['yzrh_test']['attachments']   = array( 11 => array( 'https://example.test/cover.jpg', 1200, 630, false ) );
	$GLOBALS['yzrh_test']['thumbnail_id']  = 11;
	$GLOBALS['yzrh_test']['thumbnail_url'] = 'https://example.test/cover.jpg';
	$GLOBALS['yzrh_test']['conditionals']  = array( 'is_singular' );

	assert_same( 'https://example.test/cover.jpg', YZRH_Structured_Data_Generator::get_post_image_object( 1 )['url'], 'public post keeps its image' );
	assert_same( 'https://example.test/cover.jpg', yzrh_get_ogp_tags()['og:image'], 'public post keeps og:image' );

	$GLOBALS['yzrh_test']['password_required'] = true;
	assert_same( null, YZRH_Structured_Data_Generator::get_post_image_object( 1 ) );
	assert_same( false, isset( yzrh_get_ogp_tags()['og:image'] ), 'og:image of a protected post' );
} );

test( 'F-02: templates hide cover images of password-protected posts', function () {
	foreach ( array( 'single.php', 'single-news.php', 'single-casestudy.php', 'page-templates/job-details.php', 'template-parts/post-list-item.php' ) as $file ) {
		assert_contains( 'has_post_thumbnail() && ! post_password_required()', file_get_contents( YZRH_THEME_DIR . '/' . $file ), $file );
	}
} );

test( 'F-02: the interview archive does not use custom fields, length or cover of protected interviews', function () {
	$meta = array(
		'interview_byline_name' => 'SECRET-NAME',
		'interview_byline_role' => 'SECRET-ROLE',
		'interview_read_time'   => '12 min',
	);
	$html = yzrh_test_render_interview_archive( array( 'meta' => $meta, 'password_required' => true, 'has_post_thumbnail' => true ) );
	assert_not_contains( 'SECRET-NAME', $html );
	assert_not_contains( 'SECRET-ROLE', $html );
	assert_not_contains( '12 min', $html );
	assert_same( '', yzrh_interview_archive_read_time( 1 ) );
	assert_same( 'Author', yzrh_interview_archive_byline( 1 )['name'], 'falls back to the author display name' );

	$html = yzrh_test_render_interview_archive( array( 'meta' => $meta, 'password_required' => false ) );
	assert_contains( 'SECRET-NAME', $html, 'public interviews keep their byline' );
	assert_contains( '12 min', $html );

	$template = file_get_contents( YZRH_THEME_DIR . '/archive-interview.php' );
	assert_contains( 'has_post_thumbnail( $post_id ) && ! post_password_required( $post_id )', $template );
	assert_contains( 'has_post_thumbnail( $featured_id ) && ! post_password_required( $featured_id )', $template );
} );

test( 'F-03: breadcrumbs skip private, draft and trashed ancestors', function () {
	$GLOBALS['yzrh_test']['conditionals']     = array( 'is_singular' );
	$GLOBALS['yzrh_test']['post_type']        = 'page';
	$GLOBALS['yzrh_test']['ancestors']        = array( 30, 20 ); // 親 → 祖父母の順（get_post_ancestors() と同じ）。
	$GLOBALS['yzrh_test']['titles']           = array( 20 => 'PUBLIC-GRANDPARENT', 30 => 'SECRET-PARENT', 1 => 'Child' );
	$GLOBALS['yzrh_test']['non_public_posts'] = array( 30 );

	$names = array_column( yzrh_get_breadcrumb_items(), 'name' );
	assert_same( array( 'Example Site', 'PUBLIC-GRANDPARENT', 'Child' ), $names );

	$GLOBALS['yzrh_test']['non_public_posts'] = array();
	$names = array_column( yzrh_get_breadcrumb_items(), 'name' );
	assert_same( array( 'Example Site', 'PUBLIC-GRANDPARENT', 'SECRET-PARENT', 'Child' ), $names, 'public ancestors stay' );
} );

test( 'F-04: repository-only files are excluded from archives and denied by Apache', function () {
	$attributes = file_get_contents( YZRH_THEME_DIR . '/.gitattributes' );
	foreach ( array( '/.agents', '/tests', '/AGENTS.md' ) as $path ) {
		assert_same( 1, preg_match( '#^' . preg_quote( $path, '#' ) . '\s+export-ignore\r?$#m', $attributes ), $path );
	}

	$htaccess = file_get_contents( YZRH_THEME_DIR . '/.htaccess' );
	assert_contains( 'Require all denied', $htaccess );
	assert_contains( 'RedirectMatch 404', $htaccess );
	preg_match( '/<FilesMatch "([^"]+)">/', $htaccess, $files );
	preg_match( '/RedirectMatch 404 "([^"]+)"/', $htaccess, $dirs );
	foreach ( array( '.gitattributes', '.htaccess', 'README.md', 'AGENTS.md', 'compose.yml', 'run-all.sh' ) as $name ) {
		assert_same( 1, preg_match( '/' . $files[1] . '/', $name ), "{$name} is denied" );
	}
	foreach ( array( 'style.css', 'screenshot.png', 'footerscript.js', 'functions.php', 'NotoSansJP.woff2' ) as $name ) {
		assert_same( 0, preg_match( '/' . $files[1] . '/', $name ), "{$name} is served" );
	}
	$pattern = '#' . str_replace( '#', '\#', $dirs[1] ) . '#';
	foreach ( array( '/wp-content/themes/yzrh/.git/HEAD', '/wp-content/themes/yzrh/.agents/findings.md', '/wp-content/themes/yzrh/tests/run.php', '/blog/wp-content/themes/yzrh/.git' ) as $path ) {
		assert_same( 1, preg_match( $pattern, $path ), "{$path} is 404" );
	}
	foreach ( array( '/wp-content/themes/yzrh/css/style.css', '/wp-content/themes/yzrh/js/tests.js', '/wp-content/themes/yzrh/assets/latest/a.css' ) as $path ) {
		assert_same( 0, preg_match( $pattern, $path ), "{$path} is served" );
	}
} );

/* ---------------------------------------------------------------------- */

$failed  = 0;
$skipped = 0;
foreach ( $yzrh_tests as $name => $fn ) {
	yzrh_test_reset();
	try {
		$fn();
		echo "PASS  {$name}\n";
	} catch ( YZRH_Test_Skipped $e ) {
		$skipped++;
		echo "SKIP  {$name} ({$e->getMessage()})\n";
	} catch ( Throwable $e ) {
		$failed++;
		echo "FAIL  {$name}\n    {$e->getMessage()}\n";
	}
}

printf( "\n%d tests, %d failed, %d skipped\n", count( $yzrh_tests ), $failed, $skipped );
exit( $failed ? 1 : 0 );
