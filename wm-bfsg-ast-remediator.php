<?php
/**
 * Plugin Name:       WM BFSG AST Remediator
 * Plugin URI:        https://arnoldwender.com/wordpress-plugins/wm-bfsg-ast-remediator/
 * Description:       Ändert das HTML jeder Frontend-Seite vor der Auslieferung mit fünf festen Regeln: lang-Attribut, Sprunglink zum Hauptbereich, alt-Attribut für Bilder, aria-label für unbeschriftete Formularfelder, Namen für mehrere Navigationen. Prüft keine Barrierefreiheit und macht keine Seite BFSG-konform.
 * Version:           1.2.2
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Arnold Wender
 * Author URI:        https://arnoldwender.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wm-bfsg-ast-remediator
 * Domain Path:       /languages
 *
 * @package WM_BFSG_AST_Remediator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WM_BFSG_REMEDIATOR_VERSION', '1.2.2' );
define( 'WM_BFSG_REMEDIATOR_FILE', __FILE__ );
define( 'WM_BFSG_REMEDIATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WM_BFSG_REMEDIATOR_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'WMCyber\\BFSG\\Remediator\\';
	$base_dir = WM_BFSG_REMEDIATOR_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// The one engine: the frontend buffer, the admin simulator, WP-CLI and the hub self-test all run it.
require_once WM_BFSG_REMEDIATOR_PATH . 'includes/class-bfsg-rules-engine.php';
require_once WM_BFSG_REMEDIATOR_PATH . 'includes/class-bfsg-dom-transformer.php';

if ( class_exists( 'WMCyber\\BFSG\\Remediator\\Admin\\AdminDashboard' ) ) {
	\WMCyber\BFSG\Remediator\Admin\AdminDashboard::instance()->boot();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WM_BFSG_REMEDIATOR_PATH . 'includes/class-bfsg-cli.php';
}

// The settings panel's simulator posts to wm-bfsg/v1/remediate-html (manage_options).
add_action( 'rest_api_init', static function (): void {
	if ( class_exists( 'WMCyber\\BFSG\\Remediator\\API\\RestController' ) ) {
		( new \WMCyber\BFSG\Remediator\API\RestController() )->register_routes();
	}
} );

/**
 * Load translation catalogs for WordPress 6.4–6.7 compatibility (WordPress 6.8+ loads automatically).
 */
function wm_bfsg_load_textdomain(): void {
	load_plugin_textdomain(
		'wm-bfsg-ast-remediator',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'wm_bfsg_load_textdomain', 1 );

/**
 * Factual runtime status string for Block Bindings and GenerateBlocks dynamic tags (no compliance verdict).
 */
function wm_bfsg_get_status_value( string $field = 'summary' ): string {
	$available  = WM_BFSG_DOM_Transformer::engine_available();
	$engine_on  = '1' === (string) get_option( 'wm_bfsg_remediator_enabled', '1' );
	$rules_map  = [ 'lang' => true ] + WM_BFSG_DOM_Transformer::rules_from_options();
	$total      = count( $rules_map );
	$active     = ( $available && $engine_on ) ? count( array_filter( $rules_map ) ) : 0;

	switch ( $field ) {
		case 'enabled':
			return ( $available && $engine_on ) ? '1' : '0';
		case 'active_rules_count':
			return (string) $active;
		case 'total_rules_count':
			return (string) $total;
		case 'summary':
		default:
			if ( ! $available ) {
				return __( 'HTML-Regeln inaktiv (PHP dom oder mbstring fehlt)', 'wm-bfsg-ast-remediator' );
			}
			if ( ! $engine_on ) {
				return __( 'HTML-Regeln ausgeschaltet', 'wm-bfsg-ast-remediator' );
			}
			/* translators: %1$d: number of enabled rules, %2$d: total number of rules */
			return sprintf( __( 'HTML-Regeln aktiv (%1$d/%2$d Regeln eingeschaltet)', 'wm-bfsg-ast-remediator' ), $active, $total );
	}
}

/**
 * Register WordPress 6.5+ Block Bindings source `wm-bfsg-remediator/status`.
 */
function wm_bfsg_register_block_bindings(): void {
	if ( ! function_exists( 'register_block_bindings_source' ) ) {
		return;
	}
	if ( function_exists( 'get_all_registered_block_bindings_sources' ) ) {
		$existing = get_all_registered_block_bindings_sources();
		if ( is_array( $existing ) && isset( $existing['wm-bfsg-remediator/status'] ) ) {
			return;
		}
	}
	register_block_bindings_source(
		'wm-bfsg-remediator/status',
		[
			'label'              => __( 'WM BFSG Remediator Status', 'wm-bfsg-ast-remediator' ),
			'get_value_callback' => static function ( array $source_args ): string {
				$key = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : 'summary';
				return wm_bfsg_get_status_value( $key );
			},
			'uses_context'       => [],
		]
	);
}

/**
 * Render callback for GenerateBlocks 2.x Dynamic Tag `{{wm_bfsg_remediator_status}}`.
 *
 * @param array<string, mixed> $options  Dynamic tag options.
 * @param mixed                $block    Block context.
 * @param mixed                $instance Tag instance.
 */
function wm_bfsg_render_gb_dynamic_tag( array $options = [], mixed $block = null, mixed $instance = null ): string {
	$field = isset( $options['field'] ) && is_string( $options['field'] ) ? $options['field'] : 'summary';
	return esc_html( wm_bfsg_get_status_value( $field ) );
}

/**
 * Register GenerateBlocks 2.x Dynamic Tag `{{wm_bfsg_remediator_status}}` when GenerateBlocks 2.x is active.
 */
function wm_bfsg_register_generateblocks_tags(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}
	new \GenerateBlocks_Register_Dynamic_Tag(
		[
			'title'    => __( 'WM BFSG Remediator Status', 'wm-bfsg-ast-remediator' ),
			'tag'      => 'wm_bfsg_remediator_status',
			'type'     => 'wm-bfsg-ast-remediator',
			'supports' => [],
			'return'   => 'wm_bfsg_render_gb_dynamic_tag',
		]
	);
}

add_filter(
	'generateblocks_dynamic_tag_replacement',
	static function ( mixed $replacement, mixed $args = [] ): mixed {
		if ( is_array( $args ) && ( $args['tag'] ?? '' ) === 'wm_bfsg_remediator_status' && ( '' === $replacement || null === $replacement ) ) {
			$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : [];
			return wm_bfsg_render_gb_dynamic_tag( $options );
		}
		return $replacement;
	},
	10,
	2
);

/**
 * Boot Block Bindings, GenerateBlocks dynamic tags, and signal readiness to extensions.
 */
function wm_bfsg_boot_integrations(): void {
	wm_bfsg_register_block_bindings();
	wm_bfsg_register_generateblocks_tags();
	do_action( 'wm_bfsg_remediator_ready', WM_BFSG_REMEDIATOR_VERSION );
}
add_action( 'init', 'wm_bfsg_boot_integrations', 20 );

/**
 * Register with WM Suite Hub if active.
 */
function wm_bfsg_register_spoke_module(): void {
	if ( interface_exists( 'ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface' ) ) {
		require_once WM_BFSG_REMEDIATOR_PATH . 'includes/Adapters/Bfsg_Remediator_Spoke_Adapter.php';
		if ( class_exists( 'WMCyber\BFSG\Remediator\Adapters\Bfsg_Remediator_Spoke_Adapter' ) ) {
			add_filter( 'wm_register_suite_module', static function ( array $modules ): array {
				$modules[] = new \WMCyber\BFSG\Remediator\Adapters\Bfsg_Remediator_Spoke_Adapter();
				return $modules;
			} );
		}
	}
}
add_action( 'plugins_loaded', 'wm_bfsg_register_spoke_module', 20 );

/**
 * Intercept frontend HTML output and run the rules engine on it.
 */
function wm_bfsg_start_buffer(): void {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( ! WM_BFSG_DOM_Transformer::engine_available() ) {
		return;
	}

	// Kill-switch from the settings panel. Read here, at template_redirect, so a change
	// takes effect on the next request without a cache flush.
	if ( '1' !== (string) get_option( 'wm_bfsg_remediator_enabled', '1' ) ) {
		return;
	}

	ob_start( [ 'WM_BFSG_DOM_Transformer', 'buffer_handler' ] );
}
add_action( 'template_redirect', 'wm_bfsg_start_buffer', 1 );
