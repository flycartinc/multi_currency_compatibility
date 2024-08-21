if (typeof (wdrc_jquery) == 'undefined') {
    wdrc_jquery = jQuery.noConflict();
}
wdrc = window.wdrc || {};
(function (wdrc) {
    wdrc.saveCompatibility = function () {
        let data = wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form').serialize();
        wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form #wdrc-save-button').attr('disabled', true);
        wdrc_jquery.ajax({
            data: data,
            type: 'post',
            url: wdrc_localized_data.ajax_url,
            error: function (request, error) {
                wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form #wdrc-save-button').attr('disabled', false);
            },
            success: function (json) {
                wdrc_jquery('#wdr-compatibility-main #wdrc-fields-form #wdrc-save-button').attr('disabled', false);
                alertify.set('notifier', 'position', 'top-right');
                if (!json.success) {
                    if (json.data.message) {
                        alertify.error(json.data.message);
                    }
                } else {
                    alertify.success(json.data.message);
                    setTimeout(function () {
                        location.reload();
                    }, 800);
                }
            }
        });
    };
}(wdrc));