<?php
/**
 * Cached queries for get_available_post_mime_types().
 *
 * @package WBDPCache;
 */

namespace WBDPCache\GetAvailablePostMimeTypes;

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
	add_filter( 'cached_wpdb_queries_list', n( 'add_mime_type_query_data' ) );
	add_filter( 'cached_wpdb_queries_should_rebuild', n( 'maybe_rebuild_cache' ) );
}

/**
 * Adds the MIME type query data.
 *
 * @param array $query_data List of query data.
 * @return array
 */
function add_mime_type_query_data( $query_data ) {
	global $wpdb;

	if ( apply_filters( 'cache_get_available_post_mime_types', true ) ) {

		$query_data[] = [
			// The original wpdb query that should be cached. This is currently
			// hard-coded in get_available_post_mime_types() and cannot be
			// overriden.
			'original'                => "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = 'attachment'",

			// Where to get this data from if the query isn't cached.
			'source_callback'         => '\get_available_post_mime_types',

			// How long the query results should be cached for, in seconds.
			'expires'                 => DAY_IN_SECONDS * 1,

			// The callback that creates a database record for each result
			// from the source.
			'create_records_callback' => n( 'create_cached_mime_types' ),

			// The new query that runs against the database.
			'updated'                 => "SELECT DISTINCT option_value FROM $wpdb->options WHERE option_name like 'cached_mime_type_%'",

			// The callback that flushes all cached query results
			'flush_callback'          => n( 'flush_mime_types' ),
		];
	}

	return $query_data;
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
				'rebuild_mime_type_cache' => FILTER_SANITIZE_STRING,
			]
		);

		$rebuild = '1' === $get['rebuild_mime_type_cache'];
	}

	return $rebuild;
}

/**
 * Creates options record (cached_mime_type_0, cached_mime_type_1, etc).
 *
 * @param  mixed $source_data The source data.
 * @param  array $query_data  The cachable query data.
 * @return bool
 */
function create_cached_mime_types( $source_data, $query_data ) {

	flush_mime_types();

	$i = 0;

	// Now create the unique options.
	foreach ( $source_data as $mime_type ) {
		add_option( 'cached_mime_type_' . $i, $mime_type, '', 'no' );
		$i++;
	}

	return true;
}

/**
 * Deletes all of the existing cached MIME type results.
 *
 * @return void
 */
function flush_mime_types() {
	global $wpdb;

	// Delete any existing options.
	$option_names = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name like 'cached_mime_type_%'" ); // phpcs:ignore

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}
