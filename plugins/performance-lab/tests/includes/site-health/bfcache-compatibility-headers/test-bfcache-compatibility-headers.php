<?php
/**
 * Tests for cache-control headers for bfcache compatibility site health check.
 *
 * @package performance-lab
 * @group bfcache-compatibility-headers
 */

class Test_BFCache_Compatibility_Headers extends WP_UnitTestCase {

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
	 * Test that the bfcache compatibility test is added to the site health tests.
	 *
	 * @covers ::perflab_bfcache_compatibility_headers_add_test
	 */
	public function test_perflab_bfcache_compatibility_headers_add_test(): void {
		$tests = array(
			'direct' => array(),
		);

		$tests = perflab_bfcache_compatibility_headers_add_test( $tests );
		$this->assertArrayHasKey( 'perflab_bfcache_compatibility_headers', $tests['direct'] );
		$this->assertEquals( 'Cache-Control headers may prevent fast back/forward navigation', $tests['direct']['perflab_bfcache_compatibility_headers']['label'] );
		$this->assertEquals( 'perflab_bfcache_compatibility_headers_check', $tests['direct']['perflab_bfcache_compatibility_headers']['test'] );
	}

	/**
	 * Test that the bfcache compatibility test is attached to the site status tests.
	 *
	 * @covers ::perflab_bfcache_compatibility_headers_add_test
	 */
	public function test_perflab_bfcache_compatibility_headers_add_test_is_attached(): void {
		$this->assertNotFalse( has_filter( 'site_status_tests', 'perflab_bfcache_compatibility_headers_add_test' ) );
	}

	/**
	 * Test that different Cache-Control headers return the correct bfcache compatibility result.
	 *
	 * @dataProvider data_test_bfcache_compatibility
	 * @covers ::perflab_bfcache_compatibility_headers_check
	 *
	 * @param array<int, mixed>|WP_Error $response The response headers.
	 * @param string                     $expected_status   The expected status.
	 * @param string                     $expected_message  The expected message.
	 */
	public function test_perflab_bfcache_compatibility_headers_check( $response, string $expected_status, string $expected_message ): void {
		$this->mocked_responses = array( home_url( '/' ) => $response );

		$result = perflab_bfcache_compatibility_headers_check();

		$this->assertEquals( $expected_status, $result['status'] );
		$this->assertStringContainsString( $expected_message, $result['description'] );
	}

	/**
	 * Data provider for bfcache compatibility tests.
	 *
	 * @return array<string, array<int, mixed>> Test data.
	 */
	public function data_test_bfcache_compatibility(): array {
		return array(
			'headers_not_set'    => array(
				$this->build_response( 200, array( 'cache-control' => '' ) ),
				'good',
				'If the Cache-Control response header includes directives like no-store, no-cache, or max-age=0 then it can prevent instant back/forward navigations (using the browser bfcache). Your site is configured properly.',
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
				'The request to check the Cache-Control response header responded with error code',
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
