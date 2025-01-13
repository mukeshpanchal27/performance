<?php
/**
 * Debug helpers used for Optimization Detective.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers tag visitors.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function od_debug_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}

	$debug_visitor = new Optimization_Detective_Debug_Tag_Visitor();
	$registry->register( 'optimization-detective/debug', $debug_visitor );
}

add_action( 'od_register_tag_visitors', 'od_debug_register_tag_visitors', PHP_INT_MAX );

/**
 * Filters additional properties for the element item schema for Optimization Detective.
 *
 * @since n.e.x.t
 *
 * @param array<string, array{type: string}> $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function od_debug_add_inp_schema_properties( array $additional_properties ): array {
	$additional_properties['inpData'] = array(
		'description' => __( 'INP metrics', 'optimization-detective' ),
		'type'        => 'array',

		/*
		 * All extended properties must be optional so that URL Metrics are not all immediately invalidated once an extension is deactivated.
		 * Also, no INP data will be sent if the user never interacted with the page.
		 */
		'required'    => false,
		'items'       => array(
			'type'                 => 'object',
			'required'             => true,
			'properties'           => array(
				'value'             => array(
					'type'     => 'number',
					'required' => true,
				),
				'rating'            => array(
					'type'     => 'string',
					'enum'     => array( 'good', 'needs-improvement', 'poor' ),
					'required' => true,
				),
				'interactionTarget' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
			'additionalProperties' => true,
		),
	);
	return $additional_properties;
}

add_filter( 'od_url_metric_schema_root_additional_properties', 'od_debug_add_inp_schema_properties' );

/**
 * Adds a new admin bar menu item for Optimization Detective debug mode.
 *
 * @since n.e.x.t
 *
 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by reference.
 */
function od_debug_add_admin_bar_menu_item( WP_Admin_Bar &$wp_admin_bar ): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	$wp_admin_bar->add_menu(
		array(
			'id'     => 'optimization-detective-debug',
			'parent' => null,
			'group'  => null,
			'title'  => __( 'Optimization Detective', 'optimization-detective' ),
			'meta'   => array(
				'onclick' => 'document.body.classList.toggle("od-debug");',
			),
		)
	);
}

add_action( 'admin_bar_menu', 'od_debug_add_admin_bar_menu_item', 100 );

/**
 * Adds inline JS & CSS for debugging.
 */
function od_debug_add_assets(): void {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}
	?>
		<style>
			body:not(.od-debug) .od-debug-dot,
			body:not(.od-debug) .od-debug-popover {
				display: none;
			}

			.od-debug-dot {
				height: 2em;
				width: 2em;
				background: rebeccapurple;
				border: 0;
				border-radius: 50%;
				animation: pulse 2s infinite;
				position: absolute;
				position-area: center center;
				margin: 5px 0 0 5px;
			}

			.od-debug-popover {
				position: absolute;
				position-area: top;
				bottom: anchor-size(height);
				margin: 0;
				padding: .25em .5em;
				border: none;
			}

			@keyframes pulse {
				0% {
					transform: scale(0.8);
					opacity: 0.5;
					box-shadow: 0 0 0 0 rgba(102, 51, 153, 0.7);
				}
				70% {
					transform: scale(1);
					opacity: 1;
					box-shadow: 0 0 0 10px rgba(102, 51, 153, 0);
				}
				100% {
					transform: scale(0.8);
					opacity: 0.5;
					box-shadow: 0 0 0 0 rgba(102, 51, 153, 0);
				}
			}
		</style>
		<?php
}

add_action( 'wp_footer', 'od_debug_add_assets' );
