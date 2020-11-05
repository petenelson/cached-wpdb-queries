<?php
/**
 * Plugin Name: Cached WPDB Queries
 * Description: Uses the options table for caching specific core wpdb queries.
 * Version:     1.0.1
 * Author:      Pete Nelson
 * Author URI:  https://github.com/petenelson
 * Plugin URI:  https://github.com/petenelson/cached-wpdb-queries
 * License:     GPLv2 or later
 *
 * @package WBDPCache
 */

if ( ! defined( 'CACHED_WPDB_QUERIES_VERSION' ) ) {
	define( 'CACHED_WPDB_QUERIES_VERSION', '1.0.0' );
}

if ( ! defined( 'CACHED_WPDB_QUERIES_INC' ) ) {
	define( 'CACHED_WPDB_QUERIES_INC', trailingslashit( dirname( __FILE__ ) ) . 'include/' );
}

include_once CACHED_WPDB_QUERIES_INC . 'core.php';
include_once CACHED_WPDB_QUERIES_INC . 'queries/get-available-post-mime-types.php';
include_once CACHED_WPDB_QUERIES_INC . 'queries/get-available-post-mime-types.php';
include_once CACHED_WPDB_QUERIES_INC . 'queries/wp-list-table-months-dropdown.php';
include_once CACHED_WPDB_QUERIES_INC . 'queries/wp-ajax-query-attachments.php';

// Start up the plugin.
WBDPCache\Core\setup();
WBDPCache\GetAvailablePostMimeTypes\setup();
WBDPCache\WPListTableMonthsDropdown\setup();
WBDPCache\WPAJAXQueryAttachments\setup();
