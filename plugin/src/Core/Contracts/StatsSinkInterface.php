<?php
/**
 * Stats Sink Interface.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Contracts;

/**
 * Interface for recording click statistics.
 */
interface StatsSinkInterface {

	/**
	 * Record a click for a link.
	 *
	 * @param int    $link_id Link ID.
	 * @param string $date    Date in Y-m-d format (default: today).
	 * @return bool
	 */
	public function record_click( int $link_id, string $date = '' ): bool;

	/**
	 * Get aggregated stats for a link.
	 *
	 * @param int $link_id Link ID.
	 * @param int $days    Number of days to look back.
	 * @return int Total clicks.
	 */
	public function get_clicks( int $link_id, int $days = 30 ): int;

	/**
	 * Get top links by clicks.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Maximum number of results.
	 * @return array Array of ['link_id' => int, 'clicks' => int].
	 */
	public function get_top_links( int $days = 30, int $limit = 10 ): array;

	/**
	 * Get daily stats for a link.
	 *
	 * @param int $link_id Link ID.
	 * @param int $days    Number of days.
	 * @return array Array of ['day' => string, 'clicks' => int].
	 */
	public function get_daily_stats( int $link_id, int $days = 30 ): array;

	/**
	 * Cleanup old statistics.
	 *
	 * @param int $retention_days Days to retain.
	 * @return int Number of rows deleted.
	 */
	public function cleanup( int $retention_days = 90 ): int;
}
