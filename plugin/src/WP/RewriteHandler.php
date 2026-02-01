<?php
/**
 * Rewrite Handler.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress rewrite rules for redirect URLs.
 */
class RewriteHandler {

	/**
	 * Query var name.
	 *
	 * @var string
	 */
	public const QUERY_VAR = 'cfelr_go';

	/**
	 * Default prefix.
	 *
	 * @var string
	 */
	private const DEFAULT_PREFIX = 'go';

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'init', array( $this, 'register_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirect' ), 1 );
		add_action( 'permalink_structure_changed', array( $this, 'flush_rules' ) );
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Register rewrite rules.
	 *
	 * @return void
	 */
	public function register_rules(): void {
		$prefix = $this->get_prefix();

		// Main rule: /go/slug -> cfelr_go=slug.
		add_rewrite_rule(
			'^' . preg_quote( $prefix, '/' ) . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Handle the redirect on template_redirect.
	 *
	 * @return void
	 */
	public function handle_redirect(): void {
		$slug = get_query_var( self::QUERY_VAR );

		if ( empty( $slug ) ) {
			return;
		}

		$this->process_redirect( $slug, 'rewrite' );
	}

	/**
	 * Process a redirect for a given slug.
	 *
	 * @param string $slug       The slug to redirect.
	 * @param string $matched_by How the match was made.
	 * @return void
	 */
	public function process_redirect( string $slug, string $matched_by ): void {
		// Get repository and resolver.
		$repository = new Repository\WPLinkRepository();
		$resolver   = new \CFELR\Core\Resolver( $repository );

		// Get query string.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';

		// Remove our debug param from query for passthrough.
		$query = preg_replace( '/(&|^)cfelr_debug=[^&]*/', '', $query );
		$query = ltrim( $query, '&' );

		// Resolve.
		$decision = $resolver->resolve( $slug, $matched_by, $query );

		// Handle debug mode for admins.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['cfelr_debug'] ) && current_user_can( 'manage_options' ) ) {
			$this->output_debug( $decision, $slug );
			return;
		}

		// If no redirect, let WordPress handle 404.
		if ( ! $decision->should_redirect ) {
			return;
		}

		// Record click.
		if ( $decision->link_id ) {
			$stats = new Repository\WPStatsRepository();
			$stats->record_click( $decision->link_id );
		}

		// Perform redirect.
		// Using wp_redirect (not wp_safe_redirect) because target can be external.
		// URL is pre-validated (scheme + length) in Validator.
		wp_redirect( $decision->target_url, $decision->status_code, 'Edge Link Router' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Output debug JSON for admins.
	 *
	 * @param \CFELR\Core\Models\RedirectDecision $decision The redirect decision.
	 * @param string                               $slug     The original slug.
	 * @return void
	 */
	private function output_debug( \CFELR\Core\Models\RedirectDecision $decision, string $slug ): void {
		header( 'Content-Type: application/json; charset=utf-8' );

		if ( ! $decision->should_redirect ) {
			wp_send_json(
				array(
					'handler'   => 'wp',
					'slug'      => $slug,
					'found'     => false,
					'message'   => 'Slug not found or disabled',
					'timestamp' => gmdate( 'c' ),
				)
			);
		}

		wp_send_json( $decision->to_debug_array( $slug ) );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @return void
	 */
	public function flush_rules(): void {
		$this->register_rules();
		flush_rewrite_rules();
	}

	/**
	 * Get the redirect prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		$settings = get_option( 'cfelr_settings', array() );
		return $settings['prefix'] ?? self::DEFAULT_PREFIX;
	}
}
