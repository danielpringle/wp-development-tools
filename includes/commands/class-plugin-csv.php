<?php
/**
 * DPUK CLI Command: plugin-csv
 *
 * Outputs a CSV list of all installed plugins with their name, status (active/inactive), version, update availability, and plugin URL.
 *
 * Usage: wp dpuk plugin:csv > plugins.csv
 *
 * Example output columns: name, status, version, update, link
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Output all plugins as CSV with name, status, version, update, and link.
	 */
	class DPUK_Plugin_CSV_Command extends WP_CLI_Command {
		/**
		 * Outputs plugin info as CSV.
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk plugin:csv > plugins.csv
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( $args, $assoc_args ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', [] );
			$updates        = get_plugin_updates();
			$rows           = [];
			foreach ( $all_plugins as $file => $data ) {
					$is_active = in_array( $file, $active_plugins, true ) ? 'Active' : 'Inactive';
				$update        = isset( $updates[ $file ] ) ? 'Yes' : 'No';
				$plugin_url    = $data['PluginURI'] ?? '';
				$rows[]        = [
					'name'    => $data['Name'],
					'status'  => $is_active,
					'version' => $data['Version'],
					'update'  => $update,
					'link'    => $plugin_url,
				];
			}
			\WP_CLI\Utils\format_items( 'csv', $rows, [ 'name', 'status', 'version', 'update', 'link' ] );
		}
	}
	WP_CLI::add_command( 'dpuk plugin:csv', __NAMESPACE__ . '\DPUK_Plugin_CSV_Command' );
}
