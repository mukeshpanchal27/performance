<?php
/**
 * Tests for Optimization Detective REST API site health check.
 *
 * @package optimization-detective
 */

class Test_OD_REST_API_Site_Health_Check extends WP_UnitTestCase {

	/**
	 * Test that we presume the REST API is accessible before we are able to perform the Site Health check.
	 *
	 * @covers ::od_is_rest_api_inaccessible
	 */
	public function test_rest_api_assumed_accessible(): void {
		$this->assertFalse( get_option( 'od_rest_api_inaccessible', false ) );
		$this->assertFalse( od_is_rest_api_inaccessible() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_rest_api_availability(): array {
		return array(
			'available'    => array(
				'mocked_response'      => $this->build_mock_response(
					400,
					'Bad Request',
					array(
						'data' => array(
							'params' => array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' ),
						),
					)
				),
				'expected_option'      => '0',
				'expected_status'      => 'good',
				'expected_unavailable' => false,
			),
			'unauthorized' => array(
				'mocked_response'      => $this->build_mock_response(
					401,
					'Unauthorized'
				),
				'expected_option'      => '1',
				'expected_status'      => 'recommended',
				'expected_unavailable' => true,
			),
			'forbidden'    => array(
				'mocked_response'      => $this->build_mock_response(
					403,
					'Forbidden'
				),
				'expected_option'      => '1',
				'expected_status'      => 'recommended',
				'expected_unavailable' => true,
			),
		);
	}

	/**
	 * Test various conditions for the REST API being available.
	 *
	 * @covers ::od_optimization_detective_rest_api_test
	 * @covers ::od_construct_site_health_result
	 * @covers ::od_get_rest_api_health_check_response
	 * @covers ::od_is_rest_api_inaccessible
	 *
	 * @dataProvider data_provider_test_rest_api_availability
	 *
	 * @phpstan-param array<string, mixed> $mocked_response
	 */
	public function test_rest_api_availability( array $mocked_response, string $expected_option, string $expected_status, bool $expected_unavailable ): void {
		add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) use ( $mocked_response ) {
				if ( rest_url( OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ) === $url ) {
					return $mocked_response;
				}
				return $pre;
			},
			10,
			3
		);

		$result = od_optimization_detective_rest_api_test();
		$this->assertSame( $expected_option, get_option( 'od_rest_api_inaccessible', '' ) );
		$this->assertSame( $expected_status, $result['status'] );
		$this->assertSame( $expected_unavailable, od_is_rest_api_inaccessible() );
	}

	/**
	 * Build a mock response.
	 *
	 * @param int                  $status_code HTTP status code.
	 * @param string               $message     HTTP status message.
	 * @param array<string, mixed> $body        Response body.
	 * @return array<string, mixed> Mocked response.
	 */
	protected function build_mock_response( int $status_code, string $message, array $body = array() ): array {
		return array(
			'response' => array(
				'code'    => $status_code,
				'message' => $message,
			),
			'body'     => wp_json_encode( $body ),
		);
	}
}
