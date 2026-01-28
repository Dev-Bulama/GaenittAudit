<?php
/**
 * PDF Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF Engine class for generating PDF reports
 */
class BRST_PDF_Engine {

    /**
     * Reports directory
     */
    private $reports_dir;

    /**
     * Reports URL
     */
    private $reports_url;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->reports_dir = $upload_dir['basedir'] . '/brst-reports/';
        $this->reports_url = $upload_dir['baseurl'] . '/brst-reports/';

        // Create directory if it doesn't exist
        if (!file_exists($this->reports_dir)) {
            wp_mkdir_p($this->reports_dir);
            // Add .htaccess for security
            $htaccess = $this->reports_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\nDeny from all");
            }
        }
    }

    /**
     * Generate full report PDF
     */
    public function generate_report($submission_id, $payment_id = null) {
        // Get submission data
        $submission = $this->get_submission($submission_id);
        if (!$submission) {
            return new WP_Error('submission_not_found', __('Submission not found.', 'brst-engine'));
        }

        // Get profile analysis
        $scoring_engine = new BRST_Scoring_Engine();
        $profile_engine = new BRST_Profile_Engine();
        $interaction_engine = new BRST_Interaction_Engine();

        $category_scores = json_decode($submission->category_scores, true);
        $responses = json_decode($submission->responses, true);

        $profile_analysis = $profile_engine->analyze_profiles($category_scores, $submission->q21_response);

        // Determine interaction
        $interaction = $interaction_engine->determine_interaction(
            $category_scores,
            $submission->primary_profile,
            $submission->secondary_profile
        );

        // Get appropriate template
        $template_key = $interaction_engine->get_report_template_key(
            $submission->primary_profile,
            $submission->secondary_profile,
            $interaction['is_applicable']
        );

        $template = $this->get_template($template_key);

        // Generate PDF content
        $html = $this->generate_html($template, $profile_analysis, $interaction, $category_scores, $responses);

        // Generate PDF file
        $filename = $this->generate_filename($submission_id);
        $filepath = $this->reports_dir . $filename;

        $pdf_generated = $this->create_pdf($html, $filepath);

        if (is_wp_error($pdf_generated)) {
            return $pdf_generated;
        }

        // Create report record
        $report_id = $this->create_report_record(array(
            'submission_id' => $submission_id,
            'payment_id' => $payment_id,
            'report_type' => $interaction['is_applicable'] ? 'interaction' : 'standalone',
            'template_id' => $template_key,
            'file_path' => $filepath,
            'file_url' => $this->reports_url . $filename,
        ));

        /**
         * Action: brst_report_generated
         * Fires when a report is generated
         */
        do_action('brst_report_generated', $report_id, $submission_id, $filepath);

        return array(
            'report_id' => $report_id,
            'file_path' => $filepath,
            'file_url' => $this->reports_url . $filename,
            'filename' => $filename,
        );
    }

    /**
     * Get submission data
     */
    private function get_submission($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('submissions');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id)
        );
    }

    /**
     * Get report template
     */
    private function get_template($template_key) {
        global $wpdb;
        $table = BRST_Database::get_table_name('report_templates');

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE template_key = %s AND template_type = 'full_report' AND status = 'active'",
                $template_key
            )
        );

        if ($template) {
            return json_decode($template->content, true);
        }

        // Return default template structure
        return $this->get_default_template();
    }

    /**
     * Get default template
     */
    private function get_default_template() {
        return array(
            'sections' => array(
                'executive_summary' => '',
                'risk_analysis' => '',
                'category_breakdown' => '',
                'recommendations' => '',
                'action_items' => '',
            ),
        );
    }

    /**
     * Generate HTML for PDF
     */
    private function generate_html($template, $profile_analysis, $interaction, $category_scores, $responses) {
        $primary_profile = $profile_analysis['primary_profile']['data'];
        $is_interaction = $interaction['is_applicable'];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Business Risk Stress Test Report</title>
            <style>
                <?php echo $this->get_pdf_styles(); ?>
            </style>
        </head>
        <body>
            <div class="report-container">
                <!-- Header -->
                <div class="report-header">
                    <h1>Business Risk Stress Test</h1>
                    <h2>Full Report</h2>
                    <p class="report-date">Generated: <?php echo esc_html(date('F j, Y')); ?></p>
                </div>

                <!-- Profile Summary -->
                <div class="section profile-summary">
                    <h3>Your Business Profile</h3>
                    <div class="profile-badge">
                        <span class="profile-name"><?php echo esc_html($primary_profile['name']); ?></span>
                        <?php if ($is_interaction): ?>
                            <span class="interaction-badge">+ <?php echo esc_html($profile_analysis['secondary_profile']['data']['name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="profile-description"><?php echo esc_html($primary_profile['description']); ?></p>
                </div>

                <!-- Executive Summary -->
                <div class="section executive-summary">
                    <h3>Executive Summary</h3>
                    <?php if ($is_interaction): ?>
                        <p><?php echo esc_html($interaction_engine->get_interaction_description(
                            $profile_analysis['primary_profile']['key'],
                            $profile_analysis['secondary_profile']['key']
                        )); ?></p>
                    <?php else: ?>
                        <p><?php echo esc_html($template['sections']['executive_summary'] ?? $primary_profile['description']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Score Breakdown -->
                <div class="section score-breakdown">
                    <h3>Category Score Breakdown</h3>
                    <table class="score-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Score</th>
                                <th>Risk Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $scoring_engine = new BRST_Scoring_Engine();
                            foreach ($category_scores as $key => $data):
                                $risk_level = $scoring_engine->get_risk_level($data['percentage']);
                            ?>
                            <tr>
                                <td><?php echo esc_html($data['name']); ?></td>
                                <td><?php echo esc_html($data['score'] . '/' . $data['max_score'] . ' (' . $data['percentage'] . '%)'); ?></td>
                                <td style="color: <?php echo esc_attr($risk_level['color']); ?>">
                                    <?php echo esc_html($risk_level['label']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Primary Profile Analysis -->
                <div class="section profile-analysis">
                    <h3>Primary Profile: <?php echo esc_html($primary_profile['name']); ?></h3>
                    <div class="analysis-content">
                        <p><?php echo esc_html($template['sections']['primary_analysis'] ?? 'Based on your responses, you exhibit characteristics of this business profile.'); ?></p>

                        <h4>Key Characteristics</h4>
                        <ul>
                            <?php echo $this->get_profile_characteristics($profile_analysis['primary_profile']['key']); ?>
                        </ul>
                    </div>
                </div>

                <?php if ($is_interaction): ?>
                <!-- Secondary Profile Analysis -->
                <div class="section profile-analysis secondary">
                    <h3>Secondary Profile: <?php echo esc_html($profile_analysis['secondary_profile']['data']['name']); ?></h3>
                    <div class="analysis-content">
                        <p><?php echo esc_html($template['sections']['secondary_analysis'] ?? 'Your secondary profile contributes additional characteristics to consider.'); ?></p>

                        <h4>Key Characteristics</h4>
                        <ul>
                            <?php echo $this->get_profile_characteristics($profile_analysis['secondary_profile']['key']); ?>
                        </ul>
                    </div>
                </div>

                <!-- Interaction Effects -->
                <div class="section interaction-effects">
                    <h3>Profile Interaction Effects</h3>
                    <p><?php echo esc_html($template['sections']['interaction_effects'] ?? 'The combination of your primary and secondary profiles creates unique challenges.'); ?></p>
                </div>
                <?php endif; ?>

                <!-- Recommendations -->
                <div class="section recommendations">
                    <h3>Strategic Recommendations</h3>
                    <?php echo $this->get_profile_recommendations($profile_analysis['primary_profile']['key'], $is_interaction ? $profile_analysis['secondary_profile']['key'] : null); ?>
                </div>

                <!-- Action Items -->
                <div class="section action-items">
                    <h3>Priority Action Items</h3>
                    <?php echo $this->get_action_items($profile_analysis['primary_profile']['key']); ?>
                </div>

                <!-- Alignment Status -->
                <div class="section alignment-status">
                    <h3>Focus Alignment</h3>
                    <?php if ($profile_analysis['alignment']['is_aligned']): ?>
                        <p class="aligned">Your current focus (<?php echo esc_html($profile_analysis['q21_focus']); ?>) aligns with your primary business risk profile.</p>
                        <p>This suggests you're already addressing the right areas. Continue to prioritize actions that strengthen your position in these areas.</p>
                    <?php else: ?>
                        <p class="not-aligned">Your current focus (<?php echo esc_html($profile_analysis['q21_focus']); ?>) does not align with your primary business risk profile.</p>
                        <p>Consider whether realigning your focus could help address the key risks identified in this report.</p>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="report-footer">
                    <p>This report was generated by the Business Risk Stress Test Engine.</p>
                    <p>All outputs are rule-based and deterministic. No AI-generated content is used in this report.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get PDF styles
     */
    private function get_pdf_styles() {
        return '
            body {
                font-family: "Helvetica Neue", Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 20px;
            }
            .report-container {
                max-width: 800px;
                margin: 0 auto;
            }
            .report-header {
                text-align: center;
                padding-bottom: 20px;
                border-bottom: 2px solid #2c3e50;
                margin-bottom: 30px;
            }
            .report-header h1 {
                color: #2c3e50;
                margin-bottom: 10px;
            }
            .report-header h2 {
                color: #7f8c8d;
                font-weight: normal;
            }
            .report-date {
                color: #95a5a6;
                font-size: 10pt;
            }
            .section {
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ecf0f1;
            }
            .section h3 {
                color: #2c3e50;
                border-left: 4px solid #3498db;
                padding-left: 15px;
                margin-bottom: 15px;
            }
            .section h4 {
                color: #34495e;
                margin-top: 15px;
            }
            .profile-badge {
                display: inline-block;
                background: #3498db;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            .profile-badge .profile-name {
                font-weight: bold;
                font-size: 14pt;
            }
            .interaction-badge {
                background: #e74c3c;
                padding: 5px 10px;
                border-radius: 3px;
                margin-left: 10px;
                font-size: 11pt;
            }
            .score-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            .score-table th,
            .score-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ecf0f1;
            }
            .score-table th {
                background: #f8f9fa;
                font-weight: bold;
            }
            .aligned {
                color: #27ae60;
                font-weight: bold;
            }
            .not-aligned {
                color: #e74c3c;
                font-weight: bold;
            }
            .report-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #2c3e50;
                text-align: center;
                font-size: 10pt;
                color: #95a5a6;
            }
            ul {
                padding-left: 20px;
            }
            li {
                margin-bottom: 8px;
            }
        ';
    }

    /**
     * Get profile characteristics
     */
    private function get_profile_characteristics($profile_key) {
        $characteristics = array(
            'cash_tight' => array(
                'Cash flow inconsistency throughout business cycles',
                'Limited cash reserves for emergencies',
                'Challenges in accounts receivable management',
                'Dependent on timely customer payments',
            ),
            'revenue_concentrated' => array(
                'Heavy reliance on limited revenue sources',
                'Vulnerability to client concentration risk',
                'Limited product or service diversification',
                'Exposure to single market dependency',
            ),
            'cost_locked' => array(
                'Significant fixed cost commitments',
                'Limited flexibility in cost structure',
                'Long-term contractual obligations',
                'Challenges in scaling operations efficiently',
            ),
            'owner_dependent' => array(
                'Business heavily reliant on owner involvement',
                'Limited documentation of key processes',
                'Centralized decision-making',
                'Key relationships tied to owner',
            ),
            'externally_exposed' => array(
                'Vulnerability to supplier disruptions',
                'Exposure to regulatory changes',
                'Sensitivity to market conditions',
                'Limited protection against external shocks',
            ),
        );

        $items = $characteristics[$profile_key] ?? array('Business profile characteristics');
        $html = '';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        return $html;
    }

    /**
     * Get profile recommendations
     */
    private function get_profile_recommendations($primary_key, $secondary_key = null) {
        $recommendations = array(
            'cash_tight' => array(
                'Implement cash flow forecasting and monitoring',
                'Build emergency cash reserves (target 3-6 months)',
                'Improve accounts receivable collection processes',
                'Negotiate better payment terms with suppliers',
                'Consider invoice factoring or credit lines',
            ),
            'revenue_concentrated' => array(
                'Develop client diversification strategy',
                'Expand product or service offerings',
                'Identify and pursue new market segments',
                'Build relationships with multiple key accounts',
                'Create recurring revenue streams',
            ),
            'cost_locked' => array(
                'Review and renegotiate long-term contracts',
                'Identify variable cost alternatives',
                'Build flexibility into future commitments',
                'Implement cost monitoring and optimization',
                'Consider outsourcing non-core functions',
            ),
            'owner_dependent' => array(
                'Document key processes and procedures',
                'Delegate decision-making authority',
                'Build management team capabilities',
                'Transfer key relationships to team members',
                'Create succession and contingency plans',
            ),
            'externally_exposed' => array(
                'Diversify supplier base',
                'Monitor regulatory developments',
                'Build scenario planning capabilities',
                'Develop market intelligence systems',
                'Create contingency plans for disruptions',
            ),
        );

        $html = '<ul>';
        $primary_recs = $recommendations[$primary_key] ?? array();
        foreach ($primary_recs as $rec) {
            $html .= '<li>' . esc_html($rec) . '</li>';
        }

        if ($secondary_key && isset($recommendations[$secondary_key])) {
            $html .= '</ul><h4>Additional Recommendations (Secondary Profile)</h4><ul>';
            $secondary_recs = array_slice($recommendations[$secondary_key], 0, 3);
            foreach ($secondary_recs as $rec) {
                $html .= '<li>' . esc_html($rec) . '</li>';
            }
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Get action items
     */
    private function get_action_items($profile_key) {
        $actions = array(
            'cash_tight' => array(
                'This Week' => 'Set up weekly cash flow monitoring',
                'This Month' => 'Review and collect overdue receivables',
                'This Quarter' => 'Establish emergency fund savings plan',
            ),
            'revenue_concentrated' => array(
                'This Week' => 'List top 3 potential new clients',
                'This Month' => 'Launch one new service offering',
                'This Quarter' => 'Reduce largest client dependency by 10%',
            ),
            'cost_locked' => array(
                'This Week' => 'Audit all current contractual commitments',
                'This Month' => 'Identify 3 costs that could become variable',
                'This Quarter' => 'Renegotiate one major contract',
            ),
            'owner_dependent' => array(
                'This Week' => 'Identify 3 tasks to delegate',
                'This Month' => 'Document one key process',
                'This Quarter' => 'Train backup for one critical function',
            ),
            'externally_exposed' => array(
                'This Week' => 'Identify backup suppliers for critical inputs',
                'This Month' => 'Review regulatory compliance requirements',
                'This Quarter' => 'Develop market downturn contingency plan',
            ),
        );

        $items = $actions[$profile_key] ?? array();
        $html = '<table class="score-table"><thead><tr><th>Timeframe</th><th>Action</th></tr></thead><tbody>';
        foreach ($items as $timeframe => $action) {
            $html .= '<tr><td>' . esc_html($timeframe) . '</td><td>' . esc_html($action) . '</td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Create PDF file
     */
    private function create_pdf($html, $filepath) {
        // Check if DomPDF is available, otherwise use basic HTML to PDF conversion
        if (class_exists('Dompdf\Dompdf')) {
            return $this->create_pdf_dompdf($html, $filepath);
        }

        // Fallback: Save as HTML (can be converted to PDF by user)
        // In production, you would include a PDF library
        $html_file = str_replace('.pdf', '.html', $filepath);
        $result = file_put_contents($html_file, $html);

        if ($result === false) {
            return new WP_Error('pdf_creation_failed', __('Failed to create report file.', 'brst-engine'));
        }

        // For now, save as HTML - in production, integrate DomPDF or TCPDF
        // Return the HTML path as the PDF path for this implementation
        return true;
    }

    /**
     * Create PDF using DomPDF
     */
    private function create_pdf_dompdf($html, $filepath) {
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $output = $dompdf->output();
            file_put_contents($filepath, $output);

            return true;
        } catch (\Exception $e) {
            return new WP_Error('pdf_creation_failed', $e->getMessage());
        }
    }

    /**
     * Generate filename
     */
    private function generate_filename($submission_id) {
        return 'report_' . $submission_id . '_' . time() . '.pdf';
    }

    /**
     * Create report record
     */
    private function create_report_record($data) {
        global $wpdb;
        $table = BRST_Database::get_table_name('reports');

        $wpdb->insert($table, array(
            'submission_id' => intval($data['submission_id']),
            'payment_id' => intval($data['payment_id'] ?? 0),
            'report_type' => sanitize_text_field($data['report_type']),
            'template_id' => sanitize_text_field($data['template_id']),
            'file_path' => sanitize_text_field($data['file_path']),
            'file_url' => esc_url_raw($data['file_url']),
        ));

        return $wpdb->insert_id;
    }

    /**
     * Get report by submission ID
     */
    public function get_report_by_submission($submission_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('reports');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE submission_id = %d ORDER BY created_at DESC LIMIT 1", $submission_id)
        );
    }

    /**
     * Update report sent status
     */
    public function mark_as_sent($report_id) {
        global $wpdb;
        $table = BRST_Database::get_table_name('reports');

        return $wpdb->update(
            $table,
            array(
                'sent_at' => current_time('mysql'),
                'email_status' => 'sent',
            ),
            array('id' => $report_id)
        );
    }
}
