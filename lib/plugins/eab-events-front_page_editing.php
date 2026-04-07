<?php
/*
Plugin Name: Frontpage Editor
Description: Ermöglicht das Einbetten der Bearbeitung von Titelseiten für Ereignisse mithilfe eines Shortcodes in die öffentlichen Seiten Deiner Website.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.2
Author: DerN3rd
AddonType: Integration
*/

/*
Detail: Standardmäßig arbeitet der Startseiten-Editor mit vorkonfigurierter Stub-URL. Du kannst jedoch Deine eigene Seite erstellen, den Shortcode für die Bearbeitung der Veranstaltungen (<code> [eab_event_editor] </code>) zum Inhalt hinzufügen und Deine Links zum Hinzufügen/Bearbeiten in den Plugin-Einstellungen konfigurieren, um stattdessen diese Seite zu verwenden.
*/

class Eab_Events_FrontPageEditing {

	const SLUG = 'edit-event';
	private $_data;
	private $_options = array();

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_options = wp_parse_args($this->_data->get_option('eab-events-fpe'), array(
			'id' => false,
			'integrate_with_my_events' => false,
		));
	}

	public static function serve () {
		$me = new Eab_Events_FrontPageEditing;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		/*
		if (!$this->_options['id']) {
			add_action('wp', array($this, 'check_page_location'));
		}
		*/
		add_action('wp', array($this, 'check_page_location'));

		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		// Add/Edit links
		add_filter('eab-events-after_event_details', array($this, 'add_edit_link'), 10, 2);
		add_filter('eab-buddypress-group_events-after_head', array($this, 'add_new_link'));
		if (!is_admin()) add_action('admin_bar_menu', array($this, 'admin_bar_add_menu_links'), 99);
		if ($this->_options['integrate_with_my_events']) {
			add_action('eab-events-my_events-set_up_navigation', array($this, 'my_events_add_event'));
		}

		add_shortcode('eab_event_editor', array($this, 'handle_editor_shortcode'));

		add_action('wp_ajax_eab_events_fpe-save_event', array($this, 'json_save_event'));
		
		// Add AJAX handlers for upload functionality - register globally
		add_action('wp_ajax_eab_fpe_upload', array($this, 'handle_ajax_upload'));
		add_action('wp_ajax_nopriv_eab_fpe_upload', array($this, 'handle_ajax_upload'));
	}

/* ----- Settings ----- */

	function show_settings () {
		$pages = get_pages();
		$integrate_with_my_events = $this->_options['integrate_with_my_events'] ? 'checked="checked"' : '';
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png');
?>
<div id="eab-settings-fpe" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Frontend Editor', 'eab'); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
			<label for="eab-events-fpe-use_slug">
				<?php _e('Ich möchte diese Seite als Front-Editor-Seite verwenden', 'eab'); ?>:
			</label>
			<select id="eab-events-fpe-use_slug" name="eab-events-fpe[id]">
				<option value=""><?php _e('Standardwert verwenden', 'eab');?>&nbsp;</option>
			<?php
			foreach ($pages as $page) {
				$selected = ($this->_options['id'] == $page->ID) ? 'selected="selected"' : '';
				echo "<option value='{$page->ID}' {$selected}>{$page->post_title}</option>";
			}
			?>
			</select>
			<?php echo $tips->add_tip(__("Vergiss nicht, diesen Shortcode zu Deiner ausgewählten Seite hinzuzufügen: <code>[eab_event_editor]</code>", 'eab')); ?>
			<div><?php _e('Standardmäßig arbeitet der Startseiten-Editor mit vorkonfigurierter Stub-URL. Du kannst jedoch eine eigene Seite erstellen, den Shortcode für die Bearbeitung der Startseite (<code> [eab_event_editor] </code>) zum Inhalt hinzufügen und Deine Links zum Hinzufügen/Bearbeiten hier so konfigurieren, dass stattdessen diese Seite verwendet wird.', 'eab');?></div>
		</div>
<?php if (Eab_AddonHandler::is_plugin_active('eab-buddypres-my_events')) { ?>
		<div class="eab-settings-settings_item">
			<label for="eab-events-fpe-integrate_with_my_events">
				<input type="hidden" name="eab-events-fpe[integrate_with_my_events]" value="" />
				<input type="checkbox" id="eab-events-fpe-integrate_with_my_events" name="eab-events-fpe[integrate_with_my_events]" value="1" <?php echo $integrate_with_my_events; ?> />
				<?php _e('Integration in die Erweiterung <em> Meine Ereignisse </em>', 'eab'); ?>
			</label>
			<?php echo $tips->add_tip(__("Durch Aktivieren dieser Option wird ein neuer &quot;Neues Ereignis&quot; Tab zu &quot;Meine Veranstaltungen&quot; hinzu gefügt", 'eab')); ?>
		</div>
<?php } ?>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['eab-events-fpe'] = @$_POST['eab-events-fpe'];
		return $options;
	}

/* ----- Add/Edit Links ----- */

	/**
	 * BuddyPress:My Events integration
	 */
	function my_events_add_event () {
		global $bp;
		bp_core_new_subnav_item(array(
			'name' => __('Ereignis erstellen', 'eab'),
			'slug' => 'edit-event',
			'parent_url' => trailingslashit(trailingslashit($bp->displayed_user->domain) . 'my-events'),
			'parent_slug' => 'my-events',
			'screen_function' => array($this, 'bind_bp_add_event_page'),
		));
	}

	/**
	 * Edit link for singular events.
	 */
	function add_edit_link ($content, $event) {
		if (!$this->_check_perms($event->get_id())) return false;

		// Do not edit recurring events
		if ($event->is_recurring()) return $content;

		// Do not edit multiple dates events
		$start_dates = $event->get_start_dates();
		if (count($start_dates) > 1) return $content;

		return
			$content .
			'<p>' .
				'<a href="' . $this->_get_front_editor_link($event->get_id()) . '">' .
					__('Diese Veranstaltung bearbeiten', 'eab') .
				'</a>' .
			'</p>' .
		'';
	}

	/**
	 * Add new link on top of group events.
	 */
	function add_new_link () {
		if (!$this->_check_perms(false)) return false;

		echo '' .
			'<p>' .
				'<a href="' . $this->_get_front_editor_link() . '">' .
					__('Ereignis hinzufügen', 'eab') .
				'</a>' .
			'</p>' .
		'';
	}

	/**
	 * Admin toolbar integration.
	 */
	function admin_bar_add_menu_links () {
		global $wp_admin_bar, $post;

		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		if (!current_user_can($post_type->cap->edit_posts)) return false;

		$wp_admin_bar->add_menu(array(
			'id' => 'eab-events-fpe-admin_bar',
			'title' => __('Veranstaltungen', 'eab'),
			'href' => $this->_get_front_editor_link(),
		));
		$wp_admin_bar->add_menu(array(
			'parent' => 'eab-events-fpe-admin_bar',
			'id' => 'eab-events-fpe-admin_bar-add_event',
			'title' => __('Veranstaltung hinzufügen', 'eab'),
			'href' => $this->_get_front_editor_link(),
		));
		if (is_singular() && $post && isset($post->post_type) && $post->post_type == Eab_EventModel::POST_TYPE) {
			$wp_admin_bar->remove_node('edit');
			$wp_admin_bar->add_menu(array(
				'parent' => 'eab-events-fpe-admin_bar',
				'id' => 'eab-events-fpe-admin_bar-edit_event',
				'title' => __('Bearbeite diese Veranstaltung', 'eab'),
				'href' => $this->_get_front_editor_link($post->ID),
			));
		}
	}

/* ----- Internals ----- */

	function _get_front_editor_link ($event_id=false) {
		$url = $this->_options['id']
			? get_permalink($this->_options['id'])
			: home_url(self::SLUG)
		;
		$event_id = (int)$event_id ? "?event_id={$event_id}" : '';
		return "{$url}{$event_id}";
	}

	function check_page_location () {
		global $wp_query;
		$qobj = get_queried_object();
		$object_id = is_object($qobj) && isset($qobj->ID) ? $qobj->ID : false;

		//if (self::SLUG != $wp_query->query_vars['pagename']) return false;
		if (
			($this->_options['id'] && $this->_options['id'] != $object_id)
			||
			(!$this->_options['id'] && self::SLUG != $wp_query->query_vars['name'])
		) return false;
		if (is_archive()) return false; // Do not hijack archive pages.

		// Enqueue scripts early when we know we're on the editor page
		add_action('wp_enqueue_scripts', array($this, '_enqueue_dependencies'));

		add_filter('the_content', array($this, 'the_editor_content'), 99);
		status_header( 200 );
		$wp_query->is_page = false;
		$wp_query->is_single = true;
		$wp_query->post_count = 1;
		$wp_query->is_404 = false;
		$wp_query->posts = array();
		$wp_query->posts[0] = $qobj;
	}

	function json_save_event () {
		global $current_user;
		header('Content-type: application/json');
		if (!isset($_POST['data'])) die(json_encode(array(
			'status' => 0,
			'message' => __('Keine Daten empfangen', 'eab'),
		)));

		$data = $_POST['data'];
		if (!$this->_check_perms((int)$data['id'])) die(json_encode(array(
			'status' => 0,
			'message' => __('Keine ausreichenden Berechtigungen', 'eab'),
		)));
		$post = array();

		$start = date('Y-m-d H:i', strtotime($data['start']));
		$end = date('Y-m-d H:i', strtotime($data['end']));

		$has_no_start_time = ( isset( $data['no_start_time'] ) && $data['no_start_time'] == 'true' ) ? true : false;
		$has_no_end_time = ( isset( $data['no_end_time'] ) && $data['no_end_time'] == 'true' ) ? true : false;

		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		$post['post_title'] = strip_tags($data['title']);
		$post['post_content'] = current_user_can('unfiltered_html') ? $data['content'] : wp_filter_post_kses($data['content']);
		$post['post_status'] = current_user_can($post_type->cap->publish_posts) ? 'publish' : 'pending';
		$post['post_type'] = Eab_EventModel::POST_TYPE;
		$post['post_author'] = $current_user->ID;

		$data['featured'] = !empty($data['featured'])
			? (is_numeric($data['featured']) ? (int)$data['featured'] : false)
			: false
		;

		if ((int)$data['id']) {
			$post['ID'] = $post_id = $data['id'];
			wp_update_post($post);
			/* Added by Ashok */
			update_post_meta($post_id, '_thumbnail_id', $data['featured']);
			/* End of adding by Ashok */
		} else {
			$post_id = wp_insert_post($post);
			/* Added by Ashok */
			update_post_meta($post_id, '_thumbnail_id', $data['featured']);
			/* End of adding by Ashok */
		}
		if (!$post_id) die(json_encode(array(
			'status' => 0,
			'message' => __('Beim Speichern dieses Ereignisses ist ein Fehler aufgetreten', 'eab'),
		)));

		update_post_meta($post_id, 'psource_event_start', $start);
		update_post_meta( $post_id, 'psource_event_no_start', $has_no_start_time );
		update_post_meta($post_id, 'psource_event_end', $end);
		update_post_meta( $post_id, 'psource_event_no_end', $has_no_end_time );
		update_post_meta($post_id, 'psource_event_status', strip_tags($data['status']));
		
		//specify if the event has start and end time or not.
		//if ( $data['has_start'] == 0 ) update_post_meta($post_id, 'psource_event_no_start',1);			
		//if ( $data['has_end'] == 0 ) update_post_meta($post_id, 'psource_event_no_end',1);

		$venue_map = get_post_meta($post_id, 'agm_map_created', true);
		if (!$venue_map && $data['venue'] && class_exists('AgmMapModel')) {
			$model = new AgmMapModel;
			$model->autocreate_map($post_id, false, false, $data['venue']);
		}
		update_post_meta($post_id, 'psource_event_venue', strip_tags($data['venue']));


		$is_paid = (int)$data['is_premium'];
		$fee = $is_paid ? strip_tags($data['fee']) : '';
		update_post_meta($post_id, 'psource_event_paid', ($is_paid ? '1' : ''));
		update_post_meta($post_id, 'psource_event_fee', $fee);
		do_action('eab-events-fpe-save_meta', $post_id, $data);
    
		$selected_terms = ( isset( $data['category'] ) && is_array( $data['category'] ) ) ? array_map( 'intval' , $data['category'] ) : array();
		wp_set_post_terms( $post_id, $selected_terms, 'eab_events_category', false );

		if( current_user_can($post_type->cap->publish_posts) ){
			$message = __('Ereignis gespeichert und veröffentlicht', 'eab');
			do_action( 'eab_bp_event_published', $post_id );
		}else{
			$message = __('Ereignis gespeichert und wartet auf Genehmigung', 'eab');
			do_action( 'eab_bp_event_saved_for_approval', $post_id );
		}
		
		die(json_encode(array(
			'status' => 1,
			'post_id' => $post_id,
			'permalink' => get_permalink($post_id),
			'message' => $message,
		)));
	}

	private function _check_perms ($event_id) {
		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		if ($event_id) {
			return current_user_can($post_type->cap->edit_post, $event_id);
		} else {
			return current_user_can($post_type->cap->edit_posts);
		}
		return false;
	}

/* ----- Output ----- */

	function bind_bp_add_event_page () {
		add_action('bp_template_content', array($this, 'output_bp_event_editor'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
	}

	function output_bp_event_editor () {
		echo do_shortcode('[eab_event_editor]');
	}

	function handle_editor_shortcode ($args=array(), $content='') {
		global $post, $wp_current_filter;

		$event_id = (int)@$_GET['event_id'];
		if (!$this->_check_perms($event_id)) return false;
		if (defined('EAB_EVENTS_FPE_ALREADY_HERE')) return $content;

		define('EAB_EVENTS_FPE_ALREADY_HERE', true);
		return $this->_edit_event_form($event_id); // ... and YAY! for not being able to return wp_editor >.<
	}

	function the_editor_content ($content) {
		global $post, $wp_current_filter;
		if ($post) return $content; // If not fictional, we're not interested

		$event_id = (int)@$_GET['event_id'];
		if (!$this->_check_perms($event_id)) return false;
		if (defined('EAB_EVENTS_FPE_ALREADY_HERE')) return $content;

		/*$is_excerpt = array_reduce($wp_current_filter, create_function('$ret,$val', 'return $ret ? true : preg_match("/excerpt/", $val);'), false);
		$is_head = array_reduce($wp_current_filter, create_function('$ret,$val', 'return $ret ? true : preg_match("/head/", $val);'), false);
		$is_title = array_reduce($wp_current_filter, create_function('$ret,$val', 'return $ret ? true : preg_match("/title/", $val);'), false);
		if ($is_excerpt || $is_head || $is_title) return $content;*/

		$is_excerpt = array_reduce($wp_current_filter, function($ret,$val) {return $ret ? true : preg_match("/excerpt/", $val);}, false);
		$is_head = array_reduce($wp_current_filter, function($ret,$val) {return $ret ? true : preg_match("/head/", $val);}, false);
		$is_title = array_reduce($wp_current_filter, function($ret,$val) {return $ret ? true : preg_match("/title/", $val);}, false);
		if ($is_excerpt || $is_head || $is_title) return $content;

		define('EAB_EVENTS_FPE_ALREADY_HERE', true);
		return $this->_edit_event_form($event_id); // ... and YAY! for not being able to return wp_editor >.<
	}

	private function _edit_event_form ($event_id) {
		$post = $event_id ? get_post($event_id) : false;
		$event = new Eab_EventModel($post);

		// Add footer data
		add_action('wp_footer', array($this, 'enqueue_dependency_data'));

		$style = $event->get_id() ? '' : 'style="display:none"';
                $ret = '';
		$ret .= '<div id="eab-events-fpe">';
		$ret .= '<a id="eab-events-fpe-back_to_event" href="' . get_permalink($event->get_id()) . '" ' . $style . '>' . __('ZURÜCK ZUR VERANSTALTUNG', 'eab') . '</a>';
		$ret .= '<input type="hidden" id="eab-events-fpe-event_id" value="' . (int)$event->get_id() . '" />';
		$ret .= '<div>';
		$ret .= '<label>' . __('<h2>Titel der Veranstaltung:</h2>', 'eab') . '</label>';
		$ret .= '<br /><input type="text" name="" id="eab-events-fpe-event_title" value="' . esc_attr($event->get_title()) . '" />';
		$ret .= '</div>';

		$ret .= '<div id="fpe-editor"></div>';

		$ret .= $this->_get_event_meta_boxes($event);
		$ret .= '</div>';

		return $ret;
	}

	private function _get_event_meta_boxes ($event) {
		$ret = '<div id="eab-events-fpe-meta_info">';
		$ret .= '<div class="eab-events-fpe-col_wrapper">';

		// Date, time
		$ret .= '<div class="eab-events-fpe-meta_box" id="eab-events-fpe-date_time">';
		// Start date/time
		$start = $event->get_start_timestamp();
		$start = $start ? $start : eab_current_time();

		// End date/time
		$end = $event->get_end_timestamp();
		$end = $end ? $end : eab_current_time() + 3600;

		// Has not start or end time
		$has_no_start_time = $event->has_no_start_time();
		$has_no_end_time = $event->has_no_end_time();

		ob_start();
		?>
		<div class="eab-events-fpe-meta_box_item eab_event_date eab_start_date">
			<fieldset>
				<legend><?php _e('<h3>Das Event beginnt:</h3>', 'eab') ?></legend>
				<div class="eab-events-fpe-meta_box_sub_item">
					<label class="date-title"><?php _e('Datum:', 'eab'); ?></label>
					<input type="date" lang="de" name="" id="eab-events-fpe-start_date" value="<?php echo date('Y-m-d', $start); ?>" />
				</div>
				<div class="eab-events-fpe-meta_box_sub_item">
					<div class="eab-events-fpe_wrap_time_start <?php echo $has_no_start_time ? 'hide_time_option' : '' ?>"  >
						<label class="date-title"><?php _e('Uhrzeit:', 'eab'); ?></label>					
						<input type="text" name="" id="eab-events-fpe-start_time" value="<?php echo date('H:i', $start); ?>" size="10" />					
					</div>
					<div id="eab-events-fpe-time__start">
						<input type="checkbox" id="eab-events-fpe-toggle_time__start" class="eab_action_cb eab_time_toggle" data-time-affect="start" <?php checked( $has_no_start_time ); ?> /> 
						<a class="eab_action_button eab_time_toggle" data-time-affect="start"><?php _e('Keine Startzeit', 'eab'); ?></a>
					</div>
				</div>
			</fieldset>
		</div>

		<div class="eab-events-fpe-meta_box_item eab_event_date eab_end_date">
			<fieldset>
				<legend><?php _e('<h3>Das Event endet:</h3>', 'eab') ?></legend>
				<div class="eab-events-fpe-meta_box_sub_item">
					<label class="date-title"><?php _e('Datum:', 'eab'); ?></label>
					<input type="date" lang="de" name="" id="eab-events-fpe-end_date" value="<?php echo date('Y-m-d', $end); ?>" />
				</div>
				<div class="eab-events-fpe-meta_box_sub_item">
					<div class="eab-events-fpe_wrap_time_end <?php echo $has_no_end_time ? 'hide_time_option' : '' ?>">
						<label class="date-title"><?php _e('Uhrzeit:', 'eab'); ?></label>					
						<input type="text" name="" id="eab-events-fpe-end_time" value="<?php echo date('H:i', $end); ?>" size="10" />
					</div>
					<div id="eab-events-fpe-time__end">
						<input type="checkbox" id="eab-events-fpe-toggle_time__end" class="eab_action_cb eab_time_toggle" data-time-affect="end" <?php checked( $has_no_end_time ); ?> /> 
						<a class="eab_action_button eab_time_toggle" data-time-affect="end"><?php _e('Keine Endzeit', 'eab'); ?></a></div>
				</div>
			</fieldset>
		</div>
		<?php
		$ret .= ob_get_clean();

		// End date, time, venue
		$ret .= '</div>';

		// Status, type, misc
		$ret .= '<div class="eab-events-fpe-meta_box" id="eab-events-fpe-status_type">';

		// Status
		$ret .= '<div>';
		$ret .= '<label>' . __('<h3>Event-Status:</h3>', 'eab') . '</label>';
		$ret .= '<select name="" id="eab-events-fpe-status">';
		$ret .= '	<option value="' . Eab_EventModel::STATUS_OPEN . '" '.(($event->is_open())?'selected="selected"':'').' >'.__('Findet statt', 'eab').'</option>';
		$ret .= '	<option value="' . Eab_EventModel::STATUS_CLOSED . '" '.(($event->is_closed())?'selected="selected"':'').' >'.__('Abgesagt', 'eab').'</option>';
		$ret .= '	<option value="' . Eab_EventModel::STATUS_EXPIRED . '" '.(($event->is_expired())?'selected="selected"':'').' >'.__('Abgelaufen', 'eab').'</option>';
		$ret .= '	<option value="' . Eab_EventModel::STATUS_ARCHIVED . '" '.(($event->is_archived())?'selected="selected"':'').' >'.__('Archiviert', 'eab').'</option>';
		$ret .= apply_filters('eab-events-fpe-event_meta-extra_event_status', '', $event);
		$ret .= '</select>';
		$ret .= apply_filters('eab-events-fpe-event_meta-after_event_status', '', $event);
		$ret .= '</div>';

		// Type
		if ($this->_data->get_option('accept_payments')) {
			$ret .= '<div>';
			$ret .= '<label>' . __('<h3>Ist dies eine kostenpflichtige Veranstaltung?</h3>', 'eab') . '</label>';
			$ret .= '<select name="" id="eab-events-fpe-is_premium">';
			$ret .= '	<option value="1" ' . ($event->is_premium() ? 'selected="selected"' : '') . '>'.__('Ja', 'eab').'</option>';
			$ret .= '	<option value="0" ' . ($event->is_premium() ? '' : 'selected="selected"') . '>'.__('Nein', 'eab').'</option>';
			$ret .= '</select>';
			$ret .= '<div id="eab-events-fpe-event_fee-wrapper">';
			$ret .= '<label for="eab-events-fpe-event_fee">' . __('Teilnahme-Gebühr:', 'eab') . '</label>';
			$ret .= ' <input type="text" name="" id="eab-events-fpe-event_fee" size="6" value="' . esc_attr($event->get_price()) . '" />';
			$ret .= '</div>'; // eab-events-fpe-event_fee-wrapper
			$ret .= '</div>';
		}

		// End status, type, misc
		$ret .= '</div>';

		$ret .= '</div>'; // eab-events-fpe-col_wrapper
		$ret .= '<div class="eab-events-fpe-col_wrapper">';

		// Start Venue
		$ret .= '<div class="eab-events-fpe-meta_box" id="eab-events-fpe-meta_box-venue">';
		// Venue
		$ret .= '<div>';
		$ret .= '<label>' . __('<h3>Veranstaltungsort:</h3>', 'eab') . '</label>';
		$ret .= '<br /><input type="text" name="" id="eab-events-fpe-venue" value="' . esc_attr($event->get_venue_location()) . '" />';
		$ret .= '</div>';
		// End venue
		$ret .= '</div>';

		$ret .= '</div>'; // eab-events-fpe-col_wrapper
		$ret .= '<div class="eab-events-fpe-col_wrapper">';

		// Start Categories
		$event_cat_ids = $event->get_category_ids();
		$event_cat_ids = $event_cat_ids ? $event_cat_ids : array();
		$all_cats = get_terms('eab_events_category', array('hide_empty' => false));
		$all_cats = $all_cats ? $all_cats : array();
		$ret .= '<div class="eab-events-fpe-meta_box" id="eab-events-fpe-meta_box-categories">';
		// Categories
		$ret .= '<div>';
		$ret .= '<label>' . __('<h3>Ereigniskategorie:</h3>', 'eab') . '</label>';
		if ( ! empty( $all_cats ) ) {
			$ret .= '<div>';
			foreach ( $all_cats as $cat ) {
				$checked = checked( in_array($cat->term_id, $event_cat_ids) );
				$ret .= "<label><input type='checkbox' name='eab-events-fpe-categories[]' value='{$cat->term_id}' {$checked} /> {$cat->name}</label>";
			}
			$ret .= '</div>';
		}
		$ret .= '</div>';
		// End Categories
		$ret .= '</div>';

		$ret .= '</div>'; // eab-events-fpe-col_wrapper
		$ret .= '<div class="eab-events-fpe-col_wrapper">';

		$addons = apply_filters('eab-events-fpe-add_meta', '', $event);
		if ($addons) {
			$ret .= '<div class="eab-events-fpe-col_wrapper">';
			$ret .= $addons;
			$ret .= '</div>'; // eab-events-fpe-col_wrapper
		}

		$featured_image = $event->get_featured_image_url();
		$featured_image_id = (int)$event->get_featured_image_id();
		if (current_user_can('upload_files')) {
			/* Added by Ashok - Updated to use WordPress Media Library */
			$ret .= '<div class="eab-events-fpe-col_wrapper">';
				$ret .= '<label>' . __('<h3>Veranstaltungsbild</h3>', 'eab') . '</label>' .
					'<br />' .
					'<button type="button" class="eab-fpe-upload button">' . __('<b>Veranstaltungsbild hochladen</b>', 'eab') . '</button>' .
					'<input type="hidden" id="eab-fpe-attach_id" name="" value="' . $featured_image_id . '" />' .
					'<input type="hidden" name="featured" value="' . esc_attr($featured_image_id) . '" />' .
					'<br />' .
					'<img src="' . esc_url($featured_image) . '" id="eab-fpe-preview-upload" ' . (empty($featured_image) ? 'style="display:none"' : '') . ' />';
			$ret .= '</div>';
			/* End of adding by Ashok */
		} else if (!empty($featured_image_id) && !empty($featured_image)) {
			$ret .= '<div class="eab-events-fpe-col_wrapper">';
			$ret .= '<label>' . __('<h3>Veranstaltungsbild</h3>', 'eab') . '</label>' .
				'<img src="' . esc_url($featured_image) . '" id="eab-fpe-preview-upload" />' .
				'<input type="hidden" id="eab-fpe-attach_id" name="featured" value="' . esc_attr($featured_image_id) . '" />' .
			'</div>';
		}

		// OK/Cancel
		$ok_label = $event->get_id() ?  __('EREIGNIS AKTUALISIEREN', 'eab') : __('EREIGNIS VERÖFFENTLICHEN', 'eab');
		$ret .= '<div id="eab-events-fpe-ok_cancel">';
		$ret .= '<input type="button" class="button button-primary" id="eab-events-fpe-ok" value="' . esc_attr($ok_label) . '" />';
		$ret .= '<input type="button" class="button" id="eab-events-fpe-cancel" value="' . esc_attr(__('ABBRECHEN', 'eab')) . '" />';
		$ret .= '</div>';

		$ret .= '</div>'; // eab-events-fpe-col_wrapper
		$ret .= '<div class="eab-events-fpe-col_wrapper">';

		// RSVPs
		$ret .= '<div class="eab-events-fpe-meta_box" id="eab-events-fpe-rsvps">';


		if ($event->has_bookings()) {
			$ret .= '<a href="#toggle_rsvps" id="eab-events-fpe-toggle_rsvps">' . __('RSVPs umschalten', 'eab') . '</a>';
			$ret .= '<div id="eab-events-fpe-rsvps-wrapper" style="display:none">';
			$ret .= Eab_Template::get_admin_attendance_addition_form($event, Eab_Template::get_rsvp_status_list());
			$ret .= '<div>';
			$ret .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_YES, $event);
			$ret .= '</div>';

			$ret .= '<div>';
			$ret .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_MAYBE, $event);
			$ret .= '</div>';

			$ret .= '<div>';
			$ret .= Eab_Template::get_admin_bookings(Eab_EventModel::BOOKING_NO, $event);
			$ret .= '</div>';
			$ret .= '</div>'; //eab-events-fpe-rsvps-wrapper
		} else {
			$ret .= Eab_Template::get_admin_attendance_addition_form($event, Eab_Template::get_rsvp_status_list());
		}

		// End RSVPs
		$ret .= '</div>';

		$ret .= '</div>'; // eab-events-fpe-col_wrapper

		$ret .= '</div>';
		return $ret;
	}

	public function enqueue_dependency_data () {
		printf(
			'<script type="text/javascript">var _eab_events_fpe_data={"ajax_url": "%s", "root_url": "%s"};</script>',
			admin_url('admin-ajax.php'), EAB_PLUGIN_URL . 'img/'
		);

		// Add l10n data for our inline script
		printf(
			'<script type="text/javascript">var l10nFpe=%s;</script>',
			json_encode(array(
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
			))
		);

		// Initialize upload functionality
		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Wait for wp.media to load
				setTimeout(function() {
					initMediaUploader();
				}, 1000);
				
				function initMediaUploader() {
					$(".eab-fpe-upload").off("click").on("click", function(e) {
						e.preventDefault();
						
						var mediaSuccess = false;
						
						if (typeof wp !== "undefined" && typeof wp.media !== "undefined") {
							try {
								// Method 1: Standard wp.media approach
								var mediaFrame = wp.media({
									title: l10nFpe.media_title,
									button: {
										text: l10nFpe.media_button,
									},
									multiple: false,
									library: {
										type: "image"
									}
								});
								
								if (mediaFrame) {
									mediaFrame.on("select", function() {
										var attachment = mediaFrame.state().get("selection").first().toJSON();
										$("#eab-fpe-attach_id").val(attachment.id);
										$("#eab-fpe-preview-upload")
											.attr("src", attachment.url)
											.show();
									});
									
									mediaFrame.open();
									mediaSuccess = true;
								} else {
									throw new Error("mediaFrame creation failed");
								}
							} catch(err) {
								try {
									// Method 2: Use wp.media.editor as fallback
									wp.media.editor.send.attachment = function(props, attachment) {
										$("#eab-fpe-attach_id").val(attachment.id);
										$("#eab-fpe-preview-upload")
											.attr("src", attachment.url)
											.show();
									};
									
									wp.media.editor.open("eab-fpe-upload");
									mediaSuccess = true;
								} catch(editorErr) {
									// Fall through to direct file input
								}
							}
						}
						
						// Fallback method - direct file input
						if (!mediaSuccess) {
							var fileInput = $("<input type=\"file\" accept=\"image/*\" style=\"display:none;\">");
							$("body").append(fileInput);
							
							fileInput.on("change", function() {
								var file = this.files[0];
								if (file) {
									var formData = new FormData();
									formData.append("action", "eab_fpe_upload");
									formData.append("file", file);
									formData.append("nonce", l10nFpe.nonce);
									
									$.ajax({
										url: l10nFpe.ajax_url,
										type: "POST",
										data: formData,
										processData: false,
										contentType: false,
										success: function(response) {
											if (response.success) {
												$("#eab-fpe-attach_id").val(response.data.id);
												$("#eab-fpe-preview-upload")
													.attr("src", response.data.url)
													.show();
											} else {
												alert("Upload fehlgeschlagen: " + response.data);
											}
										},
										error: function() {
											alert("Upload-Fehler aufgetreten");
										}
									});
								}
								$(this).remove();
							});
							
							fileInput.click();
						}
					});
				}
			});
		</script>';

		$event_id = (int)@$_GET['event_id'];
		$post = $event_id ? get_post($event_id) : false;
		$event = new Eab_EventModel($post);
		echo '<div id="fpe-editor-root" style="display:none">';
		wp_editor(
			(!empty($post->post_content) ? $post->post_content : ''),
			'eab-events-fpe-content', array(
				'textarea_rows' => 5,
				'media_buttons' => true,
			)
		);
		echo '</div>';
	}

	public function _enqueue_dependencies () {
		wp_enqueue_style('eab-events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . "/css/eab-events-fpe.min.css"));
		wp_add_inline_style('eab-events-fpe', '#eab-events-fpe-start_date,#eab-events-fpe-end_date{min-height:36px;}');

		wp_enqueue_script('jquery');
		
		// Enqueue media scripts for the modern Media Library
		if (current_user_can('upload_files')) {
			wp_enqueue_media();
			wp_enqueue_script('media-upload');
			wp_enqueue_script('media-models');
			wp_enqueue_script('media-views');
			wp_enqueue_script('media-editor');
			
			wp_enqueue_script('eab-events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . "/js/eab-events-fpe.js"), 
				array('jquery', 'media-upload', 'media-models', 'media-views', 'media-editor'), 
				'1.0', true);
		} else {
			wp_enqueue_script('eab-events-fpe', plugins_url(basename(EAB_PLUGIN_DIR) . "/js/eab-events-fpe.js"), 
				array('jquery'), '1.0', true);
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

		do_action('eab-events-fpe-enqueue_dependencies');
	}

	/**
	 * Handle AJAX upload as fallback if wp.media is not available
	 */
	public function handle_ajax_upload() {
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eab_fpe_upload_nonce')) {
			wp_send_json_error('Security check failed');
			return;
		}
		
		// Check capabilities
		if (!current_user_can('upload_files')) {
			wp_send_json_error('You do not have permission to upload files');
			return;
		}
		
		// Check if file was uploaded
		if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			wp_send_json_error('File upload failed');
			return;
		}
		
		if (!function_exists('wp_handle_upload')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}
		
		$uploadedfile = $_FILES['file'];
		$upload_overrides = array('test_form' => false);
		$movefile = wp_handle_upload($uploadedfile, $upload_overrides);
		
		if ($movefile && !isset($movefile['error'])) {
			// File uploaded successfully, create attachment
			$attachment = array(
				'post_mime_type' => $movefile['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			
			$attach_id = wp_insert_attachment($attachment, $movefile['file']);
			
			if (!is_wp_error($attach_id)) {
				require_once(ABSPATH . 'wp-admin/includes/image.php');
				$attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
				wp_update_attachment_metadata($attach_id, $attach_data);
				
				wp_send_json_success(array(
					'id' => $attach_id,
					'url' => wp_get_attachment_url($attach_id),
					'title' => get_the_title($attach_id)
				));
			} else {
				wp_send_json_error('Failed to create attachment: ' . $attach_id->get_error_message());
			}
		} else {
			$error_msg = isset($movefile['error']) ? $movefile['error'] : 'Unknown upload error';
			wp_send_json_error('Upload failed: ' . $error_msg);
		}
	}

}

Eab_Events_FrontPageEditing::serve();
