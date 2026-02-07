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
                <a href="<?php echo esc_url(add_query_arg('tab', 'form')); ?>" class="nav-tab <?php echo $active_tab === 'form' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Form Customization', 'brst-engine'); ?>
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
                    case 'form':
                        $this->render_form_settings();
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
                        <option value="NGN" <?php selected(get_option('brst_payment_currency'), 'NGN'); ?>>NGN (Nigerian Naira)</option>
                        <option value="USD" <?php selected(get_option('brst_payment_currency'), 'USD'); ?>>USD (US Dollar)</option>
                        <option value="GBP" <?php selected(get_option('brst_payment_currency'), 'GBP'); ?>>GBP (British Pound)</option>
                        <option value="EUR" <?php selected(get_option('brst_payment_currency'), 'EUR'); ?>>EUR (Euro)</option>
                        <option value="GHS" <?php selected(get_option('brst_payment_currency'), 'GHS'); ?>>GHS (Ghanaian Cedi)</option>
                        <option value="KES" <?php selected(get_option('brst_payment_currency'), 'KES'); ?>>KES (Kenyan Shilling)</option>
                        <option value="ZAR" <?php selected(get_option('brst_payment_currency'), 'ZAR'); ?>>ZAR (South African Rand)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_default_gateway"><?php esc_html_e('Default Gateway', 'brst-engine'); ?></label>
                </th>
                <td>
                    <select name="brst_default_gateway" id="brst_default_gateway">
                        <option value="paystack" <?php selected(get_option('brst_default_gateway'), 'paystack'); ?>>
                            <?php esc_html_e('Paystack', 'brst-engine'); ?>
                        </option>
                        <option value="stripe" <?php selected(get_option('brst_default_gateway'), 'stripe'); ?>>
                            <?php esc_html_e('Stripe', 'brst-engine'); ?>
                        </option>
                        <option value="paypal" <?php selected(get_option('brst_default_gateway'), 'paypal'); ?>>
                            <?php esc_html_e('PayPal', 'brst-engine'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Default gateway shown to users when multiple gateways are enabled.', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Paystack Settings', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_paystack_enabled"><?php esc_html_e('Enable Paystack', 'brst-engine'); ?></label>
                </th>
                <td>
                    <label class="brst-toggle">
                        <input type="checkbox" name="brst_paystack_enabled" id="brst_paystack_enabled" value="1"
                               <?php checked(get_option('brst_paystack_enabled'), true); ?>>
                        <span class="brst-toggle-slider"></span>
                    </label>
                    <span class="description"><?php esc_html_e('Enable Paystack as a payment option for users.', 'brst-engine'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paystack_public_key"><?php esc_html_e('Public Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_paystack_public_key" id="brst_paystack_public_key"
                           value="<?php echo esc_attr(get_option('brst_paystack_public_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Paystack public key (pk_live_xxx or pk_test_xxx).', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paystack_secret_key"><?php esc_html_e('Secret Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="password" name="brst_paystack_secret_key" id="brst_paystack_secret_key"
                           value="<?php echo esc_attr(get_option('brst_paystack_secret_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Paystack secret key (sk_live_xxx or sk_test_xxx).', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Stripe Settings', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_stripe_enabled"><?php esc_html_e('Enable Stripe', 'brst-engine'); ?></label>
                </th>
                <td>
                    <label class="brst-toggle">
                        <input type="checkbox" name="brst_stripe_enabled" id="brst_stripe_enabled" value="1"
                               <?php checked(get_option('brst_stripe_enabled'), true); ?>>
                        <span class="brst-toggle-slider"></span>
                    </label>
                    <span class="description"><?php esc_html_e('Enable Stripe as a payment option for users.', 'brst-engine'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_stripe_public_key"><?php esc_html_e('Publishable Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_stripe_public_key" id="brst_stripe_public_key"
                           value="<?php echo esc_attr(get_option('brst_stripe_public_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Stripe publishable key (pk_live_xxx or pk_test_xxx).', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_stripe_secret_key"><?php esc_html_e('Secret Key', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="password" name="brst_stripe_secret_key" id="brst_stripe_secret_key"
                           value="<?php echo esc_attr(get_option('brst_stripe_secret_key')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your Stripe secret key (sk_live_xxx or sk_test_xxx).', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('PayPal Settings', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_paypal_enabled"><?php esc_html_e('Enable PayPal', 'brst-engine'); ?></label>
                </th>
                <td>
                    <label class="brst-toggle">
                        <input type="checkbox" name="brst_paypal_enabled" id="brst_paypal_enabled" value="1"
                               <?php checked(get_option('brst_paypal_enabled'), true); ?>>
                        <span class="brst-toggle-slider"></span>
                    </label>
                    <span class="description"><?php esc_html_e('Enable PayPal as a payment option for users.', 'brst-engine'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paypal_sandbox"><?php esc_html_e('Sandbox Mode', 'brst-engine'); ?></label>
                </th>
                <td>
                    <label class="brst-toggle">
                        <input type="checkbox" name="brst_paypal_sandbox" id="brst_paypal_sandbox" value="1"
                               <?php checked(get_option('brst_paypal_sandbox', true), true); ?>>
                        <span class="brst-toggle-slider"></span>
                    </label>
                    <span class="description"><?php esc_html_e('Enable sandbox/test mode for PayPal.', 'brst-engine'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paypal_client_id"><?php esc_html_e('Client ID', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_paypal_client_id" id="brst_paypal_client_id"
                           value="<?php echo esc_attr(get_option('brst_paypal_client_id')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your PayPal Client ID from the Developer Dashboard.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_paypal_secret"><?php esc_html_e('Secret', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="password" name="brst_paypal_secret" id="brst_paypal_secret"
                           value="<?php echo esc_attr(get_option('brst_paypal_secret')); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Your PayPal Secret from the Developer Dashboard.', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <div class="brst-settings-info">
            <h4><?php esc_html_e('Gateway Selection', 'brst-engine'); ?></h4>
            <p><?php esc_html_e('When multiple payment gateways are enabled, users will be able to choose their preferred payment method during checkout. The default gateway will be pre-selected.', 'brst-engine'); ?></p>
        </div>
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

        <div class="brst-settings-info">
            <h4><?php esc_html_e('Email Templates', 'brst-engine'); ?></h4>
            <p><?php
                printf(
                    esc_html__('To edit email templates, go to %sTemplates%s and select the Email Templates tab.', 'brst-engine'),
                    '<a href="' . esc_url(admin_url('admin.php?page=brst-templates&tab=email')) . '">',
                    '</a>'
                );
            ?></p>
        </div>
        <?php
    }

    /**
     * Render form customization settings
     */
    private function render_form_settings() {
        $default_step_titles = array(
            'cash_tight' => __('Cash Flow Assessment', 'brst-engine'),
            'revenue_concentrated' => __('Revenue Diversity', 'brst-engine'),
            'cost_locked' => __('Cost Structure', 'brst-engine'),
            'owner_dependent' => __('Business Dependency', 'brst-engine'),
            'externally_exposed' => __('External Exposure', 'brst-engine'),
            'personalization' => __('Your Focus Area', 'brst-engine'),
        );
        $step_titles = get_option('brst_step_titles', array());

        $default_unlock_features = array(
            __('Complete risk analysis across all business categories', 'brst-engine'),
            __('Personalized recommendations based on your profile', 'brst-engine'),
            __('Actionable steps to address identified risks', 'brst-engine'),
            __('PDF report delivered to your email', 'brst-engine'),
        );
        $unlock_features = get_option('brst_unlock_features', $default_unlock_features);
        ?>
        <h2><?php esc_html_e('Form Customization', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Results Display', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_show_category_scores"><?php esc_html_e('Show Category Scores', 'brst-engine'); ?></label>
                </th>
                <td>
                    <label class="brst-toggle">
                        <input type="checkbox" name="brst_show_category_scores" id="brst_show_category_scores" value="1"
                               <?php checked(get_option('brst_show_category_scores', '1'), '1'); ?>>
                        <span class="brst-toggle-slider"></span>
                    </label>
                    <span class="description"><?php esc_html_e('Display individual category performance percentages in the mini report.', 'brst-engine'); ?></span>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Form Step Titles', 'brst-engine'); ?></h3>
        <p class="description"><?php esc_html_e('Customize the titles shown at the top of each form step. Leave empty to hide the title.', 'brst-engine'); ?></p>
        <table class="form-table">
            <?php foreach ($default_step_titles as $key => $default_title):
                $current_title = isset($step_titles[$key]) ? $step_titles[$key] : $default_title;
            ?>
            <tr>
                <th scope="row">
                    <label for="step_title_<?php echo esc_attr($key); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_step_titles[<?php echo esc_attr($key); ?>]" id="step_title_<?php echo esc_attr($key); ?>"
                           value="<?php echo esc_attr($current_title); ?>" class="regular-text"
                           placeholder="<?php echo esc_attr($default_title); ?>">
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h3><?php esc_html_e('Payment Page', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_unlock_title"><?php esc_html_e('Unlock Section Title', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_unlock_title" id="brst_unlock_title"
                           value="<?php echo esc_attr(get_option('brst_unlock_title', __('Unlock Your Full Report', 'brst-engine'))); ?>" class="large-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_unlock_description"><?php esc_html_e('Unlock Section Description', 'brst-engine'); ?></label>
                </th>
                <td>
                    <textarea name="brst_unlock_description" id="brst_unlock_description" rows="2" class="large-text"><?php
                        echo esc_textarea(get_option('brst_unlock_description', __('Your full report includes detailed analysis, personalized recommendations, and actionable insights to help your business thrive.', 'brst-engine')));
                    ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_unlock_features_title"><?php esc_html_e("What's Included Title", 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_unlock_features_title" id="brst_unlock_features_title"
                           value="<?php echo esc_attr(get_option('brst_unlock_features_title', __("What's Included", 'brst-engine'))); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Leave empty to hide this section.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Features List', 'brst-engine'); ?></label>
                </th>
                <td>
                    <?php for ($i = 0; $i < 6; $i++):
                        $feature = isset($unlock_features[$i]) ? $unlock_features[$i] : '';
                    ?>
                    <input type="text" name="brst_unlock_features[]"
                           value="<?php echo esc_attr($feature); ?>" class="large-text"
                           placeholder="<?php echo $i < 4 ? esc_attr($default_unlock_features[$i]) : ''; ?>"
                           style="margin-bottom: 8px;">
                    <?php endfor; ?>
                    <p class="description"><?php esc_html_e('Enter up to 6 features. Leave empty to skip.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_payment_email_label"><?php esc_html_e('Payment Email Field Label', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_payment_email_label" id="brst_payment_email_label"
                           value="<?php echo esc_attr(get_option('brst_payment_email_label', __('Email for payment receipt', 'brst-engine'))); ?>" class="large-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Post-Payment Email Capture', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_email_capture_title"><?php esc_html_e('Email Form Title', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_email_capture_title" id="brst_email_capture_title"
                           value="<?php echo esc_attr(get_option('brst_email_capture_title', __('Where do you want your full report delivered?', 'brst-engine'))); ?>" class="large-text">
                    <p class="description"><?php esc_html_e('The title shown on the email capture form after payment.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_email_capture_success"><?php esc_html_e('Success Message', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_email_capture_success" id="brst_email_capture_success"
                           value="<?php echo esc_attr(get_option('brst_email_capture_success', __('Payment Successful!', 'brst-engine'))); ?>" class="large-text">
                    <p class="description"><?php esc_html_e('The success message shown after payment completes.', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Form Text', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_form_title"><?php esc_html_e('Form Title', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_form_title" id="brst_form_title"
                           value="<?php echo esc_attr(get_option('brst_form_title', __('Business Risk Stress Test', 'brst-engine'))); ?>" class="large-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_form_description"><?php esc_html_e('Form Description', 'brst-engine'); ?></label>
                </th>
                <td>
                    <textarea name="brst_form_description" id="brst_form_description" rows="3" class="large-text"><?php
                        echo esc_textarea(get_option('brst_form_description', __('Answer the following questions to assess your business risk profile.', 'brst-engine')));
                    ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_submit_button_text"><?php esc_html_e('Submit Button Text', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="text" name="brst_submit_button_text" id="brst_submit_button_text"
                           value="<?php echo esc_attr(get_option('brst_submit_button_text', __('Get My Results', 'brst-engine'))); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Form Colors', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_primary_color"><?php esc_html_e('Primary Color', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="color" name="brst_primary_color" id="brst_primary_color"
                           value="<?php echo esc_attr(get_option('brst_primary_color', '#3498db')); ?>">
                    <input type="text" name="brst_primary_color_text" id="brst_primary_color_text"
                           value="<?php echo esc_attr(get_option('brst_primary_color', '#3498db')); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('Used for headings, progress bar, and accents.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_secondary_color"><?php esc_html_e('Secondary Color', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="color" name="brst_secondary_color" id="brst_secondary_color"
                           value="<?php echo esc_attr(get_option('brst_secondary_color', '#2c3e50')); ?>">
                    <input type="text" name="brst_secondary_color_text" id="brst_secondary_color_text"
                           value="<?php echo esc_attr(get_option('brst_secondary_color', '#2c3e50')); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('Used for text and secondary elements.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_button_color"><?php esc_html_e('Button Color', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="color" name="brst_button_color" id="brst_button_color"
                           value="<?php echo esc_attr(get_option('brst_button_color', '#3498db')); ?>">
                    <input type="text" name="brst_button_color_text" id="brst_button_color_text"
                           value="<?php echo esc_attr(get_option('brst_button_color', '#3498db')); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('Background color for buttons.', 'brst-engine'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brst_button_text_color"><?php esc_html_e('Button Text Color', 'brst-engine'); ?></label>
                </th>
                <td>
                    <input type="color" name="brst_button_text_color" id="brst_button_text_color"
                           value="<?php echo esc_attr(get_option('brst_button_text_color', '#ffffff')); ?>">
                    <input type="text" name="brst_button_text_color_text" id="brst_button_text_color_text"
                           value="<?php echo esc_attr(get_option('brst_button_text_color', '#ffffff')); ?>" class="small-text">
                    <p class="description"><?php esc_html_e('Text color for buttons.', 'brst-engine'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Form Preview', 'brst-engine'); ?></h3>
        <div class="brst-form-preview" style="background: #f8f9fa; padding: 20px; border-radius: 8px; max-width: 600px;">
            <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="color: <?php echo esc_attr(get_option('brst_secondary_color', '#2c3e50')); ?>; margin-top: 0;">
                    <?php echo esc_html(get_option('brst_form_title', __('Business Risk Stress Test', 'brst-engine'))); ?>
                </h2>
                <p style="color: #666;">
                    <?php echo esc_html(get_option('brst_form_description', __('Answer the following questions to assess your business risk profile.', 'brst-engine'))); ?>
                </p>
                <div style="background: #e9ecef; height: 8px; border-radius: 4px; margin: 20px 0;">
                    <div style="background: <?php echo esc_attr(get_option('brst_primary_color', '#3498db')); ?>; height: 100%; width: 33%; border-radius: 4px;"></div>
                </div>
                <button type="button" style="background: <?php echo esc_attr(get_option('brst_button_color', '#3498db')); ?>; color: <?php echo esc_attr(get_option('brst_button_text_color', '#ffffff')); ?>; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer;">
                    <?php echo esc_html(get_option('brst_submit_button_text', __('Get My Results', 'brst-engine'))); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Sync color inputs
            $('input[type="color"]').on('input', function() {
                var textInput = $(this).next('input[type="text"]');
                textInput.val($(this).val());
            });
            $('input[name$="_color_text"]').on('input', function() {
                var colorInput = $(this).prev('input[type="color"]');
                colorInput.val($(this).val());
            });
        });
        </script>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings() {
        $pages = get_pages();
        $logo_url = get_option('brst_logo_url', '');
        ?>
        <h2><?php esc_html_e('General Settings', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Branding', 'brst-engine'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brst_logo_url"><?php esc_html_e('Logo', 'brst-engine'); ?></label>
                </th>
                <td>
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <?php if ($logo_url): ?>
                        <div id="brst-logo-preview" style="max-width: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 100%; height: auto;">
                        </div>
                        <?php else: ?>
                        <div id="brst-logo-preview" style="display: none; max-width: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                            <img src="" style="max-width: 100%; height: auto;">
                        </div>
                        <?php endif; ?>
                        <div>
                            <input type="hidden" name="brst_logo_url" id="brst_logo_url" value="<?php echo esc_attr($logo_url); ?>">
                            <button type="button" class="button" id="brst-upload-logo"><?php esc_html_e('Upload Logo', 'brst-engine'); ?></button>
                            <?php if ($logo_url): ?>
                            <button type="button" class="button" id="brst-remove-logo" style="margin-left: 5px;"><?php esc_html_e('Remove', 'brst-engine'); ?></button>
                            <?php else: ?>
                            <button type="button" class="button" id="brst-remove-logo" style="margin-left: 5px; display: none;"><?php esc_html_e('Remove', 'brst-engine'); ?></button>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e('Logo will appear in email reports. Recommended size: 200px wide.', 'brst-engine'); ?></p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Legal Pages', 'brst-engine'); ?></h3>
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

        <script>
        jQuery(document).ready(function($) {
            // Media uploader for logo
            var mediaUploader;
            $('#brst-upload-logo').on('click', function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title: '<?php esc_html_e('Select Logo', 'brst-engine'); ?>',
                    button: { text: '<?php esc_html_e('Use as Logo', 'brst-engine'); ?>' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#brst_logo_url').val(attachment.url);
                    $('#brst-logo-preview').show().find('img').attr('src', attachment.url);
                    $('#brst-remove-logo').show();
                });
                mediaUploader.open();
            });

            $('#brst-remove-logo').on('click', function(e) {
                e.preventDefault();
                $('#brst_logo_url').val('');
                $('#brst-logo-preview').hide().find('img').attr('src', '');
                $(this).hide();
            });
        });
        </script>

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
                // Payment amount and currency
                update_option('brst_payment_amount', intval($_POST['brst_payment_amount'] ?? 5000));
                update_option('brst_payment_currency', sanitize_text_field($_POST['brst_payment_currency'] ?? 'NGN'));
                update_option('brst_default_gateway', sanitize_text_field($_POST['brst_default_gateway'] ?? 'paystack'));

                // Paystack
                update_option('brst_paystack_enabled', isset($_POST['brst_paystack_enabled']) ? true : false);
                update_option('brst_paystack_public_key', sanitize_text_field($_POST['brst_paystack_public_key'] ?? ''));
                update_option('brst_paystack_secret_key', sanitize_text_field($_POST['brst_paystack_secret_key'] ?? ''));

                // Stripe
                update_option('brst_stripe_enabled', isset($_POST['brst_stripe_enabled']) ? true : false);
                update_option('brst_stripe_public_key', sanitize_text_field($_POST['brst_stripe_public_key'] ?? ''));
                update_option('brst_stripe_secret_key', sanitize_text_field($_POST['brst_stripe_secret_key'] ?? ''));

                // PayPal
                update_option('brst_paypal_enabled', isset($_POST['brst_paypal_enabled']) ? true : false);
                update_option('brst_paypal_sandbox', isset($_POST['brst_paypal_sandbox']) ? true : false);
                update_option('brst_paypal_client_id', sanitize_text_field($_POST['brst_paypal_client_id'] ?? ''));
                update_option('brst_paypal_secret', sanitize_text_field($_POST['brst_paypal_secret'] ?? ''));
                break;

            case 'email':
                update_option('brst_sender_name', sanitize_text_field($_POST['brst_sender_name'] ?? ''));
                update_option('brst_sender_email', sanitize_email($_POST['brst_sender_email'] ?? ''));
                update_option('brst_feedback_reminder_hours', intval($_POST['brst_feedback_reminder_hours'] ?? 48));
                break;

            case 'form':
                // Results display - use '1' and '0' for reliable saving
                update_option('brst_show_category_scores', isset($_POST['brst_show_category_scores']) ? '1' : '0');

                // Step titles
                if (!empty($_POST['brst_step_titles']) && is_array($_POST['brst_step_titles'])) {
                    $step_titles = array();
                    foreach ($_POST['brst_step_titles'] as $key => $value) {
                        $step_titles[sanitize_key($key)] = sanitize_text_field($value);
                    }
                    update_option('brst_step_titles', $step_titles);
                }

                // Payment page / Unlock section
                update_option('brst_unlock_title', sanitize_text_field($_POST['brst_unlock_title'] ?? ''));
                update_option('brst_unlock_description', sanitize_textarea_field($_POST['brst_unlock_description'] ?? ''));
                update_option('brst_unlock_features_title', sanitize_text_field($_POST['brst_unlock_features_title'] ?? ''));
                update_option('brst_payment_email_label', sanitize_text_field($_POST['brst_payment_email_label'] ?? ''));

                // Unlock features list
                if (!empty($_POST['brst_unlock_features']) && is_array($_POST['brst_unlock_features'])) {
                    $features = array();
                    foreach ($_POST['brst_unlock_features'] as $feature) {
                        $feature = sanitize_text_field($feature);
                        if (!empty(trim($feature))) {
                            $features[] = $feature;
                        }
                    }
                    update_option('brst_unlock_features', $features);
                }

                // Email capture
                update_option('brst_email_capture_title', sanitize_text_field($_POST['brst_email_capture_title'] ?? ''));
                update_option('brst_email_capture_success', sanitize_text_field($_POST['brst_email_capture_success'] ?? ''));

                // Form text
                update_option('brst_form_title', sanitize_text_field($_POST['brst_form_title'] ?? ''));
                update_option('brst_form_description', sanitize_textarea_field($_POST['brst_form_description'] ?? ''));
                update_option('brst_submit_button_text', sanitize_text_field($_POST['brst_submit_button_text'] ?? ''));
                update_option('brst_primary_color', sanitize_hex_color($_POST['brst_primary_color'] ?? '#3498db'));
                update_option('brst_secondary_color', sanitize_hex_color($_POST['brst_secondary_color'] ?? '#2c3e50'));
                update_option('brst_button_color', sanitize_hex_color($_POST['brst_button_color'] ?? '#3498db'));
                update_option('brst_button_text_color', sanitize_hex_color($_POST['brst_button_text_color'] ?? '#ffffff'));
                break;

            case 'general':
                update_option('brst_logo_url', esc_url_raw($_POST['brst_logo_url'] ?? ''));
                update_option('brst_terms_page', intval($_POST['brst_terms_page'] ?? 0));
                update_option('brst_privacy_page', intval($_POST['brst_privacy_page'] ?? 0));
                break;
        }

        add_settings_error('brst_settings', 'settings_updated', __('Settings saved.', 'brst-engine'), 'updated');
        settings_errors('brst_settings');
    }
}
