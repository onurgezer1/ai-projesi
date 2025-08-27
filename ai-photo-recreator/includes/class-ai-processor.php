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
            
            // If no API key is provided, fall back to enhanced processing
            if (empty($api_key)) {
                error_log('AI Photo Recreator: No API key configured, using enhanced fallback processing');
                if ($this->create_enhanced_fallback($file_path, $processed_path, $instructions)) {
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
                        'message' => __('Configure OpenAI API key in settings for real AI transformations. Using enhanced fallback processing.', 'ai-photo-recreator')
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
                // AI processing failed, fall back to enhanced processing
                error_log('AI Photo Recreator: OpenAI processing failed, falling back to enhanced processing: ' . $ai_result['message']);
                
                if ($this->create_enhanced_fallback($file_path, $processed_path, $instructions)) {
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
                        'message' => sprintf(__('AI processing failed (%s). Using enhanced fallback processing.', 'ai-photo-recreator'), $ai_result['message'])
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
     * Process image with OpenAI - Vision + DALL-E pipeline
     * 
     * @param string $file_path Original file path
     * @param string $instructions User instructions
     * @param string $api_key OpenAI API key
     * @return array Processing result
     */
    private function process_with_openai($file_path, $instructions, $api_key) {
        try {
            // Step 1: Read and prepare the image
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
            
            // Step 2: Analyze the image using GPT-4V to understand its content
            $vision_result = $this->analyze_image_with_vision($base64_image, $api_key, $mime_type);
            
            if (!$vision_result['success']) {
                error_log('AI Photo Recreator: Vision analysis failed: ' . $vision_result['message']);
                return array(
                    'success' => false,
                    'message' => sprintf(__('Image analysis failed: %s', 'ai-photo-recreator'), $vision_result['message'])
                );
            }
            
            // Step 3: Create comprehensive prompt for DALL-E
            $comprehensive_prompt = $this->create_transformation_prompt($vision_result['description'], $instructions);
            
            error_log('AI Photo Recreator: Generated DALL-E prompt: ' . $comprehensive_prompt);
            
            // Step 4: Generate new image with DALL-E
            $dalle_result = $this->generate_image_with_dalle($comprehensive_prompt, $api_key);
            
            if ($dalle_result['success']) {
                return array(
                    'success' => true,
                    'image_data' => $dalle_result['image_data']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $dalle_result['message']
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
     * Analyze image using GPT-4V to understand its content
     * 
     * @param string $base64_image Base64 encoded image
     * @param string $api_key OpenAI API key
     * @param string $mime_type Image MIME type
     * @return array Analysis result
     */
    private function analyze_image_with_vision($base64_image, $api_key, $mime_type) {
        try {
            $api_url = 'https://api.openai.com/v1/chat/completions';
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            );
            
            $body = array(
                'model' => 'gpt-4-vision-preview',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => 'Please analyze this image and provide a detailed description including: the main subject(s), their clothing, pose, facial expressions, setting/background, lighting, colors, mood, and any other important visual elements. Be very specific and detailed as this will be used to recreate a similar image with modifications.'
                            ),
                            array(
                                'type' => 'image_url',
                                'image_url' => array(
                                    'url' => 'data:' . $mime_type . ';base64,' . $base64_image
                                )
                            )
                        )
                    )
                ),
                'max_tokens' => 500
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
                    'message' => sprintf(__('Vision API error (%d): %s', 'ai-photo-recreator'), $response_code, $error_message)
                );
            }
            
            $data = json_decode($response_body, true);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Vision API', 'ai-photo-recreator')
                );
            }
            
            $description = $data['choices'][0]['message']['content'];
            
            return array(
                'success' => true,
                'description' => $description
            );
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator Vision API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to analyze image with Vision API', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Create comprehensive prompt for DALL-E based on image analysis and user instructions
     * 
     * @param string $image_description Description from Vision API
     * @param string $user_instructions User's transformation instructions
     * @return string Comprehensive prompt for DALL-E
     */
    private function create_transformation_prompt($image_description, $user_instructions) {
        $prompt = sprintf(
            'Create a photorealistic image based on this description: %s. ' .
            'Now apply these modifications while keeping the same composition, subjects, and overall scene: %s. ' .
            'Maintain the same photographic style, lighting quality, and realism. ' .
            'Make sure the requested changes are clearly visible and naturally integrated into the scene.',
            $image_description,
            sanitize_text_field($user_instructions)
        );
        
        return $prompt;
    }
    
    /**
     * Generate new image using DALL-E
     * 
     * @param string $prompt Comprehensive prompt for image generation
     * @param string $api_key OpenAI API key
     * @return array Generation result
     */
    private function generate_image_with_dalle($prompt, $api_key) {
        try {
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
                'response_format' => 'b64_json',
                'quality' => 'standard'
            );
            
            $args = array(
                'timeout' => 120, // DALL-E can take longer
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
                    'message' => sprintf(__('DALL-E API error (%d): %s', 'ai-photo-recreator'), $response_code, $error_message)
                );
            }
            
            $data = json_decode($response_body, true);
            
            if (!isset($data['data'][0]['b64_json'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from DALL-E API', 'ai-photo-recreator')
                );
            }
            
            $image_data = base64_decode($data['data'][0]['b64_json']);
            
            return array(
                'success' => true,
                'image_data' => $image_data
            );
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator DALL-E API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to generate image with DALL-E', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Create enhanced processed image (fallback when no AI API is available)
     * Applies actual image processing based on user instructions
     * 
     * @param string $original_path Original image path
     * @param string $processed_path Processed image path
     * @param string $instructions User instructions
     * @return bool Success status
     */
    private function create_enhanced_fallback($original_path, $processed_path, $instructions) {
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
            
            // Apply processing based on user instructions
            $this->apply_instruction_based_processing($source, $instructions, $width, $height);
            
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
            error_log('AI Photo Recreator: Exception in create_enhanced_fallback: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply processing based on user instructions (enhanced fallback)
     * 
     * @param resource $image_resource GD image resource
     * @param string $instructions User instructions
     * @param int $width Image width
     * @param int $height Image height
     */
    private function apply_instruction_based_processing($image_resource, $instructions, $width, $height) {
        $instructions = strtolower(trim($instructions));
        
        // Snow effect
        if (strpos($instructions, 'snow') !== false) {
            $this->add_snow_effect($image_resource, $width, $height);
        }
        
        // Rain effect
        elseif (strpos($instructions, 'rain') !== false) {
            $this->add_rain_effect($image_resource, $width, $height);
        }
        
        // Vintage/sepia effect
        elseif (strpos($instructions, 'vintage') !== false || strpos($instructions, 'sepia') !== false) {
            $this->add_vintage_effect($image_resource);
        }
        
        // Black and white
        elseif (strpos($instructions, 'black and white') !== false || strpos($instructions, 'grayscale') !== false) {
            imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
        }
        
        // Blur effect
        elseif (strpos($instructions, 'blur') !== false) {
            imagefilter($image_resource, IMG_FILTER_GAUSSIAN_BLUR);
        }
        
        // Bright/brighten
        elseif (strpos($instructions, 'bright') !== false || strpos($instructions, 'lighten') !== false) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 30);
        }
        
        // Dark/darken
        elseif (strpos($instructions, 'dark') !== false) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -30);
        }
        
        // Warm colors
        elseif (strpos($instructions, 'warm') !== false) {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, 20, 0, -20);
        }
        
        // Cool colors
        elseif (strpos($instructions, 'cool') !== false) {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, -20, 0, 20);
        }
        
        // Default: Apply subtle enhancement
        else {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, -2);
        }
    }
    
    /**
     * Add snow effect to image
     */
    private function add_snow_effect($image_resource, $width, $height) {
        // Create semi-transparent white overlay for snow atmosphere
        $snow_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($snow_overlay, 0, 0, 0, 127);
        imagefill($snow_overlay, 0, 0, $transparent);
        imagesavealpha($snow_overlay, true);
        
        $white = imagecolorallocatealpha($snow_overlay, 255, 255, 255, 100);
        $light_white = imagecolorallocatealpha($snow_overlay, 255, 255, 255, 120);
        
        // Add random snowflakes
        for ($i = 0; $i < 200; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 4);
            $color = (rand(0, 1)) ? $white : $light_white;
            
            imagefilledellipse($snow_overlay, $x, $y, $size, $size, $color);
        }
        
        // Apply cool color filter to simulate winter atmosphere
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -10, -5, 10);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
        
        // Merge snow overlay
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $snow_overlay, 0, 0, 0, 0, $width, $height, 70);
        
        imagedestroy($snow_overlay);
    }
    
    /**
     * Add rain effect to image
     */
    private function add_rain_effect($image_resource, $width, $height) {
        // Create rain overlay
        $rain_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rain_overlay, 0, 0, 0, 127);
        imagefill($rain_overlay, 0, 0, $transparent);
        imagesavealpha($rain_overlay, true);
        
        $rain_color = imagecolorallocatealpha($rain_overlay, 200, 200, 255, 110);
        
        // Add rain lines
        for ($i = 0; $i < 150; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(10, 25);
            
            imageline($rain_overlay, $x, $y, $x - 2, $y + $length, $rain_color);
        }
        
        // Apply cool, darker atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -15);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -5, -5, 5);
        
        // Merge rain overlay
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rain_overlay, 0, 0, 0, 0, $width, $height, 60);
        
        imagedestroy($rain_overlay);
    }
    
    /**
     * Add vintage/sepia effect
     */
    private function add_vintage_effect($image_resource) {
        // Apply sepia tone
        imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 90, 60, 40);
        
        // Reduce contrast slightly for vintage look
        imagefilter($image_resource, IMG_FILTER_CONTRAST, -10);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -5);
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