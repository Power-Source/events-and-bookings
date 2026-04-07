<?php
/*
Plugin Name: PS Community: Meine Veranstaltungen
Description: Fügt Deinen Benutzerprofilen eine Registerkarte "Ereignisse" hinzu.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
AddonType: PS Community
Author: DerN3rd
*/

/*
Detail: Zeigt Listen der Benutzer-RSVPs in PS Community Profilen an.
*/ 

class Eab_PSCommunity_MyEvents {
	
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_PSCommunity_MyEvents;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
	}
	
	function show_nags () {
		if (!function_exists('cpc_render_profile_tabs')) {
			echo '<div class="error"><p>' .
				__("Du musst PS Community Profile Tabs aktiviert haben, damit die Erweiterung <strong>Meine Ereignisse</strong> funktioniert", 'eab') .
			'</p></div>';
		}
	}

	private function _check_permissions () {
		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		return current_user_can($post_type->cap->edit_posts);
	}
	
	function premium_event_rsvp ($content, $event, $status) {
		if (!$event->is_premium()) return $content;

		global $bp;
		$user_id = $bp->displayed_user->id;
		if (Eab_EventModel::BOOKING_YES != $status) return $content;
		if ($event->user_paid($user_id)) return $content;
		
		$content .= '<div class="eab-premium_event-unpaid_notice"><b>' . __('Veranstaltung nicht bezahlt', 'eab') . '</b></div>';

		return $content;
	}

	function exclude_premium_event_rsvp ($exclude, $event) {
		if ($exclude) return $exclude;

		global $bp;
		$user_id = $bp->displayed_user->id;

		if (!$event->is_premium()) return false;
		return !$event->user_paid($user_id);
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );
		$premium = $this->_data->get_option('bp-my_events-premium_events');
		$options = array(
			'' => __('Mach nichts Besonderes', 'eab'),
			'hide' => __('Verberge', 'eab'),
			'nag' => __('Nörgelhinweis anzeigen', 'eab'),
		);
?>
<div id="eab-settings-my_events" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Meine Ereignisse Einstellungen', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item" style="line-height:1.8em">
	    	<label for="eab_event-bp-my_events-premium_events"><?php _e('Nicht bezahlte Premium-Events mit positiven RSPVs', 'eab'); ?>:</label>
	    	<?php foreach ($options as $value => $label) { ?>
	    		<br />
				<input type="radio" id="eab_event-bp-my_events-premium_events-<?php echo esc_attr($value); ?>" name="event_default[bp-my_events-premium_events]" value="<?php echo esc_attr($value); ?>" <?php checked($value, $premium); ?> />
	    		<label for="eab_event-bp-my_events-premium_events-<?php echo esc_attr($value); ?>"><?php echo esc_html($label) ?></label>
	    	<?php } ?>
			<span><?php echo $tips->add_tip(__('Umgang mit nicht bezahlten Premium-Ereignissen in der Anzeige der Benutzerereignisliste.', 'eab')); ?></span>
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['bp-my_events-premium_events'] = $_POST['event_default']['bp-my_events-premium_events'];
		return $options;
	}
}

Eab_PSCommunity_MyEvents::serve();


class Eab_MyEvents_Shortcodes extends Eab_Codec {

	protected $_shortcodes = array(
		'my_events' => 'eab_my_events',
	);

	public static function serve () {
		$me = new Eab_MyEvents_Shortcodes;
		$me->_register();
	}

	function process_my_events_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Query arguments
			'user' => false, // User ID or keyword
		// Appearance arguments
			'class' => 'eab-my_events',
			'show_titles' => 'yes',
			'sections' => 'organized,yes,maybe,no',
		));

		if (is_numeric($args['user'])) {
			$args['user'] = $this->_arg_to_int($args['user']);
		} else {
			if ('current' == trim($args['user'])) {
				$user = wp_get_current_user();
				$args['user'] = $user->ID;
			} else {
				$args['user'] = false;
			}
		}
		if (empty($args['user'])) return $content;

		$args['sections'] = $this->_arg_to_str_list($args['sections']);
		$args['show_titles'] = $this->_arg_to_bool($args['show_titles']);

		$output = '';

		// Check if the user can organize events
		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		if (in_array('organized', $args['sections']) && user_can($args['user'], $post_type->cap->edit_posts)) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-organized">' . 
				($args['show_titles'] ? '<h4>' . __('Organisierte Veranstaltungen', 'eab') . '</h4>' : '') .
				Eab_Template::get_user_organized_events($args['user']) .
			'</div>';
		}

		if (in_array('yes', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_yes">' . 
				($args['show_titles'] ? '<h4>' . __('Teilnahme', 'eab') . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_YES, $args['user']) .
			'</div>';
		}
	
		if (in_array('maybe', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_maybe">' . 
				($args['show_titles'] ? '<h4>' . __('Mögliche Teilnahme', 'eab') . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_MAYBE, $args['user']) .
			'</div>';
		}
		
		if (in_array('no', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_no">' . 
				($args['show_titles'] ? '<h4>' . __('Abgesagte Teilnahme', 'eab') . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_NO, $args['user']) .
			'</div>';
		}

		$output = $output ? $output : $content;

		return $output;
	}

	public function add_my_events_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Mein Veranstaltungsarchiv', 'eab'),
			'tag' => 'eab_my_events',
			'arguments' => array(
				'user' => array('help' => __('Benutzer-ID oder Schlüsselwort "aktuell".', 'eab'), 'type' => 'string:or_integer'),
				'class' => array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
				'show_titles' => array('help' => __('Abschnittstitel anzeigen', 'eab'), 'type' => 'boolean'),
				'sections' => array('help' => __('Zeigen Sie diese Abschnitte. Mögliche Werte: "Organisiert", "Ja", "Möglich", "Nein".', 'eab'), 'type' => 'string:list'),
			),
		);
		return $help;
	}
}

Eab_MyEvents_Shortcodes::serve();
