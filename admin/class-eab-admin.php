<?php

/**
 * Class Eab_Admin
 *
 * Manages all the admin side (code will be added in following versions)
 */
class Eab_Admin {

	public function __construct() {
		$this->includes();

		add_action('admin_init', array( $this, 'admin_init' ), 0);

		add_action( 'admin_menu', array( $this, 'admin_menus' ) );

		add_action('admin_notices', array($this, 'check_permalink_format'));

		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
		add_action('admin_print_styles', array($this, 'admin_print_styles') );

		add_action('manage_psource_event_posts_custom_column', array($this, 'manage_posts_custom_column'));
		add_filter('manage_psource_event_posts_columns', array($this, 'manage_posts_columns'), 99);
	}

	private function includes() {
		include_once( 'class-eab-settings-menu.php' );
		include_once( 'class-eab-shortcodes-menu.php' );
		include_once( 'class-eab-get-started-menu.php' );
	}

	function admin_init() {
		// Check for tables first
		if ( ! $this->_blog_has_tables() )
			eab_activate();

		// Register scripts/styles
		wp_register_script('eab_event_dates', EAB_PLUGIN_URL . 'js/eab-event-dates.js', array(), Eab_EventsHub::CURRENT_VERSION, true);
		wp_register_script('eab_admin_js', EAB_PLUGIN_URL . 'js/eab-admin.js', array('jquery'), filemtime(EAB_PLUGIN_DIR . 'js/eab-admin.js'));
		wp_register_style('eab_admin', EAB_PLUGIN_URL . 'css/admin.min.css', null, Eab_EventsHub::CURRENT_VERSION);

		if (defined('AGM_PLUGIN_URL')) {
			add_action('admin_print_scripts-post.php', array($this, 'js_editor_button'));
			add_action('admin_print_scripts-post-new.php', array($this, 'js_editor_button'));
		}

		$event_localized = array(
			'view_all_bookings' 		=> __('Zeige Reaktionen', 'eab'),
			'back_to_gettting_started' 	=> __('Zurück zur Übersicht', 'eab'),
		);

		wp_localize_script('eab_admin_js', 'eab_event_localized', $event_localized);

	}

	function check_permalink_format () {
		if (get_option('permalink_structure')) return;
		echo '<div class="error"><p>' .
		     sprintf(
			     __('Du musst Deine Permalink-Struktur auf eine andere als die Standardstruktur aktualisieren, um PS-Events verwenden zu können. <a href="%s">Erledige das gleich hier.</a>', 'eab'),
			     admin_url('options-permalink.php')
		     ) .
		     '</p></div>';
	}

	private function _blog_has_tables () {
		global $wpdb;
		$table = Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE); // Check only one
		if ( ! $stored_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) return false;
		return strtolower($stored_table) == strtolower($table); // we are lowercasing cause uppercase is possible here;
	}

	function admin_enqueue_scripts() {
		if (!$this->_check_admin_page_id())
			return;
		if ( 'psource_event' === get_current_screen()->id ) {
			wp_enqueue_script('eab_event_dates');
			wp_localize_script('eab_event_dates', 'eabEventDates', array(
				'invalidRange' => __('Gib einen Start und ein Ende ein. Das Ende muss nach dem Start liegen.', 'eab'),
			));
		}
		wp_enqueue_script('eab_admin_js');
	}

	function admin_print_styles() {
		if (!$this->_check_admin_page_id())
			return;
		wp_enqueue_style('eab_admin');
	}

	function js_editor_button() {
		wp_enqueue_script('thickbox');
		wp_enqueue_script('eab_editor',  EAB_PLUGIN_URL . 'js/editor.js', array('jquery'));
		wp_localize_script('eab_editor', 'eab_l10nEditor', array(
			'loading' 						=> __('Lade Karten... einen Augenblick, bitte', 'eab'),
			'use_this_map' 					=> __('Diese Karte einfügen', 'eab'),
			'preview_or_edit' 				=> __('Vorschau/Bearbeiten', 'eab'),
			'delete_map' 					=> __('Löschen', 'eab'),
			'add_map' 						=> __('Karte hinzufügen', 'eab'),
			'existing_map' 					=> __('Vorhandene Karten', 'eab'),
			'no_existing_maps' 				=> __('Keine Karten', 'eab'),
			'new_map' 						=> __('Neue Karte erstellen', 'eab'),
			'advanced' 						=> __('Erweiterter Modus', 'eab'),
			'advanced_mode_activate_help' 	=> __('Aktiviere den erweiterten Modus, um einzelne Karten auszuwählen, die zu einer neuen Karte zusammengeführt werden sollen, oder um Karten im Stapel zu löschen', 'eab'),
			'advanced_mode_help' 			=> __('Um eine neue Karte aus mehreren Karten zu erstellen, wähle die Karten aus, welche Du verwenden möchtest, und klicke auf Standorte zusammenführen', 'eab'),
			'advanced_off' 					=> __('Erweiterten Modus verlassen', 'eab'),
			'merge_locations' 				=> __('Standorte zusammenführen', 'eab'),
			'batch_delete' 					=> __('Batch löschen', 'eab'),
			'new_map_intro' 				=> __('Erstelle eine neue Karte, die in diesen Beitrag oder diese Seite eingefügt werden kann. Sobald Du fertig bist, kannst Du alle unten stehenden Karten verwalten', 'eab'),
		));
	}

	private function _check_admin_page_id () {
		$_page_ids = array (
			'psource_event_page_eab_welcome',
			'edit-psource_event',
			'psource_event',
			'psource_event_page_eab_shortcodes',
			'psource_event_page_eab_settings',
		);
		$screen = get_current_screen();
		if (!in_array($screen->id, $_page_ids)) return false;
		return true;
	}

	/**
	 * Add the admin menus
	 *
	 * @see		http://codex.wordpress.org/Adding_Administration_Menus
	 */
	function admin_menus() {
		global $submenu;

		$root_key = 'edit.php?post_type=psource_event';

		if (get_option('eab_setup', false) == false) {

			new Eab_Admin_Get_Started_Menu( $root_key );

			if (isset($submenu[$root_key]) && is_array($submenu[$root_key])) foreach ($submenu[$root_key] as $k=>$item) {
				if ($item[2] == 'eab_welcome') {
					$submenu[$root_key][1] = $item;
					unset($submenu[$root_key][$k]);
				}
			}
		}

		new Eab_Admin_Settings_Menu( $root_key );
		new Eab_Admin_Shortcodes_Menu( $root_key );

		do_action('eab-admin-add_pages', $root_key);

		if (isset($submenu[$root_key]) && is_array($submenu[$root_key])) ksort($submenu[$root_key]);
	}

	function manage_posts_custom_column($column) {
		global $post;

		switch ($column) {
			case "attendees":
				global $wpdb;
				$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
				$yes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_YES));
				$no = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_NO));
				$maybe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d AND status = %s;", $event->get_id(), Eab_EventModel::BOOKING_MAYBE));
				printf('<b>' . __('Teilnahme / Unentschlossen', 'eab') . ':</b> %d / %d<br />', $yes, $maybe);
				printf('<b>' . __('Nicht teilnehmen', 'eab') . ':</b> %d', $no);
				echo '&nbsp;';
				echo '<a class="button" href="' . admin_url('index.php?eab_export=attendees&event_id='. $event->get_id()) . '" class="eab-export_attendees">' .
				     __('Exportieren', 'eab') .
				     '</a>';
				break;
			case "start":
				$event = new Eab_EventModel($post);
				$df = get_option('date_format', 'Y-m-d');
				if (!$event->is_recurring()) {
					echo
						date_i18n($df, $event->get_start_timestamp()) .
						' - ' .
						date_i18n($df, $event->get_end_timestamp())
					;
				} else {
					$repeats = $event->get_supported_recurrence_intervals();
					$title = @$repeats[$event->get_recurrence()];
					$start = date_i18n($df, $event->get_recurrence_starts());
					$end = date_i18n($df, $event->get_recurrence_ends());
					printf(__("Vom %s, wiederholt alle %s bis zum %s", 'eab'), $start, $title, $end);
				}
				break;
			case "venue":
				$event = new Eab_EventModel($post);
				echo $event->get_venue_location();
				break;
			case "event":
				$event = new Eab_EventModel($post);
				$post_type_object = get_post_type_object($post->post_type);
				$edit_link = get_edit_post_link($event->get_id());

				$statuses = array();
				if ('draft' == $post->post_status) $statuses[] = __('Draft');
				if ('private' == $post->post_status) $statuses[] = __('Private');
				if ('pending' == $post->post_status) $statuses[] = __('Pending');
				$status = $statuses ? ' - <span class="post-state">' . join(', ', $statuses) . '</span>' : '';

				$title = (current_user_can($post_type_object->cap->edit_post, $event->get_id()) && 'trash' != $post->post_status)
					? '<strong>' . '<a class="row-title" href="' . $edit_link .'" title="' . esc_attr(sprintf(__('Bearbeite &#8220;%s&#8221;' ), $event->get_title())) . '">' . $event->get_title() . '</a>&nbsp;' . $status . '</strong>'
					: '<strong>' . $event->get_title() . '&nbsp;' . $status . '</strong>'
				;

				if (current_user_can($post_type_object->cap->edit_post, $event->get_id()) && 'trash' != $post->post_status) {
					$actions['edit'] = '<a title="' . esc_attr(__('Veranstaltung bearbeiten', 'eab')) . '" href="' . $edit_link . '">' . __('Bearbeiten') . '</a>';
					if (!$event->is_recurring()) $actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="' . esc_attr(__( 'Bearbeite dieses Ereignis inline', 'eab')) . '">' . __('Schnelles&nbsp;Bearbeiten') . '</a>';
				}

				if (current_user_can($post_type_object->cap->delete_post, $event->get_id())) {
					if ('trash' == $post->post_status) {
						$actions['untrash'] = sprintf(	'<a href="%s" title="%s" aria-label="%s">%s</a>',
							wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
							'Restore this Event from the Trash',
							esc_attr( sprintf( __( 'Wiederhergestellt &#8220;%s&#8221; aus dem Papierkorb' ), $title ) ),
							__( 'Wiederherstellen' )
						);	
					} else if (EMPTY_TRASH_DAYS) {
						$actions['trash'] = '<a class="submitdelete" title="' . esc_attr(__('Verschiebe dieses Ereignis in den Papierkorb', 'eab')) . '" href="' . get_delete_post_link($event->get_id()) . '">' . __('Papierkorb') . '</a>';
					}
					if ('trash' == $post->post_status || !EMPTY_TRASH_DAYS) {
						$actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Lösche dieses Ereignis dauerhaft', 'eab')) . "' href='" . get_delete_post_link($event->get_id(), '', true ) . "'>" . __('Dauerhaft löschen') . "</a>";
					}
				}

				if ('trash' != $post->post_status) {
					$event_id = $event->get_id();
					if ($event->is_recurring()) {
						$children = Eab_CollectionFactory::get_all_recurring_children_events($event);
						if (!$children || !($children[0]) instanceof Eab_EventModel) $event_id = false;
						else $event_id = $children[0]->get_id();
					}
					if ($event_id) {
						$actions['view'] = '<a href="' . get_permalink($event_id) . '" title="' . esc_attr(sprintf(__('Ansehen &#8220;%s&#8221;'), $event->get_title())) . '" rel="permalink">' . __('Ansehen') . '</a>';
					}
				}

				echo $title;
				if (!empty($actions)) {
					foreach ($actions as $action => $link) {
						$actions[$action] = "<span class='{$action}'>{$link}</span>";
					}
				}
				echo '<div class="row-actions">' . join('&nbsp;|&nbsp;', $actions) . '</div>';
				get_inline_data($post);
				break;
		}
	}

	function manage_posts_columns($old_columns)	{
		$columns['cb'] = $old_columns['cb'];
		$columns['event'] = $old_columns['title'];

		// Allow for WPML translation field
		if (isset($old_columns['icl_translations'])) {
			$columns['icl_translations'] = $old_columns['icl_translations'];
		}

		$columns['start'] = __('Wann', 'eab');
		$columns['venue'] = __('Wo', 'eab');
		$columns['author'] = $old_columns['author'];
		$columns['date'] = $old_columns['date'];
		$columns['attendees'] = __('Reaktionen', 'eab');

		return $columns;
	}

}