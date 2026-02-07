<?php
/**
 * Database Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for creating and managing plugin tables
 */
class BRST_Database {

    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Forms table
        $table_forms = $wpdb->prefix . 'brst_forms';
        $sql_forms = "CREATE TABLE $table_forms (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            form_schema longtext NOT NULL,
            settings longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_forms);

        // Submissions table
        $table_submissions = $wpdb->prefix . 'brst_submissions';
        $sql_submissions = "CREATE TABLE $table_submissions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id bigint(20) UNSIGNED NOT NULL,
            session_id varchar(64) NOT NULL,
            user_name varchar(255),
            user_email varchar(255),
            company_name varchar(255),
            responses longtext NOT NULL,
            scores longtext,
            category_scores longtext,
            primary_profile varchar(100),
            secondary_profile varchar(100),
            interaction_applicable tinyint(1) DEFAULT 0,
            q21_response varchar(10),
            alignment_status varchar(20),
            mini_report_shown tinyint(1) DEFAULT 0,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY session_id (session_id),
            KEY primary_profile (primary_profile),
            KEY user_email (user_email),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_submissions);

        // Payments table
        $table_payments = $wpdb->prefix . 'brst_payments';
        $sql_payments = "CREATE TABLE $table_payments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) UNSIGNED NOT NULL,
            gateway varchar(50) NOT NULL,
            transaction_id varchar(255),
            reference varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'NGN',
            status varchar(20) DEFAULT 'pending',
            gateway_response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY reference (reference),
            KEY submission_id (submission_id),
            KEY status (status),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";
        dbDelta($sql_payments);

        // Email captures table
        $table_emails = $wpdb->prefix . 'brst_email_captures';
        $sql_emails = "CREATE TABLE $table_emails (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) UNSIGNED NOT NULL,
            payment_id bigint(20) UNSIGNED,
            name varchar(255),
            email varchar(255) NOT NULL,
            marketing_consent tinyint(1) DEFAULT 0,
            consent_timestamp datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta($sql_emails);

        // Reports table
        $table_reports = $wpdb->prefix . 'brst_reports';
        $sql_reports = "CREATE TABLE $table_reports (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) UNSIGNED NOT NULL,
            payment_id bigint(20) UNSIGNED,
            report_type varchar(50) NOT NULL,
            template_id varchar(100),
            file_path text,
            file_url text,
            sent_at datetime,
            email_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY email_status (email_status)
        ) $charset_collate;";
        dbDelta($sql_reports);

        // Feedback table
        $table_feedback = $wpdb->prefix . 'brst_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) UNSIGNED NOT NULL,
            report_id bigint(20) UNSIGNED,
            question_1_response text,
            question_2_response text,
            reminder_sent tinyint(1) DEFAULT 0,
            reminder_sent_at datetime,
            submitted_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY reminder_sent (reminder_sent)
        ) $charset_collate;";
        dbDelta($sql_feedback);

        // Activity log table
        $table_logs = $wpdb->prefix . 'brst_activity_log';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) UNSIGNED,
            action varchar(100) NOT NULL,
            details longtext,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_logs);

        // Report templates table
        $table_templates = $wpdb->prefix . 'brst_report_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_key varchar(100) NOT NULL,
            template_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            variables longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_key (template_key),
            KEY template_type (template_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_templates);

        // Insert default templates
        self::insert_default_templates();

        // Update database version
        update_option('brst_db_version', BRST_DB_VERSION);
    }

    /**
     * Insert default report templates
     */
    private static function insert_default_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'brst_report_templates';

        // Check if templates already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }

        // Mini report templates
        $mini_templates = self::get_mini_report_templates();
        foreach ($mini_templates as $template) {
            $wpdb->insert($table, $template);
        }

        // Full report templates
        $full_templates = self::get_full_report_templates();
        foreach ($full_templates as $template) {
            $wpdb->insert($table, $template);
        }

        // Email templates
        $email_templates = self::get_email_templates();
        foreach ($email_templates as $template) {
            $wpdb->insert($table, $template);
        }
    }

    /**
     * Get mini report templates
     */
    private static function get_mini_report_templates() {
        $profiles = array(
            'cash_tight' => 'Cash-Tight Operator',
            'revenue_concentrated' => 'Revenue-Concentrated Builder',
            'cost_locked' => 'Cost-Locked Business',
            'owner_dependent' => 'Owner-Dependent Engine',
            'externally_exposed' => 'Externally Exposed Builder',
        );

        $templates = array();

        // Add "All YES" template (no risk detected)
        $templates[] = array(
            'template_key' => 'mini_all_yes',
            'template_type' => 'mini_report',
            'title' => 'All YES - Strong Foundations',
            'content' => json_encode(array(
                'profile_name' => 'Strong Foundations',
                'alignment' => 'excellent',
                'summary' => '<p>Your responses indicate a very high level of coverage across the areas assessed.</p><p>Based on how you answered, the business appears to be operating with strong foundations in cash flow management, revenue diversity, cost structure flexibility, operational resilience, and external risk protection.</p><p>This is a positive signal that your business has the right infrastructure and practices in place to navigate most common stress points.</p><p>If anything changes or you\'d like a deeper review in the future, feel free to return.</p>',
                'cta_text' => '',
                'hide_cta' => 1,
            )),
            'variables' => json_encode(array()),
            'status' => 'active',
        );

        foreach ($profiles as $key => $name) {
            // Aligned version
            $templates[] = array(
                'template_key' => "mini_{$key}_aligned",
                'template_type' => 'mini_report',
                'title' => "{$name} - Aligned",
                'content' => json_encode(array(
                    'profile_name' => $name,
                    'alignment' => 'aligned',
                    'summary' => "Your business profile is {$name}. Your current focus aligns with your primary business risk profile. This alignment suggests you're addressing the right areas, but there may be deeper insights in your Full Report.",
                    'cta_text' => 'Unlock Full Report',
                )),
                'variables' => json_encode(array('profile_name', 'score_percentage')),
                'status' => 'active',
            );

            // Not Aligned version
            $templates[] = array(
                'template_key' => "mini_{$key}_not_aligned",
                'template_type' => 'mini_report',
                'title' => "{$name} - Not Aligned",
                'content' => json_encode(array(
                    'profile_name' => $name,
                    'alignment' => 'not_aligned',
                    'summary' => "Your business profile is {$name}. Interestingly, your current focus doesn't align with your primary business risk profile. This mismatch could indicate opportunities or blind spots worth exploring in your Full Report.",
                    'cta_text' => 'Unlock Full Report',
                )),
                'variables' => json_encode(array('profile_name', 'score_percentage')),
                'status' => 'active',
            );
        }

        return $templates;
    }

    /**
     * Get full report templates
     */
    private static function get_full_report_templates() {
        $profiles = array(
            'cash_tight' => array(
                'name' => 'Cash-Tight Operator',
                'description' => 'Your business shows characteristics of cash flow management challenges.',
            ),
            'revenue_concentrated' => array(
                'name' => 'Revenue-Concentrated Builder',
                'description' => 'Your business relies heavily on concentrated revenue sources.',
            ),
            'cost_locked' => array(
                'name' => 'Cost-Locked Business',
                'description' => 'Your business has significant fixed cost structures.',
            ),
            'owner_dependent' => array(
                'name' => 'Owner-Dependent Engine',
                'description' => 'Your business heavily depends on owner involvement.',
            ),
            'externally_exposed' => array(
                'name' => 'Externally Exposed Builder',
                'description' => 'Your business is exposed to external market factors.',
            ),
        );

        $templates = array();

        // Standalone templates
        foreach ($profiles as $key => $data) {
            $templates[] = array(
                'template_key' => "full_{$key}_standalone",
                'template_type' => 'full_report',
                'title' => "{$data['name']} - Standalone Report",
                'content' => json_encode(array(
                    'profile_name' => $data['name'],
                    'description' => $data['description'],
                    'report_type' => 'standalone',
                    'sections' => array(
                        'executive_summary' => 'Based on your responses, your business primarily exhibits characteristics of a ' . $data['name'] . '. ' . $data['description'],
                        'risk_analysis' => 'Detailed analysis of your business risks based on the questionnaire responses.',
                        'category_breakdown' => 'Your scores across all five business risk categories.',
                        'recommendations' => 'Strategic recommendations based on your business profile.',
                        'action_items' => 'Immediate action items to address identified risks.',
                    ),
                )),
                'variables' => json_encode(array('profile_name', 'scores', 'percentages', 'category_scores')),
                'status' => 'active',
            );
        }

        // Interaction templates - predefined pairs
        $interactions = array(
            'cash_tight' => 'revenue_concentrated',
            'revenue_concentrated' => 'cost_locked',
            'cost_locked' => 'owner_dependent',
            'owner_dependent' => 'externally_exposed',
            'externally_exposed' => 'cash_tight',
        );

        foreach ($interactions as $primary => $secondary) {
            $primary_name = $profiles[$primary]['name'];
            $secondary_name = $profiles[$secondary]['name'];

            $templates[] = array(
                'template_key' => "full_{$primary}_{$secondary}_interaction",
                'template_type' => 'full_report',
                'title' => "{$primary_name} + {$secondary_name} Interaction Report",
                'content' => json_encode(array(
                    'primary_profile' => $primary_name,
                    'secondary_profile' => $secondary_name,
                    'report_type' => 'interaction',
                    'sections' => array(
                        'executive_summary' => "Your business exhibits a primary profile of {$primary_name} with significant secondary characteristics of {$secondary_name}. This interaction creates unique challenges and opportunities.",
                        'primary_analysis' => "Analysis of your primary profile: {$primary_name}",
                        'secondary_analysis' => "Analysis of your secondary profile: {$secondary_name}",
                        'interaction_effects' => "How these two profiles interact and compound risks.",
                        'category_breakdown' => 'Your scores across all five business risk categories.',
                        'recommendations' => 'Strategic recommendations addressing the interaction between profiles.',
                        'action_items' => 'Priority action items considering both risk profiles.',
                    ),
                )),
                'variables' => json_encode(array('primary_profile', 'secondary_profile', 'scores', 'percentages', 'category_scores')),
                'status' => 'active',
            );
        }

        return $templates;
    }

    /**
     * Get email templates
     */
    private static function get_email_templates() {
        return array(
            array(
                'template_key' => 'email_report_delivery',
                'template_type' => 'email',
                'title' => 'Report Delivery Email',
                'content' => json_encode(array(
                    'subject' => 'Your Business Risk Stress Test Report is Ready',
                    'body' => '<h2>Your Full Report is Ready</h2>
                        <p>Thank you for completing the Business Risk Stress Test.</p>
                        <p>Your personalized report is attached to this email as a PDF document.</p>
                        <h3>Quick Summary</h3>
                        <p>Your primary business profile: <strong>{{profile_name}}</strong></p>
                        <p>Please review your report carefully and consider the recommendations provided.</p>
                        <h3>We Value Your Feedback</h3>
                        <p>After reviewing your report, please take a moment to provide feedback:</p>
                        <p><a href="{{feedback_url}}">Share Your Feedback</a></p>
                        <p>Your insights help us improve our diagnostic tools.</p>',
                )),
                'variables' => json_encode(array('profile_name', 'feedback_url', 'report_url')),
                'status' => 'active',
            ),
            array(
                'template_key' => 'email_feedback_reminder',
                'template_type' => 'email',
                'title' => 'Feedback Reminder Email',
                'content' => json_encode(array(
                    'subject' => 'Quick Feedback Request - Business Risk Stress Test',
                    'body' => '<h2>We\'d Love Your Feedback</h2>
                        <p>You recently received your Business Risk Stress Test report.</p>
                        <p>We\'d appreciate if you could take 2 minutes to answer a couple of quick questions about your experience.</p>
                        <p><a href="{{feedback_url}}">Share Your Feedback</a></p>
                        <p>Your feedback helps us improve our diagnostic tools for business owners like you.</p>
                        <p>Thank you for your time!</p>',
                )),
                'variables' => json_encode(array('feedback_url')),
                'status' => 'active',
            ),
        );
    }

    /**
     * Check and upgrade database if needed
     */
    public static function maybe_upgrade() {
        $current_version = get_option('brst_db_version', '1.0.0');

        // Version 1.0.1: Add user_name, user_email, company_name to submissions
        if (version_compare($current_version, '1.0.1', '<')) {
            self::upgrade_to_101();
            update_option('brst_db_version', '1.0.1');
        }

        // Version 1.0.2: Add "All YES" template
        if (version_compare($current_version, '1.0.2', '<')) {
            self::upgrade_to_102();
            update_option('brst_db_version', '1.0.2');
        }
    }

    /**
     * Upgrade to version 1.0.2 - Add "All YES" template
     */
    private static function upgrade_to_102() {
        global $wpdb;
        $table = $wpdb->prefix . 'brst_report_templates';

        // Check if "All YES" template already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE template_key = %s", 'mini_all_yes')
        );

        if (!$exists) {
            $wpdb->insert($table, array(
                'template_key' => 'mini_all_yes',
                'template_type' => 'mini_report',
                'title' => 'All YES - Strong Foundations',
                'content' => json_encode(array(
                    'profile_name' => 'Strong Foundations',
                    'alignment' => 'excellent',
                    'summary' => '<p>Your responses indicate a very high level of coverage across the areas assessed.</p><p>Based on how you answered, the business appears to be operating with strong foundations in cash flow management, revenue diversity, cost structure flexibility, operational resilience, and external risk protection.</p><p>This is a positive signal that your business has the right infrastructure and practices in place to navigate most common stress points.</p><p>If anything changes or you\'d like a deeper review in the future, feel free to return.</p>',
                    'cta_text' => '',
                    'hide_cta' => 1,
                )),
                'variables' => json_encode(array()),
                'status' => 'active',
            ));
        }
    }

    /**
     * Upgrade to version 1.0.1 - Add user info columns
     */
    private static function upgrade_to_101() {
        global $wpdb;

        // Add columns to submissions table
        $table_submissions = $wpdb->prefix . 'brst_submissions';

        // Check if columns exist before adding
        $columns = $wpdb->get_col("DESCRIBE $table_submissions");

        if (!in_array('user_name', $columns)) {
            $wpdb->query("ALTER TABLE $table_submissions ADD COLUMN user_name varchar(255) AFTER session_id");
        }

        if (!in_array('user_email', $columns)) {
            $wpdb->query("ALTER TABLE $table_submissions ADD COLUMN user_email varchar(255) AFTER user_name");
        }

        if (!in_array('company_name', $columns)) {
            $wpdb->query("ALTER TABLE $table_submissions ADD COLUMN company_name varchar(255) AFTER user_email");
        }

        // Add index on user_email if not exists
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_submissions WHERE Key_name = 'user_email'");
        if (empty($indexes)) {
            $wpdb->query("ALTER TABLE $table_submissions ADD INDEX user_email (user_email)");
        }

        // Add name column to email_captures table
        $table_emails = $wpdb->prefix . 'brst_email_captures';
        $email_columns = $wpdb->get_col("DESCRIBE $table_emails");

        if (!in_array('name', $email_columns)) {
            $wpdb->query("ALTER TABLE $table_emails ADD COLUMN name varchar(255) AFTER payment_id");
        }
    }

    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'brst_' . $table;
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            'brst_forms',
            'brst_submissions',
            'brst_payments',
            'brst_email_captures',
            'brst_reports',
            'brst_feedback',
            'brst_activity_log',
            'brst_report_templates',
        );

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }

        delete_option('brst_db_version');
    }
}
