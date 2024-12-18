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
		// All extended properties must be optional so that URL Metrics are not all immediately invalidated once an extension is deactivated.
		'required'    => false,
		'items'       => array(
			'type'       => 'object',
			'required'   => true,
			'properties' => array(
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
	if ( ! current_user_can( 'customize' ) && ! wp_is_development_mode( 'plugin' ) ) {
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
	if ( ! od_can_optimize_response() ) {
		return;
	}

	$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$post = OD_URL_Metrics_Post_Type::get_post( $slug );

	global $wp_the_query;

	$tag_visitor_registry = new OD_Tag_Visitor_Registry();

	$current_etag     = od_get_current_url_metrics_etag( $tag_visitor_registry, $wp_the_query, od_get_current_theme_template() );
	$group_collection = new OD_URL_Metric_Group_Collection(
		$post instanceof WP_Post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		$current_etag,
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	$inp_dots = array();

	/**
	 * @var OD_URL_Metric_Group $group
	 */
	foreach ( $group_collection as $group ) {
		/**
		 * @var OD_URL_Metric $url_metric
		 */
		foreach ( $group as $url_metric ) {
			foreach ( $url_metric->get( 'inpData' ) as $inp_data ) {
				if ( isset( $inp_dots[ $inp_data['interactionTarget'] ] ) ) {
					$inp_dots[ $inp_data['interactionTarget'] ][] = $inp_data;
				} else {
					$inp_dots[ $inp_data['interactionTarget'] ] = array( $inp_data );
				}
			}
		}
	}

	?>
		<script>
			/* TODO: Add INP elements here */
			let count = 0;
			for ( const [ interactionTarget, entries ] of Object.entries( <?php echo wp_json_encode( $inp_dots ); ?> ) ) {
				const el = document.querySelector( interactionTarget );
				if ( ! el ) {
					continue;
				}

				count++;

				const anchor = document.createElement( 'button' );
				anchor.setAttribute( 'class', 'od-debug-dot' );
				anchor.setAttribute( 'popovertarget', `od-debug-popover-${count}` );
				anchor.setAttribute( 'popovertargetaction', 'toggle' );
				anchor.setAttribute( 'style', `--anchor-name: --od-debug-dot-${count}; position-anchor: --od-debug-element-${count};` );
				anchor.setAttribute( 'aria-details', `od-debug-popover-${count}` );
				anchor.textContent = 'Optimization Detective';

				const tooltip = document.createElement( 'div' );
				tooltip.setAttribute( 'id', `od-debug-popover-${count}` );
				tooltip.setAttribute( 'popover', '' );
				tooltip.setAttribute( 'class', 'od-debug-popover' );
				tooltip.setAttribute( 'style', `position-anchor: --od-debug-dot-${count};` );
				tooltip.textContent = `INP Element (Value: ${entries[0].value}) (Rating: ${entries[0].rating})`;

				document.body.append(anchor);
				document.body.append(tooltip);
			}
		</script>
		<style>
			body:not(.od-debug) .od-debug-dot,
			body:not(.od-debug) .od-debug-popover {
				/*display: none;*/
			}

			.od-debug-dot {
				height: 2em;
				width: 2em;
				background: rebeccapurple;
				border-radius: 50%;
				animation: pulse 2s infinite;
				position: absolute;
				position-area: center center;
				margin: 5px 0 0 5px;
			}

			.od-debug-popover {
				position: absolute;
				position-area: right;
				margin: 5px 0 0 5px;
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
