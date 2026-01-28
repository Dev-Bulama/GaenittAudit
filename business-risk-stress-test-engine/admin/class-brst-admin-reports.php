<?php
/**
 * Admin Reports Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Reports class for viewing generated reports
 */
class BRST_Admin_Reports {

    /**
     * Items per page
     */
    private $per_page = 20;

    /**
     * Render reports page
     */
    public function render() {
        global $wpdb;

        $reports_table = BRST_Database::get_table_name('reports');
        $submissions_table = BRST_Database::get_table_name('submissions');
        $email_table = BRST_Database::get_table_name('email_captures');

        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $this->per_page;

        // Filters
        $where = '1=1';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND r.email_status = %s", $status_filter);
        }

        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table r WHERE $where");

        // Get reports with related data
        $reports = $wpdb->get_results("
            SELECT r.*, s.primary_profile, s.secondary_profile, e.email
            FROM $reports_table r
            LEFT JOIN $submissions_table s ON r.submission_id = s.id
            LEFT JOIN $email_table e ON r.submission_id = e.submission_id
            WHERE $where
            ORDER BY r.created_at DESC
            LIMIT $this->per_page OFFSET $offset
        ");

        $total_pages = ceil($total / $this->per_page);

        // Statistics
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table"),
            'sent' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE email_status = 'sent'"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE email_status = 'pending'"),
            'standalone' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE report_type = 'standalone'"),
            'interaction' => $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE report_type = 'interaction'"),
        );
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('Reports', 'brst-engine'); ?></h1>

            <div class="brst-dashboard-stats" style="margin-bottom: 20px;">
                <div class="brst-stat-card">
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['total']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Total Reports', 'brst-engine'); ?></span>
                    </div>
                </div>
                <div class="brst-stat-card">
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['sent']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Sent', 'brst-engine'); ?></span>
                    </div>
                </div>
                <div class="brst-stat-card">
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['standalone']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Standalone', 'brst-engine'); ?></span>
                    </div>
                </div>
                <div class="brst-stat-card">
                    <div class="brst-stat-content">
                        <span class="brst-stat-value"><?php echo esc_html($stats['interaction']); ?></span>
                        <span class="brst-stat-label"><?php esc_html_e('Interaction', 'brst-engine'); ?></span>
                    </div>
                </div>
            </div>

            <form method="get" class="brst-filters">
                <input type="hidden" name="page" value="brst-reports">

                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'brst-engine'); ?></option>
                    <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php esc_html_e('Sent', 'brst-engine'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'brst-engine'); ?></option>
                </select>

                <button type="submit" class="button"><?php esc_html_e('Filter', 'brst-engine'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=brst-reports')); ?>" class="button"><?php esc_html_e('Reset', 'brst-engine'); ?></a>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('ID', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Submission', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Email', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Profile', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Type', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Status', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Sent At', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Created', 'brst-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No reports found.', 'brst-engine'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo esc_html($report->id); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=brst-submissions&action=view&id=' . $report->submission_id)); ?>">
                                        #<?php echo esc_html($report->submission_id); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($report->email ?: '-'); ?></td>
                                <td><?php echo esc_html($report->primary_profile); ?></td>
                                <td>
                                    <?php if ($report->report_type === 'interaction'): ?>
                                        <span class="brst-badge brst-badge-info"><?php esc_html_e('Interaction', 'brst-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="brst-badge"><?php esc_html_e('Standalone', 'brst-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($report->email_status === 'sent'): ?>
                                        <span class="brst-badge brst-badge-success"><?php esc_html_e('Sent', 'brst-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="brst-badge brst-badge-warning"><?php esc_html_e('Pending', 'brst-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $report->sent_at ? esc_html(date('M j, Y H:i', strtotime($report->sent_at))) : '-'; ?></td>
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($report->created_at))); ?></td>
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
}
