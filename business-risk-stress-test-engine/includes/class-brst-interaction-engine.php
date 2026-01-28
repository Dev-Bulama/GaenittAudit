<?php
/**
 * Interaction Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interaction Engine class for determining profile interactions
 */
class BRST_Interaction_Engine {

    /**
     * Maximum score difference for interaction to apply
     */
    const MAX_SCORE_DIFFERENCE = 3;

    /**
     * Predefined interaction pairs
     * Each primary profile has ONE predefined secondary interaction
     */
    private $interaction_pairs = array(
        'cash_tight' => 'revenue_concentrated',
        'revenue_concentrated' => 'cost_locked',
        'cost_locked' => 'owner_dependent',
        'owner_dependent' => 'externally_exposed',
        'externally_exposed' => 'cash_tight',
    );

    /**
     * Constructor
     */
    public function __construct() {
        /**
         * Filter: brst_interaction_pairs
         * Allows modification of predefined interaction pairs
         */
        $this->interaction_pairs = apply_filters('brst_interaction_pairs', $this->interaction_pairs);
    }

    /**
     * Get interaction pairs
     */
    public function get_interaction_pairs() {
        return $this->interaction_pairs;
    }

    /**
     * Get the predefined interaction partner for a primary profile
     */
    public function get_interaction_partner($primary_profile) {
        return $this->interaction_pairs[$primary_profile] ?? null;
    }

    /**
     * Check if interaction is applicable
     *
     * Interaction applies ONLY IF:
     * 1. Secondary profile matches predefined interaction pair for the primary
     * 2. Score difference between Primary and Secondary is <= 3 points
     *
     * @param string $primary_profile Primary profile key
     * @param string $secondary_profile Secondary profile key (actual second-highest score)
     * @param int $score_difference Score difference between primary and secondary
     * @return bool Whether interaction is applicable
     */
    public function is_interaction_applicable($primary_profile, $secondary_profile, $score_difference) {
        // Condition 1: Secondary must match predefined pair
        $predefined_partner = $this->get_interaction_partner($primary_profile);
        if ($predefined_partner !== $secondary_profile) {
            return false;
        }

        // Condition 2: Score difference must be 3 points or less
        if ($score_difference > self::MAX_SCORE_DIFFERENCE) {
            return false;
        }

        /**
         * Filter: brst_is_interaction_applicable
         * Allows modification of interaction applicability check
         */
        return apply_filters(
            'brst_is_interaction_applicable',
            true,
            $primary_profile,
            $secondary_profile,
            $score_difference
        );
    }

    /**
     * Determine interaction status with full details
     */
    public function determine_interaction($category_scores, $primary_profile, $secondary_profile) {
        $primary_score = $category_scores[$primary_profile]['score'] ?? 0;
        $secondary_score = $category_scores[$secondary_profile]['score'] ?? 0;
        $score_difference = abs($primary_score - $secondary_score);

        $is_applicable = $this->is_interaction_applicable(
            $primary_profile,
            $secondary_profile,
            $score_difference
        );

        $predefined_partner = $this->get_interaction_partner($primary_profile);

        $result = array(
            'is_applicable' => $is_applicable,
            'primary_profile' => $primary_profile,
            'secondary_profile' => $secondary_profile,
            'predefined_partner' => $predefined_partner,
            'matches_predefined' => ($secondary_profile === $predefined_partner),
            'primary_score' => $primary_score,
            'secondary_score' => $secondary_score,
            'score_difference' => $score_difference,
            'max_difference_allowed' => self::MAX_SCORE_DIFFERENCE,
            'within_score_threshold' => ($score_difference <= self::MAX_SCORE_DIFFERENCE),
            'report_type' => $is_applicable ? 'interaction' : 'standalone',
        );

        // Add explanation for why interaction was or wasn't applied
        $result['explanation'] = $this->get_interaction_explanation($result);

        /**
         * Filter: brst_interaction_result
         * Allows modification of interaction determination result
         */
        return apply_filters('brst_interaction_result', $result, $category_scores);
    }

    /**
     * Get explanation for interaction determination
     */
    private function get_interaction_explanation($result) {
        if ($result['is_applicable']) {
            return sprintf(
                __('Interaction report selected: Your secondary profile (%s) matches the predefined interaction pair for your primary profile (%s), and the score difference of %d points is within the %d-point threshold.', 'brst-engine'),
                $result['secondary_profile'],
                $result['primary_profile'],
                $result['score_difference'],
                self::MAX_SCORE_DIFFERENCE
            );
        }

        $reasons = array();

        if (!$result['matches_predefined']) {
            $reasons[] = sprintf(
                __('Your secondary profile (%s) does not match the predefined interaction partner (%s) for your primary profile.', 'brst-engine'),
                $result['secondary_profile'],
                $result['predefined_partner']
            );
        }

        if (!$result['within_score_threshold']) {
            $reasons[] = sprintf(
                __('The score difference of %d points exceeds the maximum threshold of %d points.', 'brst-engine'),
                $result['score_difference'],
                self::MAX_SCORE_DIFFERENCE
            );
        }

        return sprintf(
            __('Standalone report selected: %s', 'brst-engine'),
            implode(' ', $reasons)
        );
    }

    /**
     * Get the appropriate report template key
     */
    public function get_report_template_key($primary_profile, $secondary_profile, $is_interaction) {
        if ($is_interaction) {
            return "full_{$primary_profile}_{$secondary_profile}_interaction";
        }
        return "full_{$primary_profile}_standalone";
    }

    /**
     * Get interaction description for reports
     */
    public function get_interaction_description($primary_profile, $secondary_profile) {
        $profile_engine = new BRST_Profile_Engine();
        $primary_data = $profile_engine->get_profile($primary_profile);
        $secondary_data = $profile_engine->get_profile($secondary_profile);

        if (!$primary_data || !$secondary_data) {
            return '';
        }

        return sprintf(
            __('Your business exhibits a primary profile of %s with significant secondary characteristics of %s. This interaction creates unique challenges and opportunities that are addressed in this report.', 'brst-engine'),
            $primary_data['name'],
            $secondary_data['name']
        );
    }

    /**
     * Get all valid interaction combinations
     */
    public function get_valid_combinations() {
        $combinations = array();

        foreach ($this->interaction_pairs as $primary => $secondary) {
            $profile_engine = new BRST_Profile_Engine();
            $primary_data = $profile_engine->get_profile($primary);
            $secondary_data = $profile_engine->get_profile($secondary);

            $combinations[] = array(
                'primary' => $primary,
                'primary_name' => $primary_data['name'] ?? $primary,
                'secondary' => $secondary,
                'secondary_name' => $secondary_data['name'] ?? $secondary,
                'template_key' => "full_{$primary}_{$secondary}_interaction",
            );
        }

        return $combinations;
    }

    /**
     * Validate interaction configuration
     */
    public function validate_configuration() {
        $profile_engine = new BRST_Profile_Engine();
        $profiles = $profile_engine->get_profiles();
        $errors = array();

        foreach ($this->interaction_pairs as $primary => $secondary) {
            if (!isset($profiles[$primary])) {
                $errors[] = sprintf(
                    __('Invalid primary profile in interaction pair: %s', 'brst-engine'),
                    $primary
                );
            }
            if (!isset($profiles[$secondary])) {
                $errors[] = sprintf(
                    __('Invalid secondary profile in interaction pair: %s', 'brst-engine'),
                    $secondary
                );
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }
}
