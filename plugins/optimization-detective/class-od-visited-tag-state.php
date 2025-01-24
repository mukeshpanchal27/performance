<?php
/**
 * Optimization Detective: OD_Visited_Tag_State class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * State for a tag visitation when visited by tag visitors while walking over a document.
 *
 * @since n.e.x.t
 * @access private
 */
final class OD_Visited_Tag_State {

	/**
	 * Whether the tag should be tracked among the elements in URL Metrics.
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $should_track_tag;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Marks the tag for being tracked in URL Metrics.
	 *
	 * @since n.e.x.t
	 */
	public function track_tag(): void {
		$this->should_track_tag = true;
	}

	/**
	 * Whether the tag should be tracked among the elements in URL Metrics.
	 *
	 * @since n.e.x.t
	 * @return bool Whether tracked.
	 */
	public function is_tag_tracked(): bool {
		return $this->should_track_tag;
	}

	/**
	 * Resets state.
	 *
	 * This should be called after tag visitors have been invoked on a tag.
	 *
	 * @since n.e.x.t
	 */
	public function reset(): void {
		$this->should_track_tag = false;
	}
}
