<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package ChamberNationEventsCalendar
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cnec_settings' );
delete_option( 'cnec_cache_version' );
delete_transient( 'cnec_github_release' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup of this plugin's transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cnec_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cnec_' ) . '%'
	)
);
