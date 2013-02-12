<?php

/*
Plugin Name: TOZ RunKeeper WordPress Activity Feed
Plugin URI: http://thinkonezero.com
Description: A plugin to automatically draft posts of all your Runkeeper Activities.
Author: A. Kai Armstrong
Version: 1.0
Author URI: http://www.kaiarmstrong.com
*/

//Setup DB Options
function toz_rk_activate(){
	add_option('toz_rk_access_token', '');
	add_option('toz_rk_auth_code', '');
	add_option('toz_rk_author_id', '');
	add_option('toz_rk_post_categories', '');
	add_option('toz_rk_last_event', '0');
}
register_activation_hook( __FILE__, 'toz_rk_activate');

//Add our Admin Menu
add_action('admin_menu', 'toz_rk_menu');
function toz_rk_menu() {
	add_options_page('RunKeeper WordPress Activity Feed Options', 'RunKeeper Activity Feed', 'manage_options', 'runkeeper-wordpress-activity-feed', 'toz_rk_admin');
}

//Setup Paths and API
define('TOZRKPATH', plugin_dir_path(__FILE__));
define('YAMLPATH', TOZRKPATH.'includes/yaml/');
define('RUNKEEPERAPIPATH', TOZRKPATH.'includes/runkeeperAPI/');
define('CONFIGPATH', TOZRKPATH.'includes/');

require(YAMLPATH.'lib/sfYamlParser.php');
require(RUNKEEPERAPIPATH.'lib/runkeeperAPI.class.php');

//Admin Interface
function toz_rk_admin() {
	/* API initialization */
	$toz_rkAPI = new runkeeperAPI(
		CONFIGPATH.'rk-api.yml'	/* api_conf_file */
	);
	if ($toz_rkAPI->api_created == false) {
		echo 'error '.$toz_rkAPI->api_last_error; /* api creation problem */
		exit();
	}
	
	/* Stores the access values we'll need after we've authorized our account. */
	if ($_GET['code'] && $_GET['access_token']) {
		update_option('toz_rk_auth_code', $_GET['code']);
		update_option('toz_rk_access_token', $_GET['access_token']);
	}
	
	//Set the Acess Token for API Use
	$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
	if ( !empty($toz_rk_auth_code) ) {
		$toz_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
	}
	
	//Update Plugin Options
	if ( isset($_POST['action']) && ( $_POST['action'] == 'toz_rk_update_options' )){
		update_option('toz_rk_author_id', $_POST['toz_rk_author_id']);
		update_option('toz_rk_post_categories', $_POST['toz_rk_post_categories']);
		//Activate Cron Job When This Happens
	} else  if ( isset($_POST['action']) && ( $_POST['action'] == 'toz_rk_reset_options' )) {
		update_option('toz_rk_access_token', '');
		update_option('toz_rk_auth_code', '');
		update_option('toz_rk_author_id', '');
		update_option('toz_rk_post_categories', '');
	} else {
		//Do Nothing
	}
	
?>
	<div class="wrap">
		<h2>RunKeeper WordPress Activity Feed</h2>
		<?php $toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
		if ( empty($toz_rk_auth_code) ) {  ?>
			<p>Let's authorize RunKeeper: <a href="http://runkeeper.thinkonezero.com"><img src="<?php echo plugins_url( 'includes/images/runkeeper-connect-blue-white.png' , __FILE__ ); ?>" width="200" height="26" alt="Connect to RunKeeper" style="padding-left:10px" /></a></p>
		<?php } else {
			$rkProfile = $toz_rkAPI->doRunkeeperRequest('Profile','Read');
			$rkProfile_array = (array) $rkProfile; ?>
			<h3>Runkeeper Profile</h3>
			<img src="<?php echo $rkProfile_array['normal_picture']; ?>"><p>Name: <?php  echo $rkProfile_array['name']; ?><br />
			Location: <?php echo $rkProfile_array['location']; ?> <br />
			Athlete Type: <?php echo $rkProfile_array['athlete_type']; ?></p>
			<hr />
			<h3>Plugin Settings</h3>
			<p><form method="post" action="">
				<table class="form-table"><tbod>
					<tr valign="top">
						<th scope="row"><label for="toz_rk_author_id">Author ID:</label></th>
						<td>
							<input type="number" name="toz_rk_author_id" value="<?php echo(get_option('toz_rk_author_id')); ?>" class="regular-text code" />
							<span class="description">WordPress Author ID of the author who will make posts.</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="toz_rk_post_categories">Post Categories:</label></th>
						<td>
							<input type="text" name="toz_rk_post_categories" value="<?php echo(get_option('toz_rk_post_categories')); ?>" class="regular-text code" />
							<span class="description">Comma separated list of Post Categories for posts. example: <code>8,3,26</code></span>
						</td>
					</tr>
				</tbody></table>
				<input type="hidden" name="action" value="toz_rk_update_options" />
				<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
			</form></p>
			<p><form method="post" action="">
				<p>Reset (so you can authorize again): 
				<input type="hidden" name="action" value="toz_rk_reset_options" />
				<input type="submit" class="button-primary" value="<?php _e('Reset Options') ?>" /></p>
			</form></p>
			<p><form method="post" action="">
				<p>Import all (1000) of your previous activities: 
				<input type="hidden" name="action" value="toz_rk_import_old" />
				<input type="submit" class="button-primary" value="<?php _e('Import') ?>" /></p>
			</form></p>
			<hr />
			<?php if ( isset($_POST['action']) && ( $_POST['action'] == 'toz_rk_import_old' )) { ?>
				<h3>Historical Import</h3>
				<?php toz_rk_import_old(); ?>
			<?php } else {
				//Do Nothing
			}
		} ?>
	</div>
<?php }

//This sets up the wp-cron function to automatically check for new events and post them.
function toz_rk_schedule_activate() {
	wp_schedule_event(time(), 'hourly', 'toz_rk_schedule_event');
}

//This posts the latest event on a schedule.
function toz_rk_schedule_event() {
	$toz_schedule_rkAPI = new runkeeperAPI(
		CONFIGPATH.'rk-api.yml'	/* api_conf_file */
	);
	if ($toz_schedule_rkAPI->api_created == false) {
		echo 'error '.$toz_schedule_rkAPI->api_last_error; /* api creation problem */
		exit();
	}

	$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
	if ( !empty($toz_rk_auth_code) ) {
		$toz_schedule_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
	}

	//Get the Previous Event
	$import_params = array (
		'pageSize' => '1'
	);
	$rkActivitiesFeedImport = $toz_schedule_rkAPI->doRunkeeperRequest('FitnessActivityFeed','Read', '', '', $import_params);
		if ($rkActivitiesFeedImport) {
			$rkActivitiesFeedImport_array = (array) $rkActivitiesFeedImport;
			foreach ($rkActivitiesFeedImport_array as $rkActivitiesItems) {
				foreach ($rkActivitiesItems as $rkActivitiesItem) {
					$rkActivity_uri = $rkActivitiesItem->uri;
					
					$rkActivity_id = explode('/', $rkActivity_uri);
					$rkActivity_id = $rkActivity_id[2];
					
					if ( $rkActivity_id > get_option('toz_rk_last_event') ) {
						$rkActivity_detailed = $toz_schedule_rkAPI->doRunkeeperRequest('FitnessActivity','Read', '', $rkActivity_uri);
						$rkActivity_detailed_array = (array) $rkActivity_detailed;

						$publish_date = date_create_from_format('*, j M Y H:i:s', $rkActivity_detailed_array['start_time']);
							
						$toz_rk_post_import = array (
							'post_title'    => $rkActivity_detailed_array['type'] . ': ' . $rkActivity_detailed_array['start_time'],
							'post_content'  => $rkActivity_detailed_array['notes'] . '<br /><ul><li>Activity: ' . $rkActivity_detailed_array['type'] . '</li><li>Distance: ' . round($rkActivity_detailed_array['total_distance']*0.00062137, 2) . ' miles</li><li>Duration: ' . date('H:i:s', $rkActivity_detailed_array['duration']) . '</li><li>Calories Burned: ' . $rkActivity_detailed_array['total_calories'] . '</li></ul>',
							'post_date'     => date_format($publish_date, 'Y-m-d H:i:s'), //this is converted activity date
							'post_status'   => 'publish',
							'post_author'   => get_option('toz_rk_author_id'),
							'post_category' => array(get_option('toz_rk_post_categories'))
							);
						$post_id = wp_insert_post( $toz_rk_post_import );
					
						if ($rkActivity_detailed_array['images']['0']) {
							$rkActivity_detailed_array_images = (array) $rkActivity_detailed_array['images']['0'];
							$image_url = $rkActivity_detailed_array_images['uri'];
							toz_rk_featured_image( $image_url, $post_id );
						} else {
							//Do Nothing
						}
						
						update_option('toz_rk_last_event', $rkActivity_id);
						
					} else {
						//Do Nothing
					}
												
				}
			}
		} else {
			echo $toz_schedule_rkAPI->api_last_error;
			print_r($toz_schedule_rkAPI->request_log);
		}
}


//This is how we import all the old posts.
function toz_rk_import_old() {
	$toz_import_rkAPI = new runkeeperAPI(
		CONFIGPATH.'rk-api.yml'	/* api_conf_file */
	);
	if ($toz_import_rkAPI->api_created == false) {
		echo 'error '.$toz_import_rkAPI->api_last_error; /* api creation problem */
		exit();
	}

	$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
	if ( !empty($toz_rk_auth_code) ) {
		$toz_import_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
	}

	//Let's get bajillions (1000) items
	$import_params = array (
		'pageSize' => '1000'
	);
	$rkActivitiesFeedImport = $toz_import_rkAPI->doRunkeeperRequest('FitnessActivityFeed','Read', '', '', $import_params);
		if ($rkActivitiesFeedImport) {
			$rkActivitiesFeedImport_array = (array) $rkActivitiesFeedImport;
			foreach ($rkActivitiesFeedImport_array as $rkActivitiesItems) {
				foreach ($rkActivitiesItems as $rkActivitiesItem) {
					$rkActivity_uri = $rkActivitiesItem->uri;
					$rkActivity_detailed = $toz_import_rkAPI->doRunkeeperRequest('FitnessActivity','Read', '', $rkActivity_uri);
					$rkActivity_detailed_array = (array) $rkActivity_detailed;

					$publish_date = date_create_from_format('*, j M Y H:i:s', $rkActivity_detailed_array['start_time']);
							
					$toz_rk_post_import = array (
						'post_title'    => $rkActivity_detailed_array['type'] . ': ' . $rkActivity_detailed_array['start_time'],
						'post_content'  => $rkActivity_detailed_array['notes'] . '<br /><ul><li>Activity: ' . $rkActivity_detailed_array['type'] . '</li><li>Distance: ' . round($rkActivity_detailed_array['total_distance']*0.00062137, 2) . ' miles</li><li>Duration: ' . date('H:i:s', $rkActivity_detailed_array['duration']) . '</li><li>Calories Burned: ' . $rkActivity_detailed_array['total_calories'] . '</li></ul>',
						'post_date'     => date_format($publish_date, 'Y-m-d H:i:s'), //this is converted activity date
						'post_status'   => 'publish',
						'post_author'   => get_option('toz_rk_author_id'),
						'post_category' => array(get_option('toz_rk_post_categories'))
					);
					$post_id = wp_insert_post( $toz_rk_post_import );
					
					if ($rkActivity_detailed_array['images']['0']) {
						$rkActivity_detailed_array_images = (array) $rkActivity_detailed_array['images']['0'];
						$image_url = $rkActivity_detailed_array_images['uri'];
						toz_rk_featured_image( $image_url, $post_id );
					} else {
						//Do Nothing
					}
					
					toz_rk_import_progress($rkActivity_uri);							
				}
			}
		} else {
			echo $toz_import_rkAPI->api_last_error;
			print_r($toz_import_rkAPI->request_log);
		}
}

//A simple (awful) notifier to tell us what was imported).
function toz_rk_import_progress($rkActivity_uri) {
	echo 'Importing Activity: ' . $rkActivity_uri . '<br />';
}

//Upload Image and Set as Featured
//from: http://wordpress.stackexchange.com/questions/40301/how-do-i-set-a-featured-image-thumbnail-by-image-url-when-using-wp-insert-post)
function toz_rk_featured_image( $image_url, $post_id ) {
	$upload_dir = wp_upload_dir();
	$image_data = file_get_contents($image_url);
	$filename = basename($image_url);
	if(wp_mkdir_p($upload_dir['path'])) {
    	$file = $upload_dir['path'] . '/' . $filename;
    } else {
    	$file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
    	'post_mime_type' => $wp_filetype['type'],
    	'post_title'     => sanitize_file_name($filename),
    	'post_content'   => '',
    	'post_status'    => 'inherit'
    );
    
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    set_post_thumbnail( $post_id, $attach_id );
}