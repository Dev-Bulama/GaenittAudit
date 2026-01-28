<?php
/**
 * Admin Submissions Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Submissions class for managing submissions
 */
class BRST_Admin_Submissions {

    /**
     * Items per page
     */
    private $per_page = 20;

    /**
     * Render submissions page
     */
    public function render() {
        // Handle export
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            $this->export_csv();
            return;
        }

        // Handle view single
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $this->render_single(intval($_GET['id']));
            return;
        }

        $this->render_list();
    }

    /**
     * Render submissions list
     */
    private function render_list() {
        global $wpdb;

        $table = BRST_Database::get_table_name('submissions');
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $this->per_page;

        // Filters
        $where = '1=1';
        $profile_filter = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : '';
        if ($profile_filter) {
            $where .= $wpdb->prepare(" AND primary_profile = %s", $profile_filter);
        }

        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        if ($date_from) {
            $where .= $wpdb->prepare(" AND created_at >= %s", $date_from . ' 00:00:00');
        }

        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        if ($date_to) {
            $where .= $wpdb->prepare(" AND created_at <= %s", $date_to . ' 23:59:59');
        }

        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");

        // Get submissions
        $submissions = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $this->per_page OFFSET $offset"
        );

        // Get profiles for filter
        $profiles = $wpdb->get_col("SELECT DISTINCT primary_profile FROM $table");

        $total_pages = ceil($total / $this->per_page);
        ?>
        <div class="wrap brst-admin-wrap">
            <h1>
                <?php esc_html_e('Submissions', 'brst-engine'); ?>
                <a href="<?php echo esc_url(add_query_arg('action', 'export')); ?>" class="page-title-action">
                    <?php esc_html_e('Export CSV', 'brst-engine'); ?>
                </a>
            </h1>

            <form method="get" class="brst-filters">
                <input type="hidden" name="page" value="brst-submissions">

                <select name="profile">
                    <option value=""><?php esc_html_e('All Profiles', 'brst-engine'); ?></option>
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?php echo esc_attr($profile); ?>" <?php selected($profile_filter, $profile); ?>>
                            <?php echo esc_html($profile); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php esc_attr_e('From', 'brst-engine'); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php esc_attr_e('To', 'brst-engine'); ?>">

                <button type="submit" class="button"><?php esc_html_e('Filter', 'brst-engine'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=brst-submissions')); ?>" class="button"><?php esc_html_e('Reset', 'brst-engine'); ?></a>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('ID', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Primary Profile', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Secondary Profile', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Alignment', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Interaction', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Date', 'brst-engine'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Actions', 'brst-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('No submissions found.', 'brst-engine'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html($submission->primary_profile); ?></td>
                                <td><?php echo esc_html($submission->secondary_profile); ?></td>
                                <td>
                                    <?php if ($submission->alignment_status === 'aligned'): ?>
                                        <span class="brst-badge brst-badge-success"><?php esc_html_e('Aligned', 'brst-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="brst-badge brst-badge-warning"><?php esc_html_e('Not Aligned', 'brst-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($submission->interaction_applicable): ?>
                                        <span class="brst-badge brst-badge-info"><?php esc_html_e('Yes', 'brst-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="brst-badge"><?php esc_html_e('No', 'brst-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($submission->created_at))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'view', 'id' => $submission->id))); ?>" class="button button-small">
                                        <?php esc_html_e('View', 'brst-engine'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(esc_html__('%d items', 'brst-engine'), $total); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page,
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render single submission view
     */
    private function render_single($submission_id) {
        global $wpdb;

        $submissions_table = BRST_Database::get_table_name('submissions');
        $payments_table = BRST_Database::get_table_name('payments');
        $reports_table = BRST_Database::get_table_name('reports');
        $feedback_table = BRST_Database::get_table_name('feedback');
        $email_table = BRST_Database::get_table_name('email_captures');
        $log_table = BRST_Database::get_table_name('activity_log');

        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $submissions_table WHERE id = %d", $submission_id));

        if (!$submission) {
            echo '<div class="wrap"><h1>' . esc_html__('Submission Not Found', 'brst-engine') . '</h1></div>';
            return;
        }

        $payments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $payments_table WHERE submission_id = %d", $submission_id));
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $reports_table WHERE submission_id = %d ORDER BY created_at DESC LIMIT 1", $submission_id));
        $feedback = $wpdb->get_row($wpdb->prepare("SELECT * FROM $feedback_table WHERE submission_id = %d", $submission_id));
        $email_capture = $wpdb->get_row($wpdb->prepare("SELECT * FROM $email_table WHERE submission_id = %d", $submission_id));
        $activity_log = $wpdb->get_results($wpdb->prepare("SELECT * FROM $log_table WHERE submission_id = %d ORDER BY created_at DESC", $submission_id));

        $responses = json_decode($submission->responses, true);
        $category_scores = json_decode($submission->category_scores, true);
        ?>
        <div class="wrap brst-admin-wrap">
            <h1>
                <?php printf(esc_html__('Submission #%d', 'brst-engine'), $submission_id); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=brst-submissions')); ?>" class="page-title-action">
                    <?php esc_html_e('Back to List', 'brst-engine'); ?>
                </a>
            </h1>

            <div class="brst-submission-details">
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Profile Information', 'brst-engine'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Primary Profile', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($submission->primary_profile); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Secondary Profile', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($submission->secondary_profile); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Alignment Status', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($submission->alignment_status); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Interaction Applicable', 'brst-engine'); ?></th>
                            <td><?php echo $submission->interaction_applicable ? esc_html__('Yes', 'brst-engine') : esc_html__('No', 'brst-engine'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Q21 Response', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($submission->q21_response); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Submitted', 'brst-engine'); ?></th>
                            <td><?php echo esc_html(date('F j, Y H:i:s', strtotime($submission->created_at))); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Category Scores', 'brst-engine'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Category', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Score', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Percentage', 'brst-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($category_scores): foreach ($category_scores as $key => $data): ?>
                                <tr>
                                    <td><?php echo esc_html($data['name']); ?></td>
                                    <td><?php echo esc_html($data['score'] . '/' . $data['max_score']); ?></td>
                                    <td><?php echo esc_html($data['percentage']); ?>%</td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($email_capture): ?>
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Email Capture', 'brst-engine'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Email', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($email_capture->email); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Marketing Consent', 'brst-engine'); ?></th>
                            <td><?php echo $email_capture->marketing_consent ? esc_html__('Yes', 'brst-engine') : esc_html__('No', 'brst-engine'); ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($payments)): ?>
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Payments', 'brst-engine'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Reference', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Gateway', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Amount', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Date', 'brst-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo esc_html($payment->reference); ?></td>
                                    <td><?php echo esc_html($payment->gateway); ?></td>
                                    <td><?php echo esc_html($payment->currency . ' ' . number_format($payment->amount, 2)); ?></td>
                                    <td>
                                        <span class="brst-badge brst-badge-<?php echo $payment->status === 'success' ? 'success' : 'warning'; ?>">
                                            <?php echo esc_html($payment->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y H:i', strtotime($payment->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($report): ?>
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Report', 'brst-engine'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Report Type', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($report->report_type); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Email Status', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($report->email_status); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Sent At', 'brst-engine'); ?></th>
                            <td><?php echo $report->sent_at ? esc_html(date('M j, Y H:i', strtotime($report->sent_at))) : '-'; ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($feedback): ?>
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Feedback', 'brst-engine'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Rating', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($feedback->question_1_response ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Comments', 'brst-engine'); ?></th>
                            <td><?php echo esc_html($feedback->question_2_response ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Submitted At', 'brst-engine'); ?></th>
                            <td><?php echo $feedback->submitted_at ? esc_html(date('M j, Y H:i', strtotime($feedback->submitted_at))) : esc_html__('Not submitted', 'brst-engine'); ?></td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($activity_log)): ?>
                <div class="brst-detail-section">
                    <h2><?php esc_html_e('Activity Log', 'brst-engine'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Action', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Details', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('IP Address', 'brst-engine'); ?></th>
                                <th><?php esc_html_e('Date', 'brst-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_log as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log->action); ?></td>
                                    <td><code><?php echo esc_html($log->details); ?></code></td>
                                    <td><?php echo esc_html($log->ip_address); ?></td>
                                    <td><?php echo esc_html(date('M j, Y H:i', strtotime($log->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Export submissions to CSV
     */
    private function export_csv() {
        global $wpdb;

        $table = BRST_Database::get_table_name('submissions');
        $submissions = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");

        $filename = 'brst-submissions-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, array(
            'ID',
            'Primary Profile',
            'Secondary Profile',
            'Alignment Status',
            'Interaction Applicable',
            'Q21 Response',
            'Created At',
        ));

        // Data
        foreach ($submissions as $submission) {
            fputcsv($output, array(
                $submission->id,
                $submission->primary_profile,
                $submission->secondary_profile,
                $submission->alignment_status,
                $submission->interaction_applicable ? 'Yes' : 'No',
                $submission->q21_response,
                $submission->created_at,
            ));
        }

        fclose($output);
        exit;
    }
}
