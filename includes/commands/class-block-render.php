<?php
/**
 * DPUK CLI Command: block:render
 *
 * Renders blocks from a WordPress post and displays their server-rendered HTML.
 * Useful for debugging dynamic blocks, render callbacks, and block output.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Inspect server-rendered block output.
	 */
	class DPUK_Block_Render_Command extends WP_CLI_Command {

		/**
		 * Render blocks from a post.
		 *
		 * ## OPTIONS
		 *
		 * <post_id>
		 * : The post ID to inspect.
		 *
		 * [--block=<name>]
		 * : Only render blocks matching the specified block name.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk block:render 123
		 *     wp dpuk block:render 123 --block=heading
		 *     wp dpuk block:render 123 --block=my-plugin/card
		 *     wp dpuk block:render 123 --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
			$format  = $assoc_args['format'] ?? 'table';
			$block   = $assoc_args['block'] ?? null;

			if ( ! $post_id ) {
				WP_CLI::error( 'Please provide a post ID.' );
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				WP_CLI::error(
					sprintf(
						'Post %d not found.',
						$post_id
					)
				);
			}

			$blocks = parse_blocks( $post->post_content );

			if ( empty( $blocks ) ) {
				WP_CLI::warning( 'No blocks found in post.' );
				return;
			}

			$rows = [];

			$this->collect_rendered_blocks(
				$blocks,
				$rows,
				$block
			);

			if ( empty( $rows ) ) {
				$message = $block
					? sprintf( 'No "%s" blocks found in post.', $block )
					: 'No blocks could be rendered.';

				WP_CLI::warning( $message );
				return;
			}

			$this->display_post_info( $post );

			if ( 'table' === $format ) {
				$this->display_table( $rows );
				return;
			}

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[ 'index', 'block_name', 'depth', 'output' ]
			);
		}

		/**
		 * Collect rendered blocks recursively.
		 *
		 * @param array       $blocks       Parsed blocks.
		 * @param array       $rows         Reference to output rows.
		 * @param string|null $block_filter Optional block name filter.
		 * @param int         $depth        Current tree depth.
		 */
		private function collect_rendered_blocks(
			array $blocks,
			array &$rows,
			?string $block_filter = null,
			int $depth = 0
		): void {
			foreach ( $blocks as $block ) {
				$block_name = $block['blockName'] ?? '(unregistered block)';

				if ( null === $block_filter || $block_name === $block_filter ) {
					$rows[] = [
						'index'      => count( $rows ) + 1,
						'block_name' => $block_name,
						'depth'      => $depth,
						'output'     => render_block( $block ),
					];
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$this->collect_rendered_blocks(
						$block['innerBlocks'],
						$rows,
						$block_filter,
						$depth + 1
					);
				}
			}
		}

		/**
		 * Display basic post information.
		 *
		 * @param \WP_Post $post Post object.
		 */
		private function display_post_info( \WP_Post $post ): void {
			WP_CLI::line(
				sprintf(
					'Post: %d — %s',
					$post->ID,
					$post->post_title ?: '(no title)'
				)
			);

			WP_CLI::line( 'Type: ' . $post->post_type );
			WP_CLI::line( 'Status: ' . $post->post_status );
			WP_CLI::line( '' );
		}

		/**
		 * Display rendered blocks in a readable terminal format.
		 *
		 * @param array $rows Rendered block rows.
		 */
		private function display_table( array $rows ): void {
			foreach ( $rows as $row ) {
				$indent = str_repeat( '    ', $row['depth'] );

				WP_CLI::line(
					sprintf(
						'%sBlock %d: %s',
						$indent,
						$row['index'],
						$row['block_name']
					)
				);

				WP_CLI::line( '' );

				$output = trim( $row['output'] );

				if ( '' === $output ) {
					WP_CLI::line(
						$indent . '(empty output)'
					);
				} else {
					foreach ( explode( "\n", $output ) as $line ) {
						WP_CLI::line(
							$indent . $line
						);
					}
				}

				WP_CLI::line( '' );
			}

			WP_CLI::success(
				sprintf(
					'%d %s rendered.',
					count( $rows ),
					1 === count( $rows ) ? 'block' : 'blocks'
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk block:render',
		DPUK_Block_Render_Command::class
	);
}