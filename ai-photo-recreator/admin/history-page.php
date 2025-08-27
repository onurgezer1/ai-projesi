<?php
/**
 * History page for AI Photo Recreator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] !== '-1') {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_action_history')) {
        wp_die(__('Security check failed'));
    }
    
    $action = sanitize_text_field($_POST['action']);
    $ids = array_map('absint', $_POST['history_ids']);
    
    if (!empty($ids)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_photo_history';
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", $ids));
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Selected records deleted.', 'ai-photo-recreator') . '</p></div>';
                break;
            case 'reprocess':
                $wpdb->query($wpdb->prepare("UPDATE $table_name SET status = 'processing' WHERE id IN ($ids_placeholder)", $ids));
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Selected records marked for reprocessing.', 'ai-photo-recreator') . '</p></div>';
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$user_filter = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($current_page - 1) * $per_page;

// Build query
global $wpdb;
$table_name = $wpdb->prefix . 'ai_photo_history';

$where_conditions = array('1=1');
$query_params = array();

if ($status_filter !== 'all') {
    $where_conditions[] = 'status = %s';
    $query_params[] = $status_filter;
}

if ($user_filter > 0) {
    $where_conditions[] = 'user_id = %d';
    $query_params[] = $user_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = 'DATE(created_at) >= %s';
    $query_params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = 'DATE(created_at) <= %s';
    $query_params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$total_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
if (!empty($query_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $query_params));
} else {
    $total_items = $wpdb->get_var($total_query);
}

// Get records
$records_query = "SELECT h.*, u.display_name 
                  FROM $table_name h 
                  LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID 
                  WHERE $where_clause 
                  ORDER BY h.created_at DESC 
                  LIMIT %d OFFSET %d";

$final_params = array_merge($query_params, array($per_page, $offset));
$records = $wpdb->get_results($wpdb->prepare($records_query, $final_params));

// Calculate pagination
$total_pages = ceil($total_items / $per_page);

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Filters -->
    <div class="history-filters" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="ai-photo-recreator-history">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div>
                    <label for="status"><?php _e('Status', 'ai-photo-recreator'); ?></label><br>
                    <select name="status" id="status">
                        <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All Statuses', 'ai-photo-recreator'); ?></option>
                        <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php _e('Processing', 'ai-photo-recreator'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'ai-photo-recreator'); ?></option>
                        <option value="error" <?php selected($status_filter, 'error'); ?>><?php _e('Error', 'ai-photo-recreator'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label for="user_id"><?php _e('User', 'ai-photo-recreator'); ?></label><br>
                    <?php 
                    wp_dropdown_users(array(
                        'name' => 'user_id',
                        'id' => 'user_id',
                        'selected' => $user_filter,
                        'show_option_all' => __('All Users', 'ai-photo-recreator')
                    )); 
                    ?>
                </div>
                
                <div>
                    <label for="date_from"><?php _e('Date From', 'ai-photo-recreator'); ?></label><br>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                </div>
                
                <div>
                    <label for="date_to"><?php _e('Date To', 'ai-photo-recreator'); ?></label><br>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                </div>
                
                <div>
                    <button type="submit" class="button button-secondary"><?php _e('Filter', 'ai-photo-recreator'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=ai-photo-recreator-history'); ?>" class="button button-secondary">
                        <?php _e('Reset', 'ai-photo-recreator'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Results summary -->
    <div style="margin-bottom: 20px;">
        <p>
            <?php printf(__('Showing %d of %d records', 'ai-photo-recreator'), count($records), $total_items); ?>
            <?php if ($status_filter !== 'all' || $user_filter > 0 || !empty($date_from) || !empty($date_to)): ?>
                <em>(<?php _e('filtered', 'ai-photo-recreator'); ?>)</em>
            <?php endif; ?>
        </p>
    </div>
    
    <?php if (!empty($records)): ?>
        <form method="post" action="">
            <?php wp_nonce_field('bulk_action_history'); ?>
            
            <!-- Bulk actions -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="action">
                        <option value="-1"><?php _e('Bulk Actions', 'ai-photo-recreator'); ?></option>
                        <option value="delete"><?php _e('Delete', 'ai-photo-recreator'); ?></option>
                        <option value="reprocess"><?php _e('Mark for Reprocessing', 'ai-photo-recreator'); ?></option>
                    </select>
                    <button type="submit" class="button action" onclick="return confirm('<?php esc_attr_e('Are you sure you want to perform this action?', 'ai-photo-recreator'); ?>')">
                        <?php _e('Apply', 'ai-photo-recreator'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Records table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="cb-select-all">
                        </td>
                        <th><?php _e('ID', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('User', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('Instructions', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('Status', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('Created', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('Completed', 'ai-photo-recreator'); ?></th>
                        <th><?php _e('Actions', 'ai-photo-recreator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="history_ids[]" value="<?php echo esc_attr($record->id); ?>">
                            </th>
                            <td><strong><?php echo esc_html($record->id); ?></strong></td>
                            <td>
                                <?php if ($record->display_name): ?>
                                    <?php echo esc_html($record->display_name); ?><br>
                                    <small style="color: #666;">ID: <?php echo esc_html($record->user_id); ?></small>
                                <?php else: ?>
                                    <em><?php _e('Unknown User', 'ai-photo-recreator'); ?></em><br>
                                    <small style="color: #666;">ID: <?php echo esc_html($record->user_id); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="max-width: 300px;">
                                    <?php echo esc_html(wp_trim_words($record->instructions, 15)); ?>
                                    <?php if (str_word_count($record->instructions) > 15): ?>
                                        <a href="#" class="show-full-instructions" data-instructions="<?php echo esc_attr($record->instructions); ?>">
                                            <?php _e('Show more', 'ai-photo-recreator'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status_classes = array(
                                    'processing' => 'status-processing',
                                    'completed' => 'status-completed',
                                    'error' => 'status-error'
                                );
                                $status_class = isset($status_classes[$record->status]) ? $status_classes[$record->status] : '';
                                ?>
                                <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($record->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html(mysql2date('Y-m-d H:i:s', $record->created_at)); ?>
                            </td>
                            <td>
                                <?php 
                                if ($record->completed_at): 
                                    echo esc_html(mysql2date('Y-m-d H:i:s', $record->completed_at));
                                else:
                                    echo '<em>' . __('Not completed', 'ai-photo-recreator') . '</em>';
                                endif; 
                                ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <?php if ($record->status === 'completed' && file_exists($record->original_file)): ?>
                                        <span>
                                            <a href="#" class="view-images" 
                                               data-original="<?php echo esc_attr(wp_upload_dir()['baseurl'] . '/ai-photo-recreator/original/' . basename($record->original_file)); ?>"
                                               data-processed="<?php echo esc_attr(wp_upload_dir()['baseurl'] . '/ai-photo-recreator/processed/' . basename($record->processed_file)); ?>">
                                                <?php _e('View Images', 'ai-photo-recreator'); ?>
                                            </a> |
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($record->status === 'completed' && !empty($record->processed_file)): ?>
                                        <?php
                                        $file_handler = new AI_Photo_File_Handler();
                                        $filename = basename($record->processed_file);
                                        $download_url = $file_handler->get_download_url($filename);
                                        ?>
                                        <span>
                                            <a href="<?php echo esc_url($download_url); ?>">
                                                <?php _e('Download', 'ai-photo-recreator'); ?>
                                            </a> |
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span>
                                        <a href="#" class="delete-record" data-id="<?php echo esc_attr($record->id); ?>" style="color: #a00;">
                                            <?php _e('Delete', 'ai-photo-recreator'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo; Previous'),
                            'next_text' => __('Next &raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page,
                            'mid_size' => 2
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div class="notice notice-info">
            <p><?php _e('No processing history found.', 'ai-photo-recreator'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Image viewer modal -->
<div id="image-viewer-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; max-width: 90%; max-height: 90%; overflow: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3><?php _e('Image Comparison', 'ai-photo-recreator'); ?></h3>
            <button id="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4><?php _e('Original', 'ai-photo-recreator'); ?></h4>
                <img id="modal-original" src="" alt="Original" style="max-width: 100%; height: auto;">
            </div>
            <div>
                <h4><?php _e('Processed', 'ai-photo-recreator'); ?></h4>
                <img id="modal-processed" src="" alt="Processed" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#cb-select-all').on('change', function() {
        $('input[name="history_ids[]"]').prop('checked', this.checked);
    });
    
    // Show full instructions
    $('.show-full-instructions').on('click', function(e) {
        e.preventDefault();
        const instructions = $(this).data('instructions');
        alert(instructions);
    });
    
    // View images modal
    $('.view-images').on('click', function(e) {
        e.preventDefault();
        const originalSrc = $(this).data('original');
        const processedSrc = $(this).data('processed');
        
        $('#modal-original').attr('src', originalSrc);
        $('#modal-processed').attr('src', processedSrc);
        $('#image-viewer-modal').show();
    });
    
    // Close modal
    $('#close-modal, #image-viewer-modal').on('click', function(e) {
        if (e.target === this) {
            $('#image-viewer-modal').hide();
        }
    });
    
    // Delete single record
    $('.delete-record').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e('Are you sure you want to delete this record?', 'ai-photo-recreator'); ?>')) {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            
            $.post(ajaxurl, {
                action: 'ai_photo_delete_record',
                id: id,
                nonce: '<?php echo wp_create_nonce('ai_photo_delete_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert('<?php _e('Error deleting record', 'ai-photo-recreator'); ?>');
                }
            });
        }
    });
});
</script>

<style>
.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-processing {
    background: #ffb900;
    color: white;
}

.status-completed {
    background: #00a32a;
    color: white;
}

.status-error {
    background: #d63638;
    color: white;
}

.row-actions span {
    display: inline;
}

.history-filters label {
    font-weight: 600;
}

.history-filters select,
.history-filters input[type="date"] {
    width: 100%;
}
</style>