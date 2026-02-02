<?php
/**
 * Settings Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Integrations\Cloudflare\Deployer;
use CFELR\Integrations\Cloudflare\IntegrationState;

/**
 * Settings page.
 */
class SettingsPage {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'cfelr_settings';

	/**
	 * Default prefix.
	 *
	 * @var string
	 */
	private const DEFAULT_PREFIX = 'go';

	/**
	 * Reserved paths that cannot be used as prefix.
	 *
	 * @var array
	 */
	private const RESERVED_PATHS = array(
		'wp-admin',
		'wp-json',
		'wp-login',
		'wp-content',
		'wp-includes',
		'feed',
		'sitemap',
		'xmlrpc',
	);

	/**
	 * Get the effective prefix with priority chain.
	 *
	 * Priority:
	 * 1. CFELR_PREFIX constant (if defined)
	 * 2. apply_filters('cfelr_prefix', $ui_prefix)
	 * 3. UI option from cfelr_settings['prefix']
	 * 4. Fallback 'go'
	 *
	 * @return string
	 */
	public static function get_prefix(): string {
		// 1. Constant takes highest priority.
		if ( defined( 'CFELR_PREFIX' ) ) {
			return sanitize_title( CFELR_PREFIX );
		}

		// 2-3. Get UI option.
		$settings   = get_option( self::OPTION_NAME, array() );
		$ui_prefix  = $settings['prefix'] ?? self::DEFAULT_PREFIX;

		// 2. Apply filter.
		$prefix = apply_filters( 'cfelr_prefix', $ui_prefix );

		// Sanitize and return.
		$prefix = sanitize_title( $prefix );

		// 4. Fallback.
		return ! empty( $prefix ) ? $prefix : self::DEFAULT_PREFIX;
	}

	/**
	 * Check if prefix is locked by constant.
	 *
	 * @return bool
	 */
	public static function is_prefix_locked(): bool {
		return defined( 'CFELR_PREFIX' );
	}

	/**
	 * Handle early form actions.
	 *
	 * @return void
	 */
	public function handle_early_actions(): void {
		if ( ! isset( $_POST['cfelr_save_settings'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cfelr_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_settings_nonce'] ) ), 'cfelr_save_settings' ) ) {
			add_settings_error( 'cfelr_settings_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
			return;
		}

		// Check if locked.
		if ( self::is_prefix_locked() ) {
			add_settings_error( 'cfelr_settings_messages', 'locked', __( 'Prefix is locked by CFELR_PREFIX constant.', 'edge-link-router' ), 'error' );
			return;
		}

		// Get and sanitize prefix.
		$new_prefix = isset( $_POST['cfelr_prefix'] ) ? sanitize_title( wp_unslash( $_POST['cfelr_prefix'] ) ) : '';

		if ( empty( $new_prefix ) ) {
			add_settings_error( 'cfelr_settings_messages', 'empty_prefix', __( 'Prefix cannot be empty.', 'edge-link-router' ), 'error' );
			return;
		}

		// Validate prefix.
		$errors = $this->validate_prefix( $new_prefix );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				add_settings_error( 'cfelr_settings_messages', 'prefix_error', $error, 'error' );
			}
			return;
		}

		// Save settings.
		$settings            = get_option( self::OPTION_NAME, array() );
		$old_prefix          = $settings['prefix'] ?? self::DEFAULT_PREFIX;
		$settings['prefix']  = $new_prefix;
		$prefix_changed      = $old_prefix !== $new_prefix;

		update_option( self::OPTION_NAME, $settings );

		// Flush rewrite rules if prefix changed.
		if ( $prefix_changed ) {
			flush_rewrite_rules();
		}

		// Handle edge mode if prefix changed.
		if ( $prefix_changed ) {
			$state = new IntegrationState();

			if ( $state->is_edge_enabled() ) {
				// Auto-update edge: republish worker with new prefix and update route.
				$deployer = new Deployer();
				$result   = $deployer->update_prefix( $old_prefix, $new_prefix );

				if ( $result ) {
					add_settings_error(
						'cfelr_settings_messages',
						'edge_updated',
						__( 'Settings saved. Edge route updated to new prefix.', 'edge-link-router' ),
						'success'
					);
					return;
				} else {
					add_settings_error(
						'cfelr_settings_messages',
						'edge_update_failed',
						sprintf(
							/* translators: %s: error message */
							__( 'Settings saved, but edge route update failed: %s. Please republish manually.', 'edge-link-router' ),
							$deployer->get_last_error()
						),
						'warning'
					);
					return;
				}
			}
		}

		add_settings_error( 'cfelr_settings_messages', 'saved', __( 'Settings saved.', 'edge-link-router' ), 'success' );
	}

	/**
	 * Validate prefix.
	 *
	 * @param string $prefix Prefix to validate.
	 * @return array Array of error messages.
	 */
	private function validate_prefix( string $prefix ): array {
		$errors = array();

		// Length check.
		if ( strlen( $prefix ) > 50 ) {
			$errors[] = __( 'Prefix must be 50 characters or less.', 'edge-link-router' );
		}

		// Character check.
		if ( ! preg_match( '/^[a-z0-9\-_]+$/', $prefix ) ) {
			$errors[] = __( 'Prefix can only contain lowercase letters, numbers, hyphens, and underscores.', 'edge-link-router' );
		}

		// Reserved paths check.
		if ( in_array( $prefix, self::RESERVED_PATHS, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: prefix */
				__( '"%s" is a reserved WordPress path and cannot be used.', 'edge-link-router' ),
				$prefix
			);
		}

		// Check for existing page/post with this slug.
		$page = get_page_by_path( $prefix );
		if ( $page ) {
			$errors[] = sprintf(
				/* translators: %1$s: prefix, %2$s: post type */
				__( 'A %2$s with slug "%1$s" already exists.', 'edge-link-router' ),
				$prefix,
				get_post_type_object( $page->post_type )->labels->singular_name
			);
		}

		// Check custom post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( isset( $post_type->rewrite['slug'] ) && $post_type->rewrite['slug'] === $prefix ) {
				$errors[] = sprintf(
					/* translators: %1$s: prefix, %2$s: post type name */
					__( 'Post type "%2$s" uses the slug "%1$s".', 'edge-link-router' ),
					$prefix,
					$post_type->labels->name
				);
			}
		}

		// Check taxonomies.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( isset( $taxonomy->rewrite['slug'] ) && $taxonomy->rewrite['slug'] === $prefix ) {
				$errors[] = sprintf(
					/* translators: %1$s: prefix, %2$s: taxonomy name */
					__( 'Taxonomy "%2$s" uses the slug "%1$s".', 'edge-link-router' ),
					$prefix,
					$taxonomy->labels->name
				);
			}
		}

		return $errors;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$current_prefix = self::get_prefix();
		$is_locked      = self::is_prefix_locked();

		// Get UI value (may differ from effective if filter is applied).
		$settings  = get_option( self::OPTION_NAME, array() );
		$ui_prefix = $settings['prefix'] ?? self::DEFAULT_PREFIX;
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Settings', 'edge-link-router' ); ?></h1>

			<?php settings_errors( 'cfelr_settings_messages' ); ?>

			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Redirect Prefix', 'edge-link-router' ); ?></h2>

				<p class="description">
					<?php esc_html_e( 'The URL prefix used for all redirect links. Default is "go".', 'edge-link-router' ); ?>
				</p>

				<form method="post" action="">
					<?php wp_nonce_field( 'cfelr_save_settings', 'cfelr_settings_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="cfelr_prefix"><?php esc_html_e( 'Prefix', 'edge-link-router' ); ?></label>
							</th>
							<td>
								<div style="display: flex; align-items: center; gap: 8px;">
									<code><?php echo esc_html( home_url( '/' ) ); ?></code>
									<input
										type="text"
										id="cfelr_prefix"
										name="cfelr_prefix"
										value="<?php echo esc_attr( $is_locked ? $current_prefix : $ui_prefix ); ?>"
										class="regular-text"
										style="width: 150px;"
										pattern="[a-z0-9\-_]+"
										maxlength="50"
										<?php echo $is_locked ? 'readonly' : ''; ?>
									>
									<code>/slug</code>
								</div>

								<?php if ( $is_locked ) : ?>
									<p class="description" style="color: #996800; margin-top: 8px;">
										<span class="dashicons dashicons-lock" style="font-size: 16px; vertical-align: middle;"></span>
										<?php
										printf(
											/* translators: %s: constant value */
											esc_html__( 'Prefix is locked by CFELR_PREFIX constant: "%s"', 'edge-link-router' ),
											esc_html( CFELR_PREFIX )
										);
										?>
									</p>
								<?php else : ?>
									<p class="description" style="margin-top: 8px;">
										<?php esc_html_e( 'Only lowercase letters, numbers, hyphens, and underscores allowed.', 'edge-link-router' ); ?>
									</p>
								<?php endif; ?>

								<p style="margin-top: 12px;">
									<?php esc_html_e( 'Current redirect URL format:', 'edge-link-router' ); ?>
									<code><?php echo esc_html( home_url( '/' . $current_prefix . '/your-slug' ) ); ?></code>
								</p>
							</td>
						</tr>
					</table>

					<?php if ( ! $is_locked ) : ?>
						<p class="submit">
							<button type="submit" name="cfelr_save_settings" class="button button-primary">
								<?php esc_html_e( 'Save Settings', 'edge-link-router' ); ?>
							</button>
						</p>
					<?php endif; ?>
				</form>
			</div>

			<!-- Info about priority -->
			<div class="cfelr-card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Advanced: Prefix Override', 'edge-link-router' ); ?></h2>

				<p><?php esc_html_e( 'For developers, the prefix can be overridden using:', 'edge-link-router' ); ?></p>

				<ol>
					<li>
						<strong><?php esc_html_e( 'Constant (highest priority, locks UI):', 'edge-link-router' ); ?></strong>
						<pre style="background: #f0f0f1; padding: 10px; margin: 5px 0;"><code>define( 'CFELR_PREFIX', 'links' );</code></pre>
					</li>
					<li>
						<strong><?php esc_html_e( 'Filter:', 'edge-link-router' ); ?></strong>
						<pre style="background: #f0f0f1; padding: 10px; margin: 5px 0;"><code>add_filter( 'cfelr_prefix', fn() => 'links' );</code></pre>
					</li>
				</ol>
			</div>
		</div>
		<?php
	}
}
