<?php

class Eab_AdminTutorial {

    public static function serve() {
        if (!is_admin()) return false;
        $me = new self;
        $me->_add_hooks();
    }

    private function _add_hooks() {
        global $wp_version;
        if (version_compare($wp_version, "3.3") >= 0) {
            add_action('admin_init', array($this, 'tutorial'));
        }
        add_action('wp_ajax_eab_restart_tutorial', array($this, 'handle_tutorial_restart'));
    }

    function tutorial() {
        // Load the file
        require_once(EAB_PLUGIN_DIR . 'lib/pointers-tutorial/pointer-tutorials.php');

        $tutorial = new Pointer_Tutorial('eab_tutorial', __('Veranstaltungen', 'eab'), false, false);

        // Adding individual tutorial steps
        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#title', __('Ereignistitel', 'eab'), array(
            'content'  => '<p>' . __("Was ist los", 'eab') . '</p>',
            'position' => array('edge' => 'top', 'align' => 'center'),
            'post_type' => 'psource_event',
        ));

        if (defined('AGM_PLUGIN_URL')) {
            $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource_event_venue_label', __('Veranstaltungsort', 'eab'), array(
                'content'  => '<p>' . __("Wo? Gib die Adresse ein oder füge eine Karte ein, indem Du auf das Globussymbol klickst", 'eab') . '</p>',
                'position' => array('edge' => 'right', 'align' => 'left'),
                'post_type' => 'psource_event',
            ));
        } else {
            $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource_event_venue_label', __('Veranstaltungsort', 'eab'), array(
                'content'  => '<p>' . __("Wo? Gib die Adresse ein", 'eab') . '</p>',
                'position' => array('edge' => 'right', 'align' => 'left'),
                'post_type' => 'psource_event',
            ));
        }

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource_event_times_label', __('Veranstaltungszeit und -daten', 'eab'), array(
            'content'  => '<p>' . __("Wann? YYYY-mm-dd HH:mm", 'eab') . '</p>',
            'position' => array('edge' => 'right', 'align' => 'left'),
            'post_type' => 'psource_event',
        ));

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource_event_status_label', __('Ereignisstatus', 'eab'), array(
            'content'  => '<p>' . __("Ist diese Veranstaltung noch offen für RSVP?", 'eab') . '</p>',
            'position' => array('edge' => 'right', 'align' => 'left'),
            'post_type' => 'psource_event',
        ));

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource_event_paid_label', __('Ereignistyp', 'eab'), array(
            'content'  => '<p>' . __("Ist das eine bezahlte Veranstaltung? Wähle Ja und gib in das angezeigte Textfeld ein, wie viel Du berechnen möchtest", 'eab') . '</p>',
            'position' => array('edge' => 'right', 'align' => 'left'),
            'post_type' => 'psource_event',
        ));

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#wp-content-editor-container', __('Veranstaltungsdetails', 'eab'), array(
            'content'  => '<p>' . __("Mehr zur Veranstaltung", 'eab') . '</p>',
            'position' => array('edge' => 'bottom', 'align' => 'center'),
            'post_type' => 'psource_event',
        ));

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#psource-event-bookings', __("Veranstaltungs RSVPs", 'eab'), array(
            'content'  => '<p>' . __("Sieh, wer anwesend ist, wer anwesend sein kann und wer nicht, nachdem Du die Veranstaltung veröffentlicht hast", 'eab') . '</p>',
            'position' => array('edge' => 'bottom', 'align' => 'center'),
            'post_type' => 'psource_event',
        ));

        $tutorial->add_step(admin_url('post-new.php?post_type=psource_event'), 'post-new.php', '#publish', __('Veröffentlichen', 'eab'), array(
            'content'  => '<p>' . __("Jetzt ist es Zeit, die Veranstaltung zu veröffentlichen", 'eab') . '</p>',
            'position' => array('edge' => 'right', 'align' => 'center'),
            'post_type' => 'psource_event',
        ));

        // Start the tutorial
        $tutorial->initialize();
        return $tutorial;
    }

	/**
	 * Handles tutorial restart requests.
	 */
	function handle_tutorial_restart () {
		$tutorial = $this->tutorial();
		$step = (int)$_POST['step'];
		$tutorial->restart($step);
		die;
	}
}