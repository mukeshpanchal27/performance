<?php
/**
 * Helper functions for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initializes extensions for Optimization Detective.
 *
 * @since 0.7.0
 */
function od_initialize_extensions(): void {
	/**
	 * Fires when extensions to Optimization Detective can be loaded and initialized.
	 *
	 * @since 0.7.0
	 *
	 * @param string $version Optimization Detective version.
	 */
	do_action( 'od_init', OPTIMIZATION_DETECTIVE_VERSION );
}

/**
 * Generates a media query for the provided minimum and maximum viewport widths.
 *
 * @since 0.7.0
 *
 * @param int|null $minimum_viewport_width Minimum viewport width.
 * @param int|null $maximum_viewport_width Maximum viewport width.
 * @return non-empty-string|null Media query, or null if the min/max were both unspecified or invalid.
 */
function od_generate_media_query( ?int $minimum_viewport_width, ?int $maximum_viewport_width ): ?string {
	if ( is_int( $minimum_viewport_width ) && is_int( $maximum_viewport_width ) && $minimum_viewport_width > $maximum_viewport_width ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The minimum width cannot be greater than the maximum width.', 'optimization-detective' ), 'Optimization Detective 0.7.0' );
		return null;
	}
	$media_attributes = array();
	if ( null !== $minimum_viewport_width && $minimum_viewport_width > 0 ) {
		$media_attributes[] = sprintf( '(min-width: %dpx)', $minimum_viewport_width );
	}
	if ( null !== $maximum_viewport_width && PHP_INT_MAX !== $maximum_viewport_width ) {
		$media_attributes[] = sprintf( '(max-width: %dpx)', $maximum_viewport_width );
	}
	if ( count( $media_attributes ) === 0 ) {
		return null;
	}
	return join( ' and ', $media_attributes );
}

/**
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="optimization-detective ' . esc_attr( OPTIMIZATION_DETECTIVE_VERSION ) . '">' . "\n";
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.9.0
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function od_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
		$force_src = true;
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'optimization-detective' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $src_path;
	}

	return $min_path;
}

/**
 * Get the group collection for the current request.
 *
 * @since n.e.x.t
 * @access private
 *
 * @global WP_Query $wp_the_query WP_Query object.
 *
 * @param OD_Tag_Visitor_Registry $tag_visitor_registry Tag visitor registry.
 * @return OD_URL_Metric_Group_Collection Group collection instance.
 */
function od_get_group_collection( OD_Tag_Visitor_Registry $tag_visitor_registry ): OD_URL_Metric_Group_Collection {
	global $wp_the_query;

	$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$post = OD_URL_Metrics_Post_Type::get_post( $slug );

	$current_etag = od_get_current_url_metrics_etag( $tag_visitor_registry, $wp_the_query, od_get_current_theme_template() );

	return new OD_URL_Metric_Group_Collection(
		$post instanceof WP_Post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		$current_etag,
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);
}
