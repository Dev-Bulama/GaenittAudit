<?php
/**
 * Admin Class
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for managing admin interface
 */
class BRST_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Business Risk Stress Test', 'brst-engine'),
            __('Risk Stress Test', 'brst-engine'),
            'manage_options',
            'brst-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-chart-area',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'brst-dashboard',
            __('Dashboard', 'brst-engine'),
            __('Dashboard', 'brst-engine'),
            'manage_options',
            'brst-dashboard',
            array($this, 'render_dashboard')
        );

        // Submissions submenu
        add_submenu_page(
            'brst-dashboard',
            __('Submissions', 'brst-engine'),
            __('Submissions', 'brst-engine'),
            'manage_options',
            'brst-submissions',
            array($this, 'render_submissions')
        );

        // Reports submenu
        add_submenu_page(
            'brst-dashboard',
            __('Reports', 'brst-engine'),
            __('Reports', 'brst-engine'),
            'manage_options',
            'brst-reports',
            array($this, 'render_reports')
        );

        // Templates submenu
        add_submenu_page(
            'brst-dashboard',
            __('Templates', 'brst-engine'),
            __('Templates', 'brst-engine'),
            'manage_options',
            'brst-templates',
            array($this, 'render_templates')
        );

        // Settings submenu
        add_submenu_page(
            'brst-dashboard',
            __('Settings', 'brst-engine'),
            __('Settings', 'brst-engine'),
            'manage_options',
            'brst-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Payment settings
        register_setting('brst_payment_settings', 'brst_payment_gateway');
        register_setting('brst_payment_settings', 'brst_payment_amount');
        register_setting('brst_payment_settings', 'brst_payment_currency');
        register_setting('brst_payment_settings', 'brst_paystack_public_key');
        register_setting('brst_payment_settings', 'brst_paystack_secret_key');
        register_setting('brst_payment_settings', 'brst_stripe_public_key');
        register_setting('brst_payment_settings', 'brst_stripe_secret_key');

        // Email settings
        register_setting('brst_email_settings', 'brst_sender_email');
        register_setting('brst_email_settings', 'brst_sender_name');
        register_setting('brst_email_settings', 'brst_feedback_reminder_hours');

        // General settings
        register_setting('brst_general_settings', 'brst_terms_page');
        register_setting('brst_general_settings', 'brst_privacy_page');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Business Risk Stress Test Dashboard', 'brst-engine'); ?></h1>

            <div class="brst-dashboard-stats">
                <div class="brst-stat-card">
                    <div class="brst-stat-icon">📋</div>
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['total_submissions']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Total Submissions', 'brst-engine'); ?></span>
                    </div>
                </div>

                <div class="brst-stat-card">
                    <div class="brst-stat-icon">💳</div>
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['total_payments']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Successful Payments', 'brst-engine'); ?></span>
                    </div>
                </div>

                <div class="brst-stat-card">
                    <div class="brst-stat-icon">📧</div>
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['reports_sent']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Reports Sent', 'brst-engine'); ?></span>
                    </div>
                </div>

                <div class="brst-stat-card">
                    <div class="brst-stat-icon">⭐</div>
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['avg_rating']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Avg. Feedback Rating', 'brst-engine'); ?></span>
                    </div>
                </div>
            </div>

            <div class="brst-dashboard-sections">
                <div class="brst-dashboard-section">
                    <h2><?php esc_html_e('Profile Distribution', 'brst-engine'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Profile', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Count', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Percentage', 'brst-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['profile_distribution'] as $profile): ?>
                            <tr>
                                <td><?php echo esc_html($profile->primary_profile); ?></td>
                                <td><?php echo esc_html($profile->count); ?></td>
                                <td><?php echo esc_html(round($profile->count / max($stats['total_submissions'], 1) * 100, 1)); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="brst-dashboard-section">
                    <h2><?php esc_html_e('Recent Submissions', 'brst-engine'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Profile', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Date', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['recent_submissions'] as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html($submission->primary_profile); ?></td>
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($submission->created_at))); ?></td>
                                <td>
                                    <?php if ($submission->mini_report_shown): ?>
                                        <span class="brst-status brst-status-complete"><?php esc_html_e('Complete', 'brst-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="brst-status brst-status-pending"><?php esc_html_e('Pending', 'brst-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=brst-submissions')); ?>"><?php esc_html_e('View All Submissions', 'brst-engine'); ?> &rarr;</a></p>
                </div>
            </div>

            <div class="brst-dashboard-shortcodes">
                <h2><?php esc_html_e('Shortcodes', 'brst-engine'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Shortcode', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Description', 'brst-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[brst_questionnaire]</code></td>
                            <td><?php esc_html_e('Display the main questionnaire form', 'brst-engine'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[brst_feedback]</code></td>
                            <td><?php esc_html_e('Display the feedback form (requires URL parameters)', 'brst-engine'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[brst_results submission_id="123"]</code></td>
                            <td><?php esc_html_e('Display results for a specific submission', 'brst-engine'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;

        $submissions_table = BRST_Database::get_table_name('submissions');
        $payments_table = BRST_Database::get_table_name('payments');
        $reports_table = BRST_Database::get_table_name('reports');
        $feedback_table = BRST_Database::get_table_name('feedback');

        return array(
            'total_submissions' => $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table"),
            'total_payments' => $wpdb->get_var("SELECT COUNT(*) FROM $payments_table WHERE status = 'success'"),
            'reports_sent' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE email_status = 'sent'"),
            'avg_rating' => round($wpdb->get_var("SELECT AVG(CAST(question_1_response AS DECIMAL)) FROM $feedback_table WHERE submitted_at IS NOT NULL") ?: 0, 1),
            'profile_distribution' => $wpdb->get_results("SELECT primary_profile, COUNT(*) as count FROM $submissions_table GROUP BY primary_profile ORDER BY count DESC"),
            'recent_submissions' => $wpdb->get_results("SELECT * FROM $submissions_table ORDER BY created_at DESC LIMIT 5"),
        );
    }

    /**
     * Render submissions page
     */
    public function render_submissions() {
        $submissions_handler = new BRST_Admin_Submissions();
        $submissions_handler->render();
    }

    /**
     * Render reports page
     */
    public function render_reports() {
        $reports_handler = new BRST_Admin_Reports();
        $reports_handler->render();
    }

    /**
     * Render templates page
     */
    public function render_templates() {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');
        $templates = $wpdb->get_results("SELECT * FROM $table ORDER BY template_type, title");
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Report Templates', 'brst-engine'); ?></h1>

            <div class="brst-templates-list">
                <h2><?php esc_html_e('Mini Report Templates', 'brst-engine'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Template Key', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Title', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template):
                            if ($template->template_type !== 'mini_report') continue;
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($template->template_key); ?></code></td>
                            <td><?php echo esc_html($template->title); ?></td>
                            <td><?php echo esc_html($template->status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e('Full Report Templates', 'brst-engine'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Template Key', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Title', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template):
                            if ($template->template_type !== 'full_report') continue;
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($template->template_key); ?></code></td>
                            <td><?php echo esc_html($template->title); ?></td>
                            <td><?php echo esc_html($template->status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e('Email Templates', 'brst-engine'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Template Key', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Title', 'brst-engine'); ?></th>
                            <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template):
                            if ($template->template_type !== 'email') continue;
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($template->template_key); ?></code></td>
                            <td><?php echo esc_html($template->title); ?></td>
                            <td><?php echo esc_html($template->status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="brst-templates-note">
                <p><strong><?php esc_html_e('Note:', 'brst-engine'); ?></strong> <?php esc_html_e('All reports are generated using pre-written, pre-approved templates. No AI-generated content is used.', 'brst-engine'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        $settings_handler = new BRST_Admin_Settings();
        $settings_handler->render();
    }
}
