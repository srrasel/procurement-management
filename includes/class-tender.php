<?php
/**
 * Tender management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_Tender {
    
    public function __construct() {
        // Admin AJAX actions
        add_action('wp_ajax_create_tender', array($this, 'create_tender'));
        add_action('wp_ajax_update_tender', array($this, 'update_tender'));
        add_action('wp_ajax_publish_tender', array($this, 'publish_tender'));
        add_action('wp_ajax_close_tender', array($this, 'close_tender'));
        add_action('wp_ajax_delete_tender', array($this, 'delete_tender'));
        
        // Admin form handling actions
        add_action('admin_post_create_tender', array($this, 'handle_create_tender'));
        add_action('admin_post_update_tender', array($this, 'handle_update_tender'));
        add_action('admin_post_publish_tender', array($this, 'handle_publish_tender'));
        
        // Frontend AJAX actions
        add_action('wp_ajax_submit_bid', array($this, 'submit_bid'));
        add_action('wp_ajax_nopriv_submit_bid', array($this, 'submit_bid'));
        add_action('wp_ajax_get_tender_details', array($this, 'get_tender_details'));
        add_action('wp_ajax_nopriv_get_tender_details', array($this, 'get_tender_details'));
        
        // Shortcodes
        add_shortcode('tender_list', array($this, 'tender_list_shortcode'));
        add_shortcode('tender_details', array($this, 'tender_details_shortcode'));
        add_shortcode('bid_form', array($this, 'bid_form_shortcode'));
        
        // Cron jobs for deadline notifications
        add_action('procurement_tender_deadline_reminder', array($this, 'send_deadline_reminders'));
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('procurement_tender_deadline_reminder')) {
            wp_schedule_event(time(), 'daily', 'procurement_tender_deadline_reminder');
        }
    }
    
    public function create_tender() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('title', 'description', 'category', 'estimated_value', 'requirements', 'submission_deadline');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(sprintf(__('Field %s is required', 'procurement-mgmt'), $field));
            }
        }
        
        // Handle document uploads
        $documents = array();
        if (!empty($_FILES['tender_documents']['name'][0])) {
            $documents = $this->handle_tender_file_uploads($_FILES['tender_documents']);
        }
        
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $result = $wpdb->insert(
            $table,
            array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => wp_kses_post($_POST['description']),
                'category' => sanitize_text_field($_POST['category']),
                'estimated_value' => floatval($_POST['estimated_value']),
                'currency' => sanitize_text_field($_POST['currency'] ?? 'XOF'),
                'requirements' => wp_kses_post($_POST['requirements']),
                'submission_deadline' => sanitize_text_field($_POST['submission_deadline']),
                'status' => 'draft',
                'created_by' => get_current_user_id(),
                'documents' => json_encode($documents)
            )
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to create tender', 'procurement-mgmt'));
        }
        
        $tender_id = $wpdb->insert_id;
        wp_send_json_success(array(
            'message' => __('Tender created successfully', 'procurement-mgmt'),
            'tender_id' => $tender_id
        ));
    }
    
    public function update_tender() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        // Get existing tender
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $tender_id
        ));
        
        if (!$tender) {
            wp_send_json_error(__('Tender not found', 'procurement-mgmt'));
        }
        
        // Don't allow updates to published tenders with bids
        if ($tender->status === 'published') {
            $bids_table = $wpdb->prefix . 'procurement_bids';
            $bid_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bids_table WHERE tender_id = %d",
                $tender_id
            ));
            
            if ($bid_count > 0) {
                wp_send_json_error(__('Cannot update tender with existing bids', 'procurement-mgmt'));
            }
        }
        
        // Handle new document uploads
        $existing_documents = json_decode($tender->documents, true) ?: array();
        $new_documents = array();
        
        if (!empty($_FILES['tender_documents']['name'][0])) {
            $new_documents = $this->handle_tender_file_uploads($_FILES['tender_documents']);
        }
        
        $all_documents = array_merge($existing_documents, $new_documents);
        
        $update_data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description']),
            'category' => sanitize_text_field($_POST['category']),
            'estimated_value' => floatval($_POST['estimated_value']),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'XOF'),
            'requirements' => wp_kses_post($_POST['requirements']),
            'submission_deadline' => sanitize_text_field($_POST['submission_deadline']),
            'documents' => json_encode($all_documents)
        );
        
        $result = $wpdb->update($table, $update_data, array('id' => $tender_id));
        
        if ($result === false) {
            wp_send_json_error(__('Failed to update tender', 'procurement-mgmt'));
        }
        
        wp_send_json_success(__('Tender updated successfully', 'procurement-mgmt'));
    }
    
    public function publish_tender() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'published',
                'publication_date' => current_time('mysql')
            ),
            array('id' => $tender_id)
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to publish tender', 'procurement-mgmt'));
        }
        
        // Send notifications to approved suppliers
        $this->notify_suppliers_new_tender($tender_id);
        
        wp_send_json_success(__('Tender published successfully', 'procurement-mgmt'));
    }
    
    public function close_tender() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $result = $wpdb->update(
            $table,
            array('status' => 'closed'),
            array('id' => $tender_id)
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to close tender', 'procurement-mgmt'));
        }
        
        wp_send_json_success(__('Tender closed successfully', 'procurement-mgmt'));
    }
    
  public function submit_bid() {
        // Debug logging
        error_log('submit_bid function called');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('User not logged in');
            wp_send_json_error(__('Please log in to submit a bid', 'procurement-mgmt'));
        }
        
        // Verify nonce
        if (!isset($_POST['bid_nonce']) || !wp_verify_nonce($_POST['bid_nonce'], 'procurement_nonce')) {
            error_log('Nonce verification failed');
            error_log('bid_nonce: ' . (isset($_POST['bid_nonce']) ? $_POST['bid_nonce'] : 'not set'));
            wp_send_json_error(__('Security check failed', 'procurement-mgmt'));
        }
        
        $user = wp_get_current_user();
        
        // Check if user has supplier role
        if (!in_array('supplier', $user->roles)) {
            wp_send_json_error(__('Only approved suppliers can submit bids. Please complete your supplier registration.', 'procurement-mgmt'));
        }
        
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('tender_id', 'bid_amount', 'technical_proposal', 'financial_proposal');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(sprintf(__('%s is required', 'procurement-mgmt'), $field));
            }
        }
        
        $tender_id = intval($_POST['tender_id']);
        $bid_amount = floatval($_POST['bid_amount']);
        $technical_proposal = wp_kses_post($_POST['technical_proposal']);
        $financial_proposal = wp_kses_post($_POST['financial_proposal']);
        
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
            } else {
                wp_send_json_error(__('Supplier not approved. Please complete your registration process.', 'procurement-mgmt'));
            }
        }
        
        // Check if tender exists and is open
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
        
        // Handle bid document uploads
        $documents = array();
        if (!empty($_FILES['bid_documents']['name'][0])) {
            $documents = $this->handle_bid_file_uploads($_FILES['bid_documents'], $supplier->id, $tender_id);
        }
        
        // Insert bid
        $result = $wpdb->insert(
            $bids_table,
            array(
                'tender_id' => $tender_id,
                'supplier_id' => $supplier->id,
                'bid_amount' => $bid_amount,
                'currency' => $tender->currency,
                'technical_proposal' => $technical_proposal,
                'financial_proposal' => $financial_proposal,
                'documents' => json_encode($documents),
                'status' => 'submitted',
                'submission_date' => current_time('mysql')
            )
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to submit bid. Please try again.', 'procurement-mgmt'));
        }
        
        // Send confirmation email
        $this->send_bid_confirmation_email($supplier, $tender);
        
        wp_send_json_success(__('Bid submitted successfully!', 'procurement-mgmt'));
    }
    
    private function handle_tender_file_uploads($files) {
        $uploaded_files = array();
        $upload_dir = wp_upload_dir();
        $tender_dir = $upload_dir['basedir'] . '/procurement/tenders/' . time();
        
        if (!file_exists($tender_dir)) {
            wp_mkdir_p($tender_dir);
        }
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = sanitize_file_name($files['name'][$i]);
                $filepath = $tender_dir . '/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                    $uploaded_files[] = array(
                        'name' => $filename,
                        'path' => $filepath,
                        'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath),
                        'size' => $files['size'][$i],
                        'type' => $files['type'][$i]
                    );
                }
            }
        }
        
        return $uploaded_files;
    }
    
    private function handle_bid_file_uploads($files, $supplier_id, $tender_id) {
        $uploaded_files = array();
        $upload_dir = wp_upload_dir();
        $bid_dir = $upload_dir['basedir'] . '/procurement/bids/' . $tender_id . '/' . $supplier_id;
        
        if (!file_exists($bid_dir)) {
            wp_mkdir_p($bid_dir);
        }
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = sanitize_file_name($files['name'][$i]);
                $filepath = $bid_dir . '/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                    $uploaded_files[] = array(
                        'name' => $filename,
                        'path' => $filepath,
                        'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath),
                        'size' => $files['size'][$i],
                        'type' => $files['type'][$i]
                    );
                }
            }
        }
        
        return $uploaded_files;
    }
    
    public function tender_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'status' => 'published',
            'category' => '',
            'limit' => 10
        ), $atts);
        
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $where_conditions = array("status = %s");
        $where_values = array($atts['status']);
        
        if (!empty($atts['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $atts['category'];
        }
        
        if ($atts['status'] === 'published') {
            $where_conditions[] = "submission_deadline > %s";
            $where_values[] = current_time('mysql');
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $where_values[] = intval($atts['limit']);
        
        $sql = "SELECT * FROM $table $where_clause ORDER BY publication_date DESC LIMIT %d";
        $tenders = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        
        ob_start();
        ?>
        <div class="procurement-tender-list">
            <div class="tender-list-header">
            <div class="tender-filters">
                <div class="filter-group">
                    <input type="text" id="tender-search" placeholder="<?php _e('Search tenders...', 'procurement-mgmt'); ?>">
                </div>
                <div class="filter-group">
                    <select id="tender-category-filter">
                        <option value=""><?php _e('All Categories', 'procurement-mgmt'); ?></option>
                        <option value="construction"><?php _e('Construction', 'procurement-mgmt'); ?></option>
                        <option value="consulting"><?php _e('Consulting', 'procurement-mgmt'); ?></option>
                        <option value="supplies"><?php _e('Supplies', 'procurement-mgmt'); ?></option>
                        <option value="services"><?php _e('Services', 'procurement-mgmt'); ?></option>
                        <option value="technology"><?php _e('Technology', 'procurement-mgmt'); ?></option>
                    </select>
                </div>
            </div>
            </div>
            <div class="tenders-grid">
                <?php if (empty($tenders)): ?>
                    <p class="no-tenders"><?php _e('No tenders available at the moment.', 'procurement-mgmt'); ?></p>
                <?php else: ?>
                    <?php foreach ($tenders as $tender): ?>
                        <div class="tender-card" data-category="<?php echo esc_attr($tender->category); ?>">
                            <div class="tender-header">
                                <h4><?php echo esc_html($tender->title); ?></h4>
                                <span class="tender-category"><?php echo esc_html(ucfirst($tender->category)); ?></span>
                            </div>
                            
                            <div class="tender-meta">
                                <div class="meta-item">
                                    <strong><?php _e('Value:', 'procurement-mgmt'); ?></strong>
                                    <?php echo number_format($tender->estimated_value, 2) . ' ' . $tender->currency; ?>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e('Deadline:', 'procurement-mgmt'); ?></strong>
                                    <?php echo date('M j, Y H:i', strtotime($tender->submission_deadline)); ?>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e('Time Left:', 'procurement-mgmt'); ?></strong>
                                    <span class="time-remaining" data-deadline="<?php echo $tender->submission_deadline; ?>">
                                        <?php echo $this->get_time_remaining($tender->submission_deadline); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="tender-description">
                                <?php echo wp_trim_words($tender->description, 30); ?>
                            </div>
                            
                            <div class="tender-actions">
                                <button class="btn btn-primary view-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                                    <?php _e('View Details', 'procurement-mgmt'); ?>
                                </button>
                                <?php if (is_user_logged_in() && in_array('supplier', wp_get_current_user()->roles)): ?>
                                    <button class="btn btn-success bid-tender-btn" data-tender-id="<?php echo $tender->id; ?>">
                                        <?php _e('Submit Bid', 'procurement-mgmt'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tender Details Modal -->
        <div id="tender-details-modal" class="tender-modal" >
            <div class="tender-modal-content">
                <span class="tender-modal-close">&times;</span>
                <div id="tender-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Bid Submission Modal -->
        <div id="bid-submission-modal" class="tender-modal" >
            <div class="tender-modal-content">
                <span class="tender-modal-close">&times;</span>
                <div id="bid-form-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // View tender details
            $(document).on('click', '.view-tender-btn', function(e) {
                e.preventDefault();
                var tenderId = $(this).data('tender-id');
                loadTenderDetails(tenderId);
            });
            
            // Submit bid
            $(document).on('click', '.bid-tender-btn', function(e) {
                e.preventDefault();
                var tenderId = $(this).data('tender-id');
                loadBidForm(tenderId);
            });
            
            // Modal close functionality
            $(document).on('click', '.tender-modal-close', function() {
                $(this).closest('.tender-modal').removeClass('show');
                $('body').removeClass('modal-open');
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('tender-modal')) {
                    $('.tender-modal').removeClass('show');
                    $('body').removeClass('modal-open');
                }
            });
            
            // Update time remaining
            updateTimeRemaining();
            setInterval(updateTimeRemaining, 60000); // Update every minute
            
            // Define functions locally to avoid dependency on global functions
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
            
            function updateTimeRemaining() {
                $('.time-remaining').each(function() {
                    var deadline = $(this).data('deadline');
                    var timeLeft = getTimeRemaining(deadline);
                    $(this).text(timeLeft);
                    
                    if (timeLeft === 'Expired') {
                        $(this).addClass('expired');
                        $(this).closest('.tender-card').addClass('expired');
                    }
                });
            }
            
            function getTimeRemaining(deadline) {
                var now = new Date().getTime();
                var deadlineTime = new Date(deadline).getTime();
                var difference = deadlineTime - now;
                
                if (difference < 0) {
                    return 'Expired';
                }
                
                var days = Math.floor(difference / (1000 * 60 * 60 * 24));
                var hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
                
                if (days > 0) {
                    return days + 'd ' + hours + 'h';
                } else if (hours > 0) {
                    return hours + 'h ' + minutes + 'm';
                } else {
                    return minutes + 'm';
                }
            }
        });
        </script>
        <?php
        return ob_get_clean();
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
    
    private function get_time_remaining($deadline) {
        $now = current_time('timestamp');
        $deadline_time = strtotime($deadline);
        $difference = $deadline_time - $now;
        
        if ($difference < 0) {
            return 'Expired';
        }
        
        $days = floor($difference / (60 * 60 * 24));
        $hours = floor(($difference % (60 * 60 * 24)) / (60 * 60));
        
        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        } else if ($hours > 0) {
            return $hours . 'h';
        } else {
            return floor($difference / 60) . 'm';
        }
    }
    
    private function notify_suppliers_new_tender($tender_id) {
        global $wpdb;
        
        // Get tender details
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tenders_table WHERE id = %d",
            $tender_id
        ));
        
        if (!$tender) return;
        
        // Get approved suppliers
        $suppliers_table = $wpdb->prefix . 'procurement_suppliers';
        $suppliers = $wpdb->get_results(
            "SELECT s.*, u.user_email FROM $suppliers_table s 
             JOIN {$wpdb->users} u ON s.user_id = u.ID 
             WHERE s.approval_status = 'approved'"
        );
        
        $site_name = get_bloginfo('name');
        $tender_url = home_url('/?tender_id=' . $tender_id);
        
        foreach ($suppliers as $supplier) {
            $subject = sprintf(__('[%s] New Tender Published: %s', 'procurement-mgmt'), $site_name, $tender->title);
            $message = sprintf(
                __("Dear %s,\n\nA new tender has been published that may interest you:\n\nTitle: %s\nCategory: %s\nEstimated Value: %s %s\nSubmission Deadline: %s\n\nView full details and submit your bid: %s\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $supplier->contact_person,
                $tender->title,
                ucfirst($tender->category),
                number_format($tender->estimated_value, 2),
                $tender->currency,
                date('F j, Y \a\t H:i', strtotime($tender->submission_deadline)),
                $tender_url,
                $site_name
            );
            
            wp_mail($supplier->user_email, $subject, $message);
        }
    }
    
    private function send_bid_confirmation_email($supplier, $tender) {
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Bid Confirmation: %s', 'procurement-mgmt'), $site_name, $tender->title);
        $message = sprintf(
            __("Dear %s,\n\nYour bid for the following tender has been successfully submitted:\n\nTender: %s\nSubmission Date: %s\n\nYou will be notified of the evaluation results in due course.\n\nBest regards,\n%s Team", 'procurement-mgmt'),
            $supplier->contact_person,
            $tender->title,
            current_time('F j, Y \a\t H:i'),
            $site_name
        );
        
        wp_mail($supplier->contact_email, $subject, $message);
    }
    
    public function send_deadline_reminders() {
        global $wpdb;
        
        // Get tenders expiring in 24 hours
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        $tomorrow = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $day_after = date('Y-m-d H:i:s', strtotime('+25 hours'));
        
        $expiring_tenders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tenders_table 
             WHERE status = 'published' 
             AND submission_deadline BETWEEN %s AND %s",
            $tomorrow,
            $day_after
        ));
        
        foreach ($expiring_tenders as $tender) {
            $this->send_deadline_reminder_emails($tender);
        }
    }
    
    private function send_deadline_reminder_emails($tender) {
        global $wpdb;
        
        // Get suppliers who haven't submitted bids yet
        $suppliers_table = $wpdb->prefix . 'procurement_suppliers';
        $bids_table = $wpdb->prefix . 'procurement_bids';
        
        $suppliers = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.user_email FROM $suppliers_table s 
             JOIN {$wpdb->users} u ON s.user_id = u.ID 
             WHERE s.approval_status = 'approved' 
             AND s.id NOT IN (
                 SELECT supplier_id FROM $bids_table WHERE tender_id = %d
             )",
            $tender->id
        ));
        
        $site_name = get_bloginfo('name');
        $tender_url = home_url('/?tender_id=' . $tender->id);
        
        foreach ($suppliers as $supplier) {
            $subject = sprintf(__('[%s] Reminder: Tender Deadline Tomorrow - %s', 'procurement-mgmt'), $site_name, $tender->title);
            $message = sprintf(
                __("Dear %s,\n\nThis is a reminder that the submission deadline for the following tender is tomorrow:\n\nTitle: %s\nDeadline: %s\n\nIf you're interested in this opportunity, please submit your bid soon: %s\n\nBest regards,\n%s Team", 'procurement-mgmt'),
                $supplier->contact_person,
                $tender->title,
                date('F j, Y \a\t H:i', strtotime($tender->submission_deadline)),
                $tender_url,
                $site_name
            );
            
            wp_mail($supplier->user_email, $subject, $message);
        }
    }
    
    public function handle_create_tender() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('title', 'description', 'category', 'estimated_value', 'requirements', 'submission_deadline');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'error' => 'missing_' . $field), admin_url('admin.php')));
                exit;
            }
        }
        
        // Handle document uploads
        $documents = array();
        if (!empty($_FILES['tender_documents']['name'][0])) {
            $documents = $this->handle_tender_file_uploads($_FILES['tender_documents']);
        }
        
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $result = $wpdb->insert(
            $table,
            array(
                'title' => sanitize_text_field($_POST['title']),
                'description' => wp_kses_post($_POST['description']),
                'category' => sanitize_text_field($_POST['category']),
                'estimated_value' => floatval($_POST['estimated_value']),
                'currency' => sanitize_text_field($_POST['currency'] ?? 'XOF'),
                'requirements' => wp_kses_post($_POST['requirements']),
                'submission_deadline' => sanitize_text_field($_POST['submission_deadline']),
                'status' => 'published',
                'publication_date' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'documents' => json_encode($documents)
            )
        );
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'error' => 'create_failed'), admin_url('admin.php')));
            exit;
        }
        
        $tender_id = $wpdb->insert_id;
        
        // Send notifications to approved suppliers
        $this->notify_suppliers_new_tender($tender_id);
        
        wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'success' => 'created'), admin_url('admin.php')));
        exit;
    }
    
    public function handle_update_tender() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        // Get existing tender
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $tender_id
        ));
        
        if (!$tender) {
            wp_die(__('Tender not found', 'procurement-mgmt'));
        }
        
        // Don't allow updates to published tenders with bids
        if ($tender->status === 'published') {
            $bids_table = $wpdb->prefix . 'procurement_bids';
            $bid_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bids_table WHERE tender_id = %d",
                $tender_id
            ));
            
            if ($bid_count > 0) {
                wp_die(__('Cannot update tender with existing bids', 'procurement-mgmt'));
            }
        }
        
        // Handle new document uploads
        $existing_documents = json_decode($tender->documents, true) ?: array();
        $new_documents = array();
        
        if (!empty($_FILES['tender_documents']['name'][0])) {
            $new_documents = $this->handle_tender_file_uploads($_FILES['tender_documents']);
        }
        
        $all_documents = array_merge($existing_documents, $new_documents);
        
        $update_data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description']),
            'category' => sanitize_text_field($_POST['category']),
            'estimated_value' => floatval($_POST['estimated_value']),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'XOF'),
            'requirements' => wp_kses_post($_POST['requirements']),
            'submission_deadline' => sanitize_text_field($_POST['submission_deadline']),
            'documents' => json_encode($all_documents)
        );
        
        $result = $wpdb->update($table, $update_data, array('id' => $tender_id));
        
        if ($result === false) {
            wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'error' => 'update_failed'), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'success' => 'updated'), admin_url('admin.php')));
        exit;
    }
    
    public function handle_publish_tender() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'procurement-mgmt'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'procurement_nonce')) {
            wp_die(__('Security check failed', 'procurement-mgmt'));
        }
        
        global $wpdb;
        $tender_id = intval($_POST['tender_id']);
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'published',
                'publication_date' => current_time('mysql')
            ),
            array('id' => $tender_id)
        );
        
        if ($result === false) {
            wp_die(__('Failed to publish tender', 'procurement-mgmt'));
        }
        
        // Send notifications to approved suppliers
        $this->notify_suppliers_new_tender($tender_id);
        
        wp_redirect(add_query_arg(array('page' => 'procurement-tenders', 'success' => 'published'), admin_url('admin.php')));
        exit;
    }
}
