<?php
/**
 * Admin functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers for admin
        add_action('wp_ajax_get_bid_details', array($this, 'get_bid_details_ajax'));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'procurement') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Procurement Management', 'procurement-mgmt'),
            __('Procurement', 'procurement-mgmt'),
            'manage_options',
            'procurement-management',
            array($this, 'admin_page'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'procurement-management',
            __('Suppliers', 'procurement-mgmt'),
            __('Suppliers', 'procurement-mgmt'),
            'manage_options',
            'procurement-suppliers',
            array($this, 'suppliers_page')
        );
        
        add_submenu_page(
            'procurement-management',
            __('Tenders', 'procurement-mgmt'),
            __('Tenders', 'procurement-mgmt'),
            'manage_options',
            'procurement-tenders',
            array($this, 'tenders_page')
        );
        
        add_submenu_page(
            'procurement-management',
            __('PPM Documents', 'procurement-mgmt'),
            __('PPM Documents', 'procurement-mgmt'),
            'manage_options',
            'procurement-ppm',
            array($this, 'ppm_page')
        );
        
        add_submenu_page(
            'procurement-management',
            __('Bids', 'procurement-mgmt'),
            __('Bids', 'procurement-mgmt'),
            'manage_options',
            'procurement-bids',
            array($this, 'bids_page')
        );
    }
    
    public function admin_init() {
        // Register settings if needed
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Procurement Management Dashboard', 'procurement-mgmt'); ?></h1>
            
            <div class="procurement-admin-dashboard">
                <div class="dashboard-widgets">
                    <?php echo $this->get_dashboard_stats(); ?>
                </div>
                
                <div class="dashboard-content">
                    <div class="recent-activity">
                        <h2><?php _e('Recent Activity', 'procurement-mgmt'); ?></h2>
                        <?php echo $this->get_recent_activity(); ?>
                    </div>
                    
                    <div class="quick-actions">
                        <h2><?php _e('Quick Actions', 'procurement-mgmt'); ?></h2>
                        <?php echo $this->get_quick_actions(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .procurement-admin-dashboard {
            margin-top: 20px;
        }
        
        .dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-widget {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .widget-title {
            margin: 0 0 15px 0;
            font-size: 18px;
            font-weight: 600;
            color: #23282d;
        }
        
        .widget-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .widget-label {
            color: #646970;
            font-size: 14px;
        }
        
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .recent-activity,
        .quick-actions {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #646970;
            font-size: 12px;
        }
        
        .quick-action-btn {
            display: block;
            width: 100%;
            margin-bottom: 10px;
            padding: 10px 15px;
            text-align: left;
            text-decoration: none;
            background: #f6f7f7;
            border: 1px solid #ddd;
            border-radius: 3px;
            color: #0073aa;
        }
        
        .quick-action-btn:hover {
            background: #0073aa;
            color: white;
        }
        </style>
        <?php
    }
    
    public function ppm_page() {
        if (isset($_GET['success'])) {
            $success_messages = array(
                'uploaded' => __('PPM document uploaded successfully!', 'procurement-mgmt'),
                'notes_updated' => __('PPM notes updated successfully!', 'procurement-mgmt'),
                'current_set' => __('Current PPM version updated successfully!', 'procurement-mgmt'),
                'version_deleted' => __('PPM version deleted successfully!', 'procurement-mgmt')
            );
            if (isset($success_messages[$_GET['success']])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $success_messages[$_GET['success']] . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            $error_messages = array(
                'missing_fields' => __('Title and version are required.', 'procurement-mgmt'),
                'no_file' => __('Please select a PPM document to upload.', 'procurement-mgmt'),
                'invalid_file' => __('Only PDF and Word documents are allowed.', 'procurement-mgmt'),
                'version_exists' => __('This version already exists. Please use a different version number.', 'procurement-mgmt'),
                'upload_failed' => __('Failed to upload file.', 'procurement-mgmt'),
                'save_failed' => __('Failed to save PPM document.', 'procurement-mgmt'),
                'update_failed' => __('Failed to update notes.', 'procurement-mgmt'),
                'set_current_failed' => __('Failed to set current version.', 'procurement-mgmt'),
                'version_not_found' => __('PPM version not found.', 'procurement-mgmt'),
                'cannot_delete_current' => __('Cannot delete the current PPM version. Please set another version as current first.', 'procurement-mgmt'),
                'delete_failed' => __('Failed to delete PPM version.', 'procurement-mgmt')
            );
            if (isset($error_messages[$_GET['error']])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $error_messages[$_GET['error']] . '</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('PPM Document Management', 'procurement-mgmt'); ?></h1>
            
            <div class="procurement-ppm-admin">
                <div class="ppm-upload-section">
                    <h2><?php _e('Upload New PPM Version', 'procurement-mgmt'); ?></h2>
                    
                    <!-- Changed form to use admin-post.php instead of AJAX -->
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('procurement_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="upload_ppm">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ppm_title"><?php _e('Document Title', 'procurement-mgmt'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="ppm_title" name="title" class="regular-text" required>
                                    <p class="description"><?php _e('Enter a descriptive title for this PPM document', 'procurement-mgmt'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ppm_version"><?php _e('Version Number', 'procurement-mgmt'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="ppm_version" name="version" class="regular-text" required placeholder="e.g., 2.1, 2024.1">
                                    <p class="description"><?php _e('Version number must be unique', 'procurement-mgmt'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ppm_document"><?php _e('PPM Document', 'procurement-mgmt'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="ppm_document" name="ppm_document" accept=".pdf,.doc,.docx" required>
                                    <p class="description"><?php _e('Upload PDF or Word document (Max size: 10MB)', 'procurement-mgmt'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="admin_notes"><?php _e('Admin Notes', 'procurement-mgmt'); ?></label>
                                </th>
                                <td>
                                    <textarea id="admin_notes" name="admin_notes" rows="4" cols="50" class="large-text"></textarea>
                                    <p class="description"><?php _e('Internal notes about this version (not visible to public)', 'procurement-mgmt'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="set_as_current" value="1">
                                        <?php _e('Set as current version', 'procurement-mgmt'); ?>
                                    </label>
                                    <p class="description"><?php _e('Check this to make this version the current public PPM', 'procurement-mgmt'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Upload PPM Document', 'procurement-mgmt'); ?>">
                        </p>
                    </form>
                </div>
                
                <div class="ppm-versions-list">
                    <h2><?php _e('PPM Document Versions', 'procurement-mgmt'); ?></h2>
                    <?php echo $this->get_ppm_versions_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function suppliers_page() {
        global $wpdb;
        
        // Handle supplier approval/rejection
        if (isset($_POST['action']) && isset($_POST['supplier_id'])) {
            $supplier_id = intval($_POST['supplier_id']);
            $action = sanitize_text_field($_POST['action']);
            
            if ($action === 'approve') {
                $wpdb->update(
                    $wpdb->prefix . 'procurement_suppliers',
                    array('approval_status' => 'approved'),
                    array('id' => $supplier_id)
                );
                echo '<div class="notice notice-success"><p>Supplier approved successfully!</p></div>';
            } elseif ($action === 'reject') {
                $wpdb->update(
                    $wpdb->prefix . 'procurement_suppliers',
                    array('approval_status' => 'rejected'),
                    array('id' => $supplier_id)
                );
                echo '<div class="notice notice-success"><p>Supplier rejected successfully!</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Supplier Management', 'procurement-mgmt'); ?></h1>
            
            <div class="procurement-suppliers-admin">
                <?php echo $this->get_suppliers_table(); ?>
            </div>
        </div>
        <?php
    }
    
    public function tenders_page() {
        if (isset($_GET['success'])) {
            $success_messages = array(
                'created' => __('Tender created and published successfully!', 'procurement-mgmt'),
                'updated' => __('Tender updated successfully!', 'procurement-mgmt'),
                'published' => __('Tender published successfully!', 'procurement-mgmt')
            );
            if (isset($success_messages[$_GET['success']])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $success_messages[$_GET['success']] . '</p></div>';
            }
        }
        
        if (isset($_GET['error'])) {
            $error_messages = array(
                'missing_title' => __('Title is required.', 'procurement-mgmt'),
                'missing_description' => __('Description is required.', 'procurement-mgmt'),
                'missing_category' => __('Category is required.', 'procurement-mgmt'),
                'missing_estimated_value' => __('Estimated value is required.', 'procurement-mgmt'),
                'missing_requirements' => __('Requirements are required.', 'procurement-mgmt'),
                'missing_submission_deadline' => __('Submission deadline is required.', 'procurement-mgmt'),
                'create_failed' => __('Failed to create tender.', 'procurement-mgmt'),
                'update_failed' => __('Failed to update tender.', 'procurement-mgmt')
            );
            if (isset($error_messages[$_GET['error']])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $error_messages[$_GET['error']] . '</p></div>';
            }
        }
        
        global $wpdb;
        
        // Handle tender actions
        if (isset($_POST['action']) && isset($_POST['tender_id'])) {
            $tender_id = intval($_POST['tender_id']);
            $action = sanitize_text_field($_POST['action']);
            
            if ($action === 'publish') {
                $wpdb->update(
                    $wpdb->prefix . 'procurement_tenders',
                    array(
                        'status' => 'published',
                        'publication_date' => current_time('mysql')
                    ),
                    array('id' => $tender_id)
                );
                echo '<div class="notice notice-success"><p>Tender published successfully!</p></div>';
            } elseif ($action === 'close') {
                $wpdb->update(
                    $wpdb->prefix . 'procurement_tenders',
                    array('status' => 'closed'),
                    array('id' => $tender_id)
                );
                echo '<div class="notice notice-success"><p>Tender closed successfully!</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Tender Management', 'procurement-mgmt'); ?></h1>
            
            <div class="procurement-tenders-admin">
                <div class="tender-actions">
                    <a href="#" class="button button-primary" onclick="showCreateTenderForm()">Create New Tender</a>
                </div>
                
                <div id="create-tender-form" style="display: none; margin: 20px 0; padding: 20px; background: white; border: 1px solid #ccd0d4;">
                    <h2>Create New Tender</h2>
                    <!-- Changed form to use admin-post.php instead of current method -->
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('procurement_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="create_tender">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="tender_title">Title</label></th>
                                <td><input type="text" id="tender_title" name="title" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="tender_description">Description</label></th>
                                <td><textarea id="tender_description" name="description" rows="5" class="large-text" required></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="tender_requirements">Requirements</label></th>
                                <td><textarea id="tender_requirements" name="requirements" rows="5" class="large-text" required></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="tender_category">Category</label></th>
                                <td>
                                    <select id="tender_category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="construction">Construction</option>
                                        <option value="supplies">Supplies</option>
                                        <option value="services">Services</option>
                                        <option value="consulting">Consulting</option>
                                        <option value="technology">Technology</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="estimated_value">Estimated Value</label></th>
                                <td><input type="number" id="estimated_value" name="estimated_value" step="0.01" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="currency">Currency</label></th>
                                <td>
                                    <select id="currency" name="currency">
                                        <option value="XOF">XOF (West African CFA Franc)</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="deadline">Submission Deadline</label></th>
                                <td><input type="datetime-local" id="deadline" name="submission_deadline" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="tender_documents">Documents</label></th>
                                <td><input type="file" id="tender_documents" name="tender_documents[]" multiple accept=".pdf,.doc,.docx"></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Create & Publish Tender">
                            <button type="button" class="button" onclick="hideCreateTenderForm()">Cancel</button>
                        </p>
                    </form>
                </div>
                
                <?php echo $this->get_tenders_table(); ?>
            </div>
        </div>
        
        <script>
        function showCreateTenderForm() {
            document.getElementById('create-tender-form').style.display = 'block';
        }
        
        function hideCreateTenderForm() {
            document.getElementById('create-tender-form').style.display = 'none';
        }
        </script>
        <?php
    }
    
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array(
            'total_suppliers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_suppliers"),
            'pending_suppliers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_suppliers WHERE approval_status = 'pending'"),
            'active_tenders' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_tenders WHERE status = 'published'"),
            'total_bids' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids")
        );
        
        $html = '';
        $html .= '<div class="dashboard-widget">';
        $html .= '<h3 class="widget-title">Total Suppliers</h3>';
        $html .= '<div class="widget-number">' . $stats['total_suppliers'] . '</div>';
        $html .= '<div class="widget-label">Registered suppliers</div>';
        $html .= '</div>';
        
        $html .= '<div class="dashboard-widget">';
        $html .= '<h3 class="widget-title">Pending Approvals</h3>';
        $html .= '<div class="widget-number">' . $stats['pending_suppliers'] . '</div>';
        $html .= '<div class="widget-label">Suppliers awaiting approval</div>';
        $html .= '</div>';
        
        $html .= '<div class="dashboard-widget">';
        $html .= '<h3 class="widget-title">Active Tenders</h3>';
        $html .= '<div class="widget-number">' . $stats['active_tenders'] . '</div>';
        $html .= '<div class="widget-label">Currently published</div>';
        $html .= '</div>';
        
        $html .= '<div class="dashboard-widget">';
        $html .= '<h3 class="widget-title">Total Bids</h3>';
        $html .= '<div class="widget-number">' . $stats['total_bids'] . '</div>';
        $html .= '<div class="widget-label">Bids received</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_recent_activity() {
        global $wpdb;
        
        $activities = array();
        
        // Recent supplier registrations
        $suppliers = $wpdb->get_results("SELECT company_name, registration_date FROM {$wpdb->prefix}procurement_suppliers ORDER BY registration_date DESC LIMIT 5");
        foreach ($suppliers as $supplier) {
            $activities[] = array(
                'text' => 'New supplier registered: ' . $supplier->company_name,
                'time' => $supplier->registration_date
            );
        }
        
        // Recent tender publications
        $tenders = $wpdb->get_results("SELECT title, publication_date FROM {$wpdb->prefix}procurement_tenders WHERE status = 'published' ORDER BY publication_date DESC LIMIT 5");
        foreach ($tenders as $tender) {
            $activities[] = array(
                'text' => 'Tender published: ' . $tender->title,
                'time' => $tender->publication_date
            );
        }
        
        // Sort by time
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        $html = '';
        foreach (array_slice($activities, 0, 10) as $activity) {
            $html .= '<div class="activity-item">';
            $html .= '<div>' . esc_html($activity['text']) . '</div>';
            $html .= '<div class="activity-time">' . human_time_diff(strtotime($activity['time'])) . ' ago</div>';
            $html .= '</div>';
        }
        
        return $html ?: '<p>No recent activity</p>';
    }
    
    private function get_quick_actions() {
        $html = '';
        $html .= '<a href="' . admin_url('admin.php?page=procurement-suppliers') . '" class="quick-action-btn">Manage Suppliers</a>';
        $html .= '<a href="' . admin_url('admin.php?page=procurement-tenders') . '" class="quick-action-btn">Manage Tenders</a>';
        $html .= '<a href="' . admin_url('admin.php?page=procurement-ppm') . '" class="quick-action-btn">Upload PPM Document</a>';
        $html .= '<a href="#" class="quick-action-btn" onclick="exportData()">Export Data</a>';
        
        return $html;
    }
    
    private function get_suppliers_table() {
        global $wpdb;
        
        $suppliers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}procurement_suppliers ORDER BY registration_date DESC");
        
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>Company Name</th><th>Contact Person</th><th>Email</th><th>Status</th><th>Registration Date</th><th>Actions</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($suppliers as $supplier) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($supplier->company_name) . '</td>';
            $html .= '<td>' . esc_html($supplier->contact_person) . '</td>';
            $html .= '<td>' . esc_html($supplier->contact_email) . '</td>';
            $html .= '<td><span class="status-' . $supplier->approval_status . '">' . ucfirst($supplier->approval_status) . '</span></td>';
            $html .= '<td>' . date('Y-m-d', strtotime($supplier->registration_date)) . '</td>';
            $html .= '<td>';
            
            if ($supplier->approval_status === 'pending') {
                $html .= '<form method="post" style="display: inline;">';
                $html .= '<input type="hidden" name="supplier_id" value="' . $supplier->id . '">';
                $html .= '<input type="hidden" name="action" value="approve">';
                $html .= '<input type="submit" class="button button-small" value="Approve">';
                $html .= '</form> ';
                
                $html .= '<form method="post" style="display: inline;">';
                $html .= '<input type="hidden" name="supplier_id" value="' . $supplier->id . '">';
                $html .= '<input type="hidden" name="action" value="reject">';
                $html .= '<input type="submit" class="button button-small" value="Reject">';
                $html .= '</form>';
            }
            
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    private function get_tenders_table() {
        global $wpdb;
        
        $tenders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}procurement_tenders ORDER BY publication_date DESC");
        
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>Title</th><th>Category</th><th>Estimated Value</th><th>Deadline</th><th>Status</th><th>Bids</th><th>Actions</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($tenders as $tender) {
            $bid_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids WHERE tender_id = %d", $tender->id));
            
            $html .= '<tr>';
            $html .= '<td>' . esc_html($tender->title) . '</td>';
            $html .= '<td>' . esc_html($tender->category) . '</td>';
            $html .= '<td>' . number_format($tender->estimated_value, 2) . ' ' . $tender->currency . '</td>';
            $html .= '<td>' . date('Y-m-d H:i', strtotime($tender->submission_deadline)) . '</td>';
            $html .= '<td><span class="status-' . $tender->status . '">' . ucfirst($tender->status) . '</span></td>';
            $html .= '<td>' . $bid_count . '</td>';
            $html .= '<td>';
            
            if ($tender->status === 'draft') {
                $html .= '<form method="post" style="display: inline;">';
                $html .= '<input type="hidden" name="tender_id" value="' . $tender->id . '">';
                $html .= '<input type="hidden" name="action" value="publish">';
                $html .= '<input type="submit" class="button button-small" value="Publish">';
                $html .= '</form> ';
            }
            
            if ($tender->status === 'published') {
                $html .= '<form method="post" style="display: inline;">';
                $html .= '<input type="hidden" name="tender_id" value="' . $tender->id . '">';
                $html .= '<input type="hidden" name="action" value="close">';
                $html .= '<input type="submit" class="button button-small" value="Close">';
                $html .= '</form>';
            }
            
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    private function get_ppm_versions_table() {
        global $wpdb;
        
        $versions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}procurement_ppm ORDER BY upload_date DESC");
        
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>Title</th><th>Version</th><th>Status</th><th>Downloads</th><th>Upload Date</th><th>Actions</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($versions as $version) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($version->title) . '</td>';
            $html .= '<td>' . esc_html($version->version) . '</td>';
            $html .= '<td>' . ($version->is_current ? '<strong>Current</strong>' : 'Archived') . '</td>';
            $html .= '<td>' . $version->download_count . '</td>';
            $html .= '<td>' . date('Y-m-d H:i', strtotime($version->upload_date)) . '</td>';
            $html .= '<td>';
            
            if (!$version->is_current) {
                $html .= '<form method="post" action="' . admin_url('admin-post.php') . '" style="display: inline;">';
                $html .= wp_nonce_field('procurement_nonce', 'nonce', true, false);
                $html .= '<input type="hidden" name="action" value="set_current_ppm">';
                $html .= '<input type="hidden" name="ppm_id" value="' . $version->id . '">';
                $html .= '<input type="submit" class="button button-small" value="Set Current">';
                $html .= '</form> ';
                
                $html .= '<form method="post" action="' . admin_url('admin-post.php') . '" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to delete this version?\')">';
                $html .= wp_nonce_field('procurement_nonce', 'nonce', true, false);
                $html .= '<input type="hidden" name="action" value="delete_ppm_version">';
                $html .= '<input type="hidden" name="ppm_id" value="' . $version->id . '">';
                $html .= '<input type="submit" class="button button-small" value="Delete">';
                $html .= '</form> ';
            }
            
            // Generate download URL
            $upload_dir = wp_upload_dir();
            $download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $version->document_path);
            $html .= '<a href="' . esc_url($download_url) . '" class="button button-small" target="_blank">Download</a>';
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    public function bids_page() {
        // Handle bid actions FIRST - before any output
        if (isset($_POST['action']) && isset($_POST['bid_id'])) {
            $this->handle_bid_action();
            // handle_bid_action() will redirect and exit, so we won't reach here
        }
        
        // Show success/error messages if redirected with parameters
        $message = '';
        if (isset($_GET['updated']) && $_GET['updated'] == '1') {
            $message = '<div class="notice notice-success is-dismissible"><p>' . __('Bid status updated successfully!', 'procurement-mgmt') . '</p></div>';
        } elseif (isset($_GET['error'])) {
            $error_type = sanitize_text_field($_GET['error']);
            switch ($error_type) {
                case 'bid_not_found':
                    $message = '<div class="notice notice-error is-dismissible"><p>' . __('Bid not found.', 'procurement-mgmt') . '</p></div>';
                    break;
                case 'invalid_action':
                    $message = '<div class="notice notice-error is-dismissible"><p>' . __('Invalid action.', 'procurement-mgmt') . '</p></div>';
                    break;
                case 'update_failed':
                    $message = '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update bid status. Please try again.', 'procurement-mgmt') . '</p></div>';
                    break;
                default:
                    $message = '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred. Please try again.', 'procurement-mgmt') . '</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <?php echo $message; ?>
            <h1><?php _e('Bids Management', 'procurement-mgmt'); ?></h1>
            
            <div class="bids-management">
                <div class="bids-filters">
                    <form method="get">
                        <input type="hidden" name="page" value="procurement-bids">
                        <select name="tender_filter">
                            <option value=""><?php _e('All Tenders', 'procurement-mgmt'); ?></option>
                            <?php echo $this->get_tender_options(); ?>
                        </select>
                        <select name="status_filter">
                            <option value=""><?php _e('All Statuses', 'procurement-mgmt'); ?></option>
                            <option value="submitted" <?php selected($_GET['status_filter'] ?? '', 'submitted'); ?>><?php _e('Submitted', 'procurement-mgmt'); ?></option>
                            <option value="under_review" <?php selected($_GET['status_filter'] ?? '', 'under_review'); ?>><?php _e('Under Review', 'procurement-mgmt'); ?></option>
                            <option value="accepted" <?php selected($_GET['status_filter'] ?? '', 'accepted'); ?>><?php _e('Accepted', 'procurement-mgmt'); ?></option>
                            <option value="rejected" <?php selected($_GET['status_filter'] ?? '', 'rejected'); ?>><?php _e('Rejected', 'procurement-mgmt'); ?></option>
                        </select>
                        <input type="submit" class="button" value="<?php _e('Filter', 'procurement-mgmt'); ?>">
                    </form>
                </div>
                
                <div class="bids-stats">
                    <?php echo $this->get_bids_stats(); ?>
                </div>
                
                <div class="bids-table">
                    <?php echo $this->get_bids_table(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .bids-management {
            margin-top: 20px;
        }
        
        .bids-filters {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .bids-filters select,
        .bids-filters input[type="submit"] {
            margin-right: 10px;
        }
        
        .bids-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #646970;
            font-size: 14px;
        }
        
        .bid-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }
        
        .bid-details-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .bid-details-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .bid-details-close:hover {
            color: #000;
        }
        
        .status-submitted { color: #0073aa; }
        .status-under_review { color: #ff8c00; }
        .status-accepted { color: #46b450; }
        .status-rejected { color: #dc3232; }
        </style>
        
        <!-- Bid Details Modal -->
        <div id="bid-details-modal" class="bid-details-modal">
            <div class="bid-details-content">
                <span class="bid-details-close">&times;</span>
                <div id="bid-details-body">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // View bid details
            $('.view-bid-details').on('click', function(e) {
                e.preventDefault();
                var bidId = $(this).data('bid-id');
                loadBidDetails(bidId);
            });
            
            // Close modal
            $('.bid-details-close').on('click', function() {
                $('#bid-details-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('bid-details-modal')) {
                    $('#bid-details-modal').hide();
                }
            });
            
            function loadBidDetails(bidId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_bid_details',
                        bid_id: bidId,
                        nonce: '<?php echo wp_create_nonce('procurement_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#bid-details-body').html(response.data);
                            $('#bid-details-modal').show();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error loading bid details. Please try again.');
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    private function handle_bid_action() {
        // Verify nonce first
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $bid_id = intval($_POST['bid_id']);
        $action = sanitize_text_field($_POST['action']);
        
        // Verify bid exists
        $bid = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}procurement_bids WHERE id = %d",
            $bid_id
        ));
        
        if (!$bid) {
            wp_redirect(admin_url('admin.php?page=procurement-bids&error=bid_not_found'));
            exit;
        }
        
        $update_success = false;
        
        switch ($action) {
            case 'accept_bid':
                $result = $wpdb->update(
                    $wpdb->prefix . 'procurement_bids',
                    array('status' => 'accepted'),
                    array('id' => $bid_id)
                );
                if ($result !== false) {
                    $this->send_bid_status_email($bid_id, 'accepted');
                    $update_success = true;
                }
                break;
                
            case 'reject_bid':
                $result = $wpdb->update(
                    $wpdb->prefix . 'procurement_bids',
                    array('status' => 'rejected'),
                    array('id' => $bid_id)
                );
                if ($result !== false) {
                    $this->send_bid_status_email($bid_id, 'rejected');
                    $update_success = true;
                }
                break;
                
            case 'review_bid':
                $result = $wpdb->update(
                    $wpdb->prefix . 'procurement_bids',
                    array('status' => 'under_review'),
                    array('id' => $bid_id)
                );
                if ($result !== false) {
                    $update_success = true;
                }
                break;
                
            default:
                wp_redirect(admin_url('admin.php?page=procurement-bids&error=invalid_action'));
                exit;
        }
        
        if ($update_success) {
            wp_redirect(admin_url('admin.php?page=procurement-bids&updated=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=procurement-bids&error=update_failed'));
        }
        exit;
    }
    
    private function get_bids_stats() {
        global $wpdb;
        
        $total_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids");
        $submitted_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids WHERE status = 'submitted'");
        $under_review_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids WHERE status = 'under_review'");
        $accepted_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids WHERE status = 'accepted'");
        $rejected_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}procurement_bids WHERE status = 'rejected'");
        
        $html = '';
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-number">' . $total_bids . '</div>';
        $html .= '<div class="stat-label">' . __('Total Bids', 'procurement-mgmt') . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-number">' . $submitted_bids . '</div>';
        $html .= '<div class="stat-label">' . __('Submitted', 'procurement-mgmt') . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-number">' . $under_review_bids . '</div>';
        $html .= '<div class="stat-label">' . __('Under Review', 'procurement-mgmt') . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-number">' . $accepted_bids . '</div>';
        $html .= '<div class="stat-label">' . __('Accepted', 'procurement-mgmt') . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-number">' . $rejected_bids . '</div>';
        $html .= '<div class="stat-label">' . __('Rejected', 'procurement-mgmt') . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_bids_table() {
        global $wpdb;
        
        $where_clause = "WHERE 1=1";
        $params = array();
        
        // Apply filters
        if (!empty($_GET['tender_filter'])) {
            $where_clause .= " AND b.tender_id = %d";
            $params[] = intval($_GET['tender_filter']);
        }
        
        if (!empty($_GET['status_filter'])) {
            $where_clause .= " AND b.status = %s";
            $params[] = sanitize_text_field($_GET['status_filter']);
        }
        
        $query = "SELECT b.*, t.title as tender_title, s.company_name, s.contact_person, s.contact_email 
                  FROM {$wpdb->prefix}procurement_bids b 
                  LEFT JOIN {$wpdb->prefix}procurement_tenders t ON b.tender_id = t.id 
                  LEFT JOIN {$wpdb->prefix}procurement_suppliers s ON b.supplier_id = s.id 
                  $where_clause 
                  ORDER BY b.submission_date DESC";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $bids = $wpdb->get_results($query);
        
        $html = '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Tender', 'procurement-mgmt') . '</th>';
        $html .= '<th>' . __('Supplier', 'procurement-mgmt') . '</th>';
        $html .= '<th>' . __('Bid Amount', 'procurement-mgmt') . '</th>';
        $html .= '<th>' . __('Status', 'procurement-mgmt') . '</th>';
        $html .= '<th>' . __('Submitted', 'procurement-mgmt') . '</th>';
        $html .= '<th>' . __('Actions', 'procurement-mgmt') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($bids as $bid) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($bid->tender_title) . '</td>';
            $html .= '<td>' . esc_html($bid->company_name) . '<br><small>' . esc_html($bid->contact_person) . '</small></td>';
            $html .= '<td>' . number_format($bid->bid_amount, 2) . ' ' . $bid->currency . '</td>';
            $html .= '<td><span class="status-' . $bid->status . '">' . ucfirst(str_replace('_', ' ', $bid->status)) . '</span></td>';
            $html .= '<td>' . date('Y-m-d H:i', strtotime($bid->submission_date)) . '</td>';
            $html .= '<td>';
            
            // View details button
            $html .= '<button class="button button-small view-bid-details" data-bid-id="' . $bid->id . '">' . __('View Details', 'procurement-mgmt') . '</button> ';
            
            // Status change buttons
            if ($bid->status === 'submitted') {
                $html .= '<form method="post" style="display: inline;">';
                $html .= wp_nonce_field('procurement_nonce', 'nonce', true, false);
                $html .= '<input type="hidden" name="bid_id" value="' . $bid->id . '">';
                $html .= '<input type="hidden" name="action" value="review_bid">';
                $html .= '<input type="submit" class="button button-small" value="' . __('Mark for Review', 'procurement-mgmt') . '">';
                $html .= '</form> ';
            }
            
            if ($bid->status === 'under_review') {
                $html .= '<form method="post" style="display: inline;">';
                $html .= wp_nonce_field('procurement_nonce', 'nonce', true, false);
                $html .= '<input type="hidden" name="bid_id" value="' . $bid->id . '">';
                $html .= '<input type="hidden" name="action" value="accept_bid">';
                $html .= '<input type="submit" class="button button-small" value="' . __('Accept', 'procurement-mgmt') . '" style="background: #46b450; border-color: #46b450; color: white;">';
                $html .= '</form> ';
                
                $html .= '<form method="post" style="display: inline;">';
                $html .= wp_nonce_field('procurement_nonce', 'nonce', true, false);
                $html .= '<input type="hidden" name="bid_id" value="' . $bid->id . '">';
                $html .= '<input type="hidden" name="action" value="reject_bid">';
                $html .= '<input type="submit" class="button button-small" value="' . __('Reject', 'procurement-mgmt') . '" style="background: #dc3232; border-color: #dc3232; color: white;">';
                $html .= '</form>';
            }
            
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    private function get_tender_options() {
        global $wpdb;
        
        $tenders = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}procurement_tenders ORDER BY title");
        
        $html = '';
        foreach ($tenders as $tender) {
            $selected = selected($_GET['tender_filter'] ?? '', $tender->id, false);
            $html .= '<option value="' . $tender->id . '" ' . $selected . '>' . esc_html($tender->title) . '</option>';
        }
        
        return $html;
    }
    
    private function send_bid_status_email($bid_id, $status) {
        global $wpdb;
        
        $bid = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, t.title as tender_title, s.contact_email, s.contact_person, s.company_name 
             FROM {$wpdb->prefix}procurement_bids b 
             LEFT JOIN {$wpdb->prefix}procurement_tenders t ON b.tender_id = t.id 
             LEFT JOIN {$wpdb->prefix}procurement_suppliers s ON b.supplier_id = s.id 
             WHERE b.id = %d",
            $bid_id
        ));
        
        if (!$bid) return;
        
        $site_name = get_bloginfo('name');
        $to = $bid->contact_email;
        
        if ($status === 'accepted') {
            $subject = sprintf(__('[%s] Your Bid Has Been Accepted', 'procurement-mgmt'), $site_name);
            $message = sprintf(
                __("Dear %s,\n\nCongratulations! Your bid for the tender '%s' has been accepted.\n\nBid Amount: %s %s\n\nWe will contact you soon with further details.\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $bid->contact_person,
                $bid->tender_title,
                number_format($bid->bid_amount, 2),
                $bid->currency,
                $site_name
            );
        } else {
            $subject = sprintf(__('[%s] Bid Status Update', 'procurement-mgmt'), $site_name);
            $message = sprintf(
                __("Dear %s,\n\nWe regret to inform you that your bid for the tender '%s' has been rejected.\n\nBid Amount: %s %s\n\nThank you for your interest. We encourage you to participate in future tenders.\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $bid->contact_person,
                $bid->tender_title,
                number_format($bid->bid_amount, 2),
                $bid->currency,
                $site_name
            );
        }
        
        wp_mail($to, $subject, $message);
    }
    
    public function get_bid_details_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $bid_id = intval($_POST['bid_id']);
        
        $bid = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, t.title as tender_title, t.description as tender_description, 
                    s.company_name, s.contact_person, s.contact_email, s.contact_phone, s.company_address
             FROM {$wpdb->prefix}procurement_bids b 
             LEFT JOIN {$wpdb->prefix}procurement_tenders t ON b.tender_id = t.id 
             LEFT JOIN {$wpdb->prefix}procurement_suppliers s ON b.supplier_id = s.id 
             WHERE b.id = %d",
            $bid_id
        ));
        
        if (!$bid) {
            wp_send_json_error(__('Bid not found', 'procurement-mgmt'));
        }
        
        $documents = json_decode($bid->documents, true) ?: array();
        
        ob_start();
        ?>
        <div class="bid-details">
            <h2><?php _e('Bid Details', 'procurement-mgmt'); ?></h2>
            
            <div class="bid-info-grid">
                <div class="info-section">
                    <h3><?php _e('Tender Information', 'procurement-mgmt'); ?></h3>
                    <p><strong><?php _e('Tender Title:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($bid->tender_title); ?></p>
                    <p><strong><?php _e('Tender Description:', 'procurement-mgmt'); ?></strong></p>
                    <div class="tender-description"><?php echo wp_kses_post($bid->tender_description); ?></div>
                </div>
                
                <div class="info-section">
                    <h3><?php _e('Supplier Information', 'procurement-mgmt'); ?></h3>
                    <p><strong><?php _e('Company Name:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($bid->company_name); ?></p>
                    <p><strong><?php _e('Contact Person:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($bid->contact_person); ?></p>
                    <p><strong><?php _e('Email:', 'procurement-mgmt'); ?></strong> <a href="mailto:<?php echo esc_attr($bid->contact_email); ?>"><?php echo esc_html($bid->contact_email); ?></a></p>
                    <p><strong><?php _e('Phone:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($bid->contact_phone); ?></p>
                    <p><strong><?php _e('Address:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($bid->company_address); ?></p>
                </div>
                
                <div class="info-section">
                    <h3><?php _e('Bid Information', 'procurement-mgmt'); ?></h3>
                    <p><strong><?php _e('Bid Amount:', 'procurement-mgmt'); ?></strong> <?php echo number_format($bid->bid_amount, 2) . ' ' . $bid->currency; ?></p>
                    <p><strong><?php _e('Status:', 'procurement-mgmt'); ?></strong> <span class="status-<?php echo $bid->status; ?>"><?php echo ucfirst(str_replace('_', ' ', $bid->status)); ?></span></p>
                    <p><strong><?php _e('Submission Date:', 'procurement-mgmt'); ?></strong> <?php echo date('F j, Y \a\t H:i', strtotime($bid->submission_date)); ?></p>
                </div>
            </div>
            
            <div class="bid-content">
                <div class="content-section">
                    <h3><?php _e('Technical Proposal', 'procurement-mgmt'); ?></h3>
                    <div class="proposal-content">
                        <?php echo wp_kses_post($bid->technical_proposal); ?>
                    </div>
                </div>
                
                <div class="content-section">
                    <h3><?php _e('Financial Breakdown', 'procurement-mgmt'); ?></h3>
                    <div class="proposal-content">
                        <?php echo wp_kses_post($bid->financial_proposal); ?>
                    </div>
                </div>
                
                <?php if (!empty($documents)): ?>
                <div class="content-section">
                    <h3><?php _e('Supporting Documents', 'procurement-mgmt'); ?></h3>
                    <div class="documents-list">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" class="document-link">
                                    <span class="document-icon">📄</span>
                                    <span class="document-name"><?php echo esc_html($doc['name']); ?></span>
                                    <span class="document-size">(<?php echo size_format($doc['size']); ?>)</span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .bid-details {
            max-width: 100%;
        }
        
        .bid-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-section h3 {
            margin-top: 0;
            color: #0073aa;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .tender-description {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .bid-content {
            margin-top: 30px;
        }
        
        .content-section {
            margin-bottom: 30px;
        }
        
        .content-section h3 {
            color: #0073aa;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .proposal-content {
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .documents-list {
            margin-top: 10px;
        }
        
        .document-item {
            margin-bottom: 10px;
        }
        
        .document-link {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background: #f0f0f0;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .document-link:hover {
            background: #e0e0e0;
        }
        
        .document-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .document-size {
            margin-left: 10px;
            color: #666;
            font-size: 12px;
        }
        </style>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
}
