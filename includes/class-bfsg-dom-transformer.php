<?php
/**
 * WM BFSG AST Remediator — Server-Side DOM AST Transformation Engine
 *
 * Parses a full HTML document with DOMDocument, runs WM_BFSG_Rules_Engine on it and serialises the
 * result. The frontend buffer, the admin simulator (REST), WP-CLI and the hub self-test all call
 * transform(), so what the simulator shows is what visitors get.
 *
 * @package WM_BFSG_AST_Remediator
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WM_BFSG_DOM_Transformer {

	/**
	 * Map of settings-panel options to rules-engine keys. Rules without a toggle
	 * (document language) always run.
	 */
	private const RULE_OPTIONS = [
		'wm_bfsg_fix_skip_links' => 'skip_link',
		'wm_bfsg_fix_images'     => 'images_alt',
		'wm_bfsg_fix_labels'     => 'form_labels',
		'wm_bfsg_fix_aria'       => 'nav_landmarks',
	];

	/**
	 * Whether PHP has what the engine needs: ext-dom for DOMDocument, ext-mbstring for the UTF-8 round
	 * trip. WordPress itself runs without both; without this check the buffer ended every frontend
	 * request in a fatal error on such a host.
	 */
	public static function engine_available(): bool {
		return class_exists( 'DOMDocument' ) && function_exists( 'mb_encode_numericentity' );
	}

	/**
	 * Read the panel toggles into a rules map. Every toggle defaults to on, which is
	 * also the state a fresh install writes, so an absent option never disables a rule.
	 *
	 * @return array<string, bool>
	 */
	public static function rules_from_options(): array {
		$enabled = [];
		if ( ! function_exists( 'get_option' ) ) {
			return $enabled;
		}
		foreach ( self::RULE_OPTIONS as $option => $rule ) {
			$enabled[ $rule ] = '1' === (string) get_option( $option, '1' );
		}
		return $enabled;
	}

	/**
	 * Output-buffer handler for the frontend (ob_start callback).
	 *
	 * PHP calls it for every flush, not only at the end. It used to transform each chunk on its own: after a
	 * mid-page ob_flush() the first half was serialised as a complete document (closing </main></body></html>)
	 * and the rest was appended after </html>. Chunks are now collected and the page is transformed once, at the
	 * final call; output flushed before that is held back until the page is complete. ob_clean() discards what
	 * was collected, as it discards the buffer.
	 */
	public static function buffer_handler( string $chunk, int $phase ): string {
		static $page = '';

		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {
			$page = '';
			return '';
		}

		$page .= $chunk;
		if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
			return '';
		}

		$html = $page;
		$page = '';
		if ( '' === trim( $html ) || ! str_contains( $html, '<html' ) ) {
			return $html;
		}
		return self::transform( $html )['html'];
	}

	/**
	 * Transform raw HTML string and remediate accessibility issues.
	 *
	 * @param string                   $html
	 * @param array<string, bool>|null $enabled_rules Rule map; null reads the panel toggles.
	 * @return array{html: string, fixes: list<string>, fixed_count: int, modified: bool}
	 */
	public static function transform( string $html, ?array $enabled_rules = null ): array {
		if ( empty( trim( $html ) ) || ! str_contains( $html, '<html' ) || ! self::engine_available() ) {
			return [
				'html'        => $html,
				'fixes'       => [],
				'fixed_count' => 0,
				'modified'    => false,
			];
		}

		$prev_use_errors = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;

		// Force UTF-8 meta injection for DOMDocument parsing
		$encoded_html = mb_encode_numericentity( $html, [ 0x80, 0x10FFFF, 0, 0x1FFFFF ], 'UTF-8' );
		$loaded = @$dom->loadHTML( $encoded_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $prev_use_errors );
			return [
				'html'        => $html,
				'fixes'       => [],
				'fixed_count' => 0,
				'modified'    => false,
			];
		}

		$rules_config = $enabled_rules ?? self::rules_from_options();
		if ( function_exists( 'apply_filters' ) ) {
			$filtered_rules = apply_filters( 'wm_bfsg_remediator_rules_config', $rules_config, $html );
			if ( is_array( $filtered_rules ) ) {
				$rules_config = $filtered_rules;
			}
		}

		$result = WM_BFSG_Rules_Engine::apply_rules( $dom, $rules_config );

		libxml_clear_errors();
		libxml_use_internal_errors( $prev_use_errors );

		if ( 0 === $result['count'] ) {
			return [
				'html'        => $html,
				'fixes'       => [],
				'fixed_count' => 0,
				'modified'    => false,
			];
		}

		$output = $dom->saveHTML();
		$decoded_html = mb_decode_numericentity( (string) $output, [ 0x80, 0x10FFFF, 0, 0x1FFFFF ], 'UTF-8' );
		// saveHTML() writes non-ASCII as named entities (ß → &szlig;). Decode only those: a
		// general html_entity_decode() here (removed 2026-09-14) turned every &lt;script&gt;
		// the theme had escaped back into live markup — a site-wide XSS whenever a rule fired.
		// Numeric entities start with '#' and never match; anything that decodes to a
		// markup character is left as it came.
		$decoded_html = (string) preg_replace_callback(
			'/&(?!(?i:lt|gt|amp|quot|apos);)[a-zA-Z][a-zA-Z0-9]{1,31};/',
			static function ( array $m ): string {
				$decoded = html_entity_decode( $m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				return ( $decoded === $m[0] || 1 === preg_match( '/[<>&"\']/', $decoded ) ) ? $m[0] : $decoded;
			},
			$decoded_html
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered_html = apply_filters( 'wm_bfsg_remediator_html_output', $decoded_html, $result['fixed_rules'], $html );
			if ( is_string( $filtered_html ) ) {
				$decoded_html = $filtered_html;
			}
		}

		return [
			'html'        => $decoded_html,
			'fixes'       => $result['fixed_rules'],
			'fixed_count' => $result['count'],
			'modified'    => true,
		];
	}
}
