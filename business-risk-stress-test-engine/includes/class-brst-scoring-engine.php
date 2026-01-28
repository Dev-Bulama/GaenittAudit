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
     * Questions per category
     */
    const QUESTIONS_PER_CATEGORY = 4;

    /**
     * Maximum score per category
     */
    const MAX_CATEGORY_SCORE = 12; // 4 questions × 3 points

    /**
     * Category definitions
     */
    private $categories = array(
        'cash_tight' => array(
            'name' => 'Cash-Tight Operator',
            'questions' => array(1, 2, 3, 4),
        ),
        'revenue_concentrated' => array(
            'name' => 'Revenue-Concentrated Builder',
            'questions' => array(5, 6, 7, 8),
        ),
        'cost_locked' => array(
            'name' => 'Cost-Locked Business',
            'questions' => array(9, 10, 11, 12),
        ),
        'owner_dependent' => array(
            'name' => 'Owner-Dependent Engine',
            'questions' => array(13, 14, 15, 16),
        ),
        'externally_exposed' => array(
            'name' => 'Externally Exposed Builder',
            'questions' => array(17, 18, 19, 20),
        ),
    );

    /**
     * Constructor
     */
    public function __construct() {
        /**
         * Filter: brst_scoring_categories
         * Allows modification of category definitions
         */
        $this->categories = apply_filters('brst_scoring_categories', $this->categories);
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
        $individual_scores = array();
        $category_scores = array();
        $total_score = 0;
        $max_possible_score = 0;

        // Initialize category scores
        foreach ($this->categories as $key => $category) {
            $category_scores[$key] = array(
                'name' => $category['name'],
                'score' => 0,
                'max_score' => self::MAX_CATEGORY_SCORE,
                'percentage' => 0,
                'questions' => array(),
            );
        }

        // Calculate individual question scores (Q1-Q20 only)
        for ($i = 1; $i <= 20; $i++) {
            $question_key = "q{$i}";
            $answer = $responses[$question_key] ?? '';
            $score = $this->get_answer_score($answer);

            $individual_scores[$question_key] = array(
                'answer' => $answer,
                'score' => $score,
            );

            // Add to category
            $category_key = $this->get_category_for_question($i);
            if ($category_key) {
                $category_scores[$category_key]['score'] += $score;
                $category_scores[$category_key]['questions'][$question_key] = $score;
            }

            $total_score += $score;
            $max_possible_score += self::MAX_SCORE_PER_QUESTION;
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

        // Store Q21 separately (not scored)
        $q21_response = $responses['q21'] ?? '';

        $result = array(
            'individual_scores' => $individual_scores,
            'category_scores' => $category_scores,
            'total_score' => $total_score,
            'max_possible_score' => $max_possible_score,
            'overall_percentage' => $overall_percentage,
            'q21_response' => $q21_response,
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
     * Validate responses format
     */
    public function validate_responses($responses) {
        $errors = array();
        $valid_answers = array('yes', 'maybe', 'no');

        // Validate Q1-Q20
        for ($i = 1; $i <= 20; $i++) {
            $question_key = "q{$i}";

            if (!isset($responses[$question_key]) || empty($responses[$question_key])) {
                $errors[$question_key] = sprintf(
                    __('Question %d is required.', 'brst-engine'),
                    $i
                );
                continue;
            }

            $answer = strtolower(trim($responses[$question_key]));
            if (!in_array($answer, $valid_answers)) {
                $errors[$question_key] = sprintf(
                    __('Invalid answer for question %d.', 'brst-engine'),
                    $i
                );
            }
        }

        // Validate Q21
        $valid_q21 = array('a', 'b', 'c', 'd', 'e');
        if (!isset($responses['q21']) || !in_array($responses['q21'], $valid_q21)) {
            $errors['q21'] = __('Please select your current primary focus area.', 'brst-engine');
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
            if ($q_num > 0 && $q_num <= 20 && isset($questions[$q_num - 1])) {
                $question = $questions[$q_num - 1];
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

        return $breakdown;
    }
}
