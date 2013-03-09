$(document).ready(function(){
	
	if ($('#slideshow_images').children('img').length > 1 ){
		$('#slideshow_images').cycle({ 
		    fx:     'fade', 
		    prev:   '#prev_btn', 
		    next:   '#next_btn', 
		    timeout: 0 
		});
		
		animationSpeed = 300;
		animationDistance = 7;
		$("#left_btn").hover(
			function () {
				$('#left_btn').animate({
					left: '-='+animationDistance,
				}, animationSpeed, function() {});
			},
			function () {
				$('#left_btn').animate({
					left: '+='+animationDistance,
				}, animationSpeed, function() {});
			}
		);
		$("#right_btn").hover(
				function () {
					$('#right_btn').animate({
						left: '+='+animationDistance,
					}, animationSpeed, function() {});
				},
				function () {
					$('#right_btn').animate({
						left: '-='+animationDistance,
					}, animationSpeed, function() {});
				}
			);
	} else {
		$('#slide_show .control').css('display', 'none');
	}
});
