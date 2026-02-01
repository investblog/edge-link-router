<?php
/**
 * Uninstall Edge Link Router.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package EdgeLinkRouter
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define constants needed for cleanup.
define( 'CFELR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Load autoloader.
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'CFELR\\';
		$base_dir = CFELR_PLUGIN_DIR . 'src/';
		$len      = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Clean up on uninstall.
 */
function cfelr_uninstall_cleanup() {
	// Best-effort: Try to remove Cloudflare resources.
	try {
		$token_storage = new CFELR\Integrations\Cloudflare\TokenStorage();

		if ( $token_storage->has_token() ) {
			$deployer = new CFELR\Integrations\Cloudflare\Deployer();
			$deployer->full_cleanup();
		}
	} catch ( Exception $e ) {
		// Ignore errors - best effort cleanup.
		// If CF token is invalid or unavailable, just skip.
	}

	// Remove database tables.
	$migration = new CFELR\WP\Migration();
	$migration->drop_tables();

	// Remove options.
	$migration->delete_options();

	// Clear any scheduled cron events.
	$cron_hooks = array(
		'cfelr_stats_cleanup',
		'cfelr_reconcile',
		'cfelr_edge_publish_debounced',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}

cfelr_uninstall_cleanup();
