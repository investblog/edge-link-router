<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link Repository Interface.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Contracts;

use CFELR\Core\Models\Link;

/**
 * Interface for Link repository operations.
 */
interface LinkRepositoryInterface {

	/**
	 * Find a link by its ID.
	 *
	 * @param int $id Link ID.
	 * @return Link|null
	 */
	public function find( int $id ): ?Link;

	/**
	 * Find a link by its slug.
	 *
	 * @param string $slug Link slug.
	 * @return Link|null
	 */
	public function find_by_slug( string $slug ): ?Link;

	/**
	 * Get all links with optional filtering.
	 *
	 * @param array $args Query arguments.
	 * @return Link[]
	 */
	public function get_all( array $args = array() ): array;

	/**
	 * Count total links.
	 *
	 * @param array $args Filter arguments.
	 * @return int
	 */
	public function count( array $args = array() ): int;

	/**
	 * Create a new link.
	 *
	 * @param Link $link Link object.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function create( Link $link ): int|false;

	/**
	 * Update an existing link.
	 *
	 * @param Link $link Link object with ID set.
	 * @return bool
	 */
	public function update( Link $link ): bool;

	/**
	 * Delete a link by ID.
	 *
	 * @param int $id Link ID.
	 * @return bool
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all links for snapshot export.
	 *
	 * @return array Associative array: slug => link data.
	 */
	public function get_snapshot_data(): array;
}
