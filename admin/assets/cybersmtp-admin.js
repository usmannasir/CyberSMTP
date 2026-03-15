(function($) {
    'use strict';

    // ─── Provider Selection ─────────────────────────────────
    $(document).on('click', '.cybersmtp-provider-card', function() {
        $('.cybersmtp-provider-card').removeClass('selected');
        $(this).addClass('selected');

        var provider = $(this).data('provider');
        $('#cybersmtp-provider-input').val(provider);

        // Show/hide provider settings panels
        $('.cybersmtp-provider-settings').hide();
        $('.cybersmtp-provider-settings[data-for="' + provider + '"]').fadeIn(200);
    });

    // ─── Mode Toggle (API vs SMTP) ─────────────────────────
    $(document).on('change', '.cybersmtp-mode-select', function() {
        var mode = $(this).val();
        var card = $(this).closest('.cybersmtp-card');

        if (mode === 'api') {
            card.find('.cybersmtp-mode-api').show();
            card.find('.cybersmtp-mode-smtp').hide();
        } else {
            card.find('.cybersmtp-mode-api').hide();
            card.find('.cybersmtp-mode-smtp').show();
        }
    });

    // Initialize mode toggles on page load
    $(document).ready(function() {
        $('.cybersmtp-mode-select').each(function() {
            $(this).trigger('change');
        });
    });

    // ─── Test Email ─────────────────────────────────────────
    $(document).on('click', '#cybersmtp-send-test', function() {
        var $btn = $(this);
        var email = $('#cybersmtp-test-email').val();
        var $result = $('#cybersmtp-test-result');

        if (!email) {
            $result.html('<div class="cybersmtp-alert cybersmtp-alert-error">Please enter an email address.</div>');
            return;
        }

        $btn.prop('disabled', true).text('Sending...');
        $result.html('');

        $.post(cybersmtp.ajax_url, {
            action: 'cybersmtp_test_email',
            nonce: cybersmtp.nonce,
            email: email
        }, function(response) {
            $btn.prop('disabled', false).text('Send Test Email');
            if (response.success) {
                $result.html('<div class="cybersmtp-alert cybersmtp-alert-success">' + response.data + '</div>');
            } else {
                $result.html('<div class="cybersmtp-alert cybersmtp-alert-error">' + response.data + '</div>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Send Test Email');
            $result.html('<div class="cybersmtp-alert cybersmtp-alert-error">Request failed. Please try again.</div>');
        });
    });

    // ─── Test Connection ────────────────────────────────────
    $(document).on('click', '#cybersmtp-test-connection', function() {
        var $btn = $(this);
        var $result = $('#cybersmtp-connection-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.html('');

        $.post(cybersmtp.ajax_url, {
            action: 'cybersmtp_test_connection',
            nonce: cybersmtp.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Test Connection');
            if (response.success) {
                $result.html('<span class="cybersmtp-text-success">' + response.data.message + '</span>');
            } else {
                $result.html('<span class="cybersmtp-text-error">' + response.data + '</span>');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Test Connection');
            $result.html('<span class="cybersmtp-text-error">Request failed.</span>');
        });
    });

    // ─── Resend Email ───────────────────────────────────────
    $(document).on('click', '.cybersmtp-resend-btn', function(e) {
        e.stopPropagation();
        var $btn = $(this);
        var logId = $btn.data('id');

        if (!confirm('Resend this email?')) return;

        $btn.prop('disabled', true);

        $.post(cybersmtp.ajax_url, {
            action: 'cybersmtp_resend_email',
            nonce: cybersmtp.nonce,
            log_id: logId
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $btn.closest('tr').find('.cybersmtp-badge').removeClass('cybersmtp-badge-error')
                    .addClass('cybersmtp-badge-success').text('Resent');
            } else {
                alert(response.data || 'Failed to resend.');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            alert('Request failed.');
        });
    });

    // ─── Log Detail Toggle ──────────────────────────────────
    $(document).on('click', '.cybersmtp-toggle-detail', function(e) {
        e.stopPropagation();
        var $row = $(this).closest('tr');
        var $detail = $row.next('.cybersmtp-log-detail');
        $detail.toggle();
        $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // ─── DNS Check ──────────────────────────────────────────
    $(document).on('click', '#cybersmtp-check-dns', function() {
        var $btn = $(this);
        var domain = $('#cybersmtp-dns-domain').val();

        if (!domain) return;

        $btn.prop('disabled', true).text('Checking...');

        $.post(cybersmtp.ajax_url, {
            action: 'cybersmtp_check_dns',
            nonce: cybersmtp.nonce,
            domain: domain
        }, function(response) {
            $btn.prop('disabled', false).text('Check Domain');

            if (!response.success) {
                alert(response.data || 'Check failed.');
                return;
            }

            var data = response.data;
            $('#cybersmtp-dns-results').show();

            // SPF
            updateDnsCard('#cybersmtp-dns-spf', data.spf, 'SPF',
                'Add a TXT record to your DNS: v=spf1 include:_spf.cyberpersons.com ~all');

            // DKIM
            var dkimHelp = 'Add a DKIM TXT record. If using CyberMail, this is configured automatically.';
            if (data.dkim.selector) {
                dkimHelp = 'Selector: ' + data.dkim.selector;
            }
            updateDnsCard('#cybersmtp-dns-dkim', data.dkim, 'DKIM', dkimHelp);

            // DMARC
            updateDnsCard('#cybersmtp-dns-dmarc', data.dmarc, 'DMARC',
                'Add a TXT record at _dmarc.' + domain + ': v=DMARC1; p=quarantine; rua=mailto:dmarc@' + domain);

            // MX
            var mxHtml = '';
            if (data.mx.status === 'found' && data.mx.records) {
                data.mx.records.forEach(function(r) {
                    mxHtml += '<div>' + r.priority + ' ' + r.host + '</div>';
                });
            }
            var $mx = $('#cybersmtp-dns-mx');
            $mx.removeClass('cybersmtp-dns-found cybersmtp-dns-missing')
               .addClass(data.mx.status === 'found' ? 'cybersmtp-dns-found' : 'cybersmtp-dns-missing');
            $mx.find('.cybersmtp-dns-icon').html(data.mx.status === 'found' ? '&#10003;' : '&#10007;');
            $mx.find('.cybersmtp-dns-status').text(data.mx.status === 'found' ? 'Configured' : 'Not Found');
            $mx.find('.cybersmtp-dns-record').html(mxHtml || '—');

        }).fail(function() {
            $btn.prop('disabled', false).text('Check Domain');
            alert('DNS check failed.');
        });
    });

    function updateDnsCard(selector, data, label, helpText) {
        var $card = $(selector);
        var found = data.status === 'found';

        $card.removeClass('cybersmtp-dns-found cybersmtp-dns-missing')
             .addClass(found ? 'cybersmtp-dns-found' : 'cybersmtp-dns-missing');
        $card.find('.cybersmtp-dns-icon').html(found ? '&#10003;' : '&#10007;');
        $card.find('.cybersmtp-dns-status').text(found ? 'Configured' : 'Not Found');
        $card.find('.cybersmtp-dns-record').text(data.record || '—');
        $card.find('.cybersmtp-dns-help').html(found ? '' : '<p class="cybersmtp-dns-suggestion">' + helpText + '</p>');
    }

    // ─── Dashboard Chart ────────────────────────────────────
    if ($('#cybersmtp-chart').length && typeof Chart !== 'undefined') {
        loadChart(7);
    }

    $(document).on('change', '#cybersmtp-chart-range', function() {
        loadChart($(this).val());
    });

    function loadChart(days) {
        $.post(cybersmtp.ajax_url, {
            action: 'cybersmtp_get_stats',
            nonce: cybersmtp.nonce,
            days: days
        }, function(response) {
            if (!response.success) return;

            var ctx = document.getElementById('cybersmtp-chart');
            if (!ctx) return;

            // Destroy existing chart
            if (window.cybersmtpChart) {
                window.cybersmtpChart.destroy();
            }

            var data = response.data;
            var labels = data.labels.map(function(d) {
                return new Date(d).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });

            window.cybersmtpChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Sent',
                            data: data.sent,
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Failed',
                            data: data.failed,
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });

            // CyberMail stats
            if (data.cybermail) {
                var cm = data.cybermail;
                var statsHtml = '<div class="cybersmtp-cm-stats-grid">';
                if (cm.sent_today !== undefined) {
                    statsHtml += '<div><strong>' + cm.sent_today + '</strong><span>Sent Today</span></div>';
                }
                if (cm.quota !== undefined) {
                    statsHtml += '<div><strong>' + cm.quota + '</strong><span>Daily Quota</span></div>';
                }
                if (cm.bounce_rate !== undefined) {
                    statsHtml += '<div><strong>' + cm.bounce_rate + '%</strong><span>Bounce Rate</span></div>';
                }
                statsHtml += '</div>';
                $('#cybersmtp-cybermail-stats').html(statsHtml);
            }
        });
    }

})(jQuery);
