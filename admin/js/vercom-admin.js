/**
 * Vercom Messageflow Plugin admin scripts.
 *
 * Handles the AJAX test email functionality on the settings page.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

( function( $ ) {
	'use strict';

	$( '#vercom-send-test' ).on( 'click', function() {
		var $button = $( this );
		var $result = $( '#vercom-test-result' );
		var email   = $( '#vercom-test-email' ).val();

		$button.prop( 'disabled', true ).text( vercom_admin.sending_text );
		$result.removeClass( 'vercom-success vercom-error' ).text( '' );

		$.post( vercom_admin.ajax_url, {
			action:     'vercom_send_test_email',
			nonce:      vercom_admin.nonce,
			test_email: email,
		} )
			.done( function( response ) {
				if ( response.success ) {
					$result.addClass( 'vercom-success' ).text( response.data.message );
				} else {
					$result.addClass( 'vercom-error' ).text( response.data.message );
				}
			} )
			.fail( function() {
				$result.addClass( 'vercom-error' ).text( vercom_admin.error_text );
			} )
			.always( function() {
				$button.prop( 'disabled', false ).text( vercom_admin.button_text );
			} );
	} );
}( jQuery ) );
