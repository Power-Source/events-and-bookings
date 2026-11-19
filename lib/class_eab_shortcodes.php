<?php

class Eab_Shortcodes extends Eab_Codec {

	protected $_shortcodes = array (
		'calendar' => 'eab_calendar',
		'archive' => 'eab_archive',
		'single' => 'eab_single',
		'expired' => 'eab_expired',
		'events_map' => 'eab_events_map',
		'my_events' => 'eab_my_events',
	);

	public static function serve () {
		$me = new Eab_Shortcodes;
		$me->_register();
	}

	/**
	 * Events map shortcode
	 * @param  array   $args    Shortcode arguments
	 * @param  boolean $content Fallback content
	 * @return string           Map string or fallback content
	 */
	function process_events_map_shortcode ( $args = array(), $content=false) {
		if (!class_exists('AgmMapModel') || !class_exists('AgmMarkerReplacer')) {
			return $content;
		}

		$map_args 	= $args;
		$args 		= $this->_preparse_arguments( $args, array(
			// Date arguments
			'relative_date' 			=> false, // A date relative to _now_ or to date argument - using a strtotime string
			'date' 						=> false, // Starting date - default to now
			'lookahead' 				=> false, // Don't use default monthly page - use weeks count instead
			'weeks' 					=> false, // Look ahead this many weeks
			// Query arguments
			'category' 					=> false, // ID or slug
			'categories' 				=> false, // Comma-separated list of IDs
			'limit' 					=> false, // Show at most this many events
			'order' 					=> false,
			// Appearance arguments
			'allow_multiple_markers' 	=> false,
			'open_only' 				=> true,
			'show_date' 				=> true,
			'show_excerpt' 				=> false,
			'excerpt_length' 			=> 55,
			'legacy' 					=> false, // Force the legacy maps fetching method
			'featured_image' 			=> false,
			'class' 					=> false,
			'template' 					=> 'get_shortcode_events_map_marker_body_output', // Always a template class call
		));
		$args['featured_image'] 		= $this->_arg_to_bool($args['featured_image']);
		$args['show_date'] 				= $this->_arg_to_bool($args['show_date']);
		$args['show_excerpt'] 			= $this->_arg_to_bool($args['show_excerpt']);
		$args['allow_multiple_markers'] = $this->_arg_to_bool($args['allow_multiple_markers']);
		$class 							= $args['class'] ? 'class="' . $args['class'] . '"' : '';

		$query 							= $this->_to_query_args($args);

		$order_method 					= $args['order']
					? function() use ( $args ) { return $args['order']; }
					: false ;
		if ( $order_method ) {
			add_filter( 'eab-collection-date_ordering_direction', $order_method );
		}

		$maps = array();
		if ( $this->_arg_to_bool( $args['legacy'] ) ) {
			// Lookahead - depending on presence, use regular upcoming query, or poll week count
			if ( $args['lookahead'] ) {
				$method = $args['weeks']
					? function() use ( $args ) { return $args['weeks']; }
					: false;
				;
				if ( $method ) {
					add_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}
				$events = Eab_CollectionFactory::get_upcoming_weeks( $args['date'], $query );
				if ( $method ) {
					remove_filter( 'eab-collection-upcoming_weeks-week_number', $method );
				}
			} else {
				// No lookahead, get the full month only
				$events = Eab_CollectionFactory::get_upcoming( $args['date'], $query );
			}
			if ( $order_method ) {
				remove_filter( 'eab-collection-date_ordering_direction', $order_method );
			}

			$model 		= new AgmMapModel;
			$raw_maps 	= $model->get_custom_maps($events->query);
			if ( empty( $raw_maps ) ) {
				return $content;
			}

			foreach ( $raw_maps as $key => $map ) {
				if ( empty( $map['markers'] ) || count( $map['markers'] ) > 1 ) continue;
				$event = !empty($map['post_ids']) && !empty($map['post_ids'][0])
					? new Eab_EventModel(get_post($map['post_ids'][0]))
					: false
				;
				if ( !$event ) continue;

				$map['markers'][0]['title'] = $event->get_title();
				$map['markers'][0]['body'] = Eab_Template::util_apply_shortcode_template($event, $args);
				if ( $args['featured_image'] ) {
					$icon = $event->get_featured_image_url();
					if ($icon) $map['markers'][0]['icon'] = $icon;
				}

				$maps[] = $map;
			}
		} else {
			if ( $args['lookahead'] ) {
				$method = $args['weeks']
					? function() use ( $args ) { return $args['weeks']; }
					: false;
				;
				if ($method) add_filter('eab-collection-upcoming_weeks-week_number', $method);
				$events = Eab_CollectionFactory::get_upcoming_weeks_events($args['date'], $query);
				if ($method) remove_filter('eab-collection-upcoming_weeks-week_number', $method);
			} else {
				// No lookahead, get the full month only
				$events = Eab_CollectionFactory::get_upcoming_events($args['date'], $query);

			}
			if ($order_method) remove_filter('eab-collection-date_ordering_direction', $order_method);

			$open_only = $this->_arg_to_bool($args['open_only']);
			foreach ($events as $event) {
				if ($open_only && !$event->is_open()) continue;
				$map = $event->get_raw_map();
				if (!is_array($map) || empty($map)) continue;
				if (empty($map['markers'])) continue;
				if (empty($args['allow_multiple_markers']) && count($map['markers']) > 1) continue;

				// Even with multiple markers, only deal with the first one
				$marker_body = Eab_Template::util_apply_shortcode_template($event, $args);
				$map['markers'][0]['title'] = $event->get_title();
				$icon = $args['featured_image']
					? $event->get_featured_image_url()
					: false
				;
				foreach ($map['markers'] as $idx => $mrk) {
					$map['markers'][$idx]['body'] = $marker_body;
					if ($args['featured_image'] && !empty($icon)) {
						$map['markers'][$idx]['icon'] = $icon;
					}
				}
				$maps[] = $map;
			}
		}

		if (!$maps) return $content;

		if (!is_array($map_args)) $map_args = array();
		$codec = new AgmMarkerReplacer;
		return "<div {$class}>" . $codec->create_overlay_tag($maps, $map_args) . '</div>';
	}

	function add_events_map_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Ereigniskarten-Shortcode', 'eab'),
			'tag' => 'eab_events_map',
			'note' => __('Benötigt <a href="https://n3rds.work/piestingtal_source/ps-google-maps-plugin/" target="_blank">PS Google Maps Plugin</a>.', 'eab'),
			'arguments' => array(
				'date' => array('help' => __('Startdatum - Standard jetzt', 'eab'), 'type' => 'string:date'),
				'relative_date' => array('help' => __('Ein Datum relativ zum Jetzt oder zum Datum Argument', 'eab'), 'type' => 'string:date_strtotime'),
				'lookahead' => array('help' => __('Verwende keine monatliche Standardseite - verwende stattdessen die Anzahl der Wochen', 'eab'), 'type' => 'boolean'),
				'weeks' => array('help' => __('Schaue so viele Wochen nach vorne', 'eab'), 'type' => 'integer'),
				'category' => array('help' => __('Ereignisse aus dieser Kategorie anzeigen (ID oder Slug)', 'eab'), 'type' => 'string:or_integer'),
				'categories' => array('help' => __('Ereignisse aus diesen Kategorien anzeigen - Akzeptiert eine durch Kommas getrennte Liste von IDs', 'eab'), 'type' => 'string:id_list'),
				'limit' => array('help' => __('Zeige höchstens so viele Veranstaltungen', 'eab'), 'type' => 'integer'),
				'order' => array('help' => __('Sortiere Ereignisse in diese Richtung', 'eab'), 'type' => 'string:sort'),
				'allow_multiple_markers' => array('help' => __('Anzeigen von Karten mit mehreren Markierungen zulassen - standardmäßig true.', 'eab'), 'type' => 'boolean'),
				'open_only' => array('help' => __('Nur offene Ereignisse anzeigen - standardmäßig true.', 'eab'), 'type' => 'boolean'),
				'show_date' => array('help' => __('Ereignisdatum in der Markierung anzeigen - standardmäßig true.', 'eab'), 'type' => 'boolean'),
				'show_excerpt' => array('help' => __('Ereignis Auszug auf dem Marker  anzeigen.', 'eab'), 'type' => 'boolean'),
				'featured_image' => array('help' => __('Verwende ein Ereignisbild anstelle von Kartenmarkierungen', 'eab'), 'type' => 'boolean'),
				'template' => array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
				'...' => array('help' => __('und Google Maps Shortcode-Attribute', 'eab')),
			),
			'advanced_arguments' => array('template'),
		);
		return $help;
	}

	/**
	 * Calendar shortcode handler.
	 */
	function process_calendar_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
			'network' => false,
			'date' => strtotime(date('Y-m')), // Calendar shortcode uses Y-m date format on paged.
			'relative_date' => false,
		// Query arguments
			'category' => false, // ID or slug
			'categories' => false, // Comma-separated list of IDs
		// Appearance arguments
			'footer' => false,
			'class' => 'eab-shortcode_calendar',
			'navigation' => false,
			'track' => true,
			'title_format' => 'M Y',
			'short_title_format' => 'm-Y',
			'long_date_format' => false,
			'template' => 'get_shortcode_calendar_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
			'with_thumbnail' => false,
			'default_thumbnail' => false,
			'show_excerpt' => false,
			'show_old' => false,
			'excerpt_length' => 55,
		));
		
		if (!empty($_GET['date'])) {
			$date = strtotime($_GET['date']);
			if ($date) $args['date'] = $date;
		}

		if( $args['show_old'] ){
			add_filter( 'eab-collection/hide_old', '__return_false' );
		}

		$query = $this->_to_query_args($args);
		$events = ($args['network'] && is_multisite())
			? Eab_Network::get_upcoming_events(30)
			: Eab_CollectionFactory::get_upcoming_events($args['date'], $query)
		;

		$output = Eab_Template::util_apply_shortcode_template($events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_calendar_shortcode', eab_call_template('util_get_default_template_style', 'calendar'));
		return $output;
	}

	function add_calendar_shortcode_help ($help) {
		$help[] = array(
			'title' 	=> __('Kalender-Shortcode', 'eab'),
			'tag' 		=> 'eab_calendar',
			'arguments' => array(
				'network' 			=> array('help' => __('Abfragetyp (Erfordert <a href="https://n3rds.work/piestingtal_source/multisite-beitragsindex-plugin/" target="_blank">Multisite Beitragsindex</a> Plugin', 'eab'), 'type' => 'boolean'),
				'date' 				=> array('help' => __('Startdatum - Standard jetzt', 'eab'), 'type' => 'string:date'),
				'relative_date' 	=> array('help' => __('Ein Datum relativ zum Jetzt oder zum Datum Argument', 'eab'), 'type' => 'string:date_strtotime'),
				'category' 			=> array('help' => __('Ereignisse aus dieser Kategorie anzeigen (ID oder Slug)', 'eab'), 'type' => 'string:or_integer'),
				'categories' 		=> array('help' => __('Ereignisse aus diesen Kategorien anzeigen - Akzeptiert eine durch Kommas getrennte Liste von IDs', 'eab'), 'type' => 'string:id_list'),
				'navigation' 		=> array('help' => __('Navigation anzeigen', 'eab'), 'type' => 'boolean'),
				'track' 			=> array('help' => __('Behalte beim Navigieren die Bildlaufposition bei', 'eab'), 'type' => 'boolean'),
				'title_format' 		=> array('help' => __('Das im Navigationstitel verwendete Datumsformat ist standardmäßig "M Y"', 'eab'), 'type' => 'string:date_format'),
				'short_title_format'=> array('help' => __('Das Datumsformat für die kürzere Datumsdarstellung im Navigationstitel ist standardmäßig "m-Y"', 'eab'), 'type' => 'string:date_format'),
				'long_date_format' 	=> array('help' => __('Das Datumsformat für die Anzeige der Darstellung langer Datumsangaben verwendet standardmäßig Deine Datumseinstellungen', 'eab'), 'type' => 'string:date_format'),
				'footer' 			=> array('help' => __('Kalendertabellenfußzeile anzeigen', 'eab'), 'type' => 'boolean'),
				'class' 			=> array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
				'with_thumbnail' 	=> array('help' => __('Miniaturansicht des Ereignisses anzeigen', 'eab'), 'type' => 'boolean'),
				'default_thumbnail' => array('help' => __('Verwende diese Bild-URL als Miniaturansicht, wenn für das Ereignis kein geeigneter Bildsatz vorhanden ist', 'eab'), 'type' => 'string:url'),
				'show_excerpt' 		=> array('help' => __('Ereignisauszug in der Schnellübersicht anzeigen.', 'eab'), 'type' => 'boolean'),
				'excerpt_length' 	=> array('help' => __('Bestimme die Länge des Auszugs (Anzahl der Zeichen), die angezeigt werden sollen.', 'eab'), 'type' => 'integer'),
				'template' 			=> array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
				'override_styles' 	=> array('help' => __('Schalte die Verwendung der Standardstile um', 'eab'), 'type' => 'boolean'),
				'override_scripts' 	=> array('help' => __('Schalte die Verwendung von Standardskripten um', 'eab'), 'type' => 'boolean'),
				'show_old'			=> array('help' => __('Vergangene Ereignisse im Kalender anzeigen', 'eab'), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles', 'default_thumbnail'),
		);
		return $help;
	}

	/**
	 * Archive shortcode handler.
	 */
	function process_archive_shortcode ($args=array(), $content=false) {
		include_once( 'shortcodes/class-eab-archive-shortcode.php' );
		$args = $this->_preparse_arguments($args, array(
			'network' 			=> false, // Query type
			// Date arguments
			'date' 				=> false, // Starting date - default to now
			'relative_date' 	=> false,
			'lookahead' 		=> false, // Don't use default monthly page - use weeks count instead
			'weeks' 			=> false, // Look ahead this many weeks
			// Query arguments
			'category' 			=> false, // ID or slug
			'categories' 		=> false, // Comma-separated list of category IDs
			'limit' 			=> false, // Show at most this many events
			'order' 			=> false,
			// Paging arguments
			'paged' 			=> false,
			'page' 				=> 1,
			// Appearance arguments
			'class' 			=> false,
			'template' 			=> 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' 	=> false,
			'override_scripts' 	=> false,
			'with_thumbnail' 	=> false,
			'thumbnail_size'	=> false,
			'end_date'		=> false,
			'day_only'		=> false,
		));

		$shortcode = new Eab_Archive_Shortcode( $args );
		return $shortcode->output( $content );
	}

	function add_archive_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Archive shortcode', 'eab'),
			'tag' => 'eab_archive',
			'arguments' => array(
				'network' => array('help' => __('Abfragetyp (Erfordert <a href="https://n3rds.work/piestingtal_source/multisite-beitragsindex-plugin/" target="_blank">Multisite Beitragsindex</a> plugin)', 'eab'), 'type' => 'boolean'),
				'date' => array('help' => __('Startdatum - Standard jetzt', 'eab'), 'type' => 'string:date'),
				'relative_date' => array('help' => __('Ein Datum relativ zum Jetzt oder zum Datum Argument', 'eab'), 'type' => 'string:date_strtotime'),
				'lookahead' => array('help' => __('Verwende keine monatliche Standardseite - verwende stattdessen die Anzahl der Wochen', 'eab'), 'type' => 'boolean'),
				'weeks' => array('help' => __('Schaue so viele Wochen nach vorne', 'eab'), 'type' => 'integer'),
				'category' => array('help' => __('Ereignisse aus dieser Kategorie anzeigen (ID oder Slug)', 'eab'), 'type' => 'string:or_integer'),
				'categories' => array('help' => __('Ereignisse aus diesen Kategorien anzeigen - Akzeptiert eine durch Kommas getrennte Liste von IDs', 'eab'), 'type' => 'string:id_list'),
				'limit' => array('help' => __('Zeige höchstens so viele Veranstaltungen', 'eab'), 'type' => 'integer'),
				'order' => array('help' => __('Sortiere Ereignisse in diese Richtung', 'eab'), 'type' => 'string:sort'),
				'paged' => array('help' => __('Paging zulassen - Verwendung mit dem Argument "limit"', 'eab'), 'type' => 'boolean'),
				'page' => array('help' => __('Beginne auf dieser Seite', 'eab'), 'type' => 'integer'),
				'class' => array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
				'template' => array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
				'with_thumbnail' => array('help' => __('Miniaturansicht des Ereignisses anzeigen', 'eab'), 'type' => 'boolean'),
				'thumbnail_size' => array('help' => __('Stelle die Größe der Miniaturansichten ein (\'thumbnail\', \'large\', \'medium-large\', \'medium\', \'full\' oder \'150,150\'', 'eab'), 'type' => 'string'),
				'override_styles' => array('help' => __('Schalte die Verwendung der Standardstile um', 'eab'), 'type' => 'boolean'),
				'override_scripts' => array('help' => __('Schalte die Verwendung von Standardskripten um', 'eab'), 'type' => 'boolean'),
				'end_date' => array('help' => __('Ende (YYYY-MM-DD)', 'eab'), 'type' => 'string:date'),
				'day_only' => array('help' => __('Schalte die Anzeige nur der Ereignisse der aktuellen Tage um', 'eab'), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles'),
		);
		return $help;
	}

	/**
	 * Expired shortcode handler.
	 */
	function process_expired_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Appearance arguments
			'class' => false,
		// Query arguments
			'category' => false, // ID or slug
			'categories' => false, // Comma-separated list of category IDs
			'limit' => false, // Show at most this many events
			'order' => false,
		// Template options
			'template' => 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));

		$query = $this->_to_query_args($args);

		$order_method = $args['order']
			/*? create_function('', 'return "' . $args['order'] . '";')*/
			? function() {return "' . $args[order] . '";}
			: false
		;
		if ($order_method) add_filter('eab-collection-date_ordering_direction', $order_method);

		$events = Eab_CollectionFactory::get_expired_events($query);

		$output = Eab_Template::util_apply_shortcode_template($events, $args);//eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}

	function add_expired_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Shortcode für abgelaufene Ereignisse', 'eab'),
			'tag' => 'eab_expired',
			'arguments' => array(
				'class' => array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
				'category' => array('help' => __('Ereignisse aus dieser Kategorie anzeigen (ID oder Slug)', 'eab'), 'type' => 'string:or_integer'),
				'categories' => array('help' => __('Ereignisse aus diesen Kategorien anzeigen - Akzeptiert eine durch Kommas getrennte Liste von IDs', 'eab'), 'type' => 'string:id_list'),
				'limit' => array('help' => __('Zeige höchstens so viele Veranstaltungen', 'eab'), 'type' => 'integer'),
				'order' => array('help' => __('Sortiere Ereignisse in diese Richtung', 'eab'), 'type' => 'string:sort'),
				'template' => array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
				'override_styles' => array('help' => __('Schalte die Verwendung der Standardstile um', 'eab'), 'type' => 'boolean'),
				'override_scripts' => array('help' => __('Schalte die Verwendung von Standardskripten um', 'eab'), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles'),
		);
		return $help;
	}

	/**
	 * Single event shortcode handler.
	 */
	function process_single_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
			'id' => false,
			'slug' => false,
		// Appearance arguments
			'class' => false,
			'template' => 'get_shortcode_single_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));
		$args['id'] = $this->_arg_to_int($args['id']);
		$event = false;

		if ($args['id']) $event = new Eab_EventModel(get_post($args['id']));
		else {
			$q = new WP_Query(array(
				'post_type' => Eab_EventModel::POST_TYPE,
				'name' => $args['slug'],
				'posts_per_page' => 1,
			));
			if (isset($q->posts[0])) $event = new Eab_EventModel($q->posts[0]);
		}
		if (!$event) return $content;

		$output = Eab_Template::util_apply_shortcode_template($event, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) {
			wp_enqueue_script('eab_event_js');
			do_action('eab-javascript-do_enqueue_api_scripts');
		}
		return $output;
	}

	function add_single_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Einzelereignis-Shortcode', 'eab'),
			'tag' => 'eab_single',
			'arguments' => array(
				'id' => array('help' => __('Ereignis-ID, die angezeigt werden soll', 'eab'), 'type' => 'integer'),
				'slug' => array('help' => __('Event von diesem Slug anzeigen', 'eab'), 'type' => 'string'),
				'class' => array('help' => __('Wende diese CSS-Klasse an', 'eab'), 'type' => 'string'),
				'template' => array('help' => __('Subtemplate-Datei oder Vorlagenklassenaufruf', 'eab'), 'type' => 'string'),
				'override_styles' => array('help' => __('Schalte die Verwendung der Standardstile um', 'eab'), 'type' => 'boolean'),
				'override_scripts' => array('help' => __('Schalteie die Verwendung von Standardskripten um', 'eab'), 'type' => 'boolean'),
			),
			'advanced_arguments' => array('template', 'override_scripts', 'override_styles'),
		);
		return $help;
	}
}
