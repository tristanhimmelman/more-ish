function a($){
	
	
function insertTheImage(filename, size){	
	var win = window.dialogArguments || opener || parent || top;
	size_array = size.split('x');
	var extension = filename.substr(filename.lastIndexOf('.'));
	var name = filename.substr(0, filename.lastIndexOf('.'));
	filename = name+'-'+size_array[0]+'_'+size_array[1]+extension;
	win.send_to_editor('<img src="'+dirtygal_url+filename+'" width="'+size_array[0]+'" height="'+size_array[1]+'" />');
	return false;
}

function copyTheImage( type, fileid ) {
	var win = window.dialogArguments || opener || parent || top;
	win.dirtygal.copyFromServer( type, fileid );
	win.tb_remove();
	return false;
}
	
$(document).ready(function(){
	var loading_gif = $('#dirtygal_load_gif').hide();
	var thumb_template = $('.dgu_single_thumb');
	
	
	// Modify the "Media Library" tab on the media upload popup when copying to dirtygal
	if ( typeof(dirtygal_copy_popup) != 'undefined' && $('#media-items').size() == 1   ) {
		$('#media-items table tbody').css('display','none');
		$('#media-items table thead input.button').val('Copy Image').attr('onclick','').click(function(){
			var mediaId = $(this).attr('id').substr( $(this).attr('id').lastIndexOf('-') + 1 );
			copyTheImage('media', mediaId);
		});
	}
	
	$('#dgu_post_types a').click(function(){
		$('#dgu_post_types a.selected').removeClass('selected');
		$(this).addClass('selected');
		var toshow = this.id.substr(9);
		$('.dgu_post_container.selected').removeClass('selected');
		$('#dgu_container_'+toshow).addClass('selected');
		return false;
	});
	
	$('.dgu_post_item').toggle( function(){
		$(this).addClass('open');
		$(this).find('.dgu_post_title').after( loading_gif.show() );
		var thumbs_scroll = $(this).parent().find('.dgu_thumb_container');
		var thumbs_holder = $(this).parent().find('.dgu_thumbs');
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php		
		jQuery.getJSON( ajaxurl+"?action=dirtygal_fetch_images&postid="+$(this).parent().attr('id').substr(5), function(data){
			loading_gif.hide();
			for (var i in data.pics){
				var p = thumb_template.clone();
				if (data.pics[i].caption != '') p.find('.dgu_thumb_caption').text(data.pics[i].caption);
				else p.find('.dgu_thumb_caption').text(data.pics[i].filename);
				p.find('.dgu_filename').attr('value',data.pics[i].filename);
				p.find('.dgu_fileid').attr('value',data.pics[i].id);
				
				var ext = data.pics[i].filename.length - 4;	//index of start of file extension
				var url = dirtygal_url+data.pics[i].filename.substr(0,ext)+'-'+dirtygal_default_size+'_'+dirtygal_default_size+data.pics[i].filename.substr(ext,4);
				var img = $('<img src="'+url+'" width="100" height="100" />');
				var select = p.find('select');
				if ( typeof(dirtygal_copy_popup) != 'undefined' ){
					select.hide();
					var copylink = $('<a href="#">Copy This Image</a>');
					copylink.click(function(){
						copyTheImage('dirtygal', $(this).siblings('.dgu_fileid').attr('value') );
					})
					select.after(copylink);
				} else {				
					var crops = data.pics[i].crops.split('/');
					for (var j in crops){
						var nums = crops[j].split(' ');
						if (nums[2] == 'fit'){
							select.append($('<option value="'+nums[3]+'x'+nums[4]+'">'+nums[0]+' x '+nums[1]+' (fit to '+nums[3]+' x '+nums[4]+')</option>'));
						} else {
							select.append($('<option value="'+nums[0]+'x'+nums[1]+'">'+nums[0]+' x '+nums[1]+'</option>'));
						}
					}
				}
				
				img.toggle( function(){
					$(this).parent().parent().parent().width( $(this).parent().parent().parent().width() + 150 );
					if (thumbs_holder.width() > thumbs_scroll.width()){
						thumbs_scroll.show().animate({height:135});
					} else {
						thumbs_scroll.show().animate({height:120});
					}
					$(this).parent().parent().find('.dgu_thumb_meta').animate({width:151});
				}, function(){
					$(this).parent().parent().find('.dgu_thumb_meta').animate({width:1}, 'slow', function(){ 
						$(this).parent().parent().width( $(this).parent().parent().width() - 150 );
						if (thumbs_holder.width() > thumbs_scroll.width()){
							thumbs_scroll.show().animate({height:135});
						} else {
							thumbs_scroll.show().animate({height:120});
						}
					}); 
				});
				p.find('.dgu_thumb').append(img);
				select.change( function(){
					insertTheImage($(this).siblings('.dgu_filename').attr('value'), $(this).attr('value'));
				});
				p.css('display','block');
				thumbs_holder.append(p);
				//var p = data.pics[i];
			}
			thumbs_holder.width(5+115*data.pics.length);
			thumbs_scroll.show().height(1);
			if (thumbs_holder.width() > thumbs_scroll.width()){
				thumbs_scroll.show().animate({height:135});
			} else {
				thumbs_scroll.show().animate({height:120});
			}
			
		});
	}, function() {
			$(this).removeClass('open');
			var thumbs_scroll = $(this).parent().find('.dgu_thumb_container');
			var thumbs_holder = $(this).parent().find('.dgu_thumbs');
			thumbs_scroll.animate({height:0}, 'slow', function(){ thumbs_scroll.hide();});

	});
});
}
a(jQuery);