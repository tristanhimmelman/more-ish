<?php
/*
Plugin Name: Dirtygal
Plugin URI: http://www.theoephraim.com/
Description: Allows users to upload and crop images while associating them with posts
Version: 0.1
Author: Theo Ephraim
Author URI: http://theoephraim.com
Minimum WordPress Version Required: 2.7.0
Tested up to: 2.7.1
*/

/* ==================================================================== */
/* = Hooks, Filters, Globals etc.                                     = */
/* ==================================================================== */

/* IDEAS / Over-arching to-do
	allow crop sizes with only one dimension specified
		save files in the form "filename-x_300.jpg" to signify height 300 but open-ended width

	on window resize, fix crop area
	on scroll, adjust crop area and follow the top of the screen?
	add a trashcan or X icon to the delete link?
	add a loading indicator while loading the fullsize image (some are big and take a while)
*/

global $dirtygal_settings, $dirtygal_sizes, $dirtygal_table, $dirtygal_options, $dirtygal_folder, $dirtygal_url;
$dirtygal_table = $wpdb->prefix . "dirtygal_images";
$dirtygal_options = get_option("dirtygal_settings");
$dirtygal_folder = $dirtygal_options['path'];
$dirtygal_url = $dirtygal_options['url'];

Dirtygal :: initializeSizes();		//get the sizes option and parse it

register_activation_hook(__FILE__, array("Dirtygal", "install"));
register_deactivation_hook(__FILE__, array("Dirtygal", "uninstall"));

add_action("admin_init", array("Dirtygal", "hookAdminInit"));
add_action( 'admin_print_scripts-settings_page_dirtygal/dirtygal', array("Dirtygal", "hookAdminSettingsInit"));

add_action("admin_print_scripts", array("Dirtygal", "hookAdminPrintScripts"));
add_action("wp_ajax_dirtygal_upload", array("Dirtygal", "hookAjaxUploadHandler"));
add_action("wp_ajax_dirtygal_copyimage", array("Dirtygal", "hookAjaxCopyHandler"));
add_action("wp_ajax_dirtygal_savecrop", array("Dirtygal", "hookAjaxSaveCrop"));
add_action("wp_ajax_dirtygal_reprocess", array("Dirtygal", "hookAjaxReprocessImages"));
add_action("save_post", array("Dirtygal", "hookSavePost"));
add_action("admin_menu", array("Dirtygal", "hookAdminMenu"));
add_action("admin_footer", array("Dirtygal", "hookAdminFooter"));
add_filter('media_upload_tabs', array("Dirtygal", "hookInitUploadTabs"), 11);	//priority must be higher than 10 because the "update_gallery_tab" filter was adding the tab back again otherwise
add_filter('media_upload_default_tab', array("Dirtygal", "hookUploadTabDefault"));
add_action('media_upload_dirtygal', array("Dirtygal", "hookMediaUploadTab") );
add_action("wp_ajax_dirtygal_fetch_images", array("Dirtygal", "hookMediaTabFetchImages"));


class Dirtygal {

	function install() {
		global $dirtygal_options, $dirtygal_version, $dirtygal_table;
		
		$sql = "CREATE TABLE " . $dirtygal_table . " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		pid bigint(11) NOT NULL,
		filename tinytext NOT NULL,
		fullwidth smallint NOT NULL,
		fullheight smallint NOT NULL,
		caption tinytext NOT NULL,
		sorder tinyint DEFAULT '0' NOT NULL,
		crops text NOT NULL,
		processed tinyint(1) DEFAULT '0' NOT NULL,
		cover tinyint(1) DEFAULT '0' NOT NULL,
		UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		//TODO: Add some preemptive versioning stuff
		//TODO: Create the folder and set the initial folder location
		if ( $dirtygal_options == null ) {
			//creates a default 100x100 cropping size
			$dirtygal_options['sizes'] = '100 100 Default Thumb';
			$dirtygal_options['sizes_backup'] = $dirtygal_options['sizes'];
			$dirtygal_options['displayname'] = 'Dirtygal: Images';
			add_option('dirtygal_settings', $dirtygal_options);
		}
	}
	function uninstall() {
		//TODO: write this function (but maybe there are different funcs for deactivation VS uninstalling?)
		//			delete photos
		//			remove options
		//			remove database
	}
	
	function hookAdminInit($hook) {
		$curpage = strrchr($_SERVER['SCRIPT_NAME'],'/');
		$editing = ( $curpage == '/page-new.php' || $curpage == '/post-new.php' || (($curpage == '/post.php' || $curpage == '/page.php') && $_GET['action']=='edit' && current_user_can('edit_post', (int) $_GET['post'])) );
		$uploading = ( $curpage == '/media-upload.php' );
		if ($editing){
			wp_enqueue_style( 'dirtygal', plugins_url('css/dirtygal.css',__FILE__) );
			wp_enqueue_style( 'jcrop', plugins_url('css/jquery.Jcrop.css',__FILE__) );
			
			wp_enqueue_script('dirtygal_js', plugins_url('js/dirtygal.js',__FILE__), array('jquery') );
			wp_enqueue_script('jcrop', plugins_url('js/jquery.Jcrop.js',__FILE__), array('jquery') );
			wp_enqueue_script('jdisabletext', plugins_url('js/jquery.disable.text.select.pack.js',__FILE__), array('jquery') );
			wp_enqueue_script('jlistreorder', plugins_url('js/jquery.listreorder.js',__FILE__), array('jquery') );
		} else if ($uploading) {
			wp_enqueue_style( 'dirtygal', plugins_url('css/uploadTab.css',__FILE__) );
			wp_enqueue_script('dirtygal_js', plugins_url('js/dirtygal_uploadtab.js',__FILE__), array('jquery') );
		}
	}
	function hookAdminSettingsInit(){
		
		wp_enqueue_script('dirtygal_js', plugins_url('js/dirtygal_admin.js',__FILE__), array('jquery') );
	}
	function hookAdminPrintScripts(){
		global $dirtygal_folder, $wpdb, $dirtygal_table, $dirtygal_url, $dirtygal_sizes;
		$pics_a = Dirtygal :: getImagesJSON($_GET['post']);
		echo '
		<script type="text/javascript">
			var dirtygal_url = "'.$dirtygal_url.'";
			var dirtygal_default_size = "'.$dirtygal_sizes[0]['width'].'";
			var dirtygal_initial_data = [ '.( count($pics_a>0) ? @implode(', ',$pics_a) : '' ).'];
		</script>';
	}
	function getImagesJSON($post_id){
		//returns an array of JSON objects holding all image information for a specific post
		
		// WARNING: SUPER CONFUSING QUERY BELOW
		// Having sql do the work to convert to json. Maybe its stupid? Probably faster though. Lots of backslashes and quotes. Weirrrrd.
		//$pics_ar = $wpdb->get_results("SELECT CONCAT( '{ id:', id, ', caption:\"', REPLACE(caption,'\"','\\\\\"'), '\", crops:\"', crops, '\", filename:\"', filename, '\", cover:\"', cover, '\"}') FROM `$dirtygal_table` WHERE `pid` = '".intval($_GET['post'])."' ORDER BY `sorder` ASC" , ARRAY_N);
		global $wpdb, $dirtygal_table;
//		$pics_ar = $wpdb->get_results("SELECT CONCAT( '{ id:', id, ', caption:\"', REPLACE(caption,'\"','\\\\\"'), '\", crops:\"', REPLACE(crops,'\\n','/'), '\", filename:\"', filename, '\", cover:\"', cover, '\"}') FROM `$dirtygal_table` WHERE `pid` = '".intval($post_id)."' ORDER BY `sorder` ASC" , ARRAY_N);
		
		$pics_ar = $wpdb->get_results("SELECT CONCAT( '{ \"id\":', id, ', \"caption\":\"', REPLACE(caption,'\"','\\\\\"'), '\", \"crops\":\"', REPLACE(crops,'\\n','/'), '\", \"filename\":\"', filename, '\", \"cover\":\"', cover, '\"}') FROM `$dirtygal_table` WHERE `pid` = '".intval($post_id)."' ORDER BY `sorder` ASC" , ARRAY_N);
		
		$pics_a = array();
		if ( count( $pics_ar ) > 0 ) foreach ( $pics_ar as $p ) $pics_a[] = $p[0];
		return $pics_a;
	}
	
	function hookAdminMenu() {
		global $dirtygal_options;
		//TODO: make the name "Dirtygal" changeable on the page/post edit form
		$post_types = get_post_types('','names');
		foreach ($post_types as $pt){
			if ($dirtygal_options['enable_'.$pt]){
				add_meta_box("dirtygal", $dirtygal_options['displayname'], array("Dirtygal", "displayEditPostForm"), $pt, "normal", "low");
			}
		}
		$config_page = add_options_page("Dirtygal Configuration", "Dirtygal", 8, __FILE__, array("Dirtygal", "displayOptions"));
				
		register_setting( 'dirtygal_settings', 'dirtygal_settings');
//		register_setting( 'dirtygal_settings', 'path' );
//		register_setting( 'dirtygal_settings', 'sizes' );
	}

	
	function hookAdminFooter() {
		//admin footer?
	}
	
/////////// CONFIGURATION / OPTIONS	
	function initializeSizes(){
		//reads the sizes option and parses it
		global $dirtygal_options, $dirtygal_sizes;
		$dirtygal_sizes = array();
		$sizes_string = $dirtygal_options['sizes'];
		if (!$sizes_string) return 'No size configuration specified';
		$sizes_lines = explode( "\n", $sizes_string );
		foreach ( $sizes_lines as $sizes_line ){
			$first_space = strpos( $sizes_line, ' ' );
			$second_space = strpos( $sizes_line, ' ', $first_space+1 );
			$width = substr( $sizes_line, 0, $first_space );
			$height = substr( $sizes_line, $first_space+1, $second_space - $first_space - 1 );
			$label = substr( $sizes_line, $second_space + 1 );
			if (is_numeric($width) && is_numeric($height) && isset($label)) array_push( $dirtygal_sizes, array( 'width' => $width, 'height' => $height, 'label' => $label ) );
			else return 'An error exists in your crop size configuration settings';
		}
	}
		
	function displayOptions() {
		// the configurations page

/*		
		global $wpdb, $dirtygal_table;
		$pics = $wpdb->get_results("SELECT id, crops FROM $dirtygal_table WHERE 1");
		foreach ($pics as $pic) {
			$newcrops = str_replace("/","\n", $pic->crops);
			$wpdb->query("UPDATE $dirtygal_table SET crops='$newcrops' WHERE id='".$pic->id."'");
		}
		die("testing something... please wait");
*/		
		?>
		<div class="wrap">
        <h2>Dirtygal Options</h2>
		<?php global $dirtygal_sizes; ?>
        <form method="post" action="options.php">
            <?php settings_fields('dirtygal_settings'); ?>
            <?php $options = get_option('dirtygal_settings'); ?>
			<?php $sizeErrors = DirtyGal :: checkCropSettings(); ?>
            <table class="form-table"> 
                <tr valign="top"> 
                    <th scope="row"><label for="displayname">Plug-in Display Name</label></th> 
                    <td><input name="dirtygal_settings[displayname]" type="text" id="displayname" value="<?php echo $options['displayname'] ?>" class="regular-text" /> 
                    <span class="description">Example: <code>Images</code></span> 
                    </td> 
                </tr> 
                <tr valign="top"> 
                    <th scope="row"><label for="upload_path">Upload images to this folder (*note the trailing slash)</label></th> 
                    <td><input name="dirtygal_settings[path]" type="text" id="upload_path" value="<?php echo $options['path'] ?>" class="regular-text code" /> 
                    <span class="description">Example: <code>/var/www/vhosts/myurl.com/httpdocs/wp-content/dg_uploads/</code></span> 
					<?php if (!is_dir($options['path'])): ?>
						<span class="description" style="color:red; font-weight:bold;">ERROR: This folder does not exist!</span>
					<?php endif; ?>
                    </td> 
                </tr> 
                <tr valign="top"> 
                    <th scope="row"><label for="upload_url">URL of images folder (*note the trailing slash)</label></th> 
                    <td><input name="dirtygal_settings[url]" type="text" id="upload_url" value="<?php echo $options['url'] ?>" class="regular-text code" /> 
                    <span class="description">Example: <code>/wp-content/dg_uploads/</code></span>
                    </td> 
                </tr>
                <tr valign="top">
                	<th scope="row"><label for="crop_sizes">Crop Settings (one per line)</label><br />
                    <p style="font-style:italic;">
                        Format is "Width Height Name"<br/>
                        ex: "80 80 Default Thumb"
                    </p></th>
                    <td><textarea name="dirtygal_settings[sizes]" id="crop_sizes" style="width:34em; height:10em;"><?php echo $options['sizes']; ?></textarea>
						<input type="hidden" name="dirtygal_settings[sizes_backup]" id="crop_sizes_backup" value="<?php echo $options['sizes_backup']; ?>" />
                    <p style="font-style:italic;">
                        The first size entered will be the default and must be square (equal width and height)
                    </p>
					<?php if ($sizeErrors) : ?>
					    <p style="color:red;">
	                        <span style="font-weight:bold">ERROR: <?php echo $sizeErrors; ?></span><br/>
	                    </p>	
					<?php endif; ?>
                    </td>
                </tr>
				<tr valign="top"> 
                    <th scope="row">Post Types</th> 
                    <td>Enable DirtyGal on the following post types:<br/>
						<?php 
							$post_types = get_post_types('','names');
							$exclude_types = array('attachment', 'revision', 'nav_menu_item');
							foreach ($post_types as $pt) {
								if ( !in_array($pt, $exclude_types) ){
									?>
									<input name="dirtygal_settings[enable_<?php echo $pt; ?>]" type="checkbox" id="enable_<?php echo $pt ?>" <?php echo $options['enable_'.$pt] ? 'CHECKED' : '' ?> /> 
									<label for="enable_<?php echo $pt ?>"><?php echo $pt ?></label><br/>
									<?php
								}
								
							}
						?>
                    </td> 
                </tr>
            </table>
            <p class="submit">
            	<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>
        </form>
		<?php if ($needsProcessingCount = DirtyGal :: checkIfProcessingNeeded() ) : ?>
			<h2 style="color:red;">IMPORTANT: Reprocessing Needed</h2>
			<p>Some of your images need to be processed (cropped). This can take some time, so please be patient. Click the button below to start the process</p>
			<p><input type="button" id="dirtygal_reprocess_button" value="Start Processing" /> <img id="dirtygal_load_gif_1" src="<?php echo plugins_url('images/loader.gif',__FILE__);?>" />
				<span id="dirtygal_repocess_status" style="font-style:italic;"> <span id="dirtygal_reprocess_count"><?php echo $needsProcessingCount; ?></span> images to be processed</span></p>
		<?php endif; ?>
    	</div>
    	<?php
		//TODO: option to clear all images?
	}
	function checkCropSettings(){
		//Check for errors with the sizes, if there are errors, revert to backup.
		// Also remove deleted crops and create new ones 
		global $dirtygal_sizes, $dirtygal_table, $dirtygal_options, $wpdb;
		$dirtygal_sizes_new = $dirtygal_sizes;
		$crop_sizes_changed = false;
		//echo $dirtygal_options['sizes_backup'].' ---- '.$dirtygal_options['sizes'];
		if ( !trim($dirtygal_options['sizes']) ){
			$error = 'The crop settings are empty!';
		} else if ( $dirtygal_options['sizes_backup'] != $dirtygal_options['sizes'] ){
			//check for errors
			$error = Dirtygal :: initializeSizes();
			if (!$error && $dirtygal_sizes[0]['width'] != $dirtygal_sizes[0]['height']) {
				$error = 'The first crop size must be square (width=height)';
			}
			
			//check for new sizes and old ones that are deleted
			$sizes_string = $dirtygal_options['sizes_backup'];
			if (!$error && !$sizes_string) {
				$wpdb->query("UPDATE $dirtygal_table SET processed='0' WHERE 1");
				$resetCrops = true;
			}
			
			if (!$error) {
				$dirtygal_sizes_backup = array();
				$sizes_to_remove = array();
				$sizes_lines = explode( "\n", $sizes_string );
				foreach ( $sizes_lines as $sizes_line ){
					if (trim($sizes_line)){
						$first_space = strpos( $sizes_line, ' ' );
						$second_space = strpos( $sizes_line, ' ', $first_space+1 );
						$width = substr( $sizes_line, 0, $first_space );
						$height = substr( $sizes_line, $first_space+1, $second_space - $first_space - 1 );
						$label = substr( $sizes_line, $second_space + 1 );
						if (is_numeric($width) && is_numeric($height) && isset($label)) array_push( $dirtygal_sizes_backup, array( 'width' => $width, 'height' => $height, 'label' => $label ) );
					}
				}
				foreach ($dirtygal_sizes_backup as $old_size) {
					for ($i = 0; $i < count ($dirtygal_sizes_new); $i++ ) {
						if ($old_size['width'] == $dirtygal_sizes_new[$i]['width'] && $old_size['height'] == $dirtygal_sizes_new[$i]['height']) {
							$dirtygal_sizes_new[$i]['found'] = true;
							break;
						}
					}
					if ( $i == count($dirtygal_sizes_new) ) {
						$sizes_to_remove[] = $old_size;
					}
				}
			
				//go thru new sizes, if corresponds to a delted size, adjust, otherwise create
				for ($i = 0; $i < count ($dirtygal_sizes_new); $i++ ) {
					if ($dirtygal_sizes_new[$i]['found'] != true){
						$adjusted = false;
						for ($j = 0; $j<count($sizes_to_remove); $j++) {
							if ($sizes_to_remove[$j]['width']/$sizes_to_remove[$j]['height'] == $dirtygal_sizes_new[$i]['width']/$dirtygal_sizes_new[$i]['height']) {
								//ADJUST SIZE
								echo '<h2>ADJUSTED CROP '.$dirtygal_sizes_new[$i]['width'].' x '.$dirtygal_sizes_new[$i]['height'].' FROM '.$sizes_to_remove[$j]['width'].'x'.$sizes_to_remove[$j]['height'].'</h2>';
								DirtyGal :: adjustCropSize($dirtygal_sizes_new[$i]['width'], $dirtygal_sizes_new[$i]['height'], $sizes_to_remove[$j]['width'], $sizes_to_remove[$j]['height']);
								array_splice($sizes_to_remove,$j,1);
								$adjusted = true;
								$crop_sizes_changed = true;
								break;
							}
						}
						if (!$adjusted){
							// ADD THE SIZE
							echo '<h2>ADDED CROP '.$dirtygal_sizes_new[$i]['width'].' x '.$dirtygal_sizes_new[$i]['height'].'</h2>';
							//DirtyGal :: addCropSize($dirtygal_sizes_new[$i]['width'], $dirtygal_sizes_new[$i]['height']);
							$crop_sizes_changed = true;
						}
					}
				}

				for ($i = 0; $i < count ($sizes_to_remove); $i++ ) {
					echo '<h2>REMOVING CROP '.$sizes_to_remove[$i]['width'].' x '.$sizes_to_remove[$i]['height'].'</h2>';					
					//DirtyGal :: removeCropSize($sizes_to_remove[$i]['width'], $sizes_to_remove[$i]['height']);
					$crop_sizes_changed = true;
				}				
			}
		}
		$options = get_option('dirtygal_settings'); 
		if ($resetCrops) {
			//there was no backup, set backup to new values but return an error to let the user know
			$error = 'There is a problem with your crop configuration backup.<br/>Resetting everything... Make sure you reprocess the images down below!';
			$options['sizes_backup'] = $options['sizes'];
			update_option('dirtygal_settings', $options);
			return $error;
		} else if ($error){
			//revert to backup
			$options['sizes'] = $options['sizes_backup'];
			update_option('dirtygal_settings', $options);
			return $error; 
		} else {
			//everything is good, update backup to new values
			if ($crop_sizes_changed) {
				//mark the images for reprocessing
				$wpdb->query("UPDATE $dirtygal_table SET processed='0' WHERE 1");
			}
			$options['sizes_backup'] = $options['sizes'];
			update_option('dirtygal_settings', $options);
		}
		
	}
	function checkIfProcessingNeeded(){
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$needs_update_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $dirtygal_table WHERE processed='0';"));
		if ($needs_update_count > 0) return $needs_update_count;
		return false;
	}
	
	function adjustCropSize( $newWidth, $newHeight, $oldWidth, $oldHeight ){
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$pics = $wpdb->get_results("SELECT id, crops FROM $dirtygal_table WHERE 1");
		foreach ($pics as $pic) {
			$filename_without_suffix = substr($pic->filename, 0, strrpos($pic->filename, '.'));	//stores the original file name without the suffix
			$file_suffix = strtolower(strrchr($pic->filename, '.'));
			//update the crops in the database			
			$crops = preg_replace("/^"."$oldWidth $oldHeight"."(.*)$/m", "$newWidth $newHeight\$1", $pic->crops);
			$wpdb->query("UPDATE $dirtygal_table SET crops='$crops', processed='0' WHERE id='".$pic->id."'");
			
			// DELETE THE OLD FILE
			if ( file_exists($dirtygal_folder . $filename_without_suffix . '-'.$newWidth.'_'.$newHeight . $file_suffix) ) {
				unlink($dirtygal_folder . $filename_without_suffix . '-'.$newWidth.'_'.$newHeight . $file_suffix);
			}
		}
		
	}
	
	function removeCropSize( $width, $height ) {
		// NOT USED! Instead just set processed to 0 and after removing the size the reprocessing should handle it. Crop files will remain!
		//removes all crops of a certain size from the database and from the dirtygal folder
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$pics = $wpdb->get_results("SELECT id, crops FROM $dirtygal_table WHERE 1");
		foreach ($pics as $pic) {
			$crops = preg_replace("/^"."$width $height".".*$/m", '', $pic->crops);
			$id = $pic->id;
			$wpdb->query("UPDATE $dirtygal_table SET crops='$crops' WHERE id='$id'");
			
			$filename_without_suffix = substr($pic->filename, 0, strrpos($pic->filename, '.'));	//stores the original file name without the suffix
			$file_suffix = strtolower(strrchr($pic->filename, '.'));						
			// DELETE THE FILE
			unlink($dirtygal_folder . $filename_without_suffix . '-'.$width.'_'.$height . $file_suffix);
		}
	}
	function addCropSize( $width, $height ){
		// NOT USED! Instead just set processed to 0 and after adding the size the reprocessing should handle it. Crop files will remain!
		//adds a new crop to all images in the database. Does default maximal size centered crop (same as when uploading a new image)
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$pics = $wpdb->get_results("SELECT id, filename, crops FROM $dirtygal_table WHERE 1");
		foreach ($pics as $pic) {
			$filename_without_suffix = substr($pic->filename, 0, strrpos($pic->filename, '.'));	//stores the original file name without the suffix
			$file_suffix = strtolower(strrchr($pic->filename, '.'));
			list($fullwidth, $fullheight, $type, $attr) = getimagesize($dirtygal_folder . $pic->filename );
			$ch = $height * $fullwidth / $width;
			if ($ch <= $fullheight){
				$cw = $fullwidth;
			} else {
				$ch = $fullheight;
				$cw = $width * $fullheight / $height;
			}
			$x1 = ($fullwidth - $cw)/2;
			$y1 = ($fullheight - $ch)/2;
			$x2 = $x1 + $cw;
			$y2 = $y1 + $ch;
			//Dirtygal :: crop_image( $dirtygal_folder . $pic->filename , $x1, $y1, $cw, $ch, $width, $height, $src_abs = false, $dirtygal_folder . $filename_without_suffix . '-'.$width.'_'.$height . $file_suffix );
			$id = $pic->id;
			$crops = $pic->crops."\n$width $height $x1 $y1 $x2 $y2";
			$wpdb->query("UPDATE $dirtygal_table SET crops='$crops', processed='0' WHERE id='$id'");
		}
	}
	function processImage( $id ){
		// Verifies the image exists and makes sure all crops are set and exist
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$pic = $wpdb->get_row("SELECT * FROM $dirtygal_table WHERE id = $id");
		//Verify the original image still exists, and delete the record if it doesn't
		if ( !file_exists($dirtygal_folder . $pic->filename) ) { 
			//$wpdb->query("DELETE FROM `$dirtygal_table` WHERE id = '$id'");
			return 0;
		}
		
		list($fullwidth, $fullheight, $type, $attr) = getimagesize($dirtygal_folder . $pic->filename );
		
		// used just to put old images back into the media library
		//Dirtygal :: addToMediaLibrary( $dirtygal_folder . $pic->filename, $pic->pid );
		
		$filename_without_suffix = substr($pic->filename, 0, strrpos($pic->filename, '.'));	//stores the original file name without the suffix
		$file_suffix = strtolower(strrchr($pic->filename, '.'));
				
		// make sure there are crop settings for each crop size and then create the file if it doesn't exist
		$old_crops_array = Dirtygal :: parseCrops( $pic->crops );
		$new_crops_array = array();
		foreach ($dirtygal_sizes as $dgs){
			$crop_found = false;
			for ($i=0; $i < count($old_crops_array); $i++) {
				if ($old_crops_array[$i][0] == $dgs['width'] && $old_crops_array[$i][1] == $dgs['height']) {
					$new_crops_array[] = $old_crops_array[$i];
					$crop_found = true;
					$width = $old_crops_array[$i][0];
					$height = $old_crops_array[$i][1];
					$x1 = $old_crops_array[$i][2];
					$y1 = $old_crops_array[$i][3];
					$x2 = $old_crops_array[$i][4];
					$y2 = $old_crops_array[$i][5];
					$cw = $x2-$x1;
					$ch = $y2-$y1;
					break;
				}
			}
			if ( !$crop_found ) {
				//if no crop found, make the default crop
				$ch = round($dgs['height'] * $fullwidth / $dgs['width']);
				if ($ch <= $fullheight){
					$cw = $fullwidth;
				} else {
					$ch = $fullheight;
					$cw = round($dgs['width'] * $fullheight / $dgs['height']);
				}
				$x1 = round(($fullwidth - $cw)/2);
				$y1 = round(($fullheight - $ch)/2);
				$x2 = $x1 + $cw;
				$y2 = $y1 + $ch;
				
				$new_crops_array[] = array($dgs['width'], $dgs['height'], $x1, $y1, $x2, $y2) ;
			}
//			echo 'PROCESSING: '.$dirtygal_folder . $pic->filename;
			
			if ( !file_exists($dirtygal_folder . $filename_without_suffix . '-'.$dgs['width'].'_'.$dgs['height'] . $file_suffix) ) {
				Dirtygal :: crop_image( $dirtygal_folder . $pic->filename , $x1, $y1, $cw, $ch, $dgs['width'], $dgs['height'], $src_abs = false, $dirtygal_folder . $filename_without_suffix . '-'.$dgs['width'].'_'.$dgs['height'] . $file_suffix );
			}
		}
		$crops_string = Dirtygal :: getCropsString($new_crops_array);
		$wpdb->query("UPDATE $dirtygal_table SET crops='$crops_string', processed='1', fullwidth='$fullwidth', fullheight='$fullheight' WHERE id='$id'");
		return $crops_string;
	}
	function hookAjaxReprocessImages() {
		//reprocesses images in chunks of 5. Ajax interface will keep calling it until all pics are done.
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		$pics = $wpdb->get_results("SELECT id FROM $dirtygal_table WHERE processed='0' LIMIT 5");
		foreach ($pics as $pic) {
			Dirtygal :: processImage( $pic->id );
		}
		$needs_update_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $dirtygal_table WHERE processed='0';"));
		die('{
			"success" : "true",
			"needs_update_count" : "'.$needs_update_count.'"
		}');
	}
	
	
//////////////  Page/Post Editing Form & Saving Hook
	function displayEditPostForm() {
		// display the editing form (the html parts)
		global $dirtygal_folder, $dirtygal_sizes, $wpdb, $dirtygal_table;
		
		echo 
		'
		<input type="hidden" name="dirtygal_edit_active" value="true" />
		<iframe style="display:none;" src="about:blank" id="dirtygal_iframe" name="dirtygal_iframe"></iframe>
		
		<table class="form-table">
		<tr>
			<th style="width:150px; font-weight:bold">Images</th>
			<th style="width:100%; font-weight:bold;">Preview &amp; Crop</th>
		</tr>
		<tr>
			<td valign="top">
				Upload (jpg, png, zip): <img id="dirtygal_load_gif_1" src="'.plugins_url('images/loader.gif',__FILE__).'" />
				<img id="dirtygal_star_on" src="'.plugins_url('images/star_on.png',__FILE__).'" />
				<img id="dirtygal_star_off" src="'.plugins_url('images/star_off.png',__FILE__).'" />
				<br/><input type="file" id="dirtygal_file_input" name="dirtygal_file_input" value=""/>
				<a href="#" id="dirtygal_server_copy_link">or copy an existing image from the server</a>
				<hr />
				<div id="dirtygal_images_container">
				</div>
			
			</td>
			<td valign="top">
				Adjust Crop Selection for 
				<select id="dirtygal_crop_size_select">';
				foreach ($dirtygal_sizes as $size){
					echo '<option value="'.$size['width'].'x'.$size['height'].'">'.$size['label'].' &mdash; '.$size['width'].'x'.$size['height']."\n";	
				}
		echo 	'</select>
				<!--input type="button" value="Preview" /-->
				<input type="button" id="dirtygal_crop_button" value="Save Crop" /> <img id="dirtygal_load_gif_2" src="'.plugins_url('images/loader.gif',__FILE__).'" /><span id="dirtygal_crop_status"></span>
				<input type="button" id="dirtygal_fit_button" value="Fit Instead of Crop" /> <img id="dirtygal_load_gif_3" src="'.plugins_url('images/loader.gif',__FILE__).'" /><span id="dirtygal_fit_status"></span>
				<div id="dirtygal_fullsize_holder"></div>
			</td>
		</tr>
		</table>
		
		';
	}
	
	function hookSavePost($post_id) {
		global $dirtygal_folder, $dirtygal_options, $wpdb, $dirtygal_table, $dirtygal_url, $dirtygal_sizes;
		if ( !isset( $_POST['dirtygal_edit_active'] ) ) return;		//dirtygal editing tools werent shown, exit
		
		$pics_ar = $wpdb->get_results("SELECT id FROM `".$dirtygal_table."` WHERE `pid` = '".intval($post_id)."'" , ARRAY_N);
		if ( count( $pics_ar ) > 0 ) foreach ( $pics_ar as $p ) {
			$pic_id = $p[0];
			if ( isset( $_POST[ 'dirtygal_caption_'.$pic_id ] ) ) {
				//TODO: check to make sure the files still exist?
				//must strip slashes in case magic quotes is on. Seems wordpress adds more slashes and we end up adding extras if we dont strip first...
				$wpdb->update( $dirtygal_table, array( 'caption' => stripslashes($_POST['dirtygal_caption_'.$pic_id]), 'sorder' => $_POST['dirtygal_order_'.$pic_id], 'cover' => $_POST['dirtygal_cover_'.$pic_id] ), array('id' => $pic_id), array('%s','%d','%d'), array('%d') );
			} else {
				// delete the entry from the database and any files (the original and any crops) 
				$wpdb->query("DELETE FROM `$dirtygal_table` WHERE id = '$pic_id'");
			}
		}	
	}
	
	
///////////////// AJAX Uploading/cropping handling functions	
	function hookAjaxSaveCrop(){
		//handle a request to recrop an image
		
		//TODO: actually do some error checking
		global $dirtygal_options, $dirtygal_sizes, $dirtygal_table, $wpdb;
					  //function wp_crop_image( $src_file, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs = false, $dst_file = false );
		$dirtygal_folder = $dirtygal_options['path'];
		$pic_id = intval($_POST['imageid']);
		$im = $wpdb->get_row('SELECT * FROM `'.$dirtygal_table.'` WHERE id = "'.$pic_id.'"', ARRAY_A);
		$filename = $im['filename'];
		$filename_without_suffix = substr($filename, 0, strrpos($filename, '.'));
		$file_suffix = substr($filename, strlen($filename)-4);
		
		if ($_POST['usefit']=='true'){
			list($fullwidth, $fullheight, $type, $attr) = getimagesize($dirtygal_folder . $filename );
			$fitp = Dirtygal :: checkFitImage( $filename, $fullwidth, $fullheight, $_POST['cropwidth'], $_POST['cropheight'] );
			
			$crops_array = Dirtygal :: parseCrops( $im['crops'] );
			Dirtygal :: changeCropSize($crops_array, intval($_POST['cropwidth']), intval($_POST['cropheight']), 'fit', $fitp['width'], $fitp['height'], 0);
			$wpdb->update( $dirtygal_table, array( 'crops' => Dirtygal ::getCropsString($crops_array) ), array('id' => $pic_id), array('%s'), array('%d') );
		} else {
		
			$x1 = intval($_POST['x1']);
			$x2 = intval($_POST['x2']);
			$y1 = intval($_POST['y1']);
			$y2 = intval($_POST['y2']);
		
			//TODO: check to see if cropwidth and cropheight correspond to a real size, if not, do nothing!
		
			Dirtygal :: crop_image( $dirtygal_folder . $filename , $x1, $y1, $x2-$x1, $y2-$y1, intval($_POST['cropwidth']), intval($_POST['cropheight']), $src_abs = false, $dirtygal_folder . $filename_without_suffix . '-'.intval($_POST['cropwidth']).'_'.intval($_POST['cropheight']) . $file_suffix );

			//update the crops in the database
			$crops_array = Dirtygal :: parseCrops( $im['crops'] );
			Dirtygal :: changeCropSize($crops_array, intval($_POST['cropwidth']), intval($_POST['cropheight']), $x1, $y1, $x2, $y2);
			$wpdb->update( $dirtygal_table, array( 'crops' => Dirtygal ::getCropsString($crops_array) ), array('id' => $pic_id), array('%s'), array('%d') );
		}
		
		die('{
			"success" : "true",
			"response" : "'.$wpdb->last_error.'"
		}');
	}
	
	function hookAjaxUploadHandler(){
		//handle a newly uploaded image
		global $dirtygal_options, $dirtygal_sizes, $dirtygal_table, $wpdb;
		$fieldname = 'dirtygal_file_input';
		$dirtygal_folder = $dirtygal_options['path'];

/*
		die ('{
			  "success": "false",
			  "user_response": "'.$filename.' - '.$file_suffix.'-'. strrchr($filename, '.').'",
			  "error" : "test"
		  }'); 
*/
		if (!is_user_logged_in()){
			//make sure user is logged in according to wordpress
			$success = 'false';
			$user_response = 'You must be logged in to upload images';
			$error = 'User not logged in';
		} else if (!is_dir($dirtygal_folder)){
			//Make sure the dirtygal folder is set and exsists
			$success = 'false';
			$user_response = "Uploads folder does not exist, go into Dirtygal settings and change it";
			$error = "Folder does not exist.";
		} else if ($_FILES[$fieldname]['error']!=0){
			//Check PHP's built in file uploading errors
			$errors = array(1 => 'Max file size exceeded in php.ini.',
							2 => 'Html form max file size exceeded.',
							3 => 'File upload was only partial.',
							4 => 'Please choose a file that exists.');
			$success = 'false';
			$user_response = $errors[$_FILES[$fieldname]['error']];
			$error = $_FILES[$fieldname]['error'];
		} else if (!is_uploaded_file($_FILES[$fieldname]['tmp_name'])){
			//Make sure the file got uploaded and exists
			$success = 'false';
			$user_response = "Please choose a file to upload";
			$error = "Not an HTTP upload.";
		} else if ( $_FILES[$fieldname]['size'] <= 0 ){
			//Make sure the file got uploaded and exists
			$success = 'false';
			$user_response = "Sorry, the file you selected is corrupt or has not contents. Please choose a different file.";
			$error = "File size of 0kb";
		}			
		
		if ($success == 'false'){			
			die('
			{
				"success": "'.$success.'", 
				"user_response":"'.$user_response.'",
				"error":"'.$error.'"
			}');
		} 
		
		//do the saving and cropping stuff and return some junk
		$filename = $_FILES[$fieldname]['name'];
		$filename_without_suffix = substr($filename, 0, strrpos($filename, '.'));	//stores the original file name without the suffix
		$file_suffix = strtolower(strrchr($filename, '.'));
		$files_json = array();
		
		// Deal with an archive if its a .zip file		
		if ($file_suffix == '.zip'){
			$total_files = $accepted_files = 0;
			$filenames = array();
			$zip = zip_open( $_FILES[$fieldname]['tmp_name'] );
			if ( is_resource($zip) ) {
			  while ( $zip_entry = zip_read($zip) ) {
				$total_files++;
				$filename = Dirtygal :: getFinalFilename( zip_entry_name($zip_entry) );
				if ( strstr($filename, '/') ) {
					die('
					{
						"success": "false", 
						"user_response":"The archive you uploaded contained a folder. It should contain only images.",
						"error":"Archive of Folder"
					}');
					
				}
				$file_suffix = strtolower(strrchr($filename, '.'));
				
				if ($file_suffix == '.jpg' || $file_suffix == '.tif' || $file_suffix == '.png') {
					$fp = fopen( $dirtygal_folder . $filename, "w");
					if (zip_entry_open($zip, $zip_entry, "r")) {
				    	$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				    	fwrite($fp,"$buf");
				    	zip_entry_close($zip_entry);
				    	fclose($fp);
						if ( Dirtygal :: verifyUploadedImage($dirtygal_folder . $filename) ) {
							$filenames[] = $filename;
							$accepted_files++;
						}
				    }
				}
			  }
			  zip_close($zip);
			  // Reverse filenames array and initialize them. This is so that the files are added in the same order as they were in the zip file
			  $filenames = array_reverse($filenames);
			  foreach ($filenames as $filename ){
				$files_json[] = Dirtygal :: initializeUploadedImage( $filename );
			  }  
			} else {
				die('
				{
					"success": "false", 
					"user_response":"There was an error with the archive you uploaded",
					"error":"Corrupted archive"
				}');
			}
		// ELSE - SINGLE IMAGE UPLOADED
		} else {
			$filename = Dirtygal :: getFinalFilename( $filename );
			@move_uploaded_file($_FILES[$fieldname]['tmp_name'], $dirtygal_folder . $filename ) or die('																			 			{
				"success": "false", 
				"user_response":"Permission Error. Please contact website technical support.",
				"error":"Insufficient permissions granted in order to upload files."
			}');
			if ( Dirtygal :: verifyUploadedImage($dirtygal_folder . $filename) ){
				$total_files = $accepted_files = 1;
				$files_json[] = Dirtygal :: initializeUploadedImage( $filename );
			} else {
				die('{
					"success" : "false",
					"user_response" : "The uploaded image was not actually an image",
					"error" : "Invalid image file",
				}');
			}
		}
		
		//Return successful message
		die('{
			"success" : "true",
			"user_response" : "'.$wpdb->print_error().'",
			"error" : "No errors",
			"total" : "'.$total_files.'",
			"accepted" : "'.$accepted_files.'",
			"images" : ['.implode(',', $files_json).']
		}');
	}
	function hookAjaxCopyHandler(){
		global $dirtygal_options, $dirtygal_sizes, $dirtygal_table, $wpdb, $dirtygal_folder;
		$file_id = (int) $_POST['file_id'];
		if ( $_POST['type'] == 'dirtygal' ){ 
			//copy from dirtygal post
			$pic = $wpdb->get_row("SELECT * FROM $dirtygal_table WHERE id = $file_id");
			$filename = Dirtygal :: getFinalFilename( $pic->filename );
			if (false === copy ( $dirtygal_folder . $pic->filename, $dirtygal_folder . $filename ) ){
				die(' {	"success" : "false",
						"user_response" : "Could not copy '.$dirtygal_folder . $pic->filename.' to '.$dirtygal_folder . $filename.'" ,
						"error" : "No errors"
					  }');	
			}
			
			$pid = (int) $_POST['post_ID'];
			$wpdb->query( "UPDATE $dirtygal_table SET sorder = sorder+1 WHERE pid = $pid");
			$wpdb->insert( $dirtygal_table, array('pid'=>$pid, 'filename'=> $filename, 'caption'=>$pic->caption, 'sorder'=>1, 'crops'=>$pic->crops), array( '%d', '%s', '%s', '%d', '%s' ) );
			$newpic_id = $wpdb->insert_id;
			$crops_string = Dirtygal :: processImage( $newpic_id );
			
			die('{
				"success" : "true",
				"user_response" : "'.$wpdb->print_error().'",
				"error" : "No errors",
				"total" : "1",
				"accepted" : "1",
				"images" : [{	"id" : '.$newpic_id.',
								"filename" : "'.$filename.'",
								"caption" : "'.addslashes($pic->caption).'",
								"crops" : "'.str_replace("\n", '/', $crops_string).'"
							}]
			}');
		} else {
			// copy from media library
			$filename = get_attached_file( $file_id );
			$new_filename = Dirtygal :: getFinalFilename( substr( strrchr( $filename, '/'), 1) );

			if (false === copy ( $filename, $dirtygal_folder . $new_filename ) ){
				die(' {	"success" : "false",
						"user_response" : "Could not copy '.$filename.' to '.$dirtygal_folder . $new_filename.'" ,
						"error" : "No errors"
					  }');	
			}
			
			if ( Dirtygal :: verifyUploadedImage($dirtygal_folder . $new_filename) ){
				$total_files = $accepted_files = 1;
				$files_json[] = Dirtygal :: initializeUploadedImage( $new_filename );
			} else {
				die('{
					"success" : "false",
					"user_response" : "The copied file is not a valid image",
					"error" : "Invalid image file",
				}');
			}
			
			// Return successful message
			die('{
				"success" : "true",
				"user_response" : "'.$wpdb->print_error().'",
				"error" : "No errors",
				"total" : "'.$total_files.'",
				"accepted" : "'.$accepted_files.'",
				"images" : ['.implode(',', $files_json).']
			}');
		}
	}
	
	function verifyUploadedImage( $fullfilename ){
		//verifies the file is actually an image and if not it is deleted
		$file_info = getimagesize( $fullfilename );
		if( empty($file_info) ) {
			unlink($fullfilename);
			return false;
		} else {
			$file_mime = $file_info['mime'];
			if ($file_mime == 'image/jpeg' ||
				$file_mime == 'image/png' ||
				$file_mime == 'image/gif' ||
				$file_mime == 'image/tiff' ||
				$file_mime == 'image/bmp' ){
					return true;
			} else {
				unlink($fullfilename);
				return false;
			} 
		}
	}
	
	function initializeUploadedImage( $filename ){
		// creates initial crops, inserts an entry in the database, returns JSON info
		global $dirtygal_options, $dirtygal_sizes, $dirtygal_table, $wpdb;
		$dirtygal_folder = $dirtygal_options['path'];
		
		$filename_without_suffix = substr($filename, 0, strrpos($filename, '.'));	//stores the original file name without the suffix
		$file_suffix = strtolower(strrchr($filename, '.'));

		//try to read the caption from IPTC headers
		$info = array();                      
		getimagesize($dirtygal_folder . $filename, $info);
		if ( isset( $info['APP13'] ) ) {	//this is the IPTC block
		    $iptc = iptcparse( $info['APP13'] );
		    $caption = $iptc['2#120'][0];
		} else {
			$caption = $filename_without_suffix;
		}

		//Adjust all other order vars up by 1 (move them down in the order)
		$wpdb->query( "UPDATE $dirtygal_table SET sorder = sorder+1 WHERE pid = $pid");

		$pid = (int) $_POST['post_ID'];
		$wpdb->insert( $dirtygal_table, array('pid'=>$pid, 'filename'=> $filename, 'caption'=>$caption, 'sorder'=>1), array( '%d', '%s', '%s', '%d' ) );
		$newpic_id = $wpdb->insert_id;
		$crops_string = Dirtygal :: processImage( $newpic_id );
//		Dirtygal :: addToMediaLibrary( $dirtygal_folder . $filename, $pid );
		return '
			{ 
				"id" : '.$newpic_id.',
				"filename" : "'.$filename.'",
				"caption" : "'.addslashes($caption).'",
				"crops" : "'.str_replace("\n", '/', $crops_string).'"
			}';


		/*
		// creates initial crops, inserts an entry in the database, returns JSON info
		global $dirtygal_options, $dirtygal_sizes, $dirtygal_table, $wpdb;
		$dirtygal_folder = $dirtygal_options['path'];
		
		$filename_without_suffix = substr($filename, 0, strrpos($filename, '.'));	//stores the original file name without the suffix
		$file_suffix = strtolower(strrchr($filename, '.'));

		//initial cropping stuff
		list($fullwidth, $fullheight, $type, $attr) = getimagesize($dirtygal_folder . $filename );

		$crops_array = array();
		foreach ($dirtygal_sizes as $dgs){
			$ch = $dgs['height'] * $fullwidth / $dgs['width'];
			if ($ch <= $fullheight){
				$cw = $fullwidth;
			} else {
				$ch = $fullheight;
				$cw = $dgs['width'] * $fullheight / $dgs['height'];
			}
			$x1 = ($fullwidth - $cw)/2;
			$y1 = ($fullheight - $ch)/2;
			$x2 = $x1 + $cw;
			$y2 = $y1 + $ch;
			Dirtygal :: crop_image( $dirtygal_folder . $filename , $x1, $y1, $cw, $ch, $dgs['width'], $dgs['height'], $src_abs = false, $dirtygal_folder . $filename_without_suffix . '-'.$dgs['width'].'_'.$dgs['height'] . $file_suffix );
			Dirtygal :: changeCropSize($crops_array, $dgs['width'], $dgs['height'], $x1, $y1, $x2, $y2);
		}
		$crops_string = Dirtygal :: getCropsString($crops_array);
		
		//try to read the caption from IPTC headers
		$info = array();                      
		getimagesize($dirtygal_folder . $filename, $info);
		if ( isset( $info['APP13'] ) ) {	//this is the IPTC block
		    $iptc = iptcparse( $info['APP13'] );
		    $caption = $iptc['2#120'][0];
		} else {
			$caption = $filename_without_suffix;
		}
		
		$pid = (int) $_POST['post_ID'];
		//Adjust all other order vars up by 1 (move them down in the order)
		$wpdb->query( "UPDATE $dirtygal_table SET sorder = sorder+1 WHERE pid = $pid");
		//Insert the new image at the top
		$wpdb->insert( $dirtygal_table, array('pid'=>$pid, 'filename'=> $filename, 'caption'=>$caption, 'sorder'=>1, 'crops'=> $crops_string), array( '%d', '%s', '%s', '%d', '%s' ) );
		$newpic_id = $wpdb->insert_id;
		Dirtygal :: addToMediaLibrary( $dirtygal_folder . $filename, $pid );
		return '
			{ 
				"id" : '.$newpic_id.',
				"filename" : "'.$filename.'",
				"caption" : "'.addslashes($caption).'",
				"crops" : "'.str_replace("\n", '/', $crops_string).'"
			}';
		*/
	}
	
	function addToMediaLibrary($file, $post_id = 0) {
		// copies the file to the Media Library uploads folder and "attaches" the file to the post.
		// this function is copied from the "Add From Server" plugin.  --http://wordpress.org/extend/plugins/add-from-server/
		set_time_limit(120);
		$time = current_time('mysql');
		if ( $post = get_post($post_id) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 )
				$time = $post->post_date;
		}

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) )
			return new WP_Error($uploads['error']);

		$wp_filetype = wp_check_filetype( $file, null );

		extract( $wp_filetype );
		
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) )
			return new WP_Error('wrong_file_type', __( 'File type does not meet security guidelines. Try another.' ) ); //A WP-core string..

		//Is the file allready in the uploads folder?
		if( preg_match('|^' . preg_quote(str_replace('\\', '/', $uploads['basedir'])) . '(.*)$|i', $file, $mat) ) {

			$filename = basename($file);
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts(array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => $uploads['subdir'] . '/' . $filename ));
			if ( !empty($attachment) )
				return $attachments[0]->ID;

			//Ok, Its in the uploads folder, But NOT in WordPress's media library.
			if ( preg_match("|(\d+)/(\d+)|", $mat[1], $datemat) ) //So lets set the date of the import to the date folder its in, IF its in a date folder.
				$time = mktime(0, 0, 0, $datemat[2], 1, $datemat[1]);
			else //Else, set the date based on the date of the files time.
				$time = @filemtime($file);

			if ( $time ) {
				$post_date = date( 'Y-m-d H:i:s', $time);
				$post_date_gmt = gmdate( 'Y-m-d H:i:s', $time);
			}
		} else {	
			$filename = wp_unique_filename( $uploads['path'], basename($file));

			// copy the file to the uploads dir
			$new_file = $uploads['path'] . '/' . $filename;
			if ( false === @copy( $file, $new_file ) )
				wp_die(sprintf( __('The selected file could not be copied to %s.', 'add-from-server'), $uploads['path']));

			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;
		}

		// Compute the URL
		$url = $uploads['url'] . '/' . rawurlencode($filename);

		//Apply upload filters
		$return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );
		$new_file = $return['file'];
		$url = $return['url'];
		$type = $return['type'];

		$title = preg_replace('!\.[^.]+$!', '', basename($file));
		$content = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($new_file) ) {
			if ( '' != trim($image_meta['title']) )
				$title = trim($image_meta['title']);
			if ( '' != trim($image_meta['caption']) )
				$content = trim($image_meta['caption']);
		}

		if ( empty($post_date) )
			$post_date = current_time('mysql');
		if ( empty($post_date_gmt) )
			$post_date_gmt = current_time('mysql', 1);

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_id,
			'post_title' => $title,
			'post_name' => $title,
			'post_content' => $content,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt
		);

		// Save the data
		$id = wp_insert_attachment($attachment, $new_file, $post_id);
		if ( !is_wp_error($id) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}

		return $id;
	}
	
	// This is a copy of wp_crop_image but changed to support multiple file types.
	// Taken from http://www.thinkplexx.com/blog/wordpress-image-croping-does-only-jpeg-buddypress-avatar-cropping-uppload-transparency-problem
	function crop_image( $src_file, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs = false, $destfilename = false ) {
		 if ( is_numeric( $src_file ) ) // Handle int as attachment ID
		  $src_file = get_attached_file( $src_file );
		 
		 $src = wp_load_image( $src_file );
		 
		 if ( !is_resource( $src ))
		  return $src;
		 
		 $dst = wp_imagecreatetruecolor( $dst_w, $dst_h );
		 
		 if ( $src_abs ) {
		  $src_w -= $src_x;
		  $src_h -= $src_y;
		 }
		 
		 list($width, $height, $orig_type) =  getimagesize( $src_file );
		 
		 if (function_exists('imageantialias'))
		  imageantialias( $dst, true );
		 
		 imagecopyresampled( $dst, $src, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
		 
		 imagedestroy( $src ); // Free up memory
		 
		// if ( ! $dst_file )
		//  $dst_file = str_replace( basename( $src_file ), 'cropped-' . basename( $src_file ), $src_file );
		 
		        // convert from full colors to index colors, like original PNG.
		        if ( IMAGETYPE_PNG == $orig_type && !imageistruecolor( $dst ) )
		                imagetruecolortopalette( $dst, false, imagecolorstotal( $dst ) );
		 
		        if ( IMAGETYPE_GIF == $orig_type ) {
		                if ( !imagegif( $dst, $destfilename ) )
		                        return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
		        } elseif ( IMAGETYPE_PNG == $orig_type ) {
		                if ( !imagepng( $dst, $destfilename ) )
		                       return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
		      } else {
		               if ( !imagejpeg( $dst, $destfilename, apply_filters( 'jpeg_quality', 90, 'wp_crop_image' ) ) )
		                       return new WP_Error('resize_path_invalid', __( 'Resize path invalid' ));
		       }
		 
		 //$dst_file = preg_replace( '/\\.[^\\.]+$/', '.jpg', $dst_file );
		 
		 //if ( imagejpeg( $dst, $dst_file, apply_filters( 'jpeg_quality', 90, 'wp_crop_image' ) ) )
		//  return $dst_file;
		// else
		//  return false;
	}
	
//////////////DISPLAY FUNCTIONS - to be used in the front end
	function getImages($pid, $args){
		global $wpdb, $dirtygal_options, $dirtygal_table;
		
		//IDEA:  Add an option to set one dimension and scale images accordingly?
		
		$defaults = array(
			'type' => 'crop',		// could be 'fit' or 'original'
		);
		$args = wp_parse_args( $args, $defaults );	
		$pid = intval($pid);
		
		$pics = $wpdb->get_results('SELECT filename, caption, crops, fullwidth, fullheight FROM `'.$dirtygal_table.'` WHERE `pid`="'.$pid.'" ORDER BY `sorder` ASC', ARRAY_A);
		for ($i = 0; $i < count($pics); $i++){
			$f = $pics[$i]['filename'];
			$pics[$i]['filename_fullres'] = $dirtygal_options['url'] . $f;
			$len = strlen($f) - 4;
			
			if ($args['type'] == 'crop') {	// Use user specified crop (and fit) settings
				$crops = $pics[$i]['crops'];
				$matches = array();
				$fitmatch = preg_match("/^"."$width $height fit ([0-9]*) ([0-9]*) 0$/m", $crops, $matches );

				if (!$fitmatch) {
					$pics[$i]['fit'] = false;
					$pics[$i]['width'] = $width;
					$pics[$i]['height'] = $height;
					$pics[$i]['filename'] = $dirtygal_options['url'] . substr($f, 0, $len ) . ( $args['width'] || $args['height'] ? '-'.$args['width'].'_'.$args['height'] : '' ) . substr($f, $len);
				} else {
					$pics[$i]['fit'] = true;
					$pics[$i]['width'] = $matches[1];
					$pics[$i]['height'] = $matches[2];
					$pics[$i]['filename'] = $dirtygal_options['url'] . substr($f, 0, $len ) . '-fit-' . $args['width'] . '_' . $args['height'] . substr($f, $len);
				}
			} else if ( $args['type'] == 'fit') {
				$fitp = Dirtygal :: checkFitImage($pics[$i]['filename'], $pics[$i]['fullwidth'], $pics[$i]['fullheight'], $args['width'], $args['height']);	
				$pics[$i]['fit'] = true;
				$pics[$i]['width'] = $fitp['width'];
				$pics[$i]['height'] = $fitp['height'];
				$pics[$i]['filename'] = $dirtygal_options['url'] . $fitp['filename'];
			}
		}
		return $pics;
	}
	function checkFitImage($filename, $fullwidth, $fullheight, $width, $height){
		global $dirtygal_folder;
		$len = strlen($filename) - 4;
		$filename_without_suffix = substr($filename, 0, $len);
		$file_suffix = substr($filename, $len);
		
		$fitheight = $height;
		$fitwidth = round($fullwidth * $height / $fullheight);
		if ($fitwidth > $width) {
			$fitwidth = $width;
			$fitheight = round($fullheight * $width / $fullwidth);
		}
		
		$fit_file = $filename_without_suffix . '-fit-' . $width . '_' . $height . $file_suffix;
		if ( ! file_exists( $dirtygal_folder . $fit_file ) ) {
			Dirtygal :: crop_image( $dirtygal_folder . $filename , 0, 0, $fullwidth, $fullheight, $fitwidth, $fitheight, $src_abs = false, $dirtygal_folder . $fit_file);
		}
		return array( 'filename' => $fit_file, 'width' => $fitwidth, 'height' => $fitheight );
	}
	
	function getNumImages($pid){
		global $wpdb, $dirtygal_options, $dirtygal_table;
		$pid = intval($pid);
		$num = $wpdb->get_var('SELECT COUNT(*) AS num FROM `'.$dirtygal_table.'` WHERE `pid`="'.$pid.'"', 0, 0);
		return $num;
	}
	function getMainImage($pid, $width = 0, $height = 0){
		global $wpdb, $dirtygal_options, $dirtygal_table;
		
		$pic = $wpdb->get_row('SELECT filename, caption FROM `'.$dirtygal_table.'` WHERE `pid`="'.$pid.'" AND `cover`=1', ARRAY_A);
		if ($pic == null){
			$pic = $wpdb->get_row('SELECT filename, caption FROM `'.$dirtygal_table.'` WHERE `pid`="'.$pid.'" ORDER BY `sorder` ASC LIMIT 1', ARRAY_A);			
		}
		if ($pic['filename'] == null) {
			$pic['filename'] = $dirtygal_options['url'] . 'box.jpg';
			return $pic;
		}
		
		$f = $pic['filename'];
		$len = strlen($f) - 4;
		$pic['filename'] = $dirtygal_options['url'] . substr($f, 0, $len ) . ( $width || $height ? '-'.$width.'_'.$height : '' ) . substr($f, $len);
		return $pic;
	}
	function getRandomImages($numImages, $width = 0, $height = 0){
		//returns some random images
		global $wpdb, $dirtygal_options, $dirtygal_table;
		
		$pics = $wpdb->get_results("SELECT filename, caption, pid FROM `$dirtygal_table` WHERE `pid` IN (SELECT id FROM `$dirtygal_table` GROUP BY `pid` HAVING count(id) > 1) ORDER BY RAND() LIMIT $numImages", ARRAY_A);
		for ($i = 0; $i < count($pics); $i++){
			$f = $pics[$i]['filename'];
			$len = strlen($f) - 4;
			$pics[$i]['filename'] = $dirtygal_options['url'] . substr($f, 0, $len ) . ( $width || $height ? '-'.$width.'_'.$height : '' ) . substr($f, $len);
		}
		return $pics;
	}
	
///////// Functions to deal with the stored crop sizes
	function parseCrops($crops_string){
		//reads the sizes option and parses it
		global $dirtygal_sizes;
		$crops_lines = explode( "\n", $crops_string );
		$crops = array();
		if ( count($crops_lines) > 0 ) foreach ( $crops_lines as $crops_line ){
			$nums = split("[ ]+", $crops_line);
			if ( count($nums) > 0 ) array_push( $crops, $nums );
		}
		return $crops;
	}
	function getCropsString($crops_array){
		$crops_string = '';
		if ( count($crops_array) > 0 ) foreach ( $crops_array as $crop ){
			$crops_string .= implode(' ', $crop )."\n";
		}
		if (strlen($crops_string)>0) $crops_string = substr($crops_string, 0, strlen($crops_string)-1);	//remove the extra \n
		return $crops_string;
	}
	function changeCropSize(&$crops_array, $width, $height, $x1, $y1, $x2, $y2){
		for ($x = 0; $x<count($crops_array); $x++){
			if ($crops_array[$x][0] == $width && $crops_array[$x][1] == $height){
				$crops_array[$x][2] = $x1;
				$crops_array[$x][3] = $y1;
				$crops_array[$x][4] = $x2;
				$crops_array[$x][5] = $y2;
				return;
			}
		}
		// only reaches this point if there was no crop at this size
		array_push($crops_array, array($width, $height, $x1, $y1, $x2, $y2) );
	}
////////// Upload Tabs
// Adding a tab to the insert image media dialogue. More info here: http://axcoto.com/blog/article/307
	function hookInitUploadTabs($tabs){
		$newtab = array('dirtygal' => __('From A Gallery', 'dirtygalMediaTab'));
		$tabs = array_merge($tabs, $newtab);
		unset($tabs['gallery']);	//remove the NextGen Gallery tab
		if ($_GET['type'] == 'dirtygal'){	//removes the From Computer & From URL tabs when copying from server into dirtygal
			unset($tabs['type']);
			unset($tabs['type_url']);
			echo '<script type="text/javascript">var dirtygal_copy_popup = true;</script>';
		}
		return $tabs;
	}
	
	function hookMediaUploadTab(){
		//array("Dirtygal", "hookMediaUploadTab")
		// this function has to start with "media" for the css to work properly
		return wp_iframe( array("Dirtygal", "mediaUploadTab") );
	}
	function hookUploadTabDefault( $default ){	
		// sets the default tab to "From A Gallery" when copying from server into dirtygal
		if ($_GET['type']=='dirtygal') return 'dirtygal';
		return $default;
	}
	function hookMediaTabFetchImages() {
		$pics_a = Dirtygal :: getImagesJSON($_GET['postid']);
		die('{ 
			"pics" : ['.implode(',', $pics_a).'] 
		}');
	}
	function mediaUploadTab() {
		global $dirtygal_options, $wpdb;
		media_upload_header();
		$post_types = get_post_types('','objects');
		$pic_counts = Dirtygal :: getPicCounts();
		?>
		<form method="post" class="media-upload-form type-form validate">
		<h3 class="media-title">Reuse an image from your galleries</h3>
		<div class="dgu_single_thumb">
			<div class="dgu_thumb"></div>
			<div class="dgu_thumb_meta">
				<div class="dgu_thumb_caption">Caption goes here</div>
				<select>
					<option value="-1">Select a size...</option>
				</select>
				<input type="hidden" name="filename" class="dgu_filename" value="filenamegoeshere" />
				<input type="hidden" name="fileid" class="dgu_fileid" value="idgoeshere" />
			</div>
		</div>
		<img id="dirtygal_load_gif" src="<?php echo plugins_url('images/loader.gif',__FILE__);?>" />
		<div id="media-upload-notice"> 
		</div> 
		<div id="media-upload-error"> 
		</div> 
		<div id="html-upload-ui">
		View: <span id="dgu_post_types">
		<?php
		$first = 1;
		foreach ($post_types as $pt){
			if ($dirtygal_options['enable_'.$pt->name]){
				?>
				<a href="#" <?php echo ( $first++ == 1 ? 'class="selected"' : ''); ?> id="dgu_show_<?php echo $pt->name; ?>"><?php echo $pt->labels->name; ?></a>
				<?php
			}
		}
		?>
		
		</span>
		<?php
		$first = 1;
		foreach ($post_types as $pt){
			if ($dirtygal_options['enable_'.$pt->name]){
				?>

				<div class="dgu_post_container<?php echo ( $first++ == 1 ? ' selected' : ''); ?>" id="dgu_container_<?php echo $pt->name; ?>">
					<?php
					$posts = get_posts('post_type='.$pt->name.'&nopaging=true'.($pt->name=='posts'?'&orderby=date&':'&orderby=title&order=ASC'));
					foreach ($posts as $post){
						$pcount = 0;
						if ($pic_counts) foreach ($pic_counts as $pc) {
							if ($pc->pid == $post->ID) $pcount = $pc->pcount;
						}
						if ($pcount > 0) :
						?>
						<div class="dgu_post" id="post_<?php echo $post->ID; ?>">
							<div class="dgu_post_item">
								<span class="dgu_post_title"><?php echo $post->post_title?></span>
								<?php if ($pcount>0) : ?><div class="dgu_toggle"><br></div>
								<?php else : ?><div class="dgu_toggle_filler"><br></div><?php endif; ?>
								<span class="dgu_pic_count"><?php echo $pcount; ?> images</span>
							</div>
							<div class="dgu_thumb_container">
							<div class="dgu_thumbs"></div>
							</div>
						</div>
						<?php
						endif;
					}
					?>
				</div>
				<?php
			}
		}
		?>
		</div>
		</form>
		<?php
	}
	
////////// Other Functions
	// adds a counter to the end of a filename (if required) so it can be stored in the dirtygal folder without any conflicts
	function getFinalFilename( $filename ){
		global $dirtygal_folder;
		$filename_without_suffix = substr($filename, 0, strrpos($filename, '.'));	//stores the original file name without the suffix
		$file_suffix = strtolower(strrchr($filename, '.'));
		if ($file_suffix == '.jpeg') $file_suffix = '.jpg';
		else if ($file_suffix == '.tiff') $file_suffix = '.tif';
		
		$filename = $filename_without_suffix . $file_suffix;	//just fixes capitalized and 4 letter file suffix
		//if there is a collision, go thru and add a counter until there is no collision
		if ( file_exists( $dirtygal_folder . $filename ) ){
			$dup_name_counter = 1;
			while ( file_exists( $dirtygal_folder . $filename_without_suffix . '_' . $dup_name_counter . $file_suffix) ) $dup_name_counter++;
			$filename = $filename_without_suffix . '_' . $dup_name_counter . $file_suffix;
			//$filename_without_suffix .= '_'.$dup_name_counter;
		}
		return $filename; 
	}
	function getPicCounts(){
		global $wpdb, $dirtygal_table;
		return $wpdb->get_results("SELECT pid, COUNT(*) AS pcount FROM $dirtygal_table WHERE 1 GROUP BY pid;");
	}
}

?>
