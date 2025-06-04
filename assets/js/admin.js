/**
 * Methane Monitor Admin JavaScript
 * 
 * Handles admin interface functionality
 */

(function($) {
    'use strict';

    /**
     * Admin Dashboard Management
     */
    class MethaneAdminDashboard {
        constructor() {
            this.init();
        }

        init() {
            this.setupEventListeners();
            this.initializeComponents();
            this.loadDashboardData();
        }

        setupEventListeners() {
            // Clear cache button
            $(document).on('click', '#clear-cache-btn', (e) => {
                e.preventDefault();
                this.clearCache();
            });

            // Recalculate aggregations button
            $(document).on('click', '#recalculate-aggregations-btn', (e) => {
                e.preventDefault();
                this.recalculateAggregations();
            });

            // File upload form
            $(document).on('submit', '#methane-upload-form', (e) => {
                e.preventDefault();
                this.handleFileUpload(e.target);
            });

            // Analytics form
            $(document).on('submit', '#analytics-form', (e) => {
                e.preventDefault();
                this.generateAnalytics(e.target);
            });

            // Export buttons
            $(document).on('click', '#export-all-data', () => {
                this.exportData('csv', 'india');
            });

            $(document).on('click', '#cleanup-old-data', () => {
                this.cleanupOldData();
            });

            $(document).on('click', '#validate-data', () => {
                this.validateData();
            });

            // State selection change for district loading
            $(document).on('change', '#state_name, #analytics_state', (e) => {
                const stateName = e.target.value;
                const targetSelect = e.target.id === 'state_name' ? '#district_name' : '#analytics_district';
                if (stateName) {
                    this.loadDistricts(stateName, targetSelect);
                } else {
                    $(targetSelect).empty().append('<option value="">-- Select District --</option>').prop('disabled', true);
                }
            });

            // Settings form validation
            $(document).on('submit', '.methane-settings-form', (e) => {
                this.validateSettingsForm(e);
            });

            // Tab switching
            $(document).on('click', '.nav-tabs .nav-link', (e) => {
                this.handleTabSwitch(e);
            });
        }

        initializeComponents() {
            // Initialize tooltips
            if (typeof bootstrap !== 'undefined') {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Initialize progress bars
            this.animateProgressBars();

            // Initialize charts if Plotly is available
            if (typeof Plotly !== 'undefined') {
                this.initializeAdminCharts();
            }
        }

        loadDashboardData() {
            // Load recent activity
            this.loadRecentActivity();
            
            // Load system status
            this.updateSystemStatus();
            
            // Refresh statistics
            this.refreshStatistics();
        }

        /**
         * Clear cache functionality
         */
        clearCache() {
            if (!confirm(methaneAdmin.strings.confirmClearCache)) {
                return;
            }

            const $button = $('#clear-cache-btn');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(methaneAdmin.strings.clearing);

            $.post(ajaxurl, {
                action: 'methane_clear_cache',
                nonce: methaneAdmin.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice(methaneAdmin.strings.cacheCleared, 'success');
                    this.refreshStatistics();
                } else {
                    this.showNotice(methaneAdmin.strings.errorClearingCache, 'error');
                }
            })
            .fail(() => {
                this.showNotice(methaneAdmin.strings.errorClearingCache, 'error');
            })
            .always(() => {
                $button.prop('disabled', false).text(originalText);
            });
        }

        /**
         * Recalculate aggregations
         */
        recalculateAggregations() {
            if (!confirm(methaneAdmin.strings.confirmRecalculate)) {
                return;
            }

            const $button = $('#recalculate-aggregations-btn');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(methaneAdmin.strings.processing);

            $.post(ajaxurl, {
                action: 'methane_process_data',
                action_type: 'calculate_aggregations',
                nonce: methaneAdmin.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice(methaneAdmin.strings.aggregationsRecalculated, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    this.showNotice(methaneAdmin.strings.errorRecalculating, 'error');
                }
            })
            .fail(() => {
                this.showNotice(methaneAdmin.strings.errorRecalculating, 'error');
            })
            .always(() => {
                $button.prop('disabled', false).text(originalText);
            });
        }

        /**
         * Handle file upload
         */
        handleFileUpload(form) {
            const formData = new FormData(form);
            formData.append('action', 'methane_upload_data');
            formData.append('nonce', methaneAdmin.nonce);

            const $form = $(form);
            const $submitBtn = $form.find('input[type="submit"]');
            const originalText = $submitBtn.val();
            
            $submitBtn.prop('disabled', true).val(methaneAdmin.strings.uploading);
            
            // Show progress
            $('#upload-progress').show();
            this.updateUploadProgress(0, methaneAdmin.strings.preparingUpload);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            this.updateUploadProgress(percentComplete, methaneAdmin.strings.uploading);
                        }
                    });
                    return xhr;
                }
            })
            .done((response) => {
                if (response.success) {
                    this.updateUploadProgress(100, methaneAdmin.strings.uploadCompleted);
                    this.showNotice(methaneAdmin.strings.uploadSuccess, 'success');
                    $form[0].reset();
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    this.updateUploadProgress(0, methaneAdmin.strings.uploadFailed);
                    this.showNotice(methaneAdmin.strings.uploadFailed + ': ' + (response.data?.message || ''), 'error');
                }
            })
            .fail(() => {
                this.updateUploadProgress(0, methaneAdmin.strings.uploadFailed);
                this.showNotice(methaneAdmin.strings.uploadFailed, 'error');
            })
            .always(() => {
                $submitBtn.prop('disabled', false).val(originalText);
                setTimeout(() => {
                    $('#upload-progress').hide();
                }, 3000);
            });
        }

        /**
         * Update upload progress
         */
        updateUploadProgress(percent, message) {
            $('.progress-fill').css('width', percent + '%');
            $('.upload-status').html(message);
        }

        /**
         * Generate analytics
         */
        generateAnalytics(form) {
            const formData = $(form).serialize();
            const analysisType = $(form).find('[name="analysis_type"]').val();
            
            $('#analytics-results').show();
            $('#analytics-content').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><p class="mt-2">' + methaneAdmin.strings.generatingAnalytics + '</p></div>');

            $.post(ajaxurl, formData + '&action=methane_get_analytics&nonce=' + methaneAdmin.nonce)
            .done((response) => {
                if (response.success) {
                    this.displayAnalyticsResults(response.data, analysisType);
                } else {
                    $('#analytics-content').html('<div class="alert alert-danger">' + methaneAdmin.strings.errorGeneratingAnalytics + ': ' + (response.data?.message || '') + '</div>');
                }
            })
            .fail(() => {
                $('#analytics-content').html('<div class="alert alert-danger">' + methaneAdmin.strings.errorGeneratingAnalytics + '</div>');
            });
        }

        /**
         * Display analytics results
         */
        displayAnalyticsResults(data, type) {
            let html = '<div class="analytics-summary">';

            switch (type) {
                case 'ranking':
                    html += this.renderRankingResults(data);
                    break;
                case 'timeseries':
                    html += this.renderTimeSeriesResults(data);
                    break;
                case 'clustering':
                    html += this.renderClusteringResults(data);
                    break;
                case 'correlation':
                    html += this.renderCorrelationResults(data);
                    break;
                default:
                    html += '<p>Unknown analysis type</p>';
            }

            html += '</div>';
            $('#analytics-content').html(html);
        }

        /**
         * Render ranking results
         */
        renderRankingResults(data) {
            let html = '';

            if (data.state_rankings) {
                html += '<h3>' + methaneAdmin.strings.stateRankings + '</h3>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped analytics-table">';
                html += '<thead><tr><th>' + methaneAdmin.strings.rank + '</th><th>' + methaneAdmin.strings.state + '</th><th>' + methaneAdmin.strings.avgEmission + '</th></tr></thead>';
                html += '<tbody>';
                
                data.state_rankings.slice(0, 10).forEach((state, index) => {
                    html += `<tr>
                        <td><span class="badge bg-primary">${index + 1}</span></td>
                        <td>${state.state_name}</td>
                        <td><strong>${parseFloat(state.avg_emission).toFixed(2)} ppb</strong></td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            }

            if (data.district_rankings) {
                html += '<h3 class="mt-4">' + methaneAdmin.strings.districtRankings + '</h3>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped analytics-table">';
                html += '<thead><tr><th>' + methaneAdmin.strings.rank + '</th><th>' + methaneAdmin.strings.district + '</th><th>' + methaneAdmin.strings.state + '</th><th>' + methaneAdmin.strings.avgEmission + '</th></tr></thead>';
                html += '<tbody>';
                
                data.district_rankings.slice(0, 15).forEach((district, index) => {
                    html += `<tr>
                        <td><span class="badge bg-secondary">${index + 1}</span></td>
                        <td>${district.district_name}</td>
                        <td>${district.state_name}</td>
                        <td><strong>${parseFloat(district.avg_emission).toFixed(2)} ppb</strong></td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            }

            return html;
        }

        /**
         * Render time series results
         */
        renderTimeSeriesResults(data) {
            let html = '<h3>' + methaneAdmin.strings.timeSeriesAnalysis + '</h3>';
            
            if (data.time_series && data.time_series.length > 0) {
                // Create chart container
                const chartId = 'admin-timeseries-chart-' + Date.now();
                html += `<div id="${chartId}" class="analytics-chart mb-4"></div>`;
                
                // Add statistics
                if (data.statistics) {
                    const stats = data.statistics;
                    html += '<div class="row">';
                    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>${stats.mean.toFixed(2)}</h5><small class="text-muted">Average (ppb)</small></div></div></div>`;
                    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>${stats.min.toFixed(2)}</h5><small class="text-muted">Minimum (ppb)</small></div></div></div>`;
                    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>${stats.max.toFixed(2)}</h5><small class="text-muted">Maximum (ppb)</small></div></div></div>`;
                    html += `<div class="col-md-3"><div class="card text-center"><div class="card-body"><h5>${stats.trend_slope > 0 ? '📈' : stats.trend_slope < 0 ? '📉' : '➡️'}</h5><small class="text-muted">Trend</small></div></div></div>`;
                    html += '</div>';
                }
                
                // Plot chart after DOM update
                setTimeout(() => {
                    this.plotAdminTimeSeries(chartId, data);
                }, 100);
            } else {
                html += '<div class="alert alert-info">' + methaneAdmin.strings.noTimeSeriesData + '</div>';
            }

            return html;
        }

        /**
         * Render clustering results
         */
        renderClusteringResults(data) {
            let html = '<h3>' + methaneAdmin.strings.clusteringAnalysis + '</h3>';
            
            if (data.district_clusters) {
                html += `<p class="lead">${methaneAdmin.strings.clustersFound}: <strong>${data.n_clusters}</strong></p>`;
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped analytics-table">';
                html += '<thead><tr><th>' + methaneAdmin.strings.district + '</th><th>' + methaneAdmin.strings.cluster + '</th><th>' + methaneAdmin.strings.avgEmission + '</th></tr></thead>';
                html += '<tbody>';
                
                data.district_clusters.forEach(district => {
                    html += `<tr>
                        <td>${district.district}</td>
                        <td><span class="badge bg-info">Cluster ${district.cluster}</span></td>
                        <td><strong>${parseFloat(district.average_methane).toFixed(2)} ppb</strong></td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            } else {
                html += '<div class="alert alert-info">' + methaneAdmin.strings.noClusteringData + '</div>';
            }

            return html;
        }

        /**
         * Render correlation results
         */
        renderCorrelationResults(data) {
            let html = '<h3>' + methaneAdmin.strings.correlationAnalysis + '</h3>';
            
            if (data.correlation_pairs) {
                html += '<h4>' + methaneAdmin.strings.strongestCorrelations + '</h4>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped analytics-table">';
                html += '<thead><tr><th>' + methaneAdmin.strings.district1 + '</th><th>' + methaneAdmin.strings.district2 + '</th><th>' + methaneAdmin.strings.correlation + '</th></tr></thead>';
                html += '<tbody>';
                
                data.strongest_correlations?.slice(0, 10).forEach(pair => {
                    const correlationClass = Math.abs(pair.correlation) > 0.7 ? 'text-success' : Math.abs(pair.correlation) > 0.4 ? 'text-warning' : 'text-muted';
                    html += `<tr>
                        <td>${pair.district1}</td>
                        <td>${pair.district2}</td>
                        <td><strong class="${correlationClass}">${pair.correlation.toFixed(3)}</strong></td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            } else {
                html += '<div class="alert alert-info">' + methaneAdmin.strings.noCorrelationData + '</div>';
            }

            return html;
        }

        /**
         * Plot time series chart for admin
         */
        plotAdminTimeSeries(containerId, data) {
            if (typeof Plotly === 'undefined') {
                document.getElementById(containerId).innerHTML = '<div class="alert alert-warning">Plotly not available</div>';
                return;
            }

            const trace = {
                x: data.time_series.map(d => d.date),
                y: data.time_series.map(d => d.value),
                type: 'scatter',
                mode: 'lines+markers',
                name: 'CH₄ Levels',
                line: { color: '#667eea', width: 2 },
                marker: { size: 6, color: '#667eea' }
            };

            const layout = {
                title: {
                    text: 'Methane Time Series Analysis',
                    font: { size: 16 }
                },
                xaxis: { title: 'Date', type: 'date' },
                yaxis: { title: 'CH₄ (ppb)' },
                margin: { t: 50, l: 60, r: 30, b: 60 },
                hovermode: 'x unified',
                plot_bgcolor: 'rgba(0,0,0,0)',
                paper_bgcolor: 'rgba(0,0,0,0)'
            };

            Plotly.newPlot(containerId, [trace], layout, { 
                responsive: true, 
                displayModeBar: false 
            });
        }

        /**
         * Load districts for a state
         */
        loadDistricts(stateName, targetSelectId) {
            const $select = $(targetSelectId);
            
            $select.prop('disabled', true).html('<option value="">Loading...</option>');

            $.post(ajaxurl, {
                action: 'methane_get_districts',
                state_name: stateName,
                nonce: methaneAdmin.nonce
            })
            .done((response) => {
                if (response.success && response.data) {
                    let options = '<option value="">-- Select District --</option>';
                    response.data.forEach(district => {
                        options += `<option value="${district}">${district}</option>`;
                    });
                    $select.html(options).prop('disabled', false);
                } else {
                    $select.html('<option value="">No districts found</option>').prop('disabled', true);
                }
            })
            .fail(() => {
                $select.html('<option value="">Error loading districts</option>').prop('disabled', true);
            });
        }

        /**
         * Export data
         */
        exportData(format, level, state = '', district = '') {
            let url = ajaxurl + '?action=methane_export_data&format=' + format + '&level=' + level + '&nonce=' + methaneAdmin.nonce;
            
            if (state) url += '&state=' + encodeURIComponent(state);
            if (district) url += '&district=' + encodeURIComponent(district);
            
            window.open(url, '_blank');
        }

        /**
         * Cleanup old data
         */
        cleanupOldData() {
            if (!confirm(methaneAdmin.strings.confirmCleanup)) {
                return;
            }

            // Placeholder - implement actual cleanup logic
            this.showNotice('Cleanup functionality will be implemented in future version', 'info');
        }

        /**
         * Validate data integrity
         */
        validateData() {
            const $button = $('#validate-data');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(methaneAdmin.strings.validating);

            // Simulate validation process
            setTimeout(() => {
                this.showNotice(methaneAdmin.strings.validationCompleted, 'success');
                $button.prop('disabled', false).text(originalText);
            }, 2000);
        }

        /**
         * Load recent activity
         */
        loadRecentActivity() {
            // This would typically load from an API endpoint
            // For now, show static content
            console.log('Loading recent activity...');
        }

        /**
         * Update system status
         */
        updateSystemStatus() {
            // Check various system components
            this.checkDatabaseConnection();
            this.checkFilePermissions();
            this.checkMemoryUsage();
        }

        checkDatabaseConnection() {
            // Simulate database check
            $('.status-database').removeClass('status-error status-warning').addClass('status-good').text('Connected');
        }

        checkFilePermissions() {
            // Simulate file permission check
            $('.status-files').removeClass('status-error status-warning').addClass('status-good').text('Writable');
        }

        checkMemoryUsage() {
            // Simulate memory check
            $('.status-memory').removeClass('status-error status-warning').addClass('status-good').text('Normal');
        }

        /**
         * Refresh statistics
         */
        refreshStatistics() {
            $.post(ajaxurl, {
                action: 'methane_get_processing_status',
                nonce: methaneAdmin.nonce
            })
            .done((response) => {
                if (response.success && response.data) {
                    this.updateStatisticsDisplay(response.data);
                }
            })
            .fail(() => {
                console.warn('Failed to refresh statistics');
            });
        }

        updateStatisticsDisplay(data) {
            if (data.database_stats) {
                const stats = data.database_stats;
                $('#stat-total-emissions').text(this.formatNumber(stats.total_emissions || 0));
                $('#stat-total-states').text(stats.total_states || 0);
                $('#stat-total-districts').text(stats.total_districts || 0);
                $('#stat-latest-date').text(stats.latest_date || 'N/A');
            }
        }

        /**
         * Animate progress bars
         */
        animateProgressBars() {
            $('.progress-bar').each(function() {
                const $bar = $(this);
                const width = $bar.data('width') || $bar.attr('aria-valuenow');
                if (width) {
                    $bar.css('width', '0%').animate({ width: width + '%' }, 1000);
                }
            });
        }

        /**
         * Initialize admin charts
         */
        initializeAdminCharts() {
            // Initialize any charts on the admin dashboard
            if ($('#admin-overview-chart').length) {
                this.createOverviewChart();
            }
        }

        createOverviewChart() {
            // Create a simple overview chart
            const data = [{
                x: ['Emissions Data', 'States', 'Districts'],
                y: [1000, 28, 640], // Sample data
                type: 'bar',
                marker: { color: '#667eea' }
            }];

            const layout = {
                title: 'Data Overview',
                xaxis: { title: 'Category' },
                yaxis: { title: 'Count' },
                margin: { t: 50, l: 60, r: 30, b: 60 }
            };

            Plotly.newPlot('admin-overview-chart', data, layout, { 
                responsive: true, 
                displayModeBar: false 
            });
        }

        /**
         * Validate settings form
         */
        validateSettingsForm(e) {
            const $form = $(e.target);
            let isValid = true;
            
            // Validate cache duration
            const cacheDuration = $form.find('[name="methane_monitor_options[cache_duration]"]').val();
            if (cacheDuration && (cacheDuration < 300 || cacheDuration > 86400)) {
                this.showNotice('Cache duration must be between 300 and 86400 seconds', 'error');
                isValid = false;
            }
            
            // Validate file size
            const fileSize = $form.find('[name="methane_monitor_options[max_file_size]"]').val();
            if (fileSize && (fileSize < 1 || fileSize > 100)) {
                this.showNotice('Maximum file size must be between 1 and 100 MB', 'error');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        }

        /**
         * Handle tab switching
         */
        handleTabSwitch(e) {
            const $tab = $(e.target);
            const targetPane = $tab.attr('data-bs-target') || $tab.attr('href');
            
            // Load content for specific tabs
            if (targetPane === '#analytics' && !$('#analytics-content').children().length) {
                this.loadAnalyticsTab();
            }
        }

        loadAnalyticsTab() {
            $('#analytics-content').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading analytics...</p></div>');
            
            // Simulate loading
            setTimeout(() => {
                $('#analytics-content').html('<p>Analytics tools are ready. Use the form above to generate reports.</p>');
            }, 1000);
        }

        /**
         * Utility methods
         */
        showNotice(message, type = 'info') {
            const alertClass = type === 'error' ? 'alert-danger' : 
                             type === 'success' ? 'alert-success' : 
                             type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const noticeId = 'notice-' + Date.now();
            const notice = `
                <div id="${noticeId}" class="notice notice-${type} is-dismissible alert ${alertClass} fade show" style="margin: 10px 0;">
                    <p>${message}</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $(`#${noticeId}`).fadeOut();
            }, 5000);
        }

        formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        formatFileSize(bytes) {
            const sizes = ['B', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 B';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }
    }

    /**
     * Initialize admin functionality when DOM is ready
     */
    $(document).ready(function() {
        // Initialize admin dashboard
        window.methaneAdmin = window.methaneAdmin || {};
        window.methaneAdmin.dashboard = new MethaneAdminDashboard();
        
        // Global admin utilities
        window.methaneAdmin.showNotice = (message, type) => {
            window.methaneAdmin.dashboard.showNotice(message, type);
        };
        
        window.methaneAdmin.formatNumber = (num) => {
            return window.methaneAdmin.dashboard.formatNumber(num);
        };
        
        console.log('Methane Monitor Admin initialized');
    });

})(jQuery);