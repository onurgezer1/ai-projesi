<?php
/**
 * Enhanced System Diagnostic Tool
 * 
 * Comprehensive system check to ensure all components are working properly
 */

// Security check and WordPress loading
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../../');
}

if (!function_exists('get_option')) {
    require_once(ABSPATH . 'wp-config.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>AI Photo Recreator - Enhanced System Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .status-ok { color: #008000; font-weight: bold; }
        .status-warning { color: #ff8800; font-weight: bold; }
        .status-error { color: #cc0000; font-weight: bold; }
        .section { border: 1px solid #ddd; margin: 15px 0; padding: 15px; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        .diagnostic-item { margin: 8px 0; padding: 8px; background: #f9f9f9; border-radius: 3px; }
        .code-sample { background: #f0f0f0; padding: 10px; font-family: monospace; font-size: 12px; border-radius: 3px; }
        .test-result { background: #e8f4fd; padding: 10px; margin: 10px 0; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 AI Photo Recreator - Enhanced System Diagnostics</h1>
    
    <?php
    $all_tests_passed = true;
    
    // Test 1: Basic PHP and WordPress
    echo "<div class='section'>";
    echo "<h3>1. Basic System Requirements</h3>";
    
    echo "<div class='diagnostic-item'>";
    echo "<strong>PHP Version:</strong> " . PHP_VERSION;
    if (version_compare(PHP_VERSION, '7.4', '>=')) {
        echo " <span class='status-ok'>✅ OK</span>";
    } else {
        echo " <span class='status-error'>❌ PHP 7.4+ required</span>";
        $all_tests_passed = false;
    }
    echo "</div>";
    
    echo "<div class='diagnostic-item'>";
    echo "<strong>WordPress Loaded:</strong> ";
    if (function_exists('get_option')) {
        echo "<span class='status-ok'>✅ YES</span>";
        echo " (Version: " . get_bloginfo('version') . ")";
    } else {
        echo "<span class='status-error'>❌ NO</span>";
        $all_tests_passed = false;
    }
    echo "</div>";
    
    echo "</div>";
    
    // Test 2: GD Extension
    echo "<div class='section'>";
    echo "<h3>2. Image Processing (GD Extension)</h3>";
    
    echo "<div class='diagnostic-item'>";
    echo "<strong>GD Extension:</strong> ";
    if (extension_loaded('gd')) {
        echo "<span class='status-ok'>✅ LOADED</span>";
        $gd_info = gd_info();
        echo "<br><strong>GD Version:</strong> " . $gd_info['GD Version'];
    } else {
        echo "<span class='status-error'>❌ NOT LOADED</span>";
        $all_tests_passed = false;
    }
    echo "</div>";
    
    if (extension_loaded('gd')) {
        $required_constants = array(
            'IMG_FILTER_BRIGHTNESS', 'IMG_FILTER_CONTRAST', 'IMG_FILTER_COLORIZE',
            'IMG_FILTER_SMOOTH', 'IMG_FILTER_GRAYSCALE'
        );
        
        foreach ($required_constants as $const) {
            echo "<div class='diagnostic-item'>";
            echo "<strong>Constant {$const}:</strong> ";
            if (defined($const)) {
                echo "<span class='status-ok'>✅ DEFINED</span> (Value: " . constant($const) . ")";
            } else {
                echo "<span class='status-error'>❌ MISSING</span>";
                $all_tests_passed = false;
            }
            echo "</div>";
        }
        
        $supported_formats = array();
        if (function_exists('imagecreatefromjpeg')) $supported_formats[] = 'JPEG';
        if (function_exists('imagecreatefrompng')) $supported_formats[] = 'PNG';  
        if (function_exists('imagecreatefromwebp')) $supported_formats[] = 'WebP';
        
        echo "<div class='diagnostic-item'>";
        echo "<strong>Supported Image Formats:</strong> " . implode(', ', $supported_formats);
        if (count($supported_formats) >= 2) {
            echo " <span class='status-ok'>✅ OK</span>";
        } else {
            echo " <span class='status-warning'>⚠️ Limited support</span>";
        }
        echo "</div>";
    }
    
    echo "</div>";
    
    // Test 3: Plugin Files
    echo "<div class='section'>";
    echo "<h3>3. Plugin Files</h3>";
    
    $plugin_files = array(
        'Main Plugin File' => __DIR__ . '/ai-photo-recreator.php',
        'AI Processor Class' => __DIR__ . '/includes/class-ai-processor.php',
        'Shortcode Handler' => __DIR__ . '/includes/class-shortcode-handler.php',
        'File Handler' => __DIR__ . '/includes/class-file-handler.php'
    );
    
    foreach ($plugin_files as $name => $file) {
        echo "<div class='diagnostic-item'>";
        echo "<strong>{$name}:</strong> ";
        if (file_exists($file)) {
            echo "<span class='status-ok'>✅ EXISTS</span>";
            echo " (Size: " . number_format(filesize($file)) . " bytes)";
            
            // Check for PHP syntax
            $output = array();
            $return_var = 0;
            exec("php -l " . escapeshellarg($file), $output, $return_var);
            if ($return_var === 0) {
                echo " <span class='status-ok'>✅ Valid PHP</span>";
            } else {
                echo " <span class='status-error'>❌ PHP Syntax Error</span>";
                $all_tests_passed = false;
            }
        } else {
            echo "<span class='status-error'>❌ MISSING</span>";
            $all_tests_passed = false;
        }
        echo "</div>";
    }
    
    echo "</div>";
    
    // Test 4: AI Processor Intelligence
    echo "<div class='section'>";
    echo "<h3>4. AI Intelligence System</h3>";
    
    if (file_exists(__DIR__ . '/includes/class-ai-processor.php')) {
        require_once(__DIR__ . '/includes/class-ai-processor.php');
        
        try {
            $processor = new AI_Photo_Processor();
            echo "<div class='diagnostic-item'>";
            echo "<strong>AI Processor Class:</strong> <span class='status-ok'>✅ LOADED</span>";
            echo "</div>";
            
            // Test instruction parsing
            $test_instructions = array(
                'Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap.' => 'Turkish clothing color change',
                'Make it snow on the man in the photo.' => 'English weather transformation'
            );
            
            foreach ($test_instructions as $instruction => $description) {
                echo "<div class='diagnostic-item'>";
                echo "<strong>Test: {$description}</strong><br>";
                echo "<em>Instruction:</em> \"{$instruction}\"<br>";
                
                try {
                    $reflection = new ReflectionClass($processor);
                    $method = $reflection->getMethod('parse_transformation_instructions');
                    $method->setAccessible(true);
                    $result = $method->invoke($processor, $instruction);
                    
                    if (isset($result['transformation_type'])) {
                        echo "<em>Detected Type:</em> " . $result['transformation_type'] . " ";
                        echo "<span class='status-ok'>✅ DETECTED</span><br>";
                    } else {
                        echo "<em>Type:</em> General transformation ";
                        echo "<span class='status-warning'>⚠️ General</span><br>";
                    }
                    
                    echo "<em>Language:</em> " . ($result['language'] ?? 'Unknown') . "<br>";
                    
                    if (!empty($result['target_clothing'])) {
                        echo "<em>Clothing:</em> " . implode(', ', $result['target_clothing']) . "<br>";
                    }
                    
                    if (!empty($result['target_colors'])) {
                        echo "<em>Colors:</em> " . implode(', ', $result['target_colors']) . "<br>";
                    }
                    
                } catch (Exception $e) {
                    echo "<span class='status-error'>❌ ERROR: " . $e->getMessage() . "</span>";
                    $all_tests_passed = false;
                }
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='diagnostic-item'>";
            echo "<strong>AI Processor Class:</strong> <span class='status-error'>❌ ERROR: " . $e->getMessage() . "</span>";
            echo "</div>";
            $all_tests_passed = false;
        }
    }
    
    echo "</div>";
    
    // Test 5: WordPress Integration
    echo "<div class='section'>";
    echo "<h3>5. WordPress Integration</h3>";
    
    // Check if plugin is active
    echo "<div class='diagnostic-item'>";
    echo "<strong>Plugin Status:</strong> ";
    if (is_plugin_active('ai-photo-recreator/ai-photo-recreator.php')) {
        echo "<span class='status-ok'>✅ ACTIVE</span>";
    } else {
        echo "<span class='status-warning'>⚠️ Not active (or path different)</span>";
    }
    echo "</div>";
    
    // Check uploads directory
    $upload_dir = wp_upload_dir();
    echo "<div class='diagnostic-item'>";
    echo "<strong>WordPress Uploads Dir:</strong> ";
    if (is_writable($upload_dir['basedir'])) {
        echo "<span class='status-ok'>✅ WRITABLE</span>";
        echo "<br><em>Path:</em> " . $upload_dir['basedir'];
    } else {
        echo "<span class='status-error'>❌ NOT WRITABLE</span>";
        $all_tests_passed = false;
    }
    echo "</div>";
    
    echo "</div>";
    
    // Test 6: System Performance Test
    echo "<div class='section'>";
    echo "<h3>6. System Performance Test</h3>";
    
    // Memory test
    echo "<div class='diagnostic-item'>";
    echo "<strong>PHP Memory Limit:</strong> " . ini_get('memory_limit');
    $memory_limit_bytes = return_bytes(ini_get('memory_limit'));
    if ($memory_limit_bytes >= 128 * 1024 * 1024) {
        echo " <span class='status-ok'>✅ Adequate</span>";
    } else {
        echo " <span class='status-warning'>⚠️ May be limited for large images</span>";
    }
    echo "</div>";
    
    // Execution time test
    echo "<div class='diagnostic-item'>";
    echo "<strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds";
    if (ini_get('max_execution_time') >= 30) {
        echo " <span class='status-ok'>✅ OK</span>";
    } else {
        echo " <span class='status-warning'>⚠️ May timeout on complex operations</span>";
    }
    echo "</div>";
    
    echo "</div>";
    
    // Overall Status
    echo "<div class='section'>";
    echo "<h3>🎯 Overall System Status</h3>";
    
    if ($all_tests_passed) {
        echo "<div class='test-result'>";
        echo "<span class='status-ok'>✅ ALL TESTS PASSED</span><br>";
        echo "Your AI Photo Recreator system is fully functional and ready to process intelligent transformations!";
        echo "</div>";
    } else {
        echo "<div class='test-result'>";
        echo "<span class='status-error'>❌ SOME TESTS FAILED</span><br>";
        echo "Please review the failed tests above and contact your system administrator to resolve the issues.";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Helper function
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = substr($val, 0, -1);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    ?>
    
</body>
</html>