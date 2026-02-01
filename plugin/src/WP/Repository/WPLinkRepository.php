<?php
/**
 * WP Link Repository.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Repository;

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s",
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

		$where = array( '1=1' );
		$values = array();

		if ( $args['enabled'] !== null ) {
			$where[]  = 'enabled = %d';
			$values[] = $args['enabled'] ? 1 : 0;
		}

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(slug LIKE %s OR target_url LIKE %s)';
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'slug', 'target_url', 'status_code', 'enabled', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$values
			)
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

		$where  = array( '1=1' );
		$values = array();

		if ( isset( $args['enabled'] ) ) {
			$where[]  = 'enabled = %d';
			$values[] = $args['enabled'] ? 1 : 0;
		}

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(slug LIKE %s OR target_url LIKE %s)';
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
					$values
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
		}

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE enabled = 1" );

		$snapshot = array();

		foreach ( $rows ?: array() as $row ) {
			$link                    = Link::from_db( $row );
			$snapshot[ $link->slug ] = $link->to_snapshot();
		}

		return $snapshot;
	}
}
