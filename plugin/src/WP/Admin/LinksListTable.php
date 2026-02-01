<?php
/**
 * Links List Table.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin;

use CFELR\WP\Repository\WPLinkRepository;
use CFELR\WP\Repository\WPStatsRepository;

// Load WP_List_Table if not available.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom list table for displaying links.
 */
class LinksListTable extends \WP_List_Table {

	/**
	 * Repository instance.
	 *
	 * @var WPLinkRepository
	 */
	private WPLinkRepository $repository;

	/**
	 * Stats repository.
	 *
	 * @var WPStatsRepository
	 */
	private WPStatsRepository $stats;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'link',
				'plural'   => 'links',
				'ajax'     => false,
			)
		);

		$this->repository = new WPLinkRepository();
		$this->stats      = new WPStatsRepository();
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'slug'        => __( 'Slug', 'edge-link-router' ),
			'target_url'  => __( 'Target URL', 'edge-link-router' ),
			'status_code' => __( 'Status', 'edge-link-router' ),
			'enabled'     => __( 'Enabled', 'edge-link-router' ),
			'clicks'      => __( 'Clicks (30d)', 'edge-link-router' ),
			'created_at'  => __( 'Created', 'edge-link-router' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'slug'        => array( 'slug', false ),
			'status_code' => array( 'status_code', false ),
			'enabled'     => array( 'enabled', false ),
			'created_at'  => array( 'created_at', true ),
		);
	}

	/**
	 * Get the primary column name.
	 *
	 * This determines which column contains row actions
	 * and remains visible on mobile devices.
	 *
	 * @return string
	 */
	protected function get_primary_column_name(): string {
		return 'slug';
	}

	/**
	 * Get table classes.
	 *
	 * @return array
	 */
	protected function get_table_classes(): array {
		return array( 'wp-list-table', 'widefat', 'fixed', 'striped', 'cfelr-links-table' );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'enable'  => __( 'Enable', 'edge-link-router' ),
			'disable' => __( 'Disable', 'edge-link-router' ),
			'delete'  => __( 'Delete', 'edge-link-router' ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Get sorting parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		// Get search parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		// Query arguments.
		$args = array(
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'search'  => $search,
		);

		// Get items.
		$this->items = $this->repository->get_all( $args );
		$total_items = $this->repository->count( array( 'search' => $search ) );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render checkbox column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="link[]" value="%s" />',
			esc_attr( $item->id )
		);
	}

	/**
	 * Render slug column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_slug( $item ): string {
		$edit_url = add_query_arg(
			array(
				'page'   => 'edge-link-router',
				'action' => 'edit',
				'id'     => $item->id,
			),
			admin_url( 'admin.php' )
		);

		$prefix   = $this->get_prefix();
		$full_url = home_url( '/' . $prefix . '/' . $item->slug );

		$actions = array(
			'copy'   => sprintf(
				'<a href="#" class="cfelr-copy-action" data-url="%s">%s</a>',
				esc_attr( $full_url ),
				__( 'Copy', 'edge-link-router' )
			),
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				__( 'Edit', 'edge-link-router' )
			),
			'test'   => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $full_url ),
				__( 'Test', 'edge-link-router' )
			),
			'delete' => sprintf(
				'<a href="%s" class="cfelr-delete-link submitdelete">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'edge-link-router',
								'action' => 'delete',
								'id'     => $item->id,
							),
							admin_url( 'admin.php' )
						),
						'delete_link_' . $item->id
					)
				),
				__( 'Delete', 'edge-link-router' )
			),
		);

		$path = '/' . $prefix . '/' . $item->slug;

		// Build slug cell with BEM structure.
		$output  = '<div class="cfelr-slug">';
		$output .= '<div class="cfelr-slug__main">';
		$output .= '<a href="' . esc_url( $edit_url ) . '" class="cfelr-slug__title">' . esc_html( $item->slug ) . '</a>';
		$output .= '<span class="cfelr-slug__path">' . esc_html( $path ) . '</span>';
		$output .= '</div>';
		$output .= sprintf(
			'<button type="button" class="cfelr-copy" data-url="%s" title="%s" aria-label="%s"><svg width="14" height="14" viewBox="0 0 19 22" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17 20H6V6h11m0-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m-3-4H2a2 2 0 0 0-2 2v14h2V2h12z" fill="currentColor"/></svg></button>',
			esc_attr( $full_url ),
			esc_attr__( 'Copy link', 'edge-link-router' ),
			esc_attr__( 'Copy link to clipboard', 'edge-link-router' )
		);
		$output .= '</div>';
		$output .= $this->row_actions( $actions );

		return $output;
	}

	/**
	 * Render target_url column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_target_url( $item ): string {
		$url      = $item->target_url;
		$full_url = $this->build_full_target_url( $item );

		return sprintf(
			'<div class="cfelr-target"><a href="%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a></div>',
			esc_url( $url ),
			esc_attr( $full_url ),
			esc_html( $url )
		);
	}

	/**
	 * Build full target URL with UTM parameters.
	 *
	 * @param object $item Link object.
	 * @return string
	 */
	private function build_full_target_url( $item ): string {
		$url = $item->target_url;

		// Get options from Link object.
		$options = $item->options ?? array();

		// Append UTM parameters if configured.
		if ( ! empty( $options['append_utm'] ) && is_array( $options['append_utm'] ) ) {
			$utm_params = array_filter( $options['append_utm'] );
			if ( ! empty( $utm_params ) ) {
				$url = add_query_arg( $utm_params, $url );
			}
		}

		return $url;
	}

	/**
	 * Render status_code column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_status_code( $item ): string {
		$labels = array(
			301 => __( 'Permanent', 'edge-link-router' ),
			302 => __( 'Temporary', 'edge-link-router' ),
			307 => __( 'Temporary', 'edge-link-router' ),
			308 => __( 'Permanent', 'edge-link-router' ),
		);

		$code  = (int) $item->status_code;
		$label = $labels[ $code ] ?? '';

		return sprintf(
			'<span class="cfelr-badge cfelr-badge--%d"><span class="cfelr-badge__code">%d</span> <span class="cfelr-badge__label">%s</span></span>',
			$code,
			$code,
			esc_html( $label )
		);
	}

	/**
	 * Render enabled column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_enabled( $item ): string {
		if ( $item->enabled ) {
			return sprintf(
				'<span class="cfelr-enabled" title="%s" aria-label="%s"><span class="dashicons dashicons-yes"></span></span>',
				esc_attr__( 'Enabled', 'edge-link-router' ),
				esc_attr__( 'Link is enabled', 'edge-link-router' )
			);
		}

		return sprintf(
			'<span class="cfelr-enabled cfelr-enabled--off" title="%s" aria-label="%s"><span class="dashicons dashicons-no-alt"></span></span>',
			esc_attr__( 'Disabled', 'edge-link-router' ),
			esc_attr__( 'Link is disabled', 'edge-link-router' )
		);
	}

	/**
	 * Render clicks column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_clicks( $item ): string {
		$clicks = $this->stats->get_clicks( $item->id, 30 );
		$class  = $clicks > 0 ? 'cfelr-clicks' : 'cfelr-clicks cfelr-clicks-zero';

		return '<span class="' . esc_attr( $class ) . '">' . number_format_i18n( $clicks ) . '</span>';
	}

	/**
	 * Render created_at column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_created_at( $item ): string {
		$timestamp = strtotime( $item->created_at );

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( wp_date( 'Y-m-d H:i:s', $timestamp ) ),
			esc_html( human_time_diff( $timestamp, time() ) . ' ' . __( 'ago', 'edge-link-router' ) )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Message for no items.
	 *
	 * @return void
	 */
	public function no_items(): void {
		$add_url = add_query_arg(
			array(
				'page'   => 'edge-link-router',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="cfelr-empty-state">
			<span class="dashicons dashicons-admin-links"></span>
			<h3><?php esc_html_e( 'No links yet', 'edge-link-router' ); ?></h3>
			<p><?php esc_html_e( 'Create your first redirect link to get started. Links can redirect visitors to any URL with optional UTM tracking.', 'edge-link-router' ); ?></p>
			<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add New Link', 'edge-link-router' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Get redirect prefix.
	 *
	 * @return string
	 */
	private function get_prefix(): string {
		$settings = get_option( 'cfelr_settings', array() );
		return $settings['prefix'] ?? 'go';
	}
}
