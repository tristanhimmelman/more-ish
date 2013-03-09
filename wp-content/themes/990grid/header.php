<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php if ( is_search() || is_author() ) : ?>
	<meta name="robots" content="noindex, nofollow" />
	<?php endif ?>
	
	<meta property="og:title" content="More-ish" />
	<meta property="og:type" content="blog" />
	<meta property="og:url" content="http://www.more-ish.ca" />
	<meta property="og:image" content="http://www.more-ish.ca/wp-content/themes/990grid/images/logo.png" />
	<meta property="og:site_name" content="More-ish" />
	<meta property="fb:admins" content="13611213, 1351410081" />
    <meta property="og:description" content="Addictive, causing one to want to have more."/> 
	
	<title><?php wp_title('-', true, 'right'); ?>More-ish</title>
	<link type="text/css" rel="stylesheet" media="screen" href="<?php bloginfo('stylesheet_url'); ?>" />
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
	<script type="text/javascript" src="<?php bloginfo('stylesheet_directory'); ?>/scripts/slideshow/jquery.cycle.all.js"></script>
	<script type="text/javascript" src="<?php bloginfo('stylesheet_directory'); ?>/scripts/slideshow/slideshow.js"></script>
	<link href='http://fonts.googleapis.com/css?family=Rock+Salt&v2' rel='stylesheet' type='text/css'>
	<?php wp_head(); ?>
</head>

<body>
	<div id="wrapper">
		<div id="content">
			<div id="navigation">
				<div id="nav_left">
					<?php 
					/* Our navigation menu.  If one isn't filled out, wp_nav_menu falls back to wp_page_menu. 
					 * The menu assiged to the primary position is the one used. If none is assigned, the menu 
					 * with the lowest ID is used. */
					wp_nav_menu( array( 'theme_location' => 'primary', 
												'menu' => 'main_menu', 
												'before' => '') ); 
					?>	
				</div>
				<div id="nav_right">
					<?php echo date("F jS, Y"); ?>
				</div>
			</div>
			<div id="header">
				<h1 id="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
						more-ish
					</a>
				</h1>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
<!--					<img id="logo" src="<?php bloginfo('stylesheet_directory'); ?>/images/logo.png" height="160"/>-->
				</a>
				<span id="tag_line">Addictive; causing one to want to have more.</span>
				<!--<h2 id="site-description"><?php bloginfo( 'description' ); ?></h2>-->
			</div>    