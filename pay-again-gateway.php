<?php
/**
 *	Plugin Name: Pay Again Gateway
 *	Description: 
 *	Version:     0.0.1
 *	Author:      Ruucm
 *	Author URI:  ruucm.me
 */

require_once("class-pay-again-order.php"); 

add_action('plugins_loaded', 'pay_again_init', 0);

function pay_again_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	require_once("pay-again.php"); 
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_pay_again_gateway' );
	add_filter( 'woocommerce_order_button_text', 'pay_again_button_text' );
}

function pay_again_button_text() {
	return 'Place Order 텍스트 변경';
}

/**
* Add the Gateway to WooCommerce
**/
function woocommerce_add_pay_again_gateway($methods) {
	$iamport_gateways[] = 'WC_Gateway_Pay_Again';
	$methods = array_merge($methods, $iamport_gateways);
	return $methods;
}

/**
 *	Add Jquery
 */
if (!is_admin()) add_action("wp_enqueue_scripts", "my_jquery_enqueue", 11);
function my_jquery_enqueue() {
	wp_deregister_script('jquery');
	wp_register_script('jquery', "http" . ($_SERVER['SERVER_PORT'] == 443 ? "s" : "") . "://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js", false, null);
	wp_enqueue_script('jquery');
}

/**
 *	Enque Basic Scripts & Jquery Clock Library
 */
add_action( 'wp_enqueue_scripts', 'pay_again_enque_scripts');
function pay_again_enque_scripts() {
	wp_enqueue_script( 'pay-again-button-js', plugin_dir_url( __FILE__ ) . 'pay_again_button.js', array(), false, true );
	wp_localize_script( 'pay-again-button-js', 'payagain', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
}

add_action( 'wp_ajax_nopriv_do_pay_again', 'do_pay_again' );
add_action( 'wp_ajax_do_pay_again', 'do_pay_again' );
function do_pay_again() {
	do_action( 'woocommerce_scheduled_subscription_payment_' . 'iamport_pay_again', 601, '' );
	echo 'hey!';
	die();
}
