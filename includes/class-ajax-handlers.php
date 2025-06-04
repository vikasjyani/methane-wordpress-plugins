<?php
/**
 * Methane Monitor AJAX Handlers
 * 
 * Handles AJAX requests for real-time data updates and interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Ajax_Handlers {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Data processor instance
     */
    private $data_processor;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Methane_Monitor_Database();
        $this->data_processor = new Methane_Monitor_Data_Processor();
        $this->register_ajax_hooks();
    }
    
    /**
     * Register AJAX hooks
     */
    private function register_ajax_hooks() {
        // Public AJAX actions (available to both logged-in and non-logged-in users)
        add_action('wp_ajax_methane_get_data', array($this, 'handle_get_data'));
        add_action('wp_ajax_nopriv_methane_get_data', array($this, 'handle_get_data'));
        
        add_action('wp_ajax_methane_get_states', array($this, 'handle_get_states'));
        add_action('wp_ajax_nopriv_methane_get_states', array($this, 'handle_get_states'));
        
        add_action('wp_ajax_methane_get_districts', array($this, 'handle_get_districts'));
        add_action('wp_ajax_nopriv_methane_get_districts', array($this, 'handle_get_districts'));
        
        add_action('wp_ajax_methane_get_analytics', array($this, 'handle_get_analytics'));
        add_action('wp_ajax_nopriv_methane_get_analytics', array($this, 'handle_get_analytics'));
        
        add_action('wp_ajax_methane_export_data', array($this, 'handle_export_data'));
        add_action('wp_ajax_nopriv_methane_export_data', array($this, 'handle_export_data'));
        
        // Admin-only AJAX actions
        add_action('wp_ajax_methane_upload_data', array($this, 'handle_upload_data'));
        add_action('wp_ajax_methane_clear_cache', array($this, 'handle_clear_cache'));
        add_action('wp_ajax_methane_process_data', array($this, 'handle_process_data'));
        add_action('wp_ajax_methane_get_processing_status', array($this, 'handle_get_processing_status'));
    }
    
    /**
     * Handle get data AJAX request
     */
    public function handle_get_data() {
        // Verify nonce for security
        if (!wp_verify_nonce($_REQUEST['nonce'], 'wp_rest')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $level = sanitize_text_field($_REQUEST['level']);
            $year = intval($_REQUEST['year']);
            $month = intval($_REQUEST['month']);
            $state = isset($_REQUEST['state']) ? sanitize_text_field($_REQUEST['state']) : '';
            $district = isset($_REQUEST['district']) ? sanitize_text_field($_REQUEST['district']) : '';
            $viz_type = isset($_REQUEST['viz_type']) ? sanitize_text_field($_REQUEST['viz_type']) : 'choropleth';
            
            // Validate parameters
            if (!in_array($level, array('india', 'state', 'district'))) {
                throw new Exception(__('Invalid level parameter', 'methane-monitor'));
            }
            
            if ($year < 2014 || $year > 2030) {
                throw new Exception(__('Invalid year parameter', 'methane-monitor'));
            }
            
            if ($month < 1 || $month > 12) {
                throw new Exception(__('Invalid month parameter', 'methane-monitor'));
            }
            
            // Get data based on level
            switch ($level) {
                case 'india':
                    $data = $this->get_india_data($year, $month, $viz_type);
                    break;
                    
                case 'state':
                    if (empty($state)) {
                        throw new Exception(__('State parameter is required', 'methane-monitor'));
                    }
                    $data = $this->get_state_data($state, $year, $month, $viz_type);
                    break;
                    
                case 'district':
                    if (empty($state) || empty($district)) {
                        throw new Exception(__('State and district parameters are required', 'methane-monitor'));
                    }
                    $data = $this->get_district_data($state, $district, $year, $month);
                    break;
                    
                default:
                    throw new Exception(__('Unsupported level', 'methane-monitor'));
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'data_error'
            ));
        }
    }
    
    /**
     * Handle get states AJAX request
     */
    public function handle_get_states() {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'wp_rest')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $states = $this->database->get_states_list();
            $state_names = array_column($states, 'state_name');
            
            wp_send_json_success($state_names);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'states_error'
            ));
        }
    }
    
    /**
     * Handle get districts AJAX request
     */
    public function handle_get_districts() {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'wp_rest')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $state_name = sanitize_text_field($_REQUEST['state_name']);
            
            if (empty($state_name)) {
                throw new Exception(__('State name is required', 'methane-monitor'));
            }
            
            $districts = $this->database->get_districts_list($state_name);
            $district_names = array_column($districts, 'district_name');
            
            wp_send_json_success($district_names);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'districts_error'
            ));
        }
    }
    
    /**
     * Handle get analytics AJAX request
     */
    public function handle_get_analytics() {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'wp_rest')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $type = sanitize_text_field($_REQUEST['type']);
            $state = isset($_REQUEST['state']) ? sanitize_text_field($_REQUEST['state']) : '';
            $district = isset($_REQUEST['district']) ? sanitize_text_field($_REQUEST['district']) : '';
            $year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : null;
            $month = isset($_REQUEST['month']) ? intval($_REQUEST['month']) : null;
            
            $analytics = new Methane_Monitor_Analytics();
            
            switch ($type) {
                case 'timeseries':
                    if (empty($state) || empty($district)) {
                        throw new Exception(__('State and district are required for time series', 'methane-monitor'));
                    }
                    $data = $analytics->generate_timeseries_data($state, $district);
                    break;
                    
                case 'clustering':
                    if (empty($state)) {
                        throw new Exception(__('State is required for clustering', 'methane-monitor'));
                    }
                    $data = $analytics->generate_clustering_data($state);
                    break;
                    
                case 'ranking':
                    if (!$year || !$month) {
                        throw new Exception(__('Year and month are required for ranking', 'methane-monitor'));
                    }
                    $data = $analytics->generate_ranking_data($year, $month);
                    break;
                    
                case 'correlation':
                    if (empty($state)) {
                        throw new Exception(__('State is required for correlation', 'methane-monitor'));
                    }
                    $data = $analytics->generate_correlation_data($state);
                    break;
                    
                default:
                    throw new Exception(__('Unsupported analytics type', 'methane-monitor'));
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'analytics_error'
            ));
        }
    }
    
    /**
     * Handle export data AJAX request
     */
    public function handle_export_data() {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'wp_rest')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $format = sanitize_text_field($_REQUEST['format']);
            $level = sanitize_text_field($_REQUEST['level']);
            $state = isset($_REQUEST['state']) ? sanitize_text_field($_REQUEST['state']) : null;
            $district = isset($_REQUEST['district']) ? sanitize_text_field($_REQUEST['district']) : null;
            $year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : null;
            $month = isset($_REQUEST['month']) ? intval($_REQUEST['month']) : null;
            
            if (!in_array($format, array('csv', 'json', 'xlsx'))) {
                throw new Exception(__('Unsupported export format', 'methane-monitor'));
            }
            
            // Generate export data
            $export_data = $this->generate_export_data($level, $state, $district, $year, $month);
            
            // Create export file
            $filename = $this->create_export_file($export_data, $format, $level, $state, $district, $year, $month);
            
            wp_send_json_success(array(
                'download_url' => $filename,
                'filename' => basename($filename)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'export_error'
            ));
        }
    }
    
    /**
     * Handle upload data AJAX request (admin only)
     */
    public function handle_upload_data() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'methane-monitor'));
        }
        
        if (!wp_verify_nonce($_REQUEST['nonce'], 'methane_monitor_admin_nonce')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            if (empty($_FILES['files'])) {
                throw new Exception(__('No files uploaded', 'methane-monitor'));
            }
            
            $state_name = sanitize_text_field($_REQUEST['state_name']);
            $district_name = sanitize_text_field($_REQUEST['district_name']);
            
            if (empty($state_name) || empty($district_name)) {
                throw new Exception(__('State and district names are required', 'methane-monitor'));
            }
            
            // Handle file upload
            $results = $this->data_processor->handle_file_upload($_FILES['files'], $state_name, $district_name);
            
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'upload_error'
            ));
        }
    }
    
    /**
     * Handle clear cache AJAX request (admin only)
     */
    public function handle_clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'methane-monitor'));
        }
        
        if (!wp_verify_nonce($_REQUEST['nonce'], 'methane_monitor_admin_nonce')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
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
            
            wp_send_json_success(array(
                'message' => __('Cache cleared successfully', 'methane-monitor')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'cache_clear_error'
            ));
        }
    }
    
    /**
     * Handle process data AJAX request (admin only)
     */
    public function handle_process_data() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'methane-monitor'));
        }
        
        if (!wp_verify_nonce($_REQUEST['nonce'], 'methane_monitor_admin_nonce')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            $action = sanitize_text_field($_REQUEST['action_type']);
            
            switch ($action) {
                case 'calculate_aggregations':
                    $year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : null;
                    $month = isset($_REQUEST['month']) ? intval($_REQUEST['month']) : null;
                    
                    $this->database->calculate_monthly_aggregations($year, $month);
                    
                    wp_send_json_success(array(
                        'message' => __('Aggregations calculated successfully', 'methane-monitor')
                    ));
                    break;
                    
                case 'process_directory':
                    $directory_path = sanitize_text_field($_REQUEST['directory_path']);
                    
                    if (!is_dir($directory_path)) {
                        throw new Exception(__('Invalid directory path', 'methane-monitor'));
                    }
                    
                    // This could be a long-running process, so we might want to use WP-Cron
                    $results = $this->data_processor->process_directory($directory_path);
                    
                    wp_send_json_success($results);
                    break;
                    
                default:
                    throw new Exception(__('Unknown action type', 'methane-monitor'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'process_error'
            ));
        }
    }
    
    /**
     * Handle get processing status AJAX request (admin only)
     */
    public function handle_get_processing_status() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'methane-monitor'));
        }
        
        if (!wp_verify_nonce($_REQUEST['nonce'], 'methane_monitor_admin_nonce')) {
            wp_die(__('Security check failed', 'methane-monitor'));
        }
        
        try {
            // Get processing statistics from options table
            $stats = get_option('methane_monitor_processing_stats', array());
            
            // Get database statistics
            global $wpdb;
            $db_stats = array(
                'total_emissions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_emissions"),
                'total_states' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_states"),
                'total_districts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_districts"),
                'latest_date' => $wpdb->get_var("SELECT MAX(measurement_date) FROM {$wpdb->prefix}methane_emissions")
            );
            
            wp_send_json_success(array(
                'processing_stats' => $stats,
                'database_stats' => $db_stats
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => 'status_error'
            ));
        }
    }
    
    /**
     * Get India data for AJAX
     */
    private function get_india_data($year, $month, $viz_type) {
        $data = $this->database->get_india_data($year, $month);
        
        if (empty($data)) {
            throw new Exception(__('No data found for the specified period', 'methane-monitor'));
        }
        
        $valid_emissions = array_filter(array_column($data, 'avg_emission'), function($val) {
            return $val !== null && $val > 0;
        });
        
        $stats = $this->calculate_statistics($valid_emissions);
        
        return array(
            'type' => $viz_type,
            'data' => $data,
            'stats' => $stats,
            'level' => 'india',
            'year' => $year,
            'month' => $month,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Get state data for AJAX
     */
    private function get_state_data($state_name, $year, $month, $viz_type) {
        $data = $this->database->get_state_data($state_name, $year, $month);
        
        if (empty($data)) {
            throw new Exception(__('No data found for the specified state and period', 'methane-monitor'));
        }
        
        $valid_emissions = array_filter(array_column($data, 'avg_emission'), function($val) {
            return $val !== null && $val > 0;
        });
        
        $stats = $this->calculate_statistics($valid_emissions);
        
        return array(
            'type' => $viz_type,
            'data' => $data,
            'stats' => $stats,
            'level' => 'state',
            'state' => $state_name,
            'year' => $year,
            'month' => $month,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Get district data for AJAX
     */
    private function get_district_data($state_name, $district_name, $year, $month) {
        $data = $this->database->get_district_data($state_name, $district_name, $year, $month);
        
        if (empty($data)) {
            throw new Exception(__('No data found for the specified district and period', 'methane-monitor'));
        }
        
        $emissions = array_column($data, 'emission_value');
        $stats = $this->calculate_statistics($emissions);
        
        // Calculate bounds
        $latitudes = array_column($data, 'latitude');
        $longitudes = array_column($data, 'longitude');
        
        $bounds = array(
            'min_lat' => min($latitudes),
            'max_lat' => max($latitudes),
            'min_lon' => min($longitudes),
            'max_lon' => max($longitudes)
        );
        
        // Convert to points format
        $points = array();
        foreach ($data as $point) {
            $points[] = array(
                floatval($point['latitude']),
                floatval($point['longitude']),
                floatval($point['emission_value'])
            );
        }
        
        return array(
            'type' => 'points',
            'points' => $points,
            'bounds' => $bounds,
            'stats' => $stats,
            'level' => 'district',
            'state' => $state_name,
            'district' => $district_name,
            'year' => $year,
            'month' => $month,
            'generated_at' => current_time('mysql')
        );
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
        
        $variance = array_sum(array_map(function($x) use ($mean) { 
            return pow($x - $mean, 2); 
        }, $values)) / $count;
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
     * Generate export data
     */
    private function generate_export_data($level, $state, $district, $year, $month) {
        switch ($level) {
            case 'india':
                return $this->database->get_india_data($year, $month);
                
            case 'state':
                return $this->database->get_state_data($state, $year, $month);
                
            case 'district':
                return $this->database->get_district_data($state, $district, $year, $month);
                
            default:
                throw new Exception(__('Invalid export level', 'methane-monitor'));
        }
    }
    
    /**
     * Create export file
     */
    private function create_export_file($data, $format, $level, $state, $district, $year, $month) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/methane-monitor/exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename_parts = array('methane', $level);
        if ($state) $filename_parts[] = $state;
        if ($district) $filename_parts[] = $district;
        if ($year && $month) $filename_parts[] = "{$year}-{$month}";
        
        $base_filename = implode('_', $filename_parts);
        $filename = $export_dir . $base_filename . '.' . $format;
        
        switch ($format) {
            case 'csv':
                $this->write_csv_file($filename, $data);
                break;
                
            case 'json':
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
                break;
                
            case 'xlsx':
                $this->write_excel_file($filename, $data);
                break;
        }
        
        // Return download URL
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filename);
    }
    
    /**
     * Write CSV file
     */
    private function write_csv_file($filename, $data) {
        $file = fopen($filename, 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($file, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }
        
        fclose($file);
    }
    
    /**
     * Write Excel file (simplified version)
     */
    private function write_excel_file($filename, $data) {
        // For now, we'll create a simple XML-based Excel file
        // In a production environment, you might want to use PhpSpreadsheet
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="Methane Data">' . "\n";
        $xml .= '<Table>' . "\n";
        
        if (!empty($data)) {
            // Headers
            $xml .= '<Row>' . "\n";
            foreach (array_keys($data[0]) as $header) {
                $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";
            
            // Data
            foreach ($data as $row) {
                $xml .= '<Row>' . "\n";
                foreach ($row as $cell) {
                    $type = is_numeric($cell) ? 'Number' : 'String';
                    $xml .= '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($cell) . '</Data></Cell>' . "\n";
                }
                $xml .= '</Row>' . "\n";
            }
        }
        
        $xml .= '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        $xml .= '</Workbook>' . "\n";
        
        file_put_contents($filename, $xml);
    }
}
