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
	require_once("inicis-pay-again.php"); 
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_pay_again_gateway' );
	add_filter( 'woocommerce_order_button_text', 'pay_again_button_text' );
	//구매자가 직접 취소할 때 환불처리(processing상태일 때만)
	add_action( 'woocommerce_order_status_processing_to_cancelled', 'iamport_refund_payment', 10, 1 );
}

function iamport_refund_payment($order_id) {
	require_once(dirname(__FILE__).'/lib/iamport.php');

	$order = new WC_Order( $order_id );

	$imp_uid = $order->get_transaction_id();
	$rest_key = get_post_meta($order_id, '_iamport_rest_key', true);
	$rest_secret = get_post_meta($order_id, '_iamport_rest_secret', true);

	$iamport = new WooIamport($rest_key, $rest_secret);

	//전액취소
	$result = $iamport->cancel(array(
		'imp_uid'=>$imp_uid,
		'reason'=> __( '구매자 환불요청', 'iamport-for-woocommerce' )
	));

	if ( $result->success ) {
		$payment_data = $result->data;
		$order->add_order_note( __( '구매자요청에 의해 전액 환불완료', 'iamport-for-woocommerce' ) );
		if ( $payment_data->amount == $payment_data->cancel_amount ) {
			$old_status = $order->get_status();
			$order->update_status('refunded'); //iamport_refund_payment가 old_status -> cancelled로 바뀌는 중이라 update_state('refunded')를 호출하는 것이 향후에 문제가 될 수 있음

			//fire hook
			do_action('iamport_order_status_changed', $old_status, $order->get_status());
		}
	} else {
		$order->add_order_note($result->error['message']);
	}
}

function pay_again_button_text() {
	return '결제';
}

/**
*	Add the Gateway to WooCommerce
**/
function woocommerce_add_pay_again_gateway($methods) {
	$iamport_gateways[] = 'WC_Gateway_Pay_Again';
	array_push($iamport_gateways,'WC_Gateway_INICIS_Pay_Again');

	$methods = array_merge($methods, $iamport_gateways);
	return $methods;
}

/**
 *	Enque Basic Scripts & Jquery Clock Library
 */
add_action( 'wp_enqueue_scripts', 'pay_again_enque_scripts');
function pay_again_enque_scripts() {
	wp_enqueue_script( 'pay-again-button-js', plugin_dir_url( __FILE__ ) . 'assets/js/iamport.payagain.button.js', array(), false, true );
	wp_localize_script( 'pay-again-button-js', 'payagain', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
	wp_enqueue_style( 'payagain-ui-css', plugin_dir_url( __FILE__ ) . 'css/payagain-ui.css' );
	wp_enqueue_style( 'payagain-ionicons', plugin_dir_url( __FILE__ ) . 'assets/ion-icon/ionicons.min.css', array() );

	// Iamport Library
	wp_register_script( 'iamport_script', 'https://service.iamport.kr/js/iamport.payment-1.1.2.js', array('jquery') );
	wp_register_script( 'iamport_jquery_url', plugins_url( '/assets/js/url.min.js',plugin_basename(__FILE__) ));
	wp_register_script( 'iamport_script_for_woocommerce', plugins_url( '/assets/js/iamport.woocommerce.js',plugin_basename(__FILE__) ), array('jquery'), '20170507');
	wp_enqueue_script('iamport_script');
	wp_enqueue_script('iamport_jquery_url');
	wp_enqueue_script('iamport_script_for_woocommerce');


	wp_register_script( 'iamport_pay_again_rsa', plugins_url( '/assets/js/rsa.bundle.js',plugin_basename(__FILE__) ));
	wp_register_script( 'iamport_pay_again_script_for_woocommerce_rsa', plugins_url( '/assets/js/iamport.payagain.woocommerce.rsa.js',plugin_basename(__FILE__) ));
	wp_enqueue_script('iamport_pay_again_rsa');
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

add_action( 'wp_ajax_nopriv_delete_pay_again_inicis_method', 'delete_pay_again_inicis_method' );
add_action( 'wp_ajax_delete_pay_again_inicis_method', 'delete_pay_again_inicis_method' );
function delete_pay_again_inicis_method() {
	$gateway_pay_again = new WC_Gateway_INICIS_Pay_Again();
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

function show_pay_again_inicis_payment_method_button( $atts ) {
	include('template/template-inicis-billing-method-info.php');
}
add_shortcode('pay-again-billing-inicis-method-info', 'show_pay_again_inicis_payment_method_button');

/**
 *	Add Custom Tab To Woocommerce
 **/
function my_custom_endpoints() {
	add_rewrite_endpoint( 'billing-method-info', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'my_custom_endpoints' );
function my_custom_query_vars( $vars ) {
	$vars[] = 'billing-method-info';

	return $vars;
}
add_filter( 'query_vars', 'my_custom_query_vars', 0 );
function my_custom_my_account_menu_items( $items ) {
	// Remove the logout menu item.
	$logout = $items['customer-logout'];
	unset( $items['customer-logout'] );

	// Insert your custom endpoint.
	$items['billing-method-info'] = '결제 정보';

	// Insert back the logout item.
	$items['customer-logout'] = $logout;

	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'my_custom_my_account_menu_items' );
function my_custom_endpoint_content() {
	do_shortcode('[pay-again-billing-method-info]');
	do_shortcode('[pay-again-billing-inicis-method-info]');
}
add_action( 'woocommerce_account_billing-method-info_endpoint', 'my_custom_endpoint_content' );
