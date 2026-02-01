<?php
/**
 * WP Stats Repository.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Repository;

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (day, link_id, clicks) VALUES (%s, %d, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clicks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(clicks) FROM {$table} WHERE link_id = %d AND day >= %s",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT link_id, SUM(clicks) as total_clicks FROM {$table} WHERE day >= %s GROUP BY link_id ORDER BY total_clicks DESC LIMIT %d",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, clicks FROM {$table} WHERE link_id = %d AND day >= %s ORDER BY day ASC",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE day < %s",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE link_id = %d",
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clicks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(clicks) FROM {$table} WHERE day >= %s",
				$since
			)
		);

		return (int) $clicks;
	}
}
