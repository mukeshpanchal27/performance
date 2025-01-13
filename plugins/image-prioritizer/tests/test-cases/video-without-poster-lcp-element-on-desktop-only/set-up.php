<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$breakpoint_max_widths = array( 480, 600, 782 );

	add_filter(
		'od_breakpoint_max_widths',
		static function () use ( $breakpoint_max_widths ) {
			return $breakpoint_max_widths;
		}
	);

	foreach ( $breakpoint_max_widths as $non_desktop_viewport_width ) {
		$elements = array(
			array(
				'isLCP' => true,
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[1][self::IMG]',
			),
			array(
				'isLCP' => false,
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[2][self::VIDEO]',
			),
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => $non_desktop_viewport_width,
					'elements'       => $elements,
				)
			)
		);
	}

	OD_URL_Metrics_Post_Type::store_url_metric(
		od_get_url_metrics_slug( od_get_normalized_query_vars() ),
		$test_case->get_sample_url_metric(
			array(
				'viewport_width' => 1000,
				'elements'       => array(
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[1][self::IMG]',
					),
				),
			)
		)
	);
};
