<?php
/**
 * Mutations for tests/run-mutations.sh: each entry undoes one correction with literal replacements
 * [ file, search, replace ]; tests/test-suite.php must turn red for every one of them.
 *
 * Single-quoted strings only: a double-quoted search string would interpolate "$" and never match.
 *
 * @package WM_BFSG_AST_Remediator
 */

return [
	[
		'a malformed image URL reaches pathinfo( false ) again',
		[
			[ 'includes/class-bfsg-rules-engine.php', '$path       = is_string( $path ) ? $path : \'\';', '$path       = $path ?? \'\';' ],
		],
	],
	[
		'the decorative guess reads the whole src again',
		[
			[ 'includes/class-bfsg-rules-engine.php', '$path_lc = strtolower( $path );', '$path_lc = $src;' ],
		],
	],
	[
		'each flushed chunk is transformed as its own document again',
		[
			[ 'includes/class-bfsg-dom-transformer.php', '$page .= $chunk;' . "\n\t\t" . 'if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {' . "\n\t\t\t" . 'return \'\';' . "\n\t\t" . '}', '$page = $chunk;' ],
		],
	],
	[
		'ob_clean() no longer discards what was collected',
		[
			[ 'includes/class-bfsg-dom-transformer.php', 'if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {' . "\n\t\t\t" . '$page = \'\';' . "\n\t\t\t" . 'return \'\';' . "\n\t\t" . '}', '' ],
		],
	],
	[
		'the frontend buffer transforms chunks directly again',
		[
			[ 'wm-bfsg-ast-remediator.php', 'ob_start( [ \'WM_BFSG_DOM_Transformer\', \'buffer_handler\' ] );', 'ob_start( static fn( string $b ): string => WM_BFSG_DOM_Transformer::transform( $b )[\'html\'] );' ],
		],
	],
	[
		'main gets an id another element already has',
		[
			[ 'includes/class-bfsg-rules-engine.php', '( $taken = $xpath->query( \'//*[@id="\' . $target . \'"]\' ) ) && $taken->length > 0', 'false' ],
		],
	],
	[
		'remediate --write reports success for a file it could not write',
		[
			[ 'includes/class-bfsg-cli.php', 'if ( ! wp_is_writable( $file ) || false === file_put_contents( $file, $result[\'html\'] ) ) {', 'if ( false ) {' ],
		],
	],
	[
		'the image rule reports REPAIRED again',
		[
			[ 'includes/class-bfsg-rules-engine.php', '"WCAG_1.1.1_IMAGE_ALT_ADDED_{$count}"', '"WCAG_1.1.1_IMAGE_ALT_REPAIRED_{$count}"' ],
		],
	],
	[
		'escaped markup is decoded back into the page (the 2026-09-13 XSS)',
		[
			[ 'includes/class-bfsg-dom-transformer.php', 'return ( $decoded === $m[0] || 1 === preg_match( \'/[<>&"\\\']/\', $decoded ) ) ? $m[0] : $decoded;', 'return $decoded;' ],
			[ 'includes/class-bfsg-dom-transformer.php', '\'/&(?!(?i:lt|gt|amp|quot|apos);)[a-zA-Z][a-zA-Z0-9]{1,31};/\'', '\'/&[a-zA-Z][a-zA-Z0-9]{1,31};/\'' ],
		],
	],
	[
		'the kill-switch is not read by the buffer',
		[
			[ 'wm-bfsg-ast-remediator.php', 'if ( \'1\' !== (string) get_option( \'wm_bfsg_remediator_enabled\', \'1\' ) ) {', 'if ( false ) {' ],
		],
	],
	[
		'the skip link points at #main-content whatever id the landmark has',
		[
			[ 'includes/class-bfsg-rules-engine.php', '$skip_link->setAttribute( \'href\', \'#\' . $target );', '$skip_link->setAttribute( \'href\', \'#main-content\' );' ],
		],
	],
	[
		'uninstall deletes saved options even without opt-in',
		[
			[ 'uninstall.php', 'if ( ! $wm_bfsg_delete_data ) {', 'if ( false ) {' ],
		],
	],
	[
		'load_plugin_textdomain is not hooked on init',
		[
			[ 'wm-bfsg-ast-remediator.php', 'add_action( \'init\', \'wm_bfsg_load_textdomain\', 1 );', '' ],
		],
	],
	[
		'wm_bfsg_remediator_rules_config filter is bypassed',
		[
			[ 'includes/class-bfsg-dom-transformer.php', '$filtered_rules = apply_filters( \'wm_bfsg_remediator_rules_config\', $rules_config, $html );', '$filtered_rules = $rules_config;' ],
		],
	],
	[
		'wm_bfsg_remediator_html_output filter is bypassed',
		[
			[ 'includes/class-bfsg-dom-transformer.php', '$filtered_html = apply_filters( \'wm_bfsg_remediator_html_output\', $decoded_html, $result[\'fixed_rules\'], $html );', '$filtered_html = $decoded_html;' ],
		],
	],
	[
		'Block Bindings source wm-bfsg-remediator/status is not registered',
		[
			[ 'wm-bfsg-ast-remediator.php', 'wm_bfsg_register_block_bindings();', '' ],
		],
	],
	[
		'GenerateBlocks dynamic tag wm_bfsg_remediator_status is not registered',
		[
			[ 'wm-bfsg-ast-remediator.php', 'wm_bfsg_register_generateblocks_tags();', '' ],
		],
	],
];
