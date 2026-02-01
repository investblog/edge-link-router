<?php
/**
 * Admin Menu.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin;

/**
 * Registers and manages admin menu pages.
 */
class AdminMenu {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'edge-link-router';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Initialize admin menu.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Handle form submissions early (before any output).
		add_action( 'admin_init', array( $this, 'handle_early_actions' ) );
	}

	/**
	 * Handle form submissions that require redirects.
	 * Must run before any output.
	 *
	 * @return void
	 */
	public function handle_early_actions(): void {
		// Only handle our pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( strpos( $page, self::MENU_SLUG ) !== 0 ) {
			return;
		}

		// Check capability.
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// Links page actions.
		if ( $page === self::MENU_SLUG ) {
			$links_page = new Pages\LinksPage();
			$links_page->handle_early_actions();
		}

		// Integrations page actions.
		if ( $page === self::MENU_SLUG . '-integrations' ) {
			$integrations_page = new Pages\IntegrationsPage();
			$integrations_page->handle_early_actions();
		}
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		// Main menu.
		add_menu_page(
			__( 'Edge Link Router', 'edge-link-router' ),
			__( 'Link Router', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_links_page' ),
			'dashicons-external',
			30
		);

		// Links submenu (same as main).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Links', 'edge-link-router' ),
			__( 'Links', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_links_page' )
		);

		// Stats submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Statistics', 'edge-link-router' ),
			__( 'Stats', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-stats',
			array( $this, 'render_stats_page' )
		);

		// Integrations submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Integrations', 'edge-link-router' ),
			__( 'Integrations', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-integrations',
			array( $this, 'render_integrations_page' )
		);

		// Tools submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools', 'edge-link-router' ),
			__( 'Tools', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-tools',
			array( $this, 'render_tools_page' )
		);

		// Logs submenu.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logs', 'edge-link-router' ),
			__( 'Logs', 'edge-link-router' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our pages.
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'cfelr-admin',
			CFELR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CFELR_VERSION
		);

		wp_enqueue_script(
			'cfelr-admin',
			CFELR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CFELR_VERSION,
			true
		);

		wp_localize_script(
			'cfelr-admin',
			'cfelrAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'cfelr/v1/' ),
				'siteUrl' => home_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'confirmDelete'     => __( 'Are you sure you want to delete this link?', 'edge-link-router' ),
					'confirmBulkDelete' => __( 'Are you sure you want to delete %d selected link(s)? This action cannot be undone.', 'edge-link-router' ),
					'confirmClearLogs'  => __( 'Are you sure you want to clear all logs? This action cannot be undone.', 'edge-link-router' ),
					'error'             => __( 'An error occurred. Please try again.', 'edge-link-router' ),
					'running'           => __( 'Running...', 'edge-link-router' ),
					'testing'           => __( 'Testing...', 'edge-link-router' ),
					'copied'            => __( 'Copied!', 'edge-link-router' ),
				),
			)
		);
	}

	/**
	 * Render Links page.
	 *
	 * @return void
	 */
	public function render_links_page(): void {
		$page = new Pages\LinksPage();
		$page->render();
	}

	/**
	 * Render Stats page.
	 *
	 * @return void
	 */
	public function render_stats_page(): void {
		$page = new Pages\StatsPage();
		$page->render();
	}

	/**
	 * Render Integrations page.
	 *
	 * @return void
	 */
	public function render_integrations_page(): void {
		$page = new Pages\IntegrationsPage();
		$page->render();
	}

	/**
	 * Render Tools page.
	 *
	 * @return void
	 */
	public function render_tools_page(): void {
		$page = new Pages\ToolsPage();
		$page->render();
	}

	/**
	 * Render Logs page.
	 *
	 * @return void
	 */
	public function render_logs_page(): void {
		$page = new Pages\LogsPage();
		$page->render();
	}
}
