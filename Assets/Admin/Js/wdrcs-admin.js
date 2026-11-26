if (typeof (wdrc_jquery) == 'undefined') {
    wdrc_jquery = jQuery.noConflict();
}
wdrc = window.wdrc || {};
(function (wdrc) {

    wdrc.showToast = function (type, message) {
        var $toast = wdrc_jquery(
            '<div class="wdrc-toast ' + type + '">'
            + '<div class="wdrc-toast-content">'
            + '<span class="wdrc-toast-msg"></span>'
            + '</div>'
            + '<button type="button" class="wdrc-toast-close dashicons dashicons-no-alt" aria-label="Close"></button>'
            + '</div>'
        );

        $toast.find('.wdrc-toast-msg').text(message);

        $toast.find('.wdrc-toast-close').on('click', function () {
            $toast.remove();
        });

        wdrc_jquery('#wdrc-notification').append($toast);

        setTimeout(function () {
            $toast.remove();
        }, 2000);
    };

    wdrc.saveCompatibility = function () {
        var $button = wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form #wdrc-save-button');
        var data = wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form').serialize()
            + '&wdrc_nonce=' + encodeURIComponent(wdrc_localized_data.nonce);

        $button.attr('disabled', true);

        wdrc_jquery.ajax({
            data: data,
            type: 'post',
            url: wdrc_localized_data.ajax_url,

            error: function () {
                $button.attr('disabled', false);
                wdrc.showToast(
                    'error',
                    wdrc_localized_data.i18n.saved_error
                );
            },

            success: function (json) {
                $button.attr('disabled', false);

                if (!json.success) {
                    wdrc.showToast(
                        'error',
                        json.data && json.data.message
                            ? json.data.message
                            : wdrc_localized_data.i18n.saved_error
                    );

                    return;
                }

                wdrc.showToast(
                    'success',
                    json.data.message
                );
            }
        });
    };

    wdrc_jquery(document).on('click', '#wdrc-save-button', function () {
        wdrc.saveCompatibility();
    });

}(wdrc));
