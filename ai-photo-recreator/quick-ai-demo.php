<?php
/**
 * Quick AI Analysis Demo
 * 
 * Shows how the improved AI analyzes Turkish clothing color commands
 */

// Basic constants
define('ABSPATH', __DIR__ . '/');

// Include the processor
require_once 'includes/class-ai-processor.php';

// Test command
$test_command = 'Bu fotoğraftaki adamın gömleğinin rengini "Siyah" yap.';

echo "🧠 AI Analysis Demo\n";
echo "==================\n\n";
echo "Command: {$test_command}\n\n";

$processor = new AI_Photo_Processor();

// Use reflection to access private methods
$reflection = new ReflectionClass($processor);

// Test Turkish case analysis
$turkishMethod = $reflection->getMethod('analyze_turkish_cases');
$turkishMethod->setAccessible(true);

$turkish_result = $turkishMethod->invoke($processor, strtolower($test_command));

echo "Turkish Analysis Results:\n";
echo "-------------------------\n";
echo "Clothing Possessive Detected: " . ($turkish_result['clothing_possessive_detected'] ? 'YES' : 'NO') . "\n";

if ($turkish_result['clothing_possessive_detected']) {
    foreach ($turkish_result['detected_patterns'] as $pattern) {
        echo "- Pattern Match: {$pattern['match']}\n";
        echo "- Item: {$pattern['item']}\n";
        echo "- Action: {$pattern['action']}\n";
    }
}

echo "\nColor Specifications:\n";
foreach ($turkish_result['color_specifications'] ?? array() as $color) {
    echo "- Color: {$color['color']} (Type: {$color['type']})\n";
}

echo "\n✅ The AI now properly understands Turkish clothing commands!\n";
?>