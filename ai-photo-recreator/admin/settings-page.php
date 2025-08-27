<?php
/**
 * Settings page for AI Photo Recreator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ai_photo_recreator_settings')) {
    $options = array(
        'max_file_size' => absint($_POST['max_file_size']) * 1024 * 1024, // Convert MB to bytes
        'allowed_formats' => array_map('sanitize_text_field', $_POST['allowed_formats']),
        'rate_limit' => absint($_POST['rate_limit']),
        'api_key' => sanitize_text_field($_POST['api_key']),
        'auto_cleanup_days' => absint($_POST['auto_cleanup_days']),
        'enable_frontend' => isset($_POST['enable_frontend']) ? 1 : 0,
        'require_login' => isset($_POST['require_login']) ? 1 : 0
    );
    
    update_option('ai_photo_recreator_options', $options);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'ai-photo-recreator') . '</p></div>';
}

// Get current options
$options = get_option('ai_photo_recreator_options', array(
    'max_file_size' => 5242880, // 5MB
    'allowed_formats' => array('jpg', 'jpeg', 'png', 'webp'),
    'rate_limit' => 10,
    'api_key' => '',
    'auto_cleanup_days' => 7,
    'enable_frontend' => 1,
    'require_login' => 1
));

$max_file_size_mb = $options['max_file_size'] / 1024 / 1024;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('ai_photo_recreator_settings'); ?>
        
        <div class="ai-photo-recreator-settings">
            <!-- General Settings -->
            <div class="settings-section">
                <h2><?php _e('General Settings', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_frontend"><?php _e('Enable Frontend', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_frontend" name="enable_frontend" value="1" 
                                   <?php checked($options['enable_frontend'], 1); ?>>
                            <p class="description">
                                <?php _e('Enable the shortcode functionality for frontend use.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="require_login"><?php _e('Require Login', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="require_login" name="require_login" value="1" 
                                   <?php checked($options['require_login'], 1); ?>>
                            <p class="description">
                                <?php _e('Require users to be logged in to use the photo recreation feature.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- File Upload Settings -->
            <div class="settings-section" style="margin-top: 40px;">
                <h2><?php _e('File Upload Settings', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_file_size"><?php _e('Maximum File Size (MB)', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="max_file_size" name="max_file_size" 
                                   value="<?php echo esc_attr($max_file_size_mb); ?>" 
                                   min="1" max="100" step="1" class="small-text">
                            <p class="description">
                                <?php 
                                $server_max = wp_max_upload_size();
                                printf(
                                    __('Maximum file size for uploads. Server limit: %s', 'ai-photo-recreator'),
                                    size_format($server_max)
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Allowed File Formats', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Allowed File Formats', 'ai-photo-recreator'); ?></legend>
                                <label>
                                    <input type="checkbox" name="allowed_formats[]" value="jpg" 
                                           <?php checked(in_array('jpg', $options['allowed_formats'])); ?>>
                                    JPG
                                </label><br>
                                <label>
                                    <input type="checkbox" name="allowed_formats[]" value="jpeg" 
                                           <?php checked(in_array('jpeg', $options['allowed_formats'])); ?>>
                                    JPEG
                                </label><br>
                                <label>
                                    <input type="checkbox" name="allowed_formats[]" value="png" 
                                           <?php checked(in_array('png', $options['allowed_formats'])); ?>>
                                    PNG
                                </label><br>
                                <label>
                                    <input type="checkbox" name="allowed_formats[]" value="webp" 
                                           <?php checked(in_array('webp', $options['allowed_formats'])); ?>>
                                    WebP
                                </label>
                            </fieldset>
                            <p class="description">
                                <?php _e('Select which image formats are allowed for upload.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Rate Limiting Settings -->
            <div class="settings-section" style="margin-top: 40px;">
                <h2><?php _e('Rate Limiting', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rate_limit"><?php _e('Requests Per Hour', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="rate_limit" name="rate_limit" 
                                   value="<?php echo esc_attr($options['rate_limit']); ?>" 
                                   min="1" max="100" step="1" class="small-text">
                            <p class="description">
                                <?php _e('Maximum number of photo processing requests per user per hour.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- AI Service Settings -->
            <div class="settings-section" style="margin-top: 40px;">
                <h2><?php _e('AI Service Settings', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_key" name="api_key" 
                                   value="<?php echo esc_attr($options['api_key']); ?>" 
                                   class="regular-text">
                            <button type="button" id="show-api-key" class="button button-secondary">
                                <?php _e('Show', 'ai-photo-recreator'); ?>
                            </button>
                            <p class="description">
                                <?php _e('API key for the AI service (e.g., OpenAI, Stable Diffusion, etc.). Leave empty to use mock processing.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- File Management Settings -->
            <div class="settings-section" style="margin-top: 40px;">
                <h2><?php _e('File Management', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_cleanup_days"><?php _e('Auto Cleanup Days', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="auto_cleanup_days" name="auto_cleanup_days" 
                                   value="<?php echo esc_attr($options['auto_cleanup_days']); ?>" 
                                   min="1" max="365" step="1" class="small-text">
                            <p class="description">
                                <?php _e('Automatically delete processed files older than this many days. Set to 0 to disable auto cleanup.', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Storage Info', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <?php
                            $upload_dir = wp_upload_dir();
                            $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/';
                            
                            $total_size = 0;
                            $file_count = 0;
                            
                            if (is_dir($ai_dir)) {
                                $directories = array('original/', 'processed/', 'temp/');
                                foreach ($directories as $dir) {
                                    $full_dir = $ai_dir . $dir;
                                    if (is_dir($full_dir)) {
                                        $files = glob($full_dir . '*');
                                        foreach ($files as $file) {
                                            if (is_file($file)) {
                                                $total_size += filesize($file);
                                                $file_count++;
                                            }
                                        }
                                    }
                                }
                            }
                            ?>
                            <p>
                                <strong><?php _e('Total Files:', 'ai-photo-recreator'); ?></strong> <?php echo number_format($file_count); ?><br>
                                <strong><?php _e('Total Size:', 'ai-photo-recreator'); ?></strong> <?php echo size_format($total_size); ?>
                            </p>
                            <button type="button" id="manual-cleanup" class="button button-secondary">
                                <?php _e('Manual Cleanup Now', 'ai-photo-recreator'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- System Information -->
            <div class="settings-section" style="margin-top: 40px;">
                <h2><?php _e('System Information', 'ai-photo-recreator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Plugin Version', 'ai-photo-recreator'); ?></th>
                        <td><?php echo AI_PHOTO_RECREATOR_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('WordPress Version', 'ai-photo-recreator'); ?></th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('PHP Version', 'ai-photo-recreator'); ?></th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('GD Extension', 'ai-photo-recreator'); ?></th>
                        <td>
                            <?php if (extension_loaded('gd')): ?>
                                <span style="color: green;">✓ <?php _e('Installed', 'ai-photo-recreator'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('Not Installed', 'ai-photo-recreator'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Upload Directory', 'ai-photo-recreator'); ?></th>
                        <td>
                            <?php 
                            $upload_dir = wp_upload_dir();
                            $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/';
                            
                            if (is_writable($ai_dir)): ?>
                                <span style="color: green;">✓ <?php _e('Writable', 'ai-photo-recreator'); ?></span><br>
                                <code><?php echo esc_html($ai_dir); ?></code>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('Not Writable', 'ai-photo-recreator'); ?></span><br>
                                <code><?php echo esc_html($ai_dir); ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Show/hide API key
    $('#show-api-key').on('click', function() {
        const input = $('#api_key');
        const button = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('<?php _e('Hide', 'ai-photo-recreator'); ?>');
        } else {
            input.attr('type', 'password');
            button.text('<?php _e('Show', 'ai-photo-recreator'); ?>');
        }
    });
    
    // Manual cleanup
    $('#manual-cleanup').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to cleanup old files? This action cannot be undone.', 'ai-photo-recreator'); ?>')) {
            const button = $(this);
            button.prop('disabled', true).text('<?php _e('Cleaning up...', 'ai-photo-recreator'); ?>');
            
            $.post(ajaxurl, {
                action: 'ai_photo_cleanup',
                nonce: '<?php echo wp_create_nonce('ai_photo_cleanup_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Files cleaned up successfully. Page will refresh to update storage info.', 'ai-photo-recreator'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Error during cleanup:', 'ai-photo-recreator'); ?> ' + (response.data || 'Unknown error'));
                    button.prop('disabled', false).text('<?php _e('Manual Cleanup Now', 'ai-photo-recreator'); ?>');
                }
            }).fail(function() {
                alert('<?php _e('Request failed. Please try again.', 'ai-photo-recreator'); ?>');
                button.prop('disabled', false).text('<?php _e('Manual Cleanup Now', 'ai-photo-recreator'); ?>');
            });
        }
    });
    
    // Validate max file size against server limit
    $('#max_file_size').on('change', function() {
        const serverLimitMB = <?php echo wp_max_upload_size() / 1024 / 1024; ?>;
        const inputValue = parseFloat($(this).val());
        
        if (inputValue > serverLimitMB) {
            alert('<?php printf(__('Warning: The specified size exceeds the server limit of %s MB.', 'ai-photo-recreator'), ''); ?>' + serverLimitMB.toFixed(1));
        }
    });
});
</script>

<style>
.settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-bottom: 20px;
}

.settings-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #ccd0d4;
    padding-bottom: 10px;
}
</style>