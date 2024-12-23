<?php
/**
 * Helper functions for the Optimization Detective REST API health check.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_optimization_detective_rest_api_test(): array {
	$result = array(
		'label'       => __( 'Your site has functional Optimization Detective REST API endpoint', 'optimization-detective' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Optimization Detective', 'optimization-detective' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Optimization Detective can send and store URL metrics via REST API endpoint', 'optimization-detective' )
		),
		'actions'     => '',
		'test'        => 'optimization_detective_rest_api',
	);

	$rest_url = get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE );
	$response = wp_remote_post(
		$rest_url,
		array(
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => false,
		)
	);

	if ( is_wp_error( $response ) ) {
		$result['status']      = 'recommended';
		$result['label']       = __( 'Your site encountered error accessing Optimization Detective REST API endpoint', 'optimization-detective' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'The Optimization Detective endpoint could not be reached. This might mean the REST API is disabled or blocked.', 'optimization-detective' )
		);
		$info                  = array(
			'error_message' => $response->get_error_message(),
			'error_code'    => $response->get_error_code(),
			'available'     => false,
		);
	} else {
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = json_decode( wp_remote_retrieve_body( $response ), true );
		$expected_params = array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' );
		$info            = array(
			'status_code' => $status_code,
			'available'   => false,
		);

		if (
			400 === $status_code
			&& isset( $data['data']['params'] )
			&& is_array( $data['data']['params'] )
			&& count( $expected_params ) === count( array_intersect( $data['data']['params'], $expected_params ) )
		) {
			// The REST API endpoint is available.
			$info['available'] = true;
		} elseif ( 401 === $status_code ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Your site encountered unauthorized error for Optimization Detective REST API endpoint', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'The REST API endpoint requires authentication. Ensure proper credentials are provided.', 'optimization-detective' )
			);
		} elseif ( 403 === $status_code ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Your site encountered forbidden error for Optimization Detective REST API endpoint', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'The REST API endpoint is blocked check server or security settings.', 'optimization-detective' )
			);
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Your site encountered error accessing Optimization Detective REST API endpoint', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'The Optimization Detective endpoint could not be reached. This might mean the REST API is disabled or blocked.', 'optimization-detective' )
			);
		}
	}

	update_option( 'od_rest_api_info', $info );
	return $result;
}

/**
 * Periodically runs the Optimization Detective REST API health check.
 *
 * @since n.e.x.t
 */
function od_schedule_rest_api_health_check(): void {
	if ( ! (bool) wp_next_scheduled( 'od_rest_api_health_check_event' ) ) {
		wp_schedule_event( time(), 'weekly', 'od_rest_api_health_check_event' );
	}
}

/**
 * Hook for the scheduled REST API health check.
 *
 * @since n.e.x.t
 */
function od_run_scheduled_rest_api_health_check(): void {
	od_optimization_detective_rest_api_test();
}
