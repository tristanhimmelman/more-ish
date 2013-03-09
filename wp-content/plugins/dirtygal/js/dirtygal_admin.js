var $j = jQuery.noConflict();	//annoying no conflict crap needed to not conflict with other libraries

function sendDirtygalProcessRequest(){
	$j('#dirtygal_reprocess_button').attr('disabled','disabled').addClass('disabled');
	$j('#dirtygal_load_gif_1').show();
	var data = {
		action     : 'dirtygal_reprocess'
	};

	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post(ajaxurl, data, function(response) {
		//alert('Got this from the server: ' + response);
		if (response.success == "true") {
			if (response.needs_update_count == 0) {
				$j('#dirtygal_reprocess_button').attr('disabled','').removeClass('disabled');
				$j('#dirtygal_load_gif_1').hide();
				$j('#dirtygal_repocess_status').text('Done!').show().fadeOut(2000);
			} else {
				$j('#dirtygal_reprocess_count').text(response.needs_update_count);
				sendDirtygalProcessRequest();
			}
		} else {
			alert("ERROR: "+response);
		}
	}, 'json');
}

$j(document).ready(function(){
	//just to make sure that the dirtygal html exists
	if ( document.getElementById( 'dirtygal_reprocess_button' ) != null ) {
		var process_button = document.getElementById('dirtygal_reprocess_button');
		var loader_1 = document.getElementById('dirtygal_load_gif_1');		//the loading gif at the bottom (used to tell you about reprocessing images)
		
		$j(loader_1).hide();
		$j(process_button).click(function(){
			sendDirtygalProcessRequest();
		});

	}
});



