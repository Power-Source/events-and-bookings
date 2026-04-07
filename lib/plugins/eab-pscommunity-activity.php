<?php
/*
Plugin Name: PS Community: Aktivitäts-Statusupdates
Description: Veröffentlicht eine Aktivitätsaktualisierung automatisch, wenn mit Deinen Ereignissen etwas passiert.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.2
AddonType: PS Community
Author: DerN3rd
*/

/*
Detail: Dieses Add-On veröffentlicht automatisch Aktivitätsaktualisierungen in PS Community, wenn eine vordefinierte Aktion in PS-Events gemäß Deinen Einstellungen ausgeführt wird.
*/ 

class Eab_PSCommunity_AutoUpdateActivity {
	
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_PSCommunity_AutoUpdateActivity;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		add_action('eab-event_meta-after_save_meta', array($this, 'dispatch_creation_activity_update'));
		add_action('eab-events-fpe-save_meta', array($this, 'dispatch_creation_activity_update'));
		
		add_action('psource_event_booking_yes', array($this, 'dispatch_positive_rsvp_activity_update'), 10, 2);
		add_action('psource_event_booking_maybe', array($this, 'dispatch_maybe_rsvp_activity_update'), 10, 2);
		add_action('psource_event_booking_no', array($this, 'dispatch_negative_rsvp_activity_update'), 10, 2);
	}

	private function can_use_cpc_activity () {
		return post_type_exists('cpc_activity');
	}

	private function get_user_link ($user_id) {
		$user_id = (int)$user_id;
		$user = get_user_by('id', $user_id);
		if (!$user) return __('Ein Benutzer', 'eab');
		return sprintf('<a href="%s">%s</a>', esc_url(get_author_posts_url($user_id)), esc_html($user->display_name));
	}

	private function cpc_activity_exists ($activity_key) {
		if (!$this->can_use_cpc_activity() || !$activity_key) return false;

		$q = new WP_Query(array(
			'post_type' => 'cpc_activity',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array(
				array(
					'key' => 'cpc_event_activity_key',
					'value' => sanitize_key($activity_key),
				),
			),
		));

		return !empty($q->posts);
	}

	private function add_cpc_activity ($user_id, $message, $activity_key = '') {
		if (!$this->can_use_cpc_activity() || !$message) return false;

		$user_id = (int)$user_id;
		if (!$user_id) return false;

		if ($activity_key && $this->cpc_activity_exists($activity_key)) return false;

		$post_id = wp_insert_post(array(
			'post_title' => wp_strip_all_tags($message),
			'post_status' => 'publish',
			'post_type' => 'cpc_activity',
			'post_author' => $user_id,
			'ping_status' => 'closed',
			'comment_status' => 'open',
		));

		if (!$post_id || is_wp_error($post_id)) return false;

		update_post_meta($post_id, 'cpc_target', $user_id);
		update_post_meta($post_id, 'cpc_activity_type', 'event');
		if ($activity_key) {
			update_post_meta($post_id, 'cpc_event_activity_key', sanitize_key($activity_key));
		}

		return true;
	}

	function dispatch_creation_activity_update ($post_id) {
		if (!$this->can_use_cpc_activity()) return false;
		
		$created = $this->_data->get_option('bp-activity_autoupdate-event_created');
		if (!$created) return false;

		$event = new Eab_EventModel(get_post($post_id));
		if (!$event->is_published()) return false;
		
		$user_link = $this->get_user_link($event->get_author());
		$update = false;

		$group_id =  $this->_is_group_event($event->get_id());
		$public_announcement = $this->_is_public_announcement($event->get_id());
		
		if ('any' == $created) {
			$update = sprintf(__('%s hat eine Veranstaltung erstellt', 'eab'), $user_link);
		} else if ('group' == $created && $group_id) {
			$group = get_post((int)$group_id);
			if (!$group || $group->post_type !== 'cpc_group') return false;
			$group_link = get_permalink($group->ID);
			$group_name = $group->post_title;
			$update = sprintf(__('%s hat eine Veranstaltung in <a href="%s">%s</a> erstellt', 'eab'), $user_link, $group_link, $group_name);
		} else if ('pa' == $created && $public_announcement) {
			$update = sprintf(__('%s hat eine öffentliche Veranstaltung erstellt', 'eab'), $user_link);
		}

		if (!$update) return false;
		$update = sprintf("{$update}, <a href='%s'>%s</a>", get_permalink($event->get_id()), $event->get_title());

		$activity_key = 'event_created_' . (int)$event->get_id();
		$this->add_cpc_activity((int)$event->get_author(), $update, $activity_key);
	}

	function dispatch_positive_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_yes')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_YES);
	}

	function dispatch_maybe_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_maybe')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_MAYBE);
	}

	function dispatch_negative_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_no')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_NO);
	}

	private function _construct_unique_rsvp_activity ($event_id, $user_id, $rsvp) {
		$group_id =  $this->_is_group_event($event_id);
		if ($this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_only') && !$group_id) return false;

		$event = new Eab_EventModel(get_post($event_id));
		$user_link = $this->get_user_link($user_id);
		$update = false;

		switch ($rsvp) {
			case Eab_EventModel::BOOKING_YES:
				$update = sprintf(__('%s wird an <a href="%s">%s</a> teilnehmen', 'eab'), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
			case Eab_EventModel::BOOKING_MAYBE:
				$update = sprintf(__('%s wird möglicherweise an <a href="%s">%s</a> teilnehmen', 'eab'), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
			case Eab_EventModel::BOOKING_NO:
				$update = sprintf(__('%s muss leider seine Teilnahme an <a href="%s">%s</a> absagen. :(', 'eab'), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
		}
		if (!$update) return false;

		$activity_key = 'event_rsvp_' . (int)$event->get_id() . '_' . (int)$user_id . '_' . sanitize_key((string)$rsvp);
		$this->add_cpc_activity((int)$user_id, $update, $activity_key);
	}
	
	function show_nags () {
		$msg = false;
		if (!$this->can_use_cpc_activity()) {
			$msg = __("PS Community Activity muss aktiv sein, damit die automatische Aktualisierung von Aktivitäten funktioniert", 'eab');
		}
		if (!$msg) return false;

		echo "<div class='error'><p>{$msg}</p></div>";
	}

	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );

		$_created = $this->_data->get_option('bp-activity_autoupdate-event_created');
		$event_created = 'any' == $_created ? 'checked="checked"' : false;
		$group_event_created = class_exists('Eab_PSCommunity_GroupEvents') && 'group' == $_created ? 'checked="checked"' : false;
		$pa_event_created = class_exists('Eab_Events_Pae') && 'pa' == $_created ? 'checked="checked"' : false;
		$skip_created = !$_created ? 'checked="checked"' : false;
		
		$created_group_post = class_exists('Eab_PSCommunity_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-created_group_post') ? 'checked="checked"' : false;
		
		$user_rsvp_yes = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_yes') ? 'checked="checked"' : false;
		$user_rsvp_maybe = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_maybe') ? 'checked="checked"' : false;
		$user_rsvp_no = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_no') ? 'checked="checked"' : false;

		
		$user_rsvp_group_only = class_exists('Eab_PSCommunity_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_only') ? 'checked="checked"' : false;
		$user_rsvp_group_post = class_exists('Eab_PSCommunity_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_post') ? 'checked="checked"' : false;
?>
<div id="eab-settings-activity_autoupdate" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Einstellungen für die automatische Aktivitätsaktualisierung', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label><?php _e('Aktivitäts-Feed automatisch aktualisieren, wenn ein Ereignis erstellt wird:', 'eab'); ?></label>	
			<span><?php echo $tips->add_tip(__('Eine Aktivitätsaktualisierung, die jedes Mal veröffentlicht wird, wenn ein Ereignis erstellt wird.', 'eab')); ?></span>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-event_created" name="eab-bp-activity_autoupdate[event_created]" value="any" <?php print $event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-event_created"><?php _e('Jedes Ereignis', 'eab'); ?></label>
		<?php if (class_exists('Eab_PSCommunity_GroupEvents')) { ?>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-group_event_created" name="eab-bp-activity_autoupdate[event_created]" value="group" <?php print $group_event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-group_event_created"><?php _e('Gruppenveranstaltung', 'eab'); ?></label>
		<?php } ?>
		<?php if (class_exists('Eab_Events_Pae')) { ?>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-pa_event_created" name="eab-bp-activity_autoupdate[event_created]" value="pa" <?php print $pa_event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-pa_event_created"><?php _e('Öffentliche Ankündigungsveranstaltung', 'eab'); ?></label>
		<?php } ?>
			<br />
			<input type="radio" id="eab_event-bp-activity_autoupdate-skip_created" name="eab-bp-activity_autoupdate[event_created]" value="any" <?php print $skip_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-skip_created"><?php _e('Aktivität nicht aktualisieren', 'eab'); ?></label>
			<br />	
			<br />	
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-created_group_post" name="eab-bp-activity_autoupdate[created_group_post]" value="1" <?php print $created_group_post; ?> />
			<label for="eab_event-bp-activity_autoupdate-created_group_post"><?php _e('Aktualisiert bei der Erstellung von Gruppenereignissen immer die entsprechenden Gruppenfeeds', 'eab'); ?></label>
		</div>
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label><?php _e('Aktivitäts-Feed beim Benutzer automatisch aktualisieren:', 'eab'); ?></label>				
			<span><?php echo $tips->add_tip(__('Ein Aktivitätsupdate, das jedes Mal veröffentlicht wird, wenn ein Benutzer reagiert.', 'eab')); ?></span>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_yes" name="eab-bp-activity_autoupdate[user_rsvp_yes]" value="1" <?php print $user_rsvp_yes; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_yes"><?php _e('... kommt', 'eab'); ?></label>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_maybe" name="eab-bp-activity_autoupdate[user_rsvp_maybe]" value="1" <?php print $user_rsvp_maybe; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_maybe"><?php _e('... hat Interesse', 'eab'); ?></label>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_no" name="eab-bp-activity_autoupdate[user_rsvp_no]" value="1" <?php print $user_rsvp_no; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_no"><?php _e('... kommt nicht', 'eab'); ?></label>
		<?php if (class_exists('Eab_PSCommunity_GroupEvents')) { ?>
			<br />
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_group_post" name="eab-bp-activity_autoupdate[user_rsvp_group_post]" value="1" <?php print $user_rsvp_group_post; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_group_post"><?php _e('Aktualisiert den Gruppenaktivitäts-Feed', 'eab'); ?></label>
		<?php } ?>
		</div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['bp-activity_autoupdate-event_created'] = $_POST['eab-bp-activity_autoupdate']['event_created'];
		$options['bp-activity_autoupdate-created_group_post'] = !empty($_POST['eab-bp-activity_autoupdate']['created_group_post']);
		$options['bp-activity_autoupdate-user_rsvp_yes'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_yes']);
		$options['bp-activity_autoupdate-user_rsvp_maybe'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_maybe']);
		$options['bp-activity_autoupdate-user_rsvp_no'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_no']);
		$options['bp-activity_autoupdate-user_rsvp_group_only'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_group_only']);
		$options['bp-activity_autoupdate-user_rsvp_group_post'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_group_post']);
		return $options;
	}

	private function _is_group_event ($post_id) {
		if (!class_exists('Eab_PSCommunity_GroupEvents')) return false;
		return get_post_meta($post_id, 'eab_event-bp-group_event', true);
	}

	private function _is_public_announcement ($post_id) {
		if (!class_exists('Eab_Events_Pae')) return false;
		return (int)get_post_meta($post_id, 'eab_public_announcement', true);
	}
	
}

Eab_PSCommunity_AutoUpdateActivity::serve();
