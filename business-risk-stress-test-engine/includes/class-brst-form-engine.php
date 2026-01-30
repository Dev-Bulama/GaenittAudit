<?php
/**
 * Form Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Engine class for building and rendering forms
 */
class BRST_Form_Engine {

    /**
     * Supported field types
     */
    private $field_types = array(
        'radio',
        'checkbox',
        'email',
        'hidden',
        'consent',
        'button',
        'text',
        'textarea',
        'select',
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
    }

    /**
     * Register custom post type for forms (optional, for future expansion)
     */
    public function register_post_type() {
        // Forms are stored in custom table, but we register a post type for potential future use
        register_post_type('brst_form', array(
            'public' => false,
            'show_ui' => false,
        ));
    }

    /**
     * Get default questionnaire form schema
     */
    public function get_default_questionnaire_schema() {
        $questions = $this->get_questions();
        $steps = array();

        // Group questions by category dynamically
        $category_groups = array();

        // Default step titles
        $default_step_titles = array(
            'cash_tight' => __('Cash Flow Assessment', 'brst-engine'),
            'revenue_concentrated' => __('Revenue Diversity', 'brst-engine'),
            'cost_locked' => __('Cost Structure', 'brst-engine'),
            'owner_dependent' => __('Business Dependency', 'brst-engine'),
            'externally_exposed' => __('External Exposure', 'brst-engine'),
            'personalization' => __('Your Focus Area', 'brst-engine'),
        );

        // Get custom step titles from admin settings
        $custom_step_titles = get_option('brst_step_titles', array());

        // Merge custom titles with defaults (custom override defaults)
        $category_names = array();
        foreach ($default_step_titles as $key => $default_title) {
            $custom_title = isset($custom_step_titles[$key]) ? trim($custom_step_titles[$key]) : '';
            // Use custom title if set, otherwise use default (empty string means hide title)
            $category_names[$key] = $custom_title !== '' ? $custom_title : $default_title;
        }

        foreach ($questions as $index => $question) {
            $cat = $question['category'];
            if (!isset($category_groups[$cat])) {
                $category_groups[$cat] = array();
            }
            $category_groups[$cat][] = array(
                'index' => $index,
                'question' => $question,
            );
        }

        // Create one step per category
        $step_count = 0;
        foreach ($category_groups as $category_key => $cat_questions) {
            $fields = array();
            foreach ($cat_questions as $entry) {
                $q_num = $entry['index'] + 1;
                $question = $entry['question'];

                $field = array(
                    'id' => "q{$q_num}",
                    'type' => 'radio',
                    'label' => $question['text'],
                    'required' => true,
                    'options' => $this->get_question_options($q_num, $question),
                    'category' => $question['category'],
                    'scored' => $question['scored'],
                );

                $fields[] = $field;
            }

            $step_title = $category_names[$category_key]
                ?? ucwords(str_replace('_', ' ', $category_key));

            $steps[] = array(
                'id' => "step_{$step_count}",
                'title' => $step_title,
                'fields' => $fields,
            );

            $step_count++;
        }

        // Get customizable settings from options
        $form_title = get_option('brst_form_title', __('Business Risk Stress Test', 'brst-engine'));
        $form_description = get_option('brst_form_description', __('Answer the following questions to assess your business risk profile.', 'brst-engine'));
        $submit_text = get_option('brst_submit_button_text', __('Get My Results', 'brst-engine'));

        return array(
            'form_id' => 'business_risk_questionnaire',
            'title' => $form_title,
            'description' => $form_description,
            'multi_step' => true,
            'steps' => $steps,
            'settings' => array(
                'show_progress' => true,
                'allow_back' => true,
                'submit_text' => $submit_text,
                'redirect_after_submit' => false,
                'show_results_on_screen' => true,
            ),
        );
    }

    /**
     * Get all questions (custom or default)
     */
    public function get_questions() {
        // Check for custom questions saved from admin
        $custom = get_option('brst_custom_questions');
        if (!empty($custom) && is_array($custom) && count($custom) >= 1) {
            return $custom;
        }

        return $this->get_default_questions();
    }

    /**
     * Get default 21 questions
     */
    private function get_default_questions() {
        return array(
            // Cash-Tight Operator (Q1-Q4)
            array(
                'text' => 'Do you have consistent cash flow throughout the month?',
                'category' => 'cash_tight',
                'scored' => true,
            ),
            array(
                'text' => 'Can you comfortably cover 3 months of operating expenses with current cash reserves?',
                'category' => 'cash_tight',
                'scored' => true,
            ),
            array(
                'text' => 'Do you have a reliable system for tracking accounts receivable?',
                'category' => 'cash_tight',
                'scored' => true,
            ),
            array(
                'text' => 'Are your payment terms with suppliers flexible enough to manage cash flow gaps?',
                'category' => 'cash_tight',
                'scored' => true,
            ),

            // Revenue-Concentrated Builder (Q5-Q8)
            array(
                'text' => 'Is your revenue spread across multiple clients or customers?',
                'category' => 'revenue_concentrated',
                'scored' => true,
            ),
            array(
                'text' => 'Do you have multiple products or services generating income?',
                'category' => 'revenue_concentrated',
                'scored' => true,
            ),
            array(
                'text' => 'Are you actively developing new revenue streams?',
                'category' => 'revenue_concentrated',
                'scored' => true,
            ),
            array(
                'text' => 'Would your business survive if your largest client left?',
                'category' => 'revenue_concentrated',
                'scored' => true,
            ),

            // Cost-Locked Business (Q9-Q12)
            array(
                'text' => 'Can you quickly reduce operating costs if revenue drops?',
                'category' => 'cost_locked',
                'scored' => true,
            ),
            array(
                'text' => 'Are most of your contracts and commitments flexible or short-term?',
                'category' => 'cost_locked',
                'scored' => true,
            ),
            array(
                'text' => 'Do you regularly review and optimize your cost structure?',
                'category' => 'cost_locked',
                'scored' => true,
            ),
            array(
                'text' => 'Can your business operate with a leaner team if needed?',
                'category' => 'cost_locked',
                'scored' => true,
            ),

            // Owner-Dependent Engine (Q13-Q16)
            array(
                'text' => 'Can your business operate effectively when you are away?',
                'category' => 'owner_dependent',
                'scored' => true,
            ),
            array(
                'text' => 'Do you have documented processes that others can follow?',
                'category' => 'owner_dependent',
                'scored' => true,
            ),
            array(
                'text' => 'Is there someone who can make key decisions in your absence?',
                'category' => 'owner_dependent',
                'scored' => true,
            ),
            array(
                'text' => 'Are client relationships managed by the team, not just you?',
                'category' => 'owner_dependent',
                'scored' => true,
            ),

            // Externally Exposed Builder (Q17-Q20)
            array(
                'text' => 'Is your business protected against major supplier disruptions?',
                'category' => 'externally_exposed',
                'scored' => true,
            ),
            array(
                'text' => 'Are you prepared for regulatory changes in your industry?',
                'category' => 'externally_exposed',
                'scored' => true,
            ),
            array(
                'text' => 'Does your business have strategies for economic downturns?',
                'category' => 'externally_exposed',
                'scored' => true,
            ),
            array(
                'text' => 'Are you insulated from major market or technology shifts?',
                'category' => 'externally_exposed',
                'scored' => true,
            ),

            // Question 21 - Personalization only
            array(
                'text' => 'What is your current primary focus area?',
                'category' => 'personalization',
                'scored' => false,
            ),
        );
    }

    /**
     * Get question options based on question data
     */
    private function get_question_options($q_num, $question = null) {
        // Determine if this is an unscored/personalization question
        $is_unscored = ($question && empty($question['scored']));

        if ($is_unscored) {
            // Personalization question - check for custom options
            $custom_q21 = get_option('brst_q21_options');
            if (!empty($custom_q21) && is_array($custom_q21)) {
                $options = array();
                foreach ($custom_q21 as $key => $label) {
                    $options[] = array('value' => $key, 'label' => $label);
                }
                return $options;
            }

            return array(
                array('value' => 'a', 'label' => 'Sales and revenue growth'),
                array('value' => 'b', 'label' => 'Cost reduction and efficiency'),
                array('value' => 'c', 'label' => 'Cash flow management'),
                array('value' => 'd', 'label' => 'Operations and processes'),
                array('value' => 'e', 'label' => 'External factors and market positioning'),
            );
        }

        // Standard scoring options - check for custom labels
        $custom_labels = get_option('brst_answer_labels');
        if (!empty($custom_labels) && is_array($custom_labels)) {
            return array(
                array('value' => 'yes', 'label' => $custom_labels['yes'] ?? 'Yes', 'score' => 0),
                array('value' => 'maybe', 'label' => $custom_labels['maybe'] ?? 'Maybe', 'score' => 2),
                array('value' => 'no', 'label' => $custom_labels['no'] ?? 'No', 'score' => 3),
            );
        }

        return array(
            array('value' => 'yes', 'label' => 'Yes', 'score' => 0),
            array('value' => 'maybe', 'label' => 'Maybe', 'score' => 2),
            array('value' => 'no', 'label' => 'No', 'score' => 3),
        );
    }

    /**
     * Get the total number of questions
     */
    public function get_question_count() {
        return count($this->get_questions());
    }

    /**
     * Render a form
     */
    public function render_form($form_schema = null, $attributes = array()) {
        if (!$form_schema) {
            $form_schema = $this->get_default_questionnaire_schema();
        }

        /**
         * Filter: brst_form_schema
         * Allows modification of form schema before rendering
         */
        $form_schema = apply_filters('brst_form_schema', $form_schema);

        $session_id = $this->generate_session_id();

        ob_start();
        ?>
        <div class="brst-form-container" data-form-id="<?php echo esc_attr($form_schema['form_id']); ?>">
            <form id="brst-questionnaire-form" class="brst-form" data-session-id="<?php echo esc_attr($session_id); ?>">
                <?php wp_nonce_field('brst_form_submit', 'brst_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_schema['form_id']); ?>">
                <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">

                <?php if (!empty($form_schema['title'])): ?>
                    <h2 class="brst-form-title"><?php echo esc_html($form_schema['title']); ?></h2>
                <?php endif; ?>

                <?php if (!empty($form_schema['description'])): ?>
                    <p class="brst-form-description"><?php echo esc_html($form_schema['description']); ?></p>
                <?php endif; ?>

                <?php if ($form_schema['multi_step'] && !empty($form_schema['settings']['show_progress'])): ?>
                    <div class="brst-progress-bar">
                        <div class="brst-progress-track">
                            <div class="brst-progress-fill" style="width: 0%"></div>
                        </div>
                        <span class="brst-progress-text">Step 1 of <?php echo count($form_schema['steps']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($form_schema['multi_step']): ?>
                    <?php foreach ($form_schema['steps'] as $step_index => $step): ?>
                        <div class="brst-form-step <?php echo $step_index === 0 ? 'active' : ''; ?>"
                             data-step="<?php echo $step_index; ?>">
                            <?php if (!empty($step['title'])): ?>
                                <h3 class="brst-step-title"><?php echo esc_html($step['title']); ?></h3>
                            <?php endif; ?>

                            <?php foreach ($step['fields'] as $field): ?>
                                <?php $this->render_field($field); ?>
                            <?php endforeach; ?>

                            <div class="brst-step-navigation">
                                <?php if ($step_index > 0 && !empty($form_schema['settings']['allow_back'])): ?>
                                    <button type="button" class="brst-btn brst-btn-secondary brst-prev-step">
                                        <?php esc_html_e('Previous', 'brst-engine'); ?>
                                    </button>
                                <?php endif; ?>

                                <?php if ($step_index < count($form_schema['steps']) - 1): ?>
                                    <button type="button" class="brst-btn brst-btn-primary brst-next-step">
                                        <?php esc_html_e('Next', 'brst-engine'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="brst-btn brst-btn-primary brst-submit">
                                        <?php echo esc_html($form_schema['settings']['submit_text'] ?? __('Submit', 'brst-engine')); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($form_schema['fields'] ?? array() as $field): ?>
                        <?php $this->render_field($field); ?>
                    <?php endforeach; ?>
                    <button type="submit" class="brst-btn brst-btn-primary brst-submit">
                        <?php echo esc_html($form_schema['settings']['submit_text'] ?? __('Submit', 'brst-engine')); ?>
                    </button>
                <?php endif; ?>

                <div class="brst-form-messages"></div>
            </form>

            <div class="brst-loading" style="display: none;">
                <div class="brst-spinner"></div>
                <p><?php esc_html_e('Processing your responses...', 'brst-engine'); ?></p>
            </div>

            <div class="brst-results-container" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single field
     */
    public function render_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';
        $required_indicator = !empty($field['required']) ? '<span class="brst-required">*</span>' : '';

        /**
         * Filter: brst_field_render
         * Allows modification of field before rendering
         */
        $field = apply_filters('brst_field_render', $field);

        ?>
        <div class="brst-field brst-field-<?php echo esc_attr($field['type']); ?>" data-field-id="<?php echo $field_id; ?>">
            <?php if (!empty($field['label']) && $field['type'] !== 'hidden'): ?>
                <label class="brst-field-label">
                    <?php echo esc_html($field['label']); ?>
                    <?php echo $required_indicator; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($field['type']) {
                case 'radio':
                    $this->render_radio_field($field);
                    break;
                case 'checkbox':
                    $this->render_checkbox_field($field);
                    break;
                case 'email':
                    $this->render_email_field($field);
                    break;
                case 'hidden':
                    $this->render_hidden_field($field);
                    break;
                case 'consent':
                    $this->render_consent_field($field);
                    break;
                case 'text':
                    $this->render_text_field($field);
                    break;
                case 'textarea':
                    $this->render_textarea_field($field);
                    break;
                case 'select':
                    $this->render_select_field($field);
                    break;
            }
            ?>

            <?php if (!empty($field['description'])): ?>
                <p class="brst-field-description"><?php echo esc_html($field['description']); ?></p>
            <?php endif; ?>

            <div class="brst-field-error"></div>
        </div>
        <?php
    }

    /**
     * Render radio field
     */
    private function render_radio_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';

        ?>
        <div class="brst-radio-group">
            <?php foreach ($field['options'] as $option): ?>
                <label class="brst-radio-option">
                    <input type="radio"
                           name="<?php echo $field_id; ?>"
                           value="<?php echo esc_attr($option['value']); ?>"
                           <?php echo $required; ?>
                           data-score="<?php echo isset($option['score']) ? esc_attr($option['score']) : ''; ?>">
                    <span class="brst-radio-label"><?php echo esc_html($option['label']); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    private function render_checkbox_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';

        if (!empty($field['options'])) {
            // Multiple checkboxes
            ?>
            <div class="brst-checkbox-group">
                <?php foreach ($field['options'] as $option): ?>
                    <label class="brst-checkbox-option">
                        <input type="checkbox"
                               name="<?php echo $field_id; ?>[]"
                               value="<?php echo esc_attr($option['value']); ?>">
                        <span class="brst-checkbox-label"><?php echo esc_html($option['label']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php
        } else {
            // Single checkbox
            ?>
            <label class="brst-checkbox-single">
                <input type="checkbox"
                       name="<?php echo $field_id; ?>"
                       value="1"
                       <?php echo $required; ?>>
                <span class="brst-checkbox-label"><?php echo esc_html($field['checkbox_label'] ?? ''); ?></span>
            </label>
            <?php
        }
    }

    /**
     * Render email field
     */
    private function render_email_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';
        $placeholder = !empty($field['placeholder']) ? esc_attr($field['placeholder']) : '';

        ?>
        <input type="email"
               id="<?php echo $field_id; ?>"
               name="<?php echo $field_id; ?>"
               class="brst-input brst-email-input"
               placeholder="<?php echo $placeholder; ?>"
               <?php echo $required; ?>>
        <?php
    }

    /**
     * Render hidden field
     */
    private function render_hidden_field($field) {
        $field_id = esc_attr($field['id']);
        $value = esc_attr($field['value'] ?? '');

        ?>
        <input type="hidden"
               id="<?php echo $field_id; ?>"
               name="<?php echo $field_id; ?>"
               value="<?php echo $value; ?>">
        <?php
    }

    /**
     * Render consent field
     */
    private function render_consent_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';

        ?>
        <label class="brst-consent-checkbox">
            <input type="checkbox"
                   name="<?php echo $field_id; ?>"
                   value="1"
                   <?php echo $required; ?>>
            <span class="brst-consent-text">
                <?php echo wp_kses_post($field['consent_text'] ?? ''); ?>
            </span>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    private function render_text_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';
        $placeholder = !empty($field['placeholder']) ? esc_attr($field['placeholder']) : '';

        ?>
        <input type="text"
               id="<?php echo $field_id; ?>"
               name="<?php echo $field_id; ?>"
               class="brst-input brst-text-input"
               placeholder="<?php echo $placeholder; ?>"
               <?php echo $required; ?>>
        <?php
    }

    /**
     * Render textarea field
     */
    private function render_textarea_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';
        $placeholder = !empty($field['placeholder']) ? esc_attr($field['placeholder']) : '';
        $rows = !empty($field['rows']) ? intval($field['rows']) : 4;

        ?>
        <textarea id="<?php echo $field_id; ?>"
                  name="<?php echo $field_id; ?>"
                  class="brst-textarea"
                  placeholder="<?php echo $placeholder; ?>"
                  rows="<?php echo $rows; ?>"
                  <?php echo $required; ?>></textarea>
        <?php
    }

    /**
     * Render select field
     */
    private function render_select_field($field) {
        $field_id = esc_attr($field['id']);
        $required = !empty($field['required']) ? 'required' : '';

        ?>
        <select id="<?php echo $field_id; ?>"
                name="<?php echo $field_id; ?>"
                class="brst-select"
                <?php echo $required; ?>>
            <option value=""><?php echo esc_html($field['placeholder'] ?? __('Select an option', 'brst-engine')); ?></option>
            <?php foreach ($field['options'] ?? array() as $option): ?>
                <option value="<?php echo esc_attr($option['value']); ?>">
                    <?php echo esc_html($option['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Generate unique session ID
     */
    public function generate_session_id() {
        return wp_generate_uuid4();
    }

    /**
     * Validate form submission
     */
    public function validate_submission($data, $form_schema = null) {
        if (!$form_schema) {
            $form_schema = $this->get_default_questionnaire_schema();
        }

        $errors = array();

        // Collect all fields from steps or direct fields
        $all_fields = array();
        if (!empty($form_schema['steps'])) {
            foreach ($form_schema['steps'] as $step) {
                $all_fields = array_merge($all_fields, $step['fields']);
            }
        } else {
            $all_fields = $form_schema['fields'] ?? array();
        }

        // Validate each required field
        foreach ($all_fields as $field) {
            if (!empty($field['required'])) {
                $field_id = $field['id'];
                if (empty($data[$field_id])) {
                    $errors[$field_id] = sprintf(
                        __('"%s" is required.', 'brst-engine'),
                        $field['label'] ?? $field_id
                    );
                }
            }

            // Email validation
            if ($field['type'] === 'email' && !empty($data[$field['id']])) {
                if (!is_email($data[$field['id']])) {
                    $errors[$field['id']] = __('Please enter a valid email address.', 'brst-engine');
                }
            }
        }

        /**
         * Filter: brst_form_validation_errors
         * Allows adding custom validation errors
         */
        return apply_filters('brst_form_validation_errors', $errors, $data, $form_schema);
    }

    /**
     * Save form to database
     */
    public function save_form($form_data) {
        global $wpdb;
        $table = BRST_Database::get_table_name('forms');

        $data = array(
            'title' => sanitize_text_field($form_data['title'] ?? ''),
            'description' => sanitize_textarea_field($form_data['description'] ?? ''),
            'form_schema' => wp_json_encode($form_data['schema'] ?? array()),
            'settings' => wp_json_encode($form_data['settings'] ?? array()),
            'status' => sanitize_text_field($form_data['status'] ?? 'active'),
        );

        if (!empty($form_data['id'])) {
            $wpdb->update($table, $data, array('id' => intval($form_data['id'])));
            return intval($form_data['id']);
        }

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Get form by ID
     */
    public function get_form($form_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('forms');

        $form = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $form_id)
        );

        if ($form) {
            $form->form_schema = json_decode($form->form_schema, true);
            $form->settings = json_decode($form->settings, true);
        }

        return $form;
    }

    /**
     * Get all forms
     */
    public function get_forms($args = array()) {
        global $wpdb;
        $table = BRST_Database::get_table_name('forms');

        $defaults = array(
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('%s %s', esc_sql($args['orderby']), esc_sql($args['order']));

        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_clause LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $forms = $wpdb->get_results($wpdb->prepare($query, $values));

        foreach ($forms as &$form) {
            $form->form_schema = json_decode($form->form_schema, true);
            $form->settings = json_decode($form->settings, true);
        }

        return $forms;
    }
}
