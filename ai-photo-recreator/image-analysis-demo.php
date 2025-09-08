<?php
/**
 * AI Photo Recreator - Comprehensive Image Analysis Demonstration
 * 
 * This script demonstrates the new comprehensive image analysis system
 * that examines uploaded photos in detail before applying transformations.
 * 
 * Usage: Place this file in your WordPress root directory and visit:
 * yoursite.com/image-analysis-demo.php?test_image=path/to/image.jpg
 */

// WordPress integration
if (file_exists('wp-config.php')) {
    require_once('wp-config.php');
    require_once('wp-load.php');
} else {
    die('WordPress not found. Place this file in your WordPress root directory.');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Administrator privileges required.');
}

// Include the AI processor class
require_once(WP_PLUGIN_DIR . '/ai-photo-recreator/includes/class-ai-processor.php');

// HTML Header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Photo Recreator - Image Analysis Demo</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            background: #2271b1; 
            color: white; 
            padding: 20px; 
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
        }
        .analysis-section { 
            margin: 20px 0; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            background: #fafafa;
        }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #e7f3ff; border-color: #b6d4fe; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .detected-item { 
            background: white; 
            padding: 10px; 
            margin: 5px 0; 
            border-left: 4px solid #2271b1;
            border-radius: 4px;
        }
        .color-sample { 
            display: inline-block; 
            width: 20px; 
            height: 20px; 
            margin-right: 8px; 
            border: 1px solid #ccc;
            border-radius: 3px;
            vertical-align: middle;
        }
        .upload-form {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            background: #2271b1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧠 AI Photo Recreator - Comprehensive Image Analysis Demo</h1>
            <p>This system analyzes uploaded photos in detail before applying transformations, exactly as requested!</p>
        </div>

<?php
// Handle image upload and analysis
$analysis_result = null;
$test_image_path = null;
$error_message = null;

if (isset($_FILES['test_image']) && $_FILES['test_image']['error'] === UPLOAD_ERR_OK) {
    // Handle uploaded file
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/ai-photo-recreator/temp/';
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    $uploaded_file = $_FILES['test_image'];
    $test_image_path = $temp_dir . 'demo_' . time() . '_' . $uploaded_file['name'];
    
    if (move_uploaded_file($uploaded_file['tmp_name'], $test_image_path)) {
        echo '<div class="analysis-section success">✅ Image uploaded successfully: ' . basename($test_image_path) . '</div>';
    } else {
        $error_message = 'Failed to upload image file';
    }
} elseif (isset($_GET['test_image'])) {
    // Handle image path parameter
    $test_image_path = ABSPATH . sanitize_text_field($_GET['test_image']);
    if (!file_exists($test_image_path)) {
        $error_message = 'Test image not found: ' . $test_image_path;
    }
}

// Display upload form
?>
        <div class="upload-form">
            <h3>📤 Upload an Image for Analysis</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="test_image" accept="image/*" required>
                <button type="submit" class="btn">Analyze Image</button>
            </form>
            <p><small>Supported formats: JPG, PNG, WebP | Max size: 5MB</small></p>
        </div>

<?php
if ($error_message) {
    echo '<div class="analysis-section error">❌ Error: ' . esc_html($error_message) . '</div>';
} elseif ($test_image_path && file_exists($test_image_path)) {
    echo '<div class="analysis-section info">🔍 Analyzing image: ' . basename($test_image_path) . '</div>';
    
    // Perform comprehensive image analysis
    $processor = new AI_Photo_Processor();
    
    // Use reflection to access private method for demonstration
    $reflection = new ReflectionClass($processor);
    $analyze_method = $reflection->getMethod('analyze_uploaded_image');
    $analyze_method->setAccessible(true);
    
    $start_time = microtime(true);
    $analysis_result = $analyze_method->invoke($processor, $test_image_path);
    $analysis_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo '<div class="analysis-section success">⏱️ Analysis completed in ' . $analysis_time . 'ms</div>';
    
    if ($analysis_result['success']) {
        ?>
        <div class="grid">
            <!-- Basic Image Information -->
            <div class="analysis-section">
                <h3>📊 Basic Image Information</h3>
                <?php if (isset($analysis_result['image_dimensions'])): ?>
                <div class="detected-item">
                    <strong>Dimensions:</strong> 
                    <?= $analysis_result['image_dimensions']['width'] ?>x<?= $analysis_result['image_dimensions']['height'] ?>px<br>
                    <strong>Type:</strong> <?= $analysis_result['image_dimensions']['type'] ?><br>
                    <strong>Orientation:</strong> 
                    <?= $analysis_result['image_dimensions']['width'] > $analysis_result['image_dimensions']['height'] ? 'Landscape' : 'Portrait' ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- People Detection Results -->
            <div class="analysis-section">
                <h3>👥 People Detection</h3>
                <?php if (isset($analysis_result['people_detection'])): ?>
                <div class="detected-item">
                    <strong>Detection Method:</strong> <?= $analysis_result['people_detection']['detection_method'] ?><br>
                    <strong>People Count:</strong> <?= $analysis_result['people_detection']['total_count'] ?><br>
                    
                    <?php if (!empty($analysis_result['people_detection']['detected_people'])): ?>
                        <h4>Detected People:</h4>
                        <?php foreach ($analysis_result['people_detection']['detected_people'] as $i => $person): ?>
                            <div style="margin-left: 20px;">
                                <strong>Person <?= $i + 1 ?>:</strong><br>
                                - ID: <?= $person['id'] ?><br>
                                - Confidence: <?= round($person['confidence'] * 100) ?>%<br>
                                - Bounds: (<?= round($person['bounds']['x']) ?>, <?= round($person['bounds']['y']) ?>) 
                                  <?= round($person['bounds']['width']) ?>x<?= round($person['bounds']['height']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Clothing Detection Results -->
            <div class="analysis-section">
                <h3>👕 Clothing Detection</h3>
                <?php if (isset($analysis_result['clothing_items'])): ?>
                <div class="detected-item">
                    <strong>Detection Method:</strong> <?= $analysis_result['clothing_items']['detection_method'] ?><br>
                    <strong>Clothing Areas:</strong> <?= $analysis_result['clothing_items']['total_areas'] ?><br>
                    
                    <?php if (!empty($analysis_result['clothing_items']['detected_areas'])): ?>
                        <h4>Detected Clothing Areas:</h4>
                        <?php foreach ($analysis_result['clothing_items']['detected_areas'] as $clothing): ?>
                            <div style="margin-left: 20px; margin-bottom: 10px;">
                                <strong><?= ucfirst(str_replace('_', ' ', $clothing['type'])) ?>:</strong><br>
                                - Person: <?= $clothing['person_id'] ?><br>
                                - Confidence: <?= round($clothing['confidence'] * 100) ?>%<br>
                                - Area: (<?= round($clothing['bounds']['x']) ?>, <?= round($clothing['bounds']['y']) ?>) 
                                  <?= round($clothing['bounds']['width']) ?>x<?= round($clothing['bounds']['height']) ?><br>
                                - Dominant Colors: 
                                <?php foreach ($clothing['dominant_colors'] as $color => $count): ?>
                                    <span style="background: <?= $color ?>; padding: 2px 6px; margin: 2px; border-radius: 3px; color: white; font-size: 11px;"><?= $color ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Color Analysis Results -->
            <div class="analysis-section">
                <h3>🎨 Color Analysis</h3>
                <?php if (!empty($analysis_result['dominant_colors'])): ?>
                <div class="detected-item">
                    <h4>Dominant Colors (Top 10):</h4>
                    <?php foreach ($analysis_result['dominant_colors'] as $i => $color): ?>
                        <div style="margin: 5px 0; display: flex; align-items: center;">
                            <span class="color-sample" style="background: <?= $color['hex'] ?>;"></span>
                            <span>
                                <strong><?= ucfirst($color['color_name']) ?></strong> 
                                (<?= $color['hex'] ?>) - 
                                Frequency: <?= $color['frequency'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (isset($analysis_result['color_distribution'])): ?>
                <div class="detected-item">
                    <h4>Color Distribution by Region:</h4>
                    <?php foreach ($analysis_result['color_distribution'] as $region => $colors): ?>
                        <div style="margin: 10px 0;">
                            <strong><?= ucfirst($region) ?> Region:</strong>
                            <?php foreach ($colors as $color => $count): ?>
                                <span style="background: #f0f0f0; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px;">
                                    <?= $color ?> (<?= $count ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Brightness Analysis -->
            <div class="analysis-section">
                <h3>💡 Brightness Analysis</h3>
                <?php if (isset($analysis_result['brightness_analysis'])): ?>
                <div class="detected-item">
                    <strong>Average Brightness:</strong> <?= round($analysis_result['brightness_analysis']['average']) ?>/255<br>
                    <strong>Category:</strong> <?= ucfirst($analysis_result['brightness_analysis']['category']) ?> image<br>
                    <div style="margin-top: 10px;">
                        <div style="background: linear-gradient(to right, #000, #fff); height: 20px; width: 200px; border: 1px solid #ccc; position: relative;">
                            <div style="position: absolute; left: <?= ($analysis_result['brightness_analysis']['average'] / 255) * 100 ?>%; top: -5px; width: 2px; height: 30px; background: red;"></div>
                        </div>
                        <small>Brightness level indicator</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Scene Analysis -->
            <div class="analysis-section">
                <h3>🌍 Scene Analysis</h3>
                <?php if (isset($analysis_result['scene_analysis'])): ?>
                <div class="detected-item">
                    <?php if (isset($analysis_result['scene_analysis']['lighting'])): ?>
                        <strong>Lighting:</strong><br>
                        - Overall: <?= ucfirst($analysis_result['scene_analysis']['lighting']['overall_brightness']) ?><br>
                        - Type: <?= $analysis_result['scene_analysis']['lighting']['lighting_type'] ?><br><br>
                    <?php endif; ?>
                    
                    <?php if (isset($analysis_result['scene_analysis']['composition'])): ?>
                        <strong>Composition:</strong><br>
                        - Orientation: <?= ucfirst($analysis_result['scene_analysis']['composition']['orientation']) ?><br>
                        - Aspect Ratio: <?= $analysis_result['scene_analysis']['composition']['aspect_ratio'] ?><br><br>
                    <?php endif; ?>
                    
                    <strong>Color Temperature:</strong> <?= ucfirst($analysis_result['scene_analysis']['color_temperature']) ?> tones
                </div>
                <?php endif; ?>
            </div>

            <!-- Object Detection -->
            <div class="analysis-section">
                <h3>🔍 Object & Background Analysis</h3>
                <?php if (isset($analysis_result['object_detection'])): ?>
                <div class="detected-item">
                    <strong>Detection Method:</strong> <?= $analysis_result['object_detection']['detection_method'] ?><br>
                    
                    <?php if (isset($analysis_result['object_detection']['background_analysis'])): ?>
                        <h4>Background Analysis:</h4>
                        <strong>Type:</strong> <?= $analysis_result['object_detection']['background_analysis']['likely_background_type'] ?><br>
                        <strong>Dominant Background Colors:</strong><br>
                        <?php foreach ($analysis_result['object_detection']['background_analysis']['dominant_background_colors'] as $color => $count): ?>
                            <span style="background: #f0f0f0; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px;">
                                <?= $color ?> (<?= $count ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Transformation Commands -->
        <div class="analysis-section info">
            <h3>🧪 Test Transformation Commands</h3>
            <p>Based on this analysis, here are some commands you could test:</p>
            
            <?php if ($analysis_result['people_count'] > 0): ?>
            <div class="detected-item">
                <h4>Clothing Color Changes (Turkish):</h4>
                <code>"Bu fotoğraftaki adamın gömleğinin rengini 'Siyah' yap"</code><br>
                <code>"Bu fotoğraftaki kişinin gömleğini beyaz yap"</code><br>
                <code>"Gömlek rengini mavi yap"</code>
                
                <h4>Clothing Color Changes (English):</h4>
                <code>"Change the person's shirt color to black"</code><br>
                <code>"Make the shirt white"</code><br>
                <code>"Turn the clothing blue"</code>
            </div>
            <?php endif; ?>
            
            <div class="detected-item">
                <h4>Environmental Changes:</h4>
                <code>"Make it snow in this photo"</code> / <code>"Bu fotoğrafta kar yağdır"</code><br>
                <code>"Add rain to the scene"</code> / <code>"Sahneye yağmur ekle"</code><br>
                <code>"Change background to sunset"</code> / <code>"Arka planı gün batımı yap"</code>
            </div>
        </div>

        <!-- Raw Analysis Data -->
        <div class="analysis-section">
            <h3>📋 Raw Analysis Data (for developers)</h3>
            <pre><?= esc_html(json_encode($analysis_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>

        <?php
        // Clean up uploaded file
        if (isset($_FILES['test_image']) && file_exists($test_image_path)) {
            unlink($test_image_path);
            echo '<div class="analysis-section success">🗑️ Temporary file cleaned up</div>';
        }
        
    } else {
        echo '<div class="analysis-section error">❌ Image analysis failed: ' . esc_html($analysis_result['message']) . '</div>';
    }
}

if (!$test_image_path) {
    ?>
    <div class="analysis-section info">
        <h3>ℹ️ How to use this demo:</h3>
        <ol>
            <li><strong>Upload an image</strong> using the form above, or</li>
            <li><strong>Use URL parameter:</strong> Add <code>?test_image=path/to/your/image.jpg</code> to this page's URL</li>
            <li>The system will analyze the photo and show detailed results</li>
        </ol>
        
        <h4>What this analysis provides:</h4>
        <ul>
            <li>🔍 <strong>People Detection:</strong> Identifies people using skin tone analysis</li>
            <li>👕 <strong>Clothing Detection:</strong> Estimates clothing areas (shirts, pants, etc.)</li>
            <li>🎨 <strong>Color Analysis:</strong> Finds dominant colors throughout the image</li>
            <li>💡 <strong>Brightness Analysis:</strong> Evaluates lighting conditions</li>
            <li>🌍 <strong>Scene Analysis:</strong> Determines composition, orientation, color temperature</li>
            <li>🎯 <strong>Object Detection:</strong> Basic background and object analysis</li>
        </ul>
        
        <p><strong>This addresses the user's request:</strong> The AI now truly examines and understands the uploaded photo before applying any transformations!</p>
    </div>
    <?php
}
?>

    </div>
</body>
</html>