<?php
/**
 * Methane Monitor Shortcodes Handler
 * 
 * Handles shortcode registration and rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class Methane_Monitor_Shortcodes {
    
    /**
     * Frontend handler instance
     */
    private $frontend;
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->frontend = new Methane_Monitor_Frontend();
        $this->database = new Methane_Monitor_Database();
        $this->register_shortcodes();
    }
    
    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('methane_monitor', array($this, 'render_methane_monitor'));
        add_shortcode('methane_map', array($this, 'render_methane_map'));
        add_shortcode('methane_stats', array($this, 'render_methane_stats'));
        add_shortcode('methane_ranking', array($this, 'render_methane_ranking'));
    }
    
    /**
     * Main methane monitor shortcode
     * Usage: [methane_monitor height="600px" show_controls="true" theme="dark"]
     */
    public function render_methane_monitor($atts, $content = null) {
        // Default attributes
        $defaults = array(
            'height' => '70vh',
            'min_height' => '500px',
            'width' => '100%',
            'show_controls' => 'true',
            'show_analytics' => 'true',
            'theme' => 'light',
            'initial_level' => 'india',
            'initial_state' => '',
            'initial_district' => '',
            'initial_year' => date('Y'),
            'initial_month' => date('n'),
            'zoom_controls' => 'true',
            'fullscreen' => 'true',
            'enable_export' => 'false',
            'color_scheme' => 'viridis',
            'class' => '',
            'id' => ''
        );
        
        $atts = shortcode_atts($defaults, $atts, 'methane_monitor');
        
        // Generate unique ID if not provided
        if (empty($atts['id'])) {
            $atts['id'] = 'methane-monitor-' . uniqid();
        }
        
        // Start output buffering
        ob_start();
        
        // Loading overlay
        $this->render_loading_overlay();
        
        // Main container
        echo '<div class="methane-monitor-container ' . esc_attr($atts['class']) . '" ';
        echo 'id="' . esc_attr($atts['id']) . '" ';
        echo 'data-theme="' . esc_attr($atts['theme']) . '" ';
        echo 'data-initial-level="' . esc_attr($atts['initial_level']) . '" ';
        echo 'data-initial-state="' . esc_attr($atts['initial_state']) . '" ';
        echo 'data-initial-district="' . esc_attr($atts['initial_district']) . '" ';
        echo 'data-initial-year="' . esc_attr($atts['initial_year']) . '" ';
        echo 'data-initial-month="' . esc_attr($atts['initial_month']) . '" ';
        echo 'data-color-scheme="' . esc_attr($atts['color_scheme']) . '">';
        
        // Header section
        $this->render_header($atts);
        
        // Breadcrumb navigation
        echo '<div id="methane-breadcrumb-container">';
        echo $this->frontend->generate_breadcrumb('india');
        echo '</div>';
        
        // Map container with controls
        $this->render_map_section($atts);
        
        // Analytics section
        if ($atts['show_analytics'] === 'true') {
            echo '<div id="methane-analytics-container">';
            echo $this->frontend->generate_analytics_tabs();
            echo '</div>';
        }
        
        // Footer
        $this->render_footer($atts);
        
        echo '</div>'; // End main container
        
        // Initialize JavaScript
        $this->render_initialization_script($atts);
        
        return ob_get_clean();
    }
    
    /**
     * Render loading overlay
     */
    private function render_loading_overlay() {
        ?>
        <div class="methane-loading-overlay" id="methane-loading-overlay" style="display: none;">
            <div class="methane-loading-content">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden"><?php _e('Loading...', 'methane-monitor'); ?></span>
                </div>
                <h5 id="methane-loading-text"><?php _e('Loading methane data...', 'methane-monitor'); ?></h5>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render header section
     */
    private function render_header($atts) {
        ?>
        <header class="methane-header text-center mb-4">
            <h1 class="methane-title">
                <img src="<?php echo METHANE_MONITOR_PLUGIN_URL; ?>assets/images/logo.png" 
                     alt="<?php _e('Vasudha Foundation', 'methane-monitor'); ?>" 
                     class="methane-logo me-2"
                     onerror="this.style.display='none'">
                <?php _e('India Methane Emissions Monitor', 'methane-monitor'); ?>
            </h1>
            <p class="methane-subtitle text-muted">
                <?php _e('Interactive Environmental Monitoring Platform (2014-2023)', 'methane-monitor'); ?>
            </p>
        </header>
        <?php
    }
    
    /**
     * Render map section with controls
     */
    private function render_map_section($atts) {
        $map_style = sprintf(
            'height: %s; min-height: %s; width: %s;',
            esc_attr($atts['height']),
            esc_attr($atts['min_height']),
            esc_attr($atts['width'])
        );
        
        ?>
        <div class="methane-map-container position-relative mb-4">
            <div id="methane-map" style="<?php echo $map_style; ?>" class="rounded shadow-sm"></div>
            
            <?php if ($atts['show_controls'] === 'true'): ?>
            <div class="methane-controls-wrapper">
                <?php echo $this->frontend->generate_control_panel($atts); ?>
            </div>
            <?php endif; ?>
            
            <!-- Map legend (will be populated by JavaScript) -->
            <div id="methane-map-legend" class="methane-legend"></div>
            
            <?php if ($atts['fullscreen'] === 'true'): ?>
            <button id="methane-fullscreen-btn" class="btn btn-outline-secondary btn-sm position-absolute" 
                    style="top: 10px; left: 10px; z-index: 1000;" title="<?php _e('Fullscreen', 'methane-monitor'); ?>">
                <i class="bi bi-fullscreen"></i>
            </button>
            <?php endif; ?>
            
            <?php if ($atts['enable_export'] === 'true'): ?>
            <div class="methane-export-controls position-absolute" style="top: 10px; left: 60px; z-index: 1000;">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                            data-bs-toggle="dropdown" title="<?php _e('Export', 'methane-monitor'); ?>">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-export="png">
                            <i class="bi bi-image"></i> <?php _e('Export as PNG', 'methane-monitor'); ?>
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-export="pdf">
                            <i class="bi bi-file-pdf"></i> <?php _e('Export as PDF', 'methane-monitor'); ?>
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-export="csv">
                            <i class="bi bi-file-csv"></i> <?php _e('Export Data as CSV', 'methane-monitor'); ?>
                        </a></li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render footer
     */
    private function render_footer($atts) {
        ?>
        <footer class="methane-footer text-center mt-4 pt-3 border-top">
            <p class="text-muted small mb-1">
                <?php _e('Data visualization powered by Vasudha Foundation', 'methane-monitor'); ?>
            </p>
            <p class="text-muted small mb-0">
                <?php 
                printf(
                    __('Last updated: %s', 'methane-monitor'),
                    '<span id="last-updated-time">' . current_time('F j, Y') . '</span>'
                );
                ?>
            </p>
        </footer>
        <?php
    }
    
    /**
     * Render initialization script
     */
    private function render_initialization_script($atts) {
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof MethaneMonitorApp !== 'undefined') {
                const config = {
                    containerId: '<?php echo esc_js($atts['id']); ?>',
                    mapId: 'methane-map',
                    initialLevel: '<?php echo esc_js($atts['initial_level']); ?>',
                    initialState: '<?php echo esc_js($atts['initial_state']); ?>',
                    initialDistrict: '<?php echo esc_js($atts['initial_district']); ?>',
                    initialYear: parseInt('<?php echo esc_js($atts['initial_year']); ?>'),
                    initialMonth: parseInt('<?php echo esc_js($atts['initial_month']); ?>'),
                    theme: '<?php echo esc_js($atts['theme']); ?>',
                    colorScheme: '<?php echo esc_js($atts['color_scheme']); ?>',
                    showControls: <?php echo $atts['show_controls'] === 'true' ? 'true' : 'false'; ?>,
                    showAnalytics: <?php echo $atts['show_analytics'] === 'true' ? 'true' : 'false'; ?>,
                    enableExport: <?php echo $atts['enable_export'] === 'true' ? 'true' : 'false'; ?>,
                    zoomControls: <?php echo $atts['zoom_controls'] === 'true' ? 'true' : 'false'; ?>
                };
                
                // Initialize the application
                window.methaneMonitorInstance = new MethaneMonitorApp(config);
            } else {
                console.error('MethaneMonitorApp not loaded');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Simple map shortcode
     * Usage: [methane_map state="RAJASTHAN" height="400px"]
     */
    public function render_methane_map($atts, $content = null) {
        $defaults = array(
            'height' => '400px',
            'width' => '100%',
            'state' => '',
            'district' => '',
            'year' => date('Y'),
            'month' => date('n'),
            'show_legend' => 'true',
            'interactive' => 'true',
            'zoom' => '5',
            'center_lat' => '20.5937',
            'center_lng' => '78.9629'
        );
        
        $atts = shortcode_atts($defaults, $atts, 'methane_map');
        $map_id = 'methane-simple-map-' . uniqid();
        
        ob_start();
        ?>
        <div class="methane-simple-map-container">
            <div id="<?php echo esc_attr($map_id); ?>" 
                 style="height: <?php echo esc_attr($atts['height']); ?>; width: <?php echo esc_attr($atts['width']); ?>;"
                 class="methane-simple-map rounded shadow-sm"></div>
        </div>
        
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof L !== 'undefined') {
                const map = L.map('<?php echo esc_js($map_id); ?>', {
                    center: [<?php echo floatval($atts['center_lat']); ?>, <?php echo floatval($atts['center_lng']); ?>],
                    zoom: <?php echo intval($atts['zoom']); ?>,
                    scrollWheelZoom: <?php echo $atts['interactive'] === 'true' ? 'true' : 'false'; ?>
                });
                
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles © Esri'
                }).addTo(map);
                
                // Load data based on parameters
                const apiUrl = methaneMonitor.restUrl + 
                    <?php if (!empty($atts['state']) && !empty($atts['district'])): ?>
                    'district/<?php echo urlencode($atts['state']); ?>/<?php echo urlencode($atts['district']); ?>/<?php echo intval($atts['year']); ?>/<?php echo intval($atts['month']); ?>';
                    <?php elseif (!empty($atts['state'])): ?>
                    'state/<?php echo urlencode($atts['state']); ?>/<?php echo intval($atts['year']); ?>/<?php echo intval($atts['month']); ?>';
                    <?php else: ?>
                    'india/<?php echo intval($atts['year']); ?>/<?php echo intval($atts['month']); ?>';
                    <?php endif; ?>
                
                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        // Handle data visualization
                        console.log('Map data loaded', data);
                    })
                    .catch(error => {
                        console.error('Error loading map data:', error);
                    });
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Statistics display shortcode
     * Usage: [methane_stats type="india" year="2023" month="6" layout="cards"]
     */
    public function render_methane_stats($atts, $content = null) {
        $defaults = array(
            'type' => 'india',
            'state' => '',
            'district' => '',
            'year' => date('Y'),
            'month' => date('n'),
            'layout' => 'cards',
            'show_trend' => 'false',
            'limit' => '5'
        );
        
        $atts = shortcode_atts($defaults, $atts, 'methane_stats');
        
        // Get data based on type
        try {
            switch ($atts['type']) {
                case 'state':
                    if (empty($atts['state'])) {
                        return $this->frontend->generate_error_message(__('State parameter is required for state statistics', 'methane-monitor'));
                    }
                    $data = $this->database->get_state_data($atts['state'], $atts['year'], $atts['month']);
                    break;
                    
                case 'district':
                    if (empty($atts['state']) || empty($atts['district'])) {
                        return $this->frontend->generate_error_message(__('State and district parameters are required for district statistics', 'methane-monitor'));
                    }
                    $data = $this->database->get_district_data($atts['state'], $atts['district'], $atts['year'], $atts['month']);
                    break;
                    
                default:
                    $data = $this->database->get_india_data($atts['year'], $atts['month']);
            }
            
            if (empty($data)) {
                return $this->frontend->generate_error_message(__('No data available for the specified parameters', 'methane-monitor'));
            }
            
            // Calculate statistics
            if ($atts['type'] === 'district') {
                $emissions = array_column($data, 'emission_value');
            } else {
                $emissions = array_filter(array_column($data, 'avg_emission'), function($val) {
                    return $val !== null && $val > 0;
                });
            }
            
            if (empty($emissions)) {
                return $this->frontend->generate_error_message(__('No valid emission data found', 'methane-monitor'));
            }
            
            $stats = array(
                'mean' => array_sum($emissions) / count($emissions),
                'min' => min($emissions),
                'max' => max($emissions),
                'count' => count($emissions)
            );
            
            // Sort data and calculate median
            sort($emissions);
            $count = count($emissions);
            $stats['median'] = $count % 2 === 0 
                ? ($emissions[$count/2 - 1] + $emissions[$count/2]) / 2 
                : $emissions[floor($count/2)];
            
            // Generate output based on layout
            switch ($atts['layout']) {
                case 'table':
                    return $this->render_stats_table($stats, $atts);
                    
                case 'inline':
                    return $this->render_stats_inline($stats, $atts);
                    
                default:
                    return $this->frontend->generate_stats_display($stats);
            }
            
        } catch (Exception $e) {
            return $this->frontend->generate_error_message($e->getMessage(), 'error');
        }
    }
    
    /**
     * Ranking shortcode
     * Usage: [methane_ranking type="states" year="2023" month="6" limit="10"]
     */
    public function render_methane_ranking($atts, $content = null) {
        $defaults = array(
            'type' => 'states',
            'year' => date('Y'),
            'month' => date('n'),
            'limit' => '10',
            'order' => 'desc',
            'show_values' => 'true',
            'style' => 'table'
        );
        
        $atts = shortcode_atts($defaults, $atts, 'methane_ranking');
        
        try {
            if ($atts['type'] === 'states') {
                $data = $this->database->get_india_data($atts['year'], $atts['month']);
                $name_field = 'state_name';
                $value_field = 'avg_emission';
            } else {
                return $this->frontend->generate_error_message(__('Only states ranking is currently supported', 'methane-monitor'));
            }
            
            if (empty($data)) {
                return $this->frontend->generate_error_message(__('No ranking data available', 'methane-monitor'));
            }
            
            // Filter and sort data
            $valid_data = array_filter($data, function($item) use ($value_field) {
                return isset($item[$value_field]) && $item[$value_field] > 0;
            });
            
            $sort_order = $atts['order'] === 'asc' ? SORT_ASC : SORT_DESC;
            $values = array_column($valid_data, $value_field);
            array_multisort($values, $sort_order, $valid_data);
            
            $limited_data = array_slice($valid_data, 0, intval($atts['limit']));
            
            return $this->render_ranking_table($limited_data, $name_field, $value_field, $atts);
            
        } catch (Exception $e) {
            return $this->frontend->generate_error_message($e->getMessage(), 'error');
        }
    }
    
    /**
     * Render statistics as table
     */
    private function render_stats_table($stats, $atts) {
        ob_start();
        ?>
        <div class="methane-stats-table">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php _e('Metric', 'methane-monitor'); ?></th>
                        <th><?php _e('Value (ppb)', 'methane-monitor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Average', 'methane-monitor'); ?></td>
                        <td><?php echo round($stats['mean'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Median', 'methane-monitor'); ?></td>
                        <td><?php echo round($stats['median'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Minimum', 'methane-monitor'); ?></td>
                        <td><?php echo round($stats['min'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Maximum', 'methane-monitor'); ?></td>
                        <td><?php echo round($stats['max'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Data Points', 'methane-monitor'); ?></td>
                        <td><?php echo number_format($stats['count']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render statistics inline
     */
    private function render_stats_inline($stats, $atts) {
        return sprintf(
            __('Average: %s ppb | Range: %s - %s ppb | Data points: %s', 'methane-monitor'),
            '<strong>' . round($stats['mean'], 2) . '</strong>',
            round($stats['min'], 2),
            round($stats['max'], 2),
            number_format($stats['count'])
        );
    }
    
    /**
     * Render ranking table
     */
    private function render_ranking_table($data, $name_field, $value_field, $atts) {
        ob_start();
        ?>
        <div class="methane-ranking-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?php _e('Rank', 'methane-monitor'); ?></th>
                        <th><?php _e('Name', 'methane-monitor'); ?></th>
                        <?php if ($atts['show_values'] === 'true'): ?>
                        <th><?php _e('CH₄ (ppb)', 'methane-monitor'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $index => $item): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                        </td>
                        <td><?php echo esc_html($item[$name_field]); ?></td>
                        <?php if ($atts['show_values'] === 'true'): ?>
                        <td>
                            <strong><?php echo round($item[$value_field], 2); ?></strong>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
