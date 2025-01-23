<?php
/**
 * Hook callbacks used for effective caching headers.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Adds tests to site health.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_effective_asset_cache_headers_add_test( array $tests ): array {
	$tests['direct']['effective_asset_cache_headers'] = array(
		'label' => __( 'Effective Caching Headers', 'performance-lab' ),
		'test'  => 'perflab_effective_asset_cache_headers_assets_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_effective_asset_cache_headers_add_test' );
