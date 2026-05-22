<?php
/**
 * WP-CLI Command: Block Tree Viewer
 *
 * Displays a hierarchical tree of all blocks used in a given WordPress post.
 * Useful for inspecting block structure, nesting, and parent/child relationships in post content.
 *
 * Usage:
 *   wp dpuk block:tree <post_id>
 *
 * Example:
 *   wp dpuk block:tree 123
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Show block tree for a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to inspect.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dpuk block:tree 123
	 */
	class DPUK_Block_Tree_Command extends WP_CLI_Command {
		/**
		 * Displays the block tree for a given post.
		 *
		 * @param array $args       Positional arguments. Expects post ID at index 0.
		 * @param array $assoc_args Associative arguments (e.g., format).
		 * @return void
		 */
		public function __invoke( $args, $assoc_args ) {
			$post_id = intval( $args[0] ?? 0 );
			$post    = get_post( $post_id );
			if ( ! $post ) {
				WP_CLI::error( 'Post not found.' );
			}
			$blocks = parse_blocks( $post->post_content );
			if ( empty( $blocks ) ) {
				WP_CLI::warning( 'No blocks found in post.' );
				return;
			}
			$rows = [];
			$this->collect_block_tree_rows( $blocks, $rows );
			$format = $assoc_args['format'] ?? 'table';
			\WP_CLI\Utils\format_items( $format, $rows, [ 'block', 'block_name', 'parent', 'depth' ] );
		}

			/**
			 * Recursively collects block tree rows for display.
			 *
			 * @param array  $blocks        Array of parsed blocks.
			 * @param array  $rows          Reference to the rows array to populate.
			 * @param int    $depth         Current depth in the block tree.
			 * @param string $parent_block  Name of the parent block.
			 * @return void
			 */
		private function collect_block_tree_rows( $blocks, &$rows, $depth = 0, $parent_block = '' ) {
			foreach ( $blocks as $i => $block ) {
				$name   = $block['blockName'] ?? '(unknown)';
				$rows[] = [
					'block'      => count( $rows ) + 1,
					'block_name' => $name,
					'parent'     => $parent_block,
					'depth'      => $depth,
				];
				if ( ! empty( $block['innerBlocks'] ) ) {
					$this->collect_block_tree_rows( $block['innerBlocks'], $rows, $depth + 1, $name );
				}
			}
		}
	}
	WP_CLI::add_command( 'dpuk block:tree', __NAMESPACE__ . '\DPUK_Block_Tree_Command' );
}
