<?php
/**
 * Controls Panel Template
 * 
 * Template for the map controls panel
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract variables from $args
$current_year = date('Y');
$current_month = date('n');
$theme = isset($args['theme']) ? $args['theme'] : 'light';
$color_scheme = isset($args['color_scheme']) ? $args['color_scheme'] : 'viridis';
$show_advanced_controls = isset($args['show_advanced_controls']) ? $args['show_advanced_controls'] : true;
?>

<div class="methane-controls-panel card" data-theme="<?php echo esc_attr($theme); ?>">
    <!-- Controls Header -->
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="bi bi-sliders me-2"></i>
            <?php _e('Controls', 'methane-monitor'); ?>
        </h6>
        <button type="button" 
                class="btn btn-sm btn-outline-secondary" 
                id="toggle-controls"
                aria-label="<?php _e('Toggle Controls', 'methane-monitor'); ?>">
            <i class="bi bi-chevron-up"></i>
        </button>
    </div>
    
    <!-- Controls Content -->
    <div class="card-body" id="controls-content">
        
        <!-- Time Period Controls -->
        <div class="mb-3">
            <label class="form-label small text-muted">
                <i class="bi bi-calendar-range me-1"></i>
                <?php _e('Time Period', 'methane-monitor'); ?>
            </label>
            <div class="row g-2">
                <div class="col-6">
                    <select id="year-select" class="form-select form-select-sm" aria-label="<?php _e('Select Year', 'methane-monitor'); ?>">
                        <?php for ($year = 2023; $year >= 2014; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php selected($year, $current_year); ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select id="month-select" class="form-select form-select-sm" aria-label="<?php _e('Select Month', 'methane-monitor'); ?>">
                        <?php
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
                        
                        foreach ($months as $num => $name):
                        ?>
                            <option value="<?php echo $num; ?>" <?php selected($num, $current_month); ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Navigation Controls -->
        <hr class="my-3">
        <div class="mb-3">
            <label class="form-label small text-muted">
                <i class="bi bi-geo-alt me-1"></i>
                <?php _e('Navigation', 'methane-monitor'); ?>
            </label>
            <select id="state-nav-select" 
                    class="form-select form-select-sm mb-2" 
                    aria-label="<?php _e('Select State for Navigation', 'methane-monitor'); ?>">
                <option value=""><?php _e('-- Select State --', 'methane-monitor'); ?></option>
            </select>
            <select id="district-nav-select" 
                    class="form-select form-select-sm" 
                    disabled
                    aria-label="<?php _e('Select District for Navigation', 'methane-monitor'); ?>">
                <option value=""><?php _e('-- Select District --', 'methane-monitor'); ?></option>
            </select>
        </div>
        
        <!-- Visualization Options -->
        <?php if ($show_advanced_controls): ?>
        <hr class="my-3">
        <div class="mb-3">
            <label class="form-label small text-muted">
                <i class="bi bi-eye me-1"></i>
                <?php _e('Visualization', 'methane-monitor'); ?>
            </label>
            
            <!-- Layer Toggle -->
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" 
                       type="checkbox" 
                       id="methane-layer-toggle" 
                       checked
                       aria-describedby="methane-layer-help">
                <label class="form-check-label small" for="methane-layer-toggle">
                    <?php _e('Show Methane Layer', 'methane-monitor'); ?>
                </label>
            </div>
            <div id="methane-layer-help" class="form-text small">
                <?php _e('Toggle the methane emissions data layer on/off', 'methane-monitor'); ?>
            </div>
            
            <!-- Heatmap Toggle -->
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" 
                       type="checkbox" 
                       id="heatmap-toggle"
                       aria-describedby="heatmap-help">
                <label class="form-check-label small" for="heatmap-toggle">
                    <?php _e('Use Heatmap Visualization', 'methane-monitor'); ?>
                </label>
            </div>
            <div id="heatmap-help" class="form-text small">
                <?php _e('Switch between choropleth and heatmap visualization', 'methane-monitor'); ?>
            </div>
            
            <!-- Color Scheme Selector -->
            <div class="mt-2">
                <label for="color-scheme-select" class="form-label small text-muted">
                    <?php _e('Color Scheme', 'methane-monitor'); ?>
                </label>
                <select id="color-scheme-select" class="form-select form-select-sm">
                    <option value="viridis" <?php selected($color_scheme, 'viridis'); ?>>
                        <?php _e('Viridis', 'methane-monitor'); ?>
                    </option>
                    <option value="plasma" <?php selected($color_scheme, 'plasma'); ?>>
                        <?php _e('Plasma', 'methane-monitor'); ?>
                    </option>
                    <option value="inferno" <?php selected($color_scheme, 'inferno'); ?>>
                        <?php _e('Inferno', 'methane-monitor'); ?>
                    </option>
                </select>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <hr class="my-3">
        <div class="d-grid gap-2">
            <button id="reset-view-btn" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-2"></i>
                <?php _e('Reset to India', 'methane-monitor'); ?>
            </button>
            
            <?php if ($show_advanced_controls): ?>
            <button id="center-map-btn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-crosshair me-2"></i>
                <?php _e('Center Map', 'methane-monitor'); ?>
            </button>
            
            <button id="refresh-data-btn" class="btn btn-outline-success btn-sm">
                <i class="bi bi-arrow-clockwise me-2"></i>
                <?php _e('Refresh Data', 'methane-monitor'); ?>
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Current Status Display -->
        <hr class="my-3">
        <div class="text-center">
            <small class="text-muted">
                <?php _e('Current View:', 'methane-monitor'); ?>
                <br>
                <span id="current-level-display" class="fw-bold text-primary">
                    <?php _e('India', 'methane-monitor'); ?>
                </span>
            </small>
        </div>
        
        <!-- Quick Statistics (if available) -->
        <div id="quick-stats" class="mt-3" style="display: none;">
            <hr class="my-2">
            <div class="small text-muted">
                <div class="d-flex justify-content-between mb-1">
                    <span><?php _e('Avg:', 'methane-monitor'); ?></span>
                    <span id="quick-avg" class="fw-bold">--</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span><?php _e('Range:', 'methane-monitor'); ?></span>
                    <span id="quick-range" class="fw-bold">--</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span><?php _e('Points:', 'methane-monitor'); ?></span>
                    <span id="quick-points" class="fw-bold">--</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Toggle controls panel
    document.getElementById('toggle-controls').addEventListener('click', function() {
        const content = document.getElementById('controls-content');
        const icon = this.querySelector('i');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.className = 'bi bi-chevron-up';
            this.setAttribute('aria-expanded', 'true');
        } else {
            content.style.display = 'none';
            icon.className = 'bi bi-chevron-down';
            this.setAttribute('aria-expanded', 'false');
        }
    });
    
    // Color scheme change handler
    <?php if ($show_advanced_controls): ?>
    document.getElementById('color-scheme-select').addEventListener('change', function() {
        const scheme = this.value;
        
        // Trigger color scheme change event
        const event = new CustomEvent('methane-color-scheme-change', {
            detail: { scheme: scheme }
        });
        document.dispatchEvent(event);
    });
    
    // Center map button
    document.getElementById('center-map-btn').addEventListener('click', function() {
        const event = new CustomEvent('methane-map-center');
        document.dispatchEvent(event);
    });
    
    // Refresh data button
    document.getElementById('refresh-data-btn').addEventListener('click', function() {
        const btn = this;
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2 spin"></i><?php _e('Refreshing...', 'methane-monitor'); ?>';
        btn.disabled = true;
        
        const event = new CustomEvent('methane-data-refresh');
        document.dispatchEvent(event);
        
        // Re-enable button after 3 seconds
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 3000);
    });
    <?php endif; ?>
    
    // Listen for data updates to show quick stats
    document.addEventListener('methane-data-loaded', function(event) {
        const stats = event.detail.stats;
        if (stats) {
            document.getElementById('quick-avg').textContent = 
                stats.mean ? parseFloat(stats.mean).toFixed(1) + ' ppb' : '--';
            document.getElementById('quick-range').textContent = 
                (stats.min && stats.max) ? 
                parseFloat(stats.min).toFixed(1) + ' - ' + parseFloat(stats.max).toFixed(1) + ' ppb' : '--';
            document.getElementById('quick-points').textContent = 
                stats.count ? new Intl.NumberFormat().format(stats.count) : '--';
            document.getElementById('quick-stats').style.display = 'block';
        }
    });
    
    // Add loading states to controls
    document.addEventListener('methane-map-loading', function() {
        document.querySelectorAll('.methane-controls-panel button').forEach(btn => {
            if (btn.id !== 'toggle-controls') {
                btn.disabled = true;
            }
        });
    });
    
    document.addEventListener('methane-map-loaded', function() {
        document.querySelectorAll('.methane-controls-panel button').forEach(btn => {
            btn.disabled = false;
        });
    });
});
</script>

<?php
// Add CSS for spin animation
?>
<style>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Enhanced controls styling */
.methane-controls-panel {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.methane-controls-panel[data-theme="dark"] {
    background: rgba(33, 37, 41, 0.95);
    color: #fff;
}

.methane-controls-panel[data-theme="dark"] .card-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.methane-controls-panel[data-theme="dark"] .form-control,
.methane-controls-panel[data-theme="dark"] .form-select {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.methane-controls-panel[data-theme="dark"] .form-control:focus,
.methane-controls-panel[data-theme="dark"] .form-select:focus {
    background-color: rgba(255, 255, 255, 0.15);
    border-color: var(--methane-primary);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}
</style>