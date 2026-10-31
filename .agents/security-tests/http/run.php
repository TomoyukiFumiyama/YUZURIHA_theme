<?php
/**
 * 未ログイン状態の HTTP テスト（隔離環境の cli コンテナから `php` で実行）。
 *
 * 対象は内部ネットワーク上の http://yzrh.test だけ。読み取りのリクエストと、
 * 拒否されることを確かめる最小限の POST（コメント投稿・XML-RPC）だけを送る。
 * 総当たり・大量リクエストは行わない（全体で 40 件程度）。
 *
 * PASS: 安全な状態。FAIL [F-xx]: 脆弱性が再現（.agents/findings.md の ID）。
 * INFO: WordPress 本体の仕様による観察結果（テーマの不具合としては数えない）。
 */

// CLI からだけ実行する。Web からのリクエストや、WordPress に読み込まれた場合は何もしない。
if ( 'cli' !== PHP_SAPI || defined( 'ABSPATH' ) ) {
	exit( 1 );
}

const YZRH_BASE = 'http://yzrh.test';

$fixture = json_decode( (string) getenv( 'YZRH_SEC_FIXTURE' ), true );
if ( ! is_array( $fixture ) ) {
	fwrite( STDERR, "YZRH_SEC_FIXTURE is missing\n" );
	exit( 1 );
}

$results = array( 'pass' => 0, 'fail' => 0, 'info' => 0 );

/**
 * HTTP リクエストを送り、ステータス・ヘッダー・本文を返す（リダイレクトは追わない）。
 */
function yzrh_http( $path, $method = 'GET', $body = null, $headers = array() ) {
	$ch = curl_init( YZRH_BASE . $path );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_HTTPHEADER     => $headers,
		)
	);
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
	$raw  = curl_exec( $ch );
	$info = curl_getinfo( $ch );
	curl_close( $ch );
	usleep( 100000 ); // 負荷をかけないよう 0.1 秒あける。

	$head = substr( (string) $raw, 0, $info['header_size'] );
	$hdrs = array();
	foreach ( preg_split( '/\r\n/', trim( $head ) ) as $line ) {
		if ( false !== strpos( $line, ':' ) ) {
			list( $k, $v ) = explode( ':', $line, 2 );
			$hdrs[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}
	return array( 'status' => (int) $info['http_code'], 'headers' => $hdrs, 'body' => substr( (string) $raw, $info['header_size'] ) );
}

function yzrh_test( $name, callable $fn ) {
	global $results;
	try {
		$note = $fn();
		$results['pass']++;
		echo "PASS  {$name}" . ( $note ? "\n      {$note}" : '' ) . "\n";
	} catch ( YzrhInfo $e ) {
		$results['info']++;
		echo "INFO  {$name}\n      " . str_replace( "\n", "\n      ", $e->getMessage() ) . "\n";
	} catch ( Throwable $e ) {
		$results['fail']++;
		echo "FAIL  {$name}\n      " . str_replace( "\n", "\n      ", $e->getMessage() ) . "\n";
	}
}

class YzrhInfo extends Exception {}

function yzrh_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** 同一ホスト内のリダイレクトを最大 3 回までたどる（パーマリンク形式の URL へ移動するため）。 */
function yzrh_get( $path ) {
	$r = yzrh_http( $path );
	for ( $i = 0; $i < 3 && isset( $r['headers']['location'] ); $i++ ) {
		$loc = parse_url( $r['headers']['location'] );
		yzrh_assert( ! isset( $loc['host'] ) || 'yzrh.test' === $loc['host'], 'off-site redirect: ' . $r['headers']['location'] );
		$r = yzrh_http( ( $loc['path'] ?? '/' ) . ( isset( $loc['query'] ) ? '?' . $loc['query'] : '' ) );
	}
	return $r;
}

/** 本文中で文字列が出現する箇所を、前後を含めて短く切り出す（証拠用。最大 $limit 箇所）。 */
function yzrh_excerpt( $body, $needle, $width = 90, $limit = 1 ) {
	$out    = array();
	$offset = 0;
	while ( count( $out ) < $limit && false !== ( $pos = strpos( $body, $needle, $offset ) ) ) {
		$start  = max( 0, $pos - $width );
		$out[]  = preg_replace( '/\s+/', ' ', substr( $body, $start, strlen( $needle ) + 2 * $width ) );
		$offset = $pos + strlen( $needle );
	}
	return implode( "\n        … ", $out );
}

/** 本文に含まれてはならない目印を探し、見つかれば出現箇所（最大 4 箇所）を証拠として失敗させる。 */
function yzrh_assert_absent( $body, array $needles, $where ) {
	$found = array();
	foreach ( $needles as $needle ) {
		if ( false !== strpos( $body, $needle ) ) {
			$found[] = "{$needle} (" . substr_count( $body, $needle ) . "x)\n        … " . yzrh_excerpt( $body, $needle, 90, 4 );
		}
	}
	yzrh_assert( empty( $found ), "{$where}:\n" . implode( "\n", $found ) );
}

/** 目印を含むスクリプト要素・イベント属性・javascript: URL を列挙する。 */
function yzrh_executable_markers( $html, $marker ) {
	if ( '' === trim( $html ) ) {
		return array( 'empty response body' );
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( $html );
	libxml_clear_errors();
	$hits = array();
	foreach ( ( new DOMXPath( $dom ) )->query( '//*' ) as $el ) {
		if ( 'script' === strtolower( $el->nodeName ) && false !== strpos( $el->textContent, $marker ) ) {
			// JSON-LD はデータであり実行されない。JSON として解析でき、要素を閉じる文字列がなければ安全。
			$is_jsonld = 'application/ld+json' === strtolower( $el->getAttribute( 'type' ) );
			if ( ! $is_jsonld || null === json_decode( $el->textContent ) || false !== stripos( $el->textContent, '</script' ) ) {
				$hits[] = '<script type="' . $el->getAttribute( 'type' ) . '">' . trim( substr( $el->textContent, 0, 80 ) );
			}
		}
		foreach ( $el->attributes as $attr ) {
			$name = strtolower( $attr->name );
			$is_event = 0 === strpos( $name, 'on' );
			$is_js    = in_array( $name, array( 'href', 'src', 'action', 'formaction' ), true ) && preg_match( '/^\s*javascript:/i', $attr->value );
			if ( ( $is_event || $is_js ) && false !== strpos( $attr->value, $marker ) ) {
				$hits[] = "<{$el->nodeName} {$name}=\"{$attr->value}\">";
			}
		}
	}
	return $hits;
}

/** JSON-LD をすべて取り出して配列で返す。 */
function yzrh_jsonld( $html ) {
	preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $m );
	return array_map( function ( $json ) { return json_decode( $json, true ); }, $m[1] );
}

/* ---------------------------------------------------------------------- */

$users = $fixture['users'];

yzrh_test( 'front page sends the theme security headers and no X-Pingback', function () {
	$r = yzrh_http( '/' );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	foreach ( array( 'x-frame-options' => 'SAMEORIGIN', 'x-content-type-options' => 'nosniff', 'referrer-policy' => 'strict-origin-when-cross-origin' ) as $k => $v ) {
		yzrh_assert( isset( $r['headers'][ $k ] ) && $v === $r['headers'][ $k ], "{$k}: " . ( $r['headers'][ $k ] ?? 'missing' ) );
	}
	yzrh_assert( ! isset( $r['headers']['x-pingback'] ), 'X-Pingback present' );
	yzrh_assert( ! isset( $r['headers']['strict-transport-security'] ), 'HSTS sent over plain HTTP' );
	yzrh_assert( false === stripos( $r['body'], 'name="generator"' ), 'generator meta present' );
} );

yzrh_test( 'REST API responses do not carry the theme security headers', function () {
	$r = yzrh_http( '/wp-json/' );
	yzrh_assert( 200 === $r['status'] && 0 === strpos( $r['headers']['content-type'] ?? '', 'application/json' ), "REST index not reached: {$r['status']}" );
	if ( ! isset( $r['headers']['x-content-type-options'] ) || ! isset( $r['headers']['referrer-policy'] ) ) {
		throw new YzrhInfo( "status {$r['status']}; x-content-type-options=" . ( $r['headers']['x-content-type-options'] ?? 'missing' ) . '; referrer-policy=' . ( $r['headers']['referrer-policy'] ?? 'missing' ) . '; x-frame-options=' . ( $r['headers']['x-frame-options'] ?? 'missing' ) );
	}
} );

yzrh_test( 'reflected input is escaped (search, order, paged, 404 path)', function () {
	$payload = '"><svg/onload=YZRHR(1)><script>YZRHR(2)</script>';
	$enc     = rawurlencode( $payload );
	foreach ( array( "/?s={$enc}", "/interview?order={$enc}", "/interview?order=ASC&x={$enc}", "/interview/page/2?order={$enc}", "/no-such-page-{$enc}", "/?author_name={$enc}", "/category/{$enc}" ) as $path ) {
		$r    = yzrh_http( $path );
		$hits = yzrh_executable_markers( $r['body'], 'YZRHR' );
		yzrh_assert( empty( $hits ), "{$path} ({$r['status']}): " . implode( ' | ', $hits ) );
		yzrh_assert( false === strpos( $r['body'], '<svg/onload=YZRHR' ), "{$path}: raw payload reflected" );
	}
} );

yzrh_test( '[F-01] author archive of a member without posts does not publish profile data', function () use ( $users ) {
	$first    = yzrh_http( '/?author=' . $users['member_a'] );
	$location = $first['headers']['location'] ?? '';
	$r        = yzrh_get( '/?author=' . $users['member_a'] );
	$persons = array_values( array_filter( yzrh_jsonld( $r['body'] ), function ( $s ) { return isset( $s['@type'] ) && 'Person' === $s['@type']; } ) );
	yzrh_assert_absent( $r['body'], array( 'MEMBER-A-BIO', 'member-a.example.test' ), "redirect: {$location}; status {$r['status']}; Person JSON-LD: " . wp_json_encode_fallback( $persons ) );
} );

yzrh_test( '[F-02] password-protected post: no cover image, excerpt or body in HTML, OGP or JSON-LD', function () use ( $fixture ) {
	$r = yzrh_get( '/?p=' . $fixture['post_protected'] );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	yzrh_assert_absent( $r['body'], array( 'SECRET-BODY-POST', 'SECRET-EXCERPT-POST' ), 'protected post text (control)' );
	yzrh_assert_absent( $r['body'], array( 'secret-protected-post-cover' ), 'protected post cover image' );
} );

yzrh_test( '[F-02] password-protected interview: single page hides custom fields and cover', function () use ( $fixture ) {
	$r = yzrh_get( '/?p=' . $fixture['interview_protected'] . '&post_type=interview' );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	yzrh_assert_absent( $r['body'], array( 'SECRET-BYLINE-NAME', 'SECRET-BYLINE-ROLE', 'SECRET-KICKER' ), 'protected interview fields (control)' );
	yzrh_assert_absent( $r['body'], array( 'secret-protected-interview-cover' ), 'protected interview cover image' );
} );

yzrh_test( '[F-02] interview archive does not reveal protected interviews\' custom fields, cover or length', function () {
	$r = yzrh_get( '/interview' );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	yzrh_assert_absent( $r['body'], array( 'SECRET-BYLINE-NAME', 'SECRET-BYLINE-ROLE', 'secret-protected-interview-cover', '>10 min<' ), 'interview archive' );
} );

yzrh_test( '[F-03] breadcrumbs of a public page do not reveal a private or draft parent', function () use ( $fixture ) {
	$found = array();
	foreach ( array( 'page_child_of_private' => 'SECRET-PRIVATE-PARENT', 'page_child_of_draft' => 'SECRET-DRAFT-PARENT' ) as $key => $marker ) {
		$r = yzrh_get( '/?page_id=' . $fixture[ $key ] );
		if ( false !== strpos( $r['body'], $marker ) ) {
			$found[] = "{$key} ({$r['status']}): " . yzrh_excerpt( $r['body'], $marker, 140 );
		}
	}
	yzrh_assert( empty( $found ), implode( "\n", $found ) );
} );

yzrh_test( 'contributor-controlled interview fields and block attributes are not executable (single and archive)', function () use ( $fixture ) {
	$r = yzrh_get( '/?p=' . $fixture['interview_xss'] . '&post_type=interview' );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	$hits = yzrh_executable_markers( $r['body'], 'YZRHXSS' );
	$a    = yzrh_get( '/interview' );
	$hits = array_merge( $hits, yzrh_executable_markers( $a['body'], 'YZRHXSS' ) );
	yzrh_assert( empty( $hits ), implode( "\n", $hits ) );
	return 'markers present but inert: ' . substr_count( $r['body'], 'YZRHXSS' ) . ' (single), ' . substr_count( $a['body'], 'YZRHXSS' ) . ' (archive)';
} );

yzrh_test( 'GTM code is printed inside <head> and right after <body>', function () {
	$r    = yzrh_http( '/' );
	$head = strpos( $r['body'], 'YZRH-GTM-HEAD' );
	$body = strpos( $r['body'], 'YZRH-GTM-BODY' );
	yzrh_assert( false !== $head && $head < strpos( $r['body'], '</head>' ), 'head code missing or outside <head>' );
	preg_match( '/<body[^>]*>/', $r['body'], $m, PREG_OFFSET_CAPTURE );
	$between = substr( $r['body'], $m[0][1] + strlen( $m[0][0] ), $body - $m[0][1] - strlen( $m[0][0] ) );
	yzrh_assert( false !== $body && '' === trim( preg_replace( '/<noscript>\s*<!--\s*$/', '', $between ) ), 'body code not right after <body>: ' . substr( $between, 0, 200 ) );
} );

yzrh_test( 'XML-RPC exposes no methods and refuses pingbacks', function () {
	$list = yzrh_http( '/xmlrpc.php', 'POST', '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>', array( 'Content-Type: text/xml' ) );
	preg_match_all( '#<string>([^<]+)</string>#', $list['body'], $m );
	$non_system = array_diff( $m[1], array( 'system.multicall', 'system.listMethods', 'system.getCapabilities' ) );
	yzrh_assert( empty( $non_system ), 'methods listed: ' . implode( ', ', $non_system ) );
	$ping = yzrh_http( '/xmlrpc.php', 'POST', '<?xml version="1.0"?><methodCall><methodName>pingback.ping</methodName><params><param><value><string>http://yzrh.test/a</string></value></param><param><value><string>http://yzrh.test/</string></value></param></params></methodCall>', array( 'Content-Type: text/xml' ) );
	yzrh_assert( false !== strpos( $ping['body'], 'faultCode' ), 'pingback not refused: ' . substr( $ping['body'], 0, 300 ) );
	$multi = yzrh_http( '/xmlrpc.php', 'POST', '<?xml version="1.0"?><methodCall><methodName>system.multicall</methodName><params><param><value><array><data><value><struct><member><name>methodName</name><value><string>wp.getUsersBlogs</string></value></member><member><name>params</name><value><array><data><value><string>lab</string></value><value><string>wrong</string></value></data></array></value></member></struct></value></data></array></value></param></params></methodCall>', array( 'Content-Type: text/xml' ) );
	yzrh_assert( false !== strpos( $multi['body'], '-32601' ) || false !== strpos( $multi['body'], 'not exist' ), 'system.multicall reached wp.getUsersBlogs: ' . substr( $multi['body'], 0, 300 ) );
	return 'only IXR introspection methods remain (' . implode( ', ', $m[1] ) . '); system.multicall cannot reach wp.* methods';
} );

yzrh_test( 'anonymous comment creation is refused (REST and wp-comments-post.php)', function () use ( $fixture ) {
	$rest = yzrh_http( '/wp-json/wp/v2/comments', 'POST', json_encode( array( 'post' => $fixture['post_public'], 'content' => 'YZRH-NEW-COMMENT', 'author_name' => 'x', 'author_email' => 'x@example.test' ) ), array( 'Content-Type: application/json' ) );
	yzrh_assert( $rest['status'] >= 400, "REST status {$rest['status']}" );
	$form = yzrh_http( '/wp-comments-post.php', 'POST', http_build_query( array( 'comment_post_ID' => $fixture['post_public'], 'comment' => 'YZRH-NEW-COMMENT', 'author' => 'x', 'email' => 'x@example.test' ) ) );
	yzrh_assert( $form['status'] >= 400, "form status {$form['status']}" );
	return "REST {$rest['status']}, form {$form['status']}";
} );

yzrh_test( 'comments left from before comments were disabled are still readable over REST', function () {
	$r = yzrh_http( '/wp-json/wp/v2/comments' );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	if ( false !== strpos( $r['body'], 'LEGACY-COMMENT' ) ) {
		throw new YzrhInfo( "status {$r['status']}: approved legacy comments (author name, content) are listed although the theme hides comments in templates" );
	}
} );

yzrh_test( 'user enumeration paths (WordPress core behaviour)', function () use ( $users ) {
	$notes = array();
	$r     = yzrh_http( '/?author=' . $users['member_a'] );
	$notes[] = "/?author=<member id> → {$r['status']} " . ( $r['headers']['location'] ?? '' );
	$u       = yzrh_get( '/wp-json/wp/v2/users' );
	$list    = json_decode( $u['body'], true );
	$notes[] = "/wp-json/wp/v2/users → {$u['status']}, slugs: " . implode( ', ', is_array( $list ) ? array_column( $list, 'slug' ) : array() );
	throw new YzrhInfo( implode( "\n", $notes ) );
} );

yzrh_test( '[F-04] repository-only files are not served from the theme directory', function () {
	$exposed = array();
	foreach ( array( '/.agents/AUDIT-2026-10-17.md', '/.agents/PROMPTS.md', '/AGENTS.md', '/.git/HEAD', '/.git/config', '/README.md', '/tests/run.php', '/tests/bootstrap.php' ) as $file ) {
		$r = yzrh_http( '/wp-content/themes/yzrh' . $file );
		if ( 200 === $r['status'] && '' !== trim( $r['body'] ) ) {
			$exposed[] = "{$file} → 200, " . strlen( $r['body'] ) . ' bytes, ' . ( $r['headers']['content-type'] ?? '' );
		}
	}
	yzrh_assert( empty( $exposed ), implode( "\n", $exposed ) );
} );

yzrh_test( 'control: an author with published posts keeps a public author page and Person JSON-LD', function () use ( $users ) {
	$r       = yzrh_get( '/?author=' . $users['author'] );
	$persons = array_filter( yzrh_jsonld( $r['body'] ), function ( $s ) { return isset( $s['@type'] ) && 'Person' === $s['@type']; } );
	yzrh_assert( 200 === $r['status'] && ! empty( $persons ), "status {$r['status']}, Person schemas: " . count( $persons ) );
} );

yzrh_test( 'control: a public post keeps its cover image in HTML, OGP and JSON-LD', function () use ( $fixture ) {
	$r = yzrh_get( '/?p=' . $fixture['post_public'] );
	yzrh_assert( 200 === $r['status'], "status {$r['status']}" );
	yzrh_assert( substr_count( $r['body'], 'public-post-cover' ) >= 3, 'cover image occurrences: ' . substr_count( $r['body'], 'public-post-cover' ) );
} );

yzrh_test( 'control: a public parent page still appears in the breadcrumbs', function () use ( $fixture ) {
	$r = yzrh_get( '/?page_id=' . $fixture['page_child_of_public'] );
	yzrh_assert( 200 === $r['status'] && false !== strpos( $r['body'], '"name":"PUBLIC-PARENT"' ), "status {$r['status']}" );
} );

yzrh_test( 'control: theme CSS and JS are still served', function () {
	foreach ( array( '/style.css', '/css/style.css', '/js/footerscript.js' ) as $file ) {
		$r = yzrh_http( '/wp-content/themes/yzrh' . $file );
		yzrh_assert( 200 === $r['status'], "{$file} → {$r['status']}" );
	}
} );

yzrh_test( 'control: theme PHP files return nothing when requested directly', function () {
	foreach ( array( '/functions.php', '/inc/seo.php', '/inc/customizer.php', '/single-interview.php', '/404.php' ) as $file ) {
		$r = yzrh_http( '/wp-content/themes/yzrh' . $file );
		yzrh_assert( '' === trim( $r['body'] ), "{$file} → {$r['status']}: " . substr( $r['body'], 0, 120 ) );
	}
} );

function wp_json_encode_fallback( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

echo "\n{$results['pass']} passed, {$results['fail']} failed, {$results['info']} info\n";
exit( $results['fail'] > 0 ? 1 : 0 );
