# Cached WordPress wpdb queries

Specific queries in WordPress core are executed directly against the database (such as `get_available_post_mime_types()`) and currently
do not have filters that allow bypassing the SQL query that is executed. On large installations with thousands of records in the wp_posts
table, some of these queries can run quite slow. This plugin allows for caching of the results of these queries to improve performance.

## How it works:

* Specific queries from core can be flagged for caching, such as: `SELECT DISTINCT post_mime_type FROM $wpdb->posts WHERE post_type = 'attachment'`
* Each original query is configured with:
	* `original` The original wpdb query from WordPress core.
	* `source_callback` that is used to get the data to be cached.
	* `expires` in seconds for how long the results should be cached.
	* `option_record_callback` used to create records in the option table. This function is passed the results from the source callback.
	* `updated` The new query that will be used instead against the options table.

See the `get_cached_queries()` function for an example. Basically it is taking the distint post_mime_type records for attachments, creating records
in the options table with specific prefixes, then running a wildcard starts-with query against the options table, rather than the wp_posts table,
to get the results.
