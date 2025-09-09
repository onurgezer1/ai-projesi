<?php
/**
 * Quick test for the user's specific issue
 */

if (!defined('ABSPATH')) define('ABSPATH', dirname(__FILE__) . '/');

require_once 'includes/class-ai-processor.php';

$processor = new AI_Photo_Processor();
$reflection = new ReflectionClass($processor);
$method = $reflection->getMethod('parse_transformation_instructions');
$method->setAccessible(true);

$instruction = 'Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap.';

echo "🧪 Testing: " . $instruction . "\n\n";

$result = $method->invoke($processor, $instruction);

echo "🔍 CRITICAL DETECTIONS:\n";
echo "- Clothing detected: " . (isset($result['target_clothing']) ? 'YES (' . implode(', ', $result['target_clothing']) . ')' : 'NO') . "\n";
echo "- Colors detected: " . (isset($result['target_colors']) ? 'YES (' . implode(', ', $result['target_colors']) . ')' : 'NO') . "\n";
echo "- Clothing transformation: " . (isset($result['transformation_type']) && $result['transformation_type'] === 'clothing_modification' ? 'YES' : 'NO') . "\n";
echo "- Color transformation: " . (isset($result['color_transformation']) && $result['color_transformation'] ? 'YES' : 'NO') . "\n";
echo "- Clothing actions: " . (isset($result['clothing_actions']) ? implode(', ', $result['clothing_actions']) : 'NONE') . "\n";

echo "\n🚀 PROCESSING OUTCOME:\n";
if (isset($result['transformation_type']) && $result['transformation_type'] === 'clothing_modification') {
    echo "✅ SUCCESS: Will trigger OBJECT-SPECIFIC clothing transformation!\n";
    echo "   - Will apply selective color transformation to shirt area\n";
    echo "   - Will add black color overlay to simulate black shirt\n";
    echo "   - Will preserve person and background\n";
} else if (isset($result['color_transformation']) && $result['color_transformation']) {
    echo "⚠️ PARTIAL: Will trigger color transformation (general)\n";
} else {
    echo "❌ ISSUE: Will use general processing only\n";
}

// Test lowercase version
echo "\n" . str_repeat("=", 50) . "\n";
echo "🧪 Testing lowercase: " . strtolower($instruction) . "\n\n";

$result2 = $method->invoke($processor, strtolower($instruction));
echo "🔍 LOWERCASE RESULTS:\n";
echo "- Clothing detected: " . (isset($result2['target_clothing']) ? 'YES (' . implode(', ', $result2['target_clothing']) . ')' : 'NO') . "\n";
echo "- Colors detected: " . (isset($result2['target_colors']) ? 'YES (' . implode(', ', $result2['target_colors']) . ')' : 'NO') . "\n";

// Test direct pattern matching
echo "\n" . str_repeat("=", 50) . "\n";
echo "🔍 DIRECT PATTERN TEST:\n";
$lower = strtolower($instruction);
echo "Instruction (lowercase): " . $lower . "\n";
echo "Contains 'gömleğinin': " . (strpos($lower, 'gömleğinin') !== false ? 'YES' : 'NO') . "\n";
echo "Contains '\"siyah\"': " . (strpos($lower, '"siyah"') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'siyah': " . (strpos($lower, 'siyah') !== false ? 'YES' : 'NO') . "\n";

?>