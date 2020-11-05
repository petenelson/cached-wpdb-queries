<?php
/**
 * Cached queries for WP_List_Table->months_dropdown() attachments.
 *
 * @package WBDPCache;
 */

namespace WBDPCache\WPAJAXQueryAttachments;

/**
 * Quickly provide a namespaced way to get functions.
 *
 * @param string $function Name of function in namespace.
 *
 * @return string
 */
function n( $function ) {
	return __NAMESPACE__ . "\\$function";
}

/**
 * WordPress hooks and filters.
 *
 * @return void
 */
function setup() {
	add_filter( 'cached_wpdb_queries_list', n( 'add_months_dropdown_query_data' ) );
	add_filter( 'cached_wpdb_queries_should_rebuild', n( 'maybe_rebuild_cache' ) );
}

/**
 * Gets the original query that wpdb would run.
 *
 * @return string
 */
function get_original_query() {
	global $wpdb;

	// See protected function months_dropdown() in class-wp-list-table.php.
	// This is what the media library runs by default.
	$query = "
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type = 'attachment'
			AND post_status != 'auto-draft' AND post_status != 'trash'
			ORDER BY post_date DESC
		";

	return $query;
}

/**
 * Adds the MIME type query data.
 *
 * @param array $query_data List of query data.
 * @return array
 */
function add_months_dropdown_query_data( $query_data ) {
	global $wpdb;

	return $query_data;

	if ( apply_filters( 'cache_attachment_months_dropdown', true ) ) {

		$query_data[] = [
			// The original wpdb query that should be cached. This is currently
			// hard-coded in get_available_post_mime_types() and cannot be
			// overriden.
			'original'                => get_original_query(),

			// Where to get this data from if the query isn't cached.
			'source_callback'         => n( 'get_months_dropdown_results' ),

			// How long the query results should be cached for, in seconds.
			'expires'                 => HOUR_IN_SECONDS * 12,

			// The callback that creates a database record for each result
			// from the source.
			'create_records_callback' => n( 'create_cached_months' ),

			// The new query that runs against the database.
			'updated'                 => "SELECT SUBSTRING_INDEX(option_value, '_', 1) as year, SUBSTRING_INDEX(option_value, '_', -1) as month FROM $wpdb->options WHERE option_name like 'cached_attachments_month_dropdown_%' ORDER BY option_name",

			// The callback that flushes all cached query results
			'flush_callback'          => n( 'flush_cached_months' ),
		];
	}

	return $query_data;
}

/**
 * Runs the original query to get data to be cached.
 *
 * @return array Objects with year and month.
 */
function get_months_dropdown_results() {
	global $wpdb;
	return $wpdb->get_results( get_original_query() ); // phpcs:ignore
}

/**
 * Check the querystring to allow rebuilding the cache.
 *
 * @param bool $rebuild Should we rebuild the cache?
 * @return bool
 */
function maybe_rebuild_cache( $rebuild ) {

	if ( ! $rebuild && is_admin() ) {

		$get = filter_var_array(
			$_GET, // phpcs:ignore
			[
				'rebuild_months_dropdown_cache' => FILTER_SANITIZE_STRING,
			]
		);

		$rebuild = '1' === $get['rebuild_months_dropdown_cache'];
	}

	return $rebuild;
}

/**
 * Creates options record (cached_attachments_month_dropdown_0, cached_attachments_month_dropdown_1, etc).
 *
 * @param  mixed $source_data The source data.
 * @param  array $query_data  The cachable query data.
 * @return bool
 */
function create_cached_months( $source_data, $query_data ) {

	flush_cached_months();

	$i = 0;

	// Now create the unique options.
	foreach ( $source_data as $year_month ) {

		$option_name  = 'cached_attachments_month_dropdown_' . str_pad( $i, 4, '0', STR_PAD_LEFT );
		$option_value = "{$year_month->year}_{$year_month->month}";

		add_option( $option_name, $option_value, '', 'no' );
		$i++;
	}

	return true;
}

/**
 * Deletes all of the existing cached MIME type results.
 *
 * @return void
 */
function flush_cached_months() {
	global $wpdb;

	// Delete any existing options.
	$option_names = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name like 'cached_attachments_month_dropdown_%'" ); // phpcs:ignore

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}
