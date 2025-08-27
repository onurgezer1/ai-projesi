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
                        'message' => sprintf(__('OpenAI processing failed (%s). Applied enhanced fallback processing. Check debug-openai-test.php for API diagnostics.', 'ai-photo-recreator'), $ai_result['message'])
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => sprintf(__('OpenAI processing failed: %s. Enhanced fallback also failed. Check debug-openai-test.php for diagnostics.', 'ai-photo-recreator'), $ai_result['message'])
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
     * 
     * @param string $instructions User instructions
     * @return array Parsed transformation requirements
     */
    private function parse_transformation_instructions($instructions) {
        $instructions = strtolower(trim($instructions));
        $transformations = array();
        
        // Weather and environmental transformations (with Turkish support)
        if (strpos($instructions, 'snow') !== false || strpos($instructions, 'kar') !== false || strpos($instructions, 'kış') !== false) {
            $transformations['environment'] = 'winter';
            $transformations['weather'] = 'heavy snowfall';
            $transformations['background'] = 'completely transform to a snowy winter landscape with snow-covered ground, falling snow, and winter environment';
            $transformations['atmosphere'] = 'cold, crisp winter atmosphere with overcast snowy sky and visible snowfall';
            $transformations['lighting'] = 'soft, diffused winter lighting typical of snowy weather';
            $transformations['effects'] = array('heavy snow falling from the sky', 'snow accumulation on all surfaces', 'frost and ice effects', 'winter atmosphere', 'cold color temperature');
            $transformations['comprehensive'] = true;
        }
        
        elseif (strpos($instructions, 'rain') !== false) {
            $transformations['environment'] = 'rainy';
            $transformations['weather'] = 'rainy';
            $transformations['background'] = 'stormy, rainy environment';
            $transformations['atmosphere'] = 'moody, overcast atmosphere with rain';
            $transformations['lighting'] = 'dramatic, darker lighting with storm clouds';
            $transformations['effects'] = array('rain drops falling', 'wet surfaces', 'puddles');
        }
        
        elseif (strpos($instructions, 'sunset') !== false || strpos($instructions, 'golden hour') !== false) {
            $transformations['environment'] = 'sunset';
            $transformations['time'] = 'golden hour';
            $transformations['background'] = 'beautiful sunset sky with warm colors';
            $transformations['atmosphere'] = 'warm, romantic golden hour atmosphere';
            $transformations['lighting'] = 'warm golden sunset lighting';
            $transformations['effects'] = array('golden sun rays', 'warm color cast', 'dramatic sky');
        }
        
        elseif (strpos($instructions, 'night') !== false || strpos($instructions, 'dark') !== false) {
            $transformations['environment'] = 'night';
            $transformations['time'] = 'nighttime';
            $transformations['background'] = 'nighttime scene with appropriate lighting';
            $transformations['atmosphere'] = 'mysterious nighttime atmosphere';
            $transformations['lighting'] = 'dramatic night lighting with artificial light sources';
            $transformations['effects'] = array('night sky', 'street lights or moon', 'night shadows');
        }
        
        // Season transformations
        elseif (strpos($instructions, 'autumn') !== false || strpos($instructions, 'fall') !== false) {
            $transformations['environment'] = 'autumn';
            $transformations['season'] = 'autumn';
            $transformations['background'] = 'autumn landscape with fall colors';
            $transformations['atmosphere'] = 'crisp autumn atmosphere';
            $transformations['effects'] = array('colorful fall leaves', 'autumn foliage', 'warm autumn tones');
        }
        
        elseif (strpos($instructions, 'spring') !== false) {
            $transformations['environment'] = 'spring';
            $transformations['season'] = 'spring';
            $transformations['background'] = 'fresh spring environment with blooming flowers';
            $transformations['atmosphere'] = 'fresh, vibrant spring atmosphere';
            $transformations['effects'] = array('blooming flowers', 'fresh green leaves', 'spring colors');
        }
        
        elseif (strpos($instructions, 'summer') !== false) {
            $transformations['environment'] = 'summer';
            $transformations['season'] = 'summer';
            $transformations['background'] = 'bright summer scene';
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
        
        // Parse transformations to understand what's needed
        $transformations = $this->parse_transformation_instructions($instructions);
        
        // Apply comprehensive transformations based on parsed instructions
        if (isset($transformations['environment'])) {
            switch ($transformations['environment']) {
                case 'winter':
                    $this->add_comprehensive_snow_effect($image_resource, $width, $height);
                    break;
                case 'rainy':
                    $this->add_comprehensive_rain_effect($image_resource, $width, $height);
                    break;
                case 'sunset':
                    $this->add_sunset_effect($image_resource);
                    break;
                case 'night':
                    $this->add_night_effect($image_resource);
                    break;
                case 'autumn':
                    $this->add_autumn_effect($image_resource);
                    break;
                case 'spring':
                    $this->add_spring_effect($image_resource);
                    break;
                case 'summer':
                    $this->add_summer_effect($image_resource);
                    break;
            }
        }
        
        // Legacy keyword-based processing for backward compatibility
        elseif (strpos($instructions, 'snow') !== false) {
            $this->add_comprehensive_snow_effect($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'rain') !== false) {
            $this->add_comprehensive_rain_effect($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'sunset') !== false || strpos($instructions, 'golden hour') !== false) {
            $this->add_sunset_effect($image_resource);
        }
        elseif (strpos($instructions, 'night') !== false) {
            $this->add_night_effect($image_resource);
        }
        elseif (strpos($instructions, 'autumn') !== false || strpos($instructions, 'fall') !== false) {
            $this->add_autumn_effect($image_resource);
        }
        elseif (strpos($instructions, 'spring') !== false) {
            $this->add_spring_effect($image_resource);
        }
        elseif (strpos($instructions, 'summer') !== false) {
            $this->add_summer_effect($image_resource);
        }
        elseif (strpos($instructions, 'vintage') !== false || strpos($instructions, 'sepia') !== false) {
            $this->add_vintage_effect($image_resource);
        }
        elseif (strpos($instructions, 'black and white') !== false || strpos($instructions, 'grayscale') !== false) {
            imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
        }
        elseif (strpos($instructions, 'blur') !== false) {
            imagefilter($image_resource, IMG_FILTER_GAUSSIAN_BLUR);
        }
        elseif (strpos($instructions, 'bright') !== false || strpos($instructions, 'lighten') !== false) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 30);
        }
        elseif (strpos($instructions, 'dark') !== false) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -30);
        }
        elseif (strpos($instructions, 'warm') !== false) {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, 20, 0, -20);
        }
        elseif (strpos($instructions, 'cool') !== false) {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, -20, 0, 20);
        }
        else {
            // Default: Apply subtle enhancement
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, -2);
        }
        
        // Apply style transformations
        if (isset($transformations['style'])) {
            switch ($transformations['style']) {
                case 'vintage':
                    $this->add_vintage_effect($image_resource);
                    break;
                case 'dramatic':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -5);
                    break;
                case 'soft':
                    imagefilter($image_resource, IMG_FILTER_SMOOTH, 2);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                    break;
            }
        }
    }
    
    /**
     * Add comprehensive snow effect to image (enhanced winter transformation)
     */
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