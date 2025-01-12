<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$outside_viewport_rect = array_merge(
		$test_case->get_sample_dom_rect(),
		array(
			'top' => 100000,
		)
	);

	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
				'isLCP' => true,
			),
			array(
				'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::DIV]',
				'isLCP'              => false,
				'intersectionRatio'  => 0.0,
				'intersectionRect'   => $outside_viewport_rect,
				'boundingClientRect' => $outside_viewport_rect,
			),
			array(
				'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[4][self::DIV]',
				'isLCP'              => false,
				'intersectionRatio'  => 0.0,
				'intersectionRect'   => $outside_viewport_rect,
				'boundingClientRect' => $outside_viewport_rect,
			),
		)
	);
};
