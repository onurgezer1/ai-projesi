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
            $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
            
            error_log('AI Photo Recreator: API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
            
            $file_info = pathinfo($file_path);
            $processed_filename = 'processed_' . time() . '_' . $file_info['filename'] . '.' . $file_info['extension'];
            $processed_path = $processed_dir . $processed_filename;
            
            // If no API key is provided, use sophisticated local processing
            if (empty($api_key)) {
                error_log('AI Photo Recreator: No API key configured, using sophisticated local processing');
                if ($this->create_advanced_transformation($file_path, $processed_path, $instructions)) {
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
                        'message' => __('Photo transformed successfully! Configure OpenAI API key in settings for cloud-based AI processing.', 'ai-photo-recreator')
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Failed to process image', 'ai-photo-recreator')
                    );
                }
            }
            
            error_log('AI Photo Recreator: Starting OpenAI processing with instructions: ' . $instructions);
            
            // Try OpenAI DALL-E processing
            $ai_result = $this->process_with_openai($file_path, $instructions, $api_key);
            
            error_log('AI Photo Recreator: OpenAI processing result: ' . ($ai_result['success'] ? 'SUCCESS' : 'FAILED - ' . $ai_result['message']));
            
            if ($ai_result['success']) {
                // Save the processed image
                if (file_put_contents($processed_path, $ai_result['image_data'])) {
                    $processed_url = $upload_dir['baseurl'] . '/ai-photo-recreator/processed/' . $processed_filename;
                    
                    error_log('AI Photo Recreator: Successfully saved AI-processed image to ' . $processed_path);
                    
                    return array(
                        'success' => true,
                        'processed_file' => $processed_path,
                        'processed_url' => $processed_url,
                        'download_url' => add_query_arg(array(
                            'action' => 'ai_photo_download',
                            'file' => urlencode($processed_filename),
                            'nonce' => wp_create_nonce('ai_photo_download_' . $processed_filename)
                        ), admin_url('admin-ajax.php')),
                        'message' => __('Photo successfully transformed using OpenAI DALL-E!', 'ai-photo-recreator')
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Failed to save processed image', 'ai-photo-recreator')
                    );
                }
            } else {
                // AI processing failed - check if it's a quota issue and provide clear guidance
                $is_quota_issue = (strpos($ai_result['message'], '429') !== false || 
                                 strpos($ai_result['message'], 'quota') !== false ||
                                 strpos($ai_result['message'], 'insufficient_quota') !== false);
                
                if ($is_quota_issue) {
                    error_log('AI Photo Recreator: OpenAI quota exceeded, using sophisticated local processing: ' . $ai_result['message']);
                    
                    if ($this->create_advanced_transformation($file_path, $processed_path, $instructions)) {
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
                            'message' => __('⚠️ OpenAI API quota exceeded. Check your billing at platform.openai.com. Applied local advanced processing instead.', 'ai-photo-recreator')
                        );
                    } else {
                        return array(
                            'success' => false,
                            'message' => __('OpenAI quota exceeded and local processing failed. Please check your OpenAI billing and try again.', 'ai-photo-recreator')
                        );
                    }
                } else {
                    // Other API errors - still try local processing
                    error_log('AI Photo Recreator: OpenAI processing failed, using sophisticated local processing: ' . $ai_result['message']);
                    
                    if ($this->create_advanced_transformation($file_path, $processed_path, $instructions)) {
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
                            'message' => sprintf(__('OpenAI processing failed (%s). Applied local advanced processing. Check debug-openai-test.php for diagnostics.', 'ai-photo-recreator'), $ai_result['message'])
                        );
                    } else {
                        return array(
                            'success' => false,
                            'message' => sprintf(__('OpenAI processing failed: %s. Local processing also failed. Check debug-openai-test.php for diagnostics.', 'ai-photo-recreator'), $ai_result['message'])
                        );
                    }
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
                'model' => 'gpt-4o',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => 'Analyze this image in great detail for AI image recreation. Provide a comprehensive description structured as follows:

SUBJECTS: Describe all people, their physical characteristics, clothing, poses, expressions, and positions in detail
SETTING: Detailed description of the location, environment, and background elements including architecture, landscape, objects
LIGHTING: Type of lighting, direction, intensity, shadows, and overall mood - be very specific about light quality
COLORS: Dominant colors, color palette, and color temperature throughout the scene
COMPOSITION: Camera angle, framing, depth of field, and photographic style
ATMOSPHERE: Weather conditions, season, time of day, and environmental factors
STYLE: Photography type (portrait, landscape, candid, etc.) and artistic qualities

Be extremely specific and detailed as this will be used to recreate the exact scene with comprehensive modifications.'
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
                'max_tokens' => 1200
            );
            
            $args = array(
                'timeout' => 60,
                'headers' => $headers,
                'body' => json_encode($body),
                'method' => 'POST'
            );
            
            $response = wp_remote_request($api_url, $args);
            
            if (is_wp_error($response)) {
                error_log('AI Photo Recreator Vision API: WP Error - ' . $response->get_error_message());
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('AI Photo Recreator Vision API: Response Code - ' . $response_code);
            
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
                
                error_log('AI Photo Recreator Vision API: Error - ' . $error_message);
                error_log('AI Photo Recreator Vision API: Full response - ' . $response_body);
                
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
     * Parse and understand user instructions for comprehensive transformations
     * Enhanced with Turkish language support and background replacement
     * 
     * @param string $instructions User instructions
     * @return array Parsed transformation requirements
     */
    private function parse_transformation_instructions($instructions) {
        $instructions = strtolower(trim($instructions));
        $transformations = array();
        
        // Background removal detection (Turkish and English)
        if (strpos($instructions, 'arka plan') !== false || strpos($instructions, 'background') !== false ||
            strpos($instructions, 'arka planı kaldır') !== false || strpos($instructions, 'remove background') !== false ||
            strpos($instructions, 'change background') !== false || strpos($instructions, 'arkaplan') !== false) {
            $transformations['background_replace'] = true;
        }
        
        // Weather and environmental transformations (with enhanced Turkish support)
        if (strpos($instructions, 'snow') !== false || strpos($instructions, 'kar') !== false || 
            strpos($instructions, 'kış') !== false || strpos($instructions, 'kar yağdır') !== false ||
            strpos($instructions, 'kar yağ') !== false || strpos($instructions, 'karlı') !== false ||
            strpos($instructions, 'winter') !== false || strpos($instructions, 'snowy') !== false) {
            $transformations['environment'] = 'winter';
            $transformations['weather'] = 'heavy snowfall';
            $transformations['background'] = 'snow-covered winter landscape with falling snow and winter atmosphere';
            $transformations['atmosphere'] = 'cold, crisp winter atmosphere with overcast snowy sky and visible snowfall';
            $transformations['lighting'] = 'soft, diffused winter lighting typical of snowy weather';
            $transformations['effects'] = array('heavy snow falling from the sky', 'snow accumulation on all surfaces', 'frost and ice effects', 'winter atmosphere', 'cold color temperature');
            $transformations['comprehensive'] = true;
            $transformations['background_replace'] = true;
        }
        
        elseif (strpos($instructions, 'rain') !== false || strpos($instructions, 'yağmur') !== false ||
                strpos($instructions, 'rainy') !== false || strpos($instructions, 'yağmurlu') !== false) {
            $transformations['environment'] = 'rainy';
            $transformations['weather'] = 'rainy';
            $transformations['background'] = 'stormy, rainy environment with dark clouds';
            $transformations['atmosphere'] = 'moody, overcast atmosphere with rain and storm clouds';
            $transformations['lighting'] = 'dramatic, darker lighting with storm clouds';
            $transformations['effects'] = array('rain drops falling', 'wet surfaces', 'puddles', 'storm clouds');
            $transformations['background_replace'] = true;
        }
        
        elseif (strpos($instructions, 'sunset') !== false || strpos($instructions, 'golden hour') !== false ||
                strpos($instructions, 'gün batımı') !== false || strpos($instructions, 'günbatımı') !== false ||
                strpos($instructions, 'altın saat') !== false) {
            $transformations['environment'] = 'sunset';
            $transformations['time'] = 'golden hour';
            $transformations['background'] = 'beautiful sunset sky with warm golden colors and dramatic clouds';
            $transformations['atmosphere'] = 'warm, romantic golden hour atmosphere';
            $transformations['lighting'] = 'warm golden sunset lighting';
            $transformations['effects'] = array('golden sun rays', 'warm color cast', 'dramatic sunset sky', 'golden light');
        }
        
        elseif (strpos($instructions, 'night') !== false || strpos($instructions, 'dark') !== false ||
                strpos($instructions, 'gece') !== false || strpos($instructions, 'karanlık') !== false) {
            $transformations['environment'] = 'night';
            $transformations['time'] = 'nighttime';
            $transformations['background'] = 'dramatic nighttime scene with stars or city lights';
            $transformations['atmosphere'] = 'mysterious nighttime atmosphere';
            $transformations['lighting'] = 'dramatic night lighting with artificial light sources or moonlight';
            $transformations['effects'] = array('night sky', 'street lights or moon', 'night shadows', 'stars');
            $transformations['background_replace'] = true;
        }
        
        // Season transformations (with Turkish support)
        elseif (strpos($instructions, 'autumn') !== false || strpos($instructions, 'fall') !== false ||
                strpos($instructions, 'sonbahar') !== false || strpos($instructions, 'güz') !== false) {
            $transformations['environment'] = 'autumn';
            $transformations['season'] = 'autumn';
            $transformations['background'] = 'autumn landscape with colorful fall foliage and trees';
            $transformations['atmosphere'] = 'crisp autumn atmosphere with falling leaves';
            $transformations['effects'] = array('colorful fall leaves', 'autumn foliage', 'warm autumn tones', 'falling leaves');
            $transformations['background_replace'] = true;
        }
        
        elseif (strpos($instructions, 'spring') !== false || strpos($instructions, 'ilkbahar') !== false) {
            $transformations['environment'] = 'spring';
            $transformations['season'] = 'spring';
            $transformations['background'] = 'fresh spring environment with blooming flowers and green trees';
            $transformations['atmosphere'] = 'fresh, vibrant spring atmosphere';
            $transformations['effects'] = array('blooming flowers', 'fresh green leaves', 'spring colors', 'cherry blossoms');
            $transformations['background_replace'] = true;
        }
        
        elseif (strpos($instructions, 'summer') !== false || strpos($instructions, 'yaz') !== false) {
            $transformations['environment'] = 'summer';
            $transformations['season'] = 'summer';
            $transformations['background'] = 'bright summer scene with blue sky and sunshine';
            $transformations['atmosphere'] = 'warm, bright summer atmosphere';
            $transformations['lighting'] = 'bright summer sunlight';
            $transformations['effects'] = array('bright sunshine', 'summer colors', 'clear blue sky');
        }
        
        // Location transformations
        if (strpos($instructions, 'beach') !== false || strpos($instructions, 'ocean') !== false) {
            $transformations['location'] = 'beach';
            $transformations['background'] = 'beautiful beach scene with ocean waves';
        }
        
        elseif (strpos($instructions, 'forest') !== false || strpos($instructions, 'woods') !== false) {
            $transformations['location'] = 'forest';
            $transformations['background'] = 'dense forest environment with trees';
        }
        
        elseif (strpos($instructions, 'mountain') !== false) {
            $transformations['location'] = 'mountains';
            $transformations['background'] = 'majestic mountain landscape';
        }
        
        elseif (strpos($instructions, 'city') !== false || strpos($instructions, 'urban') !== false) {
            $transformations['location'] = 'urban';
            $transformations['background'] = 'urban cityscape with buildings';
        }
        
        // Style transformations
        if (strpos($instructions, 'vintage') !== false || strpos($instructions, 'retro') !== false) {
            $transformations['style'] = 'vintage';
            $transformations['effects'][] = 'vintage color grading and film aesthetic';
        }
        
        elseif (strpos($instructions, 'dramatic') !== false) {
            $transformations['style'] = 'dramatic';
            $transformations['lighting'] = 'dramatic, high-contrast lighting';
        }
        
        elseif (strpos($instructions, 'soft') !== false || strpos($instructions, 'dreamy') !== false) {
            $transformations['style'] = 'soft';
            $transformations['effects'][] = 'soft, dreamy lighting and atmosphere';
        }
        
        return $transformations;
    }

    /**
     * Create comprehensive prompt for DALL-E based on image analysis and user instructions
     * 
     * @param string $image_description Description from Vision API
     * @param string $user_instructions User's transformation instructions
     * @return string Comprehensive prompt for DALL-E
     */
    private function create_transformation_prompt($image_description, $user_instructions) {
        // Parse instructions to understand what transformations are needed
        $transformations = $this->parse_transformation_instructions($user_instructions);
        
        // Create a comprehensive prompt for dramatic transformation
        if (isset($transformations['comprehensive']) && $transformations['comprehensive']) {
            // For comprehensive transformations like snow/weather changes
            $prompt = "Create a photorealistic image that recreates the EXACT same subjects, poses, and composition from this description: " . $image_description;
            
            $prompt .= "\n\nNow apply these DRAMATIC and COMPREHENSIVE environmental transformations:";
            
            // Environment and background changes
            if (isset($transformations['background'])) {
                $prompt .= "\n- COMPLETELY CHANGE BACKGROUND: " . $transformations['background'];
            }
            
            // Weather transformation
            if (isset($transformations['weather'])) {
                $prompt .= "\n- WEATHER CONDITIONS: Add intense " . $transformations['weather'] . " throughout the entire scene";
            }
            
            // Atmospheric changes
            if (isset($transformations['atmosphere'])) {
                $prompt .= "\n- ATMOSPHERIC CHANGE: Transform to " . $transformations['atmosphere'];
            }
            
            // Lighting changes
            if (isset($transformations['lighting'])) {
                $prompt .= "\n- LIGHTING TRANSFORMATION: Change to " . $transformations['lighting'];
            }
            
            // Visual effects
            if (isset($transformations['effects']) && is_array($transformations['effects'])) {
                $prompt .= "\n- VISUAL EFFECTS: " . implode(', ', $transformations['effects']);
            }
            
            $prompt .= "\n\nCRITICAL REQUIREMENTS:";
            $prompt .= "\n- Keep the EXACT same people, their poses, expressions, and positioning";
            $prompt .= "\n- Maintain the same camera angle and composition";
            $prompt .= "\n- Make the environmental transformation DRAMATIC and COMPREHENSIVE";
            $prompt .= "\n- The transformation must be immediately obvious and striking";
            $prompt .= "\n- Apply the changes to the ENTIRE scene, not just overlays";
            $prompt .= "\n- Create a completely new environment while preserving the subjects";
            
        } else {
            // Start with the base image description for other transformations
            $prompt = "Create a photorealistic image with the following base elements from the original: " . $image_description;
            
            // Apply other transformations
            if (!empty($transformations)) {
                $prompt .= "\n\nNow apply these comprehensive transformations:";
                
                // Environment and background changes
                if (isset($transformations['background'])) {
                    $prompt .= "\n- BACKGROUND: Completely transform the background to: " . $transformations['background'];
                }
                
                // Atmospheric changes
                if (isset($transformations['atmosphere'])) {
                    $prompt .= "\n- ATMOSPHERE: Change the overall atmosphere to: " . $transformations['atmosphere'];
                }
                
                // Lighting changes
                if (isset($transformations['lighting'])) {
                    $prompt .= "\n- LIGHTING: Adjust lighting to: " . $transformations['lighting'];
                }
                
                // Environmental effects
                if (isset($transformations['weather'])) {
                    $prompt .= "\n- WEATHER: Add " . $transformations['weather'] . " weather conditions";
                }
                
                // Visual effects
                if (isset($transformations['effects']) && is_array($transformations['effects'])) {
                    $prompt .= "\n- EFFECTS: Include these visual elements: " . implode(', ', $transformations['effects']);
                }
                
                // Style modifications
                if (isset($transformations['style'])) {
                    $prompt .= "\n- STYLE: Apply " . $transformations['style'] . " photographic style";
                }
            } else {
                // If no specific transformations detected, use original instructions
                $prompt .= "\n\nApply these modifications while maintaining realism: " . sanitize_text_field($user_instructions);
            }
            
            $prompt .= "\n\nIMPORTANT: Keep the same subjects, poses, and basic composition from the original image while applying these environmental and atmospheric transformations. Ensure all changes look natural and photorealistic. The transformation should be comprehensive and dramatically visible.";
        }
        
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
                error_log('AI Photo Recreator DALL-E API: WP Error - ' . $response->get_error_message());
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('AI Photo Recreator DALL-E API: Response Code - ' . $response_code);
            
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
                
                error_log('AI Photo Recreator DALL-E API: Error - ' . $error_message);
                error_log('AI Photo Recreator DALL-E API: Full response - ' . $response_body);
                
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
     * Create advanced transformation image with sophisticated local processing
     * Capable of background replacement and comprehensive scene transformations
     * 
     * @param string $original_path Original image path
     * @param string $processed_path Processed image path
     * @param string $instructions User instructions
     * @return bool Success status
     */
    private function create_advanced_transformation($original_path, $processed_path, $instructions) {
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
            
            // Parse instructions to understand requirements
            $transformations = $this->parse_transformation_instructions($instructions);
            
            // Apply sophisticated transformations
            $this->apply_advanced_transformations($source, $transformations, $width, $height);
            
            // Save processed image
            $result = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $result = @imagejpeg($source, $processed_path, 95);
                    break;
                case IMAGETYPE_PNG:
                    $result = @imagepng($source, $processed_path, 6);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        $result = @imagewebp($source, $processed_path, 95);
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
            error_log('AI Photo Recreator: Exception in create_advanced_transformation: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply advanced transformations based on parsed instructions
     * Sophisticated image processing with background replacement capabilities
     * 
     * @param resource $image_resource GD image resource
     * @param array $transformations Parsed transformation requirements
     * @param int $width Image width
     * @param int $height Image height
     */
    private function apply_advanced_transformations($image_resource, $transformations, $width, $height) {
        // If background replacement is requested, apply comprehensive scene transformation
        if (isset($transformations['background_replace']) && $transformations['background_replace']) {
            $this->apply_background_replacement($image_resource, $transformations, $width, $height);
        }
        
        // Apply environment-specific transformations
        if (isset($transformations['environment'])) {
            switch ($transformations['environment']) {
                case 'winter':
                    $this->create_comprehensive_winter_scene($image_resource, $width, $height);
                    break;
                case 'rainy':
                    $this->create_comprehensive_rainy_scene($image_resource, $width, $height);
                    break;
                case 'sunset':
                    $this->create_comprehensive_sunset_scene($image_resource, $width, $height);
                    break;
                case 'night':
                    $this->create_comprehensive_night_scene($image_resource, $width, $height);
                    break;
                case 'autumn':
                    $this->create_comprehensive_autumn_scene($image_resource, $width, $height);
                    break;
                case 'spring':
                    $this->create_comprehensive_spring_scene($image_resource, $width, $height);
                    break;
                case 'summer':
                    $this->create_comprehensive_summer_scene($image_resource, $width, $height);
                    break;
            }
        }
        
        // Apply legacy processing if no specific environment is detected
        else {
            $instructions = '';
            if (isset($transformations['effects'])) {
                $instructions = implode(' ', $transformations['effects']);
            }
            
            // Legacy keyword-based processing for backward compatibility
            if (strpos($instructions, 'snow') !== false) {
                $this->create_comprehensive_winter_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'rain') !== false) {
                $this->create_comprehensive_rainy_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'sunset') !== false || strpos($instructions, 'golden hour') !== false) {
                $this->create_comprehensive_sunset_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'night') !== false) {
                $this->create_comprehensive_night_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'autumn') !== false || strpos($instructions, 'fall') !== false) {
                $this->create_comprehensive_autumn_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'spring') !== false) {
                $this->create_comprehensive_spring_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'summer') !== false) {
                $this->create_comprehensive_summer_scene($image_resource, $width, $height);
            }
            elseif (strpos($instructions, 'vintage') !== false || strpos($instructions, 'sepia') !== false) {
                $this->add_vintage_effect($image_resource);
            }
            elseif (strpos($instructions, 'black and white') !== false || strpos($instructions, 'grayscale') !== false) {
                imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
            }
            else {
                // Default: Apply subtle enhancement
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
            }
        }
        
        // Apply style transformations
        if (isset($transformations['style'])) {
            switch ($transformations['style']) {
                case 'vintage':
                    $this->add_vintage_effect($image_resource);
                    break;
                case 'dramatic':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 20);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -5);
                    break;
                case 'soft':
                    imagefilter($image_resource, IMG_FILTER_SMOOTH, 3);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 8);
                    break;
            }
        }
    }
    
    /**
     * Apply sophisticated background replacement
     * Creates gradient overlays and masking effects for natural background changes
     */
    private function apply_background_replacement($image_resource, $transformations, $width, $height) {
        // Create background overlay based on transformation type
        if (isset($transformations['environment'])) {
            switch ($transformations['environment']) {
                case 'winter':
                    $this->create_winter_background_overlay($image_resource, $width, $height);
                    break;
                case 'rainy':
                    $this->create_stormy_background_overlay($image_resource, $width, $height);
                    break;
                case 'sunset':
                    $this->create_sunset_background_overlay($image_resource, $width, $height);
                    break;
                case 'night':
                    $this->create_night_background_overlay($image_resource, $width, $height);
                    break;
                case 'autumn':
                    $this->create_autumn_background_overlay($image_resource, $width, $height);
                    break;
                case 'spring':
                    $this->create_spring_background_overlay($image_resource, $width, $height);
                    break;
                case 'summer':
                    $this->create_summer_background_overlay($image_resource, $width, $height);
                    break;
            }
        }
    }
    
    /**
     * Create winter background overlay with sophisticated gradients
     */
    private function create_winter_background_overlay($image_resource, $width, $height) {
        // Create a winter sky gradient overlay
        $winter_bg = imagecreatetruecolor($width, $height);
        
        // Winter sky colors (top to bottom)
        $top_color = array(200, 210, 230);      // Light gray-blue
        $middle_color = array(220, 225, 240);   // Lighter gray
        $bottom_color = array(240, 245, 255);   // Almost white
        
        // Create gradient background
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            // Calculate gradient colors
            if ($ratio < 0.6) {
                // Top to middle
                $blend_ratio = $ratio / 0.6;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                // Middle to bottom
                $blend_ratio = ($ratio - 0.6) / 0.4;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($winter_bg, (int)$r, (int)$g, (int)$b);
            imageline($winter_bg, 0, $y, $width, $y, $color);
        }
        
        // Apply background overlay with reduced opacity to preserve subject
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $winter_bg, 0, 0, 0, 0, $width, $height, 40);
        
        imagedestroy($winter_bg);
    }
    
    /**
     * Create stormy background overlay for rainy scenes
     */
    private function create_stormy_background_overlay($image_resource, $width, $height) {
        $storm_bg = imagecreatetruecolor($width, $height);
        
        // Storm sky colors
        $top_color = array(80, 85, 95);        // Dark gray
        $middle_color = array(110, 115, 125);  // Medium gray
        $bottom_color = array(140, 145, 155);  // Lighter gray
        
        // Create gradient
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            if ($ratio < 0.5) {
                $blend_ratio = $ratio / 0.5;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                $blend_ratio = ($ratio - 0.5) / 0.5;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($storm_bg, (int)$r, (int)$g, (int)$b);
            imageline($storm_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $storm_bg, 0, 0, 0, 0, $width, $height, 35);
        imagedestroy($storm_bg);
    }
    
    /**
     * Create sunset background overlay
     */
    private function create_sunset_background_overlay($image_resource, $width, $height) {
        $sunset_bg = imagecreatetruecolor($width, $height);
        
        // Sunset colors
        $top_color = array(255, 180, 120);     // Orange
        $middle_color = array(255, 200, 150);  // Light orange
        $bottom_color = array(255, 220, 180);  // Cream
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            if ($ratio < 0.6) {
                $blend_ratio = $ratio / 0.6;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                $blend_ratio = ($ratio - 0.6) / 0.4;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($sunset_bg, (int)$r, (int)$g, (int)$b);
            imageline($sunset_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $sunset_bg, 0, 0, 0, 0, $width, $height, 30);
        imagedestroy($sunset_bg);
    }
    
    /**
     * Create night background overlay
     */
    private function create_night_background_overlay($image_resource, $width, $height) {
        $night_bg = imagecreatetruecolor($width, $height);
        
        // Night colors
        $top_color = array(20, 25, 40);        // Deep blue-black
        $middle_color = array(30, 35, 50);     // Dark blue
        $bottom_color = array(40, 45, 60);     // Lighter dark blue
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            $r = $top_color[0] + ($bottom_color[0] - $top_color[0]) * $ratio;
            $g = $top_color[1] + ($bottom_color[1] - $top_color[1]) * $ratio;
            $b = $top_color[2] + ($bottom_color[2] - $top_color[2]) * $ratio;
            
            $color = imagecolorallocate($night_bg, (int)$r, (int)$g, (int)$b);
            imageline($night_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $night_bg, 0, 0, 0, 0, $width, $height, 45);
        imagedestroy($night_bg);
    }
    
    /**
     * Create autumn background overlay
     */
    private function create_autumn_background_overlay($image_resource, $width, $height) {
        $autumn_bg = imagecreatetruecolor($width, $height);
        
        // Autumn colors
        $top_color = array(200, 150, 100);     // Warm brown
        $middle_color = array(220, 180, 120);  // Light brown
        $bottom_color = array(240, 200, 140);  // Cream brown
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            $r = $top_color[0] + ($bottom_color[0] - $top_color[0]) * $ratio;
            $g = $top_color[1] + ($bottom_color[1] - $top_color[1]) * $ratio;
            $b = $top_color[2] + ($bottom_color[2] - $top_color[2]) * $ratio;
            
            $color = imagecolorallocate($autumn_bg, (int)$r, (int)$g, (int)$b);
            imageline($autumn_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $autumn_bg, 0, 0, 0, 0, $width, $height, 25);
        imagedestroy($autumn_bg);
    }
    
    /**
     * Create spring background overlay
     */
    private function create_spring_background_overlay($image_resource, $width, $height) {
        $spring_bg = imagecreatetruecolor($width, $height);
        
        // Spring colors
        $top_color = array(180, 220, 180);     // Light green
        $middle_color = array(200, 240, 200);  // Very light green
        $bottom_color = array(220, 255, 220);  // Almost white green
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            $r = $top_color[0] + ($bottom_color[0] - $top_color[0]) * $ratio;
            $g = $top_color[1] + ($bottom_color[1] - $top_color[1]) * $ratio;
            $b = $top_color[2] + ($bottom_color[2] - $top_color[2]) * $ratio;
            
            $color = imagecolorallocate($spring_bg, (int)$r, (int)$g, (int)$b);
            imageline($spring_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $spring_bg, 0, 0, 0, 0, $width, $height, 20);
        imagedestroy($spring_bg);
    }
    
    /**
     * Create summer background overlay
     */
    private function create_summer_background_overlay($image_resource, $width, $height) {
        $summer_bg = imagecreatetruecolor($width, $height);
        
        // Summer colors
        $top_color = array(135, 206, 235);     // Sky blue
        $middle_color = array(173, 216, 230);  // Light blue
        $bottom_color = array(240, 248, 255);  // Alice blue
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            $r = $top_color[0] + ($bottom_color[0] - $top_color[0]) * $ratio;
            $g = $top_color[1] + ($bottom_color[1] - $top_color[1]) * $ratio;
            $b = $top_color[2] + ($bottom_color[2] - $top_color[2]) * $ratio;
            
            $color = imagecolorallocate($summer_bg, (int)$r, (int)$g, (int)$b);
            imageline($summer_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $summer_bg, 0, 0, 0, 0, $width, $height, 25);
        imagedestroy($summer_bg);
    }
    private function add_comprehensive_snow_effect($image_resource, $width, $height) {
        // Step 1: Create winter atmosphere with cooler colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -15, -10, 15); // Cool blue tint
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15); // Brighter for snow reflection
        imagefilter($image_resource, IMG_FILTER_CONTRAST, -5); // Softer contrast for overcast sky
        
        // Step 2: Create multiple snow layers for realism
        $snow_layers = array();
        
        // Heavy snow overlay
        $heavy_snow = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($heavy_snow, 0, 0, 0, 127);
        imagefill($heavy_snow, 0, 0, $transparent);
        imagesavealpha($heavy_snow, true);
        
        $white = imagecolorallocatealpha($heavy_snow, 255, 255, 255, 90);
        $light_white = imagecolorallocatealpha($heavy_snow, 240, 245, 255, 100);
        $very_light = imagecolorallocatealpha($heavy_snow, 255, 255, 255, 115);
        
        // Add various sizes of snowflakes for depth
        for ($i = 0; $i < 300; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 6);
            $alpha_variation = rand(80, 120);
            
            if ($size <= 2) {
                $color = imagecolorallocatealpha($heavy_snow, 255, 255, 255, $alpha_variation);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size, $color);
            } elseif ($size <= 4) {
                $color = imagecolorallocatealpha($heavy_snow, 240, 245, 255, $alpha_variation);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size + 1, $color);
            } else {
                $color = imagecolorallocatealpha($heavy_snow, 255, 255, 255, $alpha_variation - 20);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size + 2, $color);
            }
        }
        
        // Light snow background layer
        $light_snow = imagecreatetruecolor($width, $height);
        imagefill($light_snow, 0, 0, $transparent);
        imagesavealpha($light_snow, true);
        
        for ($i = 0; $i < 150; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 3);
            $color = imagecolorallocatealpha($light_snow, 255, 255, 255, 120);
            imagefilledellipse($light_snow, $x, $y, $size, $size, $color);
        }
        
        // Step 3: Add ground snow effect (bottom portion)
        $ground_height = $height / 3; // Bottom third of image
        $ground_snow = imagecreatetruecolor($width, $ground_height);
        $snow_ground = imagecolorallocatealpha($ground_snow, 250, 250, 255, 50);
        imagefill($ground_snow, 0, 0, $snow_ground);
        
        // Step 4: Apply all snow layers
        imagealphablending($image_resource, true);
        
        // Apply light snow first (background)
        imagecopymerge($image_resource, $light_snow, 0, 0, 0, 0, $width, $height, 40);
        
        // Apply ground snow
        imagecopymerge($image_resource, $ground_snow, 0, $height - $ground_height, 0, 0, $width, $ground_height, 30);
        
        // Apply heavy snow last (foreground)
        imagecopymerge($image_resource, $heavy_snow, 0, 0, 0, 0, $width, $height, 80);
        
        // Clean up
        imagedestroy($heavy_snow);
        imagedestroy($light_snow);
        imagedestroy($ground_snow);
    }

    /**
     * Add comprehensive rain effect to image
     */
    private function add_comprehensive_rain_effect($image_resource, $width, $height) {
        // Create stormy atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -20);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 10);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -10, -10, 5);
        
        // Create rain overlay with multiple intensities
        $rain_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rain_overlay, 0, 0, 0, 127);
        imagefill($rain_overlay, 0, 0, $transparent);
        imagesavealpha($rain_overlay, true);
        
        $rain_light = imagecolorallocatealpha($rain_overlay, 180, 190, 220, 110);
        $rain_medium = imagecolorallocatealpha($rain_overlay, 160, 170, 200, 100);
        $rain_heavy = imagecolorallocatealpha($rain_overlay, 200, 210, 240, 90);
        
        // Heavy rain lines (foreground)
        for ($i = 0; $i < 200; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(15, 30);
            $thickness = rand(1, 2);
            
            imagesetthickness($rain_overlay, $thickness);
            imageline($rain_overlay, $x, $y, $x - 3, $y + $length, $rain_heavy);
        }
        
        // Medium rain lines
        for ($i = 0; $i < 300; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(10, 20);
            
            imageline($rain_overlay, $x, $y, $x - 2, $y + $length, $rain_medium);
        }
        
        // Light rain lines (background)
        for ($i = 0; $i < 150; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(5, 15);
            
            imageline($rain_overlay, $x, $y, $x - 1, $y + $length, $rain_light);
        }
        
        // Add atmospheric mist at bottom
        $mist_height = $height / 4;
        for ($y = $height - $mist_height; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x += 3) {
                if (rand(0, 10) > 7) {
                    $alpha = 120 + rand(0, 7);
                    $mist_color = imagecolorallocatealpha($rain_overlay, 200, 210, 220, $alpha);
                    imagesetpixel($rain_overlay, $x, $y, $mist_color);
                }
            }
        }
        
        // Apply rain overlay
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rain_overlay, 0, 0, 0, 0, $width, $height, 75);
        
        imagedestroy($rain_overlay);
    }

    /**
     * Add sunset/golden hour effect
     */
    private function add_sunset_effect($image_resource) {
        // Warm golden tones
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 40, 20, -30);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
    }

    /**
     * Add night effect
     */
    private function add_night_effect($image_resource) {
        // Dark, cool atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -35);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -15, -10, 20);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
    }

    /**
     * Add autumn effect
     */
    private function add_autumn_effect($image_resource) {
        // Warm autumn colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 30, 10, -20);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
    }

    /**
     * Add spring effect
     */
    private function add_spring_effect($image_resource) {
        // Fresh, vibrant colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -5, 15, -10);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 8);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 3);
    }

    /**
     * Add summer effect
     */
    private function add_summer_effect($image_resource) {
        // Bright, warm summer tones
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 15, 5, -15);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
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
     * Create comprehensive winter scene with background transformation
     */
    private function create_comprehensive_winter_scene($image_resource, $width, $height) {
        // Step 1: Apply winter atmosphere with cooler colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -15, -10, 20); // Cool blue tint
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 20); // Brighter for snow reflection
        imagefilter($image_resource, IMG_FILTER_CONTRAST, -8); // Softer contrast for overcast sky
        
        // Step 2: Create winter ground effect (bottom portion gets snow-covered)
        $ground_height = $height / 2; // Bottom half of image
        $ground_snow = imagecreatetruecolor($width, $ground_height);
        
        // Create gradient from transparent at top to white snow at bottom
        for ($y = 0; $y < $ground_height; $y++) {
            $alpha_ratio = $y / $ground_height;
            $alpha = 127 - (60 * $alpha_ratio); // From transparent to semi-opaque
            
            for ($x = 0; $x < $width; $x += 2) {
                $snow_color = imagecolorallocatealpha($ground_snow, 250, 250, 255, (int)$alpha);
                imagesetpixel($ground_snow, $x, $y, $snow_color);
            }
        }
        
        // Step 3: Create multiple snow layers for realism
        $heavy_snow = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($heavy_snow, 0, 0, 0, 127);
        imagefill($heavy_snow, 0, 0, $transparent);
        imagesavealpha($heavy_snow, true);
        
        // Add various sizes of snowflakes for depth
        for ($i = 0; $i < 400; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 8);
            $alpha_variation = rand(70, 120);
            
            if ($size <= 2) {
                $color = imagecolorallocatealpha($heavy_snow, 255, 255, 255, $alpha_variation);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size, $color);
            } elseif ($size <= 4) {
                $color = imagecolorallocatealpha($heavy_snow, 240, 245, 255, $alpha_variation);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size + 1, $color);
            } else {
                $color = imagecolorallocatealpha($heavy_snow, 255, 255, 255, $alpha_variation - 20);
                imagefilledellipse($heavy_snow, $x, $y, $size, $size + 2, $color);
            }
        }
        
        // Step 4: Add atmospheric snow mist
        $mist_overlay = imagecreatetruecolor($width, $height);
        imagefill($mist_overlay, 0, 0, $transparent);
        imagesavealpha($mist_overlay, true);
        
        for ($y = 0; $y < $height; $y += 3) {
            for ($x = 0; $x < $width; $x += 5) {
                if (rand(0, 10) > 6) {
                    $alpha = 120 + rand(0, 7);
                    $mist_color = imagecolorallocatealpha($mist_overlay, 245, 250, 255, $alpha);
                    imagesetpixel($mist_overlay, $x, $y, $mist_color);
                }
            }
        }
        
        // Step 5: Apply all winter layers
        imagealphablending($image_resource, true);
        
        // Apply ground snow
        imagecopymerge($image_resource, $ground_snow, 0, $height - $ground_height, 0, 0, $width, $ground_height, 35);
        
        // Apply mist
        imagecopymerge($image_resource, $mist_overlay, 0, 0, 0, 0, $width, $height, 30);
        
        // Apply heavy snow last (foreground)
        imagecopymerge($image_resource, $heavy_snow, 0, 0, 0, 0, $width, $height, 80);
        
        // Clean up
        imagedestroy($heavy_snow);
        imagedestroy($ground_snow);
        imagedestroy($mist_overlay);
    }

    /**
     * Create comprehensive rainy scene
     */
    private function create_comprehensive_rainy_scene($image_resource, $width, $height) {
        // Create stormy atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -25);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -15, -15, 10);
        
        // Create rain overlay with multiple intensities
        $rain_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rain_overlay, 0, 0, 0, 127);
        imagefill($rain_overlay, 0, 0, $transparent);
        imagesavealpha($rain_overlay, true);
        
        // Heavy rain lines (foreground)
        for ($i = 0; $i < 250; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(20, 35);
            $thickness = rand(1, 3);
            
            $rain_color = imagecolorallocatealpha($rain_overlay, 200, 210, 240, 85);
            imagesetthickness($rain_overlay, $thickness);
            imageline($rain_overlay, $x, $y, $x - 4, $y + $length, $rain_color);
        }
        
        // Medium rain lines
        for ($i = 0; $i < 400; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(15, 25);
            
            $rain_color = imagecolorallocatealpha($rain_overlay, 180, 190, 220, 100);
            imageline($rain_overlay, $x, $y, $x - 3, $y + $length, $rain_color);
        }
        
        // Light rain lines (background)
        for ($i = 0; $i < 200; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = rand(8, 18);
            
            $rain_color = imagecolorallocatealpha($rain_overlay, 160, 170, 200, 115);
            imageline($rain_overlay, $x, $y, $x - 2, $y + $length, $rain_color);
        }
        
        // Add puddle reflections at bottom
        $puddle_height = $height / 6;
        for ($y = $height - $puddle_height; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x += 4) {
                if (rand(0, 10) > 6) {
                    $alpha = 110 + rand(0, 15);
                    $puddle_color = imagecolorallocatealpha($rain_overlay, 180, 190, 210, $alpha);
                    imagesetpixel($rain_overlay, $x, $y, $puddle_color);
                }
            }
        }
        
        // Apply rain overlay
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rain_overlay, 0, 0, 0, 0, $width, $height, 70);
        
        imagedestroy($rain_overlay);
    }

    /**
     * Create comprehensive sunset scene
     */
    private function create_comprehensive_sunset_scene($image_resource, $width, $height) {
        // Warm golden sunset tones
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 50, 25, -40);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
        
        // Create golden light rays
        $rays_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rays_overlay, 0, 0, 0, 127);
        imagefill($rays_overlay, 0, 0, $transparent);
        imagesavealpha($rays_overlay, true);
        
        // Add golden light rays from top
        $ray_source_x = $width / 2;
        $ray_source_y = 0;
        
        for ($i = 0; $i < 8; $i++) {
            $angle = ($i * 30) - 120; // Spread rays
            $ray_length = $height * 1.2;
            $end_x = $ray_source_x + cos(deg2rad($angle)) * $ray_length;
            $end_y = $ray_source_y + sin(deg2rad($angle)) * $ray_length;
            
            $ray_color = imagecolorallocatealpha($rays_overlay, 255, 220, 150, 110);
            imagesetthickness($rays_overlay, 8);
            imageline($rays_overlay, $ray_source_x, $ray_source_y, (int)$end_x, (int)$end_y, $ray_color);
        }
        
        // Apply golden rays
        imagecopymerge($image_resource, $rays_overlay, 0, 0, 0, 0, $width, $height, 25);
        imagedestroy($rays_overlay);
    }

    /**
     * Create comprehensive night scene
     */
    private function create_comprehensive_night_scene($image_resource, $width, $height) {
        // Dark, cool night atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -40);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -20, -15, 30);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 20);
        
        // Add stars
        $stars_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($stars_overlay, 0, 0, 0, 127);
        imagefill($stars_overlay, 0, 0, $transparent);
        imagesavealpha($stars_overlay, true);
        
        // Add random stars in upper portion
        $star_area = $height / 2;
        for ($i = 0; $i < 30; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $star_area);
            $size = rand(1, 3);
            
            $star_color = imagecolorallocatealpha($stars_overlay, 255, 255, 200, 80);
            imagefilledellipse($stars_overlay, $x, $y, $size, $size, $star_color);
        }
        
        // Add moon
        $moon_x = $width * 0.8;
        $moon_y = $height * 0.2;
        $moon_color = imagecolorallocatealpha($stars_overlay, 240, 240, 200, 70);
        imagefilledellipse($stars_overlay, (int)$moon_x, (int)$moon_y, 40, 40, $moon_color);
        
        imagecopymerge($image_resource, $stars_overlay, 0, 0, 0, 0, $width, $height, 60);
        imagedestroy($stars_overlay);
    }

    /**
     * Create comprehensive autumn scene
     */
    private function create_comprehensive_autumn_scene($image_resource, $width, $height) {
        // Warm autumn colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 40, 15, -25);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
        
        // Add falling leaves
        $leaves_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($leaves_overlay, 0, 0, 0, 127);
        imagefill($leaves_overlay, 0, 0, $transparent);
        imagesavealpha($leaves_overlay, true);
        
        // Different colored leaves
        $leaf_colors = array(
            array(180, 100, 50),  // Brown
            array(200, 150, 50),  // Orange
            array(220, 180, 100), // Yellow
            array(150, 80, 40)    // Dark brown
        );
        
        for ($i = 0; $i < 50; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(3, 8);
            
            $color_index = array_rand($leaf_colors);
            $color = $leaf_colors[$color_index];
            $leaf_color = imagecolorallocatealpha($leaves_overlay, $color[0], $color[1], $color[2], 90);
            imagefilledellipse($leaves_overlay, $x, $y, $size, $size + 2, $leaf_color);
        }
        
        imagecopymerge($image_resource, $leaves_overlay, 0, 0, 0, 0, $width, $height, 50);
        imagedestroy($leaves_overlay);
    }

    /**
     * Create comprehensive spring scene
     */
    private function create_comprehensive_spring_scene($image_resource, $width, $height) {
        // Fresh, vibrant spring colors
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -10, 20, -15);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
        
        // Add flower petals
        $petals_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($petals_overlay, 0, 0, 0, 127);
        imagefill($petals_overlay, 0, 0, $transparent);
        imagesavealpha($petals_overlay, true);
        
        // Cherry blossom petals
        for ($i = 0; $i < 30; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(2, 6);
            
            $petal_color = imagecolorallocatealpha($petals_overlay, 255, 200, 220, 100);
            imagefilledellipse($petals_overlay, $x, $y, $size, $size, $petal_color);
        }
        
        imagecopymerge($image_resource, $petals_overlay, 0, 0, 0, 0, $width, $height, 40);
        imagedestroy($petals_overlay);
    }

    /**
     * Create comprehensive summer scene
     */
    private function create_comprehensive_summer_scene($image_resource, $width, $height) {
        // Bright, vibrant summer colors
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 10, 5, -15); // Slight warm tint
        
        // Add sun rays
        $sun_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($sun_overlay, 0, 0, 0, 127);
        imagefill($sun_overlay, 0, 0, $transparent);
        imagesavealpha($sun_overlay, true);
        
        // Bright sun
        $sun_x = $width * 0.8;
        $sun_y = $height * 0.15;
        $sun_color = imagecolorallocatealpha($sun_overlay, 255, 240, 150, 85);
        imagefilledellipse($sun_overlay, (int)$sun_x, (int)$sun_y, 60, 60, $sun_color);
        
        // Sun rays
        for ($i = 0; $i < 12; $i++) {
            $angle = $i * 30;
            $ray_length = 80;
            $end_x = $sun_x + cos(deg2rad($angle)) * $ray_length;
            $end_y = $sun_y + sin(deg2rad($angle)) * $ray_length;
            
            $ray_color = imagecolorallocatealpha($sun_overlay, 255, 235, 120, 100);
            imagesetthickness($sun_overlay, 4);
            imageline($sun_overlay, (int)$sun_x, (int)$sun_y, (int)$end_x, (int)$end_y, $ray_color);
        }
        
        imagecopymerge($image_resource, $sun_overlay, 0, 0, 0, 0, $width, $height, 35);
        imagedestroy($sun_overlay);
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