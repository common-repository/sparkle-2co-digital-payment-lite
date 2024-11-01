<?php
/**
 * Plugin Name: Sparkle 2CO Digital Payment Lite
 * Description: Simplify your digital payments using 2checkout - Addon for Easy Digital Downloads(EDD) and WooCommerce
 * Plugin URI: https://wp2checkout.com/
 * Author: Sparkle WP Themes
 * Author URI: https://sparklewpthemes.com
 * Requries at least: 4.0
 * Tested up to: 6.2
 * Version: 1.0.3
 * Text Domain: sparkle-2co-digital-payment-lite
 * Domain Path: languages
 * Network: false
 *
 * @package Sparkle 2CO Digital Payment
 * @author sparklewpthemes
 * @category Core
 */

/*
* Copyright (C) 2021  SparkleWPThemes
*/

defined( 'ABSPATH' ) or die( 'No Scrpit Kiddies Please!' );

if( !class_exists( 'Sparkle_2CO_Digital_Payment_Lite' ) ){
    class Sparkle_2CO_Digital_Payment_Lite{
        
        protected static $instance  = null;
        public $name                = "Sparkle 2CO Digital Payment Lite";
        public $version             = '1.0.3';

        /**
         * Plugin initialize with requried actions
         */
        public function __construct(){
            $this->define_plugin_constants();
            $this->load_plugin_textdomain();
            $this->includes();
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
         * Check Plugin Dependencies and show admin notice
         * Initialize Plugin Class
         * @return notice or instance  of class
         * @since 1.0.0
         */
        public static function check_plugin_dependency(){
            //Firstly, check if a dependency plugin - Easy Digital Downloads or WooCommerce is active or not.
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
            if ( in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins ) || in_array( 'woocommerce/woocommerce.php', $active_plugins ) ){
                return Sparkle_2CO_Digital_Payment_Lite::get_instance();
            }else{
                add_action( 'admin_notices', array( 'Sparkle_2CO_Digital_Payment_Lite', 'install_plugin_admin_notice' ) );
		        return;
            }
        }        

        /**
         * Admin Notice
         * @return string
         * @since 1.0.0
         */
        public static function install_plugin_admin_notice() {
            ?>
            <div class="error">
                <p><?php esc_html_e( 'Sparkle 2CO Digital Payment Lite is enabled but not effective. It requires WooCommerce or Easy Digital Download in order to work.', 'sparkle-2co-digital-payment-lite' ); ?></p>
            </div>
            <?php
        }

        /**
        * Define plugins contants
        * @since 1.0.0
        */
        private function define_plugin_constants(){
            defined( 'S2CODP_PATH' ) or define( 'S2CODP_PATH', plugin_dir_path( __FILE__ ) );
            defined( 'S2CODP_DIR_PATH' ) or define( 'S2CODP_DIR_PATH', plugin_dir_url( __FILE__ ) );
            defined( 'S2CODP_IMG_DIR' ) or define( 'S2CODP_IMG_DIR', plugin_dir_url( __FILE__ ) . 'assets/images/' );
            defined( 'S2CODP_JS_DIR' ) or define( 'S2CODP_JS_DIR', plugin_dir_url( __FILE__ ) . 'assets/js' );
            defined( 'S2CODP_VERSION' ) or define( 'S2CODP_VERSION', '1.0.0' );
            defined( 'S2CODP_TD' ) or define( 'S2CODP_TD', 'sparkle-2co-digital-payment-lite' );
            defined( 'S2CODP_LANG_DIR' ) or define( 'S2CODP_LANG_DIR', basename( dirname( __FILE__ ) ) . '/languages/' );
            defined( 'S2CODP_BASENAME' ) or define( 'S2CODP_BASENAME', plugin_basename( __FILE__ ) );
        }

        /**
         * Loads plugin text domain
         * @since 1.0.0
         */
        private function load_plugin_textdomain(){
            load_plugin_textdomain( 'sparkle-2co-digital-payment-lite', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' ); 
        }

        /**
         * Plugin required files
         * @since 1.0.0
         */
        private function includes(){
            require_once ( plugin_dir_path( __FILE__ ) . "includes/class_sparkle_2co_dp_library.php" );
            require_once ( plugin_dir_path( __FILE__ ) . "includes/class_sparkle_2checkout_dp_api.php" );
            
            $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
            
            if( in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins ) ){
                require_once ( plugin_dir_path( __FILE__ ) . "includes/edd_plugin_init.php" );
            }
            
            if( in_array( 'woocommerce/woocommerce.php', $active_plugins ) ){
                require_once( plugin_dir_path( __FILE__ ) . "includes/woo_plugin_init.php" );
            }
        }

    } // end of the class - Sparkle_2CO_Digital_Payment_Lite
}
add_action( 'plugins_loaded', array ( 'Sparkle_2CO_Digital_Payment_Lite', 'check_plugin_dependency'), 0 );