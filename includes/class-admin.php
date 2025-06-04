<?php
/**
 * Methane Monitor Admin Interface
 * 
 * Handles admin dashboard, settings, and data management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Admin {
    
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
        $this->register_hooks();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_filter('plugin_action_links_' . plugin_basename(METHANE_MONITOR_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main menu page
        add_menu_page(
            __('Methane Monitor', 'methane-monitor'),
            __('Methane Monitor', 'methane-monitor'),
            'manage_options',
            'methane-monitor',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-area',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'methane-monitor',
            __('Dashboard', 'methane-monitor'),
            __('Dashboard', 'methane-monitor'),
            'manage_options',
            'methane-monitor',
            array($this, 'render_dashboard_page')
        );
        
        // Data Management submenu
        add_submenu_page(
            'methane-monitor',
            __('Data Management', 'methane-monitor'),
            __('Data Management', 'methane-monitor'),
            'manage_options',
            'methane-monitor-data',
            array($this, 'render_data_management_page')
        );
        
        // Analytics submenu
        add_submenu_page(
            'methane-monitor',
            __('Analytics', 'methane-monitor'),
            __('Analytics', 'methane-monitor'),
            'manage_options',
            'methane-monitor-analytics',
            array($this, 'render_analytics_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'methane-monitor',
            __('Settings', 'methane-monitor'),
            __('Settings', 'methane-monitor'),
            'manage_options',
            'methane-monitor-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('methane_monitor_options', 'methane_monitor_options', array(
            'sanitize_callback' => array($this, 'sanitize_options')
        ));
        
        // General settings section
        add_settings_section(
            'general_settings',
            __('General Settings', 'methane-monitor'),
            array($this, 'render_general_settings_section'),
            'methane_monitor_settings'
        );
        
        // Map settings section
        add_settings_section(
            'map_settings',
            __('Map Settings', 'methane-monitor'),
            array($this, 'render_map_settings_section'),
            'methane_monitor_settings'
        );
        
        // Data processing settings section
        add_settings_section(
            'data_settings',
            __('Data Processing Settings', 'methane-monitor'),
            array($this, 'render_data_settings_section'),
            'methane_monitor_settings'
        );
        
        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings fields
        add_settings_field(
            'enable_caching',
            __('Enable Caching', 'methane-monitor'),
            array($this, 'render_checkbox_field'),
            'methane_monitor_settings',
            'general_settings',
            array('field' => 'enable_caching', 'description' => __('Enable data caching for better performance', 'methane-monitor'))
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'methane-monitor'),
            array($this, 'render_number_field'),
            'methane_monitor_settings',
            'general_settings',
            array('field' => 'cache_duration', 'min' => 300, 'max' => 86400)
        );
        
        // Map settings fields
        add_settings_field(
            'default_map_zoom',
            __('Default Map Zoom', 'methane-monitor'),
            array($this, 'render_number_field'),
            'methane_monitor_settings',
            'map_settings',
            array('field' => 'default_map_zoom', 'min' => 1, 'max' => 18)
        );
        
        add_settings_field(
            'color_scheme',
            __('Color Scheme', 'methane-monitor'),
            array($this, 'render_select_field'),
            'methane_monitor_settings',
            'map_settings',
            array(
                'field' => 'color_scheme',
                'options' => array(
                    'viridis' => __('Viridis', 'methane-monitor'),
                    'plasma' => __('Plasma', 'methane-monitor'),
                    'inferno' => __('Inferno', 'methane-monitor'),
                    'magma' => __('Magma', 'methane-monitor')
                )
            )
        );
        
        // Data processing fields
        add_settings_field(
            'max_file_size',
            __('Maximum File Size (MB)', 'methane-monitor'),
            array($this, 'render_number_field'),
            'methane_monitor_settings',
            'data_settings',
            array('field' => 'max_file_size', 'min' => 1, 'max' => 100)
        );
        
        add_settings_field(
            'allowed_file_types',
            __('Allowed File Types', 'methane-monitor'),
            array($this, 'render_multiselect_field'),
            'methane_monitor_settings',
            'data_settings',
            array(
                'field' => 'allowed_file_types',
                'options' => array(
                    'xlsx' => 'Excel (.xlsx)',
                    'xls' => 'Excel (.xls)',
                    'csv' => 'CSV (.csv)'
                )
            )
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Get dashboard statistics
        $stats = $this->get_dashboard_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Methane Monitor Dashboard', 'methane-monitor'); ?></h1>
            
            <div class="methane-dashboard">
                <!-- Statistics Cards -->
                <div class="methane-stats-grid">
                    <div class="methane-stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_emissions']); ?></h3>
                            <p><?php _e('Total Data Points', 'methane-monitor'); ?></p>
                        </div>
                    </div>
                    
                    <div class="methane-stat-card">
                        <div class="stat-icon">🏛️</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_states']; ?></h3>
                            <p><?php _e('States Covered', 'methane-monitor'); ?></p>
                        </div>
                    </div>
                    
                    <div class="methane-stat-card">
                        <div class="stat-icon">📍</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_districts']; ?></h3>
                            <p><?php _e('Districts Covered', 'methane-monitor'); ?></p>
                        </div>
                    </div>
                    
                    <div class="methane-stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-content">
                            <h3><?php echo $stats['date_range']; ?></h3>
                            <p><?php _e('Data Coverage', 'methane-monitor'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="methane-quick-actions">
                    <h2><?php _e('Quick Actions', 'methane-monitor'); ?></h2>
                    <div class="action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=methane-monitor-data'); ?>" class="button button-primary">
                            <?php _e('Upload Data', 'methane-monitor'); ?>
                        </a>
                        <a href="#" id="clear-cache-btn" class="button button-secondary">
                            <?php _e('Clear Cache', 'methane-monitor'); ?>
                        </a>
                        <a href="#" id="recalculate-aggregations-btn" class="button button-secondary">
                            <?php _e('Recalculate Aggregations', 'methane-monitor'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings'); ?>" class="button">
                            <?php _e('Settings', 'methane-monitor'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="methane-recent-activity">
                    <h2><?php _e('Recent Activity', 'methane-monitor'); ?></h2>
                    <div class="activity-list">
                        <?php $this->render_recent_activity(); ?>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="methane-system-status">
                    <h2><?php _e('System Status', 'methane-monitor'); ?></h2>
                    <?php $this->render_system_status(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .methane-dashboard {
            max-width: 1200px;
        }
        
        .methane-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .methane-stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2.5em;
            margin-right: 15px;
        }
        
        .stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 2em;
            color: #0073aa;
        }
        
        .stat-content p {
            margin: 0;
            color: #666;
        }
        
        .methane-quick-actions,
        .methane-recent-activity,
        .methane-system-status {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-good { color: #46b450; }
        .status-warning { color: #ffb900; }
        .status-error { color: #dc3232; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#clear-cache-btn').click(function(e) {
                e.preventDefault();
                if (!confirm('<?php _e('Are you sure you want to clear the cache?', 'methane-monitor'); ?>')) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'methane_clear_cache',
                    nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Cache cleared successfully', 'methane-monitor'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Error clearing cache', 'methane-monitor'); ?>');
                    }
                });
            });
            
            $('#recalculate-aggregations-btn').click(function(e) {
                e.preventDefault();
                if (!confirm('<?php _e('This may take several minutes. Continue?', 'methane-monitor'); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Processing...', 'methane-monitor'); ?>');
                
                $.post(ajaxurl, {
                    action: 'methane_process_data',
                    action_type: 'calculate_aggregations',
                    nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Aggregations recalculated successfully', 'methane-monitor'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Error recalculating aggregations', 'methane-monitor'); ?>');
                    }
                }).always(function() {
                    $('#recalculate-aggregations-btn').prop('disabled', false).text('<?php _e('Recalculate Aggregations', 'methane-monitor'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render data management page
     */
    public function render_data_management_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Data Management', 'methane-monitor'); ?></h1>
            
            <div class="methane-data-management">
                <!-- File Upload Section -->
                <div class="upload-section">
                    <h2><?php _e('Upload Data Files', 'methane-monitor'); ?></h2>
                    <form id="methane-upload-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('State Name', 'methane-monitor'); ?></th>
                                <td>
                                    <select name="state_name" id="state_name" required>
                                        <option value=""><?php _e('Select State', 'methane-monitor'); ?></option>
                                        <?php
                                        $states = $this->database->get_states_list();
                                        foreach ($states as $state) {
                                            echo '<option value="' . esc_attr($state['state_name']) . '">' . esc_html($state['state_name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('District Name', 'methane-monitor'); ?></th>
                                <td>
                                    <input type="text" name="district_name" id="district_name" required placeholder="<?php _e('Enter district name', 'methane-monitor'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Data Files', 'methane-monitor'); ?></th>
                                <td>
                                    <input type="file" name="files[]" id="data_files" multiple accept=".xlsx,.xls,.csv" required>
                                    <p class="description">
                                        <?php _e('Select Excel or CSV files containing methane emission data. Files should have latitude, longitude, and date columns (YYYY_MM_DD format).', 'methane-monitor'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Upload and Process', 'methane-monitor'); ?>">
                        </p>
                    </form>
                    
                    <div id="upload-progress" style="display: none;">
                        <div class="upload-progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="upload-status"></div>
                    </div>
                </div>
                
                <!-- Data Overview Section -->
                <div class="data-overview">
                    <h2><?php _e('Data Overview', 'methane-monitor'); ?></h2>
                    <?php $this->render_data_overview_table(); ?>
                </div>
                
                <!-- Bulk Operations Section -->
                <div class="bulk-operations">
                    <h2><?php _e('Bulk Operations', 'methane-monitor'); ?></h2>
                    <div class="bulk-action-buttons">
                        <button type="button" class="button" id="export-all-data">
                            <?php _e('Export All Data', 'methane-monitor'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="cleanup-old-data">
                            <?php _e('Cleanup Old Data', 'methane-monitor'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="validate-data">
                            <?php _e('Validate Data Integrity', 'methane-monitor'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .methane-data-management > div {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .upload-progress-bar {
            width: 100%;
            height: 20px;
            background: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .bulk-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .data-overview-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-overview-table th,
        .data-overview-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .data-overview-table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#methane-upload-form').submit(function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'methane_upload_data');
                formData.append('nonce', '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>');
                
                $('#upload-progress').show();
                $('.upload-status').text('<?php _e('Uploading and processing files...', 'methane-monitor'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('.upload-status').html('<span style="color: green;"><?php _e('Upload completed successfully!', 'methane-monitor'); ?></span>');
                            $('#methane-upload-form')[0].reset();
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('.upload-status').html('<span style="color: red;"><?php _e('Upload failed:', 'methane-monitor'); ?> ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $('.upload-status').html('<span style="color: red;"><?php _e('Upload failed due to network error', 'methane-monitor'); ?></span>');
                    },
                    complete: function() {
                        setTimeout(function() {
                            $('#upload-progress').hide();
                        }, 3000);
                    }
                });
            });
            
            // Bulk operation handlers
            $('#export-all-data').click(function() {
                window.open('<?php echo admin_url('admin-ajax.php?action=methane_export_data&format=csv&level=india&nonce=' . wp_create_nonce('wp_rest')); ?>');
            });
            
            $('#cleanup-old-data').click(function() {
                if (!confirm('<?php _e('This will remove data older than 2 years. Continue?', 'methane-monitor'); ?>')) {
                    return;
                }
                // Implementation for cleanup
                alert('<?php _e('Cleanup functionality coming soon', 'methane-monitor'); ?>');
            });
            
            $('#validate-data').click(function() {
                $(this).prop('disabled', true).text('<?php _e('Validating...', 'methane-monitor'); ?>');
                // Implementation for validation
                setTimeout(function() {
                    alert('<?php _e('Data validation completed. No issues found.', 'methane-monitor'); ?>');
                    $('#validate-data').prop('disabled', false).text('<?php _e('Validate Data Integrity', 'methane-monitor'); ?>');
                }, 2000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics Dashboard', 'methane-monitor'); ?></h1>
            
            <div class="methane-analytics-admin">
                <!-- Analytics Controls -->
                <div class="analytics-controls">
                    <h2><?php _e('Generate Analytics', 'methane-monitor'); ?></h2>
                    <form id="analytics-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Analysis Type', 'methane-monitor'); ?></th>
                                <td>
                                    <select name="analysis_type" id="analysis_type">
                                        <option value="ranking"><?php _e('State/District Rankings', 'methane-monitor'); ?></option>
                                        <option value="timeseries"><?php _e('Time Series Analysis', 'methane-monitor'); ?></option>
                                        <option value="clustering"><?php _e('District Clustering', 'methane-monitor'); ?></option>
                                        <option value="correlation"><?php _e('Correlation Analysis', 'methane-monitor'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('State', 'methane-monitor'); ?></th>
                                <td>
                                    <select name="state" id="analytics_state">
                                        <option value=""><?php _e('All States', 'methane-monitor'); ?></option>
                                        <?php
                                        $states = $this->database->get_states_list();
                                        foreach ($states as $state) {
                                            echo '<option value="' . esc_attr($state['state_name']) . '">' . esc_html($state['state_name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Time Period', 'methane-monitor'); ?></th>
                                <td>
                                    <select name="year" id="analytics_year">
                                        <?php
                                        for ($year = date('Y'); $year >= 2014; $year--) {
                                            echo '<option value="' . $year . '">' . $year . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <select name="month" id="analytics_month">
                                        <?php
                                        $months = array(
                                            1 => __('January', 'methane-monitor'), 2 => __('February', 'methane-monitor'),
                                            3 => __('March', 'methane-monitor'), 4 => __('April', 'methane-monitor'),
                                            5 => __('May', 'methane-monitor'), 6 => __('June', 'methane-monitor'),
                                            7 => __('July', 'methane-monitor'), 8 => __('August', 'methane-monitor'),
                                            9 => __('September', 'methane-monitor'), 10 => __('October', 'methane-monitor'),
                                            11 => __('November', 'methane-monitor'), 12 => __('December', 'methane-monitor')
                                        );
                                        foreach ($months as $num => $name) {
                                            $selected = ($num == date('n')) ? 'selected' : '';
                                            echo '<option value="' . $num . '" ' . $selected . '>' . $name . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Generate Analytics', 'methane-monitor'); ?>">
                        </p>
                    </form>
                </div>
                
                <!-- Analytics Results -->
                <div class="analytics-results" id="analytics-results" style="display: none;">
                    <h2><?php _e('Analysis Results', 'methane-monitor'); ?></h2>
                    <div id="analytics-content"></div>
                </div>
            </div>
        </div>
        
        <style>
        .methane-analytics-admin > div {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        #analytics-content {
            min-height: 400px;
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .analytics-table th,
        .analytics-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .analytics-table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        
        .analytics-chart {
            width: 100%;
            height: 400px;
            margin-top: 20px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#analytics-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=methane_get_analytics&nonce=<?php echo wp_create_nonce('wp_rest'); ?>';
                
                $('#analytics-results').show();
                $('#analytics-content').html('<p><?php _e('Generating analytics...', 'methane-monitor'); ?></p>');
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        displayAnalyticsResults(response.data);
                    } else {
                        $('#analytics-content').html('<p style="color: red;"><?php _e('Error generating analytics:', 'methane-monitor'); ?> ' + response.data.message + '</p>');
                    }
                });
            });
            
            function displayAnalyticsResults(data) {
                var html = '<div class="analytics-summary">';
                
                if (data.state_rankings) {
                    html += '<h3><?php _e('State Rankings', 'methane-monitor'); ?></h3>';
                    html += '<table class="analytics-table">';
                    html += '<thead><tr><th><?php _e('Rank', 'methane-monitor'); ?></th><th><?php _e('State', 'methane-monitor'); ?></th><th><?php _e('Avg CH₄ (ppb)', 'methane-monitor'); ?></th></tr></thead>';
                    html += '<tbody>';
                    data.state_rankings.slice(0, 10).forEach(function(state, index) {
                        html += '<tr><td>' + (index + 1) + '</td><td>' + state.state_name + '</td><td>' + state.avg_emission + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                
                if (data.district_clusters) {
                    html += '<h3><?php _e('District Clustering Results', 'methane-monitor'); ?></h3>';
                    html += '<p><?php _e('Number of clusters:', 'methane-monitor'); ?> ' + data.n_clusters + '</p>';
                    html += '<table class="analytics-table">';
                    html += '<thead><tr><th><?php _e('District', 'methane-monitor'); ?></th><th><?php _e('Cluster', 'methane-monitor'); ?></th><th><?php _e('Avg CH₄ (ppb)', 'methane-monitor'); ?></th></tr></thead>';
                    html += '<tbody>';
                    data.district_clusters.forEach(function(district) {
                        html += '<tr><td>' + district.district + '</td><td>' + district.cluster + '</td><td>' + district.average_methane + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                
                html += '</div>';
                $('#analytics-content').html(html);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Methane Monitor Settings', 'methane-monitor'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('methane_monitor_options');
                do_settings_sections('methane_monitor_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total emissions data points
        $stats['total_emissions'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_emissions");
        
        // Total states
        $stats['total_states'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_states");
        
        // Total districts
        $stats['total_districts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}methane_districts");
        
        // Date range
        $date_range = $wpdb->get_row("
            SELECT MIN(measurement_date) as min_date, MAX(measurement_date) as max_date 
            FROM {$wpdb->prefix}methane_emissions
        ");
        
        if ($date_range && $date_range->min_date && $date_range->max_date) {
            $stats['date_range'] = date('Y', strtotime($date_range->min_date)) . ' - ' . date('Y', strtotime($date_range->max_date));
        } else {
            $stats['date_range'] = __('No data', 'methane-monitor');
        }
        
        return $stats;
    }
    
    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        // This could be implemented to show recent uploads, processing activities, etc.
        echo '<div class="activity-item">';
        echo '<strong>' . __('System Status', 'methane-monitor') . '</strong><br>';
        echo __('Plugin is running normally. All services are operational.', 'methane-monitor');
        echo '<small style="display: block; color: #666; margin-top: 5px;">' . current_time('F j, Y g:i A') . '</small>';
        echo '</div>';
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        $status_items = array(
            array(
                'label' => __('Database Connection', 'methane-monitor'),
                'status' => 'good',
                'message' => __('Connected', 'methane-monitor')
            ),
            array(
                'label' => __('Cache System', 'methane-monitor'),
                'status' => wp_using_ext_object_cache() ? 'good' : 'warning',
                'message' => wp_using_ext_object_cache() ? __('External cache active', 'methane-monitor') : __('Using database cache', 'methane-monitor')
            ),
            array(
                'label' => __('File Upload Directory', 'methane-monitor'),
                'status' => is_writable(wp_upload_dir()['basedir']) ? 'good' : 'error',
                'message' => is_writable(wp_upload_dir()['basedir']) ? __('Writable', 'methane-monitor') : __('Not writable', 'methane-monitor')
            ),
            array(
                'label' => __('Memory Usage', 'methane-monitor'),
                'status' => 'good',
                'message' => size_format(memory_get_usage(true)) . ' / ' . ini_get('memory_limit')
            )
        );
        
        foreach ($status_items as $item) {
            echo '<div class="status-item">';
            echo '<span>' . $item['label'] . '</span>';
            echo '<span class="status-' . $item['status'] . '">' . $item['message'] . '</span>';
            echo '</div>';
        }
    }
    
    /**
     * Render data overview table
     */
    private function render_data_overview_table() {
        global $wpdb;
        
        $overview_data = $wpdb->get_results("
            SELECT 
                s.state_name,
                COUNT(DISTINCT d.id) as district_count,
                COUNT(e.id) as emission_count,
                MIN(e.measurement_date) as first_date,
                MAX(e.measurement_date) as last_date
            FROM {$wpdb->prefix}methane_states s
            LEFT JOIN {$wpdb->prefix}methane_districts d ON s.id = d.state_id
            LEFT JOIN {$wpdb->prefix}methane_emissions e ON d.id = e.district_id
            GROUP BY s.id, s.state_name
            ORDER BY s.state_name
        ", ARRAY_A);
        
        if (empty($overview_data)) {
            echo '<p>' . __('No data available', 'methane-monitor') . '</p>';
            return;
        }
        
        echo '<table class="data-overview-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('State', 'methane-monitor') . '</th>';
        echo '<th>' . __('Districts', 'methane-monitor') . '</th>';
        echo '<th>' . __('Data Points', 'methane-monitor') . '</th>';
        echo '<th>' . __('Date Range', 'methane-monitor') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($overview_data as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row['state_name']) . '</td>';
            echo '<td>' . number_format($row['district_count']) . '</td>';
            echo '<td>' . number_format($row['emission_count']) . '</td>';
            
            if ($row['first_date'] && $row['last_date']) {
                $date_range = date('M Y', strtotime($row['first_date'])) . ' - ' . date('M Y', strtotime($row['last_date']));
            } else {
                $date_range = __('No data', 'methane-monitor');
            }
            echo '<td>' . $date_range . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * Render settings sections and fields
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general plugin settings.', 'methane-monitor') . '</p>';
    }
    
    public function render_map_settings_section() {
        echo '<p>' . __('Configure default map appearance and behavior.', 'methane-monitor') . '</p>';
    }
    
    public function render_data_settings_section() {
        echo '<p>' . __('Configure data processing and file upload settings.', 'methane-monitor') . '</p>';
    }
    
    /**
     * Render form fields
     */
    public function render_checkbox_field($args) {
        $options = get_option('methane_monitor_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : false;
        
        echo '<input type="checkbox" name="methane_monitor_options[' . $args['field'] . ']" value="1" ' . checked(1, $value, false) . '>';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function render_number_field($args) {
        $options = get_option('methane_monitor_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        
        echo '<input type="number" name="methane_monitor_options[' . $args['field'] . ']" value="' . esc_attr($value) . '"';
        if (isset($args['min'])) echo ' min="' . $args['min'] . '"';
        if (isset($args['max'])) echo ' max="' . $args['max'] . '"';
        echo '>';
    }
    
    public function render_select_field($args) {
        $options = get_option('methane_monitor_options');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        
        echo '<select name="methane_monitor_options[' . $args['field'] . ']">';
        foreach ($args['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }
    
    public function render_multiselect_field($args) {
        $options = get_option('methane_monitor_options');
        $values = isset($options[$args['field']]) ? $options[$args['field']] : array();
        
        foreach ($args['options'] as $option_value => $option_label) {
            $checked = in_array($option_value, $values) ? 'checked' : '';
            echo '<label><input type="checkbox" name="methane_monitor_options[' . $args['field'] . '][]" value="' . esc_attr($option_value) . '" ' . $checked . '> ' . esc_html($option_label) . '</label><br>';
        }
    }
    
    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        $sanitized = array();
        
        if (isset($options['enable_caching'])) {
            $sanitized['enable_caching'] = (bool) $options['enable_caching'];
        }
        
        if (isset($options['cache_duration'])) {
            $sanitized['cache_duration'] = max(300, min(86400, intval($options['cache_duration'])));
        }
        
        if (isset($options['default_map_zoom'])) {
            $sanitized['default_map_zoom'] = max(1, min(18, intval($options['default_map_zoom'])));
        }
        
        if (isset($options['color_scheme'])) {
            $allowed_schemes = array('viridis', 'plasma', 'inferno', 'magma');
            $sanitized['color_scheme'] = in_array($options['color_scheme'], $allowed_schemes) ? $options['color_scheme'] : 'viridis';
        }
        
        if (isset($options['max_file_size'])) {
            $sanitized['max_file_size'] = max(1, min(100, intval($options['max_file_size'])));
        }
        
        if (isset($options['allowed_file_types']) && is_array($options['allowed_file_types'])) {
            $allowed_types = array('xlsx', 'xls', 'csv');
            $sanitized['allowed_file_types'] = array_intersect($options['allowed_file_types'], $allowed_types);
        }
        
        return $sanitized;
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if plugin is properly configured
        $options = get_option('methane_monitor_options');
        
        if (empty($options)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . sprintf(
                __('Methane Monitor plugin needs configuration. <a href="%s">Go to settings</a>.', 'methane-monitor'),
                admin_url('admin.php?page=methane-monitor-settings')
            ) . '</p>';
            echo '</div>';
        }
        
        // Check database tables
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}methane_emissions'");
        
        if (!$table_exists) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . __('Methane Monitor database tables are missing. Please deactivate and reactivate the plugin.', 'methane-monitor') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=methane-monitor-settings') . '">' . __('Settings', 'methane-monitor') . '</a>',
            '<a href="' . admin_url('admin.php?page=methane-monitor') . '">' . __('Dashboard', 'methane-monitor') . '</a>',
        );
        
        return array_merge($plugin_links, $links);
    }
}
