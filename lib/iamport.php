<?php
if ( !class_exists('WooIamportAuthException') ) {
	class WooIamportAuthException extends Exception {
		public function __construct($message, $code) {
			parent::__construct($message, $code);
		}
	}
}

if ( !class_exists('WooIamportRequestException') ) {
	class WooIamportRequestException extends Exception {

		protected $response;

		public function __construct($response) {
			$this->response = $response;

			parent::__construct($response->message, $response->code);
		}

	}
}

if ( !class_exists('WooIamportResult') ) {
	class WooIamportResult {

		public $success = false;
		public $data;
		public $error;

		public function __construct($success=false, $data=null, $error=null) {
			$this->success = $success;
			$this->data = $data;
			$this->error = $error;
		}

	}
}

if ( !class_exists('WooIamportPayment') ) {
	class WooIamportPayment {

		protected $response;
		protected $custom_data;

		public function __construct($response) {
			$this->response = $response;

			$this->custom_data = json_decode($response->custom_data);
		}

		public function __get($name) {
			if (isset($this->response->{$name})) {
				return $this->response->{$name};
			}
		}

		public function getCustomData($name=null) {
			if ( is_null($name) )	return $this->custom_data;

			return $this->custom_data->{$name};
		}

	}
}

if ( !class_exists('WooIamport') ) {
	class WooIamport {

		const GET_TOKEN_URL 			= 'https://api.iamport.kr/users/getToken';
		const GET_PAYMENT_URL 			= 'https://api.iamport.kr/payments/';
		const FIND_PAYMENT_URL 			= 'https://api.iamport.kr/payments/find/';
		const CANCEL_PAYMENT_URL 		= 'https://api.iamport.kr/payments/cancel/';
		const SBCR_ONETIME_PAYMENT_URL 	= 'https://api.iamport.kr/subscribe/payments/onetime/';
		const SBCR_AGAIN_PAYMENT_URL 	= 'https://api.iamport.kr/subscribe/payments/again/';
		const SBCR_FOREIGN_PAYMENT_URL 	= 'https://api.iamport.kr/subscribe/payments/foreign/';
		const SBCR_CUSTOMER_URL 		= 'https://api.iamport.kr/subscribe/customers/';
		
		// const TOKEN_HEADER = 'X-ImpTokenHeader';
		const TOKEN_HEADER = 'Authorization';

		private $imp_key = null;
		private $imp_secret = null;

		private $access_token = null;
		private $expired_at = null;
		private $now = null;

		public function __construct($imp_key, $imp_secret) {
			$this->imp_key = $imp_key;
			$this->imp_secret = $imp_secret;
		}

		public function findByImpUID($imp_uid) {
			try {
				$response = $this->getResponse(self::GET_PAYMENT_URL.$imp_uid);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function findByMerchantUID($merchant_uid) {
			try {
				$response = $this->getResponse(self::FIND_PAYMENT_URL.$merchant_uid);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function cancel($data) {
			try {
				$access_token = $this->getAccessCode();

				$keys = array_flip(array('amount', 'reason', 'refund_holder', 'refund_bank', 'refund_account'));
				$cancel_data = array_intersect_key($data, $keys);
				if ( $data['imp_uid'] ) {
					$cancel_data['imp_uid'] = $data['imp_uid'];
				} else if ( $data['merchant_uid'] ) {
					$cancel_data['merchant_uid'] = $data['merchant_uid'];
				} else {
					return new WooIamportResult(false, null, array('code'=>'', 'message'=>'취소하실 imp_uid 또는 merchant_uid 중 하나를 지정하셔야 합니다.'));
				}

				$response = $this->postResponse(
					self::CANCEL_PAYMENT_URL, 
					$cancel_data,
					array(self::TOKEN_HEADER.': '.$access_token)
				);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function sbcr_onetime($data) {
			try {
				$access_token = $this->getAccessCode();

				$keys = array_flip(array('merchant_uid', 'amount', 'vat', 'card_number', 'expiry', 'birth', 'pwd_2digit', 'customer_uid', 'name', 'buyer_name', 'buyer_email', 'buyer_tel'));
				$onetime_data = array_intersect_key($data, $keys);

				$response = $this->postResponse(
					self::SBCR_ONETIME_PAYMENT_URL, 
					$onetime_data,
					array(self::TOKEN_HEADER.': '.$access_token)
				);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function sbcr_again($data) {
			try {
				$access_token = $this->getAccessCode();

				$keys = array_flip(array('merchant_uid', 'amount', 'vat', 'customer_uid', 'name', 'buyer_name', 'buyer_email', 'buyer_tel'));
				$onetime_data = array_intersect_key($data, $keys);

				$response = $this->postResponse(
					self::SBCR_AGAIN_PAYMENT_URL, 
					$onetime_data,
					array(self::TOKEN_HEADER.': '.$access_token)
				);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function sbcr_foreign($data) {
			try {
				$access_token = $this->getAccessCode();

				$keys = array_flip(array('merchant_uid', 'amount', 'vat', 'card_number', 'expiry', 'cvc', 'name', 'buyer_name', 'buyer_email', 'buyer_tel'));
				$onetime_data = array_intersect_key($data, $keys);

				$response = $this->postResponse(
					self::SBCR_FOREIGN_PAYMENT_URL, 
					$onetime_data,
					array(self::TOKEN_HEADER.': '.$access_token)
				);

				$payment_data = new WooIamportPayment($response);
				return new WooIamportResult(true, $payment_data);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function subscribeCustomerGet($customer_uid) {
			try {
				$response = $this->getResponse(self::SBCR_CUSTOMER_URL.$customer_uid);
			    
			    return new WooIamportResult(true, $response);
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		public function customer_delete($customer_uid) {
			try {
				$access_token = $this->getAccessCode();

				$response = $this->deleteResponse(
					self::SBCR_CUSTOMER_URL . $customer_uid, 
					array(self::TOKEN_HEADER.': '.$access_token)
				);

				return $response;
			} catch(WooIamportAuthException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(WooIamportRequestException $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			} catch(Exception $e) {
				return new WooIamportResult(false, null, array('code'=>$e->getCode(), 'message'=>$e->getMessage()));
			}
		}

		private function getResponse($request_url, $request_data=null) {
			$access_token = $this->getAccessCode();
			$headers = array(self::TOKEN_HEADER.': '.$access_token, 'Content-Type: application/json');

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $request_url);
			curl_setopt($ch, CURLOPT_POST, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			//execute get
			$body = curl_exec($ch);
			$error_code = curl_errno($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$r = json_decode(trim($body));
			curl_close($ch);

			if ( $error_code > 0 )	throw new Exception("Request Error(HTTP STATUS : ".$status_code.")", $error_code);
			if ( empty($r) )	throw new Exception("API서버로부터 응답이 올바르지 않습니다. ".$body, 1);
			if ( $r->code !== 0 )	throw new WooIamportRequestException($r);

			return $r->response;
		}

		private function postResponse($request_url, $post_data=array(), $headers=array()) {
			$post_data_str = json_encode($post_data);
			$default_header = array('Content-Type: application/json', 'Content-Length: ' . strlen($post_data_str));
			$headers = array_merge($default_header, $headers);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $request_url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			//execute post
			$body = curl_exec($ch);
			$error_code = curl_errno($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			$r = json_decode(trim($body));
			curl_close($ch);

			if ( $error_code > 0 )	throw new Exception("AccessCode Error(HTTP STATUS : ".$status_code.")", $error_code);
			if ( empty($r) )	throw new Exception("API서버로부터 응답이 올바르지 않습니다. ".$body, 1);
			if ( $r->code !== 0 )	throw new WooIamportRequestException($r);

			return $r->response;
		}

		private function deleteResponse($request_url, $headers=array()) {
			$default_header = array('Content-Type: application/json');
			$headers = array_merge($default_header, $headers);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $request_url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			//execute post
			$body = curl_exec($ch);
			$error_code = curl_errno($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			$r = json_decode(trim($body));
			curl_close($ch);

			if ( $error_code > 0 )	throw new Exception("AccessCode Error(HTTP STATUS : ".$status_code.")", $error_code);
			if ( empty($r) )	throw new Exception("API서버로부터 응답이 올바르지 않습니다. ".$body, 1);
			if ( $r->code !== 0 )	throw new WooIamportRequestException($r);

			return $r->response;
		}

		private function getAccessCode() {
			try {
				$now = time();
				if ( $now < $this->expired_at && !empty($this->access_token) )	return $this->access_token;

				$this->expired_at = null;
				$this->access_token = null;
				$response = $this->postResponse(
					self::GET_TOKEN_URL, 
					array(
						'imp_key' => $this->imp_key,
						'imp_secret' => $this->imp_secret
					)
				);

				$offset = $response->expired_at - $response->now;

				$this->expired_at = time() + $offset;
				$this->access_token = $response->access_token;

				return $response->access_token;
			} catch(Exception $e) {
				throw new WooIamportAuthException('[API인증오류] '.$e->getMessage(), $e->getCode());
			}
		}
	}
}