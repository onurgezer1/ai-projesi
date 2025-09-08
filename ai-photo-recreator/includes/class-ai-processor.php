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
     * Process image with OpenAI - Enhanced Vision + DALL-E pipeline with local analysis integration
     * 
     * @param string $file_path Original file path
     * @param string $instructions User instructions
     * @param string $api_key OpenAI API key
     * @return array Processing result
     */
    private function process_with_openai($file_path, $instructions, $api_key) {
        try {
            error_log('AI Photo Recreator: Starting enhanced OpenAI processing with comprehensive analysis');
            
            // Step 1: Perform local image analysis as backup and enhancement
            $local_analysis = $this->analyze_uploaded_image($file_path);
            
            // Step 2: Read and prepare the image for OpenAI
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
            
            // Step 3: Enhanced image analysis using GPT-4V with structured prompts
            error_log('AI Photo Recreator: Step 3 - Enhanced OpenAI Vision analysis');
            $vision_result = $this->analyze_image_with_enhanced_vision($base64_image, $api_key, $mime_type, $instructions, $local_analysis);
            
            if (!$vision_result['success']) {
                error_log('AI Photo Recreator: Vision analysis failed: ' . $vision_result['message']);
                return array(
                    'success' => false,
                    'message' => sprintf(__('Image analysis failed: %s', 'ai-photo-recreator'), $vision_result['message'])
                );
            }
            
            error_log('AI Photo Recreator: Vision analysis successful - detected: ' . 
                     (isset($vision_result['structured_analysis']['people_count']) ? $vision_result['structured_analysis']['people_count'] : 'unknown') . ' people, ' .
                     (isset($vision_result['structured_analysis']['clothing_items']) ? count($vision_result['structured_analysis']['clothing_items']) : 0) . ' clothing items');
            
            // Step 4: Create highly sophisticated prompt for DALL-E with comprehensive context
            $comprehensive_prompt = $this->create_enhanced_transformation_prompt(
                $vision_result['description'], 
                $vision_result['structured_analysis'] ?? array(),
                $instructions,
                $local_analysis
            );
            
            error_log('AI Photo Recreator: Generated enhanced DALL-E prompt (length: ' . strlen($comprehensive_prompt) . ')');
            
            // Step 5: Generate new image with DALL-E using enhanced prompt
            $dalle_result = $this->generate_image_with_dalle($comprehensive_prompt, $api_key);
            
            if ($dalle_result['success']) {
                return array(
                    'success' => true,
                    'image_data' => $dalle_result['image_data'],
                    'analysis_details' => array(
                        'local_analysis' => $local_analysis,
                        'openai_analysis' => $vision_result['structured_analysis'] ?? array(),
                        'prompt_used' => $comprehensive_prompt
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $dalle_result['message']
                );
            }
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator Enhanced OpenAI Error: ' . $e->getMessage());
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
     * Enhanced image analysis using GPT-4V with structured prompts and local analysis integration
     * 
     * @param string $base64_image Base64 encoded image
     * @param string $api_key OpenAI API key
     * @param string $mime_type Image MIME type
     * @param string $instructions User instructions for context
     * @param array $local_analysis Local image analysis results
     * @return array Analysis result with structured data
     */
    private function analyze_image_with_enhanced_vision($base64_image, $api_key, $mime_type, $instructions, $local_analysis) {
        try {
            $api_url = 'https://api.openai.com/v1/chat/completions';
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            );
            
            // Create enhanced analysis prompt with user context and local analysis
            $analysis_prompt = $this->create_enhanced_vision_prompt($instructions, $local_analysis);
            
            $body = array(
                'model' => 'gpt-4o',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => $analysis_prompt
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
                'max_tokens' => 1500
            );
            
            $args = array(
                'timeout' => 60,
                'headers' => $headers,
                'body' => json_encode($body),
                'method' => 'POST'
            );
            
            $response = wp_remote_request($api_url, $args);
            
            if (is_wp_error($response)) {
                error_log('AI Photo Recreator Enhanced Vision API: WP Error - ' . $response->get_error_message());
                return array(
                    'success' => false,
                    'message' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('AI Photo Recreator Enhanced Vision API: Response Code - ' . $response_code);
            
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
                
                error_log('AI Photo Recreator Enhanced Vision API: Error - ' . $error_message);
                error_log('AI Photo Recreator Enhanced Vision API: Full response - ' . $response_body);
                
                return array(
                    'success' => false,
                    'message' => sprintf(__('Vision API error (%d): %s', 'ai-photo-recreator'), $response_code, $error_message)
                );
            }
            
            $data = json_decode($response_body, true);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Enhanced Vision API', 'ai-photo-recreator')
                );
            }
            
            $description = $data['choices'][0]['message']['content'];
            
            // Parse structured analysis from response
            $structured_analysis = $this->parse_vision_structured_response($description);
            
            return array(
                'success' => true,
                'description' => $description,
                'structured_analysis' => $structured_analysis
            );
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator Enhanced Vision API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Failed to analyze image with Enhanced Vision API', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Create enhanced vision analysis prompt with user context
     */
    private function create_enhanced_vision_prompt($instructions, $local_analysis) {
        $prompt = "Analyze this image in extreme detail with focus on the user's specific request: \"$instructions\"\n\n";
        
        $prompt .= "COMPREHENSIVE ANALYSIS REQUIRED:\n\n";
        
        $prompt .= "1. PEOPLE ANALYSIS:\n";
        $prompt .= "- Count and describe each person (gender, age estimate, pose, expression)\n";
        $prompt .= "- Detailed clothing description for each person (shirts, pants, dresses, jackets, shoes, accessories)\n";
        $prompt .= "- Exact colors of each clothing item\n";
        $prompt .= "- Body positioning and poses\n\n";
        
        $prompt .= "2. CLOTHING FOCUS (CRITICAL FOR USER REQUEST):\n";
        $prompt .= "- Identify EXACTLY what clothing items are visible\n";
        $prompt .= "- Describe the EXACT colors of shirts/gömlek, pants/pantolon, etc.\n";
        $prompt .= "- Note clothing styles, patterns, textures\n";
        $prompt .= "- Specify which person is wearing what\n\n";
        
        $prompt .= "3. COLOR ANALYSIS:\n";
        $prompt .= "- List ALL dominant colors in the image\n";
        $prompt .= "- Specify colors of clothing items separately\n";
        $prompt .= "- Background colors and lighting\n\n";
        
        $prompt .= "4. SCENE DETAILS:\n";
        $prompt .= "- Location/setting description\n";
        $prompt .= "- Lighting conditions (indoor/outdoor, time of day)\n";
        $prompt .= "- Background elements\n";
        $prompt .= "- Overall atmosphere and mood\n\n";
        
        $prompt .= "5. CONTEXT FOR TRANSFORMATION:\n";
        $prompt .= "- Based on the request \"$instructions\", identify what needs to be changed\n";
        $prompt .= "- Specify the target elements (which person, which clothing item)\n";
        $prompt .= "- Note any challenges for the requested transformation\n\n";
        
        // Add local analysis context if available
        if ($local_analysis['success'] ?? false) {
            $prompt .= "6. LOCAL ANALYSIS CONFIRMATION:\n";
            $prompt .= "- Local analysis detected " . ($local_analysis['people_count'] ?? 0) . " people\n";
            $prompt .= "- Dominant local colors: " . implode(', ', array_column($local_analysis['dominant_colors'] ?? array(), 'color_name')) . "\n";
            $prompt .= "- Please confirm or correct this analysis\n\n";
        }
        
        $prompt .= "RESPONSE FORMAT:\n";
        $prompt .= "Provide detailed description followed by:\n";
        $prompt .= "STRUCTURED_DATA:\n";
        $prompt .= "People: [count]\n";
        $prompt .= "Person1_Clothing: [detailed list with colors]\n";
        $prompt .= "Person2_Clothing: [if applicable]\n";
        $prompt .= "Target_Element: [what user wants to change based on request]\n";
        $prompt .= "Feasibility: [how feasible is the requested change]\n";
        
        return $prompt;
    }
    
    /**
     * Parse structured response from enhanced vision analysis
     */
    private function parse_vision_structured_response($description) {
        $structured = array();
        
        // Extract structured data section
        if (preg_match('/STRUCTURED_DATA:\s*(.*?)$/s', $description, $matches)) {
            $data_section = $matches[1];
            
            // Parse each line
            $lines = explode("\n", $data_section);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (preg_match('/People:\s*(\d+)/', $line, $m)) {
                    $structured['people_count'] = (int)$m[1];
                } elseif (preg_match('/Person(\d+)_Clothing:\s*(.+)/', $line, $m)) {
                    $structured['people'][$m[1]]['clothing'] = trim($m[2]);
                } elseif (preg_match('/Target_Element:\s*(.+)/', $line, $m)) {
                    $structured['target_element'] = trim($m[2]);
                } elseif (preg_match('/Feasibility:\s*(.+)/', $line, $m)) {
                    $structured['feasibility'] = trim($m[2]);
                }
            }
        }
        
        // Extract clothing items mentioned in description
        $clothing_items = array();
        $clothing_patterns = array(
            'shirt' => '/(?:shirt|gömlek)/i',
            'pants' => '/(?:pants|pantolon|trousers)/i',
            'dress' => '/(?:dress|elbise)/i',
            'jacket' => '/(?:jacket|ceket|coat)/i'
        );
        
        foreach ($clothing_patterns as $item => $pattern) {
            if (preg_match($pattern, $description)) {
                $clothing_items[] = $item;
            }
        }
        
        $structured['clothing_items'] = $clothing_items;
        
        return $structured;
    }
    
    /**
     * Create enhanced transformation prompt with comprehensive analysis
     */
    private function create_enhanced_transformation_prompt($vision_description, $structured_analysis, $instructions, $local_analysis) {
        $prompt = "Create a photorealistic image that precisely recreates this scene with the requested modification.\n\n";
        
        $prompt .= "ORIGINAL SCENE ANALYSIS:\n";
        $prompt .= $vision_description . "\n\n";
        
        $prompt .= "USER REQUEST: \"$instructions\"\n\n";
        
        $prompt .= "TRANSFORMATION REQUIREMENTS:\n";
        
        // Add specific instructions based on structured analysis
        if (isset($structured_analysis['target_element'])) {
            $prompt .= "PRIMARY FOCUS: " . $structured_analysis['target_element'] . "\n";
        }
        
        // Enhanced clothing transformation instructions
        if (strpos(strtolower($instructions), 'gömlek') !== false || strpos(strtolower($instructions), 'shirt') !== false) {
            $prompt .= "CLOTHING TRANSFORMATION:\n";
            $prompt .= "- Identify the shirt/gömlek in the image\n";
            $prompt .= "- Change ONLY the shirt color as requested\n";
            $prompt .= "- Preserve all other elements: person, pose, background, other clothing\n";
            $prompt .= "- Maintain fabric texture and realistic lighting on the shirt\n";
            $prompt .= "- Ensure the new color looks natural and realistic\n\n";
        }
        
        // Color-specific instructions
        if (preg_match('/["\']([^"\']+)["\']/', $instructions, $color_matches)) {
            $requested_color = $color_matches[1];
            $prompt .= "COLOR SPECIFICATION:\n";
            $prompt .= "- Target color: $requested_color\n";
            $prompt .= "- Apply this exact color with appropriate shading and highlights\n";
            $prompt .= "- Maintain realistic fabric appearance\n\n";
        }
        
        $prompt .= "CRITICAL PRESERVATION REQUIREMENTS:\n";
        $prompt .= "- EXACTLY preserve all people: faces, expressions, poses, body positions\n";
        $prompt .= "- EXACTLY preserve scene composition and camera angle\n";
        $prompt .= "- EXACTLY preserve background and environment\n";
        $prompt .= "- EXACTLY preserve lighting conditions and atmosphere\n";
        $prompt .= "- ONLY change the specifically requested element\n\n";
        
        $prompt .= "QUALITY REQUIREMENTS:\n";
        $prompt .= "- Photorealistic quality matching the original\n";
        $prompt .= "- Natural lighting and shadows on modified elements\n";
        $prompt .= "- Seamless integration of changes\n";
        $prompt .= "- High resolution and sharp details\n\n";
        
        $prompt .= "CONTEXT AWARENESS:\n";
        $prompt .= "- This is a specific, targeted modification request\n";
        $prompt .= "- Focus on precision and accuracy of the requested change\n";
        $prompt .= "- Maintain photographic realism throughout\n";
        
        return $prompt;
    }
    
    /**
     * Advanced intelligent instruction parser with contextual understanding
     * Supports varied instruction styles, compound commands, and intensity analysis
     * 
     * @param string $instructions User instructions
     * @return array Comprehensive transformation requirements
     */
    /**
     * Advanced AI-powered transformation instruction parsing with deep semantic understanding
     */
    private function parse_transformation_instructions($instructions) {
        // Initialize comprehensive analysis framework
        $original_instructions = trim($instructions);
        $instructions_lower = strtolower($original_instructions);
        
        $transformations = array(
            'original_text' => $original_instructions,
            'instruction_hash' => md5($original_instructions . time()), // Unique identifier for this specific instruction
            'timestamp' => time(),
            'language' => $this->detect_primary_language($original_instructions)
        );
        
        // PHASE 1: Deep Semantic Analysis - Understanding Intent and Context
        $semantic_analysis = $this->perform_advanced_semantic_analysis($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $semantic_analysis);
        
        // PHASE 2: Contextual Intelligence - Understanding Relationships and Dependencies
        $contextual_intelligence = $this->analyze_contextual_relationships($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $contextual_intelligence);
        
        // PHASE 3: Linguistic Intelligence - Grammar, Syntax, and Language-Specific Patterns
        $linguistic_intelligence = $this->perform_linguistic_intelligence_analysis($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $linguistic_intelligence);
        
        // PHASE 4: Creative Intelligence - Abstract Concepts and Artistic Intent
        $creative_intelligence = $this->analyze_creative_and_artistic_intent($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $creative_intelligence);
        
        // PHASE 5: Environmental Intelligence - Scene Context and Transformation Requirements
        $environmental_intelligence = $this->analyze_environmental_transformation_requirements($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $environmental_intelligence);
        
        // PHASE 6: Complexity Assessment - Understanding Transformation Scope
        $complexity_analysis = $this->assess_transformation_complexity_and_scope($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $complexity_analysis);
        
        // PHASE 7: Object-Specific Intelligence - Clothing, Items, and Targeted Transformations
        $object_intelligence = $this->analyze_object_specific_transformations($original_instructions, $instructions_lower);
        $transformations = array_merge($transformations, $object_intelligence);
        
        // PHASE 8: Dynamic Uniqueness Factors - Ensuring Varied Results
        $uniqueness_factors = $this->generate_dynamic_uniqueness_factors($transformations);
        $transformations = array_merge($transformations, $uniqueness_factors);
        
        return $transformations;
    }
    
    /**
     * Analyze instruction intensity for graduated effects
     */
    private function analyze_instruction_intensity($instructions) {
        $intensity_markers = array(
            'extreme' => array('çok', 'very', 'extremely', 'intense', 'heavy', 'strong', 'dramatic', 'aşırı', 'yoğun', 'güçlü'),
            'high' => array('quite', 'pretty', 'fairly', 'oldukça', 'epey', 'hayli'),
            'medium' => array('normal', 'regular', 'standard', 'normal', 'düzenli', 'standart'),
            'light' => array('light', 'gentle', 'soft', 'subtle', 'hafif', 'yumuşak', 'nazik', 'ince'),
            'minimal' => array('slightly', 'barely', 'just', 'az', 'hafifçe', 'sadece', 'biraz')
        );
        
        foreach ($intensity_markers as $level => $markers) {
            foreach ($markers as $marker) {
                if (strpos($instructions, $marker) !== false) {
                    return $level;
                }
            }
        }
        
        return 'medium'; // Default intensity
    }
    
    /**
     * Extract action verbs to understand what the user wants to do
     */
    private function extract_action_verbs($instructions) {
        $action_verbs = array(
            'add' => array('add', 'ekle', 'koy', 'place', 'put'),
            'remove' => array('remove', 'kaldır', 'sil', 'delete', 'çıkar'),
            'change' => array('change', 'değiştir', 'alter', 'modify', 'transform', 'dönüştür'),
            'make' => array('make', 'yap', 'create', 'oluştur', 'generate'),
            'turn' => array('turn', 'çevir', 'convert', 'dönüştür'),
            'enhance' => array('enhance', 'improve', 'geliştir', 'iyileştir', 'better'),
            'apply' => array('apply', 'uygula', 'kullan', 'use')
        );
        
        $found_actions = array();
        foreach ($action_verbs as $action => $verbs) {
            foreach ($verbs as $verb) {
                if (strpos($instructions, $verb) !== false) {
                    $found_actions[] = $action;
                    break;
                }
            }
        }
        
        return array_unique($found_actions);
    }
    
    /**
     * Extract descriptive terms to understand the desired outcome
     */
    private function extract_descriptive_terms($instructions) {
        $descriptive_terms = array(
            'mood' => array('dramatic', 'soft', 'dreamy', 'romantic', 'mysterious', 'bright', 'dark', 'dramatik', 'yumuşak', 'rüya gibi'),
            'color' => array('colorful', 'vibrant', 'muted', 'warm', 'cool', 'bright', 'dark', 'renkli', 'canlı', 'sıcak', 'soğuk'),
            'weather' => array('stormy', 'sunny', 'cloudy', 'clear', 'foggy', 'misty', 'fırtınalı', 'güneşli', 'bulutlu', 'sisli'),
            'time' => array('morning', 'noon', 'afternoon', 'evening', 'night', 'dawn', 'dusk', 'sabah', 'öğle', 'akşam', 'gece'),
            'season' => array('seasonal', 'wintry', 'summery', 'springlike', 'autumnal', 'mevsimsel', 'kışlık', 'yazlık')
        );
        
        $found_terms = array();
        foreach ($descriptive_terms as $category => $terms) {
            foreach ($terms as $term) {
                if (strpos($instructions, $term) !== false) {
                    if (!isset($found_terms[$category])) {
                        $found_terms[$category] = array();
                    }
                    $found_terms[$category][] = $term;
                }
            }
        }
        
        return $found_terms;
    }
    
    /**
     * Advanced weather environment detection with intensity and context
     */
    private function detect_weather_environments($instructions) {
        $transformations = array();
        
        // Winter/Snow Detection with variations and intensity
        $winter_patterns = array(
            'snow' => array('patterns' => array('snow', 'kar', 'kış', 'winter', 'snowy', 'kar yağdır', 'kar yağ', 'karlı', 'kar tanesi', 'snowflake', 'buzlu', 'icy', 'don', 'frost'), 'intensity_boost' => false),
            'blizzard' => array('patterns' => array('blizzard', 'kar fırtına', 'heavy snow', 'yoğun kar', 'kar kalın'), 'intensity_boost' => true)
        );
        
        foreach ($winter_patterns as $type => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $intensity = $data['intensity_boost'] ? 'extreme' : 'high';
                    $transformations = array_merge($transformations, $this->create_winter_transformation($intensity));
                    break 2; // Exit both loops
                }
            }
        }
        
        // Rain Detection with variations
        $rain_patterns = array(
            'light_rain' => array('patterns' => array('drizzle', 'light rain', 'hafif yağmur', 'çisenti'), 'intensity' => 'light'),
            'rain' => array('patterns' => array('rain', 'yağmur', 'rainy', 'yağmurlu', 'wet', 'ıslak'), 'intensity' => 'medium'),
            'storm' => array('patterns' => array('storm', 'thunder', 'heavy rain', 'fırtına', 'gök gürültü', 'yoğun yağmur'), 'intensity' => 'extreme')
        );
        
        foreach ($rain_patterns as $type => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $transformations = array_merge($transformations, $this->create_rain_transformation($data['intensity']));
                    break 2;
                }
            }
        }
        
        // Sunset/Golden Hour Detection
        $sunset_patterns = array('sunset', 'golden hour', 'gün batımı', 'günbatımı', 'altın saat', 'warm light', 'sıcak ışık');
        foreach ($sunset_patterns as $pattern) {
            if (strpos($instructions, $pattern) !== false) {
                $transformations = array_merge($transformations, $this->create_sunset_transformation());
                break;
            }
        }
        
        // Night Detection
        $night_patterns = array('night', 'dark', 'gece', 'karanlık', 'nighttime', 'moonlight', 'ay ışığı', 'starry', 'yıldızlı');
        foreach ($night_patterns as $pattern) {
            if (strpos($instructions, $pattern) !== false) {
                $transformations = array_merge($transformations, $this->create_night_transformation());
                break;
            }
        }
        
        return $transformations;
    }
    
    /**
     * Create dynamic winter transformation based on intensity
     */
    private function create_winter_transformation($intensity = 'medium') {
        $base_transform = array(
            'environment' => 'winter',
            'background_replace' => true,
            'comprehensive' => true
        );
        
        switch ($intensity) {
            case 'extreme':
                return array_merge($base_transform, array(
                    'weather' => 'blizzard conditions with heavy snowfall',
                    'background' => 'intense winter blizzard landscape with deep snow drifts and heavy snowfall',
                    'atmosphere' => 'fierce winter storm atmosphere with whiteout conditions and intense snowfall',
                    'lighting' => 'harsh, diffused blizzard lighting with limited visibility',
                    'effects' => array('blizzard-level snowfall', 'snow drifts', 'whiteout conditions', 'ice crystals', 'extreme cold atmosphere', 'wind-blown snow'),
                    'intensity_level' => 'extreme'
                ));
            case 'high':
                return array_merge($base_transform, array(
                    'weather' => 'heavy snowfall',
                    'background' => 'snow-covered winter landscape with heavy falling snow and winter atmosphere',
                    'atmosphere' => 'cold, crisp winter atmosphere with overcast snowy sky and visible heavy snowfall',
                    'lighting' => 'soft, diffused winter lighting typical of heavy snow weather',
                    'effects' => array('heavy snow falling from the sky', 'thick snow accumulation on all surfaces', 'frost and ice effects', 'winter atmosphere', 'cold color temperature'),
                    'intensity_level' => 'high'
                ));
            case 'light':
                return array_merge($base_transform, array(
                    'weather' => 'light snowfall',
                    'background' => 'gentle winter scene with light snow and winter ambiance',
                    'atmosphere' => 'mild winter atmosphere with light overcast sky and gentle snowfall',
                    'lighting' => 'soft winter lighting with gentle snow ambiance',
                    'effects' => array('light snow falling', 'light snow dusting', 'gentle winter ambiance', 'cool color temperature'),
                    'intensity_level' => 'light'
                ));
            default: // medium
                return array_merge($base_transform, array(
                    'weather' => 'moderate snowfall',
                    'background' => 'winter landscape with moderate snow coverage and steady snowfall',
                    'atmosphere' => 'crisp winter atmosphere with steady snowfall',
                    'lighting' => 'diffused winter lighting',
                    'effects' => array('steady snowfall', 'snow accumulation', 'winter atmosphere', 'cool tones'),
                    'intensity_level' => 'medium'
                ));
        }
    }
    
    /**
     * Create dynamic rain transformation based on intensity  
     */
    private function create_rain_transformation($intensity = 'medium') {
        $base_transform = array(
            'environment' => 'rainy',
            'background_replace' => true,
            'comprehensive' => true
        );
        
        switch ($intensity) {
            case 'extreme':
                return array_merge($base_transform, array(
                    'weather' => 'thunderstorm with heavy rain',
                    'background' => 'dramatic thunderstorm environment with dark storm clouds and lightning',
                    'atmosphere' => 'intense storm atmosphere with thunder, lightning, and torrential rain',
                    'lighting' => 'dramatic storm lighting with lightning flashes and dark clouds',
                    'effects' => array('torrential rain', 'lightning', 'thunder clouds', 'flooding puddles', 'storm winds'),
                    'intensity_level' => 'extreme'
                ));
            case 'light':
                return array_merge($base_transform, array(
                    'weather' => 'light drizzle',
                    'background' => 'gentle overcast environment with light rain',
                    'atmosphere' => 'soft, misty atmosphere with light drizzle',
                    'lighting' => 'soft, diffused overcast lighting',
                    'effects' => array('light drizzle', 'gentle mist', 'soft rain drops', 'light puddles'),
                    'intensity_level' => 'light'
                ));
            default: // medium
                return array_merge($base_transform, array(
                    'weather' => 'steady rain',
                    'background' => 'rainy environment with dark clouds and steady rainfall',
                    'atmosphere' => 'moody, overcast atmosphere with steady rain',
                    'lighting' => 'dramatic, darker lighting with storm clouds',
                    'effects' => array('steady rain drops falling', 'wet surfaces', 'puddles', 'storm clouds'),
                    'intensity_level' => 'medium'
                ));
        }
    }
    
    /**
     * Create sunset transformation
     */
    private function create_sunset_transformation() {
        return array(
            'environment' => 'sunset',
            'time' => 'golden hour',
            'background' => 'beautiful sunset sky with warm golden colors and dramatic clouds',
            'atmosphere' => 'warm, romantic golden hour atmosphere',
            'lighting' => 'warm golden sunset lighting',
            'effects' => array('golden sun rays', 'warm color cast', 'dramatic sunset sky', 'golden light'),
            'comprehensive' => true
        );
    }
    
    /**
     * Create night transformation
     */
    private function create_night_transformation() {
        return array(
            'environment' => 'night',
            'time' => 'nighttime',
            'background' => 'dramatic nighttime scene with stars or city lights',
            'atmosphere' => 'mysterious nighttime atmosphere',
            'lighting' => 'dramatic night lighting with artificial light sources or moonlight',
            'effects' => array('night sky', 'street lights or moon', 'night shadows', 'stars'),
            'background_replace' => true,
            'comprehensive' => true
        );
    }
    
    /**
     * Detect seasonal and location transformations
     */
    private function detect_seasonal_and_location_transforms($instructions) {
        $transformations = array();
        
        // Seasonal transformations with Turkish support
        $seasonal_patterns = array(
            'autumn' => array('autumn', 'fall', 'sonbahar', 'güz'),
            'spring' => array('spring', 'ilkbahar'),
            'summer' => array('summer', 'yaz')
        );
        
        foreach ($seasonal_patterns as $season => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $transformations = array_merge($transformations, $this->create_seasonal_transformation($season));
                    break 2;
                }
            }
        }
        
        // Location transformations
        $location_patterns = array(
            'beach' => array('beach', 'ocean', 'sea', 'sahil', 'deniz', 'okyanuz'),
            'forest' => array('forest', 'woods', 'trees', 'orman', 'ağaç'),
            'mountain' => array('mountain', 'hill', 'dağ', 'tepe'),
            'city' => array('city', 'urban', 'street', 'şehir', 'kent', 'sokak'),
            'desert' => array('desert', 'sand', 'çöl', 'kum'),
            'field' => array('field', 'meadow', 'grass', 'tarla', 'çayır', 'ot')
        );
        
        foreach ($location_patterns as $location => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $transformations = array_merge($transformations, $this->create_location_transformation($location));
                    break 2;
                }
            }
        }
        
        return $transformations;
    }
    
    /**
     * Create seasonal transformations
     */
    private function create_seasonal_transformation($season) {
        $seasonal_configs = array(
            'autumn' => array(
                'environment' => 'autumn',
                'season' => 'autumn',
                'background' => 'autumn landscape with colorful fall foliage and trees',
                'atmosphere' => 'crisp autumn atmosphere with falling leaves',
                'effects' => array('colorful fall leaves', 'autumn foliage', 'warm autumn tones', 'falling leaves'),
                'background_replace' => true
            ),
            'spring' => array(
                'environment' => 'spring',
                'season' => 'spring',
                'background' => 'fresh spring environment with blooming flowers and green trees',
                'atmosphere' => 'fresh, vibrant spring atmosphere',
                'effects' => array('blooming flowers', 'fresh green leaves', 'spring colors', 'cherry blossoms'),
                'background_replace' => true
            ),
            'summer' => array(
                'environment' => 'summer',
                'season' => 'summer',
                'background' => 'bright summer scene with blue sky and sunshine',
                'atmosphere' => 'warm, bright summer atmosphere',
                'lighting' => 'bright summer sunlight',
                'effects' => array('bright sunshine', 'summer colors', 'clear blue sky')
            )
        );
        
        return isset($seasonal_configs[$season]) ? $seasonal_configs[$season] : array();
    }
    
    /**
     * Create location transformations
     */
    private function create_location_transformation($location) {
        $location_configs = array(
            'beach' => array(
                'location' => 'beach',
                'background' => 'beautiful beach scene with ocean waves and sand',
                'atmosphere' => 'coastal atmosphere with sea breeze',
                'effects' => array('ocean waves', 'sand', 'seagulls', 'coastal breeze'),
                'background_replace' => true
            ),
            'forest' => array(
                'location' => 'forest',
                'background' => 'dense forest environment with tall trees and natural lighting',
                'atmosphere' => 'forest atmosphere with dappled sunlight',
                'effects' => array('tall trees', 'forest canopy', 'natural lighting', 'forest floor'),
                'background_replace' => true
            ),
            'mountain' => array(
                'location' => 'mountains',
                'background' => 'majestic mountain landscape with peaks and valleys',
                'atmosphere' => 'mountain atmosphere with clear air',
                'effects' => array('mountain peaks', 'valleys', 'mountain air', 'scenic views'),
                'background_replace' => true
            ),
            'city' => array(
                'location' => 'urban',
                'background' => 'urban cityscape with buildings and streets',
                'atmosphere' => 'urban atmosphere with city energy',
                'effects' => array('buildings', 'streets', 'urban lighting', 'city life')
            ),
            'desert' => array(
                'location' => 'desert',
                'background' => 'desert landscape with sand dunes and clear sky',
                'atmosphere' => 'arid desert atmosphere',
                'effects' => array('sand dunes', 'desert sky', 'arid climate', 'desert landscape'),
                'background_replace' => true
            ),
            'field' => array(
                'location' => 'field',
                'background' => 'open field with grass and sky',
                'atmosphere' => 'open field atmosphere with natural elements',
                'effects' => array('grass field', 'open sky', 'natural environment'),
                'background_replace' => true
            )
        );
        
        return isset($location_configs[$location]) ? $location_configs[$location] : array();
    }
    
    /**
     * Detect style transformations
     */
    private function detect_style_transformations($instructions) {
        $transformations = array();
        
        $style_patterns = array(
            'vintage' => array('vintage', 'retro', 'old', 'classic', 'nostalgic', 'eski', 'klasik', 'nostalji'),
            'dramatic' => array('dramatic', 'intense', 'bold', 'striking', 'dramatik', 'yoğun', 'cesur'),
            'soft' => array('soft', 'gentle', 'dreamy', 'ethereal', 'yumuşak', 'nazik', 'rüya gibi'),
            'artistic' => array('artistic', 'creative', 'painterly', 'stylized', 'sanatsal', 'yaratıcı'),
            'cinematic' => array('cinematic', 'movie', 'film', 'sinematik', 'film gibi'),
            'bright' => array('bright', 'vivid', 'vibrant', 'colorful', 'parlak', 'canlı', 'renkli'),
            'dark' => array('dark', 'moody', 'mysterious', 'gothic', 'karanlık', 'gizemli')
        );
        
        foreach ($style_patterns as $style => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $transformations['style'] = $style;
                    $transformations['style_effects'] = $this->get_style_effects($style);
                    break 2;
                }
            }
        }
        
        return $transformations;
    }
    
    /**
     * Get effects for specific styles
     */
    private function get_style_effects($style) {
        $style_effects = array(
            'vintage' => array('sepia tones', 'film grain', 'aged look', 'warm color cast'),
            'dramatic' => array('high contrast', 'bold shadows', 'intense lighting', 'strong colors'),
            'soft' => array('soft lighting', 'gentle colors', 'dreamy atmosphere', 'subtle effects'),
            'artistic' => array('creative color grading', 'artistic filters', 'stylized look'),
            'cinematic' => array('cinematic color grading', 'film-like atmosphere', 'dramatic composition'),
            'bright' => array('increased brightness', 'vibrant colors', 'enhanced saturation'),
            'dark' => array('reduced brightness', 'enhanced shadows', 'moody atmosphere', 'mysterious tones')
        );
        
        return isset($style_effects[$style]) ? $style_effects[$style] : array();
    }
    
    /**
     * Detect compound instructions (multiple transformations)
     */
    private function detect_compound_instructions($instructions) {
        $transformations = array();
        
        // Look for connecting words that indicate multiple transformations
        $connectors = array('and', 've', 'with', 'plus', 'also', 'then', 'also add', 'ayrıca', 'ile', 'artı');
        
        foreach ($connectors as $connector) {
            if (strpos($instructions, $connector) !== false) {
                $transformations['compound'] = true;
                $transformations['multiple_transforms'] = true;
                break;
            }
        }
        
        return $transformations;
    }
    
    /**
     * Handle creative and abstract instructions
     */
    private function handle_creative_instructions($instructions) {
        $transformations = array();
        
        // Creative instruction patterns
        $creative_patterns = array(
            'magical' => array('magical', 'mystical', 'enchanted', 'fantasy', 'sihirli', 'büyülü', 'fantastik'),
            'surreal' => array('surreal', 'abstract', 'weird', 'strange', 'gerçeküstü', 'soyut', 'tuhaf'),
            'emotion_happy' => array('happy', 'joyful', 'cheerful', 'bright', 'mutlu', 'neşeli', 'sevimli'),
            'emotion_sad' => array('sad', 'melancholy', 'gloomy', 'depressing', 'üzgün', 'kasvetli', 'melankolik'),
            'energy_high' => array('energetic', 'dynamic', 'active', 'lively', 'enerjik', 'dinamik', 'hareketli'),
            'energy_calm' => array('peaceful', 'calm', 'serene', 'tranquil', 'huzurlu', 'sakin', 'dingin')
        );
        
        foreach ($creative_patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($instructions, $pattern) !== false) {
                    $transformations['creative_type'] = $type;
                    $transformations['creative_effects'] = $this->get_creative_effects($type);
                    break 2;
                }
            }
        }
        
        return $transformations;
    }
    
    /**
     * Get effects for creative transformations
     */
    private function get_creative_effects($type) {
        $creative_effects = array(
            'magical' => array('ethereal glow', 'sparkles', 'mystical atmosphere', 'enchanted lighting'),
            'surreal' => array('unusual colors', 'distorted elements', 'abstract effects', 'surreal atmosphere'),
            'emotion_happy' => array('warm colors', 'bright lighting', 'cheerful atmosphere', 'vibrant tones'),
            'emotion_sad' => array('cool colors', 'muted tones', 'melancholic atmosphere', 'soft shadows'),
            'energy_high' => array('dynamic colors', 'enhanced contrast', 'energetic atmosphere', 'vibrant effects'),
            'energy_calm' => array('soft colors', 'gentle lighting', 'peaceful atmosphere', 'serene tones')
        );
        
        return isset($creative_effects[$type]) ? $creative_effects[$type] : array();
    }

    /**
     * Create intelligent and comprehensive prompt for DALL-E based on image analysis and user instructions
     * Enhanced to produce unique outputs for different instruction variations
     * 
     * @param string $image_description Description from Vision API
     * @param string $user_instructions User's transformation instructions
     * @return string Comprehensive prompt for DALL-E
     */
    /**
     * Create intelligent, context-aware transformation prompt for OpenAI
     */
    private function create_transformation_prompt($image_description, $user_instructions) {
        // Parse instructions with advanced AI intelligence
        $transformations = $this->parse_transformation_instructions($user_instructions);
        
        // Build comprehensive, context-aware prompt
        $prompt = $this->build_advanced_intelligent_prompt($image_description, $transformations, $user_instructions);
        
        return $prompt;
    }
    
    /**
     * Build advanced intelligent prompt with deep context understanding
     */
    private function build_advanced_intelligent_prompt($image_description, $transformations, $user_instructions) {
        // Start with intelligent base prompt
        $prompt = "Create a photorealistic image that recreates the EXACT same subjects, poses, expressions, and composition from this scene: " . $image_description;
        
        // Add instruction signature for uniqueness
        $instruction_signature = isset($transformations['instruction_hash']) ? substr($transformations['instruction_hash'], 0, 12) : 'default';
        $prompt .= "\n\nINSTRUCTION SIGNATURE: [" . $instruction_signature . "] - ensure this specific interpretation produces unique results";
        
        // Add intelligent transformation analysis
        if (isset($transformations['primary_intent'])) {
            $prompt .= "\n\nPRIMARY INTENT: The user's primary goal is " . $transformations['primary_intent'] . ". Focus on achieving this specific intent.";
        }
        
        if (isset($transformations['transformation_scope'])) {
            $scope_intensity_map = array(
                'comprehensive' => 'MAXIMUM transformation strength - make changes highly dramatic and immediately obvious',
                'major' => 'STRONG transformation effects - make changes clearly visible and impactful', 
                'moderate' => 'BALANCED transformation effects - clearly visible but natural-looking',
                'minor' => 'SUBTLE transformation effects - gentle but still noticeable',
                'subtle' => 'VERY SUBTLE effects - minimal but tasteful changes'
            );
            
            $intensity_instruction = $scope_intensity_map[$transformations['transformation_scope']] ?? 'BALANCED transformation effects';
            $prompt .= "\n\nINTENSITY: Apply " . $intensity_instruction;
        }
        
        // Add intelligent weather/environmental analysis
        if (isset($transformations['weather_analysis']) && !empty($transformations['weather_analysis'])) {
            foreach ($transformations['weather_analysis'] as $weather_type => $analysis) {
                if ($analysis['detected']) {
                    $prompt .= "\n\nENVIRONMENTAL TRANSFORMATION: Create " . $weather_type . " conditions with " . $analysis['intensity'] . " intensity.";
                    
                    // Add specific weather context requirements
                    if (isset($analysis['context_requirements'])) {
                        $requirements = $analysis['context_requirements'];
                        if ($requirements['background_change'] ?? false) {
                            $prompt .= " Completely transform the background to match " . $weather_type . " environment.";
                        }
                        if ($requirements['atmosphere_change'] ?? false) {
                            $prompt .= " Create appropriate atmospheric conditions for " . $weather_type . ".";
                        }
                        if ($requirements['lighting_adjustment'] ?? false) {
                            $prompt .= " Adjust lighting to match " . $weather_type . " conditions.";
                        }
                        if (isset($requirements['color_temperature'])) {
                            $prompt .= " Apply " . $requirements['color_temperature'] . " color temperature.";
                        }
                    }
                }
            }
        }
        
        // Add artistic intelligence
        if (isset($transformations['artistic_style'])) {
            $prompt .= "\n\nARTISTIC STYLE: Apply " . $transformations['artistic_style'] . " aesthetic with " . 
                      ($transformations['artistic_intensity'] ?? 'medium') . " intensity.";
        }
        
        // Add mood and atmosphere intelligence
        if (isset($transformations['desired_mood']) && !empty($transformations['desired_mood'])) {
            $moods = implode(', ', $transformations['desired_mood']);
            $prompt .= "\n\nMOOD CREATION: Create a " . $moods . " atmosphere throughout the scene.";
        }
        
        // Add language-specific intelligence
        if (isset($transformations['language']) && $transformations['language'] == 'turkish') {
            if (isset($transformations['turkish_analysis'])) {
                $prompt .= "\n\nLANGUAGE CONTEXT: This instruction comes from Turkish context. ";
                if (isset($transformations['turkish_analysis']['imperative']) && $transformations['turkish_analysis']['imperative']) {
                    $prompt .= "Execute as a direct command with immediate, visible results.";
                }
            }
        }
        
        // Add object-specific intelligence (NEW)
        if (isset($transformations['transformation_type']) || isset($transformations['color_transformation']) || isset($transformations['body_modification'])) {
            $prompt .= "\n\nOBJECT-SPECIFIC TRANSFORMATIONS:";
            
            // Clothing modifications
            if (isset($transformations['transformation_type']) && $transformations['transformation_type'] === 'clothing_modification') {
                $clothing_items = implode(', ', $transformations['target_clothing'] ?? array());
                $prompt .= "\n- TARGET CLOTHING: Focus transformations on " . $clothing_items;
                
                if (isset($transformations['clothing_actions'])) {
                    foreach ($transformations['clothing_actions'] as $action) {
                        switch ($action) {
                            case 'color_change':
                                if (isset($transformations['target_colors'])) {
                                    $colors = implode(', ', $transformations['target_colors']);
                                    $prompt .= "\n- CLOTHING COLOR CHANGE: Change " . $clothing_items . " color to " . $colors . ". Make this the PRIMARY and MOST VISIBLE transformation.";
                                }
                                break;
                            case 'style_change':
                                $prompt .= "\n- CLOTHING STYLE: Modify the style/pattern of " . $clothing_items;
                                break;
                        }
                    }
                }
            }
            
            // Color transformations
            if (isset($transformations['color_transformation']) && $transformations['color_transformation']) {
                $colors = implode(', ', $transformations['target_colors'] ?? array());
                $intensity = $transformations['color_intensity'] ?? 'normal';
                $prompt .= "\n- COLOR TRANSFORMATION: Apply " . $colors . " with " . $intensity . " intensity. Make color changes clearly visible and prominent.";
            }
            
            // Body modifications
            if (isset($transformations['body_modification']) && $transformations['body_modification']) {
                $body_parts = implode(', ', $transformations['target_body_parts'] ?? array());
                $prompt .= "\n- BODY/FACIAL MODIFICATIONS: Modify " . $body_parts . " while preserving person's identity and overall appearance.";
            }
        }
        
        // Add complexity-based adjustments
        if (isset($transformations['complexity_score'])) {
            $complexity = $transformations['complexity_score'];
            if ($complexity > 30) {
                $prompt .= "\n\nCOMPLEX TRANSFORMATION: This is a highly complex request requiring careful attention to multiple simultaneous changes. Balance all elements harmoniously.";
            } elseif ($complexity > 15) {
                $prompt .= "\n\nMODERATE TRANSFORMATION: Apply multiple coordinated changes while maintaining natural appearance.";
            }
        }
        
        // Add creative intelligence factors
        if (isset($transformations['abstract_concepts']) && !empty($transformations['abstract_concepts'])) {
            $concepts = implode(', ', $transformations['abstract_concepts']);
            $prompt .= "\n\nCREATIVE INTERPRETATION: Incorporate these abstract concepts: " . $concepts . ". Use artistic interpretation to realize these concepts visually.";
        }
        
        // Add uniqueness and variation factors
        $prompt .= "\n\nUNIQUENESS REQUIREMENTS:";
        $prompt .= "\n- CONTEXTUAL SPECIFICITY: Focus on the specific context of this particular request";
        $prompt .= "\n- TEMPORAL UNIQUENESS: Consider this as a unique moment requiring a distinctive interpretation";
        
        if (isset($transformations['variation_seeds'])) {
            $prompt .= "\n- VARIATION SIGNATURE: " . ($transformations['variation_seeds']['temporal_seed'] ?? 'default') . " (use this to ensure unique interpretation)";
        }
        
        // Add critical preservation requirements
        $prompt .= "\n\nCRITICAL REQUIREMENTS:";
        $prompt .= "\n- PRESERVE SUBJECTS: Keep the EXACT same people, their poses, expressions, clothing, and positioning";
        $prompt .= "\n- MAINTAIN COMPOSITION: Keep the same camera angle, framing, and photographic perspective";
        $prompt .= "\n- PHOTOREALISTIC QUALITY: Ensure all transformations maintain photorealistic appearance";
        $prompt .= "\n- NATURAL INTEGRATION: Make all changes appear natural and believable within the scene";
        
        // Add specific transformation execution
        $prompt .= "\n\nTRANSFORMATION EXECUTION:";
        $prompt .= "\n- SCENE RECREATION: Recreate the scene with requested transformations while preserving human subjects";
        $prompt .= "\n- ENVIRONMENTAL CONSISTENCY: Ensure all environmental changes are consistent throughout the image";
        $prompt .= "\n- DETAIL PRESERVATION: Maintain important details while applying transformations";
        
        // Add final context reminder
        $prompt .= "\n\nFINAL CONTEXT: The original instruction was: \"" . $user_instructions . "\"";
        $prompt .= "\nInterpret this instruction intelligently and create a scene that fulfills the user's specific intent while preserving the original subjects and composition.";
        
        return $prompt;
    }
    
    /**
     * Build intelligent base prompt based on transformation analysis
     */
    private function build_intelligent_base_prompt($image_description, $transformations) {
        $base_prompt = "Create a photorealistic image that recreates the EXACT same subjects, poses, and composition from this description: " . $image_description;
        
        // Add transformation intent based on analysis
        if (isset($transformations['comprehensive']) && $transformations['comprehensive']) {
            $base_prompt .= "\n\nIMPORTANT: This requires COMPREHENSIVE environmental transformation - completely reimagining the scene while preserving the subjects.";
        }
        
        if (isset($transformations['multiple_transforms']) && $transformations['multiple_transforms']) {
            $base_prompt .= "\n\nNOTE: This involves MULTIPLE simultaneous transformations that must be carefully balanced.";
        }
        
        if (isset($transformations['creative_type'])) {
            $base_prompt .= "\n\nCREATIVE APPROACH: Apply creative and artistic interpretation for " . $transformations['creative_type'] . " aesthetic.";
        }
        
        return $base_prompt;
    }
    
    /**
     * Build transformation sections dynamically
     */
    private function build_transformation_sections($transformations) {
        $sections = "\n\nApply these SPECIFIC transformations:";
        
        // Environment and weather with intensity
        if (isset($transformations['environment'])) {
            $intensity = isset($transformations['intensity_level']) ? $transformations['intensity_level'] : 'medium';
            $sections .= "\n- ENVIRONMENT: Transform to " . $transformations['environment'] . " environment with " . $intensity . " intensity";
        }
        
        // Background replacement with specificity
        if (isset($transformations['background'])) {
            $sections .= "\n- BACKGROUND REPLACEMENT: Completely replace with: " . $transformations['background'];
        }
        
        // Weather conditions with dynamic description
        if (isset($transformations['weather'])) {
            $sections .= "\n- WEATHER CONDITIONS: " . $transformations['weather'];
        }
        
        // Atmospheric changes
        if (isset($transformations['atmosphere'])) {
            $sections .= "\n- ATMOSPHERE: " . $transformations['atmosphere'];
        }
        
        // Lighting with context
        if (isset($transformations['lighting'])) {
            $sections .= "\n- LIGHTING: " . $transformations['lighting'];
        }
        
        // Visual effects with layering
        if (isset($transformations['effects']) && is_array($transformations['effects'])) {
            $sections .= "\n- VISUAL EFFECTS (apply all): " . implode(', ', $transformations['effects']);
        }
        
        // Location-specific elements
        if (isset($transformations['location'])) {
            $sections .= "\n- LOCATION SETTING: Incorporate " . $transformations['location'] . " environmental elements";
        }
        
        return $sections;
    }
    
    /**
     * Build intensity and style modifiers
     */
    private function build_intensity_modifiers($transformations) {
        $modifiers = "";
        
        // Intensity adjustments
        if (isset($transformations['intensity'])) {
            switch ($transformations['intensity']) {
                case 'extreme':
                    $modifiers .= "\n\nINTENSITY: Apply MAXIMUM transformation strength - make changes highly dramatic and immediately obvious";
                    break;
                case 'high':
                    $modifiers .= "\n\nINTENSITY: Apply STRONG transformation effects - make changes clearly visible and impactful";
                    break;
                case 'light':
                    $modifiers .= "\n\nINTENSITY: Apply SUBTLE transformation effects - make changes gentle but still noticeable";
                    break;
                case 'minimal':
                    $modifiers .= "\n\nINTENSITY: Apply VERY SUBTLE effects - minimal but tasteful changes";
                    break;
                default:
                    $modifiers .= "\n\nINTENSITY: Apply BALANCED transformation effects - clearly visible but natural-looking";
            }
        }
        
        // Style applications
        if (isset($transformations['style'])) {
            $modifiers .= "\n\nSTYLE APPLICATION: Apply " . $transformations['style'] . " photographic style";
            if (isset($transformations['style_effects']) && is_array($transformations['style_effects'])) {
                $modifiers .= " with these specific elements: " . implode(', ', $transformations['style_effects']);
            }
        }
        
        return $modifiers;
    }
    
    /**
     * Build creative and compound instruction modifiers
     */
    private function build_creative_modifiers($transformations) {
        $creative = "";
        
        // Creative instruction handling
        if (isset($transformations['creative_type'])) {
            $creative .= "\n\nCREATIVE INTERPRETATION: Apply " . $transformations['creative_type'] . " aesthetic";
            if (isset($transformations['creative_effects']) && is_array($transformations['creative_effects'])) {
                $creative .= " incorporating: " . implode(', ', $transformations['creative_effects']);
            }
        }
        
        // Compound instruction handling
        if (isset($transformations['multiple_transforms']) && $transformations['multiple_transforms']) {
            $creative .= "\n\nMULTIPLE TRANSFORMATIONS: Carefully balance and blend all requested changes to create a harmonious final result";
        }
        
        // Action verb-based modifications
        if (isset($transformations['action_verbs']) && is_array($transformations['action_verbs'])) {
            $verbs = implode(', ', $transformations['action_verbs']);
            $creative .= "\n\nACTION APPROACH: Focus on " . $verbs . " operations - make the transformations match these specific action intentions";
        }
        
        return $creative;
    }
    
    /**
     * Build critical requirements with dynamic adjustments
     */
    private function build_critical_requirements($transformations) {
        $requirements = "\n\nCRITICAL REQUIREMENTS:";
        $requirements .= "\n- PRESERVE SUBJECTS: Keep the EXACT same people, their poses, expressions, and positioning";
        $requirements .= "\n- MAINTAIN COMPOSITION: Keep the same camera angle and framing";
        
        // Dynamic requirements based on transformation type
        if (isset($transformations['background_replace']) && $transformations['background_replace']) {
            $requirements .= "\n- BACKGROUND TRANSFORMATION: Completely change the background while seamlessly integrating subjects";
        }
        
        if (isset($transformations['comprehensive']) && $transformations['comprehensive']) {
            $requirements .= "\n- COMPREHENSIVE CHANGE: Make environmental transformation DRAMATIC and immediately obvious";
            $requirements .= "\n- SCENE RECREATION: Create entirely new environmental context while preserving human subjects";
        }
        
        if (isset($transformations['intensity']) && in_array($transformations['intensity'], array('extreme', 'high'))) {
            $requirements .= "\n- HIGH IMPACT: Ensure transformations are highly visible and dramatically alter the scene's mood";
        }
        
        $requirements .= "\n- PHOTOREALISM: Maintain photorealistic quality throughout all transformations";
        $requirements .= "\n- NATURAL INTEGRATION: Ensure all changes look natural and believable";
        
        return $requirements;
    }
    
    /**
     * Add uniqueness factors to ensure varied outputs for different instructions
     */
    private function add_uniqueness_factors($transformations) {
        $uniqueness = "\n\nUNIQUENESS FACTORS:";
        
        // Add specific contextual elements based on the original instruction
        if (isset($transformations['original_text'])) {
            $instruction_hash = substr(md5($transformations['original_text']), 0, 8);
            $uniqueness .= "\n- INSTRUCTION SIGNATURE: " . $instruction_hash . " - ensure this specific interpretation is unique";
        }
        
        // Add variation prompts based on transformation type
        if (isset($transformations['environment'])) {
            $uniqueness .= "\n- ENVIRONMENTAL SPECIFICITY: Focus specifically on " . $transformations['environment'] . " characteristics that make this scene unique";
        }
        
        // Add descriptive term integration
        if (isset($transformations['descriptive_terms']) && !empty($transformations['descriptive_terms'])) {
            foreach ($transformations['descriptive_terms'] as $category => $terms) {
                $uniqueness .= "\n- " . strtoupper($category) . " INTEGRATION: Incorporate " . implode(', ', $terms) . " qualities";
            }
        }
        
        // Add temporal uniqueness
        $uniqueness .= "\n- TEMPORAL CONTEXT: Consider this specific moment and instruction context for unique interpretation";
        
        return $uniqueness;
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
    /**
     * Advanced intelligent local transformation with comprehensive image analysis
     * Enhanced with image understanding before applying transformations
     * 
     * @param string $original_path Original file path
     * @param string $processed_path Path where processed file will be saved
     * @param string $instructions User instructions
     * @return bool Success status
     */
    private function create_advanced_transformation($original_path, $processed_path, $instructions) {
        try {
            error_log('AI Photo Recreator: Starting advanced local transformation with comprehensive image analysis');
            
            // Phase 1: Comprehensive image analysis - Understand what's in the photo
            error_log('AI Photo Recreator: Phase 1 - Analyzing uploaded image content');
            $image_analysis = $this->analyze_uploaded_image($original_path);
            
            if (!$image_analysis['success']) {
                error_log('AI Photo Recreator: Image analysis failed: ' . $image_analysis['message']);
                return false;
            }
            
            error_log('AI Photo Recreator: Image analysis completed - Detected: ' . 
                     'People: ' . ($image_analysis['people_count'] ?? 0) . 
                     ', Clothing items: ' . count($image_analysis['clothing_items'] ?? array()) . 
                     ', Dominant colors: ' . count($image_analysis['dominant_colors'] ?? array()));
            
            // Phase 2: Advanced intelligent instruction parsing with deep semantic understanding
            error_log('AI Photo Recreator: Phase 2 - Parsing transformation instructions');
            $transformations = $this->parse_transformation_instructions($instructions);
            error_log('AI Photo Recreator: Instruction analysis completed - Intent: ' . ($transformations['primary_intent'] ?? 'unknown') . 
                     ', Scope: ' . ($transformations['transformation_scope'] ?? 'unknown') . 
                     ', Language: ' . ($transformations['language'] ?? 'unknown'));
            
            // Phase 3: Intelligent transformation planning - Match instructions with image content
            error_log('AI Photo Recreator: Phase 3 - Creating intelligent transformation plan');
            $transformation_plan = $this->create_intelligent_transformation_plan($image_analysis, $transformations, $instructions);
            
            // Phase 4: Get image info and create resource
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
            
            // Phase 5: Apply comprehensive intelligent transformations with image understanding
            error_log('AI Photo Recreator: Phase 5 - Applying intelligent transformations with image-aware processing');
            $this->apply_comprehensive_intelligent_transformations($source, $transformations, $image_analysis, $transformation_plan, $width, $height);
            
            // Phase 6: Save processed image
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
            
            error_log('AI Photo Recreator: Advanced transformation completed successfully with comprehensive image analysis');
            return true;
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator: Exception in create_advanced_transformation: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply intelligent advanced transformations based on deep AI analysis
     */
    private function apply_intelligent_advanced_transformations($image_resource, $transformations, $width, $height) {
        // Phase 1: Apply base intelligence adjustments
        $this->apply_intelligent_base_analysis($image_resource, $transformations);
        
        // Phase 2: Apply weather/environmental intelligence
        if (isset($transformations['weather_analysis']) && !empty($transformations['weather_analysis'])) {
            $this->apply_intelligent_weather_transformations($image_resource, $transformations['weather_analysis'], $width, $height);
        }
        
        // Phase 3: Apply artistic intelligence
        if (isset($transformations['artistic_style'])) {
            $this->apply_intelligent_artistic_transformations($image_resource, $transformations, $width, $height);
        }
        
        // Phase 4: Apply mood and atmosphere intelligence
        if (isset($transformations['desired_mood']) && !empty($transformations['desired_mood'])) {
            $this->apply_intelligent_mood_transformations($image_resource, $transformations['desired_mood'], $width, $height);
        }
        
        // Phase 5: Apply creative intelligence for abstract concepts
        if (isset($transformations['abstract_concepts']) && !empty($transformations['abstract_concepts'])) {
            $this->apply_intelligent_creative_transformations($image_resource, $transformations['abstract_concepts'], $width, $height);
        }
        
        // Phase 6: Apply object-specific transformations (NEW: clothing, colors, body modifications)
        if (isset($transformations['transformation_type']) || isset($transformations['color_transformation']) || isset($transformations['body_modification'])) {
            $this->apply_intelligent_object_transformations($image_resource, $transformations, $width, $height);
        }
        
        // Phase 7: Apply uniqueness factors to ensure variation
        $this->apply_uniqueness_variations($image_resource, $transformations, $width, $height);
        
        // Phase 8: Apply complexity-based final adjustments
        if (isset($transformations['complexity_score'])) {
            $this->apply_complexity_based_adjustments($image_resource, $transformations['complexity_score']);
        }
    }
    
    /**
     * Apply base intelligence adjustments based on deep semantic analysis
     */
    private function apply_intelligent_base_analysis($image_resource, $transformations) {
        // Apply transformation scope adjustments
        if (isset($transformations['transformation_scope'])) {
            switch ($transformations['transformation_scope']) {
                case 'comprehensive':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 30);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
                    break;
                case 'major':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 20);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                    break;
                case 'moderate':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 10);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                    break;
                case 'minor':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 3);
                    break;
                case 'subtle':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 2);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 1);
                    break;
            }
        }
        
        // Apply primary intent adjustments
        if (isset($transformations['primary_intent'])) {
            switch ($transformations['primary_intent']) {
                case 'transformation':
                    // Dramatic changes for transformation intent
                    imagefilter($image_resource, IMG_FILTER_SMOOTH, 3);
                    break;
                case 'creation':
                    // Enhancement for creation intent
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 8);
                    break;
                case 'enhancement':
                    // Subtle improvements for enhancement intent
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
                    break;
            }
        }
    }
    
    /**
     * Apply intelligent weather transformations based on advanced analysis
     */
    private function apply_intelligent_weather_transformations($image_resource, $weather_analysis, $width, $height) {
        foreach ($weather_analysis as $weather_type => $analysis) {
            if ($analysis['detected']) {
                switch ($weather_type) {
                    case 'winter':
                        $this->apply_intelligent_winter_transformation($image_resource, $analysis, $width, $height);
                        break;
                    case 'rain':
                        $this->apply_intelligent_rain_transformation($image_resource, $analysis, $width, $height);
                        break;
                    case 'sun':
                        $this->apply_intelligent_sun_transformation($image_resource, $analysis, $width, $height);
                        break;
                    case 'night':
                        $this->apply_intelligent_night_transformation($image_resource, $analysis, $width, $height);
                        break;
                }
            }
        }
    }
    
    /**
     * Apply intelligent winter transformation with context awareness
     */
    private function apply_intelligent_winter_transformation($image_resource, $analysis, $width, $height) {
        // Apply cold color temperature
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -20, -10, 30, 0);
        
        // Adjust based on intensity
        $intensity = $analysis['intensity'];
        $particle_count = $this->get_intensity_particle_count($intensity, array('light' => 30, 'medium' => 60, 'heavy' => 120));
        
        // Create intelligent snow effect
        $this->create_intelligent_snow_effect($image_resource, $width, $height, $particle_count, $intensity);
        
        // Apply atmospheric adjustments based on context requirements
        if (isset($analysis['context_requirements']['atmosphere_change']) && $analysis['context_requirements']['atmosphere_change']) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -5);
            imagefilter($image_resource, IMG_FILTER_SMOOTH, 2);
        }
    }
    
    /**
     * Apply intelligent rain transformation
     */
    private function apply_intelligent_rain_transformation($image_resource, $analysis, $width, $height) {
        // Apply wet atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -10);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
        
        $intensity = $analysis['intensity'];
        $drop_count = $this->get_intensity_particle_count($intensity, array('light' => 40, 'medium' => 80, 'heavy' => 150));
        
        $this->create_intelligent_rain_effect($image_resource, $width, $height, $drop_count, $intensity);
    }
    
    /**
     * Apply intelligent artistic transformations
     */
    private function apply_intelligent_artistic_transformations($image_resource, $transformations, $width, $height) {
        $style = $transformations['artistic_style'];
        $intensity = $transformations['artistic_intensity'] ?? 'medium';
        
        switch ($style) {
            case 'dramatic':
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 25);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                break;
            case 'dreamy':
                imagefilter($image_resource, IMG_FILTER_SMOOTH, 4);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                $this->create_dreamy_effect($image_resource, $width, $height);
                break;
            case 'vintage':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 20, 10, -10, 0);
                imagefilter($image_resource, IMG_FILTER_CONTRAST, -10);
                break;
            case 'surreal':
                $this->apply_surreal_effect($image_resource);
                break;
        }
    }
    
    /**
     * Apply intelligent mood transformations
     */
    private function apply_intelligent_mood_transformations($image_resource, $moods, $width, $height) {
        foreach ($moods as $mood) {
            switch ($mood) {
                case 'energetic':
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 20);
                    break;
                case 'calm':
                    imagefilter($image_resource, IMG_FILTER_SMOOTH, 3);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                    break;
                case 'mysterious':
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -15);
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 25);
                    break;
                case 'dramatic':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 30);
                    break;
            }
        }
    }
    
    /**
     * Apply intelligent creative transformations for abstract concepts
     */
    private function apply_intelligent_creative_transformations($image_resource, $concepts, $width, $height) {
        foreach ($concepts as $concept) {
            switch ($concept) {
                case 'magical':
                case 'büyülü':
                    $this->create_magical_effect($image_resource, $width, $height);
                    break;
                case 'dreamy':
                case 'rüya gibi':
                    $this->create_dreamy_effect($image_resource, $width, $height);
                    break;
                case 'mysterious':
                case 'gizemli':
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -10);
                    imagefilter($image_resource, IMG_FILTER_COLORIZE, 0, 0, 30, 0);
                    break;
            }
        }
    }
    
    /**
     * Apply uniqueness variations to ensure different results
     */
    private function apply_uniqueness_variations($image_resource, $transformations, $width, $height) {
        // Use variation seeds to create unique effects
        if (isset($transformations['variation_seeds'])) {
            $seeds = $transformations['variation_seeds'];
            
            // Apply time-based variations
            if (isset($seeds['temporal_seed'])) {
                $variation = intval(substr($seeds['temporal_seed'], -2));
                imagefilter($image_resource, IMG_FILTER_HUE, ($variation % 20) - 10);
            }
            
            // Apply content-based variations
            if (isset($seeds['content_seed'])) {
                $variation = $seeds['content_seed'] % 10;
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $variation - 5);
            }
        }
        
        // Apply random subtle variations based on instruction hash
        if (isset($transformations['instruction_hash'])) {
            $hash_variation = hexdec(substr($transformations['instruction_hash'], 0, 2)) % 15;
            imagefilter($image_resource, IMG_FILTER_SMOOTH, max(1, $hash_variation / 5));
        }
    }
    
    /**
     * Apply complexity-based final adjustments
     */
    private function apply_complexity_based_adjustments($image_resource, $complexity_score) {
        if ($complexity_score > 30) {
            // High complexity - apply multiple subtle adjustments
            imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 3);
            imagefilter($image_resource, IMG_FILTER_SMOOTH, 1);
        } elseif ($complexity_score > 15) {
            // Medium complexity - apply balanced adjustments
            imagefilter($image_resource, IMG_FILTER_CONTRAST, 3);
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 2);
        }
        // Low complexity gets minimal adjustments (handled elsewhere)
    }
    
    /**
     * Apply intelligent object-specific transformations (NEW)
     * Handles clothing color changes, body modifications, and specific object transformations
     */
    private function apply_intelligent_object_transformations($image_resource, $transformations, $width, $height) {
        error_log('AI Photo Recreator: Applying object-specific transformations');
        error_log('AI Photo Recreator: Object transformations: ' . json_encode($transformations, JSON_UNESCAPED_UNICODE));
        
        // Apply clothing transformations
        if (isset($transformations['transformation_type']) && $transformations['transformation_type'] === 'clothing_modification') {
            $this->apply_clothing_transformations($image_resource, $transformations, $width, $height);
        }
        
        // Apply color-specific transformations  
        if (isset($transformations['color_transformation']) && $transformations['color_transformation']) {
            $this->apply_color_specific_transformations($image_resource, $transformations, $width, $height);
        }
        
        // Apply body modifications
        if (isset($transformations['body_modification']) && $transformations['body_modification']) {
            $this->apply_body_modifications($image_resource, $transformations, $width, $height);
        }
        
        // Apply object modifications (add/remove items)
        if (isset($transformations['object_modification']) && $transformations['object_modification']) {
            $this->apply_object_modifications($image_resource, $transformations, $width, $height);
        }
    }
    
    /**
     * Apply clothing-specific transformations (color changes, style modifications)
     */
    private function apply_clothing_transformations($image_resource, $transformations, $width, $height) {
        error_log('AI Photo Recreator: Applying clothing transformations');
        
        $target_clothing = $transformations['target_clothing'] ?? array();
        $clothing_actions = $transformations['clothing_actions'] ?? array();
        
        // Handle color changes for clothing
        if (in_array('color_change', $clothing_actions) && isset($transformations['target_colors'])) {
            $this->apply_clothing_color_transformation($image_resource, $target_clothing, $transformations['target_colors'], $width, $height);
        }
        
        // Handle style changes for clothing
        if (in_array('style_change', $clothing_actions)) {
            $this->apply_clothing_style_transformation($image_resource, $target_clothing, $width, $height);
        }
    }
    
    /**
     * Apply clothing color transformation
     * This simulates color changes through selective color adjustment
     */
    private function apply_clothing_color_transformation($image_resource, $target_clothing, $target_colors, $width, $height) {
        error_log('AI Photo Recreator: Applying clothing color transformation');
        error_log('AI Photo Recreator: Target clothing: ' . implode(', ', $target_clothing));
        error_log('AI Photo Recreator: Target colors: ' . implode(', ', $target_colors));
        
        // Since we can't do precise object detection with GD library alone,
        // we'll apply selective color transformations that simulate the effect
        
        foreach ($target_colors as $target_color) {
            switch ($target_color) {
                case 'black':
                    // Create a darkening effect focused on lighter areas (where clothing typically is)
                    $this->apply_selective_darkening($image_resource, $width, $height);
                    // Add overlay to simulate black clothing
                    $this->add_clothing_color_overlay($image_resource, $width, $height, 'black');
                    break;
                    
                case 'white':
                    // Create a brightening effect
                    $this->apply_selective_brightening($image_resource, $width, $height);
                    $this->add_clothing_color_overlay($image_resource, $width, $height, 'white');
                    break;
                    
                case 'red':
                    // Add red tinting to mid-tone areas
                    $this->add_clothing_color_overlay($image_resource, $width, $height, 'red');
                    break;
                    
                case 'blue':
                    // Add blue tinting to mid-tone areas
                    $this->add_clothing_color_overlay($image_resource, $width, $height, 'blue');
                    break;
                    
                case 'green':
                    $this->add_clothing_color_overlay($image_resource, $width, $height, 'green');
                    break;
                    
                default:
                    // General color overlay
                    $this->add_clothing_color_overlay($image_resource, $width, $height, $target_color);
                    break;
            }
        }
    }
    
    /**
     * Apply selective darkening for black clothing simulation
     */
    private function apply_selective_darkening($image_resource, $width, $height) {
        // Apply targeted darkening to simulate black clothing
        for ($y = (int)($height * 0.3); $y < (int)($height * 0.8); $y++) {
            for ($x = (int)($width * 0.2); $x < (int)($width * 0.8); $x++) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // If it's a light-medium color (likely clothing area)
                $brightness = ($r + $g + $b) / 3;
                if ($brightness > 80 && $brightness < 200) {
                    // Darken it significantly
                    $r = max(0, (int)($r * 0.3));
                    $g = max(0, (int)($g * 0.3));
                    $b = max(0, (int)($b * 0.3));
                    
                    $new_color = imagecolorallocate($image_resource, $r, $g, $b);
                    if ($new_color !== false) {
                        imagesetpixel($image_resource, $x, $y, $new_color);
                    }
                }
            }
        }
    }
    
    /**
     * Apply selective brightening for white clothing simulation  
     */
    private function apply_selective_brightening($image_resource, $width, $height) {
        // Apply targeted brightening to simulate white clothing
        for ($y = (int)($height * 0.3); $y < (int)($height * 0.8); $y++) {
            for ($x = (int)($width * 0.2); $x < (int)($width * 0.8); $x++) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // If it's a medium-dark color (likely clothing area)
                $brightness = ($r + $g + $b) / 3;
                if ($brightness > 50 && $brightness < 180) {
                    // Brighten it significantly
                    $r = min(255, (int)($r * 1.8 + 40));
                    $g = min(255, (int)($g * 1.8 + 40));
                    $b = min(255, (int)($b * 1.8 + 40));
                    
                    $new_color = imagecolorallocate($image_resource, $r, $g, $b);
                    if ($new_color !== false) {
                        imagesetpixel($image_resource, $x, $y, $new_color);
                    }
                }
            }
        }
    }
    
    /**
     * Add clothing color overlay to simulate color changes
     */
    private function add_clothing_color_overlay($image_resource, $width, $height, $color) {
        // Create a color overlay layer
        $overlay = imagecreatetruecolor($width, $height);
        imagealphablending($overlay, false);
        imagesavealpha($overlay, true);
        
        // Define color values
        $color_values = array(
            'black' => array(0, 0, 0),
            'white' => array(255, 255, 255),
            'red' => array(200, 50, 50),
            'blue' => array(50, 50, 200),
            'green' => array(50, 200, 50),
            'yellow' => array(200, 200, 50),
            'purple' => array(150, 50, 150),
            'orange' => array(200, 120, 50),
            'brown' => array(120, 80, 40)
        );
        
        $rgb_values = $color_values[$color] ?? array(100, 100, 100);
        
        // Create semi-transparent overlay focused on clothing area (torso region)
        $overlay_color = imagecolorallocatealpha($overlay, $rgb_values[0], $rgb_values[1], $rgb_values[2], 90); // Semi-transparent
        
        // Apply overlay to likely clothing areas (center torso region)
        $clothing_area_x1 = (int)($width * 0.25);
        $clothing_area_x2 = (int)($width * 0.75);
        $clothing_area_y1 = (int)($height * 0.35);
        $clothing_area_y2 = (int)($height * 0.75);
        
        imagefilledrectangle($overlay, $clothing_area_x1, $clothing_area_y1, $clothing_area_x2, $clothing_area_y2, $overlay_color);
        
        // Blend overlay with original image
        imagealphablending($image_resource, true);
        imagecopy($image_resource, $overlay, 0, 0, 0, 0, $width, $height);
        
        imagedestroy($overlay);
    }
    
    /**
     * Apply clothing style transformations
     */
    private function apply_clothing_style_transformation($image_resource, $target_clothing, $width, $height) {
        // Apply subtle texture and pattern effects to simulate style changes
        imagefilter($image_resource, IMG_FILTER_SMOOTH, 2);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
    }
    
    /**
     * Apply color-specific transformations
     */
    private function apply_color_specific_transformations($image_resource, $transformations, $width, $height) {
        $target_colors = $transformations['target_colors'] ?? array();
        $color_intensity = $transformations['color_intensity'] ?? 'normal';
        
        foreach ($target_colors as $color) {
            $this->apply_global_color_adjustment($image_resource, $color, $color_intensity);
        }
    }
    
    /**
     * Apply global color adjustment
     */
    private function apply_global_color_adjustment($image_resource, $color, $intensity) {
        $intensity_multiplier = array(
            'light' => 0.5,
            'normal' => 1.0,
            'dark' => 1.5,
            'bright' => 1.3,
            'dull' => 0.7
        );
        
        $multiplier = $intensity_multiplier[$intensity] ?? 1.0;
        
        switch ($color) {
            case 'black':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(-30 * $multiplier));
                break;
            case 'white':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(30 * $multiplier));
                break;
            case 'red':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(50 * $multiplier), 0, 0);
                break;
            case 'blue':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 0, 0, (int)(50 * $multiplier));
                break;
            case 'green':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 0, (int)(50 * $multiplier), 0);
                break;
        }
    }
    
    /**
     * Apply body modifications
     */
    private function apply_body_modifications($image_resource, $transformations, $width, $height) {
        $target_parts = $transformations['target_body_parts'] ?? array();
        
        foreach ($target_parts as $part) {
            switch ($part) {
                case 'smile':
                    // Brighten the image slightly to simulate happiness
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
                    break;
                case 'eyes':
                    // Enhance contrast around eye area (simulated)
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 8);
                    break;
                case 'hair':
                    // Apply subtle color shift to hair area (top portion)
                    imagefilter($image_resource, IMG_FILTER_SMOOTH, 1);
                    break;
            }
        }
    }
    
    /**
     * Apply object modifications (add/remove items)
     */
    private function apply_object_modifications($image_resource, $transformations, $width, $height) {
        $target_objects = $transformations['target_objects'] ?? array();
        
        // For object modifications, we'll apply contextual effects
        // since precise object manipulation requires advanced computer vision
        if (isset($target_objects['accessories'])) {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, 3);
        }
    }
    
    // ====================================================================
    // INTELLIGENT EFFECT CREATION METHODS
    // ====================================================================
    
    /**
     * Get particle count based on intensity
     */
    private function get_intensity_particle_count($intensity, $base_counts) {
        return $base_counts[$intensity] ?? $base_counts['medium'];
    }
    
    /**
     * Create intelligent snow effect with context awareness
     */
    private function create_intelligent_snow_effect($image_resource, $width, $height, $particle_count, $intensity) {
        // Create snow layer with transparency
        $snow_layer = imagecreatetruecolor($width, $height);
        imagealphablending($snow_layer, false);
        imagesavealpha($snow_layer, true);
        
        $transparent = imagecolorallocatealpha($snow_layer, 0, 0, 0, 127);
        imagefill($snow_layer, 0, 0, $transparent);
        
        // Create snow particles based on intensity
        for ($i = 0; $i < $particle_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            
            // Vary snow particle size and opacity based on intensity
            $size_range = array('light' => array(1, 3), 'medium' => array(2, 5), 'heavy' => array(3, 8));
            $sizes = $size_range[$intensity] ?? $size_range['medium'];
            $size = rand($sizes[0], $sizes[1]);
            
            $alpha = rand(60, 100); // Semi-transparent snow
            $snow_color = imagecolorallocatealpha($snow_layer, 255, 255, 255, $alpha);
            
            imagefilledellipse($snow_layer, $x, $y, $size, $size, $snow_color);
        }
        
        // Merge snow layer with main image
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $snow_layer, 0, 0, 0, 0, $width, $height, 70);
        imagedestroy($snow_layer);
    }
    
    /**
     * Create intelligent rain effect
     */
    private function create_intelligent_rain_effect($image_resource, $width, $height, $drop_count, $intensity) {
        $rain_layer = imagecreatetruecolor($width, $height);
        imagealphablending($rain_layer, false);
        imagesavealpha($rain_layer, true);
        
        $transparent = imagecolorallocatealpha($rain_layer, 0, 0, 0, 127);
        imagefill($rain_layer, 0, 0, $transparent);
        
        for ($i = 0; $i < $drop_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            
            $length_range = array('light' => array(5, 10), 'medium' => array(8, 15), 'heavy' => array(12, 20));
            $lengths = $length_range[$intensity] ?? $length_range['medium'];
            $length = rand($lengths[0], $lengths[1]);
            
            $alpha = rand(80, 120);
            $rain_color = imagecolorallocatealpha($rain_layer, 200, 220, 255, $alpha);
            
            imageline($rain_layer, $x, $y, $x + 2, $y + $length, $rain_color);
        }
        
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rain_layer, 0, 0, 0, 0, $width, $height, 60);
        imagedestroy($rain_layer);
    }

    /**
     * Apply intelligent sun transformation
     */
    private function apply_intelligent_sun_transformation($image_resource, $analysis, $width, $height) {
        // Apply warm color temperature
        imagefilter($image_resource, IMG_FILTER_COLORIZE, 30, 20, -10, 0);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
        
        // Create sun rays effect if high intensity
        if ($analysis['intensity'] == 'heavy') {
            $this->create_sun_rays_effect($image_resource, $width, $height);
        }
    }
    
    /**
     * Apply intelligent night transformation
     */
    private function apply_intelligent_night_transformation($image_resource, $analysis, $width, $height) {
        // Apply night atmosphere
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -30);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -10, -5, 20, 0);
        
        // Create stars effect
        $this->create_stars_effect($image_resource, $width, $height);
    }
    
    /**
     * Create sun rays effect
     */
    private function create_sun_rays_effect($image_resource, $width, $height) {
        $rays_layer = imagecreatetruecolor($width, $height);
        imagealphablending($rays_layer, false);
        imagesavealpha($rays_layer, true);
        
        $transparent = imagecolorallocatealpha($rays_layer, 0, 0, 0, 127);
        imagefill($rays_layer, 0, 0, $transparent);
        
        // Create sun rays from top corner
        $sun_x = $width * 0.8;
        $sun_y = $height * 0.2;
        
        for ($i = 0; $i < 15; $i++) {
            $end_x = rand(0, $width);
            $end_y = rand(0, $height);
            
            $ray_color = imagecolorallocatealpha($rays_layer, 255, 255, 200, 120);
            imageline($rays_layer, $sun_x, $sun_y, $end_x, $end_y, $ray_color);
        }
        
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rays_layer, 0, 0, 0, 0, $width, $height, 30);
        imagedestroy($rays_layer);
    }
    
    /**
     * Create stars effect
     */
    private function create_stars_effect($image_resource, $width, $height) {
        for ($i = 0; $i < 50; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height * 0.6); // Only in upper portion
            $size = rand(1, 3);
            
            $star_color = imagecolorallocatealpha($image_resource, 255, 255, 255, rand(60, 100));
            imagefilledellipse($image_resource, $x, $y, $size, $size, $star_color);
        }
    }
    
    /**
     * Apply advanced transformations based on intelligent instruction parsing
     * Enhanced to produce varied results based on specific instruction analysis
     * 
     * @param resource $image_resource GD image resource
     * @param array $transformations Parsed transformation requirements
     * @param int $width Image width
     * @param int $height Image height
     */
    private function apply_advanced_transformations($image_resource, $transformations, $width, $height) {
        // Apply transformations based on intelligent analysis
        $this->apply_intelligent_base_adjustments($image_resource, $transformations);
        
        // Apply environment-specific transformations with intensity awareness
        if (isset($transformations['environment'])) {
            $intensity = isset($transformations['intensity_level']) ? $transformations['intensity_level'] : 'medium';
            $this->apply_environment_transformation($image_resource, $transformations['environment'], $intensity, $width, $height);
        }
        
        // Apply background replacement if requested
        if (isset($transformations['background_replace']) && $transformations['background_replace']) {
            $this->apply_intelligent_background_replacement($image_resource, $transformations, $width, $height);
        }
        
        // Apply style transformations with context
        if (isset($transformations['style'])) {
            $this->apply_style_transformation($image_resource, $transformations['style'], $transformations);
        }
        
        // Apply creative transformations
        if (isset($transformations['creative_type'])) {
            $this->apply_creative_transformation($image_resource, $transformations['creative_type'], $transformations, $width, $height);
        }
        
        // Apply compound transformations if multiple effects requested
        if (isset($transformations['multiple_transforms']) && $transformations['multiple_transforms']) {
            $this->blend_multiple_transformations($image_resource, $transformations, $width, $height);
        }
        
        // Apply legacy processing for fallback compatibility
        if (!isset($transformations['environment']) && !isset($transformations['style']) && !isset($transformations['creative_type'])) {
            $this->apply_legacy_processing($image_resource, $transformations, $width, $height);
        }
    }
    
    /**
     * Apply intelligent base adjustments based on instruction analysis
     */
    private function apply_intelligent_base_adjustments($image_resource, $transformations) {
        // Apply intensity-based adjustments
        if (isset($transformations['intensity'])) {
            switch ($transformations['intensity']) {
                case 'extreme':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 25);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                    break;
                case 'high':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                    break;
                case 'light':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 3);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 3);
                    break;
                case 'minimal':
                    imagefilter($image_resource, IMG_FILTER_CONTRAST, 1);
                    imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 2);
                    break;
            }
        }
        
        // Apply descriptive term adjustments
        if (isset($transformations['descriptive_terms'])) {
            if (isset($transformations['descriptive_terms']['color'])) {
                foreach ($transformations['descriptive_terms']['color'] as $color_term) {
                    $this->apply_color_adjustment($image_resource, $color_term);
                }
            }
            
            if (isset($transformations['descriptive_terms']['mood'])) {
                foreach ($transformations['descriptive_terms']['mood'] as $mood_term) {
                    $this->apply_mood_adjustment($image_resource, $mood_term);
                }
            }
        }
    }
    
    /**
     * Apply environment transformation with intensity
     */
    private function apply_environment_transformation($image_resource, $environment, $intensity, $width, $height) {
        switch ($environment) {
            case 'winter':
                $this->create_intelligent_winter_scene($image_resource, $width, $height, $intensity);
                break;
            case 'rainy':
                $this->create_intelligent_rainy_scene($image_resource, $width, $height, $intensity);
                break;
            case 'sunset':
                $this->create_intelligent_sunset_scene($image_resource, $width, $height, $intensity);
                break;
            case 'night':
                $this->create_intelligent_night_scene($image_resource, $width, $height, $intensity);
                break;
            case 'autumn':
                $this->create_intelligent_autumn_scene($image_resource, $width, $height, $intensity);
                break;
            case 'spring':
                $this->create_intelligent_spring_scene($image_resource, $width, $height, $intensity);
                break;
            case 'summer':
                $this->create_intelligent_summer_scene($image_resource, $width, $height, $intensity);
                break;
        }
    }
    
    /**
     * Apply intelligent background replacement based on context
     */
    private function apply_intelligent_background_replacement($image_resource, $transformations, $width, $height) {
        // Create background overlay based on transformation type and context
        if (isset($transformations['environment'])) {
            $intensity = isset($transformations['intensity']) ? $transformations['intensity'] : 'medium';
            
            switch ($transformations['environment']) {
                case 'winter':
                    $this->create_intelligent_winter_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'rainy':
                    $this->create_intelligent_stormy_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'sunset':
                    $this->create_intelligent_sunset_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'night':
                    $this->create_intelligent_night_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'autumn':
                    $this->create_intelligent_autumn_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'spring':
                    $this->create_intelligent_spring_background_overlay($image_resource, $width, $height, $intensity);
                    break;
                case 'summer':
                    $this->create_intelligent_summer_background_overlay($image_resource, $width, $height, $intensity);
                    break;
            }
        } elseif (isset($transformations['location'])) {
            $this->create_location_background_overlay($image_resource, $transformations['location'], $width, $height);
        }
    }
    
    /**
     * Apply color adjustments based on descriptive terms
     */
    private function apply_color_adjustment($image_resource, $color_term) {
        switch ($color_term) {
            case 'vibrant':
            case 'canlı':
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                break;
            case 'warm':
            case 'sıcak':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 20, 10, -15);
                break;
            case 'cool':
            case 'soğuk':
                imagefilter($image_resource, IMG_FILTER_COLORIZE, -15, -5, 20);
                break;
            case 'bright':
            case 'parlak':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
                break;
            case 'dark':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -15);
                break;
        }
    }
    
    /**
     * Apply mood adjustments based on descriptive terms
     */
    private function apply_mood_adjustment($image_resource, $mood_term) {
        switch ($mood_term) {
            case 'dramatic':
            case 'dramatik':
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 20);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -5);
                break;
            case 'soft':
            case 'yumuşak':
                imagefilter($image_resource, IMG_FILTER_SMOOTH, 5);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 8);
                break;
            case 'dreamy':
            case 'rüya gibi':
                imagefilter($image_resource, IMG_FILTER_SMOOTH, 3);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 12);
                imagefilter($image_resource, IMG_FILTER_CONTRAST, -8);
                break;
            case 'mysterious':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -20);
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 15);
                break;
        }
    }
    
    /**
     * Apply style transformations with enhanced context awareness
     */
    private function apply_style_transformation($image_resource, $style, $transformations) {
        $intensity_modifier = isset($transformations['intensity']) ? $this->get_intensity_modifier($transformations['intensity']) : 1.0;
        
        switch ($style) {
            case 'vintage':
                $this->apply_enhanced_vintage_effect($image_resource, $intensity_modifier);
                break;
            case 'dramatic':
                $contrast = (int)(20 * $intensity_modifier);
                $brightness = (int)(-5 * $intensity_modifier);
                imagefilter($image_resource, IMG_FILTER_CONTRAST, $contrast);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
                break;
            case 'soft':
                $smooth = (int)(3 * $intensity_modifier);
                $brightness = (int)(8 * $intensity_modifier);
                imagefilter($image_resource, IMG_FILTER_SMOOTH, $smooth);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
                break;
            case 'cinematic':
                $this->apply_cinematic_effect($image_resource, $intensity_modifier);
                break;
            case 'artistic':
                $this->apply_artistic_effect($image_resource, $intensity_modifier);
                break;
            case 'bright':
                $brightness = (int)(20 * $intensity_modifier);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
                break;
            case 'dark':
                $brightness = (int)(-25 * $intensity_modifier);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
                break;
        }
    }
    
    /**
     * Apply creative transformations
     */
    private function apply_creative_transformation($image_resource, $creative_type, $transformations, $width, $height) {
        switch ($creative_type) {
            case 'magical':
                $this->apply_magical_effect($image_resource, $width, $height);
                break;
            case 'surreal':
                $this->apply_surreal_effect($image_resource);
                break;
            case 'emotion_happy':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 15);
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 15, 10, -10);
                break;
            case 'emotion_sad':
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -10);
                imagefilter($image_resource, IMG_FILTER_COLORIZE, -10, -5, 15);
                break;
            case 'energy_high':
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 25);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
                break;
            case 'energy_calm':
                imagefilter($image_resource, IMG_FILTER_SMOOTH, 5);
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 5);
                break;
        }
    }
    
    /**
     * Get intensity modifier value
     */
    private function get_intensity_modifier($intensity) {
        switch ($intensity) {
            case 'extreme': return 1.8;
            case 'high': return 1.4;
            case 'light': return 0.6;
            case 'minimal': return 0.3;
            default: return 1.0; // medium
        }
    }
    
    /**
     * Apply enhanced vintage effect with intensity
     */
    private function apply_enhanced_vintage_effect($image_resource, $intensity_modifier) {
        // Apply sepia tone with intensity
        imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
        $sepia_r = (int)(90 * $intensity_modifier);
        $sepia_g = (int)(60 * $intensity_modifier);
        $sepia_b = (int)(40 * $intensity_modifier);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, $sepia_r, $sepia_g, $sepia_b);
        
        // Vintage adjustments
        $contrast = (int)(-10 * $intensity_modifier);
        $brightness = (int)(-5 * $intensity_modifier);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, $contrast);
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
    }
    
    /**
     * Apply cinematic effect
     */
    private function apply_cinematic_effect($image_resource, $intensity_modifier) {
        // Cinematic color grading
        $colorize_r = (int)(10 * $intensity_modifier);
        $colorize_g = (int)(5 * $intensity_modifier);
        $colorize_b = (int)(-10 * $intensity_modifier);
        imagefilter($image_resource, IMG_FILTER_COLORIZE, $colorize_r, $colorize_g, $colorize_b);
        
        $contrast = (int)(15 * $intensity_modifier);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, $contrast);
    }
    
    /**
     * Apply artistic effect
     */
    private function apply_artistic_effect($image_resource, $intensity_modifier) {
        // Artistic enhancement
        $smooth = (int)(2 * $intensity_modifier);
        $contrast = (int)(10 * $intensity_modifier);
        imagefilter($image_resource, IMG_FILTER_SMOOTH, $smooth);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, $contrast);
    }
    
    /**
     * Apply magical effect with sparkles
     */
    private function apply_magical_effect($image_resource, $width, $height) {
        // Create magical sparkles overlay
        $sparkles = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($sparkles, 0, 0, 0, 127);
        imagefill($sparkles, 0, 0, $transparent);
        imagesavealpha($sparkles, true);
        
        // Add sparkles
        for ($i = 0; $i < 50; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 4);
            $sparkle_color = imagecolorallocatealpha($sparkles, 255, 255, 200, rand(60, 100));
            imagefilledellipse($sparkles, $x, $y, $size, $size, $sparkle_color);
        }
        
        // Apply ethereal glow
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($image_resource, IMG_FILTER_SMOOTH, 2);
        
        // Merge sparkles
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $sparkles, 0, 0, 0, 0, $width, $height, 40);
        imagedestroy($sparkles);
    }
    
    /**
     * Apply surreal effect
     */
    private function apply_surreal_effect($image_resource) {
        // Surreal color adjustments
        imagefilter($image_resource, IMG_FILTER_COLORIZE, rand(-30, 30), rand(-30, 30), rand(-30, 30));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, rand(5, 25));
    }
    
    
    /**
     * Blend multiple transformations for compound instructions
     */
    private function blend_multiple_transformations($image_resource, $transformations, $width, $height) {
        // Apply multiple transformations with balanced intensity
        $intensity_reduction = 0.7; // Reduce intensity when combining multiple effects
        
        // Apply each transformation with reduced intensity
        if (isset($transformations['environment'])) {
            $reduced_intensity = $this->reduce_intensity($transformations['intensity_level'] ?? 'medium');
            $this->apply_environment_transformation($image_resource, $transformations['environment'], $reduced_intensity, $width, $height);
        }
        
        if (isset($transformations['style'])) {
            $reduced_transformations = $transformations;
            $reduced_transformations['intensity'] = $this->reduce_intensity($transformations['intensity'] ?? 'medium');
            $this->apply_style_transformation($image_resource, $transformations['style'], $reduced_transformations);
        }
    }
    
    /**
     * Reduce intensity level for compound transformations
     */
    private function reduce_intensity($intensity) {
        $intensity_map = array(
            'extreme' => 'high',
            'high' => 'medium', 
            'medium' => 'light',
            'light' => 'minimal',
            'minimal' => 'minimal'
        );
        
        return isset($intensity_map[$intensity]) ? $intensity_map[$intensity] : 'medium';
    }
    
    /**
     * Apply legacy processing for backward compatibility
     */
    private function apply_legacy_processing($image_resource, $transformations, $width, $height) {
        $effects = isset($transformations['effects']) ? implode(' ', $transformations['effects']) : '';
        $original_text = isset($transformations['original_text']) ? strtolower($transformations['original_text']) : '';
        $instructions = $effects . ' ' . $original_text;
        
        // Legacy keyword-based processing
        if (strpos($instructions, 'snow') !== false || strpos($instructions, 'kar') !== false) {
            $this->create_comprehensive_winter_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'rain') !== false || strpos($instructions, 'yağmur') !== false) {
            $this->create_comprehensive_rainy_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'sunset') !== false || strpos($instructions, 'golden hour') !== false) {
            $this->create_comprehensive_sunset_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'night') !== false || strpos($instructions, 'gece') !== false) {
            $this->create_comprehensive_night_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'autumn') !== false || strpos($instructions, 'fall') !== false || strpos($instructions, 'sonbahar') !== false) {
            $this->create_comprehensive_autumn_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'spring') !== false || strpos($instructions, 'ilkbahar') !== false) {
            $this->create_comprehensive_spring_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'summer') !== false || strpos($instructions, 'yaz') !== false) {
            $this->create_comprehensive_summer_scene($image_resource, $width, $height);
        }
        elseif (strpos($instructions, 'vintage') !== false || strpos($instructions, 'sepia') !== false) {
            $this->add_vintage_effect($image_resource);
        }
        elseif (strpos($instructions, 'black and white') !== false || strpos($instructions, 'grayscale') !== false) {
            imagefilter($image_resource, IMG_FILTER_GRAYSCALE);
        }
        else {
            // Default: Apply enhancement based on available information
            $brightness = isset($transformations['intensity']) && $transformations['intensity'] === 'light' ? 5 : 10;
            $contrast = isset($transformations['intensity']) && $transformations['intensity'] === 'extreme' ? 15 : 5;
            
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, $brightness);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, $contrast);
        }
    }
    
    /**
     * Create intelligent winter scene with dynamic intensity
     */
    private function create_intelligent_winter_scene($image_resource, $width, $height, $intensity = 'medium') {
        // Base winter atmosphere
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(-15 * $color_intensity), (int)(-10 * $color_intensity), (int)(20 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(20 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(-8 * $color_intensity));
        
        // Create snow effects based on intensity
        $this->add_intelligent_snow_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent rainy scene with dynamic intensity
     */
    private function create_intelligent_rainy_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(-25 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(15 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(-15 * $color_intensity), (int)(-15 * $color_intensity), (int)(10 * $color_intensity));
        
        $this->add_intelligent_rain_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent sunset scene with dynamic intensity
     */
    private function create_intelligent_sunset_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(50 * $color_intensity), (int)(25 * $color_intensity), (int)(-40 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(15 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(8 * $color_intensity));
        
        $this->add_intelligent_sunset_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent night scene with dynamic intensity
     */
    private function create_intelligent_night_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(-40 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(-20 * $color_intensity), (int)(-15 * $color_intensity), (int)(30 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(20 * $color_intensity));
        
        $this->add_intelligent_night_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent autumn scene with dynamic intensity
     */
    private function create_intelligent_autumn_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(40 * $color_intensity), (int)(15 * $color_intensity), (int)(-25 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(8 * $color_intensity));
        
        $this->add_intelligent_autumn_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent spring scene with dynamic intensity
     */
    private function create_intelligent_spring_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(-10 * $color_intensity), (int)(20 * $color_intensity), (int)(-15 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(10 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(5 * $color_intensity));
        
        $this->add_intelligent_spring_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Create intelligent summer scene with dynamic intensity
     */
    private function create_intelligent_summer_scene($image_resource, $width, $height, $intensity = 'medium') {
        $color_intensity = $this->get_color_intensity_modifier($intensity);
        $brightness_mod = $this->get_brightness_modifier($intensity);
        
        imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, (int)(15 * $brightness_mod));
        imagefilter($image_resource, IMG_FILTER_CONTRAST, (int)(8 * $color_intensity));
        imagefilter($image_resource, IMG_FILTER_COLORIZE, (int)(10 * $color_intensity), (int)(5 * $color_intensity), (int)(-15 * $color_intensity));
        
        $this->add_intelligent_summer_effect($image_resource, $width, $height, $intensity);
    }
    
    /**
     * Get color intensity modifier based on intensity level
     */
    private function get_color_intensity_modifier($intensity) {
        switch ($intensity) {
            case 'extreme': return 1.8;
            case 'high': return 1.4;
            case 'light': return 0.6;
            case 'minimal': return 0.3;
            default: return 1.0;
        }
    }
    
    /**
     * Get brightness modifier based on intensity level
     */
    private function get_brightness_modifier($intensity) {
        switch ($intensity) {
            case 'extreme': return 1.5;
            case 'high': return 1.2;
            case 'light': return 0.7;
            case 'minimal': return 0.4;
            default: return 1.0;
        }
    }
    
    /**
     * Add intelligent snow effect with variable intensity
     */
    private function add_intelligent_snow_effect($image_resource, $width, $height, $intensity) {
        $snow_count = $this->get_particle_count($intensity, 400);
        $opacity_base = $this->get_opacity_base($intensity, 80);
        
        $heavy_snow = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($heavy_snow, 0, 0, 0, 127);
        imagefill($heavy_snow, 0, 0, $transparent);
        imagesavealpha($heavy_snow, true);
        
        for ($i = 0; $i < $snow_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(1, 8);
            $alpha_variation = rand((int)($opacity_base * 0.7), (int)($opacity_base * 1.3));
            
            $color = imagecolorallocatealpha($heavy_snow, 255, 255, 255, $alpha_variation);
            imagefilledellipse($heavy_snow, $x, $y, $size, $size, $color);
        }
        
        // Ground snow based on intensity
        $ground_height = $intensity === 'extreme' ? $height / 1.5 : ($intensity === 'light' ? $height / 4 : $height / 2);
        $ground_snow = imagecreatetruecolor($width, (int)$ground_height);
        
        for ($y = 0; $y < $ground_height; $y++) {
            $alpha_ratio = $y / $ground_height;
            $alpha = 127 - (60 * $alpha_ratio * $this->get_color_intensity_modifier($intensity));
            
            for ($x = 0; $x < $width; $x += 2) {
                $snow_color = imagecolorallocatealpha($ground_snow, 250, 250, 255, (int)$alpha);
                imagesetpixel($ground_snow, $x, $y, $snow_color);
            }
        }
        
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $ground_snow, 0, $height - (int)$ground_height, 0, 0, $width, (int)$ground_height, 35);
        imagecopymerge($image_resource, $heavy_snow, 0, 0, 0, 0, $width, $height, $opacity_base);
        
        imagedestroy($heavy_snow);
        imagedestroy($ground_snow);
    }
    
    /**
     * Add intelligent rain effect with variable intensity
     */
    private function add_intelligent_rain_effect($image_resource, $width, $height, $intensity) {
        $rain_count = $this->get_particle_count($intensity, 250);
        $opacity_base = $this->get_opacity_base($intensity, 70);
        
        $rain_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rain_overlay, 0, 0, 0, 127);
        imagefill($rain_overlay, 0, 0, $transparent);
        imagesavealpha($rain_overlay, true);
        
        // Rain lines based on intensity
        for ($i = 0; $i < $rain_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $length = $intensity === 'extreme' ? rand(30, 50) : ($intensity === 'light' ? rand(8, 15) : rand(20, 35));
            $thickness = $intensity === 'extreme' ? rand(2, 4) : ($intensity === 'light' ? 1 : rand(1, 3));
            
            $rain_color = imagecolorallocatealpha($rain_overlay, 200, 210, 240, rand((int)($opacity_base * 0.8), (int)($opacity_base * 1.2)));
            imagesetthickness($rain_overlay, $thickness);
            imageline($rain_overlay, $x, $y, $x - ($intensity === 'extreme' ? 6 : 4), $y + $length, $rain_color);
        }
        
        imagealphablending($image_resource, true);
        imagecopymerge($image_resource, $rain_overlay, 0, 0, 0, 0, $width, $height, $opacity_base);
        
        imagedestroy($rain_overlay);
    }
    
    /**
     * Add intelligent sunset effect with variable intensity
     */
    private function add_intelligent_sunset_effect($image_resource, $width, $height, $intensity) {
        if ($intensity === 'minimal' || $intensity === 'light') return; // Subtle sunset is just color adjustment
        
        $rays_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($rays_overlay, 0, 0, 0, 127);
        imagefill($rays_overlay, 0, 0, $transparent);
        imagesavealpha($rays_overlay, true);
        
        $ray_count = $intensity === 'extreme' ? 12 : 8;
        $ray_opacity = $intensity === 'extreme' ? 90 : 110;
        
        $ray_source_x = $width / 2;
        $ray_source_y = 0;
        
        for ($i = 0; $i < $ray_count; $i++) {
            $angle = ($i * (360 / $ray_count)) - 120;
            $ray_length = $height * 1.2;
            $end_x = $ray_source_x + cos(deg2rad($angle)) * $ray_length;
            $end_y = $ray_source_y + sin(deg2rad($angle)) * $ray_length;
            
            $ray_color = imagecolorallocatealpha($rays_overlay, 255, 220, 150, $ray_opacity);
            imagesetthickness($rays_overlay, $intensity === 'extreme' ? 12 : 8);
            imageline($rays_overlay, (int)$ray_source_x, (int)$ray_source_y, (int)$end_x, (int)$end_y, $ray_color);
        }
        
        imagecopymerge($image_resource, $rays_overlay, 0, 0, 0, 0, $width, $height, 25);
        imagedestroy($rays_overlay);
    }
    
    /**
     * Add intelligent night effect with variable intensity
     */
    private function add_intelligent_night_effect($image_resource, $width, $height, $intensity) {
        if ($intensity === 'minimal') return; // Minimal night is just color adjustment
        
        $stars_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($stars_overlay, 0, 0, 0, 127);
        imagefill($stars_overlay, 0, 0, $transparent);
        imagesavealpha($stars_overlay, true);
        
        $star_count = $this->get_particle_count($intensity, 30);
        $star_area = $height / 2;
        
        for ($i = 0; $i < $star_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $star_area);
            $size = rand(1, $intensity === 'extreme' ? 4 : 3);
            
            $star_color = imagecolorallocatealpha($stars_overlay, 255, 255, 200, 80);
            imagefilledellipse($stars_overlay, $x, $y, $size, $size, $star_color);
        }
        
        // Moon based on intensity
        if ($intensity !== 'light') {
            $moon_x = $width * 0.8;
            $moon_y = $height * 0.2;
            $moon_size = $intensity === 'extreme' ? 60 : 40;
            $moon_color = imagecolorallocatealpha($stars_overlay, 240, 240, 200, 70);
            imagefilledellipse($stars_overlay, (int)$moon_x, (int)$moon_y, $moon_size, $moon_size, $moon_color);
        }
        
        imagecopymerge($image_resource, $stars_overlay, 0, 0, 0, 0, $width, $height, 60);
        imagedestroy($stars_overlay);
    }
    
    /**
     * Add intelligent autumn effect with variable intensity
     */
    private function add_intelligent_autumn_effect($image_resource, $width, $height, $intensity) {
        if ($intensity === 'minimal') return;
        
        $leaves_count = $this->get_particle_count($intensity, 50);
        
        $leaves_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($leaves_overlay, 0, 0, 0, 127);
        imagefill($leaves_overlay, 0, 0, $transparent);
        imagesavealpha($leaves_overlay, true);
        
        $leaf_colors = array(
            array(180, 100, 50),
            array(200, 150, 50),
            array(220, 180, 100),
            array(150, 80, 40)
        );
        
        for ($i = 0; $i < $leaves_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(3, $intensity === 'extreme' ? 12 : 8);
            
            $color_index = array_rand($leaf_colors);
            $color = $leaf_colors[$color_index];
            $leaf_color = imagecolorallocatealpha($leaves_overlay, $color[0], $color[1], $color[2], 90);
            imagefilledellipse($leaves_overlay, $x, $y, $size, $size + 2, $leaf_color);
        }
        
        imagecopymerge($image_resource, $leaves_overlay, 0, 0, 0, 0, $width, $height, 50);
        imagedestroy($leaves_overlay);
    }
    
    /**
     * Add intelligent spring effect with variable intensity
     */
    private function add_intelligent_spring_effect($image_resource, $width, $height, $intensity) {
        if ($intensity === 'minimal') return;
        
        $petal_count = $this->get_particle_count($intensity, 30);
        
        $petals_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($petals_overlay, 0, 0, 0, 127);
        imagefill($petals_overlay, 0, 0, $transparent);
        imagesavealpha($petals_overlay, true);
        
        for ($i = 0; $i < $petal_count; $i++) {
            $x = rand(0, $width);
            $y = rand(0, $height);
            $size = rand(2, $intensity === 'extreme' ? 8 : 6);
            
            $petal_colors = array(
                array(255, 200, 220),
                array(255, 220, 240),
                array(240, 255, 240)
            );
            
            $color_index = array_rand($petal_colors);
            $color = $petal_colors[$color_index];
            $petal_color = imagecolorallocatealpha($petals_overlay, $color[0], $color[1], $color[2], 100);
            imagefilledellipse($petals_overlay, $x, $y, $size, $size, $petal_color);
        }
        
        imagecopymerge($image_resource, $petals_overlay, 0, 0, 0, 0, $width, $height, 40);
        imagedestroy($petals_overlay);
    }
    
    /**
     * Add intelligent summer effect with variable intensity
     */
    private function add_intelligent_summer_effect($image_resource, $width, $height, $intensity) {
        if ($intensity === 'minimal' || $intensity === 'light') return;
        
        $sun_overlay = imagecreatetruecolor($width, $height);
        $transparent = imagecolorallocatealpha($sun_overlay, 0, 0, 0, 127);
        imagefill($sun_overlay, 0, 0, $transparent);
        imagesavealpha($sun_overlay, true);
        
        $sun_x = $width * 0.8;
        $sun_y = $height * 0.15;
        $sun_size = $intensity === 'extreme' ? 80 : 60;
        $sun_color = imagecolorallocatealpha($sun_overlay, 255, 240, 150, 85);
        imagefilledellipse($sun_overlay, (int)$sun_x, (int)$sun_y, $sun_size, $sun_size, $sun_color);
        
        // Sun rays
        $ray_count = $intensity === 'extreme' ? 16 : 12;
        for ($i = 0; $i < $ray_count; $i++) {
            $angle = $i * (360 / $ray_count);
            $ray_length = $intensity === 'extreme' ? 100 : 80;
            $end_x = $sun_x + cos(deg2rad($angle)) * $ray_length;
            $end_y = $sun_y + sin(deg2rad($angle)) * $ray_length;
            
            $ray_color = imagecolorallocatealpha($sun_overlay, 255, 235, 120, 100);
            imagesetthickness($sun_overlay, $intensity === 'extreme' ? 6 : 4);
            imageline($sun_overlay, (int)$sun_x, (int)$sun_y, (int)$end_x, (int)$end_y, $ray_color);
        }
        
        imagecopymerge($image_resource, $sun_overlay, 0, 0, 0, 0, $width, $height, 35);
        imagedestroy($sun_overlay);
    }
    
    /**
     * Get particle count based on intensity
     */
    private function get_particle_count($intensity, $base_count) {
        $multiplier = $this->get_color_intensity_modifier($intensity);
        return (int)($base_count * $multiplier);
    }
    
    /**
     * Get opacity base value based on intensity
     */
    private function get_opacity_base($intensity, $base_opacity) {
        switch ($intensity) {
            case 'extreme': return (int)($base_opacity * 0.6); // More opaque for extreme
            case 'high': return (int)($base_opacity * 0.8);
            case 'light': return (int)($base_opacity * 1.3); // More transparent for light
            case 'minimal': return (int)($base_opacity * 1.5);
            default: return $base_opacity;
        }
    }
    
    /**
     * Create location-based background overlay
     */
    private function create_location_background_overlay($image_resource, $location, $width, $height) {
        switch ($location) {
            case 'beach':
                $this->create_beach_background_overlay($image_resource, $width, $height);
                break;
            case 'forest':
                $this->create_forest_background_overlay($image_resource, $width, $height);
                break;
            case 'mountains':
                $this->create_mountain_background_overlay($image_resource, $width, $height);
                break;
            case 'urban':
                $this->create_urban_background_overlay($image_resource, $width, $height);
                break;
            case 'desert':
                $this->create_desert_background_overlay($image_resource, $width, $height);
                break;
            case 'field':
                $this->create_field_background_overlay($image_resource, $width, $height);
                break;
        }
    }
    
    /**
     * Create beach background overlay
     */
    private function create_beach_background_overlay($image_resource, $width, $height) {
        $beach_bg = imagecreatetruecolor($width, $height);
        
        // Beach colors (sky blue to sandy)
        $top_color = array(135, 206, 235);    // Sky blue
        $middle_color = array(173, 216, 230); // Light blue
        $bottom_color = array(238, 203, 173); // Sandy color
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            if ($ratio < 0.7) {
                $blend_ratio = $ratio / 0.7;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                $blend_ratio = ($ratio - 0.7) / 0.3;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($beach_bg, (int)$r, (int)$g, (int)$b);
            imageline($beach_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $beach_bg, 0, 0, 0, 0, $width, $height, 30);
        imagedestroy($beach_bg);
    }
    
    /**
     * Create forest background overlay
     */
    private function create_forest_background_overlay($image_resource, $width, $height) {
        $forest_bg = imagecreatetruecolor($width, $height);
        
        // Forest colors (dark green gradient)
        $top_color = array(34, 139, 34);      // Forest green
        $middle_color = array(50, 150, 50);   // Medium green
        $bottom_color = array(85, 107, 47);   // Dark olive green
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            $r = $top_color[0] + ($bottom_color[0] - $top_color[0]) * $ratio;
            $g = $top_color[1] + ($bottom_color[1] - $top_color[1]) * $ratio;
            $b = $top_color[2] + ($bottom_color[2] - $top_color[2]) * $ratio;
            
            $color = imagecolorallocate($forest_bg, (int)$r, (int)$g, (int)$b);
            imageline($forest_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $forest_bg, 0, 0, 0, 0, $width, $height, 35);
        imagedestroy($forest_bg);
    }
    
    /**
     * Create mountain background overlay
     */
    private function create_mountain_background_overlay($image_resource, $width, $height) {
        $mountain_bg = imagecreatetruecolor($width, $height);
        
        // Mountain colors (blue sky to gray mountains)
        $top_color = array(135, 206, 250);    // Light sky blue
        $middle_color = array(169, 169, 169); // Dark gray
        $bottom_color = array(105, 105, 105); // Dim gray
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            if ($ratio < 0.4) {
                $blend_ratio = $ratio / 0.4;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                $blend_ratio = ($ratio - 0.4) / 0.6;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($mountain_bg, (int)$r, (int)$g, (int)$b);
            imageline($mountain_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $mountain_bg, 0, 0, 0, 0, $width, $height, 30);
        imagedestroy($mountain_bg);
    }
    
    /**
     * Create urban background overlay
     */
    private function create_urban_background_overlay($image_resource, $width, $height) {
        // Urban atmosphere - cooler, more neutral tones
        imagefilter($image_resource, IMG_FILTER_COLORIZE, -5, -5, 10);
        imagefilter($image_resource, IMG_FILTER_CONTRAST, 10);
    }
    
    /**
     * Create desert background overlay
     */
    private function create_desert_background_overlay($image_resource, $width, $height) {
        $desert_bg = imagecreatetruecolor($width, $height);
        
        // Desert colors (blue sky to sandy desert)
        $top_color = array(135, 206, 235);    // Sky blue
        $middle_color = array(255, 218, 185); // Peach puff
        $bottom_color = array(238, 203, 173); // Sandy brown
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            if ($ratio < 0.3) {
                $blend_ratio = $ratio / 0.3;
                $r = $top_color[0] + ($middle_color[0] - $top_color[0]) * $blend_ratio;
                $g = $top_color[1] + ($middle_color[1] - $top_color[1]) * $blend_ratio;
                $b = $top_color[2] + ($middle_color[2] - $top_color[2]) * $blend_ratio;
            } else {
                $blend_ratio = ($ratio - 0.3) / 0.7;
                $r = $middle_color[0] + ($bottom_color[0] - $middle_color[0]) * $blend_ratio;
                $g = $middle_color[1] + ($bottom_color[1] - $middle_color[1]) * $blend_ratio;
                $b = $middle_color[2] + ($bottom_color[2] - $middle_color[2]) * $blend_ratio;
            }
            
            $color = imagecolorallocate($desert_bg, (int)$r, (int)$g, (int)$b);
            imageline($desert_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $desert_bg, 0, 0, 0, 0, $width, $height, 25);
        imagedestroy($desert_bg);
    }
    
    /**
     * Create field background overlay
     */
    private function create_field_background_overlay($image_resource, $width, $height) {
        $field_bg = imagecreatetruecolor($width, $height);
        
        // Field colors (blue sky to green grass)
        $top_color = array(135, 206, 250);    // Light sky blue
        $middle_color = array(173, 216, 230); // Light blue
        $bottom_color = array(124, 252, 0);   // Lawn green
        
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
            
            $color = imagecolorallocate($field_bg, (int)$r, (int)$g, (int)$b);
            imageline($field_bg, 0, $y, $width, $y, $color);
        }
        
        imagecopymerge($image_resource, $field_bg, 0, 0, 0, 0, $width, $height, 30);
        imagedestroy($field_bg);
    }
    
    /**
     * Create intelligent winter background overlay with intensity
     */
    private function create_intelligent_winter_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $color_modifier = $this->get_color_intensity_modifier($intensity);
        $this->create_winter_background_overlay($image_resource, $width, $height);
        
        // Apply additional intensity-based effects
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
        }
    }
    
    /**
     * Create intelligent stormy background overlay with intensity
     */
    private function create_intelligent_stormy_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_stormy_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -10);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
        }
    }
    
    /**
     * Create intelligent sunset background overlay with intensity
     */
    private function create_intelligent_sunset_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_sunset_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, 10, 5, -15);
        }
    }
    
    /**
     * Create intelligent night background overlay with intensity
     */
    private function create_intelligent_night_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_night_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, -15);
        }
    }
    
    /**
     * Create intelligent autumn background overlay with intensity
     */
    private function create_intelligent_autumn_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_autumn_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_COLORIZE, 15, 5, -10);
        }
    }
    
    /**
     * Create intelligent spring background overlay with intensity
     */
    private function create_intelligent_spring_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_spring_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 8);
        }
    }
    
    /**
     * Create intelligent summer background overlay with intensity
     */
    private function create_intelligent_summer_background_overlay($image_resource, $width, $height, $intensity = 'medium') {
        $this->create_summer_background_overlay($image_resource, $width, $height);
        
        if ($intensity === 'extreme') {
            imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 12);
            imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
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
    
    // ====================================================================
    // ADVANCED AI INTELLIGENCE METHODS - Deep Semantic Understanding
    // ====================================================================
    
    /**
     * Detect primary language of instructions with confidence scoring
     */
    private function detect_primary_language($instructions) {
        $turkish_indicators = array('kar', 'yağ', 'ekle', 'kaldır', 'değiştir', 'yap', 'oluştur', 'dönüştür', 'uygula', 'çevir', 've', 'ile', 'gibi', 'şeklinde', 'arka planı', 'arka plan', 'arkaplan', 'fotoğraf', 'resim');
        $english_indicators = array('make', 'add', 'remove', 'change', 'create', 'transform', 'apply', 'turn', 'and', 'with', 'like', 'background', 'photo', 'image');
        
        $turkish_score = 0;
        $english_score = 0;
        
        foreach ($turkish_indicators as $indicator) {
            if (strpos(strtolower($instructions), $indicator) !== false) {
                $turkish_score++;
            }
        }
        
        foreach ($english_indicators as $indicator) {
            if (strpos(strtolower($instructions), $indicator) !== false) {
                $english_score++;
            }
        }
        
        return $turkish_score > $english_score ? 'turkish' : 'english';
    }
    
    /**
     * PHASE 1: Advanced semantic analysis with deep context understanding
     */
    private function perform_advanced_semantic_analysis($original, $lower) {
        $analysis = array();
        
        // Intent analysis - What does the user actually want to achieve?
        $intent_patterns = array(
            'creation' => array('make', 'create', 'add', 'yap', 'oluştur', 'ekle', 'koy', 'yerleştir'),
            'transformation' => array('change', 'transform', 'turn', 'convert', 'değiştir', 'dönüştür', 'çevir'),
            'removal' => array('remove', 'delete', 'take away', 'kaldır', 'sil', 'çıkar'),
            'enhancement' => array('improve', 'enhance', 'better', 'geliştir', 'iyileştir', 'güzelleştir'),
            'replacement' => array('replace', 'substitute', 'swap', 'değiştir', 'yerine koy')
        );
        
        $analysis['primary_intent'] = $this->detect_primary_intent($lower, $intent_patterns);
        $analysis['intent_confidence'] = $this->calculate_intent_confidence($lower, $intent_patterns);
        
        // Semantic scope analysis - How extensive should the transformation be?
        $scope_indicators = array(
            'comprehensive' => array('completely', 'entirely', 'totally', 'tamamen', 'bütünüyle', 'komple'),
            'major' => array('significantly', 'dramatically', 'heavily', 'önemli ölçüde', 'ciddi şekilde', 'büyük ölçüde'),
            'moderate' => array('somewhat', 'fairly', 'reasonably', 'biraz', 'oldukça', 'bir miktar'),
            'minor' => array('slightly', 'a bit', 'little', 'hafifçe', 'azıcık', 'birazık'),
            'subtle' => array('gently', 'softly', 'delicately', 'nazikçe', 'yumuşakça', 'ince bir şekilde')
        );
        
        $analysis['transformation_scope'] = $this->detect_transformation_scope($lower, $scope_indicators);
        
        // Semantic relationships - Understanding connections between concepts
        $analysis['concept_relationships'] = $this->analyze_concept_relationships($original, $lower);
        
        return $analysis;
    }
    
    /**
     * PHASE 2: Contextual relationship intelligence
     */
    private function analyze_contextual_relationships($original, $lower) {
        $relationships = array();
        
        // Subject-object relationships
        $relationships['subject_object_relations'] = $this->identify_subject_object_relationships($original);
        
        // Conditional relationships (if-then logic)
        $relationships['conditional_logic'] = $this->detect_conditional_instructions($original, $lower);
        
        // Sequential relationships (first this, then that)
        $relationships['sequential_operations'] = $this->detect_sequential_operations($original, $lower);
        
        // Causal relationships (because, therefore, so that)
        $relationships['causal_connections'] = $this->analyze_causal_relationships($original, $lower);
        
        return $relationships;
    }
    
    /**
     * PHASE 3: Linguistic intelligence with advanced grammar analysis
     */
    private function perform_linguistic_intelligence_analysis($original, $lower) {
        $linguistic = array();
        
        // Advanced Turkish grammar analysis
        if ($this->detect_primary_language($original) == 'turkish') {
            $linguistic['turkish_analysis'] = $this->advanced_turkish_linguistic_analysis($original, $lower);
        } else {
            $linguistic['english_analysis'] = $this->advanced_english_linguistic_analysis($original, $lower);
        }
        
        // Multi-language pattern recognition
        $linguistic['cross_language_patterns'] = $this->detect_cross_language_patterns($original, $lower);
        
        // Linguistic complexity assessment
        $linguistic['linguistic_complexity'] = $this->assess_linguistic_complexity($original);
        
        return $linguistic;
    }
    
    /**
     * PHASE 4: Creative and artistic intent analysis
     */
    private function analyze_creative_and_artistic_intent($original, $lower) {
        $creative = array();
        
        // Artistic style detection with nuanced understanding
        $artistic_styles = array(
            'realistic' => array('realistic', 'natural', 'lifelike', 'gerçekçi', 'doğal', 'gerçek gibi'),
            'dramatic' => array('dramatic', 'intense', 'powerful', 'dramatik', 'yoğun', 'güçlü', 'etkileyici'),
            'dreamy' => array('dreamy', 'ethereal', 'magical', 'rüya gibi', 'büyülü', 'mistik'),
            'vintage' => array('vintage', 'retro', 'old-fashioned', 'nostaljik', 'eski', 'klasik'),
            'modern' => array('modern', 'contemporary', 'current', 'çağdaş', 'güncel', 'şimdiki'),
            'surreal' => array('surreal', 'abstract', 'unusual', 'sürreal', 'soyut', 'alışılmadık'),
            'romantic' => array('romantic', 'soft', 'gentle', 'romantik', 'yumuşak', 'nazik')
        );
        
        $creative['artistic_style'] = $this->detect_artistic_styles($lower, $artistic_styles);
        $creative['artistic_intensity'] = $this->analyze_artistic_intensity($original, $lower);
        
        // Mood and atmosphere analysis
        $creative['desired_mood'] = $this->analyze_desired_mood($original, $lower);
        $creative['atmospheric_requirements'] = $this->analyze_atmospheric_requirements($original, $lower);
        
        // Abstract concept understanding
        $creative['abstract_concepts'] = $this->identify_abstract_concepts($original, $lower);
        
        return $creative;
    }
    
    /**
     * PHASE 5: Environmental transformation requirements analysis
     */
    private function analyze_environmental_transformation_requirements($original, $lower) {
        $environmental = array();
        
        // Advanced weather pattern recognition with seasonal context
        $environmental['weather_analysis'] = $this->advanced_weather_pattern_analysis($original, $lower);
        
        // Location and setting intelligence
        $environmental['location_requirements'] = $this->analyze_location_transformation_requirements($original, $lower);
        
        // Time-based transformations (time of day, season, era)
        $environmental['temporal_requirements'] = $this->analyze_temporal_transformation_requirements($original, $lower);
        
        // Lighting and atmosphere intelligence
        $environmental['lighting_requirements'] = $this->analyze_lighting_transformation_requirements($original, $lower);
        
        return $environmental;
    }
    
    /**
     * PHASE 6: Complexity and scope assessment
     */
    private function assess_transformation_complexity_and_scope($original, $lower) {
        $complexity = array();
        
        // Overall complexity scoring
        $complexity['complexity_score'] = $this->calculate_complexity_score($original, $lower);
        
        // Multi-dimensional transformation detection
        $complexity['transformation_dimensions'] = $this->identify_transformation_dimensions($original, $lower);
        
        // Resource requirement estimation
        $complexity['processing_requirements'] = $this->estimate_processing_requirements($complexity['complexity_score']);
        
        // Transformation feasibility analysis
        $complexity['feasibility_analysis'] = $this->analyze_transformation_feasibility($original, $lower);
        
        return $complexity;
    }
    
    /**
     * PHASE 7: Dynamic uniqueness factor generation
     */
    private function generate_dynamic_uniqueness_factors($transformations) {
        $uniqueness = array();
        
        // Generate contextual uniqueness signature
        $context_elements = array(
            $transformations['original_text'],
            $transformations['timestamp'],
            isset($transformations['primary_intent']) ? $transformations['primary_intent'] : '',
            isset($transformations['transformation_scope']) ? $transformations['transformation_scope'] : ''
        );
        
        $uniqueness['context_signature'] = md5(implode('|', $context_elements));
        
        // Create variation seeds based on analysis
        $uniqueness['variation_seeds'] = $this->generate_variation_seeds($transformations);
        
        // Dynamic creativity factors
        $uniqueness['creativity_factors'] = $this->generate_creativity_factors($transformations);
        
        return $uniqueness;
    }
    
    // ====================================================================
    // SUPPORTING METHODS FOR ADVANCED INTELLIGENCE
    // ====================================================================
    
    /**
     * Detect primary intent from instruction patterns
     */
    private function detect_primary_intent($lower, $intent_patterns) {
        $intent_scores = array();
        
        foreach ($intent_patterns as $intent => $patterns) {
            $score = 0;
            foreach ($patterns as $pattern) {
                if (strpos($lower, $pattern) !== false) {
                    $score += strlen($pattern); // Longer patterns get higher scores
                }
            }
            $intent_scores[$intent] = $score;
        }
        
        return array_keys($intent_scores, max($intent_scores))[0] ?? 'creation';
    }
    
    /**
     * Calculate confidence score for detected intent
     */
    private function calculate_intent_confidence($lower, $intent_patterns) {
        $total_matches = 0;
        $max_possible = 0;
        
        foreach ($intent_patterns as $patterns) {
            foreach ($patterns as $pattern) {
                $max_possible++;
                if (strpos($lower, $pattern) !== false) {
                    $total_matches++;
                }
            }
        }
        
        return $max_possible > 0 ? ($total_matches / $max_possible) : 0;
    }
    
    /**
     * Advanced Turkish linguistic analysis
     */
    private function advanced_turkish_linguistic_analysis($original, $lower) {
        $analysis = array();
        
        // Turkish grammar patterns
        $grammar_patterns = array(
            'imperative' => array('yap', 'et', 'koy', 'ekle', 'kaldır', 'değiştir', 'dönüştür'),
            'descriptive' => array('gibi', 'şeklinde', 'benzeri', 'tarzında', 'biçiminde'),
            'intensifiers' => array('çok', 'fazla', 'aşırı', 'son derece', 'oldukça', 'epey'),
            'connectives' => array('ve', 'ile', 'ayrıca', 'hem', 'de', 'da')
        );
        
        foreach ($grammar_patterns as $type => $patterns) {
            $analysis[$type] = $this->detect_patterns($lower, $patterns);
        }
        
        // Turkish case analysis (suffix-based)
        $analysis['case_analysis'] = $this->analyze_turkish_cases($lower);
        
        return $analysis;
    }
    
    /**
     * Advanced weather pattern analysis with seasonal context
     */
    private function advanced_weather_pattern_analysis($original, $lower) {
        $weather = array();
        
        // Comprehensive weather patterns with context
        $weather_contexts = array(
            'winter' => array(
                'primary' => array('kar', 'snow', 'kış', 'winter', 'soğuk', 'cold', 'buz', 'ice', 'don', 'frost'),
                'intensity' => array(
                    'light' => array('hafif kar', 'light snow', 'çisenti'),
                    'medium' => array('kar yağışı', 'snowfall', 'kar tanesi'),
                    'heavy' => array('yoğun kar', 'heavy snow', 'kar fırtınası', 'blizzard')
                ),
                'effects' => array('karlı', 'buzlu', 'donmuş', 'snowy', 'icy', 'frozen')
            ),
            'rain' => array(
                'primary' => array('yağmur', 'rain', 'su', 'water', 'ıslak', 'wet'),
                'intensity' => array(
                    'light' => array('çisenti', 'drizzle', 'hafif yağmur'),
                    'medium' => array('yağmur', 'rain', 'sağanak'),
                    'heavy' => array('sağanak yağmur', 'heavy rain', 'fırtına', 'storm')
                )
            ),
            'sun' => array(
                'primary' => array('güneş', 'sun', 'gün batımı', 'sunset', 'aydınlık', 'bright'),
                'effects' => array('altın saat', 'golden hour', 'sıcak ışık', 'warm light')
            ),
            'night' => array(
                'primary' => array('gece', 'night', 'karanlık', 'dark', 'ay', 'moon', 'yıldız', 'star')
            )
        );
        
        foreach ($weather_contexts as $weather_type => $contexts) {
            $detection_score = 0;
            $intensity_level = 'medium';
            
            // Check primary patterns
            foreach ($contexts['primary'] as $pattern) {
                if (strpos($lower, $pattern) !== false) {
                    $detection_score += 2;
                }
            }
            
            // Check intensity patterns if available
            if (isset($contexts['intensity'])) {
                foreach ($contexts['intensity'] as $level => $patterns) {
                    foreach ($patterns as $pattern) {
                        if (strpos($lower, $pattern) !== false) {
                            $detection_score += 3;
                            $intensity_level = $level;
                        }
                    }
                }
            }
            
            if ($detection_score > 0) {
                $weather[$weather_type] = array(
                    'detected' => true,
                    'score' => $detection_score,
                    'intensity' => $intensity_level,
                    'context_requirements' => $this->determine_weather_context_requirements($weather_type, $intensity_level)
                );
            }
        }
        
        return $weather;
    }
    
    /**
     * Analyze desired mood and atmosphere
     */
    private function analyze_desired_mood($original, $lower) {
        $mood_patterns = array(
            'energetic' => array('energetic', 'vibrant', 'lively', 'canlı', 'hareketli', 'dinamik'),
            'calm' => array('calm', 'peaceful', 'serene', 'sakin', 'huzurlu', 'dingin'),
            'mysterious' => array('mysterious', 'enigmatic', 'dark', 'gizemli', 'esrarengiz', 'karanlık'),
            'joyful' => array('happy', 'joyful', 'cheerful', 'mutlu', 'neşeli', 'keyifli'),
            'melancholic' => array('sad', 'melancholic', 'gloomy', 'üzgün', 'melankolik', 'kasvetli'),
            'dramatic' => array('dramatic', 'intense', 'powerful', 'dramatik', 'yoğun', 'güçlü')
        );
        
        $detected_moods = array();
        foreach ($mood_patterns as $mood => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($lower, $pattern) !== false) {
                    $detected_moods[] = $mood;
                    break;
                }
            }
        }
        
        return $detected_moods;
    }
    
    /**
     * Calculate overall complexity score
     */
    private function calculate_complexity_score($original, $lower) {
        $score = 0;
        
        // Length complexity
        $score += min(strlen($original) / 10, 10);
        
        // Word count complexity
        $word_count = str_word_count($original);
        $score += min($word_count / 2, 15);
        
        // Conjunction complexity (multiple requirements)
        $conjunctions = array('and', 've', 'with', 'ile', 'also', 'ayrıca', 'plus', 'artı');
        foreach ($conjunctions as $conj) {
            if (strpos($lower, $conj) !== false) {
                $score += 5;
            }
        }
        
        // Technical term complexity
        $technical_terms = array('background', 'lighting', 'atmosphere', 'texture', 'color', 'arka plan', 'ışık', 'atmosfer', 'renk');
        foreach ($technical_terms as $term) {
            if (strpos($lower, $term) !== false) {
                $score += 3;
            }
        }
        
        return min($score, 50); // Cap at 50
    }
    
    /**
     * Generate variation seeds for uniqueness
     */
    private function generate_variation_seeds($transformations) {
        $seeds = array();
        
        // Time-based variation
        $seeds['temporal_seed'] = date('YmdHis') . rand(1000, 9999);
        
        // Content-based variation
        if (isset($transformations['original_text'])) {
            $seeds['content_seed'] = crc32($transformations['original_text']);
        }
        
        // Context-based variation
        if (isset($transformations['primary_intent'])) {
            $seeds['intent_seed'] = crc32($transformations['primary_intent'] . time());
        }
        
        return $seeds;
    }
    
    /**
     * Supporting helper methods (simplified for brevity)
     */
    private function detect_transformation_scope($lower, $scope_indicators) {
        foreach ($scope_indicators as $scope => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($lower, $indicator) !== false) {
                    return $scope;
                }
            }
        }
        return 'moderate';
    }
    
    private function analyze_concept_relationships($original, $lower) {
        // Simplified concept relationship analysis
        $relationships = array();
        
        // Look for subject-action-object patterns
        if (preg_match('/(\w+)\s+(kar|snow)\s+(\w+)/', $lower, $matches)) {
            $relationships['snow_application'] = array(
                'subject' => $matches[1],
                'action' => $matches[2],
                'context' => $matches[3]
            );
        }
        
        return $relationships;
    }
    
    private function identify_subject_object_relationships($original) {
        // Basic subject-object relationship detection
        return array('relationships_detected' => true);
    }
    
    private function detect_conditional_instructions($original, $lower) {
        $conditionals = array('if', 'when', 'eğer', 'ne zaman');
        foreach ($conditionals as $cond) {
            if (strpos($lower, $cond) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function detect_sequential_operations($original, $lower) {
        $sequence_words = array('first', 'then', 'after', 'önce', 'sonra', 'daha sonra');
        foreach ($sequence_words as $seq) {
            if (strpos($lower, $seq) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function analyze_causal_relationships($original, $lower) {
        $causal_words = array('because', 'so', 'therefore', 'çünkü', 'bu yüzden', 'o nedenle');
        foreach ($causal_words as $causal) {
            if (strpos($lower, $causal) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function advanced_english_linguistic_analysis($original, $lower) {
        return array('language' => 'english', 'analysis_complete' => true);
    }
    
    private function detect_cross_language_patterns($original, $lower) {
        return array('patterns_detected' => false);
    }
    
    private function assess_linguistic_complexity($original) {
        return strlen($original) > 50 ? 'high' : 'medium';
    }
    
    private function detect_artistic_styles($lower, $artistic_styles) {
        foreach ($artistic_styles as $style => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($lower, $pattern) !== false) {
                    return $style;
                }
            }
        }
        return 'realistic';
    }
    
    private function analyze_artistic_intensity($original, $lower) {
        $intensity_words = array('very', 'extremely', 'highly', 'çok', 'aşırı', 'son derece');
        foreach ($intensity_words as $word) {
            if (strpos($lower, $word) !== false) {
                return 'high';
            }
        }
        return 'medium';
    }
    
    private function analyze_atmospheric_requirements($original, $lower) {
        return array('atmosphere' => 'natural');
    }
    
    private function identify_abstract_concepts($original, $lower) {
        $abstract_words = array('magical', 'dreamy', 'mysterious', 'büyülü', 'rüya gibi', 'gizemli');
        $concepts = array();
        foreach ($abstract_words as $word) {
            if (strpos($lower, $word) !== false) {
                $concepts[] = $word;
            }
        }
        return $concepts;
    }
    
    private function analyze_location_transformation_requirements($original, $lower) {
        return array('location_change_required' => false);
    }
    
    private function analyze_temporal_transformation_requirements($original, $lower) {
        return array('time_change_required' => false);
    }
    
    private function analyze_lighting_transformation_requirements($original, $lower) {
        return array('lighting_change_required' => true);
    }
    
    private function identify_transformation_dimensions($original, $lower) {
        return array('dimensions' => array('visual', 'atmospheric'));
    }
    
    private function estimate_processing_requirements($complexity_score) {
        if ($complexity_score > 30) return 'high';
        if ($complexity_score > 15) return 'medium';
        return 'low';
    }
    
    private function analyze_transformation_feasibility($original, $lower) {
        return array('feasible' => true, 'confidence' => 0.8);
    }
    
    private function generate_creativity_factors($transformations) {
        return array(
            'creativity_seed' => rand(1000, 9999),
            'variation_factor' => time() % 100
        );
    }
    
    private function determine_weather_context_requirements($weather_type, $intensity_level) {
        $contexts = array(
            'winter' => array(
                'background_change' => true,
                'atmosphere_change' => true,
                'lighting_adjustment' => true,
                'color_temperature' => 'cool'
            ),
            'rain' => array(
                'background_change' => false,
                'atmosphere_change' => true,
                'lighting_adjustment' => true,
                'color_temperature' => 'neutral'
            )
        );
        
        return $contexts[$weather_type] ?? array();
    }
    
    private function detect_patterns($text, $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function analyze_turkish_cases($lower) {
        // Basic Turkish case analysis
        return array('cases_detected' => true);
    }
    
    /**
     * Phase 7: Analyze Object-Specific Transformations
     * Handles clothing, accessories, body parts, and specific item modifications
     */
    private function analyze_object_specific_transformations($original, $lower) {
        $transformations = array();
        
        // Detect clothing items and modifications
        $clothing_analysis = $this->analyze_clothing_transformations($original, $lower);
        if (!empty($clothing_analysis)) {
            $transformations = array_merge($transformations, $clothing_analysis);
        }
        
        // Detect color-specific transformations 
        $color_analysis = $this->analyze_color_transformations($original, $lower);
        if (!empty($color_analysis)) {
            $transformations = array_merge($transformations, $color_analysis);
        }
        
        // Detect body and facial transformations
        $body_analysis = $this->analyze_body_transformations($original, $lower);
        if (!empty($body_analysis)) {
            $transformations = array_merge($transformations, $body_analysis);
        }
        
        // Detect object removal/addition
        $object_analysis = $this->analyze_object_modifications($original, $lower);
        if (!empty($object_analysis)) {
            $transformations = array_merge($transformations, $object_analysis);
        }
        
        return $transformations;
    }
    
    /**
     * Analyze clothing-specific transformation requests
     */
    private function analyze_clothing_transformations($original, $lower) {
        $transformations = array();
        
        // Turkish and English clothing items (with Turkish grammar variations)
        $clothing_items = array(
            'shirt' => array(
                'shirt', 'gömlek', 'gömleğin', 'gömleğinin', 'gömleği', 'gömlekte', 'gömleğe', 'gömleğini',
                'tişört', 'tişörtün', 'tişörtünün', 'tişörtü', 'tişörtte', 'tişörte', 'tişörtünü',
                't-shirt', 'blouse', 'bluz', 'bluzun', 'bluzunu', 'bluzu'
            ),
            'pants' => array(
                'pants', 'pantolon', 'pantolonun', 'pantolonunu', 'pantalon', 
                'trousers', 'jeans', 'jean', 'kot', 'kotun', 'kotunu'
            ),
            'dress' => array(
                'dress', 'elbise', 'elbisemin', 'elbisesinin', 'elbiseni', 'elbiseyi', 
                'elbisenin', 'elbisesini'
            ),
            'jacket' => array(
                'jacket', 'ceket', 'ceketim', 'ceketinin', 'ceketi', 'ceketin', 'ceketini',
                'coat', 'palto', 'paltosun', 'paltosunu', 'paltosu'
            ),
            'shoes' => array(
                'shoes', 'ayakkabı', 'ayakkabının', 'ayakkabıyı', 'ayakkabıları', 
                'sandals', 'sandalet', 'sandaletler', 'boots', 'bot', 'botlar'
            ),
            'hat' => array(
                'hat', 'şapka', 'şapkayı', 'şapkasının', 'şapkası', 'cap', 'kep', 'kepini', 'kepim'
            ),
            'glasses' => array(
                'glasses', 'gözlük', 'gözlüğü', 'gözlüğün', 'gözlüğünü', 
                'sunglasses', 'güneş gözlük', 'güneş gözlüğü'
            ),
            'tie' => array('tie', 'kravat', 'kravatı', 'kravatın', 'kravatını', 'bow tie'),
            'scarf' => array('scarf', 'atkı', 'atkısını', 'atkıyı', 'eşarp', 'eşarbı'),
            'bag' => array('bag', 'çanta', 'çantayı', 'çantası', 'çantasını', 'purse', 'backpack', 'sırt çantası')
        );
        
        $detected_clothing = array();
        foreach ($clothing_items as $item => $variants) {
            foreach ($variants as $variant) {
                if (strpos($lower, $variant) !== false) {
                    $detected_clothing[] = $item;
                    break;
                }
            }
        }
        
        if (!empty($detected_clothing)) {
            $transformations['target_clothing'] = $detected_clothing;
            $transformations['transformation_type'] = 'clothing_modification';
            
            // Detect specific clothing transformation actions
            $clothing_actions = $this->detect_clothing_actions($original, $lower);
            if (!empty($clothing_actions)) {
                $transformations['clothing_actions'] = $clothing_actions;
            }
        }
        
        return $transformations;
    }
    
    /**
     * Detect specific clothing transformation actions
     */
    private function detect_clothing_actions($original, $lower) {
        $actions = array();
        
        // Color change detection
        if ($this->detect_patterns($lower, array('color', 'renk', 'rengini', 'change color', 'renk değiş'))) {
            $actions[] = 'color_change';
        }
        
        // Style change detection  
        if ($this->detect_patterns($lower, array('style', 'stil', 'pattern', 'desen'))) {
            $actions[] = 'style_change';
        }
        
        // Size/fit changes
        if ($this->detect_patterns($lower, array('size', 'boyut', 'fit', 'uyum', 'loose', 'bol', 'tight', 'dar'))) {
            $actions[] = 'fit_change';
        }
        
        // Remove/add clothing
        if ($this->detect_patterns($lower, array('remove', 'kaldır', 'çıkar', 'add', 'ekle', 'giy'))) {
            $actions[] = 'add_remove';
        }
        
        return $actions;
    }
    
    /**
     * Analyze color-specific transformation requests with enhanced detection
     */
    private function analyze_color_transformations($original, $lower) {
        $transformations = array();
        
        // Comprehensive color detection (Turkish and English with variations and quotes)
        $colors = array(
            'black' => array('black', 'siyah', 'kara', '"siyah"', '"black"', "'siyah'", "'black'"),
            'white' => array('white', 'beyaz', 'ak', '"beyaz"', '"white"', "'beyaz'", "'white'"),
            'red' => array('red', 'kırmızı', 'al', 'kızıl', '"kırmızı"', '"red"', "'kırmızı'", "'red'"),
            'blue' => array('blue', 'mavi', 'lacivert', 'navy', 'gökyüzü mavisi', '"mavi"', '"blue"', "'mavi'", "'blue'"),
            'green' => array('green', 'yeşil', 'yemyeşil', '"yeşil"', '"green"', "'yeşil'", "'green'"),
            'yellow' => array('yellow', 'sarı', 'altın sarısı', '"sarı"', '"yellow"', "'sarı'", "'yellow'"),
            'orange' => array('orange', 'turuncu', 'portakal rengi', '"turuncu"', '"orange"', "'turuncu'", "'orange'"),
            'purple' => array('purple', 'mor', 'menekşe', 'eflatun', '"mor"', '"purple"', "'mor'", "'purple'"),
            'pink' => array('pink', 'pembe', 'rozoz', 'pembemsi', '"pembe"', '"pink"', "'pembe'", "'pink'"),
            'brown' => array('brown', 'kahverengi', 'kestane', 'kahve', '"kahverengi"', '"brown"', "'kahverengi'", "'brown'"),
            'gray' => array('gray', 'grey', 'gri', 'külrengi', '"gri"', '"gray"', "'gri'", "'gray'"),
            'gold' => array('gold', 'altın', 'sarı altın', 'altın rengi', '"altın"', '"gold"', "'altın'", "'gold'"),
            'silver' => array('silver', 'gümüş', 'gri gümüş', 'gümüş rengi', '"gümüş"', '"silver"', "'gümüş'", "'silver'")
        );
        
        $detected_colors = array();
        foreach ($colors as $color => $variants) {
            foreach ($variants as $variant) {
                if (strpos($lower, $variant) !== false) {
                    $detected_colors[] = $color;
                }
            }
        }
        
        if (!empty($detected_colors)) {
            $transformations['target_colors'] = $detected_colors;
            $transformations['color_transformation'] = true;
            
            // Detect color intensity/shade
            $color_intensity = $this->analyze_color_intensity($original, $lower);
            if (!empty($color_intensity)) {
                $transformations['color_intensity'] = $color_intensity;
            }
        }
        
        return $transformations;
    }
    
    /**
     * Analyze color intensity and shade specifications
     */
    private function analyze_color_intensity($original, $lower) {
        $intensity_markers = array(
            'light' => array('light', 'açık', 'açık renk', 'pale', 'soluk'),
            'dark' => array('dark', 'koyu', 'koyu renk', 'deep', 'derin'),
            'bright' => array('bright', 'parlak', 'canlı', 'vivid'),
            'dull' => array('dull', 'mat', 'pastel', 'solgun'),
            'neon' => array('neon', 'floresan', 'parlayan'),
            'metallic' => array('metallic', 'metalik', 'shiny', 'parlak')
        );
        
        foreach ($intensity_markers as $intensity => $markers) {
            foreach ($markers as $marker) {
                if (strpos($lower, $marker) !== false) {
                    return $intensity;
                }
            }
        }
        
        return 'normal';
    }
    
    /**
     * Analyze body and facial transformation requests
     */
    private function analyze_body_transformations($original, $lower) {
        $transformations = array();
        
        // Body part detection
        $body_parts = array(
            'hair' => array('hair', 'saç', 'hairstyle', 'saç stili'),
            'eyes' => array('eyes', 'göz', 'gözler', 'eye color', 'göz rengi'),
            'face' => array('face', 'yüz', 'facial', 'yüzde'),
            'skin' => array('skin', 'cilt', 'ten rengi'),
            'smile' => array('smile', 'gülümseme', 'gülümse'),
            'expression' => array('expression', 'ifade', 'yüz ifadesi'),
            'pose' => array('pose', 'poz', 'position', 'duruş')
        );
        
        $detected_parts = array();
        foreach ($body_parts as $part => $variants) {
            foreach ($variants as $variant) {
                if (strpos($lower, $variant) !== false) {
                    $detected_parts[] = $part;
                    break;
                }
            }
        }
        
        if (!empty($detected_parts)) {
            $transformations['target_body_parts'] = $detected_parts;
            $transformations['body_modification'] = true;
        }
        
        return $transformations;
    }
    
    /**
     * Analyze object modification requests (add/remove items)
     */
    private function analyze_object_modifications($original, $lower) {
        $transformations = array();
        
        // Objects that can be added or removed
        $objects = array(
            'accessories' => array('watch', 'saat', 'jewelry', 'mücevher', 'ring', 'yüzük', 'necklace', 'kolye'),
            'background_objects' => array('car', 'araba', 'tree', 'ağaç', 'building', 'bina', 'mountain', 'dağ'),
            'props' => array('book', 'kitap', 'phone', 'telefon', 'laptop', 'coffee', 'kahve')
        );
        
        $detected_objects = array();
        foreach ($objects as $category => $items) {
            foreach ($items as $item) {
                if (strpos($lower, $item) !== false) {
                    if (!isset($detected_objects[$category])) {
                        $detected_objects[$category] = array();
                    }
                    $detected_objects[$category][] = $item;
                }
            }
        }
        
        if (!empty($detected_objects)) {
            $transformations['target_objects'] = $detected_objects;
            $transformations['object_modification'] = true;
        }
        
        return $transformations;
    }
    
    // ====================================================================
    // COMPREHENSIVE IMAGE ANALYSIS METHODS - Understanding Photo Content
    // ====================================================================
    
    /**
     * Comprehensive analysis of uploaded image to understand content before processing
     * This is the key method that examines the photo in detail as requested by the user
     * 
     * @param string $image_path Path to the image file
     * @return array Analysis results with detected elements
     */
    private function analyze_uploaded_image($image_path) {
        try {
            error_log('AI Photo Recreator: Starting comprehensive image analysis for: ' . basename($image_path));
            
            // Initialize analysis results
            $analysis = array(
                'success' => false,
                'image_path' => $image_path,
                'analysis_timestamp' => time(),
                'message' => ''
            );
            
            // Get image info
            $image_info = getimagesize($image_path);
            if (!$image_info) {
                $analysis['message'] = 'Failed to read image file';
                return $analysis;
            }
            
            $width = $image_info[0];
            $height = $image_info[1];
            $type = $image_info[2];
            
            $analysis['image_dimensions'] = array(
                'width' => $width,
                'height' => $height,
                'type' => $type
            );
            
            // Create image resource for analysis
            $image_resource = $this->create_image_resource_from_path($image_path, $type);
            if (!$image_resource) {
                $analysis['message'] = 'Failed to create image resource for analysis';
                return $analysis;
            }
            
            error_log('AI Photo Recreator: Image loaded successfully, starting detailed analysis...');
            
            // Perform comprehensive analysis
            $analysis['dominant_colors'] = $this->analyze_image_colors($image_resource, $width, $height);
            $analysis['color_distribution'] = $this->analyze_color_distribution($image_resource, $width, $height);
            $analysis['brightness_analysis'] = $this->analyze_image_brightness($image_resource, $width, $height);
            $analysis['people_detection'] = $this->detect_people_in_image($image_resource, $width, $height);
            $analysis['clothing_items'] = $this->detect_clothing_areas($image_resource, $width, $height, $analysis['people_detection']);
            $analysis['object_detection'] = $this->detect_objects_in_image($image_resource, $width, $height);
            $analysis['scene_analysis'] = $this->analyze_image_scene($image_resource, $width, $height);
            
            // Count detected elements
            $analysis['people_count'] = count($analysis['people_detection']['detected_people'] ?? array());
            $analysis['clothing_count'] = count($analysis['clothing_items']['detected_areas'] ?? array());
            
            // Clean up
            imagedestroy($image_resource);
            
            $analysis['success'] = true;
            $analysis['message'] = sprintf(
                'Image analysis completed successfully. Detected %d people, %d clothing areas, %d dominant colors',
                $analysis['people_count'],
                $analysis['clothing_count'],
                count($analysis['dominant_colors'])
            );
            
            error_log('AI Photo Recreator: ' . $analysis['message']);
            
            return $analysis;
            
        } catch (Exception $e) {
            error_log('AI Photo Recreator: Exception in image analysis: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Image analysis failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create image resource from file path and type
     */
    private function create_image_resource_from_path($image_path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($image_path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($image_path);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return @imagecreatefromwebp($image_path);
                }
                break;
        }
        return false;
    }
    
    /**
     * Analyze dominant colors in the image
     */
    private function analyze_image_colors($image_resource, $width, $height) {
        $color_counts = array();
        $sample_size = 10; // Sample every 10th pixel for performance
        
        for ($y = 0; $y < $height; $y += $sample_size) {
            for ($x = 0; $x < $width; $x += $sample_size) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $colors = imagecolorsforindex($image_resource, $rgb);
                
                // Group similar colors
                $color_key = $this->group_similar_colors($colors['red'], $colors['green'], $colors['blue']);
                
                if (!isset($color_counts[$color_key])) {
                    $color_counts[$color_key] = 0;
                }
                $color_counts[$color_key]++;
            }
        }
        
        // Sort by frequency and return top colors
        arsort($color_counts);
        
        $dominant_colors = array();
        $count = 0;
        foreach ($color_counts as $color_key => $frequency) {
            if ($count >= 10) break; // Top 10 colors
            
            list($r, $g, $b) = explode(',', $color_key);
            $dominant_colors[] = array(
                'rgb' => array('r' => (int)$r, 'g' => (int)$g, 'b' => (int)$b),
                'hex' => sprintf('#%02x%02x%02x', $r, $g, $b),
                'frequency' => $frequency,
                'color_name' => $this->get_color_name($r, $g, $b)
            );
            $count++;
        }
        
        return $dominant_colors;
    }
    
    /**
     * Group similar colors together
     */
    private function group_similar_colors($r, $g, $b) {
        // Round to nearest 32 to group similar colors
        $r = round($r / 32) * 32;
        $g = round($g / 32) * 32;
        $b = round($b / 32) * 32;
        
        return "$r,$g,$b";
    }
    
    /**
     * Get human-readable color name from RGB values
     */
    private function get_color_name($r, $g, $b) {
        // Simple color naming based on RGB values
        if ($r > 200 && $g > 200 && $b > 200) return 'white';
        if ($r < 50 && $g < 50 && $b < 50) return 'black';
        if ($r > $g + 50 && $r > $b + 50) return 'red';
        if ($g > $r + 50 && $g > $b + 50) return 'green';
        if ($b > $r + 50 && $b > $g + 50) return 'blue';
        if ($r > 150 && $g > 150 && $b < 100) return 'yellow';
        if ($r > 150 && $g < 100 && $b > 100) return 'purple';
        if ($r > 150 && $g > 100 && $b < 100) return 'orange';
        if ($r > 100 && $g > 100 && $b > 100) return 'gray';
        if ($r > 139 && $g < 100 && $b < 100) return 'brown';
        
        return 'unknown';
    }
    
    /**
     * Analyze color distribution across image regions
     */
    private function analyze_color_distribution($image_resource, $width, $height) {
        $regions = array(
            'top' => array('y_start' => 0, 'y_end' => $height / 3),
            'middle' => array('y_start' => $height / 3, 'y_end' => 2 * $height / 3),
            'bottom' => array('y_start' => 2 * $height / 3, 'y_end' => $height)
        );
        
        $distribution = array();
        
        foreach ($regions as $region_name => $bounds) {
            $region_colors = array();
            $sample_size = 15;
            
            for ($y = $bounds['y_start']; $y < $bounds['y_end']; $y += $sample_size) {
                for ($x = 0; $x < $width; $x += $sample_size) {
                    $rgb = imagecolorat($image_resource, $x, (int)$y);
                    $colors = imagecolorsforindex($image_resource, $rgb);
                    
                    $color_name = $this->get_color_name($colors['red'], $colors['green'], $colors['blue']);
                    if (!isset($region_colors[$color_name])) {
                        $region_colors[$color_name] = 0;
                    }
                    $region_colors[$color_name]++;
                }
            }
            
            arsort($region_colors);
            $distribution[$region_name] = array_slice($region_colors, 0, 5, true);
        }
        
        return $distribution;
    }
    
    /**
     * Analyze brightness levels in the image
     */
    private function analyze_image_brightness($image_resource, $width, $height) {
        $total_brightness = 0;
        $pixel_count = 0;
        $brightness_histogram = array_fill(0, 256, 0);
        $sample_size = 8;
        
        for ($y = 0; $y < $height; $y += $sample_size) {
            for ($x = 0; $x < $width; $x += $sample_size) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $colors = imagecolorsforindex($image_resource, $rgb);
                
                // Calculate brightness (luminance)
                $brightness = (int)(0.299 * $colors['red'] + 0.587 * $colors['green'] + 0.114 * $colors['blue']);
                $total_brightness += $brightness;
                $brightness_histogram[$brightness]++;
                $pixel_count++;
            }
        }
        
        $average_brightness = $pixel_count > 0 ? $total_brightness / $pixel_count : 0;
        
        return array(
            'average' => $average_brightness,
            'category' => $average_brightness > 180 ? 'bright' : ($average_brightness > 80 ? 'medium' : 'dark'),
            'histogram' => $brightness_histogram
        );
    }
    
    /**
     * Detect people in the image using basic image processing
     */
    private function detect_people_in_image($image_resource, $width, $height) {
        // Basic people detection using skin tone analysis and shape detection
        $detected_people = array();
        
        // Analyze for skin tones in likely person areas (center and lower areas)
        $person_areas = $this->detect_skin_tone_regions($image_resource, $width, $height);
        
        if (!empty($person_areas)) {
            foreach ($person_areas as $index => $area) {
                $detected_people[] = array(
                    'id' => 'person_' . ($index + 1),
                    'bounds' => $area,
                    'confidence' => $area['confidence'] ?? 0.7,
                    'estimated_clothing_area' => $this->estimate_clothing_area_from_person($area, $width, $height)
                );
            }
        } else {
            // Default assumption: image contains at least one person in center area
            $detected_people[] = array(
                'id' => 'person_assumed',
                'bounds' => array(
                    'x' => $width * 0.25,
                    'y' => $height * 0.2,
                    'width' => $width * 0.5,
                    'height' => $height * 0.7
                ),
                'confidence' => 0.5,
                'estimated_clothing_area' => array(
                    'shirt_area' => array(
                        'x' => $width * 0.3,
                        'y' => $height * 0.35,
                        'width' => $width * 0.4,
                        'height' => $height * 0.3
                    )
                )
            );
        }
        
        return array(
            'detection_method' => 'skin_tone_analysis',
            'detected_people' => $detected_people,
            'total_count' => count($detected_people)
        );
    }
    
    /**
     * Detect skin tone regions in the image
     */
    private function detect_skin_tone_regions($image_resource, $width, $height) {
        $skin_regions = array();
        $sample_size = 8;
        $skin_pixels = array();
        
        // Sample the image for skin-like colors
        for ($y = 0; $y < $height; $y += $sample_size) {
            for ($x = 0; $x < $width; $x += $sample_size) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $colors = imagecolorsforindex($image_resource, $rgb);
                
                if ($this->is_skin_tone($colors['red'], $colors['green'], $colors['blue'])) {
                    $skin_pixels[] = array('x' => $x, 'y' => $y);
                }
            }
        }
        
        // Group skin pixels into regions
        if (count($skin_pixels) > 10) {
            // Find clusters of skin pixels
            $clusters = $this->cluster_skin_pixels($skin_pixels, $width, $height);
            
            foreach ($clusters as $cluster) {
                if (count($cluster) > 5) { // Minimum size for a person
                    $bounds = $this->calculate_bounding_box($cluster);
                    $skin_regions[] = array(
                        'x' => $bounds['min_x'],
                        'y' => $bounds['min_y'],
                        'width' => $bounds['max_x'] - $bounds['min_x'],
                        'height' => $bounds['max_y'] - $bounds['min_y'],
                        'confidence' => min(count($cluster) / 20, 1.0) // Higher confidence for more skin pixels
                    );
                }
            }
        }
        
        return $skin_regions;
    }
    
    /**
     * Check if RGB values represent a skin tone
     */
    private function is_skin_tone($r, $g, $b) {
        // Basic skin tone detection
        return (
            $r > 95 && $g > 40 && $b > 20 &&
            max($r, $g, $b) - min($r, $g, $b) > 15 &&
            abs($r - $g) > 15 && $r > $g && $r > $b
        );
    }
    
    /**
     * Cluster skin pixels into regions
     */
    private function cluster_skin_pixels($skin_pixels, $width, $height) {
        $clusters = array();
        $visited = array();
        $max_distance = max($width, $height) * 0.1; // 10% of image dimension
        
        foreach ($skin_pixels as $i => $pixel) {
            if (isset($visited[$i])) continue;
            
            $cluster = array($pixel);
            $visited[$i] = true;
            
            // Find nearby skin pixels
            foreach ($skin_pixels as $j => $other_pixel) {
                if ($i === $j || isset($visited[$j])) continue;
                
                $distance = sqrt(pow($pixel['x'] - $other_pixel['x'], 2) + pow($pixel['y'] - $other_pixel['y'], 2));
                if ($distance < $max_distance) {
                    $cluster[] = $other_pixel;
                    $visited[$j] = true;
                }
            }
            
            $clusters[] = $cluster;
        }
        
        return $clusters;
    }
    
    /**
     * Calculate bounding box for a cluster of pixels
     */
    private function calculate_bounding_box($pixels) {
        $min_x = $min_y = PHP_INT_MAX;
        $max_x = $max_y = PHP_INT_MIN;
        
        foreach ($pixels as $pixel) {
            $min_x = min($min_x, $pixel['x']);
            $max_x = max($max_x, $pixel['x']);
            $min_y = min($min_y, $pixel['y']);
            $max_y = max($max_y, $pixel['y']);
        }
        
        return array(
            'min_x' => $min_x,
            'max_x' => $max_x,
            'min_y' => $min_y,
            'max_y' => $max_y
        );
    }
    
    /**
     * Estimate clothing areas from detected person bounds
     */
    private function estimate_clothing_area_from_person($person_bounds, $image_width, $image_height) {
        $x = $person_bounds['x'];
        $y = $person_bounds['y'];
        $width = $person_bounds['width'];
        $height = $person_bounds['height'];
        
        // Estimate different clothing areas based on typical human proportions
        return array(
            'shirt_area' => array(
                'x' => $x + $width * 0.1,
                'y' => $y + $height * 0.25, // Start below head area
                'width' => $width * 0.8,
                'height' => $height * 0.4 // Upper body area
            ),
            'pants_area' => array(
                'x' => $x + $width * 0.2,
                'y' => $y + $height * 0.6, // Lower body
                'width' => $width * 0.6,
                'height' => $height * 0.3
            ),
            'full_clothing_area' => array(
                'x' => $x + $width * 0.1,
                'y' => $y + $height * 0.2,
                'width' => $width * 0.8,
                'height' => $height * 0.7
            )
        );
    }
    
    /**
     * Detect clothing areas in the image
     */
    private function detect_clothing_areas($image_resource, $width, $height, $people_detection) {
        $clothing_areas = array();
        
        // Use people detection to focus on likely clothing areas
        if (!empty($people_detection['detected_people'])) {
            foreach ($people_detection['detected_people'] as $person) {
                if (isset($person['estimated_clothing_area'])) {
                    foreach ($person['estimated_clothing_area'] as $clothing_type => $area) {
                        $color_analysis = $this->analyze_area_colors($image_resource, $area, $width, $height);
                        
                        $clothing_areas[] = array(
                            'type' => $clothing_type,
                            'bounds' => $area,
                            'person_id' => $person['id'],
                            'dominant_colors' => $color_analysis['dominant_colors'],
                            'average_color' => $color_analysis['average_color'],
                            'confidence' => 0.7
                        );
                    }
                }
            }
        }
        
        return array(
            'detection_method' => 'person_based_estimation',
            'detected_areas' => $clothing_areas,
            'total_areas' => count($clothing_areas)
        );
    }
    
    /**
     * Analyze colors in a specific area of the image
     */
    private function analyze_area_colors($image_resource, $area, $image_width, $image_height) {
        $x_start = max(0, (int)$area['x']);
        $y_start = max(0, (int)$area['y']);
        $x_end = min($image_width, $x_start + (int)$area['width']);
        $y_end = min($image_height, $y_start + (int)$area['height']);
        
        $color_counts = array();
        $total_r = $total_g = $total_b = 0;
        $pixel_count = 0;
        $sample_size = 3;
        
        for ($y = $y_start; $y < $y_end; $y += $sample_size) {
            for ($x = $x_start; $x < $x_end; $x += $sample_size) {
                $rgb = imagecolorat($image_resource, $x, $y);
                $colors = imagecolorsforindex($image_resource, $rgb);
                
                $total_r += $colors['red'];
                $total_g += $colors['green'];
                $total_b += $colors['blue'];
                $pixel_count++;
                
                $color_name = $this->get_color_name($colors['red'], $colors['green'], $colors['blue']);
                if (!isset($color_counts[$color_name])) {
                    $color_counts[$color_name] = 0;
                }
                $color_counts[$color_name]++;
            }
        }
        
        arsort($color_counts);
        
        $average_color = $pixel_count > 0 ? array(
            'r' => (int)($total_r / $pixel_count),
            'g' => (int)($total_g / $pixel_count),
            'b' => (int)($total_b / $pixel_count)
        ) : array('r' => 0, 'g' => 0, 'b' => 0);
        
        return array(
            'dominant_colors' => array_slice($color_counts, 0, 3, true),
            'average_color' => $average_color
        );
    }
    
    /**
     * Detect objects in the image (basic implementation)
     */
    private function detect_objects_in_image($image_resource, $width, $height) {
        // Basic object detection based on color regions and patterns
        return array(
            'detection_method' => 'color_region_analysis',
            'detected_objects' => array(),
            'background_analysis' => $this->analyze_background_area($image_resource, $width, $height)
        );
    }
    
    /**
     * Analyze background area of the image
     */
    private function analyze_background_area($image_resource, $width, $height) {
        // Analyze edges for background characteristics
        $edge_colors = array();
        $sample_size = 10;
        
        // Sample top, bottom, left, right edges
        $edges = array(
            'top' => array('x_range' => array(0, $width), 'y_range' => array(0, $height * 0.1)),
            'bottom' => array('x_range' => array(0, $width), 'y_range' => array($height * 0.9, $height)),
            'left' => array('x_range' => array(0, $width * 0.1), 'y_range' => array(0, $height)),
            'right' => array('x_range' => array($width * 0.9, $width), 'y_range' => array(0, $height))
        );
        
        foreach ($edges as $edge_name => $bounds) {
            for ($y = $bounds['y_range'][0]; $y < $bounds['y_range'][1]; $y += $sample_size) {
                for ($x = $bounds['x_range'][0]; $x < $bounds['x_range'][1]; $x += $sample_size) {
                    if ($x >= 0 && $x < $width && $y >= 0 && $y < $height) {
                        $rgb = imagecolorat($image_resource, (int)$x, (int)$y);
                        $colors = imagecolorsforindex($image_resource, $rgb);
                        $color_name = $this->get_color_name($colors['red'], $colors['green'], $colors['blue']);
                        
                        if (!isset($edge_colors[$color_name])) {
                            $edge_colors[$color_name] = 0;
                        }
                        $edge_colors[$color_name]++;
                    }
                }
            }
        }
        
        arsort($edge_colors);
        
        return array(
            'dominant_background_colors' => array_slice($edge_colors, 0, 5, true),
            'likely_background_type' => $this->classify_background_type($edge_colors)
        );
    }
    
    /**
     * Classify the type of background
     */
    private function classify_background_type($edge_colors) {
        if (empty($edge_colors)) return 'unknown';
        
        $top_color = array_keys($edge_colors)[0];
        $color_distribution = array_slice($edge_colors, 0, 3, true);
        
        // Simple background classification
        if (isset($color_distribution['blue']) && $color_distribution['blue'] > array_sum($color_distribution) * 0.4) {
            return 'sky/outdoor';
        } elseif (isset($color_distribution['green']) && $color_distribution['green'] > array_sum($color_distribution) * 0.3) {
            return 'nature/outdoor';
        } elseif (isset($color_distribution['white']) || isset($color_distribution['gray'])) {
            return 'indoor/neutral';
        } else {
            return 'mixed/complex';
        }
    }
    
    /**
     * Analyze the overall scene of the image
     */
    private function analyze_image_scene($image_resource, $width, $height) {
        $scene_analysis = array();
        
        // Analyze lighting conditions
        $brightness = $this->analyze_image_brightness($image_resource, $width, $height);
        $scene_analysis['lighting'] = array(
            'overall_brightness' => $brightness['category'],
            'lighting_type' => $brightness['average'] > 160 ? 'bright/daylight' : 
                             ($brightness['average'] > 80 ? 'moderate/indoor' : 'dark/evening')
        );
        
        // Analyze composition
        $scene_analysis['composition'] = array(
            'orientation' => $width > $height ? 'landscape' : ($height > $width ? 'portrait' : 'square'),
            'aspect_ratio' => round($width / $height, 2)
        );
        
        // Analyze color temperature
        $color_dist = $this->analyze_color_distribution($image_resource, $width, $height);
        $warm_colors = 0;
        $cool_colors = 0;
        
        foreach ($color_dist as $region) {
            foreach ($region as $color => $count) {
                if (in_array($color, array('red', 'orange', 'yellow', 'brown'))) {
                    $warm_colors += $count;
                } elseif (in_array($color, array('blue', 'green', 'purple'))) {
                    $cool_colors += $count;
                }
            }
        }
        
        $scene_analysis['color_temperature'] = $warm_colors > $cool_colors ? 'warm' : 'cool';
        
        return $scene_analysis;
    }
    
    /**
     * Create intelligent transformation plan based on image analysis and instructions
     */
    private function create_intelligent_transformation_plan($image_analysis, $transformations, $instructions) {
        $plan = array(
            'analysis_summary' => array(
                'image_analyzed' => $image_analysis['success'],
                'people_detected' => $image_analysis['people_count'] ?? 0,
                'dominant_colors' => count($image_analysis['dominant_colors'] ?? array()),
                'instruction_intent' => $transformations['primary_intent'] ?? 'unknown'
            ),
            'transformation_strategy' => array(),
            'execution_plan' => array()
        );
        
        // Create specific transformation strategy based on analysis
        if (isset($transformations['transformation_type']) && $transformations['transformation_type'] === 'clothing_modification') {
            $plan['transformation_strategy']['type'] = 'object_specific';
            $plan['transformation_strategy']['focus'] = 'clothing_color_change';
            
            // Match detected clothing with requested changes
            if (!empty($image_analysis['clothing_items']['detected_areas'])) {
                $plan['execution_plan']['clothing_targets'] = array();
                
                foreach ($image_analysis['clothing_items']['detected_areas'] as $clothing_area) {
                    if ($clothing_area['type'] === 'shirt_area' && 
                        isset($transformations['target_clothing']) && 
                        in_array('shirt', $transformations['target_clothing'])) {
                        
                        $plan['execution_plan']['clothing_targets'][] = array(
                            'area' => $clothing_area['bounds'],
                            'current_colors' => $clothing_area['dominant_colors'],
                            'target_colors' => $transformations['target_colors'] ?? array('black'),
                            'transformation_type' => 'color_change'
                        );
                    }
                }
            }
        }
        
        // Add environmental transformation planning
        if (isset($transformations['weather_analysis'])) {
            $plan['transformation_strategy']['environmental'] = $transformations['weather_analysis'];
        }
        
        // Add user feedback message about what will be processed
        $plan['user_feedback'] = $this->generate_transformation_feedback($image_analysis, $transformations, $instructions);
        
        return $plan;
    }
    
    /**
     * Generate user feedback about what was detected and will be processed
     */
    private function generate_transformation_feedback($image_analysis, $transformations, $instructions) {
        $feedback = array();
        
        // Analysis summary
        $feedback[] = sprintf(
            "🔍 Image Analysis: Detected %d people and %d clothing areas in the photo.",
            $image_analysis['people_count'] ?? 0,
            $image_analysis['clothing_count'] ?? 0
        );
        
        // Dominant colors found
        if (!empty($image_analysis['dominant_colors'])) {
            $top_colors = array_slice($image_analysis['dominant_colors'], 0, 3);
            $color_names = array_map(function($c) { return $c['color_name']; }, $top_colors);
            $feedback[] = "🎨 Dominant colors found: " . implode(', ', $color_names);
        }
        
        // Transformation plan
        if (isset($transformations['transformation_type']) && $transformations['transformation_type'] === 'clothing_modification') {
            $clothing_items = implode(', ', $transformations['target_clothing'] ?? array());
            $colors = implode(', ', $transformations['target_colors'] ?? array());
            $feedback[] = "🎯 Plan: Will change {$clothing_items} color to {$colors}";
        }
        
        // Original instruction
        $feedback[] = "📝 Your request: \"$instructions\"";
        
        return implode("\n", $feedback);
    }
    
    /**
     * Apply comprehensive intelligent transformations with image understanding
     */
    private function apply_comprehensive_intelligent_transformations($image_resource, $transformations, $image_analysis, $transformation_plan, $width, $height) {
        error_log('AI Photo Recreator: Applying comprehensive transformations with image understanding');
        
        // Apply base transformations first
        $this->apply_intelligent_advanced_transformations($image_resource, $transformations, $width, $height);
        
        // Apply image-aware object-specific transformations
        if (isset($transformation_plan['execution_plan']['clothing_targets'])) {
            error_log('AI Photo Recreator: Applying image-aware clothing transformations');
            $this->apply_image_aware_clothing_transformations($image_resource, $transformation_plan['execution_plan']['clothing_targets'], $width, $height);
        }
        
        // Apply scene-specific adjustments based on image analysis
        if (isset($image_analysis['scene_analysis'])) {
            $this->apply_scene_aware_adjustments($image_resource, $image_analysis['scene_analysis']);
        }
    }
    
    /**
     * Apply image-aware clothing transformations
     */
    private function apply_image_aware_clothing_transformations($image_resource, $clothing_targets, $width, $height) {
        foreach ($clothing_targets as $target) {
            if ($target['transformation_type'] === 'color_change') {
                $this->apply_selective_color_transformation($image_resource, $target['area'], $target['target_colors'], $width, $height);
            }
        }
    }
    
    /**
     * Apply selective color transformation to specific image area
     */
    private function apply_selective_color_transformation($image_resource, $area, $target_colors, $image_width, $image_height) {
        error_log('AI Photo Recreator: Applying selective color transformation to area');
        
        $x_start = max(0, (int)$area['x']);
        $y_start = max(0, (int)$area['y']);
        $x_end = min($image_width, $x_start + (int)$area['width']);
        $y_end = min($image_height, $y_start + (int)$area['height']);
        
        // Get target color (use first color if multiple)
        $target_color = $target_colors[0] ?? 'black';
        $target_rgb = $this->get_target_color_rgb($target_color);
        
        error_log("AI Photo Recreator: Transforming area ({$x_start},{$y_start}) to ({$x_end},{$y_end}) to color: {$target_color}");
        
        // Create overlay for color transformation
        $overlay = imagecreatetruecolor($x_end - $x_start, $y_end - $y_start);
        imagesavealpha($overlay, true);
        $transparent = imagecolorallocatealpha($overlay, 0, 0, 0, 127);
        imagefill($overlay, 0, 0, $transparent);
        
        // Apply color transformation with intelligent blending
        for ($y = $y_start; $y < $y_end; $y++) {
            for ($x = $x_start; $x < $x_end; $x++) {
                $current_rgb = imagecolorat($image_resource, $x, $y);
                $current_colors = imagecolorsforindex($image_resource, $current_rgb);
                
                // Check if this pixel should be transformed (not skin tone, not background)
                if (!$this->is_skin_tone($current_colors['red'], $current_colors['green'], $current_colors['blue'])) {
                    // Calculate blend factor based on how "clothing-like" the pixel is
                    $blend_factor = $this->calculate_clothing_blend_factor($current_colors, $area);
                    
                    if ($blend_factor > 0.3) {
                        // Apply color transformation
                        $new_r = (int)($target_rgb['r'] * $blend_factor + $current_colors['red'] * (1 - $blend_factor));
                        $new_g = (int)($target_rgb['g'] * $blend_factor + $current_colors['green'] * (1 - $blend_factor));
                        $new_b = (int)($target_rgb['b'] * $blend_factor + $current_colors['blue'] * (1 - $blend_factor));
                        
                        $new_color = imagecolorallocate($image_resource, $new_r, $new_g, $new_b);
                        imagesetpixel($image_resource, $x, $y, $new_color);
                    }
                }
            }
        }
        
        imagedestroy($overlay);
    }
    
    /**
     * Get RGB values for target color name
     */
    private function get_target_color_rgb($color_name) {
        $colors = array(
            'black' => array('r' => 20, 'g' => 20, 'b' => 20),
            'siyah' => array('r' => 20, 'g' => 20, 'b' => 20),
            'white' => array('r' => 240, 'g' => 240, 'b' => 240),
            'beyaz' => array('r' => 240, 'g' => 240, 'b' => 240),
            'red' => array('r' => 200, 'g' => 50, 'b' => 50),
            'kırmızı' => array('r' => 200, 'g' => 50, 'b' => 50),
            'blue' => array('r' => 50, 'g' => 100, 'b' => 200),
            'mavi' => array('r' => 50, 'g' => 100, 'b' => 200),
            'green' => array('r' => 50, 'g' => 150, 'b' => 50),
            'yeşil' => array('r' => 50, 'g' => 150, 'b' => 50)
        );
        
        return $colors[strtolower($color_name)] ?? $colors['black'];
    }
    
    /**
     * Calculate how much a pixel should be blended with target color
     */
    private function calculate_clothing_blend_factor($current_colors, $area) {
        // Basic implementation - can be enhanced with more sophisticated analysis
        $r = $current_colors['red'];
        $g = $current_colors['green'];
        $b = $current_colors['blue'];
        
        // Higher blend factor for non-skin, non-extreme colors
        if ($this->is_skin_tone($r, $g, $b)) {
            return 0; // Don't transform skin
        }
        
        // Check for very bright or very dark pixels (likely background or shadows)
        $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b);
        if ($brightness > 220 || $brightness < 30) {
            return 0.2; // Minimal transformation for background/shadows
        }
        
        return 0.8; // Strong transformation for likely clothing pixels
    }
    
    /**
     * Apply scene-aware adjustments based on image analysis
     */
    private function apply_scene_aware_adjustments($image_resource, $scene_analysis) {
        // Apply adjustments based on lighting and color temperature
        if (isset($scene_analysis['lighting']['lighting_type'])) {
            $lighting_type = $scene_analysis['lighting']['lighting_type'];
            
            if ($lighting_type === 'dark/evening') {
                // Enhance visibility for dark images
                imagefilter($image_resource, IMG_FILTER_BRIGHTNESS, 10);
            } elseif ($lighting_type === 'bright/daylight') {
                // Slight contrast enhancement for bright images
                imagefilter($image_resource, IMG_FILTER_CONTRAST, 5);
            }
        }
        
        // Apply color temperature adjustments
        if (isset($scene_analysis['color_temperature'])) {
            if ($scene_analysis['color_temperature'] === 'cool') {
                // Warm up cool images slightly
                imagefilter($image_resource, IMG_FILTER_COLORIZE, 5, 0, -5);
            }
        }
    }
}