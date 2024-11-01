<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); // Exit if accessed directly.

class Sparkle_2CO_DP_Api{
	var $merchant_code = '';
	var $secret_key = '';

    /**
     * Class constructor
     * Since 1.0.0
    */
	public function __construct( $secret_key, $merchant_code ){
        $this->secret_key    = $secret_key;
        $this->merchant_code = $merchant_code;
    }

    /**
     *  Checks the payment signature
     *  since 1.0.0
     *  @return array
     */
    public function check_signature(){
        $signature = isset( $_POST['HASH'] ) ? sanitize_text_field( $_POST['HASH'] ) : false;
        
        if( ! $signature )
            return array( 'error' => esc_html__( 'Signature not found', 'sparkle-2co-digital-payment-lite' ) );
    
        $prepare_hash = '';

        foreach( $_POST as $key => $val ) {
        
            if( $key != 'HASH' ) {
                if( is_array( $val ) ) {
                    $prepare_hash .= $this->array_expand( $val );
                } else {
                    $size           = strlen( stripslashes( $val ) );
                    $prepare_hash  .= $size . stripslashes( $val );
                }
            }
        }
        $hash = $this->hmac( $this->secret_key, $prepare_hash );

        if( $hash == $signature ) {

            $date_return = date( 'YmdGis' );
            
            $ipn_pid  = sanitize_text_field( $_POST['IPN_PID'][0] );
            $ipn_name = sanitize_text_field( $_POST['IPN_PNAME'][0] );
            $ipn_date = sanitize_text_field( $_POST['IPN_DATE'] );
            $ref_no   = (int) $_POST['REFNO'];
            $info     = $this->get_order_info( $ref_no );
            
            if( is_array( $info ) ){
                return $info;
            }
            
            $return  = strlen( $ipn_pid ) . $ipn_pid . strlen( $ipn_name ) . $ipn_name;
            $return .= strlen( $ipn_date ) . $ipn_date . strlen( $date_return ) . $date_return;

            return array(
                    'date_return' => $date_return,
                    'result_hash' => $this->hmac( $this->secret_key, $return )
                );
        } else {
            return array( 'error' => esc_html__( 'Bad IPN Signature', 'sparkle-2co-digital-payment-lite' ) );
        }
    }

    /**
     * Array Expand function
     * Since 1.0.0
     * @param  array
     * Code from 2checkout's knowledge center
     */
    private function array_expand( $array ){
        $retval = "";
        foreach( $array as $i => $value ){
            if( is_array( $value ) ){
                $retval .= array_expand( $value );
            }else{
                $size    = strlen( $value );
                $retval .= $size.$value;
            }
        }
        return $retval;
    }

    /**
     * FUNCTIONS FOR HMAC
     * Since 1.0.0
     * @param  [type] $key
     * @param  [type] $data
     * @return string
     * Code from 2checkout's knowledge center
     */
    private function hmac ( $key, $data ){
        $b = 64; // byte length for md5
        
        if ( strlen( $key ) > $b ) {
            $key = pack( "H*",md5( $key ) );
        }

        $key    = str_pad( $key, $b, chr( 0x00 ) );
        $ipad   = str_pad( '', $b, chr( 0x36 ) );
        $opad   = str_pad( '', $b, chr( 0x5c ) );
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;
        
        return md5( $k_opad . pack( "H*", md5( $k_ipad . $data ) ) );
    }

    /**
     * Get the order status info
     * @param $ref_no Reference number
     * Since 1.0.0
     * @return array
     */
    private function get_order_info( $ref_no ) {
        $date = gmdate( 'Y-m-d H:i:s', time() );
        $hash = hash_hmac( 'md5', strlen( $this->merchant_code ) . $this->merchant_code . strlen( $date ) . $date, $this->secret_key );
        
        $header = array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'X-Avangate-Authentication' => 'code="' . $this->merchant_code . '" date="' . $date . '" hash="' . $hash . '"',
        );
        
        $response = wp_remote_post( 
                        'https://api.2checkout.com/rest/6.0/orders/' . $ref_no . '/', 
                        array(
                            'method'    => 'GET',
                            'timeout'   => 120,
                            'sslverify' => false,
                            'headers'   => $header,
                            'body'      => '',
                        )
        );
        
        if ( is_wp_error( $response ) ) {
            
            $error_message = $response->get_error_message();
            
            return array( 'error' => esc_html__( 'Could not connect', 'sparkle-2co-digital-payment-lite' ) . ' ' . $error_message );
            
        } else {
            $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
            return ( $response_body['Status'] == 'COMPLETE' || $response_body['ApproveStatus'] == 'OK' ) ? true : array(
                'error' => esc_html__( 'Payment status is not complete', 'sparkle-2co-digital-payment-lite' ),
                'data'  => $response_body
            );
        }
    }

    /**
     * Check the status of the payment using 2Checkout API
     * Since 1.0.0
     * @params $payment_id Payment ID
     * @params $ref_no Reference number of payment
     * @return Array
     */
	public function check_status( $payment_id, $ref_no ){
		$date = gmdate( 'Y-m-d H:i:s', time() );
        
        $md5_hash = hash_hmac( 'md5',
            strlen( $this->merchant_code ) . $this->merchant_code . strlen( $date ) . $date, $this->secret_key
        );
        
        $header = array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'X-Avangate-Authentication' => 'code="' . $this->merchant_code . '" date="' . $date . '" hash="' . $md5_hash . '"',
        );
        
        $args = array(
		            'method'    => 'GET',
		            'timeout'   => 120,
		            'sslverify' => false,
		            'headers'   => $header,
		            'body'      => ''
		        );

        $url = 'https://api.2checkout.com/rest/6.0/orders/' . $ref_no . '/';
        
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ){
            return false;
        }
        
    	$order_response = json_decode( wp_remote_retrieve_body( $response ), true );
        if( isset( $order_response['Status'] ) ) {
            
            $status    = sanitize_text_field( $order_response['Status'] );
            $ap_status = sanitize_text_field( $order_response['ApproveStatus'] );
            $v_status  = sanitize_text_field( $order_response['VendorApproveStatus'] );
            $ext_hash  = sanitize_text_field( $order_response['ExternalReference'] );
            
            if( in_array( $status, [ 'AUTHRECEIVED', 'PENDING', 'COMPLETE' ] ) &&
                in_array( $ap_status, [ 'WAITING', 'OK' ] ) ) {
                
                if( (int)$payment_id !== (int)$ext_hash ) {
                    return array( 'status' => 'fail' );
                }
                
                if( $v_status === 'OK' ) {
                    return array( 'status' => 'completed' );
                }
                
            } else {
                return array( 'status' => 'fail' );
            }
        }else{
            // AUTHENTICATION_ERROR
            return $order_response;
        }
        return false;
	}

    /**
     * Convertplus Serialize
     * Since 1.0.0
     * @return String
     */
    public function convertplus_serialize( $params ) {
        ksort( $params );
        $map_data = array_map( function ( $value ) {
            return strlen( stripslashes( $value ) ) . stripslashes( $value );
        }, $params );

        return implode( '', $map_data );
    }

    /**
     * Generate buy link signature using ConvertPlus
     * Since 1.0.0
     * @param  $params | parameters for generating signature
     * @param  $buy_link_secret_word | 2checkout's buy link secret word
     * @return string
     */
    public function convertplus_buy_link_signature( $params, $buy_link_secret_word ) {
        $signature_params = array(
            'return-url',
            'return-type',
            'expiration',
            'order-ext-ref',
            'item-ext-ref',
            'customer-ref',
            'customer-ext-ref',
            'currency',
            'prod',
            'price',
            'qty',
            'tangible',
            'type',
            'opt',
            'description',
            'recurrence',
            'duration',
            'renewal-price'
        );

        $filtered_params = array_filter( $params, function ( $key ) use ( $signature_params ) {
            return in_array( $key, $signature_params );
        }, ARRAY_FILTER_USE_KEY );

        $serialize_string = $this->convertplus_serialize( $filtered_params );

        $signature = hash_hmac( 'sha256', $serialize_string, html_entity_decode( $buy_link_secret_word ) );

        return $signature;
    }

    /**
     * Generate return signature
     * Since 1.0.0
     * @param  $params | parameters for generating signature
     * @param  $buy_link_secret_word | 2checkout's buy link secret word
     * @return string
     */
    public function generate_return_signature( $params, $buy_link_secret_word ) {
        if ( empty( $params ) || ! isset( $params['signature'] ) || empty( $params['signature'] ) ) {
            return false;
        }

        // Remove signature key from params list.
        unset( $params['signature'], $params['edd-payment-id'] );
        $serialize_string = $this->convertplus_serialize( $params );

        return hash_hmac( 'sha256', $serialize_string, html_entity_decode( $buy_link_secret_word ) );
    }
}