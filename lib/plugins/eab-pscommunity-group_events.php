<?php
/*
Plugin Name: PS Community: Gruppenereignisse
Description: Verbindet PS Events mit Deinen PS Community Gruppen.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.2
AddonType: PS Community
Author: DerN3rd
*/

/*
Detail: Ermöglicht eine tiefere Integration Deiner Events in PS Community Gruppen.
*/ 

if( ! defined( 'EAB_SHOW_HIDDEN_GROUP' ) ) define( 'EAB_SHOW_HIDDEN_GROUP', false );

class Eab_PSCommunity_GroupEvents {
	
	const SLUG = 'group-events';
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_PSCommunity_GroupEvents;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_group_tab_assets'), 30);
		
		// Ownership: group-events tab is registered and rendered by the Events plugin addon.
		// PS Community only exposes the host tab system.
		add_filter('cpc_group_tabs', array($this, 'add_group_tab'), 20, 3);
		add_filter('cpc_group_tab_content_group-events', array($this, 'render_group_tab_content'), 20, 3);
		
		if ($this->_data->get_option('psc-group_event-auto_join_groups')) {
			add_action('psource_event_booking_yes', array($this, 'auto_join_group'), 10, 2);
			add_action('psource_event_booking_maybe', array($this, 'auto_join_group'), 10, 2);
		}
		if ($this->_data->get_option('psc-group_event-private_events') || function_exists('cpc_is_group_member')) {
			add_filter('psource-query', array($this, 'filter_query'));
		}

		add_filter('eab-event_meta-event_meta_box-after', array($this, 'add_meta_box'));
		add_action('eab-event_meta-save_meta', array($this, 'save_meta'));
		add_action('eab-events-recurrent_event_child-save_meta', array($this, 'save_meta'));
		
		// Front page editor integration
		add_filter('eab-events-fpe-add_meta', array($this, 'add_fpe_meta_box'), 10, 2);
		add_action('eab-events-fpe-enqueue_dependencies', array($this, 'enqueue_fpe_dependencies'), 10, 2);
		add_action('eab-events-fpe-save_meta', array($this, 'save_fpe_meta'), 10, 2);

		// Upcoming and popular widget integration
		add_filter('eab-widgets-upcoming-default_fields', array($this, 'widget_instance_defaults'));
		add_filter('eab-widgets-popular-default_fields', array($this, 'widget_instance_defaults'));
		
		add_filter('eab-widgets-upcoming-instance_update', array($this, 'widget_instance_update'), 10, 2);
		add_filter('eab-widgets-popular-instance_update', array($this, 'widget_instance_update'), 10, 2);
		
		add_action('eab-widgets-upcoming-widget_form', array($this, 'widget_form'), 10, 2);
		add_action('eab-widgets-popular-widget_form', array($this, 'widget_form'), 10, 2);
		
		add_action('eab-widgets-upcoming-after_event', array($this, 'widget_event_group'), 10, 2);
		add_action('eab-widgets-popular-after_event', array($this, 'widget_event_group'), 10, 2);
	}

	public function enqueue_group_tab_assets () {
		$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
		if ($active_tab !== 'group-events') {
			return;
		}

		if (!shortcode_exists('eab_event_editor')) {
			return;
		}

		wp_enqueue_style('eab-events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . '/css/eab-events-fpe.min.css'));
		wp_add_inline_style('eab-events-fpe', '#eab-events-fpe-start_date,#eab-events-fpe-end_date{min-height:36px;}');

		wp_enqueue_script('jquery');

		if (current_user_can('upload_files')) {
			wp_enqueue_media();
			wp_enqueue_script('media-upload');
			wp_enqueue_script('media-models');
			wp_enqueue_script('media-views');
			wp_enqueue_script('media-editor');

			wp_enqueue_script(
				'eab-events-fpe',
				plugins_url(basename(EAB_PLUGIN_DIR) . '/js/eab-events-fpe.js'),
				array('jquery', 'media-upload', 'media-models', 'media-views', 'media-editor'),
				'1.0',
				true
			);
		} else {
			wp_enqueue_script(
				'eab-events-fpe',
				plugins_url(basename(EAB_PLUGIN_DIR) . '/js/eab-events-fpe.js'),
				array('jquery'),
				'1.0',
				true
			);
		}

		wp_localize_script('eab-events-fpe', 'l10nFpe', array(
			'mising_time_date' => __('Bitte lege sowohl Start- als auch Enddaten und -zeiten fest', 'eab'),
			'check_time_date' => __('Bitte überprüfe Deine Zeit- und Datumseinstellungen', 'eab'),
			'general_error' => __('Fehler', 'eab'),
			'missing_id' => __('Speichern fehlgeschlagen', 'eab'),
			'all_good' => __('Alles Super!', 'eab'),
			'base_url' => site_url(),
			'media_title' => __('Veranstaltungsbild auswählen', 'eab'),
			'media_button' => __('Bild verwenden', 'eab'),
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('eab_fpe_upload_nonce')
		));

		// Keep the editor compact within the group tab.
		$compact_css = '.eab-group-events-editor{margin-top:12px;padding:12px;border:1px solid #e2e5e9;border-radius:8px;background:#fff}.eab-group-events-editor #eab-events-fpe-meta_info{gap:8px}.eab-group-events-editor .eab-events-fpe-col_wrapper{margin:.2% 0}.eab-group-events-editor .eab-events-fpe-meta_box{padding:.35rem}.eab-group-events-editor-toggle summary{cursor:pointer;font-weight:600;margin-top:10px}';
		wp_add_inline_style('eab-events-fpe', $compact_css);

		do_action('eab-events-fpe-enqueue_dependencies');
	}

	function widget_instance_defaults ($defaults) {
		$defaults['show_psc_group'] = false;
		return $defaults;
	}

	function widget_instance_update ($instance, $new) {
		$instance['show_psc_group'] = (int)$new['show_psc_group'];
		return $instance;
	}

	function widget_form ($options, $widget) {
		?>
<label for="<?php echo $widget->get_field_id('show_psc_group'); ?>" style="display:block;">
	<input type="checkbox" 
		id="<?php echo $widget->get_field_id('show_psc_group'); ?>" 
		name="<?php echo $widget->get_field_name('show_psc_group'); ?>" 
		value="1" <?php echo ($options['show_psc_group'] ? 'checked="checked"' : ''); ?> 
	/>
	<?php _e('PS Community Gruppe anzeigen', 'eab'); ?>
</label>
		<?php
	}

	function widget_event_group ($options, $event) {
		if (empty($options['show_psc_group'])) return false;
		$name = Eab_GroupEvents_Template::get_group_name($event->get_id());
		if (!$name) return false;
		echo '<div class="eab-event_group">' . $name . '</div>';
	}


	function filter_query ($query) {
		global $current_user;
		if (!($query instanceof WP_Query)) return $query;
		if (Eab_EventModel::POST_TYPE != @$query->query_vars['post_type']) return $query;
		
		$posts = array();
		foreach ($query->posts as $post) {
			$group = (int)get_post_meta($post->ID, 'eab_event-bp-group_event', true);
			if ($group) {
				if (!$this->can_user_view_group_event($group, (int)$current_user->ID)) continue;
			}
			$posts[] = $post;
		}
		$query->posts = $posts;
		$query->post_count = count($posts);
		return $query;
	}

	private function can_user_view_group_event ($group_id, $user_id = 0) {
		$group_id = (int)$group_id;
		$user_id = (int)$user_id;

		if ($group_id <= 0) return true;
		if (!$user_id) $user_id = get_current_user_id();

		$group = get_post($group_id);
		if ($group && $group->post_type === 'cpc_group' && function_exists('cpc_is_group_member')) {
			if (current_user_can('manage_options')) return true;

			$type = get_post_meta($group_id, 'cpc_group_type', true);
			if (!$type) $type = 'public';
			if ($type === 'public') return true;
			if (!$user_id) return false;

			if (cpc_is_group_member($user_id, $group_id, get_current_blog_id())) return true;

			if (function_exists('cpc_get_group_member_role')) {
				$role = cpc_get_group_member_role($user_id, $group_id, get_current_blog_id());
				if (in_array($role, array('admin', 'moderator'), true)) return true;
			}

			return false;
		}

		return false;
	}

	private function get_psc_groups ($user_id = 0) {
		if (!function_exists('cpc_get_user_groups') || !function_exists('cpc_get_groups_by_type')) {
			return array();
		}

		$groups = array();
		if ($this->_data->get_option('psc-group_event-user_groups_only')) {
			$allow_all_for_admin = is_super_admin() && $this->_data->get_option('psc-group_event-user_groups_only-unless_superadmin');
			if (!$allow_all_for_admin) {
				$groups = cpc_get_user_groups((int)$user_id, 'active', get_current_blog_id());
			}
		}

		if (empty($groups)) {
			$groups = cpc_get_groups_by_type('all', -1, get_current_blog_id());
		}

		return is_array($groups) ? $groups : array();
	}
	
	function auto_join_group ($event_id, $user_id) {
		if (!function_exists('cpc_add_group_member') || !function_exists('cpc_is_group_member')) return false;
		if (!$this->_data->get_option('psc-group_event-auto_join_groups')) return false;
		$group_id = (int)get_post_meta($event_id, 'eab_event-bp-group_event', true);
		if (!$group_id) return false;
		if (!cpc_is_group_member((int)$user_id, $group_id, get_current_blog_id())) {
			cpc_add_group_member((int)$user_id, $group_id, 'member', 'active', get_current_blog_id());
		}
	}
	
	public function add_group_tab($tabs, $group_id, $user_id) {
		// Only show if group calendar is enabled
		if (function_exists('cpc_events_allow_group_calendar') && !cpc_events_allow_group_calendar()) {
			return $tabs;
		}

		$group_id = (int)$group_id;
		if ($group_id <= 0) {
			return $tabs;
		}

		// Count group events for this group
		$count = (int)(new WP_Query(array(
			'post_type' => Eab_EventModel::POST_TYPE,
			'post_status' => 'publish',
			'meta_key' => 'eab_event-bp-group_event',
			'meta_value' => $group_id,
			'posts_per_page' => 1,
			'no_found_rows' => false,
			'fields' => 'ids',
		)))->found_posts;

		$label = __('Events', 'eab');
		if ($count > 0) {
			$label .= ' (' . $count . ')';
		}

		$tabs['group-events'] = array(
			'label' => $label,
			'icon' => 'calendar-alt',
			'priority' => 25,
		);

		return $tabs;
	}
	
	public function render_group_tab_content($html, $group_id, $shortcode_atts) {
		$group_id = (int)$group_id;
		if ($group_id <= 0) {
			return '<p>' . esc_html__('Gruppe nicht gefunden.', 'eab') . '</p>';
		}

		if (function_exists('cpc_can_view_group') && !cpc_can_view_group(get_current_user_id(), $group_id)) {
			return '<p>' . esc_html__('Keine Berechtigung.', 'eab') . '</p>';
		}

		$archive_html = do_shortcode('[eab_group_archives groups="' . (int)$group_id . '"]');
		if (trim(wp_strip_all_tags((string)$archive_html)) === '') {
			$archive_html = '<p class="eab-group-events-empty">' . esc_html__('Noch keine Gruppen-Events vorhanden.', 'eab') . '</p>';
		}

		$output = '<div class="eab-group-events-tab">';
		$output .= '<div class="eab-group-events-archive">';
		$output .= $archive_html;
		$output .= '</div>';

		// Keep original PS Events editor available, but collapsed by default.
		if (shortcode_exists('eab_event_editor')) {
			$output .= '<details class="eab-group-events-editor-toggle">';
			$output .= '<summary>' . esc_html__('Neues Gruppen-Event erstellen', 'eab') . '</summary>';
			$output .= '<div class="eab-group-events-editor">';
			$output .= do_shortcode('[eab_event_editor]');
			$output .= '</div>';
			$output .= '</details>';
		}

		$output .= '</div>';
		return $output;
	}
	
	function show_nags () {
		if (!function_exists('cpc_is_group_member')) {
			echo '<div class="error"><p>' .
				__("PS Community Groups muss aktiv sein, damit die Gruppenereignis-Erweiterung funktioniert", 'eab') .
			'</p></div>';
		}
		if (!function_exists('cpc_get_user_groups')) {
			echo '<div class="error"><p>' .
				__("PS Community Groups Funktionen fehlen. Bitte PS Community Groups aktivieren.", 'eab') .
			'</p></div>';
		}
	}
	
	function show_settings () {
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');
		$checked = $this->_data->get_option('psc-group_event-auto_join_groups') ? 'checked="checked"' : '';
		$private = $this->_data->get_option('psc-group_event-private_events') ? 'checked="checked"' : '';
		$user_groups_only = $this->_data->get_option('psc-group_event-user_groups_only') ? 'checked="checked"' : '';
		$user_groups_only_unless_superadmin = $this->_data->get_option('psc-group_event-user_groups_only-unless_superadmin') ? 'checked="checked"' : '';
		$eab_event_bp_group_event_email_grp_member = $this->_data->get_option('eab_event_bp_group_event_email_grp_member') ? 'checked="checked"' : '';
?>
<div id="eab-settings-group_events" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Einstellungen für Gruppenereignisse', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-psc-group_event-auto_join_groups"><?php _e('Automatisch der Gruppe beitreten bei Veranstaltungs-Anmeldungen', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event-psc-group_event-auto_join_groups" name="event_default[psc-group_event-auto_join_groups]" value="1" <?php print $checked; ?> />
			<span><?php echo $tips->add_tip(__('Wenn sich Deine Benutzer positiv zu Einem Gruppenereignis melden, werden sie auch automatisch der Gruppe beitreten, zu der das Ereignis gehört.', 'eab')); ?></span>
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-psc-group_event-private_events"><?php _e('Gruppenveranstaltungen sind für Gruppen privat', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event-psc-group_event-private_events" name="event_default[psc-group_event-private_events]" value="1" <?php print $private; ?> />
			<span><?php echo $tips->add_tip(__('Wenn Du diese Option aktivierst, können Benutzer außerhalb Ihrer Gruppen Gruppenereignisse <b>nicht</b> sehen.', 'eab')); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-psc-group_event-user_groups_only"><?php _e('Nur Gruppen anzeigen, zu denen der Benutzer gehört', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event-psc-group_event-user_groups_only" name="event_default[psc-group_event-user_groups_only]" value="1" <?php print $user_groups_only; ?> />
			<span><?php echo $tips->add_tip(__('Wenn Du diese Option aktivierst, können Benutzer keine Ereignisse außerhalb der Gruppen zuweisen, zu denen sie bereits gehören.', 'eab')); ?></span>
			<br />
	    	<label for="eab_event-psc-group_event-user_groups_only-unless_superadmin"><?php _e('... außer für Superadministratoren', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event-psc-group_event-user_groups_only-unless_superadmin" name="event_default[psc-group_event-user_groups_only-unless_superadmin]" value="1" <?php print $user_groups_only_unless_superadmin; ?> />
			<span><?php echo $tips->add_tip(__('Wenn Du diese Option aktivierst, können Deine Superadministratoren jeder Gruppe Ereignisse zuweisen.', 'eab')); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-psc-group_event-private_events"><?php _e('Sende eine E-Mail an alle Gruppenmitglieder, wenn ein Ereignis erstellt oder bearbeitet wird', 'eab'); ?>?</label>
			<input type="checkbox" id="eab_event_bp_group_event_email_grp_member" name="event_default[eab_event_bp_group_event_email_grp_member]" value="1" <?php print $eab_event_bp_group_event_email_grp_member; ?> />
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['psc-group_event-auto_join_groups'] = empty( $_POST['event_default']['psc-group_event-auto_join_groups'] ) ? 0 : $_POST['event_default']['psc-group_event-auto_join_groups'];
		$options['psc-group_event-private_events'] = empty( $_POST['event_default']['psc-group_event-private_events'] ) ? 0 : $_POST['event_default']['psc-group_event-private_events'];
		$options['psc-group_event-user_groups_only'] = empty( $_POST['event_default']['psc-group_event-user_groups_only'] ) ? 0 : $_POST['event_default']['psc-group_event-user_groups_only'];
		$options['psc-group_event-user_groups_only-unless_superadmin'] = empty( $_POST['event_default']['psc-group_event-user_groups_only-unless_superadmin'] ) ? 0 : $_POST['event_default']['psc-group_event-user_groups_only-unless_superadmin'];
		$options['eab_event_bp_group_event_email_grp_member'] = empty( $_POST['event_default']['eab_event_bp_group_event_email_grp_member'] ) ? 0 : $_POST['event_default']['eab_event_bp_group_event_email_grp_member'];
		return $options;
	}

	function add_meta_box ($box) {
		global $post, $current_user;
		if (!function_exists('cpc_get_user_groups')) return $box;
		$group_id = get_post_meta($post->ID, 'eab_event-bp-group_event', true);
		$groups = $this->get_psc_groups((int)$current_user->ID);
		
		$ret = '';
		$ret .= '<div class="eab_meta_box">';
		$ret .= '<div class="misc-eab-section" >';
		$ret .= '<div class="eab_meta_column_box top"><label for="eab_event-bp-group_event">' .
			__('Gruppenveranstaltung', 'eab') . 
		'</label></div>';
		
		$ret .= __('<h3>Dies ist eine Gruppenveranstaltung für:</h3>', 'eab');
		$ret .= ' <select name="eab_event-bp-group_event" id="eab_event-bp-group_event">';
		$ret .= '<option value="">' . __('Keine Gruppenveranstaltung', 'eab') . '&nbsp;</option>';
		foreach ($groups as $group) {
			$gid = isset($group->ID) ? (int)$group->ID : 0;
			$gtitle = isset($group->post_title) ? $group->post_title : '';
			if (!$gid || !$gtitle) continue;
			$selected = ($gid == $group_id) ? 'selected="selected"' : '';
			$ret .= "<option value='{$gid}' {$selected}>" . esc_html($gtitle) . "</option>";
		}
		$ret .= '</select> ';
		
		$ret .= '</div>';
		$ret .= '</div>';
		return $box . $ret;
	}

	function add_fpe_meta_box ($box, $event) {
		global $current_user;
		if (!function_exists('cpc_get_user_groups')) return $box;
		$group_id = get_post_meta($event->get_id(), 'eab_event-bp-group_event', true);
		$groups = $this->get_psc_groups((int)$current_user->ID);
		
		$ret .= '<div class="eab-events-fpe-meta_box">';
		$ret .= __('Dies ist eine Gruppenveranstaltung für', 'eab');
		$ret .= ' <select name="eab_event-bp-group_event" id="eab_event-bp-group_event">';
		$ret .= '<option value="">' . __('Kein Gruppenereignis', 'eab') . '&nbsp;</option>';
		foreach ($groups as $group) {
			$gid = isset($group->ID) ? (int)$group->ID : 0;
			$gtitle = isset($group->post_title) ? $group->post_title : '';
			if (!$gid || !$gtitle) continue;
			$selected = ($gid == $group_id) ? 'selected="selected"' : '';
			$ret .= "<option value='{$gid}' {$selected}>" . esc_html($gtitle) . "</option>";
		}
		$ret .= '</select> ';
		$ret .= '</div>';
		
		return $box . $ret;
	}
	
	private function _save_meta ($post_id, $request) {
		if (!function_exists('cpc_get_group_members')) return false;
		if (!isset($request['eab_event-bp-group_event'])) return false;
		
		$data = (int)$request['eab_event-bp-group_event'];
		//if (!$data) return false;
		
		update_post_meta($post_id, 'eab_event-bp-group_event', $data);
		
		$email_grp_member = $this->_data->get_option('eab_event_bp_group_event_email_grp_member');
		if( isset( $email_grp_member ) &&  $email_grp_member == 1 ) {
			$grp_members = cpc_get_group_members((int)$data, 'active', '', get_current_blog_id());
			foreach( $grp_members as $member ){
				if (empty($member->user_email)) continue;
				$subject = __( 'Informationen zu einem Gruppenereignis', 'eab' );
				$subject = apply_filters( 'eab_bp_grp_events_member_mail_subject', $subject, $member, $post_id );
				$message = __( 'Hallo ' . $member->display_name . ',<br><br>Eine Veranstaltung wurde erstellt. Ich hoffe, Du wirst an dieser Veranstaltung teilnehmen. Details zur Veranstaltung findest Du hier: ' . get_permalink( $post_id ), 'eab' );
				$message = apply_filters( 'eab_bp_grp_events_member_mail_message', $message, $member, $post_id );
				wp_mail( $member->user_email, $subject, $message );
			}
		}
		
	}
	
	function save_meta ($post_id) {
		$this->_save_meta($post_id, $_POST);
	}

	function save_fpe_meta ($post_id, $request) {
		$this->_save_meta($post_id, $request);
	}
	
	function enqueue_fpe_dependencies () {
		wp_enqueue_script('eab-buddypress-group_events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . "/js/eab-buddypress-group_events-fpe.js"), array('jquery'));
	}
}



class Eab_PSCommunity_GroupEventsCollection extends Eab_UpcomingCollection {
	
	private $_group_id;
		
	public function __construct ($group_id, $timestamp=false, $args=array()) {
		$this->_group_id = $group_id;
		parent::__construct($timestamp, $args);
	}
	
	public function build_query_args ($args) {
		$args = parent::build_query_args($args);
		$args['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $this->_group_id,
		);
		return $args;
	}
}
class Eab_PSCommunity_GroupEventsWeeksCollection extends Eab_UpcomingWeeksCollection {
	
	private $_group_id;
		
	public function __construct ($group_id, $timestamp=false, $args=array()) {
		$this->_group_id = $group_id;
		parent::__construct($timestamp, $args);
	}
	
	public function build_query_args ($args) {
		$args = parent::build_query_args($args);
		$args['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $this->_group_id,
		);
		return $args;
	}
}

Eab_PSCommunity_GroupEvents::serve();


/**
 * Group events add-on template extension.
 */
class Eab_GroupEvents_Template extends Eab_Template {

	public static function get_group ($event_id=false) {
		if (!$event_id) {
			global $post;
			$event_id = $post->ID;
		}
		if (!$event_id) return false;
		
		$group_id = get_post_meta($event_id, 'eab_event-bp-group_event', true);
		if (!$group_id) return false;
		
		$group = get_post($group_id);
		if (!$group || $group->post_type !== 'cpc_group') return false;

		return $group;
	}

	public static function get_group_name ($event_id=false) {
		$group = self::get_group($event_id);
		return (!empty($group->post_title))
			? $group->post_title
			: ''
		;
	}
}


class Eab_GroupEvents_Shortcodes extends Eab_Codec {

	protected $_shortcodes = array(
		'group_archives' => 'eab_group_archives',
	);

	public static function serve () {
		$me = new Eab_GroupEvents_Shortcodes;
		$me->_register();
	}

	function process_group_archives_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Date arguments	
			'date' => false, // Starting date - default to now
			'lookahead' => false, // Don't use default monthly page - use weeks count instead
			'weeks' => false, // Look ahead this many weeks
		// Query arguments
			'category' => false, // ID or slug
			'limit' => false, // Show at most this many events
			'order' => false,
			'groups' => false, // Group ID, keyword or comma-separated list of group IDs
			'user' => false, // User ID or keyword
		// Appearance arguments
			'class' => 'eab-group_events',
			'template' => 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
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

		if (is_numeric($args['groups'])) {
			// Single group ID
			$args['groups'] = $this->_arg_to_int($args['groups']);
		} else if (strstr($args['groups'], ',')) {
			// Comma-separated list of group IDs
			$ids = array_map('intval', array_map('trim', explode(',', $args['groups'])));
			if (!empty($ids)) $args['groups'] = $ids;
		} else {
			// Keyword
			if (in_array(trim($args['groups']), array('my', 'my-groups', 'my_groups')) && $args['user']) {
				if (!function_exists('cpc_get_user_groups')) return $content;
				$groups = cpc_get_user_groups((int)$args['user'], 'active', get_current_blog_id());
				$args['groups'] = array_map('intval', wp_list_pluck($groups, 'ID'));
			} else if ('all' == trim($args['groups'])) {
				if (!function_exists('cpc_get_groups_by_type')) return $content;
				$groups = cpc_get_groups_by_type('all', -1, get_current_blog_id());
				$args['groups'] = array_map('intval', wp_list_pluck($groups, 'ID'));
			} else {
				$args['groups'] = false;
			}
		}
		if (!$args['groups']) return $content;

		$events = array();
		$query = $this->_to_query_args($args);
		$query['meta_query'][] = array(
			'key' => 'eab_event-bp-group_event',
			'value' => $args['groups'],
			'compare' => (is_array($args['groups']) ? 'IN' : '='),
		);

		$order_method = $args['order']
			/*? create_function('', 'return "' . $args['order'] . '";')*/
			? function() {return "' . $args[order] . '";}
			: false
		;
		if ($order_method) add_filter('eab-collection-date_ordering_direction', $order_method);
		
		// Lookahead - depending on presence, use regular upcoming query, or poll week count
		if ($args['lookahead']) {
			$method = $args['weeks']
				/*? create_function('', 'return ' . $args['weeks'] . ';')*/
				? function() {return ' . $args[weeks] . ';}
				: false;
			;
			if ($method) add_filter('eab-collection-upcoming_weeks-week_number', $method);
					$collection = new Eab_PSCommunity_GroupEventsWeeksCollection($args['groups'], $args['date'], $query);
			if ($method) remove_filter('eab-collection-upcoming_weeks-week_number', $method);
		} else {
			// No lookahead, get the full month only
					$collection =  new Eab_PSCommunity_GroupEventsCollection($args['groups'], $args['date'], $query);
		}
		if ($order_method) remove_filter('eab-collection-date_ordering_direction', $order_method);
		$events = $collection->to_collection();

		$output = eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}

	public function add_group_archives_shortcode_help ($help) {
		$help[] = array(
			'title' => __('PS-Community-Gruppenarchive', 'eab'),
			'tag' => 'eab_group_archives',
			'arguments' => array(
				'date' => array('help' => __('Startdatum - Standard <bold>Jetzt</bold>', 'eab'), 'type' => 'string:date'),
				'lookahead' => array('help' => __('Verwende keine monatliche Standardseite - verwende stattdessen die Anzahl der Wochen', 'eab'), 'type' => 'boolean'),
				'weeks' => array('help' => __('Schaue so viele Wochen nach vorne', 'eab'), 'type' => 'integer'),
				'category' => array('help' => __('Zeige Ereignisse aus dieser Kategorie (ID oder Slug)', 'eab'), 'type' => 'string:or_integer'),
				'limit' => array('help' => __('Zeige höchstens so viele Veranstaltungen', 'eab'), 'type' => 'integer'),
				'order' => array('help' => __('Sortiere Ereignisse in diese Richtung', 'eab'), 'type' => 'string:sort'),
				'groups' => array('help' => __('Gruppen-ID, Schlüsselwörter "Meine Gruppen" oder "Alle" oder durch Kommas getrennte Liste von Gruppen-IDs', 'eab'), 'type' => 'string:or_integer'),
				'user' => array('help' => __('Benutzer-ID oder Schlüsselwort "aktuell" - erforderlich, wenn <code> groups </code> auf "Meine-Gruppen" gesetzt ist', 'eab'), 'type' => 'string:or_integer'),
				'class' => array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
    			'template' => array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
    			'override_styles' => array('help' => __('Schalte die Verwendung der Standardstile um', 'eab'), 'type' => 'boolean'),
    			'override_scripts' => array('help' => __('Schalte die Verwendung von Standardskripten um', 'eab'), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles'),
		);
		return $help;
	}
}

Eab_GroupEvents_Shortcodes::serve();
