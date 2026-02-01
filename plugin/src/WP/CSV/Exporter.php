<?php
/**
 * CSV Exporter.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\CSV;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\WP\Repository\WPLinkRepository;

/**
 * Handles CSV export of links.
 */
class Exporter {

	/**
	 * CSV columns.
	 *
	 * @var array
	 */
	private const COLUMNS = array(
		'slug',
		'target_url',
		'status_code',
		'enabled',
		'passthrough_query',
		'append_utm_json',
		'notes',
	);

	/**
	 * Export all links to CSV.
	 *
	 * @return string CSV content.
	 */
	public function export(): string {
		$repository = new WPLinkRepository();
		$links      = $repository->get_all( array( 'limit' => 10000 ) );

		$output = fopen( 'php://temp', 'r+' );

		// Write header.
		fputcsv( $output, self::COLUMNS );

		// Write rows.
		foreach ( $links as $link ) {
			$row = array(
				$link->slug,
				$link->target_url,
				$link->status_code,
				$link->enabled ? '1' : '0',
				! empty( $link->options['passthrough_query'] ) ? '1' : '0',
				! empty( $link->options['append_utm'] ) ? wp_json_encode( $link->options['append_utm'] ) : '',
				$link->options['notes'] ?? '',
			);
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Send CSV as download.
	 *
	 * @return void
	 */
	public function download(): void {
		$filename = 'edge-link-router-export-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $this->export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
