<?php
/**
 * MessageFlow API Client.
 *
 * Handles authenticated HTTP requests to the MessageFlow REST API
 * using the WordPress HTTP API (wp_remote_post).
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Vercom_API_Client
 *
 * @since 1.0.0
 */
class Vercom_API_Client {

	/**
	 * MessageFlow API base URL.
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.messageflow.com/v2.1';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 30;

	/**
	 * Send an email through the MessageFlow API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $payload Email data formatted for the MessageFlow API.
	 * @return array {
	 *     Normalized response.
	 *
	 *     @type bool   $success     Whether the request succeeded.
	 *     @type int    $status_code HTTP status code (0 on network error).
	 *     @type string $body        Raw response body.
	 *     @type string $error       Error message (empty on success).
	 * }
	 */
	public function send_email( array $payload ): array {
		$headers = $this->get_headers();

		if ( empty( $headers['Authorization'] ) || empty( $headers['Application-Key'] ) ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'body'        => '',
				'error'       => __( 'MessageFlow API credentials are not configured.', 'vercom-messageflow-plugin' ),
			);
		}

		$response = wp_remote_post(
			self::API_BASE_URL . '/email',
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => self::TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'body'        => '',
				'error'       => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success'     => false,
				'status_code' => $status_code,
				'body'        => $body,
				'error'       => $this->parse_api_error( $body, $status_code ),
			);
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
			'body'        => $body,
			'error'       => '',
		);
	}

	/**
	 * Build authentication headers from stored settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array HTTP headers for the API request.
	 */
	private function get_headers(): array {
		$options = get_option( 'vercom_plugin_settings', array() );

		return array(
			'Authorization'   => $options['api_token'] ?? '',
			'Application-Key' => $options['application_key'] ?? '',
			'Content-Type'    => 'application/json',
		);
	}

	/**
	 * Parse a human-readable error message from the API response.
	 *
	 * MessageFlow API returns errors in format:
	 * { "errors": [{ "title": "...", "message": "...", "code": "..." }] }
	 *
	 * @since 1.0.0
	 *
	 * @param string $body        Raw response body.
	 * @param int    $status_code HTTP status code.
	 * @return string Formatted error message.
	 */
	private function parse_api_error( string $body, int $status_code ): string {
		$decoded = json_decode( $body, true );

		if ( is_array( $decoded ) && ! empty( $decoded['errors'][0]['message'] ) ) {
			$api_message = $decoded['errors'][0]['message'];
			$error       = sprintf(
				'MessageFlow API error (HTTP %d): %s',
				$status_code,
				$api_message
			);

			if ( false !== stripos( $api_message, 'is not authorized' ) ) {
				$error .= ' — ' . __( 'Your sender domain is not authorized. Go to E-mail > Bezpieczenstwo nadawcy > Autoryzacja nadawcy > Autoryzacja domen in the MessageFlow panel and add the required DNS records (SPF, DKIM, DMARC).', 'vercom-messageflow-plugin' );
			}

			return $error;
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'MessageFlow API returned HTTP %d.', 'vercom-messageflow-plugin' ),
			$status_code
		);
	}
}
