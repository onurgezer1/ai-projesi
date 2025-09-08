<?php
/**
 * Object-Specific Transformation Demo and Test
 * Demonstrates the new enhanced AI capabilities for clothing, color, and object transformations
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include the AI processor class
require_once 'includes/class-ai-processor.php';

echo "<h1>🧠 Object-Specific AI Transformation Demo</h1>\n";
echo "<p>Testing the new enhanced AI intelligence system for specific object transformations.</p>\n\n";

// Initialize the AI processor
$processor = new AI_Photo_Processor();

// Test instructions that the user is having trouble with
$test_instructions = array(
    // User's specific problematic instruction
    'turkish_clothing_black' => 'Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap.',
    
    // Other clothing color changes
    'english_shirt_red' => 'Make the man\'s shirt red in this photo.',
    'turkish_shirt_white' => 'Adamın gömleğini beyaz yap.',
    'english_pants_blue' => 'Change the person\'s pants to blue color.',
    
    // Other object transformations  
    'turkish_eyes_brown' => 'Bu kişinin göz rengini kahverengi yap.',
    'english_hair_blonde' => 'Make the person\'s hair blonde.',
    'turkish_smile' => 'Bu kişiyi gülümsetir.',
    
    // Complex combinations
    'turkish_complex' => 'Adamın gömleğini siyah yap ve arka planı kış temalı değiştir.',
    'english_complex' => 'Make the shirt black and add a snowy winter background.',
    
    // Style modifications
    'turkish_style' => 'Bu fotoğraftaki elbiseyi vintage stile dönüştür.',
    'english_vintage' => 'Convert the clothing to vintage style.'
);

foreach ($test_instructions as $test_name => $instruction) {
    echo "<h2>🧪 Test: {$test_name}</h2>\n";
    echo "<strong>Instruction:</strong> <em>\"{$instruction}\"</em>\n\n";
    
    try {
        // Use reflection to access the private method for testing
        $reflection = new ReflectionClass($processor);
        $parse_method = $reflection->getMethod('parse_transformation_instructions');
        $parse_method->setAccessible(true);
        
        // Parse the instruction
        $result = $parse_method->invoke($processor, $instruction);
        
        echo "<h3>📊 Intelligent Analysis Result:</h3>\n";
        echo "<pre>" . print_r($result, true) . "</pre>\n";
        
        // Highlight key detections
        echo "<h3>🔍 Key AI Detections:</h3>\n";
        echo "<ul>\n";
        
        if (isset($result['language'])) {
            echo "<li><strong>Language Detected:</strong> " . ucfirst($result['language']) . "</li>\n";
        }
        
        if (isset($result['transformation_type'])) {
            echo "<li><strong>Transformation Type:</strong> " . $result['transformation_type'] . "</li>\n";
        }
        
        if (isset($result['target_clothing'])) {
            echo "<li><strong>Target Clothing:</strong> " . implode(', ', $result['target_clothing']) . "</li>\n";
        }
        
        if (isset($result['target_colors'])) {
            echo "<li><strong>Target Colors:</strong> " . implode(', ', $result['target_colors']) . "</li>\n";
        }
        
        if (isset($result['clothing_actions'])) {
            echo "<li><strong>Clothing Actions:</strong> " . implode(', ', $result['clothing_actions']) . "</li>\n";
        }
        
        if (isset($result['color_transformation'])) {
            echo "<li><strong>Color Transformation:</strong> " . ($result['color_transformation'] ? 'YES' : 'NO') . "</li>\n";
        }
        
        if (isset($result['target_body_parts'])) {
            echo "<li><strong>Body Parts:</strong> " . implode(', ', $result['target_body_parts']) . "</li>\n";
        }
        
        if (isset($result['primary_intent'])) {
            echo "<li><strong>Primary Intent:</strong> " . $result['primary_intent'] . "</li>\n";
        }
        
        if (isset($result['transformation_scope'])) {
            echo "<li><strong>Scope:</strong> " . $result['transformation_scope'] . "</li>\n";
        }
        
        if (isset($result['complexity_score'])) {
            echo "<li><strong>Complexity Score:</strong> " . $result['complexity_score'] . "</li>\n";
        }
        
        echo "</ul>\n";
        
        // Show processing strategy
        echo "<h3>🚀 Processing Strategy:</h3>\n";
        echo "<div style='background: #f0f8ff; padding: 10px; border-left: 4px solid #0066cc;'>\n";
        
        if (isset($result['transformation_type']) && $result['transformation_type'] === 'clothing_modification') {
            echo "<strong>✅ OBJECT-SPECIFIC PROCESSING:</strong> This instruction will trigger the new clothing transformation system!\n<br>";
            echo "• Will detect and target specific clothing items\n<br>";
            echo "• Will apply targeted color transformation\n<br>";
            echo "• Will preserve the rest of the image while modifying only the specified clothing\n<br>";
        } else if (isset($result['color_transformation']) && $result['color_transformation']) {
            echo "<strong>✅ COLOR-SPECIFIC PROCESSING:</strong> This instruction will trigger color transformation!\n<br>";
        } else {
            echo "<strong>ℹ️ GENERAL PROCESSING:</strong> This instruction will use general transformation methods.\n<br>";
        }
        
        echo "</div>\n";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>\n";
    }
    
    echo "<hr>\n\n";
}

echo "<h2>📋 Summary of New Capabilities</h2>\n";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc;'>\n";
echo "<h3>🆕 New Object-Specific Intelligence:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Clothing Detection:</strong> shirt, gömlek, pants, pantolon, dress, elbise, jacket, ceket, etc.</li>\n";
echo "<li><strong>Color Recognition:</strong> Black/Siyah, White/Beyaz, Red/Kırmızı, Blue/Mavi, etc.</li>\n";
echo "<li><strong>Body Part Detection:</strong> hair/saç, eyes/göz, smile/gülümse, face/yüz</li>\n";
echo "<li><strong>Action Understanding:</strong> color change, style change, add, remove, modify</li>\n";
echo "<li><strong>Turkish Language Support:</strong> Full support for Turkish clothing and color terms</li>\n";
echo "</ul>\n\n";

echo "<h3>🎯 Targeted Processing:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Selective Color Transformation:</strong> Changes only target clothing colors</li>\n";
echo "<li><strong>Clothing-Specific Overlays:</strong> Applies color changes to likely clothing areas</li>\n";
echo "<li><strong>Context Awareness:</strong> Understands which parts of the image to modify</li>\n";
echo "<li><strong>Intensity Control:</strong> Varies transformation strength based on instruction language</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🧪 Testing Recommendations</h2>\n";
echo "<div style='background: #fffff0; padding: 15px; border-left: 4px solid #ffcc00;'>\n";
echo "<p><strong>To test the user's specific issue:</strong></p>\n";
echo "<ol>\n";
echo "<li>Upload a photo with a person wearing a visible shirt</li>\n";
echo "<li>Use the instruction: <code>\"Bu fotoğraftaki adamın gömleğinin rengini Siyah yap.\"</code></li>\n";
echo "<li>The system should now:</li>\n";
echo "<ul>\n";
echo "<li>✅ Detect 'gömlek' (shirt) as target clothing</li>\n";
echo "<li>✅ Detect 'Siyah' (black) as target color</li>\n";
echo "<li>✅ Apply selective darkening to clothing areas</li>\n";
echo "<li>✅ Add black color overlay to simulate black shirt</li>\n";
echo "<li>✅ Preserve the person and background</li>\n";
echo "</ul>\n";
echo "</ol>\n";
echo "</div>\n";

if (function_exists('memory_get_usage')) {
    echo "<p><small>Memory used: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</small></p>\n";
}

echo "<p><strong>🎉 Enhanced AI system is ready for testing!</strong></p>\n";
?>