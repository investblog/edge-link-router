<?php
/**
 * CSV Importer.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\CSV;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Models\Link;
use CFELR\Core\Validator;
use CFELR\WP\Admin\Pages\SettingsPage;
use CFELR\WP\Repository\WPLinkRepository;

/**
 * Handles CSV import of links.
 */
class Importer {

	/**
	 * Expected CSV columns.
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
	 * Batch size for processing.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Repository instance.
	 *
	 * @var WPLinkRepository
	 */
	private WPLinkRepository $repository;

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Import results.
	 *
	 * @var array
	 */
	private array $results = array(
		'created' => 0,
		'updated' => 0,
		'errors'  => array(),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new WPLinkRepository();
		$this->validator  = new Validator();
	}

	/**
	 * Import links from CSV file.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array Import results.
	 */
	public function import( string $file_path ): array {
		// Open file.
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			$this->results['errors'][] = __( 'Could not open CSV file.', 'edge-link-router' );
			return $this->results;
		}

		// Read header.
		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->results['errors'][] = __( 'CSV file is empty or invalid.', 'edge-link-router' );
			return $this->results;
		}

		// Normalize header.
		$header = array_map( 'trim', $header );
		$header = array_map( 'strtolower', $header );

		// Validate header.
		if ( ! $this->validate_header( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $this->results;
		}

		// Create column index map.
		$column_map = array_flip( $header );

		// Process rows in batches.
		$row_number = 1; // Header is row 1.
		$batch      = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$row_number;

			// Skip empty rows.
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			// Parse row.
			$link_data = $this->parse_row( $row, $column_map, $row_number );

			if ( $link_data === null ) {
				continue; // Error already recorded.
			}

			$batch[] = $link_data;

			// Process batch.
			if ( count( $batch ) >= self::BATCH_SIZE ) {
				$this->process_batch( $batch );
				$batch = array();
			}
		}

		// Process remaining items.
		if ( ! empty( $batch ) ) {
			$this->process_batch( $batch );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $this->results;
	}

	/**
	 * Import from uploaded file.
	 *
	 * @param array $file $_FILES array element.
	 * @return array Import results.
	 */
	public function import_uploaded( array $file ): array {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->results['errors'][] = __( 'No file uploaded.', 'edge-link-router' );
			return $this->results;
		}

		// Check file type.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension !== 'csv' ) {
			$this->results['errors'][] = __( 'Please upload a CSV file.', 'edge-link-router' );
			return $this->results;
		}

		return $this->import( $file['tmp_name'] );
	}

	/**
	 * Validate CSV header.
	 *
	 * @param array $header Header row.
	 * @return bool
	 */
	private function validate_header( array $header ): bool {
		// Required columns.
		$required = array( 'slug', 'target_url' );

		foreach ( $required as $col ) {
			if ( ! in_array( $col, $header, true ) ) {
				$this->results['errors'][] = sprintf(
					/* translators: %s: column name */
					__( 'Missing required column: %s', 'edge-link-router' ),
					$col
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse a CSV row into link data.
	 *
	 * @param array $row        CSV row.
	 * @param array $column_map Column name to index map.
	 * @param int   $row_number Row number for error reporting.
	 * @return array|null Link data or null on error.
	 */
	private function parse_row( array $row, array $column_map, int $row_number ): ?array {
		$get_value = function ( string $column ) use ( $row, $column_map ): string {
			if ( ! isset( $column_map[ $column ] ) ) {
				return '';
			}
			$index = $column_map[ $column ];
			return isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
		};

		$slug       = $get_value( 'slug' );
		$target_url = $get_value( 'target_url' );

		// Basic validation.
		if ( empty( $slug ) || empty( $target_url ) ) {
			$this->results['errors'][] = sprintf(
				/* translators: %d: row number */
				__( 'Row %d: slug and target_url are required.', 'edge-link-router' ),
				$row_number
			);
			return null;
		}

		// Parse status code.
		$status_code = $get_value( 'status_code' );
		$status_code = ! empty( $status_code ) ? (int) $status_code : 302;

		if ( ! in_array( $status_code, array( 301, 302, 307, 308 ), true ) ) {
			$this->results['errors'][] = sprintf(
				/* translators: %d: row number */
				__( 'Row %d: invalid status code, using 302.', 'edge-link-router' ),
				$row_number
			);
			$status_code = 302;
		}

		// Parse enabled.
		$enabled = $get_value( 'enabled' );
		$enabled = $enabled === '' || $enabled === '1' || strtolower( $enabled ) === 'true';

		// Parse passthrough_query.
		$passthrough = $get_value( 'passthrough_query' );
		$passthrough = $passthrough === '1' || strtolower( $passthrough ) === 'true';

		// Parse UTM JSON.
		$utm_json   = $get_value( 'append_utm_json' );
		$append_utm = array();

		if ( ! empty( $utm_json ) ) {
			$decoded = json_decode( $utm_json, true );
			if ( is_array( $decoded ) ) {
				$append_utm = $decoded;
			} else {
				$this->results['errors'][] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: invalid UTM JSON, skipping UTM parameters.', 'edge-link-router' ),
					$row_number
				);
			}
		}

		// Parse notes.
		$notes = $get_value( 'notes' );

		// Sanitize slug.
		$sanitized_slug = $this->validator->sanitize_slug( $slug );

		// Validate.
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$prefix = $this->get_prefix();

		$data = array(
			'slug'       => $sanitized_slug,
			'target_url' => $target_url,
		);

		$errors = $this->validator->validate( $data, $host, $prefix );

		if ( ! empty( $errors ) ) {
			$this->results['errors'][] = sprintf(
				/* translators: %1$d: row number, %2$s: errors */
				__( 'Row %1$d: %2$s', 'edge-link-router' ),
				$row_number,
				implode( ', ', $errors )
			);
			return null;
		}

		return array(
			'slug'        => $sanitized_slug,
			'target_url'  => esc_url_raw( $target_url ),
			'status_code' => $status_code,
			'enabled'     => $enabled,
			'options'     => array(
				'passthrough_query' => $passthrough,
				'append_utm'        => $append_utm,
				'notes'             => $notes,
			),
		);
	}

	/**
	 * Process a batch of links.
	 *
	 * @param array $batch Array of link data.
	 * @return void
	 */
	private function process_batch( array $batch ): void {
		foreach ( $batch as $data ) {
			// Check if link exists.
			$existing = $this->repository->find_by_slug( $data['slug'] );

			$link              = new Link();
			$link->slug        = $data['slug'];
			$link->target_url  = $data['target_url'];
			$link->status_code = $data['status_code'];
			$link->enabled     = $data['enabled'];
			$link->options     = $data['options'];

			if ( $existing ) {
				// Update existing.
				$link->id = $existing->id;
				$result   = $this->repository->update( $link );

				if ( $result ) {
					++$this->results['updated'];
				}
			} else {
				// Create new.
				$result = $this->repository->create( $link );

				if ( $result ) {
					++$this->results['created'];
				}
			}
		}
	}

	/**
	 * Get import results.
	 *
	 * @return array
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Get redirect prefix.
	 *
	 * @return string
	 */
	private function get_prefix(): string {
		return SettingsPage::get_prefix();
	}
}
