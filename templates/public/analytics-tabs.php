<?php
/**
 * Analytics Tabs Template
 * 
 * Template for the analytics interface with tabs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract variables from $args
$theme = isset($args['theme']) ? $args['theme'] : 'light';
$show_overview = isset($args['show_overview']) ? $args['show_overview'] : true;
$show_timeseries = isset($args['show_timeseries']) ? $args['show_timeseries'] : true;
$show_clustering = isset($args['show_clustering']) ? $args['show_clustering'] : true;
$show_correlation = isset($args['show_correlation']) ? $args['show_correlation'] : false;
?>

<div class="methane-analytics-container card mt-4" data-theme="<?php echo esc_attr($theme); ?>">
    
    <!-- Tab Navigation -->
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs" id="analytics-tabs" role="tablist">
            
            <?php if ($show_overview): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" 
                        id="overview-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#overview" 
                        type="button" 
                        role="tab"
                        aria-controls="overview"
                        aria-selected="true">
                    <i class="bi bi-graph-up me-2"></i>
                    <?php _e('Overview', 'methane-monitor'); ?>
                </button>
            </li>
            <?php endif; ?>
            
            <?php if ($show_timeseries): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="timeseries-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#timeseries" 
                        type="button" 
                        role="tab"
                        aria-controls="timeseries"
                        aria-selected="false"
                        disabled>
                    <i class="bi bi-calendar-range me-2"></i>
                    <?php _e('Time Series', 'methane-monitor'); ?>
                </button>
            </li>
            <?php endif; ?>
            
            <?php if ($show_clustering): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="clustering-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#clustering" 
                        type="button" 
                        role="tab"
                        aria-controls="clustering"
                        aria-selected="false"
                        disabled>
                    <i class="bi bi-grid-3x3-gap me-2"></i>
                    <?php _e('Clustering', 'methane-monitor'); ?>
                </button>
            </li>
            <?php endif; ?>
            
            <?php if ($show_correlation): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" 
                        id="correlation-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#correlation" 
                        type="button" 
                        role="tab"
                        aria-controls="correlation"
                        aria-selected="false"
                        disabled>
                    <i class="bi bi-diagram-3 me-2"></i>
                    <?php _e('Correlation', 'methane-monitor'); ?>
                </button>
            </li>
            <?php endif; ?>
            
        </ul>
    </div>
    
    <!-- Tab Content -->
    <div class="card-body">
        <div class="tab-content" id="analytics-tab-content">
            
            <?php if ($show_overview): ?>
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" 
                 id="overview" 
                 role="tabpanel" 
                 aria-labelledby="overview-tab"
                 tabindex="0">
                
                <div class="row">
                    <div class="col-lg-8">
                        <h5 id="overview-title" class="mb-3">
                            <?php _e('Methane Emissions Overview', 'methane-monitor'); ?>
                        </h5>
                        <p id="overview-description" class="text-muted mb-4">
                            <?php _e('Explore methane emission patterns across India. Click on regions to drill down for detailed analysis.', 'methane-monitor'); ?>
                        </p>
                        
                        <!-- Statistics Cards -->
                        <div id="overview-stats" class="row g-3 mb-4">
                            <!-- Stats will be populated by JavaScript -->
                        </div>
                        
                        <!-- Current Analysis Info -->
                        <div id="current-analysis" class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong><?php _e('Current Analysis:', 'methane-monitor'); ?></strong>
                            <span id="current-analysis-text">
                                <?php _e('Select time period and region to begin analysis', 'methane-monitor'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-lightning me-2"></i>
                                    <?php _e('Quick Actions', 'methane-monitor'); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="show-national-trend">
                                        <i class="bi bi-graph-up me-2"></i>
                                        <?php _e('National Trend', 'methane-monitor'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" id="show-state-rankings">
                                        <i class="bi bi-trophy me-2"></i>
                                        <?php _e('State Rankings', 'methane-monitor'); ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" id="show-hotspots">
                                        <i class="bi bi-geo-alt me-2"></i>
                                        <?php _e('Emission Hotspots', 'methane-monitor'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Quality Indicator -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-shield-check me-2"></i>
                                    <?php _e('Data Quality', 'methane-monitor'); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="data-quality-info">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="badge bg-success me-2">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <small class="text-muted"><?php _e('Data validated', 'methane-monitor'); ?></small>
                                    </div>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 95%" aria-valuenow="95" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">
                                        <span id="data-coverage-percent">95</span>% <?php _e('coverage', 'methane-monitor'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($show_timeseries): ?>
            <!-- Time Series Tab -->
            <div class="tab-pane fade" 
                 id="timeseries" 
                 role="tabpanel" 
                 aria-labelledby="timeseries-tab"
                 tabindex="0">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <?php _e('Time Series Analysis:', 'methane-monitor'); ?>
                        <span id="timeseries-location-display" class="text-primary">
                            <?php _e('Location', 'methane-monitor'); ?>
                        </span>
                    </h5>
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?php _e('Chart Type', 'methane-monitor'); ?>">
                        <input type="radio" class="btn-check" name="chart-type" id="line-chart" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="line-chart">
                            <i class="bi bi-graph-up"></i>
                        </label>
                        
                        <input type="radio" class="btn-check" name="chart-type" id="bar-chart" autocomplete="off">
                        <label class="btn btn-outline-primary" for="bar-chart">
                            <i class="bi bi-bar-chart"></i>
                        </label>
                        
                        <input type="radio" class="btn-check" name="chart-type" id="area-chart" autocomplete="off">
                        <label class="btn btn-outline-primary" for="area-chart">
                            <i class="bi bi-graph-down"></i>
                        </label>
                    </div>
                </div>
                
                <!-- Chart Container -->
                <div id="timeseries-chart" class="mb-4" style="min-height: 400px;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-graph-up display-4 mb-3"></i>
                        <h6><?php _e('Select a district to view time series analysis', 'methane-monitor'); ?></h6>
                        <p class="small"><?php _e('Navigate to district level using the map or controls panel', 'methane-monitor'); ?></p>
                    </div>
                </div>
                
                <!-- Statistics Summary -->
                <div id="timeseries-stats" class="row g-3">
                    <!-- Statistics will be populated by JavaScript -->
                </div>
                
                <!-- Trend Analysis -->
                <div id="trend-analysis" class="mt-4" style="display: none;">
                    <h6 class="mb-3"><?php _e('Trend Analysis', 'methane-monitor'); ?></h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <div id="trend-indicator" class="display-6 mb-2">📈</div>
                                    <h6 id="trend-direction" class="card-title"><?php _e('Trend Direction', 'methane-monitor'); ?></h6>
                                    <p id="trend-description" class="card-text small text-muted"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <div id="seasonal-indicator" class="display-6 mb-2">📅</div>
                                    <h6 class="card-title"><?php _e('Peak Season', 'methane-monitor'); ?></h6>
                                    <p id="peak-season" class="card-text small text-muted"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($show_clustering): ?>
            <!-- Clustering Tab -->
            <div class="tab-pane fade" 
                 id="clustering" 
                 role="tabpanel" 
                 aria-labelledby="clustering-tab"
                 tabindex="0">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <?php _e('District Clustering:', 'methane-monitor'); ?>
                        <span id="clustering-location-display" class="text-primary">
                            <?php _e('State', 'methane-monitor'); ?>
                        </span>
                    </h5>
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?php _e('View Type', 'methane-monitor'); ?>">
                        <input type="radio" class="btn-check" name="cluster-view" id="table-view" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="table-view">
                            <i class="bi bi-table"></i>
                        </label>
                        
                        <input type="radio" class="btn-check" name="cluster-view" id="chart-view" autocomplete="off">
                        <label class="btn btn-outline-primary" for="chart-view">
                            <i class="bi bi-pie-chart"></i>
                        </label>
                    </div>
                </div>
                
                <!-- Clustering Info -->
                <div id="clustering-info" class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <span id="clustering-summary">
                        <?php _e('Select a state to analyze district clustering patterns', 'methane-monitor'); ?>
                    </span>
                </div>
                
                <!-- Clustering Table Container -->
                <div id="clustering-table-container" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover" id="clustering-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th><?php _e('District', 'methane-monitor'); ?></th>
                                <th><?php _e('Cluster', 'methane-monitor'); ?></th>
                                <th class="text-end"><?php _e('Avg CH₄ (ppb)', 'methane-monitor'); ?></th>
                                <th class="text-end"><?php _e('Data Points', 'methane-monitor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-grid-3x3-gap display-6 mb-3"></i>
                                    <div><?php _e('Navigate to state level to view clustering analysis', 'methane-monitor'); ?></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Clustering Chart (hidden by default) -->
                <div id="clustering-chart" style="display: none; min-height: 400px;"></div>
            </div>
            <?php endif; ?>
            
            <?php if ($show_correlation): ?>
            <!-- Correlation Tab -->
            <div class="tab-pane fade" 
                 id="correlation" 
                 role="tabpanel" 
                 aria-labelledby="correlation-tab"
                 tabindex="0">
                
                <h5 class="mb-3">
                    <?php _e('Correlation Analysis:', 'methane-monitor'); ?>
                    <span id="correlation-location-display" class="text-primary">
                        <?php _e('State', 'methane-monitor'); ?>
                    </span>
                </h5>
                
                <!-- Correlation Matrix -->
                <div id="correlation-matrix" class="mb-4" style="min-height: 400px;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-diagram-3 display-4 mb-3"></i>
                        <h6><?php _e('Select a state to view correlation analysis', 'methane-monitor'); ?></h6>
                    </div>
                </div>
                
                <!-- Strong Correlations -->
                <div id="strong-correlations" style="display: none;">
                    <h6 class="mb-3"><?php _e('Strongest Correlations', 'methane-monitor'); ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php _e('District 1', 'methane-monitor'); ?></th>
                                    <th><?php _e('District 2', 'methane-monitor'); ?></th>
                                    <th class="text-end"><?php _e('Correlation', 'methane-monitor'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="correlation-pairs">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching event handlers
    document.querySelectorAll('#analytics-tabs button[data-bs-toggle="tab"]').forEach(function(tabButton) {
        tabButton.addEventListener('shown.bs.tab', function(event) {
            const tabId = event.target.getAttribute('id');
            
            // Trigger tab-specific loading
            const customEvent = new CustomEvent('methane-analytics-tab-shown', {
                detail: { tabId: tabId }
            });
            document.dispatchEvent(customEvent);
        });
    });
    
    // Quick action handlers
    document.getElementById('show-national-trend')?.addEventListener('click', function() {
        // Trigger national trend analysis
        const event = new CustomEvent('methane-show-national-trend');
        document.dispatchEvent(event);
    });
    
    document.getElementById('show-state-rankings')?.addEventListener('click', function() {
        // Trigger state rankings
        const event = new CustomEvent('methane-show-state-rankings');
        document.dispatchEvent(event);
    });
    
    document.getElementById('show-hotspots')?.addEventListener('click', function() {
        // Trigger hotspot analysis
        const event = new CustomEvent('methane-show-hotspots');
        document.dispatchEvent(event);
    });
    
    // Chart type change handlers
    document.querySelectorAll('input[name="chart-type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const chartType = this.id.replace('-chart', '');
                const event = new CustomEvent('methane-chart-type-change', {
                    detail: { chartType: chartType }
                });
                document.dispatchEvent(event);
            }
        });
    });
    
    // Cluster view change handlers
    document.querySelectorAll('input[name="cluster-view"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const viewType = this.id.replace('-view', '');
                
                if (viewType === 'table') {
                    document.getElementById('clustering-table-container').style.display = 'block';
                    document.getElementById('clustering-chart').style.display = 'none';
                } else {
                    document.getElementById('clustering-table-container').style.display = 'none';
                    document.getElementById('clustering-chart').style.display = 'block';
                    
                    // Trigger chart render
                    const event = new CustomEvent('methane-render-clustering-chart');
                    document.dispatchEvent(event);
                }
            }
        });
    });
    
    // Listen for level changes to enable/disable tabs
    document.addEventListener('methane-level-change', function(event) {
        const level = event.detail.level;
        const state = event.detail.state;
        const district = event.detail.district;
        
        // Enable/disable tabs based on current level
        const timeseriesTab = document.getElementById('timeseries-tab');
        const clusteringTab = document.getElementById('clustering-tab');
        const correlationTab = document.getElementById('correlation-tab');
        
        if (timeseriesTab) {
            timeseriesTab.disabled = !(level === 'district' && state && district);
        }
        
        if (clusteringTab) {
            clusteringTab.disabled = !(level === 'state' && state);
        }
        
        if (correlationTab) {
            correlationTab.disabled = !(level === 'state' && state);
        }
    });
});
</script>

<?php
// Add theme-specific styling
?>
<style>
.methane-analytics-container[data-theme="dark"] {
    background: rgba(33, 37, 41, 0.95);
    color: #fff;
}

.methane-analytics-container[data-theme="dark"] .nav-tabs .nav-link {
    color: #adb5bd;
}

.methane-analytics-container[data-theme="dark"] .nav-tabs .nav-link.active {
    background-color: rgba(13, 110, 253, 0.1);
    border-color: transparent;
    color: #0d6efd;
}

.methane-analytics-container[data-theme="dark"] .table {
    --bs-table-bg: transparent;
    color: #fff;
}

.methane-analytics-container[data-theme="dark"] .table-light {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.methane-analytics-container[data-theme="dark"] .alert-info {
    background-color: rgba(13, 202, 240, 0.1);
    border-color: rgba(13, 202, 240, 0.2);
    color: #6edff6;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>