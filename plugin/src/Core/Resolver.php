<?php
/**
 * Resolver - resolves slugs to redirect decisions.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Contracts\LinkRepositoryInterface;
use CFELR\Core\Models\Link;
use CFELR\Core\Models\RedirectDecision;

/**
 * Resolves a slug to a redirect decision.
 */
class Resolver {

	/**
	 * Link repository.
	 *
	 * @var LinkRepositoryInterface
	 */
	private LinkRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param LinkRepositoryInterface $repository Link repository.
	 */
	public function __construct( LinkRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Resolve a slug to a redirect decision.
	 *
	 * @param string $slug       The slug to resolve.
	 * @param string $matched_by How the match was made (rewrite|fallback).
	 * @param string $query      Original query string.
	 * @return RedirectDecision
	 */
	public function resolve( string $slug, string $matched_by = 'rewrite', string $query = '' ): RedirectDecision {
		// Normalize slug.
		$slug = $this->normalize_slug( $slug );

		if ( empty( $slug ) ) {
			return RedirectDecision::not_found();
		}

		// Find link.
		$link = $this->repository->find_by_slug( $slug );

		if ( ! $link instanceof Link ) {
			return RedirectDecision::not_found();
		}

		// Check if enabled.
		if ( ! $link->enabled ) {
			return RedirectDecision::not_found();
		}

		// Create redirect decision.
		return RedirectDecision::from_link( $link, $matched_by, $query );
	}

	/**
	 * Normalize a slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string Normalized slug.
	 */
	private function normalize_slug( string $slug ): string {
		// URL decode.
		$slug = rawurldecode( $slug );

		// Trim whitespace.
		$slug = trim( $slug );

		// Lowercase.
		$slug = strtolower( $slug );

		return $slug;
	}
}
