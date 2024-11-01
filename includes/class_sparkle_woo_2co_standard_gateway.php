<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.
class Sparkle_2CO_DP_WOO_Gateway extends WC_Payment_Gateway {

	/**
	 * Class constructor
	 * @since 1.0.0
	*/
	public function __construct() {
		$this->id 			= 's2cowoop'; // 2CO Standard payment gateway ID
		$this->icon 		= S2CODP_IMG_DIR.'icon1.png'; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields 	= true;
		$this->method_title = esc_html__( 'Sparkle 2CO Standard Gateway', 'sparkle-2co-digital-payment-lite' );
		$this->method_description = esc_html__( 'Add 2Checkout standard payment gateway to checkout.', 'sparkle-2co-digital-payment-lite' ); // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods
		$this->supports = array( 'products' );

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->enabled 			= $this->get_option( 'enabled' );
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->testmode 		= 'yes' === $this->get_option( 'testmode' );
		$this->new_icon 		= $this->get_option( 'new_icon' );
		$this->merchant_code 	= $this->get_option( 'merchant_code' );
		$this->secret_key 		= $this->get_option( 'secret_key' );
		$this->buy_link_secret_word = $this->get_option( 'buy_link_secret_word' );
		$this->ipn_url 			= $this->get_option( 'ipn_url' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
 	* 2CO Standard gateway settings fields
 	* @since 1.0.0
	*/
	public function init_form_fields(){
		$ipn_url = home_url( '?sparkle_2co_woo_payment_ipn_listener=2checkout' );
		$ins_url = home_url( '?sparkle_2co_woo_payment_ins_listener=2checkout' );
		$this->form_fields = array(
			'enabled' => array(
				'title'       => esc_html__( 'Enable/Disable', 'sparkle-2co-digital-payment-lite' ),
				'label'       => esc_html__( 'Enable 2Checkout Standard Gateway', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Check this option to enable this payment gateway.', 'sparkle-2co-digital-payment-lite' ),
				'default'     => 'no'
			),
			'testmode' => array(
				'title'       => esc_html__( 'Test mode', 'sparkle-2co-digital-payment-lite' ),
				'label'       => esc_html__( 'Enable Test Mode', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Check this option if you are doing test payments using 2Checkout Payment gateway.', 'sparkle-2co-digital-payment-lite' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'title' => array(
				'title'       => esc_html__( 'Title', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'sparkle-2co-digital-payment-lite' ),
				'default'     => esc_html__( '2Checkout Standard ( Pay with Credit Cards )', 'sparkle-2co-digital-payment-lite' ),
				'desc_tip'    => false,
			),
			'description' => array(
				'title'       => esc_html__( 'Description', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'sparkle-2co-digital-payment-lite' ),
				'default'     => esc_html__( 'Pay with your credit card via 2Checkout payment gateway.', 'sparkle-2co-digital-payment-lite' ),
			),
			
			'new_icon' => array(
				'title'       => esc_html__( 'Icon Selection', 'sparkle-2co-digital-payment-lite' ),
				'description' => esc_html__( 'Please Select the 2Checkout Icon to show in Checkout Page.', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'select',
				'default'     => 'icon1',
				'options' => array(
          					'icon1' => 'Icon 1',
          					'icon2' => 'Icon 2',
          					'icon3' => 'Icon 3',
          					)
			),
			'merchant_code' => array(
				'title'       => esc_html__( 'Merchant Code(Required)', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Merchant Code from %1$s Integrations > Webhooks & API > API Section.%2$s', 'sparkle-2co-digital-payment-lite' ), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank'>" , "</a>" ),
				'desc_tip'    => false,
			),
			'secret_key' => array(
				'title'       => esc_html__( 'Secret Key(Required)', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Secret key from %1$s Integrations > Webhooks & API > API Section. %2$s', 'sparkle-2co-digital-payment-lite' ), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank' >", "</a>" ),
				'desc_tip'    => false,

			),
			'buy_link_secret_word' => array(
				'title'       => esc_html__( 'Buy Link Secret Word(Required)', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'Please enter your Buy Link Secret Word from %1$s Integrations > Webhooks & API > Secret Word %2$s. This is required for the singnature validation and IPN response validation.', 'sparkle-2co-digital-payment-lite' ), "<a href='https://secure.2checkout.com/cpanel/webhooks_api.php' target='_blank' >", "</a>" ),
				'desc_tip' => false,
			),
			'ipn_url' => array(
				'title'       => esc_html__( 'IPN URL', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'In order for 2checkout to function completely, you must configure your 2checkout IPN settings. Visit %1$s Integrations > Webhooks & API > IPN Settings %2$s. to configure them. Please add a webhook endpoint from the above readonly URL.', 'sparkle-2co-digital-payment-lite' ),
																	'<a href="https://secure.2checkout.com/cpanel/ipn_settings.php" target="_blank" rel="noopener noreferrer">',
																	'</a>'
																),
				'default'     => $ipn_url,
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
			'ins_url' => array(
				'title'       => esc_html__( 'INS URL', 'sparkle-2co-digital-payment-lite' ),
				'type'        => 'text',
				'description' => sprintf( esc_html__( 'In order for 2checkout to function completely, you must configure your 2checkout INS settings. Visit %1$s Integrations > Webhooks & API > INS Setting %2$s. to configure them. Please add a webhook endpoint from the above readonly URL. Please go to Trigger list and enable "order created", "Fraud Status Changed", "Refund Issued" for the INS settings.', 'sparkle-2co-digital-payment-lite' ),
																	'<a href="https://secure.2checkout.com/cpanel/ins_settings.php" target="_blank" rel="noopener noreferrer">',
																	'</a>'
																),
				'default'     => $ins_url,
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
		);
	}

	/**
	 * Get the icon name from settings field and filter out the icon as per selection to display on checkouot page
	 * @since 1.0.0
	 */
    public function get_icon() {
    	$icon_name = $this->new_icon;
        $icon_url = S2CODP_IMG_DIR.$icon_name.'.png';

        $icon = "<img src='". esc_url( $icon_url )."' alt='". esc_html__( '2Checkout Inline Icon', 'sparkle-2co-digital-payment-lite' ). "' />";
        
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
    }

	/**
	 * Display of gateway description in the checkout page.
	 * @since 1.0.0
	*/
	public function payment_fields() {
		echo "<p>".esc_html__( $this->description )."</p>";
	}

	/*
	* Processing of the payment and returing the redirect url to 2checkout for processing the payment
	* @since 1.0.0
	* @param $order_id Order ID
	*/
	public function process_payment( $order_id ) {
		$merchant_code 	= sanitize_text_field( $this->merchant_code );
		$secret_key 	= sanitize_text_field( $this->secret_key );
		$buy_link_secret_word = sanitize_text_field( $this->buy_link_secret_word );

		$order 	    = wc_get_order( $order_id );
		$order_data = $order->get_data(); // The Order data

		$return_url 	= home_url( '/?woo-payment-id=' . $order_id .'&gateway=' . $this->id );
		$name           = esc_html( $order_data['billing']['first_name'] ) ." ". esc_html( $order_data['billing']['last_name'] );
		$email          = is_email( $order_data['billing']['email'] ) ? esc_html( $order_data['billing']['email'] ) : '';
		$phone 			= esc_html( $order_data['billing']['phone'] );
		$card_address   = esc_html( $order_data['billing']['address_1'] );
		$card_address_2 = esc_html( $order_data['billing']['address_2'] );
		$card_city      = esc_html( $order_data['billing']['city'] );
		$card_zip       = esc_html( $order_data['billing']['postcode'] );
		$billing_country= esc_html( $order_data['billing']['country'] );
		$card_state     = esc_html( $order_data['billing']['state'] );
		$currency 		= esc_html( $order_data['currency'] );

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
			'currency' 		=> $currency,
			'return-url' 	=> $return_url,
			'return-type' 	=> 'redirect',
			'order-ext-ref' => $order_id,
			'tpl'  			=> 'default',
		);

		foreach ( $order->get_items() as $item_key => $item ):
			$item_data    		= $item->get_data();
			$product_name 		= esc_html( $item_data['name'] );
			$quantity     		= intval( $item_data['quantity'] );
			$line_subtotal     	= esc_html( $item_data['subtotal'] );
			$line_total_tax     = esc_html( $item_data['total_tax'] );

			$products[] 	= $product_name;
			$prices[] 		= $line_subtotal;
			$types[] 		= 'digital';
			$quantities[] 	= $quantity;

			// Add taxes to the cart
			if( isset($line_total_tax) && $line_total_tax !== 0 ){
				$tax 		 	= $line_total_tax;
				$products[] 	= 'tax - '. $product_name;
				$prices[]   	= $tax;
				$types[] 	 	= 'tax';
				$quantities[] 	= $quantity;
			}

		endforeach;

		/*
		* use coupon code discount in 2checkout. Loop through order coupon items
		*/
		$order_items = $order->get_items('coupon');
		$order_discount_amount = 0;
		foreach( $order_items as $coupon_item_id => $coupon_item ){
			$coupon_code 			= esc_html( $coupon_item->get_code() );
			$order_discount_amount 	= wc_get_order_item_meta( $coupon_item_id, 'discount_amount', true );
			
			if( $order_discount_amount != 0 ){
				$discount 	 	= esc_html( $order_discount_amount );					
				$products[] 	= 'discount code: '. $coupon_code;
				$prices[]   	= $discount;
				$types[] 	 	= 'coupon';
				$quantities[] 	= intval( $item['quantity'] );
			}
		}

		$args['prod']  = implode( ';', $products );
		$args['price'] = implode( ';', $prices );
		$args['type']  = implode( ';', $types );
		$args['qty']   = implode( ';', $quantities );
		
		if( TRUE === $this->testmode ) {
			$args['test'] = 1;
		}

		$sparkle_2co_api 	= new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
		$args['signature'] 	= $sparkle_2co_api->convertplus_buy_link_signature( $args, $buy_link_secret_word );
		
		$args      = apply_filters( 'sparkle_dp_woo_standard_redirect_args', $args, $order_data );
		$redirect  = 'https://secure.2checkout.com/checkout/buy?';
		$redirect .= http_build_query( $args );
		$redirect  = str_replace( '&amp;', '&', $redirect );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', esc_html__( '2Checkout(Sparkle): Awaiting 2checkout for payment confirmation.', 'sparkle-2co-digital-payment-lite' ) );
				
		// Remove cart
		WC()->cart->empty_cart();

		// Return success redirect
		return array(
			'result'    => 'success',
			'redirect'  => $redirect
		);
	}
}