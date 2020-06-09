(function(window, $, ajaxurl) {
    function getCredentials() {
        return {
            'host': $('#akamai-credentials-host').val(),
            'access-token': $('#akamai-credentials-access-token').val(),
            'client-token': $('#akamai-credentials-client-token').val(),
            'client-secret': $('#akamai-credentials-client-secret').val(),
        };
    }

    function setVerifyButtonDisabled(setting) {
        if (setting !== undefined) {
            $('#verify-creds').prop('disabled', !!setting);
            return;
        }
        const creds = getCredentials();
        const vals = Object.keys(creds).map(function(key) {
            return creds[key];
        });
        $('#verify-creds').prop('disabled', vals.includes(''));
    }

    function getRandomNumbers() {
        const c = window.crypto || window.msCrypto;
        const a = new Uint32Array(1);
        c.getRandomValues(a);
        return a[0].toString();
    }

    function NoticeDrawer({ onPush }) {
        this.drawer    = {};
        this.successes = [];
        this.onPush    = onPush.bind(this);
    }

    jQuery.fn.extend({
        noticeShow: function() {
            $(this).css('opacity', 0)
                .slideDown('normal')
                .animate(
                    { opacity: 1 },
                    { queue: false, duration: 'normal' }
                );
        },
        noticeSlideOut: function() {
            $(this).slideUp('normal', function () {
                $(this).remove();
            });
        },
        noticeFadeOut: function() {
            $(this).fadeOut('fast', function () {
                $(this).remove();
            });
        },
    });

    NoticeDrawer.prototype.add = function ({ id, message, type }) {
        this.drawer[id] = this.createNotification({ id, message, type });
        if ('success' === type) {
            this.successes.push(this.drawer[id]);
        };
        this.onPush({ id, message, type });
    };

    NoticeDrawer.prototype.createNotification = function ({ id, message, type }) {
        const $div = $(`<div id="${id}"
                            class="notice notice-${type} is-dismissable"
                            style="position: relative; display: none"></div>`);
        const $btn = $(`<button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>`);
        const $msg = $('<p>').text(message);

        $btn.click(function() {
            $div.noticeFadeOut();
        });

        return $div.prepend($msg).append($btn);
    }

    const verificationNotices = new NoticeDrawer({
        onPush: function ({ id, type }) {
            if ('success' === type && this.successes.length > 1) {
                this.successes.shift().noticeSlideOut();
            }
            $('#verification-notices-drawer').append(this.drawer[id]);
            this.drawer[id].noticeShow();
        }
    });

    // Hook up cred verification.
    $(function() {
        setVerifyButtonDisabled();
        $("form :input").keyup(function() { setVerifyButtonDisabled(); });
        $("form :input").change(function() { setVerifyButtonDisabled(); });

        $('#verify-creds').click(function(e) {
            e.stopPropagation();

            setVerifyButtonDisabled(true);
            $('#verify-creds-spinner').css({ visibility: 'visible' });

            $.ajax({
                method: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    'action': 'akamai_verify_credentials',
                    'credentials': getCredentials(),
                },
            })
            .done(function(response) {
                console.log({verification: { response }});

                setVerifyButtonDisabled(false);
                $('#verify-creds-spinner').css({ visibility: 'hidden' });

                if (response.success) {
                    verificationNotices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'success',
                        message: 'Credentials verified successfully.',
                    });
                } else if (response.error) {
                    verificationNotices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'error',
                        message: response.error,
                    });
                } else {
                    verificationNotices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'error',
                        message: 'An unexpected error occurred.',
                    });
                }
            })
            .fail(function(error) {
                console.log({verification: { error }});

                setVerifyButtonDisabled(false);
                $('#verify-creds-spinner').hide();

                verificationNotices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'An unexpected error occurred: ' + error,
                });
            });
        });
    });

    const send = {
        all: { active: true, $spinner: null, $button: null },
        url: { active: true, $spinner: null, $button: null, $input: null },
    };
    function sendPurgeAll(event) {
        event.preventDefault();

        if (!window.confirm('Are you sure you want to purge all?')) {
            return;
        }
        toggleIsActive(send.all);

        $.ajax({
            method: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'akamai_purge_all',
            },
        })
        .done(response => {
            console.log({ purge: { response } });
        })
        .fail(error => {
            console.log({ purge: { error } });
        })
        .always(() => toggleIsActive(send.all));
    }
    function sendPurgeURL(event) {
        event.preventDefault();

        if ('' === send.url.$input.val()) {
            // TODO: add error notification.
            return;
        }
        toggleIsActive(send.url);

        $.ajax({
            method: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'akamai_purge_url',
            },
        })
        .done(response => {
            console.log({ purge: { response } });
        })
        .fail(error => {
            console.log({ purge: { error } });
        })
        .always(() => toggleIsActive(send.url));
    }

    function toggleIsActive(action) {
        if (action.active) {
            action.active = false;
            action.$button.attr('disabled', true);
            if (action.$input) action.$input.attr('disabled', true);
            action.$spinner.addClass('is-active');
        } else {
            action.active = true;
            action.$button.attr('disabled', false);
            if (action.$input) action.$input.attr('disabled', false);
            action.$spinner.removeClass('is-active');
        }
    }

    // Hook up purge actions.
    $(function() {
        send.all.$button  = $('#akamai-purge-all-btn');
        send.all.$spinner = $('#akamai-purge-all-spinner');
        send.url.$input   = $('#akamai-purge-url');
        send.url.$button  = $('#akamai-purge-url-btn');
        send.url.$spinner = $('#akamai-purge-url-spinner');

        send.all.$button.click(sendPurgeAll);
        send.url.$button.click(sendPurgeURL);
    });
})(window, window.jQuery, window.ajaxurl);
