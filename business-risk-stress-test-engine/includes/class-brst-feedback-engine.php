<?php
/**
 * Feedback Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feedback Engine class for handling user feedback
 */
class BRST_Feedback_Engine {

    /**
     * Feedback questions
     */
    private $questions = array(
        'question_1' => array(
            'text' => 'How useful did you find your Business Risk Stress Test report?',
            'type' => 'rating',
            'options' => array(
                '1' => 'Not useful at all',
                '2' => 'Slightly useful',
                '3' => 'Moderately useful',
                '4' => 'Very useful',
                '5' => 'Extremely useful',
            ),
        ),
        'question_2' => array(
            'text' => 'What could we improve about the report or your experience?',
            'type' => 'textarea',
            'placeholder' => 'Share your thoughts...',
        ),
    );

    /**
     * Constructor
     */
    public function __construct() {
        /**
         * Filter: brst_feedback_questions
         * Allows modification of feedback questions
         */
        $this->questions = apply_filters('brst_feedback_questions', $this->questions);
    }

    /**
     * Get feedback questions
     */
    public function get_questions() {
        return $this->questions;
    }

    /**
     * Render feedback form
     */
    public function render_form($submission_id, $token) {
        // Verify token
        $email_engine = new BRST_Email_Engine();
        if (!$email_engine->verify_feedback_token($submission_id, $token)) {
            return '<div class="brst-error">' . esc_html__('Invalid feedback link.', 'brst-engine') . '</div>';
        }

        // Check if already submitted
        if ($this->is_submitted($submission_id)) {
            return $this->render_thank_you();
        }

        ob_start();
        ?>
        <div class="brst-feedback-container">
            <div class="brst-feedback-header">
                <h2><?php esc_html_e('Share Your Feedback', 'brst-engine'); ?></h2>
                <p><?php esc_html_e('Your feedback helps us improve our diagnostic tools.', 'brst-engine'); ?></p>
            </div>

            <form id="brst-feedback-form" class="brst-feedback-form">
                <?php wp_nonce_field('brst_feedback_submit', 'brst_feedback_nonce'); ?>
                <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission_id); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <?php foreach ($this->questions as $key => $question): ?>
                    <div class="brst-feedback-question" data-question="<?php echo esc_attr($key); ?>">
                        <label class="brst-question-label">
                            <?php echo esc_html($question['text']); ?>
                        </label>

                        <?php if ($question['type'] === 'rating'): ?>
                            <div class="brst-rating-group">
                                <?php foreach ($question['options'] as $value => $label): ?>
                                    <label class="brst-rating-option">
                                        <input type="radio" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" required>
                                        <span class="brst-rating-value"><?php echo esc_html($value); ?></span>
                                        <span class="brst-rating-label"><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['type'] === 'textarea'): ?>
                            <textarea name="<?php echo esc_attr($key); ?>"
                                      class="brst-feedback-textarea"
                                      placeholder="<?php echo esc_attr($question['placeholder'] ?? ''); ?>"
                                      rows="4"></textarea>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="brst-feedback-submit">
                    <button type="submit" class="brst-btn brst-btn-primary">
                        <?php esc_html_e('Submit Feedback', 'brst-engine'); ?>
                    </button>
                </div>

                <div class="brst-feedback-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render thank you message
     */
    private function render_thank_you() {
        ob_start();
        ?>
        <div class="brst-feedback-thank-you">
            <div class="brst-thank-you-icon">&#10003;</div>
            <h2><?php esc_html_e('Thank You!', 'brst-engine'); ?></h2>
            <p><?php esc_html_e('Your feedback has been submitted. We appreciate you taking the time to help us improve.', 'brst-engine'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Process feedback submission
     */
    public function submit_feedback($data) {
        $submission_id = intval($data['submission_id'] ?? 0);
        $token = sanitize_text_field($data['token'] ?? '');

        // Verify token
        $email_engine = new BRST_Email_Engine();
        if (!$email_engine->verify_feedback_token($submission_id, $token)) {
            return new WP_Error('invalid_token', __('Invalid feedback link.', 'brst-engine'));
        }

        // Check if already submitted
        if ($this->is_submitted($submission_id)) {
            return new WP_Error('already_submitted', __('Feedback has already been submitted.', 'brst-engine'));
        }

        // Sanitize responses
        $question_1 = sanitize_text_field($data['question_1'] ?? '');
        $question_2 = sanitize_textarea_field($data['question_2'] ?? '');

        // Validate required fields
        if (empty($question_1)) {
            return new WP_Error('required_field', __('Please rate your experience.', 'brst-engine'));
        }

        // Save feedback
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $result = $wpdb->update(
            $table,
            array(
                'question_1_response' => $question_1,
                'question_2_response' => $question_2,
                'submitted_at' => current_time('mysql'),
            ),
            array('submission_id' => $submission_id)
        );

        if ($result === false) {
            // Try insert if update fails (record might not exist)
            $wpdb->insert($table, array(
                'submission_id' => $submission_id,
                'question_1_response' => $question_1,
                'question_2_response' => $question_2,
                'submitted_at' => current_time('mysql'),
            ));
        }

        // Log activity
        $this->log_activity($submission_id, 'feedback_submitted', array(
            'rating' => $question_1,
        ));

        /**
         * Action: brst_feedback_submitted
         * Fires when feedback is submitted
         */
        do_action('brst_feedback_submitted', $submission_id, array(
            'question_1' => $question_1,
            'question_2' => $question_2,
        ));

        return true;
    }

    /**
     * Check if feedback is submitted
     */
    public function is_submitted($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $submitted = $wpdb->get_var(
            $wpdb->prepare("SELECT submitted_at FROM $table WHERE submission_id = %d", $submission_id)
        );

        return !empty($submitted);
    }

    /**
     * Get feedback for a submission
     */
    public function get_feedback($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE submission_id = %d", $submission_id)
        );
    }

    /**
     * Get all feedback with pagination
     */
    public function get_all_feedback($args = array()) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $defaults = array(
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
            'submitted_only' => true,
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        if ($args['submitted_only']) {
            $where .= ' AND submitted_at IS NOT NULL';
        }

        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get feedback statistics
     */
    public function get_statistics() {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        // Total feedback count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE submitted_at IS NOT NULL");

        // Average rating
        $avg_rating = $wpdb->get_var("SELECT AVG(CAST(question_1_response AS DECIMAL)) FROM $table WHERE submitted_at IS NOT NULL AND question_1_response != ''");

        // Rating distribution
        $distribution = $wpdb->get_results(
            "SELECT question_1_response as rating, COUNT(*) as count
             FROM $table
             WHERE submitted_at IS NOT NULL AND question_1_response != ''
             GROUP BY question_1_response
             ORDER BY question_1_response ASC"
        );

        // Pending reminders
        $pending_reminders = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE submitted_at IS NULL AND reminder_sent = 0");

        return array(
            'total_feedback' => intval($total),
            'average_rating' => round(floatval($avg_rating), 2),
            'rating_distribution' => $distribution,
            'pending_reminders' => intval($pending_reminders),
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
