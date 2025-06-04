# Methane Monitor - India Emissions Tracker

A comprehensive WordPress plugin for monitoring and visualizing methane emissions across Indian states and districts. Features interactive maps, analytics, and data management capabilities.

## Features

### 🗺️ Interactive Mapping
- **Multi-level Navigation**: Seamlessly navigate from India → State → District levels
- **Choropleth Visualization**: Color-coded regional emission data
- **Heatmap Support**: Contour-based visualization for detailed analysis
- **Real-time Filtering**: Dynamic time period selection (2014-2023)

### 📊 Advanced Analytics
- **Time Series Analysis**: Track emission trends over time for specific districts
- **District Clustering**: Identify emission patterns and group similar districts
- **State Rankings**: Compare states and districts by emission levels
- **Statistical Summaries**: Mean, median, range, and standard deviation calculations

### 🔧 Admin Features
- **Data Upload**: Secure Excel/CSV file processing
- **Dashboard Overview**: System statistics and recent activity
- **Cache Management**: Performance optimization controls
- **Export Functionality**: Download data in multiple formats

### 🎨 User Experience
- **Responsive Design**: Mobile-friendly interface
- **Multiple Themes**: Light and dark mode support
- **Color Schemes**: Viridis, Plasma, and Inferno palettes
- **Accessibility**: WCAG-compliant design patterns

## Installation

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- 256MB RAM (recommended)

### Step 1: Prepare Your Data

Organize your Excel files in this structure:
```
data/
├── ANDHRA_PRADESH/
│   ├── DISTRICT1.xlsx
│   ├── DISTRICT2.xlsx
│   └── ...
├── RAJASTHAN/
│   ├── DISTRICT1.xlsx
│   ├── DISTRICT2.xlsx
│   └── ...
└── ...
```

Each Excel file should have:
- Column A: `latitude`
- Column B: `longitude`
- Column C onwards: `2014_01_01`, `2014_02_01`, etc.

### Step 2: Process Data

Run the preprocessing script:
```bash
python data_processor.py --input data --output data_preprocessed
```

### Step 3: Install Plugin

1. Upload plugin files to `/wp-content/plugins/methane-monitor/`
2. Install Composer dependencies:
   ```bash
   cd /wp-content/plugins/methane-monitor/
   composer install
   ```
3. Copy preprocessed data:
   ```bash
   cp -r data_preprocessed/* /wp-content/uploads/methane-monitor/
   ```
4. Activate the plugin in WordPress admin

### Step 4: Configure Plugin

1. Go to **Methane Monitor > Settings**
2. Configure basic settings:
   - Enable caching (recommended)
   - Set cache duration (3600 seconds default)
   - Configure map defaults
   - Set file upload limits

## Usage

### Basic Implementation

Add the shortcode to any page or post:
```
[methane_monitor]
```

### Advanced Usage

```
[methane_monitor 
    height="600px" 
    initial_state="RAJASTHAN" 
    show_analytics="true"
    color_scheme="plasma"
    theme="dark"
]
```

### Shortcode Parameters

| Parameter | Description | Default | Options |
|-----------|-------------|---------|---------|
| `height` | Map height | `70vh` | Any CSS height value |
| `width` | Map width | `100%` | Any CSS width value |
| `initial_level` | Starting view | `india` | `india`, `state`, `district` |
| `initial_state` | Initial state | `""` | Any state name |
| `initial_district` | Initial district | `""` | Any district name |
| `initial_year` | Starting year | Current year | `2014-2023` |
| `initial_month` | Starting month | Current month | `1-12` |
| `show_controls` | Show control panel | `true` | `true`, `false` |
| `show_analytics` | Show analytics tabs | `true` | `true`, `false` |
| `theme` | Color theme | `light` | `light`, `dark` |
| `color_scheme` | Color palette | `viridis` | `viridis`, `plasma`, `inferno` |
| `zoom_controls` | Map zoom controls | `true` | `true`, `false` |
| `enable_export` | Export functionality | `false` | `true`, `false` |

## REST API

The plugin provides REST API endpoints:

### Data Endpoints
- `GET /wp-json/methane-monitor/v1/metadata`
- `GET /wp-json/methane-monitor/v1/india/{year}/{month}`
- `GET /wp-json/methane-monitor/v1/state/{state}/{year}/{month}`
- `GET /wp-json/methane-monitor/v1/district/{state}/{district}/{year}/{month}`

### Analytics Endpoints
- `GET /wp-json/methane-monitor/v1/analytics/timeseries/{state}/{district}`
- `GET /wp-json/methane-monitor/v1/analytics/clustering/{state}`
- `GET /wp-json/methane-monitor/v1/analytics/ranking/{year}/{month}`

### Admin Endpoints (Requires Authentication)
- `POST /wp-json/methane-monitor/v1/admin/clear-cache`
- `POST /wp-json/methane-monitor/v1/admin/upload`

## Development

### File Structure

```
methane-monitor/
├── methane-monitor-plugin.php     # Main plugin file
├── composer.json                  # Dependencies
├── uninstall.php                  # Cleanup script
├── includes/                      # Core PHP classes
│   ├── class-database.php
│   ├── class-data-processor.php
│   ├── class-rest-api.php
│   ├── class-admin.php
│   ├── class-frontend.php
│   ├── class-shortcodes.php
│   ├── class-ajax-handlers.php
│   ├── class-analytics.php
│   └── functions.php
├── assets/                        # Frontend assets
│   ├── css/
│   ├── js/
│   └── images/
├── templates/                     # HTML templates
├── languages/                     # Translation files
└── vendor/                        # Composer dependencies
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Code Standards

- Follow WordPress Coding Standards
- Use PSR-4 autoloading
- Include PHPDoc comments
- Write unit tests for new features

## Performance Optimization

### Caching Strategy
- Enable plugin caching in settings
- Use object caching (Redis/Memcached) if available
- Implement CDN for static assets
- Enable GZIP compression

### Database Optimization
- Proper indexing on spatial columns
- Use prepared statements
- Batch operations for large datasets
- Regular cleanup of expired cache

### Frontend Optimization
- Conditional asset loading
- Minified CSS/JS in production
- Lazy loading for large datasets
- Progressive enhancement

## Troubleshooting

### Common Issues

**"No data found"**
- Check data directory permissions
- Verify preprocessed data location
- Check WordPress debug log

**"Map not loading"**
- Ensure Leaflet.js is loading
- Check for JavaScript conflicts
- Verify Bootstrap compatibility

**"Upload fails"**
- Increase PHP upload limits
- Check file permissions
- Verify allowed file types

### Debug Mode

Enable WordPress debug in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log` for Methane Monitor errors.

## System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher  
- **WordPress**: 5.0 or higher
- **Memory**: 256MB (recommended)
- **Upload Limit**: 50MB (for Excel files)

### Browser Requirements
- Modern browsers supporting ES6
- JavaScript enabled
- Canvas support for map rendering

### Dependencies
- **Leaflet.js**: Interactive maps
- **Plotly.js**: Chart visualization
- **Chroma.js**: Color scale generation
- **Bootstrap 5**: UI components
- **PhpSpreadsheet**: Excel processing

## License

This plugin is licensed under GPL v2 or later. See [LICENSE](LICENSE) for details.

## Support

For support and updates:
- Check WordPress admin dashboard for notifications
- Review debug logs for technical issues
- Verify data format requirements
- Ensure all dependencies are loaded

## Changelog

### Version 1.0.0
- Initial release
- Multi-level interactive mapping
- Advanced analytics features
- Admin dashboard and data management
- REST API endpoints
- Comprehensive caching system

## Credits

Developed by [Vasudha Foundation](https://vasudha-foundation.org) for environmental monitoring and research.

## Screenshots

[Add screenshots of the plugin in action]

1. **Interactive Map**: Main visualization interface
2. **Analytics Dashboard**: Time series and clustering analysis
3. **Admin Panel**: Data management and settings
4. **Mobile View**: Responsive design demonstration