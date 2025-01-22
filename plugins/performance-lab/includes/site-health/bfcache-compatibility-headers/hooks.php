<?php
/**
 * Hook callbacks used for cache-control headers.
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
 * Add the bfcache compatibility check to site health tests.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_bfcache_add_compatibility_test( array $tests ): array {
	$tests['direct']['perflab_cch_cache_control'] = array(
		'label' => __( 'Cache-Control headers may prevent fast back/forward navigation', 'performance-lab' ),
		'test'  => 'perflab_bfcache_check_compatibility',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_bfcache_add_compatibility_test' );
