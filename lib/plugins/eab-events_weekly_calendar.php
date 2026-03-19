<?php
/*
Plugin Name: Wöchentlicher Veranstaltungskalender
Description: Erstellt einen wöchentlichen Kalender-Shortcode, der auf jeder Seite verwendet werden kann. Kalenderstart- und -endstunden, Intervallzeit kann ausgewählt werden. 
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.3
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Minimale Nutzung: [weekly_event_calendar] <br /> Erweiterte Nutzung: [weekly_event_calendar id="calendar1" class="weekly-calendar"] <br /><b>id</b> und <b>class</b> sind optional und definieren CSS-ID und Klassennamen. Die Einstellungen werden über die Felder auf dieser Seite unter vorgenommen <b>Wöchentlicher Veranstaltungskalender Einstellungen</b>.

*/

class Eab_CalendarTable_WeeklyEventArchiveCalendar {

	private $_data;
	protected $_events = array();
	protected $_current_timestamp;

	
	function __construct() {
		$this->_data = Eab_Options::get_instance();
		if ( !is_object( $this->_data ) ) {
			$this->_data = new Eab_Options;
		}
			
		// To follow WP Start of week setting
		if ( !$this->start_of_week = get_option('start_of_week') )
			$this->start_of_week = 0;
		$this->time_format = get_option('time_format'); // To follow WP Start of week setting
		$this->date_format = get_option('date_format'); // To follow WP Start of week setting
	}

	/**
	 * Run the Addon
	 *
	 */	
	public static function serve () {
		$me = new Eab_CalendarTable_WeeklyEventArchiveCalendar;
		$me->_add_hooks();
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 */	
	private function _add_hooks () {
		
		add_action('eab-settings-after_payment_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this,'save_settings'));
		add_filter( 'the_posts', array($this, 'load_styles') );
		add_shortcode('weekly_event_calendar', array($this,'shortcode'));
	}
	
	/**
	 * Load style only when they are necessary
	 * http://beerpla.net/2010/01/13/wordpress-plugin-development-how-to-include-css-and-javascript-conditionally-and-only-when-needed-by-the-posts/
	 */		
	function load_styles( $posts ) {
		if ( empty($posts) OR is_admin() ) 
			return $posts;
	
		$shortcode_found = false; // use this flag to see if styles and scripts need to be enqueued
		foreach ($posts as $post) {
			if (stripos($post->post_content, 'weekly_event_calendar') !== false) {
				$shortcode_found = true;
				break;
			}
		}
 
		if ($shortcode_found) 
			wp_enqueue_style('eab-events-weekly-calendar', EAB_PLUGIN_URL . "css/weekly-event-calendar.min.css" );
 
		return $posts;
	}	
	/**
	 * Returns the timestamp of Sonntag of the current week or selected date
	 *
	 */	
	function sunday( $timestamp=false ) {
	
		$date = $timestamp ? $timestamp : $this->get_local_time();
		$test = date( "l", $date );
		// Return today's timestamp if today is sunday
		if ( "Sonntag" == date( "l", $date ) ) {
			return strtotime( "today" );
		}
		// Else return last week's timestamp
		else {
			return strtotime( "last Sonntag", $date );
		}
	}
	
	function shortcode( $attr ) {
	
		$atts = shortcode_atts( array(
			'id'    => '',
			'class' => ''
		), $attr );
		$id    = sanitize_html_class( $atts['id'] );
		$class = sanitize_html_class( $atts['class'] );
		
		if ( isset( $_GET["wcalendar"] ) )
			$time = absint( $_GET["wcalendar"] );
		else
			$time = $this->get_local_time();
	
		global $post;
		$start_of_calendar = $this->sunday( $time ) + $this->start_of_week*86400;
		
		$c  = '';
		$c .= '<div id="primary">';
        $c .= '<div id="psourceevents-wrapper">';
        $c .= '<h2>'. sprintf(
            	__('Ereignisse von %s bis %s', 'eab'),
            	date_i18n($this->date_format, $start_of_calendar ), date_i18n($this->date_format, $start_of_calendar + 6*86400 ) 
				) .'</h2>';
        $c .= '<div class="psourceevents-list">';
 		$c .= $this->get_weekly_calendar($time, $id, $class);
		
		$c .= '<div class="event-pagination">';
		$prev = $time - (7*86400); 
		$next = $time + (7*86400);
		$c .= '<a href="'. add_query_arg( "wcalendar", $prev, get_permalink( $post->ID ) ) .'">' . __('Vorherige', 'eab') . '</a>';
		$c .= '<a href="'. add_query_arg( "wcalendar", $next, get_permalink( $post->ID ) ). '">' . __('Nächste', 'eab') . '</a>';
		$c .= '</div>';
			
		$c .= '</div>
			</div>
		</div>';
	$c .= '<script type="text/javascript">
			(function ($) {
				$(function () {
					$(".psourceevents-calendar-event").on("mouseenter", function () {
						$(this).find(".psourceevents-calendar-event-info").show();
					})
					.on("mouseleave", function () {
						$(this).find(".psourceevents-calendar-event-info").hide();
					});
				});
			})(jQuery);
			</script>
			';
		return $c;
	}
	
	
	protected function _get_text_domain () {
		return 'eab';
	}	
	
	public function get_timestamp () {
		return $this->_current_timestamp;
	}

	/**
	 * Gets local time
	 * 
	 */	
	public function get_local_time () {
			return current_time('timestamp');
	}
	/**
	 * Converts number of seconds to hours:mins acc to the WP time format setting
	 * 
	 */	
	public function secs2hours( $secs ) {
		$min = (int)($secs / 60);
		$hours = "00";
		if ( $min < 60 )
			$hours_min = $hours . ":" . $min;
		else {
			$hours = (int)($min / 60);
			if ( $hours < 10 )
				$hours = "0" . $hours;
			$mins = $min - $hours * 60;
			if ( $mins < 10 )
				$mins = "0" . $mins;
			$hours_min = $hours . ":" . $mins;			
		}
		if ( $this->time_format )
			$hours_min = date( $this->time_format, strtotime( $hours_min . ":00" ) );
			
		return $hours_min;
	}
	/**
	 * Arranges days array acc. to start of week, e.g 1234560 (Week starting with Montag)
	 * @ days: input array, @ prepend: What to add as first element
	 */	
	public function arrange( $days, $prepend ) {
		if ( $this->start_of_week ) {
			for ( $n = 1; $n<=$this->start_of_week; $n++ )
				array_push( $days, array_shift( $days ) );
		}

		array_unshift( $days, $prepend );
	
		return $days;
	}

	public function get_weekly_calendar ( $timestamp=false, $id='', $class='' ) {
	
		$options = Eab_Options::get_instance();
		
		if ( !is_object( $options ) )
			$options = new Eab_Options;

		$timestamp = $timestamp ? $timestamp : $this->get_local_time();
		$year = date("Y", $timestamp);
		$month = date("m", $timestamp);
		
		//$query = Eab_CollectionFactory::get_upcoming(strtotime("{$year}-{$month}-01 00:00"));
		$query = Eab_CollectionFactory::get_upcoming_weeks(strtotime("{$year}-{$month}-01 00:00"));
		$this->_events = $query->posts;
		
		$date = $timestamp ? $timestamp : $this->get_local_time();
		
		$sunday = $this->sunday( $date ); // Timestamp of first Sonntag of any date

		if ( !$start = $options->get_option('weekly_calendar_start') OR $start > 23 )
			$start = 10; // Set a default working time start
		$first = $start *3600 + $sunday; // Timestamp of the first cell of first Sonntag
		
		if ( !$end = $options->get_option('weekly_calendar_end') OR $end < 1 )
			$end = 24; // Set a default working time end
		$last = $end *3600 + $sunday; // Timestamp of the last cell of first Sonntag
		
		if ( !$interval = $options->get_option('weekly_calendar_interval') OR $interval < 10 OR $interval > 60 * 12 )
			$interval = 120; // Set a default interval in minutes
		$step = $interval * 60; // Timestamp increase interval to one cell below
		
		$days = $this->arrange( array(0,1,2,3,4,5,6), -1 ); // Arrange days acc. to start of week
		
		$post_info = array();
		foreach ($this->_events as $event) {
			$post_info[] = $this->_get_item_data($event);
		}
		
		$tbl_id = $id;
		$tbl_id = $tbl_id ? "id='{$tbl_id}'" : '';
		$tbl_class = $class;
		$tbl_class = $tbl_class ? "class='{$tbl_class}'" : '';
		
		$ret = '';
		$ret .= "<table width='100%' {$tbl_id} {$tbl_class}>";
		$ret .= $this->_get_table_meta_row('thead');
		$ret .= '<tbody>';
		
		$ret .= $this->_get_first_row();
		
		$todays_no = date("w", $this->get_local_time () ); // Number of today
		
		for ( $t=$first; $t<$last; $t=$t+$step ) {
			foreach ( $days as $key=>$i ) {
                            // Not sure if it's current fix, but it works!
                            if( $i == 0 ) $i = 7;
				if ( $i == -1 ) {
					$from = $this->secs2hours( $t - $sunday );
					$to = $this->secs2hours( $t - $sunday + $step );
					$ret .= "<td class='psourceevents-weekly-calendar-hours-mins'>".$from." - ".$to."</td>";
				}
				else {
					$current_cell_start = $t + $i * 86400; 
					$current_cell_end = $current_cell_start + $step;
					
					$this->reset_event_info_storage();
					foreach ($post_info as $ipost) {
						$count = count($ipost['event_starts']);
						for ($k = 0; $k < $count; $k++) {
							$start = strtotime($ipost['event_starts'][$k]);
							$end = strtotime($ipost['event_ends'][$k]);
							if ($start < $current_cell_end && $end > $current_cell_start) {
								if ( $options->get_option('weekly_calendar_display') )
									$this->set_event_info_author(
										array('start' => $start, 'end'=> $end), 
										array('start' => $current_cell_start, 'end'=> $current_cell_end),
										$ipost
									);
								else
									$this->set_event_info(
										array('start' => $start, 'end'=> $end), 
										array('start' => $current_cell_start, 'end'=> $current_cell_end),
										$ipost
									);
							}
						}
					}
					$activity = $this->get_event_info_as_string($i);
					$now = $todays_no == $i ? 'class="today"' : '';
					if ( ( $this->get_local_time() < $current_cell_end && $this->get_local_time() > $current_cell_start) )
						$now = 'class="now"';
					$ret .= "<td {$now}>{$activity}</td>";
				}
			}
			$ret .= '</tr><tr>'; // Close the last day of the week
		}
		
		$ret .= $this->_get_last_row();
		
		$ret .= '</tbody>';
		$ret .= $this->_get_table_meta_row('tfoot');
		$ret .= '</table>';
		
		return $ret;			
	}
	
	protected function _get_item_data ($post) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		$event_starts = $event->get_start_dates();
		$event_ends = $event->get_end_dates();
		$user_data = get_userdata( $event->get_author() );
		if ( $user_data->display_name )
			$author_name = $user_data->display_name;
		else if ( $user_data->first_name OR $user_data->last_name )
			$author_name = $user_data->first_name . " " . $user_data->last_name;
		else
			$author_name = $user_data->user_login;
		$res = array(
			'id' => $event->get_id(),
			'title' => $event->get_title(),
			'event_starts' => $event_starts,
			'event_ends' => $event_ends,
			'status_class' => Eab_Template::get_status_class($event),
			'event_venue' => $event->get_venue_location(),
			'event_author' => $author_name,
			'author_avatar'	=> get_avatar( $user_data->ID, 72 ),
			'author_bio'	=> $user_data->description,
			'event_content'	=> strip_shortcodes( $event->get_content() )
			
		);
		if (isset($post->blog_id)) $res['blog_id'] = $post->blog_id;
		return $res;
	}

	protected function _get_table_meta_row ($which) {
		$day_names_array = $this->arrange( $this->get_day_names(), __(' ', $this->_get_text_domain()) );
		$cells = '<th>' . join('</th><th>', $day_names_array) . '</th>';
		return "<{$which}><tr>{$cells}</tr></{$which}>";
	}
	
	public function get_day_names () {
		return array(
			__('Sonntag', $this->_get_text_domain()),
			__('Montag', $this->_get_text_domain()),
			__('Dienstag', $this->_get_text_domain()),
			__('WMittwoch', $this->_get_text_domain()),
			__('Donnerstag', $this->_get_text_domain()),
			__('Freitag', $this->_get_text_domain()),
			__('Samstag', $this->_get_text_domain()),
		);
	}
	
	public function set_event_info_author ($event_tstamps, $current_tstamps, $event_info) {
		$this->_data[] = '<a class="psourceevents-calendar-event ' . $event_info['status_class'] . '" href="' . get_permalink($event_info['id']) . '">' . 
			$event_info['title'] .
			'<span class="psourceevents-calendar-event-info">' . 
				"<span class='psourceevents-calendar-avatar'>". $event_info['author_avatar'] . "</span>" .
				"<span class='psourceevents-calendar-author'>" . $event_info['event_author'] . "</span><br />".
				"<span class='psourceevents-calendar-bio'>". wp_trim_words( $event_info['author_bio'], 20  ). "</span>" .
				"<span style='clear:both'></span>" .
			'</span>' . 
		'</a>'; 
	}
	
	public function set_event_info ($event_tstamps, $current_tstamps, $event_info) {
		$this->_data[] = '<a class="psourceevents-calendar-event ' . $event_info['status_class'] . '" href="' . get_permalink($event_info['id']) . '">' . 
			$event_info['title'] .
			'<span class="psourceevents-calendar-event-info">' . 
				"<span class='psourceevents-calendar-thumbnail'>". get_the_post_thumbnail( $event_info['id'], 'medium' ) . "</span>" .
				"<span class='psourceevents-calendar-start'>" . date_i18n(get_option('date_format'), $current_tstamps['start']) . "</span>".
				"<span class='psourceevents-calendar-venue'>" . $event_info['event_venue'] . "</span>".
				"<span class='psourceevents-calendar-content'>". wp_trim_words( $event_info['event_content'], 20  ). "</span>" .
				"<span style='clear:both'></span>" .
			'</span>' . 
		'</a>'; 
	}
	
	public function get_event_info_as_string ($day) {
		$activity = '';
		if ($this->_data) {
			$activity = '<p>' . join(' ', $this->_data) . '</p>';
		}
		return $activity;
	}
	
	protected function _get_first_row () { return ''; }
	protected function _get_last_row () { return ''; }
	public function reset_event_info_storage () { $this->_data = array(); }
	
	/**
	 * Save a message to the log file
	 */	
	function log( $message='' ) {
		// Don't give warning if folder is not writable
		@file_put_contents( EAB_PLUGIN_DIR . "log.txt", $message . chr(10). chr(13), FILE_APPEND ); 
	}

	/**
	 * Add Addon settings to the other admin options to be saved
	 */	
	function save_settings( $options ) {
		$event_default = isset( $_POST['event_default'] ) && is_array( $_POST['event_default'] ) ? $_POST['event_default'] : array();
		$options['weekly_calendar_start']    = sanitize_text_field( wp_unslash( isset( $event_default['weekly_calendar_start'] )    ? $event_default['weekly_calendar_start']    : '' ) );
		$options['weekly_calendar_end']      = sanitize_text_field( wp_unslash( isset( $event_default['weekly_calendar_end'] )      ? $event_default['weekly_calendar_end']      : '' ) );
		$options['weekly_calendar_interval'] = sanitize_text_field( wp_unslash( isset( $event_default['weekly_calendar_interval'] ) ? $event_default['weekly_calendar_interval'] : '' ) );
		$options['weekly_calendar_display']  = sanitize_text_field( wp_unslash( isset( $event_default['weekly_calendar_display'] )  ? $event_default['weekly_calendar_display']  : '' ) );
		return $options;
	}
	
	/**
	 * Admin settings
	 *
	 */	
	function show_settings() {
		if (!class_exists('PSource_HelpTooltips')) 
			require_once dirname(__FILE__) . '/lib/class_wd_help_tooltips.php';
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		?>
		<div id="eab-settings-weekly_calendar" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Wöchentlicher Ereigniskalender Einstellungen', 'eab'); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
					    <label for="psource_event-weekly_calendar_start" ><?php _e('Kalenderstartstunde', 'eab'); ?></label>
						<input type="text" size="10" name="event_default[weekly_calendar_start]" value="<?php print $this->_data->get_option('weekly_calendar_start'); ?>" />
						<span><?php echo $tips->add_tip(__('Gib die Stunde des Tages ein. Der Kalender beginnt im 24-Stunden-Format ohne morgens/abends und Minuten, z.B: 13. Standard ist 10 (10 Uhr).', 'eab')); ?></span>
					</div>
					    
					<div class="eab-settings-settings_item">
					    <label for="psource_event-weekly_calendar_end" ><?php _e('Kalenderendstunde', 'eab'); ?></label>
						<input type="text" size="10" name="event_default[weekly_calendar_end]" value="<?php print $this->_data->get_option('weekly_calendar_end'); ?>" />
						<span><?php echo $tips->add_tip(__('Der Kalender endet im 24-Stunden-Format ohne morgens/abends und Minuten, z.B: 22. Standard ist 24 (12 Uhr).', 'eab')); ?></span>
					</div>
					
					<div class="eab-settings-settings_item">
					    <label for="psource_event-weekly_calendar_interval" ><?php _e('Kalenderschrittintervall (Minuten)', 'eab'); ?></label>
						<input type="text" size="10" name="event_default[weekly_calendar_interval]" value="<?php print $this->_data->get_option('weekly_calendar_interval'); ?>" />
						<span><?php echo $tips->add_tip(__('Gib die Anzahl der Minuten ein, die bestimmen, wie viele Zeilen die Kalendertabelle enthalten soll. Die Standardeinstellung ist 120 (2 Stunden). Der minimal zulässige Wert ist 10. Zu kleine Werte können zu einer langen Tabelle führen.', 'eab')); ?></span>
					</div>
					
					<div class="eab-settings-settings_item">
					    <label for="psource_event-weekly_calendar_display" ><?php _e('Anzeige im Tooltip', 'eab'); ?></label>
						<select name="event_default[weekly_calendar_display]">
						<option value=""><?php _e('Veranstaltungsort, Miniaturansicht, Startdatum und Inhalt', 'eab'); ?></option>
						<option value="author" <?php if( $this->_data->get_option('weekly_calendar_display') ) echo "selected='selected'"?>><?php _e('Autorenname, Avatar und Bio', 'eab'); ?></option>
						
						</select>
						<span><?php echo $tips->add_tip(__('Wähle aus, welche Elemente in der QuickInfo angezeigt werden sollen, d. H. Wenn der Besucher die Maus über das Ereignis bewegt.', 'eab')); ?></span>
					</div>
					    
				</div>
		    </div>
		<?php
	}
}

Eab_CalendarTable_WeeklyEventArchiveCalendar::serve();