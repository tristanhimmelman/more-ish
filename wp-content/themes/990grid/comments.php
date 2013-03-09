<?php if ( have_comments() ) : ?>
	<h2 id="comments_title">
		<?php
			$numComments = get_comments_number();
			$commentStr = " comments";
			if ($numComments == 1){
				$commentStr = " comment";	
			}
			echo $numComments.$commentStr;	 
		?>
	</h2>
<?php endif;?>

<div id="comments" class="post">
	<div id="fb-root"></div>
	<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script>
	<fb:comments href="<?php echo get_permalink(); ?>" num_posts="2" width="560"></fb:comments>
</div><!-- #comments -->
