(function(window, $, ajaxurl) {
    // Notification UI helper.
    function NoticeDrawer({ onPush }) {
        this.drawer = {};
        this.successes = [];
        this.onPush = onPush.bind(this);
    }

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
        const $msg = $('<p>').html(message);

        $btn.click(function () {
            $div.noticeFadeOut();
        });

        return $div.prepend($msg).append($btn);
    }

    // Notification UI shorthands expressed as jQuery extensions.
    jQuery.fn.extend({
        noticeShow: function () {
            $(this).css('opacity', 0)
                .slideDown('normal')
                .animate(
                    { opacity: 1 },
                    { queue: false, duration: 'normal' }
                );
        },
        noticeSlideOut: function () {
            $(this).slideUp('normal', function () {
                $(this).remove();
            });
        },
        noticeFadeOut: function () {
            $(this).fadeOut('fast', function () {
                $(this).remove();
            });
        },
    });

    // Random-string helper.
    function getRandomNumbers() {
        const c = window.crypto || window.msCrypto;
        const a = new Uint32Array(1);
        c.getRandomValues(a);
        return a[0].toString();
    }

    // Verification interactions.
    const verify = {
        $notices: null,
        notices: null,
        $button: null,
        $spinner: null,
        inputs: {
            $host: null,
            $accessToken: null,
            $clientToken: null,
            $clientSecret: null
        },
        creds: null,
        setButtonDisabled: null,
        sendVerification: null,
    };
    verify.creds = function () {
        return {
            'host':          verify.inputs.$host.val(),
            'access-token':  verify.inputs.$accessToken.val(),
            'client-token':  verify.inputs.$clientToken.val(),
            'client-secret': verify.inputs.$clientSecret.val(),
        };
    }
    verify.setButtonDisabled = function (setting) {
        if (setting !== undefined) {
            verify.$button.prop('disabled', !!setting);
            return;
        }
        const creds = verify.creds();
        const vals = Object.keys(creds).map(function(key) {
            return creds[key];
        });
        verify.$button.prop('disabled', vals.includes(''));
    }
    verify.notices = new NoticeDrawer({
        onPush: function ({ id, type }) {
            if ('success' === type && this.successes.length > 1) {
                this.successes.shift().noticeSlideOut();
            }
            verify.$notices.append(this.drawer[id]);
            this.drawer[id].noticeShow();
        }
    });
    verify.sendVerification = function (event) {
        event.stopPropagation();
        event.preventDefault();

        verify.setButtonDisabled(true);
        verify.$spinner.css({ visibility: 'visible' }); // FIXME: show/hide

        $.ajax({
            method: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'akamai_verify_credentials',
                'credentials': verify.creds(),
            },
        })
            .done(function (response) {
                console.log({ verification: { response } });
                verify.setButtonDisabled(false);
                verify.$spinner.css({ visibility: 'hidden' }); // FIXME: show/hide

                if (response.success) {
                    verify.notices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'success',
                        message: 'Credentials verified successfully.',
                    });
                } else if (response.error) {
                    verify.notices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'error',
                        message: response.error,
                    });
                } else {
                    verify.notices.add({
                        id: `akamai-notice-${getRandomNumbers()}`,
                        type: 'error',
                        message: 'An unexpected error occurred.',
                    });
                }
            })
            .fail(function (error) {
                console.log({ verification: { error } });
                verify.setButtonDisabled(false);
                verify.$spinner.hide();

                verify.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'An unexpected error occurred: ' + error,
                });
            });
    }

    // Hook up cred verification.
    $(function() {
        verify.$notices             = $('#verification-notices-drawer');
        verify.$button              = $('#verify-creds');
        verify.$spinner             = $('#verify-creds-spinner');
        verify.inputs.$host         = $('#akamai-credentials-host');
        verify.inputs.$accessToken  = $('#akamai-credentials-access-token');
        verify.inputs.$clientToken  = $('#akamai-credentials-client-token');
        verify.inputs.$clientSecret = $('#akamai-credentials-client-secret');

        verify.setButtonDisabled();
        $("form :input").keyup(() => verify.setButtonDisabled());
        $("form :input").change(() => verify.setButtonDisabled());

        verify.$button.click(verify.sendVerification);
    });

    // Purge interactions.
    const purge = {
        $notices: null,
        notices: null,
        all: { active: true, $spinner: null, $button: null },
        url: { active: true, $spinner: null, $button: null, $input: null },
        sendAll: null,
        sendURL: null,
        toggleIsActive: null,
    };
    purge.notices = new NoticeDrawer({
        onPush: function ({ id, type }) {
            if ('success' === type && this.successes.length > 1) {
                this.successes.shift().noticeSlideOut();
            }
            purge.$notices.append(this.drawer[id]);
            this.drawer[id].noticeShow();
        }
    });
    purge.sendAll = function (event) {
        event.stopPropagation();
        event.preventDefault();

        if (!window.confirm('Are you sure you want to purge all?')) {
            return;
        }
        purge.toggleIsActive(purge.all);

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
            if (response.success) {
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'success',
                    message: response.message,
                });
            } else if (response.error) {
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'Could not purge all: ' + response.error,
                });
            } else {
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'An unexpected error occurred.',
                });
            }
        })
        .fail(error => {
            console.log({ purge: { error } });
            purge.notices.add({
                id: `akamai-notice-${getRandomNumbers()}`,
                type: 'error',
                message: 'Could not purge all: ' + error,
            });
        })
        .always(() => purge.toggleIsActive(purge.all));
    }
    purge.sendURL = function (event) {
        event.stopPropagation();
        event.preventDefault();

        const url = purge.url.$input.val();
        if ('' === url) {
            purge.notices.add({
                id: `akamai-notice-${getRandomNumbers()}`,
                type: 'warning',
                message: 'Must add URL to purge.',
            });
            return;
        }
        purge.toggleIsActive(purge.url);

        $.ajax({
            method: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'akamai_purge_url',
                'url': url,
            },
        })
        .done(response => {
            console.log({ purge: { response } });
            if (response.success) {
                purge.url.$input.val('');
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'success',
                    message: 'Purge URL ' + url + ' successful!',
                });
            } else if (response.error) {
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'Could not purge URL <code>' + url + '</code>: ' + response.error,
                });
            } else {
                purge.notices.add({
                    id: `akamai-notice-${getRandomNumbers()}`,
                    type: 'error',
                    message: 'An unexpected error occurred.',
                });
            }
        })
        .fail(error => {
            console.log({ purge: { error } });
            purge.notices.add({
                id: `akamai-notice-${getRandomNumbers()}`,
                type: 'error',
                message: 'Could not purge URL ' + url + ': ' + error,
            });
        })
        .always(() => {
            purge.toggleIsActive(purge.url);
        });
    }

    purge.toggleIsActive = function (action) {
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
        purge.all.$button  = $('#akamai-purge-all-btn');
        purge.all.$spinner = $('#akamai-purge-all-spinner');
        purge.url.$input   = $('#akamai-purge-url');
        purge.url.$button  = $('#akamai-purge-url-btn');
        purge.url.$spinner = $('#akamai-purge-url-spinner');
        purge.$notices     = $('#akamai-purge-notices-drawer');

        purge.all.$button.click(purge.sendAll);
        purge.url.$button.click(purge.sendURL);
    });

    // Attach all actions to global namespace.
    window.wpAkamai = {
        NoticeDrawer,
        getRandomNumbers,
        verify,
        purge,
    };
})(window, window.jQuery, window.ajaxurl);
