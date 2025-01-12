<?php
return static function ( Test_OD_Optimization $test_case ): void {
	$elements = array();
	for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS; $i++ ) {
		$elements[] = array(
			'xpath' => sprintf( '/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::IMG]', $i ),
			'isLCP' => false,
		);
	}
	$test_case->populate_url_metrics( $elements, false );
};
