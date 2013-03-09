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
<?php if ( post_password_required() ) : ?>
		<p class="nopassword"><?php _e( 'This post is password protected. Enter the password to view any comments.', 'twentyeleven' ); ?></p>
	</div><!-- #comments -->
	<?php return; ?>
<?php endif; ?>

<?php // You can start editing here -- including this comment! ?>

<?php if ( have_comments() ) : ?>
	

	<ol class="commentlist">
		<?php wp_list_comments(); ?>
	</ol>
<?php elseif ( ! comments_open() && ! is_page() && post_type_supports( get_post_type(), 'comments' ) ) :?>
	<p class="nocomments"><?php _e( 'Comments are closed.', 'twentyeleven' ); ?></p>
<?php endif; ?>

<?php comment_form(); ?>

</div><!-- #comments -->
