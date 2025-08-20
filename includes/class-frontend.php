<?php
/**
 * Frontend functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_Frontend {
    
       public function __construct() {
        add_action('wp_ajax_get_bid_form', array($this, 'get_bid_form'));
        add_action('wp_ajax_nopriv_get_bid_form', array($this, 'get_bid_form'));
        add_action('wp_ajax_get_tender_details', array($this, 'get_tender_details_ajax'));
        add_action('wp_ajax_nopriv_get_tender_details', array($this, 'get_tender_details_ajax'));
        add_action('init', array($this, 'handle_tender_query'));
    }
    
    public function handle_tender_query() {
        if (isset($_GET['tender_id']) && is_numeric($_GET['tender_id'])) {
            // Handle individual tender page display
            add_filter('the_content', array($this, 'display_tender_page'));
        }
    }
    
    public function display_tender_page($content) {
        if (is_main_query() && in_the_loop()) {
            $tender_id = intval($_GET['tender_id']);
            $tender_content = $this->get_single_tender_content($tender_id);
            return $content . $tender_content;
        }
        return $content;
    }
    
    private function get_single_tender_content($tender_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'procurement_tenders';
        
        $tender = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'published'",
            $tender_id
        ));
        
        if (!$tender) {
            return '<p>' . __('Tender not found or no longer available.', 'procurement-mgmt') . '</p>';
        }
        
        $documents = json_decode($tender->documents, true) ?: array();
        $is_expired = strtotime($tender->submission_deadline) < current_time('timestamp');
        
        // Check if user can bid
        $user = wp_get_current_user();
        $is_supplier = is_user_logged_in() && in_array('supplier', $user->roles);
        $can_bid = $is_supplier && !$is_expired;
        
        ob_start();
        ?>
        <div class="procurement-single-tender">
            <div class="tender-header">
                <h2><?php echo esc_html($tender->title); ?></h2>
                <div class="tender-status">
                    <?php if ($is_expired): ?>
                        <span class="status-badge status-expired"><?php _e('Expired', 'procurement-mgmt'); ?></span>
                    <?php else: ?>
                        <span class="status-badge status-active"><?php _e('Active', 'procurement-mgmt'); ?></span>
                        <span class="time-remaining">
                            <?php echo $this->get_time_remaining($tender->submission_deadline); ?> <?php _e('remaining', 'procurement-mgmt'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tender-meta-grid">
                <div class="meta-card">
                    <h4><?php _e('Category', 'procurement-mgmt'); ?></h4>
                    <p><?php echo esc_html(ucfirst($tender->category)); ?></p>
                </div>
                <div class="meta-card">
                    <h4><?php _e('Estimated Value', 'procurement-mgmt'); ?></h4>
                    <p><?php echo number_format($tender->estimated_value, 2) . ' ' . $tender->currency; ?></p>
                </div>
                <div class="meta-card">
                    <h4><?php _e('Submission Deadline', 'procurement-mgmt'); ?></h4>
                    <p><?php echo date('F j, Y \a\t H:i', strtotime($tender->submission_deadline)); ?></p>
                </div>
                <div class="meta-card">
                    <h4><?php _e('Published', 'procurement-mgmt'); ?></h4>
                    <p><?php echo date('F j, Y', strtotime($tender->publication_date)); ?></p>
                </div>
            </div>
            
            <?php if ($can_bid): ?>
            <div class="bid-action-section">
                <button id="submit-bid-btn" class="btn btn-primary btn-large" data-tender-id="<?php echo $tender->id; ?>">
                    <?php _e('Submit Your Bid', 'procurement-mgmt'); ?>
                </button>
            </div>
            <?php elseif (!is_user_logged_in()): ?>
            <div class="login-prompt">
                <p><?php _e('Please log in as an approved supplier to submit a bid.', 'procurement-mgmt'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="btn btn-secondary">
                    <?php _e('Log In', 'procurement-mgmt'); ?>
                </a>
            </div>
            <?php else: ?>
            <div class="access-prompt">
                <p><?php _e('You need to be an approved supplier to submit bids. Please complete your supplier registration.', 'procurement-mgmt'); ?></p>
                <?php 
                // Check if user has a pending supplier registration
                global $wpdb;
                $supplier_table = $wpdb->prefix . 'procurement_suppliers';
                $user_supplier = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $supplier_table WHERE user_id = %d",
                    get_current_user_id()
                ));
                
                if ($user_supplier): ?>
                    <?php if ($user_supplier->approval_status === 'pending'): ?>
                        <p class="status-info"><?php _e('Your registration is pending approval.', 'procurement-mgmt'); ?></p>
                    <?php elseif ($user_supplier->approval_status === 'rejected'): ?>
                        <p class="status-info"><?php _e('Your registration was rejected. Please contact administration.', 'procurement-mgmt'); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo home_url('/supplier-registration'); ?>" class="btn btn-primary">
                        <?php _e('Register as Supplier', 'procurement-mgmt'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="tender-content-sections">
                <div class="content-section">
                    <h3><?php _e('Description', 'procurement-mgmt'); ?></h3>
                    <div class="content-text">
                        <?php echo wp_kses_post($tender->description); ?>
                    </div>
                </div>
                
                <div class="content-section">
                    <h3><?php _e('Requirements', 'procurement-mgmt'); ?></h3>
                    <div class="content-text">
                        <?php echo wp_kses_post($tender->requirements); ?>
                    </div>
                </div>
                
                <?php if (!empty($documents)): ?>
                <div class="content-section">
                    <h3><?php _e('Tender Documents', 'procurement-mgmt'); ?></h3>
                    <div class="documents-grid">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-card">
                                <div class="document-icon">
                                    <?php echo $this->get_file_icon($doc['type']); ?>
                                </div>
                                <div class="document-info">
                                    <h5><?php echo esc_html($doc['name']); ?></h5>
                                    <p><?php echo size_format($doc['size']); ?></p>
                                </div>
                                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" class="btn btn-sm btn-outline">
                                    <?php _e('Download', 'procurement-mgmt'); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bid Submission Modal -->
        <div id="bid-modal" class="procurement-modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <div id="bid-form-container">
                    <!-- Bid form loaded here -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#submit-bid-btn').on('click', function() {
                var tenderId = $(this).data('tender-id');
                loadBidForm(tenderId);
            });
            
            $('.modal-close').on('click', function() {
                $('#bid-modal').removeClass('show');
                $('body').removeClass('modal-open');
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('procurement-modal')) {
                    $('#bid-modal').removeClass('show');
                    $('body').removeClass('modal-open');
                }
            });
            
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
                            $('#bid-form-container').html(response.data);
                            $('#bid-modal').addClass('show').show();
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
    
   public function get_bid_form() {
        // Debug logging
        error_log('get_bid_form function called');
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
                    <button type="button" class="btn btn-secondary" onclick="jQuery('#bid-modal').hide();">
                        <?php _e('Cancel', 'procurement-mgmt'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#bid-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'submit_bid');
                
                var $form = $(this);
                $form.addClass('loading');
                $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $form.removeClass('loading');
                        $form.find('button[type="submit"]').prop('disabled', false).text('Submit Bid');
                        
                        if (response.success) {
                            alert(response.data);
                            $('#bid-modal').removeClass('show');
                            $('body').removeClass('modal-open');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        $form.removeClass('loading');
                        $form.find('button[type="submit"]').prop('disabled', false).text('Submit Bid');
                        alert('<?php _e('Network error. Please try again.', 'procurement-mgmt'); ?>');
                        console.error('AJAX Error:', error);
                    }
                });
            });
        });
        </script>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    public function get_tender_details_ajax() {
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
        $is_expired = strtotime($tender->submission_deadline) < current_time('timestamp');
        
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
            return __('Expired', 'procurement-mgmt');
        }
        
        $days = floor($difference / (60 * 60 * 24));
        $hours = floor(($difference % (60 * 60 * 24)) / (60 * 60));
        $minutes = floor(($difference % (60 * 60)) / 60);
        
        if ($days > 0) {
            return sprintf(__('%d days, %d hours', 'procurement-mgmt'), $days, $hours);
        } else if ($hours > 0) {
            return sprintf(__('%d hours, %d minutes', 'procurement-mgmt'), $hours, $minutes);
        } else {
            return sprintf(__('%d minutes', 'procurement-mgmt'), $minutes);
        }
    }
    
    private function get_file_icon($mime_type) {
        switch ($mime_type) {
            case 'application/pdf':
                return '📄';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return '📝';
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return '📊';
            default:
                return '📎';
        }
    }
}