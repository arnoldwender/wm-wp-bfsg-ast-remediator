<?php
/**
 * WM BFSG AST Remediator — standalone test suite (no WordPress).
 *
 * Covers the one engine (WM_BFSG_Rules_Engine through WM_BFSG_DOM_Transformer) and everything that
 * calls it: the settings toggles, the REST simulator, WP-CLI, the hub adapter, the admin screen and the
 * uninstaller. WordPress functions are stubbed below; the Suite Hub contract is declared when the hub is
 * not on disk and compared with the real interface when it is.
 *
 * Run: php tests/test-suite.php   (exit 0 = green)
 *
 * @package WM_BFSG_AST_Remediator
 */

declare(strict_types=1);

namespace {
	// The real hub contract when the sibling repo is on disk, so the adapter is tested against it.
	$wm_hub_interface = dirname( __DIR__, 2 ) . '/wm-suite-hub/src/Contracts/WM_Plugin_Module_Interface.php';
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( is_file( $wm_hub_interface ) ) {
		require_once $wm_hub_interface;
	}
}

namespace ArnoldWender\SuiteHub\Contracts {
	if ( ! interface_exists( 'ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface' ) ) {
		interface WM_Plugin_Module_Interface {
			public function get_module_slug(): string;
			public function get_name(): string;
			public function get_version(): string;
			public function get_description(): string;
			public function get_health_status(): string;
			public function get_sbom_data(): array;
			public function get_dependencies(): array;
			public function get_settings_url(): ?string;
			public function get_telemetry_metrics(): array;
			public function execute_action( string $action, array $params = [] ): array;
		}
	}
}

namespace {
	define( 'WP_CLI', true );

	// ---------------------------------------------------------------------------
	// WordPress & GenerateBlocks stubs
	// ---------------------------------------------------------------------------
	$GLOBALS['wm_test_options']        = [];
	$GLOBALS['wm_test_site_options']   = [];
	$GLOBALS['wm_test_routes']         = [];
	$GLOBALS['wm_test_cap']            = true;
	$GLOBALS['wm_test_actions']        = [];
	$GLOBALS['wm_test_filters']        = [];
	$GLOBALS['wm_test_fired_actions']  = [];
	$GLOBALS['wm_test_settings']       = [];
	$GLOBALS['wm_test_textdomains']    = [];
	$GLOBALS['wm_test_block_bindings'] = [];
	$GLOBALS['wm_test_gb_tags']        = [];

	function plugin_dir_path( string $file ): string { return rtrim( dirname( $file ), '/\\' ) . '/'; }
	function plugin_dir_url( string $file ): string { return 'http://example.test/wp-content/plugins/wm-bfsg-ast-remediator/'; }
	function plugin_basename( string $file ): string { return basename( dirname( $file ) ) . '/' . basename( $file ); }
	function load_plugin_textdomain( string $domain, mixed $deprecated = false, string $plugin_rel_path = '' ): bool {
		$GLOBALS['wm_test_textdomains'][ $domain ] = $plugin_rel_path;
		return true;
	}
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['wm_test_actions'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
		return true;
	}
	function do_action( string $hook, mixed ...$args ): void {
		$GLOBALS['wm_test_fired_actions'][ $hook ][] = $args;
		if ( empty( $GLOBALS['wm_test_actions'][ $hook ] ) ) {
			return;
		}
		ksort( $GLOBALS['wm_test_actions'][ $hook ] );
		foreach ( $GLOBALS['wm_test_actions'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as [ $cb, $accepted ] ) {
				$cb( ...array_slice( $args, 0, $accepted ) );
			}
		}
	}
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['wm_test_filters'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
		return true;
	}
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		if ( empty( $GLOBALS['wm_test_filters'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['wm_test_filters'][ $hook ] );
		$all = array_merge( [ $value ], $args );
		foreach ( $GLOBALS['wm_test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as [ $cb, $accepted ] ) {
				$all[0] = $cb( ...array_slice( $all, 0, $accepted ) );
			}
		}
		return $all[0];
	}
	function register_setting( string $group, string $name, array $args = [] ): void {
		$GLOBALS['wm_test_settings'][ $name ] = [ 'group' => $group, 'args' => $args ];
	}
	function get_locale(): string { return 'de_DE'; }
	function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['wm_test_options'][ $key ] ?? $default; }
	function update_option( string $key, mixed $value, mixed $autoload = null ): bool { $GLOBALS['wm_test_options'][ $key ] = $value; return true; }
	function delete_option( string $key ): bool { unset( $GLOBALS['wm_test_options'][ $key ] ); return true; }
	function get_site_option( string $key, mixed $default = false ): mixed { return $GLOBALS['wm_test_site_options'][ $key ] ?? $default; }
	function delete_site_option( string $key ): bool { unset( $GLOBALS['wm_test_site_options'][ $key ] ); return true; }
	function register_block_bindings_source( string $name, array $props ): bool {
		$GLOBALS['wm_test_block_bindings'][ $name ] = $props;
		return true;
	}
	function get_all_registered_block_bindings_sources(): array {
		return $GLOBALS['wm_test_block_bindings'];
	}
	function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
	function __( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html__( string $text, string $domain = 'default' ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html_e( string $text, string $domain = 'default' ): void { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr_e( string $text, string $domain = 'default' ): void { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( mixed $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( mixed $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( string $url ): string { return $url; }
	function esc_url_raw( string $url ): string { return $url; }
	function esc_textarea( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function sanitize_key( string $key ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
	function sanitize_text_field( string $text ): string { return trim( $text ); }
	function wp_unslash( mixed $value ): mixed { return $value; }
	function admin_url( string $path = '' ): string { return 'http://example.test/wp-admin/' . $path; }
	function rest_url( string $path = '' ): string { return 'http://example.test/wp-json/' . $path; }
	function add_query_arg( array $args, string $url ): string { return $url . '?' . http_build_query( $args ); }
	function checked( mixed $checked, mixed $current = true, bool $echo = true ): string { $r = ( (bool) $checked === (bool) $current ) ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
	function wp_nonce_field( string $action, string $name ): void { echo '<input type="hidden" name="' . $name . '" value="nonce">'; }
	function wp_create_nonce( string $action ): string { return 'nonce'; }
	function wp_json_encode( mixed $data ): string { return (string) json_encode( $data ); }
	function current_user_can( string $cap ): bool { return (bool) $GLOBALS['wm_test_cap']; }
	function register_rest_route( string $ns, string $route, array $args ): bool { $GLOBALS['wm_test_routes'][] = $ns . $route; return true; }
	function esc_js( string $text ): string { return $text; }
	// The CLI asks WordPress, not PHP, whether it may write (Plugin Check,
	// WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, 2026-09-17).
	// On POSIX wp_is_writable() is is_writable(), which is what this stand-in does.
	function wp_is_writable( string $path ): bool { return is_writable( $path ); }

	final class GenerateBlocks_Register_Dynamic_Tag {
		public function __construct( public array $config ) {
			$GLOBALS['wm_test_gb_tags'][ (string) ( $config['tag'] ?? '' ) ] = $config;
		}
	}

	final class WP_REST_Server { public const CREATABLE = 'POST'; public const READABLE = 'GET'; }
	final class WP_REST_Request {
		public function __construct( private array $params = [] ) {}
		public function get_param( string $key ): mixed { return $this->params[ $key ] ?? null; }
	}
	final class WP_REST_Response {
		public function __construct( public mixed $data = null, public int $status = 200 ) {}
		public function get_data(): mixed { return $this->data; }
		public function get_status(): int { return $this->status; }
	}
	final class WP_CLI {
		public static array $out = [];
		public static function line( string $m ): void { self::$out[] = [ 'line', $m ]; }
		public static function success( string $m ): void { self::$out[] = [ 'success', $m ]; }
		public static function warning( string $m ): void { self::$out[] = [ 'warning', $m ]; }
		public static function error( string $m ): void { self::$out[] = [ 'error', $m ]; }
		public static function add_command( string $name, mixed $callable ): void { self::$out[] = [ 'command', $name ]; }
	}

	require_once __DIR__ . '/../wm-bfsg-ast-remediator.php';
	wm_bfsg_register_spoke_module();
	require_once __DIR__ . '/../includes/API/RestController.php';

	// ---------------------------------------------------------------------------
	// Harness
	// ---------------------------------------------------------------------------
	$total_tests  = 0;
	$passed_tests = 0;
	$failed_tests = 0;

	function assert_true( bool $condition, string $description ): void {
		global $total_tests, $passed_tests, $failed_tests;
		++$total_tests;
		if ( $condition ) {
			++$passed_tests;
			echo "  [PASS] {$description}\n";
		} else {
			++$failed_tests;
			echo "  [FAIL] {$description}\n";
		}
	}

	function assert_equals( mixed $expected, mixed $actual, string $description ): void {
		global $total_tests, $passed_tests, $failed_tests;
		++$total_tests;
		if ( $expected === $actual ) {
			++$passed_tests;
			echo "  [PASS] {$description}\n";
		} else {
			++$failed_tests;
			echo "  [FAIL] {$description} — Expected: " . var_export( $expected, true ) . ", Got: " . var_export( $actual, true ) . "\n";
		}
	}

	/** PHP source without comments, so an assertion cannot match its own explanation. */
	function code_without_comments( string $file ): string {
		$out = '';
		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}
			$out .= is_array( $token ) ? $token[1] : $token;
		}
		return $out;
	}

	echo "==============================================================================\n";
	echo "  WM BFSG AST REMEDIATOR — STANDALONE TEST SUITE\n";
	echo "==============================================================================\n\n";

	// --- Group 1: document language ---
	echo "[TEST GROUP 1] Document language\n";
	$raw_html_no_lang = '<!DOCTYPE html><html><head><title>Test</title></head><body><main><p>Hallo Welt</p></main></body></html>';
	$res1 = WM_BFSG_DOM_Transformer::transform( $raw_html_no_lang );
	assert_true( $res1['modified'], 'Detects and modifies missing lang attribute' );
	assert_true( str_contains( $res1['html'], 'lang="de-DE"' ), 'Injects lang="de-DE" into <html> element' );

	// --- Group 2: skip link ---
	echo "\n[TEST GROUP 2] Skip link to the main landmark\n";
	$raw_html_no_skip = '<!DOCTYPE html><html lang="de"><head><title>Test</title></head><body><header><nav>Menü</nav></header><main><p>Inhalt</p></main></body></html>';
	$res2 = WM_BFSG_DOM_Transformer::transform( $raw_html_no_skip );
	assert_true( str_contains( $res2['html'], 'Zum Inhalt springen' ), 'Injects a skip link' );
	assert_true( str_contains( $res2['html'], 'href="#main-content"' ) && str_contains( $res2['html'], 'id="main-content"' ), 'A <main> without id gets id="main-content" and the link points there' );

	$res2b = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><main id="primary"><p>x</p></main></body></html>' );
	assert_true( str_contains( $res2b['html'], 'href="#primary"' ) && ! str_contains( $res2b['html'], '#main-content' ), 'A <main id="primary"> gets a link to #primary, not to a missing #main-content' );

	$res2c = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><div><p>kein Hauptbereich</p></div></body></html>' );
	assert_true( ! str_contains( $res2c['html'], 'wm-bfsg-skip-link' ), 'No skip link on a page without a main landmark (it would lead nowhere)' );

	assert_true( (bool) preg_match( '#<head>.*<style>[^<]*\.wm-bfsg-skip-link:focus\{transform:none\}[^<]*</style>.*</head>#s', $res2b['html'] ), 'The injected link becomes visible on focus (style in <head>)' );
	assert_true( ! str_contains( $res2b['html'], 'top:-999px' ), 'No inline top:-999px that kept the focused link off screen' );

	$res2d = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a class="skip-link screen-reader-text" href="#content">Skip to content</a><main id="content"><p>x</p></main></body></html>' );
	assert_true( ! $res2d['modified'], 'An English theme skip link is recognised; nothing is injected' );

	// --- Group 3: form controls ---
	echo "\n[TEST GROUP 3] Form controls without a name\n";
	$raw_html_forms = '<!DOCTYPE html><html lang="de"><head><title>Test</title></head><body><main id="main-content"><a href="#main-content">Zum Inhalt springen</a><form><input type="text" name="vorname" placeholder="Vorname"><select name="kategorie"><option>Auswahl</option></select><textarea name="nachricht"></textarea></form></main></body></html>';
	$res3 = WM_BFSG_DOM_Transformer::transform( $raw_html_forms );
	assert_true( str_contains( $res3['html'], 'aria-label="Vorname"' ), 'Injects aria-label from placeholder into input' );
	assert_true( str_contains( $res3['html'], 'aria-label="Kategorie"' ), 'Injects aria-label from name into select' );
	assert_true( str_contains( $res3['html'], 'aria-label="Nachricht"' ), 'Injects aria-label from name into textarea' );

	// --- Group 4: images ---
	echo "\n[TEST GROUP 4] Image alt attributes\n";
	$raw_html_images = '<!DOCTYPE html><html lang="de"><head><title>Test</title></head><body><main id="main-content"><a href="#main-content">Zum Inhalt springen</a><img src="/assets/hero_banner_halle.webp"><img src="/assets/arrow_icon.svg"></main></body></html>';
	$res4 = WM_BFSG_DOM_Transformer::transform( $raw_html_images );
	assert_true( str_contains( $res4['html'], 'alt="Hero banner halle"' ), 'Content image gets its file name as alt' );
	assert_true( str_contains( $res4['html'], 'alt=""' ), 'Icon image gets alt=""' );

	// --- Group 5: navigation landmarks ---
	echo "\n[TEST GROUP 5] Several navigation landmarks\n";
	$raw_html_navs = '<!DOCTYPE html><html lang="de"><head><title>Test</title></head><body><a href="#main-content">Zum Inhalt springen</a><nav class="main-menu"><ul><li>Home</li></ul></nav><main id="main-content"><p>Content</p></main><footer class="footer-nav"><nav class="footer-links"><ul><li>Impressum</li></ul></nav></footer></body></html>';
	$res5 = WM_BFSG_DOM_Transformer::transform( $raw_html_navs );
	assert_true( str_contains( $res5['html'], 'aria-label="Hauptnavigation"' ), 'Main navigation named Hauptnavigation' );
	assert_true( str_contains( $res5['html'], 'aria-label="Fußzeilennavigation"' ), 'Footer navigation named Fußzeilennavigation' );

	// --- Group 6: passthrough ---
	echo "\n[TEST GROUP 6] A document no rule applies to stays byte-identical\n";
	$compliant_html = '<!DOCTYPE html><html lang="de"><head><title>Clean</title></head><body><a href="#main-content" class="skip">Zum Inhalt springen</a><main id="main-content"><p>Text</p></main></body></html>';
	$res6 = WM_BFSG_DOM_Transformer::transform( $compliant_html );
	assert_true( ! $res6['modified'] && $res6['html'] === $compliant_html, 'Unchanged document returned as it came' );
	assert_equals( 0, $res6['fixed_count'], 'Reports 0 rules applied' );

	// --- Group 7: hub adapter ---
	echo "\n[TEST GROUP 7] Suite Hub adapter\n";
	$adapter = new \WMCyber\BFSG\Remediator\Adapters\Bfsg_Remediator_Spoke_Adapter();
	$valid   = [ 'HEALTHY', 'DEGRADED', 'ERROR', 'NEEDS_CONFIG' ];
	assert_equals( 'wm-bfsg-ast-remediator', $adapter->get_module_slug(), 'Slug' );
	assert_equals( 'HEALTHY', $adapter->get_health_status(), 'Engine on (default) and dom/mbstring present: HEALTHY' );
	$GLOBALS['wm_test_options']['wm_bfsg_remediator_enabled'] = '0';
	assert_equals( 'NEEDS_CONFIG', $adapter->get_health_status(), 'Kill-switch off: NEEDS_CONFIG (was "WARNING", not a hub status)' );
	assert_true( false === $adapter->get_telemetry_metrics()['engine_enabled'], 'Telemetry reports the kill-switch state' );
	$GLOBALS['wm_test_options'] = [];
	assert_true( in_array( $adapter->get_health_status(), $valid, true ), 'Health is one of the four hub statuses' );
	$telemetry = $adapter->get_telemetry_metrics();
	assert_true( ! array_key_exists( 'total_ast_nodes_fixed', $telemetry ) && ! array_key_exists( 'total_pages_buffered', $telemetry ), 'Telemetry carries no counters the engine never writes' );
	assert_equals( [ 'lang' => true, 'skip_link' => true, 'images_alt' => true, 'form_labels' => true, 'nav_landmarks' => true ], $telemetry['rules'], 'Telemetry lists the five rules with their switches' );
	$self = $adapter->execute_action( 'self_test' );
	assert_true( true === $self['success'], 'self_test passes on the fixed document: ' . $self['message'] );
	assert_true( false === $adapter->execute_action( 'purge_cache' )['success'], 'Unknown action fails' );
	$sbom = $adapter->get_sbom_data();
	assert_equals( 64, strlen( (string) $sbom['hashes'][0]['content'] ), 'SBOM component hashes the main file' );
	assert_true( ! str_contains( (string) json_encode( $sbom['properties'] ), 'BFSG' ), 'SBOM properties name features, not compliance labels' );

	$hub_file = dirname( __DIR__, 2 ) . '/wm-suite-hub/src/Contracts/WM_Plugin_Module_Interface.php';
	if ( is_file( $hub_file ) ) {
		preg_match_all( '/public function (\w+)\s*\(/', (string) file_get_contents( $hub_file ), $m );
		$hub_methods = $m[1];
		sort( $hub_methods );
		$mine = array_map( static fn( \ReflectionMethod $r ): string => $r->getName(), ( new \ReflectionClass( 'ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface' ) )->getMethods() );
		sort( $mine );
		assert_equals( $hub_methods, $mine, 'Contract under test equals the real hub interface' );
	} else {
		echo "  [SKIP] Hub interface parity — wm-suite-hub not on disk\n";
	}

	// --- Group 8: escaping survives (P0-1) ---
	echo "\n[TEST GROUP 8] Escaped HTML stays escaped after remediation (XSS regression)\n";
	$raw_html_escaped = '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a href="#main-content">Zum Inhalt springen</a><main id="main-content"><p>&lt;img src=x onerror=alert(1)&gt;</p><p>&LT;script&GT;&nvlt;script&nvgt; Größe &euro;</p><a href="/?a=1&amp;b=2">L</a><img src="/foto.jpg"></main></body></html>';
	$res8 = WM_BFSG_DOM_Transformer::transform( $raw_html_escaped );
	assert_true( $res8['modified'], 'A rule fired on the escaped-payload document' );
	assert_true( str_contains( $res8['html'], '&lt;img src=x onerror=alert(1)&gt;' ), 'User-escaped markup is still escaped in the output' );
	assert_true( ! str_contains( $res8['html'], '<img src=x onerror' ), 'No live <img onerror> was created from escaped text' );
	assert_true( ! str_contains( $res8['html'], '<script' ), 'Upper-case and combining-char entity spellings do not become a <script> tag' );
	assert_true( str_contains( $res8['html'], 'href="/?a=1&amp;b=2"' ), 'Attribute ampersands stay entity-encoded' );
	assert_true( str_contains( $res8['html'], 'Größe €' ), 'Plain non-ASCII text is served as UTF-8' );

	// --- Group 9: toggles ---
	echo "\n[TEST GROUP 9] Rule toggles from the settings panel reach the engine\n";
	$raw_html_toggle = '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a href="#main-content">Zum Inhalt springen</a><main id="main-content"><img src="/hero_image.jpg"></main></body></html>';
	assert_true( str_contains( WM_BFSG_DOM_Transformer::transform( $raw_html_toggle )['html'], 'alt="Hero image"' ), 'Default: image alt rule runs' );
	$GLOBALS['wm_test_options']['wm_bfsg_fix_images'] = '0';
	assert_true( ! WM_BFSG_DOM_Transformer::transform( $raw_html_toggle )['modified'], 'wm_bfsg_fix_images=0 switches the image alt rule off' );
	$GLOBALS['wm_test_options'] = [];
	assert_true( ! WM_BFSG_DOM_Transformer::transform( $raw_html_toggle, [ 'images_alt' => false ] )['modified'], 'Explicit rule map switches the image alt rule off' );
	$main_file = code_without_comments( __DIR__ . '/../wm-bfsg-ast-remediator.php' );
	assert_true( (bool) preg_match( '/function wm_bfsg_start_buffer\(\).*?engine_available\(\).*?get_option\(\s*\'wm_bfsg_remediator_enabled\'/s', $main_file ), 'The buffer hook checks dom/mbstring and reads the kill-switch' );
	assert_true( str_contains( $main_file, "'wm_register_suite_module'" ), 'The adapter registers on wm_register_suite_module' );

	// --- Group 10: XPath injection ---
	echo "\n[TEST GROUP 10] Element ids cannot break the label lookup\n";
	$captured_warnings = [];
	set_error_handler( static function ( int $no, string $str ) use ( &$captured_warnings ): bool {
		$captured_warnings[] = $str;
		return true;
	} );
	$raw_html_xpath = '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a href="#main-content">Zum Inhalt springen</a><main id="main-content"><form><label for=\'q"x\'>Suche</label><input type="text" id=\'q"x\' name="q"></form></main></body></html>';
	$res10 = WM_BFSG_DOM_Transformer::transform( $raw_html_xpath );
	restore_error_handler();
	assert_equals( [], $captured_warnings, 'No PHP warning from an invalid XPath expression' );
	assert_true( ! str_contains( $res10['html'], 'aria-label=' ), 'A labelled input with a quote in its id is recognised as labelled' );

	// --- Group 11: controls that already have a name keep it ---
	echo "\n[TEST GROUP 11] Controls inside <label> or with title keep their name\n";
	$res11 = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a href="#m">Zum Inhalt springen</a><main id="m"><form><label>Name <input type="text" name="n"></label><label>Land <select name="land"><option>DE</option></select></label><input type="email" title="E-Mail" name="mail"><input type="image" src="/go.png" alt="Los"></form></main></body></html>' );
	assert_true( ! (bool) preg_match( '/<input[^>]*name="n"[^>]*aria-label/', $res11['html'] ), 'Input inside <label> keeps its visible label (was aria-label="N")' );
	assert_true( ! (bool) preg_match( '/<select[^>]*aria-label/', $res11['html'] ), 'Select inside <label> keeps its label' );
	assert_true( ! (bool) preg_match( '/<input[^>]*name="mail"[^>]*aria-label/', $res11['html'] ), 'Input with title keeps it (was aria-label="Mail")' );
	assert_true( ! (bool) preg_match( '/<input[^>]*type="image"[^>]*aria-label/', $res11['html'] ), 'Image button is left to its alt' );

	// --- Group 12: REST simulator runs the same engine ---
	echo "\n[TEST GROUP 12] REST simulator = frontend engine\n";
	$controller = new \WMCyber\BFSG\Remediator\API\RestController();
	$controller->register_routes();
	assert_equals( [ 'wm-bfsg/v1/remediate-html' ], $GLOBALS['wm_test_routes'], 'Only /remediate-html is registered (no /telemetry of counters nothing writes)' );
	// Every switchable rule has something to change here, so a simulator that skips any rule differs.
	$doc      = '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><nav class="main-menu">a</nav><main id="primary"><h1>A</h1><h4>B</h4><label>Name <input name="n"></label><input type="text" name="plz"><img src="/IMG_2034.jpg"></main><nav class="footer-links">b</nav></body></html>';
	$response = $controller->handle_remediate_html( new WP_REST_Request( [ 'raw_html' => $doc ] ) );
	$expected = WM_BFSG_DOM_Transformer::transform( $doc );
	assert_equals( 200, $response->get_status(), 'Full document: 200' );
	assert_equals( 4, $expected['fixed_count'], 'The comparison document triggers skip link, alt, aria-label and navigation names' );
	assert_equals( $expected['html'], $response->get_data()['data']['html'], 'Simulator output is byte-identical to the frontend transformer' );
	assert_true( str_contains( $response->get_data()['data']['html'], '<h4>B</h4>' ), 'Simulator does not renumber headings (the frontend never did; the old simulator engine did)' );
	assert_equals( 422, $controller->handle_remediate_html( new WP_REST_Request( [ 'raw_html' => '<form><input name="q"></form>' ] ) )->get_status(), 'Fragment without <html>: 422 with an explanation, not a pretended check' );
	assert_equals( 400, $controller->handle_remediate_html( new WP_REST_Request( [ 'raw_html' => '   ' ] ) )->get_status(), 'Empty input: 400' );
	assert_equals( 413, $controller->handle_remediate_html( new WP_REST_Request( [ 'raw_html' => '<html>' . str_repeat( 'x', \WMCyber\BFSG\Remediator\API\RestController::MAX_HTML_BYTES ) ] ) )->get_status(), 'Over 500 KB: 413' );
	$GLOBALS['wm_test_cap'] = false;
	assert_true( false === $controller->check_permissions(), 'Without manage_options the route is refused' );
	$GLOBALS['wm_test_cap'] = true;

	// --- Group 13: WP-CLI ---
	echo "\n[TEST GROUP 13] WP-CLI reports saved state and makes no accessibility verdict\n";
	$cli = new WM_BFSG_CLI();
	$GLOBALS['wm_test_options']['wm_bfsg_fix_labels'] = '0';
	WP_CLI::$out = [];
	$cli->status( [], [] );
	$status_text = implode( "\n", array_column( WP_CLI::$out, 1 ) );
	assert_true( (bool) preg_match( '/form_labels:\s+aus/', $status_text ) && (bool) preg_match( '/skip_link:\s+an/', $status_text ), 'status prints each rule switch as saved' );
	assert_true( ! str_contains( $status_text, 'BETRIEBSBEREIT' ) && ! str_contains( $status_text, 'WCAG 2.2 AA' ), 'status prints no fixed "ready" or standard label' );
	$GLOBALS['wm_test_options'] = [];
	$tmp = tempnam( sys_get_temp_dir(), 'bfsg' );
	file_put_contents( $tmp, $compliant_html );
	WP_CLI::$out = [];
	$cli->remediate( [ $tmp ], [] );
	$remediate_text = implode( "\n", array_column( WP_CLI::$out, 1 ) );
	assert_true( ! str_contains( $remediate_text, 'barrierefrei.' ) && str_contains( $remediate_text, 'keine Aussage' ), 'remediate with no change does not declare the document accessible' );
	file_put_contents( $tmp, '<p>Fragment</p>' );
	WP_CLI::$out = [];
	$cli->remediate( [ $tmp ], [ 'write' => true ] );
	assert_equals( 'warning', WP_CLI::$out[0][0] ?? '', 'remediate on a fragment warns and writes nothing' );
	assert_equals( '<p>Fragment</p>', (string) file_get_contents( $tmp ), 'fragment file left untouched' );
	unlink( $tmp );

	// --- Group 14: admin screen ---
	echo "\n[TEST GROUP 14] Admin screen: German, no emoji, no compliance promise, toggles = engine options\n";
	$dashboard = \WMCyber\BFSG\Remediator\Admin\AdminDashboard::instance();
	$emoji     = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{23E9}-\x{23FF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';
	$all_html  = '';
	foreach ( [ 'overview', 'simulator', 'rules', 'limits' ] as $tab ) {
		$_GET = [ 'tab' => $tab ];
		ob_start();
		$dashboard->render_dashboard();
		$html      = (string) ob_get_clean();
		$all_html .= $html;
		assert_true( str_contains( $html, 'class="wm-tab-link active" aria-current="page"' ), "Tab {$tab} renders and marks itself current" );
	}
	ob_start();
	$dashboard->render_dashboard_widget();
	$all_html .= (string) ob_get_clean();
	$_GET = [];
	assert_true( 0 === preg_match( $emoji, $all_html ), 'No emoji on the screen or the widget' );
	foreach ( [ 'GARANTIZADO', 'CUMPLIMIENTO', '100% Accesible', 'Accesibilidad', '&lt; 2ms', '0 CLS', 'Reparación' ] as $claim ) {
		assert_true( ! str_contains( $all_html, $claim ), "No \"{$claim}\" on the screen" );
	}
	preg_match_all( '/name="(wm_bfsg_fix_[a-z_]+)"/', $all_html, $fields );
	$engine_options = ( new \ReflectionClassConstant( 'WM_BFSG_DOM_Transformer', 'RULE_OPTIONS' ) )->getValue();
	$form_fields    = array_values( array_unique( $fields[1] ) );
	sort( $form_fields );
	$engine_keys = array_keys( $engine_options );
	sort( $engine_keys );
	assert_equals( $engine_keys, $form_fields, 'The form offers exactly the rule switches the engine reads (no headings, with skip links)' );
	assert_true( ! str_contains( $all_html, '.innerHTML' ), 'The simulator script writes server text with textContent' );

	$_POST = [ 'wm_bfsg_save_nonce' => 'nonce', 'wm_bfsg_fix_aria' => '1' ];
	function wp_verify_nonce( string $nonce, string $action ): bool { return 'nonce' === $nonce; }
	function wp_safe_redirect( string $url ): void { throw new \RuntimeException( 'redirect' ); }
	try {
		$dashboard->save_settings();
	} catch ( \RuntimeException $e ) {
		// wp_safe_redirect() is followed by exit in the plugin; the stub throws instead.
	}
	$_POST = [];
	assert_equals( '0', $GLOBALS['wm_test_options']['wm_bfsg_remediator_enabled'] ?? null, 'Saving an unchecked engine box stores 0' );
	assert_equals( '0', $GLOBALS['wm_test_options']['wm_bfsg_fix_skip_links'] ?? null, 'Skip-link switch is saved (the engine reads it)' );
	assert_equals( '1', $GLOBALS['wm_test_options']['wm_bfsg_fix_aria'] ?? null, 'Checked box stores 1' );
	assert_true( ! array_key_exists( 'wm_bfsg_fix_headings', $GLOBALS['wm_test_options'] ), 'No heading switch is saved (no rule reads it)' );
	$GLOBALS['wm_test_options'] = [];

	// --- Group 15: XXE ---
	echo "\n[TEST GROUP 15] External entities are not resolved\n";
	$xxe  = '<!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><html><body><main><p>&x;</p><img src="/a.jpg"></main></body></html>';
	$res15 = WM_BFSG_DOM_Transformer::transform( $xxe );
	assert_true( ! str_contains( $res15['html'], 'root:' ) && ! str_contains( $res15['html'], '/bin/' ), 'A local file entity does not leak into the output' );
	assert_true( (bool) preg_match( '/loadHTML\([^;]*LIBXML_NONET/', code_without_comments( __DIR__ . '/../includes/class-bfsg-dom-transformer.php' ) ), 'The parser keeps LIBXML_NONET' );
	assert_true( ! (bool) preg_match( '/LIBXML_(DTDLOAD|NOENT)/', code_without_comments( __DIR__ . '/../includes/class-bfsg-dom-transformer.php' ) ), 'No LIBXML_DTDLOAD or LIBXML_NOENT' );

	// --- Group 16: uninstall (opt-in D-UNINSTALL) ---
	echo "\n[TEST GROUP 16] Uninstall preserves data by default and removes every option only when opted in\n";
	$written = [];
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		preg_match_all( "/update_option\(\s*'([^']+)'/", (string) file_get_contents( (string) $file ), $m );
		$written = array_merge( $written, $m[1] );
	}
	$written = array_merge( $written, array_keys( $engine_options ) );
	$uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
	$missing   = array_values( array_filter( array_unique( $written ), static fn( string $o ): bool => ! str_contains( $uninstall, "'{$o}'" ) ) );
	assert_equals( [], $missing, 'Every written or read option is in uninstall.php' );

	$dashboard->register_settings();
	assert_equals( '0', $GLOBALS['wm_test_settings']['wm_bfsg_remediator_delete_data_on_uninstall']['args']['default'] ?? null, 'register_setting registers wm_bfsg_remediator_delete_data_on_uninstall with default 0' );

	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', true );
	}
	$GLOBALS['wm_test_options'] = [
		'wm_bfsg_remediator_enabled' => '0',
		'wm_bfsg_fix_images'         => '0',
	];
	require __DIR__ . '/../uninstall.php';
	assert_equals( '0', $GLOBALS['wm_test_options']['wm_bfsg_remediator_enabled'] ?? null, 'Default uninstall (no opt-in) preserves wm_bfsg_remediator_enabled' );
	assert_equals( '0', $GLOBALS['wm_test_options']['wm_bfsg_fix_images'] ?? null, 'Default uninstall (no opt-in) preserves rule toggles' );

	$GLOBALS['wm_test_options']['wm_bfsg_remediator_delete_data_on_uninstall'] = '1';
	require __DIR__ . '/../uninstall.php';
	assert_equals( [], $GLOBALS['wm_test_options'], 'Opt-in uninstall (wm_bfsg_remediator_delete_data_on_uninstall=1) removes all plugin options' );
	$GLOBALS['wm_test_options'] = [];

	// --- Group 17: malformed image URLs ---
	echo "\n[TEST GROUP 17] Image URLs that do not parse do not end the page; the decorative guess reads the path\n";
	try {
		$res17 = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><a href="#m">Zum Inhalt springen</a><main id="m"><img src="http:///x.jpg"><img src="https://icons.example.com/team_photo.jpg?icon=1"><img src="/Assets/Decor-Line.PNG"></main></body></html>' );
		$error17 = '';
	} catch ( \Throwable $t ) {
		$res17   = [ 'html' => '' ];
		$error17 = get_class( $t ) . ': ' . $t->getMessage();
	}
	assert_true( '' === $error17, 'An <img> with a malformed src does not throw (was TypeError from pathinfo(false))' . ( '' !== $error17 ? " — {$error17}" : '' ) );
	assert_true( (bool) preg_match( '#<img src="http:///x.jpg" alt="">#', $res17['html'] ), 'A src without a usable path gets alt=""' );
	assert_true( str_contains( $res17['html'], 'alt="Team photo"' ), '"icon" in the host or query does not hide a content image' );
	assert_true( (bool) preg_match( '#<img src="/Assets/Decor-Line.PNG" alt="">#', $res17['html'] ), '"Decor" in the path counts regardless of case' );

	// --- Group 18: output buffer with flushes ---
	echo "\n[TEST GROUP 18] A page flushed in parts is transformed once, as a whole\n";
	ob_start();
	ob_start( [ 'WM_BFSG_DOM_Transformer', 'buffer_handler' ] );
	echo '<!DOCTYPE html><html><head><title>T</title></head><body><main><p>erster Teil</p>';
	ob_flush();
	echo '<p>zweiter Teil</p></main></body></html>';
	ob_end_flush();
	$out18 = (string) ob_get_clean();
	assert_equals( 1, substr_count( $out18, '</html>' ), 'One </html> after a mid-page ob_flush() (was one per chunk)' );
	assert_true( (bool) preg_match( '#<main[^>]*>.*erster Teil.*zweiter Teil.*</main>#s', $out18 ) && str_contains( $out18, 'lang="de-DE"' ), 'Both parts end up inside <main> of one transformed document' );
	ob_start();
	ob_start( [ 'WM_BFSG_DOM_Transformer', 'buffer_handler' ] );
	echo '<!DOCTYPE html><html><body><main><p>Verworfene Ausgabe</p></main></body></html>';
	ob_flush();
	ob_clean();
	echo '<!DOCTYPE html><html><head><title>T</title></head><body><main><p>x</p></main></body></html>';
	ob_end_flush();
	$out18b = (string) ob_get_clean();
	assert_true( ! str_contains( $out18b, 'Verworfene Ausgabe' ) && str_contains( $out18b, 'lang="de-DE"' ), 'ob_clean() discards what was collected' );
	$main18 = code_without_comments( __DIR__ . '/../wm-bfsg-ast-remediator.php' );
	assert_true( str_contains( $main18, "ob_start( [ 'WM_BFSG_DOM_Transformer', 'buffer_handler' ] )" ), 'The frontend buffer uses that handler' );

	// --- Group 19: skip-link target id ---
	echo "\n[TEST GROUP 19] The id given to <main> is not one another element already has\n";
	$res19 = WM_BFSG_DOM_Transformer::transform( '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><div id="main-content">Teaser</div><main><p>x</p></main></body></html>' );
	assert_true( str_contains( $res19['html'], '<main id="main-content-2">' ) && str_contains( $res19['html'], 'href="#main-content-2"' ), 'With #main-content taken, <main> gets main-content-2 and the link points there' );

	// --- Group 20: WP-CLI --write ---
	echo "\n[TEST GROUP 20] remediate --write reports a file it could not write\n";
	$tmp20 = tempnam( sys_get_temp_dir(), 'bfsg' );
	file_put_contents( $tmp20, $raw_html_no_lang );
	chmod( $tmp20, 0444 );
	if ( is_writable( $tmp20 ) ) {
		echo "  [SKIP] read-only file is writable for this user (root)\n";
	} else {
		WP_CLI::$out = [];
		$cli->remediate( [ $tmp20 ], [ 'write' => true ] );
		$kinds20 = array_column( WP_CLI::$out, 0 );
		assert_true( in_array( 'error', $kinds20, true ) && ! str_contains( implode( "\n", array_column( WP_CLI::$out, 1 ) ), 'Datei aktualisiert' ), 'A read-only file ends in an error, not in "Datei aktualisiert"' );
		assert_equals( $raw_html_no_lang, (string) file_get_contents( $tmp20 ), 'The read-only file is unchanged' );
	}
	chmod( $tmp20, 0644 );
	unlink( $tmp20 );

	// --- Group 21: rule codes ---
	echo "\n[TEST GROUP 21] Rule codes say what was added, not that something was repaired\n";
	$codes21 = WM_BFSG_DOM_Transformer::transform( $doc )['fixes'];
	$bad21   = array_values( array_filter( $codes21, static fn( string $c ): bool => 1 !== preg_match( '/^WCAG_[0-9.]+_[A-Z_]+_ADDED(_[0-9]+)?$/', $c ) ) );
	assert_true( [] !== $codes21 && [] === $bad21, 'Every code ends in ADDED (was REPAIRED, INJECTED, DISAMBIGUATED): ' . implode( ', ', $bad21 ) );

	// --- Group 22: i18n, hooks, Block Bindings & GenerateBlocks bridges ---
	echo "\n[TEST GROUP 22] Textdomain loading, developer hooks, Block Bindings & GenerateBlocks dynamic tags\n";
	do_action( 'init' );
	assert_true( isset( $GLOBALS['wm_test_textdomains']['wm-bfsg-ast-remediator'] ) && str_ends_with( $GLOBALS['wm_test_textdomains']['wm-bfsg-ast-remediator'], '/languages' ), 'load_plugin_textdomain runs on init for wm-bfsg-ast-remediator' );
	// The plugin's own constant, defined by the main file loaded above: a literal had to be edited on every release.
	assert_equals( [ [ WM_BFSG_REMEDIATOR_VERSION ] ], $GLOBALS['wm_test_fired_actions']['wm_bfsg_remediator_ready'] ?? [], 'wm_bfsg_remediator_ready action fires on init with the plugin version' );

	// Block Bindings source wm-bfsg-remediator/status
	assert_true( isset( $GLOBALS['wm_test_block_bindings']['wm-bfsg-remediator/status'] ), 'Registers WordPress 6.5+ Block Bindings source wm-bfsg-remediator/status' );
	$bb_cb = $GLOBALS['wm_test_block_bindings']['wm-bfsg-remediator/status']['get_value_callback'] ?? null;
	assert_equals( '1', is_callable( $bb_cb ) ? $bb_cb( [ 'key' => 'enabled' ] ) : '', 'Block Bindings enabled=1 when engine is on' );
	assert_equals( '5', is_callable( $bb_cb ) ? $bb_cb( [ 'key' => 'active_rules_count' ] ) : '', 'Block Bindings active_rules_count=5 by default' );
	assert_true( is_callable( $bb_cb ) && str_contains( $bb_cb( [] ), '5/5' ), 'Block Bindings summary reports 5/5 active rules without compliance claims' );

	// GenerateBlocks 2.x Dynamic Tag {{wm_bfsg_remediator_status}}
	assert_true( isset( $GLOBALS['wm_test_gb_tags']['wm_bfsg_remediator_status'] ), 'Registers GenerateBlocks 2.x dynamic tag wm_bfsg_remediator_status' );
	$gb_out = apply_filters( 'generateblocks_dynamic_tag_replacement', '', [ 'tag' => 'wm_bfsg_remediator_status', 'options' => [ 'field' => 'active_rules_count' ] ] );
	assert_equals( '5', $gb_out, 'generateblocks_dynamic_tag_replacement resolves wm_bfsg_remediator_status' );
	assert_true( null === apply_filters( 'generateblocks_dynamic_tag_replacement', null, [ 'tag' => 'other_tag' ] ), 'generateblocks_dynamic_tag_replacement passes null untouched for foreign tags' );

	// Developer filters wm_bfsg_remediator_rules_config and wm_bfsg_remediator_html_output
	add_filter( 'wm_bfsg_remediator_rules_config', static function ( array $cfg ): array {
		$cfg['images_alt'] = false;
		return $cfg;
	} );
	$res22a = WM_BFSG_DOM_Transformer::transform( $raw_html_toggle );
	assert_true( ! $res22a['modified'], 'wm_bfsg_remediator_rules_config filter can override enabled rules per request' );
	$GLOBALS['wm_test_filters']['wm_bfsg_remediator_rules_config'] = [];

	add_filter( 'wm_bfsg_remediator_html_output', static fn( string $out, array $fixes ): string => $out . '<!-- rules:' . count( $fixes ) . ' -->', 10, 2 );
	$res22b = WM_BFSG_DOM_Transformer::transform( $raw_html_toggle );
	assert_true( str_contains( $res22b['html'], '<!-- rules:1 -->' ), 'wm_bfsg_remediator_html_output filter receives transformed HTML and rule codes' );
	$GLOBALS['wm_test_filters']['wm_bfsg_remediator_html_output'] = [];

	echo "\n==============================================================================\n";
	echo "  TEST SUMMARY: {$passed_tests}/{$total_tests} passed ({$failed_tests} failed)\n";
	echo "==============================================================================\n\n";

	exit( $failed_tests > 0 ? 1 : 0 );
}
