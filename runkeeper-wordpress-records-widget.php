<?php

// RunKeeper Records Widget

add_action( 'widgets_init', 'toz_rk_records_widget' );


function toz_rk_records_widget() {
	register_widget( 'toz_rk_records_widget' );
}

class toz_rk_records_widget extends WP_Widget {

	function toz_rk_records_widget() {
		$widget_ops = array( 'classname' => 'toz-rk-records', 'description' => __('A widget that displays your RunKeeper Records', 'toz-rk-records') );
		
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'toz-rk-records' );
		
		$this->WP_Widget( 'toz-rk-records', __('RunKeeper Records Widget', 'toz-rk-records'), $widget_ops, $control_ops );
	}
	
	function widget( $args, $instance ) {
	
		extract( $args );

		//Our variables from the widget settings.
		$title = apply_filters('widget_title', $instance['title'] );
		$activity_type = $instance['activity'];

		echo $before_widget;

		// Display the widget title 
		if ( $title )
			echo $before_title . $title . $after_title;
			
		$toz_records_content = get_transient( 'toz-records-content-' . $activity_type );
		if ( empty( $toz_records_content) ) {

			$toz_widget_rkAPI = new runkeeperAPI(
				TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
			);
			if ($toz_widget_rkAPI->api_created == false) {
				echo 'error '.$toz_widget_rkAPI->api_last_error; /* api creation problem */
				exit();
			}

			$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
			if ( !empty($toz_rk_auth_code) ) {
				$toz_widget_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
			}
			
			//Get Unit Options
			if ( get_option('toz_rk_units') == 'standard' ) {
				$toz_rk_units = 'mi';
			} else if ( get_option('toz_rk_units') == 'metric' ) {
				$toz_rk_units = 'km';
			} else {
				$toz_rk_units = 'mi';
			}
		
			$rkRecordsFeed = $toz_widget_rkAPI->doRunkeeperRequest('Records','Read');
			if ($rkRecordsFeed) {
				$i = 0;
				while ( isset($rkRecordsFeed[$i]->activity_type) != NULL) {
					if ($rkRecordsFeed[$i]->activity_type == $activity_type) {
						$x = 0;
						foreach ($rkRecordsFeed[$i] as $rkRecordsActivity) {
							$toz_records_content = '<ul>';
							$toz_records_content .= '<li>Best Activity: '.round($rkRecordsActivity[0]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>Best Week: '.round($rkRecordsActivity[1]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>Last Week: '.round($rkRecordsActivity[2]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>This Week: '.round($rkRecordsActivity[3]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>Best Month: '.round($rkRecordsActivity[4]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>Last Month: '.round($rkRecordsActivity[5]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '<li>This Month: '.round($rkRecordsActivity[6]->value, 2).' ' . $toz_rk_units . '</li>';
							$toz_records_content .= '</ul>';
					
							//Weird "bug" with loop executing twice... this stops that.
							if ($x == 0) {
								break;
							}
						}
					}
					$i++;
				}
				set_transient( 'toz-records-content-' . $activity_type, $toz_records_content, HOUR_IN_SECONDS );
			}
		}
		
		echo $toz_records_content;

		echo $after_widget;
	}

	//Update the widget 
	 
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		//Strip tags from title and name to remove HTML 
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['activity'] = strip_tags( $new_instance['activity'] );

		return $instance;
	}

	
	function form( $instance ) {
	
		$rkActivities_array = get_transient( 'rkActivities-array' );
		if ( empty( $rkActivities_array) ) {
	
			$toz_widget_form_rkAPI = new runkeeperAPI(
				TOZRKCONFIGPATH.'rk-api.yml'	/* api_conf_file */
			);
			if ($toz_widget_form_rkAPI->api_created == false) {
				echo 'error '.$toz_widget_form_rkAPI->api_last_error; /* api creation problem */
				exit();
			}

			$toz_rk_auth_code = get_option( 'toz_rk_auth_code' );
			if ( !empty($toz_rk_auth_code) ) {
				$toz_widget_form_rkAPI->setRunkeeperToken( get_option( 'toz_rk_access_token' ) );
			}
		
			$rkRecordsFeed = $toz_widget_form_rkAPI->doRunkeeperRequest('Records','Read');
			if ($rkRecordsFeed) {
				$i = 0;
				$rkActivities_array = array();
				while ($rkRecordsFeed[$i]->activity_type != NULL) {
					$x=0;
					foreach ($rkRecordsFeed[$i] as $rkRecordsActivity) {
						array_push($rkActivities_array, $rkRecordsFeed[$i]->activity_type);
					
						//Weird "bug" with loop executing twice... this stops that.
						if ($x == 0) {
							break;
						}
					}
					$i++;
				}
			}
		
			set_transient( 'rkActivities-array', $rkActivities_array, DAY_IN_SECONDS );
		
		} else {
			//Do Nothing
		}

		//Set up some default widget settings.
		$defaults = array( 'title' => __('RunKeeper Records', 'title'), 'activity' => __('Activity Type', 'activity') );
		$instance = wp_parse_args( (array) $instance, $defaults ); 
		$current_activity = $instance['activity']; ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'title'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'activity' ); ?>"><?php _e('Activity Type:', 'activity'); ?></label>
			<select id="<?php echo $this->get_field_id('activity'); ?>" name="<?php echo $this->get_field_name('activity'); ?>" style="width:100%;">
				<option value=""></option>
				<?php foreach($rkActivities_array as $activity_type) {
					$instance['activity'] = $activity_type;
					echo '<option value="' . $instance['activity'] . '"' . selected( $instance['activity'], $current_activity, false ) . '>' . $instance['activity'] . '</option>';
				} ?>
			</select>
		</p>

	<?php
	}
}