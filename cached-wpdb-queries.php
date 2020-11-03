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

if ( ! defined( 'CACHED_WPDB_QUERIES' ) ) {
	define( 'CACHED_WPDB_QUERIES', trailingslashit( dirname( __FILE__ ) ) );
}

include_once CACHED_WPDB_QUERIES . 'get-available-post-mime-types.php';

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
	// See get-available-post-mime-types.php for an example.
	return apply_filters( 'cached_wpdb_queries_list', [] );
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

		if ( isset( $query_data['original'] ) && $query_data['original'] === $query ) {

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
	if ( ! isset( $query_data['source_callback'] ) || ! isset( $query_data['create_records_callback'] ) ) {
		return apply_filters( 'cached_wpdb_queries_use_updated_query', false, $query_data );
	}

	if ( ! is_callable( $query_data['source_callback'] ) || ! is_callable( $query_data['create_records_callback'] ) ) {
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

		// Pass the source data to whatever will build the new records.
		$success = call_user_func( $query_data['create_records_callback'], $source_data, $query_data );

		if ( $success ) {
			delete_option( $option_key );

			$option_data = [
				'created' => time(),
				'expires' => time() + $query_data['expires'],
			];

			// Store when this was cached.
			add_option( $option_key, $option_data, '', 'no' );

			$use_updated_query = true;
		}
	}

	$use_updated_query = apply_filters( 'cached_wpdb_queries_use_updated_query', $use_updated_query, $query_data );

	return $use_updated_query;
}
