<?php
return static function ( Test_OD_Optimization $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]',
				'isLCP' => true,
			),
		),
		false
	);
};
