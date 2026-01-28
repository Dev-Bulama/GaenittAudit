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
 * Payment Engine class for handling payments via Paystack, Stripe, and PayPal
 */
class BRST_Payment_Engine {

    /**
     * Supported gateways
     */
    const GATEWAY_PAYSTACK = 'paystack';
    const GATEWAY_STRIPE = 'stripe';
    const GATEWAY_PAYPAL = 'paypal';

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
        add_action('wp_ajax_nopriv_brst_paypal_webhook', array($this, 'handle_paypal_webhook'));
        add_action('wp_ajax_brst_paypal_webhook', array($this, 'handle_paypal_webhook'));

        // PayPal return handlers
        add_action('wp_ajax_nopriv_brst_paypal_return', array($this, 'handle_paypal_return'));
        add_action('wp_ajax_brst_paypal_return', array($this, 'handle_paypal_return'));
    }

    /**
     * Get all available gateways
     */
    public function get_available_gateways() {
        $gateways = array();

        // Paystack
        if ($this->is_gateway_enabled('paystack')) {
            $gateways['paystack'] = array(
                'id' => 'paystack',
                'name' => __('Paystack', 'brst-engine'),
                'description' => __('Pay with card via Paystack', 'brst-engine'),
                'icon' => BRST_PLUGIN_URL . 'assets/images/paystack.png',
            );
        }

        // Stripe
        if ($this->is_gateway_enabled('stripe')) {
            $gateways['stripe'] = array(
                'id' => 'stripe',
                'name' => __('Stripe', 'brst-engine'),
                'description' => __('Pay with card via Stripe', 'brst-engine'),
                'icon' => BRST_PLUGIN_URL . 'assets/images/stripe.png',
            );
        }

        // PayPal
        if ($this->is_gateway_enabled('paypal')) {
            $gateways['paypal'] = array(
                'id' => 'paypal',
                'name' => __('PayPal', 'brst-engine'),
                'description' => __('Pay with PayPal', 'brst-engine'),
                'icon' => BRST_PLUGIN_URL . 'assets/images/paypal.png',
            );
        }

        /**
         * Filter: brst_available_gateways
         * Allows modification of available payment gateways
         */
        return apply_filters('brst_available_gateways', $gateways);
    }

    /**
     * Check if a gateway is enabled
     */
    public function is_gateway_enabled($gateway) {
        $enabled = get_option("brst_{$gateway}_enabled", false);

        // Also check if keys are configured
        switch ($gateway) {
            case 'paystack':
                $has_keys = !empty(get_option('brst_paystack_public_key')) && !empty(get_option('brst_paystack_secret_key'));
                break;
            case 'stripe':
                $has_keys = !empty(get_option('brst_stripe_public_key')) && !empty(get_option('brst_stripe_secret_key'));
                break;
            case 'paypal':
                $has_keys = !empty(get_option('brst_paypal_client_id')) && !empty(get_option('brst_paypal_secret'));
                break;
            default:
                $has_keys = false;
        }

        return $enabled && $has_keys;
    }

    /**
     * Get default/primary payment gateway
     */
    public function get_default_gateway() {
        $default = get_option('brst_default_gateway', self::GATEWAY_PAYSTACK);

        // If default is not enabled, get first available
        if (!$this->is_gateway_enabled($default)) {
            $available = $this->get_available_gateways();
            if (!empty($available)) {
                $default = array_key_first($available);
            }
        }

        return $default;
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
     * Initialize payment for a submission with specified gateway
     */
    public function initialize_payment($submission_id, $gateway = null) {
        if (!$gateway) {
            $gateway = $this->get_default_gateway();
        }

        // Verify gateway is enabled
        if (!$this->is_gateway_enabled($gateway)) {
            return new WP_Error('gateway_disabled', __('Selected payment gateway is not available.', 'brst-engine'));
        }

        switch ($gateway) {
            case self::GATEWAY_PAYSTACK:
                return $this->initialize_paystack_payment($submission_id);
            case self::GATEWAY_STRIPE:
                return $this->initialize_stripe_payment($submission_id);
            case self::GATEWAY_PAYPAL:
                return $this->initialize_paypal_payment($submission_id);
            default:
                return new WP_Error('invalid_gateway', __('Invalid payment gateway.', 'brst-engine'));
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
            'amount' => $amount / 100,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ));

        if (!$payment_id) {
            return new WP_Error('payment_record_failed', __('Failed to create payment record.', 'brst-engine'));
        }

        $payment_data = apply_filters('brst_paystack_payment_data', array(
            'payment_id' => $payment_id,
            'public_key' => $public_key,
            'reference' => $reference,
            'amount' => $amount,
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

        $this->update_payment_transaction_id($payment_id, $body['id']);

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
     * Initialize PayPal payment
     */
    private function initialize_paypal_payment($submission_id) {
        $client_id = get_option('brst_paypal_client_id');
        $secret = get_option('brst_paypal_secret');
        $sandbox = get_option('brst_paypal_sandbox', true);

        if (empty($client_id) || empty($secret)) {
            return new WP_Error('paypal_not_configured', __('PayPal is not properly configured.', 'brst-engine'));
        }

        $amount = $this->get_payment_amount() / 100;
        $currency = $this->get_payment_currency();
        $reference = $this->generate_reference($submission_id);

        // Create payment record
        $payment_id = $this->create_payment_record(array(
            'submission_id' => $submission_id,
            'gateway' => self::GATEWAY_PAYPAL,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => self::STATUS_PENDING,
        ));

        if (!$payment_id) {
            return new WP_Error('payment_record_failed', __('Failed to create payment record.', 'brst-engine'));
        }

        // Get PayPal access token
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            $this->update_payment_status($payment_id, self::STATUS_FAILED);
            return $access_token;
        }

        // Create PayPal order
        $api_base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        $return_url = add_query_arg(array(
            'action' => 'brst_paypal_return',
            'ref' => $reference,
        ), admin_url('admin-ajax.php'));

        $cancel_url = add_query_arg(array(
            'brst_payment_cancelled' => 1,
            'ref' => $reference,
        ), home_url());

        $response = wp_remote_post($api_base . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'reference_id' => $reference,
                        'amount' => array(
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ),
                        'description' => __('Business Risk Stress Test Report', 'brst-engine'),
                    ),
                ),
                'application_context' => array(
                    'return_url' => $return_url,
                    'cancel_url' => $cancel_url,
                    'brand_name' => get_bloginfo('name'),
                    'user_action' => 'PAY_NOW',
                ),
            )),
        ));

        if (is_wp_error($response)) {
            $this->update_payment_status($payment_id, self::STATUS_FAILED);
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $this->update_payment_status($payment_id, self::STATUS_FAILED);
            return new WP_Error('paypal_error', $body['error_description'] ?? $body['message'] ?? __('PayPal error', 'brst-engine'));
        }

        // Update payment with PayPal order ID
        $this->update_payment_transaction_id($payment_id, $body['id']);

        // Find approval URL
        $approval_url = '';
        foreach ($body['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approval_url = $link['href'];
                break;
            }
        }

        return apply_filters('brst_paypal_payment_data', array(
            'success' => true,
            'gateway' => self::GATEWAY_PAYPAL,
            'data' => array(
                'payment_id' => $payment_id,
                'order_id' => $body['id'],
                'reference' => $reference,
                'approval_url' => $approval_url,
                'amount' => $amount,
                'currency' => $currency,
                'client_id' => $client_id,
                'sandbox' => $sandbox,
            ),
        ), $submission_id);
    }

    /**
     * Get PayPal access token
     */
    private function get_paypal_access_token() {
        $client_id = get_option('brst_paypal_client_id');
        $secret = get_option('brst_paypal_secret');
        $sandbox = get_option('brst_paypal_sandbox', true);

        $api_base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        $response = wp_remote_post($api_base . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('paypal_auth_error', $body['error_description'] ?? __('PayPal authentication failed', 'brst-engine'));
        }

        return $body['access_token'];
    }

    /**
     * Handle PayPal return
     */
    public function handle_paypal_return() {
        $reference = sanitize_text_field($_GET['ref'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (empty($reference)) {
            wp_die(__('Invalid payment reference.', 'brst-engine'));
        }

        $payment = $this->get_payment_by_reference($reference);
        if (!$payment) {
            wp_die(__('Payment not found.', 'brst-engine'));
        }

        if ($payment->status === self::STATUS_SUCCESS) {
            // Already processed
            $redirect_url = add_query_arg(array(
                'brst_email_capture' => 1,
                'submission_id' => $payment->submission_id,
                'payment_id' => $payment->id,
            ), home_url());
            wp_redirect($redirect_url);
            exit;
        }

        // Capture the payment
        $capture_result = $this->capture_paypal_payment($payment->transaction_id);

        if (is_wp_error($capture_result)) {
            $this->process_failed_payment($payment->id, array('error' => $capture_result->get_error_message()));
            wp_die($capture_result->get_error_message());
        }

        if ($capture_result['status'] === 'COMPLETED') {
            $this->process_successful_payment($payment->id, $capture_result);

            $redirect_url = add_query_arg(array(
                'brst_email_capture' => 1,
                'submission_id' => $payment->submission_id,
                'payment_id' => $payment->id,
            ), home_url());
            wp_redirect($redirect_url);
            exit;
        }

        wp_die(__('Payment could not be completed.', 'brst-engine'));
    }

    /**
     * Capture PayPal payment
     */
    private function capture_paypal_payment($order_id) {
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $sandbox = get_option('brst_paypal_sandbox', true);
        $api_base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        $response = wp_remote_post($api_base . '/v2/checkout/orders/' . $order_id . '/capture', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => '{}',
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('paypal_capture_error', $body['error_description'] ?? __('Failed to capture payment', 'brst-engine'));
        }

        return $body;
    }

    /**
     * Handle PayPal webhook (IPN)
     */
    public function handle_paypal_webhook() {
        $input = file_get_contents('php://input');
        $event = json_decode($input, true);

        if ($event['event_type'] === 'CHECKOUT.ORDER.APPROVED') {
            $order_id = $event['resource']['id'];
            $reference = $event['resource']['purchase_units'][0]['reference_id'] ?? '';

            if ($reference) {
                $payment = $this->get_payment_by_reference($reference);
                if ($payment && $payment->status !== self::STATUS_SUCCESS) {
                    $capture_result = $this->capture_paypal_payment($order_id);
                    if (!is_wp_error($capture_result) && $capture_result['status'] === 'COMPLETED') {
                        $this->process_successful_payment($payment->id, $capture_result);
                    }
                }
            }
        }

        wp_send_json_success();
        exit;
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

        $result = $wpdb->update($table, $update_data, array('id' => $payment_id));
        do_action('brst_payment_status_updated', $payment_id, $status, $gateway_response);

        return $result;
    }

    /**
     * Update payment transaction ID
     */
    public function update_payment_transaction_id($payment_id, $transaction_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->update($table, array('transaction_id' => $transaction_id), array('id' => $payment_id));
    }

    /**
     * Get payment by reference
     */
    public function get_payment_by_reference($reference) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE reference = %s", $reference));
    }

    /**
     * Get payment by ID
     */
    public function get_payment($payment_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $payment_id));
    }

    /**
     * Get payments for a submission
     */
    public function get_submission_payments($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('payments');

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE submission_id = %d ORDER BY created_at DESC", $submission_id));
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
            array('headers' => array('Authorization' => 'Bearer ' . $secret_key))
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

        $this->update_payment_status($payment_id, self::STATUS_SUCCESS, $transaction_data);

        if (!empty($transaction_data['id'])) {
            $this->update_payment_transaction_id($payment_id, $transaction_data['id']);
        }

        $this->log_activity($payment->submission_id, 'payment_success', array(
            'payment_id' => $payment_id,
            'gateway' => $payment->gateway,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ));

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

        $this->update_payment_status($payment_id, self::STATUS_FAILED, $error_data);

        $this->log_activity($payment->submission_id, 'payment_failed', array(
            'payment_id' => $payment_id,
            'gateway' => $payment->gateway,
            'error' => $error_data,
        ));

        do_action('brst_payment_failed', $payment_id, $payment->submission_id, $error_data);

        return true;
    }

    /**
     * Handle Paystack webhook
     */
    public function handle_paystack_webhook() {
        $secret_key = get_option('brst_paystack_secret_key');
        $input = file_get_contents('php://input');

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
        $input = file_get_contents('php://input');
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
     * Render payment form with gateway selection
     */
    public function render_payment_form($submission_id, $payment_data) {
        $available_gateways = $this->get_available_gateways();
        $default_gateway = $this->get_default_gateway();
        $terms_page = get_option('brst_terms_page');
        $privacy_page = get_option('brst_privacy_page');
        $amount = $this->get_payment_amount() / 100;
        $currency = $this->get_payment_currency();

        if (empty($available_gateways)) {
            return '<div class="brst-error">' . esc_html__('No payment methods are currently available. Please contact support.', 'brst-engine') . '</div>';
        }

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

                <?php if (count($available_gateways) > 1): ?>
                <div class="brst-gateway-selection">
                    <h4><?php esc_html_e('Select Payment Method', 'brst-engine'); ?></h4>
                    <div class="brst-gateway-options">
                        <?php foreach ($available_gateways as $gateway_id => $gateway): ?>
                        <label class="brst-gateway-option <?php echo $gateway_id === $default_gateway ? 'selected' : ''; ?>">
                            <input type="radio" name="gateway" value="<?php echo esc_attr($gateway_id); ?>" <?php checked($gateway_id, $default_gateway); ?>>
                            <span class="brst-gateway-info">
                                <span class="brst-gateway-name"><?php echo esc_html($gateway['name']); ?></span>
                                <span class="brst-gateway-desc"><?php echo esc_html($gateway['description']); ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="gateway" value="<?php echo esc_attr($default_gateway); ?>">
                <?php endif; ?>

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
                <span><?php esc_html_e('Secure payment', 'brst-engine'); ?></span>
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
