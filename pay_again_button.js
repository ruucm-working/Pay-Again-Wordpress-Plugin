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
