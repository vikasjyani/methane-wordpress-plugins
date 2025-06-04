<?php
/**
 * Map Container Template
 * 
 * Template for the main map visualization container
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract variables from $args
$map_id = isset($args['map_id']) ? $args['map_id'] : 'methane-map';
$height = isset($args['height']) ? $args['height'] : '70vh';
$width = isset($args['width']) ? $args['width'] : '100%';
$show_controls = isset($args['show_controls']) ? $args['show_controls'] : true;
$show_fullscreen = isset($args['show_fullscreen']) ? $args['show_fullscreen'] : true;
$show_export = isset($args['show_export']) ? $args['show_export'] : false;
$theme = isset($args['theme']) ? $args['theme'] : 'light';

$map_style = sprintf(
    'height: %s; min-height: 500px; width: %s;',
    esc_attr($height),
    esc_attr($width)
);
?>

<div class="methane-map-container position-relative mb-4" data-theme="<?php echo esc_attr($theme); ?>">
    <!-- Main Map Element -->
    <div id="<?php echo esc_attr($map_id); ?>" 
         style="<?php echo $map_style; ?>" 
         class="rounded shadow-sm methane-map-element"
         data-methane-map="true">
    </div>
    
    <?php if ($show_controls): ?>
    <!-- Controls Panel -->
    <div class="methane-controls-wrapper">
        <?php 
        do_action('methane_monitor_render_controls_panel', $args);
        ?>
    </div>
    <?php endif; ?>
    
    <!-- Map Legend Container -->
    <div id="methane-map-legend" class="methane-legend"></div>
    
    <?php if ($show_fullscreen): ?>
    <!-- Fullscreen Button -->
    <button id="methane-fullscreen-btn" 
            class="btn btn-outline-secondary btn-sm position-absolute" 
            style="top: 10px; left: 10px; z-index: 1000;" 
            title="<?php _e('Fullscreen', 'methane-monitor'); ?>"
            aria-label="<?php _e('Toggle Fullscreen', 'methane-monitor'); ?>">
        <i class="bi bi-fullscreen"></i>
    </button>
    <?php endif; ?>
    
    <?php if ($show_export): ?>
    <!-- Export Controls -->
    <div class="methane-export-controls position-absolute" 
         style="top: 10px; left: <?php echo $show_fullscreen ? '60px' : '10px'; ?>; z-index: 1000;">
        <div class="btn-group" role="group">
            <button type="button" 
                    class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                    data-bs-toggle="dropdown" 
                    title="<?php _e('Export', 'methane-monitor'); ?>"
                    aria-label="<?php _e('Export Data', 'methane-monitor'); ?>">
                <i class="bi bi-download"></i>
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="#" data-export="png">
                        <i class="bi bi-image me-2"></i><?php _e('Export as PNG', 'methane-monitor'); ?>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-export="pdf">
                        <i class="bi bi-file-pdf me-2"></i><?php _e('Export as PDF', 'methane-monitor'); ?>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" data-export="csv">
                        <i class="bi bi-file-csv me-2"></i><?php _e('Export Data as CSV', 'methane-monitor'); ?>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-export="json">
                        <i class="bi bi-filetype-json me-2"></i><?php _e('Export Data as JSON', 'methane-monitor'); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Map Loading Indicator -->
    <div id="map-loading-indicator" class="position-absolute top-50 start-50 translate-middle" style="display: none; z-index: 999;">
        <div class="d-flex align-items-center text-primary">
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden"><?php _e('Loading...', 'methane-monitor'); ?></span>
            </div>
            <span><?php _e('Loading map data...', 'methane-monitor'); ?></span>
        </div>
    </div>
    
    <!-- Map Error Display -->
    <div id="map-error-display" class="position-absolute top-50 start-50 translate-middle w-75 text-center" style="display: none; z-index: 999;">
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong><?php _e('Map Error:', 'methane-monitor'); ?></strong>
            <span id="map-error-message"></span>
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="retry-map-load">
                    <?php _e('Retry', 'methane-monitor'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Additional map-specific actions
do_action('methane_monitor_after_map_container', $args);
?>

<script type="text/javascript">
// Map container specific JavaScript initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map loading states
    const mapContainer = document.getElementById('<?php echo esc_js($map_id); ?>');
    const loadingIndicator = document.getElementById('map-loading-indicator');
    const errorDisplay = document.getElementById('map-error-display');
    
    // Map loading event handlers
    mapContainer.addEventListener('methane-map-loading', function() {
        loadingIndicator.style.display = 'block';
        errorDisplay.style.display = 'none';
    });
    
    mapContainer.addEventListener('methane-map-loaded', function() {
        loadingIndicator.style.display = 'none';
        errorDisplay.style.display = 'none';
    });
    
    mapContainer.addEventListener('methane-map-error', function(event) {
        loadingIndicator.style.display = 'none';
        const errorMessage = document.getElementById('map-error-message');
        errorMessage.textContent = event.detail.message || '<?php _e('Unknown error occurred', 'methane-monitor'); ?>';
        errorDisplay.style.display = 'block';
    });
    
    // Retry button handler
    document.getElementById('retry-map-load').addEventListener('click', function() {
        errorDisplay.style.display = 'none';
        // Trigger map reload
        const event = new CustomEvent('methane-map-retry');
        mapContainer.dispatchEvent(event);
    });
    
    // Fullscreen handler
    <?php if ($show_fullscreen): ?>
    document.getElementById('methane-fullscreen-btn').addEventListener('click', function() {
        const mapContainer = document.querySelector('.methane-map-container');
        
        if (!document.fullscreenElement) {
            mapContainer.requestFullscreen().then(() => {
                // Invalidate map size after fullscreen
                setTimeout(() => {
                    const event = new CustomEvent('methane-map-resize');
                    mapContainer.dispatchEvent(event);
                }, 100);
            }).catch(err => {
                console.warn('Fullscreen request failed:', err);
            });
        } else {
            document.exitFullscreen();
        }
    });
    
    // Listen for fullscreen changes
    document.addEventListener('fullscreenchange', function() {
        const fullscreenBtn = document.getElementById('methane-fullscreen-btn');
        const icon = fullscreenBtn.querySelector('i');
        
        if (document.fullscreenElement) {
            icon.className = 'bi bi-fullscreen-exit';
            fullscreenBtn.title = '<?php _e('Exit Fullscreen', 'methane-monitor'); ?>';
        } else {
            icon.className = 'bi bi-fullscreen';
            fullscreenBtn.title = '<?php _e('Fullscreen', 'methane-monitor'); ?>';
            
            // Invalidate map size after exiting fullscreen
            setTimeout(() => {
                const event = new CustomEvent('methane-map-resize');
                mapContainer.dispatchEvent(event);
            }, 100);
        }
    });
    <?php endif; ?>
    
    // Export handlers
    <?php if ($show_export): ?>
    document.querySelectorAll('[data-export]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const format = this.getAttribute('data-export');
            
            // Trigger export event
            const exportEvent = new CustomEvent('methane-map-export', {
                detail: { format: format }
            });
            mapContainer.dispatchEvent(exportEvent);
        });
    });
    <?php endif; ?>
});
</script>