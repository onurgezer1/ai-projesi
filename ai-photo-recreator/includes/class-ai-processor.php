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
     * Advanced intelligent instruction parser with contextual understanding
     * Supports varied instruction styles, compound commands, and intensity analysis
     * 
     * @param string $instructions User instructions
     * @return array Comprehensive transformation requirements
     */
    private function parse_transformation_instructions($instructions) {
        $original_instructions = trim($instructions);
        $instructions = strtolower($original_instructions);
        $transformations = array();
        
        // Initialize instruction analysis
        $transformations['original_text'] = $original_instructions;
        $transformations['intensity'] = $this->analyze_instruction_intensity($instructions);
        $transformations['action_verbs'] = $this->extract_action_verbs($instructions);
        $transformations['descriptive_terms'] = $this->extract_descriptive_terms($instructions);
        
        // Advanced background detection (Turkish and English with more variations)
        $background_patterns = array(
            'arka plan', 'arkaplan', 'background', 'backdrop', 'sahne', 'scene',
            'arka planı değiştir', 'arka planı kaldır', 'change background', 'remove background',
            'replace background', 'new background', 'different background', 'background change',
            'zemin', 'ground', 'setting', 'environment', 'çevre', 'ortam'
        );
        
        foreach ($background_patterns as $pattern) {
            if (strpos($instructions, $pattern) !== false) {
                $transformations['background_replace'] = true;
                break;
            }
        }
        
        // Advanced weather and environmental transformations with dynamic intensity
        $weather_environments = $this->detect_weather_environments($instructions);
        if (!empty($weather_environments)) {
            $transformations = array_merge($transformations, $weather_environments);
        }
        
        // Advanced seasonal and location transformations
        $seasonal_locations = $this->detect_seasonal_and_location_transforms($instructions);
        if (!empty($seasonal_locations)) {
            $transformations = array_merge($transformations, $seasonal_locations);
        }
        
        // Advanced style transformations with context awareness
        $style_transforms = $this->detect_style_transformations($instructions);
        if (!empty($style_transforms)) {
            $transformations = array_merge($transformations, $style_transforms);
        }
        
        // Compound instruction detection (multiple transformations)
        $compound_transforms = $this->detect_compound_instructions($instructions);
        if (!empty($compound_transforms)) {
            $transformations = array_merge($transformations, $compound_transforms);
        }
        
        // Creative and abstract instruction handling
        $creative_transforms = $this->handle_creative_instructions($instructions);
        if (!empty($creative_transforms)) {
            $transformations = array_merge($transformations, $creative_transforms);
        }
        
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
    }

    /**
     * Create intelligent and comprehensive prompt for DALL-E based on image analysis and user instructions
     * Enhanced to produce unique outputs for different instruction variations
     * 
     * @param string $image_description Description from Vision API
     * @param string $user_instructions User's transformation instructions
     * @return string Comprehensive prompt for DALL-E
     */
    private function create_transformation_prompt($image_description, $user_instructions) {
        // Parse instructions to understand what transformations are needed
        $transformations = $this->parse_transformation_instructions($user_instructions);
        
        // Create intelligent prompt based on instruction analysis
        $prompt = $this->build_intelligent_base_prompt($image_description, $transformations);
        
        // Add specific transformations based on analysis
        $prompt .= $this->build_transformation_sections($transformations);
        
        // Add intensity and style modifiers
        $prompt .= $this->build_intensity_modifiers($transformations);
        
        // Add creative and compound instruction handling
        $prompt .= $this->build_creative_modifiers($transformations);
        
        // Add critical requirements with dynamic adjustments
        $prompt .= $this->build_critical_requirements($transformations);
        
        // Add uniqueness elements to ensure varied outputs
        $prompt .= $this->add_uniqueness_factors($transformations);
        
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
}