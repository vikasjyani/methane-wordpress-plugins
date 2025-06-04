<?php
/**
 * Methane Monitor Data Processor Class
 * 
 * Handles processing of Excel files containing methane emission data
 * Implements proper NA handling and state-level averaging methodology
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include PhpSpreadsheet for Excel processing
require_once METHANE_MONITOR_PLUGIN_DIR . 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Methane_Monitor_Data_Processor {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Processing statistics
     */
    private $stats;
    
    /**
     * Supported file types
     */
    private $supported_types = array('xlsx', 'xls', 'csv');
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Methane_Monitor_Database();
        $this->init_stats();
    }
    
    /**
     * Initialize processing statistics
     */
    private function init_stats() {
        $this->stats = array(
            'files_processed' => 0,
            'records_inserted' => 0,
            'records_skipped' => 0,
            'errors' => array(),
            'processing_time' => 0,
            'start_time' => microtime(true)
        );
    }
    
    /**
     * Process Excel file with methane emission data
     * Handles the specific format: latitude, longitude, YYYY_MM_DD columns
     */
    public function process_excel_file($file_path, $state_name, $district_name) {
        try {
            // Validate file
            if (!$this->validate_file($file_path)) {
                throw new Exception("Invalid file: $file_path");
            }
            
            // Load spreadsheet
            $spreadsheet = IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get header row to identify date columns
            $headers = array();
            $headerRow = $worksheet->getRowIterator(1, 1)->current();
            foreach ($headerRow->getCellIterator() as $cell) {
                $headers[] = $cell->getValue();
            }
            
            // Validate required columns
            if (!$this->validate_headers($headers)) {
                throw new Exception("Missing required columns (latitude, longitude) in file: $file_path");
            }
            
            // Get state and district IDs
            $state_id = $this->database->get_state_id($state_name);
            if (!$state_id) {
                throw new Exception("State not found: $state_name");
            }
            
            $district_id = $this->database->get_or_create_district($district_name, $state_id);
            if (!$district_id) {
                throw new Exception("Could not create/find district: $district_name");
            }
            
            // Identify date columns and other columns
            $column_mapping = $this->map_columns($headers);
            
            // Process data by month to avoid NA propagation
            $monthly_data = $this->extract_monthly_data($worksheet, $column_mapping, $state_id, $district_id, $file_path);
            
            // Insert data into database
            $inserted_count = $this->insert_monthly_data($monthly_data);
            
            $this->stats['files_processed']++;
            $this->stats['records_inserted'] += $inserted_count;
            
            return array(
                'success' => true,
                'records_inserted' => $inserted_count,
                'monthly_datasets' => count($monthly_data)
            );
            
        } catch (Exception $e) {
            $this->stats['errors'][] = array(
                'file' => $file_path,
                'error' => $e->getMessage()
            );
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Validate file existence and type
     */
    private function validate_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        return in_array($extension, $this->supported_types);
    }
    
    /**
     * Validate required headers
     */
    private function validate_headers($headers) {
        $required = array('latitude', 'longitude');
        $headers_lower = array_map('strtolower', $headers);
        
        foreach ($required as $req) {
            if (!in_array($req, $headers_lower)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Map columns to their purposes
     */
    private function map_columns($headers) {
        $mapping = array(
            'lat_col' => null,
            'lng_col' => null,
            'date_cols' => array()
        );
        
        foreach ($headers as $index => $header) {
            $header_lower = strtolower(trim($header));
            
            if (in_array($header_lower, array('latitude', 'lat'))) {
                $mapping['lat_col'] = $index;
            } elseif (in_array($header_lower, array('longitude', 'lng', 'lon'))) {
                $mapping['lng_col'] = $index;
            } elseif ($this->is_date_column($header)) {
                $mapping['date_cols'][$index] = $this->parse_date_from_header($header);
            }
        }
        
        return $mapping;
    }
    
    /**
     * Check if column header represents a date (YYYY_MM_DD format)
     */
    private function is_date_column($header) {
        return preg_match('/^\d{4}_\d{2}_\d{2}$/', trim($header));
    }
    
    /**
     * Parse date from column header
     */
    private function parse_date_from_header($header) {
        $parts = explode('_', trim($header));
        if (count($parts) === 3) {
            return array(
                'year' => (int)$parts[0],
                'month' => (int)$parts[1],
                'day' => (int)$parts[2],
                'date_string' => sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2])
            );
        }
        return null;
    }
    
    /**
     * Extract monthly data creating separate datasets per month to avoid NA propagation
     */
    private function extract_monthly_data($worksheet, $column_mapping, $state_id, $district_id, $source_file) {
        $monthly_data = array();
        
        // Process each date column separately
        foreach ($column_mapping['date_cols'] as $col_index => $date_info) {
            if (!$date_info) continue;
            
            $month_key = $date_info['year'] . '_' . sprintf('%02d', $date_info['month']);
            $monthly_data[$month_key] = array();
            
            // Iterate through data rows (skip header)
            $rowIterator = $worksheet->getRowIterator(2);
            foreach ($rowIterator as $row) {
                $cellIterator = $row->getCellIterator();
                $row_data = array();
                
                // Extract all cell values
                foreach ($cellIterator as $cell) {
                    $row_data[] = $cell->getValue();
                }
                
                // Check if we have enough columns
                if (count($row_data) <= max($column_mapping['lat_col'], $column_mapping['lng_col'], $col_index)) {
                    continue;
                }
                
                // Extract latitude, longitude, and emission value for this date
                $latitude = $this->clean_numeric_value($row_data[$column_mapping['lat_col']]);
                $longitude = $this->clean_numeric_value($row_data[$column_mapping['lng_col']]);
                $emission_value = $this->clean_numeric_value($row_data[$col_index]);
                
                // Validate data quality
                if ($this->is_valid_coordinate($latitude, $longitude) && $this->is_valid_emission($emission_value)) {
                    $monthly_data[$month_key][] = array(
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'state_id' => $state_id,
                        'district_id' => $district_id,
                        'measurement_date' => $date_info['date_string'],
                        'emission_value' => $emission_value,
                        'data_quality' => 1,
                        'source_file' => basename($source_file)
                    );
                } else {
                    $this->stats['records_skipped']++;
                }
            }
        }
        
        return $monthly_data;
    }
    
    /**
     * Clean and convert numeric values
     */
    private function clean_numeric_value($value) {
        if (is_null($value) || $value === '') {
            return null;
        }
        
        // Handle Excel date/numeric formatting
        if (is_numeric($value)) {
            return (float)$value;
        }
        
        // Clean string values
        $cleaned = trim(str_replace(array(',', ' '), '', $value));
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }
    
    /**
     * Validate coordinate values
     */
    private function is_valid_coordinate($latitude, $longitude) {
        return !is_null($latitude) && !is_null($longitude) &&
               is_numeric($latitude) && is_numeric($longitude) &&
               $latitude >= -90 && $latitude <= 90 &&
               $longitude >= -180 && $longitude <= 180 &&
               // Basic validation for Indian coordinates
               $latitude >= 6 && $latitude <= 38 &&
               $longitude >= 68 && $longitude <= 98;
    }
    
    /**
     * Validate emission values
     */
    private function is_valid_emission($value) {
        return !is_null($value) && is_numeric($value) && $value > 0 && $value < 10000; // ppb range
    }
    
    /**
     * Insert monthly data into database
     */
    private function insert_monthly_data($monthly_data) {
        $total_inserted = 0;
        
        foreach ($monthly_data as $month_key => $data_points) {
            if (empty($data_points)) {
                continue;
            }
            
            // Batch insert for efficiency
            $inserted = $this->database->batch_insert_emissions($data_points, 1000);
            $total_inserted += $inserted;
        }
        
        return $total_inserted;
    }
    
    /**
     * Process multiple files from directory structure
     * Expected structure: base_dir/state_name/district_name.xlsx
     */
    public function process_directory($base_dir) {
        $this->init_stats();
        
        if (!is_dir($base_dir)) {
            return array('success' => false, 'error' => 'Directory not found');
        }
        
        // Scan for state directories
        $state_dirs = glob($base_dir . '/*', GLOB_ONLYDIR);
        
        foreach ($state_dirs as $state_dir) {
            $state_name = basename($state_dir);
            
            // Process Excel files in state directory
            $excel_files = glob($state_dir . '/*.{xlsx,xls,csv}', GLOB_BRACE);
            
            foreach ($excel_files as $file_path) {
                $district_name = pathinfo($file_path, PATHINFO_FILENAME);
                
                // Process individual file
                $result = $this->process_excel_file($file_path, $state_name, $district_name);
                
                if ($result['success']) {
                    error_log("Processed: $state_name/$district_name - {$result['records_inserted']} records");
                } else {
                    error_log("Failed: $state_name/$district_name - {$result['error']}");
                }
            }
        }
        
        // Calculate processing time
        $this->stats['processing_time'] = microtime(true) - $this->stats['start_time'];
        
        // Calculate monthly aggregations after processing
        $this->database->calculate_monthly_aggregations();
        
        return $this->get_processing_stats();
    }
    
    /**
     * Calculate proper state-level averages using sum/count methodology
     * This ensures accurate averaging regardless of varying data point densities
     */
    public function calculate_state_averages($year = null, $month = null) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($year && $month) {
            $where_clause = 'AND YEAR(e.measurement_date) = %d AND MONTH(e.measurement_date) = %d';
            $params = array($year, $month);
        }
        
        // Get all states
        $states = $this->database->get_states_list();
        $state_averages = array();
        
        foreach ($states as $state) {
            // Calculate proper average using sum of all data points divided by count
            $sql = "SELECT 
                        s.state_name,
                        COUNT(e.id) as total_points,
                        SUM(e.emission_value) as total_emission,
                        AVG(e.emission_value) as avg_emission,
                        MIN(e.emission_value) as min_emission,
                        MAX(e.emission_value) as max_emission,
                        STDDEV(e.emission_value) as std_emission
                    FROM {$wpdb->prefix}methane_states s
                    LEFT JOIN {$wpdb->prefix}methane_emissions e ON s.id = e.state_id
                    WHERE s.id = %d $where_clause
                    GROUP BY s.id, s.state_name";
            
            $query_params = array_merge(array($state['id']), $params);
            $result = $wpdb->get_row($wpdb->prepare($sql, $query_params), ARRAY_A);
            
            if ($result && $result['total_points'] > 0) {
                // Use sum/count for most accurate averaging
                $accurate_average = $result['total_emission'] / $result['total_points'];
                
                $state_averages[] = array(
                    'state_name' => $result['state_name'],
                    'avg_emission' => $accurate_average,
                    'min_emission' => $result['min_emission'],
                    'max_emission' => $result['max_emission'],
                    'std_emission' => $result['std_emission'],
                    'data_points' => $result['total_points']
                );
            }
        }
        
        return $state_averages;
    }
    
    /**
     * Calculate district averages for a specific state
     */
    public function calculate_district_averages($state_name, $year = null, $month = null) {
        global $wpdb;
        
        $where_clause = '';
        $params = array($state_name);
        
        if ($year && $month) {
            $where_clause = 'AND YEAR(e.measurement_date) = %d AND MONTH(e.measurement_date) = %d';
            $params[] = $year;
            $params[] = $month;
        }
        
        $sql = "SELECT 
                    d.district_name,
                    s.state_name,
                    COUNT(e.id) as total_points,
                    SUM(e.emission_value) as total_emission,
                    AVG(e.emission_value) as avg_emission,
                    MIN(e.emission_value) as min_emission,
                    MAX(e.emission_value) as max_emission,
                    STDDEV(e.emission_value) as std_emission
                FROM {$wpdb->prefix}methane_districts d
                JOIN {$wpdb->prefix}methane_states s ON d.state_id = s.id
                LEFT JOIN {$wpdb->prefix}methane_emissions e ON d.id = e.district_id
                WHERE s.state_name = %s $where_clause
                GROUP BY d.id, d.district_name, s.state_name
                HAVING total_points > 0
                ORDER BY avg_emission DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Export processed data to various formats
     */
    public function export_data($format, $geographic_level, $geographic_name = null, $year = null, $month = null) {
        switch ($format) {
            case 'json':
                return $this->export_to_json($geographic_level, $geographic_name, $year, $month);
            case 'csv':
                return $this->export_to_csv($geographic_level, $geographic_name, $year, $month);
            case 'geojson':
                return $this->export_to_geojson($geographic_level, $geographic_name, $year, $month);
            default:
                throw new Exception("Unsupported export format: $format");
        }
    }
    
    /**
     * Export data to JSON format
     */
    private function export_to_json($geographic_level, $geographic_name, $year, $month) {
        switch ($geographic_level) {
            case 'india':
                $data = $this->database->get_india_data($year, $month);
                break;
            case 'state':
                $data = $this->database->get_state_data($geographic_name, $year, $month);
                break;
            case 'district':
                $data = $this->database->get_district_data($geographic_name, null, $year, $month);
                break;
            default:
                throw new Exception("Invalid geographic level: $geographic_level");
        }
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get processing statistics
     */
    public function get_processing_stats() {
        return $this->stats;
    }
    
    /**
     * Validate uploaded file before processing
     */
    public function validate_uploaded_file($file) {
        $errors = array();
        
        // Check file size
        $max_size = get_option('methane_monitor_options')['max_file_size'] * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $max_size) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Check file type
        $allowed_types = get_option('methane_monitor_options')['allowed_file_types'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error occurred';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Handle file upload and processing
     */
    public function handle_file_upload($files, $state_name, $district_name) {
        $results = array();
        
        foreach ($files as $file) {
            // Validate file
            $validation = $this->validate_uploaded_file($file);
            if ($validation !== true) {
                $results[] = array(
                    'file' => $file['name'],
                    'success' => false,
                    'errors' => $validation
                );
                continue;
            }
            
            // Move file to secure location
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/methane-monitor/data/';
            $target_file = $target_dir . basename($file['name']);
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Process the file
                $result = $this->process_excel_file($target_file, $state_name, $district_name);
                $result['file'] = $file['name'];
                $results[] = $result;
                
                // Clean up uploaded file after processing
                unlink($target_file);
            } else {
                $results[] = array(
                    'file' => $file['name'],
                    'success' => false,
                    'error' => 'Failed to save uploaded file'
                );
            }
        }
        
        return $results;
    }
}
