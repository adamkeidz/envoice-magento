define(['jquery', 'Magento_Ui/js/modal/confirm'], function ($, confirm) {
    'use strict';

    var POLL_MS = 2500,
        MAX_POLLS = 240;

    return function (config, element) {
        var $root = $(element);

        function post(url, data) {
            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $.extend({form_key: window.FORM_KEY}, data)
            });
        }

        function setStatus($row, text) {
            $row.find('[data-role="envoice-status"]').text(text);
        }

        $root.on('click', '[data-envoice-connect]', function () {
            var $button = $(this),
                site = $button.data('site'),
                $row = $button.closest('tr'),
                popup = window.open('', 'envoice-connect', 'width=520,height=760');

            $button.prop('disabled', true);
            post(config.startUrl, {site: site}).done(function (res) {
                var polls = 0,
                    timer;

                if (!res.ok) {
                    if (popup) {
                        popup.close();
                    }
                    setStatus($row, res.message);
                    $button.prop('disabled', false);
                    return;
                }
                if (popup) {
                    popup.location.href = res.popupUrl;
                } else {
                    window.open(res.popupUrl, 'envoice-connect');
                }
                setStatus($row, config.text.waiting);
                timer = setInterval(function () {
                    polls++;
                    if (polls > MAX_POLLS) {
                        clearInterval(timer);
                        setStatus($row, config.text.expired);
                        $button.prop('disabled', false);
                        return;
                    }
                    post(config.pollUrl, {site: site}).done(function (poll) {
                        if (poll.paired) {
                            clearInterval(timer);
                            window.location.reload();
                        } else if (!poll.ok) {
                            clearInterval(timer);
                            setStatus($row, poll.message);
                            $button.prop('disabled', false);
                        }
                    });
                }, POLL_MS);
            }).fail(function () {
                if (popup) {
                    popup.close();
                }
                setStatus($row, config.text.failed);
                $button.prop('disabled', false);
            });
        });

        $root.on('click', '[data-envoice-disconnect]', function () {
            var site = $(this).data('site');

            confirm({
                content: config.text.confirmDisconnect,
                actions: {
                    confirm: function () {
                        post(config.disconnectUrl, {site: site}).always(function () {
                            window.location.reload();
                        });
                    }
                }
            });
        });
    };
});
