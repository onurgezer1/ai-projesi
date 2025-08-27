<?php
/**
 * Admin page for AI Photo Recreator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ai-photo-recreator-admin">
        <!-- Upload and Process Section -->
        <div class="admin-section">
            <h2><?php _e('Process Photo', 'ai-photo-recreator'); ?></h2>
            <form id="admin-photo-form" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="admin-photo-upload"><?php _e('Select Photo', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <input type="file" id="admin-photo-upload" name="photo" accept="image/*" required>
                            <div id="admin-image-preview" class="image-preview" style="display: none; margin-top: 10px;">
                                <img id="admin-preview-image" src="" alt="Preview" style="max-width: 300px; height: auto;">
                            </div>
                            <p class="description">
                                <?php 
                                $options = get_option('ai_photo_recreator_options');
                                printf(
                                    __('Max file size: %s. Allowed formats: %s', 'ai-photo-recreator'),
                                    size_format($options['max_file_size']),
                                    implode(', ', $options['allowed_formats'])
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="admin-instructions"><?php _e('Instructions', 'ai-photo-recreator'); ?></label>
                        </th>
                        <td>
                            <textarea id="admin-instructions" name="instructions" rows="4" cols="50" required
                                      placeholder="<?php esc_attr_e('Describe how you want the photo to be recreated...', 'ai-photo-recreator'); ?>"></textarea>
                            <p class="description">
                                <?php _e('Examples: "Make this person smile", "Change background to nature", "Convert to vintage style"', 'ai-photo-recreator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" id="admin-process-btn" class="button button-primary">
                        <span class="btn-text"><?php _e('Process Photo', 'ai-photo-recreator'); ?></span>
                        <span class="btn-spinner" style="display: none;">
                            <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                            <?php _e('Processing...', 'ai-photo-recreator'); ?>
                        </span>
                    </button>
                </p>
            </form>
            
            <!-- Progress Section -->
            <div id="admin-progress-section" style="display: none; margin-top: 20px;">
                <div style="background: #f0f0f1; border-left: 4px solid #0073aa; padding: 12px;">
                    <p><?php _e('Processing your photo, please wait...', 'ai-photo-recreator'); ?></p>
                    <div class="progress-bar" style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div class="progress-fill" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div id="admin-results-section" style="display: none; margin-top: 20px;">
                <h3><?php _e('Results', 'ai-photo-recreator'); ?></h3>
                <div class="results-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                    <div class="result-item">
                        <h4><?php _e('Original Photo', 'ai-photo-recreator'); ?></h4>
                        <div style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                            <img id="admin-original-result" src="" alt="Original" style="max-width: 100%; height: auto;">
                        </div>
                    </div>
                    <div class="result-item">
                        <h4><?php _e('AI Processed Photo', 'ai-photo-recreator'); ?></h4>
                        <div style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                            <img id="admin-processed-result" src="" alt="Processed" style="max-width: 100%; height: auto;">
                        </div>
                        <p style="text-align: center; margin-top: 10px;">
                            <a id="admin-download-link" href="" class="button button-secondary" download>
                                <?php _e('Download Processed Image', 'ai-photo-recreator'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Error Section -->
            <div id="admin-error-section" style="display: none; margin-top: 20px;">
                <div class="notice notice-error">
                    <p class="error-message"></p>
                </div>
                <button type="button" id="admin-retry-btn" class="button">
                    <?php _e('Try Again', 'ai-photo-recreator'); ?>
                </button>
            </div>
        </div>
        
        <!-- Statistics Section -->
        <div class="admin-section" style="margin-top: 40px;">
            <h2><?php _e('Usage Statistics', 'ai-photo-recreator'); ?></h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_photo_history';
            
            // Get statistics
            $total_processed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
            $total_processing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing'");
            $total_errors = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'error'");
            $today_processed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND DATE(created_at) = %s",
                current_time('Y-m-d')
            ));
            ?>
            
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #0073aa;"><?php echo number_format($total_processed); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Total Processed', 'ai-photo-recreator'); ?></p>
                </div>
                <div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #d63638;"><?php echo number_format($total_processing); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Currently Processing', 'ai-photo-recreator'); ?></p>
                </div>
                <div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #dba617;"><?php echo number_format($total_errors); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Errors', 'ai-photo-recreator'); ?></p>
                </div>
                <div class="stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #00a32a;"><?php echo number_format($today_processed); ?></h3>
                    <p style="margin: 5px 0 0 0;"><?php _e('Processed Today', 'ai-photo-recreator'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Section -->
        <div class="admin-section" style="margin-top: 40px;">
            <h2><?php _e('Quick Actions', 'ai-photo-recreator'); ?></h2>
            <div class="quick-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo admin_url('admin.php?page=ai-photo-recreator-settings'); ?>" class="button button-secondary">
                    <?php _e('Plugin Settings', 'ai-photo-recreator'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-photo-recreator-history'); ?>" class="button button-secondary">
                    <?php _e('View History', 'ai-photo-recreator'); ?>
                </a>
                <button type="button" id="cleanup-files-btn" class="button button-secondary">
                    <?php _e('Cleanup Old Files', 'ai-photo-recreator'); ?>
                </button>
            </div>
        </div>
        
        <!-- Shortcode Info Section -->
        <div class="admin-section" style="margin-top: 40px;">
            <h2><?php _e('How to Use', 'ai-photo-recreator'); ?></h2>
            <div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px;">
                <h4><?php _e('Frontend Usage', 'ai-photo-recreator'); ?></h4>
                <p><?php _e('Add the following shortcode to any page or post:', 'ai-photo-recreator'); ?></p>
                <code style="background: #fff; padding: 5px; border: 1px solid #ddd; display: inline-block;">[ai_photo_recreator]</code>
                
                <h4 style="margin-top: 20px;"><?php _e('Shortcode Parameters', 'ai-photo-recreator'); ?></h4>
                <ul style="margin-left: 20px;">
                    <li><code>title</code> - <?php _e('Custom title for the form', 'ai-photo-recreator'); ?></li>
                    <li><code>show_title</code> - <?php _e('Show/hide title (true/false)', 'ai-photo-recreator'); ?></li>
                    <li><code>max_width</code> - <?php _e('Maximum width of the form container', 'ai-photo-recreator'); ?></li>
                </ul>
                
                <h4 style="margin-top: 20px;"><?php _e('Example', 'ai-photo-recreator'); ?></h4>
                <code style="background: #fff; padding: 5px; border: 1px solid #ddd; display: inline-block;">[ai_photo_recreator title="Transform Your Photos" max_width="600px"]</code>
                
                <h4 style="margin-top: 20px;"><?php _e('History Shortcode', 'ai-photo-recreator'); ?></h4>
                <p><?php _e('Show user processing history:', 'ai-photo-recreator'); ?></p>
                <code style="background: #fff; padding: 5px; border: 1px solid #ddd; display: inline-block;">[ai_photo_history]</code>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // File preview
    $('#admin-photo-upload').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#admin-preview-image').attr('src', e.target.result);
                $('#admin-image-preview').show();
            };
            reader.readAsDataURL(file);
        } else {
            $('#admin-image-preview').hide();
        }
    });
    
    // Form submission
    $('#admin-photo-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const photoFile = $('#admin-photo-upload')[0].files[0];
        const instructions = $('#admin-instructions').val();
        
        if (!photoFile) {
            alert('<?php _e('Please select a photo', 'ai-photo-recreator'); ?>');
            return;
        }
        
        if (!instructions.trim()) {
            alert('<?php _e('Please enter instructions', 'ai-photo-recreator'); ?>');
            return;
        }
        
        formData.append('action', 'ai_photo_process');
        formData.append('photo', photoFile);
        formData.append('instructions', instructions);
        formData.append('nonce', '<?php echo wp_create_nonce('ai_photo_recreator_nonce'); ?>');
        
        // Show progress
        $('#admin-process-btn .btn-text').hide();
        $('#admin-process-btn .btn-spinner').show();
        $('#admin-process-btn').prop('disabled', true);
        $('#admin-progress-section').show();
        $('#admin-results-section').hide();
        $('#admin-error-section').hide();
        
        // Animate progress bar
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 20;
            if (progress > 90) progress = 90;
            $('.progress-fill').css('width', progress + '%');
        }, 500);
        
        // Submit form
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                clearInterval(progressInterval);
                $('.progress-fill').css('width', '100%');
                
                setTimeout(function() {
                    $('#admin-progress-section').hide();
                    
                    if (response.success) {
                        $('#admin-original-result').attr('src', response.data.original_url);
                        $('#admin-processed-result').attr('src', response.data.processed_url);
                        $('#admin-download-link').attr('href', response.data.download_url);
                        $('#admin-results-section').show();
                    } else {
                        $('#admin-error-section .error-message').text(response.data || 'An error occurred');
                        $('#admin-error-section').show();
                    }
                    
                    // Reset button
                    $('#admin-process-btn .btn-text').show();
                    $('#admin-process-btn .btn-spinner').hide();
                    $('#admin-process-btn').prop('disabled', false);
                }, 1000);
            },
            error: function() {
                clearInterval(progressInterval);
                $('#admin-progress-section').hide();
                $('#admin-error-section .error-message').text('<?php _e('An error occurred during processing', 'ai-photo-recreator'); ?>');
                $('#admin-error-section').show();
                
                // Reset button
                $('#admin-process-btn .btn-text').show();
                $('#admin-process-btn .btn-spinner').hide();
                $('#admin-process-btn').prop('disabled', false);
            }
        });
    });
    
    // Retry button
    $('#admin-retry-btn').on('click', function() {
        $('#admin-error-section').hide();
    });
    
    // Cleanup files
    $('#cleanup-files-btn').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to cleanup old files? This action cannot be undone.', 'ai-photo-recreator'); ?>')) {
            $(this).prop('disabled', true).text('<?php _e('Cleaning up...', 'ai-photo-recreator'); ?>');
            
            $.post(ajaxurl, {
                action: 'ai_photo_cleanup',
                nonce: '<?php echo wp_create_nonce('ai_photo_cleanup_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Files cleaned up successfully', 'ai-photo-recreator'); ?>');
                } else {
                    alert('<?php _e('Error during cleanup', 'ai-photo-recreator'); ?>');
                }
                $('#cleanup-files-btn').prop('disabled', false).text('<?php _e('Cleanup Old Files', 'ai-photo-recreator'); ?>');
            });
        }
    });
});
</script>