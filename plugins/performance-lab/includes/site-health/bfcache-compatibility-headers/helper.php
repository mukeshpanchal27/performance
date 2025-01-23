<?php
/**
 * Helper functions used for Cache-Control headers for bfcache compatibility site health check.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Tests the Cache-Control headers for bfcache compatibility.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function perflab_bfcache_compatibility_headers_check(): array {
	$result = array(
		'label'       => __( 'The Cache-Control page header is compatible with fast back/forward navigations', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => '<p>' . wp_kses(
			__( 'If the <code>Cache-Control</code> page response header includes directives like <code>no-store</code>, <code>no-cache</code>, or <code>max-age=0</code> then it can prevent instant back/forward navigations (using the browser bfcache). Your site is configured properly.', 'performance-lab' ),
			array( 'code' => array() )
		) . '</p>',
		'actions'     => '',
		'test'        => 'perflab_cch_cache_control_header_check',
	);

	$response = wp_remote_get(
		home_url( '/' ),
		array(
			'headers'   => array( 'Accept' => 'text/html' ),
			'sslverify' => false,
		)
	);

	if ( is_wp_error( $response ) ) {
		$result['label']       = __( 'Unable to check whether the Cache-Control page header is compatible with fast back/forward navigations', 'performance-lab' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . wp_kses(
			sprintf(
				/* translators: 1: the error code, 2: the error message */
				__( 'The request to check the <code>Cache-Control</code> response header for the home page resulted in an error with code <code>%1$s</code> and the following message: %2$s.', 'performance-lab' ),
				esc_html( (string) $response->get_error_code() ),
				esc_html( rtrim( $response->get_error_message(), '.' ) )
			),
			array( 'code' => array() )
		) . '</p>';
		return $result;
	}

	$cache_control_headers = wp_remote_retrieve_header( $response, 'cache-control' );
	if ( '' === $cache_control_headers ) {
		// The Cache-Control header is not set, so it does not prevent bfcache. Return the default result.
		return $result;
	}

	foreach ( (array) $cache_control_headers as $cache_control_header ) {
		$cache_control_header = strtolower( $cache_control_header );
		$found_directives     = array();
		foreach ( array( 'no-store', 'no-cache', 'max-age=0' ) as $directive ) {
			if ( str_contains( $cache_control_header, $directive ) ) {
				$found_directives[] = $directive;
			}
		}

		if ( count( $found_directives ) > 0 ) {
			$result['label']       = __( 'The Cache-Control page header is preventing fast back/forward navigations', 'performance-lab' );
			$result['status']      = 'recommended';
			$result['description'] = sprintf(
				'<p>%s %s</p>',
				wp_kses(
					sprintf(
						/* translators: %s: problematic directive(s) */
						_n(
							'The <code>Cache-Control</code> response header for the home page includes the following directive: %s.',
							'The <code>Cache-Control</code> response header for the home page includes the following directives: %s.',
							count( $found_directives ),
							'performance-lab'
						),
						implode(
							', ',
							array_map(
								static function ( $header ) {
									return "<code>$header</code>";
								},
								$found_directives
							)
						)
					),
					array( 'code' => array() )
				),
				esc_html__( 'This can affect the performance of your site by preventing fast back/forward navigations (via browser bfcache).', 'performance-lab' )
			);
			break;
		}
	}

	return $result;
}
