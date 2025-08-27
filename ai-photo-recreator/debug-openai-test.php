<?php
/**
 * OpenAI API Debug Test Script
 * Place this file in your WordPress root directory and access it via browser
 * to test your OpenAI API key configuration
 */

// WordPress integration
if (file_exists('./wp-config.php')) {
    require_once('./wp-config.php');
    require_once('./wp-includes/wp-db.php');
    require_once('./wp-includes/functions.php');
    require_once('./wp-includes/option.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>AI Photo Recreator - OpenAI API Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; background: #f0f8ff; padding: 15px; border-left: 4px solid green; }
        .error { color: red; background: #fff0f0; padding: 15px; border-left: 4px solid red; }
        .info { color: blue; background: #f0f0ff; padding: 15px; border-left: 4px solid blue; }
        .warning { color: orange; background: #fff8f0; padding: 15px; border-left: 4px solid orange; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .test-section { margin: 20px 0; border: 1px solid #ddd; padding: 20px; }
    </style>
</head>
<body>
    <h1>AI Photo Recreator - OpenAI API Debug Test</h1>
    
    <?php
    // Test 1: Check if WordPress is loaded
    echo '<div class="test-section">';
    echo '<h2>Test 1: WordPress Integration</h2>';
    if (function_exists('get_option')) {
        echo '<div class="success">✓ WordPress functions are available</div>';
    } else {
        echo '<div class="error">✗ WordPress functions not available. Make sure this file is in your WordPress root directory.</div>';
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';
    
    // Test 2: Check plugin options
    echo '<div class="test-section">';
    echo '<h2>Test 2: Plugin Configuration</h2>';
    $options = get_option('ai_photo_recreator_options', array());
    if (!empty($options)) {
        echo '<div class="success">✓ Plugin options found</div>';
        echo '<div class="info">Settings: <pre>' . print_r($options, true) . '</pre></div>';
    } else {
        echo '<div class="warning">⚠ Plugin options not found. Plugin may not be activated or configured.</div>';
    }
    echo '</div>';
    
    // Test 3: Check API key
    echo '<div class="test-section">';
    echo '<h2>Test 3: API Key Configuration</h2>';
    $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
    if (!empty($api_key)) {
        echo '<div class="success">✓ API key is configured</div>';
        echo '<div class="info">API key length: ' . strlen($api_key) . ' characters</div>';
        echo '<div class="info">API key starts with: ' . substr($api_key, 0, 7) . '...</div>';
    } else {
        echo '<div class="error">✗ No API key configured. Please set your OpenAI API key in WordPress admin → AI Photo Recreator → Settings</div>';
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';
    
    // Test 4: Test OpenAI API connection
    echo '<div class="test-section">';
    echo '<h2>Test 4: OpenAI API Connection</h2>';
    
    $test_api_url = 'https://api.openai.com/v1/models';
    $test_headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );
    
    $test_args = array(
        'timeout' => 30,
        'headers' => $test_headers,
        'method' => 'GET'
    );
    
    $test_response = wp_remote_request($test_api_url, $test_args);
    
    if (is_wp_error($test_response)) {
        echo '<div class="error">✗ Connection error: ' . $test_response->get_error_message() . '</div>';
    } else {
        $response_code = wp_remote_retrieve_response_code($test_response);
        $response_body = wp_remote_retrieve_body($test_response);
        
        if ($response_code === 200) {
            echo '<div class="success">✓ Successfully connected to OpenAI API</div>';
            $models_data = json_decode($response_body, true);
            if (isset($models_data['data']) && is_array($models_data['data'])) {
                echo '<div class="info">Available models count: ' . count($models_data['data']) . '</div>';
                
                // Check for required models
                $required_models = array('gpt-4o', 'dall-e-3');
                $available_models = array_column($models_data['data'], 'id');
                
                foreach ($required_models as $model) {
                    if (in_array($model, $available_models)) {
                        echo '<div class="success">✓ Model available: ' . $model . '</div>';
                    } else {
                        echo '<div class="warning">⚠ Model not found: ' . $model . '</div>';
                    }
                }
            }
        } else {
            echo '<div class="error">✗ API error (Code: ' . $response_code . ')</div>';
            echo '<div class="error">Response: ' . $response_body . '</div>';
        }
    }
    echo '</div>';
    
    // Test 5: Test DALL-E API with a simple prompt
    if ($response_code === 200) {
        echo '<div class="test-section">';
        echo '<h2>Test 5: DALL-E API Test</h2>';
        
        $dalle_url = 'https://api.openai.com/v1/images/generations';
        $dalle_body = array(
            'model' => 'dall-e-3',
            'prompt' => 'A simple test image: a red apple on a white table',
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
            'quality' => 'standard'
        );
        
        $dalle_args = array(
            'timeout' => 60,
            'headers' => $test_headers,
            'body' => json_encode($dalle_body),
            'method' => 'POST'
        );
        
        echo '<div class="info">Testing DALL-E with a simple prompt...</div>';
        $dalle_response = wp_remote_request($dalle_url, $dalle_args);
        
        if (is_wp_error($dalle_response)) {
            echo '<div class="error">✗ DALL-E connection error: ' . $dalle_response->get_error_message() . '</div>';
        } else {
            $dalle_code = wp_remote_retrieve_response_code($dalle_response);
            $dalle_body_response = wp_remote_retrieve_body($dalle_response);
            
            if ($dalle_code === 200) {
                echo '<div class="success">✓ DALL-E API is working correctly</div>';
                $dalle_data = json_decode($dalle_body_response, true);
                if (isset($dalle_data['data'][0]['url'])) {
                    echo '<div class="success">✓ Test image generated successfully</div>';
                    echo '<div class="info">Test image URL: <a href="' . $dalle_data['data'][0]['url'] . '" target="_blank">View Test Image</a></div>';
                }
            } else {
                echo '<div class="error">✗ DALL-E API error (Code: ' . $dalle_code . ')</div>';
                echo '<div class="error">Response: ' . $dalle_body_response . '</div>';
            }
        }
        echo '</div>';
    }
    
    // Test 6: Test Vision API
    if ($response_code === 200) {
        echo '<div class="test-section">';
        echo '<h2>Test 6: Vision API Test</h2>';
        
        // Create a simple test image (red square)
        $test_image = imagecreate(100, 100);
        $red = imagecolorallocate($test_image, 255, 0, 0);
        imagefill($test_image, 0, 0, $red);
        
        ob_start();
        imagepng($test_image);
        $image_data = ob_get_contents();
        ob_end_clean();
        imagedestroy($test_image);
        
        $base64_image = base64_encode($image_data);
        
        $vision_url = 'https://api.openai.com/v1/chat/completions';
        $vision_body = array(
            'model' => 'gpt-4o',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => 'What do you see in this image? Describe it briefly.'
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => 'data:image/png;base64,' . $base64_image
                            )
                        )
                    )
                )
            ),
            'max_tokens' => 100
        );
        
        $vision_args = array(
            'timeout' => 60,
            'headers' => $test_headers,
            'body' => json_encode($vision_body),
            'method' => 'POST'
        );
        
        echo '<div class="info">Testing Vision API with a simple image...</div>';
        $vision_response = wp_remote_request($vision_url, $vision_args);
        
        if (is_wp_error($vision_response)) {
            echo '<div class="error">✗ Vision API connection error: ' . $vision_response->get_error_message() . '</div>';
        } else {
            $vision_code = wp_remote_retrieve_response_code($vision_response);
            $vision_body_response = wp_remote_retrieve_body($vision_response);
            
            if ($vision_code === 200) {
                echo '<div class="success">✓ Vision API is working correctly</div>';
                $vision_data = json_decode($vision_body_response, true);
                if (isset($vision_data['choices'][0]['message']['content'])) {
                    echo '<div class="info">Vision API response: ' . $vision_data['choices'][0]['message']['content'] . '</div>';
                }
            } else {
                echo '<div class="error">✗ Vision API error (Code: ' . $vision_code . ')</div>';
                echo '<div class="error">Response: ' . $vision_body_response . '</div>';
            }
        }
        echo '</div>';
    }
    
    // Final summary
    echo '<div class="test-section">';
    echo '<h2>Summary and Next Steps</h2>';
    if ($response_code === 200) {
        echo '<div class="success">✓ Your OpenAI API integration appears to be working correctly!</div>';
        echo '<div class="info">If you\'re still experiencing issues with the plugin, check the WordPress error logs for more detailed error messages.</div>';
        echo '<div class="info">WordPress error logs are typically located at: /wp-content/debug.log</div>';
    } else {
        echo '<div class="error">✗ There are issues with your OpenAI API configuration that need to be resolved.</div>';
    }
    echo '</div>';
    ?>
    
    <div class="test-section">
        <h2>How to Use This Information</h2>
        <ul>
            <li>If all tests pass but the plugin still isn't working, check your WordPress error logs</li>
            <li>Enable WordPress debug logging by adding these lines to wp-config.php:</li>
            <pre>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
            <li>Try processing a photo and then check /wp-content/debug.log for error messages</li>
            <li>Delete this file after testing for security</li>
        </ul>
    </div>
</body>
</html>