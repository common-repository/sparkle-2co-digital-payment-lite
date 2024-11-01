<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.

class Sparkle_2CO_DP_Woo{
	protected static $instance  = null;

	/**
	 * Plugin initialize with requried actions
	 * @since 1.0.0
	 */
	public function __construct(){
		add_filter( 'woocommerce_payment_gateways', array( $this, 'gateway_class' ) );
		add_action( 'plugins_loaded', array( $this, 'init_gateway_classes' ) );

		add_action( 'wp', array( $this, 'payment_wait' ) );

		add_action( 'woocommerce_review_order_before_submit', array( $this, 'check_gateway_field_settings_configured_or_not' ) );
		
		//ipn payment notification
		add_action( 'init', array( $this, 'listen_for_2co_ipn' ) );
		
		//ins payment notification
		add_action( 'init', array( $this, 'listen_for_2co_ins' ) );

	}

	/**
	 * Checks the required fields are configured or not
	 * @since 1.0.0
	 */
	public static function check_gateway_field_settings_configured_or_not(){
		$gateways_standard  = WC()->payment_gateways->payment_gateways()['s2cowoop'];

		if( 'yes' === $gateways_standard->settings['enabled'] && ( '' === $gateways_standard->settings['merchant_code'] || '' === $gateways_standard->settings['secret_key'] || '' === $gateways_standard->settings['buy_link_secret_word'] ) ){
			?>
			<div class="woocommerce-error" role="alert">
				<strong><?php esc_html_e( 'Error', 'sparkle-2co-digital-payment-lite' ); ?></strong>: <?php esc_html_e( 'Sparkle 2CO Inline Getway is not setup correctly. Please contact site administrator and notify about this error. The gateway will not work properly.', 'sparkle-2co-digital-payment-lite' ); ?>
			</div>
			<?php
		}
	}

	/** 
	 * Class instance
	 * @return instance of a class
	 * @since 1.0.0
	 */
	public static function get_instance(){
		if( null === self:: $instance ){
			self:: $instance = new self;
		}

		return self:: $instance;
	}

	/**
	* This action hook registers our PHP class as a WooCommerce payment gateway
	* @since 1.0.0
	* @return Array
	*/
	function gateway_class( $gateways ) {
		$gateways[] = 'Sparkle_2CO_DP_WOO_Gateway';
		return $gateways;
	}

	/**
	* Include required files
	* @since 1.0.0
	*/
	function init_gateway_classes() {
		require_once ( plugin_dir_path( __FILE__ ) . "class_sparkle_woo_2co_standard_gateway.php" );
	}

	/**
	 * For WooCommerce payment wait 
	 * @since 1.0.0
	 * @return actions
	 **/
	function payment_wait(){
		global $woocommerce;

		if( isset( $_GET['woo-payment-id'] ) ){
			if( 's2cowoop' === $_GET['gateway'] ){
				$gateways = WC()->payment_gateways->payment_gateways()['s2cowoop'];
			}
		}

		$_GET = stripslashes_deep( $_GET );
		
		if( ! isset( $_GET['woo-payment-id'] ) || empty( $_GET['woo-payment-id'] ) ){ 
			return;
		}

		$order_id = intval( $_GET['woo-payment-id'] );
		unset( $_GET['woo-payment-id'] );
		unset( $_GET['gateway'] );

		$merchant_code 	= sanitize_text_field( $gateways->settings['merchant_code'] );
		$secret_key 	= sanitize_text_field( $gateways->settings['secret_key'] );
		$buy_link_secret_word = sanitize_text_field( $gateways->settings['buy_link_secret_word'] );

		$sparkle_2co_api 	 = new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
		
		$generated_signature = $sparkle_2co_api->generate_return_signature( $_GET, $buy_link_secret_word );
		$returned_signature  = sanitize_text_field( $_GET['signature'] );

		$order = wc_get_order( $order_id );

		$order_received_url = $order->get_checkout_order_received_url();

		$status = $order->get_status();

		if( TRUE !== hash_equals( $generated_signature, $returned_signature ) ){
			$new_status = 'failed';
			$order->update_status( $new_status, sprintf( esc_html__( '2Checkout(Sparkle): Status changed to Failed due to signature mismatch. REFNOEXT ID: %s. generated signature:%s and returned signature: %s ', 'sparkle-2co-digital-payment-lite' ), $order_id, $generated_signature, $returned_signature ) );

			// Empty cart
			$woocommerce->cart->empty_cart();

			wp_redirect( $order_received_url );
			exit;
		}

		if( ! is_object( $order ) ) {
			return;
		}
		
		if( 'completed' == $status ) {
			$woocommerce->cart->empty_cart();
			wp_redirect( $order_received_url );
			exit;
		}

		if( isset( $_GET['refno'] ) ) {
			$checkout_api = new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );

			$ref_no = intval( $_GET['refno'] );
		
			$result = $checkout_api->check_status( $order_id, $ref_no );
			
			if( $result && is_array( $result ) ) {
				if( isset( $result['status'] ) && 'completed' == $result['status'] ) {
					
					$order->update_status( 'processing', sprintf( esc_html__( '2Checkout(Sparkle): Status has changed to processing as payment is completed. REFNOEXT ID: %s.', 'sparkle-2co-digital-payment-lite' ), $order_id ) );

					// Empty cart
					$woocommerce->cart->empty_cart();

					wp_redirect( $order_received_url );
					exit;
				}
				
				$new_status = 'failed';

				$order->update_status( $new_status, sprintf( esc_html__( '2Checkout(Sparkle): The status has changed to failed as payment is not completed yet. REFNOEXT ID: %s.', 'sparkle-2co-digital-payment-lite' ), $order_id ) );

				$woocommerce->cart->empty_cart();

				wp_redirect( $order_received_url );
				exit;
			}
		}
		exit;
	}

	/**
	 * For Woo IPN Response 
	 * @since 1.0.0
	 * @return string
	 **/
	function listen_for_2co_ipn(){

		global $woocommerce;

		if( !isset( $_GET['sparkle_2co_woo_payment_ipn_listener'] ) ){
			return;
		}

		if( ! isset( $_POST['REFNOEXT'] ) || empty( $_POST['REFNOEXT'] ) ){
			return;
		}

		if( isset( $_GET['sparkle_2co_woo_payment_ipn_listener'] )  && '2checkout' === $_GET['sparkle_2co_woo_payment_ipn_listener'] ){
			
			$order_id = absint( $_POST['REFNOEXT'] );

			$order = wc_get_order( $order_id );

			$status = $order->get_status();

			$payment_method = $order->get_payment_method();

			if( 'completed' == $status ) {
				$woocommerce->cart->empty_cart();
				exit;
			}
			
			if( 's2cowoop' === $payment_method ){
				$gateways = WC()->payment_gateways->payment_gateways()['s2cowoop'];
			}

			$merchant_code 	= sanitize_text_field( $gateways->settings['merchant_code'] );
			$secret_key 	= sanitize_text_field( $gateways->settings['secret_key'] );

			$twocheckout_api = new Sparkle_2CO_DP_Api( $secret_key, $merchant_code );
			$result 		 = $twocheckout_api->check_signature();
			
			if( isset( $result['error'] ) ) {
				if( 'WAITING' === $result['data']['ApproveStatus'] ){
					$order->update_status( 'on-hold', sprintf( esc_html__( '2Checkout(Sparkle): Payment gataway has generated an error for Order ID:%d. This error is generated from IPN. The error message is %s.' ), $order_id, $result['error'] ) );
				}else{
					ob_start();
					echo "<pre>";
					print_r($result);
					echo "</pre>";
					$output_inline = ob_get_contents();
					ob_end_clean();
					$order->update_status( 'failed', sprintf( esc_html__( '2Checkout(Sparkle): Payment Gateway has generated an error for Order ID: %d. This error is generated from IPN. The generated error is as follows %s.' ), $order_id, $output_inline ) );
				}
			} else {
				$order->payment_complete(); 
				
				// Add the note
				$note = sprintf( esc_html__( '2Checkout(Sparkle): Payment gateway charge is complete. REFNOEXT ID: %d. This status is set from IPN.', 'sparkle-2co-digital-payment-lite' ), $order_id );
				$order->add_order_note( $note );           

				$woocommerce->cart->empty_cart();

				echo "<EPAYMENT>" . esc_html( $result['date_return'] ) . "|" . esc_html( $result['result_hash'] ) . "</EPAYMENT>";
			}
			exit;
		}
		exit;
	}

	/**
	 * Validate the $_POST data with the payment data that's recorded in WooCommerce
	 *
	 * @since 1.0.0
	 * @param array $data The $_POST data passed in the Webhook
	 * @param $order_id the related order id of woocommerce order
	 *
	 * @return array
	*/
	private function validate_order_webhook( $data, $order_id ) {
		$success = true;
		$message =  esc_html__( 'Price validation with 2checkout is Complete. ', 'sparkle-2co-digital-payment-lite' );

		$order = wc_get_order( $order_id );
		$order_data = $order->get_data();

		if ( $order_data['total'] > $data['invoice_cust_amount'] ) {
			$success = false;
			$message = sprintf( esc_html__( '2Checkout total (%s) did not match payment total (%s).', 'sparkle-2co-digital-payment-lite' ), $data['invoice_cust_amount'], $order_data['total'] );
		} else {
			// Verify that each item in the cart_details matches the item amount
			$product_prices = array();
			foreach ( $order->get_items() as $key => $item ) {
				$item_data    = $item->get_data();
				$product_prices[] = $item_data['subtotal'];

				if( isset($item_data['total_tax']) && $item_data['total_tax'] !='0' ){
					$product_prices[] = $item_data['total_tax'];
				}
			}

			$order_items = $order->get_items( 'coupon' );
			if( !empty( $order_items ) ){
				$order_discount_amount = 0;
				$discount = 0;
				foreach( $order_items as $item_id => $item ){
					$order_discount_amount = wc_get_order_item_meta( $item_id, 'discount_amount', true );
					if( $order_discount_amount != 0 ){
						$product_prices[] = $order_discount_amount;					
					}
				}
			}

			foreach( $product_prices as $key=>$item_price ){
				$key += 1;
				$s2co_price    = (float) $data[ 'item_cust_amount_' . $key ];
				$s2cowoo_price = (float) $item_price;

				if ( $s2co_price < $s2cowoo_price ) {
					$success = false;
					$message = sprintf( esc_html__( '2Checkout item %s amount (%s) did not match payment item amount (%s). The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ), $key, $s2co_price, $s2cowoo_price );
					break;
				}
			}
		}

		return array( 'success' => $success, 'message' => $message );
	}

	/**
	 * For WOO INS Response 
	 * @since 1.0.0
	 * @return string
	 **/
	function listen_for_2co_ins(){

		if( !isset( $_GET['sparkle_2co_woo_payment_ins_listener'] ) ){
			return;
		}

		if ( isset( $_GET['sparkle_2co_woo_payment_ins_listener'] ) && $_GET['sparkle_2co_woo_payment_ins_listener'] == '2checkout' ) {

			$order_id = intval( $_POST['vendor_order_id'] );

			$order = wc_get_order( $order_id );

			$status = $order->get_status();

			$payment_method = $order->get_payment_method();

			if( 's2cowoop' === $payment_method ){
				$gateways = WC()->payment_gateways->payment_gateways()['s2cowoop'];
			}

			$merchant_code 		 = sanitize_text_field( $gateways->settings['merchant_code'] );
			$buy_link_secret_key = html_entity_decode( $gateways->settings['buy_link_secret_word'] );
			$hash    			 = strtoupper( md5( $_POST['sale_id'] . $merchant_code . $_POST['invoice_id'] . $buy_link_secret_key ) );

			if ( !hash_equals( $hash, $_POST['md5_hash'] ) ) {
				$msg = sprintf( esc_html__( '2Checkout(Sparkle): Invalid INS hash. INS data: %s', 'edd' ), json_encode( $_POST ) );
				$order->update_status( 'on-hold', $msg );
				die('-1');
			}
			
			if ( empty( $_POST['message_type'] ) ) {
				die( '-2' );
			}

			if ( empty( $_POST['vendor_id'] ) ) {
				die( '-3' );
			}

			switch( strtoupper( $_POST['message_type'] ) ) {

				case 'ORDER_CREATED':
					$order_validation = $this->validate_order_webhook( $_POST, $order_id );
					if ( false === $order_validation['success'] ) {
						$order->update_status( 'failed', $order_validation['message'] );
						
					} else {
						$order->update_status( 'on-hold', esc_html__( '2Checkout(Sparkle): ', 'sparkle-2co-digital-payment-lite' ). $order_validation['message'] . ' '. esc_html__( 'Now doing fraud checking. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );
						
					}
					die( '1' );

					break;

				case 'REFUND_ISSUED':
					$order_data = $order->get_data();
					$total      = $order_data['total'];
					$i          = count( $order->get_items() );

					if( isset( $_POST['item_list_amount_' . $i + 1 ] ) && $_POST['item_list_amount_' . $i + 1 ] < $total ) {
						$refunded = edd_sanitize_amount( $_POST['item_list_amount_' . $i + 1 ] );
						$note = sprintf( esc_html__( '2Checkout(Sparkle): Partial refund for %s processed in 2Checkout. ' ),  $refunded );
						$order->add_order_note( $note );

					} else {
						$order->update_status( 'refunded', esc_html__( '2Checkout(Sparkle): Payment refunded in 2Checkout. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' ) );

					}
					die( '2' );

					break;


				case 'FRAUD_STATUS_CHANGED':

					switch ( $_POST['fraud_status'] ) {
						case 'pass':
							$msg = esc_html__( '2Checkout(Sparkle): Fraud review passed. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' );
							$order->update_status( 'processing', $msg );							
							die( '3' );
							break;

						case 'fail':
							$msg = esc_html__( '2Checkout(Sparkle): 2Checkout fraud review failed. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' );
							$order->update_status( 'on-hold', $msg );
							die( '4' );
							break;

						case 'wait':
							$msg = esc_html__( '2Checkout(Sparkle): 2Checkout fraud review in progress. The status is set using INS. ', 'sparkle-2co-digital-payment-lite' );
							$order->update_status( 'on-hold', $msg );
							die( '5' );
							break;

					}

					die( '6' );
					break;

			}
			die( '1' );
		}
	}
}

//get the instance of a class
Sparkle_2CO_DP_Woo::get_instance();