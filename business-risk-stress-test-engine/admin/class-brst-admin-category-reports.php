<?php
/**
 * Admin Category Reports Upload Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Category Reports class for uploading PDF reports per category
 */
class BRST_Admin_Category_Reports {

    /**
     * Default scored categories
     */
    private $default_categories = array(
        'cash_tight' => 'Cash-Tight Operator',
        'revenue_concentrated' => 'Revenue-Concentrated Builder',
        'cost_locked' => 'Cost-Locked Business',
        'owner_dependent' => 'Owner-Dependent Engine',
        'externally_exposed' => 'Externally Exposed Builder',
    );

    /**
     * Get categories with custom names
     */
    private function get_categories() {
        $custom_names = get_option('brst_category_names');
        if (!empty($custom_names) && is_array($custom_names)) {
            return array_merge($this->default_categories, $custom_names);
        }
        return $this->default_categories;
    }

    /**
     * Render category reports page
     */
    public function render() {
        // Handle form submission
        if (isset($_POST['brst_save_category_reports']) && wp_verify_nonce($_POST['brst_category_reports_nonce'], 'brst_save_category_reports')) {
            $this->save_reports();
        }

        // Handle delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['category'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'brst_delete_report')) {
                $this->delete_report(sanitize_key($_GET['category']));
            }
        }

        $categories = $this->get_categories();
        $reports = get_option('brst_category_reports', array());
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Category Reports', 'brst-engine'); ?></h1>

            <?php settings_errors('brst_category_reports'); ?>

            <div class="brst-questions-info" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0;"><?php esc_html_e('About Category Reports', 'brst-engine'); ?></h4>
                <p><?php esc_html_e('Upload PDF reports for each risk category. These reports will be sent to users after payment based on their primary risk profile. You can upload different reports for each category to provide personalized insights.', 'brst-engine'); ?></p>
            </div>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('brst_save_category_reports', 'brst_category_reports_nonce'); ?>

                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e('Category', 'brst-engine'); ?></th>
                            <th style="width: 35%;"><?php esc_html_e('Current Report', 'brst-engine'); ?></th>
                            <th style="width: 30%;"><?php esc_html_e('Upload New Report', 'brst-engine'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Actions', 'brst-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $key => $name):
                            $report = $reports[$key] ?? null;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($name); ?></strong>
                                <br><small style="color: #666;"><?php echo esc_html($key); ?></small>
                            </td>
                            <td>
                                <?php if ($report && !empty($report['url'])): ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="dashicons dashicons-pdf" style="color: #dc3545; font-size: 24px;"></span>
                                        <div>
                                            <a href="<?php echo esc_url($report['url']); ?>" target="_blank" style="font-weight: 600;">
                                                <?php echo esc_html($report['filename'] ?? 'View Report'); ?>
                                            </a>
                                            <br>
                                            <small style="color: #666;">
                                                <?php
                                                if (!empty($report['uploaded_at'])) {
                                                    printf(
                                                        esc_html__('Uploaded: %s', 'brst-engine'),
                                                        date('M j, Y g:i a', strtotime($report['uploaded_at']))
                                                    );
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;"><?php esc_html_e('No report uploaded', 'brst-engine'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="file"
                                       name="category_reports[<?php echo esc_attr($key); ?>]"
                                       accept=".pdf,application/pdf"
                                       class="brst-file-input">
                                <p class="description" style="margin-top: 5px;"><?php esc_html_e('PDF files only', 'brst-engine'); ?></p>
                            </td>
                            <td>
                                <?php if ($report && !empty($report['url'])): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'category' => $key)), 'brst_delete_report')); ?>"
                                       class="button button-link-delete"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this report?', 'brst-engine'); ?>');">
                                        <?php esc_html_e('Delete', 'brst-engine'); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin-top: 30px;"><?php esc_html_e('Default Report (Fallback)', 'brst-engine'); ?></h3>
                <p class="description"><?php esc_html_e('This report will be sent if no category-specific report is uploaded for the user\'s profile.', 'brst-engine'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_report"><?php esc_html_e('Default Report', 'brst-engine'); ?></label>
                        </th>
                        <td>
                            <?php
                            $default_report = $reports['_default'] ?? null;
                            if ($default_report && !empty($default_report['url'])):
                            ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <span class="dashicons dashicons-pdf" style="color: #dc3545; font-size: 24px;"></span>
                                    <a href="<?php echo esc_url($default_report['url']); ?>" target="_blank">
                                        <?php echo esc_html($default_report['filename'] ?? 'View Report'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'category' => '_default')), 'brst_delete_report')); ?>"
                                       class="button button-link-delete button-small"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure?', 'brst-engine'); ?>');">
                                        <?php esc_html_e('Delete', 'brst-engine'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="category_reports[_default]" accept=".pdf,application/pdf">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="brst_save_category_reports" class="button button-primary" value="<?php esc_attr_e('Upload Reports', 'brst-engine'); ?>">
                </p>
            </form>

            <div class="brst-category-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 30px 0;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Usage Notes', 'brst-engine'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php esc_html_e('Upload a PDF report for each risk category to provide personalized reports.', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('When a user completes payment, they will receive the report matching their primary risk profile.', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('If no category-specific report exists, the default report will be sent instead.', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('Recommended file size: Under 10MB for optimal email delivery.', 'brst-engine'); ?></li>
                    <li><?php esc_html_e('Make sure your PDF reports are professionally formatted and contain actionable insights.', 'brst-engine'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Save uploaded reports
     */
    private function save_reports() {
        if (empty($_FILES['category_reports'])) {
            return;
        }

        $reports = get_option('brst_category_reports', array());
        $upload_dir = wp_upload_dir();
        $brst_upload_dir = $upload_dir['basedir'] . '/brst-reports';

        // Create directory if it doesn't exist
        if (!file_exists($brst_upload_dir)) {
            wp_mkdir_p($brst_upload_dir);

            // Add .htaccess for security
            file_put_contents($brst_upload_dir . '/.htaccess', "Options -Indexes\n");
        }

        $uploaded_count = 0;

        foreach ($_FILES['category_reports']['name'] as $category => $filename) {
            if (empty($filename)) {
                continue;
            }

            $file_index = array(
                'name' => $_FILES['category_reports']['name'][$category],
                'type' => $_FILES['category_reports']['type'][$category],
                'tmp_name' => $_FILES['category_reports']['tmp_name'][$category],
                'error' => $_FILES['category_reports']['error'][$category],
                'size' => $_FILES['category_reports']['size'][$category],
            );

            // Validate file type
            $allowed_types = array('application/pdf');
            if (!in_array($file_index['type'], $allowed_types)) {
                add_settings_error(
                    'brst_category_reports',
                    'invalid_type',
                    sprintf(__('Invalid file type for %s. Only PDF files are allowed.', 'brst-engine'), $category),
                    'error'
                );
                continue;
            }

            // Validate file size (max 20MB)
            if ($file_index['size'] > 20 * 1024 * 1024) {
                add_settings_error(
                    'brst_category_reports',
                    'file_too_large',
                    sprintf(__('File too large for %s. Maximum size is 20MB.', 'brst-engine'), $category),
                    'error'
                );
                continue;
            }

            // Generate unique filename
            $safe_category = sanitize_key($category);
            $ext = pathinfo($file_index['name'], PATHINFO_EXTENSION);
            $new_filename = 'brst-report-' . $safe_category . '-' . time() . '.' . $ext;
            $destination = $brst_upload_dir . '/' . $new_filename;

            // Delete old file if exists
            if (!empty($reports[$category]['path']) && file_exists($reports[$category]['path'])) {
                unlink($reports[$category]['path']);
            }

            // Move uploaded file
            if (move_uploaded_file($file_index['tmp_name'], $destination)) {
                $reports[$category] = array(
                    'filename' => $file_index['name'],
                    'path' => $destination,
                    'url' => $upload_dir['baseurl'] . '/brst-reports/' . $new_filename,
                    'uploaded_at' => current_time('mysql'),
                );
                $uploaded_count++;
            } else {
                add_settings_error(
                    'brst_category_reports',
                    'upload_failed',
                    sprintf(__('Failed to upload file for %s.', 'brst-engine'), $category),
                    'error'
                );
            }
        }

        update_option('brst_category_reports', $reports);

        if ($uploaded_count > 0) {
            add_settings_error(
                'brst_category_reports',
                'upload_success',
                sprintf(__('%d report(s) uploaded successfully.', 'brst-engine'), $uploaded_count),
                'updated'
            );
        }
    }

    /**
     * Delete a report
     */
    private function delete_report($category) {
        $reports = get_option('brst_category_reports', array());

        if (isset($reports[$category])) {
            // Delete the file
            if (!empty($reports[$category]['path']) && file_exists($reports[$category]['path'])) {
                unlink($reports[$category]['path']);
            }

            unset($reports[$category]);
            update_option('brst_category_reports', $reports);

            add_settings_error(
                'brst_category_reports',
                'delete_success',
                __('Report deleted successfully.', 'brst-engine'),
                'updated'
            );
        }
    }

    /**
     * Get report for a category
     *
     * @param string $category Category key
     * @return array|null Report data or null if not found
     */
    public static function get_report_for_category($category) {
        $reports = get_option('brst_category_reports', array());

        // Try category-specific report first
        if (!empty($reports[$category]['url'])) {
            return $reports[$category];
        }

        // Fall back to default report
        if (!empty($reports['_default']['url'])) {
            return $reports['_default'];
        }

        return null;
    }
}
