<?php
/**
 * Admin Data Management Template
 * Expected: $states (array), $this (Methane_Monitor_Admin instance)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap">
    <h1><?php _e('Data Management', 'methane-monitor'); ?></h1>
    <div class="methane-data-management">
        <div class="upload-section">
            <h2><?php _e('Upload Data Files', 'methane-monitor'); ?></h2>
            <form id="methane-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('methane_upload_data_nonce', '_wpnonce_methane_upload'); // Custom nonce for this form ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="state_name_upload"><?php _e('State Name', 'methane-monitor'); ?></label></th>
                        <td>
                            <select name="state_name" id="state_name_upload" required>
                                <option value=""><?php _e('Select State', 'methane-monitor'); ?></option>
                                <?php if (!empty($states)): foreach ($states as $state_item): ?>
                                <option value="<?php echo esc_attr($state_item['state_name']); ?>"><?php echo esc_html($state_item['state_name']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="district_name_upload"><?php _e('District Name', 'methane-monitor'); ?></label></th>
                        <td><input type="text" name="district_name" id="district_name_upload" required placeholder="<?php esc_attr_e('Enter district name', 'methane-monitor'); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="data_files_upload"><?php _e('Data Files', 'methane-monitor'); ?></label></th>
                        <td>
                            <input type="file" name="files[]" id="data_files_upload" multiple accept=".xlsx,.xls,.csv" required>
                            <p class="description"><?php _e('Select Excel or CSV files. Format: latitude, longitude, YYYY_MM_DD columns.', 'methane-monitor'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e('Upload and Process', 'methane-monitor'); ?>"></p>
            </form>
            <div id="upload-progress" style="display: none;">
                <div class="upload-progress-bar"><div class="progress-fill"></div></div>
                <div class="upload-status"></div>
            </div>
        </div>
        <div class="data-overview">
            <h2><?php _e('Data Overview', 'methane-monitor'); ?></h2>
            <?php $this->render_data_overview_table_content(); // Assumes method in Admin class ?>
        </div>
        <div class="bulk-operations">
            <h2><?php _e('Bulk Operations', 'methane-monitor'); ?></h2>
            <div class="bulk-action-buttons">
                <button type="button" class="button" id="export-all-data"><?php _e('Export All Data (CSV)', 'methane-monitor'); ?></button>
                <button type="button" class="button button-secondary" id="cleanup-old-data"><?php _e('Cleanup Old Data', 'methane-monitor'); ?></button>
                <button type="button" class="button button-secondary" id="validate-data"><?php _e('Validate Data Integrity', 'methane-monitor'); ?></button>
            </div>
        </div>
    </div>
</div>