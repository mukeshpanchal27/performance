<?php
/**
 * Tests for Optimization Detective REST API site health check.
 *
 * @package optimization-detective
 */

class Test_OD_REST_API_Site_Health_Check extends WP_UnitTestCase {

	const EXPECTED_MOCKED_RESPONSE_ARGS = array(
		400,
		'Bad Request',
		array(
			'data' => array(
				'params' => array( 'slug', 'current_etag', 'hmac', 'url', 'viewport', 'elements' ),
			),
		),
	);

	const UNAUTHORISED_MOCKED_RESPONSE_ARGS = array(
		401,
		'Unauthorized',
	);

	const FORBIDDEN_MOCKED_RESPONSE_ARGS = array(
		403,
		'Forbidden',
	);

	/**
	 * @covers ::od_add_rest_api_availability_test
	 */
	public function test_od_add_rest_api_availability_test(): void {
		$initial_tests = array(
			'direct' => array(
				'foo' => array(
					'label' => 'Foo',
					'test'  => 'foo_test',
				),
			),
		);

		$tests = od_add_rest_api_availability_test(
			$initial_tests
		);
		$this->assertCount( 2, $tests['direct'] );
		$this->assertArrayHasKey( 'foo', $tests['direct'] );
		$this->assertSame( $initial_tests['direct']['foo'], $tests['direct']['foo'] );
		$this->assertArrayHasKey( 'optimization_detective_rest_api', $tests['direct'] );
		$this->assertArrayHasKey( 'label', $tests['direct']['optimization_detective_rest_api'] );
		$this->assertArrayHasKey( 'test', $tests['direct']['optimization_detective_rest_api'] );

		$tests = od_add_rest_api_availability_test(
			new WP_Error()
		);
		$this->assertCount( 1, $tests['direct'] );
		$this->assertArrayHasKey( 'optimization_detective_rest_api', $tests['direct'] );
	}

	/**
	 * Test that we presume the REST API is accessible before we are able to perform the Site Health check.
	 *
	 * @covers ::od_is_rest_api_unavailable
	 */
	public function test_rest_api_assumed_accessible(): void {
		$this->assertFalse( get_option( 'od_rest_api_unavailable', false ) );
		$this->assertFalse( od_is_rest_api_unavailable() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_rest_api_availability(): array {
		return array(
			'available'    => array(
				'mocked_response'      => $this->build_mock_response( ...self::EXPECTED_MOCKED_RESPONSE_ARGS ),
				'expected_option'      => '0',
				'expected_status'      => 'good',
				'expected_unavailable' => false,
			),
			'unauthorized' => array(
				'mocked_response'      => $this->build_mock_response( ...self::UNAUTHORISED_MOCKED_RESPONSE_ARGS ),
				'expected_option'      => '1',
				'expected_status'      => 'recommended',
				'expected_unavailable' => true,
			),
			'forbidden'    => array(
				'mocked_response'      => $this->build_mock_response( ...self::FORBIDDEN_MOCKED_RESPONSE_ARGS ),
				'expected_option'      => '1',
				'expected_status'      => 'recommended',
				'expected_unavailable' => true,
			),
		);
	}

	/**
	 * Test various conditions for the REST API being available.
	 *
	 * @covers ::od_test_rest_api_availability
	 * @covers ::od_compose_site_health_result
	 * @covers ::od_get_rest_api_health_check_response
	 * @covers ::od_is_rest_api_unavailable
	 *
	 * @dataProvider data_provider_test_rest_api_availability
	 *
	 * @phpstan-param array<string, mixed> $mocked_response
	 */
	public function test_rest_api_availability( array $mocked_response, string $expected_option, string $expected_status, bool $expected_unavailable ): void {
		$this->filter_rest_api_response( $mocked_response );

		$result = od_test_rest_api_availability();
		$this->assertSame( $expected_option, get_option( 'od_rest_api_unavailable', '' ) );
		$this->assertSame( $expected_status, $result['status'] );
		$this->assertSame( $expected_unavailable, od_is_rest_api_unavailable() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_in_plugin_row(): array {
		return array(
			'in_admin_notices' => array(
				'in_plugin_row' => false,
			),
			'in_plugin_row'    => array(
				'in_plugin_row' => true,
			),
		);
	}

	/**
	 * Initial state when there is no option set yet.
	 *
	 * @dataProvider data_provider_in_plugin_row
	 * @covers ::od_maybe_render_rest_api_health_check_admin_notice
	 */
	public function test_od_maybe_render_rest_api_health_check_admin_notice_no_option_set( bool $in_plugin_row ): void {
		$this->assertFalse( od_is_rest_api_unavailable() );
		$this->assertSame( '', get_echo( 'od_maybe_render_rest_api_health_check_admin_notice', array( $in_plugin_row ) ) );
	}

	/**
	 * When the REST API works as expected.
	 *
	 * @dataProvider data_provider_in_plugin_row
	 * @covers ::od_maybe_render_rest_api_health_check_admin_notice
	 */
	public function test_od_maybe_render_rest_api_health_check_admin_notice_rest_api_available( bool $in_plugin_row ): void {
		$this->filter_rest_api_response( $this->build_mock_response( ...self::EXPECTED_MOCKED_RESPONSE_ARGS ) );
		od_test_rest_api_availability();
		$this->assertFalse( od_is_rest_api_unavailable() );
		$this->assertSame( '', get_echo( 'od_maybe_render_rest_api_health_check_admin_notice', array( $in_plugin_row ) ) );
	}

	/**
	 * When the REST API is not available.
	 *
	 * @dataProvider data_provider_in_plugin_row
	 * @covers ::od_maybe_render_rest_api_health_check_admin_notice
	 */
	public function test_od_maybe_render_rest_api_health_check_admin_notice_rest_api_not_available( bool $in_plugin_row ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id ); // Since Site Health is only available to super admins.
		wp_set_current_user( $user_id );

		$this->filter_rest_api_response( $this->build_mock_response( ...self::UNAUTHORISED_MOCKED_RESPONSE_ARGS ) );
		od_test_rest_api_availability();
		$this->assertTrue( od_is_rest_api_unavailable() );
		$notice = get_echo( 'od_maybe_render_rest_api_health_check_admin_notice', array( $in_plugin_row ) );
		$this->assertStringContainsString( '<div class="notice notice-warning', $notice );
		if ( $in_plugin_row ) {
			$this->assertStringContainsString( '<details>', $notice );
			$this->assertStringContainsString( '</summary>', $notice );
			$this->assertStringNotContainsString( '<p><strong>', $notice );
		} else {
			$this->assertStringNotContainsString( '<details>', $notice );
			$this->assertStringNotContainsString( '</summary>', $notice );
			$this->assertStringContainsString( '<p><strong>', $notice );
		}
		$this->assertTrue( current_user_can( 'view_site_health_checks' ) );
		$this->assertStringContainsString( 'site-health.php', $notice );

		// And also when the user doesn't have access to Site Health.
		add_filter(
			'user_has_cap',
			static function ( array $all_caps ): array {
				$all_caps['view_site_health_checks'] = false;
				return $all_caps;
			}
		);
		$this->assertFalse( current_user_can( 'view_site_health_checks' ) );
		$notice = get_echo( 'od_maybe_render_rest_api_health_check_admin_notice', array( $in_plugin_row ) );
		$this->assertStringNotContainsString( 'site-health.php', $notice );
	}

	/**
	 * When the REST API is available.
	 *
	 * @covers ::od_render_rest_api_health_check_admin_notice_in_plugin_row
	 * @covers ::od_maybe_render_rest_api_health_check_admin_notice
	 */
	public function test_od_render_rest_api_health_check_admin_notice_in_plugin_row_rest_api_yes_available(): void {
		$this->filter_rest_api_response( $this->build_mock_response( ...self::EXPECTED_MOCKED_RESPONSE_ARGS ) );
		od_test_rest_api_availability();
		$this->assertFalse( od_is_rest_api_unavailable() );
		$this->assertSame( '', get_echo( 'od_render_rest_api_health_check_admin_notice_in_plugin_row', array( 'foo.php' ) ) );
		$this->assertSame( '', get_echo( 'od_render_rest_api_health_check_admin_notice_in_plugin_row', array( 'optimization-detective/load.php' ) ) );
	}

	/**
	 * When the REST API is not available.
	 *
	 * @covers ::od_render_rest_api_health_check_admin_notice_in_plugin_row
	 * @covers ::od_maybe_render_rest_api_health_check_admin_notice
	 */
	public function test_od_render_rest_api_health_check_admin_notice_in_plugin_row_rest_api_not_available(): void {
		$this->filter_rest_api_response( $this->build_mock_response( ...self::UNAUTHORISED_MOCKED_RESPONSE_ARGS ) );
		od_test_rest_api_availability();
		$this->assertTrue( od_is_rest_api_unavailable() );
		$this->assertSame( '', get_echo( 'od_render_rest_api_health_check_admin_notice_in_plugin_row', array( 'foo.php' ) ) );
		$notice = get_echo( 'od_render_rest_api_health_check_admin_notice_in_plugin_row', array( 'optimization-detective/load.php' ) );
		$this->assertStringContainsString( '<div class="notice notice-warning', $notice );
		$this->assertStringContainsString( '<details>', $notice );
		$this->assertStringContainsString( '</summary>', $notice );
		$this->assertStringNotContainsString( '<p><strong>', $notice );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_od_maybe_run_rest_api_health_check(): array {
		return array(
			'option_absent_and_rest_api_available' => array(
				'set_up'   => function (): void {
					delete_option( 'od_rest_api_unavailable' );
					$this->filter_rest_api_response( $this->build_mock_response( ...self::EXPECTED_MOCKED_RESPONSE_ARGS ) );
				},
				'expected' => false,
			),
			'option_present_and_cached_available'  => array(
				'set_up'   => static function (): void {
					update_option( 'od_rest_api_unavailable', '0' );
				},
				'expected' => false,
			),
			'rest_api_unavailable'                 => array(
				'set_up'   => function (): void {
					delete_option( 'od_rest_api_unavailable' );
					$this->filter_rest_api_response( $this->build_mock_response( ...self::UNAUTHORISED_MOCKED_RESPONSE_ARGS ) );
				},
				'expected' => true,
			),
		);
	}

	/**
	 * @dataProvider data_provider_test_od_maybe_run_rest_api_health_check
	 *
	 * @covers ::od_maybe_run_rest_api_health_check
	 */
	public function test_od_maybe_run_rest_api_health( Closure $set_up, bool $expected ): void {
		remove_all_actions( 'admin_notices' );
		$set_up();
		od_maybe_run_rest_api_health_check();
		$this->assertSame( $expected, (bool) has_action( 'admin_notices' ) );
	}

	/**
	 * Filters REST API response with mock.
	 *
	 * @param array<string, mixed> $mocked_response Mocked response.
	 */
	protected function filter_rest_api_response( array $mocked_response ): void {
		remove_all_filters( 'pre_http_request' );
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
