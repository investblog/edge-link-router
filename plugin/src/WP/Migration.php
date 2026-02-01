<?php
/**
 * Database Migration.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP;

/**
 * Handles database table creation and migrations.
 */
class Migration {

	/**
	 * Option name for storing DB version.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'cfelr_db_version';

	/**
	 * Run migrations on activation.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->create_tables();
		update_option( self::VERSION_OPTION, CFELR_DB_VERSION );
	}

	/**
	 * Check and run migrations if DB version changed.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$current_version = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $current_version < CFELR_DB_VERSION ) {
			$this->create_tables();
			update_option( self::VERSION_OPTION, CFELR_DB_VERSION );
		}
	}

	/**
	 * Create database tables using dbDelta.
	 *
	 * @return void
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Links table.
		$links_table = $wpdb->prefix . 'cfelr_links';
		$sql_links   = "CREATE TABLE {$links_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(200) NOT NULL,
			target_url TEXT NOT NULL,
			status_code SMALLINT NOT NULL DEFAULT 302,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			options_json LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_slug (slug)
		) {$charset_collate};";

		// Clicks daily table.
		$clicks_table = $wpdb->prefix . 'cfelr_clicks_daily';
		$sql_clicks   = "CREATE TABLE {$clicks_table} (
			day DATE NOT NULL,
			link_id BIGINT UNSIGNED NOT NULL,
			clicks INT UNSIGNED NOT NULL DEFAULT 0,
			UNIQUE KEY idx_day_link (day, link_id),
			KEY idx_link_id (link_id)
		) {$charset_collate};";

		// Integrations table.
		$integrations_table = $wpdb->prefix . 'cfelr_integrations';
		$sql_integrations   = "CREATE TABLE {$integrations_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(50) NOT NULL,
			state_json LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_provider (provider)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_links );
		dbDelta( $sql_clicks );
		dbDelta( $sql_integrations );
	}

	/**
	 * Drop all plugin tables.
	 * Used during uninstall.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfelr_links" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfelr_clicks_daily" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfelr_integrations" );
		// phpcs:enable

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Delete all plugin options.
	 * Used during uninstall.
	 *
	 * @return void
	 */
	public function delete_options(): void {
		delete_option( self::VERSION_OPTION );
		delete_option( 'cfelr_settings' );
		delete_option( 'cfelr_cf_token_encrypted' );
		delete_option( 'cfelr_reconcile_log' );
		delete_option( 'cfelr_cf_health' );
		delete_option( 'cfelr_edge_enabled' );
		delete_option( 'cfelr_last_publish' );
		delete_option( 'cfelr_cf_route_id' );
		delete_option( 'cfelr_sample_link_created' );
		delete_transient( 'cfelr_edge_publish_debounce' );
	}
}
