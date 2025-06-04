<?php
/**
 * Methane Monitor Frontend Handler
 * 
 * Manages public-facing functionality and shortcode rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Frontend {
    
    /**
     * Database instance
     */
    private $database;
    
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
        add_action('wp_enqueue_scripts', array($this, 'conditional_enqueue_scripts'));
        add_action('wp_head', array($this, 'add_inline_styles'));
        add_filter('the_content', array($this, 'enhance_content'));
    }
    
    /**
     * Conditionally enqueue scripts only when needed
     */
    public function conditional_enqueue_scripts() {
        global $post;
        
        // Check if current page contains methane monitor shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'methane_monitor')) {
            $this->enqueue_frontend_assets();
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    private function enqueue_frontend_assets() {
        // Leaflet CSS
        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );
        
        // Bootstrap CSS (conditional)
        if (!wp_style_is('bootstrap', 'enqueued')) {
            wp_enqueue_style(
                'bootstrap',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                array(),
                '5.3.0'
            );
        }
        
        // Bootstrap Icons
        wp_enqueue_style(
            'bootstrap-icons',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
            array(),
            '1.11.1'
        );
        
        // Plugin custom styles
        wp_enqueue_style(
            'methane-monitor-public',
            METHANE_MONITOR_PLUGIN_URL . 'assets/css/public.css',
            array('leaflet'),
            METHANE_MONITOR_VERSION
        );
        
        // Leaflet JS
        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );
        
        // Plotly for charts
        wp_enqueue_script(
            'plotly',
            'https://cdn.plot.ly/plotly-2.26.0.min.js',
            array(),
            '2.26.0',
            true
        );
        
        // Leaflet heatmap plugin
        wp_enqueue_script(
            'leaflet-heat',
            'https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js',
            array('leaflet'),
            '0.2.0',
            true
        );
        
        // Chroma.js for color scaling
        wp_enqueue_script(
            'chroma-js',
            'https://unpkg.com/chroma-js@2.4.2/chroma.min.js',
            array(),
            '2.4.2',
            true
        );
        
        // Bootstrap JS (conditional)
        if (!wp_script_is('bootstrap', 'enqueued')) {
            wp_enqueue_script(
                'bootstrap',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
                array(),
                '5.3.0',
                true
            );
        }
        
        // Main plugin script
        wp_enqueue_script(
            'methane-monitor-public',
            METHANE_MONITOR_PLUGIN_URL . 'assets/js/public.js',
            array('jquery', 'leaflet', 'plotly', 'chroma-js'),
            METHANE_MONITOR_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('methane-monitor-public', 'methaneMonitor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('methane-monitor/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => METHANE_MONITOR_PLUGIN_URL,
            'currentYear' => date('Y'),
            'currentMonth' => date('n'),
            'defaultCenter' => array(
                'lat' => 20.5937,
                'lng' => 78.9629
            ),
            'defaultZoom' => 5,
            'bounds' => array(
                'india' => array(
                    'north' => 37.6,
                    'south' => 6.4,
                    'east' => 97.25,
                    'west' => 68.7
                )
            ),
            'strings' => array(
                'loading' => __('Loading data...', 'methane-monitor'),
                'error' => __('Error loading data', 'methane-monitor'),
                'noData' => __('No data available', 'methane-monitor'),
                'ppb' => __('ppb', 'methane-monitor'),
                'clickToExplore' => __('Click to explore', 'methane-monitor'),
                'january' => __('January', 'methane-monitor'),
                'february' => __('February', 'methane-monitor'),
                'march' => __('March', 'methane-monitor'),
                'april' => __('April', 'methane-monitor'),
                'may' => __('May', 'methane-monitor'),
                'june' => __('June', 'methane-monitor'),
                'july' => __('July', 'methane-monitor'),
                'august' => __('August', 'methane-monitor'),
                'september' => __('September', 'methane-monitor'),
                'october' => __('October', 'methane-monitor'),
                'november' => __('November', 'methane-monitor'),
                'december' => __('December', 'methane-monitor')
            ),
            'colorSchemes' => array(
                'viridis' => array('#440154', '#482777', '#3f4a8a', '#31678e', '#26838f', '#1f9d8a', '#6cce5a', '#b6de2b', '#fee825'),
                'plasma' => array('#0d0887', '#41049d', '#6a00a8', '#8f0da4', '#b12a90', '#cc4778', '#e16462', '#f2844b', '#fca636', '#fcce25'),
                'inferno' => array('#000004', '#1b0c41', '#4a0c6b', '#781c6d', '#a52c60', '#cf4446', '#ed6925', '#fb9b06', '#f7d03c', '#fcffa4')
            )
        ));
    }
    
    /**
     * Add inline styles for dynamic theming
     */
    public function add_inline_styles() {
        if ($this->should_load_styles()) {
            ?>
            <style id="methane-monitor-dynamic-styles">
                :root {
                    --methane-primary: #667eea;
                    --methane-secondary: #764ba2;
                    --methane-success: #00c853;
                    --methane-danger: #ff5252;
                    --methane-warning: #ffc107;
                    --methane-info: #17a2b8;
                }
                
                .methane-monitor-container {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                }
                
                .methane-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    backdrop-filter: blur(5px);
                }
                
                .methane-loading-content {
                    background: white;
                    padding: 2rem;
                    border-radius: 1rem;
                    text-align: center;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                }
                
                @media (max-width: 768px) {
                    .methane-monitor-container .controls-panel {
                        position: relative !important;
                        top: auto !important;
                        right: auto !important;
                        margin-top: 1rem;
                        width: 100% !important;
                    }
                }
            </style>
            <?php
        }
    }
    
    /**
     * Check if styles should be loaded
     */
    private function should_load_styles() {
        global $post;
        return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'methane_monitor');
    }
    
    /**
     * Enhance content with additional features
     */
    public function enhance_content($content) {
        // Add schema markup for environmental data
        if (has_shortcode($content, 'methane_monitor')) {
            $schema = $this->generate_schema_markup();
            $content = $schema . $content;
        }
        
        return $content;
    }
    
    /**
     * Generate JSON-LD schema markup for environmental data
     */
    private function generate_schema_markup() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => 'India Methane Emissions Monitor',
            'description' => 'Interactive monitoring system for methane emissions across Indian states and districts',
            'url' => get_permalink(),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            ),
            'spatialCoverage' => array(
                '@type' => 'Place',
                'name' => 'India',
                'geo' => array(
                    '@type' => 'GeoShape',
                    'polygon' => '20.5937,78.9629 20.5937,78.9629 20.5937,78.9629 20.5937,78.9629'
                )
            ),
            'temporalCoverage' => '2014-01-01/2023-12-31',
            'measurementTechnique' => 'Satellite-based methane concentration measurement',
            'variableMeasured' => array(
                '@type' => 'PropertyValue',
                'name' => 'Methane Concentration',
                'unitText' => 'parts per billion (ppb)'
            )
        );
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    /**
     * Generate breadcrumb navigation
     */
    public function generate_breadcrumb($current_level, $state = null, $district = null) {
        $breadcrumb = '<nav aria-label="Geographic navigation" class="methane-breadcrumb">';
        $breadcrumb .= '<ol class="breadcrumb">';
        
        // India level (always present)
        if ($current_level === 'india') {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">🇮🇳 India</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="#" data-action="load-india">🇮🇳 India</a></li>';
        }
        
        // State level
        if ($state) {
            if ($current_level === 'state') {
                $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">🏛️ ' . esc_html($state) . '</li>';
            } else {
                $breadcrumb .= '<li class="breadcrumb-item"><a href="#" data-action="load-state" data-state="' . esc_attr($state) . '">🏛️ ' . esc_html($state) . '</a></li>';
            }
        }
        
        // District level
        if ($district) {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">📍 ' . esc_html($district) . '</li>';
        }
        
        $breadcrumb .= '</ol>';
        $breadcrumb .= '</nav>';
        
        return $breadcrumb;
    }
    
    /**
     * Generate statistics display
     */
    public function generate_stats_display($stats) {
        if (empty($stats)) {
            return '<div class="alert alert-info">' . __('No statistics available', 'methane-monitor') . '</div>';
        }
        
        $output = '<div class="row g-3 mb-4">';
        
        $stat_items = array(
            array(
                'label' => __('Average', 'methane-monitor'),
                'value' => isset($stats['mean']) ? round($stats['mean'], 1) : '--',
                'unit' => 'ppb',
                'class' => 'text-primary',
                'icon' => 'bi-graph-up'
            ),
            array(
                'label' => __('Minimum', 'methane-monitor'),
                'value' => isset($stats['min']) ? round($stats['min'], 1) : '--',
                'unit' => 'ppb',
                'class' => 'text-success',
                'icon' => 'bi-arrow-down'
            ),
            array(
                'label' => __('Maximum', 'methane-monitor'),
                'value' => isset($stats['max']) ? round($stats['max'], 1) : '--',
                'unit' => 'ppb',
                'class' => 'text-danger',
                'icon' => 'bi-arrow-up'
            ),
            array(
                'label' => __('Data Points', 'methane-monitor'),
                'value' => isset($stats['count']) ? number_format($stats['count']) : '--',
                'unit' => '',
                'class' => 'text-info',
                'icon' => 'bi-grid'
            )
        );
        
        foreach ($stat_items as $item) {
            $output .= '<div class="col-md-3 col-6">';
            $output .= '<div class="card h-100">';
            $output .= '<div class="card-body text-center">';
            $output .= '<i class="' . $item['icon'] . ' fs-2 ' . $item['class'] . ' mb-2"></i>';
            $output .= '<h5 class="card-title ' . $item['class'] . '">' . $item['value'] . ' ' . $item['unit'] . '</h5>';
            $output .= '<p class="card-text small text-muted">' . $item['label'] . '</p>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Generate control panel HTML
     */
    public function generate_control_panel($attributes) {
        $current_year = date('Y');
        $current_month = date('n');
        
        $output = '<div class="methane-controls-panel card">';
        $output .= '<div class="card-header d-flex justify-content-between align-items-center">';
        $output .= '<h6 class="mb-0"><i class="bi bi-sliders"></i> ' . __('Controls', 'methane-monitor') . '</h6>';
        $output .= '<button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-controls">';
        $output .= '<i class="bi bi-chevron-up"></i>';
        $output .= '</button>';
        $output .= '</div>';
        
        $output .= '<div class="card-body" id="controls-content">';
        
        // Time period controls
        $output .= '<div class="mb-3">';
        $output .= '<label class="form-label small text-muted">' . __('Time Period', 'methane-monitor') . '</label>';
        $output .= '<div class="row g-2">';
        $output .= '<div class="col-6">';
        $output .= '<select id="year-select" class="form-select form-select-sm">';
        
        // Generate year options (2014-2023)
        for ($year = 2023; $year >= 2014; $year--) {
            $selected = ($year == $current_year) ? 'selected' : '';
            $output .= '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
        }
        
        $output .= '</select>';
        $output .= '</div>';
        $output .= '<div class="col-6">';
        $output .= '<select id="month-select" class="form-select form-select-sm">';
        
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
        
        foreach ($months as $num => $name) {
            $selected = ($num == $current_month) ? 'selected' : '';
            $output .= '<option value="' . $num . '" ' . $selected . '>' . $name . '</option>';
        }
        
        $output .= '</select>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Navigation controls
        $output .= '<hr class="my-3">';
        $output .= '<div class="mb-3">';
        $output .= '<label class="form-label small text-muted">' . __('Navigation', 'methane-monitor') . '</label>';
        $output .= '<select id="state-nav-select" class="form-select form-select-sm mb-2">';
        $output .= '<option value="">' . __('-- Select State --', 'methane-monitor') . '</option>';
        $output .= '</select>';
        $output .= '<select id="district-nav-select" class="form-select form-select-sm" disabled>';
        $output .= '<option value="">' . __('-- Select District --', 'methane-monitor') . '</option>';
        $output .= '</select>';
        $output .= '</div>';
        
        // Visualization options
        $output .= '<hr class="my-3">';
        $output .= '<div class="mb-3">';
        $output .= '<div class="form-check form-switch mb-2">';
        $output .= '<input class="form-check-input" type="checkbox" id="methane-layer-toggle" checked>';
        $output .= '<label class="form-check-label small" for="methane-layer-toggle">';
        $output .= __('Show Methane Layer', 'methane-monitor');
        $output .= '</label>';
        $output .= '</div>';
        $output .= '<div class="form-check form-switch mb-2">';
        $output .= '<input class="form-check-input" type="checkbox" id="heatmap-toggle">';
        $output .= '<label class="form-check-label small" for="heatmap-toggle">';
        $output .= __('Use Heatmap Visualization', 'methane-monitor');
        $output .= '</label>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Reset button
        $output .= '<button id="reset-view-btn" class="btn btn-primary btn-sm w-100 mb-3">';
        $output .= '<i class="bi bi-arrow-counterclockwise"></i> ' . __('Reset to India', 'methane-monitor');
        $output .= '</button>';
        
        // Current level display
        $output .= '<div class="text-center">';
        $output .= '<small class="text-muted">';
        $output .= __('Current:', 'methane-monitor') . ' <span id="current-level-display" class="fw-bold">India</span>';
        $output .= '</small>';
        $output .= '</div>';
        
        $output .= '</div>'; // End card-body
        $output .= '</div>'; // End card
        
        return $output;
    }
    
    /**
     * Generate analytics tabs
     */
    public function generate_analytics_tabs() {
        $output = '<div class="methane-analytics-container card mt-4">';
        
        // Tab navigation
        $output .= '<div class="card-header p-0">';
        $output .= '<ul class="nav nav-tabs card-header-tabs" id="analytics-tabs" role="tablist">';
        
        $tabs = array(
            array(
                'id' => 'overview',
                'label' => __('Overview', 'methane-monitor'),
                'icon' => 'bi-graph-up',
                'active' => true
            ),
            array(
                'id' => 'timeseries',
                'label' => __('Time Series', 'methane-monitor'),
                'icon' => 'bi-calendar-range',
                'active' => false
            ),
            array(
                'id' => 'clustering',
                'label' => __('Clustering', 'methane-monitor'),
                'icon' => 'bi-grid-3x3-gap',
                'active' => false
            )
        );
        
        foreach ($tabs as $tab) {
            $active_class = $tab['active'] ? 'active' : '';
            $output .= '<li class="nav-item" role="presentation">';
            $output .= '<button class="nav-link ' . $active_class . '" id="' . $tab['id'] . '-tab" ';
            $output .= 'data-bs-toggle="tab" data-bs-target="#' . $tab['id'] . '" type="button" role="tab">';
            $output .= '<i class="' . $tab['icon'] . '"></i> ' . $tab['label'];
            $output .= '</button>';
            $output .= '</li>';
        }
        
        $output .= '</ul>';
        $output .= '</div>';
        
        // Tab content
        $output .= '<div class="card-body">';
        $output .= '<div class="tab-content" id="analytics-tab-content">';
        
        // Overview tab
        $output .= '<div class="tab-pane fade show active" id="overview" role="tabpanel">';
        $output .= '<h5 id="overview-title" class="mb-3">' . __('Methane Emissions Overview', 'methane-monitor') . '</h5>';
        $output .= '<p id="overview-description" class="text-muted mb-4">';
        $output .= __('Explore methane emission patterns across India. Click on regions to drill down for detailed analysis.', 'methane-monitor');
        $output .= '</p>';
        $output .= '<div id="overview-stats"></div>';
        $output .= '<div id="overview-analysis"></div>';
        $output .= '</div>';
        
        // Time series tab
        $output .= '<div class="tab-pane fade" id="timeseries" role="tabpanel">';
        $output .= '<h5 class="mb-3">' . __('Time Series Analysis:', 'methane-monitor');
        $output .= ' <span id="timeseries-location-display" class="text-primary">Location</span></h5>';
        $output .= '<div id="timeseries-chart" style="min-height: 400px;"></div>';
        $output .= '<div id="timeseries-stats" class="mt-3 text-muted small"></div>';
        $output .= '</div>';
        
        // Clustering tab
        $output .= '<div class="tab-pane fade" id="clustering" role="tabpanel">';
        $output .= '<h5 class="mb-3">' . __('District Clustering:', 'methane-monitor');
        $output .= ' <span id="clustering-location-display" class="text-primary">State</span></h5>';
        $output .= '<div id="clustering-info" class="mb-3 text-muted"></div>';
        $output .= '<div id="clustering-table-container" style="max-height: 400px; overflow-y: auto;">';
        $output .= '<table class="table table-sm table-hover" id="clustering-table">';
        $output .= '<thead><tr><th>' . __('District', 'methane-monitor') . '</th>';
        $output .= '<th>' . __('Cluster', 'methane-monitor') . '</th>';
        $output .= '<th>' . __('Avg CH₄ (ppb)', 'methane-monitor') . '</th></tr></thead>';
        $output .= '<tbody></tbody>';
        $output .= '</table>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>'; // End tab-content
        $output .= '</div>'; // End card-body
        $output .= '</div>'; // End card
        
        return $output;
    }
    
    /**
     * Generate error message display
     */
    public function generate_error_message($message, $type = 'warning') {
        $icon_map = array(
            'warning' => 'bi-exclamation-triangle',
            'error' => 'bi-x-circle',
            'info' => 'bi-info-circle'
        );
        
        $icon = isset($icon_map[$type]) ? $icon_map[$type] : 'bi-exclamation-triangle';
        
        return '<div class="alert alert-' . $type . ' d-flex align-items-center" role="alert">' .
               '<i class="' . $icon . ' me-2"></i>' .
               '<div>' . esc_html($message) . '</div>' .
               '</div>';
    }
    
    /**
     * Get user preferences for display settings
     */
    public function get_user_preferences() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return get_user_meta($user_id, 'methane_monitor_preferences', true);
        }
        
        // Default preferences for non-logged-in users
        return array(
            'color_scheme' => 'viridis',
            'default_zoom' => 5,
            'show_controls' => true,
            'enable_animations' => true
        );
    }
    
    /**
     * Save user preferences
     */
    public function save_user_preferences($preferences) {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return update_user_meta($user_id, 'methane_monitor_preferences', $preferences);
        }
        
        return false;
    }
}
