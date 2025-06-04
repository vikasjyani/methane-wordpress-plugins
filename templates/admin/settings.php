<?php
/**
 * Settings Page Template
 * 
 * Template for the plugin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current options
$options = get_option('methane_monitor_options', array());
$defaults = methane_monitor_get_option();
$options = wp_parse_args($options, $defaults);

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php _e('Methane Monitor Settings', 'methane-monitor'); ?></h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Settings saved.', 'methane-monitor'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Settings Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix" role="tablist">
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=general'); ?>" 
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'general' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('General', 'methane-monitor'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=map'); ?>" 
           class="nav-tab <?php echo $current_tab === 'map' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'map' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-location-alt"></span>
            <?php _e('Map Settings', 'methane-monitor'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=data'); ?>" 
           class="nav-tab <?php echo $current_tab === 'data' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'data' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-database"></span>
            <?php _e('Data Processing', 'methane-monitor'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=performance'); ?>" 
           class="nav-tab <?php echo $current_tab === 'performance' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'performance' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('Performance', 'methane-monitor'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=advanced'); ?>" 
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'advanced' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Advanced', 'methane-monitor'); ?>
        </a>
        
        <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=troubleshooting'); ?>" 
           class="nav-tab <?php echo $current_tab === 'troubleshooting' ? 'nav-tab-active' : ''; ?>"
           role="tab" aria-selected="<?php echo $current_tab === 'troubleshooting' ? 'true' : 'false'; ?>">
            <span class="dashicons dashicons-sos"></span>
            <?php _e('Troubleshooting', 'methane-monitor'); ?>
        </a>
    </nav>
    
    <form method="post" action="options.php" class="methane-settings-form">
        <?php settings_fields('methane_monitor_options'); ?>
        
        <div class="tab-content">
            
            <?php if ($current_tab === 'general'): ?>
            <!-- General Settings Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('General Settings', 'methane-monitor'); ?></h2>
                <p><?php _e('Configure basic plugin functionality and caching options.', 'methane-monitor'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="enable_caching"><?php _e('Enable Caching', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Enable Caching', 'methane-monitor'); ?></span></legend>
                                <label for="enable_caching">
                                    <input type="checkbox" 
                                           id="enable_caching" 
                                           name="methane_monitor_options[enable_caching]" 
                                           value="1" 
                                           <?php checked(1, $options['enable_caching']); ?> />
                                    <?php _e('Enable data caching for better performance', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Caching significantly improves loading times for maps and analytics. Recommended for production sites.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cache_duration"><?php _e('Cache Duration', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="cache_duration" 
                                   name="methane_monitor_options[cache_duration]" 
                                   value="<?php echo esc_attr($options['cache_duration']); ?>" 
                                   min="300" 
                                   max="86400" 
                                   class="small-text" /> 
                            <?php _e('seconds', 'methane-monitor'); ?>
                            <p class="description">
                                <?php _e('How long to cache data. Default: 3600 seconds (1 hour). Range: 300-86400 seconds.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="debug_mode"><?php _e('Debug Mode', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Debug Mode', 'methane-monitor'); ?></span></legend>
                                <label for="debug_mode">
                                    <input type="checkbox" 
                                           id="debug_mode" 
                                           name="methane_monitor_options[debug_mode]" 
                                           value="1" 
                                           <?php checked(1, $options['debug_mode'] ?? false); ?> />
                                    <?php _e('Enable debug logging', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Enable detailed logging for troubleshooting. Only enable when needed as it may impact performance.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($current_tab === 'map'): ?>
            <!-- Map Settings Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('Map Display Settings', 'methane-monitor'); ?></h2>
                <p><?php _e('Configure default map appearance and behavior.', 'methane-monitor'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="default_map_zoom"><?php _e('Default Map Zoom', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="range" 
                                   id="default_map_zoom" 
                                   name="methane_monitor_options[default_map_zoom]" 
                                   value="<?php echo esc_attr($options['default_map_zoom']); ?>" 
                                   min="1" 
                                   max="18" 
                                   oninput="document.getElementById('zoom_value').textContent = this.value" />
                            <span id="zoom_value"><?php echo esc_html($options['default_map_zoom']); ?></span>
                            <p class="description">
                                <?php _e('Default zoom level for the map. Lower values show more area, higher values zoom in closer.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="color_scheme"><?php _e('Color Scheme', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <select id="color_scheme" name="methane_monitor_options[color_scheme]">
                                <option value="viridis" <?php selected($options['color_scheme'], 'viridis'); ?>>
                                    <?php _e('Viridis (Purple to Yellow)', 'methane-monitor'); ?>
                                </option>
                                <option value="plasma" <?php selected($options['color_scheme'], 'plasma'); ?>>
                                    <?php _e('Plasma (Purple to Pink)', 'methane-monitor'); ?>
                                </option>
                                <option value="inferno" <?php selected($options['color_scheme'], 'inferno'); ?>>
                                    <?php _e('Inferno (Black to Yellow)', 'methane-monitor'); ?>
                                </option>
                                <option value="magma" <?php selected($options['color_scheme'], 'magma'); ?>>
                                    <?php _e('Magma (Black to White)', 'methane-monitor'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Color scheme for displaying emission data on maps. Viridis is recommended for accessibility.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_fullscreen"><?php _e('Fullscreen Button', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Fullscreen Button', 'methane-monitor'); ?></span></legend>
                                <label for="enable_fullscreen">
                                    <input type="checkbox" 
                                           id="enable_fullscreen" 
                                           name="methane_monitor_options[enable_fullscreen]" 
                                           value="1" 
                                           <?php checked(1, $options['enable_fullscreen'] ?? true); ?> />
                                    <?php _e('Show fullscreen button on maps', 'methane-monitor'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_zoom_controls"><?php _e('Zoom Controls', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Zoom Controls', 'methane-monitor'); ?></span></legend>
                                <label for="enable_zoom_controls">
                                    <input type="checkbox" 
                                           id="enable_zoom_controls" 
                                           name="methane_monitor_options[enable_zoom_controls]" 
                                           value="1" 
                                           <?php checked(1, $options['enable_zoom_controls'] ?? true); ?> />
                                    <?php _e('Show zoom in/out buttons', 'methane-monitor'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($current_tab === 'data'): ?>
            <!-- Data Processing Settings Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('Data Processing Settings', 'methane-monitor'); ?></h2>
                <p><?php _e('Configure file upload limits and data processing options.', 'methane-monitor'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="max_file_size"><?php _e('Maximum File Size', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="max_file_size" 
                                   name="methane_monitor_options[max_file_size]" 
                                   value="<?php echo esc_attr($options['max_file_size']); ?>" 
                                   min="1" 
                                   max="100" 
                                   class="small-text" /> 
                            <?php _e('MB', 'methane-monitor'); ?>
                            <p class="description">
                                <?php 
                                printf(
                                    __('Maximum size for uploaded files. Server limit: %s', 'methane-monitor'),
                                    ini_get('upload_max_filesize')
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Allowed File Types', 'methane-monitor'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Allowed File Types', 'methane-monitor'); ?></span></legend>
                                <?php
                                $allowed_types = $options['allowed_file_types'] ?? array();
                                $file_types = array(
                                    'xlsx' => __('Excel 2007+ (.xlsx)', 'methane-monitor'),
                                    'xls' => __('Excel 97-2003 (.xls)', 'methane-monitor'),
                                    'csv' => __('Comma Separated Values (.csv)', 'methane-monitor')
                                );
                                
                                foreach ($file_types as $type => $label):
                                ?>
                                <label for="file_type_<?php echo $type; ?>">
                                    <input type="checkbox" 
                                           id="file_type_<?php echo $type; ?>" 
                                           name="methane_monitor_options[allowed_file_types][]" 
                                           value="<?php echo esc_attr($type); ?>" 
                                           <?php checked(in_array($type, $allowed_types)); ?> />
                                    <?php echo $label; ?>
                                </label><br>
                                <?php endforeach; ?>
                                <p class="description">
                                    <?php _e('Select which file types users can upload. Excel formats are recommended for complex data.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Processing Batch Size', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="batch_size" 
                                   name="methane_monitor_options[batch_size]" 
                                   value="<?php echo esc_attr($options['batch_size'] ?? 1000); ?>" 
                                   min="100" 
                                   max="5000" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Number of records to process in each batch. Lower values use less memory but take longer.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="validate_coordinates"><?php _e('Coordinate Validation', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Coordinate Validation', 'methane-monitor'); ?></span></legend>
                                <label for="validate_coordinates">
                                    <input type="checkbox" 
                                           id="validate_coordinates" 
                                           name="methane_monitor_options[validate_coordinates]" 
                                           value="1" 
                                           <?php checked(1, $options['validate_coordinates'] ?? true); ?> />
                                    <?php _e('Validate coordinates are within India boundaries', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Reject data points with coordinates outside India. Disable only if processing global data.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($current_tab === 'performance'): ?>
            <!-- Performance Settings Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('Performance Settings', 'methane-monitor'); ?></h2>
                <p><?php _e('Optimize plugin performance for your server and traffic levels.', 'methane-monitor'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="enable_object_cache"><?php _e('Object Cache', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Object Cache', 'methane-monitor'); ?></span></legend>
                                <label for="enable_object_cache">
                                    <input type="checkbox" 
                                           id="enable_object_cache" 
                                           name="methane_monitor_options[enable_object_cache]" 
                                           value="1" 
                                           <?php checked(1, $options['enable_object_cache'] ?? true); ?> />
                                    <?php _e('Use WordPress object cache if available', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php 
                                    $cache_status = wp_using_ext_object_cache() ? 
                                        __('External object cache detected (Redis/Memcached)', 'methane-monitor') : 
                                        __('Using database cache (consider Redis/Memcached for better performance)', 'methane-monitor');
                                    echo $cache_status;
                                    ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="lazy_load_assets"><?php _e('Lazy Load Assets', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Lazy Load Assets', 'methane-monitor'); ?></span></legend>
                                <label for="lazy_load_assets">
                                    <input type="checkbox" 
                                           id="lazy_load_assets" 
                                           name="methane_monitor_options[lazy_load_assets]" 
                                           value="1" 
                                           <?php checked(1, $options['lazy_load_assets'] ?? true); ?> />
                                    <?php _e('Only load CSS/JS when shortcode is present', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Improves page load times by only loading plugin assets when needed.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="analytics_cache_duration"><?php _e('Analytics Cache Duration', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="analytics_cache_duration" 
                                   name="methane_monitor_options[analytics_cache_duration]" 
                                   value="<?php echo esc_attr($options['analytics_cache_duration'] ?? 7200); ?>" 
                                   min="600" 
                                   max="86400" 
                                   class="regular-text" /> 
                            <?php _e('seconds', 'methane-monitor'); ?>
                            <p class="description">
                                <?php _e('How long to cache analytics results. Default: 7200 seconds (2 hours).', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_compression"><?php _e('Enable Compression', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Enable Compression', 'methane-monitor'); ?></span></legend>
                                <label for="enable_compression">
                                    <input type="checkbox" 
                                           id="enable_compression" 
                                           name="methane_monitor_options[enable_compression]" 
                                           value="1" 
                                           <?php checked(1, $options['enable_compression'] ?? true); ?> />
                                    <?php _e('Compress API responses for faster transfer', 'methane-monitor'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($current_tab === 'advanced'): ?>
            <!-- Advanced Settings Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('Advanced Settings', 'methane-monitor'); ?></h2>
                <p><?php _e('Advanced configuration options for experienced users.', 'methane-monitor'); ?></p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="custom_css"><?php _e('Custom CSS', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <textarea id="custom_css" 
                                      name="methane_monitor_options[custom_css]" 
                                      rows="10" 
                                      cols="50" 
                                      class="large-text code"><?php echo esc_textarea($options['custom_css'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php _e('Add custom CSS to modify the appearance of maps and analytics.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_rate_limit"><?php _e('API Rate Limit', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="api_rate_limit" 
                                   name="methane_monitor_options[api_rate_limit]" 
                                   value="<?php echo esc_attr($options['api_rate_limit'] ?? 1000); ?>" 
                                   min="100" 
                                   max="10000" 
                                   class="regular-text" /> 
                            <?php _e('requests per hour', 'methane-monitor'); ?>
                            <p class="description">
                                <?php _e('Maximum API requests per hour per IP address. Set to 0 to disable rate limiting.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cleanup_on_uninstall"><?php _e('Data Cleanup', 'methane-monitor'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e('Data Cleanup', 'methane-monitor'); ?></span></legend>
                                <label for="cleanup_on_uninstall">
                                    <input type="checkbox" 
                                           id="cleanup_on_uninstall" 
                                           name="methane_monitor_options[cleanup_on_uninstall]" 
                                           value="1" 
                                           <?php checked(1, $options['cleanup_on_uninstall'] ?? false); ?> />
                                    <?php _e('Remove all data when plugin is uninstalled', 'methane-monitor'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('WARNING: This will permanently delete all emission data, settings, and cached files when the plugin is uninstalled.', 'methane-monitor'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($current_tab === 'troubleshooting'): ?>
            <!-- Troubleshooting Tab -->
            <div class="methane-settings-section">
                <h2><?php _e('Troubleshooting & Diagnostics', 'methane-monitor'); ?></h2>
                <p><?php _e('Tools for diagnosing and fixing common issues.', 'methane-monitor'); ?></p>
                
                <!-- System Information -->
                <h3><?php _e('System Information', 'methane-monitor'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Component', 'methane-monitor'); ?></th>
                            <th><?php _e('Value', 'methane-monitor'); ?></th>
                            <th><?php _e('Status', 'methane-monitor'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $system_info = methane_monitor_get_system_info();
                        foreach ($system_info as $key => $value):
                            $status = 'good';
                            if ($key === 'memory_limit' && intval($value) < 256) $status = 'warning';
                            if ($key === 'upload_max_filesize' && intval($value) < 50) $status = 'warning';
                        ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td>
                            <td><code><?php echo esc_html($value); ?></code></td>
                            <td>
                                <span class="status-indicator status-<?php echo $status; ?>">
                                    <?php echo $status === 'good' ? '✓' : '⚠'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Diagnostic Tools -->
                <h3><?php _e('Diagnostic Tools', 'methane-monitor'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php _e('Test Database Connection', 'methane-monitor'); ?></th>
                        <td>
                            <button type="button" class="button" id="test-database">
                                <?php _e('Test Connection', 'methane-monitor'); ?>
                            </button>
                            <span id="database-result"></span>
                            <p class="description">
                                <?php _e('Verify that the plugin can connect to the database and access required tables.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Clear All Cache', 'methane-monitor'); ?></th>
                        <td>
                            <button type="button" class="button" id="clear-all-cache">
                                <?php _e('Clear Cache', 'methane-monitor'); ?>
                            </button>
                            <span id="cache-result"></span>
                            <p class="description">
                                <?php _e('Clear all cached data including transients and object cache.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Reset Settings', 'methane-monitor'); ?></th>
                        <td>
                            <button type="button" class="button button-secondary" id="reset-settings">
                                <?php _e('Reset to Defaults', 'methane-monitor'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Reset all plugin settings to their default values. This will not affect your data.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Export Settings -->
                <h3><?php _e('Settings Backup', 'methane-monitor'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php _e('Export Settings', 'methane-monitor'); ?></th>
                        <td>
                            <button type="button" class="button" id="export-settings">
                                <?php _e('Download Settings', 'methane-monitor'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Download your current plugin settings as a JSON file for backup purposes.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Import Settings', 'methane-monitor'); ?></th>
                        <td>
                            <input type="file" id="import-settings-file" accept=".json" style="display: none;" />
                            <button type="button" class="button" id="import-settings">
                                <?php _e('Import Settings', 'methane-monitor'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Import settings from a previously exported JSON file.', 'methane-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php endif; ?>
        </div>
        
        <?php if ($current_tab !== 'troubleshooting'): ?>
        <?php submit_button(); ?>
        <?php endif; ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Troubleshooting tools
    $('#test-database').click(function() {
        const $button = $(this);
        const $result = $('#database-result');
        
        $button.prop('disabled', true).text('<?php _e('Testing...', 'methane-monitor'); ?>');
        $result.html('<span class="spinner is-active"></span>');
        
        $.post(ajaxurl, {
            action: 'methane_test_database',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $result.html('<span style="color: green;">✓ <?php _e('Connection successful', 'methane-monitor'); ?></span>');
            } else {
                $result.html('<span style="color: red;">✗ <?php _e('Connection failed', 'methane-monitor'); ?>: ' + response.data.message + '</span>');
            }
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Test Connection', 'methane-monitor'); ?>');
        });
    });
    
    $('#clear-all-cache').click(function() {
        const $button = $(this);
        const $result = $('#cache-result');
        
        $button.prop('disabled', true).text('<?php _e('Clearing...', 'methane-monitor'); ?>');
        $result.html('<span class="spinner is-active"></span>');
        
        $.post(ajaxurl, {
            action: 'methane_clear_cache',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                $result.html('<span style="color: green;">✓ <?php _e('Cache cleared', 'methane-monitor'); ?></span>');
            } else {
                $result.html('<span style="color: red;">✗ <?php _e('Failed to clear cache', 'methane-monitor'); ?></span>');
            }
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Clear Cache', 'methane-monitor'); ?>');
        });
    });
    
    $('#reset-settings').click(function() {
        if (!confirm('<?php _e('Are you sure you want to reset all settings to defaults? This cannot be undone.', 'methane-monitor'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'methane_reset_settings',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert('<?php _e('Settings reset successfully. Reloading page...', 'methane-monitor'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Failed to reset settings.', 'methane-monitor'); ?>');
            }
        });
    });
    
    $('#export-settings').click(function() {
        window.location.href = ajaxurl + '?action=methane_export_settings&nonce=<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>';
    });
    
    $('#import-settings').click(function() {
        $('#import-settings-file').click();
    });
    
    $('#import-settings-file').change(function() {
        const file = this.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('action', 'methane_import_settings');
        formData.append('settings_file', file);
        formData.append('nonce', '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Settings imported successfully. Reloading page...', 'methane-monitor'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Failed to import settings:', 'methane-monitor'); ?> ' + response.data.message);
                }
            }
        });
    });
    
    // Form validation
    $('.methane-settings-form').submit(function(e) {
        // Validate cache duration
        const cacheDuration = $('#cache_duration').val();
        if (cacheDuration && (cacheDuration < 300 || cacheDuration > 86400)) {
            alert('<?php _e('Cache duration must be between 300 and 86400 seconds', 'methane-monitor'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Validate file size
        const fileSize = $('#max_file_size').val();
        if (fileSize && (fileSize < 1 || fileSize > 100)) {
            alert('<?php _e('Maximum file size must be between 1 and 100 MB', 'methane-monitor'); ?>');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<style>
.status-indicator {
    font-weight: bold;
    font-size: 16px;
}

.status-indicator.status-good {
    color: #00a32a;
}

.status-indicator.status-warning {
    color: #dba617;
}

.status-indicator.status-error {
    color: #d63638;
}

.methane-settings-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.nav-tab .dashicons {
    margin-right: 5px;
}

.form-table th {
    width: 200px;
}

.code {
    font-family: Consolas, Monaco, monospace;
}
</style>