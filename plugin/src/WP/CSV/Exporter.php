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

		$lines = array();

		// Header row.
		$lines[] = $this->array_to_csv_line( self::COLUMNS );

		// Data rows.
		foreach ( $links as $link ) {
			$row = array(
				$link->slug,
				$link->target_url,
				(string) $link->status_code,
				$link->enabled ? '1' : '0',
				! empty( $link->options['passthrough_query'] ) ? '1' : '0',
				! empty( $link->options['append_utm'] ) ? wp_json_encode( $link->options['append_utm'] ) : '',
				$link->options['notes'] ?? '',
			);
			$lines[] = $this->array_to_csv_line( $row );
		}

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Convert array to CSV line with proper escaping.
	 *
	 * @param array $fields Array of field values.
	 * @return string CSV formatted line.
	 */
	private function array_to_csv_line( array $fields ): string {
		$escaped = array();

		foreach ( $fields as $field ) {
			$escaped[] = $this->escape_csv_field( (string) $field );
		}

		return implode( ',', $escaped );
	}

	/**
	 * Escape a single CSV field value.
	 *
	 * @param string $field Field value.
	 * @return string Escaped field value.
	 */
	private function escape_csv_field( string $field ): string {
		// If field contains special characters, wrap in quotes and escape internal quotes.
		if (
			false !== strpos( $field, '"' ) ||
			false !== strpos( $field, ',' ) ||
			false !== strpos( $field, "\n" ) ||
			false !== strpos( $field, "\r" )
		) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}

		return $field;
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
