<?php
/**
 * DPUK CLI Command: rest:check
 *
 * Performs an HTTP GET request against a WordPress REST API endpoint
 * and reports useful diagnostic information about the response.
 *
 * @package DPUK\DevelopmentTools
 * @since 1.0.0
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Check a WordPress REST API endpoint.
	 */
	class DPUK_REST_Check_Command extends WP_CLI_Command {

		/**
		 * Check a REST API endpoint.
		 *
		 * ## OPTIONS
		 *
		 * <route>
		 * : REST API route to check, for example /wp/v2/posts.
		 *
		 * [--url=<url>]
		 * : Base URL to use instead of the current site's REST API URL.
		 *
		 * [--headers]
		 * : Display response headers.
		 *
		 * [--format=<format>]
		 * : Output format. Accepts table, json, yaml, csv.
		 * ---
		 * default: table
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp dpuk rest:check /wp/v2/posts
		 *     wp dpuk rest:check /myplugin/v1/hotels
		 *     wp dpuk rest:check /wp/v2/posts --headers
		 *     wp dpuk rest:check /wp/v2/posts --url=https://example.com
		 *     wp dpuk rest:check /wp/v2/posts --format=json
		 *
		 * @when after_wp_load
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function __invoke( array $args, array $assoc_args ): void {
			$route   = $args[0] ?? '';
			$format  = $assoc_args['format'] ?? 'table';
			$headers = isset( $assoc_args['headers'] );

			if ( '' === $route ) {
				WP_CLI::error( 'A REST API route is required.' );
			}

			if ( '/' !== $route[0] ) {
				$route = '/' . $route;
			}

			$url = $this->build_url(
				$route,
				$assoc_args['url'] ?? null
			);

			$start_time = microtime( true );

			$response = wp_remote_get(
				$url,
				[
					'timeout'     => 10,
					'redirection' => 5,
                    'sslverify'   => false,
				]
			);

			$response_time = microtime( true ) - $start_time;

			if ( is_wp_error( $response ) ) {
				$this->output_error(
					$url,
					$response_time,
					$response->get_error_message(),
					$format
				);

				return;
			}

			$status_code  = wp_remote_retrieve_response_code( $response );
			$status_text  = wp_remote_retrieve_response_message( $response );
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			$body         = wp_remote_retrieve_body( $response );
			$body_size    = strlen( $body );

			$json = json_decode( $body, true );

			$data = [
				'url'            => $url,
				'method'         => 'GET',
				'status'         => $status_code,
				'status_message' => $status_text,
				'response_time'  => round( $response_time * 1000, 2 ) . ' ms',
				'response_size'  => size_format( $body_size, 2 ),
				'content_type'   => $content_type ?: 'Unknown',
				'valid_json'     => JSON_ERROR_NONE === json_last_error() ? 'Yes' : 'No',
			];

			if ( is_array( $json ) ) {
				$data['response_type'] = wp_is_numeric_array( $json )
					? 'array'
					: 'object';

				if ( wp_is_numeric_array( $json ) ) {
					$data['items'] = count( $json );
				}
			}

			$rest_error = $this->get_rest_error( $json );

			if ( $rest_error ) {
				$data['error_code']    = $rest_error['code'];
				$data['error_message'] = $rest_error['message'];
			}

			if ( 'table' !== $format ) {
				WP_CLI\Utils\format_items(
					$format,
					[ $data ],
					array_keys( $data )
				);

				return;
			}

			$this->display_result( $data, $headers, $response );
		}

		/**
		 * Build the REST API URL.
		 *
		 * @param string      $route Route.
		 * @param string|null $base_url Optional base URL.
		 * @return string REST API URL.
		 */
		private function build_url( string $route, ?string $base_url ): string {
			if ( $base_url ) {
				return rtrim( $base_url, '/' ) . '/wp-json' . $route;
			}

			return rtrim( get_rest_url(), '/' ) . $route;
		}

		/**
		 * Display a human-readable result.
		 *
		 * @param array   $data     Response data.
		 * @param bool    $headers  Whether to display headers.
		 * @param array   $response HTTP response.
		 */
		private function display_result(
			array $data,
			bool $headers,
			array $response
		): void {
			WP_CLI::line( 'REST API Check' );
			WP_CLI::line( '' );

			WP_CLI::line( 'Endpoint' );
			WP_CLI::line( '  URL:            ' . $data['url'] );
			WP_CLI::line( '  Method:         ' . $data['method'] );
			WP_CLI::line( '' );

			WP_CLI::line( 'Response' );
			WP_CLI::line(
				sprintf(
					'  Status:         %d %s',
					$data['status'],
					$data['status_message']
				)
			);
			WP_CLI::line( '  Response time:  ' . $data['response_time'] );
			WP_CLI::line( '  Response size:  ' . $data['response_size'] );
			WP_CLI::line( '  Content-Type:   ' . $data['content_type'] );
			WP_CLI::line( '  Valid JSON:     ' . $data['valid_json'] );

			if ( isset( $data['response_type'] ) ) {
				WP_CLI::line( '  Response type:  ' . $data['response_type'] );
			}

			if ( isset( $data['items'] ) ) {
				WP_CLI::line( '  Items:          ' . $data['items'] );
			}

			if ( isset( $data['error_code'] ) ) {
				WP_CLI::line( '' );
				WP_CLI::line( 'REST Error' );
				WP_CLI::line( '  Code:           ' . $data['error_code'] );
				WP_CLI::line( '  Message:        ' . $data['error_message'] );
			}

			if ( $headers ) {
				WP_CLI::line( '' );
				WP_CLI::line( 'Headers' );

				foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
					WP_CLI::line(
						'  ' . $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value )
					);
				}
			}

			WP_CLI::line( '' );

            if ( $data['status'] >= 200 && $data['status'] < 300 ) {
                WP_CLI::success(
                    sprintf(
                        'REST API request succeeded with HTTP %d.',
                        $data['status']
                    )
                );

                return;
            }

            WP_CLI::error(
                sprintf(
                    'REST API request failed with HTTP %d.',
                    $data['status']
                )
            );
		}

		/**
		 * Extract a WordPress REST API error from a decoded response.
		 *
		 * @param mixed $json Decoded response.
		 * @return array|null REST error information.
		 */
		private function get_rest_error( $json ): ?array {
			if (
				! is_array( $json )
				|| empty( $json['code'] )
				|| empty( $json['message'] )
			) {
				return null;
			}

			return [
				'code'    => $json['code'],
				'message' => wp_strip_all_tags( $json['message'] ),
			];
		}

		/**
		 * Output a request error.
		 *
		 * @param string $url            Request URL.
		 * @param float  $response_time  Response time.
		 * @param string $message        Error message.
		 * @param string $format         Output format.
		 */
		private function output_error(
			string $url,
			float $response_time,
			string $message,
			string $format
		): void {
			$data = [
				'url'           => $url,
				'method'        => 'GET',
				'response_time' => round( $response_time * 1000, 2 ) . ' ms',
				'error'         => $message,
			];

			if ( 'table' !== $format ) {
				WP_CLI\Utils\format_items(
					$format,
					[ $data ],
					array_keys( $data )
				);

				return;
			}

			WP_CLI::error(
				sprintf(
					'REST API request failed: %s',
					$message
				)
			);
		}
	}

	WP_CLI::add_command(
		'dpuk rest:check',
		DPUK_REST_Check_Command::class
	);
}