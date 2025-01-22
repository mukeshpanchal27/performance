<?php
/**
 * Hook callbacks used for far-future headers.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds tests to site health.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_ffh_add_test( array $tests ): array {
	$tests['direct']['far_future_headers'] = array(
		'label' => __( 'Effective Caching Headers', 'performance-lab' ),
		'test'  => 'perflab_ffh_assets_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_ffh_add_test' );
