<?php
/**
 * PPM (Public Procurement Manual) management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_PPM {
    
    public function __construct() {
        // Admin form handling actions
        add_action('admin_post_upload_ppm', array($this, 'handle_ppm_upload'));
        add_action('admin_post_update_ppm_notes', array($this, 'handle_update_ppm_notes'));
        add_action('admin_post_set_current_ppm', array($this, 'handle_set_current_ppm'));
        add_action('admin_post_delete_ppm_version', array($this, 'handle_delete_ppm_version'));
        
        // Public download tracking
        add_action('wp_ajax_track_ppm_download', array($this, 'track_ppm_download'));
        add_action('wp_ajax_nopriv_track_ppm_download', array($this, 'track_ppm_download'));
        
        // Shortcodes
        add_shortcode('ppm_viewer', array($this, 'ppm_viewer_shortcode'));
        add_shortcode('current_ppm', array($this, 'current_ppm_shortcode'));
    }
    
    public function handle_ppm_upload() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        // Validate required fields
        if (empty($_POST['title']) || empty($_POST['version'])) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'missing_fields'), admin_url('admin.php')));
            exit;
        }
        
        // Check if file was uploaded
        if (empty($_FILES['ppm_document']['name'])) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'no_file'), admin_url('admin.php')));
            exit;
        }
        
        // Validate file type
        $allowed_types = array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $file_type = $_FILES['ppm_document']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'invalid_file'), admin_url('admin.php')));
            exit;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_ppm';
        
        // Check if version already exists
        $existing_version = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE version = %s",
            sanitize_text_field($_POST['version'])
        ));
        
        if ($existing_version) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'version_exists'), admin_url('admin.php')));
            exit;
        }
        
        // Handle file upload
        $upload_result = $this->handle_ppm_file_upload($_FILES['ppm_document']);
        
        if (is_wp_error($upload_result)) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'upload_failed'), admin_url('admin.php')));
            exit;
        }
        
        // Set all other versions as not current if this is marked as current
        $is_current = isset($_POST['set_as_current']) ? 1 : 0;
        if ($is_current) {
            $wpdb->update($table, array('is_current' => 0), array('is_current' => 1));
        }
        
        // Insert PPM record
        $result = $wpdb->insert(
            $table,
            array(
                'title' => sanitize_text_field($_POST['title']),
                'version' => sanitize_text_field($_POST['version']),
                'document_path' => $upload_result['path'],
                'file_size' => $upload_result['size'],
                'mime_type' => $upload_result['type'],
                'uploaded_by' => get_current_user_id(),
                'is_current' => $is_current,
                'admin_notes' => sanitize_textarea_field($_POST['admin_notes'] ?? '')
            )
        );
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'save_failed'), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'success' => 'uploaded'), admin_url('admin.php')));
        exit;
    }
    
    private function handle_ppm_file_upload($file) {
        $upload_dir = wp_upload_dir();
        $ppm_dir = $upload_dir['basedir'] . '/procurement/ppm';
        
        if (!file_exists($ppm_dir)) {
            wp_mkdir_p($ppm_dir);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'ppm_' . time() . '_' . wp_generate_password(8, false) . '.' . $file_extension;
        $filepath = $ppm_dir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', __('Failed to upload file', 'procurement-mgmt'));
        }
        
        return array(
            'path' => $filepath,
            'url' => $upload_dir['baseurl'] . '/procurement/ppm/' . $filename,
            'size' => $file['size'],
            'type' => $file['type'],
            'name' => $filename
        );
    }
    
    public function handle_update_ppm_notes() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $ppm_id = intval($_POST['ppm_id']);
        $admin_notes = sanitize_textarea_field($_POST['admin_notes']);
        $table = $wpdb->prefix . 'procurement_ppm';
        
        $result = $wpdb->update(
            $table,
            array('admin_notes' => $admin_notes),
            array('id' => $ppm_id)
        );
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'update_failed'), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'success' => 'notes_updated'), admin_url('admin.php')));
        exit;
    }
    
    public function handle_set_current_ppm() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $ppm_id = intval($_POST['ppm_id']);
        $table = $wpdb->prefix . 'procurement_ppm';
        
        // Set all versions as not current
        $wpdb->update($table, array('is_current' => 0), array('is_current' => 1));
        
        // Set selected version as current
        $result = $wpdb->update(
            $table,
            array('is_current' => 1),
            array('id' => $ppm_id)
        );
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'set_current_failed'), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'success' => 'current_set'), admin_url('admin.php')));
        exit;
    }
    
    public function handle_delete_ppm_version() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $ppm_id = intval($_POST['ppm_id']);
        $table = $wpdb->prefix . 'procurement_ppm';
        
        // Get PPM details before deletion
        $ppm = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $ppm_id
        ));
        
        if (!$ppm) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'version_not_found'), admin_url('admin.php')));
            exit;
        }
        
        // Don't allow deletion of current version
        if ($ppm->is_current) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'cannot_delete_current'), admin_url('admin.php')));
            exit;
        }
        
        // Delete file
        if (file_exists($ppm->document_path)) {
            unlink($ppm->document_path);
        }
        
        // Delete database record
        $result = $wpdb->delete($table, array('id' => $ppm_id));
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'error' => 'delete_failed'), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array('page' => 'procurement-ppm', 'success' => 'version_deleted'), admin_url('admin.php')));
        exit;
    }
    
    public function track_ppm_download() {
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            return;
        }
        
        global $wpdb;
        $ppm_id = intval($_POST['ppm_id']);
        $table = $wpdb->prefix . 'procurement_ppm';
        
        // Increment download count
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET download_count = download_count + 1 WHERE id = %d",
            $ppm_id
        ));
        
        wp_send_json_success();
    }
    
    public function current_ppm_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_title' => 'true',
            'show_version' => 'true',
            'show_date' => 'true'
        ), $atts);
        
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_ppm';
        
        $current_ppm = $wpdb->get_row(
            "SELECT * FROM $table WHERE is_current = 1 ORDER BY upload_date DESC LIMIT 1"
        );
        
        if (!$current_ppm) {
            return '<p class="no-ppm">' . __('No current PPM document available.', 'procurement-mgmt') . '</p>';
        }
        
        $upload_dir = wp_upload_dir();
        $download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $current_ppm->document_path);
        
        ob_start();
        ?>
        <div class="current-ppm-widget">
            <div class="ppm-header">
                <h3><?php _e('Current Public Procurement Manual', 'procurement-mgmt'); ?></h3>
            </div>
            
            <div class="ppm-info">
                <?php if ($atts['show_title'] === 'true'): ?>
                    <h4 class="ppm-title"><?php echo esc_html($current_ppm->title); ?></h4>
                <?php endif; ?>
                
                <div class="ppm-meta">
                    <?php if ($atts['show_version'] === 'true'): ?>
                        <span class="ppm-version">
                            <strong><?php _e('Version:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($current_ppm->version); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_date'] === 'true'): ?>
                        <span class="ppm-date">
                            <strong><?php _e('Updated:', 'procurement-mgmt'); ?></strong> <?php echo date('F j, Y', strtotime($current_ppm->upload_date)); ?>
                        </span>
                    <?php endif; ?>
                    
                    <span class="ppm-size">
                        <strong><?php _e('Size:', 'procurement-mgmt'); ?></strong> <?php echo size_format($current_ppm->file_size); ?>
                    </span>
                </div>
            </div>
            
            <div class="ppm-actions">
                <a href="<?php echo esc_url($download_url); ?>" 
                   class="btn btn-primary ppm-download-btn" 
                   data-ppm-id="<?php echo $current_ppm->id; ?>"
                   target="_blank">
                    <span class="download-icon">📄</span>
                    <?php _e('Download PPM', 'procurement-mgmt'); ?>
                </a>
                
                <div class="download-stats">
                    <small><?php printf(__('Downloaded %d times', 'procurement-mgmt'), $current_ppm->download_count); ?></small>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ppm-download-btn').on('click', function() {
                var ppmId = $(this).data('ppm-id');
                
                // Track download
                $.post(procurement_ajax.ajax_url, {
                    action: 'track_ppm_download',
                    ppm_id: ppmId,
                    nonce: procurement_ajax.nonce
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function ppm_viewer_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_history' => 'true',
            'items_per_page' => '10'
        ), $atts);
        
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_ppm';
        
        // Get current PPM
        $current_ppm = $wpdb->get_row(
            "SELECT * FROM $table WHERE is_current = 1 ORDER BY upload_date DESC LIMIT 1"
        );
        
        // Get all PPM versions for history
        $all_versions = array();
        if ($atts['show_history'] === 'true') {
            $limit = intval($atts['items_per_page']);
            $all_versions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table ORDER BY upload_date DESC LIMIT %d",
                $limit
            ));
        }
        
        ob_start();
        ?>
        <div class="procurement-ppm-viewer">
            <?php if ($current_ppm): ?>
                <div class="ppm-current-section">
                    <div class="section-header">
                        <h3><?php _e('Current Public Procurement Manual', 'procurement-mgmt'); ?></h3>
                        <span class="current-badge"><?php _e('Current Version', 'procurement-mgmt'); ?></span>
                    </div>
                    
                    <div class="ppm-current-card">
                        <div class="ppm-info">
                            <h4><?php echo esc_html($current_ppm->title); ?></h4>
                            <div class="ppm-meta-grid">
                                <div class="meta-item">
                                    <strong><?php _e('Version:', 'procurement-mgmt'); ?></strong>
                                    <span><?php echo esc_html($current_ppm->version); ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e('Published:', 'procurement-mgmt'); ?></strong>
                                    <span><?php echo date('F j, Y', strtotime($current_ppm->upload_date)); ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e('File Size:', 'procurement-mgmt'); ?></strong>
                                    <span><?php echo size_format($current_ppm->file_size); ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e('Downloads:', 'procurement-mgmt'); ?></strong>
                                    <span><?php echo number_format($current_ppm->download_count); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ppm-actions">
                            <?php
                            $upload_dir = wp_upload_dir();
                            $download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $current_ppm->document_path);
                            ?>
                            <a href="<?php echo esc_url($download_url); ?>" 
                               class="btn btn-primary btn-large ppm-download-link" 
                               data-ppm-id="<?php echo $current_ppm->id; ?>"
                               target="_blank">
                                <span class="download-icon">📄</span>
                                <?php _e('Download Current PPM', 'procurement-mgmt'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-ppm-message">
                    <h3><?php _e('Public Procurement Manual', 'procurement-mgmt'); ?></h3>
                    <p><?php _e('No PPM document is currently available. Please check back later.', 'procurement-mgmt'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_history'] === 'true' && !empty($all_versions)): ?>
                <div class="ppm-history-section">
                    <h3><?php _e('Document History', 'procurement-mgmt'); ?></h3>
                    <p class="history-description">
                        <?php _e('Access previous versions of the Public Procurement Manual for reference.', 'procurement-mgmt'); ?>
                    </p>
                    
                    <div class="ppm-versions-table">
                        <table class="ppm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Version', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Title', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Published', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Size', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Downloads', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Status', 'procurement-mgmt'); ?></th>
                                    <th><?php _e('Action', 'procurement-mgmt'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_versions as $version): ?>
                                    <tr class="<?php echo $version->is_current ? 'current-version' : ''; ?>">
                                        <td>
                                            <strong><?php echo esc_html($version->version); ?></strong>
                                        </td>
                                        <td><?php echo esc_html($version->title); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($version->upload_date)); ?></td>
                                        <td><?php echo size_format($version->file_size); ?></td>
                                        <td><?php echo number_format($version->download_count); ?></td>
                                        <td>
                                            <?php if ($version->is_current): ?>
                                                <span class="status-badge status-current"><?php _e('Current', 'procurement-mgmt'); ?></span>
                                            <?php else: ?>
                                                <span class="status-badge status-archived"><?php _e('Archived', 'procurement-mgmt'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $version_download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $version->document_path);
                                            ?>
                                            <a href="<?php echo esc_url($version_download_url); ?>" 
                                               class="btn btn-sm btn-outline ppm-download-link" 
                                               data-ppm-id="<?php echo $version->id; ?>"
                                               target="_blank">
                                                <?php _e('Download', 'procurement-mgmt'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ppm-download-link').on('click', function() {
                var ppmId = $(this).data('ppm-id');
                
                // Track download
                $.post(procurement_ajax.ajax_url, {
                    action: 'track_ppm_download',
                    ppm_id: ppmId,
                    nonce: procurement_ajax.nonce
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
