<?php
/**
 * DPUK CLI Command: plugin:list
 *
 * Displays installed WordPress plugins with their status, version,
 * available update, and plugin URL.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * List installed WordPress plugins.
	 */
	class DPUK_Plugin_List_Command extends WP_CLI_Command {

		/**
		 * Display installed plugins.
		 *
		 * ## OPTIONS
		 *
		 * [--status=<status>]
		 * : Filter plugins by status.
		 * ---
		 * options:
		 *   - active
		 *   - inactive
		 *   - network-active
		 * ---
		 *
		 * [--updates]
		 * : Only show plugins with an available update.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk plugin:list
		 *     wp dpuk plugin:list --status=active
		 *     wp dpuk plugin:list --status=inactive
		 *     wp dpuk plugin:list --updates
		 *     wp dpuk plugin:list --format=csv > plugins.csv
		 *     wp dpuk plugin:list --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$plugins         = get_plugins();
			$active_plugins  = (array) get_option( 'active_plugins', [] );
			$network_plugins = is_multisite()
				? (array) get_site_option( 'active_sitewide_plugins', [] )
				: [];
			$updates         = get_plugin_updates();

			$status_filter = $assoc_args['status'] ?? null;
			$updates_only  = isset( $assoc_args['updates'] );
			$format        = $assoc_args['format'] ?? 'table';

			$rows = [];

			foreach ( $plugins as $file => $data ) {
				$status = $this->get_plugin_status(
					$file,
					$active_plugins,
					$network_plugins
				);

				if ( $status_filter && $status !== $status_filter ) {
					continue;
				}

				$has_update = isset( $updates[ $file ] );

				if ( $updates_only && ! $has_update ) {
					continue;
				}

				$rows[] = [
					'file'           => $file,
					'name'           => $data['Name'] ?? '',
					'status'         => $status,
					'version'        => $data['Version'] ?? '',
					'available'      => $has_update
						? ( $updates[ $file ]->update->new_version ?? '' )
						: '',
					'plugin_url'     => $data['PluginURI'] ?? '',
				];
			}

			if ( empty( $rows ) ) {
				WP_CLI::warning( 'No plugins matched the specified filters.' );
				return;
			}

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[
					'file',
					'name',
					'status',
					'version',
					'available',
					'plugin_url',
				]
			);

			$this->display_summary( $rows );
		}

		/**
		 * Determine the plugin activation status.
		 *
		 * @param string[] $file             Plugin files.
		 * @param string[] $active_plugins    Site-active plugins.
		 * @param array    $network_plugins   Network-active plugins.
		 * @return string Plugin status.
		 */
		private function get_plugin_status(
			string $file,
			array $active_plugins,
			array $network_plugins
		): string {
			if ( isset( $network_plugins[ $file ] ) ) {
				return 'network-active';
			}

			if ( in_array( $file, $active_plugins, true ) ) {
				return 'active';
			}

			return 'inactive';
		}

		/**
		 * Display a summary of the results.
		 *
		 * @param array $rows Plugin rows.
		 */
		private function display_summary( array $rows ): void {
			$active         = 0;
			$inactive       = 0;
			$network_active = 0;
			$updates        = 0;

			foreach ( $rows as $row ) {
				switch ( $row['status'] ) {
					case 'active':
						++$active;
						break;

					case 'network-active':
						++$network_active;
						break;

					case 'inactive':
						++$inactive;
						break;
				}

				if ( '' !== $row['available'] ) {
					++$updates;
				}
			}

			WP_CLI::line( '' );

			WP_CLI::line(
				sprintf(
					'Plugins: %d | Active: %d | Network active: %d | Inactive: %d | Updates: %d',
					count( $rows ),
					$active,
					$network_active,
					$inactive,
					$updates
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk plugin:list',
		DPUK_Plugin_List_Command::class
	);
}