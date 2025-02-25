<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	add_filter(
		'od_breakpoint_max_widths',
		static function () {
			return array( 480, 600, 782 );
		}
	);

	$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$sample_size = od_get_url_metrics_breakpoint_sample_size();

	$bg_images = array(
		'https://example.com/mobile.jpg',
		'https://example.com/tablet.jpg',
		'https://example.com/phablet.jpg',
		'https://example.com/desktop.jpg',
	);

	// Fully populate all viewport groups, but for all except desktop record that the LCP element had a different tag, id, or class.
	foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $i => $viewport_width ) {
		for ( $j = 0; $j < $sample_size; $j++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(),
						'extended_root'  => array(
							'lcpElementExternalBackgroundImage' => array(
								'url'   => $bg_images[ $i ],
								'tag'   => 0 === $i ? 'DIV' : 'HEADER',
								'id'    => 1 === $i ? 'foo' : 'masthead',
								'class' => 2 === $i ? 'bar' : 'banner',
							),
						),
					)
				)
			);
		}
	}
};
