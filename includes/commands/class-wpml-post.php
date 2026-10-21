<?php
/**
 * DPUK CLI Command: wpml:post
 *
 * Displays WPML language and translation information for a WordPress post.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Inspect WPML information for a post.
	 */
	class DPUK_WPML_Post_Command extends WP_CLI_Command {

		/**
		 * Display WPML information for a post.
		 *
		 * ## OPTIONS
		 *
		 * <id>
		 * : The post ID to inspect.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, json, yaml, csv.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk wpml:post 6294
		 *     wp dpuk wpml:post 6294 --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$post_id = absint( $args[0] ?? 0 );

			if ( ! $post_id ) {
				WP_CLI::error( 'A valid post ID is required.' );
			}

			if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
				WP_CLI::error( 'WPML is not active.' );
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

			$language_details = apply_filters(
				'wpml_post_language_details',
				null,
				$post_id
			);

			if ( ! is_array( $language_details ) ) {
				WP_CLI::error(
					'Unable to determine the WPML language for this post.'
				);
			}

			$language_code = $language_details['language_code'] ?? '';
			$source_language = $language_details['source_language_code'] ?? null;

			$locale = $this->get_locale( $language_code );

			$translations = apply_filters(
				'wpml_get_element_translations',
				null,
				$this->get_trid( $post_id ),
				'post_' . $post->post_type
			);

			$translation_rows = [];

			if ( is_array( $translations ) ) {
				foreach ( $translations as $translation ) {
					$translation_id = isset( $translation->element_id )
						? (int) $translation->element_id
						: 0;

					if ( ! $translation_id ) {
						continue;
					}

					$translation_post = get_post( $translation_id );

					$translation_rows[] = [
						'language' => $translation->language_code ?? '',
						'locale'   => $this->get_locale(
							$translation->language_code ?? ''
						),
						'id'       => $translation_id,
						'status'   => $translation_post
							? $translation_post->post_status
							: 'unknown',
						'title'    => $translation_post
							? get_the_title( $translation_id )
							: '',
					];
				}
			}

			$data = [
				'id'              => $post_id,
				'post_type'       => $post->post_type,
				'status'          => $post->post_status,
				'title'           => get_the_title( $post_id ),
				'language'        => $language_code,
				'locale'          => $locale,
				'source_language' => $source_language ?: 'Original',
				'trid'            => $this->get_trid( $post_id ),
				'translations'    => $translation_rows,
			];

			$format = $assoc_args['format'] ?? 'table';

			if ( 'table' !== $format ) {
				WP_CLI\Utils\format_items(
					$format,
					[
						[
							'id'              => $data['id'],
							'post_type'       => $data['post_type'],
							'status'          => $data['status'],
							'title'           => $data['title'],
							'language'        => $data['language'],
							'locale'          => $data['locale'],
							'source_language' => $data['source_language'],
							'trid'            => $data['trid'],
							'translations'    => wp_json_encode(
								$data['translations']
							),
						],
					],
					[
						'id',
						'post_type',
						'status',
						'title',
						'language',
						'locale',
						'source_language',
						'trid',
						'translations',
					]
				);

				return;
			}

			$this->display_table( $data );
		}

		/**
		 * Display human-readable output.
		 *
		 * @param array $data Command data.
		 */
		private function display_table( array $data ): void {
			WP_CLI::line( 'WPML Post' );
			WP_CLI::line( '' );

			WP_CLI::line( 'Post' );
			WP_CLI::line( '  ID:               ' . $data['id'] );
			WP_CLI::line( '  Type:             ' . $data['post_type'] );
			WP_CLI::line( '  Status:           ' . $data['status'] );
			WP_CLI::line( '  Title:            ' . $data['title'] );
			WP_CLI::line( '  Language:         ' . $data['language'] );
			WP_CLI::line( '  Locale:           ' . $data['locale'] );
			WP_CLI::line( '  Source language:  ' . $data['source_language'] );
			WP_CLI::line( '  Translation ID:   ' . $data['trid'] );

			WP_CLI::line( '' );
			WP_CLI::line( 'Translations' );

			if ( empty( $data['translations'] ) ) {
				WP_CLI::line( '  No translations found.' );
				return;
			}

			WP_CLI\Utils\format_items(
				'table',
				$data['translations'],
				[
					'language',
					'locale',
					'id',
					'status',
					'title',
				]
			);
		}

		/**
		 * Get the WPML translation group ID.
		 *
		 * @param int $post_id Post ID.
		 * @return int Translation group ID.
		 */
		private function get_trid( int $post_id ): int {
			return (int) apply_filters(
				'wpml_element_trid',
				null,
				$post_id,
				'post_' . get_post_type( $post_id )
			);
		}

		/**
		 * Get the locale for a WPML language code.
		 *
		 * @param string $language_code WPML language code.
		 * @return string Locale.
		 */
		private function get_locale( string $language_code ): string {
			if ( '' === $language_code ) {
				return '';
			}

			$languages = apply_filters(
				'wpml_active_languages',
				null,
				[
					'skip_missing' => 0,
				]
			);

			return $languages[ $language_code ]['default_locale'] ?? '';
		}
	}

	WP_CLI::add_command(
		'dpuk wpml:post',
		DPUK_WPML_Post_Command::class
	);
}