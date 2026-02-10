<?php
/**
 * WP Link Repository.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Repository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Contracts\LinkRepositoryInterface;
use CFELR\Core\Models\Link;

/**
 * WordPress database implementation of LinkRepositoryInterface.
 */
class WPLinkRepository implements LinkRepositoryInterface {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE = 'cfelr_links';

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Find a link by ID.
	 *
	 * @param int $id Link ID.
	 * @return Link|null
	 */
	public function find( int $id ): ?Link {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return Link::from_db( $row );
	}

	/**
	 * Find a link by slug.
	 *
	 * @param string $slug Link slug.
	 * @return Link|null
	 */
	public function find_by_slug( string $slug ): ?Link {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE slug = %s',
				$table,
				$slug
			)
		);

		if ( ! $row ) {
			return null;
		}

		return Link::from_db( $row );
	}

	/**
	 * Get all links.
	 *
	 * @param array $args Query arguments.
	 * @return Link[]
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$table = $this->table();

		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
			'search'  => '',
			'enabled' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build SQL by concatenation to avoid interpolation warnings.
		$sql            = 'SELECT * FROM %i WHERE 1=1';
		$prepare_values = array( $table );

		if ( $args['enabled'] !== null ) {
			$sql             .= ' AND enabled = %d';
			$prepare_values[] = $args['enabled'] ? 1 : 0;
		}

		if ( ! empty( $args['search'] ) ) {
			$search           = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql             .= ' AND (slug LIKE %s OR target_url LIKE %s)';
			$prepare_values[] = $search;
			$prepare_values[] = $search;
		}

		$allowed_orderby = array( 'id', 'slug', 'target_url', 'status_code', 'enabled', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		$sql             .= ' ORDER BY %i';
		$prepare_values[] = $orderby;
		$sql             .= strtoupper( $args['order'] ) === 'ASC' ? ' ASC' : ' DESC';
		$sql             .= ' LIMIT %d OFFSET %d';
		$prepare_values[] = (int) $args['limit'];
		$prepare_values[] = (int) $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare( $sql, $prepare_values )
		);

		return array_map( fn( $row ) => Link::from_db( $row ), $rows ?: array() );
	}

	/**
	 * Count links.
	 *
	 * @param array $args Filter arguments.
	 * @return int
	 */
	public function count( array $args = array() ): int {
		global $wpdb;

		$table = $this->table();

		$sql            = 'SELECT COUNT(*) FROM %i WHERE 1=1';
		$prepare_values = array( $table );

		if ( isset( $args['enabled'] ) ) {
			$sql             .= ' AND enabled = %d';
			$prepare_values[] = $args['enabled'] ? 1 : 0;
		}

		if ( ! empty( $args['search'] ) ) {
			$search           = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql             .= ' AND (slug LIKE %s OR target_url LIKE %s)';
			$prepare_values[] = $search;
			$prepare_values[] = $search;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare( $sql, $prepare_values )
		);

		return (int) $count;
	}

	/**
	 * Create a new link.
	 *
	 * @param Link $link Link object.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function create( Link $link ): int|false {
		global $wpdb;

		$data = $link->to_db_array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table(),
			$data,
			array( '%s', '%s', '%d', '%d', '%s' )
		);

		if ( $result === false ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing link.
	 *
	 * @param Link $link Link object with ID.
	 * @return bool
	 */
	public function update( Link $link ): bool {
		global $wpdb;

		if ( ! $link->id ) {
			return false;
		}

		$data = $link->to_db_array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $link->id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete a link and its stats.
	 *
	 * @param int $id Link ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// Delete associated stats first.
		$stats = new WPStatsRepository();
		$stats->delete_for_link( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get snapshot data for edge publishing.
	 *
	 * @return array Associative array slug => data.
	 */
	public function get_snapshot_data(): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE enabled = 1', $table ) );

		$snapshot = array();

		foreach ( $rows ?: array() as $row ) {
			$link                    = Link::from_db( $row );
			$snapshot[ $link->slug ] = $link->to_snapshot();
		}

		return $snapshot;
	}
}
