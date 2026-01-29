<?php
/**
 * Mini Report Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mini Report class for generating and displaying mini reports
 */
class BRST_Mini_Report {

    /**
     * Constructor
     */
    public function __construct() {
        // Mini reports are displayed immediately after submission
    }

    /**
     * Generate mini report data
     *
     * @param array $profile_analysis Profile analysis data from Profile Engine
     * @param array $scores_data Scores data from Scoring Engine
     * @return array Mini report data
     */
    public function generate($profile_analysis, $scores_data) {
        $primary_profile = $profile_analysis['primary_profile']['key'];
        $alignment = $profile_analysis['alignment'];
        $alignment_status = $alignment['status'];

        // Get template
        $template = $this->get_template($primary_profile, $alignment_status);

        // Build mini report
        $mini_report = array(
            'profile_key' => $primary_profile,
            'profile_name' => $profile_analysis['primary_profile']['data']['name'] ?? $primary_profile,
            'profile_short_name' => $profile_analysis['primary_profile']['data']['short_name'] ?? $primary_profile,
            'alignment_status' => $alignment_status,
            'is_aligned' => $alignment['is_aligned'],
            'template' => $template,
            'summary' => $this->generate_summary($template, $profile_analysis, $scores_data),
            'score_percentage' => $profile_analysis['primary_profile']['percentage'],
            'focus_area' => $profile_analysis['q21_focus'],
            'cta_text' => $template['cta_text'] ?? __('Unlock Full Report', 'brst-engine'),
            'category_scores' => $scores_data['category_scores'] ?? array(),
        );

        /**
         * Filter: brst_mini_report_data
         * Allows modification of mini report data before display
         */
        return apply_filters('brst_mini_report_data', $mini_report, $profile_analysis, $scores_data);
    }

    /**
     * Get mini report template from database
     */
    public function get_template($primary_profile, $alignment_status) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $template_key = "mini_{$primary_profile}_{$alignment_status}";

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE template_key = %s AND template_type = 'mini_report' AND status = 'active'",
                $template_key
            )
        );

        if ($template) {
            return json_decode($template->content, true);
        }

        // Return default template if not found
        return $this->get_default_template($primary_profile, $alignment_status);
    }

    /**
     * Get default template if not in database
     */
    private function get_default_template($primary_profile, $alignment_status) {
        $profile_engine = new BRST_Profile_Engine();
        $profile_data = $profile_engine->get_profile($primary_profile);
        $profile_name = $profile_data['name'] ?? $primary_profile;

        if ($alignment_status === 'aligned') {
            return array(
                'profile_name' => $profile_name,
                'alignment' => 'aligned',
                'summary' => sprintf(
                    __('Your business profile is %s. Your current focus aligns with your primary business risk profile. This alignment suggests you\'re addressing the right areas, but there may be deeper insights in your Full Report.', 'brst-engine'),
                    $profile_name
                ),
                'cta_text' => __('Unlock Full Report', 'brst-engine'),
            );
        }

        return array(
            'profile_name' => $profile_name,
            'alignment' => 'not_aligned',
            'summary' => sprintf(
                __('Your business profile is %s. Interestingly, your current focus doesn\'t align with your primary business risk profile. This mismatch could indicate opportunities or blind spots worth exploring in your Full Report.', 'brst-engine'),
                $profile_name
            ),
            'cta_text' => __('Unlock Full Report', 'brst-engine'),
        );
    }

    /**
     * Generate summary text with variables replaced
     */
    private function generate_summary($template, $profile_analysis, $scores_data) {
        $summary = $template['summary'] ?? '';

        // Replace variables
        $variables = array(
            '{{profile_name}}' => $profile_analysis['primary_profile']['data']['name'] ?? '',
            '{{score_percentage}}' => $profile_analysis['primary_profile']['percentage'] . '%',
            '{{focus_area}}' => $profile_analysis['q21_focus'],
            '{{alignment_status}}' => $profile_analysis['alignment']['status'],
        );

        return str_replace(array_keys($variables), array_values($variables), $summary);
    }

    /**
     * Render mini report HTML
     */
    public function render($mini_report, $submission_id) {
        /**
         * Action: brst_before_mini_report_render
         * Fires before mini report is rendered
         */
        do_action('brst_before_mini_report_render', $mini_report, $submission_id);

        ob_start();
        ?>
        <div class="brst-mini-report" data-submission-id="<?php echo esc_attr($submission_id); ?>">
            <div class="brst-mini-report-header">
                <h2 class="brst-mini-report-title">
                    <?php esc_html_e('Your Business Profile', 'brst-engine'); ?>
                </h2>
                <div class="brst-profile-badge <?php echo esc_attr($mini_report['profile_key']); ?>">
                    <?php echo esc_html($mini_report['profile_name']); ?>
                </div>
            </div>

            <div class="brst-mini-report-content">
                <div class="brst-alignment-indicator <?php echo esc_attr($mini_report['alignment_status']); ?>">
                    <?php if ($mini_report['is_aligned']): ?>
                        <span class="brst-alignment-icon">&#10003;</span>
                        <span class="brst-alignment-text"><?php esc_html_e('Focus Aligned', 'brst-engine'); ?></span>
                    <?php else: ?>
                        <span class="brst-alignment-icon">&#8596;</span>
                        <span class="brst-alignment-text"><?php esc_html_e('Focus Misaligned', 'brst-engine'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="brst-mini-report-summary">
                    <?php echo wp_kses_post($mini_report['summary']); ?>
                </div>

                <div class="brst-score-display">
                    <span class="brst-score-label"><?php esc_html_e('Risk Score:', 'brst-engine'); ?></span>
                    <span class="brst-score-value"><?php echo esc_html($mini_report['score_percentage']); ?>%</span>
                </div>

                <?php if (!empty($mini_report['category_scores'])): ?>
                <div class="brst-category-scores">
                    <h4 class="brst-category-scores-title"><?php esc_html_e('Category Performance', 'brst-engine'); ?></h4>
                    <?php foreach ($mini_report['category_scores'] as $category_key => $category_data): ?>
                    <div class="brst-category-score-item">
                        <div class="brst-category-score-header">
                            <span class="brst-category-name"><?php echo esc_html($category_data['name']); ?></span>
                            <span class="brst-category-percentage"><?php echo esc_html(round($category_data['percentage'])); ?>%</span>
                        </div>
                        <div class="brst-category-score-bar">
                            <div class="brst-category-score-fill <?php echo esc_attr($this->get_score_level_class($category_data['percentage'])); ?>" style="width: <?php echo esc_attr($category_data['percentage']); ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="brst-focus-display">
                    <span class="brst-focus-label"><?php esc_html_e('Your Current Focus:', 'brst-engine'); ?></span>
                    <span class="brst-focus-value"><?php echo esc_html($mini_report['focus_area']); ?></span>
                </div>
            </div>

            <div class="brst-mini-report-cta">
                <p class="brst-cta-description">
                    <?php esc_html_e('Get detailed insights, risk analysis, and actionable recommendations in your personalized Full Report.', 'brst-engine'); ?>
                </p>
                <button type="button" class="brst-btn brst-btn-primary brst-unlock-full-report" data-submission-id="<?php echo esc_attr($submission_id); ?>">
                    <?php echo esc_html($mini_report['cta_text']); ?>
                </button>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        /**
         * Filter: brst_mini_report_html
         * Allows modification of mini report HTML
         */
        return apply_filters('brst_mini_report_html', $html, $mini_report, $submission_id);
    }

    /**
     * Get mini report variants for a profile
     */
    public function get_variants($profile_key) {
        return array(
            array(
                'key' => "mini_{$profile_key}_aligned",
                'alignment' => 'aligned',
                'label' => __('Aligned', 'brst-engine'),
            ),
            array(
                'key' => "mini_{$profile_key}_not_aligned",
                'alignment' => 'not_aligned',
                'label' => __('Not Aligned', 'brst-engine'),
            ),
        );
    }

    /**
     * Update mini report template
     */
    public function update_template($template_key, $content) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        return $wpdb->update(
            $table,
            array(
                'content' => wp_json_encode($content),
                'updated_at' => current_time('mysql'),
            ),
            array('template_key' => $template_key),
            array('%s', '%s'),
            array('%s')
        );
    }

    /**
     * Mark mini report as shown
     */
    public function mark_as_shown($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');

        return $wpdb->update(
            $table,
            array('mini_report_shown' => 1),
            array('id' => $submission_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Get CSS class for score level
     *
     * @param float $percentage The score percentage
     * @return string CSS class name
     */
    private function get_score_level_class($percentage) {
        if ($percentage >= 70) {
            return 'brst-score-high';
        } elseif ($percentage >= 40) {
            return 'brst-score-medium';
        } else {
            return 'brst-score-low';
        }
    }
}
