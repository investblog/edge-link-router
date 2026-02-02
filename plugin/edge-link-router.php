<?php
/**
 * Plugin Name:       Edge Link Router
 * Plugin URI:        https://github.com/investblog/edge-link-router
 * Description:       Simple redirect management with optional Cloudflare edge acceleration. Works immediately in WP-only mode, edge is optional.
 * Version:           1.0.5
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            301.st
 * Author URI:        https://301.st
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       edge-link-router
 * Domain Path:       /languages
 * GitHub Plugin URI: investblog/edge-link-router
 * Primary Branch:    main
 *
 * @package EdgeLinkRouter
 */

namespace CFELR;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'CFELR_VERSION', '1.0.2' );
define( 'CFELR_DB_VERSION', 1 );
define( 'CFELR_PLUGIN_FILE', __FILE__ );
define( 'CFELR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFELR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFELR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for PSR-4 compliant classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		// Project-specific namespace prefix.
		$prefix = 'CFELR\\';

		// Base directory for the namespace prefix.
		$base_dir = CFELR_PLUGIN_DIR . 'src/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// No, move to the next registered autoloader.
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators,
		// append with .php.
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Plugin activation hook.
 */
function cfelr_activate() {
	// Run migrations.
	$migration = new WP\Migration();
	$migration->run();

	// Register rewrite rules.
	$rewrite_handler = new WP\RewriteHandler();
	$rewrite_handler->register_rules();

	// Flush rewrite rules.
	flush_rewrite_rules();

	// Schedule cron jobs.
	$cron = new WP\Cron();
	$cron->schedule();

	// Create sample link on first activation.
	cfelr_maybe_create_sample_link();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\cfelr_activate' );

/**
 * Create a sample link on first activation.
 */
function cfelr_maybe_create_sample_link() {
	// Only create once.
	if ( get_option( 'cfelr_sample_link_created' ) ) {
		return;
	}

	$repository = new WP\Repository\WPLinkRepository();

	// Check if sample slug already exists.
	if ( $repository->find_by_slug( '301st' ) ) {
		update_option( 'cfelr_sample_link_created', true );
		return;
	}

	// Create sample link to 301.st.
	$link             = new Core\Models\Link();
	$link->slug       = '301st';
	$link->target_url = 'https://301.st';
	$link->status_code = 302;
	$link->enabled    = true;
	$link->options    = array(
		'passthrough_query' => false,
		'append_utm'        => array(
			'utm_source'   => 'edge-link-router',
			'utm_medium'   => 'wp-plugin',
			'utm_campaign' => 'sample-link',
		),
		'notes'             => __( 'Sample link created on plugin activation. Feel free to edit or delete.', 'edge-link-router' ),
	);

	$repository->create( $link );

	update_option( 'cfelr_sample_link_created', true );
}

/**
 * Plugin deactivation hook.
 */
function cfelr_deactivate() {
	// Remove rewrite rules.
	flush_rewrite_rules();

	// Unschedule cron jobs.
	$cron = new WP\Cron();
	$cron->unschedule();

	// Note: We keep Worker deployed (if any) and tables intact.
	// Route is removed via Cloudflare API if edge was enabled.
	$deployer = new Integrations\Cloudflare\Deployer();
	$deployer->remove_route_on_deactivation();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\cfelr_deactivate' );

/**
 * Plugin uninstall hook is registered separately in uninstall.php.
 */

/**
 * Initialize the plugin.
 */
function cfelr_init() {
	// Note: load_plugin_textdomain() not needed since WP 4.6 for WordPress.org hosted plugins.

	// Check and run migrations if needed.
	$migration = new WP\Migration();
	$migration->maybe_upgrade();

	// Initialize rewrite handler.
	$rewrite_handler = new WP\RewriteHandler();
	$rewrite_handler->init();

	// Initialize fallback handler.
	$fallback_handler = new WP\FallbackHandler();
	$fallback_handler->init();

	// Initialize cron.
	$cron = new WP\Cron();
	$cron->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfelr_init' );

/**
 * Initialize admin functionality.
 */
function cfelr_admin_init() {
	if ( ! is_admin() ) {
		return;
	}

	$admin_menu = new WP\Admin\AdminMenu();
	$admin_menu->init();

	$dashboard_widget = new WP\Admin\DashboardWidget();
	$dashboard_widget->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfelr_admin_init' );

/**
 * Initialize REST API.
 */
function cfelr_rest_init() {
	$links_controller = new WP\REST\LinksController();
	$links_controller->register_routes();

	$stats_controller = new WP\REST\StatsController();
	$stats_controller->register_routes();

	$diagnostics_controller = new WP\REST\DiagnosticsController();
	$diagnostics_controller->register_routes();

	$integrations_controller = new WP\REST\IntegrationsController();
	$integrations_controller->register_routes();
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\cfelr_rest_init' );
