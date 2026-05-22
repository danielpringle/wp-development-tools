<?php
/**
 * Base Command Class
 *
 * Provides common functionality for all CLI commands
 *
 * @package DPUKCLICommands
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Base Command Class
	 */
	abstract class Base_Command {

		/**
		 * Register the command
		 */
		abstract public function register();

		/**
		 * Log a message with timestamp
		 *
		 * @param string $message Message to log.
		 */
		protected function log( $message ) {
			WP_CLI::log( '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message );
		}

		/**
		 * Validate date format
		 *
		 * @param string $date Date string.
		 * @param string $format Date format.
		 * @return bool
		 */
		protected function validate_date( $date, $format = 'Y-m-d' ) {
			$d = \DateTime::createFromFormat( $format, $date );
			return $d && $d->format( $format ) === $date;
		}
	}
}
