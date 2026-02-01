<?php
/**
 * Link Model.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a redirect link/rule.
 */
class Link {

	/**
	 * Link ID.
	 *
	 * @var int|null
	 */
	public ?int $id = null;

	/**
	 * URL slug.
	 *
	 * @var string
	 */
	public string $slug = '';

	/**
	 * Target URL.
	 *
	 * @var string
	 */
	public string $target_url = '';

	/**
	 * HTTP status code for redirect.
	 *
	 * @var int
	 */
	public int $status_code = 302;

	/**
	 * Whether the link is enabled.
	 *
	 * @var bool
	 */
	public bool $enabled = true;

	/**
	 * Options array.
	 *
	 * @var array
	 */
	public array $options = array(
		'passthrough_query' => false,
		'append_utm'        => array(),
		'notes'             => '',
	);

	/**
	 * Created timestamp.
	 *
	 * @var string|null
	 */
	public ?string $created_at = null;

	/**
	 * Updated timestamp.
	 *
	 * @var string|null
	 */
	public ?string $updated_at = null;

	/**
	 * Create a Link from database row.
	 *
	 * @param object|array $row Database row.
	 * @return self
	 */
	public static function from_db( object|array $row ): self {
		$row  = (object) $row;
		$link = new self();

		$link->id          = isset( $row->id ) ? (int) $row->id : null;
		$link->slug        = $row->slug ?? '';
		$link->target_url  = $row->target_url ?? '';
		$link->status_code = isset( $row->status_code ) ? (int) $row->status_code : 302;
		$link->enabled     = isset( $row->enabled ) ? (bool) $row->enabled : true;
		$link->created_at  = $row->created_at ?? null;
		$link->updated_at  = $row->updated_at ?? null;

		// Parse options JSON.
		if ( ! empty( $row->options_json ) ) {
			$options = json_decode( $row->options_json, true );
			if ( is_array( $options ) ) {
				$link->options = array_merge( $link->options, $options );
			}
		}

		return $link;
	}

	/**
	 * Convert to array for database storage.
	 *
	 * @return array
	 */
	public function to_db_array(): array {
		return array(
			'slug'         => $this->slug,
			'target_url'   => $this->target_url,
			'status_code'  => $this->status_code,
			'enabled'      => $this->enabled ? 1 : 0,
			'options_json' => wp_json_encode( $this->options ),
		);
	}

	/**
	 * Get full target URL with UTM parameters appended.
	 *
	 * @return string
	 */
	public function get_full_target_url(): string {
		$url = $this->target_url;

		if ( ! empty( $this->options['append_utm'] ) && is_array( $this->options['append_utm'] ) ) {
			$utm_params = array_filter( $this->options['append_utm'] );
			if ( ! empty( $utm_params ) ) {
				$url = add_query_arg( $utm_params, $url );
			}
		}

		return $url;
	}

	/**
	 * Convert to snapshot format for edge.
	 *
	 * @return array
	 */
	public function to_snapshot(): array {
		$data = array(
			'target_url'  => $this->target_url,
			'status_code' => $this->status_code,
			'options'     => array(),
		);

		if ( ! empty( $this->options['passthrough_query'] ) ) {
			$data['options']['passthrough_query'] = true;
		}

		if ( ! empty( $this->options['append_utm'] ) ) {
			$data['options']['append_utm'] = $this->options['append_utm'];
		}

		return $data;
	}
}
