<?php

	/**
	* @Description : Plugin main core class
	* @Package : WooCommerce - WooCommerce Transfirst Payment Gateway
	* @Author : #
	*/

	if ( ! defined( 'ABSPATH' ) || ! defined('WOO_TRANSFIRST_GATEWAY') ) {
		exit;
	}

	/**
	* Begin : WOO_transfirst_init Class
	*/

	if( ! class_exists('WOO_transfirst_init') ) {

		class WOO_transfirst_init {

			// @var : declare instance of the class
			protected static $instance = null;

			/**
			* Creates or returns an instance of this class.
			* @return  Init A single instance of this class.
			*/

			public static function get_instance() {
				if( null == self::$instance ) {
					self::$instance = new self;
				}
				return self::$instance;
			}

			/**
			* Load and initialize plugin
			*/

			public function __construct() {

				// Make sure WooCommerce installed or Active
				if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
					WOO_transfirst_init::error_log_notice();
				}

				// Load and setup our Plugin
				add_action('plugins_loaded', array( $this, 'WOO_transfirst_load_init') );
			}

			/**
			* Setup plugin and load files, assets, filter and administration functions
			*/

			public function WOO_transfirst_load_init() {

				// Set up localization.
				$this->WOO_transfirst_textdomain();

				// Register filter/plugin hooks
				$this->WOO_transfirst_hooks();

				// Load API php file.
				//$this->WOO_transfirst_load('WNP_gateway_API', 'api');

				// Get WooCommerce version
				if ( floor( WC_VERSION ) < 3 ) {
					// Load Old Payment Gateway Class
					$this->WOO_transfirst_load('WNP_payment_gateway_old', 'inc');
				} else {
					// Load Payment Gateway Class
					$this->WOO_transfirst_load('woo_transfirst_payment_gateway', 'inc');
				}

				// Plugin loaded action
				do_action( 'WNP_gateway_plugin_loaded' );
			}

			/**
			* Setup plugin and load file, assets, filter and administration functions
			*/

			public function WOO_transfirst_load( $file, $path = null ) {

				// Check Plugin directory is defined
				if ( ! defined('WOO_TRANSFIRST_DIRECTORY') || ! is_dir( WOO_TRANSFIRST_DIRECTORY ) ) {
					return false;
				}

				// Setup file path
				$file_path = WOO_TRANSFIRST_DIRECTORY .'/' .$path .'/'. $file .'.php';

				// Checks whether a file or directory exists
				if( file_exists( $file_path ) ) {

					// require and load requested file
					require_once( $file_path );

				}else {
					// @todo : log error
				}
			}

			/**
			* Load and initialize plugin
			*/

			private function WOO_transfirst_hooks() {}

			/**
			* Display Admin error/success Notice
			*/

			private static function error_log_notice() {
				add_action('admin_notices', function(){
					echo '<div class="notice notice-error"><p>'. __('Transfirst - WooCommerce Payment Gateway enabled but it requires WooCommerce in order to work.','woo-transfirst-gateway') .'</p></div>';
				});
			}

			/**
			* Setup and install settings/options or create database
			*/

			public static function install() {
				//@ todo : use this when needed.
			}

			/**
			* Load plugin translation file
			*/

			private function WOO_transfirst_textdomain() {
				load_plugin_textdomain( 'woo-transfirst-gateway', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
			}
		}
	}