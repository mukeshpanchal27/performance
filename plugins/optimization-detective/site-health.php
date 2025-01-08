<?php
/**
 * Site Health checks.
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

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_optimization_detective_rest_api_test(): array {
	$result = array(
		'label'       => __( 'The REST API endpoint is functional.', 'optimization-detective' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Optimization Detective', 'optimization-detective' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Your site can send and receive URL metrics via the REST API endpoint.', 'optimization-detective' )
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
		$result['label']       = __( 'Error accessing the REST API endpoint', 'optimization-detective' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'There was an issue reaching the REST API endpoint. This might be due to server settings or the REST API being disabled.', 'optimization-detective' )
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
			$result['label']       = __( 'Authorization should not be required to access the REST API endpoint.', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'To collect URL metrics, the REST API endpoint should be accessible without requiring authorization.', 'optimization-detective' )
			);
		} elseif ( 403 === $status_code ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'The REST API endpoint should not be forbidden.', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'The REST API endpoint is blocked. Please review your server or security settings.', 'optimization-detective' )
			);
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Error accessing the REST API endpoint', 'optimization-detective' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'There was an issue reaching the REST API endpoint. This might be due to server settings or the REST API being disabled.', 'optimization-detective' )
			);
		}
		$info['error_message'] = $result['label'];
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

/**
 * Displays an admin notice if the REST API health check fails.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_file Plugin file.
 */
function od_rest_api_health_check_admin_notice( string $plugin_file ): void {
	if ( 'optimization-detective/load.php' !== $plugin_file ) {
		return;
	}

	$od_rest_api_info = get_option( 'od_rest_api_info', array() );
	if (
		isset( $od_rest_api_info['available'] ) &&
		! (bool) $od_rest_api_info['available'] &&
		isset( $od_rest_api_info['error_message'] )
	) {
		wp_admin_notice(
			esc_html( $od_rest_api_info['error_message'] ),
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'inline', 'notice-alt' ),
			)
		);
	}
}

/**
 * Plugin activation hook for the REST API health check.
 *
 * @since n.e.x.t
 */
function od_rest_api_health_check_plugin_activation(): void {
	// Add the option if it doesn't exist.
	if ( ! (bool) get_option( 'od_rest_api_info' ) ) {
		add_option( 'od_rest_api_info', array() );
	}
	od_schedule_rest_api_health_check();
	// Run the check immediately after Optimization Detective is activated.
	add_action(
		'activated_plugin',
		static function ( string $plugin ): void {
			if ( 'optimization-detective/load.php' === $plugin ) {
				od_optimization_detective_rest_api_test();
			}
		}
	);
}
