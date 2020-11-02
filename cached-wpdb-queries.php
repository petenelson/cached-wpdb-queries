<?php
/**
 * Plugin Name: Cached WPDB Queries
 * Description: Uses the options table for caching specific core wpdb queries.
 * Version:     1.0.0
 * Author:      Pete Nelson
 * Author URI:  https://github.com/petenelson
 * License:     GPLv2 or later
 *
 * @package WBDPCache
 */

namespace WBDPCache;

/**
 * Quickly provide a namespaced way to get functions.
 *
 * @param string $function Name of function in namespace.
 *
 * @return string
 */
function n( string $function ): string {
	return __NAMESPACE__ . "\\$function";
}

/**
 * WordPress hooks and filters.
 *
 * @return void
 */
function setup() {
	add_filter( 'query', n( 'update_query' ) );
}
add_action( 'init', n( 'setup' ) );

/**
 * Gets the option key prefix for caching queries.
 *
 * @return string
 */
function get_option_prefix() {
	return apply_filters( 'cached_wpdb_queries_option_prefix', 'wbdb_cached_query_' );
}

/**
 * Gets a list of MySQL queries that should be cached.
 *
 * @return array
 */
function get_cached_queries() {
	global $wpdb;

	$queries = [];

	$queries[] = [
		// The original wpdb query that should be cached.
		'original'             => "SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = 'attachment'",

		// Where to get this data from if the query isn't cached.
		'source_callback'      => '\get_available_post_mime_types',

		// How long the query results should be cached for, in seconds.
		'expires'              => DAY_IN_SECONDS * 1,

		// The callback that creates the option record for each result from
		// the source. Remember that each option name needs to be unique and
		// is limited to 191 records.
		'option_record_callback' => __NAMESPACE__ . '\create_cached_mime_types',

		// The new query that runs against the option table. These options should
		// be created within the option_record_callback function.
		'updated'              => "SELECT DISTINCT option_value FROM $wpdb->options WHERE option_name like 'cached_mime_type_%'",
	];

	return apply_filters( 'cached_wpdb_queries_list', $queries );
}

/**
 * Updates the main wpdb SQL query with an options query to produce the same
 * results.
 *
 * @param  string $query The main SQL query command.
 * @return string
 */
function update_query( $query ) {

	$queries = get_cached_queries();

	foreach ( $queries as $query_data ) {

		if ( $query_data['original'] === $query ) {

			remove_filter( 'query', n( 'update_query' ) );

			// If there are cached results built and usable, then we can use
			// the query against the option table.
			$use_updated_query = maybe_update_cached_results( $query_data );

			if ( $use_updated_query ) {
				return $query_data['updated'];
			}
		}
	}

	return $query;
}

/**
 * Checks the options table for this cached query and rebuilds it if necessary.
 *
 * @param  array $query_data The cached query data.
 * @return bool True if we can use the updated query.
 */
function maybe_update_cached_results( $query_data ) {

	// Make sure we can actually run the callbacks.
	if ( ! is_callable( $query_data['source_callback'] ) || ! is_callable( $query_data['option_record_callback'] ) ) {
		return apply_filters( 'cached_wpdb_queries_use_updated_query', false, $query_data );
	}

	$rebuild           = false;
	$use_updated_query = false;

	$option_key = get_option_prefix() . md5( $query_data['original'] );
	$option     = get_option( $option_key );

	if ( false === $option || ! is_array( $option ) ) {
		$rebuild = true;
	} else { // phpcs:ignore

		// If the option exists and we don't need to rebuild it, we
		// can use the updated query.
		// See if it has expired.

		$option = wp_parse_args(
			$option,
			[
				'expires' => 0,
			]
		);

		if ( time() > $option['expires'] ) {
			// This has expired.
			$rebuild = true;
		} else {
			// This has not expired yet.
			$rebuild = false;
		}
	}

	$rebuild = apply_filters( 'cached_wpdb_queries_should_rebuild', $rebuild, $query_data, $option );

	if ( $rebuild ) {

		// Get the source data.
		$source_data = call_user_func( $query_data['source_callback'] );

		// Pass it to whatever will build the options records.
		call_user_func( $query_data['option_record_callback'], $source_data, $query_data );

		delete_option( $option_key );

		$option_data = [
			'created' => time(),
			'expires' => time() + $query_data['expires'],
		];

		// Store when this was cached.
		add_option( $option_key, $option_data, '', 'no' );

		$use_updated_query = true;
	}

	$use_updated_query = apply_filters( 'cached_wpdb_queries_use_updated_query', $use_updated_query, $query_data );

	return $use_updated_query;
}

/**
 * Creates an option record for cached MIME types.
 *
 * @param  mixed $source_data The source data.
 * @param  array $query_data  The cachable query data.
 * @return void
 */
function create_cached_mime_types( $source_data, $query_data ) {
	global $wpdb;

	// Delete any existing options.
	$option_names = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name like 'cached_mime_type_%'" ); // phpcs:ignore

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	// Now create the unique options.
	foreach ( $source_data as $mime_type ) {
		add_option( 'cached_mime_type_' . $mime_type, $mime_type, '', 'no' );
	}
}
