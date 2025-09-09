<?php
/**
 * Test Improved Turkish Clothing Color Detection
 * 
 * This script tests the improved Turkish language detection
 * for clothing color transformation commands.
 */

// Include WordPress environment
if (file_exists('../../../wp-config.php')) {
    require_once '../../../wp-config.php';
} else {
    // Define basic constants for testing
    define('ABSPATH', __DIR__ . '/');
}

// Include the AI processor class
require_once 'includes/class-ai-processor.php';

echo "<h1>🧠 Test Improved Turkish Clothing Color Detection</h1>\n";
echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>\n";

// Test commands
$test_commands = array(
    '"Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap."',
    '"Gömlek rengini beyaz yap"',
    '"Adamın tişörtünün rengini kırmızı değiştir"',
    '"Change the shirt color to blue"',
    '"Make the man\'s shirt black"',
    '"Ceketinin rengini mavi yap"'
);

$processor = new AI_Photo_Processor();

foreach ($test_commands as $i => $command) {
    echo "<h3>Test " . ($i + 1) . ": " . htmlspecialchars($command) . "</h3>\n";
    
    // Use reflection to access private method for testing
    $reflection = new ReflectionClass($processor);
    $parseMethod = $reflection->getMethod('parse_transformation_instructions');
    $parseMethod->setAccessible(true);
    
    try {
        $result = $parseMethod->invoke($processor, $command);
        
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
        echo "<strong>Analysis Results:</strong><br>\n";
        
        // Display key results
        echo "Language: " . ($result['language'] ?? 'unknown') . "<br>\n";
        echo "Transformation Type: " . ($result['transformation_type'] ?? 'none') . "<br>\n";
        
        if (isset($result['target_clothing'])) {
            echo "Target Clothing: " . implode(', ', $result['target_clothing']) . "<br>\n";
        }
        
        if (isset($result['target_colors'])) {
            echo "Target Colors: " . implode(', ', $result['target_colors']) . "<br>\n";
        }
        
        if (isset($result['clothing_actions'])) {
            echo "Actions: " . implode(', ', $result['clothing_actions']) . "<br>\n";
        }
        
        // Display Turkish analysis if available
        if (isset($result['turkish_analysis']['case_analysis'])) {
            $case_analysis = $result['turkish_analysis']['case_analysis'];
            if ($case_analysis['clothing_possessive_detected'] ?? false) {
                echo "<strong>✅ Turkish Possessive Pattern Detected!</strong><br>\n";
                foreach ($case_analysis['detected_patterns'] as $pattern) {
                    echo "- Pattern: " . htmlspecialchars($pattern['match']) . "<br>\n";
                    echo "- Item: " . $pattern['item'] . ", Action: " . $pattern['action'] . "<br>\n";
                }
            }
            
            if (!empty($case_analysis['color_specifications'])) {
                echo "<strong>🎨 Color Specifications:</strong><br>\n";
                foreach ($case_analysis['color_specifications'] as $color) {
                    echo "- " . $color['color'] . " (type: " . $color['type'] . ")<br>\n";
                }
            }
        }
        
        echo "</div>\n";
        
        // Check if clothing transformation was properly detected
        $success = isset($result['transformation_type']) && 
                  $result['transformation_type'] === 'clothing_modification' &&
                  !empty($result['target_clothing']) &&
                  !empty($result['target_colors']);
        
        if ($success) {
            echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>✅ SUCCESS: Clothing color transformation properly detected!</div>\n";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>❌ FAILED: Clothing color transformation not properly detected</div>\n";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    }
    
    echo "<hr>\n";
}

echo "<h2>🔧 System Information</h2>\n";
echo "<div style='background: #e9ecef; padding: 10px; border-radius: 5px;'>\n";
echo "PHP Version: " . phpversion() . "<br>\n";
echo "GD Extension: " . (extension_loaded('gd') ? 'Loaded' : 'Not loaded') . "<br>\n";
echo "Test File: " . __FILE__ . "<br>\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "<br>\n";
echo "</div>\n";

echo "</div>\n";
?>