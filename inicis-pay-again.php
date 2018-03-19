<?php
class WC_Gateway_INICIS_Pay_Again extends WC_Payment_Gateway {

	public function __construct() {
		//settings
		$this->id = 'iamport_inicis_pay_again'; //id가 먼저 세팅되어야 init_setting가 제대로 동작
		$this->method_title = '이니시스(비인증 결제)';
		$this->method_description = '이니시스 PG사와, 아임포트를 통해 비인증결제를 사용하실 수 있습니다.';
		$this->has_fields = true;

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->settings['title'];
		// $this->description = $this->settings['description'];

		$this->imp_rest_key = $this->settings['imp_rest_key'];
		$this->imp_rest_secret = $this->settings['imp_rest_secret'];


		//import values
		$this->imp_user_code = $this->settings['imp_user_code'];
		$this->imp_rest_key = $this->settings['imp_rest_key'];
		$this->imp_rest_secret = $this->settings['imp_rest_secret'];


		//woocommerce action
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( class_exists( 'WC_Pay_Again_Order' ) ) {
			add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'iamport_credit_card_form_fields' ), 10, 2);
			add_action( 'a_action', array( $this, 'print_a' ), 10, 2 );	
		}
	}

	public function init_form_fields() {
		//iamport기본 플러그인에 해당 정보가 세팅되어있는지 먼저 확인
		$default_api_key = get_option('iamport_rest_key');
		$default_api_secret = get_option('iamport_rest_secret');

		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => '이니시스(비인증 결제) 결제 사용',
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '비인증 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'imp_user_code' => array(
				'title' => __( '[아임포트] 가맹점 식별코드', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '[아임포트] 가맹점 식별코드', 'iamport-for-woocommerce' ),
				'default' => $default_api_key
			),
			'imp_rest_key' => array(
				'title' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
				'default' => $default_api_key
			),
			'imp_rest_secret' => array(
				'title' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
				'default' => $default_api_secret
			)
		);
	}

	public function iamport_credit_card_form_fields($default_fields, $id) {
		if ( $id !== $this->id ) 	return $default_fields;

		$args = array('fields_have_names'=>true);

		if ($this->is_first_payment()) {
			$iamoprt_fields = array(
				'info-field' => '<p>카드 등록을 먼저 하셔야합니다.</p><button class="button" id="inicis-register-card">카드 등록</button>',
			);
		} else {
			$iamoprt_fields = array(
				'info-field' => '<p>입력하셨던 카드 정보로 결제가 이루어집니다.</p><button class="button">결제</button>',
			);
		}
		return $iamoprt_fields;
	}

	public function payment_fields() {
		ob_start();

		$private_key = $this->get_private_key();
		$public_key = $this->get_public_key($private_key, $this->keyphrase());
		?>
		<div id="iamport-inicis-card-holder" data-module="<?=$public_key['module']?>" data-exponent="<?=$public_key['exponent']?>">
			<?php $this->credit_card_form( array( 'fields_have_names' => false ) ); ?>
		</div>
		<?php
		ob_end_flush();
	}

	public function process_payment( $order_id, $retry = true ) {
		return $this->process_pay_again( $order_id, $retry );
	}

	public function iamport_payment_info( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );
		$order_name = $this->get_order_name($order, '');
		$redirect_url = add_query_arg( array('order_id'=>$order_id, 'wc-api'=>get_class( $this )), $order->get_checkout_payment_url());

		$response = array(
			'user_code' => $this->imp_user_code,
			'name' => $order_name,
			'merchant_uid' => $order->order_key,
			'amount' => $order->order_total, //amount
			'buyer_name' => $order->billing_last_name . $order->billing_first_name, //name
			'buyer_email' => $order->billing_email, //email
			'buyer_tel' => $order->billing_phone, //tel
			'buyer_addr' => strip_tags($order->get_formatted_shipping_address()), //address
			'buyer_postcode' => $order->shipping_postcode,
			'vbank_due' => date('Ymd', strtotime("+1 day")),
			'm_redirect_url' => $redirect_url
		);

		return $response;
	}

	public function process_pay_again( $order_id, $retry = true ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 && $this->is_first_payment() ) {

			// First Payment
			$iamport_response = $this->process_pay_again_payment( $order, $order->get_total(), true );


			if ( is_wp_error($iamport_response) ) {
				throw new Exception( sprintf( '비인증 결제 최초 과금(signup fee)에 실패하였습니다. (상세사유 : %s)' , $iamport_response->get_error_message() ) );
			}
			return $iamport_response;
		} else if ( $order->get_total() > 0 && !$this->is_first_payment() ) {

			// Non First Payment
			$iamport_response = $this->process_pay_again_payment( $order, $order->get_total(), false );

			if ( is_wp_error($iamport_response) ) {
				throw new Exception( sprintf( '비인증 결제 최초 과금(signup fee)에 실패하였습니다. (상세사유 : %s)' , $iamport_response->get_error_message() ) );
			}
		} else {
			$order->payment_complete();
		}

		WC()->cart->empty_cart();

		// Return thank you page redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order )
		);
	}

	public function is_first_payment() {
		$result = $this->getPayAgainCustomer()->success;
		if ($result == null) return true;
		elseif ($result == 1) return false;
		else return false;
	}

	public function deleteCurrentPayAgainCustomer() {
		try {
			require_once(dirname(__FILE__).'/lib/iamport.php');
			$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
			$prefix = get_option('_iamport_customer_prefix');
			if ( empty($prefix) ) echo '구매자 정보가 없습니다';
			$user_id = get_current_user_id();
			if ( empty($user_id) )		throw new Exception( __( "비인증 결제기능은 로그인된 사용자만 사용하실 수 있습니다.", 'iamport-for-woocommerce' ), 1);

			$customer_uid = $prefix . 'c' . $user_id;
			$iamport->customer_delete($customer_uid);
			return true;
		} catch(Exception $e) {
			return new WP_Error( 'iamport_error', $e->getMessage() );
		}
	}

	public function getPayAgainCustomer() {
		try {
			require_once(dirname(__FILE__).'/lib/iamport.php');
			$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
			$prefix = get_option('_iamport_customer_prefix');
			if ( empty($prefix) ) echo '구매자 정보가 없습니다';
			$user_id = get_current_user_id();
			if ( empty($user_id) )		throw new Exception( __( "비인증 결제기능은 로그인된 사용자만 사용하실 수 있습니다.", 'iamport-for-woocommerce' ), 1);

			$customer_uid = $prefix . 'c' . $user_id;
			$result = $iamport->subscribeCustomerGet($customer_uid);
			return $result;
		} catch(Exception $e) {
			return new WP_Error( 'iamport_error', $e->getMessage() );
		}
	}

	/**
	 * process_pay_again_payment function.
	 *
	 * @access public
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 * @param  bool initial_payment
	 */
	public function process_pay_again_payment( $order = '', $amount = 0, $initial_payment = false ) {
		try {
			$customer_uid 	 = $this->get_customer_uid($order);
			if ($initial_payment) {
				global $woocommerce;
				if ( $order->has_status(array('processing', 'completed')) ) {
					$redirect_url = $order->get_checkout_order_received_url();
				} else {
					$redirect_url = $order->get_checkout_payment_url( false );
				}
				$iamport_info = $this->iamport_payment_info( $order->id );
				$result = array(
					'result' => 'success',
					'redirect'	=> $redirect_url,
					'order_id' => $order->id,
					'order_key' => $order->order_key,
					'iamport' => $iamport_info,
					'customer_uid' => $customer_uid
				);
			} else {
				$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
				$result = $iamport->sbcr_again(array(
					'amount' => $amount,
					// 'vat' => 0,
					'merchant_uid' => $order->order_key.date('md'),
					'customer_uid' => $customer_uid,
					'name' => $this->get_order_name($order, $initial_payment),
					'buyer_name' => $order->billing_last_name . $order->billing_first_name,
					'buyer_email' => $order->billing_email,
					'buyer_tel' => $order->billing_phone
				));

				$payment_data = $result->data;
				if ( $result->success ) {
					if ( $payment_data->status == 'paid' ) {
						$order_id = $order->id;

						$this->_iamport_post_meta($order_id, '_iamport_rest_key', $this->imp_rest_key);
						$this->_iamport_post_meta($order_id, '_iamport_rest_secret', $this->imp_rest_secret);
						$this->_iamport_post_meta($order_id, '_iamport_provider', $payment_data->pg_provider);
						$this->_iamport_post_meta($order_id, '_iamport_paymethod', $payment_data->pay_method);
						$this->_iamport_post_meta($order_id, '_iamport_receipt_url', $payment_data->receipt_url);
						$this->_iamport_post_meta($order_id, '_iamport_customer_uid', $customer_uid);

						$order->add_order_note( sprintf( __( '비인증 결제 회차 과금(%s차결제)에 성공하였습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $order->suspension_count, $payment_data->imp_uid ) );

						$order->payment_complete( $payment_data->imp_uid );
					} else {
						$message = sprintf( __( '비인증 결제 회차 과금(%s차결제)에 실패하였습니다. (status : %s)', 'iamport-for-woocommerce' ) , $order->suspension_count, $payment_data->status );
						$order->add_order_note( $message );

						return new WP_Error( 'iamport_error', $message );
					}
				} else {
					$message = sprintf( __( '비인증 결제 회차 과금(%s차결제)에 실패하였습니다. (사유 : %s)', 'iamport-for-woocommerce' ) , $order->suspension_count, $result->error['message'] );

					$order->add_order_note( $message );

					return new WP_Error( 'iamport_error', $message );
				}
			}
			return $result;
		} catch(Exception $e) {
			return new WP_Error( 'iamport_error', $e->getMessage() );
		}
	}

	//common for refund
	public function process_refund($order_id, $amount = null, $reason = '') {
		require_once(dirname(__FILE__).'/lib/iamport.php');

		global $woocommerce;
		$order = new WC_Order( $order_id );

		$imp_uid = $order->get_transaction_id();
		$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);

		// 만약 데이터 동기화에 실패하는 상황이 되어 imp_uid가 없더라도 order_key가 있으면 취소를 시도해볼 수 있다.
		if ( empty($imp_uid) ) {
			$cancel_data = array(
				'merchant_uid'=>$order->order_key,
				'reason'=>$reason,
				'amount'=>$amount
			);
		} else {
			$cancel_data = array(
				'imp_uid'=>$imp_uid,
				'reason'=>$reason,
				'amount'=>$amount
			);
		}

		$result = $iamport->cancel($cancel_data);

		if ( $result->success ) {
			$payment_data = $result->data;
			$order->add_order_note( sprintf(__( '%s 원 환불완료', 'iamport-for-woocommerce'), number_format($amount)) );
			if ( $payment_data->amount == $payment_data->cancel_amount ) {
				$order->update_status('refunded');
			}
			return true;
		} else {
			$order->add_order_note($result->error['message']);
			return false;
		}

		return false;
	}

	protected function get_order_name($order, $initial_payment) {
		logw('hey');
		logw('initial_payment');
		logw_a($initial_payment);
		if ( $initial_payment ) {
			$order_name = "#" . $order->get_order_number() . "번 주문 비인증 결제(최초과금)";
		} else {
			$order_name = "#" . $order->get_order_number() . sprintf("번 주문 비인증 결제(%s회차)", $order->suspension_count);
		}

		return $order_name;
	}

	private function get_customer_uid($order) {
		$prefix = get_option('_iamport_customer_prefix');
		if ( empty($prefix) ) {
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$hasher = new PasswordHash( 8, false );
			$prefix = md5( $hasher->get_random_bytes( 32 ) );

			if ( !add_option( '_iamport_customer_prefix', $prefix ) )	throw new Exception( __( "비인증 결제 구매자정보 생성에 실패하였습니다.", 'iamport-for-woocommerce' ), 1);
		}

		$user_id = $order->user_id; // wp_cron에서는 get_current_user_id()가 없다.
		if ( empty($user_id) )		throw new Exception( __( "비인증 결제기능은 로그인된 사용자만 사용하실 수 있습니다.", 'iamport-for-woocommerce' ), 1);

		return $prefix . 'c' . $user_id;
	}

	protected function _iamport_post_meta($order_id, $meta_key, $meta_value) {
		if ( !add_post_meta($order_id, $meta_key, $meta_value, true) ) {
			update_post_meta($order_id, $meta_key, $meta_value);
		}
	}

	private function format_expiry($expiry) {
		$arr = explode('/', $expiry);
		if ( $arr && count($arr) == 2 ) {
			$month = trim($arr[0]);
			$year = trim($arr[1]);

			if ( strlen($year) == 2 ) {
				$year = '20'.$year;
			}

			return $year . '-' . $month;
		}

		return false;
	}

	//rsa

	private function keyphrase() {
		$keyphrase = get_option('_iamport_rsa_keyphrase');
		if ( $keyphrase )		return $keyphrase;

		require_once( ABSPATH . 'wp-includes/class-phpass.php');
		$hasher = new PasswordHash( 8, false );
		$keyphrase = md5( $hasher->get_random_bytes( 16 ) );

		if ( add_option('_iamport_rsa_keyphrase', $keyphrase) )		return $keyphrase;

		return false;
	}

	private function get_private_key() {
		$private_key = get_option('_iamport_rsa_private_key');

		if ( $private_key )		return $private_key; //있으면 기존 것을 반환

		$config = array(
			"digest_alg" => "sha256",
			"private_key_bits" => 4096,
			"private_key_type" => OPENSSL_KEYTYPE_RSA
		);

		// Create the private key
		$res = openssl_pkey_new($config);
		$success = openssl_pkey_export($res, $private_key, $this->keyphrase()); //-------BEGIN RSA PRIVATE KEY...로 시작되는 문자열을 $private_key에 저장

		if ( $success && add_option('_iamport_rsa_private_key', $private_key) )		return $private_key;

		return false;
	}

	private function get_public_key($private_key, $keyphrase) {
		$res = openssl_pkey_get_private($private_key, $keyphrase);
		$details = openssl_pkey_get_details($res);

		return array('module'=>$this->to_hex($details['rsa']['n']), 'exponent'=>$this->to_hex($details['rsa']['e']));
	}

	private function to_hex($data) {
		return strtoupper(bin2hex($data));
	}

	private function decrypt($encrypted, $private_key) {
		$payload = pack('H*', $encrypted);
		$pk_info = openssl_pkey_get_private($private_key, $this->keyphrase());
		if ( $pk_info && openssl_private_decrypt($payload, $decrypted, $pk_info) ) {
			return $decrypted;
		}

		return false;
	}

}
