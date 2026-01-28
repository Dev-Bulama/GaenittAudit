<?php
/**
 * Payment Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Engine class for handling payments via Paystack and Stripe
 */
class BRST_Payment_Engine {

    /**
     * Supported gateways
     */
    const GATEWAY_PAYSTACK = 'paystack';
    const GATEWAY_STRIPE = 'stripe';

    /**
     * Payment statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Constructor
     */
    public function __construct() {
        // Add webhook handlers
        add_action('wp_ajax_nopriv_brst_paystack_webhook', array($this, 'handle_paystack_webhook'));
        add_action('wp_ajax_brst_paystack_webhook', array($this, 'handle_paystack_webhook'));
        add_action('wp_ajax_nopriv_brst_stripe_webhook', array($this, 'handle_stripe_webhook'));
        add_action('wp_ajax_brst_stripe_webhook', array($this, 'handle_stripe_webhook'));
    }

    /**
     * Get active payment gateway
     */
    public function get_active_gateway() {
        return get_option('brst_payment_gateway', self::GATEWAY_PAYSTACK);
    }

    /**
     * Get payment amount
     */
    public function get_payment_amount() {
        return intval(get_option('brst_payment_amount', 5000));
    }

    /**
     * Get payment currency
     */
    public function get_payment_currency() {
        return get_option('brst_payment_currency', 'NGN');
    }

    /**
     * Initialize payment for a submission
     */
    public function initialize_payment($submission_id) {
        $gateway = $this->get_active_gateway();

        switch ($gateway) {
            case self::GATEWAY_PAYSTACK:
                return $this->initialize_paystack_payment($submission_id);
            case self::GATEWAY_STRIPE:
                return $this->initialize_stripe_payment($submission_id);
            default:
                return new WP_Error('invalid_gateway', __('Invalid payment gateway configured.', 'brst-engine'));
        }
    }

    /**
     * Initialize Paystack payment
     */
    private function initialize_paystack_payment($submission_id) {
        $secret_key = get_option('brst_paystack_secret_key');
        $public_key = get_option('brst_paystack_public_key');

        if (empty($secret_key) || empty($public_key)) {
            return new WP_Error('paystack_not_configured', __('Paystack is not properly configured.', 'brst-engine'));
        }

        $amount = $this->get_payment_amount();
        $currency = $this->get_payment_currency();
        $reference = $this->generate_reference($submission_id);

        // Create payment record
        $payment_id = $this->create_payment_record(array(
            'submission_id' => $submission_id,
            'gateway' => self::GATEWAY_PAYSTACK,
            'reference' => $reference,
            'amount' => $amount / 100, // Store in major currency unit
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ));

        if (!$payment_id) {
            return new WP_Error('payment_record_failed', __('Failed to create payment record.', 'brst-engine'));
        }

        /**
         * Filter: brst_paystack_payment_data
         * Allows modification of Paystack payment initialization data
         */
        $payment_data = apply_filters('brst_paystack_payment_data', array(
            'payment_id' => $payment_id,
            'public_key' => $public_key,
            'reference' => $reference,
            'amount' => $amount, // In kobo
            'currency' => $currency,
            'callback_url' => add_query_arg(array(
                'brst_payment_callback' => 1,
                'gateway' => 'paystack',
                'ref' => $reference,
            ), home_url()),
        ), $submission_id);

        return array(
            'success' => true,
            'gateway' => self::GATEWAY_PAYSTACK,
            'data' => $payment_data,
        );
    }

    /**
     * Initialize Stripe payment
     */
    private function initialize_stripe_payment($submission_id) {
        $secret_key = get_option('brst_stripe_secret_key');
        $public_key = get_option('brst_stripe_public_key');

        if (empty($secret_key) || empty($public_key)) {
            return new WP_Error('stripe_not_configured', __('Stripe is not properly configured.', 'brst-engine'));
        }

        $amount = $this->get_payment_amount();
        $currency = strtolower($this->get_payment_currency());
        $reference = $this->generate_reference($submission_id);

        // Create payment record
        $payment_id = $this->create_payment_record(array(
            'submission_id' => $submission_id,
            'gateway' => self::GATEWAY_STRIPE,
            'reference' => $reference,
            'amount' => $amount / 100,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ));

        if (!$payment_id) {
            return new WP_Error('payment_record_failed', __('Failed to create payment record.', 'brst-engine'));
        }

        // Create Stripe PaymentIntent
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount' => $amount,
                'currency' => $currency,
                'metadata[submission_id]' => $submission_id,
                'metadata[payment_id]' => $payment_id,
                'metadata[reference]' => $reference,
            ),
        ));

        if (is_wp_error($response)) {
            $this->update_payment_status($payment_id, self::STATUS_FAILED);
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $this->update_payment_status($payment_id, self::STATUS_FAILED);
            return new WP_Error('stripe_error', $body['error']['message']);
        }

        // Update payment with Stripe ID
        $this->update_payment_transaction_id($payment_id, $body['id']);

        /**
         * Filter: brst_stripe_payment_data
         * Allows modification of Stripe payment initialization data
         */
        return apply_filters('brst_stripe_payment_data', array(
            'success' => true,
            'gateway' => self::GATEWAY_STRIPE,
            'data' => array(
                'payment_id' => $payment_id,
                'public_key' => $public_key,
                'client_secret' => $body['client_secret'],
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
            ),
        ), $submission_id);
    }

    /**
     * Generate unique payment reference
     */
    public function generate_reference($submission_id) {
        return 'BRST_' . $submission_id . '_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * Create payment record
     */
    public function create_payment_record($data) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        $result = $wpdb->insert($table, array(
            'submission_id' => intval($data['submission_id']),
            'gateway' => sanitize_text_field($data['gateway']),
            'reference' => sanitize_text_field($data['reference']),
            'amount' => floatval($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'status' => sanitize_text_field($data['status']),
        ));

        if ($result) {
            $payment_id = $wpdb->insert_id;

            /**
             * Action: brst_payment_created
             * Fires when a payment record is created
             */
            do_action('brst_payment_created', $payment_id, $data);

            return $payment_id;
        }

        return false;
    }

    /**
     * Update payment status
     */
    public function update_payment_status($payment_id, $status, $gateway_response = null) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        $update_data = array('status' => $status);
        if ($gateway_response) {
            $update_data['gateway_response'] = wp_json_encode($gateway_response);
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $payment_id)
        );

        /**
         * Action: brst_payment_status_updated
         * Fires when payment status is updated
         */
        do_action('brst_payment_status_updated', $payment_id, $status, $gateway_response);

        return $result;
    }

    /**
     * Update payment transaction ID
     */
    public function update_payment_transaction_id($payment_id, $transaction_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->update(
            $table,
            array('transaction_id' => $transaction_id),
            array('id' => $payment_id)
        );
    }

    /**
     * Get payment by reference
     */
    public function get_payment_by_reference($reference) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE reference = %s", $reference)
        );
    }

    /**
     * Get payment by ID
     */
    public function get_payment($payment_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $payment_id)
        );
    }

    /**
     * Get payments for a submission
     */
    public function get_submission_payments($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE submission_id = %d ORDER BY created_at DESC", $submission_id)
        );
    }

    /**
     * Verify Paystack payment
     */
    public function verify_paystack_payment($reference) {
        $secret_key = get_option('brst_paystack_secret_key');

        if (empty($secret_key)) {
            return new WP_Error('paystack_not_configured', __('Paystack is not properly configured.', 'brst-engine'));
        }

        $response = wp_remote_get(
            'https://api.paystack.co/transaction/verify/' . rawurlencode($reference),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body['status']) {
            return new WP_Error('verification_failed', $body['message'] ?? __('Payment verification failed.', 'brst-engine'));
        }

        return $body['data'];
    }

    /**
     * Process successful payment
     */
    public function process_successful_payment($payment_id, $transaction_data = array()) {
        $payment = $this->get_payment($payment_id);

        if (!$payment) {
            return new WP_Error('payment_not_found', __('Payment not found.', 'brst-engine'));
        }

        // Update payment status
        $this->update_payment_status($payment_id, self::STATUS_SUCCESS, $transaction_data);

        if (!empty($transaction_data['id'])) {
            $this->update_payment_transaction_id($payment_id, $transaction_data['id']);
        }

        // Log the activity
        $this->log_activity($payment->submission_id, 'payment_success', array(
            'payment_id' => $payment_id,
            'gateway' => $payment->gateway,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ));

        /**
         * Action: brst_payment_success
         * Fires when payment is successful
         */
        do_action('brst_payment_success', $payment_id, $payment->submission_id, $transaction_data);

        return true;
    }

    /**
     * Process failed payment
     */
    public function process_failed_payment($payment_id, $error_data = array()) {
        $payment = $this->get_payment($payment_id);

        if (!$payment) {
            return new WP_Error('payment_not_found', __('Payment not found.', 'brst-engine'));
        }

        // Update payment status
        $this->update_payment_status($payment_id, self::STATUS_FAILED, $error_data);

        // Log the activity
        $this->log_activity($payment->submission_id, 'payment_failed', array(
            'payment_id' => $payment_id,
            'gateway' => $payment->gateway,
            'error' => $error_data,
        ));

        /**
         * Action: brst_payment_failed
         * Fires when payment fails
         */
        do_action('brst_payment_failed', $payment_id, $payment->submission_id, $error_data);

        return true;
    }

    /**
     * Handle Paystack webhook
     */
    public function handle_paystack_webhook() {
        $secret_key = get_option('brst_paystack_secret_key');
        $input = file_get_contents('php://input');

        // Verify signature
        if (!empty($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
            $signature = hash_hmac('sha512', $input, $secret_key);
            if ($signature !== $_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) {
                wp_send_json_error('Invalid signature', 400);
                exit;
            }
        }

        $event = json_decode($input, true);

        if ($event['event'] === 'charge.success') {
            $reference = $event['data']['reference'];
            $payment = $this->get_payment_by_reference($reference);

            if ($payment && $payment->status !== self::STATUS_SUCCESS) {
                $this->process_successful_payment($payment->id, $event['data']);
            }
        }

        wp_send_json_success();
        exit;
    }

    /**
     * Handle Stripe webhook
     */
    public function handle_stripe_webhook() {
        $secret_key = get_option('brst_stripe_webhook_secret');
        $input = file_get_contents('php://input');

        // Verify signature if webhook secret is configured
        if (!empty($secret_key) && !empty($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            // Stripe signature verification would go here
        }

        $event = json_decode($input, true);

        if ($event['type'] === 'payment_intent.succeeded') {
            $payment_intent = $event['data']['object'];
            $reference = $payment_intent['metadata']['reference'] ?? '';

            if ($reference) {
                $payment = $this->get_payment_by_reference($reference);
                if ($payment && $payment->status !== self::STATUS_SUCCESS) {
                    $this->process_successful_payment($payment->id, $payment_intent);
                }
            }
        }

        wp_send_json_success();
        exit;
    }

    /**
     * Render payment form
     */
    public function render_payment_form($submission_id, $payment_data) {
        $gateway = $this->get_active_gateway();
        $terms_page = get_option('brst_terms_page');
        $privacy_page = get_option('brst_privacy_page');
        $amount = $this->get_payment_amount() / 100;
        $currency = $this->get_payment_currency();

        ob_start();
        ?>
        <div class="brst-payment-container" data-submission-id="<?php echo esc_attr($submission_id); ?>">
            <div class="brst-payment-header">
                <h2><?php esc_html_e('Unlock Your Full Report', 'brst-engine'); ?></h2>
                <p class="brst-payment-description">
                    <?php esc_html_e('Your full report includes detailed analysis, personalized recommendations, and actionable insights to help your business thrive.', 'brst-engine'); ?>
                </p>
            </div>

            <div class="brst-payment-summary">
                <h3><?php esc_html_e('What\'s Included', 'brst-engine'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Complete risk analysis across all 5 business categories', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('Personalized recommendations based on your profile', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('Actionable steps to address identified risks', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('PDF report delivered to your email', 'brst-engine'); ?></li>
                </ul>
            </div>

            <div class="brst-payment-amount">
                <span class="brst-currency"><?php echo esc_html($currency); ?></span>
                <span class="brst-amount"><?php echo esc_html(number_format($amount, 2)); ?></span>
            </div>

            <form id="brst-payment-form" class="brst-payment-form">
                <?php wp_nonce_field('brst_payment', 'brst_payment_nonce'); ?>
                <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission_id); ?>">
                <input type="hidden" name="gateway" value="<?php echo esc_attr($gateway); ?>">

                <div class="brst-consent-section">
                    <label class="brst-consent-checkbox brst-required-consent">
                        <input type="checkbox" name="accept_terms" required>
                        <span>
                            <?php
                            if ($terms_page) {
                                printf(
                                    esc_html__('I accept the %sTerms & Conditions%s', 'brst-engine'),
                                    '<a href="' . esc_url(get_permalink($terms_page)) . '" target="_blank">',
                                    '</a>'
                                );
                            } else {
                                esc_html_e('I accept the Terms & Conditions', 'brst-engine');
                            }
                            ?>
                            <span class="brst-required">*</span>
                        </span>
                    </label>

                    <label class="brst-consent-checkbox brst-required-consent">
                        <input type="checkbox" name="accept_privacy" required>
                        <span>
                            <?php
                            if ($privacy_page) {
                                printf(
                                    esc_html__('I accept the %sPrivacy Policy%s', 'brst-engine'),
                                    '<a href="' . esc_url(get_permalink($privacy_page)) . '" target="_blank">',
                                    '</a>'
                                );
                            } else {
                                esc_html_e('I accept the Privacy Policy', 'brst-engine');
                            }
                            ?>
                            <span class="brst-required">*</span>
                        </span>
                    </label>
                </div>

                <div class="brst-consent-warning" style="display: none;">
                    <?php esc_html_e('You must accept the Terms & Conditions and Privacy Policy to proceed.', 'brst-engine'); ?>
                </div>

                <button type="submit" class="brst-btn brst-btn-primary brst-pay-button" disabled>
                    <?php esc_html_e('Proceed to Payment', 'brst-engine'); ?>
                </button>
            </form>

            <div class="brst-payment-secure">
                <span class="brst-secure-icon">🔒</span>
                <span><?php esc_html_e('Secure payment powered by', 'brst-engine'); ?> <?php echo esc_html(ucfirst($gateway)); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Log activity
     */
    private function log_activity($submission_id, $action, $details = array()) {
        global $wpdb;
        $table = BRST_Database::get_table_name('activity_log');

        $wpdb->insert($table, array(
            'submission_id' => $submission_id,
            'action' => $action,
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_client_ip(),
        ));
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
