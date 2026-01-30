<?php
/**
 * Scoring Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scoring Engine class for calculating and processing scores
 * Supports dynamic question counts per category
 */
class BRST_Scoring_Engine {

    /**
     * Scoring rules
     */
    const SCORE_YES = 0;
    const SCORE_MAYBE = 2;
    const SCORE_NO = 3;

    /**
     * Maximum score per question
     */
    const MAX_SCORE_PER_QUESTION = 3;

    /**
     * Category definitions - built dynamically from questions
     */
    private $categories = array();

    /**
     * Category display names (defaults)
     */
    private $default_category_names = array(
        'cash_tight' => 'Cash-Tight Operator',
        'revenue_concentrated' => 'Revenue-Concentrated Builder',
        'cost_locked' => 'Cost-Locked Business',
        'owner_dependent' => 'Owner-Dependent Engine',
        'externally_exposed' => 'Externally Exposed Builder',
    );

    /**
     * Active category names (custom + defaults)
     */
    private $category_names = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_category_names();
        $this->build_categories_from_questions();

        /**
         * Filter: brst_scoring_categories
         * Allows modification of category definitions
         */
        $this->categories = apply_filters('brst_scoring_categories', $this->categories);
    }

    /**
     * Load category names from options (custom names override defaults)
     */
    private function load_category_names() {
        $this->category_names = $this->default_category_names;

        $custom_names = get_option('brst_category_names');
        if (!empty($custom_names) && is_array($custom_names)) {
            $this->category_names = array_merge($this->category_names, $custom_names);
        }
    }

    /**
     * Build category definitions dynamically from the questions list
     */
    private function build_categories_from_questions() {
        $form_engine = new BRST_Form_Engine();
        $questions = $form_engine->get_questions();

        $this->categories = array();

        foreach ($questions as $index => $question) {
            $q_num = $index + 1;
            $category = $question['category'];

            // Skip non-scored questions (personalization)
            if (empty($question['scored'])) {
                continue;
            }

            if (!isset($this->categories[$category])) {
                $this->categories[$category] = array(
                    'name' => $this->category_names[$category] ?? ucwords(str_replace('_', ' ', $category)),
                    'questions' => array(),
                );
            }

            $this->categories[$category]['questions'][] = $q_num;
        }
    }

    /**
     * Get categories
     */
    public function get_categories() {
        return $this->categories;
    }

    /**
     * Calculate score for an answer
     */
    public function get_answer_score($answer) {
        $answer = strtolower(trim($answer));

        /**
         * Filter: brst_answer_score
         * Allows modification of score for an answer
         */
        $scores = apply_filters('brst_answer_scores', array(
            'yes' => self::SCORE_YES,
            'maybe' => self::SCORE_MAYBE,
            'no' => self::SCORE_NO,
        ));

        return $scores[$answer] ?? 0;
    }

    /**
     * Calculate all scores from responses
     *
     * @param array $responses Array of question responses (q1 => 'yes', q2 => 'no', etc.)
     * @return array Comprehensive scoring data
     */
    public function calculate_scores($responses) {
        $form_engine = new BRST_Form_Engine();
        $questions = $form_engine->get_questions();

        $individual_scores = array();
        $category_scores = array();
        $total_score = 0;
        $max_possible_score = 0;
        $q21_response = '';
        $unscored_responses = array();

        // Initialize category scores dynamically
        foreach ($this->categories as $key => $category) {
            $question_count = count($category['questions']);
            $category_scores[$key] = array(
                'name' => $category['name'],
                'score' => 0,
                'max_score' => $question_count * self::MAX_SCORE_PER_QUESTION,
                'percentage' => 0,
                'questions' => array(),
            );
        }

        // Calculate scores for all questions
        foreach ($questions as $index => $question) {
            $q_num = $index + 1;
            $question_key = "q{$q_num}";
            $answer = $responses[$question_key] ?? '';

            if (!empty($question['scored'])) {
                // Scored question
                $score = $this->get_answer_score($answer);

                $individual_scores[$question_key] = array(
                    'answer' => $answer,
                    'score' => $score,
                );

                // Add to category
                $category_key = $question['category'];
                if (isset($category_scores[$category_key])) {
                    $category_scores[$category_key]['score'] += $score;
                    $category_scores[$category_key]['questions'][$question_key] = $score;
                }

                $total_score += $score;
                $max_possible_score += self::MAX_SCORE_PER_QUESTION;
            } else {
                // Unscored question (personalization)
                $unscored_responses[$question_key] = $answer;
                // Keep backward compatibility - the last unscored question is q21_response
                $q21_response = $answer;
            }
        }

        // Calculate percentages for each category
        foreach ($category_scores as $key => &$category) {
            $category['percentage'] = $this->calculate_percentage(
                $category['score'],
                $category['max_score']
            );
        }

        // Calculate overall percentage
        $overall_percentage = $this->calculate_percentage($total_score, $max_possible_score);

        $result = array(
            'individual_scores' => $individual_scores,
            'category_scores' => $category_scores,
            'total_score' => $total_score,
            'max_possible_score' => $max_possible_score,
            'overall_percentage' => $overall_percentage,
            'q21_response' => $q21_response,
            'unscored_responses' => $unscored_responses,
        );

        /**
         * Filter: brst_calculated_scores
         * Allows modification of calculated scores
         */
        return apply_filters('brst_calculated_scores', $result, $responses);
    }

    /**
     * Get category key for a question number
     */
    public function get_category_for_question($question_num) {
        foreach ($this->categories as $key => $category) {
            if (in_array($question_num, $category['questions'])) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Calculate percentage
     */
    public function calculate_percentage($score, $max_score) {
        if ($max_score <= 0) {
            return 0;
        }
        return round(($score / $max_score) * 100, 2);
    }

    /**
     * Get risk level based on percentage
     */
    public function get_risk_level($percentage) {
        if ($percentage >= 75) {
            return array(
                'level' => 'high',
                'label' => __('High Risk', 'brst-engine'),
                'color' => '#dc3545',
            );
        } elseif ($percentage >= 50) {
            return array(
                'level' => 'medium',
                'label' => __('Medium Risk', 'brst-engine'),
                'color' => '#ffc107',
            );
        } elseif ($percentage >= 25) {
            return array(
                'level' => 'moderate',
                'label' => __('Moderate Risk', 'brst-engine'),
                'color' => '#17a2b8',
            );
        }
        return array(
            'level' => 'low',
            'label' => __('Low Risk', 'brst-engine'),
            'color' => '#28a745',
        );
    }

    /**
     * Get sorted categories by score
     */
    public function get_sorted_categories($category_scores) {
        $sorted = $category_scores;
        uasort($sorted, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        return $sorted;
    }

    /**
     * Validate responses format - dynamic based on actual questions
     */
    public function validate_responses($responses) {
        $errors = array();
        $valid_answers = array('yes', 'maybe', 'no');

        $form_engine = new BRST_Form_Engine();
        $questions = $form_engine->get_questions();

        foreach ($questions as $index => $question) {
            $q_num = $index + 1;
            $question_key = "q{$q_num}";

            if (!isset($responses[$question_key]) || empty($responses[$question_key])) {
                $errors[$question_key] = sprintf(
                    __('Question %d is required.', 'brst-engine'),
                    $q_num
                );
                continue;
            }

            if (!empty($question['scored'])) {
                // Scored questions must be yes/maybe/no
                $answer = strtolower(trim($responses[$question_key]));
                if (!in_array($answer, $valid_answers)) {
                    $errors[$question_key] = sprintf(
                        __('Invalid answer for question %d.', 'brst-engine'),
                        $q_num
                    );
                }
            }
            // Unscored questions accept any non-empty value
        }

        return $errors;
    }

    /**
     * Get category score summary for display
     */
    public function get_score_summary($category_scores) {
        $summary = array();

        foreach ($category_scores as $key => $data) {
            $risk_level = $this->get_risk_level($data['percentage']);

            $summary[$key] = array(
                'name' => $data['name'],
                'score' => $data['score'],
                'max_score' => $data['max_score'],
                'percentage' => $data['percentage'],
                'risk_level' => $risk_level['level'],
                'risk_label' => $risk_level['label'],
                'risk_color' => $risk_level['color'],
            );
        }

        return $summary;
    }

    /**
     * Format scores for storage
     */
    public function format_for_storage($scores_data) {
        return array(
            'individual' => wp_json_encode($scores_data['individual_scores']),
            'categories' => wp_json_encode($scores_data['category_scores']),
            'total' => $scores_data['total_score'],
            'max' => $scores_data['max_possible_score'],
            'percentage' => $scores_data['overall_percentage'],
        );
    }

    /**
     * Get question score breakdown
     */
    public function get_question_breakdown($individual_scores) {
        $breakdown = array();

        $form_engine = new BRST_Form_Engine();
        $questions = $form_engine->get_questions();

        foreach ($individual_scores as $q_key => $data) {
            $q_num = intval(str_replace('q', '', $q_key));
            if ($q_num > 0 && isset($questions[$q_num - 1])) {
                $question = $questions[$q_num - 1];
                if (!empty($question['scored'])) {
                    $breakdown[$q_key] = array(
                        'question_number' => $q_num,
                        'question_text' => $question['text'],
                        'category' => $question['category'],
                        'answer' => $data['answer'],
                        'score' => $data['score'],
                        'max_score' => self::MAX_SCORE_PER_QUESTION,
                    );
                }
            }
        }

        return $breakdown;
    }
}
