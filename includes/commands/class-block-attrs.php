<?php
/**
 * WP-CLI Command: Block Attribute Dumper
 *
 * Outputs all block attributes for every block in a given WordPress post.
 * Useful for debugging, auditing, or inspecting block attribute values in post content.
 *
 * Usage:
 *   wp dpuk block:attrs <post_id>
 *
 * Example:
 *   wp dpuk block:attrs 123
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Dump block attributes for a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to inspect.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dpuk block:attrs 123
	 */
	/**
	 * Suppress PhpUndefinedClassInspection warning for WP_CLI.
	 */
	/**
	 * WP_CLI global variable annotation for IDEs.
	 *
	 * @var \WP_CLI $WP_CLI
	 */
	class DPUK_Block_Attrs_Command extends \WP_CLI_Command {
		/**
		 * Outputs all block attributes for a given post.
		 *
		 * @param array $args       Positional arguments passed to the command.
		 * @param array $assoc_args Associative arguments passed to the command.
		 */
		public function __invoke( array $args, array $assoc_args ) {
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
				$block_name = $block['blockName'] ?? '(unknown)';
				if ( ! empty( $block['attrs'] ) ) {
					foreach ( $block['attrs'] as $key => $value ) {
							$rows[] = [
								'block'      => $i + 1,
								'block_name' => $block_name,
								'attribute'  => $key,
								'value'      => is_scalar( $value ) ? $value : wp_json_encode( $value ),
							];
					}
				} else {
						$rows[] = [
							'block'      => $i + 1,
							'block_name' => $block_name,
							'attribute'  => '(none)',
							'value'      => '',
						];
				}
			}
			$format = $assoc_args['format'] ?? 'table';
			\WP_CLI\Utils\format_items( $format, $rows, [ 'block', 'block_name', 'attribute', 'value' ] );
		}
	}
	WP_CLI::add_command( 'dpuk block:attrs', __NAMESPACE__ . '\DPUK_Block_Attrs_Command' );
}
