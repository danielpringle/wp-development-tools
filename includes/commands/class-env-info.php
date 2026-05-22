<?php
/**
 * DPUK CLI Command: env-info
 *
 * Outputs a summary of the current WordPress environment, including WordPress version, PHP version, database info, active theme, and active plugins.
 *
 * Usage: wp dpuk env:info
 *
 * Example output includes: WP version, PHP version, DB version, theme, and plugin list.
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Show environment and system info in one place.
	 */
	class DPUK_Env_Info_Command extends WP_CLI_Command {
		/**
		 * Outputs a summary of the current WordPress environment, including WordPress version, PHP version, database info, active theme, and active plugins.
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk env:info
		 *
		 * @param array $args       Positional arguments passed to the command.
		 * @param array $assoc_args Associative arguments passed to the command.
		 */
		public function __invoke( $args, $assoc_args ) {
			global $wpdb;

			$theme          = wp_get_theme();
			$active_plugins = get_option( 'active_plugins', [] );
				$cron       = _get_cron_array();
				$info       = [
					[
						'Label' => 'WP version',
						'Value' => get_bloginfo( 'version' ),
					],
					[
						'Label' => 'PHP version',
						'Value' => phpversion(),
					],
					[
						'Label' => 'Memory limit',
						'Value' => ini_get( 'memory_limit' ),
					],
					[
						'Label' => 'Debug mode',
						'Value' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled',
					],
					[
						'Label' => 'DB version',
						'Value' => $wpdb->get_var( 'SELECT VERSION()' ),
					],
					[
						'Label' => 'Object cache',
						'Value' => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ? 'Enabled' : 'Disabled',
					],
					[
						'Label' => 'Cron events',
						'Value' => $cron ? count( $cron ) . ' scheduled' : 'None scheduled',
					],
					[
						'Label' => 'REST API',
						'Value' => $this->check_rest_api(),
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
						'Label' => 'Active theme',
						'Value' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
					],
					[
						'Label' => 'Active plugins',
						'Value' => count( $active_plugins ),
					],
					[
						'Label' => 'Site URL',
						'Value' => get_option( 'siteurl' ),
					],
					[
						'Label' => 'Home URL',
						'Value' => get_option( 'home' ),
					],
					[
						'Label' => 'WP_ENVIRONMENT_TYPE',
						'Value' => defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'Not set',
					],
					[
						'Label' => 'PHP max execution time',
						'Value' => ini_get( 'max_execution_time' ),
					],
					[
						'Label' => 'Server software',
						'Value' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname(),
					],
					[
						'Label' => 'Multisite',
						'Value' => is_multisite() ? 'Yes' : 'No',
					],
					[
						'Label' => 'DB table prefix',
						'Value' => $wpdb->prefix,
					],
					[
						'Label' => 'Filesystem method',
						'Value' => function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'N/A',
					],
					[
						'Label' => 'SSL enabled',
						'Value' => is_ssl() ? 'Yes' : 'No',
					],
				];

				$format = $assoc_args['format'] ?? 'table';
				\WP_CLI\Utils\format_items( $format, $info, [ 'Label', 'Value' ] );
		}

		/**
		 * Checks if the REST API is available.
		 *
		 * @return string REST API status.
		 */
		private function check_rest_api() {
			$rest_url = get_rest_url();
			$response = wp_remote_get( $rest_url );
			if ( is_wp_error( $response ) ) {
				return 'Unavailable';
			}
			$code = wp_remote_retrieve_response_code( $response );
			return ( $code >= 200 && $code < 300 ) ? 'Available' : 'Unavailable (' . $code . ')';
		}
	}

	WP_CLI::add_command( 'dpuk env:info', __NAMESPACE__ . '\DPUK_Env_Info_Command' );
}
