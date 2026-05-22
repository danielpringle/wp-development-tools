<?php
/**
 * Command Loader Class
 *
 * Automatically discovers and registers all WP-CLI commands
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Command_Loader
 */
class Command_Loader {

	/**
	 * Commands directory path
	 *
	 * @var string
	 */
	private $commands_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Use __DIR__ since this file is in includes/, so commands/ is a subdirectory
		$this->commands_dir = __DIR__ . '/commands/';
	}

	/**
	 * Initialize command loading
	 *
	 * Only loads commands if WP-CLI is available
	 */
	public function init() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$this->load_commands();
	}

	/**
	 * Load all command files from the commands directory
	 */
	private function load_commands() {
		$command_files = glob( $this->commands_dir . 'class-*.php' );

		if ( ! $command_files ) {
			return;
		}

		foreach ( $command_files as $file ) {
			require_once $file;
		}
	}
}
