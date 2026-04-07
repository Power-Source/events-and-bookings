<?php


class Eab_Admin_Get_Started_Menu {
	private $_data; // Deklaration der Eigenschaften
    private $_api;
	public function __construct( $parent ) {
		$id = add_submenu_page(
			$parent,
			__("Eventboard", eab_domain()),
			__("Eventboard", eab_domain()),
			'manage_options',
			'eab_welcome',
			array($this,'render')
		);

		$eab = events_and_bookings();
		$this->_data = $eab->_data;
		$this->_api = $eab->_api;
	}

	function render() {
		?>
		<div class="wrap">
			<h1><?php _e('PS-Events Dashboard', eab_domain() ); ?></h1>

			<p>
				<?php _e('PS-Events fügt Deiner Webseite oder Deiner Multisite ein mächtiges Events & Bookings System hinzu.', eab_domain() ) ?>
			</p>

			<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
				<div id="eab-actionlist" class="eab-metabox postbox">
					<h3 class="eab-hndle"><?php _e('Loslegen', eab_domain() ); ?></h3>
					<div class="eab-inside">
						<div class="eab-note"><?php _e('Du bist fast bereit! Befolge diese Schritte und erstelle Ereignisse auf Deiner WordPress-Seite.', eab_domain() ); ?></div>
						<ol>
							<li>
								<?php _e('Bevor Du ein Ereignis erstellst, musst Du einige Grundeinstellungen konfigurieren, z. B. Deinen Root-Slug und die Zahlungsoptionen.', eab_domain() ); ?>
								<a href="<?php echo esc_url('edit.php?post_type=psource_event&page=eab_settings&eab_step=1'); ?>" class="eab-goto-step button" id="eab-goto-step-0" ><?php _e('Konfiguriere Deine Einstellungen', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('Jetzt kannst Du Dein erstes Ereignis erstellen.', eab_domain() ); ?>
								<a href="<?php echo esc_url('post-new.php?post_type=psource_event&eab_step=2'); ?>" class="eab-goto-step button"><?php _e('Ereignis hinzufügen', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('Du kannst vorhandenen Ereignisse jederzeit anzeigen und bearbeiten.', eab_domain() ); ?>
								<a href="<?php echo esc_url('edit.php?post_type=psource_event&eab_step=3'); ?>" class="eab-goto-step button"><?php _e('Ereignisse bearbeiten', eab_domain() ); ?></a>
							</li>
							<li>
								<?php _e('Das Archiv zeigt eine Liste der bevorstehenden Ereignisse auf Deiner Seite an.', eab_domain() ); ?>
								<a href="<?php echo home_url($this->_data->get_option('slug')) . '/'; ?>" class="eab-goto-step button"><?php _e('Veranstaltungsarchiv', eab_domain() ); ?></a>
							</li>
						</ol>
					</div>
				</div>
			</div>

			<?php if (!defined('PSOURCE_REMOVE_BRANDING') || !constant('PSOURCE_REMOVE_BRANDING')) { ?>
				<div class="eab-metaboxcol metabox-holder eab-metaboxcol-one eab-metaboxcol-center">
					<div id="eab-helpbox" class="eab-metabox postbox">
						<h3 class="eab-hndle"><?php _e('Brauchst Du Hilfe?', eab_domain() ); ?></h3>
						<div class="eab-inside">
							<ol>
								<li><a href="https://psource.eimen.net/wiki/ps-events-dokumentation/"><?php _e('Zur Dokumentation', eab_domain() ); ?></a></li>
								<li><a href="https://psource.eimen.net/psource-support-forum/"><?php _e('Finde Hilfe im Support-Forum', eab_domain() ); ?></a></li>
								<li><a href="https://psource.eimen.net/events-and-bookings"><?php _e('GitHub Repo', eab_domain() ); ?></a></li>
								<li><a href="https://psource.eimen.net/psource-manager/"><?php _e('Halte PS Events aktuell und finde noch mehr PSOURCE für Dein Projekt', eab_domain() ); ?></a></li>
							</ol>
						</div>
					</div>
				</div>
			<?php } ?>

			<div class="clear"></div>

			<div class="eab-dashboard-footer">

			</div>
		</div>
		<?php
	}
}