<?php
/**
 * Tests for Optimization Detective REST API site health check.
 *
 * @package optimization-detective
 */

class Test_OD_REST_API_Site_Health_Check extends WP_UnitTestCase {

	/**
	 * Holds mocked response headers for different test scenarios.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected $mocked_responses = array();

	/**
	 * Setup each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any filters or mocks.
		remove_all_filters( 'pre_http_request' );

		// Add the filter to mock HTTP requests.
		add_filter( 'pre_http_request', array( $this, 'mock_http_requests' ), 10, 3 );
	}

	/**
	 * Test that the site health check is `good` when the REST API is available.
	 */
	public function test_rest_api_available(): void {
		$this->mocked_responses = array(
			get_rest_url( null, OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ) => $this->build_mock_response(
				400,
				'Bad Request',
				array(
					'data' => array(
						'params' => array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' ),
					),
				)
			),
		);

		$result = od_optimization_detective_rest_api_test();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Mock HTTP requests for assets to simulate different responses.
	 *
	 * @param bool                 $response A preemptive return value of an HTTP request. Default false.
	 * @param array<string, mixed> $args     Request arguments.
	 * @param string               $url      The request URL.
	 * @return array<string, mixed> Mocked response.
	 */
	public function mock_http_requests( bool $response, array $args, string $url ): array {
		if ( isset( $this->mocked_responses[ $url ] ) ) {
			return $this->mocked_responses[ $url ];
		}

		// If no specific mock set, default to a generic success response.
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
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
