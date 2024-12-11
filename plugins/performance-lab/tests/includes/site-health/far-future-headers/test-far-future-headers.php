<?php
/**
 * Tests for far-future headers health check.
 *
 * @package performance-lab
 * @group far-future-headers
 */

class Test_Far_Future_Headers extends WP_UnitTestCase {

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
	 * Test that when all assets have valid far-future headers, the status is "good".
	 */
	public function test_all_assets_valid_far_future_headers(): void {
		// Mock responses: all assets have a max-age > 1 year (threshold).
		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 1000 ) ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 500 ) ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS + 1000 ) . ' GMT' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 2000 ) ) ),
		);

		$result = perflab_ffh_assets_test();
		$this->assertEquals( 'good', $result['status'] );
		$this->assertEmpty( $result['actions'] );
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

		// If no specific mock set, default to a generic success with no caching.
		return $this->build_response( 200 );
	}

	/**
	 * Helper method to build a mock HTTP response.
	 *
	 * @param int                       $status_code HTTP status code.
	 * @param array<string, string|int> $headers     HTTP headers.
	 * @return array{response: array{code: int, message: string}, headers: WpOrg\Requests\Utility\CaseInsensitiveDictionary}
	 */
	protected function build_response( int $status_code = 200, array $headers = array() ): array {
		return array(
			'response' => array(
				'code'    => $status_code,
				'message' => '',
			),
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( $headers ),
		);
	}
}
