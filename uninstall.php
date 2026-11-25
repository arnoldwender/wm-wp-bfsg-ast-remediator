<?php
/**
 * WM Suite BFSG AST Remediator — uninstaller.
 *
 * Preserves all options by default. Deletes the plugin's options only when the administrator has
 * explicitly opted in via `wm_bfsg_remediator_delete_data_on_uninstall`. `wm_bfsg_fix_headings` and
 * `wm_bfsg_telemetry` are no longer written by the current code but were written by earlier versions,
 * so they are removed alongside the active options when opt-in deletion is enabled.
 *
 * @package WMCyber\BFSG\Remediator
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wm_bfsg_delete_data = '1' === (string) get_option( 'wm_bfsg_remediator_delete_data_on_uninstall', '0' )
	|| ( function_exists( 'get_site_option' ) && '1' === (string) get_site_option( 'wm_bfsg_remediator_delete_data_on_uninstall', '0' ) );

if ( ! $wm_bfsg_delete_data ) {
	return;
}

$wm_bfsg_remediator_options = [
	'wm_bfsg_remediator_enabled',
	'wm_bfsg_remediator_delete_data_on_uninstall',
	'wm_bfsg_fix_skip_links',
	'wm_bfsg_fix_labels',
	'wm_bfsg_fix_aria',
	'wm_bfsg_fix_images',
	'wm_bfsg_fix_headings',
	'wm_bfsg_telemetry',
];

foreach ( $wm_bfsg_remediator_options as $wm_bfsg_remediator_option ) {
	delete_option( $wm_bfsg_remediator_option );
	if ( function_exists( 'delete_site_option' ) ) {
		delete_site_option( $wm_bfsg_remediator_option );
	}
}
