<?php
/**
 * WP Development Tools
 *
 * @package           DPUK\DevelopmentTools
 * @author            Daniel Pringle
 *
 * @wordpress-plugin
 * Plugin Name:       WP Development Tools
 * Plugin URI:        https://danielpringle.co.uk
 * Description:       Runtime inspection, debugging, developer tooling, and custom WP-CLI commands for WordPress.
 * Version:           0.0.1
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Daniel Pringle
 * Author URI:        https://danielpringle.co.uk
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-development-tools
 */

namespace DPUK\DevelopmentTools;

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die; // Exit if accessed directly.
}

/**
 * Load WP-CLI Commands
 *
 * Automatically loads all commands from the includes/commands directory
 *
 * @category Class
 * @package DPUKCLICommands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require plugin_dir_path( __FILE__ ) . 'includes/class-command-loader.php';
	$command_loader = new Command_Loader();
	$command_loader->init();
}
