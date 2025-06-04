<?php
/**
 * Methane Monitor Database Management Class
 * 
 * Handles all database operations for methane emissions data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Database {
    
    /**
     * WordPress database instance
     */
    private $wpdb;
    
    /**
     * Table names
     */
    private $tables;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Define table names
        $this->tables = array(
            'emissions' => $wpdb->prefix . 'methane_emissions',
            'states' => $wpdb->prefix . 'methane_states',
            'districts' => $wpdb->prefix . 'methane_districts',
            'monthly_data' => $wpdb->prefix . 'methane_monthly_data',
            'analytics' => $wpdb->prefix . 'methane_analytics'
        );
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // States table
        $sql_states = "CREATE TABLE {$this->tables['states']} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            state_name varchar(100) NOT NULL,
            state_code varchar(10) NOT NULL,
            total_area float DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY state_name (state_name),
            KEY state_code (state_code)
        ) $charset_collate;";
        
        // Districts table
        $sql_districts = "CREATE TABLE {$this->tables['districts']} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            district_name varchar(100) NOT NULL,
            state_id mediumint(9) NOT NULL,
            district_area float DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY state_id (state_id),
            KEY district_name (district_name),
            FOREIGN KEY (state_id) REFERENCES {$this->tables['states']}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Emissions data table (point data)
        $sql_emissions = "CREATE TABLE {$this->tables['emissions']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            state_id mediumint(9) NOT NULL,
            district_id mediumint(9) NOT NULL,
            measurement_date date NOT NULL,
            emission_value float NOT NULL,
            data_quality tinyint(1) DEFAULT 1,
            source_file varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lat_lng (latitude, longitude),
            KEY state_district (state_id, district_id),
            KEY measurement_date (measurement_date),
            KEY emission_value (emission_value),
            FOREIGN KEY (state_id) REFERENCES {$this->tables['states']}(id) ON DELETE CASCADE,
            FOREIGN KEY (district_id) REFERENCES {$this->tables['districts']}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Monthly aggregated data table
        $sql_monthly = "CREATE TABLE {$this->tables['monthly_data']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            geographic_type enum('india', 'state', 'district') NOT NULL,
            geographic_id mediumint(9) DEFAULT NULL,
            year smallint(4) NOT NULL,
            month tinyint(2) NOT NULL,
            avg_emission float NOT NULL,
            min_emission float DEFAULT NULL,
            max_emission float DEFAULT NULL,
            median_emission float DEFAULT NULL,
            std_emission float DEFAULT NULL,
            data_points_count int(11) DEFAULT 0,
            calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_monthly_data (geographic_type, geographic_id, year, month),
            KEY year_month (year, month),
            KEY geographic_lookup (geographic_type, geographic_id)
        ) $charset_collate;";
        
        // Analytics cache table
        $sql_analytics = "CREATE TABLE {$this->tables['analytics']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_type enum('ranking', 'timeseries', 'correlation', 'clustering', 'extreme_events') NOT NULL,
            geographic_type enum('india', 'state', 'district') NOT NULL,
            geographic_id mediumint(9) DEFAULT NULL,
            year smallint(4) DEFAULT NULL,
            month tinyint(2) DEFAULT NULL,
            data_json longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY cache_type (cache_type),
            KEY expires_at (expires_at),
            KEY geographic_lookup (geographic_type, geographic_id)
        ) $charset_collate;";
        
        // Execute table creation
        dbDelta($sql_states);
        dbDelta($sql_districts);
        dbDelta($sql_emissions);
        dbDelta($sql_monthly);
        dbDelta($sql_analytics);
        
        // Insert default states and districts if they don't exist
        $this->insert_default_geographic_data();
        
        // Update database version
        update_option('methane_monitor_db_version', '1.0');
    }
    
    /**
     * Insert default Indian states and districts
     */
    private function insert_default_geographic_data() {
        // Check if states already exist
        $state_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['states']}");
        
        if ($state_count > 0) {
            return; // Data already exists
        }
        
        // Indian states data
        $states_data = array(
            array('state_name' => 'ANDHRA PRADESH', 'state_code' => 'AP'),
            array('state_name' => 'ARUNACHAL PRADESH', 'state_code' => 'AR'),
            array('state_name' => 'ASSAM', 'state_code' => 'AS'),
            array('state_name' => 'BIHAR', 'state_code' => 'BR'),
            array('state_name' => 'CHHATTISGARH', 'state_code' => 'CG'),
            array('state_name' => 'GOA', 'state_code' => 'GA'),
            array('state_name' => 'GUJARAT', 'state_code' => 'GJ'),
            array('state_name' => 'HARYANA', 'state_code' => 'HR'),
            array('state_name' => 'HIMACHAL PRADESH', 'state_code' => 'HP'),
            array('state_name' => 'JHARKHAND', 'state_code' => 'JH'),
            array('state_name' => 'KARNATAKA', 'state_code' => 'KA'),
            array('state_name' => 'KERALA', 'state_code' => 'KL'),
            array('state_name' => 'MADHYA PRADESH', 'state_code' => 'MP'),
            array('state_name' => 'MAHARASHTRA', 'state_code' => 'MH'),
            array('state_name' => 'MANIPUR', 'state_code' => 'MN'),
            array('state_name' => 'MEGHALAYA', 'state_code' => 'ML'),
            array('state_name' => 'MIZORAM', 'state_code' => 'MZ'),
            array('state_name' => 'NAGALAND', 'state_code' => 'NL'),
            array('state_name' => 'ODISHA', 'state_code' => 'OR'),
            array('state_name' => 'PUNJAB', 'state_code' => 'PB'),
            array('state_name' => 'RAJASTHAN', 'state_code' => 'RJ'),
            array('state_name' => 'SIKKIM', 'state_code' => 'SK'),
            array('state_name' => 'TAMIL NADU', 'state_code' => 'TN'),
            array('state_name' => 'TELANGANA', 'state_code' => 'TG'),
            array('state_name' => 'TRIPURA', 'state_code' => 'TR'),
            array('state_name' => 'UTTAR PRADESH', 'state_code' => 'UP'),
            array('state_name' => 'UTTARAKHAND', 'state_code' => 'UK'),
            array('state_name' => 'WEST BENGAL', 'state_code' => 'WB')
        );
        
        // Insert states
        foreach ($states_data as $state) {
            $this->wpdb->insert(
                $this->tables['states'],
                $state,
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Get state ID by name
     */
    public function get_state_id($state_name) {
        $state_name = strtoupper(trim($state_name));
        
        $state_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['states']} WHERE state_name = %s",
                $state_name
            )
        );
        
        return $state_id;
    }
    
    /**
     * Get or create district
     */
    public function get_or_create_district($district_name, $state_id) {
        $district_name = strtoupper(trim($district_name));
        
        // Check if district exists
        $district_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['districts']} 
                 WHERE district_name = %s AND state_id = %d",
                $district_name, $state_id
            )
        );
        
        if (!$district_id) {
            // Create new district
            $result = $this->wpdb->insert(
                $this->tables['districts'],
                array(
                    'district_name' => $district_name,
                    'state_id' => $state_id
                ),
                array('%s', '%d')
            );
            
            if ($result !== false) {
                $district_id = $this->wpdb->insert_id;
            }
        }
        
        return $district_id;
    }
    
    /**
     * Insert emission data point
     */
    public function insert_emission_data($data) {
        return $this->wpdb->insert(
            $this->tables['emissions'],
            array(
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'state_id' => $data['state_id'],
                'district_id' => $data['district_id'],
                'measurement_date' => $data['measurement_date'],
                'emission_value' => $data['emission_value'],
                'data_quality' => isset($data['data_quality']) ? $data['data_quality'] : 1,
                'source_file' => isset($data['source_file']) ? $data['source_file'] : null
            ),
            array('%f', '%f', '%d', '%d', '%s', '%f', '%d', '%s')
        );
    }
    
    /**
     * Batch insert emission data
     */
    public function batch_insert_emissions($data_array, $batch_size = 1000) {
        $inserted = 0;
        $batches = array_chunk($data_array, $batch_size);
        
        foreach ($batches as $batch) {
            $values = array();
            $placeholders = array();
            
            foreach ($batch as $data) {
                $placeholders[] = "(%f, %f, %d, %d, %s, %f, %d, %s)";
                $values[] = $data['latitude'];
                $values[] = $data['longitude'];
                $values[] = $data['state_id'];
                $values[] = $data['district_id'];
                $values[] = $data['measurement_date'];
                $values[] = $data['emission_value'];
                $values[] = isset($data['data_quality']) ? $data['data_quality'] : 1;
                $values[] = isset($data['source_file']) ? $data['source_file'] : null;
            }
            
            $sql = "INSERT INTO {$this->tables['emissions']} 
                    (latitude, longitude, state_id, district_id, measurement_date, emission_value, data_quality, source_file) 
                    VALUES " . implode(', ', $placeholders);
            
            $prepared = $this->wpdb->prepare($sql, $values);
            $result = $this->wpdb->query($prepared);
            
            if ($result !== false) {
                $inserted += $result;
            }
        }
        
        return $inserted;
    }
    
    /**
     * Get emission data for India (aggregated by state)
     */
    public function get_india_data($year, $month) {
        $sql = "SELECT 
                    s.id as state_id,
                    s.state_name,
                    AVG(e.emission_value) as avg_emission,
                    MIN(e.emission_value) as min_emission,
                    MAX(e.emission_value) as max_emission,
                    COUNT(e.id) as data_points
                FROM {$this->tables['states']} s
                LEFT JOIN {$this->tables['emissions']} e ON s.id = e.state_id 
                    AND YEAR(e.measurement_date) = %d 
                    AND MONTH(e.measurement_date) = %d
                GROUP BY s.id, s.state_name
                ORDER BY s.state_name";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $year, $month),
            ARRAY_A
        );
    }
    
    /**
     * Get emission data for a specific state (aggregated by district)
     */
    public function get_state_data($state_name, $year, $month) {
        $sql = "SELECT 
                    d.id as district_id,
                    d.district_name,
                    s.state_name,
                    AVG(e.emission_value) as avg_emission,
                    MIN(e.emission_value) as min_emission,
                    MAX(e.emission_value) as max_emission,
                    COUNT(e.id) as data_points
                FROM {$this->tables['districts']} d
                JOIN {$this->tables['states']} s ON d.state_id = s.id
                LEFT JOIN {$this->tables['emissions']} e ON d.id = e.district_id 
                    AND YEAR(e.measurement_date) = %d 
                    AND MONTH(e.measurement_date) = %d
                WHERE s.state_name = %s
                GROUP BY d.id, d.district_name, s.state_name
                ORDER BY d.district_name";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $year, $month, strtoupper($state_name)),
            ARRAY_A
        );
    }
    
    /**
     * Get emission data points for a specific district
     */
    public function get_district_data($state_name, $district_name, $year, $month) {
        $sql = "SELECT 
                    e.latitude,
                    e.longitude,
                    e.emission_value,
                    e.measurement_date,
                    e.data_quality
                FROM {$this->tables['emissions']} e
                JOIN {$this->tables['districts']} d ON e.district_id = d.id
                JOIN {$this->tables['states']} s ON e.state_id = s.id
                WHERE s.state_name = %s 
                    AND d.district_name = %s 
                    AND YEAR(e.measurement_date) = %d 
                    AND MONTH(e.measurement_date) = %d
                    AND e.data_quality = 1
                ORDER BY e.emission_value DESC";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, strtoupper($state_name), strtoupper($district_name), $year, $month),
            ARRAY_A
        );
    }
    
    /**
     * Get available years and months
     */
    public function get_available_periods() {
        $sql = "SELECT DISTINCT 
                    YEAR(measurement_date) as year,
                    MONTH(measurement_date) as month
                FROM {$this->tables['emissions']}
                ORDER BY year DESC, month DESC";
        
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get list of all states
     */
    public function get_states_list() {
        $sql = "SELECT id, state_name, state_code FROM {$this->tables['states']} ORDER BY state_name";
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get list of districts for a state
     */
    public function get_districts_list($state_name) {
        $sql = "SELECT d.id, d.district_name 
                FROM {$this->tables['districts']} d
                JOIN {$this->tables['states']} s ON d.state_id = s.id
                WHERE s.state_name = %s
                ORDER BY d.district_name";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, strtoupper($state_name)),
            ARRAY_A
        );
    }
    
    /**
     * Calculate and store monthly aggregations
     */
    public function calculate_monthly_aggregations($year = null, $month = null) {
        $where_clause = '';
        $params = array();
        
        if ($year && $month) {
            $where_clause = 'WHERE YEAR(measurement_date) = %d AND MONTH(measurement_date) = %d';
            $params = array($year, $month);
        }
        
        // Calculate India-level aggregation
        $this->calculate_india_aggregation($where_clause, $params);
        
        // Calculate state-level aggregations
        $this->calculate_state_aggregations($where_clause, $params);
        
        // Calculate district-level aggregations
        $this->calculate_district_aggregations($where_clause, $params);
    }
    
    /**
     * Calculate India-level aggregation
     */
    private function calculate_india_aggregation($where_clause, $params) {
        $sql = "SELECT 
                    YEAR(measurement_date) as year,
                    MONTH(measurement_date) as month,
                    AVG(emission_value) as avg_emission,
                    MIN(emission_value) as min_emission,
                    MAX(emission_value) as max_emission,
                    COUNT(*) as data_points
                FROM {$this->tables['emissions']}
                $where_clause
                GROUP BY YEAR(measurement_date), MONTH(measurement_date)";
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
        
        foreach ($results as $result) {
            $this->wpdb->replace(
                $this->tables['monthly_data'],
                array(
                    'geographic_type' => 'india',
                    'geographic_id' => null,
                    'year' => $result['year'],
                    'month' => $result['month'],
                    'avg_emission' => $result['avg_emission'],
                    'min_emission' => $result['min_emission'],
                    'max_emission' => $result['max_emission'],
                    'data_points_count' => $result['data_points']
                )
            );
        }
    }
    
    /**
     * Calculate state-level aggregations
     */
    private function calculate_state_aggregations($where_clause, $params) {
        $states = $this->get_states_list();
        
        foreach ($states as $state) {
            $sql = "SELECT 
                        YEAR(measurement_date) as year,
                        MONTH(measurement_date) as month,
                        AVG(emission_value) as avg_emission,
                        MIN(emission_value) as min_emission,
                        MAX(emission_value) as max_emission,
                        COUNT(*) as data_points
                    FROM {$this->tables['emissions']}
                    WHERE state_id = %d $where_clause
                    GROUP BY YEAR(measurement_date), MONTH(measurement_date)";
            
            $state_params = array_merge(array($state['id']), $params);
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $state_params),
                ARRAY_A
            );
            
            foreach ($results as $result) {
                $this->wpdb->replace(
                    $this->tables['monthly_data'],
                    array(
                        'geographic_type' => 'state',
                        'geographic_id' => $state['id'],
                        'year' => $result['year'],
                        'month' => $result['month'],
                        'avg_emission' => $result['avg_emission'],
                        'min_emission' => $result['min_emission'],
                        'max_emission' => $result['max_emission'],
                        'data_points_count' => $result['data_points']
                    )
                );
            }
        }
    }
    
    /**
     * Calculate district-level aggregations
     */
    private function calculate_district_aggregations($where_clause, $params) {
        // Get all districts
        $sql = "SELECT d.id, d.district_name, s.state_name 
                FROM {$this->tables['districts']} d
                JOIN {$this->tables['states']} s ON d.state_id = s.id";
        
        $districts = $this->wpdb->get_results($sql, ARRAY_A);
        
        foreach ($districts as $district) {
            $sql = "SELECT 
                        YEAR(measurement_date) as year,
                        MONTH(measurement_date) as month,
                        AVG(emission_value) as avg_emission,
                        MIN(emission_value) as min_emission,
                        MAX(emission_value) as max_emission,
                        COUNT(*) as data_points
                    FROM {$this->tables['emissions']}
                    WHERE district_id = %d $where_clause
                    GROUP BY YEAR(measurement_date), MONTH(measurement_date)";
            
            $district_params = array_merge(array($district['id']), $params);
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $district_params),
                ARRAY_A
            );
            
            foreach ($results as $result) {
                $this->wpdb->replace(
                    $this->tables['monthly_data'],
                    array(
                        'geographic_type' => 'district',
                        'geographic_id' => $district['id'],
                        'year' => $result['year'],
                        'month' => $result['month'],
                        'avg_emission' => $result['avg_emission'],
                        'min_emission' => $result['min_emission'],
                        'max_emission' => $result['max_emission'],
                        'data_points_count' => $result['data_points']
                    )
                );
            }
        }
    }
    
    /**
     * Get cached analytics data
     */
    public function get_analytics_cache($cache_key) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT data_json FROM {$this->tables['analytics']} 
                 WHERE cache_key = %s AND expires_at > NOW()",
                $cache_key
            )
        );
        
        return $result ? json_decode($result->data_json, true) : null;
    }
    
    /**
     * Set analytics cache
     */
    public function set_analytics_cache($cache_key, $data, $cache_type, $geographic_type, $geographic_id = null, $year = null, $month = null, $expires_in = 3600) {
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        return $this->wpdb->replace(
            $this->tables['analytics'],
            array(
                'cache_key' => $cache_key,
                'cache_type' => $cache_type,
                'geographic_type' => $geographic_type,
                'geographic_id' => $geographic_id,
                'year' => $year,
                'month' => $month,
                'data_json' => json_encode($data),
                'expires_at' => $expires_at
            )
        );
    }
    
    /**
     * Clean expired cache entries
     */
    public function clean_expired_cache() {
        return $this->wpdb->query(
            "DELETE FROM {$this->tables['analytics']} WHERE expires_at < NOW()"
        );
    }
}
