<?php
/**
 * Plugin Name: Business Risk Stress Test Engine
 * Plugin URI: https://example.com/business-risk-stress-test
 * Description: An advanced form automation, scoring, conditional logic, payment-gated report delivery system for small business risk assessment.
 * Version: 1.0.0
 * Author: Gaenitt
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: brst-engine
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package BusinessRiskStressTest
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BRST_VERSION', '1.0.0');
define('BRST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRST_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BRST_DB_VERSION', '1.0.1');

/**
 * Main Plugin Class
 */
final class Business_Risk_Stress_Test_Engine {

    /**
     * Single instance of the class
     *
     * @var Business_Risk_Stress_Test_Engine
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $form_engine;
    public $scoring_engine;
    public $profile_engine;
    public $interaction_engine;
    public $report_engine;
    public $payment_engine;
    public $email_engine;
    public $feedback_engine;
    public $admin;

    /**
     * Get single instance
     *
     * @return Business_Risk_Stress_Test_Engine
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->check_requirements();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Check system requirements
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' .
                    esc_html__('Business Risk Stress Test Engine requires PHP 8.0 or higher.', 'brst-engine') .
                    '</p></div>';
            });
            return false;
        }
        return true;
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-database.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-form-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-scoring-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-profile-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-interaction-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-mini-report.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-payment-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-pdf-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-email-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-feedback-engine.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-gdpr.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-ajax-handler.php';
        require_once BRST_PLUGIN_DIR . 'includes/class-brst-shortcodes.php';

        // Admin includes
        if (is_admin()) {
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-submissions.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-settings.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-reports.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-templates.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-form-builder.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-documentation.php';
            require_once BRST_PLUGIN_DIR . 'admin/class-brst-admin-questions.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'), 10);
        add_action('init', array($this, 'init'), 0);

        // Load textdomain
        add_action('init', array($this, 'load_textdomain'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Schedule cron events
        add_action('brst_send_feedback_reminders', array($this, 'process_feedback_reminders'));

        // AJAX hooks are registered in BRST_Ajax_Handler
    }

    /**
     * Initialize components
     */
    public function init_components() {
        // Check for database upgrades
        BRST_Database::maybe_upgrade();

        $this->form_engine = new BRST_Form_Engine();
        $this->scoring_engine = new BRST_Scoring_Engine();
        $this->profile_engine = new BRST_Profile_Engine();
        $this->interaction_engine = new BRST_Interaction_Engine();
        $this->report_engine = new BRST_PDF_Engine();
        $this->payment_engine = new BRST_Payment_Engine();
        $this->email_engine = new BRST_Email_Engine();
        $this->feedback_engine = new BRST_Feedback_Engine();

        if (is_admin()) {
            $this->admin = new BRST_Admin();
        }

        // Initialize shortcodes
        new BRST_Shortcodes();

        // Initialize AJAX handler
        new BRST_Ajax_Handler();

        // Initialize GDPR handler
        new BRST_GDPR();

        /**
         * Action: brst_loaded
         * Fires when all plugin components are loaded
         */
        do_action('brst_loaded');
    }

    /**
     * Initialize plugin
     */
    public function init() {
        /**
         * Action: brst_init
         * Fires during WordPress init
         */
        do_action('brst_init');
    }

    /**
     * Load textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'brst-engine',
            false,
            dirname(BRST_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Main styles
        wp_enqueue_style(
            'brst-public',
            BRST_PLUGIN_URL . 'public/css/brst-public.css',
            array(),
            BRST_VERSION
        );

        // Load custom form styles if set
        $custom_styles = $this->get_custom_form_styles();
        if (!empty($custom_styles)) {
            wp_add_inline_style('brst-public', $custom_styles);
        }

        // Payment gateway scripts - load based on enabled gateways
        // Paystack
        if (get_option('brst_paystack_enabled') && get_option('brst_paystack_public_key')) {
            wp_enqueue_script(
                'paystack',
                'https://js.paystack.co/v1/inline.js',
                array(),
                null,
                true
            );
        }

        // Stripe
        if (get_option('brst_stripe_enabled') && get_option('brst_stripe_public_key')) {
            wp_enqueue_script(
                'stripe',
                'https://js.stripe.com/v3/',
                array(),
                null,
                true
            );
        }

        // PayPal
        if (get_option('brst_paypal_enabled') && get_option('brst_paypal_client_id')) {
            $paypal_client_id = get_option('brst_paypal_client_id');
            $paypal_currency = get_option('brst_payment_currency', 'USD');
            $paypal_sandbox = get_option('brst_paypal_sandbox', true);

            wp_enqueue_script(
                'paypal',
                'https://www.paypal.com/sdk/js?client-id=' . esc_attr($paypal_client_id) . '&currency=' . esc_attr($paypal_currency),
                array(),
                null,
                true
            );
        }

        // Main public JS
        wp_enqueue_script(
            'brst-public',
            BRST_PLUGIN_URL . 'public/js/brst-public.js',
            array('jquery'),
            BRST_VERSION,
            true
        );

        wp_localize_script('brst-public', 'brst_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brst_nonce'),
            'gateways' => array(
                'paystack_enabled' => (bool) get_option('brst_paystack_enabled'),
                'stripe_enabled' => (bool) get_option('brst_stripe_enabled'),
                'paypal_enabled' => (bool) get_option('brst_paypal_enabled'),
                'stripe_public_key' => get_option('brst_stripe_public_key', ''),
            ),
            'strings' => array(
                'loading' => __('Loading...', 'brst-engine'),
                'error' => __('An error occurred. Please try again.', 'brst-engine'),
                'required' => __('This field is required.', 'brst-engine'),
                'invalid_email' => __('Please enter a valid email address.', 'brst-engine'),
                'payment_processing' => __('Processing payment...', 'brst-engine'),
                'payment_success' => __('Payment successful!', 'brst-engine'),
                'payment_failed' => __('Payment failed. Please try again.', 'brst-engine'),
                'consent_required' => __('You must accept the Terms & Conditions and Privacy Policy to continue.', 'brst-engine'),
            ),
        ));
    }

    /**
     * Get custom form styles from settings
     */
    private function get_custom_form_styles() {
        $primary_color = get_option('brst_primary_color', '#3498db');
        $secondary_color = get_option('brst_secondary_color', '#2c3e50');
        $button_color = get_option('brst_button_color', '#3498db');
        $button_text_color = get_option('brst_button_text_color', '#ffffff');

        $css = "
            :root {
                --brst-primary: {$primary_color};
                --brst-secondary: {$secondary_color};
            }
            .brst-btn-primary {
                background: {$button_color};
                color: {$button_text_color};
            }
            .brst-btn-primary:hover {
                background: {$primary_color};
            }
            .brst-profile-badge,
            .brst-progress-fill {
                background: {$primary_color};
            }
        ";

        return $css;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'brst') === false && strpos($hook, 'business-risk') === false) {
            return;
        }

        wp_enqueue_style(
            'brst-admin',
            BRST_PLUGIN_URL . 'admin/css/brst-admin.css',
            array(),
            BRST_VERSION
        );

        wp_enqueue_script(
            'brst-admin',
            BRST_PLUGIN_URL . 'admin/js/brst-admin.js',
            array('jquery', 'jquery-ui-sortable', 'wp-util'),
            BRST_VERSION,
            true
        );

        wp_localize_script('brst-admin', 'brst_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brst_admin_nonce'),
        ));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        BRST_Database::create_tables();

        // Schedule cron events
        if (!wp_next_scheduled('brst_send_feedback_reminders')) {
            wp_schedule_event(time(), 'hourly', 'brst_send_feedback_reminders');
        }

        // Set default options
        $this->set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        /**
         * Action: brst_activated
         * Fires when plugin is activated
         */
        do_action('brst_activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('brst_send_feedback_reminders');

        // Flush rewrite rules
        flush_rewrite_rules();

        /**
         * Action: brst_deactivated
         * Fires when plugin is deactivated
         */
        do_action('brst_deactivated');
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            // Payment Gateway Keys
            'brst_paystack_public_key' => '',
            'brst_paystack_secret_key' => '',
            'brst_stripe_public_key' => '',
            'brst_stripe_secret_key' => '',
            'brst_paypal_client_id' => '',
            'brst_paypal_secret' => '',
            'brst_paypal_sandbox' => true,

            // Gateway Enable/Disable
            'brst_paystack_enabled' => false,
            'brst_stripe_enabled' => false,
            'brst_paypal_enabled' => false,
            'brst_default_gateway' => 'paystack',

            // Payment Settings
            'brst_payment_amount' => 5000,
            'brst_payment_currency' => 'NGN',

            // Page Settings
            'brst_terms_page' => '',
            'brst_privacy_page' => '',

            // Email Settings
            'brst_feedback_reminder_hours' => 48,
            'brst_sender_email' => get_option('admin_email'),
            'brst_sender_name' => get_bloginfo('name'),

            // Form Customization
            'brst_primary_color' => '#3498db',
            'brst_secondary_color' => '#2c3e50',
            'brst_button_color' => '#3498db',
            'brst_button_text_color' => '#ffffff',
            'brst_form_title' => __('Business Risk Stress Test', 'brst-engine'),
            'brst_form_description' => __('Answer the following questions to assess your business risk profile.', 'brst-engine'),
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Process feedback reminders cron job
     */
    public function process_feedback_reminders() {
        if ($this->email_engine) {
            $this->email_engine->send_pending_feedback_reminders();
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}

/**
 * Returns the main instance of the plugin
 *
 * @return Business_Risk_Stress_Test_Engine
 */
function BRST() {
    return Business_Risk_Stress_Test_Engine::instance();
}

// Initialize the plugin
BRST();
