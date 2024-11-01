<?php

	/**
	* @Description : Plugin admin gateway class
	* @Package : Transfirst - WooCommerce Processing Gateway
	* @Author : #
	*/

	if ( ! defined( 'ABSPATH' ) || ! defined('WOO_TRANSFIRST_GATEWAY') ) {
		exit;
	}

	/**
	* Begin : WOO_transfirst_payment_gateway Class
	*/

	if( ! class_exists('WOO_transfirst_payment_gateway') ) {

		class WOO_transfirst_payment_gateway extends WC_Payment_Gateway_CC {

			// @var : Enable sandbox
			public $sandbox = false;

			// @var : Transfirst account API 'merchant_id'
			private $merchant_id = '';

			// @var : Transfirst account API 'hosted_key'
			private $hosted_key = '';

			// @var : Set gateway ID
			public static $gateway_id = 'woo-trans';

			// @var : Transfirst remote url
			public $transfirst_url = '';

			/**
			* Begin constructor
			*/

			public function __construct() {

				// Default of woocommerce
				$this->id					= self::$gateway_id;
				$this->icon					= apply_filters('woocommerce_offline_icon', '');
				$this->has_fields			= true;
				$this->method_title			= __('Transfirst','woo-transfirst-gateway');
				$this->method_description	= __('Pay with Transfirst gateway','woo-transfirst-gateway');

				//$this->supports( array('refunds') );

				// Load the settings
				$this->init_form_fields();

				// WooCommerce settings
				$this->init_settings();

				// Set WPN variable for this plugin
				$this->merchant_id = $this->get_option( 'merchant_id' );
				$this->hosted_key = $this->get_option( 'hosted_key' );
				$this->transfirst_url = $this->get_option('transfirst_url');

				// Define user set variables
				$this->title        = $this->get_option( 'title' );
				$this->description  = $this->get_option( 'description' );
				$this->instructions = $this->get_option( 'instructions' );

				add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'print_national_processing_receipt' ), 10, 1 );

				// register admin options
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

				// register ipn response handler
				add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_response' ) );

				// show admin notice
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}

			/**
			* Initialize options field for payment gateway
			*/

			public function init_form_fields() {

				$this->form_fields = array(
					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'woo-transfirst-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Transfirst Payment', 'woo-transfirst-gateway' ),
						'default' => 'yes'
					),
					'title' => array(
						'title'       => __( 'Title', 'woo-transfirst-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'woo-transfirst-gateway' ),
						'default'     => __( 'Transfirst', 'woo-transfirst-gateway' ),
						'desc_tip'    => true,
					),

					'description' => array(
						'title'       => __( 'Description', 'woo-transfirst-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-transfirst-gateway' ),
						'default'     => __( 'Pay with Transfirst gateway', 'woo-transfirst-gateway' ),
						'desc_tip'    => true,
					),
					'instructions' => array(
						'title'       => __( 'Instructions', 'woo-transfirst-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woo-transfirst-gateway' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					'merchant_id' => array(
						'title' => __( 'Merchant ID*', 'woo-transfirst-gateway' ),
						'type' => 'text',
						'description' => __( 'Enter Transfirst Merchat ID', 'woo-transfirst-gateway' )
					),
					'hosted_key' 		=> array(
						'title' 		=> __( 'Hosted Key*', 'woo-transfirst-gateway' ),
						'type' 			=> 'text',
						'description' 	=> __( 'Enter Transfirst Hosted Key', 'woo-transfirst-gateway' )
					),
					'transfirst_url' => array(
						'title' 		=> __( 'Transfirst URL', 'woo-transfirst-gateway' ),
						'type' 			=> 'text',
						'description' 	=> __( 'Enter Transfirst remote URL', 'woo-transfirst-gateway' ),
						'default'		=>	'https://webservices.primerchants.com/billing/TransactionCentral/processCC.asp?'
					),
				);

			}

			public function process_refunds() {

			}

			/**
			* Display payment fields form
			*/

			public function payment_fields() {
				if ( $description = $this->get_description() ) {
					echo wpautop( esc_html( wptexturize( $description ) ) );
				}

				// use credit/card form build in in WooCommerce
				if ( $this->has_fields() ) {
					parent::payment_fields();
				}
			}

			/**
			* Check payment details for valid format
			*/

			public function validate_fields() {

				// CC details
				$card_number         = preg_replace('/[\s]+/','', $this->get_post( 'woo-trans-card-number' ) );
				$card_csv            = $this->get_post( 'woo-trans-card-cvc' );
				$card_expiration	 = $this->get_post( 'woo-trans-card-expiry' );

				$cc_exp = array_map ( 'trim',  explode( '/', $card_expiration ) );
				$current_year = date( 'y' );

				// Check card number
				if ( empty( $card_number ) || ! ctype_digit( $card_number ) ) {
					wc_add_notice( __( 'Card number is invalid.', 'woo-transfirst-gateway' ), 'error' );
					return false;
				}

				// Check expiration
				if( ! ctype_digit ( $cc_exp[0] ) || ! ctype_digit ( $cc_exp[1] )
					|| $cc_exp[0] < 1
					|| $cc_exp[0] > 12
					|| $cc_exp[1] < $current_year
					|| $cc_exp[1] > $current_year + 20 ) {
						wc_add_notice( __( 'Card expiration date is invalid', 'woo-transfirst-gateway' ), 'error' );
						return false;
				}

				// Check security code
				if ( ! ctype_digit( $card_csv ) ) {
					wc_add_notice( __( 'Card security code is invalid (only digits are allowed).', 'woo-transfirst-gateway' ), $notice_type = 'error' );
					return false;
				}

				// Check CSV card length
				if ( ( strlen( $card_csv ) > 4  ) ) {
					wc_add_notice( __( 'Card security code is invalid (wrong length).', 'woo-transfirst-gateway' ),'error' );
					return false;
				}

				return true;
			}

			/**
			*  Process the payment and return the result.
			*/

			public function process_payment( $order_id ) {
				global $woocommerce;

				// Get order details (object)
				$order = new WC_Order( $order_id );

				// Make sure Merchant ID and Hosted/Reg Key supplied.
				if( empty ( $this->merchant_id ) || empty ( $this->hosted_key )  ) {
					wc_add_notice( __('Payment error : ', 'woo-transfirst-gateway') . ' API `Merchant ID` and `Hosted Key` not set.', 'error' );
					return;
				}

				// CC details
				$card_expiration	 = $this->get_post( 'woo-trans-card-expiry' );
				$cc_exp = array_map ( 'trim',  explode( '/', $card_expiration ) );
				$cc_cvv = $this->get_post( 'woo-trans-card-cvc' );

				$cc_card = array(
					'cc_number'			=>	preg_replace('/[\s]+/','', $this->get_post( 'woo-trans-card-number' ) ),
					'cc_exp_month'		=>	$cc_exp[0],
					'cc_exp_year'		=>	$cc_exp[1],
				);

				// Transfirst Gateway Options
				$gateway_options = array();

				// Docs : http://www.merchantanywhere.com/ecshop/tc%20interface%20new.pdf ( Page 6 )
				$gateway_options = array(
					'MerchantID'	=> 	$this->merchant_id,
					'RegKey'		=> 	$this->hosted_key,
					'Amount' 		=> 	$order->get_total(),
					'RefID'			=> 	$order_id,
					'AccountNo'		=> 	$cc_card['cc_number'],
					'CCMonth'		=> 	$cc_card['cc_exp_month'],
					'CCYear'		=> 	$cc_card['cc_exp_year'],
					'CVV2'			=> 	$cc_cvv,
					'NameonAccount' => 	$order->get_billing_first_name(). ' ' . $order->get_billing_last_name(),
					'AVSADDR' 		=> 	$order->get_billing_address_1(),
					'AVSZIP' 		=> 	$order->get_billing_postcode(),
					'CCRURL'		=> 	'', // URL to redirect after processing transaction
					'ShipToZipCode' => 	! empty( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $order->get_billing_postcode()
				);

				// Set remote options
				$options = array(
					'timeout' 		=> 10,
					'body' 			=> $gateway_options,
					'user-agent' 	=> 'WooCommerce ' . $woocommerce->version . " " . get_bloginfo( 'url' ),
					'sslverify' 	=> false
				);

				// Send request to transfirst url remotely
				$response = wp_remote_post( $this->transfirst_url, $options );

				// Check result if there's an error
				if( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					wc_add_notice( __('Payment error : ', 'woo-transfirst-gateway') . "$error_message", 'error' );
					return;
				}

				// Remove all html tags
				$response = wp_strip_all_tags( $response['body'] );

				// Parse string text into array
				wp_parse_str( $response, $results );

				// process results
				if( $results['Auth'] == 'Declined' || $results['Notes'] != '' ) {
					wc_add_notice( __('Transaction failed : ', 'woo-transfirst-gateway') . ' ' .  esc_html( $results['Notes'] ), 'error' );
					return;
				}else {
					// Complete order if payment success then we complete the order.
					$order->payment_complete();

					// Return thank you page redirect
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
			}

			/**
			* Display admin notices
			*/

			public function admin_notices() {

			}

			/**
			* Get post data if set
			*/

			protected function get_post( $name ) {
				if ( isset( $_POST[ $name ] ) ) {
					return sanitize_text_field( $_POST[ $name ] );
				}
				return null;
			}
		}

		/**
		* Add the gateway to WC Available Gateways
		*/

		function WOO_transfirst_register_gateway( $methods ) {
			$methods[] = 'WOO_transfirst_payment_gateway';
			return $methods;
		}

		add_filter( 'woocommerce_payment_gateways', 'WOO_transfirst_register_gateway' );
	}