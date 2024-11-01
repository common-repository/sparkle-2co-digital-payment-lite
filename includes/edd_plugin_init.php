<?php 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.

class Sparkle_2CO_DP_EDD{
	protected static $instance  = null;

	/**
	 * Class constructor
	 * Since 1.0.0
	*/
	public function __construct() {
		add_action( 'edd_s2coedd_payment_cc_form', array( $this, 'sparkle_edd_2co_s2coedd_payment_cc_form' ) );

		add_filter( 'sparkle_edd_2checkout_label', array( $this, 'sparkle_edd_2co_label' ), 10, 3 );
		add_filter( 'edd_payment_gateways', array( $this, 'sparkle_edd_add_2co_payment_checkbox' ) );
		add_filter( 'edd_accepted_payment_icons', array( $this, 'sparkle_edd_add_2co_payment_icons' ) );
		add_filter( 'edd_settings_sections_gateways', array( $this, 'sparkle_edd_2co_add_settings_section') );
		add_filter( 'edd_settings_gateways', array( $this, 'sparkle_edd_2co_payment_add_settings' ) );
		add_action( 'edd_gateway_s2coedd_payment', array( $this, 'edd_2co_process_payment' ) );

		add_action( 'wp', array( $this, 'edd_payment_wait' ) );

		//ipn payment notification
		add_action( 'init', array( $this, 'edd_listen_for_2co_ipn' ) );
		
		//ins payment notification
		add_action( 'init', array( $this, 'edd_listen_for_2co_ins' ) );

	}

	/** 
	 * Class instance
	 * @since 1.0.0
	 * @return instance of a class
	 */
	public static function get_instance(){
		if( null === self:: $instance ){
			self:: $instance = new self;
		}

		return self:: $instance;
	}

	/**
	 * remove the Credit Card Info form as it will be entered in 2checkout form.
	 * @since 1.0.0
	 * @return string
	*/
	function sparkle_edd_2co_s2coedd_payment_cc_form(){
		// register the action to remove default CC form
		return;
	}
	
	/**
	 * Returns 2checkout radio button label
	 * @since 1.0.0
	 * @return string
	 */
	function sparkle_edd_2co_label( $string ) {
		$options = get_option( 'edd_settings' );
		$string  = ( isset( $options['sparkle-2co-dp-payment-2checkout-radio-button-label'] ) && $options['sparkle-2co-dp-payment-2checkout-radio-button-label'] !='') ? $options['sparkle-2co-dp-payment-2checkout-radio-button-label'] : $string;
		return esc_html( $string );
	}

	/**
	 * Defaults for 2checkout gateways admin label and checkout label. Checkout label will be filter out using apply_filters.
	 * @param  [array] $gateways
	 * @since 1.0.0
	 * @return array
	 */
	function sparkle_edd_add_2co_payment_checkbox( $gateways ){
		$gateways['s2coedd_payment'] = array(
											'admin_label'    => esc_html__( '2Checkout(Sparkle) - Standard', 'sparkle-2co-digital-payment-lite' ),
											'checkout_label' => apply_filters( 'sparkle_edd_2checkout_label', esc_html__( '2Checkout', 'sparkle-2co-digital-payment-lite' ) ),
										);
		return $gateways;
	}

	/**
	 * Addition of custom icons for our plugin's 2checkout payment gateways
	 * @param  [array] $icons 
	 * @since 1.0.0
	 * @return array
	 */
	function sparkle_edd_add_2co_payment_icons( $icons ){
		$icons[ S2CODP_IMG_DIR.'icon1.png' ] = esc_html__( '2Checkout - Icon 1', 'sparkle-2co-digital-payment-lite' ) ;
		$icons[ S2CODP_IMG_DIR.'icon2.png' ] = esc_html__( '2Checkout - Icon 2', 'sparkle-2co-digital-payment-lite' ) ;
		$icons[ S2CODP_IMG_DIR.'icon3.png' ] = esc_html__( '2Checkout - Icon 3', 'sparkle-2co-digital-payment-lite' ) ;
    	return $icons;
	}
	
	/**
	 * Add plugin's 2checkout section in payment gateways for configuration of plugin's settings
	 * @param  [array] $section
	 * @since 1.0.0
	 * @return array
	 */
	function sparkle_edd_2co_add_settings_section( $sections ){
		$sections['s2coedd_payment'] = esc_html__( '2Checkout(sparkle)', 'sparkle-2co-digital-payment-lite' );
		return $sections;
	}
	
	/**
	 * Addion of the plugin's 2checkout gateway settings fields.
	 * @param  [array] $settings
	 * @since 1.0.0
	 * @return array
	 */
	function sparkle_edd_2co_payment_add_settings( $settings ){
		$s2coedd_payment_settings = array('s2coedd_payment' => array(					
									array(
										'id' => 's2coedd-payment-settings',
										'name' => '<strong>' . esc_html__( '2Checkout Settings', 'sparkle-2co-digital-payment-lite' ) . '</strong>',
										'desc' => esc_html__( 'Configure the 2Checkout settings', 'sparkle-2co-digital-payment-lite' ),
										'type' => 'header',
									),
									array(
										'id' => 'sparkle-2co-dp-payment-button-merchant-code',
										'name' => esc_html__( 'Merchant Code(Required)', 'sparkle-2co-digital-payment-lite' ),
										'desc' => sprintf( esc_html__( 'Please enter your Merchant Code from %1$s Integrations > Webhooks & API > API Section.%2$s', 'sparkle-2co-digital-payment-lite' ), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank' >", "</a>" ),
										'type' => 'text',
										'size' => 'regular',
									),
									array(
										'id' => 'sparkle-2co-dp-payment-button-secret-key',
										'name' => esc_html__( 'Secret Key(Required)', 'sparkle-2co-digital-payment-lite' ),
										'desc' => sprintf( esc_html__( 'Please enter your Secret key from %1$s Integrations > Webhooks & API > API Section. %2$s', 'sparkle-2co-digital-payment-lite' ), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank' >", "</a>" ),
										'type' => 'text',
										'size' => 'regular',
									),
									array(
										'id' => 'sparkle-2co-dp-payment-button-buy-link-secret-word',
										'name' => esc_html__( 'Buy Link Secret Word(Required)', 'sparkle-2co-digital-payment-lite' ),
										'desc' => sprintf( esc_html__( 'Please enter your Buy Link Secret Word from %1$s Integrations > Webhooks & API > Secret Word.%2$s This is required for the singnature validation and IPN response validation.', 'sparkle-2co-digital-payment-lite'), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank' >", "</a>" ),
										'type' => 'text',
										'size' => 'regular',
									),
									array(
										'id' => 'sparkle-2co-dp-payment-2checkout-radio-button-label',
										'name' => esc_html__( '2Checkout Radio button Label', 'sparkle-2co-digital-payment-lite' ),
										'desc' => esc_html__( "Please enter the radio button label to be displayed in checkout page. If kept blank default label(2Checkout) will be displayed in checkout page.", 'sparkle-2co-digital-payment-lite' ),
										'type' => 'text',
										'size' => 'regular',
									),
									array(
										'id'    => 'sparkle-2co-edd-webhook-description',
										'type'  => 'descriptive_text',
										'name'  => esc_html__( 'IPN URL', 'sparkle-2co-digital-payment-lite' ),
										'desc'  =>
										'<p>' . sprintf(
											esc_html__( 'In order for 2checkout to function completely, you must configure your 2checkout IPN settings. Visit %1$s Integrations > Webhooks & API > IPN Settings.%2$s to configure them. Please add a webhook endpoint for the URL below.', 'sparkle-2co-digital-payment-lite' ),
											'<a href="https://secure.2checkout.com/cpanel/ipn_settings.php" target="_blank" rel="noopener noreferrer">',
											'</a>'
										) . '</p>' .
										'<p><strong>' . sprintf(
											esc_html__( 'Webhook URL: %s', 'sparkle-2co-digital-payment-lite' ),
											home_url( '?sparkle_2co_edd_payment_ipn_listener=2checkout' )
										) . '</strong></p>' .
										'<p>'
									),
									array(
										'id'    => 'sparkle-2co-edd-webhook-ins-description',
										'type'  => 'descriptive_text',
										'name'  => esc_html__( 'INS URL', 'sparkle-2co-digital-payment-lite' ),
										'desc'  =>
										'<p>' . sprintf(
											esc_html__( 'In order for 2checkout to function completely, you must configure your 2checkout INS settings. Visit %1$s Integrations > Webhooks & API > INS Settings.%2$s to configure them. Please add a webhook endpoint for the URL below.', 'sparkle-2co-digital-payment-lite' ),
											'<a href="https://secure.2checkout.com/cpanel/ins_settings.php" target="_blank" rel="noopener noreferrer">',
											'</a>'
										) . '</p>' .
										'<p><strong>' . sprintf(
											esc_html__( 'Webhook URL: %s', 'sparkle-2co-digital-payment-lite' ),
											home_url( '?sparkle_2co_edd_payment_ins_listener=2checkout' )
										) . '</strong></p>' .
										'<p>'
									),
								));

		return array_merge( $settings, $s2coedd_payment_settings );
	}

	/**
	 * Standard Checkout - Payment insetions and redirect to 2checkout website for processing of the payment 
	 * @param  [array] $purchase_data 
	 * @since 1.0.0
	 */
	function edd_2co_process_payment( $purchase_data ){
		$options 		= get_option( 'edd_settings' );
		$merchant_code 	= sanitize_text_field( $options['sparkle-2co-dp-payment-button-merchant-code'] );
		$secret_key 	= sanitize_text_field( $options['sparkle-2co-dp-payment-button-secret-key'] );
		$buy_link_secret_word = sanitize_text_field( $options['sparkle-2co-dp-payment-button-buy-link-secret-word'] );
		
		if( !isset( $options['gateways']['s2coedd_payment'] ) ){
			return;
		}
		
		$payment_data = array(
			'price'         => sanitize_text_field( $purchase_data['price'] ),
			'date'          => sanitize_text_field( $purchase_data['date'] ),
			'user_email'    => sanitize_email( $purchase_data['user_email'] ),
			'purchase_key'  => sanitize_text_field( $purchase_data['purchase_key'] ),
			'currency'      => edd_get_currency(),
			'downloads'     => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['downloads'] ),
			'cart_details'  => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['cart_details'] ),
			'user_info'     => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['user_info'] ),
			'status'        => 'pending',
		);

		//insert payment details to database and set status to pending
		$payment = edd_insert_payment( $payment_data );

		if ( $payment ) {
			$return_url 	= home_url( '/?edd-payment-id=' . $payment );
			$post_data      = $purchase_data['post_data'];
			$name           = esc_html( $post_data['edd_first'] ) ." ". esc_html( $post_data['edd_last'] );
			$email          = is_email( $post_data['edd_email'] ) ? esc_html( $post_data['edd_email'] ) : '';
			$phone 			= isset( $post_data['edd_phone']) ? esc_html( $post_data['edd_phone'] ) : '';
			$card_address   = isset( $post_data['card_address'] ) ? esc_html( $post_data['card_address'] ) : '';
			$card_address_2 = isset( $post_data['card_address_2'] ) ? esc_html( $post_data['card_address_2'] ) : '';
			$card_city      = isset( $post_data['card_city'] ) ?  $esc_html( $post_data['card_city'] ) : '';
			$card_zip       = isset( $post_data['card_zip'] ) ? esc_html( $post_data['card_zip'] ) : '';
			$billing_country= isset( $post_data['billing_country'] ) ? esc_html( $post_data['billing_country'] ) : '';
			$card_state     = isset( $post_data['card_state'] ) ? esc_html( $post_data['card_state'] ) : '';

			$args = array(
				'merchant' 		=> $merchant_code,
				'dynamic'  		=> 1,
				'name'      	=> $name,
				'phone' 		=> $phone,
				'email'     	=> $email,
				'country'   	=> $billing_country,
				'city'      	=> $card_city,
				'state'     	=> $card_state,
				'address'   	=> $card_address,
				'billing_address' => $card_address_2,
				'zip'       	=> $card_zip,
				'currency' 		=> edd_get_currency(),
				'return-url' 	=> $return_url,
				'return-type' 	=> 'redirect',
				'order-ext-ref' => $payment,
				'tpl'  			=> 'default',
			);

			$products 	= array();
			$prices 	= array();
			$types 		= array();
			$quantities = array();

			foreach( $purchase_data['cart_details'] as $item ) {
				$download_id = intval( $item['id'] );
				$item_name 	 = esc_html( $item['name'] );
				if( edd_has_variable_prices( $download_id ) ) {
					// download has variable prices enabled
					$price_id 					= intval( $item['item_number']['options']['price_id'] );
					$var_prices 				= edd_get_variable_prices( $download_id );
					$current_variable_product 	= $var_prices[$price_id];
					$variable_product_name 	  	= esc_html( $current_variable_product['name'] );
					$item_name 					= $item_name .' - '. $variable_product_name;
				}

				$item_amount 	= esc_html( $item['subtotal'] );
				
				$products[]  	= $item_name;
				$prices[]   	= $item_amount;
				$types[] 	 	= 'digital';
				$quantities[] 	= intval( $item['quantity'] );

				//use coupon code discount in 2checkout
				if ( isset( $item['discount'] ) && !empty( $item['discount'] ) ) {
					$discount 	 	= isset( $item['discount'] ) ? esc_html( $item['discount'] ) : 0 ;
					$products[] 	= 'discount - '. $item_name;
					$prices[]   	= $discount;
					$types[] 	 	= 'coupon';
					$quantities[] 	= intval( $item['quantity'] );
				}

				// Add taxes to the cart
				if ( edd_use_taxes() ) {
					$tax 		 	= isset( $item['tax'] ) ? esc_html( $item['tax'] ) : 0 ;
					$products[] 	= 'tax - '. $item_name;
					$prices[]   	= $tax;
					$types[] 	 	= 'tax';
					$quantities[] 	= intval( $item['quantity'] );
				}
			}
					
			$args['prod']  = implode( ';', $products );
			$args['price'] = implode( ';', $prices );
			$args['type']  = implode( ';', $types );
			$args['qty']   = implode( ';', $quantities );
			
			if( edd_is_test_mode() ) {
				$args['demo'] = 1;
			}
			
			$sparkle_2co_api 	= new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
			$args['signature'] 	= $sparkle_2co_api->convertplus_buy_link_signature( $args, $buy_link_secret_word );
			
			$args      = apply_filters( 'sparkle_2co_edd_standard_redirect_args', $args, $purchase_data );
			$redirect  = 'https://secure.2checkout.com/checkout/buy?';
			$redirect .= http_build_query( $args );
			$redirect  = str_replace( '&amp;', '&', $redirect );
			
			wp_redirect( $redirect );
			exit;
		}else {
			if( TRUE === edd_is_debug_mode() ){
				$message = esc_html__( 'Payment insertion to database failed.', 'sparkle-2co-digital-payment-lite' );
				edd_debug_log( $message, $force );
			}
			// If errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		}
	}
	
	/**
	 * Inline checkout - Payment insertion and return json object required for inline payment for 2checkout.
	 * @param  [array] $purchase_data
	 * @since 1.0.0
	 * @return json obj
	 */
	function edd_2co_process_payment_inline( $purchase_data ){
		$options = get_option( 'edd_settings' );

		if( !isset( $options['gateways']['s2coedd_payment_inline'] ) ){
			return;
		}
			
		$payment_data = array(
			'price'         => sanitize_text_field( $purchase_data['price'] ),
			'date'          => sanitize_text_field( $purchase_data['date'] ),
			'user_email'    => sanitize_email( $purchase_data['user_email'] ),
			'purchase_key'  => sanitize_text_field( $purchase_data['purchase_key'] ),
			'currency'      => edd_get_currency(),
			'downloads'     => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['downloads'] ),
			'cart_details'  => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['cart_details'] ),
			'user_info'     => Sparkle_2CO_DP_Library:: sanitize_array( $purchase_data['user_info'] ),
			'status'        => 'pending',
		);

		//insert payment details to database and set status to pending
		$payment = edd_insert_payment( $payment_data );

		if ( $payment ) {
			$return_url =  home_url( '/?edd-payment-id=' . $payment );
				if( !is_user_logged_in() ){
					$new_payment = new EDD_Payment( $payment );
					if ( empty( $new_payment->email ) ) {
						// Setup and store the customers's details
						$address = array();
						$address['line1']    = ! empty( $purchase_data['post_data']['card_address']       ) ? sanitize_text_field( $purchase_data['post_data']['card_address'] )       : false;
						$address['city']     = ! empty( $purchase_data['post_data']['card_city']         ) ? sanitize_text_field( $purchase_data['post_data']['card_city'] )         : false;
						$address['state']    = ! empty( $purchase_data['post_data']['card_state']        ) ? sanitize_text_field( $purchase_data['post_data']['card_state'] )        : false;
						$address['country']  = ! empty( $purchase_data['post_data']['billing_country'] ) ? sanitize_text_field( $data['billing_country'] ) : false;
						$address['zip']      = ! empty( $purchase_data['post_data']['card_zip']          ) ? intval( substr( $purchase_data['post_data']['card_zip'], 0, 5 ) ) : false;


						$new_payment->email      = sanitize_email( $purchase_data['post_data']['edd_email'] );
						$new_payment->first_name = sanitize_text_field( $purchase_data['post_data']['edd_first'] );
						$new_payment->last_name  = sanitize_text_field( $purchase_data['post_data']['edd_last'] );
						$new_payment->address    = $address;
						
						if( empty( $new_payment->customer_id ) ) {
							$customer = new EDD_Customer( $new_payment->email );
							if( ! $customer || $customer->id < 1 ) {
								$userdata = array(
									'user_login' => sanitize_text_field( $purchase_data['post_data']['edd_user_login'] ),
									'user_pass'  => sanitize_text_field( $purchase_data['post_data']['edd_user_pass'] ),
									'user_email' => sanitize_email( $purchase_data['post_data']['edd_email'] ),
									'user_first' => sanitize_text_field( $purchase_data['post_data']['edd_first'] ),
									'user_last'  => sanitize_text_field( $purchase_data['post_data']['edd_last'] ),
									'user_registered' => date( 'Y-m-d H:i:s' ),
									'role' => get_option( 'default_role' ),
								);

								$user_id = edd_register_and_login_new_user( $userdata );

								// Get the user's billing address details.
								$user['address'] = array();
								$user['address']['line1']   = ! empty( $_POST['card_address']    ) ? sanitize_text_field( $_POST['card_address']    ) : '';
								$user['address']['line2']   = ! empty( $_POST['card_address_2']  ) ? sanitize_text_field( $_POST['card_address_2']  ) : '';
								$user['address']['city']    = ! empty( $_POST['card_city']       ) ? sanitize_text_field( $_POST['card_city']       ) : '';
								$user['address']['state']   = ! empty( $_POST['card_state']      ) ? sanitize_text_field( $_POST['card_state']      ) : '';
								$user['address']['country'] = ! empty( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : '';
								$user['address']['zip']     = ! empty( $_POST['card_zip']        ) ? intval( substr( $_POST['card_zip'], 0, 5 ) ) : '';

								if ( empty( $user['address']['country'] ) ) {
									$user['address'] = false; // Country will always be set if address fields are present.
								}

								if ( ! empty( $user_id ) && $user_id > 0 && ! empty( $user['address'] ) ) {
									// Store the address in the user's meta so the cart can be pre-populated with it on return purchases.
									update_user_meta( $user_id, '_edd_user_address', $user['address'] );
								}
							}

							$new_payment->customer_id = $user_id;
						}
						$new_payment->save();
					}
				}

				$items 		= $purchase_data['cart_details'];
				$cart_items = array();
				foreach( $items as $item ){
					$download_id 			= intval( $item['id'] );
					$variable_product_name 	= '';
					$item_name 				= esc_html( $item['name'] );
					
					if( edd_has_variable_prices( $download_id ) ) {
						// download has variable prices enabled
						$price_id 					= intval( $item['item_number']['options']['price_id'] );
						$prices   					= edd_get_variable_prices( $download_id );
						$current_variable_product 	= $prices[$price_id];
						$variable_product_name 		= esc_html( $current_variable_product['name'] );
						$item_name 					= $item_name .' - '. $variable_product_name;
					}

					$cart_items[] = array(
							'name' 		=> $item_name,
							'price' 	=> esc_html( $item['subtotal'] ),
							'quantity' 	=> intval( $item['quantity'] ),
							'tangible' 	=> true,
							'type' 		=> 'PRODUCT',
					);

					//use coupon code discount in 2checkout
					if ( isset( $item['discount'] ) && !empty( $item['discount'] ) ) {
						$discount 	 	= isset( $item['discount'] ) ? esc_html( $item['discount'] ) : 0 ;					
						$cart_items[] 	= array(
							'name' 		=> 'discount - '. $item_name,
							'price' 	=> $discount,
							'quantity' 	=> intval( $item['quantity'] ),
							'tangible' 	=> true,
							'type' 		=> "coupon",
						);
					}

					// Add taxes to the cart
					if ( edd_use_taxes() ) {
						$tax 		  = isset( $item['tax'] ) ? esc_html( $item['tax'] ) : 0;
						$cart_items[] = array(
							'name' 		=> 'tax - '. $item_name,
							'price' 	=> $tax,
							'quantity' 	=> intval( $item['quantity'] ),
							'tangible' 	=> true,
							'type' 		=> 'tax',
						);
					}
				}

				$post_data 		= $purchase_data['post_data'];
				$email 			= is_email( $post_data['edd_email'] ) ? sanitize_email( $post_data['edd_email'] ) : '';
				$name 			= esc_html( $post_data['edd_first'] ) ." ". esc_html( $post_data['edd_last'] );
				$phone 			= esc_html( $post_data['edd_phone'] );
				$card_address 	= esc_html( $post_data['card_address'] );
				$card_address_2 = esc_html( $post_data['card_address_2'] );
				$card_city 		= esc_html( $post_data['card_city'] );
				$card_zip 		= intval( substr( $post_data['card_zip'], 0, 5 ) );
				$billing_country= esc_html( $post_data['billing_country'] );
				$card_state 	= esc_html( $post_data['card_state'] );
				$total 			= esc_html( $purchase_data['price'] );

				$array_to_send_to_inline_checkout = array(
					'address' 			=> $card_address,
					'billing_address' 	=> $card_address_2,
					'billing_city' 		=> $card_city,
					'billing_country' 	=> $billing_country,
					'billing_name' 		=> $name,
					'billing_phone'	 	=> $phone,
					'billing_state' 	=> $card_state,
					'billing_zip' 		=> $card_zip,
					'city' 				=> $card_city,
					'country' 			=> $billing_country,
					'currency' 			=> edd_get_currency(),
					'email' 			=> $email,
					'items' 			=> $cart_items,
					'lang' 				=> 'en',
					'link' 				=> $return_url,
					'name' 				=> $name,
					'order_id' 			=> $payment,
					'phone_number' 		=> $phone,
					'reference' 		=> $payment,
					'result' 			=> 'success',
					'state' 			=> $card_state,
					'total' 			=> $total,
					'zip' 				=> $card_zip,
				);

				if( edd_is_test_mode() ) {
					$array_to_send_to_inline_checkout['test'] = 1;
				}

				echo json_encode( $array_to_send_to_inline_checkout );
			
		}else {
			if( TRUE === edd_is_debug_mode() ){
				$message = esc_html__( 'Payment insertion to database failed.', 'sparkle-2co-digital-payment-lite' );
				edd_debug_log( $message, $force );
			}
			// If errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout( '?payment-mode=' . esc_html( $purchase_data['post_data']['edd-gateway'] ) );
		}
	}
	
	/**
	 * EDD Payment Wait. Get the status of payment using API after payment.
	 * @since 1.0.0
	 * @return string
	 **/
	function edd_payment_wait(){

		$_GET = stripslashes_deep( $_GET );
		
		if( ! isset( $_GET['edd-payment-id'] ) || empty( $_GET['edd-payment-id'] ) ){ 
			return;
		}

		$options 				= get_option( 'edd_settings' );
		$merchant_code 			= sanitize_text_field( $options['sparkle-2co-dp-payment-button-merchant-code'] );
		$secret_key 			= sanitize_text_field( $options['sparkle-2co-dp-payment-button-secret-key'] );
		$buy_link_secret_word 	= sanitize_text_field( $options['sparkle-2co-dp-payment-button-buy-link-secret-word'] );

		$sparkle_2co_api 	 	= new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
		$generated_signature 	= $sparkle_2co_api->generate_return_signature ( $_GET, $buy_link_secret_word );
		$returned_signature  	= sanitize_text_field( $_GET['signature'] );

		$payment_id 			= intval( $_GET['edd-payment-id'] );
		$payment    			= new EDD_Payment( $payment_id );
		
		if( TRUE !== hash_equals( $generated_signature, $returned_signature ) ){
			$note = sprintf( esc_html__( '2Checkout(Sparkle): Status changed to Failed due to signature mismatch REFNOEXT ID: %s', 'sparkle-2co-digital-payment-lite' ), $payment_id );
			edd_insert_payment_note( $payment_id, $note );
			
			$new_status = 'failed';
			edd_update_payment_status( $payment_id, $new_status ); 

			edd_empty_cart();
			
			if( TRUE === edd_is_debug_mode() ){
				$message = $note;
				edd_debug_log( $message, $force );
			}

			wp_redirect( edd_get_success_page_uri() );
			exit;
		}

		if( ! is_object( $payment ) ) {
			return;
		}
		
		$status = $payment->status;
		
		if( 'publish' == $status ) {
			edd_empty_cart();
			wp_redirect( edd_get_success_page_uri() );
			exit;
		}

		if( isset( $_GET['refno'] ) ) {
			$checkout_api 	= new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
			$ref_no 		= intval( $_GET['refno'] );
			$result 		= $checkout_api->check_status( $payment_id, $ref_no );
			
			if( $result && is_array( $result ) ) {
				if( isset( $result['status'] ) && 'completed' == $result['status'] ) {
					$new_status = 'processing';
					edd_update_payment_status( $payment_id, $new_status );

					edd_empty_cart();
					
					wp_redirect( edd_get_success_page_uri() );
					exit;
				}
				
				$new_status = 'failed';
				edd_update_payment_status( $payment_id, $new_status );

				if( TRUE === edd_is_debug_mode() ){
					$message = sprintf( esc_html__( '2Checkout(Sparkle): status changed to Failed due to payment failure. REFNOEXT ID: %s', 'sparkle-2co-digital-payment-lite' ), $payment_id );
					$message .= esc_html__( 'The Debugging Array for payment is ', 'sparkle-2co-digital-payment-lite' ) . $result;
					edd_debug_log( $message, $force );
				}

				wp_redirect( edd_get_success_page_uri() );
				exit;
			}
		}
		exit;
	}
	
	/**
	 * For EDD IPN Response 
	 * @since 1.0.0
	 * @return string
	 **/
	function edd_listen_for_2co_ipn(){
		
		if( !isset( $_GET['sparkle_2co_edd_payment_ipn_listener'] ) ){
			return;
		}

		if( ! isset( $_POST['REFNOEXT'] ) || empty( $_POST['REFNOEXT'] ) ){
			return;
		}

		if( isset( $_GET['sparkle_2co_edd_payment_ipn_listener'] )  && '2checkout' === $_GET['sparkle_2co_edd_payment_ipn_listener'] ){
			$payment_id = absint( $_POST['REFNOEXT'] );	
			$payment    = new EDD_Payment( $payment_id );
			$status 	= $payment->status;

			if( 'publish' == $status ) {
				edd_empty_cart();
				exit;
			}

			$tnx_id = get_post_meta( $payment_id, 'sparkle_2co_txn_id', true );
			
			if( $tnx_id !='' ) {
				exit;
			}

			$options 		= get_option( 'edd_settings' );
			$merchant_code 	= sanitize_text_field( $options['sparkle-2co-dp-payment-button-merchant-code'] );
			$secret_key 	= sanitize_text_field( $options['sparkle-2co-dp-payment-button-secret-key'] );

			$twocheckout_api = new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
			$result 		 = $twocheckout_api->check_signature();
			
			if( isset( $result['error'] ) ) {
				if( 'WAITING' === $result['data']['ApproveStatus'] ){
					$note = sprintf( esc_html__( '2Checkout(Sparkle): Payment gataway has generated an error for Payment ID:%d. This error is generated from IPN. The error message is %s.' ), $payment_id, $result['error'] );

					edd_insert_payment_note( $payment_id, $note );

					$new_status = 'failed';
					edd_update_payment_status( $payment_id, $new_status );
				}else{
					ob_start();
					echo "<pre>";
					print_r($result);
					echo "</pre>";
					$output_inline = ob_get_contents();
					ob_end_clean();

					$note = sprintf( esc_html__( '2Checkout(Sparkle): Payment Gateway has generated an error for Order ID: %d. This error is generated from IPN. The generated error is as follows %s.' ), $payment_id, $output_inline );
					
					edd_insert_payment_note( $payment_id, $note );

					$new_status = 'failed';
					edd_update_payment_status( $payment_id, $new_status );
				}

				if( TRUE === edd_is_debug_mode() ){
					$message = sprintf( esc_html__( 'Payment ID %d failed', 'sparkle-2co-digital-payment-lite' ), $payment_id );
					ob_start();
					print_r($result);
					$output = ob_get_contents();
					ob_end_clean();
					
					$message = $output;
					$message .= esc_html__( 'The response received from IPS is as follows: ' , 'sparkle-2co-digital-payment-lite' ) . $result;
					edd_debug_log( $message, $force );
				}

			} else {            
				update_post_meta( $payment_id, 'sparkle_2co_txn_id', (int) $_POST['REFNO'] );
				
				$new_status = 'publish';
				edd_update_payment_status( $payment_id, $new_status );
				
				$note = sprintf( esc_html__( '2Checkout(Sparkle): Charge complete REFNOEXT ID: %s', 'sparkle-2co-digital-payment-lite' ), $payment_id );
				edd_insert_payment_note( $payment_id, $note );

				edd_empty_cart();

				echo "<EPAYMENT>" . esc_html( $result['date_return'] ) . "|" . esc_html( $result['result_hash'] ) . "</EPAYMENT>";
			}
			exit;
		}
		exit;
	}

	/**
	 * Validate the $_POST data with the payment data that's recorded in EDD
	 *
	 * @since 1.0.0
	 * @param array       $data    The $_POST data passed in the Webhook
	 * @param EDD_Payment $payment The EDD_Payment object for the related payment
	 *
	 * @return array
	*/
	private function validate_order_webhook( $data, $payment ) {
		$success = true;
		$message = esc_html__( '2Checkout(Sparkle): Price validation with 2checkout is Complete. ', 'sparkle-2co-digital-payment-lite');

		$payment_total = (float) edd_get_payment_amount( $payment->ID );
		
		if ( $payment_total > (float) $data['invoice_list_amount'] ) {
			$success = false;
			$message = sprintf( esc_html__( '2Checkout(Sparkle): 2Checkout total (%s) did not match payment total (%s). The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ), $data['invoice_list_amount'], $payment->total );
		} else {
			// Verify that each item in the cart_details matches the item amount
			// lets make an array for cart details with each tax and discount as product price
			$product_prices = array();
			foreach ( $payment->cart_details as $key => $item ) {
				$product_prices[] = $item['subtotal'];
				
				if( isset($item['discount']) && $item['discount'] !='0' ){
					$product_prices[] = $item['discount'];
				}

				if( isset($item['tax']) && $item['tax'] !='0' ){
					$product_prices[] = $item['tax'];
				}
			}
			
			foreach( $product_prices as $key=>$item_price ){
				$key += 1;
				$s2coserver_price    = (float) $data[ 'item_list_amount_' . $key ];
				$s2coedd_price    = (float) $item_price;

				if ( $s2coserver_price < $s2coedd_price ) {
					$success = false;
					$message = sprintf( esc_html__( '2Checkout(Sparkle): 2Checkout item %s amount (%s) did not match payment item amount (%s). The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ), $key, $s2coserver_price, $s2coedd_price );
					break;
				}
			}
		}
		return array( 'success' => $success, 'message' => $message );

	}

	/**
	 * For EDD INS Response 
	 * @since 1.0.0
	 * @return string
	 **/
	function edd_listen_for_2co_ins(){
		if( !isset( $_GET['sparkle_2co_edd_payment_ins_listener'] ) ){
			return;
		}

		if ( isset( $_GET['sparkle_2co_edd_payment_ins_listener'] ) && $_GET['sparkle_2co_edd_payment_ins_listener'] == '2checkout' ) {

			$options    			= get_option( 'edd_settings' );
			$merchant_code 			= sanitize_text_field( $options['sparkle-2co-edd-payment-button-merchant-code'] );
			$buy_link_secret_word 	= sanitize_text_field( $options['sparkle-2co-edd-payment-button-buy-link-secret-word'] );

			$secret  = html_entity_decode( $buy_link_secret_word );
			$hash    = strtoupper( md5( $_POST['sale_id'] . $merchant_code . $_POST['invoice_id'] . $secret ) );

			if ( ! hash_equals( $hash, $_POST['md5_hash'] ) ) {
				$msg = sprintf( esc_html__( '2Checkout(Sparkle): Invalid INS hash. INS data: %s', 'edd' ), json_encode( $_POST ) );
				edd_debug_log( $msg, 1 );
				die('-1');
			}
			
			if ( empty( $_POST['message_type'] ) ) {
				edd_debug_log( "-2", 1 );
				die( '-2' );
			}

			if ( empty( $_POST['vendor_id'] ) ) {
				edd_debug_log( "-3", 1 );
				die( '-3' );
			}

			$payment_id = sanitize_text_field( $_POST['vendor_order_id'] );

			if( strlen( $payment_id ) == 32 ) {
				$payment = edd_get_payment_by( 'key', $payment_id );

			} else {
				$payment = edd_get_payment_by( 'id', absint( $payment_id ) );
			}

			if ( ! $payment || ! $payment->ID > 0 ) {
				edd_debug_log( "-4", 1 );
				die( '-4' );
			}
     			
			switch( strtoupper( $_POST['message_type'] ) ) {

				case 'ORDER_CREATED':
					$order_validation = $this->validate_order_webhook( $_POST, $payment );
					if ( false === $order_validation['success'] ) {
						edd_update_payment_status( $payment->ID, 'failed' );
						$payment->add_note( $order_validation['message'] );
					} else {
						$payment->add_note( $order_validation['message'] . ' '. esc_html__( 'Now doing fraud checking. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );
						edd_update_payment_status( $payment->ID, 'fraud_waiting' );
	
					}

					edd_set_payment_transaction_id( $payment->ID, sanitize_text_field( $_POST['sale_id'] ) );
					die( '1' );

				break;

				case 'REFUND_ISSUED':
					$cart_items = edd_get_payment_meta_cart_details( $payment->ID );
					$total      = edd_get_payment_amount( $payment->ID );
					$i          = count( $cart_items );

					// Look for the new refund line item
					if( isset( $_POST['item_list_amount_' . $i + 1 ] ) && $_POST['item_list_amount_' . $i + 1 ] < $total ){
						$refunded = edd_sanitize_amount( $_POST['item_list_amount_' . $i + 1 ] );
						edd_insert_payment_note( $payment->ID, sprintf( esc_html__( '2Checkout(Sparkle): Partial refund for %s processed in 2Checkout. The status is set using INS.' ), edd_currency_filter( $refunded ) ) );

					}else{
						edd_update_payment_status( $payment->ID, 'refunded' );
						edd_insert_payment_note( $payment->ID, esc_html__( '2Checkout(Sparkle): Payment refunded in 2Checkout. The status is set using INS.', 'sparkle-2co-digital-payment-lite' ) );

					}
					die( '2' );

				break;

				case 'FRAUD_STATUS_CHANGED' :
					switch ( $_POST['fraud_status'] ){
						case 'pass':
 							edd_update_payment_status( $payment->ID, 'publish' );
							edd_insert_payment_note( $payment->ID, esc_html__( '2Checkout(Sparkle): 2Checkout fraud review passed. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );
							die( '3' );
							break;

						case 'fail':
							edd_update_payment_status( $payment->ID, 'revoked' );
							edd_insert_payment_note( $payment->ID, esc_html__( '2Checkout(Sparkle): 2Checkout fraud review failed. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );
							die( '4' );
							break;

						case 'wait':
							edd_insert_payment_note( $payment->ID, esc_html__( '2Checkout(Sparkle): 2Checkout fraud review in progress. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );
							die( '5' );
							break;
					}

				die( '6' );
				break;

			}
			die( '1' );
		}else{
			edd_debug_log( "sparkle_2co_edd_payment_ins_listener not valid", 1 );
		}
	}
}

//get the instance of a class
Sparkle_2CO_DP_EDD::get_instance();