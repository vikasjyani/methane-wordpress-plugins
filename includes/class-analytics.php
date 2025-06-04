<?php
/**
 * Methane Monitor Analytics Class
 * 
 * Handles analytics calculations and data generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Analytics {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Methane_Monitor_Database();
    }
    
    /**
     * Generate time series data for a specific district
     */
    public function generate_timeseries_data($state_name, $district_name) {
        global $wpdb;
        
        $state_name = strtoupper($state_name);
        $district_name = strtoupper($district_name);
        
        // Get monthly aggregated data for the district
        $sql = "SELECT 
                    YEAR(e.measurement_date) as year,
                    MONTH(e.measurement_date) as month,
                    AVG(e.emission_value) as avg_emission,
                    MIN(e.emission_value) as min_emission,
                    MAX(e.emission_value) as max_emission,
                    COUNT(e.id) as data_points,
                    STDDEV(e.emission_value) as std_emission
                FROM {$wpdb->prefix}methane_emissions e
                JOIN {$wpdb->prefix}methane_districts d ON e.district_id = d.id
                JOIN {$wpdb->prefix}methane_states s ON e.state_id = s.id
                WHERE s.state_name = %s AND d.district_name = %s
                GROUP BY YEAR(e.measurement_date), MONTH(e.measurement_date)
                ORDER BY YEAR(e.measurement_date), MONTH(e.measurement_date)";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $state_name, $district_name),
            ARRAY_A
        );
        
        if (empty($results)) {
            return array(
                'error' => sprintf(__('No time series data found for %s, %s', 'methane-monitor'), $district_name, $state_name),
                'time_series' => array(),
                'statistics' => array(),
                'trends' => array()
            );
        }
        
        // Format time series data
        $time_series = array();
        $values = array();
        
        foreach ($results as $row) {
            $date = sprintf('%04d-%02d-01', $row['year'], $row['month']);
            $value = floatval($row['avg_emission']);
            
            $time_series[] = array(
                'date' => $date,
                'year' => intval($row['year']),
                'month' => intval($row['month']),
                'value' => $value,
                'min' => floatval($row['min_emission']),
                'max' => floatval($row['max_emission']),
                'std' => floatval($row['std_emission']),
                'data_points' => intval($row['data_points'])
            );
            
            $values[] = $value;
        }
        
        // Calculate statistics
        $statistics = $this->calculate_timeseries_statistics($values, $time_series);
        
        // Calculate trends
        $trends = $this->calculate_trends($time_series);
        
        return array(
            'time_series' => $time_series,
            'statistics' => $statistics,
            'trends' => $trends,
            'peak_month' => $this->find_peak_month($time_series),
            'seasonal_pattern' => $this->analyze_seasonal_pattern($time_series),
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Generate clustering data for districts within a state
     */
    public function generate_clustering_data($state_name) {
        global $wpdb;
        
        $state_name = strtoupper($state_name);
        
        // Get average emissions for all districts in the state
        $sql = "SELECT 
                    d.district_name,
                    AVG(e.emission_value) as average_methane,
                    STDDEV(e.emission_value) as std_methane,
                    COUNT(e.id) as data_points
                FROM {$wpdb->prefix}methane_districts d
                JOIN {$wpdb->prefix}methane_states s ON d.state_id = s.id
                LEFT JOIN {$wpdb->prefix}methane_emissions e ON d.id = e.district_id
                WHERE s.state_name = %s
                GROUP BY d.id, d.district_name
                HAVING data_points > 0
                ORDER BY average_methane DESC";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $state_name),
            ARRAY_A
        );
        
        if (empty($results)) {
            return array(
                'error' => sprintf(__('No clustering data found for %s', 'methane-monitor'), $state_name),
                'district_clusters' => array(),
                'cluster_info' => array()
            );
        }
        
        // Perform simple k-means clustering
        $clusters = $this->perform_kmeans_clustering($results);
        
        return array(
            'district_clusters' => $clusters['districts'],
            'cluster_info' => $clusters['info'],
            'n_clusters' => $clusters['n_clusters'],
            'inertia' => $clusters['inertia'],
            'silhouette_score' => $clusters['silhouette_score'],
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Generate ranking data for states or districts
     */
    public function generate_ranking_data($year, $month) {
        global $wpdb;
        
        // State rankings
        $state_sql = "SELECT 
                        s.state_name,
                        AVG(e.emission_value) as avg_emission,
                        COUNT(e.id) as data_points,
                        MIN(e.emission_value) as min_emission,
                        MAX(e.emission_value) as max_emission
                      FROM {$wpdb->prefix}methane_states s
                      LEFT JOIN {$wpdb->prefix}methane_emissions e ON s.id = e.state_id
                          AND YEAR(e.measurement_date) = %d 
                          AND MONTH(e.measurement_date) = %d
                      GROUP BY s.id, s.state_name
                      HAVING data_points > 0
                      ORDER BY avg_emission DESC";
        
        $state_rankings = $wpdb->get_results(
            $wpdb->prepare($state_sql, $year, $month),
            ARRAY_A
        );
        
        // District rankings
        $district_sql = "SELECT 
                           d.district_name,
                           s.state_name,
                           AVG(e.emission_value) as avg_emission,
                           COUNT(e.id) as data_points
                         FROM {$wpdb->prefix}methane_districts d
                         JOIN {$wpdb->prefix}methane_states s ON d.state_id = s.id
                         LEFT JOIN {$wpdb->prefix}methane_emissions e ON d.id = e.district_id
                             AND YEAR(e.measurement_date) = %d 
                             AND MONTH(e.measurement_date) = %d
                         GROUP BY d.id, d.district_name, s.state_name
                         HAVING data_points > 0
                         ORDER BY avg_emission DESC
                         LIMIT 100";
        
        $district_rankings = $wpdb->get_results(
            $wpdb->prepare($district_sql, $year, $month),
            ARRAY_A
        );
        
        // Add rankings
        foreach ($state_rankings as $index => &$state) {
            $state['rank'] = $index + 1;
            $state['avg_emission'] = round(floatval($state['avg_emission']), 2);
        }
        
        foreach ($district_rankings as $index => &$district) {
            $district['rank'] = $index + 1;
            $district['avg_emission'] = round(floatval($district['avg_emission']), 2);
        }
        
        return array(
            'state_rankings' => $state_rankings,
            'district_rankings' => $district_rankings,
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_states' => count($state_rankings),
            'total_districts' => count($district_rankings),
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Generate correlation data for a state
     */
    public function generate_correlation_data($state_name) {
        global $wpdb;
        
        $state_name = strtoupper($state_name);
        
        // Get monthly data for correlation analysis
        $sql = "SELECT 
                    d.district_name,
                    YEAR(e.measurement_date) as year,
                    MONTH(e.measurement_date) as month,
                    AVG(e.emission_value) as avg_emission
                FROM {$wpdb->prefix}methane_emissions e
                JOIN {$wpdb->prefix}methane_districts d ON e.district_id = d.id
                JOIN {$wpdb->prefix}methane_states s ON e.state_id = s.id
                WHERE s.state_name = %s
                GROUP BY d.id, d.district_name, YEAR(e.measurement_date), MONTH(e.measurement_date)
                ORDER BY d.district_name, year, month";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $state_name),
            ARRAY_A
        );
        
        if (empty($results)) {
            return array(
                'error' => sprintf(__('No correlation data found for %s', 'methane-monitor'), $state_name),
                'correlation_matrix' => array(),
                'correlation_pairs' => array()
            );
        }
        
        // Organize data by district
        $district_data = array();
        foreach ($results as $row) {
            $district = $row['district_name'];
            if (!isset($district_data[$district])) {
                $district_data[$district] = array();
            }
            $period_key = $row['year'] . '-' . sprintf('%02d', $row['month']);
            $district_data[$district][$period_key] = floatval($row['avg_emission']);
        }
        
        // Calculate correlations between districts
        $correlations = $this->calculate_district_correlations($district_data);
        
        return array(
            'correlation_matrix' => $correlations['matrix'],
            'correlation_pairs' => $correlations['pairs'],
            'strongest_correlations' => $correlations['strongest'],
            'weakest_correlations' => $correlations['weakest'],
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Generate extreme events data
     */
    public function generate_extreme_events_data($state_name, $district_name, $threshold_percentile = 90) {
        global $wpdb;
        
        $state_name = strtoupper($state_name);
        $district_name = strtoupper($district_name);
        
        // Get all emission values for the district to calculate threshold
        $sql = "SELECT e.emission_value, e.measurement_date, e.latitude, e.longitude
                FROM {$wpdb->prefix}methane_emissions e
                JOIN {$wpdb->prefix}methane_districts d ON e.district_id = d.id
                JOIN {$wpdb->prefix}methane_states s ON e.state_id = s.id
                WHERE s.state_name = %s AND d.district_name = %s
                ORDER BY e.emission_value DESC";
        
        $all_data = $wpdb->get_results(
            $wpdb->prepare($sql, $state_name, $district_name),
            ARRAY_A
        );
        
        if (empty($all_data)) {
            return array(
                'error' => sprintf(__('No extreme events data found for %s, %s', 'methane-monitor'), $district_name, $state_name),
                'extreme_events' => array(),
                'statistics' => array()
            );
        }
        
        // Calculate threshold value
        $values = array_column($all_data, 'emission_value');
        $threshold_index = floor(count($values) * (100 - $threshold_percentile) / 100);
        $threshold = $values[$threshold_index];
        
        // Find extreme events
        $extreme_events = array();
        foreach ($all_data as $point) {
            if (floatval($point['emission_value']) >= $threshold) {
                $extreme_events[] = array(
                    'date' => $point['measurement_date'],
                    'value' => floatval($point['emission_value']),
                    'latitude' => floatval($point['latitude']),
                    'longitude' => floatval($point['longitude']),
                    'severity' => $this->calculate_severity(floatval($point['emission_value']), $values)
                );
            }
        }
        
        // Calculate statistics
        $extreme_values = array_column($extreme_events, 'value');
        $statistics = array(
            'threshold' => $threshold,
            'threshold_percentile' => $threshold_percentile,
            'total_events' => count($extreme_events),
            'event_frequency' => count($extreme_events) / count($all_data) * 100,
            'max_value' => !empty($extreme_values) ? max($extreme_values) : 0,
            'avg_extreme' => !empty($extreme_values) ? array_sum($extreme_values) / count($extreme_values) : 0
        );
        
        return array(
            'extreme_events' => $extreme_events,
            'statistics' => $statistics,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Calculate time series statistics
     */
    private function calculate_timeseries_statistics($values, $time_series) {
        if (empty($values)) {
            return array();
        }
        
        $count = count($values);
        $mean = array_sum($values) / $count;
        $min = min($values);
        $max = max($values);
        
        // Calculate trend using linear regression
        $x_values = range(0, $count - 1);
        $trend_slope = $this->calculate_linear_regression_slope($x_values, $values);
        
        // Calculate variance and standard deviation
        $variance = array_sum(array_map(function($x) use ($mean) { 
            return pow($x - $mean, 2); 
        }, $values)) / $count;
        $std = sqrt($variance);
        
        return array(
            'mean' => round($mean, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'std' => round($std, 2),
            'trend_slope' => round($trend_slope, 4),
            'coefficient_of_variation' => $mean > 0 ? round(($std / $mean) * 100, 2) : 0,
            'data_points' => $count
        );
    }
    
    /**
     * Calculate trends in time series data
     */
    private function calculate_trends($time_series) {
        if (count($time_series) < 2) {
            return array();
        }
        
        $values = array_column($time_series, 'value');
        $periods = range(0, count($values) - 1);
        
        // Overall trend
        $overall_slope = $this->calculate_linear_regression_slope($periods, $values);
        
        // Yearly trends
        $yearly_trends = array();
        $years = array_unique(array_column($time_series, 'year'));
        
        foreach ($years as $year) {
            $year_data = array_filter($time_series, function($item) use ($year) {
                return $item['year'] == $year;
            });
            
            if (count($year_data) >= 2) {
                $year_values = array_column($year_data, 'value');
                $year_periods = range(0, count($year_values) - 1);
                $year_slope = $this->calculate_linear_regression_slope($year_periods, $year_values);
                
                $yearly_trends[] = array(
                    'year' => $year,
                    'slope' => round($year_slope, 4),
                    'direction' => $year_slope > 0.01 ? 'increasing' : ($year_slope < -0.01 ? 'decreasing' : 'stable'),
                    'avg_value' => round(array_sum($year_values) / count($year_values), 2)
                );
            }
        }
        
        return array(
            'overall_slope' => round($overall_slope, 4),
            'overall_direction' => $overall_slope > 0.01 ? 'increasing' : ($overall_slope < -0.01 ? 'decreasing' : 'stable'),
            'yearly_trends' => $yearly_trends
        );
    }
    
    /**
     * Find peak month in time series
     */
    private function find_peak_month($time_series) {
        $monthly_averages = array();
        
        for ($month = 1; $month <= 12; $month++) {
            $month_data = array_filter($time_series, function($item) use ($month) {
                return $item['month'] == $month;
            });
            
            if (!empty($month_data)) {
                $month_values = array_column($month_data, 'value');
                $monthly_averages[$month] = array_sum($month_values) / count($month_values);
            }
        }
        
        if (empty($monthly_averages)) {
            return null;
        }
        
        $peak_month = array_keys($monthly_averages, max($monthly_averages))[0];
        return $peak_month;
    }
    
    /**
     * Analyze seasonal patterns
     */
    private function analyze_seasonal_pattern($time_series) {
        $seasonal_data = array(
            'spring' => array(), // Mar, Apr, May
            'summer' => array(), // Jun, Jul, Aug
            'monsoon' => array(), // Sep, Oct, Nov
            'winter' => array()  // Dec, Jan, Feb
        );
        
        foreach ($time_series as $point) {
            $month = $point['month'];
            $value = $point['value'];
            
            if (in_array($month, array(3, 4, 5))) {
                $seasonal_data['spring'][] = $value;
            } elseif (in_array($month, array(6, 7, 8))) {
                $seasonal_data['summer'][] = $value;
            } elseif (in_array($month, array(9, 10, 11))) {
                $seasonal_data['monsoon'][] = $value;
            } else {
                $seasonal_data['winter'][] = $value;
            }
        }
        
        $seasonal_averages = array();
        foreach ($seasonal_data as $season => $values) {
            if (!empty($values)) {
                $seasonal_averages[$season] = array(
                    'average' => round(array_sum($values) / count($values), 2),
                    'count' => count($values)
                );
            }
        }
        
        return $seasonal_averages;
    }
    
    /**
     * Perform K-means clustering on district data
     */
    private function perform_kmeans_clustering($districts) {
        $values = array_column($districts, 'average_methane');
        $n_clusters = min(5, max(2, floor(count($districts) / 3))); // Dynamic cluster count
        
        // Simple k-means implementation
        $clusters = $this->simple_kmeans($values, $n_clusters);
        
        // Assign clusters to districts
        $clustered_districts = array();
        foreach ($districts as $index => $district) {
            $clustered_districts[] = array(
                'district' => $district['district_name'],
                'average_methane' => round(floatval($district['average_methane']), 2),
                'std_methane' => round(floatval($district['std_methane']), 2),
                'data_points' => intval($district['data_points']),
                'cluster' => $clusters['assignments'][$index]
            );
        }
        
        return array(
            'districts' => $clustered_districts,
            'info' => $clusters['centroids'],
            'n_clusters' => $n_clusters,
            'inertia' => $clusters['inertia'],
            'silhouette_score' => $this->calculate_silhouette_score($values, $clusters['assignments'])
        );
    }
    
    /**
     * Simple K-means clustering implementation
     */
    private function simple_kmeans($values, $k, $max_iterations = 100) {
        $n = count($values);
        
        // Initialize centroids
        $min_val = min($values);
        $max_val = max($values);
        $centroids = array();
        
        for ($i = 0; $i < $k; $i++) {
            $centroids[$i] = $min_val + ($max_val - $min_val) * ($i / ($k - 1));
        }
        
        $assignments = array_fill(0, $n, 0);
        
        for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
            $new_assignments = array();
            
            // Assign points to nearest centroid
            foreach ($values as $i => $value) {
                $best_distance = PHP_FLOAT_MAX;
                $best_cluster = 0;
                
                for ($j = 0; $j < $k; $j++) {
                    $distance = abs($value - $centroids[$j]);
                    if ($distance < $best_distance) {
                        $best_distance = $distance;
                        $best_cluster = $j;
                    }
                }
                
                $new_assignments[$i] = $best_cluster;
            }
            
            // Check for convergence
            if ($new_assignments === $assignments) {
                break;
            }
            
            $assignments = $new_assignments;
            
            // Update centroids
            for ($j = 0; $j < $k; $j++) {
                $cluster_values = array();
                foreach ($assignments as $i => $cluster) {
                    if ($cluster === $j) {
                        $cluster_values[] = $values[$i];
                    }
                }
                
                if (!empty($cluster_values)) {
                    $centroids[$j] = array_sum($cluster_values) / count($cluster_values);
                }
            }
        }
        
        // Calculate inertia (within-cluster sum of squares)
        $inertia = 0;
        foreach ($assignments as $i => $cluster) {
            $inertia += pow($values[$i] - $centroids[$cluster], 2);
        }
        
        return array(
            'assignments' => $assignments,
            'centroids' => $centroids,
            'inertia' => $inertia
        );
    }
    
    /**
     * Calculate Silhouette score for clustering quality
     */
    private function calculate_silhouette_score($values, $assignments) {
        $n = count($values);
        $silhouette_scores = array();
        
        for ($i = 0; $i < $n; $i++) {
            $a = $this->calculate_intra_cluster_distance($i, $values, $assignments);
            $b = $this->calculate_nearest_cluster_distance($i, $values, $assignments);
            
            if (max($a, $b) > 0) {
                $silhouette_scores[] = ($b - $a) / max($a, $b);
            } else {
                $silhouette_scores[] = 0;
            }
        }
        
        return !empty($silhouette_scores) ? array_sum($silhouette_scores) / count($silhouette_scores) : 0;
    }
    
    /**
     * Calculate intra-cluster distance for silhouette score
     */
    private function calculate_intra_cluster_distance($point_index, $values, $assignments) {
        $point_cluster = $assignments[$point_index];
        $distances = array();
        
        foreach ($assignments as $i => $cluster) {
            if ($i !== $point_index && $cluster === $point_cluster) {
                $distances[] = abs($values[$point_index] - $values[$i]);
            }
        }
        
        return !empty($distances) ? array_sum($distances) / count($distances) : 0;
    }
    
    /**
     * Calculate nearest cluster distance for silhouette score
     */
    private function calculate_nearest_cluster_distance($point_index, $values, $assignments) {
        $point_cluster = $assignments[$point_index];
        $cluster_distances = array();
        $clusters = array_unique($assignments);
        
        foreach ($clusters as $cluster) {
            if ($cluster !== $point_cluster) {
                $distances = array();
                foreach ($assignments as $i => $assigned_cluster) {
                    if ($assigned_cluster === $cluster) {
                        $distances[] = abs($values[$point_index] - $values[$i]);
                    }
                }
                
                if (!empty($distances)) {
                    $cluster_distances[] = array_sum($distances) / count($distances);
                }
            }
        }
        
        return !empty($cluster_distances) ? min($cluster_distances) : 0;
    }
    
    /**
     * Calculate district correlations
     */
    private function calculate_district_correlations($district_data) {
        $districts = array_keys($district_data);
        $n_districts = count($districts);
        $correlation_matrix = array();
        $correlation_pairs = array();
        
        // Calculate all pairwise correlations
        for ($i = 0; $i < $n_districts; $i++) {
            $correlation_matrix[$districts[$i]] = array();
            
            for ($j = 0; $j < $n_districts; $j++) {
                if ($i === $j) {
                    $correlation = 1.0;
                } else {
                    $correlation = $this->calculate_pearson_correlation(
                        $district_data[$districts[$i]],
                        $district_data[$districts[$j]]
                    );
                }
                
                $correlation_matrix[$districts[$i]][$districts[$j]] = round($correlation, 3);
                
                // Store unique pairs
                if ($i < $j) {
                    $correlation_pairs[] = array(
                        'district1' => $districts[$i],
                        'district2' => $districts[$j],
                        'correlation' => round($correlation, 3)
                    );
                }
            }
        }
        
        // Sort pairs by correlation strength
        usort($correlation_pairs, function($a, $b) {
            return abs($b['correlation']) <=> abs($a['correlation']);
        });
        
        $strongest = array_slice($correlation_pairs, 0, 5);
        $weakest = array_slice(array_reverse($correlation_pairs), 0, 5);
        
        return array(
            'matrix' => $correlation_matrix,
            'pairs' => $correlation_pairs,
            'strongest' => $strongest,
            'weakest' => $weakest
        );
    }
    
    /**
     * Calculate Pearson correlation coefficient
     */
    private function calculate_pearson_correlation($x_data, $y_data) {
        // Find common periods
        $common_periods = array_intersect(array_keys($x_data), array_keys($y_data));
        
        if (count($common_periods) < 2) {
            return 0;
        }
        
        $x_values = array();
        $y_values = array();
        
        foreach ($common_periods as $period) {
            $x_values[] = $x_data[$period];
            $y_values[] = $y_data[$period];
        }
        
        $n = count($x_values);
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x2 = 0;
        $sum_y2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_x2 += $x_values[$i] * $x_values[$i];
            $sum_y2 += $y_values[$i] * $y_values[$i];
        }
        
        $numerator = $n * $sum_xy - $sum_x * $sum_y;
        $denominator = sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));
        
        return $denominator != 0 ? $numerator / $denominator : 0;
    }
    
    /**
     * Calculate linear regression slope
     */
    private function calculate_linear_regression_slope($x_values, $y_values) {
        $n = count($x_values);
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_x2 += $x_values[$i] * $x_values[$i];
        }
        
        $denominator = $n * $sum_x2 - $sum_x * $sum_x;
        
        return $denominator != 0 ? ($n * $sum_xy - $sum_x * $sum_y) / $denominator : 0;
    }
    
    /**
     * Calculate severity of extreme event
     */
    private function calculate_severity($value, $all_values) {
        $max_value = max($all_values);
        $percentile_95 = $all_values[floor(count($all_values) * 0.05)]; // 95th percentile
        
        if ($value >= $max_value * 0.95) {
            return 'extreme';
        } elseif ($value >= $percentile_95) {
            return 'high';
        } else {
            return 'moderate';
        }
    }
}
