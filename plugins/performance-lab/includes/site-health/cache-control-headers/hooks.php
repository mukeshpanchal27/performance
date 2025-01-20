<?php
/**
 * Hook callbacks used for Cache-Control headers.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add the Cache-Control check to site health tests.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_cch_add_cache_control_test( array $tests ): array {
	$tests['direct']['perflab_cch_cache_control'] = array(
		'label' => __( 'Use of Cache-Control no-store/no-cache/max-age=0 for unauthenticated homepage', 'performance-lab' ),
		'test'  => 'perflab_cch_add_check_cache_control_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_cch_add_cache_control_test' );
