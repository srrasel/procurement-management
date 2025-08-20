<?php
/**
 * Plugin Name: Procurement Management System
 * Plugin URI: https://srrasel.xyz
 * Description: Complete procurement management system with supplier registration, tender management, and PPM document handling.
 * Version: 1.0.0
 * Author: Rasel Hossain
 * Author URI: https://srrasel.xyz
 * License: GPL v2 or later
 * Text Domain: procurement-mgmt
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PROCUREMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROCUREMENT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PROCUREMENT_VERSION', '1.0.0');

// Main plugin class
class ProcurementManagement {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('procurement-mgmt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-database.php';
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-supplier.php';
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-tender.php';
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-ppm.php';
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-admin.php';
        require_once PROCUREMENT_PLUGIN_PATH . 'includes/class-frontend.php';
    }
    
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Initialize components
        new Procurement_Database();
        new Procurement_Supplier();
        new Procurement_Tender();
        new Procurement_PPM();
        
        if (is_admin()) {
            new Procurement_Admin();
        } else {
            new Procurement_Frontend();
        }
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('procurement-frontend', PROCUREMENT_PLUGIN_URL . 'assets/css/frontend.css', array(), PROCUREMENT_VERSION);
        wp_enqueue_script('procurement-frontend', PROCUREMENT_PLUGIN_URL . 'assets/js/frontend.jsx', array('jquery'), PROCUREMENT_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('procurement-frontend', 'procurement_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('procurement_nonce')
        ));
    }
    
    public function enqueue_admin_assets() {
        wp_enqueue_style('procurement-admin', PROCUREMENT_PLUGIN_URL . 'assets/css/admin.css', array(), PROCUREMENT_VERSION);
        wp_enqueue_script('procurement-admin', PROCUREMENT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PROCUREMENT_VERSION, true);
    }
    
    public function activate() {
        $this->load_dependencies();
        
        // Create database tables
        $database = new Procurement_Database();
        $database->create_tables();
        
        // Create upload directories
        $this->create_upload_directories();
        
        // Set default options
        add_option('procurement_version', PROCUREMENT_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $procurement_dir = $upload_dir['basedir'] . '/procurement';
        
        if (!file_exists($procurement_dir)) {
            wp_mkdir_p($procurement_dir);
        }
        
        // Create subdirectories
        $subdirs = array('tenders', 'ppm', 'suppliers');
        foreach ($subdirs as $subdir) {
            $dir_path = $procurement_dir . '/' . $subdir;
            if (!file_exists($dir_path)) {
                wp_mkdir_p($dir_path);
            }
        }
    }
}

// Initialize the plugin
new ProcurementManagement();
