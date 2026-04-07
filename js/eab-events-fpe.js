(function($) {

    // Global variable to store media frame
    var eab_media_frame;

    /**
     * Shows/hides attendance information.
     */
    function toggle_rsvps() {
        var $rsvps = $("#eab-events-fpe-rsvps-wrapper");
        if ($rsvps.is(":visible")) $rsvps.slideUp('slow');
        else $rsvps.slideDown('slow');
        return false;
    }

    /**
     * Shows Fee box if the event is premium.
     */
    function toggle_fee() {
        var $select = $("#eab-events-fpe-is_premium");
        if (!$select.val()) return false;

        var $fee = $("#eab-events-fpe-event_fee-wrapper");

        var is_premium = parseInt($select.val());
        if (is_premium) $fee.show();
        else $fee.hide();
    }

    /**
     * Shows missing date/time-specific error.
     */
    function missing_datetime_error($erroneous) {
        if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
        $("#eab-events-fpe-date_time").append(
            '<div id="eab-events-fpe-date_time-error">' +
            l10nFpe.mising_time_date +
            '</div>'
        );
        return false;
    }

    /**
     * Shows invalid date/time-specific error.
     */
    function invalid_datetime_error($erroneous) {
        if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
        $("#eab-events-fpe-date_time").append(
            '<div id="eab-events-fpe-date_time-error">' +
            l10nFpe.check_time_date +
            '</div>'
        );
        return false;
    }

    /**
     * Shows general purpose level messages.
     */
    function show_message(msg, is_error) {
        var cls = is_error ? 'eab-events-fpe-error' : 'eab-events-fpe-success';
        $(".eab-events-fpe-notification").remove();
        $("#eab-events-fpe-ok_cancel").append(
            '<div class="eab-events-fpe-notification ' + cls + '"><p>' +
            msg +
            '</p></div>'
        );
        setTimeout(function() {
            $(".eab-events-fpe-notification").slideUp("slow");
        }, 2000);
    }

    /**
     * Normalizes a time string into 24-hours format array.
     * @param string Time string to normalilze
     * @return array [HH,mm]
     */
    function _time_string_to_array(time_string) {
        var time_parts = false;
        if (time_string.match(/[ap]m/i)) { // Yanks have been here
            var is_night = time_string.match(/pm/i);
            var normalized = time_string.replace(/[ap]m/i, '');
            time_parts = (normalized.indexOf(':') >= 0) ?
                normalized.split(':') :
                [normalized, '00'];
            if (is_night) {
                time_parts[0] = 12 + parseInt(time_parts[0], 10);
            }
        } else {
            time_parts = (time_string.indexOf(':') >= 0) ?
                time_string.split(':') :
                [time_string, '00'];
        }
        return time_parts ? time_parts : ['12', '00'];
    }

    /**
     * Parses date input in both German (dd.mm.yyyy) and ISO (yyyy-mm-dd) format.
     */
    function _parse_date_input(value) {
        if (!value) return false;
        var raw = $.trim(value);
        var parts = false;

        if (/^\d{2}\.\d{2}\.\d{4}$/.test(raw)) {
            parts = raw.split('.');
            return new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            parts = raw.split('-');
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        }

        var parsed = new Date(raw);
        if (isNaN(parsed.getTime())) return false;
        return parsed;
    }

    function _to_iso_date(dateObj) {
        var y = dateObj.getFullYear();
        var m = ('0' + (dateObj.getMonth() + 1)).slice(-2);
        var d = ('0' + dateObj.getDate()).slice(-2);
        return y + '-' + m + '-' + d;
    }

    function _supports_native_date_input() {
        var input = document.createElement('input');
        input.setAttribute('type', 'date');
        return input.type === 'date';
    }

    function _iso_to_de(value) {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
        var p = value.split('-');
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    /**
     * Manage no start and end time checkboxes
     */
    jQuery(document).ready(function(e) {
        jQuery(document).on('change', '#psource_event_no_start_time_0', function() {
            if (document.getElementById("psource_event_no_start_time_0").checked == true) {
                jQuery('#eab-events-fpe-start_time').hide();
            } else {
                jQuery('#eab-events-fpe-start_time').show();
            }
        });
        jQuery(document).on('change', '#psource_event_no_end_time_0', function() {
            if (document.getElementById("psource_event_no_end_time_0").checked == true) {
                jQuery('#eab-events-fpe-end_time').hide();
            } else {
                jQuery('#eab-events-fpe-end_time').show();
            }
        });

        if (jQuery('#eab-events-fpe-start_time').val() == "00:00") {
            jQuery('.eab-events-fpe_wrap_time_start ').hide();
            jQuery('#eab-events-fpe-toggle_time__start').attr('checked', true);
        }
        if (jQuery('#eab-events-fpe-end_time').val() == "00:00") {
            jQuery('.eab-events-fpe_wrap_time_end').hide();
            jQuery('#eab-events-fpe-toggle_time__end').attr('checked', true);
        }
    });
    /**
     * Sends save request and shows general message
     */
    function send_save_request() {
        if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
        var has_start = false,
            has_end = false,
            $start_date = $("#eab-events-fpe-start_date");

        if (!$start_date.val()) return missing_datetime_error($start_date);
        var start = _parse_date_input($start_date.val());
        if (!start) return invalid_datetime_error($start_date);

        var start_time_parts = [];
        var end_time_parts = [];

        if ($('#eab-events-fpe-toggle_time__start').not(':checked')) {
            var $start_time = $("#eab-events-fpe-start_time");
            if (!$start_time.val()) return missing_datetime_error($start_time);

            start_time_parts = _time_string_to_array($start_time.val());

            start.setHours(start_time_parts[0]);
            start.setMinutes(start_time_parts[1]);
            has_start = true;
        }

        var $end_date = $("#eab-events-fpe-end_date");
        if (!$end_date.val()) return missing_datetime_error($end_date);
        var end = _parse_date_input($end_date.val());
        if (!end) return invalid_datetime_error($end_date);


        if ($('#eab-events-fpe-toggle_time__end').not(':checked')) {
            var $end_time = $("#eab-events-fpe-end_time");
            if (!$end_time.val()) return missing_datetime_error($end_time);

            end_time_parts = _time_string_to_array($end_time.val());
            end.setHours(end_time_parts[0]);
            end.setMinutes(end_time_parts[1]);
            has_end = true;
        }

        if (((
                (!has_start && !has_end) || (has_start && has_end)) && start >= end) ||
            start > end) {
            return invalid_datetime_error();
        }

        $("#eab-events-fpe-ok").after(
            '<img src="' + _eab_events_fpe_data.root_url + '/waiting.gif" id="eab-events-fpe-waiting_indicator" />'
        );
        var content = $("#eab-events-fpe-content").is(":visible") ? $("#eab-events-fpe-content").val() : tinyMCE.activeEditor.getContent();
        var selected_categories = [];

        var modified_start_time = start_time_parts.join(':');
        var modified_end_time = end_time_parts.join(':');

        modified_start_time = modified_start_time.replace(/ /g, '');
        modified_end_time = modified_end_time.replace(/ /g, '');
        if (!modified_start_time) modified_start_time = '00:00';
        if (!modified_end_time) modified_end_time = '00:00';

        var start_iso = _to_iso_date(start);
        var end_iso = _to_iso_date(end);

        $('input[name="eab-events-fpe-categories[]"]:checked').each(function() {
            selected_categories.push($(this).val());
        });

        var data = {
            "id": $("#eab-events-fpe-event_id").val(),
            "title": $("#eab-events-fpe-event_title").val(),
            "content": content,
            "start": start_iso + ' ' + modified_start_time,
            "end": end_iso + ' ' + modified_end_time,
            "no_start_time": $('#eab-events-fpe-toggle_time__start').is(':checked'),
            "no_end_time": $('#eab-events-fpe-toggle_time__end').is(':checked'),
            "venue": $("#eab-events-fpe-venue").val(),
            "status": $("#eab-events-fpe-status").val(),
            "is_premium": ($("#eab-events-fpe-is_premium").length ? $("#eab-events-fpe-is_premium").val() : 0),
            "category": selected_categories,
            /* Added by Ashok */
            "featured": $('#eab-fpe-attach_id').val(),
            /* End of adding by Ashok */
            /* Added by Lindeni */
            "has_start": has_start,
            "has_end": has_end
                /* End of adding by Lindeni */
        };
        if ($("#eab-events-fpe-event_fee").length) {
            data["fee"] = $("#eab-events-fpe-event_fee").val();
        }
        $(document).trigger('eab-events-fpe-save_request', [data]);

        // Start sending!!
        $.post(_eab_events_fpe_data.ajax_url, {
            "action": "eab_events_fpe-save_event",
            "data": data
        }, function(response) {
            $("#eab-events-fpe-waiting_indicator").remove();
            var status = false;
            var message = false;
            try { status = parseInt(response.status, 10); } catch (e) { status = 0; }
            try { message = response.message; } catch (e) { message = false; }
            if (!status) return show_message((message ? message : l10nFpe.general_error), true);

            var post_id = false;
            try { post_id = parseInt(response.post_id, 10); } catch (e) { post_id = 0; }
            if (!post_id) return show_message((message ? message : l10nFpe.missing_id), true);

            var link = false;
            try { link = response.permalink; } catch (e) { link = false; }
            if (link) {
                $("#eab-events-fpe-back_to_event").attr("href", link).show();
                $("#eab-events-fpe-cancel").off("click").on("click", function() {
                    window.location = link;
                }).show();
            }

            $("#eab-events-fpe-event_id").val(post_id);
            $(".eab-attendance-event_id").val(post_id);
            return show_message((message ? message : l10nFpe.all_good), false);
        });
        return false;
    }


    // Init
    $(function() {

        // Fallback for browsers without native date picker support.
        if (!_supports_native_date_input()) {
            $("#eab-events-fpe-start_date, #eab-events-fpe-end_date").each(function() {
                var $el = $(this);
                $el.attr('type', 'text');
                $el.attr('placeholder', 'TT.MM.JJJJ');
                $el.attr('inputmode', 'numeric');
                $el.attr('autocomplete', 'off');
                $el.attr('pattern', '\\d{2}\\.\\d{2}\\.\\d{4}');
                $el.attr('title', 'Bitte Datum als TT.MM.JJJJ eingeben');

                var current = $.trim($el.val());
                if (current) {
                    $el.val(_iso_to_de(current));
                }
            });
        }

        $("#fpe-editor").append($("#fpe-editor-root"));
        $("#fpe-editor-root").show();

        // Toggle RSVPs
        $("#eab-events-fpe-toggle_rsvps").on("click", toggle_rsvps);

        // Init Fee toggling
        if ($("#eab-events-fpe-is_premium")) {
            $("#eab-events-fpe-is_premium").change(toggle_fee);
            toggle_fee();
        }

        $("body").on("click", ".eab-add_attendance .button", function() {
            var $root = $(".eab-add_attendance"),
                event_id = $root.find(".eab-attendance-event_id").val()
            email = $root.find(".eab-attendance-email").val(),
                status = $root.find(".eab-attendance-status").val();
            if (!event_id || !email || !status) return false;
            $.post(ajaxurl, {
                action: "eab_add_attendance",
                user: email,
                post_id: event_id,
                status: status
            }, function(data) {
                $(".eab-add_attendance-container").html(data);
            });
            return false;
        });

        // Attendance deleting
        $("body").on("click", ".eab-guest-delete_attendance", function() {
            var $me = $(this);
            var user_id = $me.attr("data-eab-user_id");
            var post_id = $me.attr("data-eab-event_id");
            $.post(ajaxurl, {
                "action": "eab_delete_attendance",
                "user_id": user_id,
                "post_id": post_id
            }, function(data) {
                $(".eab-add_attendance-container").html(data);
                window.location.reload();
            });
            return false;
        });

        $("body").on("click", ".eab-guest-cancel_attendance", function() {
            var $me = $(this);
            var user_id = $me.attr("data-eab-user_id");
            var post_id = $me.attr("data-eab-event_id");
            $.post(ajaxurl, {
                "action": "eab_cancel_attendance",
                "user_id": user_id,
                "post_id": post_id
            }, function(data) {
                $(".eab-add_attendance-container").html(data);
                window.location.reload();
            });
            return false;
        });

        // Init save request processing
        $("#eab-events-fpe-ok").on("click", send_save_request);

        var link = $("#eab-events-fpe-back_to_event").is(":visible") && $("#eab-events-fpe-back_to_event").attr("href");
        if (link) {
            $("#eab-events-fpe-cancel").off("click").on("click", function() {
                window.location = link;
            }).show();
        } else $("#eab-events-fpe-cancel").hide();
        /* Added by Ashok - Updated to use WordPress Media Library */
        function initMediaUploader() {
            console.log('Initializing media uploader...');
            
            // Remove any existing event handlers first
            $('.eab-fpe-upload').off('click.media-uploader');
            
            $('.eab-fpe-upload').on("click.media-uploader", function(e) {
                console.log('Media upload button clicked!');
                e.preventDefault();
                e.stopPropagation();
                
                // Check if wp.media is available
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    console.error('WordPress Media Library nicht verfügbar');
                    alert('Media Library nicht verfügbar. Bitte laden Sie die Seite neu.');
                    return false;
                }
                
                console.log('wp.media is available, creating media frame...');
                
                // If the media frame already exists, reopen it.
                if (eab_media_frame) {
                    console.log('Reopening existing media frame');
                    eab_media_frame.open();
                    return false;
                }
                
                // Create the media frame.
                eab_media_frame = wp.media({
                    title: l10nFpe.media_title || 'Veranstaltungsbild auswählen',
                    button: {
                        text: l10nFpe.media_button || 'Bild verwenden',
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                console.log('Media frame created, setting up select handler...');
                
                // When an image is selected, run a callback.
                eab_media_frame.on('select', function() {
                    console.log('Image selected in media frame');
                    var attachment = eab_media_frame.state().get('selection').first().toJSON();
                    console.log('Selected attachment:', attachment);
                    $('#eab-fpe-attach_id').val(attachment.id);
                    $('#eab-fpe-preview-upload')
                        .attr('src', attachment.url)
                        .show();
                });
                
                console.log('Opening media frame...');
                // Finally, open the modal
                eab_media_frame.open();
                
                return false;
            });
            
            // Also add a delegated event handler as backup
            $(document).off('click.media-uploader-delegate', '.eab-fpe-upload').on('click.media-uploader-delegate', '.eab-fpe-upload', function(e) {
                console.log('Delegated media upload button clicked!');
                e.preventDefault();
                e.stopPropagation();
                
                if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                    $('.eab-fpe-upload').first().trigger('click.media-uploader');
                } else {
                    alert('Media Library ist noch nicht bereit. Bitte versuchen Sie es erneut.');
                }
            });
            
            console.log('Media uploader initialized. Found ' + $('.eab-fpe-upload').length + ' upload buttons.');
        }

        // Initialize media uploader when document is ready
        $(document).ready(function() {
            console.log('Document ready, checking for wp.media...');
            console.log('jQuery version:', $.fn.jquery);
            console.log('Found upload buttons:', $('.eab-fpe-upload').length);
            
            // Add a simple test click handler first
            $('.eab-fpe-upload').on('click.test', function() {
                console.log('TEST: Button clicked, jQuery is working!');
            });
            
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                console.log('wp.media available immediately, initializing...');
                initMediaUploader();
            } else {
                console.log('wp.media not available yet, waiting...');
                // Wait for wp.media to be available
                var checkMedia = setInterval(function() {
                    if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                        console.log('wp.media now available, initializing...');
                        initMediaUploader();
                        clearInterval(checkMedia);
                    } else {
                        console.log('Still waiting for wp.media...');
                    }
                }, 100);
                
                // Timeout after 10 seconds
                setTimeout(function() {
                    clearInterval(checkMedia);
                    console.error('WordPress Media Library konnte nicht geladen werden');
                    // Fallback: Try to initialize anyway in case it loads later
                    $(document).on('click', '.eab-fpe-upload', function(e) {
                        e.preventDefault();
                        alert('Media Library ist noch nicht bereit. Bitte versuchen Sie es in einem Moment erneut.');
                    });
                }, 10000);
            }
        });
        /* End of adding by Ashok */

        /* Toggle time options */
        $(document).on('click', '#eab-events-fpe-date_time .eab_time_toggle', function() {

            var affect = $(this).data('time-affect'),
                source = ($(this).attr('type') == 'checkbox') ? 'checkbox' : 'other_trigger',
                target = $('.eab-events-fpe_wrap_time_' + affect),
                checkbox = $('#eab-events-fpe-toggle_time__' + affect);

            if (source == 'other_trigger') {
                checkbox.prop("checked", !checkbox.prop("checked"));
            }

            if (checkbox.is(':checked')) {
                target.fadeOut(300);
            } else {
                target.show(300);
            }

        });
    });



})(jQuery);