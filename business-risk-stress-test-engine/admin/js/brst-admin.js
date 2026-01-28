/**
 * Business Risk Stress Test - Admin JavaScript
 */

(function($) {
    'use strict';

    window.BRSTAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.initFormBuilder();
            this.initSettings();
            this.initExport();
        },

        /**
         * Initialize form builder (placeholder for future drag-and-drop)
         */
        initFormBuilder: function() {
            // Drag and drop functionality would be added here
            // For now, forms are schema-based
        },

        /**
         * Initialize settings page
         */
        initSettings: function() {
            // Toggle gateway fields based on selection
            $('#brst_payment_gateway').on('change', function() {
                var gateway = $(this).val();

                if (gateway === 'paystack') {
                    $('[id^="brst_paystack"]').closest('tr').show();
                    $('[id^="brst_stripe"]').closest('tr').hide();
                } else if (gateway === 'stripe') {
                    $('[id^="brst_paystack"]').closest('tr').hide();
                    $('[id^="brst_stripe"]').closest('tr').show();
                }
            }).trigger('change');

            // Test payment gateway connection
            $('.brst-test-gateway').on('click', function(e) {
                e.preventDefault();
                var gateway = $(this).data('gateway');
                BRSTAdmin.testGatewayConnection(gateway);
            });
        },

        /**
         * Test gateway connection
         */
        testGatewayConnection: function(gateway) {
            var button = $('.brst-test-gateway[data-gateway="' + gateway + '"]');
            var originalText = button.text();

            button.prop('disabled', true).text('Testing...');

            $.ajax({
                url: brst_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'brst_test_gateway',
                    gateway: gateway,
                    nonce: brst_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Connection successful!');
                    } else {
                        alert('Connection failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Connection test failed. Please try again.');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Initialize export functionality
         */
        initExport: function() {
            $('.brst-export-csv').on('click', function(e) {
                var confirmExport = confirm('This will download all submissions as a CSV file. Continue?');
                if (!confirmExport) {
                    e.preventDefault();
                }
            });
        },

        /**
         * Confirm delete
         */
        confirmDelete: function(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'success';
            var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';

            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

            $('.brst-admin-wrap h1').after(notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BRSTAdmin.init();
    });

})(jQuery);
