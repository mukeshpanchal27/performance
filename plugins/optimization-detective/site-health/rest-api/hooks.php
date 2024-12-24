<?php
/**
 * Hook callbacks used for the Optimization Detective REST API health check.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the Optimization Detective REST API check to site health tests.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function od_optimization_detective_add_rest_api_test( array $tests ): array {
	$tests['direct']['optimization_detective_rest_api'] = array(
		'label' => __( 'Optimization Detective REST API Endpoint Availability', 'optimization-detective' ),
		'test'  => 'od_optimization_detective_rest_api_test',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'od_optimization_detective_add_rest_api_test' );

// Hook for the scheduled REST API health check.
add_action( 'od_rest_api_health_check_event', 'od_run_scheduled_rest_api_health_check' );
add_action( 'after_plugin_row_meta', 'od_rest_api_health_check_admin_notice', 30 );
