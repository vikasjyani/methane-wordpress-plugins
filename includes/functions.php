<?php
/**
 * Methane Monitor Helper Functions
 * 
 * Utility functions used throughout the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin options with defaults
 */
function methane_monitor_get_option($key = null, $default = null) {
    $options = get_option('methane_monitor_options', array());
    
    // Default options
    $defaults = array(
        'enable_caching' => true,
        'cache_duration' => 3600,
        'max_file_size' => 50,
        'allowed_file_types' => array('xlsx', 'csv'),
        'default_map_zoom' => 5,
        'default_map_center' => array('lat' => 20.5937, 'lng' => 78.9629),
        'color_scheme' => 'viridis'
    );
    
    $options = wp_parse_args($options, $defaults);
    
    if ($key === null) {
        return $options;
    }
    
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Update plugin option
 */
function methane_monitor_update_option($key, $value) {
    $options = get_option('methane_monitor_options', array());
    $options[$key] = $value;
    return update_option('methane_monitor_options', $options);
}

/**
 * Get upload directory for methane monitor
 */
function methane_monitor_get_upload_dir() {
    $upload_dir = wp_upload_dir();
    $methane_dir = $upload_dir['basedir'] . '/methane-monitor/';
    
    // Create directory if it doesn't exist
    if (!file_exists($methane_dir)) {
        wp_mkdir_p($methane_dir);
        
        // Add index.php for security
        file_put_contents($methane_dir . 'index.php', '<?php // Silence is golden');
    }
    
    return array(
        'path' => $methane_dir,
        'url' => $upload_dir['baseurl'] . '/methane-monitor/',
        'subdir' => '/methane-monitor/'
    );
}

/**
 * Validate coordinates
 */
function methane_monitor_validate_coordinates($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }
    
    $lat = floatval($lat);
    $lng = floatval($lng);
    
    // Basic coordinate validation
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return false;
    }
    
    // India-specific validation (optional)
    if ($lat < 6 || $lat > 38 || $lng < 68 || $lng > 98) {
        return false;
    }
    
    return true;
}

/**
 * Validate emission value
 */
function methane_monitor_validate_emission($value) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $value = floatval($value);
    
    // Reasonable range for methane emissions (ppb)
    return $value > 0 && $value < 10000;
}

/**
 * Convert Excel date to MySQL date
 */
function methane_monitor_excel_date_to_mysql($excel_date) {
    if (is_numeric($excel_date)) {
        // Excel serial date
        $unix_date = ($excel_date - 25569) * 86400;
        return date('Y-m-d', $unix_date);
    } elseif (is_string($excel_date)) {
        // Try to parse string date
        $timestamp = strtotime($excel_date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    
    return null;
}

/**
 * Parse date from column header (YYYY_MM_DD format)
 */
function methane_monitor_parse_date_column($column_name) {
    if (!is_string($column_name)) {
        return null;
    }
    
    $parts = explode('_', $column_name);
    
    if (count($parts) === 3 && 
        is_numeric($parts[0]) && 
        is_numeric($parts[1]) && 
        is_numeric($parts[2])) {
        
        $year = intval($parts[0]);
        $month = intval($parts[1]);
        $day = intval($parts[2]);
        
        // Validate date
        if (checkdate($month, $day, $year)) {
            return array(
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'date_string' => sprintf('%04d-%02d-%02d', $year, $month, $day)
            );
        }
    }
    
    return null;
}

/**
 * Sanitize state name
 */
function methane_monitor_sanitize_state_name($state_name) {
    return strtoupper(trim(sanitize_text_field($state_name)));
}

/**
 * Sanitize district name
 */
function methane_monitor_sanitize_district_name($district_name) {
    return strtoupper(trim(sanitize_text_field($district_name)));
}

/**
 * Format file size
 */
function methane_monitor_format_file_size($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get allowed file types for upload
 */
function methane_monitor_get_allowed_file_types() {
    $allowed_types = methane_monitor_get_option('allowed_file_types', array('xlsx', 'csv'));
    
    $mime_types = array();
    foreach ($allowed_types as $type) {
        switch ($type) {
            case 'xlsx':
                $mime_types[] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $mime_types[] = 'application/vnd.ms-excel';
                break;
            case 'csv':
                $mime_types[] = 'text/csv';
                $mime_types[] = 'application/csv';
                break;
        }
    }
    
    return $mime_types;
}

/**
 * Validate uploaded file
 */
function methane_monitor_validate_uploaded_file($file) {
    $errors = array();
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = __('File upload error occurred', 'methane-monitor');
        return $errors;
    }
    
    // Check file size
    $max_size = methane_monitor_get_option('max_file_size', 50) * 1024 * 1024; // Convert MB to bytes
    if ($file['size'] > $max_size) {
        $errors[] = sprintf(
            __('File size (%s) exceeds maximum allowed size (%s)', 'methane-monitor'),
            methane_monitor_format_file_size($file['size']),
            methane_monitor_format_file_size($max_size)
        );
    }
    
    // Check file type
    $allowed_types = methane_monitor_get_option('allowed_file_types', array('xlsx', 'csv'));
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = sprintf(
            __('File type "%s" is not allowed. Allowed types: %s', 'methane-monitor'),
            $file_extension,
            implode(', ', $allowed_types)
        );
    }
    
    // Check MIME type
    $allowed_mime_types = methane_monitor_get_allowed_file_types();
    if (!in_array($file['type'], $allowed_mime_types)) {
        $errors[] = __('Invalid MIME type for uploaded file', 'methane-monitor');
    }
    
    return $errors;
}

/**
 * Log debug message
 */
function methane_monitor_log($message, $context = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[Methane Monitor] [%s] %s', strtoupper($context), $message));
    }
}

/**
 * Get cache key for data
 */
function methane_monitor_get_cache_key($type, $params = array()) {
    $key_parts = array('methane_monitor', $type);
    
    if (!empty($params)) {
        $key_parts[] = md5(serialize($params));
    }
    
    return implode('_', $key_parts);
}

/**
 * Get cached data
 */
function methane_monitor_get_cache($key) {
    if (!methane_monitor_get_option('enable_caching', true)) {
        return false;
    }
    
    return get_transient($key);
}

/**
 * Set cached data
 */
function methane_monitor_set_cache($key, $data, $expiration = null) {
    if (!methane_monitor_get_option('enable_caching', true)) {
        return false;
    }
    
    if ($expiration === null) {
        $expiration = methane_monitor_get_option('cache_duration', 3600);
    }
    
    return set_transient($key, $data, $expiration);
}

/**
 * Clear cache
 */
function methane_monitor_clear_cache($pattern = 'methane_monitor_*') {
    global $wpdb;
    
    $pattern = str_replace('*', '%', $pattern);
    
    // Clear transients
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_' . $pattern,
        '_transient_timeout_' . $pattern
    ));
    
    // Clear object cache if available
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    return true;
}

/**
 * Calculate statistics for an array of values
 */
function methane_monitor_calculate_statistics($values) {
    if (empty($values)) {
        return array(
            'count' => 0,
            'min' => 0,
            'max' => 0,
            'mean' => 0,
            'median' => 0,
            'std' => 0
        );
    }
    
    $values = array_filter($values, 'is_numeric');
    $values = array_map('floatval', $values);
    
    if (empty($values)) {
        return array(
            'count' => 0,
            'min' => 0,
            'max' => 0,
            'mean' => 0,
            'median' => 0,
            'std' => 0
        );
    }
    
    $count = count($values);
    $sum = array_sum($values);
    $mean = $sum / $count;
    
    sort($values);
    $median = ($count % 2 === 0) 
        ? ($values[$count / 2 - 1] + $values[$count / 2]) / 2
        : $values[floor($count / 2)];
    
    // Calculate standard deviation
    $variance = 0;
    foreach ($values as $value) {
        $variance += pow($value - $mean, 2);
    }
    $variance /= $count;
    $std = sqrt($variance);
    
    return array(
        'count' => $count,
        'min' => min($values),
        'max' => max($values),
        'mean' => $mean,
        'median' => $median,
        'std' => $std
    );
}

/**
 * Generate color from value using a color scale
 */
function methane_monitor_value_to_color($value, $min, $max, $scheme = 'viridis') {
    if ($min >= $max) {
        $max = $min + 1; // Avoid division by zero
    }
    
    $normalized = ($value - $min) / ($max - $min);
    $normalized = max(0, min(1, $normalized)); // Clamp between 0 and 1
    
    // Color schemes (simplified)
    $colors = array(
        'viridis' => array(
            array(68, 1, 84),     // Purple
            array(59, 82, 139),   // Blue
            array(33, 145, 140),  // Teal
            array(94, 201, 98),   // Green
            array(253, 231, 37)   // Yellow
        ),
        'plasma' => array(
            array(13, 8, 135),    // Dark blue
            array(126, 3, 168),   // Purple
            array(203, 70, 121),  // Pink
            array(253, 141, 60),  // Orange
            array(252, 253, 191)  // Light yellow
        )
    );
    
    $color_array = isset($colors[$scheme]) ? $colors[$scheme] : $colors['viridis'];
    $color_count = count($color_array);
    
    $index = $normalized * ($color_count - 1);
    $lower_index = floor($index);
    $upper_index = ceil($index);
    $fraction = $index - $lower_index;
    
    if ($lower_index === $upper_index) {
        $color = $color_array[$lower_index];
    } else {
        $lower_color = $color_array[$lower_index];
        $upper_color = $color_array[$upper_index];
        
        $color = array(
            round($lower_color[0] + ($upper_color[0] - $lower_color[0]) * $fraction),
            round($lower_color[1] + ($upper_color[1] - $lower_color[1]) * $fraction),
            round($lower_color[2] + ($upper_color[2] - $lower_color[2]) * $fraction)
        );
    }
    
    return sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2]);
}

/**
 * Check if user can access methane monitor data
 */
function methane_monitor_user_can_access($capability = 'read') {
    if ($capability === 'read') {
        return true; // Public access for reading
    }
    
    return current_user_can($capability);
}

/**
 * Get month name
 */
function methane_monitor_get_month_name($month_number) {
    $months = array(
        1 => __('January', 'methane-monitor'),
        2 => __('February', 'methane-monitor'),
        3 => __('March', 'methane-monitor'),
        4 => __('April', 'methane-monitor'),
        5 => __('May', 'methane-monitor'),
        6 => __('June', 'methane-monitor'),
        7 => __('July', 'methane-monitor'),
        8 => __('August', 'methane-monitor'),
        9 => __('September', 'methane-monitor'),
        10 => __('October', 'methane-monitor'),
        11 => __('November', 'methane-monitor'),
        12 => __('December', 'methane-monitor')
    );
    
    return isset($months[$month_number]) ? $months[$month_number] : '';
}

/**
 * Get plugin version
 */
function methane_monitor_get_version() {
    return defined('METHANE_MONITOR_VERSION') ? METHANE_MONITOR_VERSION : '1.0.0';
}

/**
 * Check if plugin is properly configured
 */
function methane_monitor_is_configured() {
    global $wpdb;
    
    // Check if database tables exist
    $tables = array(
        $wpdb->prefix . 'methane_emissions',
        $wpdb->prefix . 'methane_states',
        $wpdb->prefix . 'methane_districts'
    );
    
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }
    }
    
    return true;
}

/**
 * Get system info for debugging
 */
function methane_monitor_get_system_info() {
    global $wpdb;
    
    return array(
        'plugin_version' => methane_monitor_get_version(),
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $wpdb->db_version(),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'is_configured' => methane_monitor_is_configured()
    );
}