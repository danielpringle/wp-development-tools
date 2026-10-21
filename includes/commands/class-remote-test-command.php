<?php

/**
 * A WP-CLI diagnostic command for testing remote HTTP endpoints using WordPress's HTTP API. 
 * It helps verify connectivity, authentication, response status, timing, headers, and body content, making it useful for debugging  REST APIs, Application Password authentication, and remote integrations.
 */

namespace DPUK\DevelopmentTools;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class DPUK_Remote_Test_Command extends WP_CLI_Command {

	/**
	 * Test a remote endpoint.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : Endpoint URL.
	 *
	 * [--url=<url>]
	 * : Endpoint URL (alternate form).
	 *
	 * [--remote-user=<user>]
	 * : Username for Application Password authentication.
	 *
	 * [--remote-password=<password>]
	 * : Application Password. Required when --remote-user is provided.
	 *
	 * [--timeout=<seconds>]
	 * : Request timeout.
	 * ---
	 * default: 15
	 * ---
	 *
	 * [--headers]
	 * : Display response headers.
	 *
	 * [--body]
	 * : Display response body.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dpuk remote:test \
	 *       --url=https://example.com/wp-json/my-plugin/v1/content
	 *
	 *     wp dpuk remote:test \
	 *       --url=https://example.com/wp-json/my-plugin/v1/content \
	 *       --remote-user=myuser
	 *
	 *     wp dpuk remote:test \
	 *       --url=https://example.com/wp-json/my-plugin/v1/content \
	 *       --remote-user=myuser \
	 *       --remote-password="abcd EFGH ijkl MNOP"
	 *
	 *     wp dpuk remote:test \
	 *       --url=https://example.com/wp-json/my-plugin/v1/ \
	 *       --remote-user=username \
	 *       --remote-password="your-application-password" \
	 *       --headers \
	 *       --body
	 *
	 * @when after_wp_load
     * @param array $args Positional arguments passed to the command.
     * @param array $assoc_args Associative arguments (CLI flags).
	 */
	public function __invoke( $args, $assoc_args ) {

		$url = $assoc_args['url'] ?? ( $args[0] ?? null );

		if ( empty( $url ) && ! empty( $args ) ) {
			foreach ( $args as $arg ) {
				if ( ! is_string( $arg ) ) {
					continue;
				}

                // Normalise typographic dashes (–, —) to "-" to prevent broken CLI arguments
                // when commands are copied from rich text sources (Docs, email, etc.).
				$normalized_arg = str_replace( array( '—', '–' ), '-', $arg );

				if ( 0 === strpos( $normalized_arg, '--url=' ) ) {
					$url = substr( $normalized_arg, 6 );
					break;
				}
			}
		}

		if ( empty( $url ) && ! empty( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) ) {
			$argv = $_SERVER['argv'];
			$argc = count( $argv );

			for ( $i = 0; $i < $argc; $i++ ) {
				if ( ! is_string( $argv[ $i ] ) ) {
					continue;
				}

				$normalized_arg = str_replace( array( '—', '–' ), '-', $argv[ $i ] );

				if ( 0 === strpos( $normalized_arg, '--url=' ) ) {
					$url = substr( $normalized_arg, 6 );
					break;
				}

				if ( '--url' === $normalized_arg && isset( $argv[ $i + 1 ] ) && is_string( $argv[ $i + 1 ] ) ) {
					$url = $argv[ $i + 1 ];
					break;
				}
			}
		}

		if ( empty( $url ) ) {
			WP_CLI::error( 'Missing required URL. Use <url> or --url=<url>.' );
		}

		$user     = $assoc_args['remote-user'] ?? null;
		$password = $assoc_args['remote-password'] ?? null;
		$timeout  = (int) ( $assoc_args['timeout'] ?? 15 );

		$request_args = [
			'timeout'   => $timeout,
			'sslverify' => false,
		];

		if ( $user ) {

			if ( ! $password ) {
				WP_CLI::error( 'When using --remote-user, also provide --remote-password.' );
			}

			$request_args['headers']['Authorization'] =
				'Basic ' . base64_encode( "{$user}:{$password}" );
		}

		WP_CLI::log( sprintf( 'URL: %s', $url ) );
		WP_CLI::log( sprintf( 'Authenticated: %s', $user ? 'yes' : 'no' ) );

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( $host ) {
			WP_CLI::log(
				sprintf(
					'Host resolves to: %s',
					gethostbyname( $host )
				)
			);
		}

		$start = microtime( true );

		$response = wp_remote_get( $url, $request_args );

		$elapsed = round( microtime( true ) - $start, 3 );

		WP_CLI::log( sprintf( 'Elapsed: %ss', $elapsed ) );

		if ( is_wp_error( $response ) ) {

			WP_CLI::error_multi_line(
				[
					'WP_Error encountered:',
					'',
					'Codes:',
					implode( ', ', $response->get_error_codes() ),
					'',
					'Messages:',
					implode( PHP_EOL, $response->get_error_messages() ),
				]
			);

			return;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$reason = wp_remote_retrieve_response_message( $response );

		WP_CLI::success(
			sprintf(
				'HTTP %d %s',
				$status,
				$reason
			)
		);

		if ( isset( $assoc_args['headers'] ) ) {

			WP_CLI::log( '' );
			WP_CLI::log( 'Headers:' );

			$headers = wp_remote_retrieve_headers( $response );

			foreach ( $headers as $key => $value ) {
				WP_CLI::log( "{$key}: {$value}" );
			}
		}

		if ( isset( $assoc_args['body'] ) ) {

			WP_CLI::log( '' );
			WP_CLI::log( 'Body:' );

			WP_CLI::line(
				wp_remote_retrieve_body( $response )
			);
		}
	}
}

WP_CLI::add_command(
	'dpuk remote:test',
	__NAMESPACE__ . '\\DPUK_Remote_Test_Command'
);