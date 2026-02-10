<?php
/**
 * WP Stats Repository.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Repository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Contracts\StatsSinkInterface;

/**
 * WordPress database implementation of StatsSinkInterface.
 */
class WPStatsRepository implements StatsSinkInterface {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE = 'cfelr_clicks_daily';

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
	 * Record a click for a link.
	 *
	 * @param int    $link_id Link ID.
	 * @param string $date    Date in Y-m-d format.
	 * @return bool
	 */
	public function record_click( int $link_id, string $date = '' ): bool {
		global $wpdb;

		if ( empty( $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		$table = $this->table();

		// Atomic upsert: INSERT ... ON DUPLICATE KEY UPDATE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (day, link_id, clicks) VALUES (%s, %d, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1',
				$table,
				$date,
				$link_id
			)
		);

		return $result !== false;
	}

	/**
	 * Get total clicks for a link.
	 *
	 * @param int $link_id Link ID.
	 * @param int $days    Number of days to look back.
	 * @return int
	 */
	public function get_clicks( int $link_id, int $days = 30 ): int {
		global $wpdb;

		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$clicks = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(clicks) FROM %i WHERE link_id = %d AND day >= %s',
				$table,
				$link_id,
				$since
			)
		);

		return (int) $clicks;
	}

	/**
	 * Get top links by clicks.
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Max results.
	 * @return array
	 */
	public function get_top_links( int $days = 30, int $limit = 10 ): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT link_id, SUM(clicks) as total_clicks FROM %i WHERE day >= %s GROUP BY link_id ORDER BY total_clicks DESC LIMIT %d',
				$table,
				$since,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			fn( $row ) => array(
				'link_id' => (int) $row['link_id'],
				'clicks'  => (int) $row['total_clicks'],
			),
			$results ?: array()
		);
	}

	/**
	 * Get daily stats for a link.
	 *
	 * @param int $link_id Link ID.
	 * @param int $days    Number of days.
	 * @return array
	 */
	public function get_daily_stats( int $link_id, int $days = 30 ): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT day, clicks FROM %i WHERE link_id = %d AND day >= %s ORDER BY day ASC',
				$table,
				$link_id,
				$since
			),
			ARRAY_A
		);

		return array_map(
			fn( $row ) => array(
				'day'    => $row['day'],
				'clicks' => (int) $row['clicks'],
			),
			$results ?: array()
		);
	}

	/**
	 * Cleanup old statistics.
	 *
	 * @param int $retention_days Days to retain.
	 * @return int Number of rows deleted.
	 */
	public function cleanup( int $retention_days = 90 ): int {
		global $wpdb;

		$before = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );
		$table  = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE day < %s',
				$table,
				$before
			)
		);

		return $deleted !== false ? $deleted : 0;
	}

	/**
	 * Delete stats for a specific link.
	 *
	 * @param int $link_id Link ID.
	 * @return int Number of rows deleted.
	 */
	public function delete_for_link( int $link_id ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE link_id = %d',
				$table,
				$link_id
			)
		);

		return $deleted !== false ? $deleted : 0;
	}

	/**
	 * Get total clicks across all links.
	 *
	 * @param int $days Number of days.
	 * @return int
	 */
	public function get_total_clicks( int $days = 30 ): int {
		global $wpdb;

		$since = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$clicks = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(clicks) FROM %i WHERE day >= %s',
				$table,
				$since
			)
		);

		return (int) $clicks;
	}
}
