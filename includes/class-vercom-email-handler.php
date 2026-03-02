<?php
/**
 * Email Handler.
 *
 * Intercepts WordPress wp_mail() calls via the pre_wp_mail filter
 * and sends emails through the MessageFlow transactional API.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Vercom_Email_Handler
 *
 * @since 1.0.0
 */
class Vercom_Email_Handler {

	/**
	 * API client instance.
	 *
	 * @var Vercom_API_Client
	 */
	private Vercom_API_Client $api_client;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Vercom_API_Client $api_client API client instance.
	 */
	public function __construct( Vercom_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Register the pre_wp_mail filter to intercept emails.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_filter( 'pre_wp_mail', array( $this, 'intercept_email' ), 10, 2 );
	}

	/**
	 * Intercept wp_mail() and send via MessageFlow API.
	 *
	 * Returning a non-null value from this filter short-circuits wp_mail()
	 * completely, bypassing PHPMailer.
	 *
	 * @since 1.0.0
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts   {
	 *     Array of wp_mail() arguments.
	 *
	 *     @type string|array $to          Recipients.
	 *     @type string       $subject     Subject line.
	 *     @type string       $message     Message body.
	 *     @type string|array $headers     Email headers.
	 *     @type string|array $attachments File paths to attach.
	 * }
	 * @return bool Whether the email was sent successfully.
	 */
	public function intercept_email( $return, array $atts ): bool {
		// Respect other filters that already handled the email.
		if ( null !== $return ) {
			return (bool) $return;
		}

		$payload = $this->transform_to_payload( $atts );
		$result  = $this->api_client->send_email( $payload );

		if ( ! $result['success'] ) {
			do_action(
				'wp_mail_failed',
				new WP_Error(
					'vercom_send_failed',
					$result['error'],
					$atts
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Transform wp_mail() arguments into a MessageFlow API payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts The wp_mail() arguments.
	 * @return array Payload formatted for the MessageFlow API.
	 */
	private function transform_to_payload( array $atts ): array {
		$options = get_option( 'vercom_plugin_settings', array() );
		$parsed  = $this->parse_headers( $atts['headers'] ?? '' );

		// Determine from email: header > plugin setting > wp filter > WP admin email.
		$default_from_email = ! empty( $options['from_email'] )
			? $options['from_email']
			: get_option( 'admin_email' );
		$from_email = $parsed['from_email']
			?? apply_filters( 'wp_mail_from', $default_from_email );

		// Determine from name: header > plugin setting > wp filter > site name.
		$default_from_name = ! empty( $options['from_name'] )
			? $options['from_name']
			: get_option( 'blogname' );
		$from_name = $parsed['from_name']
			?? apply_filters( 'wp_mail_from_name', $default_from_name );

		// Determine content type: header > wp filter > default plain text.
		$content_type = $parsed['content_type']
			?? apply_filters( 'wp_mail_content_type', 'text/plain' );

		// Build content based on detected content type.
		$content = array();
		if ( 'text/html' === $content_type ) {
			$content['html'] = $atts['message'];
			$content['text'] = wp_strip_all_tags( $atts['message'] );
		} else {
			$content['text'] = $atts['message'];
		}

		$payload = array(
			'subject'     => $atts['subject'],
			'smtpAccount' => $options['smtp_account'] ?? '',
			'from'        => array(
				'email' => $from_email,
				'name'  => $from_name,
			),
			'to'          => $this->parse_recipients( $atts['to'] ),
			'content'     => $content,
		);

		if ( ! empty( $parsed['reply_to'] ) ) {
			$payload['replyTo'] = $parsed['reply_to'];
		}

		if ( ! empty( $parsed['cc'] ) ) {
			$payload['cc'] = $this->parse_recipients( $parsed['cc'] );
		}

		if ( ! empty( $parsed['bcc'] ) ) {
			$payload['bcc'] = $this->parse_recipients( $parsed['bcc'] );
		}

		if ( ! empty( $parsed['custom_headers'] ) ) {
			$payload['headers'] = $parsed['custom_headers'];
		}

		if ( ! empty( $atts['attachments'] ) ) {
			$attachments = $this->process_attachments( $atts['attachments'] );
			if ( ! empty( $attachments ) ) {
				$payload['attachments'] = $attachments;
			}
		}

		return $payload;
	}

	/**
	 * Parse WordPress email headers into structured data.
	 *
	 * WordPress passes headers as a string (newline-separated) or an array.
	 * This method extracts From, Reply-To, CC, BCC, Content-Type, and custom headers.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $raw_headers Raw headers from wp_mail().
	 * @return array Parsed header data.
	 */
	private function parse_headers( $raw_headers ): array {
		$parsed = array(
			'content_type'   => null,
			'from_email'     => null,
			'from_name'      => null,
			'reply_to'       => null,
			'cc'             => array(),
			'bcc'            => array(),
			'custom_headers' => array(),
		);

		if ( empty( $raw_headers ) ) {
			return $parsed;
		}

		// Normalize headers to an array of "Name: Value" strings.
		if ( is_string( $raw_headers ) ) {
			$raw_headers = explode( "\n", str_replace( "\r\n", "\n", $raw_headers ) );
		}

		foreach ( $raw_headers as $header ) {
			$header = trim( $header );
			if ( empty( $header ) ) {
				continue;
			}

			$parts = explode( ':', $header, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}

			$name  = strtolower( trim( $parts[0] ) );
			$value = trim( $parts[1] );

			switch ( $name ) {
				case 'from':
					$address = $this->parse_email_address( $value );
					if ( $address ) {
						$parsed['from_email'] = $address['email'];
						if ( ! empty( $address['name'] ) ) {
							$parsed['from_name'] = $address['name'];
						}
					}
					break;

				case 'reply-to':
					$address = $this->parse_email_address( $value );
					if ( $address ) {
						$parsed['reply_to'] = $address;
					}
					break;

				case 'cc':
					$parsed['cc'] = array_merge(
						$parsed['cc'],
						array_map( 'trim', explode( ',', $value ) )
					);
					break;

				case 'bcc':
					$parsed['bcc'] = array_merge(
						$parsed['bcc'],
						array_map( 'trim', explode( ',', $value ) )
					);
					break;

				case 'content-type':
					$type_parts             = explode( ';', $value );
					$parsed['content_type'] = strtolower( trim( $type_parts[0] ) );
					break;

				default:
					// Preserve custom headers (e.g., X-Mailer, X-Priority).
					$original_name                              = trim( $parts[0] );
					$parsed['custom_headers'][ $original_name ] = $value;
					break;
			}
		}

		return $parsed;
	}

	/**
	 * Parse an email address string into name and email components.
	 *
	 * Handles formats: "Name <email@example.com>" and "email@example.com".
	 *
	 * @since 1.0.0
	 *
	 * @param string $address Raw email address string.
	 * @return array|null Array with 'email' and 'name' keys, or null on failure.
	 */
	private function parse_email_address( string $address ): ?array {
		$address = trim( $address );

		if ( preg_match( '/^(.+?)\s*<([^>]+)>$/', $address, $matches ) ) {
			$name  = trim( $matches[1], " \t\n\r\0\x0B\"'" );
			$email = trim( $matches[2] );

			if ( is_email( $email ) ) {
				return array(
					'email' => $email,
					'name'  => $name,
				);
			}
		}

		if ( is_email( $address ) ) {
			return array(
				'email' => $address,
				'name'  => '',
			);
		}

		return null;
	}

	/**
	 * Parse recipients into MessageFlow API format.
	 *
	 * Handles WordPress recipient formats: comma-separated string or array.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $recipients Raw recipients from wp_mail().
	 * @return array Array of recipient objects for the API.
	 */
	private function parse_recipients( $recipients ): array {
		if ( is_string( $recipients ) ) {
			$recipients = explode( ',', $recipients );
		}

		if ( ! is_array( $recipients ) ) {
			return array();
		}

		$result = array();

		foreach ( $recipients as $recipient ) {
			$address = $this->parse_email_address( trim( $recipient ) );
			if ( $address ) {
				$entry = array( 'email' => $address['email'] );
				if ( ! empty( $address['name'] ) ) {
					$entry['name'] = $address['name'];
				}
				$result[] = $entry;
			}
		}

		return $result;
	}

	/**
	 * Process file attachments for the MessageFlow API.
	 *
	 * Reads files from filesystem paths, base64-encodes content,
	 * and detects MIME types using wp_check_filetype().
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $attachments File paths from wp_mail().
	 * @return array Array of attachment objects for the API.
	 */
	private function process_attachments( $attachments ): array {
		if ( is_string( $attachments ) ) {
			$attachments = array( $attachments );
		}

		if ( ! is_array( $attachments ) ) {
			return array();
		}

		$result    = array();
		$max_bytes = 10 * 1024 * 1024; // 10 MB per file safety limit.

		foreach ( $attachments as $file_path ) {
			$file_path = trim( $file_path );

			if ( empty( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			$file_size = filesize( $file_path );
			if ( false === $file_size || $file_size > $max_bytes ) {
				continue;
			}

			$content = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				continue;
			}

			$file_type = wp_check_filetype( basename( $file_path ) );

			$result[] = array(
				'fileNames'   => basename( $file_path ),
				'fileMime'    => $file_type['type'] ?? 'application/octet-stream',
				'fileContent' => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		return $result;
	}
}
