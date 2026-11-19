jQuery(function($) {
    jQuery('[href*="preview=true"]').hide(); // Preview won't work

    if (!jQuery("#psource_event-accept_payments").is(":checked")) {
        jQuery("#eab-settings-paypal").hide();
    }

    jQuery("#psource_event-accept_payments").on("change", function() {
        if (!jQuery("#psource_event-accept_payments").is(":checked")) {
            jQuery("#eab-settings-paypal").hide();
        } else {
            jQuery("#eab-settings-paypal").show();
        }
    });

    if (jQuery("#psource_event_paid").val() == 0) {
        jQuery(".psource_event-fee_row").hide();
    }
    jQuery("#psource-event").on("change", "#psource_event_paid", function() {
        if (jQuery("#psource_event_paid").val() == 0) {
            jQuery(".psource_event-fee_row").hide();
        } else {
            jQuery(".psource_event-fee_row").show();
        }
    });

    jQuery('a.eab-info').on("click",function() {
        jQuery(jQuery(this).attr('href')).toggle();
        return false;
    });

    if (!jQuery('#psource-event-bookings').hasClass('closed')) {
        jQuery('#psource-event-bookings').addClass('closed');
    }
    jQuery('#psource-event-bookings .hndle').append('<span class="eab-expand-metabox">' + eab_event_localized["view_all_bookings"] + '</span>');
    if (!jQuery('#psource-event-wizard').hasClass('closed')) {
        jQuery('#psource-event-wizard').addClass('closed');
    }
    jQuery('#psource-event-wizard .hndle').append('<a href="edit.php?post_type=psource_event&page=eab_welcome" class="eab-expand-metabox">' + eab_event_localized["back_to_gettting_started"] + '</a>');


    _eab_location = window.location;

    (function($) {
        // API toggling
        function toggle_api_settings() {
            if ($("#psource_event-accept_api_logins").is(":checked")) $("#eab-settings-apis .eab-inside").show();
            else $("#eab-settings-apis .eab-inside").hide();
        }

        // Appearance toggling
        function toggle_appearance_settings() {
            if ($("#psource_event-override_appearance_defaults").is(":checked")) {
                $("#psource_event-archive_template").attr("disabled", false);
                $("#psource_event-single_template").attr("disabled", false);
            } else {
                $("#psource_event-archive_template").attr("disabled", true);
                $("#psource_event-single_template").attr("disabled", true);
            }
        }

         // Recurrence instance editing toggling
        function toggle_recurrence_instances() {
            if ($("#eab_event-recurring_instances").is(":visible")) $("#eab_event-recurring_instances").hide();
            else $("#eab_event-recurring_instances").show();
            return false;
        }

        $(function() {
            // Init API toggle
            $("#psource_event-accept_api_logins").on("change", toggle_api_settings);
            toggle_api_settings();

            // Init Appearance toggle
            $("#psource_event-override_appearance_defaults").on("change", toggle_appearance_settings);
            toggle_appearance_settings();

            // Tutorial restart
            $(".eab-restart_tutorial").on("click",function() {
                var $me = $(this);
                $.post(ajaxurl, {
                    "action": "eab_restart_tutorial",
                    "step": $me.attr("data-eab_tutorial")
                }, function() {
                    window.location.reload();
                });
                return false;
            });

            // Init recurrence instances toggle
            $("#eab_event-edit_recurring_instances").on("click", toggle_recurrence_instances);

            // Attendance canceling
            $("body").on("click", ".eab-guest-cancel_attendance", function() {
                var $me = $(this);
                var user_id = $me.attr("data-eab-user_id");
                var post_id = $me.attr("data-eab-event_id");
                $.post(ajaxurl, {
                    "action": "eab_cancel_attendance",
                    "user_id": user_id,
                    "post_id": post_id
                }, function(data) {
                    $("#eab-bookings-response").html(data);
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
                    $("#eab-bookings-response").html(data);
                });
                return false;
            });

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
                    $("#eab-bookings-response").html(data);
                });
                return false;
            });


        });
    })(jQuery);

    // Main settings page tabbing
    (function($) {

        var MIN_SIZE = 768,
            $win = $(window),
            $submit = false;

        if (!window.location.search.match('page=eab_settings')) return false; // Only on settings page
        if ($win.width() < MIN_SIZE) return false; // Only if we have enough space
        if (!$(".wrap").is(".tabbable")) return false; // Allow override

        function reveal_page(e) {
            e.preventDefault();
            e.stopPropagation();
            var me = $(this),
                box_id = me.attr('data-box_id'),
                box = false;
            if (box_id) box = $("#" + box_id);
            if (box_id && box_id.length) {
                $("#eab-root-settings_nav h3").removeClass("active");
                me.addClass("active");
                $('.eab-metabox.postbox').hide();
                box.show();
                /**
                 * Added by Ashok
                 *
                 * Updating URl with correct hash tag
                 * based on selected tab in event settings
                 */
                window.location.hash = box_id;
                set_event_url();
            }
        }

        function boot() {
            var boxes = $('.eab-metabox.postbox'),
                box_root = $(".eab-metaboxcol.metabox-holder");
            $submit = $submit.length ? $submit : box_root.siblings(".submit")
            if (!boxes.length) return;

            var root = $("#eab-root-settings_nav");
            if (root.length) {
                if ($win.width() < MIN_SIZE) {
                    box_root.removeClass("tabbed");
                    root.remove();
                    boxes.show();
                }
                box_root.append($submit);
                return;
            }

            box_root.prepend('<div id="eab-root-settings_nav"></div>');
            root = $("#eab-root-settings_nav");

            root.empty();

            boxes.each(function() {
                var me = $(this),
                    box_id = me.attr("id"),
                    title = me.find("h3.eab-hndle"),
                    new_title = title.clone();
                if (box_id) {
                    new_title
                        .attr("data-box_id", box_id)
                        .on('click', reveal_page);
                    root.append(new_title);
                    me.hide();
                }
            });
            box_root.append($submit);

            /**
             * Added by Ashok
             *
             * Show the correct page based on
             * appropriate hash tag in URL
             */
            var hash = window.location.hash;
            hash = hash.split('#')[1];
            if (hash) {
                var obj = root.find('h3[data-box_id="' + hash + '"]');
                if (obj.length) {
                    obj.on("click");
                } else {
                    root.find("h3:first").on("click");
                }
            } else {
                root.find("h3:first").on("click");
            }
            set_event_url();

            box_root.addClass("tabbed");

            $(".eab-loading-cover.tabbable").remove();
            $(".wrap.tabbable.hide").removeClass("hide");
        }

        /**
         * Added by Ashok
         *
         * Updating hidden field with current page url
         */
        function set_event_url() {
            var url = window.location;
            $('.event_settings_url').val(url);
        }

        $(boot);
        $win.on("resize", boot);

    })(jQuery);

});