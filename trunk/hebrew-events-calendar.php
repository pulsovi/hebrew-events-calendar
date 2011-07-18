<?php
/*
Plugin Name: Hebrew Events Calendar (v.4)
Plugin URI: http://wordpress.org/extend/plugins/hebrew-events-calendar/
Description: A hebrew friendly events calendar
Version: 0.4
Author: Yitzchak ben Avraham
Author URI: http://wordpress.org/extend/plugins/profile/yitzi
License: GPL2
*/

require_once 'scb-hooks.php';
require_once 'yba-shortcodes.php';

$hec_weekdays = array(
	1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday',
	-1 => 'Rishon', -2 => 'Sheni', -3 => 'Shlishi', -4 => "Revi'i", -5 => 'Chamishi', -6 => 'Shishi', -7 => 'Shabbat');

$hec_months = array(
	1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
	-1 => 'Tishri', -2 => 'Cheshvan', -3 => 'Kislev', -4 => 'Tevet', -5 => 'Shevat', -6 => 'Adar Aleph', -7 => 'Adar', -8 => 'Nisan', -9 => 'Iyar', -10 => 'Sivan', -11 => 'Tammuz', -12 => 'Av', -13 => 'Elul');

$hec_year_labels = array(353 => '-Cheit', 354 => '-Kaf', 355 => '-Shin', 383 => '-Cheit', 384 => '-Kaf', 385 => '-Shin');
$hec_rosh_hashanah_labels = array(1 => '-Beit', 2 => '-Gimmel', 4 => '-Hei', 6 => '-Zayin');
$hec_year_types = array(
	'Mem-Beit-Cheit', 'Mem-Beit-Shin', 'Mem-Gimel-Kaf', 'Mem-Hei-Cheit', 'Mem-Hei-Shin', 'Mem-Zayin-Cheit', 'Mem-Zayin-Shin',
	'Pei-Beit-Cheit', 'Pei-Beit-Shin', 'Pei-Gimel-Kaf', 'Pei-Hei-Kaf', 'Pei-Hei-Shin', 'Pei-Zayin-Cheit', 'Pei-Zayin-Shin');

function my_rewrite_flush() {
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'my_rewrite_flush');


class hec_hooks
{
	static function init() {
		global $hec_options, $wp_rewrite, $wp_query;

		date_default_timezone_set(get_option('timezone_string'));
		
		$hec_old_options = get_option('hec_options', array());
		$hec_options = array_merge(
		array(
				'latitude' => ini_get('date.default_latitude'),
				'longitude' => ini_get('date.default_longitude'),
				'sunrise_zenith' => ini_get('date.sunrise_zenith'),
				'sunset_zenith' => ini_get('date.sunset_zenith'),
				'post_types' => array('page', 'post', 'hec_event'),
				'ics_subscription_text' => 'Subscribe to calendar using <a href="%3$s">Webcal (Outlook, Apple iCal, etc.)</a> or <a title="Add to Google Calendar" href="http://www.google.com/calendar/render?cid=%2$s">Google Calendar</a>.',
				'occurence_limit' => 10,
				'day_limit' => 390,
				'ics_permalink' => 'calendar.ics',
				'ics_title' => 'My Events',
				'ics_description' => 'Description of my events.'),
		$hec_old_options);
	
		/*if (!isset($hec_old_options['version'])) {
			$old_query = $wp_query;
			$wp_query = new WP_Query( array ( 'post_type' => $hec_options['post_types'], 'meta_query' => array( array( 'key' => '_hec_event' ) ) ) );
			
			while (have_posts()) {
				the_post();
				//echo $post->ID;
				$event = get_post_meta($post->ID, '_hec_event', true);
				if ($event != '') {
					foreach ($event as $k => $v)
					if (substr($k, 0, 6) == 'notes_')
					add_post_meta($post->ID, '_hec_notes', vsprintf('%3$04d-%1$02d-%2$02d', explode('/', jdtogregorian(substr($k, 6)))) . ',' . $v);
					elseif (substr($k, 0, 5) == 'hide_')
					add_post_meta($post->ID, '_hec_hide', vsprintf('%3$04d-%1$02d-%2$02d', explode('/', jdtogregorian(substr($k, 5)))));
				}
			}
			
			// Reset Post Data
			$wp_query = $old_query;
			wp_reset_postdata();
			
			$hec_old_options['version'] = 0.4;
		}*/
		
		if ($hec_old_options != $hec_options)
		update_option('hec_options', $hec_options);
	
		if (!isset($wp_rewrite->rules, $hec_options['ics_permalink']))
		$wp_rewrite->flush_rules();
		
		if (in_array('hec_event', $hec_options['post_types']))
		{
			register_taxonomy(
				'hec_event_tag',
				'hec_event',
				array(
						'label' => __( 'Event Tags' ),
						'sort' => true,
						'args' => array( 'orderby' => 'term_order' ),
						'rewrite' => array( 'slug' => 'tag' )));				
			
			register_post_type(
				'hec_event',
				array(
					'label' => 'Events',
					'description' => 'Hebrew event',
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => true,
					'capability_type' => 'post',
					'hierarchical' => false,
					'rewrite' => array( 'slug' => 'event', 'with_front' => false ),
					'query_var' => true,
					'supports' => array('title','editor','excerpt','trackbacks','custom-fields','comments','revisions','thumbnail','author'),
					'taxonomies' => array('event_tag'),
					'labels' => array(
						'name' => 'Events',
						'singular_name' => 'Event',
						'menu_name' => 'Events',
						'add_new' => 'Add Event',
						'add_new_item' => 'Add New Event',
						'edit' => 'Edit',
						'edit_item' => 'Edit Event',
						'new_item' => 'New Event',
						'view' => 'View Event',
						'view_item' => 'View Event',
						'search_items' => 'Search Events',
						'not_found' => 'No Events Found',
						'not_found_in_trash' => 'No Events Found in Trash',
						'parent' => 'Parent Event')));
			
			//flush_rewrite_rules();
		}
	}
	
	static function admin_init() { // whitelist options
		global $wpdb, $hec_dashboard_days, $current_user;
		
		$hec_dashboard_days = get_user_meta($current_user, $wpdb->prefix . 'hec_dashboard_days', true);
		if ($hec_dashboard_days == false) $hec_dashboard_days = 7;
		/*register_setting('hec_options', 'latitude');
		register_setting('hec_options', 'longitude');
		register_setting('hec_options', 'sunset_zenith');
		register_setting('hec_options', 'sunrise_zenith');
		register_setting('hec_options', 'occurence_limit');
		register_setting('hec_options', 'day_limit');
		register_setting('hec_options', 'post_types');
		register_setting('hec_options', 'calendar_ics');*/
		//register_setting('hec_edit_event', 'hec_duration');
		register_setting('hec_options', 'hec_options', array( 'hec_options_page', 'callback_sanitize_options' ) );
		add_settings_section('hec_settings', 'Main Settings', array( 'hec_options_page', 'callback_settings_section' ), 'hec_options');
		//add_settings_field('hec_latitude', 'Latitude', 'hec_latitude_field', 'hec_options', 'hec_settings');  
		add_settings_field('hec_latitude', 'Latitude', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'latitude', 'label_for' => 'hec_latitude'));
		add_settings_field('hec_longitude', 'Longitude', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'longitude', 'label_for' => 'hec_longitude'));  
		add_settings_field('hec_sunset_zenith', 'Sunset Zenith', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'sunset_zenith', 'label_for' => 'hec_sunset_zenith'));  
		add_settings_field('hec_sunrise_zenith', 'Sunrise Zenith', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'sunrise_zenith', 'label_for' => 'hec_sunrise_zenith'));  
		add_settings_field('hec_occurence_limit', 'Occurence Limit', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'occurence_limit', 'label_for' => 'hec_occurence_limit'));  
		add_settings_field('hec_day_limit', 'Day Limit', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_settings', array('name' => 'day_limit', 'label_for' => 'hec_day_limit'));  
		add_settings_section('hec_ics', 'ICS Feed Settings', array( 'hec_options_page', 'callback_ics_section' ), 'hec_options');
		add_settings_field('hec_ics_permalink', 'Permalink', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_ics', array('name' => 'ics_permalink', 'label_for' => 'hec_ics_permalink'));  
		add_settings_field('hec_ics_title', 'Title', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_ics', array('name' => 'ics_title', 'label_for' => 'hec_ics_title'));  
		add_settings_field('hec_ics_description', 'Description', array( 'hec_options_page', 'callback_text_settings_field' ), 'hec_options', 'hec_ics', array('name' => 'ics_description', 'label_for' => 'hec_ics_description'));  
		add_settings_field('hec_ics_subscription_text', 'Subscription Text', array( 'hec_options_page', 'callback_textarea_settings_field' ), 'hec_options', 'hec_ics', array('name' => 'ics_subscription_text', 'label_for' => 'hec_ics_subscription_text'));  
		//add_settings_field('hec_post_types', 'Post Types', 'hec_sunset_zenith_field', 'hec_options', 'hec_settings');  
		add_settings_section('hec_post_types', 'Post Types', array( 'hec_options_page', 'callback_post_types_section' ), 'hec_options');
		foreach (array_merge(array('hec_event'), get_post_types(array('public' => 1, 'show_ui' => 1))) as $post_type)
			add_settings_field('hec_' . $post_type, ($post_type == 'hec_event') ? 'Event (Custom Post Type for Hebrew Events)' : get_post_type_object($post_type)->labels->name, array( 'hec_options_page', 'callback_checkbox_settings_field' ), 'hec_options', 'hec_post_types', array('name' => $post_type, 'label_for' => 'hec_' . $post_type));  
	}
	
	static function admin_menu() {
		add_options_page('Hebrew Event Options', 'Hebrew Events Calendar', 'manage_options', 'hec_options', array( 'hec_options_page', 'callback_options_page' ) );
	}
	
	static function wp_print_styles() {
		$myStyleUrl = WP_PLUGIN_URL . '/hebrew-events-calendar/style.css';
		$myStyleFile = WP_PLUGIN_DIR . '/hebrew-events-calendar/style.css';
		if ( file_exists($myStyleFile) ) {
			wp_register_style('hec_stylesheet', $myStyleUrl);
			wp_enqueue_style( 'hec_stylesheet');
		}		
	}
	
	static function wp_head() {
		global $hec_options;
		echo '<link rel="alternate" type="text/calendar" title="ICS Calendar" href="' . home_url($hec_options['ics_permalink']) . '"/>';
	}
	
	static function save_post( $post_ID ) {
		global $hec_year_types, $hec_options;
	
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	
		if (!wp_verify_nonce( $_REQUEST['hec_event_mb_nonce'], 'hec_update_event' )) return;
		
		delete_post_meta($post_ID, '_hec_hide');
		delete_post_meta($post_ID, '_hec_notes');
	
		$data = get_post_meta($post_ID, '_hec_event', true);
		$old_event = ($data == '') ? array() : $data;
		$event = array();
		
		if ($_REQUEST['hec_occurence_limit'] != '' && $_REQUEST['hec_occurence_limit'] != $hec_options['occurence_limit']) $event['occurence_limit'] = (int)$_REQUEST['hec_occurence_limit'];
		if ($_REQUEST['hec_day_limit'] != '' && $_REQUEST['hec_day_limit'] != $hec_options['day_limit']) $event['day_limit'] = (int)$_REQUEST['hec_day_limit'];
	
		foreach ($_REQUEST['hec_settings'] as $id => $settings) {
			
			$result = array();
			
			if ($settings['start_date'] != '') $result['start_date'] = unixtojd(strtotime($settings['start_date']));
			if ($settings['stop_date'] != '') $result['stop_date'] = unixtojd(strtotime($settings['stop_date']));
			if ($settings['weekday'] != '') $result['weekday'] = (int)$settings['weekday'];
			if ($settings['week'] != '') $result['week'] = (int)$settings['week'];
			if ($settings['day'] != '') $result['day'] = (int)$settings['day'];
			if ($settings['month'] != '') $result['month'] = (int)$settings['month'];
			if ($settings['year'] != '') $result['year'] = (int)$settings['year'];
			if ($settings['latitude'] != '') $result['latitude'] = (float)$settings['latitude'];
			if ($settings['longitude'] != '') $result['longitude'] = (float)$settings['longitude'];
			if ($settings['sunset_zenith'] != '') $result['sunset_zenith'] = (float)$settings['sunset_zenith'];
			if ($settings['sunrise_zenith'] != '') $result['sunrise_zenith'] = (float)$settings['sunrise_zenith'];
			if (isset($settings['sunrise'])) $result['sunrise'] = 1;
	
			$t = rtrim(trim(strtoupper($settings['time'])), 'M');
			if ($t != '')
			{
				if (substr($t, -1) != 'A' && substr($t, -1) != 'P') $result['time_offset'] = true;
				$parts = explode(':', rtrim($t, 'AP'));
				$result['time'] = ($result['time_offset']) ?
				((count($parts) == 1) ? (int)$parts[0] : ((int)$parts[0]*60+(((int)$parts[0] < 0) ? -(int)$parts[1] : (int)$parts[1]))) :
				(((count($parts) == 1) ? (int)$parts[0]*60 : ((int)$parts[0]*60+(int)$parts[1])) + ((substr($t, -1) == 'P') ? 720 : 0));
				if (substr($t,0,2) == '-0') $result['time'] = -$result['time'];
			}
	
			$t = trim($settings['duration']);
			if ($t != '')
			{
				$p = explode(':', $t);
				if (count($p) == 1)
				{
					$p = explode(' ', $p[0]);
					$result['duration_days'] = (int)$p[0];
					if (count($p) > 1) $result['duration_minutes'] = 60*(int)$p[1];
				}
				else
				{
					$result['duration_minutes'] = (int)$p[1];
					$p = explode(' ', $p[0]);
					if (count($p) > 1) $result['duration_days'] = (int)$p[0];
					$result['duration_minutes'] += 60*(int)$p[(count($p) == 1) ? 0 : 1];
				}
			}
	
			foreach ($hec_year_types as $year_type)
			{
				$alt_year_type = str_replace('-', '_', $year_type);
				if ($settings['encoded_day'][$alt_year_type] != '') $result['day_' . $year_type] = (int)$settings['encoded_day'][$alt_year_type];
				if ($settings['encoded_month'][$alt_year_type] != '') $result['month_' . $year_type] = (int)$settings['encoded_month'][$alt_year_type];
			}
			
			if (isset($settings['notes']))
			{
				$show = (isset($settings['show'])) ? $settings['show'] : array();
				foreach ($settings['notes'] as $jd => $notes)
				{
					if (!isset($show[$jd])) add_post_meta($post_ID, '_hec_hide', $jd);
					if ($notes != '') add_post_meta($post_ID, '_hec_notes', $jd . ',' . $notes);
				}
			}
			
			if (count($result) != 0)
			{
				if (!isset($event['settings'])) $event['settings'] = array();
				$event['settings'][$id] = $result;
			}
		}
	
		if (empty($event))
			delete_post_meta($post_ID, '_hec_event');
		else
			update_post_meta($post_ID, '_hec_event', $event);
		
		delete_post_meta($post_ID, '_hec_calculation');
		delete_post_meta($post_ID, '_hec_start');
		delete_post_meta($post_ID, '_hec_stop');
	
		return $post_ID;
	}
	
	static function add_meta_boxes() {
		global $hec_options;
		foreach ($hec_options['post_types'] as $post_type) {
			add_meta_box('hec-event-mb', 'Hebrew Event', array( 'hec_metaboxes', 'callback_event_metabox' ), $post_type, 'normal');
			//add_meta_box('hec-related-mb', 'Related Hebrew Events', array( 'hec_metaboxes', 'callback_related_metabox' ), $post_type, 'side');
			//add_meta_box('hec-occurences-mb', 'Hebrew Event Occurences', array( 'hec_metaboxes', 'callback_occurences_metabox' ), $post_type, 'normal');
		}
	}

	static function widgets_init() {
		return register_widget("hec_events_widget");
	}
	
	static function wp_dashboard_setup() {
		if ( current_user_can( 'edit_posts') ) 
			wp_add_dashboard_widget('hec_dashboard_widget', 'Hebrew Events', array( 'hec_dashboard_widget', 'callback' ), array( 'hec_dashboard_widget', 'control_callback' ) );
	}

	static function the_content($content)
	{
		global $occurence_range, $hec_options, $post, $wp_query;//, $hec_occurence_limit, $hec_day_limit;	
	
		if (is_singular() && in_array(get_post_type(), $hec_options['post_types'])) {
			$event = get_post_meta(get_the_ID(), '_hec_event', true);
			if ($event != '') {
				$occurence_limit = (isset($event['occurence_limit'])) ? $event['occurence_limit'] : $hec_options['occurence_limit'];
				if ($occurence_limit > 0) {
					$old_query = $wp_query;
					$wp_query = new WP_Query(
							array('post__in' => array($post->ID),
							'post_type' => 'any',
							'hec_date_time' => array(hec::sql_date_time(), hec::sql_date_time((isset($event['day_limit'])) ? $event['day_limit'] : $hec_options['day_limit'])),
							'posts_per_page' => $occurence_limit) );
							
					if (have_posts()) {
					
						$content .= '<h3>Upcoming Occurences</h3>';//<table><tr><th>Date</th><th>Time</th><th>Notes</th></tr>';

						$content .= hec::get_the_event_list(null, null, null, false, false);
						
						/*while (have_posts())  {
							the_post();
							$content .= '<tr><td>' . hec::date(strtotime($post->hec_start)) . '</td><td>' . hec::time(strtotime($post->hec_start)) . '</td><td>' .
								$post->hec_notes . '</td></tr>';
						}
						
						$content .= '</table>';*/
						
						
					}
					
					$wp_query = $old_query;
					wp_reset_query();
					
					return $content;
				}
			}
		}
		
		return $content;
	}

	static function query_vars($vars) {
		// add the option for the ICS feed
		$vars[] = 'hec_ics';
		$vars[] = 'hec_id';
		$vars[] = 'hec_show_all';
		$vars[] = 'hec_date_time';
		$vars[] = 'hec_range';
		//$vars[] = 'hec_month';
		//$vars[] = 'hec_year';
		return $vars;
	}
	
	/*static function posts_request($query) {
		echo "<p>$query</p>";
		return $query;
	}*/
	
	/*static function pre_get_posts($wp_query) {
		if ($wp_query->get('orderby')) {
			$count = 0;
			$orderby = preg_replace('/\bhec_start\b/', 'meta_value', $wp_query->get('orderby'), -1, $count);
			if ($count > 0) {
				$wp_query->set('orderby', $orderby);
				$wp_query->set('meta_key', '_hec_start');
			}
		}
	}*/
	
	/* @priority: 8 */
	static function posts_clauses($clauses, $wp_query) {
		global $wpdb;
		if ($datetime = $wp_query->get('hec_date_time')) {
			//echo $datetime[0] . ' ' . $datetime[1];
		
			if (is_string($datetime)) $datetime = explode(',', $datetime);
			
			//echo unixtojd(strtotime($datetime[0])) . '  '. unixtojd(strtotime($datetime[1]));
			//echo date("Y-m-d H:i:s", jdtounix(unixtojd(strtotime($datetime[0])))) . '  '. date("Y-m-d H:i:s", jdtounix(unixtojd(strtotime($datetime[1]))));
			hec::calculate_all(unixtojd(strtotime($datetime[0])), unixtojd(strtotime($datetime[1])));
			
			$clauses['distinct'] = '';
			$clauses['fields'] .= ", LEFT(mt_start.meta_value, 13) AS hec_id, SUBSTR(mt_start.meta_value, 15) AS hec_start, SUBSTR(mt_notes.meta_value, 25) AS hec_notes, SUBSTR(mt_stop.meta_value, 25) AS hec_stop, mt_hide.meta_value AS hec_hide";
			$clauses['join'] .= "
				INNER JOIN {$wpdb->postmeta} AS mt_start ON ({$wpdb->posts}.ID = mt_start.post_id)
				LEFT JOIN {$wpdb->postmeta} AS mt_notes ON (mt_start.post_id = mt_notes.post_id AND mt_notes.meta_key = '_hec_notes' AND LEFT(mt_notes.meta_value, 24) = LEFT(mt_start.meta_value, 24))
				LEFT JOIN {$wpdb->postmeta} AS mt_stop ON (mt_start.post_id = mt_stop.post_id AND mt_stop.meta_key = '_hec_stop' AND LEFT(mt_stop.meta_value, 24) =  LEFT(mt_start.meta_value, 24))
				LEFT JOIN {$wpdb->postmeta} AS mt_hide ON (mt_start.post_id = mt_hide.post_id AND mt_hide.meta_key = '_hec_hide' AND LEFT(mt_hide.meta_value, 24) =  LEFT(mt_start.meta_value, 24))";
			$clauses['where'] .= " AND mt_start.meta_key = '_hec_start'";
			if ($wp_query->get('hec_id'))
				$clauses['where'] .= " AND LEFT(mt_start.meta_value, 13) = '" . $wp_query->get('hec_id') . "'";
			$clauses['where'] .= ($wp_query->get('hec_range')) ?
				" AND ((mt_stop.meta_key IS NULL AND mt_start.meta_key = '_hec_start' AND CAST(SUBSTR(mt_start.meta_value, 15) AS DATETIME) BETWEEN '{$datetime[0]}' AND '{$datetime[1]}') OR (CAST(SUBSTR(mt_start.meta_value, 15) AS DATETIME) <= '{$datetime[1]}' AND CAST(SUBSTR(mt_stop.meta_value, 25) AS DATETIME) >= '{$datetime[0]}'))" :
				" AND CAST(SUBSTR(mt_start.meta_value, 15) AS DATETIME) BETWEEN '{$datetime[0]}' AND '{$datetime[1]}'";
			if (!$wp_query->get('hec_show_all'))
				$clauses['where'] .= " AND mt_hide.meta_key IS NULL";
			$clauses['groupby'] = "SUBSTR(mt_start.meta_value, 15), {$wpdb->posts}.post_title";
			$clauses['orderby'] = 'SUBSTR(mt_start.meta_value, 15)';
		}
		
		return $clauses;
	}	

	static private function encode_text_ics($text)
	{
		return str_replace(',','\,', str_replace(';', '\;', str_replace("\n", '\n', str_replace("\\", '\\', html_entity_decode($text, ENT_QUOTES, 'UTF-8')))));
	}
	
	static function template_redirect() {
		global $hec_options, $post, $wp_query;
		if (get_query_var('hec_ics')) {
			header('Content-type: text/calendar');
			echo "BEGIN:VCALENDAR\n";
			echo "VERSION:2.0\n";
			echo "CALSCALE:GREGORIAN\n";
			echo "X-WR-CALNAME;CHARSET=UTF-8:" . self::encode_text_ics($hec_options['ics_title']) . "\n";
			echo "X-WR-CALDESC;CHARSET=UTF-8:" . self::encode_text_ics($hec_options['ics_description']) . "\n";
			echo "X-WR-TIMEZONE:" . get_option('timezone_string') . "\n";
			echo "X-PUBLISHED-TTL:PT360M\n";

			$old_query = $wp_query;
			$wp_query = new WP_Query(
				array(
					'post_type' => $hec_options['post_types'],
					'hec_date_time' => array(hec::sql_date_time(), hec::sql_date_time($hec_options['day_limit'])),
					'nopaging' => true) );
					
			while (have_posts())  {
				the_post();
				echo "BEGIN:VEVENT\n";
				echo "UID:" . $post->ID . '-' . substr($post->hec_start, 0, 10) . "\n";
				echo gmstrftime("DTSTART:%Y%m%dT%H%M00Z\n", strtotime($post->hec_start));
				if (!is_null($post->hec_stop)) echo gmstrftime("DTEND:%Y%m%dT%H%M00Z\n", strtotime($post->hec_stop));
				echo "SUMMARY;CHARSET=UTF-8:" . self::encode_text_ics(hec::get_the_summary()) . "\n";
				echo "DESCRIPTION;CHARSET=UTF-8:" . self::encode_text_ics(hec::get_the_description()) . "\n";
				echo "URL:" . self::encode_text_ics(get_permalink($post->ID)) . "\n";
				if (has_post_thumbnail())
					echo "ATTACH:" . home_url(wp_get_attachment_thumb_url( get_post_thumbnail_id())) . "\n";
				echo "END:VEVENT\n";
			}
	
			/*foreach (hec::get_occurences()/*unixtojd(), $hec_options['day_limit']) *\/as $occurence)
			{
				echo "BEGIN:VEVENT\n";
				echo "UID:" . $occurence->post_id . '-' . unixtojd($occurence->start) . "\n";
				echo gmstrftime("DTSTART:%Y%m%dT%H%M00Z\n", $occurence->start);
				if (!is_null($occurence->stop)) echo gmstrftime("DTEND:%Y%m%dT%H%M00Z\n", $occurence->stop);
				echo "SUMMARY;CHARSET=UTF-8:" . self::encode_text_ics($occurence->title) . "\n";
				if (!is_null($occurence->description) && !is_null($occurence->notes))
					echo "DESCRIPTION;CHARSET=UTF-8:" . self::encode_text_ics($occurence->description . "\n\n" . $occurence->notes) . "\n";
				else if (!is_null($occurence->description))
					echo "DESCRIPTION;CHARSET=UTF-8:" . self::encode_text_ics($occurence->description) . "\n";
				else if (!is_null($occurence->notes)) echo "DESCRIPTION:" . self::encode_text_ics($occurence->notes) . "\n";
				echo "URL:" . self::encode_text_ics(get_permalink($occurence->post_id)) . "\n";
				echo "END:VEVENT\n";
			}*/
	
			echo "END:VCALENDAR\n";
			
			$wp_query = $old_query;
			wp_reset_postdata();
			
			exit();
		}
	}
	
	static function update_option_hec_options($old, $new) {
		global $hec_options, $wp_rewrite;
		$hec_options = $new;
		// if hec_options has been updated then flush the permalinks in case ics_permalink changed
		$wp_rewrite->flush_rules();
	}
	
	static function generate_rewrite_rules( $wp_rewrite ) {
		global $hec_options;
		// set the permalink the ICS feed
		$wp_rewrite->rules = array($hec_options['ics_permalink'] => 'index.php?hec_ics=all') + $wp_rewrite->rules;
	}
	
	
	
}

class hec_shortcodes {
	
	static function agenda($atts, $content = null) {
		global $wp_query, $hec_options;
		
		extract(
			shortcode_atts(
				array(
					'start' => hec::sql_date(),
					'days' => 7,
					'tags' => null,
					'start_format' => null,
					'stop_format' => null,
					 'multiday_stop_format' => null),
				$atts));
		
		$old_query = $wp_query;
		$wp_query = new WP_Query(
		array(
			'post_type' => $hec_options['post_types'],
			'hec_date_time' => array($start . ' 00:00:00', hec::sql_date($days, strtotime($start)) . ' 23:59:59'),
			'nopaging' => true) );
		
		if (have_posts())
			$r = hec::get_the_event_list($start_format, $stop_format, $multiday_stop_format);
		else
			'<p>No events.</p>';
		
		$wp_query = $old_query;
		wp_reset_postdata();
		
		return $r;		
	}
	
	static function calendar($atts, $content = null) {
		global $hec_months, $post, $hec_options, $wp_query, $hec_weekdays;
		
		$start_of_week = get_option('start_of_week');
		//echo get_option('timezone_string') . ' ' . ini_get('date.timezone');
		
		date_default_timezone_set(get_option('timezone_string'));
		
		$time_format = get_option('time_format');
		$date_time_format = get_option('date_format') . '; ' . $time_format;
		
		$d = getdate();
		$month = $d['mon'];
		$year = $d['year'];
		extract(shortcode_atts(array('month' => isset($_REQUEST['hec_month']) ? $_REQUEST['hec_month'] : $d['mon'], 'year' => isset($_REQUEST['hec_year']) ? $_REQUEST['hec_year'] : $d['year']), $atts));
	
		$today = unixtojd();
		$md = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$jd = gregoriantojd($month, 1, $year);
		$dw = jddayofweek($jd);
		$weeks = ceil(($md + $dw) / 7);
		$jd -= $dw;
	
		/*$r = "<table style=\"border-style:none;\"><tr><td style=\"border-style:none;width:5%;\"><a rel=\"nofollow\" href=\"?hec_month=" . ((($month+10) % 12) + 1) . "&hec_year=" .
		(($month == 1) ? $year-1 : $year) . "\">&lt;&lt; Previous Month</a></td><td style=\"border-style:none;width:5%;\"><h3 style=\"text-align:center;\">$hec_months[$month] $year</h3></td><td style=\"border-style:none;text-align:right;width:5%;\"><a rel=\"nofollow\" href=\"?hec_month=" .
		(($month % 12) + 1) . "&hec_year=" . (($month == 12) ? $year+1 : $year) . "\">Next Month &gt;&gt;</a></td></tr></table>";
		*/
		$r .= '<table class="hec-calendar"><caption>';
		if ($today - $jd < $hec_options['day_limit'])
			$r .= "<a class=\"previous-month\" rel=\"nofollow\" href=\"?hec_month=" . ((($month+10) % 12) + 1) . "&hec_year=" . (($month == 1) ? $year-1 : $year) . "\">&lt;&lt; Previous Month</a> ";
		$r .= $hec_months[$month] . ' ' .$year;
		if ($jd + $md - $today < $hec_options['day_limit'])
			$r .= "<a class=\"next-month\" rel=\"nofollow\" href=\"?hec_month=" . (($month % 12) + 1) . "&hec_year=" . (($month == 12) ? $year+1 : $year) . "\">Next Month &gt;&gt;</a>";
		$r .= '</caption><thead><tr>';
		
		for ($i = 0, $d = $start_of_week+1; $i < 7; $i++, $d = ($d % 7)+1) $r .= "<th>{$hec_weekdays[$d]}</th>";
		
		$r .= '</tr></thead><tbody>';
	
		for ($week = 0; $week < $weeks; $week++) {
			$r .= '<tr>';
			
			for ($i = 0; $i < 7; $i++)  {
				$d = cal_from_jd($jd+$i, CAL_GREGORIAN);
				$dj = cal_from_jd($jd+$i, CAL_JEWISH);
				$r .= '<th>' . $d['day'] . ' (' . $dj['day'] . ' ' . $hec_months[(!hec::is_jewish_leap($dj['year']) && $dj['month'] == 6) ? 7 : -$dj['month']] . ')</th>';
			}
			
			$r .= '</tr><tr>';
			for ($i = 0; $i < 7; $i++, $jd++)  {
				$d = cal_from_jd($jd, CAL_GREGORIAN);
				$df = sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
				//$dj = cal_from_jd($jd, CAL_JEWISH);
				$r .= '<td>';
				//$j = 0;
				
				$old_query = $wp_query;
				$wp_query = new WP_Query(
					array(
						'post_type' => $hec_options['post_types'],
						'hec_date_time' => array($df . ' 00:00:00', $df . ' 23:59:59'),
						'hec_range' => true,
						'nopaging' => true) );
						
				if (have_posts())
					$r .= hec::get_the_event_list($time_format, $time_format, $date_time_format);
				
				$wp_query = $old_query;
				wp_reset_postdata();
				
				$r .= '</td>';
			}
		
			$r .= '</tr>';
		}
	
		$r = $r . '</tbody></table>' ;
		if (!isset($hidesubscription)) $r = $r . '<p style="text-align:center;">' . hec::ics_link(). '</p>';
	
		return $r;
	}
	
}

scbHooks::add('hec_hooks');
ybaShortcodes::add('hec_shortcodes');

class hec {
	
	static function ics_link() {
		global $hec_options;
		$url = home_url($hec_options['ics_permalink']);
		$webcal = preg_replace('/^https?:/', 'webcal:', $url);
		return sprintf($hec_options['ics_subscription_text'], $url, urlencode($url), $webcal, urlencode($webcal));
	}
	
	static function get_the_event_list($start_format = null, $stop_format = null, $multiday_stop_format = null, $link = true, $title = true) {
		global $post;
		if (is_null($start_format)) $start_format = get_option('date_format') . '; ' . get_option('time_format');
		if (is_null($stop_format)) $stop_format = get_option('time_format');
		if (is_null($multiday_stop_format)) $multiday_stop_format = get_option('date_format') . '; ' . get_option('time_format');
		//if (is_null($format)) $format = get_option('time_format');
		$r = '<dl class="hec-events">';
		while (have_posts())  {
			the_post();
			$start = strtotime($post->hec_start);
			$r .= '<dt>' . date($start_format, $start);
			if (!is_null($post->hec_stop)) {
				$stop = strtotime($post->hec_stop);
				$r .= ' &#x2013; ' . date((unixtojd($start) == unixtojd($stop)) ? $stop_format : $multiday_stop_format, $stop);
			}
			 			
			//if ($show_date) $r .= hec::date(strtotime($post->hec_start)) . ', ';
			//if ($show_day) $r .= date(strtotime($post->hec_start)) . ', ';
			//$r .= ((unixtojd(strtotime($post->hec_start)) < $jd+$i) ? (($jd+$i == unixtojd(strtotime($post->hec_stop))) ? 'Until ' . hec::time(strtotime($post->hec_stop)) : 'All day') : hec::time(strtotime($post->hec_start)));
			$r .= '</dt><dd title="' . get_the_excerpt() . '">';
			//$id = 'vevent-' . $post->ID . '-' . substr($post->hec_start, 0, 10);
			//echo "<a class=\"colorbox-link\" href=\"#$id\">";
			if ($link) $r .= '<a href="' . get_permalink() . '">';
			if ($title) {
				$r .= get_the_title();
				if (!is_null($post->hec_notes)) $r .= " ({$post->hec_notes})";
			}
			else if (!is_null($post->hec_notes)) $r .= $post->hec_notes;
			if ($link) $r .= '</a>';
			/*echo '<div style="display:none">';
			 echo hec::get_the_event();
			echo '</div>';*/
			$r .= '</dd>';
			/*echo '<div class="vevent" title="' . get_the_excerpt() . '">';
			 hec::the_uid();
			hec::the_dtstart();
			hec::the_dtend();
			hec::the_url();
			echo ((unixtojd(strtotime($post->hec_start)) < $jd+$i) ? (($jd+$i == unixtojd(strtotime($post->hec_stop))) ? 'Until ' . hec::time(strtotime($post->hec_stop)) : 'All day') : hec::time(strtotime($post->hec_start))) . ' — ';
			echo '<a href="' . get_permalink() . '">';
			hec::the_summary('span', true);
			echo '</a>';
			hec::the_description();
			echo '</div>';*/
		}
		$r .= '</dl>';
		return $r;
	}
	
	private static function get_the_property($property, $value, $label, $text = '', $a = false) {
		return "<dt class=\"$property-label\">$label</dt><dd class=\"$property\">" .
			(($text === true) ?
				$value :
				(($a) ?
					"<a href=\"$value\">$text</a>" :
					"<span class=\"value-title\" title=\"$value\">$text</span>")) . "</dd>";
	}
	
	static function get_the_event() {
		global $post;
		$uid = $post->ID . '-' . substr($post->hec_start, 0, 10);
		return
			"<dl id=\"vevent-$uid\" class=\"vevent\">" .
				self::get_the_property('summary', self::get_the_summary(), "Summary", true) .
				self::get_the_property('dtstart', $post->hec_start, "Start", hec::date_time(strtotime($post->hec_start))) .
				((is_null($post->hec_stop)) ? '' : self::get_the_property('dtend', $post->hec_stop, "End", hec::date_time(strtotime($post->hec_stop)))) .
				self::get_the_property('uid', $uid, "UID", true) .
				self::get_the_property('url', get_permalink(), "URL", get_permalink(), true) .
				self::get_the_property('description', get_the_excerpt(), "Description", true) .
			'</dl>';
	}

	private static function the_property($property, $value, $tag, $text = '', $attr = null) {
		echo "<$tag class=\"$property\"";
		echo ($text === true) ?
			">$value" :
			((is_null($attr)) ?
				"><span class=\"value-title\" title=\"$value\">$text</span>" :
				" $attr=\"$value\">$text");
		echo "</$tag>";
	}
	
	static function the_uid($tag = 'span', $text = '') {
		global $post;
		self::the_property('uid', $post->ID . '-' . substr($post->hec_start, 0, 10), $tag, $text);
	}
	
	static function the_dtstart($tag = 'span', $text = '') {
		global $post;
		self::the_property('dtstart', self::sql_date_time(0, strtotime($post->hec_start)), $tag, $text);
	}
	
	static function the_dtend($tag = 'span', $text = '') {
		global $post;
		if (!is_null($post->hec_stop))
			self::the_property('dtend', self::sql_date_time(0, strtotime($post->hec_stop)), $tag, $text);
	}
	
	static function get_the_summary() {
		global $post;
		$value = get_the_title();
		if (!is_null($post->hec_notes))
			$value .= ' (' . $post->hec_notes . ')';
		return $value;
	}
	
	static function the_summary($tag = 'span', $text = '') {
		self::the_property('summary', self::get_the_summary(), $tag, $text);
	}
	
	static function get_the_description() {
		return get_the_excerpt();
	}
	
	static function the_description($tag = 'span', $text = '') {
		global $post;
		self::the_property('description', self::get_the_description(), $tag, $text);
	}
	
	static function the_url($tag = 'a', $text = '') {
		global $post;
		self::the_property('url', get_permalink(), $tag, $text, 'href');
	}
	
	static function the_event() {
		global $post;
	}
	
	static function is_jewish_leap($year)
	{
		$year = $year % 19;
		return ($year == 0) || ($year == 3) || ($year == 6) || ($year == 8) || ($year == 11) || ($year == 14) || ($year == 17);
	}
	
	static function get_year_type($year)
	{
		global $hec_rosh_hashanah_labels;
		global $hec_year_labels;
		return ((((($year*7)+1) % 19) < 7) ? 'Mem' : 'Pei') . $hec_rosh_hashanah_labels[jddayofweek(jewishtojd(1, 1, $year))] . $hec_year_labels[jewishtojd(1, 1, $year+1) - jewishtojd(1, 1, $year)];
	}
	
	static function calculate_all($start, $stop) {
		global $wpdb;
		foreach ($wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '_hec_event'") as $row) {
			//echo "<p>" . $row->post_id . "</p>";
			self::calculate($start, $stop, $row->post_id);
		}
	}
	
	static function sql_date($offset = 0, $time = null) {
		return date("Y-m-d", ((is_null($time)) ? time() : $time) + $offset * 86400);
	}
	
	static function sql_date_time($offset = 0, $time = null) {
		return date("Y-m-d H:i:s", ((is_null($time)) ? time() : $time) + $offset * 86400);
	}
	
	static function date($time) {
		return date(get_option('date_format'), $time);
	}
	
	static function time($time) {
		return date(get_option('time_format'), $time);
	}
	
	static function date_time($time) {
		return date(get_option('date_format') . '; ' . get_option('time_format'), $time);
	}
	
	static function calculate($start, $stop, $post_id) {
		
		//echo "<p>$start $stop</p>";
		//return;
		
		global $post, $hec_options;
	
		$event = get_post_meta($post_id, '_hec_event', true);
		if ($event == '') return;
		
		if (isset($event['settings']))
		
		foreach ($event['settings'] as $id => $setting) {
		
			if ((isset($setting['start_date']) && ($stop < $setting['start_date'])) || (isset($setting['stop_date']) && ($start > $setting['stop_date']))) return;
			
			$calculation = get_post_meta($post_id, '_hec_calculation', true);
			//if ($calculation == '') unset($calculation);
			if ($calculation != '') {
				if ($calculation[0] <= $start && $calculation[1] >= $stop) return;
				$start = min($start, $calculation[1]);
				$stop = max($stop, $calculation[0]);
			}
			
			$latitude = (isset($setting['latitude'])) ? $setting['latitude'] : $hec_options['latitude'];
			$longitude = (isset($setting['longitude'])) ? $setting['longitude'] : $hec_options['longitude'];
			$sunset_zenith = (isset($setting['sunset_zenith'])) ? $setting['sunset_zenith'] : $hec_options['sunset_zenith'];
			$sunrise_zenith = (isset($setting['sunrise_zenith'])) ? $setting['sunrise_zenith'] : $hec_options['sunrise_zenith'];
			
			//echo $latitude . ' ' . $longitude . ' ' . $sunset_zenith . ' ';
	
			for ( $julian_date = $start; $julian_date <= $stop; $julian_date++ ) {
				
				if ((isset($setting['start_date']) && ($julian_date<$setting['start_date'])) || (isset($setting['stop_date']) && ($julian_date>$setting['stop_date']))) continue;
		
				if ($calculation != '' && $calculation[0] <= $julian_date && $calculation[1] >= $julian_date) continue;
				
				$j = explode('/', jdtogregorian($julian_date));
				$month = (int)$j[0];
				$day = (int)$j[1];
				$year = (int)$j[2];
				$week = ceil($day/7);
			
				$j = explode('/', jdtojewish($julian_date));
				$jmonth0 = (int)$j[0];
				$jday0 = (int)$j[1];
				$jyear0 = (int)$j[2];
				$jweek0 = ceil($jday0/7);
				$jleap0 = self::is_jewish_leap($jyear0);
				$jtype0 = self::get_year_type($jyear0);
				if (!$jleap0 && ($jmonth0 == 6)) $jmonth0 = 7;
			
				$j = explode('/', jdtojewish($julian_date+1));
				$jmonth1 = (int)$j[0];
				$jday1 = (int)$j[1];
				$jyear1 = (int)$j[2];
				$jweek1 = ceil($jday1/7);
				$jleap1 = self::is_jewish_leap($jyear1);
				$jtype1 = self::get_year_type($jyear1);
				if (!$jleap1 && ($jmonth1 == 6)) $jmonth1 = 7;
			
				$midnight = mktime(0, 0, 0, $month, $day, $year);
				$midnight0 = mktime(0, 0, 0, $month0, $day0, $year0);
				$weekday = jddayofweek($julian_date+$i);
		
				$j0 = true;
				$j1 = true;
		
				if (isset($setting['year'])) {
					if ($setting['year'] < 5000) {
						if ($setting['year'] != $year) continue;
					} else {
						$j0 = ($j0 && ($setting['year'] == $jyear0));
						$j1 = ($j1 && ($setting['year'] == $jyear1));
						if (!$j0 && !$j1) continue;
					}
				}
		
				if (isset($setting['month'])) {
					if ($setting['month'] < 0) {
						$j0 = ($j0 && (-$setting['month'] == $jmonth0));
						$j1 = ($j1 && (-$setting['month'] == $jmonth1));
						if (!$j0 && !$j1) continue;
					}
					else if ($setting['month'] != $month) continue;
				}
		
				if (isset($setting['day'])) {
					if ($setting['month'] < 0) {
						$j0 = ($j0 && ($setting['day'] == $jday0));
						$j1 = ($j1 && ($setting['day'] == $jday1));
						if (!$j0 && !$j1) continue;
					}
					else if ($setting['day'] != $day) continue;
				}
		
				if (isset($setting['week'])) {
					if ($setting['month'] < 0) {
						$j0 = ($j0 && ($setting['week'] == $jweek0));
						$j1 = ($j1 && ($setting['week'] == $jweek1));
						if (!$j0 && !$j1) continue;
					}
					else if ($setting['week'] != $week) continue;
				}
		
				if (isset($setting['weekday'])) {
					if ($setting['weekday'] < 0) {
						$j0 = ($j0 && ((-$setting['weekday']-1) == $weekday));
						$j1 = ($j1 && (((-$setting['weekday']+5) % 7) == $weekday));
						if (!$j0 && !$j1) continue;
					}
					else if ($setting['weekday']-1 != $weekday) continue;
				}
		
				if (array_key_exists('month_' . $jtype0, $setting))
					$j0 = ($j0 && ($setting['month_' . $jtype0] == $jmonth0));
		
				if (array_key_exists('month_' . $jtype1, $setting))
					$j1 = ($j1 && ($setting['month_' . $jtype1] == $jmonth1));
		
				if (array_key_exists('day_' . $jtype0, $setting))
					$j0 = ($j0 && ($setting['day_' . $jtype0] == $jday0));
			
				if (array_key_exists('day_' . $jtype1, $setting))
					$j1 = ($j1 && ($setting['day_' . $jtype1] == $jday1));
		
				if (!$j0 && !$j1) continue;
		
				$hebrew = ((isset($setting['time_offset']) || !isset($setting['time'])) && (count(preg_grep('/^(day|month)_/', array_keys($setting))) > 0 || $setting['weekday'] < 0 || ($setting['month'] < 0 && isset($setting['day']))));
				$offset = ((is_null($setting['time'])) ? 0 : ((int)$setting['time']*60));
		
				if ($setting['sunrise'] && $hebrew) $j1 = false;
				if (!$j0 && !$j1) continue;
		
				$t = (($setting['sunrise']) ?
					date_sunrise($midnight, SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, $sunrise_zenith) :
					(($hebrew) ? date_sunset(($j0) ? $midnight-1 : $midnight, SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, $sunset_zenith) : $midnight)) + $offset;
		
				if ($t < $midnight || $t >= ($midnight + 86400)) continue;
				
				add_post_meta($post_id, '_hec_start', $id . ',' .hec::sql_date_time(0, $t));
							
				if (isset($setting['duration_days']) || isset($setting['duration_minutes'])) {
					$tstop = (isset($setting['duration_days'])) ?
						((($hebrew) ?
							date_sunset((($j0) ? $midnight-1 : $midnight) + 86400*$setting['duration_days'], SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude, $sunset_zenith) :
							($t + 86400*$setting['duration_days'])))
						: $t;
					if (isset($setting['duration_minutes'])) $tstop += 60*(int)$setting['duration_minutes'];
					add_post_meta($post_id, '_hec_stop',  $id . ',' . hec::sql_date(0, $t) . ',' . hec::sql_date_time(0, $tstop));
				}
			}
			
		}
		
		$calculation = ($calculation == '') ?
			array( $start, $stop ) :
			array( min( $calculation[0], $start ), max( $calculation[1], $stop ) );
		
		update_post_meta($post_id, '_hec_calculation', $calculation);		
	}
					
}


class hec_options_page {
	
	static function callback_sanitize_options($options) {
		global $wp_query, $post, $hec_options;
		//unset($options['version']);
		$options['latitude'] = floatval($options['latitude']);
		$options['longitude'] = floatval($options['longitude']);
		$options['sunset_zenith'] = floatval($options['sunset_zenith']);
		$options['sunrise_zenith'] = floatval($options['sunrise_zenith']);
		$options['occurence_limit'] = intval($options['occurence_limit']);
		$options['day_limit'] = intval($options['day_limit']);
		$options['post_types'] = array_keys($options['post_types']);
		
		$old_query = $wp_query;
		$wp_query = new WP_Query( array ( 'nopaging' => true, 'post_type' => $options['post_types'] ) );
			
		while (have_posts()) {
			the_post();
			delete_post_meta($post->ID, '_hec_calculation');
			delete_post_meta($post->ID, '_hec_start');
			delete_post_meta($post->ID, '_hec_stop');
		}
		
		// Reset Post Data
		$wp_query = $old_query;
		wp_reset_postdata();

		$hec_options = $options;
		
		flush_rewrite_rules();

		return $options;
	}
	
	static function callback_ics_section() {
		echo '<p>Settings for the ICS feed. In subscription text field %1$s = URL of feed, %2$s = encoded form of URL for HTML queries, %3$s = Webcal URL of feed, and %4$s = encoded form of Webcal URL for HTML queries.</p>';
	}
	
	static function callback_settings_section() {
		echo '<p>Main settings for Hebrew events.</p>';
	}
	
	static function callback_post_types_section() {
		echo '<p>Post Types with Hebrew event data.</p>';
	}
	
	static function callback_text_settings_field($args) {
		global $hec_options;
		echo '<input id="' . $args['label_for'] . '" name="hec_options[' . $args['name']. ']" size="40" type="text" value="' . $hec_options[$args['name']] . '" />';
	}
	
	static function callback_textarea_settings_field($args) {
		global $hec_options;
		echo '<textarea id="' . $args['label_for'] . '" class="theEditor" rows="5" cols="40" name="hec_options[' . $args['name']. ']">' . htmlentities($hec_options[$args['name']]) . '</textarea>';
	}
	
	static function callback_checkbox_settings_field($args) {
		global $hec_options;
		echo '<input id="' . $args['label_for'] . '" name="hec_options[post_types][' . $args['name']. ']" type="checkbox"';
		if (in_array($args['name'], $hec_options['post_types'])) echo ' checked';
		echo '/>';
	}
	
	static function callback_options_page()
	{
		wp_tiny_mce(true, array("editor_selector" => "theEditor")); ?>
			<div class="wrap">
				<h2>Hebrew Events Calendar Options</h2>
				<form method="post" action="options.php">
					<?php settings_fields('hec_options'); do_settings_sections('hec_options'); ?>
					<p class="submit"><input type="submit" class="button-primary" value="Save Changes"/></p>
				</form>
				<p>Please support further development by donating.</p>
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
					<input type="hidden" name="cmd" value="_s-xclick"/>
					<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHRwYJKoZIhvcNAQcEoIIHODCCBzQCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYCkKNbfzOqqdNvbM/Dqm2Y2e2o+vsKBAZ0BIBCUhJfj4uIZ4zXKuthXKNT5MTyT88CuRfrrGlyevSjYoCFKnxp+htgibwNfJv2W+VyThX5ujvefx96+de8O5RNc6gXkUkm6ly05sIR0+hwlYYVsU/rz1s5hq0TIxrq4Jk13EnDoQTELMAkGBSsOAwIaBQAwgcQGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQIlmVPWh5t4w+AgaA2MGNCMV6Q0BQZHqdPrDw59oDLmpW/RIoTHob48r7HDZXSA5HW3eAOJV8Y1taf5lb+lVYbpUmjrKyIzFSNKp8MzG6qrJcRX08iyew7AhHaqJ4VTNAEMMLoSzFDdI6oA6sy33DiYBJJYL3H7UtQPmcg/OtQRLrQ1tKtyUAoVLheapgaalkHS2Nf/doOotZ5ohsJcCscqWj3fBa+/Yeyn3cvoIIDhzCCA4MwggLsoAMCAQICAQAwDQYJKoZIhvcNAQEFBQAwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMB4XDTA0MDIxMzEwMTMxNVoXDTM1MDIxMzEwMTMxNVowgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDBR07d/ETMS1ycjtkpkvjXZe9k+6CieLuLsPumsJ7QC1odNz3sJiCbs2wC0nLE0uLGaEtXynIgRqIddYCHx88pb5HTXv4SZeuv0Rqq4+axW9PLAAATU8w04qqjaSXgbGLP3NmohqM6bV9kZZwZLR/klDaQGo1u9uDb9lr4Yn+rBQIDAQABo4HuMIHrMB0GA1UdDgQWBBSWn3y7xm8XvVk/UtcKG+wQ1mSUazCBuwYDVR0jBIGzMIGwgBSWn3y7xm8XvVk/UtcKG+wQ1mSUa6GBlKSBkTCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb22CAQAwDAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQUFAAOBgQCBXzpWmoBa5e9fo6ujionW1hUhPkOBakTr3YCDjbYfvJEiv/2P+IobhOGJr85+XHhN0v4gUkEDI8r2/rNk1m0GA8HKddvTjyGw/XqXa+LSTlDYkqI8OwR8GEYj4efEtcRpRYBxV8KxAW93YDWzFGvruKnnLbDAF6VR5w/cCMn5hzGCAZowggGWAgEBMIGUMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbQIBADAJBgUrDgMCGgUAoF0wGAYJKoZIhvcNAQkDMQsGCSqGSIb3DQEHATAcBgkqhkiG9w0BCQUxDxcNMTEwNjI1MTMzNDU1WjAjBgkqhkiG9w0BCQQxFgQUc9MXCHj9L1hmFDHAe4/fVDkUzUYwDQYJKoZIhvcNAQEBBQAEgYCkrZaknfNBDweykOuOIIYhdKgaMsfFfBxqLl7YeV+JE/og6qQAkmlo0Uss2xyLZ7mY7ElJFMaZENUuVtp6Zt99yBSvKVms6wpvvuZQsgFiveEgWB/WPP4Aa5W9COs7uuLzIBaNWsJ0UYEfydPqyJysjOQqAAcEQk6n43+ngp28ZQ==-----END PKCS7-----"/>
					<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"/>
					<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"/>
				</form>
			</div><?php
	}		
	
}

class hec_metaboxes {
	
	static function event_settings($id, $settings, $day_limit) {
		global $post, $wp_query, $hec_year_types, $hec_months, $hec_weekdays;
		
		$old_query = $wp_query;
		$wp_query = new WP_Query(
		array(
			'post__in' => array( $post->ID ),
			'hec_show_all' => true,
			'hec_id' => $id,
			'post_type' => 'any',
			'hec_date_time' => array(hec::sql_date_time(), hec::sql_date_time($day_limit)),
			'nopaging' => true) );
			
		//$occurences = hec::get_occurences(array('day_limit' => $day_limit, 'show_hidden' => true), /*unixtojd(),  $day_limit, true, false, true,*/ array('post__in' => array($post->ID)));
		// Use nonce for verification
		//wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
		
		?>
		
		<tr><th>Settings</th><th>Occurences</th></tr>
		<tr>
		<td style="vertical-align:top">

			<table>
		
				<tr>
					<th class="left"><label for="hec_time">Time</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][time]" type="text" id="hec_time" value="<?php echo hec_format_time($settings); ?>"/></td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_weekday">Weekday</label></th>
					<td>
						<select name="hec_settings[<?php echo $id; ?>][weekday]" id="hec_weekday">
							<option value="">None</option>
							<?php
								foreach ($hec_weekdays as $i => $name)
								{
									echo '<option value="' . $i . '"';
									if ($settings['weekday'] == $i) echo ' selected="selected"';
									echo '>' . $name . '</option>';
								}  ?>
						</select>
					</td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_week">Week</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][week]" type="text" id="hec_week" value="<?php echo $settings['week']; ?>"/></td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_day">Day</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][day]" type="text" id="hec_day" value="<?php echo $settings['day']; ?>"/></td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_month">Month</label></th>
					<td>
						<select name="hec_settings[<?php echo $id; ?>][month]" id="hec_month">
							<option value="">None</option>
							<?php
							foreach ($hec_months as $i => $name)
							{
								echo '<option value="' . $i . '"';
								if ($settings['month'] == $i) echo ' selected="selected"';
								echo '>' . $name . '</option>';
							}  ?>
						</select>
					</td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_year">Year</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][year]" type="text" id="hec_year" value="<?php echo $settings['year']; ?>"/></td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_sunrise">Sunrise</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][sunrise]" type="checkbox" id="hec_sunrise" <?php if ($settings['sunrise']) echo ' checked'; ?>/></td>
				</tr>
		
				<tr>
					<th class="left"><label for="hec_duration">Duration</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][duration]" type="text" id="hec_duration" value="<?php echo trim($settings['duration_days'] . ((isset($settings['duration_minutes'])) ? sprintf(' %u:%02u',floor($settings['duration_minutes'] / 60), $settings['duration_minutes'] % 60) : '')); ?>"/></td>
				</tr>
		
				<tr title="Start date of event window. Before this date the event will not be shown.">
					<th class="left"><label for="hec_start_date">Start Date</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][start_date]" type="text" id="hec_start_date" value="<?php if (isset($settings['start_date'])) echo strftime('%m/%d/%Y', jdtounix($settings['start_date'])); ?>"/></td>
				</tr>
		
				<tr title="Stop date of event window. After this date the event will not be shown.">
					<th class="left"><label for="hec_stop_date">Stop Date</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][stop_date]" type="text" id="hec_stop_date" value="<?php if (isset($settings['stop_date'])) echo strftime('%m/%d/%Y', jdtounix($settings['stop_date'])); ?>"/></td>
				</tr>
				
				<tr>
					<th class="left"><label for="hec_latitude">Latitude</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][latitude]" type="text" id="hec_latitude" value="<?php echo $settings['latitude']; ?>"/></td>
				</tr>
				
				<tr>
					<th class="left"><label for="hec_longitude">Longitude</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][longitude]" type="text" id="hec_longitude" value="<?php echo $settings['longitude']; ?>"/></td>
				</tr>
				
				<tr>
					<th class="left"><label for="hec_sunset_zenith">Sunset Zenith</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][sunset_zenith]" type="text" id="hec_sunset_zenith" value="<?php echo $settings['sunset_zenith']; ?>"/></td>
				</tr>
				
				<tr>
					<th class="left"><label for="hec_sunrise_zenith">Sunrise Zenith</label></th>
					<td><input name="hec_settings[<?php echo $id; ?>][sunrise_zenith]" type="text" id="hec_sunrise_zenith" value="<?php echo $settings['sunrise_zenith']; ?>"/></td>
				</tr>
				
			</table>
		
			<p><strong>Hebrew Encoded Dates:</strong></p>
		
			<table>
				<thead>
					<tr>
						<th>Type</th>
						<th>Day</th>
						<th>Month</th>
					</tr>
				</thead>
		
				<tfoot>
					<tr>
						<th>Type</th>
						<th>Day</th>
						<th>Month</th>
					</tr>
				</tfoot>
		
				<tbody>
					<?php foreach ($hec_year_types as $year_type) : ?>
						<tr>
							<td><?php echo $year_type; ?></td>
							<td><input name="hec_settings[<?php echo $id; ?>][encoded_day][<?php echo str_replace('-', '_', $year_type);?>]" type="text" value="<?php echo $settings['day_' . $year_type]; ?>" size="4"/></td>
							<td>
								<select name="hec_settings[<?php echo $id; ?>][encoded_month][<?php echo str_replace('-', '_', $year_type);?>]">
									<option value="">None</option>
									<?php foreach ($hec_months as $i => $name) :
										if ($i < 0) : ?>
											<option value="<?php echo abs($i); ?>" <?php if ($settings['month_' . $year_type] == abs($i)) echo ' selected="selected"'; ?> >
												<?php echo $name; ?>
											</option>
										<?php endif; endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>		
			
		</td>
		
		<td style="vertical-align:top">

			<?php if (have_posts()) : ?>
			<table>
				<thead>
					<tr>
						<th>Show</th>
						<th>Date</th>
						<th>Notes</th>
					</tr>
				</thead>
				
				<tfoot>
					<tr>
						<th>Show</th>
						<th>Date</th>
						<th>Notes</th>
					</tr>
				</tfoot>
		
				<tbody><?php
				while (have_posts())  {
					the_post();
					$label = $id . ',' . substr($post->hec_start, 0, 10);
					//$jd = unixtojd($occurence->start);
					echo '<tr><td><input name="hec_settings[' . $id . '][show][' . $label . ']" type="checkbox"'. (($post->hec_hide) ? '' : ' checked="yes"') .'/></td><td>' .
					hec::date(strtotime($post->hec_start)) .
					'</td><td><input size="40" name="hec_settings[' . $id . '][notes][' . $label . ']" type="text" value="' . $post->hec_notes . '"/></td></tr>'; }
				?>
				</tbody>
			</table>
			<?php else: ?>
			<p>No occurences in the next <?php echo $day_limit; ?> days.</p>
			<?php endif;  
			$wp_query = $old_query;
			wp_reset_postdata(); ?>
		
		</td>
		</tr>
		
		<?php
	}
	
	static function callback_event_metabox() {
		global $post, $hec_weekdays, $hec_months, $hec_year_types, $hec_options;
		// Use nonce for verification
		wp_nonce_field( 'hec_update_event', 'hec_event_mb_nonce' );
	
		$data = get_post_meta($post->ID, '_hec_event', true);
		$event = ($data == '') ? array() : $data;
	
		$day_limit = (isset($event['day_limit'])) ? $event['day_limit'] : $hec_options['day_limit'];
		$occurence_limit = (isset($event['occurence_limit'])) ? $event['occurence_limit'] : $hec_options['occurence_limit'];
		
		?>

		<table>
			<tr>
				<th><label for="hec_occurence_limit">Occurence Limit</label></th>
				<td><input name="hec_occurence_limit" type="text" id="hec_occurence_limit" value="<?php echo $occurence_limit; ?>"/></td>
			</tr>
		
			<tr>
				<th><label for="hec_day_limit">Day Limit</label></th>
				<td><input name="hec_day_limit" type="text" id="hec_day_limit" value="<?php echo $day_limit; ?>"/></td>
			</tr>
		
		</table>
		
		<table>
		<?php
		
		if (isset($event['settings']))
			foreach ($event['settings'] as $id => $settings)
				self::event_settings($id, $settings, $day_limit);
		
		self::event_settings(uniqid(), array(), $day_limit);

		?> </table> <?php 
	}
		
}


class hec_dashboard_widget {
	
	static function callback() {
		global $hec_options, $post, $wp_query, $hec_dashboard_days;
		
		echo '<p class="sub">Upcoming Hebrew Events</p>';
		echo '<table width="100%" style="border-collapse:collapse;">';
		$old_query = $wp_query;
		$wp_query = new WP_Query(
			array(
				'post_type' => $hec_options['post_types'],
				'hec_date_time' => array(hec::sql_date_time(), hec::sql_date_time($hec_dashboard_days)),
				'nopaging' => true) );
		$k = 0;
		while (have_posts())  {
			the_post();
			//echo "<tr><td>{$post->hec_notes}</td><td>{$post->hec_stop}</td></tr>";
			echo '<tr style="' . (($k % 2 == 0) ? 'background:	#fafafa; ' : '' ) . 'border-top:solid 1px	#d0d0d0; border-bottom:solid 1px #d0d0d0;">';
			echo '<td style="padding: 5pt;"><a href="' . get_edit_post_link($post->ID) . '">' . get_the_title() . '</a></td>';
			echo '<td style="padding: 5pt;">' .  $post->hec_notes . '</td>';
			echo '<td style="text-align:right; padding: 5pt;">' . hec::date_time(strtotime($post->hec_start)) . '</td>';
			echo '</tr>';
			$k++;
		}
		$wp_query = $old_query;
		wp_reset_postdata();		
		echo '</table>';
		
		/*global $hec_dashboard_days;
		echo '<p class="sub">Upcoming Hebrew Events</p>';
		echo '<table width="100%" style="border-collapse:collapse;">';
		foreach (hec::get_occurences(array('day_limit' => $hec_dashboard_days)) as $k => $occurence) {
			echo '<tr style="' . (($k % 2 == 0) ? 'background:	#fafafa; ' : '' ) . 'border-top:solid 1px	#d0d0d0; border-bottom:solid 1px #d0d0d0;">';
			echo '<td style="padding: 5pt;"><a href="' . get_edit_post_link($occurence->post_id) . '">' . $occurence->title . '</a></td>';
			echo '<td style="text-align:right; padding: 5pt;">' . date(get_option('date_format') . '; ' . get_option('time_format'), $occurence->start) . '</td>';
			echo '</tr>';
		}
		echo '</table>';*/
	}
	
	static function control_callback() {
		global $current_user, $hec_dashboard_days;
		$options = hec_user_options();
	
		if ( 'post' == strtolower($_SERVER['REQUEST_METHOD']) && isset( $_POST['widget_id'] ) && 'hec_dashboard_widget' == $_POST['widget_id'] )
		update_user_option($current_user, 'hec_dashboard_days', $hec_dashboard_days = (int)$_REQUEST['hec_dashboard_days']);
	
		echo '<label for="hec_dashboard_days">Days</label><input id="hec_dashboard_days" name="hec_dashboard_days" type="text" size="4" value="' . $hec_dashboard_days . '"/>';
	}
	
}

/*foreach (get_option('hec_post_types', array('page' => 1, 'post' => 1)) as $post_type => $show)
	if ($show)
	{
		add_filter('manage_' . $post_type . 's_columns', 'hec_manage_columns');
		add_action( 'manage_' . $post_type . 's_custom_column' , 'hec_manage_custom_column' );
	}*/

function hec_user_options() {
	global $current_user, $wpdb;
	$defaults = array( 'dashboard_widget_days' => 7 );
	$options = get_user_meta($current_user, $wpdb->prefix . 'hec', true);
	return array_merge( $defaults, (is_array($options)) ? $options : array() );
}
 

function hec_manage_columns($columns) {
	$columns['next_occurence'] = 'Next Occurence';
	return $columns; }

function hec_manage_custom_column($column)
{
	global $post;

	if ($column == 'next_occurence')
	{
		$event = get_post_meta($post->ID, '_hec_event', true);
		if ($event != '')
		{
			//date_default_timezone_set(get_option('timezone_string'));
			$occurences = hec::get_occurences(array('first_only' => true), array('post__in' => array( $post->ID )));
			echo ($occurences) ? hec::date_time($occurence->start) : 'None';
		}
	}
}

function hec_format_time($event)
{
	if (!isset($event['time'])) return '';
	$time = (int)$event['time'];
	$neg = ($time<0);
	$time = abs($time);
	return (isset($event['time_offset'])) ?
			sprintf('%s%d:%02u', ($neg) ? '-' : '+', $time / 60, $time % 60) :
			sprintf('%u:%02u %sM', ((($time / 60) % 12) == 0) ? 12 : (($time / 60) % 12), $time % 60, (($time < 720) ? 'A' : 'P'));
}

class hec_events_widget extends WP_Widget {
	function hec_events_widget() {
		$widget_ops = array('classname' => 'hec-events-widget', 'description' => 'Upcoming events.' );
		$this->WP_Widget('hec_events_widget', __('Hebrew Events Widget'), $widget_ops);
	}
 
	function widget($args, $instance) {
		global $hec_options, $post, $wp_query;
		extract($args, EXTR_SKIP);
 
		//date_default_timezone_set(get_option('timezone_string'));

		echo $before_widget;
		echo $before_title . $instance['title'] . $after_title;
		$time_format = get_option('time_format');
		echo hec_shortcodes::agenda(array(
			'days' => $instance['days'],
			'start_format' => 'l; ' . $time_format,
			'stop_format' => $time_format,
			'multiday_stop_format' => 'l; ' . $time_format));
		/*echo '<ul>';
		$jd = unixtojd(time());
		for ($i = 0; $i < $instance['days']; $i++)
		{
			$old_query = $wp_query;
			$wp_query = new WP_Query(
				array(
					'post_type' => $hec_options['post_types'],
					'hec_date_time' => array(hec::sql_date_time($i), hec::sql_date_time($i+1)),
					'nopaging' => true) );
			
			if (have_posts())
				echo '<li>' . JDDayOfWeek($jd+$i,1) . hec::get_the_event_list() . '</li>';
			
			$wp_query = $old_query;
			wp_reset_postdata();
				
		}
		echo '</ul>';*/
		if ($instance['subscribe']) echo '<div style="margin-top:10pt;">' . hec::ics_link() . '</div>';
		echo $after_widget;
	}
 
	function update($new_instance, $old_instance) {
		$new_instance['subscribe'] = isset($new_instance['subscribe']);
		return array_merge(array('days' => 7, 'title' => 'Upcoming Events', 'subscribe' => true), $old_instance, $new_instance);
		$instance = $old_instance;
		$new_instance = wp_parse_args((array)$new_instance, array('days' => 7, 'title' => 'Upcoming Events', 'subscribe' => 1));
		$instance['days']			= $new_instance['days'];
		$instance['title']			= $new_instance['title'];
		if (isset($new_instance['subscribe'])) $instance['subscribe'] = 1;
		return $instance;
	}
 
	function form($instance) {
		extract(array_merge(array('days' => 7, 'title' => 'Upcoming Events', 'subscribe' => true), $instance));
		/*$instance = wp_parse_args((array)$instance,
			array('days' => 7, 'title' => 'Upcoming Events', 'subscribe' => 1));
		extract($instance);*/
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<p><label for="<?php echo $this->get_field_id('days'); ?>"><?php _e('Days:'); ?></label>
		<input id="<?php echo $this->get_field_id('days'); ?>" size="4" name="<?php echo $this->get_field_name('days'); ?>" type="text" value="<?php echo esc_attr($days); ?>" /></p>
		<p>
		<input class="checkbox" id="<?php echo $this->get_field_id('subscribe'); ?>" name="<?php echo $this->get_field_name('subscribe'); ?>" type="checkbox" <?php checked($subscribe,true ); ?> />
		<label for="<?php echo $this->get_field_id('subscribe'); ?>"><?php _e('Show Subscription Text'); ?></label>
		</p>
<?php
	}
}


?>