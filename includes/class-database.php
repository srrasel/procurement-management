<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Procurement_Database {
    
    public function __construct() {
        // Constructor
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Suppliers table
        $suppliers_table = $wpdb->prefix . 'procurement_suppliers';
        $suppliers_sql = "CREATE TABLE $suppliers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            company_name varchar(255) NOT NULL,
            company_address text NOT NULL,
            contact_person varchar(255) NOT NULL,
            contact_email varchar(255) NOT NULL,
            contact_phone varchar(50) NOT NULL,
            ifu_number varchar(100) NOT NULL,
            rccm_number varchar(100) NOT NULL,
            business_category varchar(255) NOT NULL,
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            approval_status enum('pending','approved','rejected') DEFAULT 'pending',
            approval_date datetime NULL,
            approved_by bigint(20) UNSIGNED NULL,
            rejection_reason text NULL,
            documents longtext NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ifu_number (ifu_number),
            UNIQUE KEY rccm_number (rccm_number),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Tenders table
        $tenders_table = $wpdb->prefix . 'procurement_tenders';
        $tenders_sql = "CREATE TABLE $tenders_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description longtext NOT NULL,
            category varchar(255) NOT NULL,
            estimated_value decimal(15,2) NOT NULL,
            currency varchar(10) DEFAULT 'XOF',
            requirements longtext NOT NULL,
            submission_deadline datetime NOT NULL,
            publication_date datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('draft','published','closed','awarded') DEFAULT 'draft',
            created_by bigint(20) UNSIGNED NOT NULL,
            documents longtext NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY status (status),
            KEY submission_deadline (submission_deadline)
        ) $charset_collate;";
        
        // Bids table
        $bids_table = $wpdb->prefix . 'procurement_bids';
        $bids_sql = "CREATE TABLE $bids_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tender_id mediumint(9) NOT NULL,
            supplier_id mediumint(9) NOT NULL,
            bid_amount decimal(15,2) NOT NULL,
            currency varchar(10) DEFAULT 'XOF',
            technical_proposal longtext NOT NULL,
            financial_proposal longtext NOT NULL,
            documents longtext NULL,
            submission_date datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('submitted','under_review','accepted','rejected') DEFAULT 'submitted',
            PRIMARY KEY (id),
            KEY tender_id (tender_id),
            KEY supplier_id (supplier_id),
            UNIQUE KEY unique_bid (tender_id, supplier_id)
        ) $charset_collate;";
        
        // PPM Documents table
        $ppm_table = $wpdb->prefix . 'procurement_ppm';
        $ppm_sql = "CREATE TABLE $ppm_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            version varchar(50) NOT NULL,
            document_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            mime_type varchar(100) NOT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            uploaded_by bigint(20) UNSIGNED NOT NULL,
            is_current tinyint(1) DEFAULT 0,
            admin_notes longtext NULL,
            download_count bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            KEY version (version),
            KEY is_current (is_current),
            KEY upload_date (upload_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($suppliers_sql);
        dbDelta($tenders_sql);
        dbDelta($bids_sql);
        dbDelta($ppm_sql);
    }
    
    public function get_suppliers($status = 'all', $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'procurement_suppliers';
        $where = '';
        
        if ($status !== 'all') {
            $where = $wpdb->prepare("WHERE approval_status = %s", $status);
        }
        
        $sql = "SELECT * FROM $table $where ORDER BY registration_date DESC LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    public function get_tenders($status = 'published', $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'procurement_tenders';
        $where = $wpdb->prepare("WHERE status = %s", $status);
        
        $sql = "SELECT * FROM $table $where ORDER BY publication_date DESC LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    public function get_current_ppm() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'procurement_ppm';
        $sql = "SELECT * FROM $table WHERE is_current = 1 ORDER BY upload_date DESC LIMIT 1";
        
        return $wpdb->get_row($sql);
    }
    
    public function get_ppm_versions($limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'procurement_ppm';
        $sql = "SELECT * FROM $table ORDER BY upload_date DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }
}
