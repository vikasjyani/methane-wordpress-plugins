<?php
/**
 * Methane Monitor Plugin Configuration
 * 
 * Central configuration file for plugin settings and constants
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Configuration Class
 */
class Methane_Monitor_Config {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0';
    
    /**
     * Minimum requirements
     */
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.0';
    const MIN_MYSQL_VERSION = '5.6';
    
    /**
     * Default plugin options
     */
    public static function get_default_options() {
        return array(
            // General settings
            'enable_caching' => true,
            'cache_duration' => 3600, // 1 hour
            'debug_mode' => false,
            'cleanup_on_uninstall' => false,
            
            // Map settings
            'default_map_zoom' => 5,
            'default_map_center' => array(
                'lat' => 20.5937,
                'lng' => 78.9629
            ),
            'map_bounds' => array(
                'north' => 37.6,
                'south' => 6.4,
                'east' => 97.25,
                'west' => 68.7
            ),
            'color_scheme' => 'viridis',
            'enable_fullscreen' => true,
            'enable_zoom_controls' => true,
            
            // Data processing settings
            'max_file_size' => 50, // MB
            'allowed_file_types' => array('xlsx', 'xls', 'csv'),
            'batch_size' => 1000,
            'processing_timeout' => 300, // 5 minutes
            'validate_coordinates' => true,
            'coordinate_precision' => 6,
            
            // Performance settings
            'enable_object_cache' => true,
            'enable_compression' => true,
            'lazy_load_assets' => true,
            'minify_assets' => false,
            'cdn_url' => '',
            
            // Analytics settings
            'enable_analytics' => true,
            'analytics_cache_duration' => 7200, // 2 hours
            'max_timeseries_points' => 120, // 10 years of monthly data
            'clustering_algorithm' => 'kmeans',
            'correlation_threshold' => 0.7,
            
            // Security settings
            'allowed_user_roles' => array('administrator', 'editor'),
            'enable_rate_limiting' => true,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 3600, // 1 hour
            'sanitize_uploads' => true,
            
            // Display settings
            'show_credits' => true,
            'show_data_quality' => true,
            'show_loading_animations' => true,
            'responsive_breakpoints' => array(
                'mobile' => 576,
                'tablet' => 768,
                'desktop' => 992,
                'large' => 1200
            ),
            
            // API settings
            'api_version' => 'v1',
            'api_rate_limit' => 1000,
            'api_cache_headers' => true,
            'api_cors_enabled' => false,
            'api_cors_origins' => array(),
            
            // Notification settings
            'enable_admin_notices' => true,
            'enable_error_logging' => true,
            'log_level' => 'warning', // debug, info, warning, error
            'notification_email' => '',
            
            // Backup settings
            'auto_backup' => false,
            'backup_frequency' => 'weekly',
            'backup_retention' => 30, // days
            'backup_location' => 'uploads',
            
            // Maintenance settings
            'auto_cleanup' => true,
            'cleanup_frequency' => 'daily',
            'cleanup_old_cache' => true,
            'cleanup_old_logs' => true,
            'cleanup_temp_files' => true,
            'max_log_size' => 10, // MB
            
            // Advanced settings
            'custom_css' => '',
            'custom_js' => '',
            'google_analytics_id' => '',
            'heatmap_js_cdn' => 'https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js',
            'plotly_js_cdn' => 'https://cdn.plot.ly/plotly-2.26.0.min.js',
            'leaflet_css_cdn' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            'leaflet_js_cdn' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            'chroma_js_cdn' => 'https://unpkg.com/chroma-js@2.4.2/chroma.min.js',
            'bootstrap_css_cdn' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            'bootstrap_js_cdn' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
        );
    }
    
    /**
     * Get database table names
     */
    public static function get_table_names() {
        global $wpdb;
        
        return array(
            'emissions' => $wpdb->prefix . 'methane_emissions',
            'states' => $wpdb->prefix . 'methane_states',
            'districts' => $wpdb->prefix . 'methane_districts',
            'monthly_data' => $wpdb->prefix . 'methane_monthly_data',
            'analytics' => $wpdb->prefix . 'methane_analytics'
        );
    }
    
    /**
     * Get supported color schemes
     */
    public static function get_color_schemes() {
        return array(
            'viridis' => array(
                'name' => __('Viridis', 'methane-monitor'),
                'colors' => array('#440154', '#482777', '#3f4a8a', '#31678e', '#26838f', '#1f9d8a', '#6cce5a', '#b6de2b', '#fee825'),
                'description' => __('Perceptually uniform color scheme', 'methane-monitor')
            ),
            'plasma' => array(
                'name' => __('Plasma', 'methane-monitor'),
                'colors' => array('#0d0887', '#41049d', '#6a00a8', '#8f0da4', '#b12a90', '#cc4778', '#e16462', '#f2844b', '#fca636', '#fcce25'),
                'description' => __('High contrast purple to yellow', 'methane-monitor')
            ),
            'inferno' => array(
                'name' => __('Inferno', 'methane-monitor'),
                'colors' => array('#000004', '#1b0c41', '#4a0c6b', '#781c6d', '#a52c60', '#cf4446', '#ed6925', '#fb9b06', '#f7d03c', '#fcffa4'),
                'description' => __('Dark to bright fire colors', 'methane-monitor')
            ),
            'magma' => array(
                'name' => __('Magma', 'methane-monitor'),
                'colors' => array('#000004', '#140e36', '#3b0f70', '#641a80', '#8c2981', '#b73779', '#de4968', '#f7705c', '#fe9f6d', '#fecf92', '#fcfdbf'),
                'description' => __('Dark purple to light yellow', 'methane-monitor')
            )
        );
    }
    
    /**
     * Get supported file formats
     */
    public static function get_supported_formats() {
        return array(
            'xlsx' => array(
                'name' => 'Excel 2007+',
                'mime_types' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'extensions' => array('xlsx'),
                'max_size' => 50 * 1024 * 1024 // 50MB
            ),
            'xls' => array(
                'name' => 'Excel 97-2003',
                'mime_types' => array('application/vnd.ms-excel'),
                'extensions' => array('xls'),
                'max_size' => 30 * 1024 * 1024 // 30MB
            ),
            'csv' => array(
                'name' => 'Comma Separated Values',
                'mime_types' => array('text/csv', 'application/csv', 'text/plain'),
                'extensions' => array('csv'),
                'max_size' => 20 * 1024 * 1024 // 20MB
            )
        );
    }
    
    /**
     * Get API endpoints configuration
     */
    public static function get_api_endpoints() {
        return array(
            'metadata' => array(
                'path' => '/metadata',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 3600
            ),
            'india_data' => array(
                'path' => '/india/(?P<year>\d{4})/(?P<month>\d{1,2})',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 1800
            ),
            'state_data' => array(
                'path' => '/state/(?P<state_name>[a-zA-Z\s]+)/(?P<year>\d{4})/(?P<month>\d{1,2})',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 1800
            ),
            'district_data' => array(
                'path' => '/district/(?P<state_name>[a-zA-Z\s]+)/(?P<district_name>[a-zA-Z\s]+)/(?P<year>\d{4})/(?P<month>\d{1,2})',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 1800
            ),
            'analytics_timeseries' => array(
                'path' => '/analytics/timeseries/(?P<state_name>[a-zA-Z\s]+)/(?P<district_name>[a-zA-Z\s]+)',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 3600
            ),
            'analytics_clustering' => array(
                'path' => '/analytics/clustering/(?P<state_name>[a-zA-Z\s]+)',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 3600
            ),
            'analytics_ranking' => array(
                'path' => '/analytics/ranking/(?P<year>\d{4})/(?P<month>\d{1,2})',
                'methods' => array('GET'),
                'public' => true,
                'cache' => 3600
            ),
            'admin_upload' => array(
                'path' => '/admin/upload',
                'methods' => array('POST'),
                'public' => false,
                'capability' => 'manage_options'
            ),
            'admin_cache' => array(
                'path' => '/admin/clear-cache',
                'methods' => array('POST'),
                'public' => false,
                'capability' => 'manage_options'
            )
        );
    }
    
    /**
     * Get validation rules
     */
    public static function get_validation_rules() {
        return array(
            'coordinates' => array(
                'latitude' => array(
                    'type' => 'float',
                    'min' => 6.0,
                    'max' => 38.0,
                    'required' => true
                ),
                'longitude' => array(
                    'type' => 'float',
                    'min' => 68.0,
                    'max' => 98.0,
                    'required' => true
                )
            ),
            'emissions' => array(
                'value' => array(
                    'type' => 'float',
                    'min' => 0,
                    'max' => 10000,
                    'required' => true
                ),
                'unit' => 'ppb'
            ),
            'dates' => array(
                'format' => 'Y-m-d',
                'min_year' => 2014,
                'max_year' => 2030,
                'required' => true
            ),
            'names' => array(
                'state' => array(
                    'type' => 'string',
                    'max_length' => 100,
                    'pattern' => '/^[A-Z\s]+$/',
                    'required' => true
                ),
                'district' => array(
                    'type' => 'string',
                    'max_length' => 100,
                    'pattern' => '/^[A-Z\s]+$/',
                    'required' => true
                )
            )
        );
    }
    
    /**
     * Get error messages
     */
    public static function get_error_messages() {
        return array(
            'file_upload' => array(
                'size_exceeded' => __('File size exceeds maximum allowed size', 'methane-monitor'),
                'invalid_type' => __('Invalid file type', 'methane-monitor'),
                'upload_failed' => __('File upload failed', 'methane-monitor'),
                'processing_failed' => __('File processing failed', 'methane-monitor')
            ),
            'data_validation' => array(
                'invalid_coordinates' => __('Invalid coordinates', 'methane-monitor'),
                'invalid_emission' => __('Invalid emission value', 'methane-monitor'),
                'invalid_date' => __('Invalid date format', 'methane-monitor'),
                'missing_required' => __('Missing required field', 'methane-monitor')
            ),
            'api' => array(
                'invalid_request' => __('Invalid API request', 'methane-monitor'),
                'rate_limit_exceeded' => __('Rate limit exceeded', 'methane-monitor'),
                'data_not_found' => __('Data not found', 'methane-monitor'),
                'server_error' => __('Internal server error', 'methane-monitor')
            ),
            'general' => array(
                'permission_denied' => __('Permission denied', 'methane-monitor'),
                'feature_disabled' => __('Feature is disabled', 'methane-monitor'),
                'maintenance_mode' => __('System is in maintenance mode', 'methane-monitor')
            )
        );
    }
    
    /**
     * Get performance thresholds
     */
    public static function get_performance_thresholds() {
        return array(
            'query_time' => 2.0, // seconds
            'memory_usage' => 128 * 1024 * 1024, // 128MB
            'file_processing_time' => 300, // 5 minutes
            'api_response_time' => 1.0, // 1 second
            'cache_size' => 100 * 1024 * 1024, // 100MB
            'max_data_points' => 50000, // per request
            'concurrent_uploads' => 3
        );
    }
    
    /**
     * Get feature flags
     */
    public static function get_feature_flags() {
        return array(
            'beta_features' => false,
            'experimental_analytics' => false,
            'advanced_clustering' => true,
            'real_time_data' => false,
            'machine_learning' => false,
            'export_formats' => array('csv', 'json', 'xlsx'),
            'import_formats' => array('xlsx', 'csv'),
            'visualization_types' => array('choropleth', 'heatmap', 'contour'),
            'analytics_types' => array('timeseries', 'clustering', 'ranking', 'correlation')
        );
    }
    
    /**
     * Check if feature is enabled
     */
    public static function is_feature_enabled($feature) {
        $flags = self::get_feature_flags();
        return isset($flags[$feature]) && $flags[$feature];
    }
    
    /**
     * Get system requirements
     */
    public static function get_system_requirements() {
        return array(
            'php' => array(
                'version' => self::MIN_PHP_VERSION,
                'extensions' => array('json', 'mbstring', 'curl', 'zip', 'gd'),
                'functions' => array('file_get_contents', 'curl_init', 'json_decode')
            ),
            'wordpress' => array(
                'version' => self::MIN_WP_VERSION,
                'multisite' => true
            ),
            'mysql' => array(
                'version' => self::MIN_MYSQL_VERSION,
                'features' => array('spatial_functions', 'json_functions')
            ),
            'server' => array(
                'memory_limit' => '256M',
                'max_execution_time' => 300,
                'upload_max_filesize' => '50M',
                'post_max_size' => '60M'
            )
        );
    }
}