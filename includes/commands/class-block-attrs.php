<?php
/**
 * DPUK CLI Command: block:attrs
 *
 * Displays stored attributes for blocks in a WordPress post.
 * Useful for inspecting Gutenberg block attributes and debugging block content.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Inspect stored block attributes.
	 */
	class DPUK_Block_Attrs_Command extends WP_CLI_Command {

		/**
		 * Display block attributes for a post.
		 *
		 * ## OPTIONS
		 *
		 * <post_id>
		 * : The post ID to inspect.
		 *
		 * [--block=<name>]
		 * : Only display attributes for blocks matching the specified block name.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk block:attrs 123
		 *     wp dpuk block:attrs 123 --block=core/image
		 *     wp dpuk block:attrs 123 --block=my-plugin/card
		 *     wp dpuk block:attrs 123 --format=json
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

			$this->collect_block_attributes(
				$blocks,
				$rows,
				$block
			);

			if ( empty( $rows ) ) {
				$message = $block
					? sprintf(
						'No attributes found for "%s" blocks in post.',
						$block
					)
					: 'No block attributes found in post.';

				WP_CLI::warning( $message );
				return;
			}

			$this->display_post_info( $post );

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[
					'block',
					'block_name',
					'depth',
					'attribute',
					'value',
				]
			);

			$this->display_summary( $rows );
		}

		/**
		 * Recursively collect block attributes.
		 *
		 * @param array       $blocks       Parsed blocks.
		 * @param array       $rows         Reference to output rows.
		 * @param string|null $block_filter Optional block name filter.
		 * @param int         $depth        Current tree depth.
		 */
		private function collect_block_attributes(
			array $blocks,
			array &$rows,
			?string $block_filter = null,
			int $depth = 0
		): void {
			foreach ( $blocks as $block ) {
				$block_name = $block['blockName'] ?? '(unregistered block)';

				$is_match = null === $block_filter || $block_name === $block_filter;

				if ( $is_match && ! empty( $block['attrs'] ) ) {
					foreach ( $block['attrs'] as $attribute => $value ) {
						$rows[] = [
							'block'      => count( $rows ) + 1,
							'block_name' => $block_name,
							'depth'      => $depth,
							'attribute'  => $attribute,
							'value'      => $this->format_attribute_value( $value ),
						];
					}
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$this->collect_block_attributes(
						$block['innerBlocks'],
						$rows,
						$block_filter,
						$depth + 1
					);
				}
			}
		}

		/**
		 * Format a block attribute value for CLI output.
		 *
		 * @param mixed $value Attribute value.
		 * @return string Formatted attribute value.
		 */
		private function format_attribute_value( mixed $value ): string {
			if ( is_scalar( $value ) || null === $value ) {
				return (string) $value;
			}

			$json = wp_json_encode(
				$value,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			return false !== $json ? $json : '(unable to encode value)';
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
		 * Display a summary of the results.
		 *
		 * @param array $rows Attribute rows.
		 */
		private function display_summary( array $rows ): void {
			$blocks = [];

			foreach ( $rows as $row ) {
				$key = $row['block_name'] . ':' . $row['depth'];

				$blocks[ $key ] = true;
			}

			WP_CLI::line( '' );

			WP_CLI::success(
				sprintf(
					'%d %s found across %d block instance%s.',
					count( $rows ),
					1 === count( $rows ) ? 'attribute' : 'attributes',
					count( $blocks ),
					1 === count( $blocks ) ? '' : 's'
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk block:attrs',
		DPUK_Block_Attrs_Command::class
	);
}