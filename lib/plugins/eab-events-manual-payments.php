<?php
/*
Plugin Name: Manuelle Zahlungen
Description: Ermöglicht Benutzern das manuelle Bezahlen (Scheck, Überweisung usw.)
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 0.3
Author: DerN3rd
AddonType: Events
*/

/*
Detail: Fügt dem Frontend Codes für die manuellen Zahlungsanweisungen hinzu. Diese Anweisungen werden auf dieser Einstellungsseite unter <b>Manuelle Zahlungseinstellungen</b> eingegeben. Fügt der Ereignisseite außerdem Codes hinzu, sodass Du auswählen kannst, dass ein Mitglied manuell bezahlt hat.
*/


class Eab_Events_ManualPayments {

	private $_data;
	private $_email_qr_images = array();

	/**
	 * Constructor
	 */	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	/**
	 * Run the Addon
	 *
	 */	
	public static function serve () {
		$me = new Eab_Events_ManualPayments;
		$me->_add_hooks();
	}

	/**
	 * Hooks to the main plugin Events+
	 *
	 */	
	private function _add_hooks () {
		add_action('eab-settings-after_payment_settings', array($this, 'show_settings'));
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_qr_script'));
		add_action('wp_ajax_eab_manual_payment', array($this, 'do_payment'));
		add_action('wp_ajax_eab_approve_manual_payment', array($this, 'approve_payment'));
		add_filter('eab-event-show_pay_note', array($this,'will_show_pay_note'), 10, 2);
		add_filter('eab-event-payment_status', array($this,'status'), 10, 2);
		add_filter('eab-event-booking_metabox_content', array($this,'add_approve_payment'), 10, 2);
		add_filter('eab-settings-before_save', array($this,'save_settings'));
		add_filter('eab-event-payment_forms', array($this,'add_select_button'), 10, 2);
		add_filter('eab-event-after_payment_forms', array($this,'add_instructions'), 10, 2);
		add_filter('eab-rsvps-non_paid-email-body', array($this, 'add_payment_details_to_reminder'), 10, 3);
		add_action('phpmailer_init', array($this, 'embed_email_qr_codes'));
	}

	function enqueue_qr_script () {
		wp_enqueue_script(
			'eab-manual-payment-qr',
			EAB_PLUGIN_URL . 'js/eab-manual-payment-qr.min.js',
			array(),
			'1.0.0',
			true
		);
	}

	/**
	 * Ajax call when user clicks payment button
	 */	
	function do_payment() {
		check_ajax_referer( 'manual-payment-nonce', 'nonce' );
		$user_id = get_current_user_id();
		$event_id = isset($_POST["event_id"]) ? absint($_POST["event_id"]) : 0;
		if ( !$user_id || !$event_id || !get_post($event_id) )
			die();
		$event = new Eab_EventModel(get_post($event_id));
		if ( !$event->get_user_booking_id($user_id) )
			die();
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment" ) ) );
		if ( !is_array( $payments ) )
			$payments = array();
		else {
			foreach ( $payments as $payment ) { // Make a check
				if ( $payment["id"] == $user_id ) // User has a record before!?
					die();
			}
		}
                
		array_push( $payments, array( "id"=>$user_id, "stat"=>"pending"));
		//$payments = array_filter( array_unique( $payments ) ); // Clear empty records, just in case
                $payments = array_map( "unserialize", array_unique( array_map( "serialize", $payments ) ) );
		Eab_EventModel::update_booking_meta( $event_id, "manual_payment", serialize( $payments ) );
		die();
	}

	/**
	 * Adds the button to the front end that reveals instructions box
	 */	
	function add_select_button( $content, $event_id ) {
		if ($this->_data->get_option('paypal_email')) $content .= '<br /><br />';
		$content .= '<a class="psourceevents-yes-submit" style="float:none !important" href="javascript:void(0)" id="manual_payment_select_'.$event_id.'">'. $this->_data->get_option('manual_payment_select') . '</a>';
		$content .= '<script type="text/javascript">';
		$content .= 'jQuery(document).ready(function($){
						$("#manual_payment_select_'.$event_id.'").on("click", function() {
							$("#manual_payment_instructions_'.$event_id.'").slideToggle("slow");
						});
					});';
		$content .= '</script>';
		
		return $content;
	}

	/**
	 * Adds instructions box to the front end
	 */	
	function add_instructions( $content, $event_id ) {
		global $current_user;
		$content .= '<div class="message" id="manual_payment_instructions_'.$event_id.'" style="display:none">';
		$button = '<a class="psourceevents-yes-submit" style="float:none !important" href="javascript:void(0)" id="manual_payment_pay_'.$event_id.'">'. $this->_data->get_option('manual_payment_pay') . '</a>';
		$content .= wpautop(str_replace("MANUALPAYMENTBUTTON", $button,$this->_data->get_option('manual_payment_instructions')));
		$content .= $this->get_payment_details_markup($event_id, $current_user->ID, true);
		$content .= '</div>';
		$content .= '<script type="text/javascript">';
		$content .= 'jQuery(document).ready(function($){
						$("#manual_payment_pay_'.$event_id.'").on("click",function() {
							$.post("'.admin_url('admin-ajax.php').'", {
								"action": "eab_manual_payment",
								"user_id":'.$current_user->ID.',
								"event_id":'.$event_id.',
								"nonce":"'.wp_create_nonce("manual-payment-nonce").'"
							}, function (data) {
								if (data && data.error) {alert(data.error);}
								else {
									$("#manual_payment_pay_'.$event_id.'").css("opacity","0.2");
									alert("'.__('Vielen Dank für die Zahlung!','eab').'");
								}
							});
							return false;
						});
					});';
		$content .= '</script>';
		return $content;
	}

	/**
	 * Warn admin if Manual Button is not inserted
	 */
	function show_nags() {
		if ( strpos($this->_data->get_option('manual_payment_instructions'), "MANUALPAYMENTBUTTON") === false) {
			echo '<div class="error"><p>' .
				__("Du hast kein Schlüsselwort MANUALPAYMENTBUTTON im Feld Anweisungen. Dies bedeutet, dass es keine Schaltfläche gibt und der Benutzer Dich nicht darüber informieren kann, dass er eine Zahlung geleistet hat, was wiederum bedeutet, dass die manuelle Zahlung praktisch unbrauchbar ist.", 'eab') .
			'</p></div>';
		}
	}
	 
	/**
	 * Add Addon settings to the other admin options to be saved
	 */	
	function save_settings( $options ) {
		$data = isset($_POST['event_default']) ? wp_unslash($_POST['event_default']) : array();
		$options['manual_payment_select'] = isset($data['manual_payment_select']) ? sanitize_text_field($data['manual_payment_select']) : '';
		$options['manual_payment_pay'] = isset($data['manual_payment_pay']) ? sanitize_text_field($data['manual_payment_pay']) : '';
		$options['manual_payment_instructions'] = isset($data['manual_payment_instructions']) ? wp_kses_post($data['manual_payment_instructions']) : '';
		$options['manual_payment_account_holder'] = isset($data['manual_payment_account_holder']) ? sanitize_text_field($data['manual_payment_account_holder']) : '';
		$options['manual_payment_iban'] = isset($data['manual_payment_iban']) ? $this->sanitize_iban($data['manual_payment_iban']) : '';
		$options['manual_payment_bic'] = isset($data['manual_payment_bic']) ? strtoupper(preg_replace('/[^A-Z0-9]/', '', sanitize_text_field($data['manual_payment_bic']))) : '';
		$options['manual_payment_bank_name'] = isset($data['manual_payment_bank_name']) ? sanitize_text_field($data['manual_payment_bank_name']) : '';
		$options['manual_payment_reference_prefix'] = isset($data['manual_payment_reference_prefix']) ? sanitize_text_field($data['manual_payment_reference_prefix']) : '';
		
		return $options;
	}
	
	/**
	 * Admin settings
	 *
	 */	
	function show_settings() {
		if ( !class_exists( 'PSource_HelpTooltips' ) ) 
			require_once dirname(__FILE__) . '/lib/class_wd_help_tooltips.php';
		$tips = new PSource_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );
		?>
		<div id="eab-settings-manual_payments" class="eab-metabox postbox">
				<h3 class="eab-hndle"><?php _e('Manuelle Zahlungseinstellungen', 'eab'); ?></h3>
				<div class="eab-inside">
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_select" ><?php _e('Wähle den Schaltflächentext aus', 'eab'); ?></label>
						<input type="text" size="40" name="event_default[manual_payment_select]" value="<?php print $this->_data->get_option('manual_payment_select'); ?>" />
						<span><?php echo $tips->add_tip(__('Dies ist der Text, der auf der Schaltfläche Manuelle Zahlung auswählen angezeigt wird.', 'eab')); ?></span>
					</div>
					    
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_pay" ><?php _e('Bezahlbutton Text', 'eab'); ?></label>
						<input type="text" size="40" name="event_default[manual_payment_pay]" value="<?php print $this->_data->get_option('manual_payment_pay'); ?>" />
						<span><?php echo $tips->add_tip( __('Dies ist der Text, der auf der Schaltfläche Bezahlen angezeigt wird. Der Benutzer muss auf diese Schaltfläche klicken, nachdem er die Zahlung getätigt hat.', 'eab')); ?></span>
					</div>
					
					<div class="eab-settings-settings_item">
					    <label for="psource_event-manual_payment_instructions" ><?php _e('Anleitung', 'eab'); ?>&nbsp;:</label>
						<span><?php echo $tips->add_tip( __('Schreibe hier das Verfahren, das der Benutzer für eine manuelle Zahlung ausführen muss. Verwende den MANUALPAYMENTBUTTON, um den Pay Button an der gewünschten Stelle einzufügen.', 'eab')); ?></span>
						<?php wp_editor( $this->_data->get_option('manual_payment_instructions'), 'manualpaymentsinstructions', array('textarea_name'=>'event_default[manual_payment_instructions]', 'textarea_rows' => 5) ); ?>
					</div>

					<div class="eab-settings-settings_item">
						<label for="psource_event-manual_payment_account_holder"><?php _e('Kontoinhaber', 'eab'); ?></label>
						<input type="text" size="40" id="psource_event-manual_payment_account_holder" name="event_default[manual_payment_account_holder]" value="<?php echo esc_attr($this->_data->get_option('manual_payment_account_holder')); ?>" />
					</div>
					<div class="eab-settings-settings_item">
						<label for="psource_event-manual_payment_iban"><?php _e('IBAN', 'eab'); ?></label>
						<input type="text" size="40" id="psource_event-manual_payment_iban" name="event_default[manual_payment_iban]" value="<?php echo esc_attr($this->_data->get_option('manual_payment_iban')); ?>" />
						<span><?php echo $tips->add_tip(__('Für den EPC-QR-Code werden ein gültiger IBAN, Kontoinhaber, EUR als Währung und ein positiver Veranstaltungspreis benötigt.', 'eab')); ?></span>
					</div>
					<div class="eab-settings-settings_item">
						<label for="psource_event-manual_payment_bic"><?php _e('BIC (optional)', 'eab'); ?></label>
						<input type="text" size="16" id="psource_event-manual_payment_bic" name="event_default[manual_payment_bic]" value="<?php echo esc_attr($this->_data->get_option('manual_payment_bic')); ?>" />
						<label for="psource_event-manual_payment_bank_name"><?php _e('Bankname (optional)', 'eab'); ?></label>
						<input type="text" size="30" id="psource_event-manual_payment_bank_name" name="event_default[manual_payment_bank_name]" value="<?php echo esc_attr($this->_data->get_option('manual_payment_bank_name')); ?>" />
					</div>
					<div class="eab-settings-settings_item">
						<label for="psource_event-manual_payment_reference_prefix"><?php _e('Referenzpräfix (optional)', 'eab'); ?></label>
						<input type="text" size="30" id="psource_event-manual_payment_reference_prefix" name="event_default[manual_payment_reference_prefix]" value="<?php echo esc_attr($this->_data->get_option('manual_payment_reference_prefix')); ?>" />
						<span><?php echo $tips->add_tip(__('Die Überweisungsreferenz enthält immer die Event- und Buchungs-ID.', 'eab')); ?></span>
					</div>
					    
				</div>
		    </div>
		<?php
	}

	/**
	 * Check if 'You havent paid for this event' note will be displayed
	 * If user is approved to be paid, return false, i.e don't show pay note
	 * Otherwise return whatever sent here
	 */	
	function will_show_pay_note( $show_pay_note, $event_id ) {
		global $current_user;
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment") ) );
		if ( is_array( $payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $current_user->ID AND $payment["stat"] == "paid" )
					return false; // User approved to be paid
			}
		}
		return $show_pay_note;
	}

	/**
	 * Modify payment status text if user is manually selected that he paid
	 */		
	function status( $payment_status, $user_id ) {
		global $post; // This object should be available as we are on Admin side
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $post->ID, "manual_payment") ) );
		if ( is_array( $payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $user_id )
					return $payment["stat"]; // User status is either paid or pending
			}
		}
		return $payment_status;
	}

	/**
	 * Approve a payment on the admin side
	 */	
	function approve_payment() {
		$user_id = isset($_POST["user_id"]) ? absint($_POST["user_id"]) : 0;
		$event_id = isset($_POST["event_id"]) ? absint($_POST["event_id"]) : 0;
		if ( !current_user_can('edit_post', $event_id) || !check_ajax_referer('approve-manual-payment-' . $event_id, 'nonce', false) )
			die( json_encode( array( "error" => __( "Keine Berechtigung", 'eab' ) ) ) );
		if ( !$user_id OR !$event_id )
			die( json_encode( array( "error" => __( "Benutzer-ID oder Ereignis-ID fehlt", 'eab' ) ) ) );
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $event_id, "manual_payment" ) ) );
		if ( is_array( $payments ) ) {
			$post  		= get_post( $event_id );
			$event 		= ( $post instanceof Eab_EventModel ) ? $post : new Eab_EventModel( $post );
			$booking_id = $event->get_user_booking_id( $user_id );
			foreach ( $payments as $key=>$payment ) { 
				if ( $payment["id"] == $user_id ) {
					$payments[$key]["stat"] = "paid";
					//$payments = array_filter( array_unique( $payments ) );
                    $payments = array_map( "unserialize", array_unique( array_map( "serialize", $payments ) ) );
					Eab_EventModel::update_booking_meta( $event_id, "manual_payment", serialize( $payments ) );
					Eab_EventModel::update_booking_meta( $booking_id, 'booking_transaction_key', true );
					Eab_EventModel::update_booking_meta( $booking_id, 'booking_ticket_count', true );
					die();
				}
			}
		}
		die( json_encode( array( "error" => __( "Datensatz konnte nicht gefunden werden", 'eab' ) ) ) );
	}
	
	/**
	 * Add manual payment link inside the RSVP box
	 */		
	function add_approve_payment( $content, $user_id ) {
		global $post;
		$payments = maybe_unserialize( stripslashes( Eab_EventModel::get_booking_meta( $post->ID, "manual_payment") ) );
		if ( is_array($payments ) ) {
			foreach ( $payments as $payment ) {
				if ( $payment["id"] == $user_id AND $payment["stat"] == 'pending' ) {
					$content .= '<div class="eab-guest-actions" id="div_approve_payment_'.$user_id.'">
					<a id="approve_payment_'.$user_id.'" href="javascript:void(0)" class="eab-guest-manual_payment" >' .
					__('Zahlung genehmigen', 'eab') .
					'</a></div>';
					$content .= '<script type="text/javascript">';
					$content .= 'jQuery(document).ready(function($){
									$("#approve_payment_'.$user_id.'").on("click",function() {
										if (confirm("'. __( "Bist Du sicher, diese Zahlung zu genehmigen?", 'eab' ) .'")){
											$.post(ajaxurl, {
												"action": "eab_approve_manual_payment",
												"user_id":'.$user_id.',
												"event_id":'.$post->ID.',
												"nonce":"'.wp_create_nonce('approve-manual-payment-' . $post->ID).'"
											}, function (data) {
												if (data && data.error) {alert(data.error);}
												else {
													$("#div_approve_payment_'.$user_id.'").parent(".eab-guest").find(".eab-guest-payment_info").html("'.__( 'Bezahlt', 'eab' ).'");
													$("#div_approve_payment_'.$user_id.'").remove();
												}
											},
											"json");
											return false;
										}
										else {return false;}
									});
								});';
					$content .= '</script>';
				}
			}
		}
		return $content;
	}

	private function sanitize_iban ($iban) {
		return strtoupper(preg_replace('/[^A-Z0-9]/', '', sanitize_text_field($iban)));
	}

	private function get_reference ($event_id, $booking_id) {
		$prefix = trim($this->_data->get_option('manual_payment_reference_prefix'));
		$suffix = 'EAB-' . absint($event_id) . '-' . absint($booking_id);
		$prefix = substr($prefix, 0, max(0, 139 - strlen($suffix)));
		return ($prefix ? $prefix . '-' : '') . $suffix;
	}

	private function get_payment_data ($event_id, $user_id) {
		$event = new Eab_EventModel(get_post($event_id));
		$booking_id = $event->get_user_booking_id($user_id);
		$iban = $this->sanitize_iban($this->_data->get_option('manual_payment_iban'));
		$holder = trim($this->_data->get_option('manual_payment_account_holder'));
		$bic = strtoupper($this->_data->get_option('manual_payment_bic'));
		$price = apply_filters('eab-payment-event_price-for_user', $event->get_price(), $event_id, $user_id);
		$ticket_count = (int) Eab_EventModel::get_booking_meta($booking_id, 'booking_ticket_count', 1);
		$amount = (float) $price * max(1, $ticket_count);

		if (
			!$booking_id ||
			strtoupper($this->_data->get_option('currency')) !== 'EUR' ||
			!$holder ||
			strlen($holder) > 70 ||
			!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) ||
			($bic && !preg_match('/^[A-Z0-9]{8}(?:[A-Z0-9]{3})?$/', $bic)) ||
			$amount <= 0
		) return false;

		return array(
			'booking_id' => $booking_id,
			'holder' => $holder,
			'iban' => $iban,
			'bic' => $bic,
			'bank_name' => trim($this->_data->get_option('manual_payment_bank_name')),
			'amount' => number_format($amount, 2, '.', ''),
			'reference' => $this->get_reference($event_id, $booking_id),
		);
	}

	private function get_epc_payload ($data) {
		return implode("\n", array(
			'BCD', '002', '1', 'SCT', $data['bic'], $data['holder'], $data['iban'],
			'EUR' . $data['amount'], '', '', $data['reference'],
		));
	}

	private function get_payment_details_markup ($event_id, $user_id, $include_qr=false) {
		$data = $this->get_payment_data($event_id, $user_id);
		if (!$data) return '';

		$markup = '<div class="eab-manual-payment-details"><p><strong>' . esc_html__('Banküberweisung', 'eab') . '</strong><br />' .
			esc_html__('Kontoinhaber:', 'eab') . ' ' . esc_html($data['holder']) . '<br />' .
			'IBAN: ' . esc_html($data['iban']) . '<br />';
		if ($data['bic']) $markup .= 'BIC: ' . esc_html($data['bic']) . '<br />';
		if ($data['bank_name']) $markup .= esc_html__('Bank:', 'eab') . ' ' . esc_html($data['bank_name']) . '<br />';
		$markup .= esc_html__('Betrag:', 'eab') . ' EUR ' . esc_html($data['amount']) . '<br />' .
			esc_html__('Verwendungszweck:', 'eab') . ' <strong>' . esc_html($data['reference']) . '</strong></p>';
		if ($include_qr) {
			$id = 'eab-manual-payment-qr-' . absint($event_id) . '-' . absint($user_id);
			$markup .= '<div id="' . esc_attr($id) . '" aria-label="' . esc_attr__('EPC-QR-Code für Banküberweisung', 'eab') . '"></div>' .
				'<script>jQuery(function(){new QRCode(document.getElementById("' . esc_js($id) . '"),{text:' . wp_json_encode($this->get_epc_payload($data)) . ',width:180,height:180});});</script>';
		}
		return $markup . '</div>';
	}

	function add_payment_details_to_reminder ($body, $event_id, $user_id) {
		$payments = maybe_unserialize(stripslashes(Eab_EventModel::get_booking_meta($event_id, 'manual_payment')));
		if (!is_array($payments)) return $body;
		foreach ($payments as $payment) {
			if (isset($payment['id'], $payment['stat']) && $payment['id'] == $user_id && $payment['stat'] === 'pending') {
				$data = $this->get_payment_data($event_id, $user_id);
				if (!$data) return $body;

				$body .= $this->get_payment_details_markup($event_id, $user_id);
				$png = $this->get_epc_qr_png($data);
				if ($png) {
					$cid = 'eab-manual-payment-qr-' . absint($event_id) . '-' . absint($data['booking_id']);
					$this->_email_qr_images[$cid] = $png;
					$body .= '<p><img src="cid:' . esc_attr($cid) . '" width="180" height="180" alt="' .
						esc_attr__('EPC-QR-Code für Banküberweisung', 'eab') . '" /></p>';
				}
				return $body;
			}
		}
		return $body;
	}

	private function get_epc_qr_png ($data) {
		if (!extension_loaded('gd')) return false;

		$autoload = dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php';
		if (is_readable($autoload)) require_once $autoload;
		if (!class_exists('chillerlan\\QRCode\\QRCode')) return false;

		try {
			$options = new \chillerlan\QRCode\QROptions(array(
				'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
				'outputBase64' => false,
				'scale' => 6,
				'quietzoneSize' => 4,
			));
			return (new \chillerlan\QRCode\QRCode($options))->render($this->get_epc_payload($data));
		} catch (\Exception $e) {
			return false;
		}
	}

	function embed_email_qr_codes ($mailer) {
		foreach ($this->_email_qr_images as $cid => $image) {
			if (false !== strpos($mailer->Body, 'cid:' . $cid)) {
				$mailer->addStringEmbeddedImage($image, $cid, 'epc-transfer.png', 'base64', 'image/png');
			}
			unset($this->_email_qr_images[$cid]);
		}
	}
}

Eab_Events_ManualPayments::serve();