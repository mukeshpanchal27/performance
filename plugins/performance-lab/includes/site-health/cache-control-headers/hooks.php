<?php
/**
 * Hook callbacks used for cache-control headers.
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
		'label' => __( 'Cache settings may impact site performance', 'performance-lab' ),
		'test'  => 'perflab_cch_check_cache_control_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_cch_add_cache_control_test' );
