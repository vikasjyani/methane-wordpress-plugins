<?php
/**
 * Admin Dashboard Template
 * 
 * Template for the main admin dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard statistics
$stats = isset($args['stats']) ? $args['stats'] : array();
$system_status = isset($args['system_status']) ? $args['system_status'] : array();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Methane Monitor Dashboard', 'methane-monitor'); ?>
    </h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Settings saved.', 'methane-monitor'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="methane-dashboard">
        
        <!-- Statistics Cards -->
        <div class="methane-stats-grid">
            <div class="methane-stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3 id="stat-total-emissions"><?php echo number_format($stats['total_emissions'] ?? 0); ?></h3>
                    <p><?php _e('Total Data Points', 'methane-monitor'); ?></p>
                </div>
            </div>
            
            <div class="methane-stat-card">
                <div class="stat-icon">🏛️</div>
                <div class="stat-content">
                    <h3 id="stat-total-states"><?php echo $stats['total_states'] ?? 0; ?></h3>
                    <p><?php _e('States Covered', 'methane-monitor'); ?></p>
                </div>
            </div>
            
            <div class="methane-stat-card">
                <div class="stat-icon">📍</div>
                <div class="stat-content">
                    <h3 id="stat-total-districts"><?php echo $stats['total_districts'] ?? 0; ?></h3>
                    <p><?php _e('Districts Covered', 'methane-monitor'); ?></p>
                </div>
            </div>
            
            <div class="methane-stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <h3 id="stat-latest-date"><?php echo esc_html($stats['date_range'] ?? __('No data', 'methane-monitor')); ?></h3>
                    <p><?php _e('Data Coverage', 'methane-monitor'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="dashboard-widgets-wrap">
            <div class="dashboard-widgets">
                
                <!-- Left Column -->
                <div class="postbox-container" style="width: 65%;">
                    
                    <!-- Quick Actions -->
                    <div class="methane-quick-actions">
                        <h2><?php _e('Quick Actions', 'methane-monitor'); ?></h2>
                        <div class="action-buttons">
                            <a href="<?php echo admin_url('admin.php?page=methane-monitor-data'); ?>" class="button button-primary">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Upload Data', 'methane-monitor'); ?>
                            </a>
                            <a href="#" id="clear-cache-btn" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Clear Cache', 'methane-monitor'); ?>
                            </a>
                            <a href="#" id="recalculate-aggregations-btn" class="button button-secondary">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <?php _e('Recalculate Aggregations', 'methane-monitor'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings'); ?>" class="button">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php _e('Settings', 'methane-monitor'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="methane-recent-activity">
                        <h2><?php _e('Recent Activity', 'methane-monitor'); ?></h2>
                        <div class="activity-list" id="activity-list">
                            <?php 
                            $recent_activities = $this->get_recent_activities();
                            if (!empty($recent_activities)):
                                foreach ($recent_activities as $activity):
                            ?>
                            <div class="activity-item">
                                <strong><?php echo esc_html($activity['title']); ?></strong>
                                <p><?php echo esc_html($activity['description']); ?></p>
                                <small><?php echo esc_html($activity['time']); ?></small>
                            </div>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <div class="activity-item">
                                <strong><?php _e('System Status', 'methane-monitor'); ?></strong>
                                <p><?php _e('Plugin is running normally. All services are operational.', 'methane-monitor'); ?></p>
                                <small><?php echo current_time('F j, Y g:i A'); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="activity-footer">
                            <button type="button" class="button" id="refresh-activity">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Refresh', 'methane-monitor'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Data Overview Chart -->
                    <div class="methane-data-overview">
                        <h2><?php _e('Data Overview', 'methane-monitor'); ?></h2>
                        <div id="overview-chart" style="height: 300px; width: 100%;"></div>
                        <div class="chart-controls">
                            <label for="chart-timeframe"><?php _e('Time Period:', 'methane-monitor'); ?></label>
                            <select id="chart-timeframe" class="regular-text">
                                <option value="12"><?php _e('Last 12 months', 'methane-monitor'); ?></option>
                                <option value="24"><?php _e('Last 24 months', 'methane-monitor'); ?></option>
                                <option value="all" selected><?php _e('All time', 'methane-monitor'); ?></option>
                            </select>
                            <button type="button" class="button" id="update-chart">
                                <?php _e('Update Chart', 'methane-monitor'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="postbox-container" style="width: 35%;">
                    
                    <!-- System Status -->
                    <div class="methane-system-status">
                        <h2><?php _e('System Status', 'methane-monitor'); ?></h2>
                        <div class="status-items">
                            
                            <div class="status-item">
                                <span class="status-label"><?php _e('Database Connection', 'methane-monitor'); ?></span>
                                <span class="status-database status-good">
                                    <?php _e('Connected', 'methane-monitor'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php _e('Cache System', 'methane-monitor'); ?></span>
                                <span class="status-cache <?php echo wp_using_ext_object_cache() ? 'status-good' : 'status-warning'; ?>">
                                    <?php echo wp_using_ext_object_cache() ? __('External cache active', 'methane-monitor') : __('Using database cache', 'methane-monitor'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php _e('File Upload Directory', 'methane-monitor'); ?></span>
                                <span class="status-files <?php echo is_writable(wp_upload_dir()['basedir']) ? 'status-good' : 'status-error'; ?>">
                                    <?php echo is_writable(wp_upload_dir()['basedir']) ? __('Writable', 'methane-monitor') : __('Not writable', 'methane-monitor'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php _e('Memory Usage', 'methane-monitor'); ?></span>
                                <span class="status-memory status-good">
                                    <?php echo size_format(memory_get_usage(true)) . ' / ' . ini_get('memory_limit'); ?>
                                </span>
                            </div>
                            
                            <div class="status-item">
                                <span class="status-label"><?php _e('Plugin Version', 'methane-monitor'); ?></span>
                                <span class="status-version status-good">
                                    <?php echo METHANE_MONITOR_VERSION; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="status-footer">
                            <button type="button" class="button" id="run-diagnostics">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e('Run Diagnostics', 'methane-monitor'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="methane-quick-stats">
                        <h2><?php _e('Today\'s Summary', 'methane-monitor'); ?></h2>
                        <div class="quick-stat-items">
                            <div class="quick-stat-item">
                                <span class="stat-number" id="uploads-today">0</span>
                                <span class="stat-label"><?php _e('Uploads', 'methane-monitor'); ?></span>
                            </div>
                            <div class="quick-stat-item">
                                <span class="stat-number" id="api-calls-today">0</span>
                                <span class="stat-label"><?php _e('API Calls', 'methane-monitor'); ?></span>
                            </div>
                            <div class="quick-stat-item">
                                <span class="stat-number" id="cache-hits-today">0</span>
                                <span class="stat-label"><?php _e('Cache Hits', 'methane-monitor'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Help & Documentation -->
                    <div class="methane-help-section">
                        <h2><?php _e('Help & Documentation', 'methane-monitor'); ?></h2>
                        <div class="help-items">
                            <div class="help-item">
                                <h4>
                                    <span class="dashicons dashicons-book"></span>
                                    <a href="#" target="_blank"><?php _e('User Guide', 'methane-monitor'); ?></a>
                                </h4>
                                <p><?php _e('Complete guide for using the plugin', 'methane-monitor'); ?></p>
                            </div>
                            
                            <div class="help-item">
                                <h4>
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <a href="#" target="_blank"><?php _e('Video Tutorials', 'methane-monitor'); ?></a>
                                </h4>
                                <p><?php _e('Step-by-step video tutorials', 'methane-monitor'); ?></p>
                            </div>
                            
                            <div class="help-item">
                                <h4>
                                    <span class="dashicons dashicons-sos"></span>
                                    <a href="#" target="_blank"><?php _e('Support Forum', 'methane-monitor'); ?></a>
                                </h4>
                                <p><?php _e('Get help from the community', 'methane-monitor'); ?></p>
                            </div>
                            
                            <div class="help-item">
                                <h4>
                                    <span class="dashicons dashicons-admin-tools"></span>
                                    <a href="<?php echo admin_url('admin.php?page=methane-monitor-settings&tab=troubleshooting'); ?>"><?php _e('Troubleshooting', 'methane-monitor'); ?></a>
                                </h4>
                                <p><?php _e('Common issues and solutions', 'methane-monitor'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- News & Updates -->
                    <div class="methane-news-section">
                        <h2><?php _e('News & Updates', 'methane-monitor'); ?></h2>
                        <div id="plugin-news" class="news-items">
                            <div class="news-item">
                                <h4><?php _e('Welcome to Methane Monitor!', 'methane-monitor'); ?></h4>
                                <p><?php _e('Thank you for installing Methane Monitor. Check out our getting started guide to begin monitoring emissions data.', 'methane-monitor'); ?></p>
                                <small><?php echo current_time('F j, Y'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Dashboard -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Initialize dashboard
    initializeDashboard();
    
    // Clear cache button
    $('#clear-cache-btn').click(function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to clear the cache?', 'methane-monitor'); ?>')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Clearing...', 'methane-monitor'); ?>');
        
        $.post(ajaxurl, {
            action: 'methane_clear_cache',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                showNotice('<?php _e('Cache cleared successfully', 'methane-monitor'); ?>', 'success');
                refreshStatistics();
            } else {
                showNotice('<?php _e('Error clearing cache', 'methane-monitor'); ?>', 'error');
            }
        })
        .fail(function() {
            showNotice('<?php _e('Error clearing cache', 'methane-monitor'); ?>', 'error');
        })
        .always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    });
    
    // Recalculate aggregations button
    $('#recalculate-aggregations-btn').click(function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('This may take several minutes. Continue?', 'methane-monitor'); ?>')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Processing...', 'methane-monitor'); ?>');
        
        $.post(ajaxurl, {
            action: 'methane_process_data',
            action_type: 'calculate_aggregations',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                showNotice('<?php _e('Aggregations recalculated successfully', 'methane-monitor'); ?>', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotice('<?php _e('Error recalculating aggregations', 'methane-monitor'); ?>', 'error');
            }
        })
        .fail(function() {
            showNotice('<?php _e('Error recalculating aggregations', 'methane-monitor'); ?>', 'error');
        })
        .always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    });
    
    // Refresh activity button
    $('#refresh-activity').click(function() {
        refreshActivity();
    });
    
    // Update chart button
    $('#update-chart').click(function() {
        updateOverviewChart();
    });
    
    // Run diagnostics button
    $('#run-diagnostics').click(function() {
        runSystemDiagnostics();
    });
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        refreshStatistics();
        refreshActivity();
    }, 300000);
    
    // Initialize overview chart
    if (typeof Plotly !== 'undefined') {
        loadOverviewChart();
    }
    
    function initializeDashboard() {
        // Load initial data
        refreshStatistics();
        loadTodayStats();
        
        // Check for updates
        checkForUpdates();
    }
    
    function refreshStatistics() {
        $.post(ajaxurl, {
            action: 'methane_get_processing_status',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                updateStatisticsDisplay(response.data);
            }
        });
    }
    
    function updateStatisticsDisplay(data) {
        if (data.database_stats) {
            const stats = data.database_stats;
            $('#stat-total-emissions').text(formatNumber(stats.total_emissions || 0));
            $('#stat-total-states').text(stats.total_states || 0);
            $('#stat-total-districts').text(stats.total_districts || 0);
            $('#stat-latest-date').text(stats.latest_date || '<?php _e('N/A', 'methane-monitor'); ?>');
        }
    }
    
    function refreshActivity() {
        $.post(ajaxurl, {
            action: 'methane_get_recent_activity',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                updateActivityList(response.data);
            }
        });
    }
    
    function updateActivityList(activities) {
        const $list = $('#activity-list');
        $list.empty();
        
        if (activities.length === 0) {
            $list.append('<div class="activity-item"><p><?php _e('No recent activity', 'methane-monitor'); ?></p></div>');
            return;
        }
        
        activities.forEach(function(activity) {
            const $item = $('<div class="activity-item">');
            $item.append('<strong>' + escapeHtml(activity.title) + '</strong>');
            $item.append('<p>' + escapeHtml(activity.description) + '</p>');
            $item.append('<small>' + escapeHtml(activity.time) + '</small>');
            $list.append($item);
        });
    }
    
    function loadTodayStats() {
        $.post(ajaxurl, {
            action: 'methane_get_today_stats',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                $('#uploads-today').text(response.data.uploads || 0);
                $('#api-calls-today').text(response.data.api_calls || 0);
                $('#cache-hits-today').text(response.data.cache_hits || 0);
            }
        });
    }
    
    function loadOverviewChart() {
        $.post(ajaxurl, {
            action: 'methane_get_overview_chart_data',
            timeframe: $('#chart-timeframe').val(),
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                renderOverviewChart(response.data);
            }
        });
    }
    
    function renderOverviewChart(data) {
        const trace = {
            x: data.dates,
            y: data.values,
            type: 'scatter',
            mode: 'lines+markers',
            name: '<?php _e('Average Emissions', 'methane-monitor'); ?>',
            line: { color: '#667eea', width: 2 },
            marker: { size: 6, color: '#667eea' }
        };
        
        const layout = {
            title: '<?php _e('Emissions Trend Over Time', 'methane-monitor'); ?>',
            xaxis: { title: '<?php _e('Date', 'methane-monitor'); ?>' },
            yaxis: { title: '<?php _e('CH₄ (ppb)', 'methane-monitor'); ?>' },
            margin: { t: 50, l: 60, r: 30, b: 60 }
        };
        
        Plotly.newPlot('overview-chart', [trace], layout, {
            responsive: true,
            displayModeBar: false
        });
    }
    
    function updateOverviewChart() {
        loadOverviewChart();
    }
    
    function runSystemDiagnostics() {
        const $button = $('#run-diagnostics');
        const originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Running...', 'methane-monitor'); ?>');
        
        $.post(ajaxurl, {
            action: 'methane_run_diagnostics',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                showNotice('<?php _e('Diagnostics completed. Check the system status.', 'methane-monitor'); ?>', 'success');
                // Update system status display
                updateSystemStatus(response.data);
            } else {
                showNotice('<?php _e('Diagnostics failed', 'methane-monitor'); ?>', 'error');
            }
        })
        .always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    }
    
    function updateSystemStatus(status) {
        // Update status indicators based on diagnostics results
        Object.keys(status).forEach(function(key) {
            const $element = $('.status-' + key);
            if ($element.length) {
                $element.removeClass('status-good status-warning status-error')
                       .addClass('status-' + status[key].status)
                       .text(status[key].message);
            }
        });
    }
    
    function checkForUpdates() {
        // Check for plugin updates
        $.post(ajaxurl, {
            action: 'methane_check_updates',
            nonce: '<?php echo wp_create_nonce('methane_monitor_admin_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success && response.data.has_update) {
                showNotice('<?php _e('A new version is available!', 'methane-monitor'); ?> <a href="' + response.data.update_url + '"><?php _e('Update now', 'methane-monitor'); ?></a>', 'info');
            }
        });
    }
    
    function showNotice(message, type) {
        const alertClass = type === 'error' ? 'notice-error' : 
                          type === 'success' ? 'notice-success' : 
                          type === 'warning' ? 'notice-warning' : 'notice-info';
        
        const notice = $('<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<!-- Add spinning animation for loading states -->
<style>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.quick-stat-items {
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.quick-stat-item {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    font-size: 0.9em;
    color: #666;
}

.help-item, .news-item {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.help-item:last-child, .news-item:last-child {
    border-bottom: none;
}

.help-item h4, .news-item h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.help-item p, .news-item p {
    margin: 5px 0;
    color: #666;
    font-size: 13px;
}

.chart-controls {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.activity-footer, .status-footer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    text-align: center;
}
</style>