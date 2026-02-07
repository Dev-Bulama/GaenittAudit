<?php
/**
 * Email Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Engine class for sending emails
 */
class BRST_Email_Engine {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }

    /**
     * Set HTML content type for emails
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Get sender email
     */
    private function get_sender_email() {
        return get_option('brst_sender_email', get_option('admin_email'));
    }

    /**
     * Get sender name
     */
    private function get_sender_name() {
        return get_option('brst_sender_name', get_bloginfo('name'));
    }

    /**
     * Send report delivery email
     */
    public function send_report_email($submission_id, $email, $report_path) {
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            return new WP_Error('submission_not_found', __('Submission not found.', 'brst-engine'));
        }

        // Get template
        $template = $this->get_email_template('email_report_delivery');

        // Generate feedback URL
        $feedback_url = add_query_arg(array(
            'brst_feedback' => 1,
            'submission_id' => $submission_id,
            'token' => $this->generate_feedback_token($submission_id),
        ), home_url());

        // Get profile name
        $profile_engine = new BRST_Profile_Engine();
        $profile = $profile_engine->get_profile($submission->primary_profile);
        $profile_name = $profile ? $profile['name'] : $submission->primary_profile;

        // Replace variables
        $variables = array(
            '{{profile_name}}' => $profile_name,
            '{{feedback_url}}' => $feedback_url,
            '{{report_url}}' => '',
        );

        $subject = $this->replace_variables($template['subject'], $variables);
        $body = $this->replace_variables($template['body'], $variables);

        // Wrap in email template
        $html = $this->get_email_wrapper($body);

        // Prepare headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_sender_name() . ' <' . $this->get_sender_email() . '>',
        );

        // Prepare attachments - prioritize category PDF from admin uploads
        $attachments = array();

        // First, try to get category-specific PDF from admin-uploaded reports
        $category_report = $this->get_category_report_pdf($submission->primary_profile);
        if ($category_report && file_exists($category_report)) {
            $attachments[] = $category_report;
        } elseif (file_exists($report_path)) {
            // Fall back to generated report
            $attachments[] = $report_path;
        } else {
            // Try HTML version as last resort
            $html_path = str_replace('.pdf', '.html', $report_path);
            if (file_exists($html_path)) {
                $attachments[] = $html_path;
            }
        }

        // Send email
        $sent = wp_mail($email, $subject, $html, $headers, $attachments);

        if ($sent) {
            // Update report status
            $this->update_report_email_status($submission_id, 'sent');

            // Log activity
            $this->log_activity($submission_id, 'email_sent', array(
                'type' => 'report_delivery',
                'email' => $email,
            ));

            // Create feedback record for reminder tracking
            $this->create_feedback_record($submission_id);

            /**
             * Action: brst_report_email_sent
             * Fires when report email is sent
             */
            do_action('brst_report_email_sent', $submission_id, $email);
        }

        return $sent;
    }

    /**
     * Send feedback reminder email
     */
    public function send_feedback_reminder($submission_id, $email) {
        // Check if feedback already submitted
        if ($this->is_feedback_submitted($submission_id)) {
            return false;
        }

        // Check if reminder already sent
        if ($this->is_reminder_sent($submission_id)) {
            return false;
        }

        // Get template
        $template = $this->get_email_template('email_feedback_reminder');

        // Generate feedback URL
        $feedback_url = add_query_arg(array(
            'brst_feedback' => 1,
            'submission_id' => $submission_id,
            'token' => $this->generate_feedback_token($submission_id),
        ), home_url());

        // Replace variables
        $variables = array(
            '{{feedback_url}}' => $feedback_url,
        );

        $subject = $this->replace_variables($template['subject'], $variables);
        $body = $this->replace_variables($template['body'], $variables);

        // Wrap in email template
        $html = $this->get_email_wrapper($body);

        // Prepare headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_sender_name() . ' <' . $this->get_sender_email() . '>',
        );

        // Send email
        $sent = wp_mail($email, $subject, $html, $headers);

        if ($sent) {
            // Mark reminder as sent
            $this->mark_reminder_sent($submission_id);

            // Log activity
            $this->log_activity($submission_id, 'email_sent', array(
                'type' => 'feedback_reminder',
                'email' => $email,
            ));

            /**
             * Action: brst_feedback_reminder_sent
             * Fires when feedback reminder email is sent
             */
            do_action('brst_feedback_reminder_sent', $submission_id, $email);
        }

        return $sent;
    }

    /**
     * Send pending feedback reminders (called by cron)
     */
    public function send_pending_feedback_reminders() {
        global $wpdb;

        $feedback_table = BRST_Database::get_table_name('feedback');
        $email_table = BRST_Database::get_table_name('email_captures');
        $reports_table = BRST_Database::get_table_name('reports');

        $reminder_hours = intval(get_option('brst_feedback_reminder_hours', 48));
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$reminder_hours} hours"));

        // Find submissions that:
        // 1. Have a report sent
        // 2. Have not submitted feedback
        // 3. Have not received a reminder
        // 4. Report was sent more than X hours ago
        $query = "
            SELECT f.submission_id, e.email, r.sent_at
            FROM $feedback_table f
            INNER JOIN $email_table e ON f.submission_id = e.submission_id
            INNER JOIN $reports_table r ON f.submission_id = r.submission_id
            WHERE f.submitted_at IS NULL
            AND f.reminder_sent = 0
            AND r.sent_at IS NOT NULL
            AND r.sent_at <= %s
        ";

        $pending = $wpdb->get_results($wpdb->prepare($query, $cutoff_time));

        foreach ($pending as $item) {
            $this->send_feedback_reminder($item->submission_id, $item->email);
        }

        return count($pending);
    }

    /**
     * Get email template
     */
    private function get_email_template($template_key) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE template_key = %s AND template_type = 'email' AND status = 'active'",
                $template_key
            )
        );

        if ($template) {
            return json_decode($template->content, true);
        }

        // Return defaults
        return $this->get_default_template($template_key);
    }

    /**
     * Get default email template
     */
    private function get_default_template($template_key) {
        $defaults = array(
            'email_report_delivery' => array(
                'subject' => __('Your Business Risk Stress Test Report is Ready', 'brst-engine'),
                'body' => '<h2>Your Full Report is Ready</h2>
                    <p>Thank you for completing the Business Risk Stress Test.</p>
                    <p>Your personalized report is attached to this email.</p>
                    <p>Your primary business profile: <strong>{{profile_name}}</strong></p>
                    <p>After reviewing your report, please share your feedback:</p>
                    <p><a href="{{feedback_url}}">Share Your Feedback</a></p>',
            ),
            'email_feedback_reminder' => array(
                'subject' => __('Quick Feedback Request - Business Risk Stress Test', 'brst-engine'),
                'body' => '<h2>We\'d Love Your Feedback</h2>
                    <p>You recently received your Business Risk Stress Test report.</p>
                    <p>Please take 2 minutes to share your feedback:</p>
                    <p><a href="{{feedback_url}}">Share Your Feedback</a></p>
                    <p>Thank you!</p>',
            ),
        );

        return $defaults[$template_key] ?? array('subject' => '', 'body' => '');
    }

    /**
     * Get email wrapper HTML
     */
    private function get_email_wrapper($content) {
        $site_name = get_bloginfo('name');
        $logo_url = get_option('brst_logo_url', '');

        // Build header with logo or site name
        $header_content = '';
        if (!empty($logo_url)) {
            $header_content = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-width: 200px; height: auto;">';
        } else {
            $header_content = '<h1 style="color: #2c3e50; margin: 0;">' . esc_html($site_name) . '</h1>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    ' . $header_content . '
                </div>
                <div style="background: white; padding: 30px; border-radius: 5px;">
                    ' . $content . '
                </div>
                <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #7f8c8d;">
                    <p>&copy; ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Get category report PDF path from admin uploads
     *
     * @param string $profile_key The profile/category key (e.g., 'cash_tight')
     * @return string|null Full path to PDF file or null if not found
     */
    private function get_category_report_pdf($profile_key) {
        $reports = get_option('brst_category_reports', array());

        // Try category-specific report first
        if (!empty($reports[$profile_key]['path']) && file_exists($reports[$profile_key]['path'])) {
            return $reports[$profile_key]['path'];
        }

        // Fall back to default report
        if (!empty($reports['_default']['path']) && file_exists($reports['_default']['path'])) {
            return $reports['_default']['path'];
        }

        return null;
    }

    /**
     * Replace variables in template
     */
    private function replace_variables($content, $variables) {
        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Generate feedback token
     */
    public function generate_feedback_token($submission_id) {
        return wp_hash($submission_id . 'brst_feedback' . wp_salt());
    }

    /**
     * Verify feedback token
     */
    public function verify_feedback_token($submission_id, $token) {
        return $token === $this->generate_feedback_token($submission_id);
    }

    /**
     * Get submission
     */
    private function get_submission($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id)
        );
    }

    /**
     * Update report email status
     */
    private function update_report_email_status($submission_id, $status) {
        global $wpdb;
        $table = BRST_Database::get_table_name('reports');

        return $wpdb->update(
            $table,
            array(
                'email_status' => $status,
                'sent_at' => current_time('mysql'),
            ),
            array('submission_id' => $submission_id)
        );
    }

    /**
     * Create feedback record
     */
    private function create_feedback_record($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        // Check if record exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE submission_id = %d", $submission_id)
        );

        if (!$exists) {
            $wpdb->insert($table, array(
                'submission_id' => $submission_id,
            ));
        }
    }

    /**
     * Check if feedback is submitted
     */
    private function is_feedback_submitted($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $submitted = $wpdb->get_var(
            $wpdb->prepare("SELECT submitted_at FROM $table WHERE submission_id = %d", $submission_id)
        );

        return !empty($submitted);
    }

    /**
     * Check if reminder is sent
     */
    private function is_reminder_sent($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT reminder_sent FROM $table WHERE submission_id = %d", $submission_id)
        );
    }

    /**
     * Mark reminder as sent
     */
    private function mark_reminder_sent($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        return $wpdb->update(
            $table,
            array(
                'reminder_sent' => 1,
                'reminder_sent_at' => current_time('mysql'),
            ),
            array('submission_id' => $submission_id)
        );
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
