<?php
/**
 * Plugin Name: Methane Monitor - India Emissions Tracker
 * Plugin URI: https://your-domain.com/methane-monitor
 * Description: Interactive geospatial monitoring system for methane emissions across Indian states and districts with advanced analytics and visualization capabilities.
 * Version: 1.0.0
 * Author: Vasudha Foundation
 * Author URI: https://vasudha-foundation.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: methane-monitor
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('METHANE_MONITOR_VERSION', '1.0.0');
define('METHANE_MONITOR_PLUGIN_FILE', __FILE__);
define('METHANE_MONITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('METHANE_MONITOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('METHANE_MONITOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader if available
if (file_exists(METHANE_MONITOR_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once METHANE_MONITOR_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load core files
require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/functions.php';

/**
 * Main Plugin Class
 */
class Methane_Monitor_Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $database;
    private $data_processor;
    private $admin;
    private $frontend;
    private $rest_api;
    private $ajax_handlers;
    private $shortcodes;
    private $analytics;
    
    /**
     * Get plugin instance (Singleton pattern)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-database.php';
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-data-processor.php';
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-analytics.php';
        
        // Admin classes
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-admin.php';
        
        // Frontend classes
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-shortcodes.php';
        
        // API classes
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once METHANE_MONITOR_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Plugin initialization
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'check_requirements'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Plugin links
        add_filter('plugin_action_links_' . METHANE_MONITOR_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize core components
        $this->database = new Methane_Monitor_Database();
        $this->data_processor = new Methane_Monitor_Data_Processor();
        $this->analytics = new Methane_Monitor_Analytics();
        
        // Initialize admin components
        if (is_admin()) {
            $this->admin = new Methane_Monitor_Admin();
        }
        
        // Initialize frontend components
        $this->frontend = new Methane_Monitor_Frontend();
        $this->shortcodes = new Methane_Monitor_Shortcodes();
        
        // Initialize API components
        $this->rest_api = new Methane_Monitor_REST_API();
        $this->ajax_handlers = new Methane_Monitor_Ajax_Handlers();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        if (!$this->meets_requirements()) {
            deactivate_plugins(METHANE_MONITOR_PLUGIN_BASENAME);
            wp_die(__('Methane Monitor requires PHP 7.4 or higher and WordPress 5.0 or higher.', 'methane-monitor'));
        }
        
        // Create database tables
        $this->database->create_tables();
        
        // Set default options
        $default_options = array(
            'enable_caching' => true,
            'cache_duration' => 3600,
            'max_file_size' => 50,
            'allowed_file_types' => array('xlsx', 'csv'),
            'default_map_zoom' => 5,
            'color_scheme' => 'viridis'
        );
        
        add_option('methane_monitor_options', $default_options);
        
        // Create upload directories
        $upload_dir = methane_monitor_get_upload_dir();
        $directories = array(
            $upload_dir['path'] . 'data/',
            $upload_dir['path'] . 'exports/',
            $upload_dir['path'] . 'temp/'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                file_put_contents($dir . 'index.php', '<?php // Silence is golden');
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        set_transient('methane_monitor_activation_redirect', true, 30);
        
        methane_monitor_log('Plugin activated successfully');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('methane_monitor_cleanup');
        
        // Clear caches
        methane_monitor_clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        methane_monitor_log('Plugin deactivated');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check for activation redirect
        if (get_transient('methane_monitor_activation_redirect')) {
            delete_transient('methane_monitor_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=methane-monitor'));
                exit;
            }
        }
        
        // Schedule cleanup task
        if (!wp_next_scheduled('methane_monitor_cleanup')) {
            wp_schedule_event(time(), 'daily', 'methane_monitor_cleanup');
        }
        
        // Add cleanup action
        add_action('methane_monitor_cleanup', array($this, 'daily_cleanup'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'methane-monitor',
            false,
            dirname(METHANE_MONITOR_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        if (!$this->meets_requirements()) {
            add_action('admin_notices', array($this, 'requirements_notice'));
            return false;
        }
        
        // Check if database tables exist
        if (!methane_monitor_is_configured()) {
            add_action('admin_notices', array($this, 'configuration_notice'));
        }
        
        return true;
    }
    
    /**
     * Check if server meets requirements
     */
    private function meets_requirements() {
        return (
            version_compare(PHP_VERSION, '7.4', '>=') &&
            version_compare(get_bloginfo('version'), '5.0', '>=')
        );
    }
    
    /**
     * Show admin notices
     */
    public function admin_notices() {
        // This method is called by the admin_notices hook
        // Individual notice methods are added via check_requirements()
    }
    
    /**
     * Requirements notice
     */
    public function requirements_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Methane Monitor Error:', 'methane-monitor'); ?></strong>
                <?php _e('This plugin requires PHP 7.4 or higher and WordPress 5.0 or higher.', 'methane-monitor'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Configuration notice
     */
    public function configuration_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('Methane Monitor:', 'methane-monitor'); ?></strong>
                <?php printf(
                    __('Plugin needs configuration. Please visit the <a href="%s">settings page</a> to complete setup.', 'methane-monitor'),
                    admin_url('admin.php?page=methane-monitor-settings')
                ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=methane-monitor-settings') . '">' . __('Settings', 'methane-monitor') . '</a>',
            '<a href="' . admin_url('admin.php?page=methane-monitor') . '">' . __('Dashboard', 'methane-monitor') . '</a>'
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Add plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if (METHANE_MONITOR_PLUGIN_BASENAME === $file) {
            $meta_links = array(
                '<a href="https://github.com/vasudha-foundation/methane-monitor" target="_blank">' . __('Documentation', 'methane-monitor') . '</a>',
                '<a href="https://github.com/vasudha-foundation/methane-monitor/issues" target="_blank">' . __('Support', 'methane-monitor') . '</a>'
            );
            
            return array_merge($links, $meta_links);
        }
        
        return $links;
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup() {
        // Clean expired cache
        methane_monitor_clear_cache('methane_monitor_cache_*');
        
        // Clean temporary files
        $upload_dir = methane_monitor_get_upload_dir();
        $temp_dir = $upload_dir['path'] . 'temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > DAY_IN_SECONDS) {
                    unlink($file);
                }
            }
        }
        
        // Clean old exports
        $exports_dir = $upload_dir['path'] . 'exports/';
        if (is_dir($exports_dir)) {
            $files = glob($exports_dir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > (7 * DAY_IN_SECONDS)) {
                    unlink($file);
                }
            }
        }
        
        methane_monitor_log('Daily cleanup completed');
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return METHANE_MONITOR_VERSION;
    }
    
    /**
     * Get database instance
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Get data processor instance
     */
    public function get_data_processor() {
        return $this->data_processor;
    }
    
    /**
     * Get analytics instance
     */
    public function get_analytics() {
        return $this->analytics;
    }
}

/**
 * Get plugin instance
 */
function methane_monitor() {
    return Methane_Monitor_Plugin::get_instance();
}

// Initialize plugin
methane_monitor();

/**
 * Plugin uninstall hook
 */
function methane_monitor_uninstall() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Remove options
    delete_option('methane_monitor_options');
    delete_option('methane_monitor_db_version');
    
    // Remove transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_methane_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_methane_%'");
    
    // Remove uploaded files (optional - comment out if you want to preserve data)

    $upload_dir = wp_upload_dir();
    $methane_dir = $upload_dir['basedir'] . '/methane-monitor/';
    if (is_dir($methane_dir)) {
        function recursive_rmdir($dir) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? recursive_rmdir($path) : unlink($path);
            }
            return rmdir($dir);
        }
        recursive_rmdir($methane_dir);
    }
   
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'methane_monitor_uninstall');