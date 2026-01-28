/**
 * Business Risk Stress Test - Public JavaScript
 */

(function($) {
    'use strict';

    // Main BRST object
    window.BRST = {
        form: null,
        currentStep: 0,
        totalSteps: 0,
        submissionId: null,

        /**
         * Initialize
         */
        init: function() {
            this.form = $('#brst-questionnaire-form');
            if (this.form.length) {
                this.initForm();
            }

            this.initPaymentForm();
            this.initEmailCapture();
            this.initFeedbackForm();
        },

        /**
         * Initialize questionnaire form
         */
        initForm: function() {
            var self = this;
            var steps = this.form.find('.brst-form-step');
            this.totalSteps = steps.length;

            // Next button
            this.form.on('click', '.brst-next-step', function(e) {
                e.preventDefault();
                if (self.validateCurrentStep()) {
                    self.goToStep(self.currentStep + 1);
                }
            });

            // Previous button
            this.form.on('click', '.brst-prev-step', function(e) {
                e.preventDefault();
                self.goToStep(self.currentStep - 1);
            });

            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                if (self.validateCurrentStep()) {
                    self.submitForm();
                }
            });

            // Radio change - auto advance visual feedback
            this.form.on('change', 'input[type="radio"]', function() {
                $(this).closest('.brst-radio-group').find('.brst-radio-option').removeClass('selected');
                $(this).closest('.brst-radio-option').addClass('selected');
            });
        },

        /**
         * Go to specific step
         */
        goToStep: function(step) {
            if (step < 0 || step >= this.totalSteps) return;

            var steps = this.form.find('.brst-form-step');
            steps.removeClass('active');
            steps.eq(step).addClass('active');

            this.currentStep = step;
            this.updateProgress();

            // Scroll to top of form
            $('html, body').animate({
                scrollTop: this.form.offset().top - 50
            }, 300);
        },

        /**
         * Update progress bar
         */
        updateProgress: function() {
            var progress = ((this.currentStep + 1) / this.totalSteps) * 100;
            this.form.find('.brst-progress-fill').css('width', progress + '%');
            this.form.find('.brst-progress-text').text('Step ' + (this.currentStep + 1) + ' of ' + this.totalSteps);
        },

        /**
         * Validate current step
         */
        validateCurrentStep: function() {
            var currentStepEl = this.form.find('.brst-form-step').eq(this.currentStep);
            var isValid = true;

            // Clear previous errors
            currentStepEl.find('.brst-field').removeClass('has-error');
            currentStepEl.find('.brst-field-error').text('');

            // Check required fields
            currentStepEl.find('[required]').each(function() {
                var field = $(this);
                var fieldContainer = field.closest('.brst-field');
                var value = '';

                if (field.is('input[type="radio"]')) {
                    var name = field.attr('name');
                    value = currentStepEl.find('input[name="' + name + '"]:checked').val();
                } else {
                    value = field.val();
                }

                if (!value || value.trim() === '') {
                    fieldContainer.addClass('has-error');
                    fieldContainer.find('.brst-field-error').text(brst_ajax.strings.required);
                    isValid = false;
                }
            });

            // Email validation
            currentStepEl.find('input[type="email"]').each(function() {
                var email = $(this).val();
                if (email && !BRST.isValidEmail(email)) {
                    $(this).closest('.brst-field').addClass('has-error');
                    $(this).closest('.brst-field').find('.brst-field-error').text(brst_ajax.strings.invalid_email);
                    isValid = false;
                }
            });

            return isValid;
        },

        /**
         * Validate email
         */
        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Submit form
         */
        submitForm: function() {
            var self = this;
            var container = this.form.closest('.brst-form-container');
            var formData = this.form.serialize();

            // Show loading
            this.form.hide();
            container.find('.brst-loading').show();

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=brst_submit_form',
                success: function(response) {
                    container.find('.brst-loading').hide();

                    if (response.success) {
                        self.submissionId = response.data.submission_id;
                        container.find('.brst-results-container').html(response.data.mini_report_html).show();

                        // Initialize unlock button
                        self.initUnlockButton();
                    } else {
                        self.form.show();
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    container.find('.brst-loading').hide();
                    self.form.show();
                    self.showError(brst_ajax.strings.error);
                }
            });
        },

        /**
         * Initialize unlock button
         */
        initUnlockButton: function() {
            var self = this;
            var container = this.form.closest('.brst-form-container');

            container.on('click', '.brst-unlock-full-report', function(e) {
                e.preventDefault();
                var submissionId = $(this).data('submission-id') || self.submissionId;
                self.showPaymentPage(submissionId);
            });
        },

        /**
         * Show payment page
         */
        showPaymentPage: function(submissionId) {
            var self = this;
            var container = this.form.closest('.brst-form-container');

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'brst_get_payment_page',
                    submission_id: submissionId
                },
                success: function(response) {
                    if (response.success) {
                        container.find('.brst-results-container').html(response.data.html);
                        self.initPaymentForm();
                    }
                }
            });
        },

        /**
         * Initialize payment form
         */
        initPaymentForm: function() {
            var self = this;

            // Check if pay button should be enabled (email + consent)
            var checkPaymentReady = function() {
                var form = $('#brst-payment-form');
                if (!form.length) return;

                var termsChecked = form.find('input[name="accept_terms"]').is(':checked');
                var privacyChecked = form.find('input[name="accept_privacy"]').is(':checked');
                var emailVal = form.find('input[name="payment_email"]').val();
                var emailValid = emailVal && BRST.isValidEmail(emailVal);
                var payButton = form.find('.brst-pay-button');

                if (termsChecked && privacyChecked && emailValid) {
                    payButton.prop('disabled', false);
                } else {
                    payButton.prop('disabled', true);
                }
            };

            // Consent checkbox handling
            $(document).on('change', '#brst-payment-form input[type="checkbox"]', checkPaymentReady);

            // Email field handling
            $(document).on('input', '#brst-payment-form input[name="payment_email"]', checkPaymentReady);

            // Gateway selection
            $(document).on('change', '#brst-payment-form input[name="gateway"]', function() {
                var form = $(this).closest('form');
                form.find('.brst-gateway-option').removeClass('selected');
                $(this).closest('.brst-gateway-option').addClass('selected');
            });

            // Form submission
            $(document).on('submit', '#brst-payment-form', function(e) {
                e.preventDefault();
                self.processPayment($(this));
            });
        },

        /**
         * Process payment
         */
        processPayment: function(form) {
            var self = this;
            var submissionId = form.find('input[name="submission_id"]').val();
            var gateway = form.find('input[name="gateway"]:checked').val() || form.find('input[name="gateway"]').val();
            var paymentEmail = form.find('input[name="payment_email"]').val();
            var formData = form.serialize();

            // Validate email
            if (!paymentEmail || !this.isValidEmail(paymentEmail)) {
                alert('Please enter a valid email address.');
                return;
            }

            // Store the payment email for later use in email capture
            this.paymentEmail = paymentEmail;

            form.find('.brst-pay-button').prop('disabled', true).text(brst_ajax.strings.payment_processing);

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=brst_init_payment',
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        if (data.gateway === 'paystack') {
                            self.openPaystack(data.data);
                        } else if (data.gateway === 'stripe') {
                            self.openStripe(data.data);
                        } else if (data.gateway === 'paypal') {
                            self.openPayPal(data.data);
                        }
                    } else {
                        form.find('.brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                        alert(response.data.message);
                    }
                },
                error: function() {
                    form.find('.brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                    alert(brst_ajax.strings.error);
                }
            });
        },

        /**
         * Open Paystack payment
         */
        openPaystack: function(data) {
            var self = this;

            // Check if PaystackPop is available
            if (typeof PaystackPop === 'undefined') {
                alert('Paystack is not properly loaded. Please refresh the page and try again.');
                $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                return;
            }

            var handler = PaystackPop.setup({
                key: data.public_key,
                email: data.email || '',
                ref: data.reference,
                amount: data.amount,
                currency: data.currency,
                callback: function(response) {
                    self.verifyPayment(response.reference, 'paystack');
                },
                onClose: function() {
                    $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                }
            });

            handler.openIframe();
        },

        /**
         * Open Stripe payment
         */
        openStripe: function(data) {
            var self = this;

            // Check if Stripe is available
            if (typeof Stripe === 'undefined') {
                alert('Stripe is not properly loaded. Please refresh the page and try again.');
                $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                return;
            }

            var stripe = Stripe(data.public_key);

            // Create payment element
            var elements = stripe.elements();
            var cardElement = elements.create('card');

            // Show Stripe modal
            self.showStripeModal(stripe, data, cardElement);
        },

        /**
         * Show Stripe payment modal
         */
        showStripeModal: function(stripe, data, cardElement) {
            var self = this;

            // Create modal HTML
            var modalHtml = '<div class="brst-stripe-modal">' +
                '<div class="brst-stripe-modal-content">' +
                '<h3>Enter Card Details</h3>' +
                '<div id="brst-card-element"></div>' +
                '<div id="brst-card-errors" class="brst-error"></div>' +
                '<div class="brst-stripe-buttons">' +
                '<button type="button" class="brst-btn brst-btn-secondary brst-stripe-cancel">Cancel</button>' +
                '<button type="button" class="brst-btn brst-btn-primary brst-stripe-pay">Pay Now</button>' +
                '</div>' +
                '</div>' +
                '</div>';

            $('body').append(modalHtml);
            cardElement.mount('#brst-card-element');

            // Handle card errors
            cardElement.on('change', function(event) {
                var displayError = document.getElementById('brst-card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });

            // Cancel button
            $('.brst-stripe-cancel').on('click', function() {
                $('.brst-stripe-modal').remove();
                $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
            });

            // Pay button
            $('.brst-stripe-pay').on('click', function() {
                $(this).prop('disabled', true).text('Processing...');

                stripe.confirmCardPayment(data.client_secret, {
                    payment_method: {
                        card: cardElement
                    }
                }).then(function(result) {
                    if (result.error) {
                        $('#brst-card-errors').text(result.error.message);
                        $('.brst-stripe-pay').prop('disabled', false).text('Pay Now');
                    } else {
                        $('.brst-stripe-modal').remove();
                        self.verifyPayment(data.reference, 'stripe');
                    }
                });
            });
        },

        /**
         * Open PayPal payment
         */
        openPayPal: function(data) {
            var self = this;

            // For PayPal, redirect to approval URL
            if (data.approval_url) {
                window.location.href = data.approval_url;
            } else {
                alert('PayPal payment could not be initialized. Please try again.');
                $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
            }
        },

        /**
         * Verify payment
         */
        verifyPayment: function(reference, gateway) {
            var self = this;

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'brst_verify_payment',
                    reference: reference,
                    gateway: gateway
                },
                success: function(response) {
                    if (response.success) {
                        self.showEmailCapture(response.data.submission_id, reference);
                    } else {
                        alert(response.data.message || brst_ajax.strings.payment_failed);
                        $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                    }
                },
                error: function() {
                    alert(brst_ajax.strings.payment_failed);
                    $('#brst-payment-form .brst-pay-button').prop('disabled', false).text('Proceed to Payment');
                }
            });
        },

        /**
         * Show email capture form (after successful payment)
         */
        showEmailCapture: function(submissionId, paymentRef) {
            var container = $('.brst-payment-container').parent();
            var prefillEmail = this.paymentEmail || '';

            var html = '<div class="brst-email-capture-container">' +
                '<div class="brst-email-capture-header">' +
                '<div class="brst-success-icon">&#10003;</div>' +
                '<h2>Payment Successful!</h2>' +
                '<p>Where do you want your full report delivered?</p>' +
                '</div>' +
                '<form id="brst-email-capture-form" class="brst-email-capture-form">' +
                '<input type="hidden" name="submission_id" value="' + submissionId + '">' +
                '<input type="hidden" name="payment_ref" value="' + paymentRef + '">' +

                '<div class="brst-field">' +
                '<label for="brst-capture-email">Email Address <span class="brst-required">*</span></label>' +
                '<input type="email" id="brst-capture-email" name="email" required placeholder="your@email.com" value="' + prefillEmail + '">' +
                '</div>' +

                '<div class="brst-field">' +
                '<label for="brst-capture-name">Your Name</label>' +
                '<input type="text" id="brst-capture-name" name="user_name" class="brst-input" placeholder="Enter your name (for personalizing your report)">' +
                '</div>' +

                '<div class="brst-field">' +
                '<label for="brst-capture-company">Company / Business Name</label>' +
                '<input type="text" id="brst-capture-company" name="company_name" class="brst-input" placeholder="Enter your business name (optional)">' +
                '</div>' +

                '<div class="brst-privacy-note">' +
                '<p>Your details will be used solely to personalize and deliver your report. ' +
                'We take your privacy seriously and will never share your information with third parties.</p>' +
                '</div>' +

                '<div class="brst-field brst-consent-field">' +
                '<label class="brst-checkbox-single">' +
                '<input type="checkbox" name="marketing_consent" value="1">' +
                '<span>Yes, I would like to receive occasional insights, tips, and updates about business risk management. You can unsubscribe at any time.</span>' +
                '</label>' +
                '</div>' +

                '<button type="submit" class="brst-btn brst-btn-primary brst-btn-full">Send My Report</button>' +
                '<div class="brst-email-capture-messages"></div>' +
                '</form>' +
                '</div>';

            container.html(html);
        },

        /**
         * Initialize email capture form
         */
        initEmailCapture: function() {
            var self = this;

            $(document).on('submit', '#brst-email-capture-form', function(e) {
                e.preventDefault();
                self.submitEmailCapture($(this));
            });
        },

        /**
         * Submit email capture
         */
        submitEmailCapture: function(form) {
            var email = form.find('input[name="email"]').val();

            if (!this.isValidEmail(email)) {
                form.find('.brst-email-capture-messages').html(
                    '<div class="brst-error">' + brst_ajax.strings.invalid_email + '</div>'
                );
                return;
            }

            var submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=brst_submit_email&brst_nonce=' + brst_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        form.parent().html(
                            '<div class="brst-feedback-thank-you">' +
                            '<div class="brst-thank-you-icon">&#10003;</div>' +
                            '<h2>Report Sent!</h2>' +
                            '<p>Your full report has been sent to <strong>' + response.data.email + '</strong></p>' +
                            '<p>Please check your inbox (and spam folder).</p>' +
                            '</div>'
                        );
                    } else {
                        form.find('.brst-email-capture-messages').html(
                            '<div class="brst-error">' + response.data.message + '</div>'
                        );
                        submitBtn.prop('disabled', false).text('Send My Report');
                    }
                },
                error: function() {
                    form.find('.brst-email-capture-messages').html(
                        '<div class="brst-error">' + brst_ajax.strings.error + '</div>'
                    );
                    submitBtn.prop('disabled', false).text('Send My Report');
                }
            });
        },

        /**
         * Initialize feedback form
         */
        initFeedbackForm: function() {
            var self = this;

            $(document).on('submit', '#brst-feedback-form', function(e) {
                e.preventDefault();
                self.submitFeedback($(this));
            });
        },

        /**
         * Submit feedback
         */
        submitFeedback: function(form) {
            var submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Submitting...');

            $.ajax({
                url: brst_ajax.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=brst_submit_feedback',
                success: function(response) {
                    if (response.success) {
                        form.parent().html(
                            '<div class="brst-feedback-thank-you">' +
                            '<div class="brst-thank-you-icon">&#10003;</div>' +
                            '<h2>Thank You!</h2>' +
                            '<p>' + response.data.message + '</p>' +
                            '</div>'
                        );
                    } else {
                        form.find('.brst-feedback-messages').html(
                            '<div class="brst-error">' + response.data.message + '</div>'
                        );
                        submitBtn.prop('disabled', false).text('Submit Feedback');
                    }
                },
                error: function() {
                    form.find('.brst-feedback-messages').html(
                        '<div class="brst-error">' + brst_ajax.strings.error + '</div>'
                    );
                    submitBtn.prop('disabled', false).text('Submit Feedback');
                }
            });
        },

        /**
         * Show error message
         */
        showError: function(message) {
            var messagesEl = this.form.find('.brst-form-messages');
            messagesEl.html('<div class="brst-error">' + message + '</div>');

            setTimeout(function() {
                messagesEl.empty();
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BRST.init();
    });

})(jQuery);
