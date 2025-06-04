/**
 * Methane Monitor Frontend JavaScript
 * 
 * Handles map interactions, data visualization, and analytics
 */

(function($) {
    'use strict';

    /**
     * Main Methane Monitor Application Class
     */
    class MethaneMonitorApp {
        constructor(config = {}) {
            this.config = {
                containerId: 'methane-monitor-container',
                mapId: 'methane-map',
                initialLevel: 'india',
                initialState: '',
                initialDistrict: '',
                initialYear: new Date().getFullYear(),
                initialMonth: new Date().getMonth() + 1,
                theme: 'light',
                colorScheme: 'viridis',
                showControls: true,
                showAnalytics: true,
                enableExport: false,
                zoomControls: true,
                ...config
            };

            this.state = {
                currentLevel: this.config.initialLevel,
                currentState: this.config.initialState,
                currentDistrict: this.config.initialDistrict,
                currentYear: this.config.initialYear,
                currentMonth: this.config.initialMonth,
                currentData: null,
                currentStats: null,
                map: null,
                layers: {
                    methane: null,
                    heatmap: null,
                    points: null
                },
                legend: null,
                colorScale: null,
                useHeatmap: false
            };

            this.init();
        }

        /**
         * Initialize the application
         */
        init() {
            this.setupEventListeners();
            this.initializeMap();
            this.loadInitialData();
            this.setupAnalytics();
            
            // Show loading overlay initially
            this.showLoading('Initializing methane monitor...');
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Time controls
            $('#year-select').on('change', (e) => {
                this.state.currentYear = parseInt(e.target.value);
                this.updateCurrentView();
            });

            $('#month-select').on('change', (e) => {
                this.state.currentMonth = parseInt(e.target.value);
                this.updateCurrentView();
            });

            // Navigation controls
            $('#state-nav-select').on('change', (e) => {
                const selectedState = e.target.value;
                if (selectedState) {
                    this.loadStateView(selectedState);
                } else {
                    this.resetToIndiaView();
                }
            });

            $('#district-nav-select').on('change', (e) => {
                const selectedDistrict = e.target.value;
                if (selectedDistrict && this.state.currentState) {
                    this.loadDistrictView(this.state.currentState, selectedDistrict);
                } else if (this.state.currentState) {
                    this.loadStateView(this.state.currentState);
                }
            });

            // Visualization toggles
            $('#methane-layer-toggle').on('change', (e) => {
                this.toggleMethaneLayer(e.target.checked);
            });

            $('#heatmap-toggle').on('change', (e) => {
                this.state.useHeatmap = e.target.checked;
                this.updateCurrentView();
            });

            // Reset view
            $('#reset-view-btn').on('click', () => {
                this.resetToIndiaView();
            });

            // Controls toggle
            $('#toggle-controls-btn').on('click', () => {
                $('#controls-content').slideToggle(200, () => {
                    const icon = $('#toggle-controls-btn i');
                    icon.toggleClass('bi-chevron-up bi-chevron-down');
                });
            });

            // Analytics tabs
            $('#analytics-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
                const tabId = $(e.target).attr('id');
                this.handleAnalyticsTabChange(tabId);
            });

            // Export functions
            if (this.config.enableExport) {
                $('[data-export]').on('click', (e) => {
                    e.preventDefault();
                    const format = $(e.target).data('export');
                    this.exportData(format);
                });
            }

            // Fullscreen toggle
            $('#methane-fullscreen-btn').on('click', () => {
                this.toggleFullscreen();
            });
        }

        /**
         * Initialize Leaflet map
         */
        initializeMap() {
            // Initialize map
            this.state.map = L.map(this.config.mapId, {
                center: [20.5937, 78.9629],
                zoom: 5,
                minZoom: 4,
                maxZoom: 18,
                preferCanvas: true,
                zoomControl: this.config.zoomControls
            });

            // Add satellite tile layer
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                maxZoom: 20
            }).addTo(this.state.map);

            // Add legend
            this.addLegend();

            // Initialize color scale
            this.initializeColorScale(1700, 2200);

            console.log('Map initialized successfully');
        }

        /**
         * Initialize color scale
         */
        initializeColorScale(min, max) {
            if (typeof chroma === 'undefined') {
                console.warn('Chroma.js not loaded, using fallback colors');
                return;
            }

            const colorSchemes = {
                viridis: ['#440154', '#482777', '#3f4a8a', '#31678e', '#26838f', '#1f9d8a', '#6cce5a', '#b6de2b', '#fee825'],
                plasma: ['#0d0887', '#41049d', '#6a00a8', '#8f0da4', '#b12a90', '#cc4778', '#e16462', '#f2844b', '#fca636', '#fcce25'],
                inferno: ['#000004', '#1b0c41', '#4a0c6b', '#781c6d', '#a52c60', '#cf4446', '#ed6925', '#fb9b06', '#f7d03c', '#fcffa4']
            };

            const colors = colorSchemes[this.config.colorScheme] || colorSchemes.viridis;
            this.state.colorScale = chroma.scale(colors).domain([min, max]).mode('lab');
        }

        /**
         * Get color for value
         */
        getColorForValue(value, min, max) {
            if (value === null || value === undefined || isNaN(value) || value <= 0) {
                return '#E5E5E5'; // Gray for no data
            }

            if (!this.state.colorScale || min !== this.state.colorScale.domain()[0] || max !== this.state.colorScale.domain()[1]) {
                this.initializeColorScale(min, max);
            }

            return this.state.colorScale(value).hex();
        }

        /**
         * Add legend to map
         */
        addLegend() {
            if (this.state.legend) {
                this.state.map.removeControl(this.state.legend);
            }

            this.state.legend = L.control({ position: 'bottomright' });
            this.state.legend.onAdd = (map) => {
                const div = L.DomUtil.create('div', 'info legend');
                this.updateLegendContent(div, 1700, 2200);
                return div;
            };
            this.state.legend.addTo(this.state.map);
        }

        /**
         * Update legend content
         */
        updateLegendContent(legendDiv, minVal, maxVal) {
            if (!this.state.colorScale) {
                this.initializeColorScale(minVal, maxVal);
            }

            const gradientStops = [];
            for (let i = 0; i <= 10; i++) {
                const pct = i * 10;
                const val = minVal + (maxVal - minVal) * (i / 10);
                gradientStops.push(`${this.state.colorScale(val).hex()} ${pct}%`);
            }

            legendDiv.innerHTML = `
                <h6><i class="bi bi-droplet-half"></i> CH₄ (ppb)</h6>
                <div class="legend-gradient" style="background: linear-gradient(to right, ${gradientStops.join(', ')}); height: 20px; width: 100%; border-radius: 4px; margin-bottom: 8px; border: 1px solid #ddd;"></div>
                <div class="legend-labels" style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">
                    <span>${minVal.toFixed(0)}</span>
                    <span>${((minVal + maxVal) / 2).toFixed(0)}</span>
                    <span>${maxVal.toFixed(0)}</span>
                </div>
            `;
        }

        /**
         * Load initial data
         */
        async loadInitialData() {
            try {
                // Load metadata first
                await this.loadMetadata();

                // Load initial view based on config
                switch (this.config.initialLevel) {
                    case 'state':
                        if (this.config.initialState) {
                            await this.loadStateView(this.config.initialState);
                        } else {
                            await this.loadIndiaView();
                        }
                        break;
                    case 'district':
                        if (this.config.initialState && this.config.initialDistrict) {
                            await this.loadDistrictView(this.config.initialState, this.config.initialDistrict);
                        } else {
                            await this.loadIndiaView();
                        }
                        break;
                    default:
                        await this.loadIndiaView();
                }
            } catch (error) {
                console.error('Failed to load initial data:', error);
                this.showError('Failed to load initial data. Please refresh the page.');
            } finally {
                this.hideLoading();
            }
        }

        /**
         * Load metadata
         */
        async loadMetadata() {
            try {
                const response = await fetch(`${methaneMonitor.restUrl}metadata`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const metadata = await response.json();
                this.populateYearSelect(metadata.years);
                this.populateStateSelect(metadata.all_states_list);
                
                console.log('Metadata loaded successfully');
            } catch (error) {
                console.error('Error loading metadata:', error);
                throw error;
            }
        }

        /**
         * Populate year select
         */
        populateYearSelect(years) {
            const yearSelect = $('#year-select');
            yearSelect.empty();
            
            years.forEach(year => {
                yearSelect.append(`<option value="${year}" ${year === this.state.currentYear ? 'selected' : ''}>${year}</option>`);
            });
        }

        /**
         * Populate state select
         */
        populateStateSelect(states) {
            const stateSelect = $('#state-nav-select');
            stateSelect.empty().append('<option value="">-- Select State --</option>');
            
            states.forEach(state => {
                stateSelect.append(`<option value="${state}">${state}</option>`);
            });
        }

        /**
         * Load India view
         */
        async loadIndiaView() {
            this.showLoading(`Loading India data for ${methaneMonitor.strings[this.getMonthName(this.state.currentMonth)]} ${this.state.currentYear}...`);
            
            try {
                this.state.currentLevel = 'india';
                this.state.currentState = null;
                this.state.currentDistrict = null;

                this.updateBreadcrumb();
                this.updateCurrentLevelDisplay();
                this.resetAnalyticsTabs('india');
                this.resetNavigationSelects();

                const vizType = this.state.useHeatmap ? 'heatmap' : 'choropleth';
                const response = await fetch(`${methaneMonitor.restUrl}india/${this.state.currentYear}/${this.state.currentMonth}?viz=${vizType}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                this.state.currentData = data;
                this.state.currentStats = data.stats;

                this.updateMapLayers();
                this.updateStatistics(data.stats);
                this.updateOverviewPanel(data);
                this.updateLegend();

                this.showSuccess('India view loaded successfully');
            } catch (error) {
                console.error('Error loading India data:', error);
                this.showError('Failed to load India data');
                this.clearMapLayers();
            } finally {
                this.hideLoading();
            }
        }

        /**
         * Load state view
         */
        async loadStateView(stateName) {
            this.showLoading(`Loading ${stateName} data for ${methaneMonitor.strings[this.getMonthName(this.state.currentMonth)]} ${this.state.currentYear}...`);
            
            try {
                this.state.currentLevel = 'state';
                this.state.currentState = stateName;
                this.state.currentDistrict = null;

                this.updateBreadcrumb();
                this.updateCurrentLevelDisplay();
                this.resetAnalyticsTabs('state');
                this.updateNavigationSelects();

                const vizType = this.state.useHeatmap ? 'heatmap' : 'choropleth';
                const response = await fetch(`${methaneMonitor.restUrl}state/${encodeURIComponent(stateName)}/${this.state.currentYear}/${this.state.currentMonth}?viz=${vizType}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                this.state.currentData = data;
                this.state.currentStats = data.stats;

                this.updateMapLayers();
                this.updateStatistics(data.stats);
                this.updateOverviewPanel(data);
                this.updateLegend();
                this.loadDistrictsList(stateName);

                this.showSuccess(`${stateName} view loaded successfully`);
            } catch (error) {
                console.error(`Error loading ${stateName} data:`, error);
                this.showError(`Failed to load ${stateName} data`);
                this.clearMapLayers();
            } finally {
                this.hideLoading();
            }
        }

        /**
         * Load district view
         */
        async loadDistrictView(stateName, districtName) {
            this.showLoading(`Loading ${districtName} data...`);
            
            try {
                this.state.currentLevel = 'district';
                this.state.currentState = stateName;
                this.state.currentDistrict = districtName;

                this.updateBreadcrumb();
                this.updateCurrentLevelDisplay();
                this.resetAnalyticsTabs('district');
                this.updateNavigationSelects();

                const response = await fetch(`${methaneMonitor.restUrl}district/${encodeURIComponent(stateName)}/${encodeURIComponent(districtName)}/${this.state.currentYear}/${this.state.currentMonth}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                this.state.currentData = data;
                this.state.currentStats = data.stats;

                this.updateMapLayers();
                this.updateStatistics(data.stats);
                this.updateOverviewPanel(data);
                this.updateLegend();

                this.showSuccess(`${districtName} view loaded successfully`);
            } catch (error) {
                console.error(`Error loading ${districtName} data:`, error);
                this.showError(`Failed to load ${districtName} data`);
                this.clearMapLayers();
            } finally {
                this.hideLoading();
            }
        }

        /**
         * Update current view
         */
        updateCurrentView() {
            switch (this.state.currentLevel) {
                case 'india':
                    this.loadIndiaView();
                    break;
                case 'state':
                    if (this.state.currentState) {
                        this.loadStateView(this.state.currentState);
                    }
                    break;
                case 'district':
                    if (this.state.currentState && this.state.currentDistrict) {
                        this.loadDistrictView(this.state.currentState, this.state.currentDistrict);
                    }
                    break;
            }
        }

        /**
         * Clear map layers
         */
        clearMapLayers() {
            Object.values(this.state.layers).forEach(layer => {
                if (layer) {
                    this.state.map.removeLayer(layer);
                }
            });
            this.state.layers = { methane: null, heatmap: null, points: null };
        }

        /**
         * Update map layers
         */
        updateMapLayers() {
            this.clearMapLayers();

            if (!$('#methane-layer-toggle').is(':checked')) {
                return;
            }

            const data = this.state.currentData;
            if (!data) return;

            if (data.type === 'interpolated_contour') {
                this.renderInterpolatedContour(data.interpolated_grid_bundle);
                if (data.original_points) {
                    this.addMeasurementPoints(data.original_points);
                }
            } else if (data.type === 'contour_points_only') {
                this.createHeatmapFromPoints(data.points);
                if (data.bounds) {
                    this.state.map.fitBounds([
                        [data.bounds.min_lat, data.bounds.min_lon],
                        [data.bounds.max_lat, data.bounds.max_lon]
                    ], { padding: [30, 30] });
                }
                this.addMeasurementPoints(data.points);
            } else if (data.type === 'choropleth' && data.geojson) {
                this.renderChoroplethData(data.geojson);
            }
        }

        /**
         * Render choropleth data
         */
        renderChoroplethData(geojson) {
            if (!geojson || !geojson.features) {
                console.warn('No GeoJSON features to render');
                return;
            }

            const stats = this.state.currentStats;
            this.state.layers.methane = L.geoJSON(geojson, {
                style: (feature) => ({
                    fillColor: this.getColorForValue(
                        feature.properties.methane_ppb || feature.properties.avg_emission,
                        stats.data_min,
                        stats.data_max
                    ),
                    weight: 1,
                    opacity: 1,
                    color: 'white',
                    fillOpacity: 0.8
                }),
                onEachFeature: (feature, layer) => {
                    this.bindFeatureInteractions(feature, layer);
                }
            }).addTo(this.state.map);

            if (this.state.layers.methane.getBounds().isValid()) {
                this.state.map.fitBounds(this.state.layers.methane.getBounds(), { padding: [30, 30] });
            }
        }

        /**
         * Bind feature interactions
         */
        bindFeatureInteractions(feature, layer) {
            const props = feature.properties;
            const name = props.STATE || props.District_1 || props.name || 'N/A';
            const value = props.methane_ppb || props.avg_emission;
            const valueDisplay = (value !== null && value > 0) ? `${parseFloat(value).toFixed(1)} ppb` : 'No data';
            
            const popupContent = `
                <div class="methane-popup" style="text-align: center; min-width: 150px;">
                    <h6 style="color: var(--methane-primary); margin-bottom: 10px; font-weight: 600;">${name}</h6>
                    <div class="value" style="font-size: 1.5rem; font-weight: 700; margin: 10px 0;">${valueDisplay}</div>
                    <div class="period" style="color: #666; font-size: 12px;">${methaneMonitor.strings[this.getMonthName(this.state.currentMonth)]} ${this.state.currentYear}</div>
                    ${this.shouldShowClickHint() ? '<div class="action" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; font-size: 12px; color: var(--methane-info);"><i class="bi bi-hand-index"></i> Click to explore</div>' : ''}
                </div>
            `;

            layer.bindPopup(popupContent);
            layer.bindTooltip(`<strong>${name}</strong><br>${valueDisplay}`, { 
                sticky: true, 
                className: 'custom-tooltip' 
            });

            layer.on({
                mouseover: (e) => {
                    e.target.setStyle({ weight: 3, color: '#333', fillOpacity: 0.9 });
                },
                mouseout: (e) => {
                    this.state.layers.methane.resetStyle(e.target);
                },
                click: (e) => {
                    if (!this.shouldShowClickHint()) return;
                    
                    if (this.state.currentLevel === 'india') {
                        this.loadStateView(props.STATE);
                    } else if (this.state.currentLevel === 'state') {
                        this.loadDistrictView(props.STATE, props.District_1);
                    }
                }
            });
        }

        /**
         * Should show click hint
         */
        shouldShowClickHint() {
            return !this.state.useHeatmap && (this.state.currentLevel === 'india' || this.state.currentLevel === 'state');
        }

        /**
         * Render interpolated contour
         */
        renderInterpolatedContour(interpolatedBundle) {
            if (!interpolatedBundle || !interpolatedBundle.grid) {
                console.warn('No interpolated grid data to render');
                return;
            }

            const { grid, lat_range, lon_range, value_range } = interpolatedBundle;
            const heatPoints = [];

            for (let i = 0; i < grid.length; i++) {
                for (let j = 0; j < grid[0].length; j++) {
                    const val = grid[i][j];
                    if (val !== null && !isNaN(val) && val > 0) {
                        heatPoints.push([lat_range[i], lon_range[j], val]);
                    }
                }
            }

            if (heatPoints.length > 0) {
                const gradient = this.createHeatmapGradient(value_range.min, value_range.max);
                this.state.layers.heatmap = L.heatLayer(heatPoints, {
                    radius: 25,
                    blur: 20,
                    maxZoom: 18,
                    max: value_range.max,
                    gradient: gradient
                }).addTo(this.state.map);

                if (interpolatedBundle.bounds) {
                    this.state.map.fitBounds([
                        [interpolatedBundle.bounds.min_lat, interpolatedBundle.bounds.min_lon],
                        [interpolatedBundle.bounds.max_lat, interpolatedBundle.bounds.max_lon]
                    ], { padding: [30, 30] });
                }
            }
        }

        /**
         * Create heatmap from points
         */
        createHeatmapFromPoints(points) {
            if (!points || points.length === 0) {
                console.warn('No points provided for heatmap');
                return;
            }

            const heatPoints = [];
            let minVal = Infinity, maxVal = -Infinity;

            points.forEach(p => {
                const [lat, lon, val] = p;
                if (lat && lon && val && val > 0) {
                    heatPoints.push([lat, lon, val]);
                    minVal = Math.min(minVal, val);
                    maxVal = Math.max(maxVal, val);
                }
            });

            if (heatPoints.length > 0) {
                const gradient = this.createHeatmapGradient(minVal, maxVal);
                this.state.layers.heatmap = L.heatLayer(heatPoints, {
                    radius: 20,
                    blur: 15,
                    maxZoom: 18,
                    max: maxVal,
                    gradient: gradient
                }).addTo(this.state.map);
            }
        }

        /**
         * Create heatmap gradient
         */
        createHeatmapGradient(min, max) {
            this.initializeColorScale(min, max);
            const gradient = {};

            for (let i = 0; i <= 10; i++) {
                const ratio = i / 10;
                gradient[ratio] = this.state.colorScale(min + (max - min) * ratio).hex();
            }

            return gradient;
        }

        /**
         * Add measurement points
         */
        addMeasurementPoints(points) {
            if (!points || points.length === 0) return;

            this.state.layers.points = L.layerGroup();
            let renderedCount = 0;

            points.forEach(p => {
                const [lat, lon, val] = p;
                if (lat && lon && val && val > 0) {
                    const color = this.getColorForValue(val, this.state.currentStats.data_min, this.state.currentStats.data_max);
                    L.circleMarker([lat, lon], {
                        radius: 4,
                        fillColor: color,
                        color: '#333',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    })
                    .bindTooltip(`<strong>${parseFloat(val).toFixed(1)} ppb</strong><br><small>(${parseFloat(lat).toFixed(4)}, ${parseFloat(lon).toFixed(4)})</small>`, { sticky: true })
                    .addTo(this.state.layers.points);
                    renderedCount++;
                }
            });

            if (renderedCount > 0) {
                this.state.layers.points.addTo(this.state.map);
            }

            console.log(`Added ${renderedCount} measurement point markers`);
        }

        /**
         * Toggle methane layer
         */
        toggleMethaneLayer(show) {
            if (show) {
                this.updateMapLayers();
            } else {
                this.clearMapLayers();
            }
        }

        /**
         * Update statistics display
         */
        updateStatistics(stats) {
            const format = (val) => (val !== null && !isNaN(val)) ? parseFloat(val).toFixed(1) : '--';
            
            $('#stat-average').text(format(stats?.mean));
            $('#stat-min').text(format(stats?.data_min ?? stats?.min));
            $('#stat-max').text(format(stats?.data_max ?? stats?.max));
        }

        /**
         * Update overview panel
         */
        updateOverviewPanel(data) {
            let title = `Methane Emissions Overview - ${methaneMonitor.strings[this.getMonthName(this.state.currentMonth)]} ${this.state.currentYear}`;
            let description = 'Explore emission patterns. Click areas for details.';

            if (this.state.currentLevel === 'india') {
                title = `India - ${title}`;
            } else if (this.state.currentLevel === 'state') {
                title = `${this.state.currentState} - District Emissions`;
                description = `Analyzing district-level data for ${this.state.currentState}`;
            } else if (this.state.currentLevel === 'district') {
                title = `${this.state.currentDistrict}, ${this.state.currentState}`;
                const pointCount = data.points?.length || data.original_points?.length || 0;
                description = `High-resolution data. ${pointCount} data points analyzed.`;
            }

            $('#overview-title').text(title);
            $('#overview-description').text(description);

            const analysisHtml = `
                <div class="text-center">
                    <h6 class="text-primary mb-3">${this.state.currentYear} - ${methaneMonitor.strings[this.getMonthName(this.state.currentMonth)]}</h6>
                    <hr>
                    <p class="mb-2"><strong>Level:</strong> ${this.state.currentLevel.charAt(0).toUpperCase() + this.state.currentLevel.slice(1)}</p>
                    ${this.state.currentState ? `<p class="mb-2"><strong>State:</strong> ${this.state.currentState}</p>` : ''}
                    ${this.state.currentDistrict ? `<p class="mb-2"><strong>District:</strong> ${this.state.currentDistrict}</p>` : ''}
                    ${data.stats ? `<p class="mb-0"><strong>Data Points:</strong> ${data.stats.count || 'N/A'}</p>` : ''}
                </div>
            `;
            $('#current-analysis').html(analysisHtml);
        }

        /**
         * Update breadcrumb
         */
        updateBreadcrumb() {
            let html = `<a href="#" onclick="event.preventDefault(); methaneMonitorInstance.resetToIndiaView();">🇮🇳 India</a>`;
            
            if (this.state.currentLevel === 'state' || this.state.currentLevel === 'district') {
                html += ` > `;
                if (this.state.currentLevel === 'state') {
                    html += `<span class="current">🏛️ ${this.state.currentState}</span>`;
                } else {
                    html += `<a href="#" onclick="event.preventDefault(); methaneMonitorInstance.loadStateView('${this.state.currentState}');">🏛️ ${this.state.currentState}</a>`;
                    html += ` > <span class="current">📍 ${this.state.currentDistrict}</span>`;
                }
            }
            
            $('#methane-breadcrumb-container').html(html);
        }

        /**
         * Update current level display
         */
        updateCurrentLevelDisplay() {
            let display = this.state.currentLevel.charAt(0).toUpperCase() + this.state.currentLevel.slice(1);
            if (this.state.currentState) display += ` - ${this.state.currentState}`;
            if (this.state.currentDistrict) display += ` > ${this.state.currentDistrict}`;
            $('#current-level-display').text(display);
        }

        /**
         * Update legend
         */
        updateLegend() {
            if (this.state.legend && this.state.legend.getContainer() && this.state.currentStats) {
                this.updateLegendContent(
                    this.state.legend.getContainer(), 
                    this.state.currentStats.data_min, 
                    this.state.currentStats.data_max
                );
            }
        }

        /**
         * Reset navigation selects
         */
        resetNavigationSelects() {
            $('#state-nav-select').val('');
            $('#district-nav-select').empty().append('<option value="">-- Select District --</option>').prop('disabled', true);
        }

        /**
         * Update navigation selects
         */
        updateNavigationSelects() {
            if (this.state.currentState) {
                $('#state-nav-select').val(this.state.currentState);
            }
            if (this.state.currentDistrict) {
                $('#district-nav-select').val(this.state.currentDistrict);
            }
        }

        /**
         * Load districts list
         */
        async loadDistrictsList(stateName) {
            try {
                const response = await fetch(`${methaneMonitor.restUrl}districts/${encodeURIComponent(stateName)}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const districts = await response.json();
                const select = $('#district-nav-select').empty().append('<option value="">-- Select District --</option>');
                
                if (districts && districts.length > 0) {
                    districts.forEach(district => {
                        select.append(`<option value="${district}">${district}</option>`);
                    });
                    select.prop('disabled', false);
                } else {
                    select.prop('disabled', true);
                }

                if (this.state.currentDistrict) {
                    select.val(this.state.currentDistrict);
                }
            } catch (error) {
                console.error('Error loading districts list:', error);
            }
        }

        /**
         * Reset analytics tabs
         */
        resetAnalyticsTabs(level = 'india') {
            const overviewTab = new bootstrap.Tab(document.getElementById('overview-tab'));
            overviewTab.show();

            $('#timeseries-tab, #clustering-tab').prop('disabled', true)
                .removeClass('active').attr('aria-selected', 'false');

            if (level === 'state' && this.state.currentState) {
                $('#clustering-tab').prop('disabled', false);
            } else if (level === 'district' && this.state.currentState && this.state.currentDistrict) {
                $('#timeseries-tab').prop('disabled', false);
            }
        }

        /**
         * Handle analytics tab change
         */
        handleAnalyticsTabChange(tabButtonId) {
            switch (tabButtonId) {
                case 'timeseries-tab':
                    if (this.state.currentState && this.state.currentDistrict) {
                        this.loadTimeSeriesAnalysis(this.state.currentState, this.state.currentDistrict);
                    }
                    break;
                case 'clustering-tab':
                    if (this.state.currentState) {
                        this.loadClusteringAnalysis(this.state.currentState);
                    }
                    break;
            }
        }

        /**
         * Load time series analysis
         */
        async loadTimeSeriesAnalysis(state, district) {
            $('#timeseries-location-display').text(`${district}, ${state}`);
            const chartDiv = $('#timeseries-chart');
            
            chartDiv.html('<div class="text-center mt-5"><div class="spinner-border text-primary"></div><p>Loading chart...</p></div>');
            $('#timeseries-stats').empty();

            try {
                const response = await fetch(`${methaneMonitor.restUrl}analytics/timeseries/${encodeURIComponent(state)}/${encodeURIComponent(district)}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (!data || data.error || !data.time_series) {
                    chartDiv.html(`<p class="text-center text-warning mt-5">${data?.error || 'No time series data available.'}</p>`);
                    return;
                }

                if (data.time_series.length > 0) {
                    this.plotTimeSeries(data);
                } else {
                    chartDiv.html('<p class="text-center text-muted mt-5">No time series data points for this period.</p>');
                }
            } catch (error) {
                console.error('Error loading time series:', error);
                chartDiv.html('<p class="text-center text-danger mt-5">Error loading time series data.</p>');
            }
        }

        /**
         * Plot time series using Plotly
         */
        plotTimeSeries(data) {
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
                    text: `Methane Time Series - ${this.state.currentDistrict}, ${this.state.currentState}`,
                    font: { size: 16 }
                },
                xaxis: { title: 'Date', type: 'date', automargin: true },
                yaxis: { title: 'CH₄ (ppb)', automargin: true },
                margin: { t: 50, l: 60, r: 30, b: 60 },
                hovermode: 'x unified',
                plot_bgcolor: 'rgba(0,0,0,0)',
                paper_bgcolor: 'rgba(0,0,0,0)'
            };

            Plotly.newPlot('timeseries-chart', [trace], layout, { 
                responsive: true, 
                displayModeBar: false 
            });

            // Update statistics
            if (data.statistics) {
                const stats = data.statistics;
                const trendIndicator = stats.trend_slope > 0.01 ? '📈 Up' : 
                                     (stats.trend_slope < -0.01 ? '📉 Down' : '➡️ Stable');
                const peakMonth = data.peak_month ? methaneMonitor.strings[this.getMonthName(data.peak_month)] : 'N/A';
                
                const statsHtml = `
                    <strong>Avg:</strong> ${parseFloat(stats.mean).toFixed(1)} | 
                    <strong>Std:</strong> ${parseFloat(stats.std).toFixed(1)} | 
                    <strong>Trend:</strong> ${trendIndicator} (${parseFloat(stats.trend_slope).toFixed(2)}) | 
                    <strong>Peak:</strong> ${peakMonth}
                `;
                $('#timeseries-stats').html(statsHtml);
            }
        }

        /**
         * Load clustering analysis
         */
        async loadClusteringAnalysis(state) {
            $('#clustering-location-display').text(state);
            const tableContainer = $('#clustering-table-container');
            const clusteringInfoDiv = $('#clustering-info');
            
            tableContainer.html('<div class="text-center mt-5"><div class="spinner-border text-primary"></div><p>Loading clustering data...</p></div>');
            clusteringInfoDiv.html('Loading analysis info...');

            try {
                const response = await fetch(`${methaneMonitor.restUrl}analytics/clustering/${encodeURIComponent(state)}`, {
                    headers: {
                        'X-WP-Nonce': methaneMonitor.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                tableContainer.html(`
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="clustering-table">
                            <thead>
                                <tr>
                                    <th>District</th>
                                    <th>Cluster</th>
                                    <th>Avg CH₄ (ppb)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                `);

                const tableBody = $('#clustering-table tbody');

                if (!data || data.error || !data.district_clusters) {
                    tableBody.html(`<tr><td colspan="3" class="text-center text-warning">${data?.error || 'No clustering data available.'}</td></tr>`);
                    clusteringInfoDiv.html(`<span class="text-warning">${data?.error || 'Analysis info unavailable.'}</span>`);
                    return;
                }

                if (data.district_clusters.length > 0) {
                    clusteringInfoDiv.html(`Districts grouped into <strong>${data.n_clusters}</strong> clusters. Inertia: ${parseFloat(data.inertia).toFixed(2)}`);
                    
                    const sorted = data.district_clusters.sort((a, b) => 
                        (a.cluster !== b.cluster) ? a.cluster - b.cluster : b.average_methane - a.average_methane
                    );

                    sorted.forEach(dc => {
                        tableBody.append(`
                            <tr>
                                <td>${dc.district}</td>
                                <td><span class="badge bg-secondary">Cluster ${dc.cluster}</span></td>
                                <td class="text-end">${parseFloat(dc.average_methane).toFixed(1)}</td>
                            </tr>
                        `);
                    });
                } else {
                    tableBody.append('<tr><td colspan="3" class="text-center text-muted">No clustering data.</td></tr>');
                    clusteringInfoDiv.html('No clustering data to analyze.');
                }
            } catch (error) {
                console.error('Error loading clustering:', error);
                tableContainer.html('<p class="text-danger text-center mt-3">Error loading clustering.</p>');
                clusteringInfoDiv.html('<span class="text-danger">Error loading info.</span>');
            }
        }

        /**
         * Setup analytics
         */
        setupAnalytics() {
            // Initialize any analytics-specific functionality
            console.log('Analytics setup completed');
        }

        /**
         * Reset to India view
         */
        resetToIndiaView() {
            this.state.currentLevel = 'india';
            this.state.currentState = null;
            this.state.currentDistrict = null;
            $('#heatmap-toggle').prop('checked', false);
            this.state.useHeatmap = false;
            this.loadIndiaView();
        }

        /**
         * Export data
         */
        async exportData(format) {
            try {
                let url = `${methaneMonitor.ajaxUrl}?action=methane_export_data&format=${format}&level=${this.state.currentLevel}&nonce=${methaneMonitor.nonce}`;
                
                if (this.state.currentState) {
                    url += `&state=${encodeURIComponent(this.state.currentState)}`;
                }
                if (this.state.currentDistrict) {
                    url += `&district=${encodeURIComponent(this.state.currentDistrict)}`;
                }
                if (this.state.currentYear && this.state.currentMonth) {
                    url += `&year=${this.state.currentYear}&month=${this.state.currentMonth}`;
                }

                window.open(url, '_blank');
            } catch (error) {
                console.error('Export error:', error);
                this.showError('Export failed');
            }
        }

        /**
         * Toggle fullscreen
         */
        toggleFullscreen() {
            const mapContainer = document.getElementById(this.config.mapId).parentElement;
            
            if (!document.fullscreenElement) {
                mapContainer.requestFullscreen().then(() => {
                    setTimeout(() => this.state.map.invalidateSize(), 100);
                });
            } else {
                document.exitFullscreen();
            }
        }

        /**
         * Utility methods
         */
        getMonthName(monthNumber) {
            const months = {
                1: 'january', 2: 'february', 3: 'march', 4: 'april',
                5: 'may', 6: 'june', 7: 'july', 8: 'august',
                9: 'september', 10: 'october', 11: 'november', 12: 'december'
            };
            return months[monthNumber] || 'january';
        }

        showLoading(text = 'Loading...') {
            $('#methane-loading-text').text(text);
            $('#methane-loading-overlay').fadeIn(150);
        }

        hideLoading() {
            $('#methane-loading-overlay').fadeOut(200);
        }

        showError(message) {
            console.error('Methane Monitor Error:', message);
            const alertId = `alert-${Date.now()}`;
            const alertHtml = `
                <div id="${alertId}" class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3"
                     style="z-index: 10001; max-width: 400px;" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertHtml);
            setTimeout(() => $(`#${alertId}`).alert('close'), 5000);
        }

        showSuccess(message) {
            console.log('Methane Monitor Success:', message);
            const alertId = `alert-success-${Date.now()}`;
            const alertHtml = `
                <div id="${alertId}" class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3"
                     style="z-index: 10001; max-width: 400px;" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertHtml);
            setTimeout(() => $(`#${alertId}`).alert('close'), 3000);
        }
    }

    // Make MethaneMonitorApp globally available
    window.MethaneMonitorApp = MethaneMonitorApp;

    // Auto-initialize if container exists
    $(document).ready(function() {
        if ($('#methane-monitor-container').length > 0) {
            window.methaneMonitorInstance = new MethaneMonitorApp({
                containerId: 'methane-monitor-container',
                mapId: 'methane-map'
            });
        }
    });

})(jQuery);