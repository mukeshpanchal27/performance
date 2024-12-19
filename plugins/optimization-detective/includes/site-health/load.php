<?php
/**
 * Site Health checks loader.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// REST API site health check.
require_once __DIR__ . '/rest-api/helper.php';
require_once __DIR__ . '/rest-api/hooks.php';
