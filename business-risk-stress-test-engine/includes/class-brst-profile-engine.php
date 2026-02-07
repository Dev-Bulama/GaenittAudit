<?php
/**
 * Profile Engine
 *
 * @package BusinessRiskStressTest
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Profile Engine class for determining business profiles
 */
class BRST_Profile_Engine {

    /**
     * Profile definitions
     */
    private $profiles = array(
        'cash_tight' => array(
            'key' => 'cash_tight',
            'name' => 'Cash-Tight Operator',
            'short_name' => 'Cash-Tight',
            'description' => 'Your business shows characteristics of cash flow management challenges. This profile indicates potential vulnerabilities in liquidity, receivables management, and cash reserves.',
            'focus_area' => 'c', // Q21 mapping
        ),
        'revenue_concentrated' => array(
            'key' => 'revenue_concentrated',
            'name' => 'Revenue-Concentrated Builder',
            'short_name' => 'Revenue-Concentrated',
            'description' => 'Your business relies heavily on concentrated revenue sources. This profile indicates potential vulnerabilities in client diversification and revenue stream variety.',
            'focus_area' => 'a', // Q21 mapping
        ),
        'cost_locked' => array(
            'key' => 'cost_locked',
            'name' => 'Cost-Locked Business',
            'short_name' => 'Cost-Locked',
            'description' => 'Your business has significant fixed cost structures. This profile indicates potential challenges in cost flexibility and operational scalability.',
            'focus_area' => 'b', // Q21 mapping
        ),
        'owner_dependent' => array(
            'key' => 'owner_dependent',
            'name' => 'Owner-Dependent Engine',
            'short_name' => 'Owner-Dependent',
            'description' => 'Your business heavily depends on owner involvement. This profile indicates potential risks in business continuity and scalability.',
            'focus_area' => 'd', // Q21 mapping
        ),
        'externally_exposed' => array(
            'key' => 'externally_exposed',
            'name' => 'Externally Exposed Builder',
            'short_name' => 'Externally Exposed',
            'description' => 'Your business is exposed to external market factors. This profile indicates potential vulnerabilities to supplier, regulatory, or market changes.',
            'focus_area' => 'e', // Q21 mapping
        ),
    );

    /**
     * Q21 Focus Area Mapping
     */
    private $focus_mapping = array(
        'a' => 'revenue_concentrated', // Sales
        'b' => 'cost_locked',          // Costs
        'c' => 'cash_tight',           // Cash
        'd' => 'owner_dependent',      // Operations
        'e' => 'externally_exposed',   // External factors
    );

    /**
     * Constructor
     */
    public function __construct() {
        /**
         * Filter: brst_profile_definitions
         * Allows modification of profile definitions
         */
        $this->profiles = apply_filters('brst_profile_definitions', $this->profiles);

        /**
         * Filter: brst_focus_mapping
         * Allows modification of Q21 focus mapping
         */
        $this->focus_mapping = apply_filters('brst_focus_mapping', $this->focus_mapping);
    }

    /**
     * Get all profiles
     */
    public function get_profiles() {
        return $this->profiles;
    }

    /**
     * Get a specific profile
     */
    public function get_profile($key) {
        return $this->profiles[$key] ?? null;
    }

    /**
     * Tie-breaker priority order (lower index = higher priority)
     * When two or more categories have the same score, the one with higher priority wins.
     */
    private $priority_order = array(
        'cash_tight',           // 1st priority - cash flow is most critical
        'revenue_concentrated', // 2nd priority - revenue diversity
        'owner_dependent',      // 3rd priority - business continuity
        'cost_locked',          // 4th priority - cost flexibility
        'externally_exposed',   // 5th priority - external factors
    );

    /**
     * Determine primary profile from category scores
     *
     * Tie-breaker Logic:
     * When two or more categories have the same highest score, the system uses
     * a priority ranking to determine the primary profile:
     * 1. Cash-Tight Operator (cash_tight)
     * 2. Revenue-Concentrated Builder (revenue_concentrated)
     * 3. Owner-Dependent Engine (owner_dependent)
     * 4. Cost-Locked Business (cost_locked)
     * 5. Externally Exposed Builder (externally_exposed)
     *
     * @param array $category_scores Array of category scores
     * @return string Primary profile key
     */
    public function get_primary_profile($category_scores) {
        $highest_score = -1;
        $tied_profiles = array();

        // Find the highest score and all profiles with that score
        foreach ($category_scores as $key => $data) {
            $score = $data['score'] ?? 0;
            if ($score > $highest_score) {
                $highest_score = $score;
                $tied_profiles = array($key);
            } elseif ($score == $highest_score && $highest_score > -1) {
                $tied_profiles[] = $key;
            }
        }

        // If only one profile has the highest score, return it
        if (count($tied_profiles) === 1) {
            $primary_profile = $tied_profiles[0];
        } else {
            // Tie-breaker: use priority order
            $primary_profile = $this->resolve_tie($tied_profiles);
        }

        /**
         * Filter: brst_primary_profile
         * Allows modification of primary profile determination
         */
        return apply_filters('brst_primary_profile', $primary_profile, $category_scores);
    }

    /**
     * Resolve tie between profiles using priority order
     *
     * @param array $tied_profiles Array of profile keys that have the same score
     * @return string The winning profile key
     */
    private function resolve_tie($tied_profiles) {
        foreach ($this->priority_order as $priority_profile) {
            if (in_array($priority_profile, $tied_profiles)) {
                return $priority_profile;
            }
        }
        // Fallback to first tied profile if none in priority list
        return $tied_profiles[0];
    }

    /**
     * Determine secondary profile from category scores
     *
     * Uses the same tie-breaker priority as primary profile.
     *
     * @param array $category_scores Array of category scores
     * @param string $primary_profile Primary profile key to exclude
     * @return string Secondary profile key
     */
    public function get_secondary_profile($category_scores, $primary_profile) {
        $highest_score = -1;
        $tied_profiles = array();

        foreach ($category_scores as $key => $data) {
            if ($key === $primary_profile) {
                continue;
            }

            $score = $data['score'] ?? 0;
            if ($score > $highest_score) {
                $highest_score = $score;
                $tied_profiles = array($key);
            } elseif ($score == $highest_score && $highest_score > -1) {
                $tied_profiles[] = $key;
            }
        }

        // Resolve ties using priority
        $secondary_profile = count($tied_profiles) === 1
            ? $tied_profiles[0]
            : $this->resolve_tie($tied_profiles);

        /**
         * Filter: brst_secondary_profile
         * Allows modification of secondary profile determination
         */
        return apply_filters('brst_secondary_profile', $secondary_profile, $category_scores, $primary_profile);
    }

    /**
     * Get score difference between two profiles
     */
    public function get_score_difference($category_scores, $profile1, $profile2) {
        $score1 = $category_scores[$profile1]['score'] ?? 0;
        $score2 = $category_scores[$profile2]['score'] ?? 0;

        return abs($score1 - $score2);
    }

    /**
     * Determine alignment status based on Q21 response
     *
     * @param string $primary_profile Primary profile key
     * @param string $q21_response Q21 answer (a, b, c, d, or e)
     * @return array Alignment data
     */
    public function determine_alignment($primary_profile, $q21_response) {
        $q21_profile = $this->focus_mapping[$q21_response] ?? null;
        $is_aligned = ($q21_profile === $primary_profile);

        $result = array(
            'is_aligned' => $is_aligned,
            'status' => $is_aligned ? 'aligned' : 'not_aligned',
            'primary_profile' => $primary_profile,
            'focus_profile' => $q21_profile,
            'q21_response' => $q21_response,
        );

        /**
         * Filter: brst_alignment_result
         * Allows modification of alignment determination
         */
        return apply_filters('brst_alignment_result', $result, $primary_profile, $q21_response);
    }

    /**
     * Get focus area description for Q21 option
     */
    public function get_focus_description($q21_option) {
        $descriptions = array(
            'a' => __('Sales and revenue growth', 'brst-engine'),
            'b' => __('Cost reduction and efficiency', 'brst-engine'),
            'c' => __('Cash flow management', 'brst-engine'),
            'd' => __('Operations and processes', 'brst-engine'),
            'e' => __('External factors and market positioning', 'brst-engine'),
        );

        return $descriptions[$q21_option] ?? '';
    }

    /**
     * Get profile mapped to Q21 response
     */
    public function get_profile_from_focus($q21_response) {
        $profile_key = $this->focus_mapping[$q21_response] ?? null;
        return $profile_key ? $this->profiles[$profile_key] : null;
    }

    /**
     * Determine all profiles with ranking
     */
    public function get_profile_ranking($category_scores) {
        $ranking = array();

        foreach ($category_scores as $key => $data) {
            $ranking[$key] = array(
                'key' => $key,
                'name' => $this->profiles[$key]['name'] ?? $key,
                'score' => $data['score'],
                'percentage' => $data['percentage'],
            );
        }

        // Sort by score descending
        uasort($ranking, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Add rank
        $rank = 1;
        foreach ($ranking as &$item) {
            $item['rank'] = $rank++;
        }

        return $ranking;
    }

    /**
     * Get complete profile analysis
     */
    public function analyze_profiles($category_scores, $q21_response) {
        $primary = $this->get_primary_profile($category_scores);
        $secondary = $this->get_secondary_profile($category_scores, $primary);
        $alignment = $this->determine_alignment($primary, $q21_response);
        $score_difference = $this->get_score_difference($category_scores, $primary, $secondary);
        $ranking = $this->get_profile_ranking($category_scores);

        $analysis = array(
            'primary_profile' => array(
                'key' => $primary,
                'data' => $this->profiles[$primary] ?? null,
                'score' => $category_scores[$primary]['score'] ?? 0,
                'percentage' => $category_scores[$primary]['percentage'] ?? 0,
            ),
            'secondary_profile' => array(
                'key' => $secondary,
                'data' => $this->profiles[$secondary] ?? null,
                'score' => $category_scores[$secondary]['score'] ?? 0,
                'percentage' => $category_scores[$secondary]['percentage'] ?? 0,
            ),
            'alignment' => $alignment,
            'score_difference' => $score_difference,
            'ranking' => $ranking,
            'q21_response' => $q21_response,
            'q21_focus' => $this->get_focus_description($q21_response),
        );

        /**
         * Filter: brst_profile_analysis
         * Allows modification of complete profile analysis
         */
        return apply_filters('brst_profile_analysis', $analysis, $category_scores, $q21_response);
    }

    /**
     * Get mini report template key
     */
    public function get_mini_report_template_key($primary_profile, $alignment_status) {
        return "mini_{$primary_profile}_{$alignment_status}";
    }

    /**
     * Check if profile combination is notable
     */
    public function is_notable_combination($primary, $secondary, $score_difference) {
        // A combination is notable if the scores are close (within 3 points)
        return $score_difference <= 3;
    }
}
