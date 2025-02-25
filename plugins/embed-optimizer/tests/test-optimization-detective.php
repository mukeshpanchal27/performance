<?php
/**
 * Tests for embed-optimizer plugin hooks.php.
 *
 * @package embed-optimizer
 *
 * @noinspection PhpUnhandledExceptionInspection
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
	 * @return array<string, array{ directory: non-empty-string }> Test cases.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
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
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	public function test_od_optimize_template_output_buffer( string $directory ): void {
		$this->assert_snapshot_equals( $directory );
	}
}
