<?php
/**
 * Tests for optimization-detective plugin hooks.php.
 *
 * @package optimization-detective
 */

class Test_OD_Hooks extends WP_UnitTestCase {

	/**
	 * Make sure the hooks are added in hooks.php.
	 *
	 * @see Test_OD_Storage_Post_Type::test_add_hooks()
	 */
	public function test_hooks_added(): void {
		$this->assertEquals( PHP_INT_MAX, has_action( 'init', 'od_initialize_extensions' ) );
		$this->assertEquals( PHP_INT_MAX, has_filter( 'template_include', 'od_buffer_output' ) );

		$this->assertEquals( 10, has_filter( 'wp', 'od_maybe_add_template_output_buffer_filter' ) );
		$this->assertEquals( 10, has_action( 'wp_head', 'od_render_generator_meta_tag' ) );
		$this->assertEquals( 10, has_filter( 'site_status_tests', 'od_add_rest_api_availability_test' ) );
		$this->assertEquals( 10, has_action( 'admin_init', 'od_maybe_run_rest_api_health_check' ) );
		$this->assertEquals( 30, has_action( 'after_plugin_row_meta', 'od_render_rest_api_health_check_admin_notice_in_plugin_row' ) );
	}
}
