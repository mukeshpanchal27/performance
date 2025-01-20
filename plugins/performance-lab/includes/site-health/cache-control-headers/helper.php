<?php
/**
 * Helper functions used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Tests the Cache-Control headers.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function perflab_cch_add_check_cache_control_test(): array {
	$result = array(
		'label'       => __( 'Cache settings are optimal for site performance.', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => '<p>' . esc_html__( 'Your site’s cache settings are configured correctly, helping to improve its performance.', 'performance-lab' ) . '</p>',
		'actions'     => '',
		'test'        => 'perflab_cch_cache_control_header_check',
	);

	$response = wp_remote_get(
		home_url(),
		array(
			'headers'   => array( 'Content-Type' => 'text/html' ),
			'sslverify' => false,
		)
	);

	if ( is_wp_error( $response ) ) {
		$result['label']       = __( 'Error checking cache settings', 'performance-lab' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . wp_kses(
			sprintf(
				/* translators: %s is the error code */
				__( 'There was an error while checking your site cache settings. Error code: <code>%s</code> and the following error message:', 'performance-lab' ),
				esc_html( (string) $response->get_error_code() )
			),
			array( 'code' => array() )
		) . '</p><blockquote>' . esc_html( $response->get_error_message() ) . '</blockquote>';
		return $result;
	}

	$cache_control_header = wp_remote_retrieve_header( $response, 'cache-control' );
	if ( is_string( $cache_control_header ) && '' === $cache_control_header ) {
		$result['label']       = __( 'Cache-Control headers are not set correctly', 'performance-lab' );
		$result['status']      = 'recommended';
		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'Cache-Control headers are not set correctly. This can affect the performance of your site.', 'performance-lab' )
		);
		return $result;
	}

	$cache_control_header = (array) $cache_control_header;
	foreach ( $cache_control_header as $header ) {
		$header           = strtolower( $header );
		$headers_to_check = array( 'no-store', 'no-cache', 'max-age=0' );
		$flagged_headers  = array();

		foreach ( $headers_to_check as $header_to_check ) {
			if ( is_int( strpos( $header, $header_to_check ) ) ) {
				$flagged_headers[] = $header_to_check;
			}
		}

		$flagged_headers_string = implode( ', ', $flagged_headers );
		if ( '' !== $flagged_headers_string ) {
			$result['label'] = sprintf(
				/* translators: %s: Cache-Control header value */
				__( 'Cache-Control headers are set to %s', 'performance-lab' ),
				$flagged_headers_string
			);
			$result['status']      = 'recommended';
			$result['description'] = sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Cache-Control header value */
					esc_html__( 'Cache-Control headers are set to %s. This can affect the performance of your site.', 'performance-lab' ),
					$flagged_headers_string
				)
			);
			break;
		}
	}

	return $result;
}
