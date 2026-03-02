<?php
/**
 * Admin Settings.
 *
 * Registers the plugin settings page under WordPress Settings menu,
 * handles settings registration via the Settings API, and provides
 * an AJAX endpoint for sending test emails.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Vercom_Admin
 *
 * @since 1.0.0
 */
class Vercom_Admin {

	/**
	 * Settings page hook suffix returned by add_options_page().
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * Register admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_vercom_send_test_email', array( $this, 'ajax_send_test_email' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . VERCOM_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add the settings page under the WordPress Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->page_hook = add_options_page(
			__( 'Vercom Messageflow Plugin Settings', 'vercom-messageflow-plugin' ),
			__( 'Vercom Messageflow Plugin', 'vercom-messageflow-plugin' ),
			'manage_options',
			'vercom-messageflow-plugin',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings, sections, and fields using the Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			'vercom_plugin_settings_group',
			'vercom_plugin_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		// --- API Credentials Section ---
		add_settings_section(
			'vercom_credentials_section',
			__( 'API Credentials', 'vercom-messageflow-plugin' ),
			array( $this, 'render_credentials_section' ),
			'vercom-messageflow-plugin'
		);

		add_settings_field(
			'vercom_application_key',
			__( 'Application Key', 'vercom-messageflow-plugin' ),
			array( $this, 'render_password_field' ),
			'vercom-messageflow-plugin',
			'vercom_credentials_section',
			array(
				'label_for'   => 'vercom-application-key',
				'field_key'   => 'application_key',
				'description' => __( 'Found in MessageFlow panel: Konto > Ustawienia > API > Nowy klucz API.', 'vercom-messageflow-plugin' ),
			)
		);

		add_settings_field(
			'vercom_api_token',
			__( 'Authorization Token', 'vercom-messageflow-plugin' ),
			array( $this, 'render_password_field' ),
			'vercom-messageflow-plugin',
			'vercom_credentials_section',
			array(
				'label_for'   => 'vercom-api-token',
				'field_key'   => 'api_token',
				'description' => __( 'Found in MessageFlow panel: Konto > Ustawienia > API > Nowy klucz API.', 'vercom-messageflow-plugin' ),
			)
		);

		// --- Email Configuration Section ---
		add_settings_section(
			'vercom_email_section',
			__( 'Email Configuration', 'vercom-messageflow-plugin' ),
			array( $this, 'render_email_section' ),
			'vercom-messageflow-plugin'
		);

		add_settings_field(
			'vercom_smtp_account',
			__( 'SMTP Account', 'vercom-messageflow-plugin' ),
			array( $this, 'render_text_field' ),
			'vercom-messageflow-plugin',
			'vercom_email_section',
			array(
				'label_for'   => 'vercom-smtp-account',
				'field_key'   => 'smtp_account',
				'placeholder' => '1.yourdomain.smtp',
				'description' => __( 'Found in MessageFlow panel: E-mail > E-mail API > Ustawienia > Konta SMTP.', 'vercom-messageflow-plugin' ),
			)
		);

		add_settings_field(
			'vercom_from_email',
			__( 'Default From Email', 'vercom-messageflow-plugin' ),
			array( $this, 'render_email_field' ),
			'vercom-messageflow-plugin',
			'vercom_email_section',
			array(
				'label_for'   => 'vercom-from-email',
				'field_key'   => 'from_email',
				'placeholder' => 'contact@yourdomain.com',
				'description' => __( 'Default sender address. Leave empty to use the WordPress admin email.', 'vercom-messageflow-plugin' ),
			)
		);

		add_settings_field(
			'vercom_from_name',
			__( 'Default From Name', 'vercom-messageflow-plugin' ),
			array( $this, 'render_text_field' ),
			'vercom-messageflow-plugin',
			'vercom_email_section',
			array(
				'label_for'   => 'vercom-from-name',
				'field_key'   => 'from_name',
				'placeholder' => 'My Company',
				'description' => __( 'Default sender name. Leave empty to use the site name.', 'vercom-messageflow-plugin' ),
			)
		);
	}

	/**
	 * Sanitize settings before saving to the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw settings input from the form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		return array(
			'api_token'       => sanitize_text_field( $input['api_token'] ?? '' ),
			'application_key' => sanitize_text_field( $input['application_key'] ?? '' ),
			'smtp_account'    => sanitize_text_field( $input['smtp_account'] ?? '' ),
			'from_email'      => sanitize_email( $input['from_email'] ?? '' ),
			'from_name'       => sanitize_text_field( $input['from_name'] ?? '' ),
		);
	}

	/**
	 * Render the settings page by including the view template.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include VERCOM_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render the API credentials section description.
	 *
	 * @since 1.0.0
	 */
	public function render_credentials_section(): void {
		printf(
			'<p>%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>.</p>',
			esc_html__( 'Both keys can be found in the MessageFlow panel: Konto > Ustawienia > API > Nowy klucz API.', 'vercom-messageflow-plugin' ),
			esc_url( 'https://app.messageflow.com' ),
			esc_html__( 'Open MessageFlow panel', 'vercom-messageflow-plugin' )
		);
		printf(
			'<div class="notice notice-info inline" style="margin: 10px 0;"><p><strong>&#9432; %s</strong> %s</p></div>',
			esc_html__( 'Important:', 'vercom-messageflow-plugin' ),
			esc_html__( 'When creating the API key, make sure to select the "E-mail API" permission. Without it, the key will not be authorized to send emails. Newly generated keys may need a few minutes to become active.', 'vercom-messageflow-plugin' )
		);
	}

	/**
	 * Render the email configuration section description.
	 *
	 * @since 1.0.0
	 */
	public function render_email_section(): void {
		echo '<p>' . esc_html__( 'Configure the default sender details for outgoing emails.', 'vercom-messageflow-plugin' ) . '</p>';
		printf(
			'<div class="notice notice-warning inline" style="margin: 10px 0;"><p><strong>&#9888; %s</strong> %s<br />%s</p></div>',
			esc_html__( 'Domain authorization required!', 'vercom-messageflow-plugin' ),
			esc_html__( 'Emails will not be sent without an authorized sender domain.', 'vercom-messageflow-plugin' ),
			esc_html__(
				'Go to: E-mail > Bezpieczenstwo nadawcy > Autoryzacja nadawcy > Autoryzacja domen — add your domain and configure the required DNS records (SPF, DKIM, DMARC).',
				'vercom-messageflow-plugin'
			)
		);
	}

	/**
	 * Render a password input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments from add_settings_field().
	 */
	public function render_password_field( array $args ): void {
		$options = get_option( 'vercom_plugin_settings', array() );
		$value   = $options[ $args['field_key'] ] ?? '';

		printf(
			'<input type="password" id="%s" name="vercom_plugin_settings[%s]" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['field_key'] ),
			esc_attr( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render a text input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments from add_settings_field().
	 */
	public function render_text_field( array $args ): void {
		$options = get_option( 'vercom_plugin_settings', array() );
		$value   = $options[ $args['field_key'] ] ?? '';

		printf(
			'<input type="text" id="%s" name="vercom_plugin_settings[%s]" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['field_key'] ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] ?? '' )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render an email input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Field arguments from add_settings_field().
	 */
	public function render_email_field( array $args ): void {
		$options = get_option( 'vercom_plugin_settings', array() );
		$value   = $options[ $args['field_key'] ] ?? '';

		printf(
			'<input type="email" id="%s" name="vercom_plugin_settings[%s]" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr( $args['label_for'] ),
			esc_attr( $args['field_key'] ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] ?? '' )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Handle the AJAX request to send a test email.
	 *
	 * Sends a test email through wp_mail(), which is intercepted by
	 * the pre_wp_mail handler -- this tests the entire integration pipeline.
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_test_email(): void {
		check_ajax_referer( 'vercom_test_email', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'vercom-messageflow-plugin' ) )
			);
		}

		$to = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );

		if ( ! is_email( $to ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please enter a valid email address.', 'vercom-messageflow-plugin' ) )
			);
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Test Email from %s via MessageFlow', 'vercom-messageflow-plugin' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			'<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">'
			. '<h2 style="color: #333;">%s</h2>'
			. '<p style="color: #555; line-height: 1.6;">%s</p>'
			. '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;" />'
			. '<p style="color: #999; font-size: 12px;">%s</p>'
			. '</div>',
			esc_html__( 'MessageFlow Email Integration', 'vercom-messageflow-plugin' ),
			esc_html__( 'This test email confirms that your WordPress site is successfully sending emails through the MessageFlow API.', 'vercom-messageflow-plugin' ),
			esc_html(
				sprintf(
					/* translators: 1: site name, 2: date and time */
					__( 'Sent from %1$s on %2$s', 'vercom-messageflow-plugin' ),
					get_bloginfo( 'name' ),
					wp_date( 'Y-m-d H:i:s' )
				)
			)
		);

		// Capture the error message from wp_mail_failed action if sending fails.
		$last_error = '';
		$error_callback = function ( WP_Error $error ) use ( &$last_error ) {
			$last_error = $error->get_error_message();
		};
		add_action( 'wp_mail_failed', $error_callback );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$result  = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', $error_callback );

		if ( $result ) {
			wp_send_json_success(
				array( 'message' => __( 'Test email sent successfully!', 'vercom-messageflow-plugin' ) )
			);
		} else {
			$message = ! empty( $last_error )
				? $last_error
				: __( 'Failed to send test email. Please verify your API credentials and SMTP account.', 'vercom-messageflow-plugin' );

			wp_send_json_error( array( 'message' => $message ) );
		}
	}

	/**
	 * Enqueue admin CSS and JavaScript on the plugin settings page only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'vercom-admin',
			VERCOM_PLUGIN_URL . 'admin/css/vercom-admin.css',
			array(),
			VERCOM_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'vercom-admin',
			VERCOM_PLUGIN_URL . 'admin/js/vercom-admin.js',
			array( 'jquery' ),
			VERCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'vercom-admin',
			'vercom_admin',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'vercom_test_email' ),
				'sending_text' => __( 'Sending...', 'vercom-messageflow-plugin' ),
				'button_text'  => __( 'Send Test Email', 'vercom-messageflow-plugin' ),
				'error_text'   => __( 'An unexpected error occurred. Please try again.', 'vercom-messageflow-plugin' ),
			)
		);
	}

	/**
	 * Add a "Settings" link to the plugins list page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified action links with Settings prepended.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=vercom-messageflow-plugin' ) ),
			esc_html__( 'Settings', 'vercom-messageflow-plugin' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
