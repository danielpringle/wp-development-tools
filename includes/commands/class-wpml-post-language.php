<?php
/**
 * DPUK CLI Command: wpml:post-language
 *
 * Displays WPML language information for a specific post.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Display WPML language information for a post.
	 */
	class DPUK_WPML_Post_Language_Command extends WP_CLI_Command {

		/**
		 * Display WPML language information.
		 *
		 * ## OPTIONS
		 *
		 * <post_type>
		 * : The WordPress post type.
		 *
		 * <id>
		 * : The WordPress post ID.
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk wpml:post-language post 6294
		 *     wp dpuk wpml:post-language attachment 7193
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {

			$post_type = $args[0] ?? null;
			$post_id   = isset( $args[1] ) ? absint( $args[1] ) : 0;

			if ( ! $post_type || ! $post_id ) {
				WP_CLI::error(
					'Usage: wp dpuk wpml:post-language <post_type> <id>'
				);
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				WP_CLI::error(
					sprintf(
						'Post ID %d was not found.',
						$post_id
					)
				);
			}

			if ( $post->post_type !== $post_type ) {
				WP_CLI::error(
					sprintf(
						'Post ID %d is post type "%s", not "%s".',
						$post_id,
						$post->post_type,
						$post_type
					)
				);
			}

			$language = apply_filters(
				'wpml_post_language_details',
				null,
				$post_id
			);

			WP_CLI::line( 'WPML Post Language' );
			WP_CLI::line( '==================' );
			WP_CLI::line( '' );

			WP_CLI::line(
				sprintf(
					'Post Type:       %s',
					$post->post_type
				)
			);

			WP_CLI::line(
				sprintf(
					'ID:              %d',
					$post->ID
				)
			);

			WP_CLI::line(
				sprintf(
					'Title:           %s',
					$post->post_title ?: '(no title)'
				)
			);

			WP_CLI::line(
				sprintf(
					'Language:        %s',
					$language['language_code'] ?? 'NONE'
				)
			);

			WP_CLI::line(
				sprintf(
					'Locale:          %s',
					$language['locale'] ?? 'NONE'
				)
			);

			WP_CLI::line(
				sprintf(
					'Source Language: %s',
					$language['source_language_code'] ?? 'NONE'
				)
			);

			WP_CLI::line(
				sprintf(
					'TRID:            %s',
					$language['trid'] ?? 'NONE'
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk wpml:post-language',
		DPUK_WPML_Post_Language_Command::class
	);
}