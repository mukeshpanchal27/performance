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
		includes_url( 'js/wp-embed.min.js' ),
		includes_url( 'css/buttons.min.css' ),
		includes_url( 'fonts/dashicons.woff2' ),
		includes_url( 'images/media/video.png' ),
	);

	/**
	 * Filters the list of assets to check for far-future headers.
	 *
	 * @since n.e.x.t
	 *
	 * @param string[] $assets List of asset URLs to check.
	 */
	$assets = apply_filters( 'perflab_ffh_assets_to_check', $assets );

	// Check if far-future headers are enabled for all assets.
	$results = perflab_ffh_check_assets( $assets );

	if ( 'good' !== $results['final_status'] ) {
		$result['status'] = $results['final_status'];
		$result['label']  = __( 'Your site does not serve static assets with recommended far-future expiration headers', 'performance-lab' );

		if ( count( $results['details'] ) > 0 ) {
			$table_html        = perflab_ffh_get_extensions_table( $results['details'] );
			$result['actions'] = sprintf(
				'<p>%s</p>%s',
				esc_html__( 'The following file types do not have the recommended far-future headers. Consider adding or adjusting Cache-Control or Expires headers for these asset types.', 'performance-lab' ),
				$table_html
			);
		} else {
			$result['actions'] = sprintf(
				'<p>%s</p>',
				esc_html__( 'Far-future Cache-Control or Expires headers can be added or adjusted with a small configuration change by your hosting provider.', 'performance-lab' )
			);
		}
	}

	return $result;
}

/**
 * Checks if far-future expiration headers are enabled for a list of assets.
 *
 * @since n.e.x.t
 *
 * @param  string[] $assets List of asset URLs to check.
 * @return array{final_status: string, details: string[]} Final status and details.
 */
function perflab_ffh_check_assets( array $assets ): array {
	$final_status      = 'good';
	$extension_results = array(); // Extensions that need improvement.

	foreach ( $assets as $asset ) {
		$response = wp_remote_get( $asset, array( 'sslverify' => false ) );

		// Extract extension from the URL.
		$path_info = pathinfo( (string) wp_parse_url( $asset, PHP_URL_PATH ) );
		$extension = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : 'unknown';

		if ( is_wp_error( $response ) ) {
			continue;
		}

		$headers = wp_remote_retrieve_headers( $response );
		if ( ! is_object( $headers ) ) {
			continue;
		}

		if ( ! perflab_ffh_check_headers( $headers ) ) {
			if ( ! perflab_ffh_try_conditional_request( $asset, $headers ) ) {
				$final_status        = 'recommended';
				$extension_results[] = $extension;
				continue;
			}

			// Conditional pass means still recommended, not fully good.
			$final_status        = 'recommended';
			$extension_results[] = $extension;
		}
	}

	return array(
		'final_status' => $final_status,
		'details'      => $extension_results,
	);
}

/**
 * Checks if far-future expiration headers are enabled.
 *
 * @since n.e.x.t
 *
 * @param WpOrg\Requests\Utility\CaseInsensitiveDictionary $headers Response headers.
 * @return bool True if far-future headers are enabled, false otherwise.
 */
function perflab_ffh_check_headers( WpOrg\Requests\Utility\CaseInsensitiveDictionary $headers ): bool {
	/**
	 * Filters the threshold for far-future headers.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $threshold Threshold in seconds.
	 */
	$threshold = apply_filters( 'perflab_far_future_headers_threshold', YEAR_IN_SECONDS );

	$cache_control = isset( $headers['cache-control'] ) ? $headers['cache-control'] : '';
	$expires       = isset( $headers['expires'] ) ? $headers['expires'] : '';

	// Check Cache-Control header for max-age.
	$max_age = 0;
	if ( '' !== $cache_control ) {
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
	if ( is_string( $expires ) && '' !== $expires ) {
		$expires_time = strtotime( $expires );
		if ( (bool) $expires_time && ( $expires_time - time() ) >= $threshold ) {
			return true;
		}
	}

	return false;
}

/**
 * Attempt a conditional request with ETag/Last-Modified.
 *
 * @param string                                           $url     The asset URL.
 * @param WpOrg\Requests\Utility\CaseInsensitiveDictionary $headers The initial response headers.
 * @return bool True if a 304 response was received.
 */
function perflab_ffh_try_conditional_request( string $url, WpOrg\Requests\Utility\CaseInsensitiveDictionary $headers ): bool {
	$etag          = isset( $headers['etag'] ) ? $headers['etag'] : '';
	$last_modified = isset( $headers['last-modified'] ) ? $headers['last-modified'] : '';

	$conditional_headers = array();
	if ( '' !== $etag ) {
		$conditional_headers['If-None-Match'] = $etag;
	}
	if ( '' !== $last_modified ) {
		$conditional_headers['If-Modified-Since'] = $last_modified;
	}

	$response = wp_remote_get(
		$url,
		array(
			'sslverify' => false,
			'headers'   => $conditional_headers,
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	return ( 304 === $status_code );
}

/**
 * Generate a table listing file extensions that need far-future headers.
 *
 * @since n.e.x.t
 *
 * @param string[] $extensions Array of file extensions needing improvement.
 * @return string HTML formatted table.
 */
function perflab_ffh_get_extensions_table( array $extensions ): string {
	$html_table = sprintf(
		'<table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>',
		esc_html__( 'File Extension', 'performance-lab' ),
		esc_html__( 'Status', 'performance-lab' )
	);

	foreach ( $extensions as $extension ) {
		$html_table .= sprintf(
			'<tr><td>%s</td><td>%s</td></tr>',
			esc_html( $extension ),
			esc_html__( 'Needs far-future headers', 'performance-lab' )
		);
	}

	$html_table .= '</tbody></table>';

	return $html_table;
}
