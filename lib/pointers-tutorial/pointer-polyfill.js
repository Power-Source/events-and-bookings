(function ($) {
    if (!$ || typeof $.fn === 'undefined') {
        return;
    }

    function setState($el, state) {
        $el.data('psPointerState', state);
    }

    function getState($el) {
        return $el.data('psPointerState') || {};
    }

    function positionPopup($el, $popup, opts) {
        var off = $el.offset();
        if (!off) {
            return;
        }

        var edge = ((opts.position && opts.position.edge) || 'left').toLowerCase();
        var align = ((opts.position && opts.position.align) || 'center').toLowerCase();
        var top = off.top;
        var left = off.left + $el.outerWidth() + 14;

        $popup.removeClass('is-left is-right is-top is-bottom');

        if (edge === 'right') {
            left = off.left - $popup.outerWidth() - 14;
            $popup.addClass('is-right');
        } else if (edge === 'top') {
            top = off.top + $el.outerHeight() + 14;
            left = off.left;
            $popup.addClass('is-top');
        } else if (edge === 'bottom') {
            top = off.top - $popup.outerHeight() - 14;
            left = off.left;
            $popup.addClass('is-bottom');
        } else {
            $popup.addClass('is-left');
        }

        if (edge === 'left' || edge === 'right') {
            if (align === 'top') {
                top = off.top;
            } else if (align === 'bottom') {
                top = off.top + $el.outerHeight() - $popup.outerHeight();
            } else {
                top = off.top + ($el.outerHeight() / 2) - ($popup.outerHeight() / 2);
            }
        } else {
            if (align === 'right') {
                left = off.left + $el.outerWidth() - $popup.outerWidth();
            } else if (align === 'center') {
                left = off.left + ($el.outerWidth() / 2) - ($popup.outerWidth() / 2);
            }
        }

        $popup.css({
            position: 'absolute',
            top: Math.max(8, top),
            left: Math.max(8, left),
            zIndex: 100100
        });
    }

    function removePopup($el, invokeClose) {
        var state = getState($el);

        if (state.popup && state.popup.length) {
            state.popup.remove();
        }

        if (state.bound) {
            $(window).off('.psPointer-' + state.bound);
        }

        state.popup = null;

        if (invokeClose && state.options && typeof state.options.close === 'function') {
            state.options.close.call($el[0]);
        }

        setState($el, state);
    }

    function createPopup($el) {
        var state = getState($el);
        var opts = state.options || {};

        removePopup($el, false);

        var $popup = $('<div class="wp-pointer ps-pointer"><div class="wp-pointer-content"></div><div class="wp-pointer-buttons"></div><div class="wp-pointer-arrow"><span class="wp-pointer-arrow-inner"></span></div></div>');

        if (opts.pointerClass) {
            $popup.addClass(opts.pointerClass);
        }

        $popup.find('.wp-pointer-content').append(opts.content || '');

        if (typeof opts.buttons === 'function') {
            var $buttons = opts.buttons.call($el[0], null, {
                element: $el,
                pointer: $popup,
                options: opts
            });
            if ($buttons) {
                $popup.find('.wp-pointer-buttons').append($buttons);
            }
        } else {
            var $close = $('<a class="button button-primary close" href="#">Dismiss</a>');
            $close.on('click.psPointer', function (ev) {
                ev.preventDefault();
                $el.pointer('close');
            });
            $popup.find('.wp-pointer-buttons').append($close);
        }

        $('body').append($popup);

        state.popup = $popup;
        state.bound = Math.random().toString(36).slice(2);

        $(window).on('resize.psPointer-' + state.bound + ' scroll.psPointer-' + state.bound, function () {
            positionPopup($el, $popup, opts);
        });

        positionPopup($el, $popup, opts);
        setState($el, state);
    }

    $.fn.pointer = function (arg) {
        if (typeof arg === 'string') {
            if (arg === 'open') {
                return this.each(function () {
                    createPopup($(this));
                });
            }

            if (arg === 'close') {
                return this.each(function () {
                    removePopup($(this), true);
                });
            }

            if (arg === 'destroy') {
                return this.each(function () {
                    removePopup($(this), false);
                });
            }

            return this;
        }

        return this.each(function () {
            var $el = $(this);
            var state = getState($el);
            state.options = $.extend(true, {}, arg || {});
            setState($el, state);
        });
    };
})(window.jQuery);
