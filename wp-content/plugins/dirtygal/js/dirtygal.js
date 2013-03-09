var $j = jQuery.noConflict();	//annoying no conflict crap needed to not conflict with other libraries
var dirtygal;

function Dirtygal(){
	var thisObj = this;
	var upload_frame = document.getElementById( 'dirtygal_iframe' );
	var file_input = document.getElementById( 'dirtygal_file_input' );
	var im_container = document.getElementById( 'dirtygal_images_container' );
	var fullsize_container = document.getElementById('dirtygal_fullsize_holder');
	var size_select = document.getElementById('dirtygal_crop_size_select');
	var crop_button = document.getElementById('dirtygal_crop_button');
	var server_copy_link = document.getElementById('dirtygal_server_copy_link');
	var fit_button = document.getElementById('dirtygal_fit_button');
	var fullsize_image;
	var fullsize_width;
	var fullsize_height;
	var selected_image;
	var jcrop;
	var loader_1 = document.getElementById('dirtygal_load_gif_1');		//the loading gif on the left
	var loader_2 = document.getElementById('dirtygal_load_gif_2');		//the loading gif on the right (used to tell you about cropping
	var loader_3 = document.getElementById('dirtygal_load_gif_3');		//the loading gif for fit cropping option
	var empty_html = '<p style="font-style:italic;">There are currently no images associated with this page</p>';
	var images = Array();
	var just_deleted = false;
	var drag_placeholder;
	var drag_item;
	var drag_start;
	var drag_offset;
	var drag_happening = false;
	var drag_index;
	
	var file_being_uploaded; 
	var new_file_obj;
	
	this.uploadImage = function(){
		file_being_uploaded = $j(file_input).attr('value');
		
		if ( file_being_uploaded.toLowerCase().indexOf('.jpg')<0 
			&& file_being_uploaded.indexOf('.jpeg')<0
			&& file_being_uploaded.indexOf('.png')<0
			&& file_being_uploaded.indexOf('.zip')<0 ) {
			alert('Sorry, you can only select jpeg images, png images, or zip archives (full of images). Please select a valid file.');
			thisObj.resetFileControl();
		} else {
			var beforeFile = $j(file_input).prev();
			//create a new form, move the file input to it, submit it to the ajax handler, then move file input back
			var tempForm = document.createElement("FORM");
 			$j(document.body).append(tempForm);
			tempForm.method = "POST";
 			tempForm.action = ajaxurl+'?rand='+Math.floor(Math.random()*11*6).toString()
			tempForm.target = upload_frame.name;
			tempForm.enctype = 'multipart/form-data';
			$j(tempForm).append( $j(document.createElement('input')).attr('name','action').attr('value','dirtygal_upload') );
			$j(tempForm).append( file_input );
			$j(tempForm).append('<input type="hidden" name="post_ID" value="'+$j('#post_ID').val()+'" />');
			tempForm.submit();
			beforeFile.after(file_input);
			/*
			//change the form action and target, submit, and change it back
			var main_form = $j(file_input).parents('form');
			var original_action = main_form.attr('action');
			var original_enctype = main_form.attr('enctype');
			var original_wpaction = $j('#hiddenaction').val();
			
			main_form.get(0).action = 'test.php';
			//ajaxurl is defined by wordpress
			main_form.attr('action',ajaxurl+'?rand='+Math.floor(Math.random()*11*6).toString()).attr('target',upload_frame.name);
			main_form.attr('enctype','multipart/form-data');
			$j('#hiddenaction').val('dirtygal_upload');
			main_form.submit();
			
			$j('#hiddenaction').val(original_wpaction);
			main_form.attr('action',original_action).attr('target','_self');
			main_form.attr('enctype',original_enctype);
			*/
			
			//show the loading gif and disable the file control until we have a response
			$j(loader_1).show();
			$j(file_input).attr('disabled','disabled');
			
			//TODO: Add a cancel button?
		}
	}
	this.resetFileControl = function() {
		$j(file_input).attr('value','');
		$j(file_input).removeAttr('disabled','');
	}
	
	this.uploadComplete = function(result){
		if (images.length == 0) im_container.innerHTML = '';	//clears out the "no images" notice
		var im;
		for (var i=0; i<result.images.length; i++){
			im = new Dirtygal_Image(result.images[i]);
			images.push(im);
			$j(im_container).prepend(im.getDbox());
			setupDbox(im);
		}
		thisObj.selectImage(im);
		thisObj.fixImageIndeces();
	}
	this.fixImageIndeces = function(){
		for ( var i=0; i < images.length; i++ ){ 
			images[i].changeIndex( $j('.dirtygal_image_container').index(images[i].getDbox()) );
		}
	}
	this.removeImage = function(im){
		var i;
		for (i = 0; i < images.length; i++ ){	
			if (images[i].getId() == im.getId()) break;
		}
		im_container.removeChild(images[i].getDbox());
		if (im == selected_image){
			fullsize_container.innerHTML='';
		}
		images.splice(i, 1);
		//stop event propogation to the load fullsize stuff
		just_deleted = true;
		thisObj.fixImageIndeces();
		if ( images.length == 0 ) im_container.innerHTML = empty_html;
	}
	this.selectImage = function(im){
		//TODO: maybe do some DOM removal to save memory?
		
		//if the click came from deleting an image, we want to ignore it. Could do this by stopping event propogation, but this was easy and quick.
		if ( just_deleted == true ) {
			just_deleted = false;
			return;
		}
		
		if (selected_image) {
			if (selected_image == im) return 0;	//do nothing if already selected
			//de-highlight selected image
			$j(selected_image.getDbox()).removeClass('selected');
		}
		$j(crop_button).attr('disabled','disabled').addClass('disabled');
		$j(fit_button).attr('disabled','disabled').addClass('disabled');
		fullsize_container.innerHTML='';	//clear the old image
		fullsize_image = new Image();
		//onload MUST be defined before changing the src to ensure the event fires
		fullsize_image.onload = function(){
			//Jcrop has to be created after the image loads
			var jcrop_max_width = $j(fullsize_container).width();		//calculate the available area BEFORE showing the image
			var crop_dim = size_select.value;
			var crop_width = crop_dim.substring(0, crop_dim.indexOf('x'));
			var crop_height = crop_dim.substring(crop_dim.indexOf('x')+1);
			$j(fullsize_image).show();
			//would use naturalWidth and naturalHeight, but it is unsupported in old versions of IE
			fullsize_width = fullsize_image.width;
			fullsize_height = fullsize_image.height;
			jcrop = $j.Jcrop(fullsize_image, {boxWidth: jcrop_max_width, aspectRatio:(crop_width/crop_height), onSelect: function(){ $j(crop_button).removeAttr('disabled').removeClass('disabled');}});		
				//boxWidth scales down the image and scales up the crop coords accordingly
//			jcrop.setSelect();
//			jcrop.setOptions({ aspectRatio: (crop_width/crop_height) });
			thisObj.changeCropSize();
		}
		$j(fullsize_image).hide();		//hide so you dont see the fullsize image loading
		fullsize_image.src = im.getFileName();
		fullsize_container.appendChild(fullsize_image);
		selected_image = im;
		$j(selected_image.getDbox()).addClass('selected');
	}
	this.selectCover = function(im){
		for (i = 0; i < images.length; i++ ){	
			if (images[i].getId() == im.getId()) images[i].setCover(true);
			else images[i].setCover(false);
		}
	}
	this.changeCropSize = function() {
		//checks the size dropdown and adjusts the current cropping area
		//currently maximizes available area
		//TODO: make it move to saved cropping area
		var crop_dim = size_select.value;
		var crop_width = crop_dim.substring(0, crop_dim.indexOf('x'));
		var crop_height = crop_dim.substring(crop_dim.indexOf('x')+1);
		jcrop.setOptions({ aspectRatio: (crop_width/crop_height) });
		var pre_crop = selected_image.getCropCoords(crop_width, crop_height);
		if (typeof(pre_crop) == 'undefined'){
			if (crop_height * fullsize_width/crop_width <= fullsize_height){
				jcrop.animateTo([0, 0, fullsize_width, crop_height * fullsize_width/crop_width]);
			} else {
				jcrop.animateTo([0, 0, crop_width * fullsize_height/crop_height, fullsize_height]);
			}
		} else {
			if (pre_crop == 'fit'){
				$j(fit_button).removeAttr('disabled').removeClass('disabled').addClass('activated');
				$j(crop_button).removeAttr('disabled','disabled').addClass('disabled');
				jcrop.release();
			} else {
				$j(fit_button).removeAttr('disabled').removeClass('disabled').removeClass('activated');
				$j(crop_button).removeAttr('disabled').removeClass('disabled');
				jcrop.animateTo(pre_crop);
			}
		}
	}
	function sendCropRequest(){
		$j(crop_button).attr('disabled','disabled').addClass('disabled');
		$j(fit_button).attr('disabled','disabled').addClass('disabled').removeClass('activated');
		$j(loader_2).show();
		var crop_dim = size_select.value;
		var crop_width = crop_dim.substring(0, crop_dim.indexOf('x'));
		var crop_height = crop_dim.substring(crop_dim.indexOf('x')+1);
		var bounds = jcrop.tellSelect();
		var data = {
			action     : 'dirtygal_savecrop',
			imageid    : selected_image.getId(),
			cropwidth  : crop_width,
			cropheight : crop_height,
			x1         : bounds.x,
			y1         : bounds.y,
			x2         : bounds.x2,
			y2         : bounds.y2
		};

		selected_image.setCropCoords(crop_width, crop_height, bounds.x, bounds.y, bounds.x2, bounds.y2);
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			//alert('Got this from the server: ' + response);
			$j(crop_button).removeAttr('disabled').removeClass('disabled');
			$j(fit_button).removeAttr('disabled').removeClass('disabled');
			$j(loader_2).hide();
			$j('#dirtygal_crop_status').text('Saved').show().fadeOut(2000);
			if ($j('#'+size_select.id+' :first-child').val() == size_select.value){
				selected_image.refreshThumb();
			}
		});
	}
	function sendFitRequest(){
		$j(crop_button).attr('disabled','disabled').addClass('disabled');
		$j(fit_button).attr('disabled','disabled').addClass('disabled');
		$j(loader_3).show();
		jcrop.release();
		var crop_dim = size_select.value;
		var crop_width = crop_dim.substring(0, crop_dim.indexOf('x'));
		var crop_height = crop_dim.substring(crop_dim.indexOf('x')+1);
		var data = {
			action     : 'dirtygal_savecrop',
			imageid    : selected_image.getId(),
			cropwidth  : crop_width,
			cropheight : crop_height,
			usefit     : 'true'
		};

		selected_image.setCropCoords(crop_width, crop_height, 'fit');
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			//alert('Got this from the server: ' + response);
			$j(fit_button).removeAttr('disabled').removeClass('disabled').addClass('activated');
			$j(loader_3).hide();
			$j('#dirtygal_fit_status').text('Saved').show().fadeOut(2000);
		});
	}
	function showCopyFromServer(){
		formfield = $j('#dirtygal_from_server').attr('name');
		tb_show('', 'media-upload.php?type=dirtygal&amp;TB_iframe=true');
		return false;
	}
	
	this.copyFromServer = function( type, id ){
		$j(loader_1).show();
		var data = {
			action		: 'dirtygal_copyimage',
			type		: type,
			file_id    	: id,
			post_ID		: $j('#post_ID').val()
		};
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			//alert('Got this from the server: ' + response);
			thisObj.uploadComplete(response);
			$j(loader_1).hide();
		}, 'json');		
		return null;
	}
	
	function setupDbox(im){
		im.getDbox().onclick = function(d){return function(){thisObj.selectImage(d);}}(im);
		im.getDeleteLink().onclick = function(d){return function(){thisObj.removeImage(d);}}(im);
		im.getStar().onclick = function(d){return function(){thisObj.selectCover(d);}}(im);
		$j(im.getThumb()).mousedown( dragStart );
	}
	function dragStart(e){
		drag_happening = false;
		drag_item = $j(this).parent().parent();
		$j(document).disableTextSelect();
		drag_start = [e.pageX, e.pageY];
		$j(document).mousemove( dragMove );
		$j(document).mouseup( dragUp );
		if (!e) var e = window.event;
		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		return false;
	}
	function dragMove(e){
		if (! drag_happening ) {
			if (Math.abs(drag_start[0] - e.pageX) > 15 || Math.abs(drag_start[1] - e.pageY) > 15 ){
				drag_happening = true;
				drag_offset = [e.pageX - drag_item.offset().left, e.pageY - drag_item.offset().top];
				drag_item.after(drag_placeholder);
				$j(document.body).append(drag_item);
				drag_item.css({	'position': 'absolute',
										'left': (e.pageX-drag_offset[0])+'px',
										'top' : (e.pageY-drag_offset[1])+'px'});
				drag_item.fadeTo('slow',.5);
			}
		} else {
			//do the drag stuff	
			drag_item.css({	'left': (e.pageX-drag_offset[0])+'px',
							'top' : (e.pageY-drag_offset[1])+'px'});
			for (var i=0; i < images.length; i++){
				if (images[i].getDbox().id != drag_item.attr('id')){
					var boxtop = $j(images[i].getDbox()).offset().top;
					var boxheight = $j(images[i].getDbox()).height();
					if ( e.pageY > boxtop && e.pageY < boxtop + boxheight ){
						if (e.pageY < boxtop + boxheight/2){
							if ($j(images[i].getDbox()).prev().attr('id') != drag_placeholder.id) {
								$j(images[i].getDbox()).before(drag_placeholder);
							}
						} else {
							if ($j(images[i].getDbox()).next().attr('id') != drag_placeholder.id) {
								$j(images[i].getDbox()).after(drag_placeholder);
							}							
						}
					}
				}
			}
		}
	}
	function dragUp(e){
		if (drag_happening){
			drag_happening = false;
			$j(drag_placeholder).after(drag_item);
			im_container.removeChild(drag_placeholder);
			$j(drag_item).css({	'position': '', 'left':'','top':'' });
			$j(drag_item).fadeTo('slow',1);
			thisObj.fixImageIndeces();
		}
		$j(document).enableTextSelect();
		$j(document).unbind('mouseup', dragUp );
		$j(document).unbind('mousemove', dragMove );
	}
	
	
	function initialize(){
		thisObj.resetFileControl();
		if (dirtygal_initial_data.length > 0){
			im_container.innerHTML = '';	//get rid of "no images" text
			for (var i in dirtygal_initial_data){
				var im = new Dirtygal_Image(dirtygal_initial_data[i], i);
				images.push(im);
				$j(im_container).append(im.getDbox());
				setupDbox(im);
			}
//			thisObj.selectImage(images[0]);
		}
	}
	
	$j(upload_frame).load(function(){
		//find the document
		var d;
		if (upload_frame.contentDocument) d = upload_frame.contentDocument;
		else if (upload_frame.contentWindow) d = upload_frame.contentWindow.document;
		else d = window.frames[upload_frame.id].document;
		//if the document is not blank
		if (d.location.href != "about:blank") {
			eval('result='+d.body.innerHTML);
			if (result.success == 'true') {
				thisObj.uploadComplete(result);
			} else {
				thisObj.resetFileControl();
			}
		}
		$j(loader_1).hide();
		thisObj.resetFileControl();
	});
	
	//initialization
	$j(im_container).html(empty_html);
	$j(loader_1).hide();
	$j(loader_2).hide();
	$j(loader_3).hide();
	$j('#dirtygal_star_on').hide();
	$j('#dirtygal_star_off').hide();
	$j(crop_button).attr('disabled','disabled').addClass('disabled');
	$j(fit_button).attr('disabled','disabled').addClass('disabled');
	size_select.onchange = thisObj.changeCropSize;	
	file_input.onchange = thisObj.uploadImage;
	crop_button.onclick = sendCropRequest;
	fit_button.onclick = sendFitRequest;
	server_copy_link.onclick = showCopyFromServer;
	drag_placeholder = document.createElement('DIV');
	drag_placeholder.id = 'dirtygal_drag_placeholder';
	initialize();
}
function Dirtygal_Image(result, sorder){
	var thisObj = this;
	var id = result.id;					//id in the database
	var filename = result.filename;		//the base filename
	var caption = result.caption;
	var index = sorder;
	var dbox;			//the dom box which represents the image
	var crop_settings = result.crops.split('/');	//the crop coordinates for each of the sizes
	var order_input;
	var delete_link;
	var thumb;
	var cover = result.cover;
	var cover_input;
	var cover_star;
	
	// getters
	this.getDbox     = function(){ return dbox; }
	this.getDeleteLink = function(){ return delete_link; }
	this.getId       = function(){ return id; }
	this.getIndex    = function(){ return index; }
	this.getThumb    = function(){ return thumb; }
	this.getStar     = function(){ return cover_star; }
	this.getFileName = function(width, height){
		if (typeof(width) == 'undefined' || typeof(height) == 'undefined' || width == 0 || height == 0) return dirtygal_url+filename;
		else {
			var ext = filename.length - 4;	//index of start of file extension
			return dirtygal_url+filename.substr(0,ext)+'-'+width+'_'+height+filename.substr(ext,4);
		}
	}
	
	this.changeIndex = function( newIndex ){
		index = newIndex;
		order_input.value = newIndex;
	}
	this.getCropCoords = function(width, height) {
		for (var i=0; i<crop_settings.length; i++){
			var crop_nums = crop_settings[i].split(' ');
			if (crop_nums[0] == width && crop_nums[1] == height){
				if (crop_nums[2]=='fit') return 'fit';
				else return [crop_nums[2], crop_nums[3], crop_nums[4], crop_nums[5]];
			}
		}
		return;		//remains "undefined"  (had it set to null before...)
	}
	this.setCropCoords = function( width, height, x1, y1, x2, y2 ){
		for (var i=0; i < crop_settings.length; i++){
			var crop_nums = crop_settings[i].split(' ');
			if (crop_nums[0] == width && crop_nums[1] == height){
				if (x1 == 'fit') {
					crop_settings[i] = width+' '+height+' fit';
				} else {
					crop_settings[i] = width+' '+height+' '+x1+' '+y1+' '+x2+' '+y2;
				}
				return;
			}
		}
		crop_settings.push(width+' '+height+' '+x1+' '+y1+' '+x2+' '+y2);
	}
	this.refreshThumb = function(){  
		thumb.src = thisObj.getFileName(dirtygal_default_size,dirtygal_default_size)+'?rand='+Math.floor(Math.random()*1000000);
	}
	this.makeThumbDraggable = function(){
		thumb.mousedown(function(){
			
		});
	}
	this.setCover = function( iscover ){
		cover_star.src = $j( iscover ? '#dirtygal_star_on' : '#dirtygal_star_off').attr('src');
		cover_input.value = iscover ? 1 : 0;
	}
	
	//initialization
	dbox = document.createElement('DIV');
	dbox.className = 'dirtygal_image_container';
	dbox.id = 'dirtygal_image_'+id;
	//I use these anonymous functions to avoid saving any extra references to these DOM elements
	thumb = document.createElement('IMG');
	thumb.src = thisObj.getFileName(dirtygal_default_size, dirtygal_default_size); thumb.width=100; thumb.height=100; 
	dbox.appendChild(function(){var t = document.createElement('DIV'); t.className='dirtygal_thumb'; 
								t.appendChild(thumb); return t;}());
	cover_star = document.createElement('IMG');
	cover_star.src = $j( cover==1 ? '#dirtygal_star_on' : '#dirtygal_star_off').attr('src');
	cover_star.className = "dirtygal_coverstar";
	dbox.appendChild(cover_star);
	cover_input = document.createElement('INPUT'); cover_input.type = 'hidden'; cover_input.name = "dirtygal_cover_"+id; cover_input.value = cover;
	dbox.appendChild( cover_input );
	delete_link = document.createElement('A'); delete_link.innerHTML = 'Delete this image';
	dbox.appendChild(function(){var t = document.createElement('DIV'); t.className='dirtygal_image_options'; 
									var c = document.createElement('TEXTAREA'); c.id=c.name="dirtygal_caption_"+id; c.value=caption; t.appendChild(c); 
									t.appendChild(delete_link);
									return t;}());
	dbox.appendChild(function(){var t = document.createElement('DIV'); t.className='dirtygal_image_clear'; return t;}());
	order_input = document.createElement('INPUT'); order_input.type = 'hidden'; order_input.name = "dirtygal_order_"+id; order_input.value = index;
	dbox.appendChild( order_input );
	
	
	function toString(){
		return 'Dirtygal image id['+id+']';	
	}

}

$j(document).ready(function(){
	//just to make sure that the dirtygal html exists
	if ( document.getElementById( 'dirtygal_file_input' ) != null ) dirtygal = new Dirtygal();
});



