<?php
/**
 * Tests for cache-control headers health check.
 *
 * @package performance-lab
 * @group cache-control-headers
 */

class Test_Cache_Control_Headers extends WP_UnitTestCase {

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
	 * Test that the cache-control headers test is added to the site health tests.
	 *
	 * @covers ::perflab_cch_add_cache_control_test
	 */
	public function test_perflab_cch_add_cache_control_test(): void {
		$tests = array(
			'direct' => array(),
		);

		$tests = perflab_cch_add_cache_control_test( $tests );
		$this->assertArrayHasKey( 'perflab_cch_cache_control', $tests['direct'] );
		$this->assertEquals( 'Cache settings may impact site performance', $tests['direct']['perflab_cch_cache_control']['label'] );
		$this->assertEquals( 'perflab_cch_add_check_cache_control_test', $tests['direct']['perflab_cch_cache_control']['test'] );
	}

	/**
	 * Test that the cache-control headers test is attached to the site status tests.
	 *
	 * @covers ::perflab_cch_add_cache_control_test
	 */
	public function test_perflab_cch_add_cache_control_test_is_attached_to_site_status_tests(): void {
		$this->assertNotFalse( has_filter( 'site_status_tests', 'perflab_cch_add_cache_control_test' ) );
	}

	/**
	 * Test that different conditional headers return the correct result.
	 *
	 * @dataProvider data_test_cache_control_headers
	 * @covers ::perflab_cch_check_cache_control_test
	 *
	 * @param array<int, mixed>|WP_Error $response The response headers.
	 * @param string                     $expected_status   The expected status.
	 * @param string                     $expected_message  The expected message.
	 */
	public function test_perflab_cch_check_cache_control_test( $response, string $expected_status, string $expected_message ): void {
		$this->mocked_responses = array( home_url() => $response );

		$result = perflab_cch_check_cache_control_test();

		$this->assertEquals( $expected_status, $result['status'] );
		$this->assertStringContainsString( $expected_message, $result['description'] );
	}

	/**
	 * Data provider for test_cache_control_headers.
	 *
	 * @return array<string, array<int, mixed>> Test data.
	 */
	public function data_test_cache_control_headers(): array {
		return array(
			'headers_not_set'    => array(
				$this->build_response( 200, array( 'cache-control' => '' ) ),
				'recommended',
				'Cache-Control headers are not set correctly',
			),
			'no_store'           => array(
				$this->build_response( 200, array( 'cache-control' => 'no-store' ) ),
				'recommended',
				'Cache-Control headers are set to no-store',
			),
			'no_cache'           => array(
				$this->build_response( 200, array( 'cache-control' => 'no-cache' ) ),
				'recommended',
				'Cache-Control headers are set to no-cache',
			),
			'max_age_0'          => array(
				$this->build_response( 200, array( 'cache-control' => 'max-age=0' ) ),
				'recommended',
				'Cache-Control headers are set to max-age=0',
			),
			'max_age_0_no_store' => array(
				$this->build_response( 200, array( 'cache-control' => 'max-age=0, no-store' ) ),
				'recommended',
				'Cache-Control headers are set to no-store, max-age=0',
			),
			'error'              => array(
				new WP_Error( 'http_request_failed', 'HTTP request failed' ),
				'recommended',
				'There was an error while checking your site cache settings',
			),
		);
	}

	/**
	 * Mock HTTP requests for assets to simulate different responses.
	 *
	 * @param bool                 $response A preemptive return value of an HTTP request. Default false.
	 * @param array<string, mixed> $args     Request arguments.
	 * @param string               $url      The request URL.
	 * @return array<string, mixed>|WP_Error Mocked response.
	 */
	public function mock_http_requests( bool $response, array $args, string $url ) {
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
