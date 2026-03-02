<?php
/**
 * Plugin Name:       Vercom Messageflow Plugin
 * Description:       Sends all WordPress emails through the MessageFlow transactional email API. Recruitment task for Vercom.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Michał Kita
 * Text Domain:       vercom-messageflow-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 */
define( 'VERCOM_PLUGIN_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'VERCOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'VERCOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename for action links.
 */
define( 'VERCOM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Load plugin classes.
 */
require_once VERCOM_PLUGIN_DIR . 'includes/class-vercom-api-client.php';
require_once VERCOM_PLUGIN_DIR . 'includes/class-vercom-email-handler.php';
require_once VERCOM_PLUGIN_DIR . 'includes/class-vercom-admin.php';
require_once VERCOM_PLUGIN_DIR . 'includes/class-vercom-plugin.php';

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 */
function vercom_plugin_init() {
	new Vercom_Plugin();
}
add_action( 'plugins_loaded', 'vercom_plugin_init' );
