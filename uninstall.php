<?php
/**
 * Runs when the plugin is deleted from WordPress admin.
 *
 * Removes all options, transients, and pending Action Scheduler actions
 * created by this plugin so no orphaned data is left behind.
 *
 * @package BentoPMProIntegration
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove settings and sync-status options.
delete_option( 'bento_pmpro_integration_settings' );
delete_option( 'bento_pmpro_sync_status' );

// Remove the Bento fields cache transient.
delete_transient( 'bento_pmpro_fields_cache' );

// Cancel all pending / in-progress Action Scheduler actions for this plugin.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'bento_pmpro_as_sync',  [], 'bento_pmpro_integration' );
	as_unschedule_all_actions( 'bento_pmpro_as_event', [], 'bento_pmpro_integration' );
}
