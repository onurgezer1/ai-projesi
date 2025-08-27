<?php
/**
 * File Handler Class
 * 
 * Handles file uploads, validation and management
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

class AI_Photo_File_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize file handler
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file Uploaded file information
     * @return array Validation result
     */
    public function validate_file($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return array(
                'valid' => false,
                'message' => __('No file was uploaded', 'ai-photo-recreator')
            );
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'valid' => false,
                'message' => $this->get_upload_error_message($file['error'])
            );
        }
        
        // Get plugin options
        $options = get_option('ai_photo_recreator_options');
        
        // Check file size
        if ($file['size'] > $options['max_file_size']) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('File size exceeds maximum allowed size of %s', 'ai-photo-recreator'),
                    size_format($options['max_file_size'])
                )
            );
        }
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        $allowed_types = $options['allowed_formats'];
        
        if (!in_array($file_type['ext'], $allowed_types)) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('File type not allowed. Allowed types: %s', 'ai-photo-recreator'),
                    implode(', ', $allowed_types)
                )
            );
        }
        
        // Verify it's actually an image
        if (!$this->is_valid_image($file['tmp_name'])) {
            return array(
                'valid' => false,
                'message' => __('File is not a valid image', 'ai-photo-recreator')
            );
        }
        
        // Check image dimensions (optional - set reasonable limits)
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info) {
            $max_width = 4000;
            $max_height = 4000;
            
            if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
                return array(
                    'valid' => false,
                    'message' => sprintf(
                        __('Image dimensions exceed maximum allowed size of %dx%d pixels', 'ai-photo-recreator'),
                        $max_width,
                        $max_height
                    )
                );
            }
        }
        
        return array(
            'valid' => true,
            'message' => __('File is valid', 'ai-photo-recreator')
        );
    }
    
    /**
     * Save uploaded file
     * 
     * @param array $file Uploaded file information
     * @return array Save result
     */
    public function save_uploaded_file($file) {
        try {
            // Get upload directory
            $upload_dir = wp_upload_dir();
            $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/';
            
            // Create directories if they don't exist
            if (!file_exists($ai_dir)) {
                wp_mkdir_p($ai_dir);
            }
            
            if (!file_exists($ai_dir . 'original/')) {
                wp_mkdir_p($ai_dir . 'original/');
            }
            
            // Generate unique filename
            $file_info = pathinfo($file['name']);
            $filename = 'original_' . time() . '_' . uniqid() . '.' . $file_info['extension'];
            $file_path = $ai_dir . 'original/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $file_url = $upload_dir['baseurl'] . '/ai-photo-recreator/original/' . $filename;
                
                return array(
                    'success' => true,
                    'message' => __('File uploaded successfully', 'ai-photo-recreator'),
                    'file_path' => $file_path,
                    'file_url' => $file_url,
                    'filename' => $filename
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to save uploaded file', 'ai-photo-recreator')
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('An error occurred while saving the file', 'ai-photo-recreator')
            );
        }
    }
    
    /**
     * Check if file is a valid image
     * 
     * @param string $file_path Path to file
     * @return bool Whether file is valid image
     */
    private function is_valid_image($file_path) {
        // Check with getimagesize
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return false;
        }
        
        // Check MIME type
        $allowed_mime_types = array(
            'image/jpeg',
            'image/png',
            'image/webp'
        );
        
        if (!in_array($image_info['mime'], $allowed_mime_types)) {
            return false;
        }
        
        // Additional security check - verify file signature
        $file_handle = fopen($file_path, 'rb');
        if (!$file_handle) {
            return false;
        }
        
        $file_header = fread($file_handle, 12);
        fclose($file_handle);
        
        // Check file signatures
        $valid_signatures = array(
            'jpeg' => array("\xFF\xD8\xFF"),
            'png' => array("\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"),
            'webp' => array("RIFF", "WEBP")
        );
        
        $is_valid = false;
        foreach ($valid_signatures as $format => $signatures) {
            foreach ($signatures as $signature) {
                if (substr($file_header, 0, strlen($signature)) === $signature) {
                    $is_valid = true;
                    break 2;
                }
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Get upload error message
     * 
     * @param int $error_code Upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('File is too large', 'ai-photo-recreator');
            case UPLOAD_ERR_PARTIAL:
                return __('File was only partially uploaded', 'ai-photo-recreator');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'ai-photo-recreator');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing temporary folder', 'ai-photo-recreator');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'ai-photo-recreator');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension', 'ai-photo-recreator');
            default:
                return __('Unknown upload error', 'ai-photo-recreator');
        }
    }
    
    /**
     * Clean up old files
     * 
     * @param int $days_old Delete files older than this many days
     */
    public function cleanup_old_files($days_old = 7) {
        $upload_dir = wp_upload_dir();
        $ai_dir = $upload_dir['basedir'] . '/ai-photo-recreator/';
        
        $directories = array('original/', 'processed/', 'temp/');
        
        foreach ($directories as $dir) {
            $full_dir = $ai_dir . $dir;
            
            if (!is_dir($full_dir)) {
                continue;
            }
            
            $files = glob($full_dir . '*');
            $cutoff_time = time() - ($days_old * 24 * 60 * 60);
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Get file download URL
     * 
     * @param string $filename Filename
     * @param string $type File type (original|processed)
     * @return string Download URL
     */
    public function get_download_url($filename, $type = 'processed') {
        return add_query_arg(array(
            'action' => 'ai_photo_download',
            'file' => urlencode($filename),
            'type' => $type,
            'nonce' => wp_create_nonce('ai_photo_download_' . $filename)
        ), admin_url('admin-ajax.php'));
    }
    
    /**
     * Handle file download
     * 
     * @param string $filename Filename to download
     * @param string $type File type (original|processed)
     */
    public function handle_download($filename, $type = 'processed') {
        // Security checks
        if (!wp_verify_nonce($_GET['nonce'], 'ai_photo_download_' . $filename)) {
            wp_die(__('Security check failed', 'ai-photo-recreator'));
        }
        
        // Sanitize filename
        $filename = sanitize_file_name($filename);
        
        // Build file path
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/ai-photo-recreator/' . $type . '/' . $filename;
        
        // Check if file exists
        if (!file_exists($file_path)) {
            wp_die(__('File not found', 'ai-photo-recreator'));
        }
        
        // Get file info
        $file_info = pathinfo($file_path);
        $mime_type = wp_check_filetype($filename);
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type['type']);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        
        // Clean output buffer
        ob_clean();
        flush();
        
        // Output file
        readfile($file_path);
        exit;
    }
}