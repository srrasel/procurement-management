<?php
/**
 * Supplier management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_Supplier {
    
    public function __construct() {
        add_action('wp_ajax_register_supplier', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_register_supplier', array($this, 'handle_registration'));
        add_action('wp_ajax_approve_supplier', array($this, 'approve_supplier'));
        add_action('wp_ajax_reject_supplier', array($this, 'reject_supplier'));
        add_action('wp_ajax_update_supplier_profile', array($this, 'update_supplier_profile'));
        
        // Add missing AJAX handlers
        add_action('wp_ajax_get_bid_form', array($this, 'get_bid_form'));
        add_action('wp_ajax_nopriv_get_bid_form', array($this, 'get_bid_form'));
        add_action('wp_ajax_get_tender_details', array($this, 'get_tender_details'));
        add_action('wp_ajax_nopriv_get_tender_details', array($this, 'get_tender_details'));
        
        // Add supplier role
        add_action('init', array($this, 'add_supplier_role'));
        
        // Add login redirect for suppliers
        add_filter('login_redirect', array($this, 'supplier_login_redirect'), 10, 3);
        
        // Shortcodes
        add_shortcode('supplier_registration', array($this, 'registration_form_shortcode'));
        add_shortcode('supplier_dashboard', array($this, 'dashboard_shortcode'));
    }
    
    public function add_supplier_role() {
        // Remove the role first if it exists to refresh capabilities
        if (get_role('supplier')) {
            remove_role('supplier');
        }
        
        add_role('supplier', __('Supplier', 'procurement-mgmt'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
            'submit_bids' => true,
            'view_tenders' => true,
        ));
    }
    
    /**
     * Redirect suppliers to dashboard after login
     */
    public function supplier_login_redirect($redirect_to, $requested_redirect_to, $user) {
        // Check if user exists and has supplier role
        if ($user && is_object($user) && !is_wp_error($user)) {
            if (in_array('supplier', $user->roles)) {
                // Get the dashboard page URL
                $dashboard_page = $this->get_dashboard_page_url();
                if ($dashboard_page) {
                    return $dashboard_page;
                }
            }
        }
        
        // Return default redirect for non-suppliers or if dashboard page not found
        return $redirect_to;
    }
    
    /**
     * Get the dashboard page URL
     */
    private function get_dashboard_page_url() {
        // First, try to find a page with the supplier_dashboard shortcode
        $pages = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => 'default',
            'hierarchical' => 0
        ));
        
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'supplier_dashboard')) {
                return get_permalink($page->ID);
            }
        }
        
        // If no page with shortcode found, try to find a page with 'dashboard' in the slug
        $dashboard_page = get_page_by_path('dashboard');
        if ($dashboard_page) {
            return get_permalink($dashboard_page->ID);
        }
        
        // If still not found, try to find a page with 'dashboard' in the title
        $pages = get_pages(array(
            'hierarchical' => 0
        ));
        
        foreach ($pages as $page) {
            if (stripos($page->post_title, 'dashboard') !== false) {
                return get_permalink($page->ID);
            }
        }
        
        // Fallback to home page if no dashboard page found
        return home_url('/');
    }
    
    public function registration_form_shortcode($atts) {
        ob_start();
        ?>
        <div class="procurement-supplier-registration">
            <h3><?php _e('Supplier Registration', 'procurement-mgmt'); ?></h3>
            
            <form id="supplier-registration-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('supplier_registration', 'supplier_nonce'); ?>
                
                <div class="form-group">
                    <label for="company_name"><?php _e('Company Name', 'procurement-mgmt'); ?> *</label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>
                
                <div class="form-group">
                    <label for="company_address"><?php _e('Company Address', 'procurement-mgmt'); ?> *</label>
                    <textarea id="company_address" name="company_address" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_person"><?php _e('Contact Person', 'procurement-mgmt'); ?> *</label>
                        <input type="text" id="contact_person" name="contact_person" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_email"><?php _e('Contact Email', 'procurement-mgmt'); ?> *</label>
                        <input type="email" id="contact_email" name="contact_email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_phone"><?php _e('Contact Phone', 'procurement-mgmt'); ?> *</label>
                        <input type="tel" id="contact_phone" name="contact_phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_category"><?php _e('Business Category', 'procurement-mgmt'); ?> *</label>
                        <select id="business_category" name="business_category" required>
                            <option value=""><?php _e('Select Category', 'procurement-mgmt'); ?></option>
                            <option value="construction"><?php _e('Construction', 'procurement-mgmt'); ?></option>
                            <option value="consulting"><?php _e('Consulting', 'procurement-mgmt'); ?></option>
                            <option value="supplies"><?php _e('Supplies', 'procurement-mgmt'); ?></option>
                            <option value="services"><?php _e('Services', 'procurement-mgmt'); ?></option>
                            <option value="technology"><?php _e('Technology', 'procurement-mgmt'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ifu_number"><?php _e('IFU Number', 'procurement-mgmt'); ?> *</label>
                        <input type="text" id="ifu_number" name="ifu_number" required>
                        <small><?php _e('Tax Identification Number', 'procurement-mgmt'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="rccm_number"><?php _e('RCCM Number', 'procurement-mgmt'); ?> *</label>
                        <input type="text" id="rccm_number" name="rccm_number" required>
                        <small><?php _e('Commercial Registry Number', 'procurement-mgmt'); ?></small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="company_documents"><?php _e('Company Documents', 'procurement-mgmt'); ?></label>
                    <input type="file" id="company_documents" name="company_documents[]" multiple accept=".pdf,.doc,.docx">
                    <small><?php _e('Upload relevant company documents (PDF, DOC, DOCX)', 'procurement-mgmt'); ?></small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" style="width: 20px; height: 20px;" name="terms_accepted" required>
                        <?php _e('I accept the terms and conditions', 'procurement-mgmt'); ?> *
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <?php _e('Register as Supplier', 'procurement-mgmt'); ?>
                </button>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#supplier-registration-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'register_supplier');
                
                $.ajax({
                    url: procurement_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            if (response.data.redirect_url) {
                                // Show success message and redirect to dashboard
                                alert('Registration submitted successfully! Redirecting to your dashboard...');
                                window.location.href = response.data.redirect_url;
                            } else {
                            alert('Registration submitted successfully! You will be notified once approved.');
                            $('#supplier-registration-form')[0].reset();
                            }
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function handle_registration() {
        if (!wp_verify_nonce($_POST['supplier_nonce'], 'supplier_registration')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('company_name', 'company_address', 'contact_person', 'contact_email', 'contact_phone', 'ifu_number', 'rccm_number', 'business_category');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(__('All required fields must be filled', 'procurement-mgmt'));
            }
        }
        
        // Check if IFU or RCCM already exists
        $table = $wpdb->prefix . 'procurement_suppliers';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE ifu_number = %s OR rccm_number = %s",
            $_POST['ifu_number'],
            $_POST['rccm_number']
        ));
        
        if ($existing) {
            wp_send_json_error(__('A supplier with this IFU or RCCM number already exists', 'procurement-mgmt'));
        }
        
        // Create user account
        $username = sanitize_user($_POST['contact_email']);
        $password = wp_generate_password();
        
        $user_id = wp_create_user($username, $password, $_POST['contact_email']);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Assign supplier role to the new user
        $user = get_user_by('id', $user_id);
        if ($user) {
            // Remove any existing roles first to avoid conflicts
            $user->set_role('');
            
            // Add the supplier role
            $user->add_role('supplier');
            
            // Also add the subscriber role to ensure basic capabilities
            $user->add_role('subscriber');
        }
        
        // Handle file uploads
        $documents = array();
        if (!empty($_FILES['company_documents']['name'][0])) {
            $documents = $this->handle_file_uploads($_FILES['company_documents'], $user_id);
        }
        
        // Insert supplier data
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'company_name' => sanitize_text_field($_POST['company_name']),
                'company_address' => sanitize_textarea_field($_POST['company_address']),
                'contact_person' => sanitize_text_field($_POST['contact_person']),
                'contact_email' => sanitize_email($_POST['contact_email']),
                'contact_phone' => sanitize_text_field($_POST['contact_phone']),
                'ifu_number' => sanitize_text_field($_POST['ifu_number']),
                'rccm_number' => sanitize_text_field($_POST['rccm_number']),
                'business_category' => sanitize_text_field($_POST['business_category']),
                'documents' => json_encode($documents),
                'approval_status' => 'pending'
            )
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to register supplier', 'procurement-mgmt'));
        }
        
        // Send notification email
        wp_new_user_notification($user_id, null, 'user');
        
        // Auto-login the user after successful registration
        $this->auto_login_supplier($user_id);
        
        wp_send_json_success(array(
            'message' => __('Registration submitted successfully', 'procurement-mgmt'),
            'redirect_url' => $this->get_dashboard_page_url()
        ));
    }
    
    private function handle_file_uploads($files, $user_id) {
        $uploaded_files = array();
        $upload_dir = wp_upload_dir();
        $supplier_dir = $upload_dir['basedir'] . '/procurement/suppliers/' . $user_id;
        
        if (!file_exists($supplier_dir)) {
            wp_mkdir_p($supplier_dir);
        }
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = sanitize_file_name($files['name'][$i]);
                $filepath = $supplier_dir . '/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                    $uploaded_files[] = array(
                        'name' => $filename,
                        'path' => $filepath,
                        'url' => $upload_dir['baseurl'] . '/procurement/suppliers/' . $user_id . '/' . $filename
                    );
                }
            }
        }
        
        return $uploaded_files;
    }
    
    /**
     * Auto-login supplier after registration
     */
    private function auto_login_supplier($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user && !is_wp_error($user)) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
        }
    }
    
    public function approve_supplier() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $supplier_id = intval($_POST['supplier_id']);
        $table = $wpdb->prefix . 'procurement_suppliers';
        
        // Get supplier data
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $supplier_id
        ));
        
        if (!$supplier) {
            wp_send_json_error(__('Supplier not found', 'procurement-mgmt'));
        }
        
        // Update supplier status
        $result = $wpdb->update(
            $table,
            array(
                'approval_status' => 'approved',
                'approval_date' => current_time('mysql'),
                'approved_by' => get_current_user_id()
            ),
            array('id' => $supplier_id)
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to approve supplier', 'procurement-mgmt'));
        }
        
        // Get user and ensure supplier role is assigned
        $user = get_user_by('id', $supplier->user_id);
        if ($user) {
            // Remove any existing roles first to avoid conflicts
            $user->set_role('');
            
            // Add the supplier role
            $user->add_role('supplier');
            
            // Also add the subscriber role to ensure basic capabilities
            $user->add_role('subscriber');
        }
        
        $this->send_approval_email($supplier, 'approved');
        
        wp_send_json_success(__('Supplier approved successfully', 'procurement-mgmt'));
    }
    
    public function reject_supplier() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $supplier_id = intval($_POST['supplier_id']);
        $rejection_reason = sanitize_textarea_field($_POST['rejection_reason']);
        $table = $wpdb->prefix . 'procurement_suppliers';
        
        // Get supplier data
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $supplier_id
        ));
        
        if (!$supplier) {
            wp_send_json_error(__('Supplier not found', 'procurement-mgmt'));
        }
        
        // Update supplier status
        $result = $wpdb->update(
            $table,
            array(
                'approval_status' => 'rejected',
                'approval_date' => current_time('mysql'),
                'approved_by' => get_current_user_id(),
                'rejection_reason' => $rejection_reason
            ),
            array('id' => $supplier_id)
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to reject supplier', 'procurement-mgmt'));
        }
        
        $this->send_approval_email($supplier, 'rejected', $rejection_reason);
        
        wp_send_json_success(__('Supplier rejected successfully', 'procurement-mgmt'));
    }
    
    private function send_approval_email($supplier, $status, $rejection_reason = '') {
        $user = get_user_by('id', $supplier->user_id);
        if (!$user) return;
        
        $site_name = get_bloginfo('name');
        $to = $user->user_email;
        
        if ($status === 'approved') {
            $subject = sprintf(__('[%s] Supplier Registration Approved', 'procurement-mgmt'), $site_name);
            $message = sprintf(
                __("Dear %s,\n\nYour supplier registration for %s has been approved!\n\nYou can now log in to your dashboard and start participating in tenders.\n\nLogin URL: %s\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $supplier->contact_person,
                $supplier->company_name,
                wp_login_url(),
                $site_name
            );
        } else {
            $subject = sprintf(__('[%s] Supplier Registration Status', 'procurement-mgmt'), $site_name);
            $message = sprintf(
                __("Dear %s,\n\nWe regret to inform you that your supplier registration for %s has been rejected.\n\nReason: %s\n\nYou may resubmit your application after addressing the issues mentioned above.\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $supplier->contact_person,
                $supplier->company_name,
                $rejection_reason,
                $site_name
            );
        }
        
        wp_mail($to, $subject, $message);
    }
    
    public function update_supplier_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Please log in', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        $user = wp_get_current_user();
        if (!in_array('supplier', $user->roles)) {
            wp_send_json_error(__('Access denied', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_suppliers';
        
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user->ID
        ));
        
        if (!$supplier) {
            wp_send_json_error(__('Supplier profile not found', 'procurement-mgmt'));
        }
        
        // Only allow updates if not approved yet or specific fields for approved suppliers
        if ($supplier->approval_status === 'approved') {
            // Approved suppliers can only update contact info
            $allowed_fields = array('company_address', 'contact_person', 'contact_email', 'contact_phone');
            $update_data = array();
            
            foreach ($allowed_fields as $field) {
                if (isset($_POST[$field])) {
                    $update_data[$field] = sanitize_text_field($_POST[$field]);
                }
            }
        } else {
            // Pending suppliers can update all fields
            $update_data = array(
                'company_name' => sanitize_text_field($_POST['company_name']),
                'company_address' => sanitize_textarea_field($_POST['company_address']),
                'contact_person' => sanitize_text_field($_POST['contact_person']),
                'contact_email' => sanitize_email($_POST['contact_email']),
                'contact_phone' => sanitize_text_field($_POST['contact_phone']),
                'business_category' => sanitize_text_field($_POST['business_category'])
            );
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $supplier->id));
        
        if ($result === false) {
            wp_send_json_error(__('Failed to update profile', 'procurement-mgmt'));
        }
        
        wp_send_json_success(__('Profile updated successfully', 'procurement-mgmt'));
    }

    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());
            return '<div class="access-prompt">
                <h3>' . __('Supplier Dashboard Access', 'procurement-mgmt') . '</h3>
                <p>' . __('Please log in to access your supplier dashboard.', 'procurement-mgmt') . '</p>
                <div class="access-actions">
                    <a href="' . $login_url . '" class="btn btn-primary">' . __('Login', 'procurement-mgmt') . '</a>
                    <a href="' . home_url('/supplier-registration') . '" class="btn btn-secondary">' . __('Register as Supplier', 'procurement-mgmt') . '</a>
                </div>
            </div>';
        }
        
        $user = wp_get_current_user();
        
        // Check if user has supplier role
        if (!in_array('supplier', $user->roles)) {
            return '<div class="access-prompt"><p>' . __('Access denied. Supplier role required. Please register as a supplier first.', 'procurement-mgmt') . '</p>
                    <a href="' . home_url('/supplier-registration') . '" class="btn btn-primary">' . __('Register as Supplier', 'procurement-mgmt') . '</a></div>';
        }
        
        global $wpdb;
        $supplier_table = $wpdb->prefix . 'procurement_suppliers';
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $supplier_table WHERE user_id = %d",
            $user->ID
        ));
        
        if (!$supplier) {
            return '<p>' . __('Supplier profile not found.', 'procurement-mgmt') . '</p>';
        }
        
        // Get dashboard statistics
        $bids_table = $wpdb->prefix . 'procurement_bids';
        $total_bids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bids_table WHERE supplier_id = %d", $supplier->id));
        $accepted_bids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bids_table WHERE supplier_id = %d AND status = 'accepted'", $supplier->id));
        $pending_bids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bids_table WHERE supplier_id = %d AND status = 'submitted'", $supplier->id));
        $under_review_bids = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bids_table WHERE supplier_id = %d AND status = 'under_review'", $supplier->id));
        
        // Get recent bids
        $recent_bids = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, t.title as tender_title 
            FROM $bids_table b 
            LEFT JOIN {$wpdb->prefix}procurement_tenders t ON b.tender_id = t.id 
            WHERE b.supplier_id = %d 
            ORDER BY b.submission_date DESC 
            LIMIT 5
        ", $supplier->id));
        
        ob_start();
        ?>
        <?php echo $this->get_dashboard_styles(); ?>
        <div class="supplier-dashboard">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="welcome-section">
                    <h1><?php _e('Welcome back,', 'procurement-mgmt'); ?> <?php echo esc_html($supplier->contact_person); ?>!</h1>
                    <p><?php _e('Manage your bids, view tenders, and track your procurement activities.', 'procurement-mgmt'); ?></p>
                </div>
                <div class="status-indicator">
                <span class="status-badge status-<?php echo $supplier->approval_status; ?>">
                    <?php echo ucfirst($supplier->approval_status); ?>
                </span>
                <?php if ($supplier->approval_status === 'rejected' && $supplier->rejection_reason): ?>
                        <div class="rejection-notice">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php _e('Registration rejected. Please contact administration.', 'procurement-mgmt'); ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Dashboard Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_bids; ?></div>
                        <div class="stat-label"><?php _e('Total Bids', 'procurement-mgmt'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $accepted_bids; ?></div>
                        <div class="stat-label"><?php _e('Accepted Bids', 'procurement-mgmt'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $pending_bids; ?></div>
                        <div class="stat-label"><?php _e('Pending Review', 'procurement-mgmt'); ?></div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🔍</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $under_review_bids; ?></div>
                        <div class="stat-label"><?php _e('Under Review', 'procurement-mgmt'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Content -->
            <div class="dashboard-content">
                <!-- Left Column -->
                <div class="dashboard-main">
                    <?php if ($supplier->approval_status === 'approved'): ?>
                        <!-- Recent Activity -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h3><?php _e('Recent Activity', 'procurement-mgmt'); ?></h3>
                                <a href="#my-bids" class="view-all-link"><?php _e('View All', 'procurement-mgmt'); ?></a>
                            </div>
                            <div class="recent-activity">
                                <?php if (!empty($recent_bids)): ?>
                                    <?php foreach ($recent_bids as $bid): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon status-<?php echo $bid->status; ?>">
                                                <?php 
                                                switch($bid->status) {
                                                    case 'accepted': echo '✅'; break;
                                                    case 'rejected': echo '❌'; break;
                                                    case 'under_review': echo '🔍'; break;
                                                    default: echo '📝'; break;
                                                }
                                                ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-title"><?php echo esc_html($bid->tender_title); ?></div>
                                                <div class="activity-details">
                                                    <?php echo number_format($bid->bid_amount, 2) . ' ' . $bid->currency; ?> • 
                                                    <?php echo ucfirst(str_replace('_', ' ', $bid->status)); ?> • 
                                                    <?php echo date('M j, Y', strtotime($bid->submission_date)); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-activity">
                                        <p><?php _e('No recent activity. Start by submitting your first bid!', 'procurement-mgmt'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Available Tenders -->
                        <div class="dashboard-section" id="available-tenders">
                            <div class="section-header">
                                <h3><?php _e('Available Tenders', 'procurement-mgmt'); ?></h3>
                                <span class="section-badge"><?php echo $this->get_available_tenders_count(); ?> <?php _e('Active', 'procurement-mgmt'); ?></span>
                            </div>
                            <div class="tenders-grid">
                                <?php echo $this->get_available_tenders_cards(); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Approval Status Section -->
                        <div class="dashboard-section approval-status">
                            <div class="status-content">
                                <div class="status-icon">
                                    <?php if ($supplier->approval_status === 'pending'): ?>
                                        ⏳
                                    <?php elseif ($supplier->approval_status === 'rejected'): ?>
                                        ❌
                                    <?php endif; ?>
                                </div>
                                <div class="status-text">
                                    <h3><?php _e('Registration Status', 'procurement-mgmt'); ?></h3>
                                    <p>
                                        <?php if ($supplier->approval_status === 'pending'): ?>
                                            <?php _e('Your supplier registration is currently under review. You will be notified once approved.', 'procurement-mgmt'); ?>
                                        <?php elseif ($supplier->approval_status === 'rejected'): ?>
                                            <?php _e('Your supplier registration was not approved. Please contact administration for more information.', 'procurement-mgmt'); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Sidebar -->
                <div class="dashboard-sidebar">
                    <!-- Company Profile -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <h3><?php _e('Company Profile', 'procurement-mgmt'); ?></h3>
                            <button id="edit-profile-btn" class="edit-btn">
                                <i class="fas fa-edit"></i>
                    </button>
                </div>
                
                <div id="profile-view" class="profile-info">
                            <div class="profile-item">
                                <label><?php _e('Company', 'procurement-mgmt'); ?></label>
                                <span><?php echo esc_html($supplier->company_name); ?></span>
                            </div>
                            <div class="profile-item">
                                <label><?php _e('Category', 'procurement-mgmt'); ?></label>
                                <span><?php echo esc_html(ucfirst($supplier->business_category)); ?></span>
                            </div>
                            <div class="profile-item">
                                <label><?php _e('Contact', 'procurement-mgmt'); ?></label>
                                <span><?php echo esc_html($supplier->contact_person); ?></span>
                            </div>
                            <div class="profile-item">
                                <label><?php _e('Email', 'procurement-mgmt'); ?></label>
                                <span><?php echo esc_html($supplier->contact_email); ?></span>
                            </div>
                            <div class="profile-item">
                                <label><?php _e('Phone', 'procurement-mgmt'); ?></label>
                                <span><?php echo esc_html($supplier->contact_phone); ?></span>
                            </div>
                </div>
                
                <div id="profile-edit" class="profile-edit-form" style="display: none;">
                    <form id="profile-update-form">
                        <?php wp_nonce_field('procurement_nonce', 'profile_nonce'); ?>
                        
                        <?php if ($supplier->approval_status !== 'approved'): ?>
                        <div class="form-group">
                            <label for="edit_company_name"><?php _e('Company Name', 'procurement-mgmt'); ?></label>
                            <input type="text" id="edit_company_name" name="company_name" value="<?php echo esc_attr($supplier->company_name); ?>" required>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="edit_company_address"><?php _e('Company Address', 'procurement-mgmt'); ?></label>
                            <textarea id="edit_company_address" name="company_address" rows="3" required><?php echo esc_textarea($supplier->company_address); ?></textarea>
                        </div>
                        
                            <div class="form-group">
                                <label for="edit_contact_person"><?php _e('Contact Person', 'procurement-mgmt'); ?></label>
                                <input type="text" id="edit_contact_person" name="contact_person" value="<?php echo esc_attr($supplier->contact_person); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_contact_email"><?php _e('Contact Email', 'procurement-mgmt'); ?></label>
                                <input type="email" id="edit_contact_email" name="contact_email" value="<?php echo esc_attr($supplier->contact_email); ?>" required>
                        </div>
                        
                            <div class="form-group">
                                <label for="edit_contact_phone"><?php _e('Contact Phone', 'procurement-mgmt'); ?></label>
                                <input type="tel" id="edit_contact_phone" name="contact_phone" value="<?php echo esc_attr($supplier->contact_phone); ?>" required>
                            </div>
                            
                            <?php if ($supplier->approval_status !== 'approved'): ?>
                            <div class="form-group">
                                <label for="edit_business_category"><?php _e('Business Category', 'procurement-mgmt'); ?></label>
                                <select id="edit_business_category" name="business_category" required>
                                    <option value="construction" <?php selected($supplier->business_category, 'construction'); ?>><?php _e('Construction', 'procurement-mgmt'); ?></option>
                                    <option value="consulting" <?php selected($supplier->business_category, 'consulting'); ?>><?php _e('Consulting', 'procurement-mgmt'); ?></option>
                                    <option value="supplies" <?php selected($supplier->business_category, 'supplies'); ?>><?php _e('Supplies', 'procurement-mgmt'); ?></option>
                                    <option value="services" <?php selected($supplier->business_category, 'services'); ?>><?php _e('Services', 'procurement-mgmt'); ?></option>
                                    <option value="technology" <?php selected($supplier->business_category, 'technology'); ?>><?php _e('Technology', 'procurement-mgmt'); ?></option>
                                </select>
                            </div>
                            <?php endif; ?>
                        
                        <div class="form-actions">
                                    <button type="submit" class="btn btn-primary"><?php _e('Update Profile', 'procurement-mgmt'); ?></button>
                            <button type="button" id="cancel-edit-btn" class="btn btn-secondary"><?php _e('Cancel', 'procurement-mgmt'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($supplier->approval_status === 'approved'): ?>
                        <!-- Quick Actions -->
                        <div class="sidebar-section">
                            <h3><?php _e('Quick Actions', 'procurement-mgmt'); ?></h3>
                            <div class="quick-actions">
                                <a href="#available-tenders" class="quick-action-btn">
                                    <i class="fas fa-search"></i>
                                    <?php _e('Browse Tenders', 'procurement-mgmt'); ?>
                                </a>
                                <a href="#my-bids" class="quick-action-btn">
                                    <i class="fas fa-list"></i>
                                    <?php _e('View My Bids', 'procurement-mgmt'); ?>
                                </a>
                                <button id="edit-profile-quick" class="quick-action-btn">
                                    <i class="fas fa-user-edit"></i>
                                    <?php _e('Edit Profile', 'procurement-mgmt'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                </div>
                
            <?php if ($supplier->approval_status === 'approved'): ?>
                <!-- My Bids Section -->
                <div class="dashboard-section" id="my-bids">
                    <div class="section-header">
                        <h3><?php _e('My Bids', 'procurement-mgmt'); ?></h3>
                    </div>
                    <div class="bids-table-container">
                        <?php echo $this->get_supplier_bids($supplier->id); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Profile editing functionality
            $('#edit-profile-btn').on('click', function() {
                $('#profile-view').hide();
                $('#profile-edit').show();
                $(this).hide();
            });
            
            $('#cancel-edit-btn').on('click', function() {
                $('#profile-edit').hide();
                $('#profile-view').show();
                $('#edit-profile-btn').show();
            });
            
            $('#profile-update-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=update_supplier_profile&nonce=' + $('#profile_nonce').val();
                
                $.ajax({
                    url: procurement_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('Profile updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
            
            // Dashboard tender view details functionality
            $(document).on('click', '.view-tender-btn', function(e) {
                e.preventDefault();
                var tenderId = $(this).data('tender-id');
                console.log('Dashboard view-tender-btn clicked for tender ID:', tenderId);
                loadTenderDetails(tenderId);
            });
            
            // Dashboard bid submission functionality
            $(document).on('click', '.bid-tender-btn', function(e) {
                e.preventDefault();
                var tenderId = $(this).data('tender-id');
                console.log('Dashboard bid-tender-btn clicked for tender ID:', tenderId);
                loadBidForm(tenderId);
            });
            
            // Define functions for dashboard
            function loadTenderDetails(tenderId) {
                console.log('loadTenderDetails called with tenderId:', tenderId);
                
                if (!window.procurement_ajax || !window.procurement_ajax.ajax_url) {
                    console.error('procurement_ajax.ajax_url is not available');
                    alert('AJAX configuration error. Please refresh the page.');
                    return;
                }
                
                $.ajax({
                    url: window.procurement_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_tender_details',
                        tender_id: tenderId,
                        nonce: window.procurement_ajax.nonce
                    },
                    success: function(response) {
                        console.log('loadTenderDetails success response:', response);
                        if (response.success) {
                            $('#tender-details-content').html(response.data);
                            $('#tender-details-modal').addClass('show').show();
                            $('body').addClass('modal-open');
                        } else {
                            console.error('loadTenderDetails error:', response.data);
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('loadTenderDetails AJAX error:', {xhr, status, error});
                        console.error('Response text:', xhr.responseText);
                        alert('Error loading tender details. Please try again. Check console for details.');
                    }
                });
            }
            
            function loadBidForm(tenderId) {
                console.log('loadBidForm called with tenderId:', tenderId);
                console.log('procurement_ajax:', window.procurement_ajax);
                
                if (!window.procurement_ajax || !window.procurement_ajax.ajax_url) {
                    console.error('procurement_ajax.ajax_url is not available');
                    alert('AJAX configuration error. Please refresh the page.');
                    return;
                }
                
                $.ajax({
                    url: window.procurement_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_bid_form',
                        tender_id: tenderId,
                        nonce: window.procurement_ajax.nonce
                    },
                    success: function(response) {
                        console.log('loadBidForm success response:', response);
                        if (response.success) {
                            $('#bid-form-content').html(response.data);
                            $('#bid-submission-modal').addClass('show').show();
                            $('body').addClass('modal-open');
                        } else {
                            console.error('loadBidForm error:', response.data);
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('loadBidForm AJAX error:', {xhr, status, error});
                        console.error('Response text:', xhr.responseText);
                        alert('Error loading bid form. Please try again. Check console for details.');
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    // Get available tenders count
    private function get_available_tenders_count() {
        global $wpdb;
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $tenders_table 
            WHERE status = 'published' 
            AND submission_deadline > %s
        ", current_time('mysql')));
    }
    
    // Get available tenders as cards
    private function get_available_tenders_cards() {
        global $wpdb;
        
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        $tenders = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $tenders_table 
            WHERE status = 'published' 
            AND submission_deadline > %s 
            ORDER BY submission_deadline ASC 
            LIMIT 6
        ", current_time('mysql')));
        
        if (empty($tenders)) {
            return '<div class="no-tenders"><p>' . __('No active tenders available.', 'procurement-mgmt') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="tenders-grid">
            <?php foreach ($tenders as $tender): ?>
            <div class="tender-card">
                <div class="tender-header">
                    <h4><?php echo esc_html($tender->title); ?></h4>
                    <span class="tender-category"><?php echo esc_html(ucfirst($tender->category)); ?></span>
                </div>
                <div class="tender-body">
                    <div class="tender-meta">
                        <div class="meta-item">
                            <span class="meta-label"><?php _e('Value:', 'procurement-mgmt'); ?></span>
                            <span class="meta-value"><?php echo number_format($tender->estimated_value, 2) . ' ' . $tender->currency; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label"><?php _e('Deadline:', 'procurement-mgmt'); ?></span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($tender->submission_deadline)); ?></span>
                        </div>
                    </div>
                    <p class="tender-description"><?php echo wp_trim_words($tender->description, 15); ?></p>
                </div>
                <div class="tender-actions">
                    <button class="btn btn-outline view-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                        <?php _e('View Details', 'procurement-mgmt'); ?>
                    </button>
                    <?php 
                    $is_expired = strtotime($tender->submission_deadline) < current_time('timestamp');
                    if (!$is_expired): ?>
                        <button class="btn btn-primary bid-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                            <?php _e('Submit Bid', 'procurement-mgmt'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // In the get_available_tenders method:
private function get_available_tenders() {
    global $wpdb;
    
    $tenders_table = $wpdb->prefix . 'procurement_tenders';
    $tenders = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $tenders_table 
        WHERE status = 'published' 
        AND submission_deadline > %s 
        ORDER BY submission_deadline ASC 
        LIMIT 5
    ", current_time('mysql')));
    
    if (empty($tenders)) {
        return '<p>' . __('No active tenders available.', 'procurement-mgmt') . '</p>';
    }
    
    ob_start();
    ?>
    <div class="available-tenders">
        <?php foreach ($tenders as $tender): ?>
        <div class="tender-card">
            <h5><?php echo esc_html($tender->title); ?></h5>
            <div class="tender-meta">
                <span><strong><?php _e('Category:', 'procurement-mgmt'); ?></strong> <?php echo esc_html($tender->category); ?></span>
                <span><strong><?php _e('Value:', 'procurement-mgmt'); ?></strong> <?php echo number_format($tender->estimated_value, 2) . ' ' . $tender->currency; ?></span>
                <span><strong><?php _e('Deadline:', 'procurement-mgmt'); ?></strong> <?php echo date('M j, Y', strtotime($tender->submission_deadline)); ?></span>
            </div>
            <p class="tender-description"><?php echo wp_trim_words($tender->description, 20); ?></p>
            <div class="tender-actions">
            <button class="btn btn-primary view-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                    <?php _e('View Details', 'procurement-mgmt'); ?>
                                </button>
                <?php 
                // Check if user can bid on this tender
                $is_expired = strtotime($tender->submission_deadline) < current_time('timestamp');
                if (!$is_expired): ?>
                    <button class="btn btn-success bid-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                        <?php _e('Submit Bid', 'procurement-mgmt'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div id="tender-details-modal" class="tender-modal" >
            <div class="tender-modal-content">
                <span class="tender-modal-close">&times;</span>
                <div id="tender-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
    </div>
    
    <!-- Bid Submission Modal -->
    <div id="bid-submission-modal" class="tender-modal">
        <div class="tender-modal-content">
            <span class="tender-modal-close">&times;</span>
            <div id="bid-form-content">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    

    <?php
    return ob_get_clean();
}
    
    private function get_supplier_bids($supplier_id) {
        global $wpdb;
        
        $bids_table = $wpdb->prefix . 'procurement_bids';
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        
        $bids = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, t.title as tender_title, t.submission_deadline 
            FROM $bids_table b 
            JOIN $tenders_table t ON b.tender_id = t.id 
            WHERE b.supplier_id = %d 
            ORDER BY b.submission_date DESC
        ", $supplier_id));
        
        if (empty($bids)) {
            return '<p>' . __('No bids submitted yet.', 'procurement-mgmt') . '</p>';
        }
        
        ob_start();
        ?>
        <table class="bids-table">
            <thead>
                <tr>
                    <th><?php _e('Tender', 'procurement-mgmt'); ?></th>
                    <th><?php _e('Bid Amount', 'procurement-mgmt'); ?></th>
                    <th><?php _e('Status', 'procurement-mgmt'); ?></th>
                    <th><?php _e('Submitted', 'procurement-mgmt'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bids as $bid): ?>
                <tr>
                    <td><?php echo esc_html($bid->tender_title); ?></td>
                    <td><?php echo number_format($bid->bid_amount, 2) . ' ' . $bid->currency; ?></td>
                    <td><span class="status-badge status-<?php echo $bid->status; ?>"><?php echo ucfirst(str_replace('_', ' ', $bid->status)); ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($bid->submission_date)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    // Add missing AJAX handler methods
    public function get_bid_form() {
        // Debug logging
        error_log('get_bid_form function called in supplier class');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('User not logged in in get_bid_form');
            wp_send_json_error(__('Please log in to submit a bid', 'procurement-mgmt'));
        }
        
        $user = wp_get_current_user();
        
        // Check if user has supplier role
        if (!in_array('supplier', $user->roles)) {
            wp_send_json_error(__('Only approved suppliers can submit bids. Please complete your supplier registration.', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        
        // Get tender details
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tenders_table WHERE id = %d AND status = 'published'",
            $tender_id
        ));
        
        if (!$tender) {
            wp_send_json_error(__('Tender not found', 'procurement-mgmt'));
        }
        
        // Check if deadline has passed
        if (strtotime($tender->submission_deadline) < current_time('timestamp')) {
            wp_send_json_error(__('Submission deadline has passed', 'procurement-mgmt'));
        }
        
        // Get supplier data
        $suppliers_table = $wpdb->prefix . 'procurement_suppliers';
        $supplier = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $suppliers_table WHERE user_id = %d",
            $user->ID
        ));
        
        if (!$supplier) {
            wp_send_json_error(__('Supplier profile not found. Please complete your supplier registration.', 'procurement-mgmt'));
        }
        
        // Check if supplier is approved
        if ($supplier->approval_status !== 'approved') {
            if ($supplier->approval_status === 'pending') {
                wp_send_json_error(__('Your supplier registration is pending approval. You cannot submit bids until approved.', 'procurement-mgmt'));
            } elseif ($supplier->approval_status === 'rejected') {
                wp_send_json_error(__('Your supplier registration was rejected. Please contact administration.', 'procurement-mgmt'));
            }
        }
        
        // Check if supplier already submitted a bid
        $bids_table = $wpdb->prefix . 'procurement_bids';
        $existing_bid = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $bids_table WHERE tender_id = %d AND supplier_id = %d",
            $tender_id,
            $supplier->id
        ));
        
        if ($existing_bid) {
            wp_send_json_error(__('You have already submitted a bid for this tender', 'procurement-mgmt'));
        }
        
        ob_start();
        ?>
        <div class="bid-submission-form">
            <h3><?php _e('Submit Your Bid', 'procurement-mgmt'); ?></h3>
            <p class="tender-title"><?php echo esc_html($tender->title); ?></p>
            
            <form id="bid-form" enctype="multipart/form-data">
                <?php wp_nonce_field('procurement_nonce', 'bid_nonce'); ?>
                <input type="hidden" name="tender_id" value="<?php echo $tender->id; ?>">
                
                <div class="form-group">
                    <label for="bid_amount"><?php _e('Bid Amount', 'procurement-mgmt'); ?> (<?php echo $tender->currency; ?>) *</label>
                    <input type="number" id="bid_amount" name="bid_amount" step="0.01" min="0" required>
                    <small><?php _e('Enter your total bid amount including all costs', 'procurement-mgmt'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="technical_proposal"><?php _e('Technical Proposal', 'procurement-mgmt'); ?> *</label>
                    <textarea id="technical_proposal" name="technical_proposal" rows="6" required 
                              placeholder="<?php _e('Describe your technical approach, methodology, timeline, and qualifications...', 'procurement-mgmt'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="financial_proposal"><?php _e('Financial Breakdown', 'procurement-mgmt'); ?> *</label>
                    <textarea id="financial_proposal" name="financial_proposal" rows="4" required 
                              placeholder="<?php _e('Provide detailed cost breakdown and payment terms...', 'procurement-mgmt'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="bid_documents"><?php _e('Supporting Documents', 'procurement-mgmt'); ?></label>
                    <input type="file" id="bid_documents" name="bid_documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx">
                    <small><?php _e('Upload relevant documents (certificates, portfolio, references, etc.)', 'procurement-mgmt'); ?></small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="bid_terms_accepted" required>
                        <?php _e('I confirm that this bid is valid and binding, and I accept the tender terms and conditions', 'procurement-mgmt'); ?> *
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php _e('Submit Bid', 'procurement-mgmt'); ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="jQuery('#bid-submission-modal').hide();">
                        <?php _e('Cancel', 'procurement-mgmt'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    public function get_tender_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'published'",
            $tender_id
        ));
        
        if (!$tender) {
            wp_send_json_error(__('Tender not found', 'procurement-mgmt'));
        }
        
        $documents = json_decode($tender->documents, true) ?: array();
        
        ob_start();
        ?>
        <div class="tender-details">
            <h3><?php echo esc_html($tender->title); ?></h3>
            
            <div class="tender-info-grid">
                <div class="info-item">
                    <strong><?php _e('Category:', 'procurement-mgmt'); ?></strong>
                    <?php echo esc_html(ucfirst($tender->category)); ?>
                </div>
                <div class="info-item">
                    <strong><?php _e('Estimated Value:', 'procurement-mgmt'); ?></strong>
                    <?php echo number_format($tender->estimated_value, 2) . ' ' . $tender->currency; ?>
                </div>
                <div class="info-item">
                    <strong><?php _e('Submission Deadline:', 'procurement-mgmt'); ?></strong>
                    <?php echo date('F j, Y \a\t H:i', strtotime($tender->submission_deadline)); ?>
                </div>
                <div class="info-item">
                    <strong><?php _e('Published:', 'procurement-mgmt'); ?></strong>
                    <?php echo date('F j, Y', strtotime($tender->publication_date)); ?>
                </div>
            </div>
            
            <div class="tender-section">
                <h4><?php _e('Description', 'procurement-mgmt'); ?></h4>
                <div class="tender-content">
                    <?php echo wp_kses_post($tender->description); ?>
                </div>
            </div>
            
            <div class="tender-section">
                <h4><?php _e('Requirements', 'procurement-mgmt'); ?></h4>
                <div class="tender-content">
                    <?php echo wp_kses_post($tender->requirements); ?>
                </div>
            </div>
            
            <?php if (!empty($documents)): ?>
            <div class="tender-section">
                <h4><?php _e('Documents', 'procurement-mgmt'); ?></h4>
                <div class="tender-documents">
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
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    // Add comprehensive CSS for the new dashboard design
    private function get_dashboard_styles() {
        return '
        <style>
        /* Modern Dashboard Styles */
        .supplier-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-section h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .welcome-section p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .status-indicator {
            text-align: right;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-approved { background: #10b981; color: white; }
        .status-pending { background: #f59e0b; color: white; }
        .status-rejected { background: #ef4444; color: white; }
        
        .rejection-notice {
            margin-top: 10px;
            padding: 10px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        /* Dashboard Statistics */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-right: 20px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Dashboard Content Layout */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .view-all-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .section-badge {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Recent Activity */
        .recent-activity {
            space-y: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            background: #f9fafb;
            margin-bottom: 10px;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:hover {
            background: #f3f4f6;
        }
        
        .activity-icon {
            font-size: 1.5rem;
            margin-right: 15px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .activity-details {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .no-activity {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        
        /* Tenders Grid */
        .tenders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .tender-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .tender-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .tender-header {
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tender-header h4 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .tender-category {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .tender-body {
            padding: 20px;
        }
        
        .tender-meta {
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .meta-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .meta-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .tender-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }
        
        .tender-actions {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 1px solid #3b82f6;
        }
        
        .btn-outline:hover {
            background: #3b82f6;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        /* Sidebar */
        .dashboard-sidebar {
            space-y: 20px;
        }
        
        .sidebar-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        
        .edit-btn {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .edit-btn:hover {
            color: #2563eb;
        }
        
        /* Profile Info */
        .profile-info {
            space-y: 15px;
        }
        
        .profile-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .profile-item:last-child {
            border-bottom: none;
        }
        
        .profile-item label {
            font-weight: 600;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .profile-item span {
            color: #1f2937;
            font-weight: 500;
        }
        
        /* Profile Edit Form */
        .profile-edit-form {
            space-y: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Quick Actions */
        .quick-actions {
            space-y: 10px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .quick-action-btn:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .quick-action-btn i {
            margin-right: 10px;
        }
        
        /* Approval Status */
        .approval-status {
            text-align: center;
            padding: 40px 20px;
        }
        
        .status-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .status-text h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
        }
        
        .status-text p {
            color: #6b7280;
            line-height: 1.6;
            margin: 0;
        }
        
        /* Bids Table */
        .bids-table-container {
            overflow-x: auto;
        }
        
        .bids-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .bids-table th,
        .bids-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .bids-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .bids-table tr:hover {
            background: #f9fafb;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .welcome-section h1 {
                font-size: 2rem;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tenders-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
            }
            
            .stat-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
        
        /* Access Prompt Styles */
        .access-prompt {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            max-width: 500px;
            margin: 50px auto;
        }
        
        .access-prompt h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 1.5rem;
        }
        
        .access-prompt p {
            color: #6b7280;
            margin-bottom: 25px;
            font-size: 1rem;
        }
        
        .access-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .access-actions .btn {
            padding: 12px 24px;
            font-size: 1rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
            .quick-action-btn {
  margin-bottom: 10px;
}
.sidebar-section {
  margin-bottom: 10px;
}
        </style>
        ';
    }
}