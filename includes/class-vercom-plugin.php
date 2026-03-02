<?php
/**
 * Plugin orchestrator.
 *
 * Wires together all plugin components: API client, email handler,
 * and admin settings. Acts as the composition root with dependency injection.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Vercom_Plugin
 *
 * @since 1.0.0
 */
class Vercom_Plugin {

	/**
	 * Email handler instance.
	 *
	 * @var Vercom_Email_Handler
	 */
	private Vercom_Email_Handler $email_handler;

	/**
	 * Initialize plugin components and register hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$api_client          = new Vercom_API_Client();
		$this->email_handler = new Vercom_Email_Handler( $api_client );

		if ( is_admin() ) {
			$admin = new Vercom_Admin();
			$admin->register();
		}

		// Only intercept emails when the plugin is fully configured.
		if ( $this->is_configured() ) {
			$this->email_handler->register();
		}

		add_action( 'wp_mail_failed', array( $this, 'log_mail_failure' ) );
	}

	/**
	 * Check if the plugin has all required settings configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if API token, application key, and SMTP account are set.
	 */
	private function is_configured(): bool {
		$options = get_option( 'vercom_plugin_settings', array() );

		return ! empty( $options['api_token'] )
			&& ! empty( $options['application_key'] )
			&& ! empty( $options['smtp_account'] );
	}

	/**
	 * Log email send failures when WP_DEBUG is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error The error object from the wp_mail_failed action.
	 */
	public function log_mail_failure( WP_Error $error ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[Vercom Messageflow Plugin] Send failed: %s',
					$error->get_error_message()
				)
			);
		}
	}
}
