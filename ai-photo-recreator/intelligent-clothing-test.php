<?php
/**
 * Intelligent Clothing Color Transformation Test
 * 
 * Tests the enhanced AI system's ability to understand and process
 * specific clothing color change commands in Turkish and English
 */

// Security check
if (!defined('ABSPATH')) {
    // For standalone testing
    define('ABSPATH', __DIR__ . '/../../../');
}

// Load WordPress if not already loaded
if (!function_exists('get_option')) {
    require_once(ABSPATH . 'wp-config.php');
}

// Include the AI processor
require_once(__DIR__ . '/includes/class-ai-processor.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Intelligent Clothing Color Transformation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .test-case { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
        .test-case h3 { margin-top: 0; color: #333; }
        .instruction { background: #f0f0f0; padding: 10px; border-radius: 3px; font-weight: bold; }
        .result { background: #e8f4fd; padding: 10px; border-radius: 3px; margin-top: 10px; }
        .analysis { background: #f9f9f9; padding: 10px; border-radius: 3px; margin-top: 10px; font-family: monospace; font-size: 12px; }
        .success { color: #008000; }
        .warning { color: #ff8800; }
        .error { color: #cc0000; }
    </style>
</head>
<body>
    <h1>🧠 Intelligent Clothing Color Transformation Test</h1>
    <p>Testing the enhanced AI system's ability to understand and process clothing color transformation commands.</p>

    <?php
    // Initialize the AI processor
    $processor = new AI_Photo_Processor();
    
    // Test cases for clothing color transformations
    $test_cases = array(
        array(
            'name' => 'Turkish Clothing Color Change (Quoted Color)',
            'instruction' => 'Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap.',
            'expected' => 'Should detect: shirt + black color + clothing modification'
        ),
        array(
            'name' => 'Turkish Clothing Color Change (Unquoted)',
            'instruction' => 'Bu fotoğraftaki adamın gömleğinin rengini siyah yap.',
            'expected' => 'Should detect: shirt + black color + clothing modification'
        ),
        array(
            'name' => 'English Clothing Color Change',
            'instruction' => 'Change the man\'s shirt color to black in the photo.',
            'expected' => 'Should detect: shirt + black color + clothing modification'
        ),
        array(
            'name' => 'Turkish Complex Grammar',
            'instruction' => 'Fotoğraftaki kişinin ceketinin rengini "Mavi" olarak değiştir.',
            'expected' => 'Should detect: jacket + blue color + clothing modification'
        ),
        array(
            'name' => 'Multiple Clothing Items',
            'instruction' => 'Change the shirt to red and pants to blue.',
            'expected' => 'Should detect: shirt + pants + red + blue + clothing modification'
        ),
        array(
            'name' => 'Turkish T-shirt Variation',
            'instruction' => 'Adamın tişörtünün rengini "Yeşil" yap.',
            'expected' => 'Should detect: shirt (t-shirt) + green color + clothing modification'
        ),
        array(
            'name' => 'Weather Command (Non-clothing)',
            'instruction' => 'Make it snow on the man in the photo.',
            'expected' => 'Should detect: weather transformation + snow effect'
        ),
        array(
            'name' => 'Turkish Weather Command',
            'instruction' => 'Fotoğrafta kar yağdır.',
            'expected' => 'Should detect: weather transformation + snow effect'
        )
    );
    
    // Process each test case
    foreach ($test_cases as $index => $test) {
        echo "<div class='test-case'>";
        echo "<h3>Test Case " . ($index + 1) . ": " . htmlspecialchars($test['name']) . "</h3>";
        echo "<div class='instruction'>Instruction: \"" . htmlspecialchars($test['instruction']) . "\"</div>";
        echo "<div>Expected: " . htmlspecialchars($test['expected']) . "</div>";
        
        try {
            // Call the private method using reflection for testing
            $reflection = new ReflectionClass($processor);
            $method = $reflection->getMethod('parse_transformation_instructions');
            $method->setAccessible(true);
            
            $result = $method->invoke($processor, $test['instruction']);
            
            echo "<div class='result'>";
            echo "<strong>Analysis Results:</strong><br>";
            
            // Check for clothing detection
            if (isset($result['transformation_type']) && $result['transformation_type'] === 'clothing_modification') {
                echo "<span class='success'>✅ Clothing Transformation Detected</span><br>";
                
                if (!empty($result['target_clothing'])) {
                    echo "<strong>Detected Clothing:</strong> " . implode(', ', $result['target_clothing']) . "<br>";
                }
                
                if (!empty($result['target_colors'])) {
                    echo "<strong>Target Colors:</strong> " . implode(', ', $result['target_colors']) . "<br>";
                }
                
                if (!empty($result['clothing_actions'])) {
                    echo "<strong>Actions:</strong> " . implode(', ', $result['clothing_actions']) . "<br>";
                }
            } else {
                // Check for weather transformation
                if (isset($result['weather_analysis']['effects'])) {
                    echo "<span class='success'>✅ Weather Transformation Detected</span><br>";
                    echo "<strong>Weather Effects:</strong> " . implode(', ', array_keys($result['weather_analysis']['effects'])) . "<br>";
                } else {
                    echo "<span class='warning'>⚠️ No Specific Transformation Type Detected</span><br>";
                }
            }
            
            echo "<strong>Language:</strong> " . ($result['language'] ?? 'Unknown') . "<br>";
            echo "<strong>Primary Intent:</strong> " . ($result['primary_intent'] ?? 'Unknown') . "<br>";
            echo "</div>";
            
            // Detailed analysis for debugging
            echo "<details><summary>Detailed Analysis (Click to expand)</summary>";
            echo "<div class='analysis'>";
            echo htmlspecialchars(print_r($result, true));
            echo "</div></details>";
            
        } catch (Exception $e) {
            echo "<div class='result error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        echo "</div>";
    }
    ?>
    
    <h2>🎯 Intelligence System Features</h2>
    <div class="test-case">
        <h3>Advanced Features Demonstrated</h3>
        <ul>
            <li>✅ <strong>Turkish Grammar Support:</strong> Handles possessive forms like "gömleğinin", "ceketinin"</li>
            <li>✅ <strong>Quoted Color Detection:</strong> Recognizes colors in quotes like "Siyah", "Mavi"</li>
            <li>✅ <strong>Multi-language Processing:</strong> Works with both Turkish and English</li>
            <li>✅ <strong>Clothing Item Recognition:</strong> Detects shirts, jackets, t-shirts, pants, etc.</li>
            <li>✅ <strong>Action Detection:</strong> Understands color change, style change, fit change</li>
            <li>✅ <strong>Intent Analysis:</strong> Distinguishes between clothing modifications and other transformations</li>
            <li>✅ <strong>Comprehensive Analysis:</strong> 7-phase intelligent processing pipeline</li>
        </ul>
    </div>
    
    <div class="test-case">
        <h3>Processing Pipeline</h3>
        <ol>
            <li><strong>Semantic Analysis:</strong> Understanding intent and context</li>
            <li><strong>Contextual Intelligence:</strong> Analyzing relationships and dependencies</li>
            <li><strong>Linguistic Intelligence:</strong> Grammar, syntax, and language patterns</li>
            <li><strong>Creative Intelligence:</strong> Abstract concepts and artistic intent</li>
            <li><strong>Environmental Intelligence:</strong> Scene context and transformation requirements</li>
            <li><strong>Complexity Assessment:</strong> Understanding transformation scope</li>
            <li><strong>Object-Specific Intelligence:</strong> Clothing, items, and targeted transformations</li>
        </ol>
    </div>

</body>
</html>