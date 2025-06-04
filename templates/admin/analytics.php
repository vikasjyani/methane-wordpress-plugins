<?php
/**
 * Admin Analytics Page Template
 * Expected: $states (array), $months (array)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$current_year = date('Y');
$current_month_num = date('n');
?>
<div class="wrap">
    <h1><?php _e('Analytics Dashboard', 'methane-monitor'); ?></h1>
    <div class="methane-analytics-admin">
        <div class="analytics-controls">
            <h2><?php _e('Generate Analytics', 'methane-monitor'); ?></h2>
            <form id="analytics-form">
                <?php wp_nonce_field('methane_get_analytics_nonce', '_wpnonce_methane_analytics'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="analysis_type"><?php _e('Analysis Type', 'methane-monitor'); ?></label></th>
                        <td>
                            <select name="type" id="analysis_type"> <option value="ranking"><?php _e('State/District Rankings', 'methane-monitor'); ?></option>
                                <option value="timeseries"><?php _e('Time Series Analysis', 'methane-monitor'); ?></option>
                                <option value="clustering"><?php _e('District Clustering', 'methane-monitor'); ?></option>
                                <option value="correlation"><?php _e('Correlation Analysis', 'methane-monitor'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="analytics_state_select"><?php _e('State', 'methane-monitor'); ?></label></th>
                        <td>
                            <select name="state" id="analytics_state_select">
                                <option value=""><?php _e('All States / Select for specific analytics', 'methane-monitor'); ?></option>
                                <?php if (!empty($states)): foreach ($states as $state_item): ?>
                                <option value="<?php echo esc_attr($state_item['state_name']); ?>"><?php echo esc_html($state_item['state_name']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                             <p class="description"><?php _e('Required for Time Series, Clustering, and Correlation.', 'methane-monitor'); ?></p>
                        </td>
                    </tr>
                     <tr>
                        <th scope="row"><label for="analytics_district_select"><?php _e('District (for Time Series)', 'methane-monitor'); ?></label></th>
                        <td>
                            <input type="text" name="district" id="analytics_district_select" class="regular-text" placeholder="<?php esc_attr_e('Enter district name if state selected', 'methane-monitor'); ?>">
                             <p class="description"><?php _e('Required for Time Series analysis.', 'methane-monitor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="analytics_year_select"><?php _e('Time Period (for Rankings)', 'methane-monitor'); ?></label></th>
                        <td>
                            <select name="year" id="analytics_year_select">
                                <?php for ($year_option = $current_year; $year_option >= 2014; $year_option--): ?>
                                <option value="<?php echo esc_attr($year_option); ?>"><?php echo esc_html($year_option); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="month" id="analytics_month_select">
                                <?php if(!empty($months)): foreach ($months as $num => $name): ?>
                                <option value="<?php echo esc_attr($num); ?>" <?php selected($num, $current_month_num); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                            <p class="description"><?php _e('Primarily used for Ranking analysis.', 'methane-monitor'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e('Generate Analytics', 'methane-monitor'); ?>"></p>
            </form>
        </div>
        <div class="analytics-results" id="analytics-results" style="display: none;">
            <h2><?php _e('Analysis Results', 'methane-monitor'); ?></h2>
            <div id="analytics-content"><p><?php _e('Results will appear here.', 'methane-monitor'); ?></p></div>
        </div>
    </div>
</div>