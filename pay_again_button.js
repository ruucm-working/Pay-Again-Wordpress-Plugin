jQuery( document ).on( 'click', '.pay-again-button', function() {
	var post_id = jQuery(this).data('id');
	jQuery.ajax({
		url : payagain.ajax_url,
		type : 'post',
		data : {
			action : 'do_pay_again',
			post_id : post_id
		},
		success : function( response ) {
			alert(response)
		}
	});
})