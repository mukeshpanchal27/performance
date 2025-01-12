<?php
/**
 * Tests for embed-optimizer plugin hooks.php.
 *
 * @package embed-optimizer
 *
 * @noinspection PhpUnhandledExceptionInspection
 *
 */

class Test_Embed_Optimizer_Optimization_Detective extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
			$this->markTestSkipped( 'Optimization Detective is not active.' );
		}

		// Normalize the data for computing the current URL Metrics ETag to work around the issue where there is no
		// global variable storing the OD_Tag_Visitor_Registry instance along with any registered tag visitors, so
		// during set up we do not know what the ETag will look like. The current ETag is only established when
		// the output begins to be processed by od_optimize_template_output_buffer().
		add_filter( 'od_current_url_metrics_etag_data', '__return_empty_array' );
	}

	/**
	 * Tests embed_optimizer_register_tag_visitors().
	 *
	 * @covers ::embed_optimizer_register_tag_visitors
	 */
	public function test_embed_optimizer_register_tag_visitors(): void {
		$registry = new OD_Tag_Visitor_Registry();
		embed_optimizer_register_tag_visitors( $registry );
		$this->assertTrue( $registry->is_registered( 'embeds' ) );
		$this->assertInstanceOf( Embed_Optimizer_Tag_Visitor::class, $registry->get_registered( 'embeds' ) );
	}


	/**
	 * Tests embed_optimizer_add_element_item_schema_properties().
	 *
	 * @covers ::embed_optimizer_add_element_item_schema_properties
	 */
	public function test_embed_optimizer_add_element_item_schema_properties(): void {
		$props = embed_optimizer_add_element_item_schema_properties( array( 'foo' => array() ) );
		$this->assertArrayHasKey( 'foo', $props );
		$this->assertArrayHasKey( 'resizedBoundingClientRect', $props );
		$this->assertArrayHasKey( 'properties', $props['resizedBoundingClientRect'] );
	}

	/**
	 * Tests embed_optimizer_filter_extension_module_urls().
	 *
	 * @covers ::embed_optimizer_filter_extension_module_urls
	 */
	public function test_embed_optimizer_filter_extension_module_urls(): void {
		$urls = embed_optimizer_filter_extension_module_urls( null );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString( 'detect', $urls[0] );

		$urls = embed_optimizer_filter_extension_module_urls( array( 'foo.js' ) );
		$this->assertCount( 2, $urls );
		$this->assertStringContainsString( 'foo.js', $urls[0] );
		$this->assertStringContainsString( 'detect', $urls[1] );
	}

	/**
	 * Tests embed_optimizer_filter_oembed_html_to_detect_embed_presence().
	 *
	 * @covers ::embed_optimizer_filter_oembed_html_to_detect_embed_presence
	 */
	public function test_embed_optimizer_filter_oembed_html_to_detect_embed_presence(): void {
		$this->assertFalse( has_filter( 'od_extension_module_urls', 'embed_optimizer_filter_extension_module_urls' ) );
		$this->assertSame( '...', embed_optimizer_filter_oembed_html_to_detect_embed_presence( '...' ) );
		$this->assertSame( 10, has_filter( 'od_extension_module_urls', 'embed_optimizer_filter_extension_module_urls' ) );
	}

	/**
	 * Data provider.
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 *
	 * @return array<string, array{ directory: non-empty-string }> Test cases.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		// TODO: Delete this commented-out code and the PHP files it would load.
//		$test_cases = array();
//		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
//			$name                = basename( $test_case, '.php' );
//			$test_cases[ $name ] = require $test_case;
//
//			$dir = dirname( $test_case ) . DIRECTORY_SEPARATOR . $name;
//			if ( ! file_exists( $dir ) ) {
//				mkdir( $dir );
//			}
//			file_put_contents( $dir . DIRECTORY_SEPARATOR . 'buffer.html', trim( preg_replace( "/^\t\t/m", "", $test_cases[ $name ]['buffer'] ) ) . PHP_EOL );
//			file_put_contents( $dir . DIRECTORY_SEPARATOR . 'expected.html', trim( preg_replace( "/^\t\t/m", "", $test_cases[ $name ]['expected'] ) ) . PHP_EOL );
//
//			$test_case_source = file_get_contents( $test_case );
//			if ( ! preg_match( "/'set_up'\s*=>\s+(.+?}),\s*'buffer'\s*=>/s", $test_case_source, $matches ) ) {
//				throw new Exception( "Pattern match faiulure for $test_case" );
//			}
//
//			$set_up_function_source = trim( $matches[1] );
//			$set_up_function_source = preg_replace( '/^\t/m', '', $set_up_function_source );
//
//			file_put_contents( $dir . DIRECTORY_SEPARATOR . 'set-up.php', "<?php\nreturn $set_up_function_source;\n" );
//		}

		return $this->load_snapshot_test_cases( __DIR__ . '/test-cases' );
	}

	/**
	 * Test embed_optimizer_visit_tag().
	 *
	 * @covers       Embed_Optimizer_Tag_Visitor
	 * @covers ::embed_optimizer_update_markup
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 *
	 * @param non-empty-string $directory Test case directory.
	 */
	public function test_od_optimize_template_output_buffer( string $directory ): void {
		$this->assert_snapshot_equals( $directory );
	}
}
