<?php
/**
 * Admin Feedbacks Handler
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Feedbacks class for viewing user feedback submissions
 */
class BRST_Admin_Feedbacks {

    /**
     * Items per page
     */
    private $per_page = 20;

    /**
     * Render feedbacks page
     */
    public function render() {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $filter_rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

        // Get statistics
        $feedback_engine = new BRST_Feedback_Engine();
        $stats = $feedback_engine->get_statistics();

        // Get feedbacks with pagination
        $feedbacks = $this->get_feedbacks($current_page, $filter_rating);
        $total_items = $this->get_total_feedbacks($filter_rating);
        $total_pages = ceil($total_items / $this->per_page);
        ?>
        <div class="wrap brst-admin-wrap">
            <h1><?php esc_html_e('User Feedbacks', 'brst-engine'); ?></h1>

            <!-- Statistics Cards -->
            <div class="brst-feedback-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="brst-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 150px;">
                    <div class="brst-stat-value" style="font-size: 32px; font-weight: bold; color: #2271b1;">
                        <?php echo esc_html($stats['total_feedback']); ?>
                    </div>
                    <div class="brst-stat-label" style="color: #646970;">
                        <?php esc_html_e('Total Feedbacks', 'brst-engine'); ?>
                    </div>
                </div>

                <div class="brst-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 150px;">
                    <div class="brst-stat-value" style="font-size: 32px; font-weight: bold; color: #dba617;">
                        <?php echo esc_html($stats['average_rating'] ?: '-'); ?>
                    </div>
                    <div class="brst-stat-label" style="color: #646970;">
                        <?php esc_html_e('Average Rating', 'brst-engine'); ?>
                    </div>
                </div>

                <div class="brst-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 150px;">
                    <div class="brst-stat-value" style="font-size: 32px; font-weight: bold; color: #00a32a;">
                        <?php echo esc_html($stats['pending_reminders']); ?>
                    </div>
                    <div class="brst-stat-label" style="color: #646970;">
                        <?php esc_html_e('Pending Reminders', 'brst-engine'); ?>
                    </div>
                </div>
            </div>

            <!-- Rating Distribution -->
            <?php if (!empty($stats['rating_distribution'])): ?>
            <div class="brst-rating-distribution" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Rating Distribution', 'brst-engine'); ?></h3>
                <div style="display: flex; gap: 20px; align-items: flex-end;">
                    <?php
                    $max_count = 1;
                    foreach ($stats['rating_distribution'] as $dist) {
                        if ($dist->count > $max_count) {
                            $max_count = $dist->count;
                        }
                    }
                    $rating_labels = array(
                        '1' => 'Not useful',
                        '2' => 'Slightly useful',
                        '3' => 'Moderately useful',
                        '4' => 'Very useful',
                        '5' => 'Extremely useful',
                    );
                    for ($i = 1; $i <= 5; $i++):
                        $count = 0;
                        foreach ($stats['rating_distribution'] as $dist) {
                            if ($dist->rating == $i) {
                                $count = $dist->count;
                                break;
                            }
                        }
                        $height = $max_count > 0 ? round(($count / $max_count) * 100) : 0;
                    ?>
                    <div style="text-align: center; flex: 1;">
                        <div style="height: 100px; display: flex; align-items: flex-end; justify-content: center;">
                            <div style="width: 40px; height: <?php echo $height; ?>px; background: #2271b1; border-radius: 4px 4px 0 0; min-height: <?php echo $count > 0 ? '10' : '0'; ?>px;"></div>
                        </div>
                        <div style="margin-top: 5px;">
                            <strong><?php echo esc_html($i); ?> ★</strong>
                            <br>
                            <small><?php echo esc_html($count); ?></small>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="brst-feedbacks">
                        <select name="rating">
                            <option value="0"><?php esc_html_e('All Ratings', 'brst-engine'); ?></option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($filter_rating, $i); ?>>
                                    <?php echo $i; ?> <?php esc_html_e('Star', 'brst-engine'); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'brst-engine'); ?>">
                    </form>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(
                            _n('%s item', '%s items', $total_items, 'brst-engine'),
                            number_format_i18n($total_items)
                        ); ?>
                    </span>
                    <?php echo $this->get_pagination_links($current_page, $total_pages, $filter_rating); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Feedbacks Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('ID', 'brst-engine'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Rating', 'brst-engine'); ?></th>
                        <th><?php esc_html_e('Feedback Comments', 'brst-engine'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Submission', 'brst-engine'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Submitted At', 'brst-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($feedbacks)): ?>
                        <tr>
                            <td colspan="5">
                                <?php esc_html_e('No feedbacks found.', 'brst-engine'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <tr>
                                <td><?php echo esc_html($feedback->id); ?></td>
                                <td>
                                    <?php
                                    $rating = intval($feedback->question_1_response);
                                    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                                    $rating_class = $rating >= 4 ? 'brst-rating-good' : ($rating >= 3 ? 'brst-rating-ok' : 'brst-rating-poor');
                                    ?>
                                    <span class="<?php echo esc_attr($rating_class); ?>" style="color: <?php echo $rating >= 4 ? '#00a32a' : ($rating >= 3 ? '#dba617' : '#d63638'); ?>;">
                                        <?php echo esc_html($stars); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($feedback->question_2_response)): ?>
                                        <?php echo esc_html($feedback->question_2_response); ?>
                                    <?php else: ?>
                                        <em style="color: #646970;"><?php esc_html_e('No comments provided', 'brst-engine'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($feedback->submission_id): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=brst-submissions&view=' . $feedback->submission_id)); ?>">
                                            #<?php echo esc_html($feedback->submission_id); ?>
                                        </a>
                                        <?php
                                        // Get submission details
                                        $submission = $this->get_submission($feedback->submission_id);
                                        if ($submission && !empty($submission->user_email)):
                                        ?>
                                            <br>
                                            <small style="color: #646970;"><?php echo esc_html($submission->user_email); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($feedback->submitted_at): ?>
                                        <?php echo esc_html(date('M j, Y', strtotime($feedback->submitted_at))); ?>
                                        <br>
                                        <small style="color: #646970;"><?php echo esc_html(date('g:i A', strtotime($feedback->submitted_at))); ?></small>
                                    <?php else: ?>
                                        <em style="color: #646970;"><?php esc_html_e('Pending', 'brst-engine'); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Bottom Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo $this->get_pagination_links($current_page, $total_pages, $filter_rating); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get feedbacks with pagination
     */
    private function get_feedbacks($page, $filter_rating = 0) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $offset = ($page - 1) * $this->per_page;

        $where = 'submitted_at IS NOT NULL';
        $params = array();

        if ($filter_rating > 0) {
            $where .= ' AND question_1_response = %s';
            $params[] = $filter_rating;
        }

        $params[] = $this->per_page;
        $params[] = $offset;

        $query = "SELECT * FROM $table WHERE $where ORDER BY submitted_at DESC LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get total feedbacks count
     */
    private function get_total_feedbacks($filter_rating = 0) {
        global $wpdb;
        $table = BRST_Database::get_table_name('feedback');

        $where = 'submitted_at IS NOT NULL';

        if ($filter_rating > 0) {
            $where .= $wpdb->prepare(' AND question_1_response = %s', $filter_rating);
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
    }

    /**
     * Get submission details
     */
    private function get_submission($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $submission_id
        ));
    }

    /**
     * Generate pagination links
     */
    private function get_pagination_links($current_page, $total_pages, $filter_rating) {
        $base_url = admin_url('admin.php?page=brst-feedbacks');
        if ($filter_rating > 0) {
            $base_url = add_query_arg('rating', $filter_rating, $base_url);
        }

        $links = array();

        // First page
        if ($current_page > 1) {
            $links[] = sprintf(
                '<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">«</span></a>',
                esc_url(add_query_arg('paged', 1, $base_url)),
                __('First page', 'brst-engine')
            );
            $links[] = sprintf(
                '<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">‹</span></a>',
                esc_url(add_query_arg('paged', $current_page - 1, $base_url)),
                __('Previous page', 'brst-engine')
            );
        } else {
            $links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
            $links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
        }

        // Page indicator
        $links[] = sprintf(
            '<span class="paging-input">%d of <span class="total-pages">%d</span></span>',
            $current_page,
            $total_pages
        );

        // Last page
        if ($current_page < $total_pages) {
            $links[] = sprintf(
                '<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">›</span></a>',
                esc_url(add_query_arg('paged', $current_page + 1, $base_url)),
                __('Next page', 'brst-engine')
            );
            $links[] = sprintf(
                '<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">»</span></a>',
                esc_url(add_query_arg('paged', $total_pages, $base_url)),
                __('Last page', 'brst-engine')
            );
        } else {
            $links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
            $links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
        }

        return '<span class="pagination-links">' . implode("\n", $links) . '</span>';
    }
}
