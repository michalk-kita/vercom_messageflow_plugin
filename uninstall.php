<?php
/**
 * Uninstall script.
 *
 * Removes all plugin data from the database when the plugin is deleted
 * through the WordPress admin interface.
 *
 * @since   1.0.0
 * @package Vercom_Messageflow_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'vercom_plugin_settings' );
