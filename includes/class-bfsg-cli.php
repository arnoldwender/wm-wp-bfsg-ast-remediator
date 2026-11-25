<?php
/**
 * WM BFSG AST Remediator — WP-CLI commands `wp bfsg status` and `wp bfsg remediate`.
 *
 * @package WM_BFSG_AST_Remediator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WM_BFSG_CLI {

	/**
	 * Show the saved switches: engine on/off, each rule, and whether PHP has ext-dom and ext-mbstring.
	 *
	 * Until 2026-09-14 this printed a fixed version, "BFSG 2025 / BITV 2.0 / WCAG 2.2 AA" and
	 * "AKTIV & BETRIEBSBEREIT" whatever the settings were.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bfsg status
	 */
	public function status( array $args, array $assoc_args ): void {
		$on    = static fn( bool $value ): string => $value ? 'an' : 'aus';
		$rules = [ 'lang' => true ] + WM_BFSG_DOM_Transformer::rules_from_options();

		WP_CLI::line( 'WM BFSG AST Remediator ' . ( defined( 'WM_BFSG_REMEDIATOR_VERSION' ) ? WM_BFSG_REMEDIATOR_VERSION : '' ) );
		WP_CLI::line( 'Engine (wm_bfsg_remediator_enabled): ' . $on( '1' === (string) get_option( 'wm_bfsg_remediator_enabled', '1' ) ) );
		WP_CLI::line( 'PHP dom + mbstring:                 ' . ( WM_BFSG_DOM_Transformer::engine_available() ? 'vorhanden' : 'FEHLT' ) );
		foreach ( $rules as $rule => $enabled ) {
			WP_CLI::line( sprintf( 'Regel %-14s                 %s', $rule . ':', $on( $enabled ) ) );
		}
	}

	/**
	 * Run the rules on an HTML file with the saved rule switches and list what changed.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a full HTML document (with <html>).
	 *
	 * [--write]
	 * : Overwrite the file with the changed HTML.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bfsg remediate /path/to/template.html
	 */
	public function remediate( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';

		if ( empty( $file ) || ! file_exists( $file ) ) {
			WP_CLI::error( "HTML-Datei nicht gefunden: {$file}" );
			return;
		}

		$html = file_get_contents( $file );
		if ( false === $html ) {
			WP_CLI::error( "Fehler beim Lesen der Datei: {$file}" );
			return;
		}

		if ( ! WM_BFSG_DOM_Transformer::engine_available() ) {
			WP_CLI::error( 'PHP-Erweiterung dom oder mbstring fehlt.' );
			return;
		}
		if ( ! str_contains( $html, '<html' ) ) {
			WP_CLI::warning( 'Kein <html> im Dokument: die Regeln laufen nur auf vollständigen Dokumenten, die Datei bleibt unverändert.' );
			return;
		}

		$result = WM_BFSG_DOM_Transformer::transform( $html );

		// "No rule changed anything" is not "the document is accessible": until 2026-09-14 this
		// branch said "Keine BFSG-Verstöße gefunden. Dokument ist barrierefrei."
		if ( ! $result['modified'] ) {
			WP_CLI::success( 'Keine der aktiven Regeln hat etwas geändert. Das ist keine Aussage über die Barrierefreiheit des Dokuments.' );
			return;
		}

		WP_CLI::success( sprintf( '%d Regeln haben das Dokument geändert:', $result['fixed_count'] ) );
		foreach ( $result['fixes'] as $fix ) {
			WP_CLI::line( "  - {$fix}" );
		}

		if ( isset( $assoc_args['write'] ) ) {
			// The result was ignored until 2026-09-15: a read-only file was reported as updated.
			if ( ! wp_is_writable( $file ) || false === file_put_contents( $file, $result['html'] ) ) {
				WP_CLI::error( "Datei konnte nicht geschrieben werden: {$file}" );
				return;
			}
			WP_CLI::success( "Datei aktualisiert: {$file}" );
		}
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'bfsg', 'WM_BFSG_CLI' );
}
