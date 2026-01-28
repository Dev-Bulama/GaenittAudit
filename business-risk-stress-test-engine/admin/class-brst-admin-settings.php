<?php
/**
 * Admin Settings Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings class for managing plugin settings
 */
class BRST_Admin_Settings {

    /**
     * Render settings page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['brst_save_settings']) && wp_verify_nonce($_POST['brst_settings_nonce'], 'brst_save_settings')) {
            $this->save_settings();
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'payment';
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Settings', 'brst-engine'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'payment')); ?>" class="nav-tab <?php echo $active_tab === 'payment' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Payment', 'brst-engine'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'email')); ?>" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email', 'brst-engine'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'general')); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'brst-engine'); ?>
                </a>
            </nav>

            <form method="post" class="brst-settings-form">
                <?php wp_nonce_field('brst_save_settings', 'brst_settings_nonce'); ?>
                <input type="hidden" name="brst_settings_tab" value="<?php echo esc_attr($active_tab); ?>">

                <?php
                switch ($active_tab) {
                    case 'payment':
                        $this->render_payment_settings();
                        break;
                    case 'email':
                        $this->render_email_settings();
                        break;
                    case 'general':
                        $this->render_general_settings();
                        break;
                }
                ?>

                <p class="submit">
                    <input type="submit" name="brst_save_settings" class="button-primary" value="<?php esc_attr_e('Save Settings', 'brst-engine'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render payment settings
     */
    private function render_payment_settings() {
        ?>
        <h2><?php esc_html_e('Payment Settings', 'brst-engine'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_payment_gateway"><?php esc_html_e('Active Gateway', 'brst-engine'); ?></label>
                </th>
                <td>
                    <select name="brst_payment_gateway" id="brst_payment_gateway">
                        <option value="paystack" <?php selected(get_option('brst_payment_gateway'), 'paystack'); ?>>
                            <?php esc_html_e('Paystack', 'brst-engine'); ?>
                        </option>
                        <option value="stripe" <?php selected(get_option('brst_payment_gateway'), 'stripe'); ?>>
                            <?php esc_html_e('Stripe', 'brst-engine'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_payment_amount"><?php esc_html_e('Payment Amount', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="number" name="brst_payment_amount" id="brst_payment_amount"
                           value="<?php echo esc_attr(get_option('brst_payment_amount', 5000)); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Amount in smallest currency unit (e.g., kobo for NGN, cents for USD)', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_payment_currency"><?php esc_html_e('Currency', 'brst-engine'); ?></label>
                </th>
                <td>
                    <select name="brst_payment_currency" id="brst_payment_currency">
                        <option value="NGN" <?php selected(get_option('brst_payment_currency'), 'NGN'); ?>>NGN</option>
                        <option value="USD" <?php selected(get_option('brst_payment_currency'), 'USD'); ?>>USD</option>
                        <option value="GBP" <?php selected(get_option('brst_payment_currency'), 'GBP'); ?>>GBP</option>
                        <option value="EUR" <?php selected(get_option('brst_payment_currency'), 'EUR'); ?>>EUR</option>
                    </select>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Paystack Settings', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_paystack_public_key"><?php esc_html_e('Public Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_paystack_public_key" id="brst_paystack_public_key"
                           value="<?php echo esc_attr(get_option('brst_paystack_public_key')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paystack_secret_key"><?php esc_html_e('Secret Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="password" name="brst_paystack_secret_key" id="brst_paystack_secret_key"
                           value="<?php echo esc_attr(get_option('brst_paystack_secret_key')); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Stripe Settings', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_stripe_public_key"><?php esc_html_e('Publishable Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_stripe_public_key" id="brst_stripe_public_key"
                           value="<?php echo esc_attr(get_option('brst_stripe_public_key')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_stripe_secret_key"><?php esc_html_e('Secret Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="password" name="brst_stripe_secret_key" id="brst_stripe_secret_key"
                           value="<?php echo esc_attr(get_option('brst_stripe_secret_key')); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render email settings
     */
    private function render_email_settings() {
        ?>
        <h2><?php esc_html_e('Email Settings', 'brst-engine'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_sender_name"><?php esc_html_e('Sender Name', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_sender_name" id="brst_sender_name"
                           value="<?php echo esc_attr(get_option('brst_sender_name', get_bloginfo('name'))); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_sender_email"><?php esc_html_e('Sender Email', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="email" name="brst_sender_email" id="brst_sender_email"
                           value="<?php echo esc_attr(get_option('brst_sender_email', get_option('admin_email'))); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_feedback_reminder_hours"><?php esc_html_e('Feedback Reminder Delay', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="number" name="brst_feedback_reminder_hours" id="brst_feedback_reminder_hours"
                           value="<?php echo esc_attr(get_option('brst_feedback_reminder_hours', 48)); ?>" class="small-text">
                    <span><?php esc_html_e('hours', 'brst-engine'); ?></span>
                    <p class="description"><?php esc_html_e('Time to wait before sending a feedback reminder email.', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings() {
        $pages = get_pages();
        ?>
        <h2><?php esc_html_e('General Settings', 'brst-engine'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_terms_page"><?php esc_html_e('Terms & Conditions Page', 'brst-engine'); ?></label>
                </th>
                <td>
                    <select name="brst_terms_page" id="brst_terms_page">
                        <option value=""><?php esc_html_e('— Select —', 'brst-engine'); ?></option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected(get_option('brst_terms_page'), $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_privacy_page"><?php esc_html_e('Privacy Policy Page', 'brst-engine'); ?></label>
                </th>
                <td>
                    <select name="brst_privacy_page" id="brst_privacy_page">
                        <option value=""><?php esc_html_e('— Select —', 'brst-engine'); ?></option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected(get_option('brst_privacy_page'), $page->ID); ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Shortcode Reference', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Questionnaire', 'brst-engine'); ?></th>
                <td><code>[brst_questionnaire]</code></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Feedback Form', 'brst-engine'); ?></th>
                <td><code>[brst_feedback]</code></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Results Display', 'brst-engine'); ?></th>
                <td><code>[brst_results submission_id="123"]</code></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $tab = isset($_POST['brst_settings_tab']) ? sanitize_text_field($_POST['brst_settings_tab']) : 'payment';

        switch ($tab) {
            case 'payment':
                update_option('brst_payment_gateway', sanitize_text_field($_POST['brst_payment_gateway'] ?? 'paystack'));
                update_option('brst_payment_amount', intval($_POST['brst_payment_amount'] ?? 5000));
                update_option('brst_payment_currency', sanitize_text_field($_POST['brst_payment_currency'] ?? 'NGN'));
                update_option('brst_paystack_public_key', sanitize_text_field($_POST['brst_paystack_public_key'] ?? ''));
                update_option('brst_paystack_secret_key', sanitize_text_field($_POST['brst_paystack_secret_key'] ?? ''));
                update_option('brst_stripe_public_key', sanitize_text_field($_POST['brst_stripe_public_key'] ?? ''));
                update_option('brst_stripe_secret_key', sanitize_text_field($_POST['brst_stripe_secret_key'] ?? ''));
                break;

            case 'email':
                update_option('brst_sender_name', sanitize_text_field($_POST['brst_sender_name'] ?? ''));
                update_option('brst_sender_email', sanitize_email($_POST['brst_sender_email'] ?? ''));
                update_option('brst_feedback_reminder_hours', intval($_POST['brst_feedback_reminder_hours'] ?? 48));
                break;

            case 'general':
                update_option('brst_terms_page', intval($_POST['brst_terms_page'] ?? 0));
                update_option('brst_privacy_page', intval($_POST['brst_privacy_page'] ?? 0));
                break;
        }

        add_settings_error('brst_settings', 'settings_updated', __('Settings saved.', 'brst-engine'), 'updated');
        settings_errors('brst_settings');
    }
}
