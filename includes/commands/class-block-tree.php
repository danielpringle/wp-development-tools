<?php
/**
 * DPUK CLI Command: block:tree
 *
 * Displays the hierarchical block structure of a WordPress post.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Show the block tree for a post.
	 */
	class DPUK_Block_Tree_Command extends WP_CLI_Command {

		/**
		 * Displays the block tree for a given post.
		 *
		 * ## OPTIONS
		 *
		 * <post_id>
		 * : The post ID to inspect.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts tree, table, json, csv, yaml.
		 * ---
		 * default: tree
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk block:tree 123
		 *     wp dpuk block:tree 123 --format=table
		 *     wp dpuk block:tree 123 --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
			$format  = $assoc_args['format'] ?? 'tree';

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

			$this->display_post_info( $post );

			if ( empty( $blocks ) ) {
				WP_CLI::warning( 'No blocks found in post.' );
				return;
			}

			$rows = [];

			$this->collect_block_tree_rows(
				$blocks,
				$rows
			);

			if ( 'tree' === $format ) {
				$this->display_tree( $blocks );
				$this->display_summary( $rows );
				return;
			}

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[ 'block', 'parent', 'depth' ]
			);

			$this->display_summary( $rows );
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
			WP_CLI::line( 'Block tree:' );
			WP_CLI::line( '' );
		}

		/**
		 * Display the hierarchical block tree.
		 *
		 * @param array $blocks Parsed blocks.
		 */
		private function display_tree( array $blocks ): void {
			$this->display_tree_level( $blocks );
		}

		/**
		 * Display a level of the block tree.
		 *
		 * @param array  $blocks Parsed blocks.
		 * @param string $prefix Tree prefix.
		 */
		private function display_tree_level(
			array $blocks,
			string $prefix = ''
		): void {
			$last_index = count( $blocks ) - 1;

			foreach ( $blocks as $index => $block ) {
				$name = $block['blockName'] ?? '(unregistered block)';
				$is_last = $index === $last_index;

				$branch = $is_last ? '└── ' : '├── ';

				WP_CLI::line(
					$prefix . $branch . $name
				);

				if ( ! empty( $block['innerBlocks'] ) ) {
					$child_prefix = $prefix . ( $is_last ? '    ' : '│   ' );

					$this->display_tree_level(
						$block['innerBlocks'],
						$child_prefix
					);
				}
			}
		}

		/**
		 * Collect flattened block information for structured output.
		 *
		 * @param array  $blocks       Parsed blocks.
		 * @param array  $rows         Reference to output rows.
		 * @param int    $depth        Current tree depth.
		 * @param string $parent_block Parent block name.
		 */
		private function collect_block_tree_rows(
			array $blocks,
			array &$rows,
			int $depth = 0,
			string $parent_block = ''
		): void {
			foreach ( $blocks as $block ) {
				$name = $block['blockName'] ?? '(unregistered block)';

				$rows[] = [
					'block'  => $name,
					'parent' => $parent_block ?: '-',
					'depth'  => $depth,
				];

				if ( ! empty( $block['innerBlocks'] ) ) {
					$this->collect_block_tree_rows(
						$block['innerBlocks'],
						$rows,
						$depth + 1,
						$name
					);
				}
			}
		}

		/**
		 * Display a summary of the block tree.
		 *
		 * @param array $rows Flattened block rows.
		 */
		private function display_summary( array $rows ): void {
			WP_CLI::line( '' );

			$count = count( $rows );

			WP_CLI::success(
				sprintf(
					'%d %s found.',
					$count,
					1 === $count ? 'block' : 'blocks'
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk block:tree',
		DPUK_Block_Tree_Command::class
	);
}