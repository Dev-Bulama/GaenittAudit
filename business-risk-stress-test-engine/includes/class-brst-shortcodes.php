<?php
/**
 * Shortcodes
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes class for registering and rendering shortcodes
 */
class BRST_Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        // Main questionnaire form
        add_shortcode('brst_questionnaire', array($this, 'render_questionnaire'));

        // Feedback form
        add_shortcode('brst_feedback', array($this, 'render_feedback'));

        // Email capture form (standalone)
        add_shortcode('brst_email_capture', array($this, 'render_email_capture'));

        // Results display
        add_shortcode('brst_results', array($this, 'render_results'));

        // Handle URL parameters for feedback
        add_action('template_redirect', array($this, 'handle_feedback_page'));
        add_action('template_redirect', array($this, 'handle_payment_callback'));
    }

    /**
     * Render questionnaire shortcode
     * Usage: [brst_questionnaire]
     */
    public function render_questionnaire($atts) {
        $atts = shortcode_atts(array(
            'title' => '',
            'description' => '',
        ), $atts);

        $form_engine = new BRST_Form_Engine();
        $schema = $form_engine->get_default_questionnaire_schema();

        // Override title and description if provided
        if (!empty($atts['title'])) {
            $schema['title'] = $atts['title'];
        }
        if (!empty($atts['description'])) {
            $schema['description'] = $atts['description'];
        }

        return $form_engine->render_form($schema);
    }

    /**
     * Render feedback shortcode
     * Usage: [brst_feedback]
     */
    public function render_feedback($atts) {
        $submission_id = intval($_GET['submission_id'] ?? 0);
        $token = sanitize_text_field($_GET['token'] ?? '');

        if (!$submission_id || !$token) {
            return '<div class="brst-error">' . esc_html__('Invalid feedback link. Please use the link from your email.', 'brst-engine') . '</div>';
        }

        $feedback_engine = new BRST_Feedback_Engine();
        return $feedback_engine->render_form($submission_id, $token);
    }

    /**
     * Render email capture shortcode
     * Usage: [brst_email_capture submission_id="123"]
     */
    public function render_email_capture($atts) {
        $atts = shortcode_atts(array(
            'submission_id' => 0,
        ), $atts);

        $submission_id = intval($atts['submission_id']);

        if (!$submission_id) {
            $submission_id = intval($_GET['submission_id'] ?? 0);
        }

        if (!$submission_id) {
            return '';
        }

        ob_start();
        ?>
        <div class="brst-email-capture-container">
            <div class="brst-email-capture-header">
                <h2><?php esc_html_e('Where should we send your report?', 'brst-engine'); ?></h2>
                <p><?php esc_html_e('Enter your email address to receive your personalized PDF report.', 'brst-engine'); ?></p>
            </div>

            <form id="brst-email-capture-form" class="brst-email-capture-form">
                <?php wp_nonce_field('brst_nonce', 'brst_nonce'); ?>
                <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission_id); ?>">

                <div class="brst-field">
                    <label for="brst-email"><?php esc_html_e('Email Address', 'brst-engine'); ?> <span class="brst-required">*</span></label>
                    <input type="email" id="brst-email" name="email" required placeholder="<?php esc_attr_e('your@email.com', 'brst-engine'); ?>">
                </div>

                <div class="brst-field brst-consent-field">
                    <label class="brst-checkbox-single">
                        <input type="checkbox" name="marketing_consent" value="1">
                        <span><?php esc_html_e('I consent to receive marketing emails and updates about new products (optional)', 'brst-engine'); ?></span>
                    </label>
                </div>

                <button type="submit" class="brst-btn brst-btn-primary">
                    <?php esc_html_e('Send My Report', 'brst-engine'); ?>
                </button>

                <div class="brst-email-capture-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render results shortcode
     * Usage: [brst_results submission_id="123"]
     */
    public function render_results($atts) {
        $atts = shortcode_atts(array(
            'submission_id' => 0,
        ), $atts);

        $submission_id = intval($atts['submission_id']);

        if (!$submission_id) {
            $submission_id = intval($_GET['submission_id'] ?? 0);
        }

        if (!$submission_id) {
            return '<div class="brst-error">' . esc_html__('No results to display.', 'brst-engine') . '</div>';
        }

        // Get submission
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id));

        if (!$submission) {
            return '<div class="brst-error">' . esc_html__('Results not found.', 'brst-engine') . '</div>';
        }

        // Get profile data
        $profile_engine = new BRST_Profile_Engine();
        $scoring_engine = new BRST_Scoring_Engine();

        $category_scores = json_decode($submission->category_scores, true);
        $profile_analysis = $profile_engine->analyze_profiles($category_scores, $submission->q21_response);

        ob_start();
        ?>
        <div class="brst-results-container">
            <div class="brst-results-header">
                <h2><?php esc_html_e('Your Business Risk Profile', 'brst-engine'); ?></h2>
            </div>

            <div class="brst-primary-profile">
                <div class="brst-profile-badge">
                    <?php echo esc_html($profile_analysis['primary_profile']['data']['name']); ?>
                </div>
                <p class="brst-profile-description">
                    <?php echo esc_html($profile_analysis['primary_profile']['data']['description']); ?>
                </p>
            </div>

            <div class="brst-score-breakdown">
                <h3><?php esc_html_e('Category Scores', 'brst-engine'); ?></h3>
                <div class="brst-score-bars">
                    <?php foreach ($category_scores as $key => $data):
                        $risk_level = $scoring_engine->get_risk_level($data['percentage']);
                    ?>
                    <div class="brst-score-bar-item">
                        <div class="brst-score-bar-label">
                            <span class="brst-category-name"><?php echo esc_html($data['name']); ?></span>
                            <span class="brst-category-score"><?php echo esc_html($data['percentage']); ?>%</span>
                        </div>
                        <div class="brst-score-bar-track">
                            <div class="brst-score-bar-fill" style="width: <?php echo esc_attr($data['percentage']); ?>%; background-color: <?php echo esc_attr($risk_level['color']); ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="brst-alignment-section">
                <h3><?php esc_html_e('Focus Alignment', 'brst-engine'); ?></h3>
                <div class="brst-alignment-status <?php echo esc_attr($profile_analysis['alignment']['status']); ?>">
                    <?php if ($profile_analysis['alignment']['is_aligned']): ?>
                        <span class="brst-alignment-icon">&#10003;</span>
                        <span><?php esc_html_e('Your focus is aligned with your primary risk profile', 'brst-engine'); ?></span>
                    <?php else: ?>
                        <span class="brst-alignment-icon">&#8596;</span>
                        <span><?php esc_html_e('Your focus does not align with your primary risk profile', 'brst-engine'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle feedback page via URL parameter
     */
    public function handle_feedback_page() {
        if (isset($_GET['brst_feedback']) && $_GET['brst_feedback'] == 1) {
            // Add feedback form to page content via filter
            add_filter('the_content', array($this, 'inject_feedback_form'));
        }
    }

    /**
     * Inject feedback form into page content
     */
    public function inject_feedback_form($content) {
        if (!is_main_query() || !in_the_loop()) {
            return $content;
        }

        return $this->render_feedback(array());
    }

    /**
     * Handle payment callback
     */
    public function handle_payment_callback() {
        if (!isset($_GET['brst_payment_callback'])) {
            return;
        }

        $reference = sanitize_text_field($_GET['ref'] ?? '');
        $gateway = sanitize_text_field($_GET['gateway'] ?? 'paystack');

        if (empty($reference)) {
            return;
        }

        // Verify payment
        $payment_engine = new BRST_Payment_Engine();
        $payment = $payment_engine->get_payment_by_reference($reference);

        if (!$payment) {
            wp_die(__('Payment not found.', 'brst-engine'));
        }

        if ($payment->status !== 'success') {
            // Verify with gateway
            if ($gateway === 'paystack') {
                $verification = $payment_engine->verify_paystack_payment($reference);

                if (!is_wp_error($verification) && $verification['status'] === 'success') {
                    $payment_engine->process_successful_payment($payment->id, $verification);
                }
            }
        }

        // Redirect to email capture or success page
        $redirect_url = add_query_arg(array(
            'brst_email_capture' => 1,
            'submission_id' => $payment->submission_id,
            'payment_id' => $payment->id,
        ), home_url());

        wp_redirect($redirect_url);
        exit;
    }
}
