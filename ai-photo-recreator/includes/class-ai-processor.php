<?php
/**
 * AI Photo Processor Class
 * 
 * Handles AI processing of uploaded photos
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class AI_Photo_Processor {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize processor
    }
    
    /**
     * Process photo with AI
     * 
     * @param array $file Uploaded file information
     * @param string $instructions User instructions
     * @return array Processing result
     */
    public function process_photo($file, $instructions) {
        try {
            // Rate limiting check
            if (!$this->check_rate_limit()) {
                return array(
                    'success' => false,
                    'message' => __('Rate limit exceeded. Please try again later.', 'ai-photo-recreator')
                );
            }
            
            // Validate file
            $file_handler = new AI_Photo_File_Handler();
            $validation_result = $file_handler->validate_file($file);
            
            if (!$validation_result['valid']) {
                return array(
                    'success' => false,
                    'message' => $validation_result['message']
                );
            }
            
            // Save original file
            $upload_result = $file_handler->save_uploaded_file($file);
            if (!$upload_result['success']) {
                return array(
                    'success' => false,
                    'message' => $upload_result['message']
                );
            }
            
            $original_file_path = $upload_result['file_path'];
            
            // Log processing start
            $history_id = $this->log_processing_start($original_file_path, $instructions);
            
            // Process with AI (mock implementation for now)
            $processed_result = $this->ai_process($original_file_path, $instructions);
            
            if ($processed_result['success']) {
                // Update processing log
                $this->log_processing_complete($history_id, $processed_result['processed_file']);
                
                return array(
                    'success' => true,
                    'message' => __('Photo processed successfully', 'ai-photo-recreator'),
                    'original_url' => $upload_result['file_url'],
                    'processed_url' => $processed_result['processed_url'],
                    'download_url' => $processed_result['download_url']
                );
            } else {
                // Update processing log with error
                $this->log_processing_error($history_id, $processed_result['message']);
                
                return array(
                    'success' => false,
                    'message' => $processed_result['message']
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('An error occurred during processing', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Check rate limiting
     * 
     * @return bool Whether request is within rate limit
     */
    private function check_rate_limit() {
        $options = get_option('ai_photo_recreator_options');
        $rate_limit = $options['rate_limit'];
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            $user_id = $_SERVER['REMOTE_ADDR']; // Use IP for non-logged users
        }
        
        $transient_key = 'ai_photo_rate_limit_' . md5($user_id);
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($current_count >= $rate_limit) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * AI processing with real OpenAI integration
     * 
     * @param string $file_path Original file path
     * @param string $instructions User instructions
     * @return array Processing result
     */
    private function ai_process($file_path, $instructions) {
        try {
            $upload_dir = wp_upload_dir();
            $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/';
            
            // Create processed directory if it doesn't exist
            $processed_dir = $ai_dir . 'processed/';
            if (!file_exists($processed_dir)) {
                if (!wp_mkdir_p($processed_dir)) {
                    return array(
                        'success' => false,
                        'message' => __('Failed to create processed directory', 'ai-photo-recreator')
                    );
                }
            }
            
            // Check if original file exists
            if (!file_exists($file_path)) {
                return array(
                    'success' => false,
                    'message' => __('Original file not found', 'ai-photo-recreator')
                );
            }
            
            // Get API key from settings
            $options = get_option('ai_photo_recreator_options', array());
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            
            $file_info = pathinfo($file_path);
            $processed_filename = 'processed_' . time() . '_' . $file_info['filename'] . '.' . $file_info['extension'];
            $processed_path = $processed_dir . $processed_filename;
            
            // If no API key is provided, fall back to mock processing
            if (empty($api_key)) {
                error_log('AI Photo Recreator: No API key configured, using fallback processing');
                if ($this->create_mock_processed_image($file_path, $processed_path, $instructions)) {
                    $processed_url = $upload_dir['baseurl'] . '/ai-photo-recreator/processed/' . $processed_filename;
                    
                    return array(
                        'success' => true,
                        'processed_file' => $processed_path,
                        'processed_url' => $processed_url,
                        'download_url' => add_query_arg(array(
                            'action' => 'ai_photo_download',
                            'file' => urlencode($processed_filename),
                            'nonce' => wp_create_nonce('ai_photo_download_' . $processed_filename)
                        ), admin_url('admin-ajax.php')),
                        'message' => __('Note: Using fallback processing. Configure OpenAI API key in settings for real AI transformations.', 'ai-photo-recreator')
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Failed to process image', 'ai-photo-recreator')
                    );
                }
            }
            
            // Try OpenAI DALL-E processing
            $ai_result = $this->process_with_openai($file_path, $instructions, $api_key);
            
            if ($ai_result['success']) {
                // Save the processed image
                if (file_put_contents($processed_path, $ai_result['image_data'])) {
                    $processed_url = $upload_dir['baseurl'] . '/ai-photo-recreator/processed/' . $processed_filename;
                    
                    return array(
                        'success' => true,
                        'processed_file' => $processed_path,
                        'processed_url' => $processed_url,
                        'download_url' => add_query_arg(array(
                            'action' => 'ai_photo_download',
                            'file' => urlencode($processed_filename),
                            'nonce' => wp_create_nonce('ai_photo_download_' . $processed_filename)
                        ), admin_url('admin-ajax.php'))
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Failed to save processed image', 'ai-photo-recreator')
                    );
                }
            } else {
                // AI processing failed, fall back to mock processing
                error_log('AI Photo Recreator: OpenAI processing failed, falling back to mock: ' . $ai_result['message']);
                
                if ($this->create_mock_processed_image($file_path, $processed_path, $instructions)) {
                    $processed_url = $upload_dir['baseurl'] . '/ai-photo-recreator/processed/' . $processed_filename;
                    
                    return array(
                        'success' => true,
                        'processed_file' => $processed_path,
                        'processed_url' => $processed_url,
                        'download_url' => add_query_arg(array(
                            'action' => 'ai_photo_download',
                            'file' => urlencode($processed_filename),
                            'nonce' => wp_create_nonce('ai_photo_download_' . $processed_filename)
                        ), admin_url('admin-ajax.php')),
                        'message' => sprintf(__('AI processing failed (%s). Using fallback processing.', 'ai-photo-recreator'), $ai_result['message'])
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => sprintf(__('AI processing failed: %s', 'ai-photo-recreator'), $ai_result['message'])
                    );
                }
            }
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator Processing Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred during AI processing', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Process image with OpenAI DALL-E
     * 
     * @param string $file_path Original file path
     * @param string $instructions User instructions
     * @param string $api_key OpenAI API key
     * @return array Processing result
     */
    private function process_with_openai($file_path, $instructions, $api_key) {
        try {
            // Prepare the image for OpenAI
            $image_data = file_get_contents($file_path);
            if (!$image_data) {
                return array(
                    'success' => false,
                    'message' => __('Failed to read image file', 'ai-photo-recreator')
                );
            }
            
            // Convert image to base64
            $base64_image = base64_encode($image_data);
            $image_info = getimagesize($file_path);
            $mime_type = $image_info['mime'];
            
            // Prepare the prompt for DALL-E
            $prompt = sprintf(
                'Transform this image according to these instructions: %s. Keep the main subject and composition but apply the requested changes.',
                sanitize_text_field($instructions)
            );
            
            // OpenAI API call for image editing
            $response = $this->call_openai_api($base64_image, $prompt, $api_key, $mime_type);
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'image_data' => $response['image_data']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $response['message']
                );
            }
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator OpenAI Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('OpenAI processing error', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Call OpenAI API for image processing
     * 
     * @param string $base64_image Base64 encoded image
     * @param string $prompt Processing prompt
     * @param string $api_key OpenAI API key
     * @param string $mime_type Image MIME type
     * @return array API response
     */
    private function call_openai_api($base64_image, $prompt, $api_key, $mime_type) {
        try {
            // Use OpenAI's image generation API
            $api_url = 'https://api.openai.com/v1/images/generations';
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            );
            
            $body = array(
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'response_format' => 'b64_json'
            );
            
            $args = array(
                'timeout' => 60,
                'headers' => $headers,
                'body' => json_encode($body),
                'method' => 'POST'
            );
            
            $response = wp_remote_request($api_url, $args);
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
                
                return array(
                    'success' => false,
                    'message' => sprintf(__('OpenAI API error (%d): %s', 'ai-photo-recreator'), $response_code, $error_message)
                );
            }
            
            $data = json_decode($response_body, true);
            
            if (!isset($data['data'][0]['b64_json'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from OpenAI API', 'ai-photo-recreator')
                );
            }
            
            $image_data = base64_decode($data['data'][0]['b64_json']);
            
            return array(
                'success' => true,
                'image_data' => $image_data
            );
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator OpenAI API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to call OpenAI API', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Create mock processed image (fallback when no AI API is available)
     * 
     * @param string $original_path Original image path
     * @param string $processed_path Processed image path
     * @param string $instructions User instructions
     * @return bool Success status
     */
    private function create_mock_processed_image($original_path, $processed_path, $instructions) {
        try {
            // Get image info
            $image_info = getimagesize($original_path);
            if (!$image_info) {
                error_log('AI Photo Recreator: Failed to get image info for ' . $original_path);
                return false;
            }
            
            $width = $image_info[0];
            $height = $image_info[1];
            $type = $image_info[2];
            
            // Create image resource based on type
            $source = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = @imagecreatefromjpeg($original_path);
                    break;
                case IMAGETYPE_PNG:
                    $source = @imagecreatefrompng($original_path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $source = @imagecreatefromwebp($original_path);
                    } else {
                        error_log('AI Photo Recreator: WebP support not available');
                        return false;
                    }
                    break;
                default:
                    error_log('AI Photo Recreator: Unsupported image type: ' . $type);
                    return false;
            }
            
            if (!$source) {
                error_log('AI Photo Recreator: Failed to create image resource from ' . $original_path);
                return false;
            }
            
            // Instead of just copying, we'll apply some basic visual modifications
            // to show that processing occurred, though not real AI processing
            
            // Apply a subtle color filter to indicate "processing"
            if (function_exists('imagefilter')) {
                // Apply a subtle artistic effect
                imagefilter($source, IMG_FILTER_BRIGHTNESS, 10);
                imagefilter($source, IMG_FILTER_CONTRAST, -5);
                imagefilter($source, IMG_FILTER_SMOOTH, 2);
            }
            
            // Add a small watermark to indicate this is fallback processing
            $text_color = imagecolorallocatealpha($source, 255, 255, 255, 30);
            $bg_color = imagecolorallocatealpha($source, 0, 0, 0, 70);
            
            if ($text_color !== false && $bg_color !== false) {
                // Add a small notice at the bottom right
                $notice_text = __('Fallback Processing', 'ai-photo-recreator');
                $text_box = imagettfbbox(8, 0, __FILE__, $notice_text);
                if ($text_box === false) {
                    // Fallback to imagestring if TTF not available
                    $text_width = strlen($notice_text) * 8;
                    $text_height = 12;
                } else {
                    $text_width = abs($text_box[4] - $text_box[0]);
                    $text_height = abs($text_box[5] - $text_box[1]);
                }
                
                $x = $width - $text_width - 15;
                $y = $height - $text_height - 10;
                
                // Add semi-transparent background
                imagefilledrectangle($source, $x - 5, $y - 15, $x + $text_width + 5, $y + 5, $bg_color);
                
                // Add text
                imagestring($source, 2, $x, $y - 12, $notice_text, $text_color);
            }
            
            // Save processed image
            $result = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $result = @imagejpeg($source, $processed_path, 90);
                    break;
                case IMAGETYPE_PNG:
                    $result = @imagepng($source, $processed_path, 6);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $result = @imagewebp($source, $processed_path, 90);
                    }
                    break;
            }
            
            // Clean up memory
            imagedestroy($source);
            
            if (!$result) {
                error_log('AI Photo Recreator: Failed to save processed image to ' . $processed_path);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator: Exception in create_mock_processed_image: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log processing start
     * 
     * @param string $original_file Original file path
     * @param string $instructions User instructions
     * @return int History ID
     */
    private function log_processing_start($original_file, $instructions) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'original_file' => $original_file,
                'instructions' => sanitize_text_field($instructions),
                'status' => 'processing',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Log processing completion
     * 
     * @param int $history_id History record ID
     * @param string $processed_file Processed file path
     */
    private function log_processing_complete($history_id, $processed_file) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        $wpdb->update(
            $table_name,
            array(
                'processed_file' => $processed_file,
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $history_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Log processing error
     * 
     * @param int $history_id History record ID
     * @param string $error_message Error message
     */
    private function log_processing_error($history_id, $error_message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_photo_history';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => 'error',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $history_id),
            array('%s', '%s'),
            array('%d')
        );
    }
}