<?php
/**
 * Test AJAX Handlers
 * Place this in your WordPress root directory and access via browser
 */

// Include WordPress
require_once('wp-config.php');

// Check if user is logged in
$user_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();

// Check AJAX handler registration
global $wp_filter;
$ajax_handlers = array();

// Check all relevant AJAX handlers
$handlers_to_check = array(
    'wp_ajax_get_bid_form',
    'wp_ajax_nopriv_get_bid_form',
    'wp_ajax_get_tender_details',
    'wp_ajax_nopriv_get_tender_details',
    'wp_ajax_submit_bid',
    'wp_ajax_nopriv_submit_bid'
);

foreach ($handlers_to_check as $handler) {
    if (isset($wp_filter[$handler])) {
        $ajax_handlers[$handler] = 'Registered';
    } else {
        $ajax_handlers[$handler] = 'NOT REGISTERED';
    }
}

// Get AJAX URL and nonce
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('procurement_nonce');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test AJAX Handlers</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .result {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            white-space: pre-wrap;
        }
        .error {
            background: #ffe6e6;
            color: #d63031;
        }
        .success {
            background: #e6ffe6;
            color: #00b894;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
        }
        button {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            background: #3498db;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background: #2980b9;
        }
        .user-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Test AJAX Handlers</h1>
    
    <div class="user-info">
        <h2>User Information</h2>
        <p><strong>Logged In:</strong> <?php echo $user_logged_in ? 'Yes' : 'No'; ?></p>
        <?php if ($user_logged_in): ?>
            <p><strong>User:</strong> <?php echo $current_user->user_login; ?> (ID: <?php echo $current_user->ID; ?>)</p>
            <p><strong>Roles:</strong> <?php echo implode(', ', $current_user->roles); ?></p>
        <?php endif; ?>
        <p><strong>AJAX URL:</strong> <?php echo $ajax_url; ?></p>
        <p><strong>Nonce:</strong> <?php echo $nonce; ?></p>
    </div>
    
    <div class="test-section">
        <h2>AJAX Handler Registration Status</h2>
        <?php foreach ($ajax_handlers as $handler => $status): ?>
            <p><strong><?php echo $handler; ?>:</strong> 
                <span class="<?php echo $status === 'Registered' ? 'success' : 'error'; ?>">
                    <?php echo $status; ?>
                </span>
            </p>
        <?php endforeach; ?>
    </div>
    
    <div class="test-section">
        <h2>Test AJAX Requests</h2>
        <button onclick="testGetBidForm()">Test get_bid_form</button>
        <button onclick="testGetTenderDetails()">Test get_tender_details</button>
        <button onclick="testSubmitBid()">Test submit_bid</button>
        <div id="ajax-results" class="result"></div>
    </div>
    
    <div class="test-section">
        <h2>Plugin Status</h2>
        <?php
        $plugin_file = 'cci-bf-procurement/procurement-management.php';
        if (is_plugin_active($plugin_file)) {
            echo '<p class="success">Plugin is active</p>';
        } else {
            echo '<p class="error">Plugin is NOT active</p>';
        }
        
        // Check if plugin files exist
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (file_exists($plugin_path)) {
            echo '<p class="success">Plugin file exists</p>';
        } else {
            echo '<p class="error">Plugin file does not exist</p>';
        }
        
        // Check if classes are loaded
        if (class_exists('Procurement_Supplier')) {
            echo '<p class="success">Procurement_Supplier class loaded</p>';
        } else {
            echo '<p class="error">Procurement_Supplier class NOT loaded</p>';
        }
        
        if (class_exists('Procurement_Tender')) {
            echo '<p class="success">Procurement_Tender class loaded</p>';
        } else {
            echo '<p class="error">Procurement_Tender class NOT loaded</p>';
        }
        
        if (class_exists('Procurement_Frontend')) {
            echo '<p class="success">Procurement_Frontend class loaded</p>';
        } else {
            echo '<p class="error">Procurement_Frontend class NOT loaded</p>';
        }
        ?>
    </div>

    <script>
        // Set up procurement_ajax for testing
        window.procurement_ajax = {
            ajax_url: '<?php echo $ajax_url; ?>',
            nonce: '<?php echo $nonce; ?>'
        };
        
        function testGetBidForm() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = 'Testing get_bid_form...\n';
            results.className = 'result';
            
            $.ajax({
                url: window.procurement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_bid_form',
                    tender_id: 1,
                    nonce: window.procurement_ajax.nonce
                },
                success: function(response) {
                    results.innerHTML += 'SUCCESS: AJAX request completed\n';
                    results.innerHTML += 'Response: ' + JSON.stringify(response, null, 2) + '\n';
                    results.className = 'result success';
                },
                error: function(xhr, status, error) {
                    results.innerHTML += 'ERROR: AJAX request failed\n';
                    results.innerHTML += 'Status: ' + status + '\n';
                    results.innerHTML += 'Error: ' + error + '\n';
                    results.innerHTML += 'Response Text: ' + xhr.responseText + '\n';
                    results.innerHTML += 'Status Code: ' + xhr.status + '\n';
                    results.className = 'result error';
                }
            });
        }
        
        function testGetTenderDetails() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = 'Testing get_tender_details...\n';
            results.className = 'result';
            
            $.ajax({
                url: window.procurement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_tender_details',
                    tender_id: 1,
                    nonce: window.procurement_ajax.nonce
                },
                success: function(response) {
                    results.innerHTML += 'SUCCESS: AJAX request completed\n';
                    results.innerHTML += 'Response: ' + JSON.stringify(response, null, 2) + '\n';
                    results.className = 'result success';
                },
                error: function(xhr, status, error) {
                    results.innerHTML += 'ERROR: AJAX request failed\n';
                    results.innerHTML += 'Status: ' + status + '\n';
                    results.innerHTML += 'Error: ' + error + '\n';
                    results.innerHTML += 'Response Text: ' + xhr.responseText + '\n';
                    results.innerHTML += 'Status Code: ' + xhr.status + '\n';
                    results.className = 'result error';
                }
            });
        }
        
        function testSubmitBid() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = 'Testing submit_bid...\n';
            results.className = 'result';
            
            const formData = new FormData();
            formData.append('action', 'submit_bid');
            formData.append('bid_nonce', window.procurement_ajax.nonce);
            formData.append('tender_id', '1');
            formData.append('bid_amount', '1000');
            formData.append('technical_proposal', 'Test proposal');
            formData.append('financial_proposal', 'Test financial');
            
            $.ajax({
                url: window.procurement_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    results.innerHTML += 'SUCCESS: Form submitted\n';
                    results.innerHTML += 'Response: ' + JSON.stringify(response, null, 2) + '\n';
                    results.className = 'result success';
                },
                error: function(xhr, status, error) {
                    results.innerHTML += 'ERROR: Form submission failed\n';
                    results.innerHTML += 'Status: ' + status + '\n';
                    results.innerHTML += 'Error: ' + error + '\n';
                    results.innerHTML += 'Response Text: ' + xhr.responseText + '\n';
                    results.innerHTML += 'Status Code: ' + xhr.status + '\n';
                    results.className = 'result error';
                }
            });
        }
    </script>
</body>
</html>
