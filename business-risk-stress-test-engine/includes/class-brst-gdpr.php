<?php
/**
 * GDPR Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GDPR class for handling privacy-related functionality
 */
class BRST_GDPR {

    /**
     * Constructor
     */
    public function __construct() {
        // Register privacy policy content
        add_action('admin_init', array($this, 'add_privacy_policy_content'));

        // Register data exporter
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'));

        // Register data eraser
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'));
    }

    /**
     * Add privacy policy suggested content
     */
    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = $this->get_privacy_policy_content();
        wp_add_privacy_policy_content('Business Risk Stress Test Engine', $content);
    }

    /**
     * Get privacy policy content
     */
    private function get_privacy_policy_content() {
        return '
        <h2>' . __('Business Risk Stress Test', 'brst-engine') . '</h2>
        <p>' . __('When you use our Business Risk Stress Test, we collect the following information:', 'brst-engine') . '</p>
        <ul>
            <li>' . __('Your responses to the questionnaire (21 questions)', 'brst-engine') . '</li>
            <li>' . __('Your email address (required for report delivery)', 'brst-engine') . '</li>
            <li>' . __('Payment information (processed securely by our payment provider)', 'brst-engine') . '</li>
            <li>' . __('Your IP address for security purposes', 'brst-engine') . '</li>
            <li>' . __('Optional marketing consent preference', 'brst-engine') . '</li>
        </ul>
        <h3>' . __('How We Use Your Data', 'brst-engine') . '</h3>
        <p>' . __('We use this information to:', 'brst-engine') . '</p>
        <ul>
            <li>' . __('Generate your personalized business risk report', 'brst-engine') . '</li>
            <li>' . __('Send your report to your email address', 'brst-engine') . '</li>
            <li>' . __('Process your payment securely', 'brst-engine') . '</li>
            <li>' . __('Send feedback requests (one reminder only)', 'brst-engine') . '</li>
            <li>' . __('Send marketing communications (only with your consent)', 'brst-engine') . '</li>
        </ul>
        <h3>' . __('Data Retention', 'brst-engine') . '</h3>
        <p>' . __('Your questionnaire responses and report data are retained for 2 years unless you request earlier deletion. Payment records are retained as required by law.', 'brst-engine') . '</p>
        <h3>' . __('Your Rights', 'brst-engine') . '</h3>
        <p>' . __('You have the right to access, correct, or delete your personal data. Contact us to exercise these rights.', 'brst-engine') . '</p>
        ';
    }

    /**
     * Register data exporter
     */
    public function register_data_exporter($exporters) {
        $exporters['brst-engine'] = array(
            'exporter_friendly_name' => __('Business Risk Stress Test Data', 'brst-engine'),
            'callback' => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    /**
     * Register data eraser
     */
    public function register_data_eraser($erasers) {
        $erasers['brst-engine'] = array(
            'eraser_friendly_name' => __('Business Risk Stress Test Data', 'brst-engine'),
            'callback' => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    /**
     * Export personal data
     */
    public function export_personal_data($email, $page = 1) {
        global $wpdb;

        $data_to_export = array();
        $items_per_page = 10;
        $offset = ($page - 1) * $items_per_page;

        // Find submissions by email
        $email_table = BRST_Database::get_table_name('email_captures');
        $submissions_table = BRST_Database::get_table_name('submissions');

        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, e.email, e.marketing_consent, e.consent_timestamp
             FROM $submissions_table s
             INNER JOIN $email_table e ON s.id = e.submission_id
             WHERE e.email = %s
             LIMIT %d OFFSET %d",
            $email,
            $items_per_page,
            $offset
        ));

        foreach ($submissions as $submission) {
            $data = array(
                array(
                    'name' => __('Submission Date', 'brst-engine'),
                    'value' => $submission->created_at,
                ),
                array(
                    'name' => __('Primary Profile', 'brst-engine'),
                    'value' => $submission->primary_profile,
                ),
                array(
                    'name' => __('Secondary Profile', 'brst-engine'),
                    'value' => $submission->secondary_profile,
                ),
                array(
                    'name' => __('Alignment Status', 'brst-engine'),
                    'value' => $submission->alignment_status,
                ),
                array(
                    'name' => __('Marketing Consent', 'brst-engine'),
                    'value' => $submission->marketing_consent ? __('Yes', 'brst-engine') : __('No', 'brst-engine'),
                ),
                array(
                    'name' => __('Consent Timestamp', 'brst-engine'),
                    'value' => $submission->consent_timestamp,
                ),
            );

            // Add responses
            $responses = json_decode($submission->responses, true);
            if ($responses) {
                foreach ($responses as $key => $value) {
                    $data[] = array(
                        'name' => sprintf(__('Response: %s', 'brst-engine'), strtoupper($key)),
                        'value' => $value,
                    );
                }
            }

            $data_to_export[] = array(
                'group_id' => 'brst_submission',
                'group_label' => __('Business Risk Stress Test Submissions', 'brst-engine'),
                'item_id' => 'submission-' . $submission->id,
                'data' => $data,
            );
        }

        // Check for more data
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $email_table WHERE email = %s",
            $email
        ));

        $done = ($offset + $items_per_page) >= $total;

        return array(
            'data' => $data_to_export,
            'done' => $done,
        );
    }

    /**
     * Erase personal data
     */
    public function erase_personal_data($email, $page = 1) {
        global $wpdb;

        $items_removed = 0;
        $items_retained = 0;
        $messages = array();

        // Find submissions by email
        $email_table = BRST_Database::get_table_name('email_captures');

        $email_records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $email_table WHERE email = %s",
            $email
        ));

        foreach ($email_records as $record) {
            $submission_id = $record->submission_id;

            // Check if there's a payment record (retain for legal reasons)
            $payments_table = BRST_Database::get_table_name('payments');
            $has_payment = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $payments_table WHERE submission_id = %d AND status = 'success'",
                $submission_id
            ));

            if ($has_payment) {
                // Anonymize instead of delete
                $this->anonymize_submission($submission_id);
                $items_retained++;
                $messages[] = sprintf(
                    __('Submission %d was anonymized (payment records retained for legal compliance).', 'brst-engine'),
                    $submission_id
                );
            } else {
                // Full deletion
                $this->delete_submission($submission_id);
                $items_removed++;
            }
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => true,
        );
    }

    /**
     * Anonymize a submission
     */
    private function anonymize_submission($submission_id) {
        global $wpdb;

        // Anonymize email
        $email_table = BRST_Database::get_table_name('email_captures');
        $wpdb->update(
            $email_table,
            array(
                'email' => 'anonymized@deleted.local',
                'marketing_consent' => 0,
            ),
            array('submission_id' => $submission_id)
        );

        // Clear IP address from submissions
        $submissions_table = BRST_Database::get_table_name('submissions');
        $wpdb->update(
            $submissions_table,
            array('ip_address' => '0.0.0.0'),
            array('id' => $submission_id)
        );

        // Clear IP from activity log
        $log_table = BRST_Database::get_table_name('activity_log');
        $wpdb->update(
            $log_table,
            array('ip_address' => '0.0.0.0'),
            array('submission_id' => $submission_id)
        );

        // Delete feedback comments (but keep rating for statistics)
        $feedback_table = BRST_Database::get_table_name('feedback');
        $wpdb->update(
            $feedback_table,
            array('question_2_response' => ''),
            array('submission_id' => $submission_id)
        );

        // Delete generated reports
        $reports_table = BRST_Database::get_table_name('reports');
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path FROM $reports_table WHERE submission_id = %d",
            $submission_id
        ));

        if ($report && !empty($report->file_path) && file_exists($report->file_path)) {
            unlink($report->file_path);
        }

        $wpdb->update(
            $reports_table,
            array(
                'file_path' => '',
                'file_url' => '',
            ),
            array('submission_id' => $submission_id)
        );
    }

    /**
     * Delete a submission completely
     */
    private function delete_submission($submission_id) {
        global $wpdb;

        $tables = array(
            'email_captures',
            'feedback',
            'reports',
            'activity_log',
            'payments',
            'submissions',
        );

        // Delete report file
        $reports_table = BRST_Database::get_table_name('reports');
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path FROM $reports_table WHERE submission_id = %d",
            $submission_id
        ));

        if ($report && !empty($report->file_path) && file_exists($report->file_path)) {
            unlink($report->file_path);
        }

        // Delete from all tables
        foreach ($tables as $table) {
            $table_name = BRST_Database::get_table_name($table);
            $column = ($table === 'submissions') ? 'id' : 'submission_id';
            $wpdb->delete($table_name, array($column => $submission_id));
        }
    }

    /**
     * Save email with consent
     */
    public function save_email_capture($submission_id, $email, $marketing_consent = false) {
        global $wpdb;
        $table = BRST_Database::get_table_name('email_captures');

        $data = array(
            'submission_id' => intval($submission_id),
            'email' => sanitize_email($email),
            'marketing_consent' => $marketing_consent ? 1 : 0,
            'consent_timestamp' => $marketing_consent ? current_time('mysql') : null,
        );

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE submission_id = %d",
            $submission_id
        ));

        if ($existing) {
            $wpdb->update($table, $data, array('id' => $existing));
            return $existing;
        }

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Get email capture for submission
     */
    public function get_email_capture($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('email_captures');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE submission_id = %d",
            $submission_id
        ));
    }

    /**
     * Update marketing consent
     */
    public function update_marketing_consent($submission_id, $consent) {
        global $wpdb;
        $table = BRST_Database::get_table_name('email_captures');

        return $wpdb->update(
            $table,
            array(
                'marketing_consent' => $consent ? 1 : 0,
                'consent_timestamp' => $consent ? current_time('mysql') : null,
            ),
            array('submission_id' => $submission_id)
        );
    }
}
