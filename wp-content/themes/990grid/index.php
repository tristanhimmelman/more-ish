<?php get_header(); ?>

<div id="main-container">
    <?php if (is_page()) : ?>
    	<?php 
    	$page = get_page($page_id);
    	?>
    	
		<div id="columns">
			<div id="col_1" class="column" style="margin-top:50px;">
			<div class="post page<?php echo $topcategory; ?>">
			    <div class="post_title"><?php the_title(); ?></div>	
				<div class="post_image"></div>
				<div class="post_content">
					<?php print_r ($page->{'post_content'}); ?>
				</div>
				<div class="post_footer">
					
				</div>
			</div>
			</div>
			<div id="col_2" class="column">
				<?php get_sidebar();?>
				<div align="center" style="padding-top:40px;"><a href="http://foodgawker.com/post/archive/moreish/"  title="my foodgawker gallery"><img src="http://static.foodgawker.com/images/badges/bowls2-150x150.png" alt="my foodgawker gallery"/></a></div>
			</div>			
		</div>
	<div style="clear:both;"></div>
		
	<?php elseif (have_posts()) : ?>
    	<?php $count = 0;?>
	    <?php while (have_posts()) : the_post() ?>
	    	<?php $count++; ?>
	    	<?php if ($count == 1) : ?>
			<div id="slide_show">
				<div id="big_picture">
					<div id="slideshow_images">
						<?php $mainImage = Dirtygal :: getMainImage($post->ID, 860, 538); ?>
						<?php 
						$images = Dirtygal :: getImages($post->ID, 860, 538);
						for ($i = 0; $i < count($images); $i++){ 
							$imageName = $images[$i]['filename'];
							$imageName = substr($imageName, 0, -4)."-860_538.jpg";
							?>
							
						<img src="<?php echo $imageName; ?>" alt="<?php echo addSlashes($images[$i]['caption']);?>" width="860" height="538" <?php echo ($i==0)?'class="first"':''; ?> />	
						<?php } ?>
					</div>
				</div>
				<div id="left_btn" class="control">
					<a id="prev_btn" href="#">
						<img src="<?php bloginfo('stylesheet_directory'); ?>/images/arrow_left.png" width="30"; height="44"/>
					</a>
				</div>
				<div id="right_btn" class="control">
					<a id="next_btn" href="#">
						<img src="<?php bloginfo('stylesheet_directory'); ?>/images/arrow_right.png" width="30"; height="44"/>
					</a>
				</div>
			</div>	
			<div id="columns">
				<div id="col_1" class="column">
			<?php endif; ?>
			
			<?php if (is_single()) : /////////////////////////// SINGLE POST /////////////////////////?>
			<div class="post <?php echo $topcategory; ?>">
				<div class="post_title"><?php the_title(); ?></div>
				<div class="post_content"><?php the_content(); ?></div>
				<div class="post_footer">
					<?php echo get_the_time('M j, Y'); ?>
				</div>
			</div>
			<?php comments_template(); ?> 
			<?php else :?>
		    <div class="post <?php echo $topcategory; ?>">
			    <div class="post_title"><a href="<?php echo get_permalink(); ?>"><?php the_title(); ?></a></div>	
				<?php if ($count != 1) : ?>
				<div class="post_image">
					<?php $mainImage = Dirtygal :: getMainImage($post->ID, 560, 310); ?>
					<a href="<?php echo get_permalink(); ?>">
						<img src="<?php echo $mainImage['filename']; ?>" alt="<?php echo addSlashes($mainImage['caption']);?>" width="560" height="310" />
					</a>
				</div>
				<?php endif; ?>
				<div class="post_content">
					<?php 
						if ($count == 1){
							the_content();
						} else {
							the_excerpt();
							echo '<a href="'.get_permalink().'" >Read more...</a>';		
						}
					 ?>
				</div>
				<div class="post_footer">
					<?php echo get_the_time('M j, Y'); ?>
				</div>
			</div>
		    <?php endif; ?>
	    <?php endwhile; ?>
	    
	    <?php
			ob_start();
			next_posts_link("Previous");
			$nextlink = ob_get_contents();
			ob_clean();
			previous_posts_link("Next");
			$prevlink = ob_get_contents();
			ob_end_clean();
		?>
		
		<?php if ($nextlink || $prevlink) : ?>
		<div id="morepostsbox" class="postcontainer">
			<div id="more_posts_older"><?php echo $prevlink; ?></div>
			<div id="more_posts_middle"></div>
			<div id="more_posts_newer"><?php echo $nextlink; ?></div>
			<div style="clear:both;"></div>
		</div>
		<?php endif; ?>
	    
	    </div>
		<div id="col_2" class="column">
			<?php get_sidebar();?>
			<div align="center" style="padding-top:40px;"><a href="http://foodgawker.com/post/archive/moreish/"  title="my foodgawker gallery"><img src="http://static.foodgawker.com/images/badges/bowls2-150x150.png" alt="my foodgawker gallery"/></a></div>			
		</div>
	</div>
	<div style="clear:both;"></div>
	    
	    
	<?php else : // if NO posts ?>
		<div class="postcontainer">
	        <div class="posttitle">No&nbsp;&nbsp;Posts&nbsp;&nbsp;Found</div>
			<img src="example2_550_190.jpg" />
	        <span class="postdate">
				<?php if (is_search()) : ?>
					No Matches
				<?php elseif (is_category() || is_tag()) : ?>
					Empty
				<?php else : ?>
					ERROR: 404
				<?php endif; ?>
	        </span>
	        <div class="posttext">
			<div class="postexcerpt">
				<?php if (is_search()) : ?>
					Sorry, there are no posts that match your search.
				<?php elseif (is_category()) : ?>
					Sorry, there are no posts in this category yet.
				<?php elseif (is_tag()) : ?>
					Sorry, there are no posts with that tag yet.
				<?php else : ?>
					Sorry, that post or page does not exist.
				<?php endif; ?>
				<br/>
			</div></div>
		</div>
	<?php endif; // end if have posts ?>

</div> <!-- End main-container -->
<?php get_footer(); ?>