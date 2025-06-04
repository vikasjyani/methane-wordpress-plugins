<?php
/**
 * Methane Monitor Uninstall Script
 * 
 * Runs when the plugin is deleted from WordPress admin.
 * This file is called automatically by WordPress when the plugin is deleted.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants for uninstall
define('METHANE_MONITOR_UNINSTALLING', true);

/**
 * Clean up plugin data on uninstall
 */
function methane_monitor_uninstall_cleanup() {
    global $wpdb;
    
    // Only proceed if user has proper capabilities
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Get the plugin that was uninstalled
    $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
    if ($plugin !== 'methane-monitor-plugin/methane-monitor-plugin.php') {
        return;
    }
    
    // Remove plugin options
    delete_option('methane_monitor_options');
    delete_option('methane_monitor_db_version');
    delete_option('methane_monitor_activation_redirect');
    
    // Remove any plugin-specific user meta
    delete_metadata('user', 0, 'methane_monitor_preferences', '', true);
    
    // Clean up transients and caches
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_methane_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_methane_%'");
    
    // Clean up scheduled events
    wp_clear_scheduled_hook('methane_monitor_cleanup');
    wp_clear_scheduled_hook('methane_monitor_daily_cleanup');
    wp_clear_scheduled_hook('methane_monitor_weekly_cleanup');
    
    // Get option to preserve data
    $preserve_data = get_option('methane_monitor_preserve_data_on_uninstall', false);
    
    if (!$preserve_data) {
        // Drop database tables
        drop_methane_monitor_tables();
        
        // Remove uploaded files
        remove_methane_monitor_files();
    }
    
    // Clear any remaining cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Clear rewrite rules
    flush_rewrite_rules();
    
    // Log uninstall
    error_log('Methane Monitor plugin uninstalled and cleaned up.');
}

/**
 * Drop all plugin database tables
 */
function drop_methane_monitor_tables() {
    global $wpdb;
    
    $tables = array(
        $wpdb->prefix . 'methane_emissions',
        $wpdb->prefix . 'methane_states', 
        $wpdb->prefix . 'methane_districts',
        $wpdb->prefix . 'methane_monthly_data',
        $wpdb->prefix . 'methane_analytics'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

/**
 * Remove uploaded files and directories
 */
function remove_methane_monitor_files() {
    $upload_dir = wp_upload_dir();
    $methane_dir = $upload_dir['basedir'] . '/methane-monitor/';
    
    if (is_dir($methane_dir)) {
        recursive_rmdir($methane_dir);
    }
}

/**
 * Recursively remove directory and all contents
 */
function recursive_rmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            recursive_rmdir($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}

/**
 * Clean up plugin-specific database entries
 */
function cleanup_methane_monitor_database() {
    global $wpdb;
    
    // Remove any orphaned metadata
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'methane_monitor_%'");
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'methane_monitor_%'");
    $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE 'methane_monitor_%'");
    
    // Remove any custom capabilities added by the plugin
    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap('manage_methane_monitor');
        $role->remove_cap('upload_methane_data');
        $role->remove_cap('view_methane_analytics');
        $role->remove_cap('export_methane_data');
    }
    
    // Remove any custom roles created by the plugin
    remove_role('methane_monitor_analyst');
    remove_role('methane_monitor_viewer');
}

/**
 * Show admin notice about uninstall completion
 */
function methane_monitor_uninstall_notice() {
    if (is_admin() && current_user_can('activate_plugins')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Methane Monitor:</strong> Plugin data has been completely removed from your site.</p>';
            echo '</div>';
        });
    }
}

/**
 * Backup critical data before deletion (optional)
 */
function backup_methane_monitor_data() {
    global $wpdb;
    
    $backup_option = get_option('methane_monitor_backup_on_uninstall', false);
    
    if (!$backup_option) {
        return;
    }
    
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/methane-monitor-backup/';
    
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }
    
    // Export critical data to JSON
    $states = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}methane_states", ARRAY_A);
    $districts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}methane_districts", ARRAY_A);
    $monthly_data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}methane_monthly_data", ARRAY_A);
    
    $backup_data = array(
        'timestamp' => current_time('mysql'),
        'version' => get_option('methane_monitor_db_version', '1.0'),
        'states' => $states,
        'districts' => $districts,
        'monthly_data' => $monthly_data,
        'options' => get_option('methane_monitor_options', array())
    );
    
    $backup_file = $backup_dir . 'methane_monitor_backup_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    
    // Create backup info file
    $info_file = $backup_dir . 'README.txt';
    $info_content = "Methane Monitor Backup Created: " . current_time('F j, Y g:i A') . "\n\n";
    $info_content .= "This backup was created automatically when the Methane Monitor plugin was uninstalled.\n";
    $info_content .= "The backup contains:\n";
    $info_content .= "- States data\n";
    $info_content .= "- Districts data\n";  
    $info_content .= "- Monthly aggregated data\n";
    $info_content .= "- Plugin settings\n\n";
    $info_content .= "To restore this data, you would need to reinstall the plugin and import this backup.\n";
    
    file_put_contents($info_file, $info_content);
}

/**
 * Check for multisite and clean up accordingly
 */
function cleanup_multisite_data() {
    if (!is_multisite()) {
        return;
    }
    
    // Get all sites in the network
    $sites = get_sites(array('number' => 0));
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Clean up site-specific data
        delete_option('methane_monitor_options');
        delete_option('methane_monitor_db_version');
        
        // Clean up site-specific transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_methane_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_methane_%'");
        
        restore_current_blog();
    }
    
    // Clean up network-wide options if they exist
    delete_site_option('methane_monitor_network_options');
}

/**
 * Run the uninstall process
 */
try {
    // Create backup if requested
    backup_methane_monitor_data();
    
    // Clean up multisite data if applicable
    cleanup_multisite_data();
    
    // Clean up database entries
    cleanup_methane_monitor_database();
    
    // Main cleanup process
    methane_monitor_uninstall_cleanup();
    
    // Show completion notice
    methane_monitor_uninstall_notice();
    
} catch (Exception $e) {
    // Log any errors during uninstall
    error_log('Methane Monitor uninstall error: ' . $e->getMessage());
    
    // Try to continue with basic cleanup
    delete_option('methane_monitor_options');
    delete_option('methane_monitor_db_version');
}

// Final cleanup - remove any remaining traces
unset($GLOBALS['methane_monitor_plugin']);

// Clear any object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}