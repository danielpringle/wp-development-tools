<?php
/**
 * WP-CLI Command: Block Render Inspector
 *
 * Renders all blocks in a given WordPress post and outputs the rendered HTML for inspection.
 * Useful for debugging block rendering, verifying output, and troubleshooting block markup issues.
 *
 * Usage:
 *   wp dpuk block:render <post_id>
 *
 * Example:
 *   wp dpuk block:render 123
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Render all blocks in a post and show output.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to inspect.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dpuk block:render 123
	 */
	class DPUK_Block_Render_Command extends WP_CLI_Command {
		/**
		 * Renders all blocks in a given post and outputs the rendered HTML.
		 *
		 * @param array $args       Positional arguments. Expects post ID at index 0.
		 * @param array $assoc_args Associative arguments (not used).
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
			foreach ( $blocks as $i => $block ) {
				$name   = $block['blockName'] ?? '(unknown)';
				$output = render_block( $block );
				$rows[] = [
					'block'      => $i + 1,
					'block_name' => $name,
					'output'     => $output,
				];
			}
			$format = $assoc_args['format'] ?? 'table';
			\WP_CLI\Utils\format_items( $format, $rows, [ 'block', 'block_name', 'output' ] );
		}
	}
	WP_CLI::add_command( 'dpuk block:render', __NAMESPACE__ . '\DPUK_Block_Render_Command' );
}
