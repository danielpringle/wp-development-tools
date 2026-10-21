<?php
/**
 * DPUK CLI Command: wpml:languages
 *
 * Displays the languages configured and active in WPML.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Display WPML language configuration.
	 */
	class DPUK_WPML_Languages_Command extends WP_CLI_Command {

		/**
		 * Display WPML languages.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk wpml:languages
		 *     wp dpuk wpml:languages --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
				WP_CLI::error( 'WPML is not active.' );
			}

			$languages = apply_filters(
				'wpml_active_languages',
				null,
				[
					'skip_missing' => 0,
					'orderby'      => 'code',
				]
			);

			if ( ! is_array( $languages ) || empty( $languages ) ) {
				WP_CLI::warning( 'No WPML languages were found.' );
				return;
			}

			$default_language = apply_filters(
				'wpml_default_language',
				null
			);

			$rows = [];

			foreach ( $languages as $language ) {
				$code = $language['code'] ?? '';

				$rows[] = [
					'code'    => $code,
					'locale'  => $language['default_locale'] ?? '',
					'name'    => $language['native_name'] ?? $language['translated_name'] ?? '',
					'default' => $code === $default_language ? 'Yes' : 'No',
					'active'  => ! empty( $language['active'] ) ? 'Yes' : 'No',
				];
			}

			$format = $assoc_args['format'] ?? 'table';

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[
					'code',
					'locale',
					'name',
					'default',
					'active',
				]
			);

			if ( 'table' === $format ) {
				WP_CLI::line( '' );
				WP_CLI::line(
					sprintf(
						'%d WPML languages found. Default language: %s',
						count( $rows ),
						$default_language ?: 'unknown'
					)
				);
			}
		}
	}

	WP_CLI::add_command(
		'dpuk wpml:languages',
		DPUK_WPML_Languages_Command::class
	);
}