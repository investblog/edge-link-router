<?php
/**
 * Smoke tests for SQL %i migration.
 *
 * Run via: wp eval-file tests/smoke-test.php
 *
 * @package EdgeLinkRouter
 */

global $smoke_passed, $smoke_failed;
$smoke_passed = 0;
$smoke_failed = 0;

function smoke_pass( string $name ): void {
	global $smoke_passed;
	++$smoke_passed;
	WP_CLI::log( WP_CLI::colorize( "  %g✓%n {$name}" ) );
}

function smoke_fail( string $name, string $reason = '' ): void {
	global $smoke_failed;
	++$smoke_failed;
	$msg = $reason ? "{$name} — {$reason}" : $name;
	WP_CLI::log( WP_CLI::colorize( "  %r✗%n {$msg}" ) );
}

WP_CLI::log( '' );
WP_CLI::log( '=== Edge Link Router — Smoke Tests ===' );
WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// WPLinkRepository
// ---------------------------------------------------------------------------
WP_CLI::log( 'WPLinkRepository' );

$repo = new \CFELR\WP\Repository\WPLinkRepository();

// create
$link              = new \CFELR\Core\Models\Link();
$link->slug        = 'smoke-test-' . wp_generate_password( 6, false );
$link->target_url  = 'https://example.com/smoke';
$link->status_code = 301;
$link->enabled     = true;

$id = $repo->create( $link );
if ( $id && is_int( $id ) ) {
	smoke_pass( "create() returned ID {$id}" );
} else {
	smoke_fail( 'create()', 'returned ' . var_export( $id, true ) );
}

// find
$found = $id ? $repo->find( $id ) : null;
if ( $found && $found->slug === $link->slug ) {
	smoke_pass( "find({$id}) returned correct slug" );
} else {
	smoke_fail( "find({$id})", $found ? 'slug mismatch' : 'null' );
}

// find_by_slug
$found2 = $repo->find_by_slug( $link->slug );
if ( $found2 && $found2->id === $id ) {
	smoke_pass( "find_by_slug('{$link->slug}') returned correct ID" );
} else {
	smoke_fail( "find_by_slug('{$link->slug}')", $found2 ? 'ID mismatch' : 'null' );
}

// get_all (no filters)
$all = $repo->get_all();
if ( is_array( $all ) && count( $all ) > 0 ) {
	smoke_pass( 'get_all() returned ' . count( $all ) . ' links' );
} else {
	smoke_fail( 'get_all()', 'empty or not array' );
}

// get_all (enabled filter)
$enabled = $repo->get_all( array( 'enabled' => true ) );
if ( is_array( $enabled ) ) {
	smoke_pass( 'get_all(enabled=true) returned ' . count( $enabled ) . ' links' );
} else {
	smoke_fail( 'get_all(enabled=true)' );
}

// get_all (search)
$searched = $repo->get_all( array( 'search' => 'smoke' ) );
if ( is_array( $searched ) && count( $searched ) >= 1 ) {
	smoke_pass( 'get_all(search=smoke) found ' . count( $searched ) . ' links' );
} else {
	smoke_fail( 'get_all(search=smoke)', 'expected at least 1' );
}

// get_all (orderby + ASC)
$sorted = $repo->get_all( array( 'orderby' => 'slug', 'order' => 'ASC', 'limit' => 5 ) );
if ( is_array( $sorted ) ) {
	smoke_pass( 'get_all(orderby=slug, order=ASC) ok' );
} else {
	smoke_fail( 'get_all(orderby=slug, order=ASC)' );
}

// count (no filters)
$total = $repo->count();
if ( is_int( $total ) && $total > 0 ) {
	smoke_pass( "count() = {$total}" );
} else {
	smoke_fail( 'count()', "returned {$total}" );
}

// count (enabled filter)
$cnt_enabled = $repo->count( array( 'enabled' => true ) );
if ( is_int( $cnt_enabled ) ) {
	smoke_pass( "count(enabled=true) = {$cnt_enabled}" );
} else {
	smoke_fail( 'count(enabled=true)' );
}

// count (search filter)
$cnt_search = $repo->count( array( 'search' => 'smoke' ) );
if ( is_int( $cnt_search ) && $cnt_search >= 1 ) {
	smoke_pass( "count(search=smoke) = {$cnt_search}" );
} else {
	smoke_fail( 'count(search=smoke)', "returned {$cnt_search}" );
}

// get_snapshot_data
$snapshot = $repo->get_snapshot_data();
if ( is_array( $snapshot ) && isset( $snapshot[ $link->slug ] ) ) {
	smoke_pass( 'get_snapshot_data() contains created link' );
} else {
	smoke_fail( 'get_snapshot_data()', 'missing created link slug' );
}

WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// WPStatsRepository
// ---------------------------------------------------------------------------
WP_CLI::log( 'WPStatsRepository' );

$stats = new \CFELR\WP\Repository\WPStatsRepository();

// record_click
$click_ok = $stats->record_click( $id );
if ( $click_ok ) {
	smoke_pass( "record_click({$id}) ok" );
} else {
	smoke_fail( "record_click({$id})" );
}

// record second click (tests ON DUPLICATE KEY UPDATE)
$click_ok2 = $stats->record_click( $id );
if ( $click_ok2 ) {
	smoke_pass( 'record_click() duplicate-key update ok' );
} else {
	smoke_fail( 'record_click() duplicate-key update' );
}

// get_clicks
$clicks = $stats->get_clicks( $id, 30 );
if ( $clicks >= 2 ) {
	smoke_pass( "get_clicks({$id}) = {$clicks}" );
} else {
	smoke_fail( "get_clicks({$id})", "expected >= 2, got {$clicks}" );
}

// get_top_links
$top = $stats->get_top_links( 30, 10 );
if ( is_array( $top ) ) {
	smoke_pass( 'get_top_links() returned ' . count( $top ) . ' entries' );
} else {
	smoke_fail( 'get_top_links()' );
}

// get_daily_stats
$daily = $stats->get_daily_stats( $id, 7 );
if ( is_array( $daily ) && count( $daily ) >= 1 ) {
	smoke_pass( 'get_daily_stats() returned ' . count( $daily ) . ' days' );
} else {
	smoke_fail( 'get_daily_stats()', 'expected at least 1 day' );
}

// get_total_clicks
$total_clicks = $stats->get_total_clicks( 30 );
if ( $total_clicks >= 2 ) {
	smoke_pass( "get_total_clicks() = {$total_clicks}" );
} else {
	smoke_fail( "get_total_clicks()", "expected >= 2, got {$total_clicks}" );
}

// delete_for_link
$deleted_stats = $stats->delete_for_link( $id );
if ( $deleted_stats >= 1 ) {
	smoke_pass( "delete_for_link({$id}) deleted {$deleted_stats} rows" );
} else {
	smoke_fail( "delete_for_link({$id})", "deleted {$deleted_stats}" );
}

// cleanup (should not error)
$cleaned = $stats->cleanup( 90 );
if ( is_int( $cleaned ) ) {
	smoke_pass( "cleanup(90) removed {$cleaned} old rows" );
} else {
	smoke_fail( 'cleanup()' );
}

WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// IntegrationState
// ---------------------------------------------------------------------------
WP_CLI::log( 'IntegrationState' );

$state = new \CFELR\Integrations\Cloudflare\IntegrationState();

// get (may be null if not configured — that's ok)
$state_data = $state->get();
if ( is_array( $state_data ) || $state_data === null ) {
	$label = $state_data === null ? 'null (not configured)' : 'array';
	smoke_pass( "get() returned {$label}" );
} else {
	smoke_fail( 'get()' );
}

// update_field
$update_ok = $state->update_field( '_smoke_test', 'ok' );
if ( $update_ok ) {
	smoke_pass( 'update_field() ok' );
} else {
	smoke_fail( 'update_field()' );
}

// verify written
$state_after = $state->get();
if ( is_array( $state_after ) && ( $state_after['_smoke_test'] ?? '' ) === 'ok' ) {
	smoke_pass( 'get() after update_field contains new value' );
} else {
	smoke_fail( 'get() after update_field', 'missing _smoke_test key' );
}

// clean up smoke key
$state->update_field( '_smoke_test', null );

WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// DashboardWidget (get_widget_data is private, test via render)
// ---------------------------------------------------------------------------
WP_CLI::log( 'DashboardWidget' );

$widget = new \CFELR\WP\Admin\DashboardWidget();
ob_start();
try {
	$widget->render();
	$html = ob_get_clean();
	if ( strlen( $html ) > 50 && str_contains( $html, 'cfelr-dashboard-widget' ) ) {
		smoke_pass( 'render() produced ' . strlen( $html ) . ' bytes of HTML' );
	} else {
		smoke_fail( 'render()', 'output too short or missing wrapper class' );
	}
} catch ( \Throwable $e ) {
	ob_end_clean();
	smoke_fail( 'render()', $e->getMessage() );
}

WP_CLI::log( '' );

// ---------------------------------------------------------------------------
// Cleanup: delete test link
// ---------------------------------------------------------------------------
if ( $id ) {
	$repo->delete( $id );
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
WP_CLI::log( '---' );
global $smoke_passed, $smoke_failed;
$total_tests = $smoke_passed + $smoke_failed;
if ( $smoke_failed === 0 ) {
	WP_CLI::success( "All {$total_tests} tests passed." );
} else {
	WP_CLI::error( "{$smoke_failed}/{$total_tests} tests failed.", false );
}
