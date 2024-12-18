<?php
/**
 * Image Prioritizer: IP_Img_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor that optimizes IMG tags.
 *
 * @phpstan-import-type LinkAttributes from OD_Link_Collection
 *
 * @since n.e.x.t
 * @access private
 */
final class Optimization_Detective_Debug_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;

		if ( ! $context->url_metric_group_collection->is_any_group_populated() ) {
			return false;
		}

		$xpath = $processor->get_xpath();

		$visited = false;

		/**
		 * @var OD_URL_Metric_Group $group
		 */
		foreach ( $context->url_metric_group_collection as $group ) {
			// This is the LCP element for this group.
			if ( $group->get_lcp_element() instanceof OD_Element && $xpath === $group->get_lcp_element()->get_xpath() ) {
				$uuid = wp_generate_uuid4();

				$processor->set_meta_attribute(
					'viewport',
					$group->get_minimum_viewport_width()
				);

				$processor->set_attribute(
					'style',
					"--anchor-name: --od-debug-element-$uuid;" . $processor->get_attribute( 'style' ) ?? ''
				);

				$processor->set_meta_attribute(
					'debug-is-lcp',
					true
				);

				$anchor_text  = __( 'Optimization Detective', 'optimization-detective' );
				$popover_text = __( 'LCP Element', 'optimization-detective' );

				$processor->append_body_html(
					<<<HTML
<button
	class="od-debug-dot"
	type="button"
	popovertarget="od-debug-popover-$uuid"
	popovertargetaction="toggle"
	style="--anchor-name: --od-debug-dot-$uuid; position-anchor: --od-debug-element-$uuid;"
	aria-details="od-debug-popover-$uuid"
	>
	$anchor_text
</button>
<div
	id="od-debug-popover-$uuid"
	popover
	class="od-debug-popover"
	style="position-anchor: --od-debug-dot-$uuid;"
	>
	$popover_text
</div>
HTML
				);

				$visited = true;
			}
		}

		return $visited;
	}
}
