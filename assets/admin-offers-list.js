(function ($) {
    'use strict';

    $(document).on('click', '.flexmile-homepage-toggle', function (e) {
        e.preventDefault();
        var $label = $(this);
        if ($label.hasClass('flexmile-loading')) return;

        var postId = $label.data('post-id');
        var nonce = $label.data('nonce');
        var $checkbox = $label.find('input[type="checkbox"]');

        $label.addClass('flexmile-loading').css('opacity', '0.5');

        $.post(flexmileOffersList.ajaxUrl, {
            action: 'flexmile_toggle_homepage_visible',
            post_id: postId,
            nonce: nonce
        })
            .done(function (res) {
                if (res.success && res.data) {
                    $checkbox.prop('checked', res.data.enabled);
                    $label.data('enabled', res.data.enabled ? 1 : 0);
                }
                $label.removeClass('flexmile-loading').css('opacity', '1');
            })
            .fail(function () {
                $label.removeClass('flexmile-loading').css('opacity', '1');
                alert('Nie udało się zaktualizować. Odśwież stronę.');
            });
    });
})(jQuery);
