<?php
/**
 * Admin settings page template.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options       = get_option( 'vercom_plugin_settings', array() );
$is_configured = ! empty( $options['api_token'] )
	&& ! empty( $options['application_key'] )
	&& ! empty( $options['smtp_account'] );
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( $is_configured ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'Vercom Messageflow Plugin is active. All WordPress emails are being sent through the MessageFlow API.', 'vercom-messageflow-plugin' ); ?></p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Please enter your API credentials and SMTP account to start sending emails through MessageFlow.', 'vercom-messageflow-plugin' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'vercom_plugin_settings_group' );
		do_settings_sections( 'vercom-messageflow-plugin' );
		submit_button( __( 'Save Settings', 'vercom-messageflow-plugin' ) );
		?>
	</form>

	<hr />
	<h2><?php esc_html_e( 'Send Test Email', 'vercom-messageflow-plugin' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Send a test email to verify that your MessageFlow integration is working correctly.', 'vercom-messageflow-plugin' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="vercom-test-email">
					<?php esc_html_e( 'Recipient Email', 'vercom-messageflow-plugin' ); ?>
				</label>
			</th>
			<td>
				<input type="email"
					   id="vercom-test-email"
					   class="regular-text"
					   placeholder="<?php esc_attr_e( 'Enter recipient email address', 'vercom-messageflow-plugin' ); ?>"
					   <?php echo $is_configured ? '' : 'disabled'; ?> />
				<button type="button"
						id="vercom-send-test"
						class="button button-secondary"
						<?php echo $is_configured ? '' : 'disabled'; ?>
						<?php if ( ! $is_configured ) : ?>
							title="<?php esc_attr_e( 'Fill in Authorization Token, Application Key and SMTP Account first, then save settings.', 'vercom-messageflow-plugin' ); ?>"
						<?php endif; ?>>
					<?php esc_html_e( 'Send Test Email', 'vercom-messageflow-plugin' ); ?>
				</button>
				<span id="vercom-test-result"></span>
				<?php if ( ! $is_configured ) : ?>
					<p class="description" style="color: #d63638;">
						<?php esc_html_e( 'Fill in Authorization Token, Application Key and SMTP Account above, then save settings to enable test email.', 'vercom-messageflow-plugin' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</div>
