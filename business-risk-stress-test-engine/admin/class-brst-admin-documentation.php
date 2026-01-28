<?php
/**
 * Admin Documentation Page
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Documentation class for displaying plugin documentation
 */
class BRST_Admin_Documentation {

    /**
     * Render documentation page
     */
    public function render() {
        $active_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'getting-started';
        ?>
        <div class="wrap brst-admin-wrap brst-documentation">
            <h1><?php esc_html_e('Documentation', 'brst-engine'); ?></h1>

            <div class="brst-docs-container" style="display: flex; gap: 30px; margin-top: 20px;">
                <!-- Sidebar Navigation -->
                <div class="brst-docs-sidebar" style="width: 250px; flex-shrink: 0;">
                    <nav class="brst-docs-nav" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px;">
                        <h3 style="margin-top: 0;"><?php esc_html_e('Contents', 'brst-engine'); ?></h3>
                        <ul style="list-style: none; margin: 0; padding: 0;">
                            <?php
                            $sections = array(
                                'getting-started' => __('Getting Started', 'brst-engine'),
                                'shortcodes' => __('Shortcodes', 'brst-engine'),
                                'payment-setup' => __('Payment Setup', 'brst-engine'),
                                'templates' => __('Customizing Templates', 'brst-engine'),
                                'form-customization' => __('Form Customization', 'brst-engine'),
                                'profiles' => __('Understanding Profiles', 'brst-engine'),
                                'scoring' => __('Scoring System', 'brst-engine'),
                                'reports' => __('Reports & Delivery', 'brst-engine'),
                                'troubleshooting' => __('Troubleshooting', 'brst-engine'),
                            );

                            foreach ($sections as $key => $label):
                                $is_active = ($active_section === $key);
                            ?>
                                <li style="margin-bottom: 8px;">
                                    <a href="<?php echo esc_url(add_query_arg('section', $key)); ?>"
                                       style="<?php echo $is_active ? 'font-weight: bold; color: #0073aa;' : 'color: #444;'; ?> text-decoration: none;">
                                        <?php echo esc_html($label); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>

                <!-- Main Content -->
                <div class="brst-docs-content" style="flex: 1; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 30px;">
                    <?php
                    switch ($active_section) {
                        case 'getting-started':
                            $this->render_getting_started();
                            break;
                        case 'shortcodes':
                            $this->render_shortcodes();
                            break;
                        case 'payment-setup':
                            $this->render_payment_setup();
                            break;
                        case 'templates':
                            $this->render_templates();
                            break;
                        case 'form-customization':
                            $this->render_form_customization();
                            break;
                        case 'profiles':
                            $this->render_profiles();
                            break;
                        case 'scoring':
                            $this->render_scoring();
                            break;
                        case 'reports':
                            $this->render_reports();
                            break;
                        case 'troubleshooting':
                            $this->render_troubleshooting();
                            break;
                        default:
                            $this->render_getting_started();
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Getting Started section
     */
    private function render_getting_started() {
        ?>
        <h2><?php esc_html_e('Getting Started', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Overview', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('The Business Risk Stress Test Engine is a comprehensive WordPress plugin that allows you to create interactive business risk assessments for small business owners. The plugin features:', 'brst-engine'); ?></p>
        <ul>
            <li><?php esc_html_e('21-question risk assessment questionnaire', 'brst-engine'); ?></li>
            <li><?php esc_html_e('5 business profile categories', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Profile interaction analysis', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Mini report preview (free)', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Full report with payment gateway (Paystack, Stripe, PayPal)', 'brst-engine'); ?></li>
            <li><?php esc_html_e('PDF report generation and email delivery', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Feedback collection system', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Quick Setup', 'brst-engine'); ?></h3>
        <ol>
            <li>
                <strong><?php esc_html_e('Configure Payment Gateway:', 'brst-engine'); ?></strong>
                <?php
                printf(
                    esc_html__('Go to %sSettings > Payment%s and enter your API keys for at least one payment gateway.', 'brst-engine'),
                    '<a href="' . esc_url(admin_url('admin.php?page=brst-settings&tab=payment')) . '">',
                    '</a>'
                );
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('Create a Page:', 'brst-engine'); ?></strong>
                <?php esc_html_e('Create a new WordPress page where you want the questionnaire to appear.', 'brst-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Add Shortcode:', 'brst-engine'); ?></strong>
                <?php esc_html_e('Add the shortcode', 'brst-engine'); ?> <code>[brst_questionnaire]</code> <?php esc_html_e('to your page.', 'brst-engine'); ?>
            </li>
            <li>
                <strong><?php esc_html_e('Customize (Optional):', 'brst-engine'); ?></strong>
                <?php
                printf(
                    esc_html__('Customize form colors and text in %sSettings > Form Customization%s.', 'brst-engine'),
                    '<a href="' . esc_url(admin_url('admin.php?page=brst-settings&tab=form')) . '">',
                    '</a>'
                );
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('Test:', 'brst-engine'); ?></strong>
                <?php esc_html_e('Test the complete flow including payment (use test/sandbox mode).', 'brst-engine'); ?>
            </li>
        </ol>

        <div class="brst-docs-tip" style="background: #e7f5ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
            <strong><?php esc_html_e('Tip:', 'brst-engine'); ?></strong>
            <?php esc_html_e('Always test the complete user flow in sandbox/test mode before going live.', 'brst-engine'); ?>
        </div>
        <?php
    }

    /**
     * Shortcodes section
     */
    private function render_shortcodes() {
        ?>
        <h2><?php esc_html_e('Shortcodes', 'brst-engine'); ?></h2>

        <h3><code>[brst_questionnaire]</code></h3>
        <p><?php esc_html_e('Displays the main business risk stress test questionnaire. This is the primary shortcode you\'ll use.', 'brst-engine'); ?></p>
        <p><strong><?php esc_html_e('Example:', 'brst-engine'); ?></strong></p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;">[brst_questionnaire]</pre>

        <h3><code>[brst_feedback]</code></h3>
        <p><?php esc_html_e('Displays the feedback form. Users are typically directed here via email after receiving their report.', 'brst-engine'); ?></p>
        <p><?php esc_html_e('This shortcode requires URL parameters (submission_id and token) which are automatically included in feedback email links.', 'brst-engine'); ?></p>
        <p><strong><?php esc_html_e('Example:', 'brst-engine'); ?></strong></p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;">[brst_feedback]</pre>

        <h3><code>[brst_results]</code></h3>
        <p><?php esc_html_e('Displays results for a specific submission. Useful for creating a results page or embedding results elsewhere.', 'brst-engine'); ?></p>
        <p><strong><?php esc_html_e('Parameters:', 'brst-engine'); ?></strong></p>
        <ul>
            <li><code>submission_id</code> - <?php esc_html_e('The ID of the submission to display (required)', 'brst-engine'); ?></li>
        </ul>
        <p><strong><?php esc_html_e('Example:', 'brst-engine'); ?></strong></p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;">[brst_results submission_id="123"]</pre>
        <?php
    }

    /**
     * Payment Setup section
     */
    private function render_payment_setup() {
        ?>
        <h2><?php esc_html_e('Payment Setup', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Supported Payment Gateways', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('The plugin supports three payment gateways. You can enable one or more:', 'brst-engine'); ?></p>

        <h4><?php esc_html_e('Paystack', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('Ideal for Nigerian businesses and African markets.', 'brst-engine'); ?></p>
        <ol>
            <li><?php esc_html_e('Create an account at paystack.com', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Go to Settings > API Keys & Webhooks in your Paystack dashboard', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Copy your Public Key and Secret Key', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Paste them in Settings > Payment > Paystack Settings', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Enable Paystack by checking the toggle', 'brst-engine'); ?></li>
        </ol>

        <h4><?php esc_html_e('Stripe', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('Global payment processing, supports most countries.', 'brst-engine'); ?></p>
        <ol>
            <li><?php esc_html_e('Create an account at stripe.com', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Go to Developers > API keys in your Stripe dashboard', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Copy your Publishable Key and Secret Key', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Paste them in Settings > Payment > Stripe Settings', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Enable Stripe by checking the toggle', 'brst-engine'); ?></li>
        </ol>

        <h4><?php esc_html_e('PayPal', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('Widely trusted payment method worldwide.', 'brst-engine'); ?></p>
        <ol>
            <li><?php esc_html_e('Create a PayPal Business account', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Go to developer.paypal.com and create an app', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Copy your Client ID and Secret', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Paste them in Settings > Payment > PayPal Settings', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Enable PayPal by checking the toggle', 'brst-engine'); ?></li>
            <li><?php esc_html_e('For testing, enable Sandbox Mode', 'brst-engine'); ?></li>
        </ol>

        <h3><?php esc_html_e('Multiple Gateways', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('When multiple gateways are enabled, users will see a gateway selection option during checkout. Set your preferred default gateway in the settings.', 'brst-engine'); ?></p>

        <div class="brst-docs-warning" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
            <strong><?php esc_html_e('Important:', 'brst-engine'); ?></strong>
            <?php esc_html_e('Always test payments in sandbox/test mode first. Use test API keys during development.', 'brst-engine'); ?>
        </div>
        <?php
    }

    /**
     * Templates section
     */
    private function render_templates() {
        ?>
        <h2><?php esc_html_e('Customizing Templates', 'brst-engine'); ?></h2>

        <p><?php esc_html_e('You can customize the content of mini reports, full reports, and email templates from the Templates page.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('Template Types', 'brst-engine'); ?></h3>

        <h4><?php esc_html_e('Mini Report Templates', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('These are shown to users immediately after completing the questionnaire. There are two variants for each profile:', 'brst-engine'); ?></p>
        <ul>
            <li><strong><?php esc_html_e('Aligned:', 'brst-engine'); ?></strong> <?php esc_html_e('When the user\'s focus (Q21) matches their primary profile', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Not Aligned:', 'brst-engine'); ?></strong> <?php esc_html_e('When there\'s a mismatch', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('Full Report Templates', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('These are used for the paid PDF reports. There are two types:', 'brst-engine'); ?></p>
        <ul>
            <li><strong><?php esc_html_e('Standalone:', 'brst-engine'); ?></strong> <?php esc_html_e('Single profile analysis', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Interaction:', 'brst-engine'); ?></strong> <?php esc_html_e('Analysis of two interacting profiles', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('Email Templates', 'brst-engine'); ?></h4>
        <ul>
            <li><strong><?php esc_html_e('Report Delivery:', 'brst-engine'); ?></strong> <?php esc_html_e('Sent when the report is emailed to the user', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Feedback Reminder:', 'brst-engine'); ?></strong> <?php esc_html_e('Sent after the configured delay to request feedback', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Using Variables', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('Templates support variables that are replaced with actual data. Use double curly braces:', 'brst-engine'); ?></p>
        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;">Your primary profile is {{profile_name}}.</pre>
        <?php
    }

    /**
     * Form Customization section
     */
    private function render_form_customization() {
        ?>
        <h2><?php esc_html_e('Form Customization', 'brst-engine'); ?></h2>

        <p><?php
            printf(
                esc_html__('Customize the appearance and text of your questionnaire form in %sSettings > Form Customization%s.', 'brst-engine'),
                '<a href="' . esc_url(admin_url('admin.php?page=brst-settings&tab=form')) . '">',
                '</a>'
            );
        ?></p>

        <h3><?php esc_html_e('Customizable Text', 'brst-engine'); ?></h3>
        <ul>
            <li><strong><?php esc_html_e('Form Title:', 'brst-engine'); ?></strong> <?php esc_html_e('The main heading displayed above the form', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Form Description:', 'brst-engine'); ?></strong> <?php esc_html_e('Introductory text below the title', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Submit Button Text:', 'brst-engine'); ?></strong> <?php esc_html_e('Text on the final submit button', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Customizable Colors', 'brst-engine'); ?></h3>
        <ul>
            <li><strong><?php esc_html_e('Primary Color:', 'brst-engine'); ?></strong> <?php esc_html_e('Used for progress bar, accents, and highlights', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Secondary Color:', 'brst-engine'); ?></strong> <?php esc_html_e('Used for headings and text', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Button Color:', 'brst-engine'); ?></strong> <?php esc_html_e('Background color for buttons', 'brst-engine'); ?></li>
            <li><strong><?php esc_html_e('Button Text Color:', 'brst-engine'); ?></strong> <?php esc_html_e('Text color for buttons', 'brst-engine'); ?></li>
        </ul>

        <div class="brst-docs-tip" style="background: #e7f5ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
            <strong><?php esc_html_e('Tip:', 'brst-engine'); ?></strong>
            <?php esc_html_e('Use colors that match your website\'s branding for a seamless experience.', 'brst-engine'); ?>
        </div>
        <?php
    }

    /**
     * Profiles section
     */
    private function render_profiles() {
        ?>
        <h2><?php esc_html_e('Understanding Business Profiles', 'brst-engine'); ?></h2>

        <p><?php esc_html_e('The questionnaire identifies five distinct business risk profiles:', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('1. Cash-Tight Operator', 'brst-engine'); ?></h3>
        <p><strong><?php esc_html_e('Questions:', 'brst-engine'); ?></strong> Q1-Q4</p>
        <p><?php esc_html_e('Businesses struggling with cash flow management, inconsistent revenue, or limited cash reserves.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('2. Revenue-Concentrated Builder', 'brst-engine'); ?></h3>
        <p><strong><?php esc_html_e('Questions:', 'brst-engine'); ?></strong> Q5-Q8</p>
        <p><?php esc_html_e('Businesses with revenue heavily dependent on few clients or limited product/service offerings.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('3. Cost-Locked Business', 'brst-engine'); ?></h3>
        <p><strong><?php esc_html_e('Questions:', 'brst-engine'); ?></strong> Q9-Q12</p>
        <p><?php esc_html_e('Businesses with inflexible cost structures and limited ability to scale down operations.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('4. Owner-Dependent Engine', 'brst-engine'); ?></h3>
        <p><strong><?php esc_html_e('Questions:', 'brst-engine'); ?></strong> Q13-Q16</p>
        <p><?php esc_html_e('Businesses that rely heavily on the owner\'s presence and decision-making.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('5. Externally Exposed Builder', 'brst-engine'); ?></h3>
        <p><strong><?php esc_html_e('Questions:', 'brst-engine'); ?></strong> Q17-Q20</p>
        <p><?php esc_html_e('Businesses vulnerable to external factors like supplier issues, regulations, or market shifts.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('Question 21 - Focus Area', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('This question determines the user\'s current focus area and is used for alignment analysis. It doesn\'t contribute to scoring.', 'brst-engine'); ?></p>
        <?php
    }

    /**
     * Scoring section
     */
    private function render_scoring() {
        ?>
        <h2><?php esc_html_e('Scoring System', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Question Scoring', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('Each question (Q1-Q20) is scored as follows:', 'brst-engine'); ?></p>
        <table class="widefat" style="max-width: 400px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Answer', 'brst-engine'); ?></th>
                    <th><?php esc_html_e('Score', 'brst-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><?php esc_html_e('Yes', 'brst-engine'); ?></td><td>0</td></tr>
                <tr><td><?php esc_html_e('Maybe', 'brst-engine'); ?></td><td>2</td></tr>
                <tr><td><?php esc_html_e('No', 'brst-engine'); ?></td><td>3</td></tr>
            </tbody>
        </table>

        <h3><?php esc_html_e('Category Scores', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('Each category has 4 questions, so:', 'brst-engine'); ?></p>
        <ul>
            <li><?php esc_html_e('Minimum score per category: 0 (all "Yes")', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Maximum score per category: 12 (all "No")', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Profile Selection', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('The category with the highest score becomes the primary profile. A higher score indicates greater risk in that area.', 'brst-engine'); ?></p>

        <h3><?php esc_html_e('Interaction Rules', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('When both primary and secondary profiles have scores >= 8, the interaction rule is triggered, providing additional analysis of how these risk areas compound.', 'brst-engine'); ?></p>
        <?php
    }

    /**
     * Reports section
     */
    private function render_reports() {
        ?>
        <h2><?php esc_html_e('Reports & Delivery', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Report Types', 'brst-engine'); ?></h3>

        <h4><?php esc_html_e('Mini Report (Free)', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('Shown immediately after completing the questionnaire. Includes:', 'brst-engine'); ?></p>
        <ul>
            <li><?php esc_html_e('Primary profile identification', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Alignment status with current focus', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Brief summary', 'brst-engine'); ?></li>
            <li><?php esc_html_e('CTA to unlock full report', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('Full Report (Paid)', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('Generated after payment. Includes:', 'brst-engine'); ?></p>
        <ul>
            <li><?php esc_html_e('Complete profile analysis', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Category breakdown with scores', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Interaction analysis (if applicable)', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Recommendations', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Action items', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Report Delivery', 'brst-engine'); ?></h3>
        <ol>
            <li><?php esc_html_e('User completes questionnaire and sees mini report', 'brst-engine'); ?></li>
            <li><?php esc_html_e('User clicks to unlock full report', 'brst-engine'); ?></li>
            <li><?php esc_html_e('User completes payment', 'brst-engine'); ?></li>
            <li><?php esc_html_e('PDF report is generated and emailed to user', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Feedback reminder is sent after configured delay', 'brst-engine'); ?></li>
        </ol>
        <?php
    }

    /**
     * Troubleshooting section
     */
    private function render_troubleshooting() {
        ?>
        <h2><?php esc_html_e('Troubleshooting', 'brst-engine'); ?></h2>

        <h3><?php esc_html_e('Common Issues', 'brst-engine'); ?></h3>

        <h4><?php esc_html_e('Payment gateway not working', 'brst-engine'); ?></h4>
        <ul>
            <li><?php esc_html_e('Verify API keys are correct and match the environment (test vs live)', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Ensure the gateway is enabled in settings', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Check browser console for JavaScript errors', 'brst-engine'); ?></li>
            <li><?php esc_html_e('For PayPal, ensure sandbox mode matches your API keys', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('"PaystackPop is not defined" error', 'brst-engine'); ?></h4>
        <p><?php esc_html_e('This occurs when the Paystack JavaScript library fails to load. Check:', 'brst-engine'); ?></p>
        <ul>
            <li><?php esc_html_e('Your Paystack public key is configured', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Paystack is enabled in settings', 'brst-engine'); ?></li>
            <li><?php esc_html_e('No ad blockers are interfering', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('Emails not being sent', 'brst-engine'); ?></h4>
        <ul>
            <li><?php esc_html_e('Check WordPress can send emails (use a plugin like WP Mail SMTP)', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Verify sender email is configured correctly', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Check spam folders', 'brst-engine'); ?></li>
        </ul>

        <h4><?php esc_html_e('Form not displaying', 'brst-engine'); ?></h4>
        <ul>
            <li><?php esc_html_e('Verify the shortcode is correct: [brst_questionnaire]', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Check for JavaScript errors in browser console', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Ensure plugin is activated', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Try deactivating other plugins to check for conflicts', 'brst-engine'); ?></li>
        </ul>

        <h3><?php esc_html_e('Getting Help', 'brst-engine'); ?></h3>
        <p><?php esc_html_e('If you continue to experience issues:', 'brst-engine'); ?></p>
        <ol>
            <li><?php esc_html_e('Enable WordPress debug mode to see detailed errors', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Check the Activity Log in Submissions for error details', 'brst-engine'); ?></li>
            <li><?php esc_html_e('Contact support with specific error messages and steps to reproduce', 'brst-engine'); ?></li>
        </ol>
        <?php
    }
}
