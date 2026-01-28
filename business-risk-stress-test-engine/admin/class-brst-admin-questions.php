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
     * Scored categories
     */
    private $scored_categories = array(
        'cash_tight' => 'Cash-Tight Operator',
        'revenue_concentrated' => 'Revenue-Concentrated Builder',
        'cost_locked' => 'Cost-Locked Business',
        'owner_dependent' => 'Owner-Dependent Engine',
        'externally_exposed' => 'Externally Exposed Builder',
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
            delete_option('brst_q21_options');
            delete_option('brst_answer_labels');
            add_settings_error('brst_questions', 'questions_reset', __('Questions reset to default.', 'brst-engine'), 'updated');
        }

        $questions = $this->get_questions();

        // Group questions by category for display
        $grouped = array();
        foreach ($questions as $index => $q) {
            $cat = $q['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = array();
            }
            $grouped[$cat][] = array_merge($q, array('original_index' => $index));
        }

        $all_categories = array_merge($this->scored_categories, array('personalization' => 'Focus Area (Not Scored)'));
        ?>
        <div class="wrap brst-admin-wrap brst-questions-editor">
            <h1><?php esc_html_e('Edit Questions', 'brst-engine'); ?></h1>

            <?php settings_errors('brst_questions'); ?>

            <div class="brst-questions-info" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0;"><?php esc_html_e('How It Works', 'brst-engine'); ?></h4>
                <p><?php esc_html_e('Questions are grouped into risk categories. Each scored question contributes to its category score (Yes=0, Maybe=2, No=3 points). You can add, remove, or edit questions in each category. The last category is for personalization questions which are not scored.', 'brst-engine'); ?></p>
            </div>

            <form method="post" id="brst-questions-form">
                <?php wp_nonce_field('brst_save_questions', 'brst_questions_nonce'); ?>

                <div id="brst-questions-container">
                    <?php
                    $global_index = 0;
                    foreach ($all_categories as $cat_key => $cat_name):
                        $cat_questions = $grouped[$cat_key] ?? array();
                        $is_scored = ($cat_key !== 'personalization');
                    ?>
                    <div class="brst-category-section" data-category="<?php echo esc_attr($cat_key); ?>" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                        <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                            <?php echo esc_html($cat_name); ?>
                            <span style="font-size: 14px; font-weight: normal; color: #666;">
                                (<?php echo count($cat_questions); ?> <?php echo count($cat_questions) === 1 ? 'question' : 'questions'; ?>)
                            </span>
                        </h2>

                        <div class="brst-category-questions" data-category="<?php echo esc_attr($cat_key); ?>">
                            <?php foreach ($cat_questions as $cq_index => $cq):
                                $global_index++;
                            ?>
                            <div class="brst-question-item" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; position: relative;">
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span class="brst-question-number" style="background: #0073aa; color: #fff; border-radius: 50%; min-width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; margin-top: 2px;">
                                        Q<?php echo $global_index; ?>
                                    </span>
                                    <div style="flex: 1;">
                                        <input type="hidden" name="questions[<?php echo esc_attr($global_index - 1); ?>][category]" value="<?php echo esc_attr($cat_key); ?>">
                                        <input type="hidden" name="questions[<?php echo esc_attr($global_index - 1); ?>][scored]" value="<?php echo $is_scored ? '1' : '0'; ?>">
                                        <textarea name="questions[<?php echo esc_attr($global_index - 1); ?>][text]" rows="2" class="large-text" style="width: 100%;"><?php echo esc_textarea($cq['text']); ?></textarea>
                                    </div>
                                    <button type="button" class="button brst-remove-question" title="<?php esc_attr_e('Remove this question', 'brst-engine'); ?>" style="color: #dc3545; border-color: #dc3545;" onclick="if(confirm('Remove this question?')) { this.closest('.brst-question-item').remove(); brstReindexQuestions(); }">
                                        &times;
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="button brst-add-question" data-category="<?php echo esc_attr($cat_key); ?>" data-scored="<?php echo $is_scored ? '1' : '0'; ?>" style="margin-top: 10px;">
                            + <?php printf(esc_html__('Add Question to %s', 'brst-engine'), esc_html($cat_name)); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Q21 Options Editor -->
                <div class="brst-category-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                    <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">
                        <?php esc_html_e('Focus Area Options', 'brst-engine'); ?>
                    </h2>
                    <p class="description"><?php esc_html_e('These are the multiple-choice answer options for the Focus Area (personalization) questions.', 'brst-engine'); ?></p>

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
                        <?php esc_html_e('Answer Labels (Scored Questions)', 'brst-engine'); ?>
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
                    <input type="submit" name="brst_reset_questions" class="button" value="<?php esc_attr_e('Reset to Default', 'brst-engine'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all questions to default? This cannot be undone.', 'brst-engine'); ?>');">
                </p>
            </form>
        </div>

        <script>
        (function() {
            // Add question handler
            document.querySelectorAll('.brst-add-question').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var category = this.getAttribute('data-category');
                    var scored = this.getAttribute('data-scored');
                    var container = this.closest('.brst-category-section').querySelector('.brst-category-questions');
                    var index = document.querySelectorAll('.brst-question-item').length;

                    var html = '<div class="brst-question-item" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; position: relative;">' +
                        '<div style="display: flex; align-items: flex-start; gap: 10px;">' +
                        '<span class="brst-question-number" style="background: #0073aa; color: #fff; border-radius: 50%; min-width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; margin-top: 2px;">NEW</span>' +
                        '<div style="flex: 1;">' +
                        '<input type="hidden" name="questions[' + index + '][category]" value="' + category + '">' +
                        '<input type="hidden" name="questions[' + index + '][scored]" value="' + scored + '">' +
                        '<textarea name="questions[' + index + '][text]" rows="2" class="large-text" style="width: 100%;" placeholder="Enter your question here..."></textarea>' +
                        '</div>' +
                        '<button type="button" class="button brst-remove-question" title="Remove" style="color: #dc3545; border-color: #dc3545;" onclick="if(confirm(\'Remove this question?\')) { this.closest(\'.brst-question-item\').remove(); brstReindexQuestions(); }">&times;</button>' +
                        '</div></div>';

                    container.insertAdjacentHTML('beforeend', html);
                    brstReindexQuestions();

                    // Focus the new textarea
                    container.querySelector('.brst-question-item:last-child textarea').focus();
                });
            });

            // Re-index all questions globally
            window.brstReindexQuestions = function() {
                var allItems = document.querySelectorAll('.brst-question-item');
                var globalNum = 0;

                allItems.forEach(function(item) {
                    globalNum++;
                    // Update number badge
                    var badge = item.querySelector('.brst-question-number');
                    if (badge) badge.textContent = 'Q' + globalNum;

                    // Update input names
                    var inputs = item.querySelectorAll('input[type="hidden"], textarea');
                    inputs.forEach(function(input) {
                        var name = input.getAttribute('name');
                        if (name) {
                            input.setAttribute('name', name.replace(/questions\[\d+\]/, 'questions[' + (globalNum - 1) + ']'));
                        }
                    });
                });

                // Update category counts
                document.querySelectorAll('.brst-category-section[data-category]').forEach(function(section) {
                    var count = section.querySelectorAll('.brst-question-item').length;
                    var countSpan = section.querySelector('h2 span');
                    if (countSpan) {
                        countSpan.textContent = '(' + count + ' ' + (count === 1 ? 'question' : 'questions') + ')';
                    }
                });
            };
        })();
        </script>
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
            array('text' => __('Do you have consistent cash flow throughout the month?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Can you comfortably cover 3 months of operating expenses with current cash reserves?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Do you have a reliable system for tracking accounts receivable?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),
            array('text' => __('Are your payment terms with suppliers flexible enough to manage cash flow gaps?', 'brst-engine'), 'category' => 'cash_tight', 'scored' => true),

            array('text' => __('Is your revenue spread across multiple clients or customers?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Do you have multiple products or services generating income?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Are you actively developing new revenue streams?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),
            array('text' => __('Would your business survive if your largest client left?', 'brst-engine'), 'category' => 'revenue_concentrated', 'scored' => true),

            array('text' => __('Can you quickly reduce operating costs if revenue drops?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Are most of your contracts and commitments flexible or short-term?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Do you regularly review and optimize your cost structure?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),
            array('text' => __('Can your business operate with a leaner team if needed?', 'brst-engine'), 'category' => 'cost_locked', 'scored' => true),

            array('text' => __('Can your business operate effectively when you are away?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Do you have documented processes that others can follow?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Is there someone who can make key decisions in your absence?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),
            array('text' => __('Are client relationships managed by the team, not just you?', 'brst-engine'), 'category' => 'owner_dependent', 'scored' => true),

            array('text' => __('Is your business protected against major supplier disruptions?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Are you prepared for regulatory changes in your industry?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Does your business have strategies for economic downturns?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),
            array('text' => __('Are you insulated from major market or technology shifts?', 'brst-engine'), 'category' => 'externally_exposed', 'scored' => true),

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
            // Sort by keys to maintain order
            ksort($_POST['questions']);

            foreach ($_POST['questions'] as $question_data) {
                $text = sanitize_textarea_field($question_data['text'] ?? '');
                if (empty(trim($text))) {
                    continue; // Skip empty questions
                }

                $questions[] = array(
                    'text' => $text,
                    'category' => sanitize_key($question_data['category'] ?? ''),
                    'scored' => !empty($question_data['scored']),
                );
            }
        }

        if (empty($questions)) {
            add_settings_error('brst_questions', 'no_questions', __('You must have at least one question. Changes were not saved.', 'brst-engine'), 'error');
            return;
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

        add_settings_error('brst_questions', 'questions_saved', sprintf(__('Questions saved successfully. Total: %d questions.', 'brst-engine'), count($questions)), 'updated');
    }
}
