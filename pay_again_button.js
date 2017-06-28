jQuery( document ).on( 'click', '.pay-again-delete-payment-button', function() {
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
})