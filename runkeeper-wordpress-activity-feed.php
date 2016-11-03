<?php

/*
Plugin Name: RunKeeper WordPress Activity Feed
Plugin URI: http://runkeeper.thinkonezero.com
Description: A plugin to automatically draft posts of all your Runkeeper Activities.
Author: A. Kai Armstrong
Version: 1.7.4
Author URI: http://www.kaiarmstrong.com
*/

//Setup DB Options
function toz_rk_activate(){
	add_option('toz_rk_access_token', '');
	add_option('toz_rk_auth_code', '');
	add_option('toz_rk_author_id', '');
	add_option('toz_rk_post_categories', '');
	add_option('toz_rk_post_options_notes', '');
	add_option('toz_rk_post_options_type', '');
	add_option('toz_rk_post_options_distance', '');
	add_option('toz_rk_post_options_duration', '');
	add_option('toz_rk_post_options_speed', '');
	add_option('toz_rk_post_options_pace', '');
	add_option('toz_rk_post_options_calories', '');
	add_option('toz_rk_post_options_heartrate', '');
	add_option('toz_rk_post_options_url', '');
	add_option('toz_rk_post_options_time', '');
	add_option('toz_rk_last_event', '0');
	add_option('toz_rk_units', 'standard');
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
define('TOZRKCONFIGPATH', TOZRKPATH.'includes/');

require(YAMLPATH.'lib/sfYamlParser.php');
require(RUNKEEPERAPIPATH.'lib/runkeeperAPI.class.php');

//RunKeeper Widgets
require('runkeeper-wordpress-records-widget.php');

//Admin Interface
function toz_rk_admin() {
	/* API initialization */
	$toz_rkAPI = new runkeeperAPI(
		TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
	);
	if ($toz_rkAPI->api_created == false) {
		echo 'error '.$toz_rkAPI->api_last_error; /* api creation problem */
		exit();
	}

	/* Stores the access values we'll need after we've authorized our account. */
	if ( isset($_GET['code']) && isset($_GET['access_token']) ) {
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
		if ( isset($_POST['toz_rk_author_id']) ) { update_option('toz_rk_author_id', $_POST['toz_rk_author_id']); }
		if ( isset($_POST['toz_rk_post_categories']) ) { update_option('toz_rk_post_categories', $_POST['toz_rk_post_categories']); }
		if ( isset($_POST['toz_rk_post_options_notes']) ) { update_option('toz_rk_post_options_notes', $_POST['toz_rk_post_options_notes']); }
		if ( isset($_POST['toz_rk_post_options_type']) ) { update_option('toz_rk_post_options_type', $_POST['toz_rk_post_options_type']); }
		if ( isset($_POST['toz_rk_post_options_distance']) ) { update_option('toz_rk_post_options_distance', $_POST['toz_rk_post_options_distance']); }
		if ( isset($_POST['toz_rk_post_options_duration']) ) { update_option('toz_rk_post_options_duration', $_POST['toz_rk_post_options_duration']); }
		if ( isset($_POST['toz_rk_post_options_speed']) ) { update_option('toz_rk_post_options_speed', $_POST['toz_rk_post_options_speed']); }
		if ( isset($_POST['toz_rk_post_options_pace']) ) { update_option('toz_rk_post_options_pace', $_POST['toz_rk_post_options_pace']); }
		if ( isset($_POST['toz_rk_post_options_calories']) ) { update_option('toz_rk_post_options_calories', $_POST['toz_rk_post_options_calories']); }
		if ( isset($_POST['toz_rk_post_options_heartrate']) ) { update_option('toz_rk_post_options_heartrate', $_POST['toz_rk_post_options_heartrate']); }
		if ( isset($_POST['toz_rk_post_options_url']) ) { update_option('toz_rk_post_options_url', $_POST['toz_rk_post_options_url']); }
		if ( isset($_POST['toz_rk_post_options_time']) ) { update_option('toz_rk_post_options_time', $_POST['toz_rk_post_options_time']); }
		if ( isset($_POST['toz_rk_units']) ) { update_option('toz_rk_units', $_POST['toz_rk_units']); }
		toz_rk_schedule_activate();
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
			<p>Let's authorize RunKeeper: <a href="http://runkeeper.thinkonezero.com/"><img src="<?php echo plugins_url( 'includes/images/runkeeper-connect-blue-white.png' , __FILE__ ); ?>" width="200" height="26" alt="Connect to RunKeeper" style="padding-left:10px" /></a></p>
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

				<!-- Author Options -->
				<table class="form-table"><tbody>
					<tr valign="top">
						<th scope="row"><label for="toz_rk_author_id">Author ID:</label></th>
						<td>
							<select name="toz_rk_author_id">
								<?php foreach(get_users() as $user):?>
									<option value="<?php echo $user->data->ID;?>" <?php echo (get_option('toz_rk_author_id') == $user->data->ID ? 'selected="selected"' : ''); ?>><?php echo $user->data->display_name;?></option>
								<?php endforeach;?>
							</select>
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

				<!-- Units Table -->
				<table class="form-table"><tbody>
					<tr valign="top">
						<th scope="row"><label for="toz_rk_units">Units:</label></th>
						<td width="200px">
							<input id="rk_units_standard" type="radio" name="toz_rk_units" value="standard" class="code" <?php if ( get_option('toz_rk_units') == 'standard' ) { echo 'checked'; } else { } ?> />
							<label for="rk_units_standard" class="description">Standard</label>
						</td>
						<td>
							<input id="rk_units_metric" type="radio" name="toz_rk_units" value="metric" class="code" <?php if ( get_option('toz_rk_units') == 'metric' ) { echo 'checked'; } else { } ?> />
							<label for="rk_units_metric" class="description">Metric</label;>
						</td>
					</tr>
				</tbody></table>

				<!-- Post Content Options //3 Columns -->
				<table class="form-table"><tbody>
					<tr valign="top">
						<th scope="row"><label for="toz_rk_post_options">Post Options:</label></th>
						<td width="200px">
							<input id="options_activity_notes" type="checkbox" name="toz_rk_post_options_notes" value="true" class="code" <?php if ( get_option('toz_rk_post_options_notes') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_activity_notes">Activity Notes</label>
						</td>
						<td width="200px">
							<input id="options_type" type="checkbox" name="toz_rk_post_options_type" value="true" class="code" <?php if ( get_option('toz_rk_post_options_type') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_type">Activity Type</label>
						</td>
						<td>
							<input id="options_distance" type="checkbox" name="toz_rk_post_options_distance" value="true" class="code" <?php if ( get_option('toz_rk_post_options_distance') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_distance">Total Distance</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"></th>
						<td>
							<input id="options_duration" type="checkbox" name="toz_rk_post_options_duration" value="true" class="code" <?php if ( get_option('toz_rk_post_options_duration') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_duration">Total Duration</label>
						</td>
						<td>
							<input id="options_speed" type="checkbox" name="toz_rk_post_options_speed" value="true" class="code" <?php if ( get_option('toz_rk_post_options_speed') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_speed">Average Speed</label>
						</td>
						<td>
							<input id="options_pace" type="checkbox" name="toz_rk_post_options_pace" value="true" class="code" <?php if ( get_option('toz_rk_post_options_pace') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_pace">Average Pace</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"></th>
						<td>
							<input id="options_calories" type="checkbox" name="toz_rk_post_options_calories" value="true" class="code" <?php if ( get_option('toz_rk_post_options_calories') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_calories">Total Calories</label>
						</td>
						<td>
							<input id="options_heartrate" type="checkbox" name="toz_rk_post_options_heartrate" value="true" class="code" <?php if ( get_option('toz_rk_post_options_heartrate') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_heartrate">Heart Rate</label>
						</td>
						<td>
							<input id="options_url" type="checkbox" name="toz_rk_post_options_url" value="true" class="code" <?php if ( get_option('toz_rk_post_options_url') == true ) { echo 'checked'; } else { } ?> />
							<label class="description" for="options_url">Activity URL</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"></th>
						<td>
							<input id="options_time" type="checkbox" name="toz_rk_post_options_time" value="true" class="code" <?php if ( get_option('toz_rk_post_options_time') == true ) { echo 'checked'; } else { } ?> />
							<span class="description" for="options_time">Start Time</span>
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
			<hr />
			<p><form method="post" action="">
				<p>Import a Single Post based on ID:
				<input type="hidden" name="action" value="toz_rk_import_single" />
				<input type="text" name="rkActivity_id" value="Activity ID" class="regular-text code" />
				<input type="submit" class="button-primary" value="<?php _e('Import Single') ?>" /></p>
			</form></p>
			<hr />
			<p><form method="post" action="">
				<p>Import all (1000) of your previous activities:
				<input type="hidden" name="action" value="toz_rk_import_old" />
				<input type="submit" class="button-primary" value="<?php _e('Import') ?>" /></p>
			</form></p>
			<hr />
			<?php if ( isset($_POST['action']) && ( $_POST['action'] == 'toz_rk_import_old' )) { ?>
				<h3>Historical Import</h3>
				<?php toz_rk_import_old();
			} else if ( isset($_POST['action']) && ( $_POST['action'] == 'toz_rk_import_single' )) { ?>
				<h3>Single Activity Import</h3>
				<?php toz_rk_import_single($_POST['rkActivity_id']);
			} else {
				//DO NOTHING
			}
		} ?>
	</div>
<?php }

//Executes the Schedule Event Function
add_action('toz_rk_schedule', 'toz_rk_schedule_event');

//This sets up the wp-cron function to automatically check for new events and post them.
function toz_rk_schedule_activate() {
	if ( !wp_next_scheduled( 'toz_rk_schedule' ) ) {
		wp_schedule_event(time(), 'hourly', 'toz_rk_schedule');
	} else {
		//Do Nothing
	}
}

//This posts the latest event on a schedule.
function toz_rk_schedule_event() {
	$toz_schedule_rkAPI = new runkeeperAPI(
		TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
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
		foreach ($rkActivitiesFeedImport_array['items'] as $rkActivitiesItem) {
			$rkActivity_uri = $rkActivitiesItem->uri;

			$rkActivity_id = explode('/', $rkActivity_uri);
			$rkActivity_id = $rkActivity_id[2];

			if ( intval($rkActivity_id) > intval(get_option('toz_rk_last_event')) ) {
				$rkActivity_detailed = $toz_schedule_rkAPI->doRunkeeperRequest('FitnessActivity','Read', '', $rkActivity_uri);
				$rkActivity_detailed_array = (array) $rkActivity_detailed;

				toz_rk_post($rkActivity_detailed_array);

				update_option('toz_rk_last_event', $rkActivity_id);

			} else {
				update_option('toz_rk_schedule_result', 'failure: '.time());
			}

		}
	} else {
		echo $toz_schedule_rkAPI->api_last_error;
		print_r($toz_schedule_rkAPI->request_log);
	}
}

//Import a Single Event based on ID
function toz_rk_import_single($rkActivity_id) {
	$toz_import_single_rkAPI = new runkeeperAPI(
		TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
	);
	if ($toz_import_single_rkAPI->api_created == false) {
		echo 'error '.$toz_import_single_rkAPI->api_last_error; /* api creation problem */
		exit();
	}

	$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
	if ( !empty($toz_rk_auth_code) ) {
		$toz_import_single_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
	}

	if ( !empty($rkActivity_id) ) {

		$rkActivity_uri = '/fitnessActivities/' . $rkActivity_id;

		$rkActivity_detailed = $toz_import_single_rkAPI->doRunkeeperRequest('FitnessActivity','Read', '', $rkActivity_uri);
		$rkActivity_detailed_array = (array) $rkActivity_detailed;

		toz_rk_post($rkActivity_detailed_array);

	} else {
		echo $toz_import_single_rkAPI->api_last_error;
		print_r($toz_import_single_rkAPI->request_log);
	}
}

//This is how we import all the old posts.
function toz_rk_import_old() {
	$toz_import_rkAPI = new runkeeperAPI(
		TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
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
		foreach ($rkActivitiesFeedImport_array['items'] as $rkActivitiesItem) {
			$rkActivity_uri = $rkActivitiesItem->uri;
			$rkActivity_detailed = $toz_import_rkAPI->doRunkeeperRequest('FitnessActivity','Read', '', $rkActivity_uri);
			$rkActivity_detailed_array = (array) $rkActivity_detailed;

			toz_rk_post($rkActivity_detailed_array);

			toz_rk_import_progress($rkActivity_uri);

		}
	} else {
		echo $toz_import_rkAPI->api_last_error;
		print_r($toz_import_rkAPI->request_log);
	}
}

function toz_rk_post( $rkActivity_detailed_array ) {

	$publish_date = date_create_from_format('*, j M Y H:i:s', $rkActivity_detailed_array['start_time']);

	$post_import_content = '';

	//Get all the Post Options and build the post_content
	$post_options = array (
		'type'      => get_option('toz_rk_post_options_type'),
		'distance'  => get_option('toz_rk_post_options_distance'),
		'duration'  => get_option('toz_rk_post_options_duration'),
		'speed'     => get_option('toz_rk_post_options_speed'),
		'pace'      => get_option('toz_rk_post_options_pace'),
		'calories'  => get_option('toz_rk_post_options_calories'),
		'heartrate' => get_option('toz_rk_post_options_heartrate'),
		'url'       => get_option('toz_rk_post_options_url'),
		'time'      => get_option('toz_rk_post_options_time')
	);

	$post_options_notes = get_option('toz_rk_post_options_notes');

	if ( !empty($post_options_notes) ) {
		if ( !empty($rkActivity_detailed_array['notes']) ) {
			$post_import_content .= $rkActivity_detailed_array['notes'] . '<br />';
		} else {
			//Do Nothing
		}
	} else {
		//Do Nothing
	}

	if ( !empty($post_options) ) {

		$post_import_content .= '<ul class="rk-list">';

		if ( !empty($post_options['type']) ) {
			if ( !empty($rkActivity_detailed_array['type']) ) {
				$post_import_content .= '<li class="rk-activity">Activity: ' . $rkActivity_detailed_array['type'] . '</li>';
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['distance']) ) {
			if ( !empty($rkActivity_detailed_array['total_distance']) ) {
				if ( get_option('toz_rk_units') == 'standard' ) {
					$post_import_content .= '<li class="rk-distance">Distance: ' . round($rkActivity_detailed_array['total_distance']*0.00062137, 2) . ' mi</li>'; //Meters * Miles
				} else if ( get_option('toz_rk_units') == 'metric' ) {
					$post_import_content .= '<li class="rk-distance">Distance: ' . round($rkActivity_detailed_array['total_distance']/1000, 2) . ' km</li>'; //Meters / KM
				} else {
					$post_import_content .= '<li class="rk-distance">Distance: ' . round($rkActivity_detailed_array['total_distance']*0.00062137, 2) . ' mi</li>'; //Default to Standard
				}
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['duration']) ) {
			if ( !empty($rkActivity_detailed_array['duration']) ) {
				$post_import_content .= '<li class="rk-duration">Duration: ' . date('H:i:s', $rkActivity_detailed_array['duration']) . '</li>';
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['speed']) ) {
			if ( !empty($rkActivity_detailed_array['duration']) && !empty($rkActivity_detailed_array['total_distance']) ) {
				$hms = date('H:i:s', $rkActivity_detailed_array['duration']);
				list($hours, $minutes, $seconds) = explode(":",$hms);
				$total_seconds = $hours * 60 * 60 + $minutes * 60 + $seconds;

				if ( get_option('toz_rk_units') == 'standard' ) {
					$dps = round($rkActivity_detailed_array['total_distance'] * 0.00062137, 2) / $total_seconds;
					$sph = round($dps * 60 * 60, 2);
					$post_import_content .= '<li class="rk-avg-speed">Average Speed: ' . $sph . ' mph</li>';
				} else if ( get_option('toz_rk_units') == 'metric' ) {
					$dps = round($rkActivity_detailed_array['total_distance'] / 1000, 2) / $total_seconds;
					$sph = round($dps * 60 * 60, 2);
					$post_import_content .= '<li class="rk-avg-speed">Average Speed: ' . $sph . ' kmh</li>';
				} else {
					$dps = round($rkActivity_detailed_array['total_distance'] * 0.00062137, 2) / $total_seconds;
					$sph = round($dps * 60 * 60, 2);
					$post_import_content .= '<li class="rk-avg-speed">Average Speed: ' . $sph . ' mph</li>';
				}

			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['pace']) ) {
			if ( !empty($rkActivity_detailed_array['duration']) && !empty($rkActivity_detailed_array['total_distance']) ) {
				$hms = date('H:i:s', $rkActivity_detailed_array['duration']);
				list($hours, $minutes, $seconds) = explode(":",$hms);
				$total_seconds = $hours * 60 * 60 + $minutes * 60 + $seconds;

				if ( get_option('toz_rk_units') == 'standard' ) {
					$pps = $total_seconds / round($rkActivity_detailed_array['total_distance'] * 0.00062137, 2);
					$ppm = date('i:s', $pps);
					$post_import_content .= '<li class="rk-avg-pace">Average Pace: ' . $ppm . ' min/mi</li>';
				} else if ( get_option('toz_rk_units') == 'metric' ) {
					$pps = $total_seconds / round($rkActivity_detailed_array['total_distance'] / 1000, 2);
					$ppm = date('i:s', $pps);
					$post_import_content .= '<li class="rk-avg-pace">Average Pace: ' . $ppm . ' min/km</li>';
				} else {
					$pps = $total_seconds / round($rkActivity_detailed_array['total_distance'] * 0.00062137, 2);
					$ppm = date('i:s', $pps);
					$post_import_content .= '<li class="rk-avg-pace">Average Pace: ' . $ppm . ' min/mi</li>';
				}

			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['calories']) ) {
			if ( !empty($rkActivity_detailed_array['total_calories']) ) {
				$post_import_content .= '<li class="rk-calories">Calories Burned: ' . $rkActivity_detailed_array['total_calories'] . '</li>';
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['heartrate']) ) {
			if ( !empty($rkActivity_detailed_array['average_heart_rate']) ) {
				$post_import_content .= '<li class="rk-heart-rate">Heart Rate: ' . $rkActivity_detailed_array['average_heart_rate'] . '</li>';
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['url']) ) {
			if ( !empty($rkActivity_detailed_array['activity']) ) {
				$post_import_content .= '<li class="rk-activity-link">Activity Link: <a href="' . $rkActivity_detailed_array['activity'] . '">' . $rkActivity_detailed_array['activity'] . '</a></li>';;
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		if ( !empty($post_options['time']) ) {
			if ( !empty($rkActivity_detailed_array['start_time']) ) {
				$post_import_content .= '<li class="rk-start-time">Start Time: ' . $rkActivity_detailed_array['start_time'] . '</li>';
			} else {
				//Do Nothing
			}
		} else {
			//Do Nothing
		}

		$post_import_content .= '</ul>';

	} else {
		//Do Nothing
	}

	$toz_rk_post_import = array (
		'post_title'    => $rkActivity_detailed_array['type'] . ': ' . $rkActivity_detailed_array['start_time'],
		'post_content'  => $post_import_content,
		'post_date'     => date_format($publish_date, 'Y-m-d H:i:s'), //this is converted activity date
		'post_status'   => 'publish',
		'post_author'   => get_option('toz_rk_author_id'),
		'post_category' => array(get_option('toz_rk_post_categories')),
		'tags_input'    => $rkActivity_detailed_array['type']
	);

	$post_id = wp_insert_post( $toz_rk_post_import );

	if (isset($rkActivity_detailed_array['images']['0'])) {
		$rkActivity_detailed_array_images = (array) $rkActivity_detailed_array['images']['0'];
		$image_url = $rkActivity_detailed_array_images['uri'];
		toz_rk_featured_image( $image_url, $post_id );
	} else {
		//Do Nothing
	}
}

//A simple (awful) notifier to tell us what was imported.
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
