<?php
/**
 * Admin Questions Editor
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Questions class for editing questionnaire questions
 */
class BRST_Admin_Questions {

    /**
     * Categories
     */
    private $categories = array(
        'cash_tight' => 'Cash-Tight Operator',
        'revenue_concentrated' => 'Revenue-Concentrated Builder',
        'cost_locked' => 'Cost-Locked Business',
        'owner_dependent' => 'Owner-Dependent Engine',
        'externally_exposed' => 'Externally Exposed Builder',
        'personalization' => 'Focus Area (Q21)',
    );

    /**
     * Render questions editor page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['brst_save_questions']) && wp_verify_nonce($_POST['brst_questions_nonce'], 'brst_save_questions')) {
            $this->save_questions();
        }

        // Handle reset to default
        if (isset($_POST['brst_reset_questions']) && wp_verify_nonce($_POST['brst_questions_nonce'], 'brst_save_questions')) {
            delete_option('brst_custom_questions');
            add_settings_error('brst_questions', 'questions_reset', __('Questions reset to default.', 'brst-engine'), 'updated');
        }

        $questions = $this->get_questions();
        ?>
        <div class="wrap brst-admin-wrap brst-questions-editor">
            <h1><?php esc_html_e('Edit Questions', 'brst-engine'); ?></h1>

            <?php settings_errors('brst_questions'); ?>

            <div class="brst-questions-info" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0;"><?php esc_html_e('About the Questionnaire', 'brst-engine'); ?></h4>
                <p><?php esc_html_e('The questionnaire consists of 21 questions:', 'brst-engine'); ?></p>
                <ul style="margin-bottom: 0;">
                    <li><strong>Q1-Q4:</strong> <?php esc_html_e('Cash-Tight Operator (Cash Flow)', 'brst-engine'); ?></li>
                    <li><strong>Q5-Q8:</strong> <?php esc_html_e('Revenue-Concentrated Builder (Revenue Diversity)', 'brst-engine'); ?></li>
                    <li><strong>Q9-Q12:</strong> <?php esc_html_e('Cost-Locked Business (Cost Structure)', 'brst-engine'); ?></li>
                    <li><strong>Q13-Q16:</strong> <?php esc_html_e('Owner-Dependent Engine (Business Dependency)', 'brst-engine'); ?></li>
                    <li><strong>Q17-Q20:</strong> <?php esc_html_e('Externally Exposed Builder (External Factors)', 'brst-engine'); ?></li>
                    <li><strong>Q21:</strong> <?php esc_html_e('Focus Area (for personalization, not scored)', 'brst-engine'); ?></li>
                </ul>
            </div>

            <form method="post" id="brst-questions-form">
                <?php wp_nonce_field('brst_save_questions', 'brst_questions_nonce'); ?>

                <?php
                $question_num = 1;
                $category_index = 0;
                $categories_array = array_keys($this->categories);

                foreach ($questions as $index => $question):
                    $category = $question['category'];
                    $category_name = $this->categories[$category] ?? $category;

                    // Show category header
                    if ($question_num === 1 || $question_num === 5 || $question_num === 9 || $question_num === 13 || $question_num === 17 || $question_num === 21):
                ?>
                    <div class="brst-category-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                        <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                            <?php echo esc_html($category_name); ?>
                            <?php if ($question_num <= 20): ?>
                                <span style="font-size: 14px; font-weight: normal; color: #666;">
                                    (Q<?php echo $question_num; ?>-Q<?php echo min($question_num + 3, 20); ?>)
                                </span>
                            <?php endif; ?>
                        </h2>
                <?php endif; ?>

                        <div class="brst-question-item" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                <?php printf(__('Question %d', 'brst-engine'), $question_num); ?>
                                <?php if (!$question['scored']): ?>
                                    <span style="font-weight: normal; color: #666;">(<?php esc_html_e('Not Scored', 'brst-engine'); ?>)</span>
                                <?php endif; ?>
                            </label>
                            <input type="hidden" name="questions[<?php echo $index; ?>][category]" value="<?php echo esc_attr($category); ?>">
                            <input type="hidden" name="questions[<?php echo $index; ?>][scored]" value="<?php echo $question['scored'] ? '1' : '0'; ?>">
                            <textarea name="questions[<?php echo $index; ?>][text]" rows="2" class="large-text" style="width: 100%;"><?php echo esc_textarea($question['text']); ?></textarea>
                        </div>

                <?php
                    // Close category section
                    if ($question_num === 4 || $question_num === 8 || $question_num === 12 || $question_num === 16 || $question_num === 20 || $question_num === 21):
                ?>
                    </div>
                <?php
                    endif;
                    $question_num++;
                endforeach;
                ?>

                <!-- Q21 Options Editor -->
                <div class="brst-category-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        <?php esc_html_e('Question 21 Options', 'brst-engine'); ?>
                    </h2>
                    <p class="description"><?php esc_html_e('These are the answer options for Question 21 (Focus Area).', 'brst-engine'); ?></p>

                    <?php
                    $q21_options = get_option('brst_q21_options', $this->get_default_q21_options());
                    foreach ($q21_options as $key => $option):
                    ?>
                        <div style="margin-bottom: 10px;">
                            <label style="display: inline-block; width: 50px; font-weight: 600;">
                                <?php echo esc_html(strtoupper($key)); ?>:
                            </label>
                            <input type="text" name="q21_options[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($option); ?>" class="regular-text" style="width: calc(100% - 60px);">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Answer Labels -->
                <div class="brst-category-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        <?php esc_html_e('Answer Labels (Q1-Q20)', 'brst-engine'); ?>
                    </h2>
                    <p class="description"><?php esc_html_e('Customize the answer labels for scored questions.', 'brst-engine'); ?></p>

                    <?php
                    $answer_labels = get_option('brst_answer_labels', array(
                        'yes' => __('Yes', 'brst-engine'),
                        'maybe' => __('Maybe', 'brst-engine'),
                        'no' => __('No', 'brst-engine'),
                    ));
                    ?>
                    <table class="form-table" style="margin: 0;">
                        <tr>
                            <th style="width: 150px;"><?php esc_html_e('Yes (Score: 0)', 'brst-engine'); ?></th>
                            <td><input type="text" name="answer_labels[yes]" value="<?php echo esc_attr($answer_labels['yes']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Maybe (Score: 2)', 'brst-engine'); ?></th>
                            <td><input type="text" name="answer_labels[maybe]" value="<?php echo esc_attr($answer_labels['maybe']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('No (Score: 3)', 'brst-engine'); ?></th>
                            <td><input type="text" name="answer_labels[no]" value="<?php echo esc_attr($answer_labels['no']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="brst_save_questions" class="button button-primary" value="<?php esc_attr_e('Save Questions', 'brst-engine'); ?>">
                    <input type="submit" name="brst_reset_questions" class="button" value="<?php esc_attr_e('Reset to Default', 'brst-engine'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all questions to default?', 'brst-engine'); ?>');">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Get questions (custom or default)
     */
    public function get_questions() {
        $custom = get_option('brst_custom_questions');
        if (!empty($custom) && is_array($custom)) {
            return $custom;
        }
        return $this->get_default_questions();
    }

    /**
     * Get default questions
     */
    private function get_default_questions() {
        return array(
            // Cash-Tight Operator (Q1-Q4)
            array('text' => __('Do you have consistent cash flow throughout the month?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Can you comfortably cover 3 months of operating expenses with current cash reserves?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Do you have a reliable system for tracking accounts receivable?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Are your payment terms with suppliers flexible enough to manage cash flow gaps?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),

            // Revenue-Concentrated Builder (Q5-Q8)
            array('text' => __('Is your revenue spread across multiple clients or customers?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Do you have multiple products or services generating income?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Are you actively developing new revenue streams?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Would your business survive if your largest client left?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),

            // Cost-Locked Business (Q9-Q12)
            array('text' => __('Can you quickly reduce operating costs if revenue drops?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Are most of your contracts and commitments flexible or short-term?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Do you regularly review and optimize your cost structure?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Can your business operate with a leaner team if needed?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),

            // Owner-Dependent Engine (Q13-Q16)
            array('text' => __('Can your business operate effectively when you are away?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Do you have documented processes that others can follow?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Is there someone who can make key decisions in your absence?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Are client relationships managed by the team, not just you?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),

            // Externally Exposed Builder (Q17-Q20)
            array('text' => __('Is your business protected against major supplier disruptions?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Are you prepared for regulatory changes in your industry?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Does your business have strategies for economic downturns?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Are you insulated from major market or technology shifts?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),

            // Question 21 - Personalization
            array('text' => __('What is your current primary focus area?', 'brst-engine'), 'category' => 'personalization', 'scored' => false),
        );
    }

    /**
     * Get default Q21 options
     */
    private function get_default_q21_options() {
        return array(
            'a' => __('Sales and revenue growth', 'brst-engine'),
            'b' => __('Cost reduction and efficiency', 'brst-engine'),
            'c' => __('Cash flow management', 'brst-engine'),
            'd' => __('Operations and processes', 'brst-engine'),
            'e' => __('External factors and market positioning', 'brst-engine'),
        );
    }

    /**
     * Save questions
     */
    private function save_questions() {
        $questions = array();

        if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $question_data) {
                $questions[] = array(
                    'text' => sanitize_textarea_field($question_data['text'] ?? ''),
                    'category' => sanitize_key($question_data['category'] ?? ''),
                    'scored' => !empty($question_data['scored']),
                );
            }
        }

        // Save questions
        update_option('brst_custom_questions', $questions);

        // Save Q21 options
        if (!empty($_POST['q21_options']) && is_array($_POST['q21_options'])) {
            $q21_options = array();
            foreach ($_POST['q21_options'] as $key => $value) {
                $q21_options[sanitize_key($key)] = sanitize_text_field($value);
            }
            update_option('brst_q21_options', $q21_options);
        }

        // Save answer labels
        if (!empty($_POST['answer_labels']) && is_array($_POST['answer_labels'])) {
            $answer_labels = array();
            foreach ($_POST['answer_labels'] as $key => $value) {
                $answer_labels[sanitize_key($key)] = sanitize_text_field($value);
            }
            update_option('brst_answer_labels', $answer_labels);
        }

        add_settings_error('brst_questions', 'questions_saved', __('Questions saved successfully.', 'brst-engine'), 'updated');
    }
}
