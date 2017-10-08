jQuery.fn.serializeObject = function() {
	var obj = null;
	try {
		if ( this[0].tagName && this[0].tagName.toUpperCase() == "FORM" ) {
			var arr = this.serializeArray();
			if ( arr ) {
				obj = {};
				jQuery.each(arr, function() {
					obj[this.name] = this.value;
				});				
			}//if ( arr ) {
		}
	}
	catch(e) {alert(e.message);}
	finally  {}

	return obj;
};

jQuery(function($) {
	var iamport_gateways = [
		'iamport_pay_again',
		'iamport_foreign'
	];

	var iamport_checkout_types = (function() {
		var arr = [],
			len = iamport_gateways.length;
		for (var i = 0; i < len; i++) {
			arr.push( 'checkout_place_order_' + iamport_gateways[i] );
		};

		return arr;
	}());

	function in_iamport_gateway(gateway) {
		for (var i = iamport_gateways.length - 1; i >= 0; i--) {
			if ( gateway === iamport_gateways[i] )	return true;
		};

		return false;
	}

	function handle_error($form, err) {
		// Reload page
		if ( err.reload === 'true' ) {
			window.location.reload();
			return;
		}

		// Remove old errors
		$( '.woocommerce-error, .woocommerce-message' ).remove();

		// Add new errors
		$form.prepend( err.messages );

		// Cancel processing
		$form.removeClass( 'processing' ).unblock();

		// Lose focus for all fields
		$form.find( '.input-text, select' ).blur();

		// Scroll to top
		$( 'html, body' ).animate({
			scrollTop: ( $form.offset().top - 100 )
		}, 1000 );

		// Trigger update in case we need a fresh nonce
		if ( err.refresh === 'true' )
			$( 'body' ).trigger( 'update_checkout' );

		$( 'body' ).trigger( 'checkout_error' );
	}

	function error_html(plain_message) {
		return '<ul class="woocommerce-error">\n\t\t\t<li>' + plain_message + '<\/li>\n\t<\/ul>\n';
	}

	function check_required_card_field(gateway, param) {
		if ( gateway == 'iamport_pay_again' ) {
			return 	param['iamport_pay_again-card-number'] &&
				 	param['iamport_pay_again-card-expiry'] &&
				 	param['iamport_pay_again-card-birth'] &&
				 	param['iamport_pay_again-card-pwd'];
		} else if ( gateway == 'iamport_foreign' ) {
			return 	param['iamport_foreign-card-number'] &&
				 	param['iamport_foreign-card-expiry'] &&
				 	param['iamport_foreign-card-cvc'];
		}

		return false;
	}

	function encrypt_card_info(gateway, param) {
		if ( gateway == 'iamport_pay_again' ) {
			var holder = $('#iamport-nicepay-card-holder'),
				module = holder.data('module'),
				exponent = holder.data('exponent');

			var rsa = new RSAKey();
			rsa.setPublic(module, exponent);

			// encrypt using public key
			var enc_card_number = rsa.encrypt( param['iamport_pay_again-card-number'] );
			var enc_card_expiry = rsa.encrypt( param['iamport_pay_again-card-expiry'] );
			var enc_card_birth 	= rsa.encrypt( param['iamport_pay_again-card-birth'] );
			var enc_card_pwd 	= rsa.encrypt( param['iamport_pay_again-card-pwd'] );

			param['enc_iamport_pay_again-card-number'] 	= enc_card_number;
			param['enc_iamport_pay_again-card-expiry'] 	= enc_card_expiry;
			param['enc_iamport_pay_again-card-birth'] 	= enc_card_birth;
			param['enc_iamport_pay_again-card-pwd']	 	= enc_card_pwd;

			delete param['iamport_pay_again-card-number'];
			delete param['iamport_pay_again-card-expiry'];
			delete param['iamport_pay_again-card-birth'];
			delete param['iamport_pay_again-card-pwd'];
		} else if ( gateway == 'iamport_foreign' ) {
			var holder = $('#iamport-foreign-card-holder'),
				module = holder.data('module'),
				exponent = holder.data('exponent');

			var rsa = new RSAKey();
			rsa.setPublic(module, exponent);

			// encrypt using public key
			var enc_card_number = rsa.encrypt( param['iamport_foreign-card-number'] );
			var enc_card_expiry = rsa.encrypt( param['iamport_foreign-card-expiry'] );
			var enc_card_cvc 	= rsa.encrypt( param['iamport_foreign-card-cvc'] );

			param['enc_iamport_foreign-card-number'] 	= enc_card_number;
			param['enc_iamport_foreign-card-expiry'] 	= enc_card_expiry;
			param['enc_iamport_foreign-card-cvc'] 		= enc_card_cvc;

			delete param['iamport_foreign-card-number'];
			delete param['iamport_foreign-card-expiry'];
			delete param['iamport_foreign-card-cvc'];
		}
	}


	$('form[name="checkout"]').on(iamport_checkout_types.join(' '), function() {
		//woocommerce의 checkout.js의 기본동작을 그대로..woocommerce 버전바뀔 때마다 확인 필요
		var $form = $(this),
			gateway_name = $form.find('input[name="payment_method"]:checked').val();

		var form_param = $form.serializeObject();

		if ( !check_required_card_field(gateway_name, form_param) ) {
			handle_error($form, {
				"result": "failure",
				"messages": error_html('카드정보를 입력해주세요.'),
				"refresh": false,
				"reload": false
			});

			return false;
		}

		encrypt_card_info(gateway_name, form_param);

		$form.addClass( 'processing' );
		var form_data = $form.data();

		if ( 1 !== form_data['blockUI.isBlocked'] ) {
			$form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		}

		$.ajax({
			type: 	'POST',
			url: 	wc_checkout_params.checkout_url,
			data: 	jQuery.param( form_param ),
			dataType: 'json',
			dataFilter : function(data) {
				var wc_dummy = /<!--.*?-->/g;
				
				return data.replace(wc_dummy, '');
			},
			success: function( result ) {
				try {
					if ( result.result === 'success' ) {
						if ( -1 === result.redirect.indexOf( 'https://' ) || -1 === result.redirect.indexOf( 'http://' ) ) {
							window.location = result.redirect;
						} else {
							window.location = decodeURI( result.redirect );
						}
					} else if ( result.result === 'failure' ) {
						throw result;
					} else {
						throw result;
					}
				} catch( err ) {
					handle_error($form, err);
                }
            },
            error:  function( jqXHR, textStatus, errorThrown ) {
            	alert(errorThrown);
            	window.location.reload();
            }
        });

		return false; //기본 checkout 프로세스를 중단
	});


	$('form#order_review').on('submit', function(e) {
		var $form = $(this),
			gateway_name = $( '#order_review input[name=payment_method]:checked' ).val(),
			prefix = 'iamport_';

		if ( !in_iamport_gateway(gateway_name) )	return true; //다른 결제수단이 submit될 수 있도록

		// e.preventDefault(); // woocommerce기본동작대로 submit되도록 preventDefault()하지 않는다.
		
		var form_param = $form.serializeObject();

		if ( !check_required_card_field(gateway_name, form_param) ) {
			handle_error($form, {
				"result": "failure",
				"messages": error_html('카드정보를 입력해주세요.'),
				"refresh": false,
				"reload": false
			});

			return false;
		}

		encrypt_card_info(gateway_name, form_param);

		if ( gateway_name == 'iamport_pay_again' ) {
			$form[0]['enc_iamport_pay_again-card-number'].value = form_param['enc_iamport_pay_again-card-number'];
			$form[0]['enc_iamport_pay_again-card-expiry'].value = form_param['enc_iamport_pay_again-card-expiry'];
			$form[0]['enc_iamport_pay_again-card-birth'].value = form_param['enc_iamport_pay_again-card-birth'];
			$form[0]['enc_iamport_pay_again-card-pwd'].value = form_param['enc_iamport_pay_again-card-pwd'];

			//plain-text 카드정보는 submit되지 않도록 disabled처리
			$('#iamport_pay_again-card-number').attr('disabled', true);
			$('#iamport_pay_again-card-expiry').attr('disabled', true);
			$('#iamport_pay_again-card-birth').attr('disabled', true);
			$('#iamport_pay_again-card-pwd').attr('disabled', true);
		} else if ( gateway_name == 'iamport_foreign' ) {
			$form[0]['enc_iamport_foreign-card-number'].value = form_param['enc_iamport_foreign-card-number'];
			$form[0]['enc_iamport_foreign-card-expiry'].value = form_param['enc_iamport_foreign-card-expiry'];
			$form[0]['enc_iamport_foreign-card-cvc'].value = form_param['enc_iamport_foreign-card-cvc'];

			//plain-text 카드정보는 submit되지 않도록 disabled처리
			$('#iamport_foreign-card-number').attr('disabled', true);
			$('#iamport_foreign-card-expiry').attr('disabled', true);
			$('#iamport_foreign-card-cvc').attr('disabled', true);
		}

		return true;
	})
})