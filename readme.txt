=== PS Events ===
Contributors: DerN3rd (PSOURCE)
Tags: multisite, events, kalender, buddypress
Requires at least: 4.9
Tested up to: 6.8.1
Stable tag: 1.0.1
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PS Events fügt Deiner Webseite oder Deiner Multisite ein mächtiges Events & Bookings System hinzu.

== Description ==

=  Verkaufe Tickets für Dein Webinar oder Dein wiederkehrendes Verkaufsseminar =

oder baue ein komplettes Event-Management-Netzwerk wie Eventbrite oder Facebook Events auf.

Bewerbe, verwalte und verkaufe Tickets für Deine Veranstaltung.

= Bewerbe und verwalte Deine Veranstaltung =

Erstelle Veranstaltungsseiten mit Reservierungen, Ticketverkäufen, Werbevideos, Veranstaltungsinformationen, Standort und Karte. 
Mit PS Events kannst Du auch Dein nächstes lokales Meetup, Deine nächste Konferenz oder Online-Veranstaltung verwalten.

=  Die perfekte Design- Passform =

PS Events passt sich dem Design Deines Themes an. 
Du musst nicht mehr mit speziellen Vorlagen oder anderem Müll herumspielen - nur mit dem gewünschten Layout. 
Verwende Shortcodes und integrierte Widgets, um Elemente genau dort zu integrieren, wo Du möchtest - Seitenleisten, Seiten, Beiträge - wo immer Du willst.

PS Events enthält Shortcodes, Add-Ons und Widgets für mehr Kontrolle über Layouts.

=  Ticketverkauf =

Verwende PayPal direkt nach dem Auschecken oder zusammen mit PSeCommerce, um zusätzliche Zahlungsgateways zu aktivieren.


=  Ereignis-Countdown =

Baue mit einem Countdown die Vorfreude auf Dein Online-Ereignis auf und leite Benutzer nach Ablauf des Timers automatisch zu Deinem Live-Stream weiter. 
Wir finden das ziemlich cool. 
Mit PS Events kannst Du ganz einfach wiederkehrende Ereignisse festlegen. 
Stelle es einmal ein und da ist es, genau wie am Schnürchen.

PS Events beinhaltet die Integration von E-Newsletter für einen besseren E-Mail-Follow-up-Stil.

=  Kalenderfreigabe & Benachrichtigungen =

Lasse Gäste mit einem Klick Ereignisse zu iCal oder Google Kalender hinzufügen. 
Sende automatisch Updates für Gäste, wenn sich die Ereignisinformationen ändern. 
Integriere in den E-Newsletter und verwende benutzerdefinierte E-Mail-Vorlagen, um Deine Marke besser darzustellen - oder um sich einfach an Dein Party-Theme zu halten.

=  Zeit sparen - Service Sync =

Das Teilen Deiner Veranstaltungen in sozialen Medien sollte einfach sein, daher haben wir es so gemacht. 
Erstelle ein Ereignis mit Facebook-Ereignissen, und PS Events veröffentlicht es automatisch auf Deiner Webseite.

Du kannst sogar Ereignisse aus anderen beliebten Kalendern abrufen, indem Du sie aus Google Kalender und Meetup.com importierst.   

=  Integration des Google Maps Plugins =

Richtungen waren noch nie so lustig. 
Teile Karten mit Stil und füge benutzerdefinierte Drop-Pin-Symbole hinzu. 
Mit der Integration des Google Maps Plugins kannst Du Deiner Veranstaltung eine Karte mit benutzerdefinierten Symbolen und Formatsteuerung hinzufügen, ohne mit Code herumspielen zu müssen.

Wunderschönes, benutzerdefiniertes und einzigartiges Kartendesign mit Google Maps-Plugin und Snazzy Map-Kompatibilität.

Lasse Benutzer sich mit ihrem sozialen Netzwerk anmelden.

=  Einfaches RSVP & Social Share =

Erstelle Deine Mailingliste und mache es Benutzern einfach, ihren Platz mit einem einfachen E-Mail-RSVP zu reservieren. 
Begeistere die Teilnehmer für Deine Veranstaltung. Verbreite das Wort mit automatisiertem Social Sharing, wenn Teilnehmer sich melden, damit sie ihren Freunden mitteilen können, was sie verpassen.


== ChangeLog ==

= 1.0.2 =

* Fix: PHP 8 Warning "Attempt to read property post_type on null" in agm_google_maps_post_meta_address und weiteren Hooks behoben
* Compat: Alle direkten $post->post_type Zugriffe mit instanceof WP_Post Guard abgesichert (archive_content, single_content, save_event_meta, respawn_recurring_instances, manage_post_actions, handle_post_updated_messages, post_type_link)
* Compat: Gleiche Guards in lib/default_filters.php, lib/widgets/Attendees_Widget.class.php und lib/plugins/lib/class_eab_mp_bridge.php
* HTML5: Veraltete border="0" Attribute in PayPal-Formular-Markup durch style="border:0" ersetzt (class_eab_template.php, eab-payment-multiple_prices.php)

= 1.0.1 =

* Security: Escaping, Input-Sanitization und Nonce/Capability-Checks verbessert
* Fix: SQL/AJAX-Sicherheitsfixes und weitere WPCS-Anpassungen
* Compatibility: Verbesserungen fuer PHP 8+

= 1.0.0 =

* Release