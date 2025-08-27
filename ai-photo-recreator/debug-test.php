<?php
/**
 * Debug Test Script for AI Photo Recreator
 * This file helps diagnose plugin issues
 * 
 * USAGE: Add this file to your WordPress root and access it via browser
 * IMPORTANT: Remove this file after debugging for security!
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress
if (!file_exists('./wp-config.php')) {
    die('WordPress not found. Please place this file in your WordPress root directory.');
}
require_once('./wp-config.php');

echo "<h1>AI Photo Recreator Debug Test</h1>";

// Test 1: Check if WordPress loaded
echo "<h2>1. WordPress Status</h2>";
echo "WordPress loaded: " . (defined('ABSPATH') ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
echo "WordPress version: " . get_bloginfo('version') . "<br><br>";

// Test 2: Check PHP requirements
echo "<h2>2. PHP Requirements</h2>";
echo "PHP version: " . PHP_VERSION . " " . (version_compare(PHP_VERSION, '7.4', '>=') ? '<strong style="color:green">✓ OK</strong>' : '<strong style="color:red">✗ TOO OLD</strong>') . "<br>";
echo "GD Extension: " . (extension_loaded('gd') ? '<strong style="color:green">✓ Available</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";

if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "JPEG Support: " . (isset($gd_info['JPEG Support']) && $gd_info['JPEG Support'] ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
    echo "PNG Support: " . (isset($gd_info['PNG Support']) && $gd_info['PNG Support'] ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
    echo "WebP Support: " . (isset($gd_info['WebP Support']) && $gd_info['WebP Support'] ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
}
echo "<br>";

// Test 3: Check plugin status
echo "<h2>3. Plugin Status</h2>";
$plugin_path = 'ai-photo-recreator/ai-photo-recreator.php';
echo "Plugin exists: " . (file_exists(WP_PLUGIN_DIR . '/' . $plugin_path) ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
echo "Plugin active: " . (is_plugin_active($plugin_path) ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";

// Test 4: Check required classes
echo "<h2>4. Plugin Classes</h2>";
echo "AI_Photo_Recreator: " . (class_exists('AI_Photo_Recreator') ? '<strong style="color:green">✓ Available</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "AI_Photo_Processor: " . (class_exists('AI_Photo_Processor') ? '<strong style="color:green">✓ Available</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "AI_Photo_File_Handler: " . (class_exists('AI_Photo_File_Handler') ? '<strong style="color:green">✓ Available</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "AI_Photo_Recreator_Shortcode: " . (class_exists('AI_Photo_Recreator_Shortcode') ? '<strong style="color:green">✓ Available</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "<br>";

// Test 5: Check directories
echo "<h2>5. Directory Structure</h2>";
$upload_dir = wp_upload_dir();
$ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator';

echo "Upload dir: " . $upload_dir['basedir'] . " " . (is_dir($upload_dir['basedir']) ? '<strong style="color:green">✓ Exists</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "AI dir: " . $ai_dir . " " . (is_dir($ai_dir) ? '<strong style="color:green">✓ Exists</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "Original dir: " . $ai_dir . '/original' . " " . (is_dir($ai_dir . '/original') ? '<strong style="color:green">✓ Exists</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";
echo "Processed dir: " . $ai_dir . '/processed' . " " . (is_dir($ai_dir . '/processed') ? '<strong style="color:green">✓ Exists</strong>' : '<strong style="color:red">✗ Missing</strong>') . "<br>";

echo "<br>Directory permissions:<br>";
echo "Upload writable: " . (is_writable($upload_dir['basedir']) ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
if (is_dir($ai_dir)) {
    echo "AI dir writable: " . (is_writable($ai_dir) ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";
}
echo "<br>";

// Test 6: Check database table
echo "<h2>6. Database Table</h2>";
global $wpdb;
$table_name = $wpdb->prefix . 'ai_photo_history';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
echo "History table exists: " . ($table_exists ? '<strong style="color:green">✓ YES</strong>' : '<strong style="color:red">✗ NO</strong>') . "<br>";

if ($table_exists) {
    $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "Records in table: $record_count<br>";
}
echo "<br>";

// Test 7: Check AJAX endpoints
echo "<h2>7. AJAX Endpoints</h2>";
echo "These should be registered if the plugin is active:<br>";
echo "• wp_ajax_ai_photo_process<br>";
echo "• wp_ajax_nopriv_ai_photo_process<br>";
echo "• wp_ajax_ai_photo_download<br>";
echo "• wp_ajax_nopriv_ai_photo_download<br>";
echo "<br>";

// Test 8: Plugin options
echo "<h2>8. Plugin Options</h2>";
$options = get_option('ai_photo_recreator_options');
if ($options) {
    echo "Options found: <strong style='color:green'>✓ YES</strong><br>";
    echo "Max file size: " . size_format($options['max_file_size']) . "<br>";
    echo "Allowed formats: " . implode(', ', $options['allowed_formats']) . "<br>";
    echo "Rate limit: " . $options['rate_limit'] . " requests/hour<br>";
} else {
    echo "Options found: <strong style='color:red'>✗ NO</strong><br>";
}
echo "<br>";

// Test 9: Test shortcode
echo "<h2>9. Shortcode Test</h2>";
if (shortcode_exists('ai_photo_recreator')) {
    echo "Shortcode registered: <strong style='color:green'>✓ YES</strong><br>";
    echo "Try using: <code>[ai_photo_recreator]</code><br>";
} else {
    echo "Shortcode registered: <strong style='color:red'>✗ NO</strong><br>";
}

if (shortcode_exists('ai_photo_history')) {
    echo "History shortcode registered: <strong style='color:green'>✓ YES</strong><br>";
} else {
    echo "History shortcode registered: <strong style='color:red'>✗ NO</strong><br>";
}
echo "<br>";

// Final recommendations
echo "<h2>10. Recommendations</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa;'>";
echo "<strong>To fix the 'An error occurred' issue:</strong><br><br>";
echo "1. Make sure the plugin is activated<br>";
echo "2. Check that all directories are created and writable<br>";
echo "3. Verify GD extension is installed<br>";
echo "4. Check your server error logs for detailed error messages<br>";
echo "5. Try deactivating and reactivating the plugin<br>";
echo "<br>";
echo "<strong>Common Issues:</strong><br>";
echo "• Missing GD extension → Contact hosting provider<br>";
echo "• Permission issues → Check file/folder permissions (755/644)<br>";
echo "• Plugin conflicts → Test with other plugins disabled<br>";
echo "• Theme conflicts → Test with default theme<br>";
echo "</div>";

echo "<br><br>";
echo "<p><strong>Remember to delete this debug file after troubleshooting!</strong></p>";
?>