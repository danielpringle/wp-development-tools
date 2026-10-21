<?php
/**
 * DPUK CLI Command: env-info
 *
 * Outputs a summary of the current WordPress environment.
 *
 * Usage:
 *   wp dpuk env:info
 *
 * Examples:
 *   wp dpuk env:info
 *   wp dpuk env:info --format=json
 *   wp dpuk env:info --format=csv
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Show WordPress environment and system information.
	 */
	class DPUK_Env_Info_Command extends WP_CLI_Command {

		/**
		 * Outputs a summary of the current WordPress environment.
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk env:info
		 *     wp dpuk env:info --format=json
		 *
		 * @param array $args       Positional arguments passed to the command.
		 * @param array $assoc_args Associative arguments passed to the command.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			global $wpdb;

			$theme                 = wp_get_theme();
			$active_plugins        = get_option( 'active_plugins', [] );
			$network_plugins       = is_multisite()
				? get_site_option( 'active_sitewide_plugins', [] )
				: [];
			$environment_type      = wp_get_environment_type();
			$cron                  = $this->get_cron_events();
			$db_server             = $wpdb->db_server_info();
			$db_version            = $wpdb->get_var( 'SELECT VERSION()' );
			$filesystem_method     = function_exists( 'get_filesystem_method' )
				? get_filesystem_method()
				: 'N/A';
			$object_cache_enabled  = function_exists( 'wp_using_ext_object_cache' )
				&& wp_using_ext_object_cache();

			$info = [
				[
					'Label' => 'WordPress version',
					'Value' => get_bloginfo( 'version' ),
				],
				[
					'Label' => 'WP-CLI version',
					'Value' => defined( 'WP_CLI_VERSION' ) ? WP_CLI_VERSION : 'Unknown',
				],
				[
					'Label' => 'Environment',
					'Value' => $environment_type,
				],
				[
					'Label' => 'PHP version',
					'Value' => PHP_VERSION,
				],
				[
					'Label' => 'PHP memory limit',
					'Value' => ini_get( 'memory_limit' ),
				],
				[
					'Label' => 'WP memory limit',
					'Value' => WP_MEMORY_LIMIT,
				],
				[
					'Label' => 'WP max memory limit',
					'Value' => WP_MAX_MEMORY_LIMIT,
				],
				[
					'Label' => 'PHP max execution time',
					'Value' => ini_get( 'max_execution_time' ),
				],
				[
					'Label' => 'Upload max filesize',
					'Value' => ini_get( 'upload_max_filesize' ),
				],
				[
					'Label' => 'Post max size',
					'Value' => ini_get( 'post_max_size' ),
				],
				[
					'Label' => 'Debug mode',
					'Value' => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled',
				],
				[
					'Label' => 'Object cache',
					'Value' => $object_cache_enabled ? 'Enabled' : 'Disabled',
				],
				[
					'Label' => 'Cron events',
					'Value' => $cron
						? count( $cron ) . ' scheduled timestamps'
						: 'None scheduled',
				],
				[
					'Label' => 'REST API',
					'Value' => $this->check_rest_api(),
				],
				[
					'Label' => 'Database',
					'Value' => $db_server ?: 'Unknown',
				],
				[
					'Label' => 'Database version',
					'Value' => $db_version ?: 'Unknown',
				],
				[
					'Label' => 'DB table prefix',
					'Value' => $wpdb->prefix,
				],
				[
					'Label' => 'Active theme',
					'Value' => $this->get_theme_label( $theme ),
				],
				[
					'Label' => 'Theme template',
					'Value' => $theme->get_template(),
				],
				[
					'Label' => 'Theme stylesheet',
					'Value' => $theme->get_stylesheet(),
				],
				[
					'Label' => 'Active plugins',
					'Value' => count( $active_plugins ),
				],
				[
					'Label' => 'Network active plugins',
					'Value' => is_multisite() ? count( $network_plugins ) : 'N/A',
				],
				[
					'Label' => 'Site URL',
					'Value' => site_url(),
				],
				[
					'Label' => 'Home URL',
					'Value' => home_url(),
				],
				[
					'Label' => 'Server software',
					'Value' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname(),
				],
				[
					'Label' => 'Filesystem method',
					'Value' => $filesystem_method,
				],
				[
					'Label' => 'Multisite',
					'Value' => is_multisite() ? 'Yes' : 'No',
				],
				[
					'Label' => 'SSL enabled',
					'Value' => is_ssl() ? 'Yes' : 'No',
				],
			];

			$format = $assoc_args['format'] ?? 'table';

			WP_CLI\Utils\format_items(
				$format,
				$info,
				[ 'Label', 'Value' ]
			);
		}

		/**
		 * Check whether the REST API is reachable over HTTP.
		 *
		 * This deliberately performs an HTTP request rather than only checking
		 * whether the REST API is registered. This tests whether the site can
		 * actually reach its own REST API endpoint.
		 *
		 * @return string REST API status.
		 */
		private function check_rest_api(): string {
			$response = wp_remote_get(
				get_rest_url(),
				[
					'timeout' => 5,
				]
			);

			if ( is_wp_error( $response ) ) {
				return 'Unavailable';
			}

			$code = wp_remote_retrieve_response_code( $response );

			return $code >= 200 && $code < 300
				? 'Reachable (' . $code . ')'
				: 'Unavailable (' . $code . ')';
		}

		/**
		 * Get the number of scheduled cron timestamps.
		 *
		 * WordPress does not currently expose a public API for enumerating all
		 * scheduled cron events, so this diagnostic uses the internal cron array.
		 *
		 * @return array|null Scheduled cron events.
		 */
		private function get_cron_events(): ?array {
			if ( ! function_exists( '_get_cron_array' ) ) {
				return null;
			}

			return _get_cron_array();
		}

		/**
		 * Format the active theme name and version.
		 *
		 * @param \WP_Theme $theme Active theme object.
		 *
		 * @return string Theme name and version.
		 */
		private function get_theme_label( \WP_Theme $theme ): string {
			$name    = $theme->get( 'Name' );
			$version = $theme->get( 'Version' );

			if ( ! $version ) {
				return $name;
			}

			return $name . ' (' . $version . ')';
		}
	}

	WP_CLI::add_command(
		'dpuk env:info',
		__NAMESPACE__ . '\DPUK_Env_Info_Command'
	);
}