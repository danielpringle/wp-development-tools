<?php
/**
 * Performance Slow Queries Command
 *
 * Analyzes slow database queries for performance profiling
 *
 * @package DPUKCLICommands
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Class for Custom CLI Command to analyze slow database queries
	 *
	 * @category Class
	 */
	class Perf_Slow_Queries {

		/**
		 * Command name
		 *
		 * @var string
		 */
		private $command_name = 'dpuk-perf-slow-queries';

		/**
		 * Whether SAVEQUERIES was auto-enabled
		 *
		 * @var bool
		 */
		private $enabled_savequeries = false;

		/**
		 * Constructor - registers the command
		 */
		public function __construct() {
			WP_CLI::add_command(
				$this->command_name,
				$this,
				[
					'shortdesc' => 'Analyze slow database queries for performance profiling',
				]
			);
		}

		/**
		 * Analyzes slow database queries
		 *
		 * ## OPTIONS
		 *
		 * [--threshold=<seconds>]
		 * : Minimum query time in seconds to include. Default: 0.05
		 *
		 * [--limit=<number>]
		 * : Maximum number of results to display. Default: 20
		 *
		 * [--format=<format>]
		 * : Output format (table, json, csv, yaml). Default: table
		 *
		 * [--group]
		 * : Group identical queries together
		 *
		 * [--stack]
		 * : Show call stack for each query
		 *
		 * [--request=<type>]
		 * : Filter by request type (admin, cron, frontend)
		 *
		 * [--auto-savequeries]
		 * : Automatically enable SAVEQUERIES if not already enabled
		 *
		 * ## EXAMPLES
		 *
		 * wp dpuk-perf-slow-queries
		 * wp dpuk-perf-slow-queries --threshold=0.1 --limit=10
		 * wp dpuk-perf-slow-queries --group --stack
		 * wp dpuk-perf-slow-queries --request=admin
		 * wp dpuk-perf-slow-queries --auto-savequeries
		 *
		 * @when after_wp_load
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( $args, $assoc_args ) {
			global $wpdb;

			if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
				if ( isset( $assoc_args['auto-savequeries'] ) ) {
					define( 'SAVEQUERIES', true );
					$this->enabled_savequeries = true;
					WP_CLI::log( 'SAVEQUERIES auto-enabled.' );
				} else {
					WP_CLI::error( 'SAVEQUERIES is disabled. Use --auto-savequeries.' );
				}
			}

			if ( empty( $wpdb->queries ) ) {
				WP_CLI::warning( 'No queries recorded yet. Trigger a page load first.' );
				return;
			}

			$threshold = (float) ( $assoc_args['threshold'] ?? 0.05 );
			$group     = isset( $assoc_args['group'] );
			$limit     = (int) ( $assoc_args['limit'] ?? 20 );
			$format    = $assoc_args['format'] ?? 'table';
			$stack     = isset( $assoc_args['stack'] );
			$request   = $assoc_args['request'] ?? null;

			$results = [];

			foreach ( $wpdb->queries as $q ) {
				[ $sql, $time, $trace ] = $q;

				if ( $time < $threshold ) {
					continue;
				}

				if ( $request && ! $this->matches_request( $trace, $request ) ) {
					continue;
				}

				$source = $this->detect_source( $trace );
				$key    = $group ? md5( $sql ) : uniqid();

				if ( ! isset( $results[ $key ] ) ) {
					$results[ $key ] = [
						'time'   => 0,
						'calls'  => 0,
						'avg'    => 0,
						'source' => $source,
						'query'  => $this->normalize_sql( $sql ),
						'stack'  => $stack ? $this->short_stack( $trace ) : null,
					];
				}

				$results[ $key ]['time']  += $time;
				$results[ $key ]['calls'] += 1;
			}

			foreach ( $results as &$r ) {
				$r['avg']  = round( $r['time'] / $r['calls'], 4 );
				$r['time'] = round( $r['time'], 4 );
			}

			usort( $results, fn( $a, $b ) => $b['time'] <=> $a['time'] );
			$results = array_slice( $results, 0, $limit );

			if ( empty( $results ) ) {
				$message = sprintf(
					'No queries found above threshold (%.2fs)',
					$threshold
				);
				if ( $request ) {
					$message .= sprintf( ' for request type: %s', $request );
				}
				$message .= '. Try lowering --threshold or ensure queries have been executed.';
				WP_CLI::warning( $message );
				return;
			}

			$fields = [ 'time', 'calls', 'avg', 'source', 'query' ];
			if ( $stack ) {
				$fields[] = 'stack';
			}

			WP_CLI\Utils\format_items( $format, $results, $fields );
		}

		/**
		 * Normalize SQL query for display
		 *
		 * @param string $sql SQL query string.
		 * @return string Normalized SQL query.
		 */
		private function normalize_sql( string $sql ): string {
			return mb_substr( preg_replace( '/\s+/', ' ', $sql ), 0, 180 ) . '…';
		}

		/**
		 * Detect the source of the query (plugin, theme, or core)
		 *
		 * @param string $trace Stack trace string.
		 * @return string Source identifier.
		 */
		private function detect_source( string $trace ): string {
			if ( preg_match( '#plugins/([^/]+)#', $trace, $m ) ) {
				return 'plugin:' . $m[1];
			}
			if ( preg_match( '#themes/([^/]+)#', $trace, $m ) ) {
				return 'theme:' . $m[1];
			}
			return 'core';
		}

		/**
		 * Check if trace matches request type filter
		 *
		 * @param string $trace Stack trace string.
		 * @param string $request Request type (admin, cron, frontend).
		 * @return bool Whether the trace matches the request type.
		 */
		private function matches_request( string $trace, string $request ): bool {
			return match ( $request ) {
				'admin'    => str_contains( $trace, 'wp-admin' ),
				'cron'     => str_contains( $trace, 'wp-cron.php' ),
				'frontend' => ! str_contains( $trace, 'wp-admin' ),
				default    => true,
			};
		}

		/**
		 * Get short stack trace
		 *
		 * @param string $trace Full stack trace.
		 * @return string Shortened stack trace.
		 */
		private function short_stack( string $trace ): string {
			$lines = array_slice( explode( ',', $trace ), 0, 3 );
			return implode( ' ← ', $lines );
		}
	}

	// Auto-register when class is loaded
	new Perf_Slow_Queries();

}
