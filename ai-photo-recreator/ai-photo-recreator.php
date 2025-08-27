<?php
/**
 * Plugin Name: AI Photo Recreator
 * Plugin URI: https://github.com/onurgezer1/ai-projesi
 * Description: WordPress yapay zeka fotoğraf yeniden oluşturma plugin'i. Kullanıcıların yüklediği fotoğrafları belirtilen talimatlara göre AI ile yeniden oluşturur.
 * Version: 1.0.0
 * Author: Onur Gezer
 * Text Domain: ai-photo-recreator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Security check - prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_PHOTO_RECREATOR_VERSION', '1.0.0');
define('AI_PHOTO_RECREATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_PHOTO_RECREATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_PHOTO_RECREATOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class AI_Photo_Recreator {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        
        // Public hooks
        add_action('wp_enqueue_scripts', array($this, 'public_enqueue_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_ai_photo_process', array($this, 'ajax_process_photo'));
        add_action('wp_ajax_nopriv_ai_photo_process', array($this, 'ajax_process_photo'));
        add_action('wp_ajax_ai_photo_cleanup', array($this, 'ajax_cleanup_files'));
        add_action('wp_ajax_ai_photo_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_ai_photo_delete_record', array($this, 'ajax_delete_record'));
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('ai-photo-recreator', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once AI_PHOTO_RECREATOR_PLUGIN_DIR . 'includes/class-ai-processor.php';
        require_once AI_PHOTO_RECREATOR_PLUGIN_DIR . 'includes/class-file-handler.php';
        require_once AI_PHOTO_RECREATOR_PLUGIN_DIR . 'includes/class-shortcode.php';
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Initialize shortcode
        new AI_Photo_Recreator_Shortcode();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check system requirements
        if (!extension_loaded('gd')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('AI Photo Recreator requires the GD extension to be installed. Please contact your hosting provider.', 'ai-photo-recreator'));
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('AI Photo Recreator requires PHP 7.4 or higher. Please contact your hosting provider.', 'ai-photo-recreator'));
        }
        
        // Create upload directory structure
        $upload_dir = wp_upload_dir();
        $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator';
        
        $directories = array(
            $ai_dir,
            $ai_dir . '/original',
            $ai_dir . '/processed',
            $ai_dir . '/temp'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add .htaccess for security (prevent direct access to originals)
                if (strpos($dir, '/original') !== false) {
                    $htaccess_content = "Options -Indexes\nDeny from all";
                    file_put_contents($dir . '/.htaccess', $htaccess_content);
                }
            }
        }
        
        // Add default options
        add_option('ai_photo_recreator_options', array(
            'max_file_size' => 5242880, // 5MB
            'allowed_formats' => array('jpg', 'jpeg', 'png', 'webp'),
            'rate_limit' => 10, // requests per hour
            'api_key' => ''
        ));
        
        // Create database table for processing history
        $this->create_database_table();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files
        $this->cleanup_temp_files();
    }
    
    /**
     * Create database table for processing history
     */
    private function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            original_file varchar(255) NOT NULL,
            processed_file varchar(255) NOT NULL,
            instructions text NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'processing',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/temp/';
        
        if (is_dir($ai_dir)) {
            $files = glob($ai_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Photo Recreator', 'ai-photo-recreator'),
            __('AI Photo Recreator', 'ai-photo-recreator'),
            'manage_options',
            'ai-photo-recreator',
            array($this, 'admin_page'),
            'dashicons-format-image',
            30
        );
        
        add_submenu_page(
            'ai-photo-recreator',
            __('Settings', 'ai-photo-recreator'),
            __('Settings', 'ai-photo-recreator'),
            'manage_options',
            'ai-photo-recreator-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ai-photo-recreator',
            __('History', 'ai-photo-recreator'),
            __('History', 'ai-photo-recreator'),
            'manage_options',
            'ai-photo-recreator-history',
            array($this, 'history_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'ai-photo-recreator') !== false) {
            wp_enqueue_style('ai-photo-recreator-admin', AI_PHOTO_RECREATOR_PLUGIN_URL . 'admin/css/admin-style.css', array(), AI_PHOTO_RECREATOR_VERSION);
            wp_enqueue_script('ai-photo-recreator-admin', AI_PHOTO_RECREATOR_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), AI_PHOTO_RECREATOR_VERSION, true);
            
            wp_localize_script('ai-photo-recreator-admin', 'aiPhotoRecreator', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_photo_recreator_nonce'),
                'strings' => array(
                    'processing' => __('Processing...', 'ai-photo-recreator'),
                    'error' => __('An error occurred', 'ai-photo-recreator'),
                    'success' => __('Photo processed successfully', 'ai-photo-recreator')
                )
            ));
        }
    }
    
    /**
     * Enqueue public scripts and styles
     */
    public function public_enqueue_scripts() {
        wp_enqueue_style('ai-photo-recreator-public', AI_PHOTO_RECREATOR_PLUGIN_URL . 'public/css/public-style.css', array(), AI_PHOTO_RECREATOR_VERSION);
        wp_enqueue_script('ai-photo-recreator-public', AI_PHOTO_RECREATOR_PLUGIN_URL . 'public/js/public-script.js', array('jquery'), AI_PHOTO_RECREATOR_VERSION, true);
        
        wp_localize_script('ai-photo-recreator-public', 'aiPhotoRecreator', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_photo_recreator_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'ai-photo-recreator'),
                'error' => __('An error occurred', 'ai-photo-recreator'),
                'success' => __('Photo processed successfully', 'ai-photo-recreator'),
                'selectFile' => __('Please select a file', 'ai-photo-recreator'),
                'enterInstructions' => __('Please enter instructions', 'ai-photo-recreator')
            )
        ));
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include AI_PHOTO_RECREATOR_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include AI_PHOTO_RECREATOR_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * History page
     */
    public function history_page() {
        include AI_PHOTO_RECREATOR_PLUGIN_DIR . 'admin/history-page.php';
    }
    
    /**
     * AJAX photo processing
     */
    public function ajax_process_photo() {
        try {
            // Security check
            if (!wp_verify_nonce($_POST['nonce'], 'ai_photo_recreator_nonce')) {
                wp_send_json_error(__('Security check failed', 'ai-photo-recreator'));
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('upload_files')) {
                wp_send_json_error(__('You do not have permission to upload files', 'ai-photo-recreator'));
                return;
            }
            
            // Validate input
            if (empty($_FILES['photo'])) {
                wp_send_json_error(__('No photo uploaded', 'ai-photo-recreator'));
                return;
            }
            
            if (empty($_POST['instructions'])) {
                wp_send_json_error(__('No instructions provided', 'ai-photo-recreator'));
                return;
            }
            
            // Check if required classes exist
            if (!class_exists('AI_Photo_Processor')) {
                wp_send_json_error(__('AI Processor not available', 'ai-photo-recreator'));
                return;
            }
            
            if (!class_exists('AI_Photo_File_Handler')) {
                wp_send_json_error(__('File Handler not available', 'ai-photo-recreator'));
                return;
            }
            
            // Process the photo
            $processor = new AI_Photo_Processor();
            $result = $processor->process_photo($_FILES['photo'], $_POST['instructions']);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                // Log the error for debugging
                error_log('AI Photo Recreator Error: ' . $result['message']);
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            // Log the exception
            error_log('AI Photo Recreator Exception: ' . $e->getMessage());
            wp_send_json_error(__('An unexpected error occurred', 'ai-photo-recreator'));
        }
    }
    
    /**
     * AJAX cleanup files
     */
    public function ajax_cleanup_files() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'ai_photo_cleanup_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-photo-recreator'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions', 'ai-photo-recreator'));
        }
        
        try {
            $file_handler = new AI_Photo_File_Handler();
            $file_handler->cleanup_old_files(7); // Clean files older than 7 days
            
            wp_send_json_success(__('Files cleaned up successfully', 'ai-photo-recreator'));
        } catch (Exception $e) {
            wp_send_json_error(__('Error during cleanup', 'ai-photo-recreator'));
        }
    }
    
    /**
     * AJAX get statistics
     */
    public function ajax_get_stats() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'ai_photo_recreator_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-photo-recreator'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions', 'ai-photo-recreator'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        $stats = array(
            'total_processed' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'"),
            'total_processing' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing'"),
            'total_errors' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'error'"),
            'today_processed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND DATE(created_at) = %s",
                current_time('Y-m-d')
            ))
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX delete record
     */
    public function ajax_delete_record() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'ai_photo_delete_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-photo-recreator'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions', 'ai-photo-recreator'));
        }
        
        $record_id = absint($_POST['id']);
        if (!$record_id) {
            wp_send_json_error(__('Invalid record ID', 'ai-photo-recreator'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        // Get record to delete associated files
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $record_id));
        
        if ($record) {
            // Delete associated files
            if (!empty($record->original_file) && file_exists($record->original_file)) {
                unlink($record->original_file);
            }
            if (!empty($record->processed_file) && file_exists($record->processed_file)) {
                unlink($record->processed_file);
            }
            
            // Delete database record
            $result = $wpdb->delete($table_name, array('id' => $record_id), array('%d'));
            
            if ($result) {
                wp_send_json_success(__('Record deleted successfully', 'ai-photo-recreator'));
            } else {
                wp_send_json_error(__('Failed to delete record', 'ai-photo-recreator'));
            }
        } else {
            wp_send_json_error(__('Record not found', 'ai-photo-recreator'));
        }
    }
}

// Initialize plugin
AI_Photo_Recreator::get_instance();