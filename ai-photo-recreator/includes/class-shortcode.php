<?php
/**
 * Shortcode Class
 * 
 * Handles shortcode functionality for frontend display
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class AI_Photo_Recreator_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('ai_photo_recreator', array($this, 'render_shortcode'));
        add_action('wp_ajax_ai_photo_process', array($this, 'handle_ajax_process'));
        add_action('wp_ajax_nopriv_ai_photo_process', array($this, 'handle_ajax_process'));
        add_action('wp_ajax_ai_photo_download', array($this, 'handle_download'));
        add_action('wp_ajax_nopriv_ai_photo_download', array($this, 'handle_download'));
    }
    
    /**
     * Render shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Default attributes
        $atts = shortcode_atts(array(
            'title' => __('AI Photo Recreator', 'ai-photo-recreator'),
            'show_title' => 'true',
            'max_width' => '800px'
        ), $atts, 'ai_photo_recreator');
        
        // Check if user can upload files
        if (!current_user_can('upload_files')) {
            return '<div class="ai-photo-recreator-error">' . 
                   __('You need to be logged in to use this feature.', 'ai-photo-recreator') . 
                   '</div>';
        }
        
        // Start output buffering
        ob_start();
        ?>
        
        <div class="ai-photo-recreator-container" style="max-width: <?php echo esc_attr($atts['max_width']); ?>;">
            <?php if ($atts['show_title'] === 'true'): ?>
                <h3 class="ai-photo-recreator-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <div class="ai-photo-recreator-form">
                <form id="ai-photo-form" enctype="multipart/form-data">
                    <!-- File Upload Section -->
                    <div class="form-section">
                        <label for="photo-upload" class="form-label">
                            <?php _e('Select Photo', 'ai-photo-recreator'); ?>
                            <span class="required">*</span>
                        </label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="photo-upload" name="photo" accept="image/*" required>
                            <div class="file-upload-info">
                                <small class="help-text">
                                    <?php 
                                    $options = get_option('ai_photo_recreator_options');
                                    printf(
                                        __('Max file size: %s. Allowed formats: %s', 'ai-photo-recreator'),
                                        size_format($options['max_file_size']),
                                        implode(', ', $options['allowed_formats'])
                                    );
                                    ?>
                                </small>
                            </div>
                        </div>
                        <div id="image-preview" class="image-preview" style="display: none;">
                            <img id="preview-image" src="" alt="Preview">
                            <button type="button" id="remove-image" class="remove-image-btn">×</button>
                        </div>
                    </div>
                    
                    <!-- Instructions Section -->
                    <div class="form-section">
                        <label for="instructions" class="form-label">
                            <?php _e('Instructions', 'ai-photo-recreator'); ?>
                            <span class="required">*</span>
                        </label>
                        <textarea 
                            id="instructions" 
                            name="instructions" 
                            rows="4" 
                            placeholder="<?php esc_attr_e('Describe how you want the photo to be recreated...', 'ai-photo-recreator'); ?>"
                            required
                        ></textarea>
                        <small class="help-text">
                            <?php _e('Examples: "Make this person smile", "Change background to nature", "Convert to vintage style"', 'ai-photo-recreator'); ?>
                        </small>
                    </div>
                    
                    <!-- Submit Section -->
                    <div class="form-section">
                        <button type="submit" id="process-btn" class="process-button">
                            <span class="btn-text"><?php _e('Process Photo', 'ai-photo-recreator'); ?></span>
                            <span class="btn-spinner" style="display: none;">
                                <span class="spinner"></span>
                                <?php _e('Processing...', 'ai-photo-recreator'); ?>
                            </span>
                        </button>
                    </div>
                    
                    <!-- Progress Section -->
                    <div id="progress-section" class="progress-section" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-text"><?php _e('Processing your photo...', 'ai-photo-recreator'); ?></div>
                    </div>
                    
                    <!-- Results Section -->
                    <div id="results-section" class="results-section" style="display: none;">
                        <h4><?php _e('Results', 'ai-photo-recreator'); ?></h4>
                        <div class="results-grid">
                            <div class="result-item">
                                <h5><?php _e('Original', 'ai-photo-recreator'); ?></h5>
                                <div class="result-image">
                                    <img id="original-result" src="" alt="Original">
                                </div>
                            </div>
                            <div class="result-item">
                                <h5><?php _e('AI Processed', 'ai-photo-recreator'); ?></h5>
                                <div class="result-image">
                                    <img id="processed-result" src="" alt="Processed">
                                </div>
                                <div class="result-actions">
                                    <a id="download-link" href="" class="download-button" download>
                                        <?php _e('Download', 'ai-photo-recreator'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error Section -->
                    <div id="error-section" class="error-section" style="display: none;">
                        <div class="error-message"></div>
                        <button type="button" id="retry-btn" class="retry-button">
                            <?php _e('Try Again', 'ai-photo-recreator'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX photo processing
     */
    public function handle_ajax_process() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'ai_photo_recreator_nonce')) {
            wp_send_json_error(__('Security check failed', 'ai-photo-recreator'));
        }
        
        // Check user capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to upload files', 'ai-photo-recreator'));
        }
        
        // Validate input
        if (empty($_FILES['photo']) || empty($_POST['instructions'])) {
            wp_send_json_error(__('Missing required fields', 'ai-photo-recreator'));
        }
        
        // Process the photo
        $processor = new AI_Photo_Processor();
        $result = $processor->process_photo($_FILES['photo'], $_POST['instructions']);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle file downloads
     */
    public function handle_download() {
        if (!isset($_GET['file']) || !isset($_GET['nonce'])) {
            wp_die(__('Invalid download request', 'ai-photo-recreator'));
        }
        
        $filename = sanitize_text_field($_GET['file']);
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'processed';
        
        $file_handler = new AI_Photo_File_Handler();
        $file_handler->handle_download($filename, $type);
    }
    
    /**
     * Get processing history for current user
     * 
     * @param int $limit Number of records to retrieve
     * @return array Processing history
     */
    public function get_user_history($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_photo_history';
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return array();
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Render processing history shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_history_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'title' => __('Processing History', 'ai-photo-recreator')
        ), $atts, 'ai_photo_history');
        
        if (!is_user_logged_in()) {
            return '<div class="ai-photo-recreator-error">' . 
                   __('You need to be logged in to view your history.', 'ai-photo-recreator') . 
                   '</div>';
        }
        
        $history = $this->get_user_history($atts['limit']);
        
        if (empty($history)) {
            return '<div class="ai-photo-recreator-info">' . 
                   __('No processing history found.', 'ai-photo-recreator') . 
                   '</div>';
        }
        
        ob_start();
        ?>
        
        <div class="ai-photo-recreator-history">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <div class="history-list">
                <?php foreach ($history as $record): ?>
                    <div class="history-item status-<?php echo esc_attr($record->status); ?>">
                        <div class="history-info">
                            <div class="history-date">
                                <?php echo esc_html(mysql2date('F j, Y g:i a', $record->created_at)); ?>
                            </div>
                            <div class="history-status">
                                <span class="status-badge"><?php echo esc_html(ucfirst($record->status)); ?></span>
                            </div>
                        </div>
                        <div class="history-instructions">
                            <?php echo esc_html(wp_trim_words($record->instructions, 20)); ?>
                        </div>
                        <?php if ($record->status === 'completed' && !empty($record->processed_file)): ?>
                            <div class="history-actions">
                                <?php
                                $file_handler = new AI_Photo_File_Handler();
                                $filename = basename($record->processed_file);
                                $download_url = $file_handler->get_download_url($filename);
                                ?>
                                <a href="<?php echo esc_url($download_url); ?>" class="download-link">
                                    <?php _e('Download', 'ai-photo-recreator'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
}

// Register history shortcode separately
add_shortcode('ai_photo_history', function($atts) {
    $shortcode = new AI_Photo_Recreator_Shortcode();
    return $shortcode->render_history_shortcode($atts);
});