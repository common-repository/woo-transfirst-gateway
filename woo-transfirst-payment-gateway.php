<?php

	/**
	* Plugin Name: Transfirst Gateway for WooCommerce
	* Plugin URI: http://codedropz.com
	* Description: This plugin allows you to add "Transfirst" 'transaction central' payment gateway to Wordpress WooCommerce.
	* Version: 1.0
	* Author: Glen Don L. Mongaya
	* Author URI: https://profiles.wordpress.org/glenwpcoder
	* License: GPL2
	**/

	/**  This protect the plugin file from direct access */
	if ( ! defined( 'WPINC' ) ) {
		die;
	}

	/** Set plugin constant to true **/
	define( 'WOO_TRANSFIRST_GATEWAY', true );

	/**  Define plugin Version */
	define( 'WOO_TRANSFIRST_VERSION', '1.0' );

	/**  Define constant Plugin Directories  */
	define( 'WOO_TRANSFIRST_DIRECTORY', untrailingslashit( dirname( __FILE__ ) ) );

	// require plugin core file
	require_once( WOO_TRANSFIRST_DIRECTORY .'/inc/woo_transfirst_init.php' );

	// run the plugin
	WOO_transfirst_init::get_instance();

	// Execute when this plugin is activated ( Save default options, meta etc )
	register_activation_hook( __FILE__, array( 'WOO_transfirst_init', 'install' ) );