<?php
/**
 * Admin Templates Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Templates class for managing report and email templates
 */
class BRST_Admin_Templates {

    /**
     * Render templates page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['brst_save_template']) && wp_verify_nonce($_POST['brst_template_nonce'], 'brst_save_template')) {
            $this->save_template();
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'mini_report';
        $edit_template = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Templates', 'brst-engine'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'mini_report', remove_query_arg('edit'))); ?>" class="nav-tab <?php echo $active_tab === 'mini_report' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Mini Report Templates', 'brst-engine'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'full_report', remove_query_arg('edit'))); ?>" class="nav-tab <?php echo $active_tab === 'full_report' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Full Report Templates', 'brst-engine'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'email', remove_query_arg('edit'))); ?>" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email Templates', 'brst-engine'); ?>
                </a>
            </nav>

            <?php
            if ($edit_template) {
                $this->render_edit_template($edit_template);
            } else {
                $this->render_template_list($active_tab);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render template list
     */
    private function render_template_list($template_type) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $templates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE template_type = %s ORDER BY title ASC",
            $template_type
        ));
        ?>
        <div class="brst-templates-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Template Key', 'brst-engine'); ?></th>
                        <th style="width: 35%;"><?php esc_html_e('Title', 'brst-engine'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Status', 'brst-engine'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Actions', 'brst-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No templates found.', 'brst-engine'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><code><?php echo esc_html($template->template_key); ?></code></td>
                                <td><?php echo esc_html($template->title); ?></td>
                                <td>
                                    <span class="brst-status brst-status-<?php echo esc_attr($template->status); ?>">
                                        <?php echo esc_html(ucfirst($template->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg('edit', $template->template_key)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'brst-engine'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="brst-templates-help" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3><?php esc_html_e('Available Variables', 'brst-engine'); ?></h3>
            <p><?php esc_html_e('Use these placeholders in your templates. They will be replaced with actual values when the template is rendered.', 'brst-engine'); ?></p>

            <?php if ($template_type === 'mini_report' || $template_type === 'full_report'): ?>
                <table class="widefat" style="max-width: 600px;">
                    <tr><td><code>{{profile_name}}</code></td><td><?php esc_html_e('Primary profile name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{secondary_profile}}</code></td><td><?php esc_html_e('Secondary profile name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{score_percentage}}</code></td><td><?php esc_html_e('Primary profile score percentage', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{user_name}}</code></td><td><?php esc_html_e('User\'s name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{company_name}}</code></td><td><?php esc_html_e('Company name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{alignment_status}}</code></td><td><?php esc_html_e('Alignment status (aligned/not_aligned)', 'brst-engine'); ?></td></tr>
                </table>
            <?php elseif ($template_type === 'email'): ?>
                <table class="widefat" style="max-width: 600px;">
                    <tr><td><code>{{profile_name}}</code></td><td><?php esc_html_e('Primary profile name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{user_name}}</code></td><td><?php esc_html_e('User\'s name', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{feedback_url}}</code></td><td><?php esc_html_e('Link to feedback form', 'brst-engine'); ?></td></tr>
                    <tr><td><code>{{report_url}}</code></td><td><?php esc_html_e('Link to download report', 'brst-engine'); ?></td></tr>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render template editor
     */
    private function render_edit_template($template_key) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE template_key = %s",
            $template_key
        ));

        if (!$template) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Template not found.', 'brst-engine') . '</p></div>';
            return;
        }

        $content = json_decode($template->content, true);
        ?>
        <form method="post" class="brst-template-form">
            <?php wp_nonce_field('brst_save_template', 'brst_template_nonce'); ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
            <input type="hidden" name="template_key" value="<?php echo esc_attr($template->template_key); ?>">
            <input type="hidden" name="template_type" value="<?php echo esc_attr($template->template_type); ?>">

            <div class="brst-template-header" style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <a href="<?php echo esc_url(add_query_arg('tab', $template->template_type, remove_query_arg('edit'))); ?>" class="button">
                        &larr; <?php esc_html_e('Back to List', 'brst-engine'); ?>
                    </a>
                </div>
                <h2 style="margin: 0;">
                    <?php printf(esc_html__('Edit Template: %s', 'brst-engine'), esc_html($template->title)); ?>
                </h2>
                <div>
                    <input type="submit" name="brst_save_template" class="button button-primary" value="<?php esc_attr_e('Save Template', 'brst-engine'); ?>">
                </div>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="template_title"><?php esc_html_e('Title', 'brst-engine'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="template_title" id="template_title"
                               value="<?php echo esc_attr($template->title); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="template_status"><?php esc_html_e('Status', 'brst-engine'); ?></label>
                    </th>
                    <td>
                        <select name="template_status" id="template_status">
                            <option value="active" <?php selected($template->status, 'active'); ?>>
                                <?php esc_html_e('Active', 'brst-engine'); ?>
                            </option>
                            <option value="inactive" <?php selected($template->status, 'inactive'); ?>>
                                <?php esc_html_e('Inactive', 'brst-engine'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Template Content', 'brst-engine'); ?></h3>

            <?php if ($template->template_type === 'email'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email_subject"><?php esc_html_e('Email Subject', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="content[subject]" id="email_subject"
                                   value="<?php echo esc_attr($content['subject'] ?? ''); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email_body"><?php esc_html_e('Email Body', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $content['body'] ?? '',
                                'email_body',
                                array(
                                    'textarea_name' => 'content[body]',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'quicktags' => true,
                                )
                            );
                            ?>
                            <p class="description"><?php esc_html_e('Use HTML to format your email. Variables like {{profile_name}} will be replaced with actual values.', 'brst-engine'); ?></p>
                        </td>
                    </tr>
                </table>

            <?php elseif ($template->template_type === 'mini_report'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="profile_name"><?php esc_html_e('Profile Name', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="content[profile_name]" id="profile_name"
                                   value="<?php echo esc_attr($content['profile_name'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="summary"><?php esc_html_e('Summary Text', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <textarea name="content[summary]" id="summary" rows="5" class="large-text"><?php echo esc_textarea($content['summary'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Brief summary shown in the mini report.', 'brst-engine'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cta_text"><?php esc_html_e('CTA Button Text', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="content[cta_text]" id="cta_text"
                                   value="<?php echo esc_attr($content['cta_text'] ?? 'Unlock Full Report'); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

            <?php elseif ($template->template_type === 'full_report'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="profile_name"><?php esc_html_e('Profile Name', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="content[profile_name]" id="profile_name"
                                   value="<?php echo esc_attr($content['profile_name'] ?? $content['primary_profile'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description"><?php esc_html_e('Profile Description', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <textarea name="content[description]" id="description" rows="3" class="large-text"><?php echo esc_textarea($content['description'] ?? ''); ?></textarea>
                        </td>
                    </tr>
                </table>

                <h4><?php esc_html_e('Report Sections', 'brst-engine'); ?></h4>
                <?php
                $sections = $content['sections'] ?? array();
                $section_labels = array(
                    'executive_summary' => __('Executive Summary', 'brst-engine'),
                    'risk_analysis' => __('Risk Analysis', 'brst-engine'),
                    'primary_analysis' => __('Primary Profile Analysis', 'brst-engine'),
                    'secondary_analysis' => __('Secondary Profile Analysis', 'brst-engine'),
                    'interaction_effects' => __('Interaction Effects', 'brst-engine'),
                    'category_breakdown' => __('Category Breakdown', 'brst-engine'),
                    'recommendations' => __('Recommendations', 'brst-engine'),
                    'action_items' => __('Action Items', 'brst-engine'),
                );
                ?>
                <table class="form-table">
                    <?php foreach ($sections as $key => $value): ?>
                        <tr>
                            <th scope="row">
                                <label for="section_<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($section_labels[$key] ?? ucfirst(str_replace('_', ' ', $key))); ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="content[sections][<?php echo esc_attr($key); ?>]"
                                          id="section_<?php echo esc_attr($key); ?>"
                                          rows="4" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <p class="submit">
                <input type="submit" name="brst_save_template" class="button button-primary" value="<?php esc_attr_e('Save Template', 'brst-engine'); ?>">
                <a href="<?php echo esc_url(add_query_arg('tab', $template->template_type, remove_query_arg('edit'))); ?>" class="button">
                    <?php esc_html_e('Cancel', 'brst-engine'); ?>
                </a>
            </p>
        </form>
        <?php
    }

    /**
     * Save template
     */
    private function save_template() {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $template_id = intval($_POST['template_id'] ?? 0);
        $template_type = sanitize_text_field($_POST['template_type'] ?? '');

        // Build content array based on template type
        $content = array();

        if ($template_type === 'email') {
            $content['subject'] = sanitize_text_field($_POST['content']['subject'] ?? '');
            $content['body'] = wp_kses_post($_POST['content']['body'] ?? '');
        } elseif ($template_type === 'mini_report') {
            $content['profile_name'] = sanitize_text_field($_POST['content']['profile_name'] ?? '');
            $content['summary'] = sanitize_textarea_field($_POST['content']['summary'] ?? '');
            $content['cta_text'] = sanitize_text_field($_POST['content']['cta_text'] ?? '');
            $content['alignment'] = sanitize_text_field($_POST['content']['alignment'] ?? '');
        } elseif ($template_type === 'full_report') {
            $content['profile_name'] = sanitize_text_field($_POST['content']['profile_name'] ?? '');
            $content['description'] = sanitize_textarea_field($_POST['content']['description'] ?? '');
            $content['report_type'] = sanitize_text_field($_POST['content']['report_type'] ?? 'standalone');
            $content['sections'] = array();

            if (!empty($_POST['content']['sections']) && is_array($_POST['content']['sections'])) {
                foreach ($_POST['content']['sections'] as $key => $value) {
                    $content['sections'][sanitize_key($key)] = sanitize_textarea_field($value);
                }
            }
        }

        $data = array(
            'title' => sanitize_text_field($_POST['template_title'] ?? ''),
            'content' => wp_json_encode($content),
            'status' => sanitize_text_field($_POST['template_status'] ?? 'active'),
            'updated_at' => current_time('mysql'),
        );

        $result = $wpdb->update($table, $data, array('id' => $template_id));

        if ($result !== false) {
            add_settings_error('brst_templates', 'template_updated', __('Template saved successfully.', 'brst-engine'), 'updated');
        } else {
            add_settings_error('brst_templates', 'template_error', __('Failed to save template.', 'brst-engine'), 'error');
        }

        settings_errors('brst_templates');
    }
}
