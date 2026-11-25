<?php
/**
 * WM Suite BFSG AST Remediator — Suite Hub Spoke Adapter
 *
 * Implements the WM Suite Hub contract (WM_Plugin_Module_Interface): health from the
 * kill-switch and the PHP extensions, telemetry from the saved rule toggles, a self-test that runs
 * the engine on a fixed document, and one SBOM component.
 *
 * @package WMCyber\BFSG\Remediator
 */

declare(strict_types=1);

namespace WMCyber\BFSG\Remediator\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface' ) ) {
	return;
}

use ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface;

final class Bfsg_Remediator_Spoke_Adapter implements WM_Plugin_Module_Interface {

	/**
	 * Fixed document for self_test: a <main> with an id but no skip link, an image without alt, a
	 * control inside a <label> (must keep its name) and one without any name.
	 */
	public const SELF_TEST_DOCUMENT = '<!DOCTYPE html><html lang="de"><head><title>T</title></head><body><main id="primary"><' . 'img src="/bild.jpg"><form><label>Name <input type="text" name="n"></label><input type="text" name="plz"></form></main></body></html>';

	public function get_module_slug(): string {
		return 'wm-bfsg-ast-remediator';
	}

	public function get_name(): string {
		return 'WM BFSG AST Remediator';
	}

	public function get_version(): string {
		return defined( 'WM_BFSG_REMEDIATOR_VERSION' ) ? WM_BFSG_REMEDIATOR_VERSION : '1.2.2';
	}

	public function get_description(): string {
		return 'Ändert das HTML der Frontend-Seiten mit fünf festen Regeln (lang, Sprunglink, alt, aria-label für Formularfelder, Navigationsnamen); prüft keine Barrierefreiheit.';
	}

	/**
	 * ERROR without ext-dom or ext-mbstring, NEEDS_CONFIG while the kill-switch is off, HEALTHY
	 * otherwise. Until 2026-09-14 the switched-off state answered "WARNING", which is not a hub status.
	 */
	public function get_health_status(): string {
		if ( ! \WM_BFSG_DOM_Transformer::engine_available() ) {
			return 'ERROR';
		}
		return self::engine_enabled() ? 'HEALTHY' : 'NEEDS_CONFIG';
	}

	private static function engine_enabled(): bool {
		return '1' === (string) get_option( 'wm_bfsg_remediator_enabled', '1' );
	}

	public function get_sbom_data(): array {
		return [
			'bom-ref'     => 'pkg:wordpress/wm-bfsg-ast-remediator@' . $this->get_version(),
			'type'        => 'application',
			'name'        => $this->get_name(),
			'version'     => $this->get_version(),
			'description' => $this->get_description(),
			'scope'       => 'required',
			'hashes'      => [
				[
					'alg'     => 'SHA-256',
					'content' => defined( 'WM_BFSG_REMEDIATOR_FILE' ) ? (string) hash_file( 'sha256', WM_BFSG_REMEDIATOR_FILE ) : '',
				],
			],
			// SPDX license identifier matching the plugin header and readme.txt.
			'licenses'    => [
				[
					'license' => [
						'id' => 'GPL-2.0-or-later',
					],
				],
			],
			// Features, not legal labels: applying the rules does not make a page BFSG or WCAG conformant.
			'properties'  => [
				[
					'name'  => 'wm:features:rules',
					'value' => implode( ', ', \WM_BFSG_Rules_Engine::RULES ),
				],
				[
					'name'  => 'wm:features:engine',
					'value' => 'DOMDocument output buffer at template_redirect, LIBXML_NONET',
				],
			],
		];
	}

	public function get_dependencies(): array {
		return [];
	}

	public function get_settings_url(): ?string {
		return admin_url( 'admin.php?page=wm-bfsg-remediator' );
	}

	/**
	 * The saved switches. The engine keeps no statistics; until 2026-09-14 this method read counters
	 * under keys nothing ever wrote and reported them as 0 / "Never".
	 */
	public function get_telemetry_metrics(): array {
		return [
			'engine_enabled'   => self::engine_enabled(),
			'engine_available' => \WM_BFSG_DOM_Transformer::engine_available(),
			'rules'            => [ 'lang' => true ] + \WM_BFSG_DOM_Transformer::rules_from_options(),
		];
	}

	public function execute_action( string $action, array $params = [] ): array {
		switch ( $action ) {
			case 'self_test':
				if ( ! \WM_BFSG_DOM_Transformer::engine_available() ) {
					return [ 'success' => false, 'message' => 'Selbsttest nicht möglich: PHP-Erweiterung dom oder mbstring fehlt.' ];
				}

				$all_on = array_fill_keys( \WM_BFSG_Rules_Engine::RULES, true );
				$result = \WM_BFSG_DOM_Transformer::transform( self::SELF_TEST_DOCUMENT, $all_on );
				$html   = $result['html'];

				$checks = [
					'Sprunglink auf #primary'        => str_contains( $html, 'href="#primary"' ),
					'alt am Bild'                    => (bool) preg_match( '/<' . 'img[^>]*\salt=/', $html ),
					'Feld im label behält den Namen' => ! (bool) preg_match( '/<input[^>]*name="n"[^>]*aria-label=/', $html ),
					'aria-label am Feld ohne Namen'  => (bool) preg_match( '/<input[^>]*name="plz"[^>]*aria-label="Plz"/', $html ),
				];
				$failed = array_keys( array_filter( $checks, static fn( bool $ok ): bool => ! $ok ) );

				return [
					'success' => [] === $failed,
					'message' => [] === $failed
						? sprintf( 'Selbsttest bestanden: %d Regeln haben das Testdokument wie erwartet geändert.', (int) $result['fixed_count'] )
						: 'Selbsttest fehlgeschlagen: ' . implode( ', ', $failed ) . '.',
				];

			default:
				return [
					'success' => false,
					'message' => sprintf( 'Unbekannte Spoke-Aktion "%s".', esc_html( $action ) ),
				];
		}
	}
}
