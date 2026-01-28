<?php
/**
 * Uninstall script for Business Risk Stress Test Engine
 *
 * This file runs when the plugin is deleted from WordPress admin.
 * It removes all plugin data including database tables and options.
 *
 * @package BusinessRiskStressTest
 */

// Exit if accessed directly or not in uninstall mode
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define constants if not already defined
if (!defined('BRST_PLUGIN_DIR')) {
    define('BRST_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

/**
 * Clean up all plugin data
 */
function brst_uninstall_cleanup() {
    global $wpdb;

    // Only proceed if user has proper permissions
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Check if we should delete data (can be controlled by a setting)
    $delete_data = get_option('brst_delete_data_on_uninstall', true);

    if (!$delete_data) {
        return;
    }

    // Drop custom tables
    $tables = array(
        $wpdb->prefix . 'brst_forms',
        $wpdb->prefix . 'brst_submissions',
        $wpdb->prefix . 'brst_payments',
        $wpdb->prefix . 'brst_email_captures',
        $wpdb->prefix . 'brst_reports',
        $wpdb->prefix . 'brst_feedback',
        $wpdb->prefix . 'brst_activity_log',
        $wpdb->prefix . 'brst_report_templates',
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete all plugin options
    $options = array(
        'brst_db_version',
        'brst_paystack_public_key',
        'brst_paystack_secret_key',
        'brst_stripe_public_key',
        'brst_stripe_secret_key',
        'brst_payment_gateway',
        'brst_payment_amount',
        'brst_payment_currency',
        'brst_terms_page',
        'brst_privacy_page',
        'brst_feedback_reminder_hours',
        'brst_sender_email',
        'brst_sender_name',
        'brst_delete_data_on_uninstall',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Clear any scheduled cron events
    wp_clear_scheduled_hook('brst_send_feedback_reminders');

    // Delete uploaded reports
    $upload_dir = wp_upload_dir();
    $reports_dir = $upload_dir['basedir'] . '/brst-reports/';

    if (is_dir($reports_dir)) {
        brst_delete_directory($reports_dir);
    }

    // Clear any transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_brst_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_brst_%'");

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Recursively delete a directory
 *
 * @param string $dir Directory path
 * @return bool
 */
function brst_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            brst_delete_directory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

// Run cleanup
brst_uninstall_cleanup();
