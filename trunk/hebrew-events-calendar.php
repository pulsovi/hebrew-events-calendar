<?php
/*
Plugin Name: Hebrew Events Calendar
Plugin URI: http://wordpress.org/extend/plugins/hebrew-events-calendar/
Description: A hebrew friendly events calendar
Version: 0.3
Author: Yitzchak ben Avraham
Author URI: http://wordpress.org/extend/plugins/profile/yitzi
License: GPL2
*/

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

add_action('init', 'hec_init');
add_action('admin_init', 'hec_admin_init');
add_action('admin_menu', 'hec_admin_menu');
add_action('save_post', 'hec_save_postdata' );
add_action('add_meta_boxes', 'hec_register_meta_box');
add_action('widgets_init', 'hec_widgets_init');
add_action('wp_dashboard_setup', 'hec_dashboard_setup');
add_filter('the_content', 'hec_post_events', 9); 
add_shortcode('calendar', 'hec_calendar_sc');  
add_filter('query_vars','hec_query_vars');
add_action('template_redirect', 'hec_ics');
register_activation_hook( __FILE__, 'hec_activate' );
add_filter( 'generate_rewrite_rules', 'hec_rewrite' );

function hec_rewrite( $wp_rewrite ) {
	$wp_rewrite->rules = array(get_option('hec_ics_permalink', 'calendar.ics') => 'index.php?hec_ics=all') + $wp_rewrite->rules;
}

function hec_activate() {
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}

function hec_query_vars($vars) {
	$vars[] = 'hec_ics';
	return $vars;
}

function hec_encode_text($text)
{
	return str_replace(',','\,', str_replace(';', '\;', str_replace("\n", '\n', str_replace("\\", '\\', $text))));
}

function hec_ics() {
	global $hec_options;
	if (get_query_var('hec_ics')) {
		header('Content-type: text/calendar');
		echo "BEGIN:VCALENDAR\n";
		echo "VERSION:2.0\n";
		echo "X-WR-CALNAME:" . hec_encode_text($hec_options['ics_title']) . "\n";
		echo "X-WR-CALDESC:" . hec_encode_text($hec_options['ics_description']) . "\n";
		echo "X-PUBLISHED-TTL:PT360M\n";

		foreach (hec_get_occurences(unixtojd(), $hec_options['day_limit']) as $occurence)
		{
			echo "BEGIN:VEVENT\n";
			echo "UID:" . $occurence->post_id . '-' . unixtojd($occurence->start) . "\n";
			echo gmstrftime("DTSTART:%Y%m%dT%H%M00Z\n", $occurence->start);
			if (!is_null($occurence->stop)) echo gmstrftime("DTEND:%Y%m%dT%H%M00Z\n", $occurence->stop);
			echo "SUMMARY:" . hec_encode_text($occurence->title) . "\n";
			if (!is_null($occurence->description) && !is_null($occurence->notes))
				echo "DESCRIPTION:" . hec_encode_text($occurence->description . "\n\n" . $occurence->notes) . "\n";
			else if (!is_null($occurence->description))
				echo "DESCRIPTION:" . hec_encode_text($occurence->description) . "\n";
			else if (!is_null($occurence->notes)) echo "DESCRIPTION:" . hec_encode_text($occurence->notes) . "\n";
			echo "URL:" . hec_encode_text(get_permalink($occurence->post_id)) . "\n";
			echo "END:VEVENT\n";
		}

		echo "END:VCALENDAR\n";
		exit();
	}
}

/*foreach (get_option('hec_post_types', array('page' => 1, 'post' => 1)) as $post_type => $show)
	if ($show)
	{
		add_filter('manage_' . $post_type . 's_columns', 'hec_manage_columns');
		add_action( 'manage_' . $post_type . 's_custom_column' , 'hec_manage_custom_column' );
	}*/

function hec_dashboard_setup() {
	if ( current_user_can( 'edit_posts') ) 
	wp_add_dashboard_widget('hec_dashboard_widget', 'Hebrew Events', 'hec_dashboard_widget', 'hec_dashboard_widget_setup');	
}

function hec_dashboard_widget() {
	global $hec_dashboard_days;
	echo '<p class="sub">Upcoming Hebrew Events</p>';
	echo '<table width="100%" style="border-collapse:collapse;">';
	foreach (hec_get_occurences(unixtojd(), $hec_dashboard_days) as $k => $occurence) {
		echo '<tr style="' . (($k % 2 == 0) ? 'background:	#fafafa; ' : '' ) . 'border-top:solid 1px	#d0d0d0; border-bottom:solid 1px #d0d0d0;">';
		echo '<td style="padding: 5pt;"><a href="' . get_edit_post_link($occurence->post_id) . '">' . $occurence->title . '</a></td>';
		echo '<td style="text-align:right; padding: 5pt;">' . strftime('%l:%M %P on %m/%d/%Y', $occurence->start) . '</td>';
		echo '</tr>'; }
	echo '</table>'; } 

function hec_user_options() {
	global $current_user, $wpdb;
	$defaults = array( 'dashboard_widget_days' => 7 );
	$options = get_user_meta($current_user, $wpdb->prefix . 'hec', true);
	return array_merge( $defaults, (is_array($options)) ? $options : array() );
}
 
function hec_dashboard_widget_setup() {
	global $current_user, $hec_dashboard_days;
	$options = hec_user_options();
 
	if ( 'post' == strtolower($_SERVER['REQUEST_METHOD']) && isset( $_POST['widget_id'] ) && 'hec_dashboard_widget' == $_POST['widget_id'] )
		update_user_option($current_user, 'hec_dashboard_days', $hec_dashboard_days = (int)$_REQUEST['hec_dashboard_days']);
		
	echo '<label for="hec_dashboard_days">Days</label><input id="hec_dashboard_days" name="hec_dashboard_days" type="text" size="4" value="' . $hec_dashboard_days . '"/>'; }


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
			$occurences = hec_get_occurences(unixtojd(), 400, /*$show_all*/ false, /*$post_id*/ $post->ID, /*$first_only*/ true, /*$start_only*/false);
			echo ($occurences) ? strftime('%l:%M %P on %m/%d/%Y', $occurence->start) : 'None';
		}
	}
}

function hec_widgets_init()
{
	return register_widget("hec_events_widget");
}

function hec_init() {
	global /*$hec_post_types, $hec_latitude, $hec_longitude, $hec_sunrise_zenith, $hec_sunset_zenith, $hec_occurence_limit, $hec_day_limit, */$hec_options;

	$hec_options = get_option('hec_options');
	if (!$hec_options)
		update_option('hec_options',
			$hec_options = array(
				'latitude' => ini_get('date.default_latitude'),
				'longitude' => ini_get('date.default_longitude'),
				'sunrise_zenith' => ini_get('date.sunrise_zenith'),
				'sunset_zenith' => ini_get('date.sunset_zenith'),
				'post_types' => array('page' => 1, 'post' => 1),
				'occurence_limit' => 10,
				'day_limit' => 390,
				'ics_permalink' => 'calendar.ics',
				'ics_title' => 'My Events',
				'ics_description' => 'Description of my events.'));

	ini_set('date.default_latitude', $hec_options['latitude']);
	ini_set('date.default_longitude', $hec_options['longitude']);
	ini_set('date.sunrise_zenith', $hec_options['sunrise_zenith']);
	ini_set('date.sunset_zenith', $hec_options['sunset_zenith']);
	date_default_timezone_set(get_option('timezone_string'));

	/*$hec_post_types = get_option('hec_post_types', array('page' => 1, 'post' => 1));
	$hec_occurence_limit = get_option('hec_occurence_limit', 10);
	$hec_day_limit = get_option('hec_day_limit', 390);

	ini_set('date.default_latitude', $hec_latitude = get_option('hec_latitude', ini_get('date.default_latitude')));
	ini_set('date.default_longitude', $hec_longitude = get_option('hec_longitude', ini_get('date.default_longitude')));
	ini_set('date.sunrise_zenith', $hec_sunrise_zenith = get_option('hec_sunrise_zenith', ini_get('date.sunrise_zenith')));
	ini_set('date.sunset_zenith', $hec_sunset_zenith = get_option('hec_sunset_zenith', ini_get('date.sunset_zenith')));
	date_default_timezone_set(get_option('timezone_string'));*/

/*	register_post_type(
		'hec_event',
		array(
			'label' => 'Events',
			'description' => 'Hebrew event',
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'rewrite' => array('slug' => 'event'),
			'query_var' => true,
			'supports' => array('title','editor','excerpt','trackbacks','custom-fields','comments','revisions','thumbnail','author','page-attributes',),
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
				'parent' => 'Parent Event'),
			'register_meta_box_cb' => 'hec_register_meta_box'
		)
	);*/
}

function hec_format_time2($time)
{
	return trim(strftime('%l:%M %P', $time));
}

function hec_format_time($event)
{
	if (!isset($event['time'])) return '';
	$time = (int)$event['time'];
	$neg = ($time<0);
	$time = abs($time);
	return (isset($event['offset'])) ?
			sprintf('%s%d:%02u', ($neg) ? '-' : '+', $time / 60, $time % 60) :
			sprintf('%u:%02u %sM', ((($time / 60) % 12) == 0) ? 12 : (($time / 60) % 12), $time % 60, (($time < 720) ? 'A' : 'P'));
}

function hec_save_postdata( $post_ID ) {
	global $hec_year_types;

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

	$occurences_mb = wp_verify_nonce( $_REQUEST['hec_occurences_mb_nonce'], 'hec_update_occurences' );
	$events_mb = wp_verify_nonce( $_REQUEST['hec_event_mb_nonce'], 'hec_update_event' );
	if (!$occurences_mb && !$events_mb) return;

	$data = get_post_meta($post_ID, '_hec_event', true);
	$old_event = ($data == '') ? array() : $data;
	$event = array();
	if (!$events_mb || !$occurences_mb)
		foreach ($old_event as $k => $v)
		{
			$occurence_field = $k == 'occurence_limit' || $k == 'day_limit' || substr($k, 0, 5) == 'hide_' || substr($k, 0, 6) == 'notes_';
			if (($occurence_field && $events_mb) || (!$occurence_field && $occurences_mb))
				$event[$k] = $v;
		}

	if ($events_mb)
	{
		if ($_REQUEST['hec_start_date'] != '') $event['start_date'] = unixtojd(strtotime($_REQUEST['hec_start_date']));
		if ($_REQUEST['hec_stop_date'] != '') $event['stop_date'] = unixtojd(strtotime($_REQUEST['hec_stop_date']));
		if ($_REQUEST['hec_weekday'] != '') $event['weekday'] = (int)$_REQUEST['hec_weekday'];
		if ($_REQUEST['hec_week'] != '') $event['week'] = (int)$_REQUEST['hec_week'];
		if ($_REQUEST['hec_day'] != '') $event['day'] = (int)$_REQUEST['hec_day'];
		if ($_REQUEST['hec_month'] != '') $event['month'] = (int)$_REQUEST['hec_month'];
		if ($_REQUEST['hec_year'] != '') $event['year'] = (int)$_REQUEST['hec_year'];
		if (isset($_REQUEST['hec_sunrise'])) $event['sunrise'] = 1;

		$t = rtrim(trim(strtoupper($_REQUEST['hec_time'])), 'M');
		if ($t != '')
		{
			if (substr($t, -1) != 'A' && substr($t, -1) != 'P') $event['time_offset'] = 1;
			$parts = explode(':', rtrim($t, 'AP'));
			$event['time'] = ($event_time_offset) ? 
			((count($parts) == 1) ? (int)$parts[0] : ((int)$parts[0]*60+(int)$parts[1])) :
			(((count($parts) == 1) ? (int)$parts[0]*60 : ((int)$parts[0]*60+(int)$parts[1])) + ((substr($t, -1) == 'P') ? 720 : 0));
			if (substr($t,0,2) == '-0') $event['time'] = -$event['time'];
		}

		$t = trim($_REQUEST['hec_duration']);
		if ($t != '')
		{
			$p = explode(':', $t);
			if (count($p) == 1)
			{
				$p = explode(' ', $p[0]);
				$event['duration_days'] = (int)$p[0];
				if (count($p) > 1) $event['duration_minutes'] = 60*(int)$p[1];
			}
			else
			{
				$event['duration_minutes'] = (int)$p[1];
				$p = explode(' ', $p[0]);
				if (count($p) > 1) $event['duration_days'] = (int)$p[0];
				$event['duration_minutes'] += 60*(int)$p[(count($p) == 1) ? 0 : 1];
			}
		}
		
		foreach ($hec_year_types as $year_type)
		{
			$alt_year_type = str_replace('-', '_', $year_type);
			if ($_REQUEST['hec_encoded_day'][$alt_year_type] != '') $event['day_' . $year_type] = (int)$_REQUEST['hec_encoded_day'][$alt_year_type];
			if ($_REQUEST['hec_encoded_month'][$alt_year_type] != '') $event['month_' . $year_type] = (int)$_REQUEST['hec_encoded_month'][$alt_year_type];
		}	
	}
	
	if ($occurences_mb)
	{
		if ($_REQUEST['hec_occurence_limit'] != '') $event['occurence_limit'] = (int)$_REQUEST['hec_occurence_limit'];
		if ($_REQUEST['hec_day_limit'] != '') $event['day_limit'] = (int)$_REQUEST['hec_day_limit'];

		if (isset($_REQUEST['hec_notes']))
		{
			$show = (isset($_REQUEST['hec_show'])) ? $_REQUEST['hec_show'] : array();
			foreach ($_REQUEST['hec_notes'] as $jd => $notes)
			{
				if (!isset($show[$jd])) $event['hide_' . $jd] = 1;
				if ($notes != '') $event['notes_' . $jd] = $notes;
			}
		}
	}
	
	if (empty($event))
		delete_post_meta($post_ID, '_hec_event');
	else
		update_post_meta($post_ID, '_hec_event', $event);
  
	return $post_ID;
}


function hec_register_meta_box() {
	global $hec_options;
	foreach ($hec_options['post_types'] as $post_type => $show)
		if ($show) 
		{
			add_meta_box('hec-event-mb', 'Hebrew Event', 'hec_event_mb', $post_type, 'side');
			add_meta_box('hec-occurences-mb', 'Hebrew Event Occurences', 'hec_occurences_mb', $post_type, 'normal');
		}
}

function hec_event_mb() {
	global $post, $hec_weekdays, $hec_months, $hec_year_types;
	// Use nonce for verification
	wp_nonce_field( 'hec_update_event', 'hec_event_mb_nonce' );

	$data = get_post_meta($post->ID, '_hec_event', true);
	$event = ($data == '') ? array() : $data;
	
	?>

	<table>

		<tr>
			<th class="left"><label for="hec_start_date">Start Date</label></th>
			<td><input name="hec_start_date" type="text" id="hec_start_date" value="<?php if (isset($event['start_date'])) echo strftime('%m/%d/%Y', jdtounix($event['start_date'])); ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_stop_date">Stop Date</label></th>
			<td><input name="hec_stop_date" type="text" id="hec_stop_date" value="<?php if (isset($event['stop_date'])) echo strftime('%m/%d/%Y', jdtounix($event['stop_date'])); ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_time">Time</label></th>
			<td><input name="hec_time" type="text" id="hec_time" value="<?php echo hec_format_time($event); ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_weekday">Weekday</label></th>
			<td>
				<select name="hec_weekday" id="hec_weekday">
					<option value="">None</option>
					<?php
						foreach ($hec_weekdays as $i => $name)
						{
							echo '<option value="' . $i . '"';
							if ($event['weekday'] == $i) echo ' selected="selected"';
							echo '>' . $name . '</option>';
						}  ?>
				</select>
			</td>
		</tr>

		<tr>
			<th><label for="hec_week">Week</label></th>
			<td><input name="hec_week" type="text" id="hec_week" value="<?php echo $event['week']; ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_day">Day</label></th>
			<td><input name="hec_day" type="text" id="hec_day" value="<?php echo $event['day']; ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_month">Month</label></th>
			<td>
				<select name="hec_month" id="hec_month">
					<option value="">None</option>
					<?php
					foreach ($hec_months as $i => $name)
					{
						echo '<option value="' . $i . '"';
						if ($event['month'] == $i) echo ' selected="selected"';
						echo '>' . $name . '</option>';
					}  ?>
				</select>
			</td>
		</tr>

		<tr>
			<th><label for="hec_year">Year</label></th>
			<td><input name="hec_year" type="text" id="hec_year" value="<?php echo $event['year']; ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_sunrise">Sunrise</label></th>
			<td><input name="hec_sunrise" type="checkbox" id="hec_sunrise" <?php if ($event['sunrise']) echo ' checked'; ?>/></td>
		</tr>

		<tr>
			<th><label for="hec_duration">Duration</label></th>
			<td><input name="hec_duration" type="text" id="hec_duration" value="<?php echo trim($event['duration_days'] . ((isset($event['duration_minutes'])) ? sprintf(' %u:%02u',floor($event['duration_minutes'] / 60), $event['duration_minutes'] % 60) : '')); ?>"/></td>
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
					<td><input name="hec_encoded_day[<?php echo str_replace('-', '_', $year_type);?>]" type="text" value="<?php echo $event['day_' . $year_type]; ?>" size="4"/></td>
					<td>
						<select name="hec_encoded_month[<?php echo str_replace('-', '_', $year_type);?>]">
							<option value="">None</option>
							<?php foreach ($hec_months as $i => $name) :
								if ($i < 0) : ?>
									<option value="<?php echo abs($i); ?>" <?php if ($event['month_' . $year_type] == abs($i)) echo ' selected="selected"'; ?> >
										<?php echo $name; ?>
									</option>
								<?php endif; endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table><?php
}
			
function hec_occurences_mb() {
	global $post, $hec_weekdays, $hec_months, $hec_year_types, $hec_options;//$hec_occurence_limit, $hec_day_limit;
	// Use nonce for verification
	wp_nonce_field( 'hec_update_occurences', 'hec_occurences_mb_nonce' );

	$data = get_post_meta($post->ID, '_hec_event', true);
	$event = ($data == '') ? array() : $data;
	$day_limit = (isset($event['day_limit'])) ? $event['day_limit'] : $hec_options['day_limit'];
	$occurences = hec_get_occurences(unixtojd(),  $day_limit, true, $post->ID);
	// Use nonce for verification
	//wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
	?>
	<table>
		<tr>
			<th><label for="hec_occurence_limit">Occurence Limit</label></th>
			<td><input name="hec_occurence_limit" type="text" id="hec_occurence_limit" value="<?php echo $event['occurence_limit']; ?>"/></td>
		</tr>

		<tr>
			<th><label for="hec_day_limit">Day Limit</label></th>
			<td><input name="hec_day_limit" type="text" id="hec_day_limit" value="<?php echo $event['day_limit']; ?>"/></td>
		</tr>

	</table>
	<?php if (empty($occurences)) : ?>
	<p>No occurences in the next <?php echo $day_limit; ?> days.</p>
	<?php else: ?>
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

		<tbody>
		<?php foreach ($occurences as $occurence) {
			$jd = unixtojd($occurence->start);
			echo '<tr><td><input name="hec_show[' . $jd . ']" type="checkbox"'. (($event['hide_' . $jd]) ? '' : ' checked="yes"') .'/></td><td>' .
			strftime('%m/%d/%Y', $occurence->start) .
			'</td><td><input size="40" name="hec_notes[' . $jd . ']" type="text" value="' . $event['notes_' . $jd] . '"/></td></tr>'; }
		?>
		</tbody>
	</table><?php endif; 
}

function hec_is_jewish_leap($year)
{
	$year = $year % 19;
	return ($year == 0) || ($year == 3) || ($year == 6) || ($year == 8) || ($year == 11) || ($year == 14) || ($year == 17);
}

function hec_get_year_type($year)
{
	global $hec_rosh_hashanah_labels;
	global $hec_year_labels;
	return ((((($year*7)+1) % 19) < 7) ? 'Mem' : 'Pei') . $hec_rosh_hashanah_labels[jddayofweek(jewishtojd(1, 1, $year))] . $hec_year_labels[jewishtojd(1, 1, $year+1) - jewishtojd(1, 1, $year)];
}

function hec_admin_init() { // whitelist options
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
	register_setting('hec_options', 'hec_options', 'hec_sanitize_options');
	add_settings_section('hec_settings', 'Main Settings', 'hec_settings_section_text', 'hec_options');
	//add_settings_field('hec_latitude', 'Latitude', 'hec_latitude_field', 'hec_options', 'hec_settings');  
	add_settings_field('hec_latitude', 'Latitude', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'latitude', 'label_for' => 'hec_latitude'));
	add_settings_field('hec_longitude', 'Longitude', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'longitude', 'label_for' => 'hec_longitude'));  
	add_settings_field('hec_sunset_zenith', 'Sunset Zenith', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'sunset_zenith', 'label_for' => 'hec_sunset_zenith'));  
	add_settings_field('hec_sunrise_zenith', 'Sunrise Zenith', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'sunrise_zenith', 'label_for' => 'hec_sunrise_zenith'));  
	add_settings_field('hec_occurence_limit', 'Occurence Limit', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'occurence_limit', 'label_for' => 'hec_occurence_limit'));  
	add_settings_field('hec_day_limit', 'Day Limit', 'hec_text_options_field', 'hec_options', 'hec_settings', array('name' => 'day_limit', 'label_for' => 'hec_day_limit'));  
	add_settings_section('hec_ics', 'ICS Feed Settings', 'hec_ics_section_text', 'hec_options');
	add_settings_field('hec_ics_permalink', 'Permalink', 'hec_text_options_field', 'hec_options', 'hec_ics', array('name' => 'ics_permalink', 'label_for' => 'hec_ics_permalink'));  
	add_settings_field('hec_ics_title', 'Title', 'hec_text_options_field', 'hec_options', 'hec_ics', array('name' => 'ics_title', 'label_for' => 'hec_ics_title'));  
	add_settings_field('hec_ics_description', 'Description', 'hec_text_options_field', 'hec_options', 'hec_ics', array('name' => 'ics_description', 'label_for' => 'hec_ics_description'));  
	//add_settings_field('hec_post_types', 'Post Types', 'hec_sunset_zenith_field', 'hec_options', 'hec_settings');  
	add_settings_section('hec_post_types', 'Post Types', 'hec_post_types_section_text', 'hec_options');
	foreach (get_post_types(array('public' => 1, 'show_ui' => 1), 'objects') as $post_type)
		add_settings_field('hec_' . $post_type->name, $post_type->labels->name, 'hec_checkbox_options_field', 'hec_options', 'hec_post_types', array('name' => $post_type->name, 'label_for' => 'hec_' . $post_type->name));  
}

function hec_sanitize_options($options) {
	$options['latitude'] = floatval($options['latitude']);
	$options['longitude'] = floatval($options['longitude']);
	$options['sunset_zenith'] = floatval($options['sunset_zenith']);
	$options['sunrise_zenith'] = floatval($options['sunrise_zenith']);
	$options['occurence_limit'] = intval($options['occurence_limit']);
	$options['day_limit'] = intval($options['day_limit']);
	return $options;
}

function hec_ics_section_text() {  
	echo '<p>Settings for the ICS feed.</p>';  
}

function hec_settings_section_text() {  
	echo '<p>Main settings for Hebrew events.</p>';  
}

function hec_post_types_section_text() {  
	echo '<p>Post Types with Hebrew event data.</p>';  
}

function hec_text_options_field($args) {  
	global $hec_options;
	echo '<input id="' . $args['label_for'] . '" name="hec_options[' . $args['name']. ']" size="40" type="text" value="' . $hec_options[$args['name']] . '" />';
} 

function hec_checkbox_options_field($args) {  
	global $hec_options;
	echo '<input id="' . $args['label_for'] . '" name="hec_options[post_types][' . $args['name']. ']" type="checkbox"';
	if ($hec_options['post_types'][$args['name']]) echo ' checked';
	echo '/>';
} 

function hec_admin_menu() {
	//add_menu_page('Events', 'Events', 'publish_pages', 'hec_events', 'hec_events_page', '', 11);
	//add_submenu_page('hec_events', 'Add New Event', 'Add New', 'publish_pages', 'hec_edit_event', 'hec_edit_event_page');
	add_options_page('Hebrew Event Options', 'Hebrew Events Calendar', 'manage_options', 'hec_options', 'hec_options_page');
}


function hec_options_page()
{
	//global /*$hec_post_types, $hec_latitude, $hec_longitude, $hec_sunrise_zenith, $hec_sunset_zenith, $hec_occurence_limit, $hec_day_limit*/;
	echo '<div class="wrap">';
	echo '<h2>Hebrew Events Calendar Options</h2>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'hec_options' );
	do_settings_sections('hec_options');
	/*echo '<table class="form-table">';
	echo '<tr valign="top"><th scope="row">Latitude</th><td><input type="text" name="hec_latitude" value="' . $hec_latitude . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Longitude</th><td><input type="text" name="hec_longitude" value="' . $hec_longitude . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Sunset Zenith</th><td><input type="text" name="hec_sunset_zenith" value="' . $hec_sunset_zenith . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Sunrise Zenith</th><td><input type="text" name="hec_sunrise_zenith" value="' . $hec_sunrise_zenith . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Occurence Limit</th><td><input type="text" name="hec_occurence_limit" value="' . $hec_occurence_limit . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Day Limit</th><td><input type="text" name="hec_day_limit" value="' . $hec_day_limit . '"/></td></tr>';
	echo '<tr valign="top"><th scope="row">Calendar ICS Permalink</th><td><input type="text" name="hec_calendar_ics" value="' . get_option('hec_calendar_ics', 'calendar.ics') . '"/></td></tr>';
	foreach (get_post_types(array('public' => 1, 'show_ui' => 1), 'objects') as $post_type)
	{
		echo '<tr valign="top"><th scope="row">' . $post_type->labels->name . '</th><td><input type="checkbox" name="hec_post_types[' . $post_type->name . ']"';
		if ($hec_post_types[$post_type->name]) echo ' checked';
		echo '/></td></tr>';
	}
	echo '</table>';*/
	echo '<p class="submit"><input type="submit" class="button-primary" value="Save Changes"/></p></form></div>';
}


function hec_get_occurences($jd, $count, $show_all=false, $post_id=null, $first_only=false, $start_only=true)
{
	global $post, $hec_options;

	/*ini_set('date.default_latitude', get_option('hec_latitude', ini_get('date.default_latitude')));
	ini_set('date.default_longitude', get_option('hec_longitude', ini_get('date.default_longitude')));
	ini_set('date.sunrise_zenith', get_option('hec_sunrise_zenith', ini_get('date.sunrise_zenith')));
	ini_set('date.sunset_zenith', get_option('hec_sunset_zenith', ini_get('date.sunset_zenith')));
	date_default_timezone_set(get_option('timezone_string'));*/
	$times = array();
	$post_types = array();
	foreach ($hec_options['post_types'] as $post_type => $show)
		if ($show) $post_types[] = $post_type;
	$query_args = array( 'post_type' => $post_types, 'meta_key' => '_hec_event' , 'nopaging' => true);
	if (!is_null($post_id)) $query_args['post__in'] = array($post_id);
	$the_query = new WP_Query( $query_args );
	
	if ($the_query->have_posts() == 0) return array();
	
	for ($i=($first_only || $start_only) ? 0 : -9; $i<$count; $i++)
	{
		$j = explode('/', jdtogregorian($jd+max($i,0)));
		$month0 = (int)$j[0];
		$day0 = (int)$j[1];
		$year0 = (int)$j[2];

		$j = explode('/', jdtogregorian($jd+$i));
		$month = (int)$j[0];
		$day = (int)$j[1];
		$year = (int)$j[2];
		$week = ceil($day/7);

		$j = explode('/', jdtojewish($jd+$i));
		$jmonth0 = (int)$j[0];
		$jday0 = (int)$j[1];
		$jyear0 = (int)$j[2];
		$jweek0 = ceil($jday0/7);
		$jleap0 = hec_is_jewish_leap($jyear0);
		$jtype0 = hec_get_year_type($jyear0);
		if (!$jleap0 && ($jmonth0 == 6)) $jmonth0 = 7;

		$j = explode('/', jdtojewish($jd+$i+1));
		$jmonth1 = (int)$j[0];
		$jday1 = (int)$j[1];
		$jyear1 = (int)$j[2];
		$jweek1 = ceil($jday1/7);
		$jleap1 = hec_is_jewish_leap($jyear1);
		$jtype1 = hec_get_year_type($jyear1);
		if (!$jleap1 && ($jmonth1 == 6)) $jmonth1 = 7;

		$midnight = mktime(0, 0, 0, $month, $day, $year);
		$midnight0 = mktime(0, 0, 0, $month0, $day0, $year0);
		$sunset0 = date_sunset($midnight-1, SUNFUNCS_RET_TIMESTAMP);
		$sunset1 = date_sunset($midnight, SUNFUNCS_RET_TIMESTAMP);
		$weekday = jddayofweek($jd+$i);

		$the_query->rewind_posts();
		while ($the_query->have_posts())  {
			$the_query->the_post();
			$event =  get_post_meta($post->ID, '_hec_event', true);

			if ((isset($event['start_date']) && (($jd+$i)<$event['start_date'])) || (isset($event['stop_date']) && (($jd+$i)>$event['stop_date']))) continue;
			if (!$show_all)
				if ($event['hide_' . ($jd+$i)]) continue;

			$j0 = true;
			$j1 = true;

			if (isset($event['year']))
			{
				if ($event['year'] < 5000)
				{
					if ($event['year'] != $year) continue;
				}
				else
				{
					$j0 = ($j0 && ($event['year'] == $jyear0));
					$j1 = ($j1 && ($event['year'] == $jyear1));
					if (!$j0 && !$j1) continue;
				}
			}
	
			if (isset($event['month']))
			{
				if ($event['month'] < 0)
				{
					$j0 = ($j0 && (-$event['month'] == $jmonth0));
					$j1 = ($j1 && (-$event['month'] == $jmonth1));
					if (!$j0 && !$j1) continue;
				}
				else if ($event['month'] != $month) continue;
			}
	
			if (isset($event['day']))
			{
				if ($event['month'] < 0)
				{
					$j0 = ($j0 && ($event['day'] == $jday0));
					$j1 = ($j1 && ($event['day'] == $jday1));
					if (!$j0 && !$j1) continue;
				}
				else if ($event['day'] != $day) continue;
			}
	
			if (isset($event['week']))
			{
				if ($event['month'] < 0)
				{
					$j0 = ($j0 && ($event['week'] == $jweek0));
					$j1 = ($j1 && ($event['week'] == $jweek1));
					if (!$j0 && !$j1) continue;
				}
				else if ($event['week'] != $week) continue;
			}
	
			if (isset($event['weekday']))
			{
				if ($event['weekday'] < 0)
				{
					$j0 = ($j0 && ((-$event['weekday']-1) == $weekday));
					$j1 = ($j1 && (((-$event['weekday']+5) % 7) == $weekday));
					if (!$j0 && !$j1) continue;
				}
				else if ($event['weekday']-1 != $weekday) continue;
			}

			if (array_key_exists('month_' . $jtype0, $event))
				$j0 = ($j0 && ($event['month_' . $jtype0] == $jmonth0));

			if (array_key_exists('month_' . $jtype1, $event))
				$j1 = ($j1 && ($event['month_' . $jtype1] == $jmonth1));

			if (array_key_exists('day_' . $jtype0, $event))
				$j0 = ($j0 && ($event['day_' . $jtype0] == $jday0));

			if (array_key_exists('day_' . $jtype1, $event))
				$j1 = ($j1 && ($event['day_' . $jtype1] == $jday1));

			if (!$j0 && !$j1) continue;
		
			$hebrew = (isset($event['time_offset']) && (count($event['months']) > 0 || count($event['days']) > 0	|| $event['weekday'] < 0 || ($event['month'] < 0 && isset($event['day']))));
			$offset = ((is_null($event['time'])) ? 0 : ((int)$event['time']*60));

			if ($event['sunrise'] && $hebrew) $j1 = false; 
			if (!$j0 && !$j1) continue;
	
			$t = (($event['sunrise']) ? date_sunrise($midnight, SUNFUNCS_RET_TIMESTAMP) :
			(($hebrew) ? (($j0) ? $sunset0 : $sunset1) : $midnight)) + $offset;

			$start_in_range = ($t >= $midnight && $t < ($midnight + 86400));
			if ($start_in_range && $first_only) return $t;
			
			$stop = null;
			if (isset($event['duration_days']) || isset($event['duration_minutes']))
			{
				$stop = ((is_null($event['duration_days'])) ?
					$t :
					((($hebrew) ?
						date_sunset((($j0) ? $midnight-1 : $midnight) + 86400*$event['duration_days'], SUNFUNCS_RET_TIMESTAMP) :
						($t + 86400*$event['duration_days'])) + $offset));
				if (isset($event['duration_minutes'])) $stop += 60*(int)$event['duration_minutes'];
			}

			if (($start_only && $start_in_range)
				|| (!$start_only && ($j1 || $event['sunrise']) && ((is_null($stop) && $t >= $midnight0 && $t < ($midnight0 + 86400)) || (isset($stop) && $t < ($midnight0 + 86400) && $stop >= $midnight0))))
			{
				$title = the_title_attribute(array(
					'echo' => 0,
					'before' => (!$start_only && $hebrew && $j1 && $i>-1 && isset($event['duration_days'])) ? 'Erev ' : ''));
				$notes = $event['notes_' . ($jd+$i)];
				if ($notes == '') $notes = null;
				$times[] = new hec_occurrence($post->ID, $title, $notes, $t, $stop);
			}
		}
	}
	if ($first_only) return array();
	usort($times, 'hec_o_cmp');
	wp_reset_postdata();
	return $times;
}

class hec_occurrence
{
	function __construct($post_id, $title, $notes, $start, $stop)
	{
		//$this->link = $link;
		//$this->description = $description;
		$this->notes = $notes;
		$this->erev = $erev;
		$this->title = $title;
		$this->start = $start;
		$this->post_id = $post_id;
		$this->stop = $stop;
	}
}

function hec_o_cmp($a, $b)
{
	if ($a->start == $b-> start) return 0;
	return ($a->start < $b->start) ? -1 : 1;
}

function hec_post_events($content)
{
	global $occurence_range, $hec_options;//, $hec_occurence_limit, $hec_day_limit;	

	if ($hec_options['post_types'][get_post_type()]) {	
		$event =  get_post_meta(get_the_ID(), '_hec_event', true);
		if ($event != '') {
			$occurence_limit = (isset($event['occurence_limit'])) ? $event['occurence_limit'] : $hec_options['occurence_limit'];
			if ($occurence_limit > 0) {
				$occurences = hec_get_occurences(unixtojd(), (isset($event['day_limit'])) ? $event['day_limit'] : $hec_options['day_limit'], false, get_the_ID());
				if (count($occurences) == 0) return $content;
				
				$content .= '<h3>Upcoming Occurences</h3><table><tr><th>Date</th><th>Time</th><th>Notes</th></tr>';

				end($occurences);
				while ((count($occurences) > $occurence_limit) && is_null(current($occurences)->notes))
				{
					unset($occurences[key($occurences)]);
					end($occurences);
				}

				foreach ($occurences as $occurence)
				{
					$content .= '<tr><td>' . strftime('%A, %B %e, %Y</td><td>%l:%M %P', $occurence->start) . '</td><td>' .
						$occurence->notes . '</td></tr>';
				}
				
				return $content . '</table>';
			}
		}
	}
	
	return $content;
}

function hec_ics_link() {
	global $hec_options;
	$url = home_url($hec_options['ics_permalink']);
	return 'Subscribe to calendar using <a href="' . str_replace('http:', 'webcal:', $url) . '">Webcal (Outlook, Apple iCal, etc.)</a> or <a title="Add to Google Calendar" href="http://www.google.com/calendar/render?cid=' . urlencode($url) . '">Google Calendar</a>.';
}

class hec_events_widget extends WP_Widget {
	function hec_events_widget() {
		$widget_ops = array('classname' => 'hec_events_widget', 'description' => 'Upcoming events.' );
		$this->WP_Widget('hec_events_widget', __('Hebrew Events Widget'), $widget_ops);
	}
 
	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
 
		//date_default_timezone_set(get_option('timezone_string'));

		echo $before_widget;
		echo $before_title . $instance['title'] . $after_title;
		echo '<ul>';
		$jd = unixtojd();
		for ($i = 0; $i < $instance['days']; $i++)
		{
			$occurences = hec_get_occurences($jd+$i, 1, /*$show_all*/ false, /*$post_id*/ null, /*$first_only*/ false, /*$start_only*/false);
			if (count($occurences) > 0)
			{
				echo '<li>' . JDDayOfWeek($jd+$i,1) . '';
				foreach ($occurences as $occurence)
				{
					echo '<div title="' . $occurence->title;
					//if (!is_null($occurence->description)) echo ' — ' . $occurence->description;
					echo '">' . ((unixtojd($occurence->start) < $jd+$i) ? (($jd+$i == unixtojd($occurence->stop)) ? 'Until ' . hec_format_time2($occurence->stop) : 'All day') : hec_format_time2($occurence->start)) . ' — ';
					$c = $occurence->title;
					if (!is_null($occurence->notes)) $c .= ' (' . $occurence->notes . ')';
					echo '<a href="' . get_permalink($occurence->post_id) . '">' . $c . '</a>';
					echo '</div>';
				}
				echo '</li>';
			}
		}
		echo '</ul>';
		echo '<div style="margin-top:10pt;">' . hec_ics_link() . '</div>';
		echo $after_widget;
	}
 
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$new_instance = wp_parse_args((array) $new_instance, array('days' => 7, 'title' => 'Upcoming Events'));
		$instance['days']			= $new_instance['days'];
		$instance['title']			= $new_instance['title'];
		return $instance;
	}
 
	function form($instance) {
		$instance = wp_parse_args((array) $instance,
			array('days' => 7, 'title' => 'Upcoming Events'));
		$days = $instance['days'];
		$title = $instance['title'];
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<p><label for="<?php echo $this->get_field_id('days'); ?>"><?php _e('Days:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('days'); ?>" name="<?php echo $this->get_field_name('days'); ?>" type="text" value="<?php echo esc_attr($days); ?>" /></p>
<?php
	}
}

function hec_calendar_sc($atts, $content = null) {
	global $hec_months;
		extract(shortcode_atts(array(
		), $atts));

	if (isset($_REQUEST['d']))
	{
		$d = explode('/', $_REQUEST['d']);
		$month = $d[0];
		$year = $d[1];
	}
	else
	{
		$d = getdate();
		$month = $d['mon'];
		$year = $d['year'];
	}
	$md = cal_days_in_month(CAL_GREGORIAN, $month, $year);
	$jd = gregoriantojd($month, 1, $year);
	$dw = jddayofweek($jd);
	$weeks = ceil(($md + $dw) / 7);
	$jd -= $dw;

	$r = "<table style=\"border-style:none;\"><tr><td style=\"border-style:none;width:5%;\"><a href=\"?d=" . ((($month+10) % 12) + 1) . "/" .
		(($month == 1) ? $year-1 : $year) . "\">&lt;&lt; Previous Month</a></td><td style=\"border-style:none;width:5%;\"><h3 style=\"text-align:center;\">$hec_months[$month] $year</h3></td><td style=\"border-style:none;text-align:right;width:5%;\"><a href=\"?d=" .
		(($month % 12) + 1) . "/" . (($month == 12) ? $year+1 : $year) . "\">Next Month &gt;&gt;</a></td></tr></table>"
		. '<table><thead><tr><th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th><th>Saturday</th></tr></thead><tbody>';

	for ($week = 0; $week < $weeks; $week++)
	{
		$r .= '<tr>';
		for ($i = 0; $i < 7; $i++, $jd++)
		{
			$d = cal_from_jd($jd, CAL_GREGORIAN);
			$dj = cal_from_jd($jd, CAL_JEWISH);
			$r .= '<td style="font-size:80%;width:5%; vertical-align:top; text-align:center; "><div style="align:center;font-weight:bold;">' . $d['day'] . ' (' . $dj['day'] . ' ' . $hec_months[(!hec_is_jewish_leap($dj['year']) && $dj['month'] == 6) ? 7 : -$dj['month']] . ')</div>';
			$j = 0;
			//$sep = '';
			//$r .= '<dl>';
			foreach (hec_get_occurences($jd, 1, /*$show_all*/ false, /*$post_id*/ null, /*$event_id*/ null, /*$first_only*/ false, /*$start_only*/false) as $occurence)
			{
				$r .= '<hr style="margin-left:5%;margin-right:5%;"/><div';// style="border-top:1px dotted;"';
				//$r .= '<li' .
				$has_note = !is_null($occurence->notes);
				$has_desc = false;//!is_null($occurence->description);
				if ($has_note && $has_desc)
					$r .= ' title="' . $occurence->description . ' ('. $occurence->notes . ')"';
				else if ($has_note && !$has_desc)
					$r .= ' title="' . $occurence->notes . '"';
				else if (!$has_note && $has_desc)
					$r .= ' title="' . $occurence->description . '"';
				$r .= '><div style="font-style:italic;">';
				if ($jd > unixtojd($occurence->start))
					$r .= ($jd == unixtojd($occurence->stop)) ? 'until ' . hec_format_time2($occurence->stop) : 'all day';
				else if (!is_null($occurence->stop) && unixtojd($occurence->stop) == unixtojd($occurence->start))
					$r .= hec_format_time2($occurence->start) . '–' . hec_format_time2($occurence->stop);
				else
					$r .= hec_format_time2($occurence->start);
				$r .= '</div><div><a href="' . get_permalink($occurence->post_id) . '">' . $occurence->title . '</a></div></div>';
				//$sep = ', ';
				/*$r .= '<div style="margin-top:2px;border:1px dotted;padding:2px;"';
				$r .= '<span';
				$has_note = isset($event->event->occurences[$jd]) && ($event->event->occurences[$jd] != '');
				$has_desc = !is_null($event->event->description);
				if ($has_note && $has_desc)
					$r .= ' title="' . $event->event->description . ' ('. $event->event->occurences[$jd] . ')"';
				else if ($has_note && !$has_desc)
					$r .= ' title="' . $event->event->occurences[$jd] . '"';
				else if (!$has_note && $has_desc)
					$r .= ' title="' . $event->event->description . '"';
				$r .= '><div><b>' . strftime('%l:%M %p', $event->start) . '</b></div><div>' . ((is_null($event->event->post_id)) ? $event->event->title : 
		 ('<a href="' . get_permalink($event->event->post_id) . '">' . get_post($event->event->post_id)->post_title . '</a>')) . '</div>';
				$r .= '</div>';*/
			}
			//$r .= '</dl>';
			$r .= '</td>';
		}
		$r .= '</tr>';
	}
	
	return $r . '</tbody></table>' . '<p style="text-align:center;">' . hec_ics_link(). '</p>';
}

?>