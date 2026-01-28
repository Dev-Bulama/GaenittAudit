<?php
/**
 * AJAX Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler class for processing AJAX requests
 */
class BRST_Ajax_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // Form submission
        add_action('wp_ajax_brst_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_brst_submit_form', array($this, 'handle_form_submission'));

        // Initialize payment
        add_action('wp_ajax_brst_init_payment', array($this, 'handle_init_payment'));
        add_action('wp_ajax_nopriv_brst_init_payment', array($this, 'handle_init_payment'));

        // Verify payment
        add_action('wp_ajax_brst_verify_payment', array($this, 'handle_verify_payment'));
        add_action('wp_ajax_nopriv_brst_verify_payment', array($this, 'handle_verify_payment'));

        // Submit email
        add_action('wp_ajax_brst_submit_email', array($this, 'handle_submit_email'));
        add_action('wp_ajax_nopriv_brst_submit_email', array($this, 'handle_submit_email'));

        // Submit feedback
        add_action('wp_ajax_brst_submit_feedback', array($this, 'handle_submit_feedback'));
        add_action('wp_ajax_nopriv_brst_submit_feedback', array($this, 'handle_submit_feedback'));

        // Get mini report
        add_action('wp_ajax_brst_get_mini_report', array($this, 'handle_get_mini_report'));
        add_action('wp_ajax_nopriv_brst_get_mini_report', array($this, 'handle_get_mini_report'));

        // Get payment page
        add_action('wp_ajax_brst_get_payment_page', array($this, 'handle_get_payment_page'));
        add_action('wp_ajax_nopriv_brst_get_payment_page', array($this, 'handle_get_payment_page'));
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['brst_nonce'] ?? '', 'brst_form_submit')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'brst-engine')));
        }

        // Get form data
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $responses = array();

        // Get user info (name, email, company)
        $user_name = sanitize_text_field($_POST['user_name'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $company_name = sanitize_text_field($_POST['company_name'] ?? '');

        // Validate user info
        if (empty($user_name)) {
            wp_send_json_error(array('message' => __('Please enter your name.', 'brst-engine')));
        }

        if (empty($user_email) || !is_email($user_email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'brst-engine')));
        }

        // Collect responses for Q1-Q21
        for ($i = 1; $i <= 21; $i++) {
            $key = "q{$i}";
            if (isset($_POST[$key])) {
                $responses[$key] = sanitize_text_field($_POST[$key]);
            }
        }

        // Validate responses
        $scoring_engine = new BRST_Scoring_Engine();
        $validation_errors = $scoring_engine->validate_responses($responses);

        if (!empty($validation_errors)) {
            wp_send_json_error(array(
                'message' => __('Please answer all questions.', 'brst-engine'),
                'errors' => $validation_errors,
            ));
        }

        // Calculate scores
        $scores_data = $scoring_engine->calculate_scores($responses);

        // Analyze profiles
        $profile_engine = new BRST_Profile_Engine();
        $profile_analysis = $profile_engine->analyze_profiles(
            $scores_data['category_scores'],
            $scores_data['q21_response']
        );

        // Determine interaction
        $interaction_engine = new BRST_Interaction_Engine();
        $interaction = $interaction_engine->determine_interaction(
            $scores_data['category_scores'],
            $profile_analysis['primary_profile']['key'],
            $profile_analysis['secondary_profile']['key']
        );

        // Save submission with user info
        $submission_id = $this->save_submission(array(
            'form_id' => $form_id,
            'session_id' => $session_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'company_name' => $company_name,
            'responses' => $responses,
            'scores_data' => $scores_data,
            'profile_analysis' => $profile_analysis,
            'interaction' => $interaction,
        ));

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Failed to save your responses. Please try again.', 'brst-engine')));
        }

        // Save email capture immediately (so we have it for reports)
        $gdpr = new BRST_GDPR();
        $gdpr->save_email_capture($submission_id, $user_email, false, $user_name);

        // Generate mini report
        $mini_report_engine = new BRST_Mini_Report();
        $mini_report = $mini_report_engine->generate($profile_analysis, $scores_data);
        $mini_report_html = $mini_report_engine->render($mini_report, $submission_id);

        // Mark mini report as shown
        $mini_report_engine->mark_as_shown($submission_id);

        // Log activity
        $this->log_activity($submission_id, 'form_submitted');

        /**
         * Action: brst_form_submitted
         * Fires when form is submitted successfully
         */
        do_action('brst_form_submitted', $submission_id, $responses, $scores_data);

        wp_send_json_success(array(
            'submission_id' => $submission_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'mini_report' => $mini_report,
            'mini_report_html' => $mini_report_html,
            'profile' => $profile_analysis['primary_profile'],
        ));
    }

    /**
     * Save submission to database
     */
    private function save_submission($data) {
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');

        $result = $wpdb->insert($table, array(
            'form_id' => 1, // Default form
            'session_id' => $data['session_id'],
            'user_name' => $data['user_name'] ?? '',
            'user_email' => $data['user_email'] ?? '',
            'company_name' => $data['company_name'] ?? '',
            'responses' => wp_json_encode($data['responses']),
            'scores' => wp_json_encode($data['scores_data']['individual_scores']),
            'category_scores' => wp_json_encode($data['scores_data']['category_scores']),
            'primary_profile' => $data['profile_analysis']['primary_profile']['key'],
            'secondary_profile' => $data['profile_analysis']['secondary_profile']['key'],
            'interaction_applicable' => $data['interaction']['is_applicable'] ? 1 : 0,
            'q21_response' => $data['scores_data']['q21_response'],
            'alignment_status' => $data['profile_analysis']['alignment']['status'],
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ));

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Handle initialize payment
     */
    public function handle_init_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['brst_payment_nonce'] ?? '', 'brst_payment')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'brst-engine')));
        }

        $submission_id = intval($_POST['submission_id'] ?? 0);
        $gateway = sanitize_text_field($_POST['gateway'] ?? '');

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Invalid submission.', 'brst-engine')));
        }

        // Check consent checkboxes
        if (empty($_POST['accept_terms']) || empty($_POST['accept_privacy'])) {
            wp_send_json_error(array('message' => __('You must accept the Terms & Conditions and Privacy Policy.', 'brst-engine')));
        }

        // Get user email from the submission for payment gateways
        global $wpdb;
        $submissions_table = BRST_Database::get_table_name('submissions');
        $submission = $wpdb->get_row($wpdb->prepare("SELECT user_email, user_name FROM $submissions_table WHERE id = %d", $submission_id));
        $user_email = $submission ? $submission->user_email : '';

        // Initialize payment with selected gateway
        $payment_engine = new BRST_Payment_Engine();
        $result = $payment_engine->initialize_payment($submission_id, $gateway ?: null);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Attach user email to the response for Paystack
        if (!empty($result['data']) && !empty($user_email)) {
            $result['data']['email'] = $user_email;
        }

        // Log activity
        $this->log_activity($submission_id, 'payment_initiated');

        wp_send_json_success($result);
    }

    /**
     * Handle verify payment
     */
    public function handle_verify_payment() {
        $reference = sanitize_text_field($_POST['reference'] ?? $_GET['reference'] ?? '');
        $gateway = sanitize_text_field($_POST['gateway'] ?? $_GET['gateway'] ?? 'paystack');

        if (empty($reference)) {
            wp_send_json_error(array('message' => __('Payment reference is required.', 'brst-engine')));
        }

        $payment_engine = new BRST_Payment_Engine();
        $payment = $payment_engine->get_payment_by_reference($reference);

        if (!$payment) {
            wp_send_json_error(array('message' => __('Payment not found.', 'brst-engine')));
        }

        if ($payment->status === 'success') {
            wp_send_json_success(array(
                'status' => 'success',
                'submission_id' => $payment->submission_id,
                'message' => __('Payment already verified.', 'brst-engine'),
            ));
        }

        // Verify with gateway
        if ($gateway === 'paystack') {
            $verification = $payment_engine->verify_paystack_payment($reference);

            if (is_wp_error($verification)) {
                $payment_engine->process_failed_payment($payment->id, array('error' => $verification->get_error_message()));
                wp_send_json_error(array('message' => $verification->get_error_message()));
            }

            if ($verification['status'] === 'success') {
                $payment_engine->process_successful_payment($payment->id, $verification);

                wp_send_json_success(array(
                    'status' => 'success',
                    'submission_id' => $payment->submission_id,
                    'message' => __('Payment successful!', 'brst-engine'),
                ));
            }
        }

        wp_send_json_error(array('message' => __('Payment verification failed.', 'brst-engine')));
    }

    /**
     * Handle submit email
     */
    public function handle_submit_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['brst_nonce'] ?? '', 'brst_nonce')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'brst-engine')));
        }

        $submission_id = intval($_POST['submission_id'] ?? 0);
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $marketing_consent = !empty($_POST['marketing_consent']);

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Invalid submission.', 'brst-engine')));
        }

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'brst-engine')));
        }

        // Save email capture
        $gdpr = new BRST_GDPR();
        $gdpr->save_email_capture($submission_id, $email, $marketing_consent);

        // Generate report
        $pdf_engine = new BRST_PDF_Engine();
        $report = $pdf_engine->generate_report($submission_id, $payment_id);

        if (is_wp_error($report)) {
            wp_send_json_error(array('message' => $report->get_error_message()));
        }

        // Send email
        $email_engine = new BRST_Email_Engine();
        $sent = $email_engine->send_report_email($submission_id, $email, $report['file_path']);

        if (!$sent) {
            wp_send_json_error(array('message' => __('Failed to send email. Please try again.', 'brst-engine')));
        }

        // Log activity
        $this->log_activity($submission_id, 'report_sent', array('email' => $email));

        wp_send_json_success(array(
            'message' => __('Your report has been sent to your email!', 'brst-engine'),
            'email' => $email,
        ));
    }

    /**
     * Handle submit feedback
     */
    public function handle_submit_feedback() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['brst_feedback_nonce'] ?? '', 'brst_feedback_submit')) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'brst-engine')));
        }

        $feedback_engine = new BRST_Feedback_Engine();
        $result = $feedback_engine->submit_feedback($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Thank you for your feedback!', 'brst-engine'),
        ));
    }

    /**
     * Handle get mini report
     */
    public function handle_get_mini_report() {
        $submission_id = intval($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Invalid submission.', 'brst-engine')));
        }

        // Get submission
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id));

        if (!$submission) {
            wp_send_json_error(array('message' => __('Submission not found.', 'brst-engine')));
        }

        // Regenerate mini report
        $scoring_engine = new BRST_Scoring_Engine();
        $profile_engine = new BRST_Profile_Engine();

        $category_scores = json_decode($submission->category_scores, true);
        $profile_analysis = $profile_engine->analyze_profiles($category_scores, $submission->q21_response);

        $scores_data = array(
            'category_scores' => $category_scores,
            'q21_response' => $submission->q21_response,
        );

        $mini_report_engine = new BRST_Mini_Report();
        $mini_report = $mini_report_engine->generate($profile_analysis, $scores_data);
        $mini_report_html = $mini_report_engine->render($mini_report, $submission_id);

        wp_send_json_success(array(
            'mini_report' => $mini_report,
            'mini_report_html' => $mini_report_html,
        ));
    }

    /**
     * Handle get payment page
     */
    public function handle_get_payment_page() {
        $submission_id = intval($_POST['submission_id'] ?? 0);

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Invalid submission.', 'brst-engine')));
        }

        $payment_engine = new BRST_Payment_Engine();
        $html = $payment_engine->render_payment_form($submission_id, array());

        wp_send_json_success(array(
            'html' => $html,
        ));
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
     * Get client IP
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
