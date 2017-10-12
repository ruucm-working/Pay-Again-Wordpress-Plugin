jQuery( document ).on( 'click', '.pay-again-delete-payment-button', function() {
	if (confirm('정말로 등록된 카드를 삭제하시겠어요?')) {
		var post_id = jQuery(this).data('id');
		jQuery.ajax({
			url : payagain.ajax_url,
			type : 'post',
			data : {
				action : 'delete_pay_again_method',
				post_id : post_id
			},
			success : function( response ) {
				alert(response);
				location.reload();
			}
		});
	} else {
		// Do nothing!
	}
})

jQuery( document ).on( 'click', '.pay-again-delete-inicis-payment-button', function() {
	if (confirm('정말로 등록된 카드를 삭제하시겠어요?')) {
		var post_id = jQuery(this).data('id');
		jQuery.ajax({
			url : payagain.ajax_url,
			type : 'post',
			data : {
				action : 'delete_pay_again_inicis_method',
				post_id : post_id
			},
			success : function( response ) {
				alert(response);
				location.reload();
			}
		});
	} else {
		// Do nothing!
	}
})

jQuery(function($) {

	var iamport_gateways = [
		'iamport_card',
		'iamport_trans',
		'iamport_vbank',
		'iamport_phone',
		'iamport_kakao',
		'iamport_kpay',
		'iamport_inicis_pay_again'
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

	$('form[name="checkout"]').on(iamport_checkout_types.join(' '), function() {

		if ($('#inicis-register-card').length == 1) {

			//woocommerce의 checkout.js의 기본동작을 그대로..woocommerce 버전바뀔 때마다 확인 필요
			var $form = $(this),
				gateway_name = $form.find('input[name="payment_method"]:checked').val();

			var pay_method = 'card',
				prefix = 'iamport_';
			if ( gateway_name.indexOf(prefix) == 0 )	pay_method = gateway_name.substring(prefix.length);

			//카카오페이, 이니시스 비인증결제 처리
			if ( pay_method == 'kakao' || pay_method == 'inicis_pay_again' )				pay_method = 'card';

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
				data: 	$form.serialize(),
				dataType: 'json',
				dataFilter : function(data) {
					var wc_dummy = /<!--.*?-->/g;
					return data.replace(wc_dummy, '');
				},
				success: function( result ) {
					try {
						if ( result.result === 'success' ) {
							//iamport process
							var req_param = {
								pay_method : pay_method,
							    // escrow : result.iamport.escrow,
							    merchant_uid : result.iamport.merchant_uid,
							    name : result.iamport.name,
							    amount : parseInt(result.iamport.amount),
							    buyer_email : result.iamport.buyer_email,
							    buyer_name : result.iamport.buyer_name,
							    buyer_tel : result.iamport.buyer_tel,
							    // buyer_addr : result.iamport.buyer_addr,
							    // buyer_postcode : result.iamport.buyer_postcode,
							    // vbank_due : result.iamport.vbank_due,
							    // m_redirect_url : result.iamport.m_redirect_url,
							    // digital : result.iamport.digital || false,
							    // custom_data : {woocommerce:result.order_id},
							    customer_uid : result.customer_uid,
							};

							if ( result.iamport.pg )	req_param.pg = result.iamport.pg;

							IMP.init(result.iamport.user_code);
							IMP.request_pay(req_param, function(rsp) {
								if ( rsp.success ) {
									window.location.href = result.iamport.m_redirect_url + "&imp_uid=" + rsp.imp_uid; //IamportPlugin.check_payment_response() 에서 필수
									alert('카드 등록에 성공 하였습니다.');
									// window.history.back();
									location = location;
								} else {
									alert(rsp.error_msg);
									// window.location.reload();
								}
							});
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
		} else {
			return true;
		}
	});


	$('form#order_review').on('submit', function(e) {
		var $form = $(this),
			gateway_name = $( '#order_review input[name=payment_method]:checked' ).val(),
			prefix = 'iamport_';

		if ( !in_iamport_gateway(gateway_name) )	return true; //다른 결제수단이 submit될 수 있도록

		e.preventDefault(); // iamport와 관련있는 것일 때만
		var pay_method = gateway_name.substring(prefix.length),
			order_key = $.url('?key');
		$.ajax({
			type: 	'GET',
			url: 	wc_checkout_params.ajax_url,
			data: 	{
				action: 'iamport_payment_info',
				pay_method: pay_method,
				order_key: order_key
			},
			dataType: 'json',
			success: function( result ) {
				try {
					if ( result.result === 'success' ) {
						//iamport process
						var req_param = {
							pay_method : pay_method,
						    escrow : result.iamport.escrow,
						    merchant_uid : result.iamport.merchant_uid,
						    name : result.iamport.name,
						    amount : parseInt(result.iamport.amount),
						    buyer_email : result.iamport.buyer_email,
						    buyer_name : result.iamport.buyer_name,
						    buyer_tel : result.iamport.buyer_tel,
						    buyer_addr : result.iamport.buyer_addr,
						    buyer_postcode : result.iamport.buyer_postcode,
						    vbank_due : result.iamport.vbank_due,
						    m_redirect_url : result.iamport.m_redirect_url,
						    digital : result.iamport.digital || false,
						    custom_data : {woocommerce:result.order_id}
						};

						if ( result.iamport.pg )	req_param.pg = result.iamport.pg;

						IMP.init(result.iamport.user_code);
						IMP.request_pay(req_param, function(rsp) {
							if ( rsp.success ) {
								window.location.href = result.iamport.m_redirect_url + "&imp_uid=" + rsp.imp_uid;
							} else {
								alert(rsp.error_msg);
								window.location.reload();
							}
						});
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

		return false;
	})
})