<?php
/**
 * Helper function to detect if static assets have far-future expiration headers.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback for the far-future caching test.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function perflab_ffh_assets_test(): array {
	$result = array(
		'label'       => __( 'Your site serves static assets with far-future expiration headers', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html__(
				'Serving static assets with far-future expiration headers improves performance by allowing browsers to cache files for a long time, reducing repeated requests.',
				'performance-lab'
			)
		),
		'actions'     => '',
		'test'        => 'is_far_future_headers_enabled',
	);

	// List of assets to check.
	$assets = array(
		plugins_url( 'far-future-headers/assets/test.css', __DIR__ ),
	);

	// Check if far-future headers are enabled for all assets.
	$far_future_enabled = true;
	foreach ( $assets as $asset ) {
		if ( ! perflab_far_future_headers_is_enabled( $asset ) ) {
			$far_future_enabled = false;
			break;
		}
	}

	if ( ! $far_future_enabled ) {
		$result['status']  = 'recommended';
		$result['label']   = __( 'Your site does not serve static assets with recommended far-future expiration headers', 'performance-lab' );
		$result['actions'] = sprintf(
			'<p>%s</p>',
			esc_html__( 'Consider adding or adjusting your server configuration (e.g., .htaccess, nginx config, or a caching plugin) to include far-future Cache-Control or Expires headers for static assets.', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Checks if far-future expiration headers are enabled.
 *
 * @since n.e.x.t
 *
 * @param  string $url URL to check.
 * @return bool True if far-future headers are enabled, false otherwise.
 */
function perflab_far_future_headers_is_enabled( string $url ): bool {
	/**
	 * Filters the threshold for far-future headers.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $threshold Threshold in seconds.
	 */
	$threshold = apply_filters( 'perflab_far_future_headers_threshold', YEAR_IN_SECONDS );

	$response = wp_remote_request( $url, array( 'sslverify' => false ) );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$headers = wp_remote_retrieve_headers( $response );

	$cache_control = isset( $headers['cache-control'] ) ? $headers['cache-control'] : '';
	$expires       = isset( $headers['expires'] ) ? $headers['expires'] : '';

	// Check Cache-Control header for max-age.
	$max_age = 0;
	if ( $cache_control ) {
		// Cache-Control can have multiple directives; we only care about max-age.
		$controls = is_array( $cache_control ) ? $cache_control : array( $cache_control );
		foreach ( $controls as $control ) {
			if ( (bool) preg_match( '/max-age\s*=\s*(\d+)/', $control, $matches ) ) {
				$max_age = (int) $matches[1];
				break;
			}
		}
	}

	// If max-age meets or exceeds the threshold, we consider it good.
	if ( $max_age >= $threshold ) {
		return true;
	}

	// If max-age is not sufficient, check Expires.
	// Expires is a date; we want to ensure it's far in the future.
	if ( $expires ) {
		$expires_time = strtotime( $expires );
		if ( $expires_time && ( $expires_time - time() ) >= $threshold ) {
			return true;
		}
	}

	return false;
}
