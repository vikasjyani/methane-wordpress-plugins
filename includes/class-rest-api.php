<?php
/**
 * Methane Monitor REST API Handler
 * 
 * Provides REST API endpoints to replace Flask application routes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_REST_API {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * API namespace
     */
    private $namespace = 'methane-monitor/v1';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Methane_Monitor_Database();
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Metadata endpoint
        register_rest_route($this->namespace, '/metadata', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_metadata'),
            'permission_callback' => array($this, 'check_read_permission')
        ));
        
        // India data endpoint
        register_rest_route($this->namespace, '/india/(?P<year>\d{4})/(?P<month>\d{1,2})', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_india_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 2014,
                    'maximum' => 2030
                ),
                'month' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12
                ),
                'viz' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('choropleth', 'heatmap'),
                    'default' => 'choropleth'
                )
            )
        ));
        
        // State data endpoint
        register_rest_route($this->namespace, '/state/(?P<state_name>[a-zA-Z\s]+)/(?P<year>\d{4})/(?P<month>\d{1,2})', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_state_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'state_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 2014,
                    'maximum' => 2030
                ),
                'month' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12
                ),
                'viz' => array(
                    'required' => false,
                    'type' => 'string',
                    'enum' => array('choropleth', 'heatmap'),
                    'default' => 'choropleth'
                )
            )
        ));
        
        // District data endpoint
        register_rest_route($this->namespace, '/district/(?P<state_name>[a-zA-Z\s]+)/(?P<district_name>[a-zA-Z\s]+)/(?P<year>\d{4})/(?P<month>\d{1,2})', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_district_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'state_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'district_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 2014,
                    'maximum' => 2030
                ),
                'month' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12
                )
            )
        ));
        
        // States list endpoint
        register_rest_route($this->namespace, '/states', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_states_list'),
            'permission_callback' => array($this, 'check_read_permission')
        ));
        
        // Districts list endpoint
        register_rest_route($this->namespace, '/districts/(?P<state_name>[a-zA-Z\s]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_districts_list'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'state_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Analytics endpoints
        $this->register_analytics_routes();
        
        // Admin endpoints
        $this->register_admin_routes();
    }
    
    /**
     * Register analytics routes
     */
    private function register_analytics_routes() {
        // Time series data
        register_rest_route($this->namespace, '/analytics/timeseries/(?P<state_name>[a-zA-Z\s]+)/(?P<district_name>[a-zA-Z\s]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_timeseries_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'state_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'district_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Clustering data
        register_rest_route($this->namespace, '/analytics/clustering/(?P<state_name>[a-zA-Z\s]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_clustering_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'state_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Ranking data
        register_rest_route($this->namespace, '/analytics/ranking/(?P<year>\d{4})/(?P<month>\d{1,2})', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_ranking_data'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'year' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 2014,
                    'maximum' => 2030
                ),
                'month' => array(
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 12
                )
            )
        ));
    }
    
    /**
     * Register admin routes
     */
    private function register_admin_routes() {
        // Clear cache endpoint
        register_rest_route($this->namespace, '/admin/clear-cache', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'clear_cache'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Upload data endpoint
        register_rest_route($this->namespace, '/admin/upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_data_upload'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
    }
    
    /**
     * Check read permissions
     */
    public function check_read_permission() {
        // Allow public read access
        return true;
    }
    
    /**
     * Check admin permissions
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get metadata endpoint
     */
    public function get_metadata($request) {
        $cache_key = 'methane_metadata';
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $periods = $this->database->get_available_periods();
            $states = $this->database->get_states_list();
            
            $years = array_unique(array_column($periods, 'year'));
            rsort($years);
            
            $district_map = array();
            foreach ($states as $state) {
                $districts = $this->database->get_districts_list($state['state_name']);
                $district_map[$state['state_name']] = array_column($districts, 'district_name');
            }
            
            $metadata = array(
                'years' => array_map('intval', $years),
                'min_year' => !empty($years) ? min($years) : null,
                'max_year' => !empty($years) ? max($years) : null,
                'total_states' => count($states),
                'all_states_list' => array_column($states, 'state_name'),
                'district_map' => $district_map,
                'api_version' => '1.0',
                'generated_at' => current_time('mysql')
            );
            
            $this->set_cached_data($cache_key, $metadata, 3600);
            
            return new WP_REST_Response($metadata, 200);
            
        } catch (Exception $e) {
            return new WP_Error('metadata_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get India data endpoint
     */
    public function get_india_data($request) {
        $year = $request->get_param('year');
        $month = $request->get_param('month');
        $viz_type = $request->get_param('viz');
        
        $cache_key = "india_data_{$year}_{$month}_{$viz_type}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $data = $this->database->get_india_data($year, $month);
            
            if (empty($data)) {
                return new WP_Error('no_data', 'No data found for the specified period', array('status' => 404));
            }
            
            // Calculate statistics
            $valid_emissions = array_filter(array_column($data, 'avg_emission'), function($val) {
                return $val !== null && $val > 0;
            });
            
            $stats = $this->calculate_statistics($valid_emissions);
            
            if ($viz_type === 'heatmap') {
                // Generate interpolated data for heatmap visualization
                $response = array(
                    'type' => 'interpolated_contour',
                    'interpolated_grid_bundle' => $this->generate_india_interpolation($data),
                    'stats' => $stats,
                    'top_regions_label' => 'Top Emitting States',
                    'top_regions_data' => $this->get_top_regions($data, 'state_name', 'avg_emission')
                );
            } else {
                // Generate GeoJSON for choropleth visualization
                $geojson = $this->generate_india_geojson($data);
                
                $response = array(
                    'type' => 'choropleth',
                    'geojson' => $geojson,
                    'stats' => $stats,
                    'top_states' => $this->get_top_regions($data, 'state_name', 'avg_emission')
                );
            }
            
            $this->set_cached_data($cache_key, $response, 1800); // 30 minutes cache
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            return new WP_Error('india_data_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get state data endpoint
     */
    public function get_state_data($request) {
        $state_name = strtoupper($request->get_param('state_name'));
        $year = $request->get_param('year');
        $month = $request->get_param('month');
        $viz_type = $request->get_param('viz');
        
        $cache_key = "state_data_{$state_name}_{$year}_{$month}_{$viz_type}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $data = $this->database->get_state_data($state_name, $year, $month);
            
            if (empty($data)) {
                return new WP_Error('no_data', "No data found for {$state_name} in {$year}-{$month}", array('status' => 404));
            }
            
            // Calculate statistics
            $valid_emissions = array_filter(array_column($data, 'avg_emission'), function($val) {
                return $val !== null && $val > 0;
            });
            
            $stats = $this->calculate_statistics($valid_emissions);
            
            if ($viz_type === 'heatmap') {
                // Generate interpolated data for heatmap
                $response = array(
                    'type' => 'interpolated_contour',
                    'interpolated_grid_bundle' => $this->generate_state_interpolation($data, $state_name),
                    'stats' => $stats,
                    'top_regions_label' => "Top Districts in {$state_name}",
                    'top_regions_data' => $this->get_top_regions($data, 'district_name', 'avg_emission')
                );
            } else {
                // Generate GeoJSON for choropleth
                $geojson = $this->generate_state_geojson($data, $state_name);
                
                $response = array(
                    'type' => 'choropleth',
                    'geojson' => $geojson,
                    'stats' => $stats,
                    'top_districts' => $this->get_top_regions($data, 'district_name', 'avg_emission')
                );
            }
            
            $this->set_cached_data($cache_key, $response, 1800);
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            return new WP_Error('state_data_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get district data endpoint
     */
    public function get_district_data($request) {
        $state_name = strtoupper($request->get_param('state_name'));
        $district_name = strtoupper($request->get_param('district_name'));
        $year = $request->get_param('year');
        $month = $request->get_param('month');
        
        $cache_key = "district_data_{$state_name}_{$district_name}_{$year}_{$month}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $data = $this->database->get_district_data($state_name, $district_name, $year, $month);
            
            if (empty($data)) {
                return new WP_Error('no_data', "No data found for {$district_name}, {$state_name} in {$year}-{$month}", array('status' => 404));
            }
            
            // Calculate bounds and statistics
            $latitudes = array_column($data, 'latitude');
            $longitudes = array_column($data, 'longitude');
            $emissions = array_column($data, 'emission_value');
            
            $bounds = array(
                'min_lat' => min($latitudes),
                'max_lat' => max($latitudes),
                'min_lon' => min($longitudes),
                'max_lon' => max($longitudes)
            );
            
            $stats = $this->calculate_statistics($emissions);
            
            // Convert to points format for visualization
            $points = array();
            foreach ($data as $point) {
                $points[] = array(
                    floatval($point['latitude']),
                    floatval($point['longitude']),
                    floatval($point['emission_value'])
                );
            }
            
            // Try to generate interpolated surface
            $interpolated_data = $this->generate_district_interpolation($points, $bounds);
            
            if ($interpolated_data) {
                $response = array(
                    'type' => 'interpolated_contour',
                    'original_points' => $points,
                    'bounds' => $bounds,
                    'interpolated_grid_bundle' => $interpolated_data,
                    'stats' => $stats
                );
            } else {
                $response = array(
                    'type' => 'contour_points_only',
                    'points' => $points,
                    'bounds' => $bounds,
                    'stats' => $stats
                );
            }
            
            $this->set_cached_data($cache_key, $response, 1800);
            
            return new WP_REST_Response($response, 200);
            
        } catch (Exception $e) {
            return new WP_Error('district_data_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get states list endpoint
     */
    public function get_states_list($request) {
        $cache_key = 'states_list';
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $states = $this->database->get_states_list();
            $state_names = array_column($states, 'state_name');
            
            $this->set_cached_data($cache_key, $state_names, 3600);
            
            return new WP_REST_Response($state_names, 200);
            
        } catch (Exception $e) {
            return new WP_Error('states_list_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get districts list endpoint
     */
    public function get_districts_list($request) {
        $state_name = strtoupper($request->get_param('state_name'));
        
        $cache_key = "districts_list_{$state_name}";
        $cached = $this->get_cached_data($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $districts = $this->database->get_districts_list($state_name);
            $district_names = array_column($districts, 'district_name');
            
            $this->set_cached_data($cache_key, $district_names, 3600);
            
            return new WP_REST_Response($district_names, 200);
            
        } catch (Exception $e) {
            return new WP_Error('districts_list_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get time series data for analytics
     */
    public function get_timeseries_data($request) {
        $state_name = strtoupper($request->get_param('state_name'));
        $district_name = strtoupper($request->get_param('district_name'));
        
        $cache_key = "timeseries_{$state_name}_{$district_name}";
        $cached = $this->database->get_analytics_cache($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $analytics = new Methane_Monitor_Analytics();
            $data = $analytics->generate_timeseries_data($state_name, $district_name);
            
            $this->database->set_analytics_cache($cache_key, $data, 'timeseries', 'district', null, null, null, 3600);
            
            return new WP_REST_Response($data, 200);
            
        } catch (Exception $e) {
            return new WP_Error('timeseries_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get clustering data for analytics
     */
    public function get_clustering_data($request) {
        $state_name = strtoupper($request->get_param('state_name'));
        
        $cache_key = "clustering_{$state_name}";
        $cached = $this->database->get_analytics_cache($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $analytics = new Methane_Monitor_Analytics();
            $data = $analytics->generate_clustering_data($state_name);
            
            $this->database->set_analytics_cache($cache_key, $data, 'clustering', 'state', null, null, null, 3600);
            
            return new WP_REST_Response($data, 200);
            
        } catch (Exception $e) {
            return new WP_Error('clustering_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get ranking data for analytics
     */
    public function get_ranking_data($request) {
        $year = $request->get_param('year');
        $month = $request->get_param('month');
        
        $cache_key = "ranking_{$year}_{$month}";
        $cached = $this->database->get_analytics_cache($cache_key);
        
        if ($cached !== null) {
            return new WP_REST_Response($cached, 200);
        }
        
        try {
            $analytics = new Methane_Monitor_Analytics();
            $data = $analytics->generate_ranking_data($year, $month);
            
            $this->database->set_analytics_cache($cache_key, $data, 'ranking', 'india', null, $year, $month, 3600);
            
            return new WP_REST_Response($data, 200);
            
        } catch (Exception $e) {
            return new WP_Error('ranking_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Clear cache endpoint
     */
    public function clear_cache($request) {
        try {
            // Clear WordPress transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_methane_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_methane_%'");
            
            // Clear analytics cache
            $this->database->clean_expired_cache();
            
            // Clear object cache if available
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Cache cleared successfully'
            ), 200);
            
        } catch (Exception $e) {
            return new WP_Error('cache_clear_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Handle data upload endpoint
     */
    public function handle_data_upload($request) {
        if (!isset($_FILES['files'])) {
            return new WP_Error('no_files', 'No files uploaded', array('status' => 400));
        }
        
        $state_name = $request->get_param('state_name');
        $district_name = $request->get_param('district_name');
        
        if (!$state_name || !$district_name) {
            return new WP_Error('missing_params', 'State and district names are required', array('status' => 400));
        }
        
        try {
            $processor = new Methane_Monitor_Data_Processor();
            $results = $processor->handle_file_upload($_FILES['files'], $state_name, $district_name);
            
            return new WP_REST_Response($results, 200);
            
        } catch (Exception $e) {
            return new WP_Error('upload_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Calculate statistics for emission data
     */
    private function calculate_statistics($values) {
        if (empty($values)) {
            return array(
                'mean' => 0, 'min' => 0, 'max' => 0, 'median' => 0, 'std' => 0, 'count' => 0,
                'data_min' => 1700, 'data_max' => 2200
            );
        }
        
        $count = count($values);
        $mean = array_sum($values) / $count;
        $min = min($values);
        $max = max($values);
        
        sort($values);
        $median = $count % 2 === 0 
            ? ($values[$count/2 - 1] + $values[$count/2]) / 2 
            : $values[floor($count/2)];
        
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $values)) / $count;
        $std = sqrt($variance);
        
        return array(
            'mean' => round($mean, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'median' => round($median, 2),
            'std' => round($std, 2),
            'count' => $count,
            'data_min' => round($min, 2),
            'data_max' => round($max, 2)
        );
    }
    
    /**
     * Get top regions by emission values
     */
    private function get_top_regions($data, $name_field, $value_field, $limit = 5) {
        $valid_data = array_filter($data, function($item) use ($value_field) {
            return isset($item[$value_field]) && $item[$value_field] > 0;
        });
        
        usort($valid_data, function($a, $b) use ($value_field) {
            return $b[$value_field] <=> $a[$value_field];
        });
        
        return array_slice(array_map(function($item) use ($name_field, $value_field) {
            return array(
                'name' => $item[$name_field],
                'methane_ppb' => round($item[$value_field], 2)
            );
        }, $valid_data), 0, $limit);
    }
    
    /**
     * Get cached data
     */
    private function get_cached_data($key) {
        return get_transient("methane_{$key}");
    }
    
    /**
     * Set cached data
     */
    private function set_cached_data($key, $data, $expiration) {
        return set_transient("methane_{$key}", $data, $expiration);
    }
    
    /**
     * Generate India GeoJSON (placeholder - would need actual boundary data)
     */
    private function generate_india_geojson($data) {
        // In a real implementation, this would load actual state boundary GeoJSON
        // and merge with emission data
        return array(
            'type' => 'FeatureCollection',
            'features' => array() // Placeholder
        );
    }
    
    /**
     * Generate state GeoJSON (placeholder)
     */
    private function generate_state_geojson($data, $state_name) {
        // In a real implementation, this would load district boundaries
        return array(
            'type' => 'FeatureCollection',
            'features' => array() // Placeholder
        );
    }
    
    /**
     * Generate interpolation data for India (placeholder)
     */
    private function generate_india_interpolation($data) {
        // Placeholder for interpolation algorithm
        return array(
            'grid' => array(),
            'lat_range' => array(),
            'lon_range' => array(),
            'method' => 'idw'
        );
    }
    
    /**
     * Generate interpolation data for state (placeholder)
     */
    private function generate_state_interpolation($data, $state_name) {
        // Placeholder for state-level interpolation
        return array(
            'grid' => array(),
            'lat_range' => array(),
            'lon_range' => array(),
            'method' => 'idw'
        );
    }
    
    /**
     * Generate interpolation data for district
     */
    private function generate_district_interpolation($points, $bounds) {
        if (count($points) < 3) {
            return null;
        }
        
        // Simple IDW interpolation implementation
        $grid_size = 30;
        $lat_range = array();
        $lon_range = array();
        $grid = array();
        
        // Create coordinate ranges
        for ($i = 0; $i < $grid_size; $i++) {
            $lat_range[] = $bounds['min_lat'] + ($bounds['max_lat'] - $bounds['min_lat']) * ($i / ($grid_size - 1));
            $lon_range[] = $bounds['min_lon'] + ($bounds['max_lon'] - $bounds['min_lon']) * ($i / ($grid_size - 1));
        }
        
        // Generate grid using IDW
        for ($i = 0; $i < $grid_size; $i++) {
            $grid[$i] = array();
            for ($j = 0; $j < $grid_size; $j++) {
                $grid_lat = $lat_range[$i];
                $grid_lon = $lon_range[$j];
                
                $sum = 0;
                $weight_sum = 0;
                
                foreach ($points as $point) {
                    $distance = sqrt(pow($grid_lat - $point[0], 2) + pow($grid_lon - $point[1], 2));
                    if ($distance < 0.0001) {
                        $grid[$i][$j] = $point[2];
                        continue 2;
                    }
                    
                    $weight = 1 / pow($distance, 2);
                    $sum += $point[2] * $weight;
                    $weight_sum += $weight;
                }
                
                $grid[$i][$j] = $weight_sum > 0 ? $sum / $weight_sum : 0;
            }
        }
        
        $grid_values = array();
        foreach ($grid as $row) {
            foreach ($row as $val) {
                if ($val > 0) $grid_values[] = $val;
            }
        }
        
        return array(
            'grid' => $grid,
            'lat_range' => $lat_range,
            'lon_range' => $lon_range,
            'bounds' => $bounds,
            'method' => 'idw',
            'value_range' => array(
                'min' => !empty($grid_values) ? min($grid_values) : 0,
                'max' => !empty($grid_values) ? max($grid_values) : 0,
                'mean' => !empty($grid_values) ? array_sum($grid_values) / count($grid_values) : 0
            )
        );
    }
}
