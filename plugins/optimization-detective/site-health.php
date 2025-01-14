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
		'label'       => __( 'The Optimization Detective REST API endpoint is functional.', 'optimization-detective' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Optimization Detective', 'optimization-detective' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Your site can send and receive URL metrics via the Optimization Detective REST API endpoint.', 'optimization-detective' )
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
		$result['label']       = __( 'Error accessing the Optimization Detective REST API endpoint', 'optimization-detective' );
		$result['description'] = esc_html__( 'There was an issue reaching the Optimization Detective REST API endpoint. This might be due to server settings or the REST API being disabled.', 'optimization-detective' );
		$info                  = array(
			'error_message' => $result['description'],
			'error_code'    => $response->get_error_code(),
			'available'     => false,
		);
	} else {
		$status_code = wp_remote_retrieve_response_code( $response );
		$data        = json_decode( wp_remote_retrieve_body( $response ), true );
		$info        = array(
			'status_code'   => $status_code,
			'available'     => false,
			'error_message' => '',
		);

		if (
			400 === $status_code &&
			isset( $data['data']['params'] ) &&
			is_array( $data['data']['params'] ) &&
			count( $data['data']['params'] ) > 0
		) {
			// The REST API endpoint is available.
			$info['available'] = true;
		} elseif ( 401 === $status_code ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Authorization should not be required to access the Optimization Detective REST API endpoint.', 'optimization-detective' );
			$result['description'] = esc_html__( 'To collect URL metrics, the Optimization Detective REST API endpoint should be accessible without requiring authorization.', 'optimization-detective' );
		} elseif ( 403 === $status_code ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Access to the Optimization Detective REST API endpoint was denied.', 'optimization-detective' );
			$result['description'] = esc_html__( 'Access was denied because the user does not have the necessary capabilities. Please check user roles and capabilities, as all users should have access to the Optimization Detective REST API endpoint.', 'optimization-detective' );
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Error accessing the Optimization Detective REST API endpoint', 'optimization-detective' );
			$result['description'] = esc_html__( 'There was an issue reaching the Optimization Detective REST API endpoint. This might be due to server settings or the REST API being disabled.', 'optimization-detective' );
		}
		$info['error_message'] = $result['description'];
	}

	$result['description'] = sprintf(
		'<p>%s</p>',
		$result['description']
	);

	update_option( 'od_rest_api_info', $info );
	return $result;
}

/**
 * Renders an admin notice if the REST API health check fails.
 *
 * @since n.e.x.t
 *
 * @param array<string> $additional_classes Additional classes to add to the notice.
 */
function od_render_rest_api_health_check_notice( array $additional_classes ): void {
	$rest_api_info = get_option( 'od_rest_api_info', array() );
	if (
		isset( $rest_api_info['available'] ) &&
		false === $rest_api_info['available'] &&
		isset( $rest_api_info['error_message'] ) &&
		'' !== $rest_api_info['error_message']
	) {
		wp_admin_notice(
			esc_html( $rest_api_info['error_message'] ),
			array(
				'type'               => 'warning',
				'additional_classes' => $additional_classes,
			)
		);
	}
}

/**
 * Displays an admin notice on the plugin row if the REST API health check fails.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_file Plugin file.
 */
function od_rest_api_health_check_admin_notice( string $plugin_file ): void {
	if ( 'optimization-detective/load.php' !== $plugin_file ) {
		return;
	}
	od_render_rest_api_health_check_notice( array( 'inline', 'notice-alt' ) );
}

/**
 * Plugin activation hook for the REST API health check.
 *
 * @since n.e.x.t
 */
function od_rest_api_health_check_plugin_activation(): void {
	// If the option already exists, do nothing except attach our plugin-row notice.
	if ( false !== get_option( 'od_rest_api_info' ) ) {
		add_action( 'after_plugin_row_meta', 'od_rest_api_health_check_admin_notice', 30 );
		return;
	}

	// This will populate the od_rest_api_info option so that the function won't execute on the next page load.
	od_optimization_detective_rest_api_test();
	od_render_rest_api_health_check_notice( array( 'notice-alt' ) );
}
