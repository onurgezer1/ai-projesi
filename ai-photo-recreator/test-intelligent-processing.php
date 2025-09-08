<?php
/**
 * Test script to demonstrate the new intelligent AI processing capabilities
 * This shows how different instructions now produce varied and intelligent parsing results
 */

// Include the AI processor class
require_once 'includes/class-ai-processor.php';

/**
 * Test function to demonstrate instruction parsing
 */
function test_instruction_parsing($instruction) {
    $processor = new AI_Photo_Processor();
    
    // Use reflection to access the private method for testing
    $reflection = new ReflectionClass($processor);
    $method = $reflection->getMethod('parse_transformation_instructions');
    $method->setAccessible(true);
    
    $result = $method->invokeArgs($processor, [$instruction]);
    
    echo "=== Testing Instruction: '$instruction' ===\n";
    echo "Parsed Result:\n";
    print_r($result);
    echo "\n";
}

// Test various instructions to show intelligence and variety
echo "AI Photo Recreator - Intelligent Processing Test\n";
echo "================================================\n\n";

// Basic instructions
test_instruction_parsing("Make it snow on the man in the photo");
test_instruction_parsing("Kar yağdır");
test_instruction_parsing("Very heavy snow storm");
test_instruction_parsing("Light snow effect");

// Background replacement instructions
test_instruction_parsing("Fotoğraftaki arka planı kaldır ve kış temalı bir arka plan ekle");
test_instruction_parsing("Change background to winter landscape");
test_instruction_parsing("Replace with stormy rainy background");

// Compound instructions
test_instruction_parsing("Make it snow and add vintage style");
test_instruction_parsing("Kar yağdır ve dramatik stil ekle");
test_instruction_parsing("Change to night scene with soft lighting");

// Creative instructions
test_instruction_parsing("Make it magical and dreamy");
test_instruction_parsing("Create a surreal atmosphere");
test_instruction_parsing("Make it happy and energetic");

// Intensity variations
test_instruction_parsing("Extreme winter blizzard conditions");
test_instruction_parsing("Hafif kar efekti");
test_instruction_parsing("Very dramatic sunset");
test_instruction_parsing("Subtle spring colors");

// Location-based instructions
test_instruction_parsing("Move to beach setting with summer vibes");
test_instruction_parsing("Forest environment with autumn leaves");
test_instruction_parsing("Urban cityscape at night");

echo "Test completed! Notice how each instruction produces unique parsing results.\n";
echo "The AI now understands context, intensity, and can handle complex compound instructions.\n";
?>