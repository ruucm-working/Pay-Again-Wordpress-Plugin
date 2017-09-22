<?php
/**
 *	Plugin Name: Pay Again Gateway
 *	Plugin URI: https://github.com/ruucm/Pay-Again-Wordpress-Plugin
 *	Description: 아임포트 비인증결제를 위한 워드프레스 플러그인
 *	Version:     1.0.0
 *	Author:      Ruucm
 *	Author URI:  https://ruucm.me
 */

require_once("class-pay-again-order.php"); 

add_action('plugins_loaded', 'pay_again_init', 0);

function pay_again_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	require_once("nicepay-pay-again.php"); 
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_pay_again_gateway' );
	add_filter( 'woocommerce_order_button_text', 'pay_again_button_text' );
}

function pay_again_button_text() {
	return '결제';
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
 *	Enque Basic Scripts & Jquery Clock Library
 */
add_action( 'wp_enqueue_scripts', 'pay_again_enque_scripts');
function pay_again_enque_scripts() {
	wp_enqueue_script( 'pay-again-button-js', plugin_dir_url( __FILE__ ) . 'pay_again_button.js', array(), false, true );
	wp_localize_script( 'pay-again-button-js', 'payagain', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
	wp_enqueue_style( 'payagain-ui-css', plugin_dir_url( __FILE__ ) . 'css/payagain-ui.css' );
	wp_enqueue_style( 'payagain-ionicons', plugin_dir_url( __FILE__ ) . 'assets/ion-icon/ionicons.min.css', array() );
}

add_action( 'wp_ajax_nopriv_delete_pay_again_method', 'delete_pay_again_method' );
add_action( 'wp_ajax_delete_pay_again_method', 'delete_pay_again_method' );
function delete_pay_again_method() {
	$gateway_pay_again = new WC_Gateway_Pay_Again();
	$res = $gateway_pay_again->deleteCurrentPayAgainCustomer();
	if ($res)
		echo '카드정보 삭제에 성공했습니다';
	else
		echo '카드정보 삭제에 실패했습니다';
	die();
}

function show_pay_again_payment_method_button( $atts ) {
	include('template/template-billing-method-info.php');
}
add_shortcode('pay-again-billing-method-info', 'show_pay_again_payment_method_button');
