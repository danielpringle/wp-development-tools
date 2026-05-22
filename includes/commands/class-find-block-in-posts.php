<?php
/**
 * WP-CLI Command: Find Block in Posts
 *
 * Provides a custom WP-CLI command to search the posts table for usage of a specific block within a date range.
 * Outputs the IDs of posts containing the specified block, with options to filter by block name and date range.
 *
 * Usage examples:
 *   wp dpuk-find-block --block=heading
 *   wp dpuk-find-block --block=my-plugin/my-custom-block
 *   wp dpuk-find-block --start-date=2023-01-01 --end-date=2023-12-31 --block=paragraph
 *
 * Note: The block name should match the string after <!-- wp: in your post content. For example, for <!-- wp:heading --> use --block=heading.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;


if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Class for Custom CLI Command to find a block in the posts table
	 *
	 * @category Class
	 */
	class Find_Block_In_Posts {

		/**
		 * Command name
		 *
		 * @var string
		 */
		private $command_name = 'dpuk-find-block';

		/**
		 * Constructor - registers the command
		 */
		public function __construct() {
			WP_CLI::add_command( $this->command_name, $this );
		}

		/**
		 * Finds in which posts a block is being used.
		 *
		 * ## OPTIONS
		 *
		 * [--block=<name>]
		 * : The block name as it appears after <!-- wp: in post content, e.g. 'heading' for <!-- wp:heading --> or 'my-plugin/my-custom-block' for <!-- wp:my-plugin/my-custom-block -->.
		 *
		 * [--start-date=<date>]
		 * : The query start date in Y-m-d format. Default is 30 days ago.
		 *
		 * [--end-date=<date>]
		 * : The query end date in Y-m-d format. Default is today.
		 *
		 *
		 * ## EXAMPLES
		 *
		 * wp dpuk-find-block
		 * wp dpuk-find-block --start-date=2023-10-10 --end-date=2025-07-1
		 * wp dpuk-find-block --block=heading
		 * wp dpuk-find-block --start-date=2023-10-10 --block=heading
		 * wp dpuk-find-block --start-date=2023-10-10 --end-date=2025-07-1 --block=heading
		 * wp dpuk-find-block --start-date=2023-10-10 --block=wp:heading | tee output.log
		 *
		 * wp help dpuk-find-block
		 *
		 * @when after_wp_load
		 *
		 * @param array $args stores all the positional arguments
		 * @param array $assoc_args stores all the flags
		 */
		public function __invoke( $args, $assoc_args ) {

			\WP_CLI::line( 'Scanning posts...' );

			// default start and end dates
			$start_date = '2000-01-01';
			$end_date   = gmdate( 'Y-m-d' );

			// default block name
			$block = 'dpuk/insert-post-link';

			// Check for block flag
			if ( isset( $assoc_args['block'] ) ) {
				$block = $assoc_args['block'];
			}

			// Check for start date flag
			if ( isset( $assoc_args['start-date'] ) ) {
				$start_date = $assoc_args['start-date'];
				if ( $start_date > $end_date ) {
					\WP_CLI::error( 'The start date cannot be after the end date' );
					return;
				}
			}

			// Check for end date flag
			if ( isset( $assoc_args['end-date'] ) ) {
				$end_date = gmdate( 'Y-m-d', strtotime( $assoc_args['end-date'] ) );
				if ( $end_date < $start_date ) {
					\WP_CLI::error( 'The end date cannot be before the start date' );
					return;
				}
			}

			// Output passed information
			\WP_CLI::line( 'Block: ' . $block );
			\WP_CLI::line( 'Start date: ' . $start_date );
			\WP_CLI::line( 'End date: ' . $end_date );

			// Run DB query
			$post_ids = $this->find_block( $block, $start_date, $end_date );

			// Output result
			if ( count( $post_ids ) > 0 ) {
				\WP_CLI::line( 'Post IDs:' );
				foreach ( $post_ids as $post ) {
					\WP_CLI::line( $post->ID );
				}
				\WP_CLI::success( sprintf( 'Block %s found in %u posts :) ', $block, count( $post_ids ) ) );
			} else {
				\WP_CLI::error( 'No results were found for the date range' );
			}
		}

		/**
		 * WPDB query to find a block in the posts table
		 *
		 * @param string $block the name of the block
		 * @param string $start_date the start date for the query
		 * @param string $end_date the end date for the query
		 * @return array results array of post IDs
		 */
		public function find_block( $block, $start_date, $end_date ) {
			global $wpdb;

			$like = '%<!-- wp:' . $block . '%';

			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID
			FROM {$wpdb->posts}
			WHERE post_content LIKE %s
			AND post_date BETWEEN %s AND %s
			AND post_status = 'publish'",
					$like,
					$start_date,
					$end_date
				)
			);
		}
	}

	// Auto-register when class is loaded
	new Find_Block_In_Posts();

}
