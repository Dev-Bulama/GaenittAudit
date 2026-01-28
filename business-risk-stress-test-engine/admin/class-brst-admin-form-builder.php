<?php
/**
 * Admin Form Builder
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Form Builder class for drag-and-drop form building
 */
class BRST_Admin_Form_Builder {

    /**
     * Available field types
     */
    private $field_types = array(
        'text' => array(
            'label' => 'Text Field',
            'icon' => 'dashicons-editor-textcolor',
        ),
        'email' => array(
            'label' => 'Email Field',
            'icon' => 'dashicons-email',
        ),
        'textarea' => array(
            'label' => 'Text Area',
            'icon' => 'dashicons-text',
        ),
        'radio' => array(
            'label' => 'Radio Buttons',
            'icon' => 'dashicons-marker',
        ),
        'checkbox' => array(
            'label' => 'Checkboxes',
            'icon' => 'dashicons-yes',
        ),
        'select' => array(
            'label' => 'Dropdown',
            'icon' => 'dashicons-arrow-down-alt2',
        ),
    );

    /**
     * Render form builder page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['brst_save_form']) && wp_verify_nonce($_POST['brst_form_builder_nonce'], 'brst_save_form_builder')) {
            $this->save_custom_fields();
        }

        // Handle reset to default
        if (isset($_POST['brst_reset_form']) && wp_verify_nonce($_POST['brst_form_builder_nonce'], 'brst_save_form_builder')) {
            delete_option('brst_custom_user_fields');
            add_settings_error('brst_form_builder', 'form_reset', __('Form reset to default.', 'brst-engine'), 'updated');
        }

        $custom_fields = get_option('brst_custom_user_fields', $this->get_default_user_fields());
        ?>
        <div class="wrap brst-admin-wrap brst-form-builder">
            <h1><?php esc_html_e('Form Builder', 'brst-engine'); ?></h1>

            <?php settings_errors('brst_form_builder'); ?>

            <div class="brst-form-builder-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                <strong><?php esc_html_e('Note:', 'brst-engine'); ?></strong>
                <?php esc_html_e('The 21 questionnaire questions are fixed and cannot be modified. You can customize the user information fields that appear at the beginning of the form (name, email, etc.).', 'brst-engine'); ?>
            </div>

            <form method="post" id="brst-form-builder-form">
                <?php wp_nonce_field('brst_save_form_builder', 'brst_form_builder_nonce'); ?>

                <div class="brst-form-builder-container" style="display: flex; gap: 30px; margin-top: 20px;">
                    <!-- Field Palette -->
                    <div class="brst-field-palette" style="width: 250px; flex-shrink: 0;">
                        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px;">
                            <h3 style="margin-top: 0;"><?php esc_html_e('Add Fields', 'brst-engine'); ?></h3>
                            <p class="description"><?php esc_html_e('Click to add a field to the form.', 'brst-engine'); ?></p>

                            <div class="brst-field-types" style="margin-top: 15px;">
                                <?php foreach ($this->field_types as $type => $data): ?>
                                    <button type="button" class="brst-add-field button" data-type="<?php echo esc_attr($type); ?>" style="display: block; width: 100%; margin-bottom: 8px; text-align: left;">
                                        <span class="dashicons <?php echo esc_attr($data['icon']); ?>" style="margin-right: 8px;"></span>
                                        <?php echo esc_html($data['label']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Canvas -->
                    <div class="brst-form-canvas" style="flex: 1;">
                        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                            <h3 style="margin-top: 0;"><?php esc_html_e('User Information Fields', 'brst-engine'); ?></h3>
                            <p class="description"><?php esc_html_e('These fields appear at the start of the questionnaire. Drag to reorder.', 'brst-engine'); ?></p>

                            <div id="brst-fields-list" class="brst-fields-list" style="min-height: 200px; margin-top: 20px;">
                                <?php
                                foreach ($custom_fields as $index => $field) {
                                    $this->render_field_editor($field, $index);
                                }
                                ?>
                            </div>

                            <div class="brst-form-builder-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                                <input type="submit" name="brst_save_form" class="button button-primary" value="<?php esc_attr_e('Save Form', 'brst-engine'); ?>">
                                <input type="submit" name="brst_reset_form" class="button" value="<?php esc_attr_e('Reset to Default', 'brst-engine'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset the form to default?', 'brst-engine'); ?>');">
                            </div>
                        </div>

                        <!-- Preview Section -->
                        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
                            <h3 style="margin-top: 0;"><?php esc_html_e('Form Structure Preview', 'brst-engine'); ?></h3>
                            <div class="brst-form-preview-structure">
                                <ol style="margin: 0; padding-left: 20px;">
                                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                        <strong><?php esc_html_e('Step 1: User Information', 'brst-engine'); ?></strong>
                                        <span style="color: #666;"> - <?php esc_html_e('Custom fields you configure above', 'brst-engine'); ?></span>
                                    </li>
                                    <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                        <strong><?php esc_html_e('Step 2-5: Assessment Questions', 'brst-engine'); ?></strong>
                                        <span style="color: #666;"> - <?php esc_html_e('Q1-Q20 (4 questions per category)', 'brst-engine'); ?></span>
                                    </li>
                                    <li style="padding: 10px 0;">
                                        <strong><?php esc_html_e('Step 6: Focus Area', 'brst-engine'); ?></strong>
                                        <span style="color: #666;"> - <?php esc_html_e('Q21 (personalization question)', 'brst-engine'); ?></span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var fieldIndex = <?php echo count($custom_fields); ?>;

            // Make fields sortable
            $('#brst-fields-list').sortable({
                handle: '.brst-field-handle',
                placeholder: 'brst-field-placeholder',
                update: function(event, ui) {
                    updateFieldIndexes();
                }
            });

            // Add new field
            $('.brst-add-field').on('click', function() {
                var type = $(this).data('type');
                var fieldHtml = getFieldTemplate(type, fieldIndex);
                $('#brst-fields-list').append(fieldHtml);
                fieldIndex++;
                updateFieldIndexes();
            });

            // Remove field
            $(document).on('click', '.brst-remove-field', function() {
                if (confirm('<?php esc_attr_e('Remove this field?', 'brst-engine'); ?>')) {
                    $(this).closest('.brst-field-item').remove();
                    updateFieldIndexes();
                }
            });

            // Toggle field settings
            $(document).on('click', '.brst-field-toggle', function() {
                $(this).closest('.brst-field-item').find('.brst-field-settings').slideToggle();
            });

            function updateFieldIndexes() {
                $('#brst-fields-list .brst-field-item').each(function(index) {
                    $(this).find('input, select, textarea').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                        }
                    });
                });
            }

            function getFieldTemplate(type, index) {
                var labels = {
                    'text': '<?php esc_attr_e('Text Field', 'brst-engine'); ?>',
                    'email': '<?php esc_attr_e('Email Field', 'brst-engine'); ?>',
                    'textarea': '<?php esc_attr_e('Text Area', 'brst-engine'); ?>',
                    'radio': '<?php esc_attr_e('Radio Buttons', 'brst-engine'); ?>',
                    'checkbox': '<?php esc_attr_e('Checkbox', 'brst-engine'); ?>',
                    'select': '<?php esc_attr_e('Dropdown', 'brst-engine'); ?>'
                };

                var html = '<div class="brst-field-item" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 10px;">';
                html += '<input type="hidden" name="fields[' + index + '][type]" value="' + type + '">';
                html += '<div class="brst-field-header" style="display: flex; align-items: center; gap: 10px;">';
                html += '<span class="brst-field-handle dashicons dashicons-move" style="cursor: move; color: #999;"></span>';
                html += '<span class="brst-field-type-label" style="flex: 1; font-weight: bold;">' + labels[type] + '</span>';
                html += '<button type="button" class="brst-field-toggle button button-small"><?php esc_attr_e('Settings', 'brst-engine'); ?></button>';
                html += '<button type="button" class="brst-remove-field button button-small" style="color: #a00;"><?php esc_attr_e('Remove', 'brst-engine'); ?></button>';
                html += '</div>';
                html += '<div class="brst-field-settings" style="margin-top: 15px; display: none;">';
                html += '<table class="form-table">';
                html += '<tr><th><label><?php esc_attr_e('Field ID', 'brst-engine'); ?></label></th>';
                html += '<td><input type="text" name="fields[' + index + '][id]" value="custom_' + index + '" class="regular-text" required></td></tr>';
                html += '<tr><th><label><?php esc_attr_e('Label', 'brst-engine'); ?></label></th>';
                html += '<td><input type="text" name="fields[' + index + '][label]" value="" class="regular-text" required></td></tr>';
                html += '<tr><th><label><?php esc_attr_e('Placeholder', 'brst-engine'); ?></label></th>';
                html += '<td><input type="text" name="fields[' + index + '][placeholder]" value="" class="regular-text"></td></tr>';
                html += '<tr><th><label><?php esc_attr_e('Required', 'brst-engine'); ?></label></th>';
                html += '<td><input type="checkbox" name="fields[' + index + '][required]" value="1"></td></tr>';
                html += '</table>';
                html += '</div>';
                html += '</div>';

                return html;
            }
        });
        </script>

        <style>
            .brst-field-placeholder {
                background: #e7f5ff;
                border: 2px dashed #0073aa;
                height: 60px;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            .brst-field-item.ui-sortable-helper {
                box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            }
        </style>
        <?php
    }

    /**
     * Render a single field editor
     */
    private function render_field_editor($field, $index) {
        $type_labels = array(
            'text' => __('Text Field', 'brst-engine'),
            'email' => __('Email Field', 'brst-engine'),
            'textarea' => __('Text Area', 'brst-engine'),
            'radio' => __('Radio Buttons', 'brst-engine'),
            'checkbox' => __('Checkbox', 'brst-engine'),
            'select' => __('Dropdown', 'brst-engine'),
        );
        ?>
        <div class="brst-field-item" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
            <input type="hidden" name="fields[<?php echo $index; ?>][type]" value="<?php echo esc_attr($field['type']); ?>">

            <div class="brst-field-header" style="display: flex; align-items: center; gap: 10px;">
                <span class="brst-field-handle dashicons dashicons-move" style="cursor: move; color: #999;"></span>
                <span class="brst-field-type-label" style="flex: 1;">
                    <strong><?php echo esc_html($field['label'] ?? $type_labels[$field['type']] ?? $field['type']); ?></strong>
                    <code style="margin-left: 10px; font-size: 11px;"><?php echo esc_html($field['id']); ?></code>
                </span>
                <button type="button" class="brst-field-toggle button button-small"><?php esc_html_e('Settings', 'brst-engine'); ?></button>
                <?php if (!in_array($field['id'], array('user_name', 'user_email'))): ?>
                    <button type="button" class="brst-remove-field button button-small" style="color: #a00;"><?php esc_html_e('Remove', 'brst-engine'); ?></button>
                <?php endif; ?>
            </div>

            <div class="brst-field-settings" style="margin-top: 15px; display: none;">
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th style="padding: 10px 0;"><label><?php esc_html_e('Field ID', 'brst-engine'); ?></label></th>
                        <td style="padding: 10px 0;">
                            <input type="text" name="fields[<?php echo $index; ?>][id]"
                                   value="<?php echo esc_attr($field['id']); ?>" class="regular-text"
                                   <?php echo in_array($field['id'], array('user_name', 'user_email')) ? 'readonly' : ''; ?>>
                            <?php if (in_array($field['id'], array('user_name', 'user_email'))): ?>
                                <p class="description"><?php esc_html_e('This field ID cannot be changed as it is required by the system.', 'brst-engine'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 10px 0;"><label><?php esc_html_e('Label', 'brst-engine'); ?></label></th>
                        <td style="padding: 10px 0;">
                            <input type="text" name="fields[<?php echo $index; ?>][label]"
                                   value="<?php echo esc_attr($field['label'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 10px 0;"><label><?php esc_html_e('Placeholder', 'brst-engine'); ?></label></th>
                        <td style="padding: 10px 0;">
                            <input type="text" name="fields[<?php echo $index; ?>][placeholder]"
                                   value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 10px 0;"><label><?php esc_html_e('Description', 'brst-engine'); ?></label></th>
                        <td style="padding: 10px 0;">
                            <input type="text" name="fields[<?php echo $index; ?>][description]"
                                   value="<?php echo esc_attr($field['description'] ?? ''); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 10px 0;"><label><?php esc_html_e('Required', 'brst-engine'); ?></label></th>
                        <td style="padding: 10px 0;">
                            <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1"
                                   <?php checked(!empty($field['required'])); ?>
                                   <?php echo in_array($field['id'], array('user_name', 'user_email')) ? 'disabled checked' : ''; ?>>
                            <?php if (in_array($field['id'], array('user_name', 'user_email'))): ?>
                                <input type="hidden" name="fields[<?php echo $index; ?>][required]" value="1">
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get default user fields
     */
    private function get_default_user_fields() {
        return array(
            array(
                'id' => 'user_name',
                'type' => 'text',
                'label' => __('Your Full Name', 'brst-engine'),
                'placeholder' => __('Enter your name', 'brst-engine'),
                'required' => true,
            ),
            array(
                'id' => 'user_email',
                'type' => 'email',
                'label' => __('Email Address', 'brst-engine'),
                'placeholder' => __('Enter your email', 'brst-engine'),
                'description' => __('Your report will be sent to this email address.', 'brst-engine'),
                'required' => true,
            ),
            array(
                'id' => 'company_name',
                'type' => 'text',
                'label' => __('Company/Business Name', 'brst-engine'),
                'placeholder' => __('Enter your business name (optional)', 'brst-engine'),
                'required' => false,
            ),
        );
    }

    /**
     * Save custom fields
     */
    private function save_custom_fields() {
        $fields = array();

        if (!empty($_POST['fields']) && is_array($_POST['fields'])) {
            foreach ($_POST['fields'] as $field_data) {
                $field = array(
                    'id' => sanitize_key($field_data['id'] ?? ''),
                    'type' => sanitize_text_field($field_data['type'] ?? 'text'),
                    'label' => sanitize_text_field($field_data['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field_data['placeholder'] ?? ''),
                    'description' => sanitize_text_field($field_data['description'] ?? ''),
                    'required' => !empty($field_data['required']),
                );

                // Validate field
                if (!empty($field['id']) && !empty($field['type'])) {
                    $fields[] = $field;
                }
            }
        }

        // Ensure required fields exist
        $has_name = false;
        $has_email = false;

        foreach ($fields as $field) {
            if ($field['id'] === 'user_name') $has_name = true;
            if ($field['id'] === 'user_email') $has_email = true;
        }

        if (!$has_name) {
            array_unshift($fields, array(
                'id' => 'user_name',
                'type' => 'text',
                'label' => __('Your Full Name', 'brst-engine'),
                'placeholder' => __('Enter your name', 'brst-engine'),
                'required' => true,
            ));
        }

        if (!$has_email) {
            // Insert after name
            $position = 1;
            foreach ($fields as $index => $field) {
                if ($field['id'] === 'user_name') {
                    $position = $index + 1;
                    break;
                }
            }

            array_splice($fields, $position, 0, array(array(
                'id' => 'user_email',
                'type' => 'email',
                'label' => __('Email Address', 'brst-engine'),
                'placeholder' => __('Enter your email', 'brst-engine'),
                'description' => __('Your report will be sent to this email address.', 'brst-engine'),
                'required' => true,
            )));
        }

        update_option('brst_custom_user_fields', $fields);

        add_settings_error('brst_form_builder', 'form_saved', __('Form saved successfully.', 'brst-engine'), 'updated');
    }
}
