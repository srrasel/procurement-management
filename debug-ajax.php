<?php
/**
 * Debug script for AJAX issues
 * Place this in your WordPress root directory and access via browser
 */

// Include WordPress
require_once('wp-config.php');

// Check if user is logged in
$user_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();

// Check if procurement_ajax is properly localized
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('procurement_nonce');

// Test AJAX handler registration
global $wp_filter;
$ajax_handlers = array();
if (isset($wp_filter['wp_ajax_get_bid_form'])) {
    $ajax_handlers['get_bid_form'] = 'Registered';
} else {
    $ajax_handlers['get_bid_form'] = 'NOT REGISTERED';
}

if (isset($wp_filter['wp_ajax_submit_bid'])) {
    $ajax_handlers['submit_bid'] = 'Registered';
} else {
    $ajax_handlers['submit_bid'] = 'NOT REGISTERED';
}

if (isset($wp_filter['wp_ajax_get_tender_details'])) {
    $ajax_handlers['get_tender_details'] = 'Registered';
} else {
    $ajax_handlers['get_tender_details'] = 'NOT REGISTERED';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement AJAX Debug</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .debug-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .error {
            color: #d63031;
            background: #ffe6e6;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: #00b894;
            background: #e6ffe6;
            padding: 10px;
            border-radius: 4px;
        }
        .info {
            color: #0984e3;
            background: #e6f3ff;
            padding: 10px;
            border-radius: 4px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
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
    </style>
</head>
<body>
    <h1>Procurement AJAX Debug Information</h1>
    
    <div class="debug-section">
        <h2>WordPress Environment</h2>
        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
        <p><strong>Site URL:</strong> <?php echo get_site_url(); ?></p>
        <p><strong>Admin URL:</strong> <?php echo admin_url(); ?></p>
        <p><strong>AJAX URL:</strong> <?php echo $ajax_url; ?></p>
        <p><strong>User Logged In:</strong> <?php echo $user_logged_in ? 'Yes' : 'No'; ?></p>
        <?php if ($user_logged_in): ?>
            <p><strong>Current User:</strong> <?php echo $current_user->user_login; ?> (ID: <?php echo $current_user->ID; ?>)</p>
            <p><strong>User Roles:</strong> <?php echo implode(', ', $current_user->roles); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="debug-section">
        <h2>AJAX Handler Registration</h2>
        <?php foreach ($ajax_handlers as $handler => $status): ?>
            <p><strong><?php echo $handler; ?>:</strong> 
                <span class="<?php echo $status === 'Registered' ? 'success' : 'error'; ?>">
                    <?php echo $status; ?>
                </span>
            </p>
        <?php endforeach; ?>
    </div>
    
    <div class="debug-section">
        <h2>JavaScript Configuration</h2>
        <p><strong>Nonce:</strong> <?php echo $nonce; ?></p>
        <div id="js-config"></div>
    </div>
    
    <div class="debug-section">
        <h2>Test AJAX Requests</h2>
        <button onclick="testGetBidForm()">Test get_bid_form</button>
        <button onclick="testGetTenderDetails()">Test get_tender_details</button>
        <button onclick="testSubmitBid()">Test submit_bid</button>
        <div id="ajax-results"></div>
    </div>
    
    <div class="debug-section">
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
        ?>
    </div>

    <script>
        // Set up procurement_ajax for testing
        window.procurement_ajax = {
            ajax_url: '<?php echo $ajax_url; ?>',
            nonce: '<?php echo $nonce; ?>'
        };
        
        // Display JavaScript configuration
        document.getElementById('js-config').innerHTML = `
            <p><strong>procurement_ajax.ajax_url:</strong> ${window.procurement_ajax.ajax_url}</p>
            <p><strong>procurement_ajax.nonce:</strong> ${window.procurement_ajax.nonce}</p>
        `;
        
        function testGetBidForm() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = '<p>Testing get_bid_form...</p>';
            
            $.ajax({
                url: window.procurement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_bid_form',
                    tender_id: 1,
                    nonce: window.procurement_ajax.nonce
                },
                success: function(response) {
                    results.innerHTML += `<p class="success">SUCCESS: ${JSON.stringify(response)}</p>`;
                },
                error: function(xhr, status, error) {
                    results.innerHTML += `<p class="error">ERROR: ${status} - ${error}</p>`;
                    results.innerHTML += `<pre>${xhr.responseText}</pre>`;
                }
            });
        }
        
        function testGetTenderDetails() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = '<p>Testing get_tender_details...</p>';
            
            $.ajax({
                url: window.procurement_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_tender_details',
                    tender_id: 1,
                    nonce: window.procurement_ajax.nonce
                },
                success: function(response) {
                    results.innerHTML += `<p class="success">SUCCESS: ${JSON.stringify(response)}</p>`;
                },
                error: function(xhr, status, error) {
                    results.innerHTML += `<p class="error">ERROR: ${status} - ${error}</p>`;
                    results.innerHTML += `<pre>${xhr.responseText}</pre>`;
                }
            });
        }
        
        function testSubmitBid() {
            const results = document.getElementById('ajax-results');
            results.innerHTML = '<p>Testing submit_bid...</p>';
            
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
                    results.innerHTML += `<p class="success">SUCCESS: ${JSON.stringify(response)}</p>`;
                },
                error: function(xhr, status, error) {
                    results.innerHTML += `<p class="error">ERROR: ${status} - ${error}</p>`;
                    results.innerHTML += `<pre>${xhr.responseText}</pre>`;
                }
            });
        }
    </script>
</body>
</html>
