<?php
/**
 * DPUK CLI Command: rest:routes
 *
 * Lists registered WordPress REST API routes.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * List registered REST API routes.
	 */
	class DPUK_REST_Routes_Command extends WP_CLI_Command {

		/**
		 * Display registered REST API routes.
		 *
		 * ## OPTIONS
		 *
		 * [--namespace=<namespace>]
		 * : Only show routes belonging to the specified namespace.
		 *
		 * [--search=<term>]
		 * : Only show routes containing the specified term.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, csv, json, yaml.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk rest:routes
		 *     wp dpuk rest:routes --namespace=wp/v2
		 *     wp dpuk rest:routes --namespace=myplugin/v1
		 *     wp dpuk rest:routes --search=hotels
		 *     wp dpuk rest:routes --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$routes = rest_get_server()->get_routes();

			$namespace = $assoc_args['namespace'] ?? null;
			$search    = $assoc_args['search'] ?? null;
			$format    = $assoc_args['format'] ?? 'table';

			$rows = [];

			foreach ( $routes as $route => $handlers ) {
				$route_namespace = $this->get_namespace( $route );

				if ( $namespace && $route_namespace !== $namespace ) {
					continue;
				}

				if ( $search && false === stripos( $route, $search ) ) {
					continue;
				}

				$rows[] = [
					'namespace' => $route_namespace,
					'route'     => $route,
					'methods'   => $this->get_methods( $handlers ),
				];
			}

			if ( empty( $rows ) ) {
				WP_CLI::warning( 'No REST API routes matched the specified filters.' );
				return;
			}

			WP_CLI\Utils\format_items(
				$format,
				$rows,
				[
					'namespace',
					'route',
					'methods',
				]
			);

			if ( 'table' === $format ) {
				WP_CLI::line( '' );
				WP_CLI::line(
					sprintf(
						'%d REST API routes found.',
						count( $rows )
					)
				);
			}
		}

		/**
		 * Get the namespace from a REST route.
		 *
		 * @param string $route REST route.
		 * @return string REST namespace.
		 */
		private function get_namespace( string $route ): string {
			$parts = explode( '/', trim( $route, '/' ) );

			if ( count( $parts ) < 2 ) {
				return '';
			}

			return $parts[0] . '/' . $parts[1];
		}

		/**
		 * Get HTTP methods supported by a route.
		 *
		 * @param array $handlers Route handlers.
		 * @return string Comma-separated HTTP methods.
		 */
		private function get_methods( array $handlers ): string {
			$methods = [];

			foreach ( $handlers as $handler ) {
				if ( empty( $handler['methods'] ) ) {
					continue;
				}

				foreach ( $handler['methods'] as $method => $enabled ) {
					if ( $enabled && ! in_array( $method, $methods, true ) ) {
						$methods[] = $method;
					}
				}
			}

			return implode( ', ', $methods );
		}
	}

	WP_CLI::add_command(
		'dpuk rest:routes',
		DPUK_REST_Routes_Command::class
	);
}