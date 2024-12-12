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
	 * Test that when an asset has no far-future headers but has conditional caching (ETag/Last-Modified), status is 'recommended'.
	 */
	public function test_assets_conditionally_cached(): void {
		// For conditional caching scenario, setting etag/last-modified headers.
		$this->mocked_responses = array(
			'js/wp-embed.min.js'     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 1000 ) ) ),
			'css/buttons.min.css'    => $this->build_response( 200, array( 'etag' => '"123456789"' ) ),
			'fonts/dashicons.woff2'  => $this->build_response( 200, array( 'last-modified' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT' ) ),
			'images/media/video.png' => $this->build_response(
				200,
				array(
					'etag'          => '"123456789"',
					'last-modified' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT',
				)
			),
			'conditional_304'        => $this->build_response( 304 ),
		);

		$result = perflab_ffh_assets_test();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertNotEmpty( $result['actions'] );
	}

	/**
	 * Test that different status messages are returned based on the test results.
	 */
	public function test_status_messages(): void {
		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS - 1000 ) ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS - 1000 ) . ' GMT' ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'etag' => '"123456789"' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array() ),
			'conditional_304'                        => $this->build_response( 304 ),
		);

		$result = perflab_ffh_check_assets(
			array(
				includes_url( 'js/wp-embed.min.js' ),
				includes_url( 'css/buttons.min.css' ),
				includes_url( 'fonts/dashicons.woff2' ),
				includes_url( 'images/media/video.png' ),
			)
		);

		$this->assertEquals( 'recommended', $result['final_status'] );
		$this->assertEquals( 'max-age below threshold', $result['details'][0]['reason'] );
		$this->assertEquals( 'expires below threshold', $result['details'][1]['reason'] );
		$this->assertEquals( 'No far-future headers but conditionally cached', $result['details'][2]['reason'] );
		$this->assertEquals( 'No far-future headers and no conditional caching', $result['details'][3]['reason'] );
	}

	/**
	 * Test that the filter `perflab_ffh_assets_to_check` and `perflab_far_future_headers_threshold` are working as expected.
	 */
	public function test_filters(): void {
		add_filter(
			'perflab_ffh_assets_to_check',
			static function ( $assets ) {
				$assets[] = includes_url( 'images/blank.gif' );
				return $assets;
			}
		);

		add_filter(
			'perflab_far_future_headers_threshold',
			static function () {
				return 1000;
			}
		);

		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . 1500 ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'cache-control' => 'max-age=' . 500 ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + 1500 ) . ' GMT' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + 500 ) . ' GMT' ) ),
			includes_url( 'images/blank.gif' )       => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( 500 ) ) ),
		);

		$result = perflab_ffh_check_assets(
			array(
				includes_url( 'js/wp-embed.min.js' ),
				includes_url( 'css/buttons.min.css' ),
				includes_url( 'fonts/dashicons.woff2' ),
				includes_url( 'images/media/video.png' ),
				includes_url( 'images/blank.gif' ),
			)
		);

		$this->assertEquals( 'recommended', $result['final_status'] );
		$this->assertEquals( 'max-age below threshold', $result['details'][0]['reason'] );
		$this->assertEquals( 'expires below threshold', $result['details'][1]['reason'] );
		$this->assertEquals( 'max-age below threshold', $result['details'][2]['reason'] );
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
		// If conditional headers used in second request, simulate a 304 response.
		if ( isset( $this->mocked_responses['conditional_304'] ) && ( isset( $args['headers']['If-None-Match'] ) || isset( $args['headers']['If-Modified-Since'] ) ) ) {
			return $this->mocked_responses['conditional_304'];
		}

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
