<?php
declare(strict_types=1);

namespace WMCyber\BFSG\Remediator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `POST /wp-json/wm-bfsg/v1/remediate-html` for the admin simulator (manage_options).
 *
 * It runs WM_BFSG_DOM_Transformer with the rule toggles saved in the panel, the same call the
 * frontend buffer makes. Until 2026-09-14 it ran a second engine (Core\Plugin with four Remediator
 * classes) that normalised headings and labelled controls differently from what visitors received,
 * and a `/telemetry` route returned counters nothing in the current code writes.
 */
final class RestController {
	private const REST_NAMESPACE = 'wm-bfsg/v1';

	/** Upper bound for a pasted document, so the simulator cannot be used to parse arbitrary megabytes. */
	public const MAX_HTML_BYTES = 500000;

	public function register_routes(): void {
		register_rest_route( self::REST_NAMESPACE, '/remediate-html', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle_remediate_html' ],
			'permission_callback' => [ $this, 'check_permissions' ],
			'args'                => [
				'raw_html' => [
					'type'     => 'string',
					'required' => true,
				],
			],
		] );
	}

	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	public function handle_remediate_html( WP_REST_Request $request ): WP_REST_Response {
		$raw_html = (string) $request->get_param( 'raw_html' );

		if ( '' === trim( $raw_html ) ) {
			return new WP_REST_Response( [ 'success' => false, 'error' => 'Bitte ein HTML-Dokument einfügen.' ], 400 );
		}
		if ( strlen( $raw_html ) > self::MAX_HTML_BYTES ) {
			return new WP_REST_Response( [ 'success' => false, 'error' => 'Das Dokument ist größer als 500 KB.' ], 413 );
		}
		if ( ! \WM_BFSG_DOM_Transformer::engine_available() ) {
			return new WP_REST_Response( [ 'success' => false, 'error' => 'PHP-Erweiterung dom oder mbstring fehlt; die Regeln laufen auf diesem Server nicht.' ], 503 );
		}
		if ( ! str_contains( $raw_html, '<html' ) ) {
			// The frontend buffer skips anything without <html>; the simulator says so instead of
			// pretending a fragment was checked.
			return new WP_REST_Response( [ 'success' => false, 'error' => 'Die Regeln laufen nur auf vollständigen Dokumenten mit <html>; ein Fragment bleibt auch im Frontend unverändert.' ], 422 );
		}

		$result = \WM_BFSG_DOM_Transformer::transform( $raw_html );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'html'        => $result['html'],
				'fixes'       => $result['fixes'],
				'fixed_count' => $result['fixed_count'],
				'modified'    => $result['modified'],
				'rules'       => \WM_BFSG_DOM_Transformer::rules_from_options(),
			],
		], 200 );
	}
}
