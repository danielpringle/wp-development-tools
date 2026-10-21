<?php
/**
 * DPUK CLI Command: block:find
 *
 * Finds published posts containing a specified Gutenberg block within a date range.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Find published posts containing a Gutenberg block.
	 */
	class DPUK_Find_Block_Command extends WP_CLI_Command {

		/**
		 * Finds published posts containing a specified block.
		 *
		 * ## OPTIONS
		 *
		 * --block=<name>
		 * : Block name, for example "heading" or "my-plugin/my-custom-block".
		 *
		 * [--start-date=<date>]
		 * : Start date in Y-m-d format. Defaults to 30 days ago.
		 *
		 * [--end-date=<date>]
		 * : End date in Y-m-d format. Defaults to today.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml, ids, count.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk block:find --block=heading
		 *     wp dpuk block:find --block=my-plugin/my-custom-block
		 *     wp dpuk block:find --block=heading --start-date=2026-01-01
		 *     wp dpuk block:find --block=heading --start-date=2026-01-01 --end-date=2026-08-17
		 *     wp dpuk block:find --block=heading --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$block      = $assoc_args['block'] ?? null;
			$start_date = $assoc_args['start-date'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			$end_date   = $assoc_args['end-date'] ?? gmdate( 'Y-m-d' );
			$format     = $assoc_args['format'] ?? 'table';

			if ( ! $block ) {
				WP_CLI::error( 'Please specify a block with --block=<name>.' );
			}

			$this->validate_date_or_error( $start_date, 'start date' );
			$this->validate_date_or_error( $end_date, 'end date' );

			if ( $start_date > $end_date ) {
				WP_CLI::error( 'The start date cannot be after the end date.' );
			}

			WP_CLI::line( 'Scanning posts...' );

			$post_ids = $this->find_block(
				$block,
				$start_date,
				$end_date
			);

			if ( empty( $post_ids ) ) {
				WP_CLI::success( 'No matching posts found.' );
				return;
			}

			$results = array_map(
				static function ( int $post_id ): array {
					return [ 'ID' => $post_id ];
				},
				$post_ids
			);

			WP_CLI\Utils\format_items(
				$format,
				$results,
				[ 'ID' ]
			);

			WP_CLI::success(
				sprintf(
					'Block %s found in %d posts.',
					$block,
					count( $post_ids )
				)
			);
		}

		/**
		 * Find published posts containing a block.
		 *
		 * @param string $block      Block name.
		 * @param string $start_date Inclusive start date.
		 * @param string $end_date   Inclusive end date.
		 * @return array<int> Post IDs.
		 */
		private function find_block(
			string $block,
			string $start_date,
			string $end_date
		): array {
			global $wpdb;

			$like = '%' . $wpdb->esc_like( '<!-- wp:' . $block ) . '%';

			$end_date_exclusive = gmdate(
				'Y-m-d',
				strtotime( $end_date . ' +1 day' )
			);

			return array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID
						FROM {$wpdb->posts}
						WHERE post_content LIKE %s
						AND post_date >= %s
						AND post_date < %s
						AND post_status = 'publish'",
						$like,
						$start_date,
						$end_date_exclusive
					)
				)
			);
		}

		/**
		 * Validate a date or terminate the command with an error.
		 *
		 * @param string $date  Date to validate.
		 * @param string $label Human-readable date label.
		 */
		private function validate_date_or_error(
			string $date,
			string $label
		): void {
			$parsed = \DateTimeImmutable::createFromFormat(
				'Y-m-d',
				$date
			);

			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				WP_CLI::error(
					sprintf(
						'Invalid %s. Expected Y-m-d format.',
						$label
					)
				);
			}
		}
	}

	WP_CLI::add_command(
		'dpuk block:find',
		DPUK_Find_Block_Command::class
	);
}