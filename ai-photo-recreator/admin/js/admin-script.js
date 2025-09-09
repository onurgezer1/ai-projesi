/**
 * AI Photo Recreator - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize admin functionality
        initFileUpload();
        initFormHandling();
        initUtilities();
        
    });
    
    /**
     * Initialize file upload functionality
     */
    function initFileUpload() {
        
        // File input change handler
        $(document).on('change', 'input[type="file"]', function(e) {
            const file = e.target.files[0];
            const $input = $(this);
            const previewId = $input.data('preview') || $input.attr('id') + '-preview';
            const $preview = $('#' + previewId);
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert(aiPhotoRecreator.strings.invalidFileType || 'Invalid file type');
                    $input.val('');
                    return;
                }
                
                // Validate file size (get from PHP settings)
                const maxSize = 5242880; // 5MB default
                if (file.size > maxSize) {
                    alert(aiPhotoRecreator.strings.fileTooLarge || 'File too large');
                    $input.val('');
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.find('img').attr('src', e.target.result);
                    $preview.show();
                };
                reader.readAsDataURL(file);
            } else {
                $preview.hide();
            }
        });
        
        // Remove image button
        $(document).on('click', '.remove-image-btn', function(e) {
            e.preventDefault();
            const $preview = $(this).closest('.image-preview');
            const $input = $preview.siblings('input[type="file"]');
            
            $input.val('');
            $preview.hide();
        });
        
    }
    
    /**
     * Initialize form handling
     */
    function initFormHandling() {
        
        // Photo processing form
        $(document).on('submit', '#admin-photo-form', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $photoInput = $('#admin-photo-upload');
            const $instructionsInput = $('#admin-instructions');
            const $processBtn = $('#admin-process-btn');
            
            // Validate inputs
            if (!$photoInput[0].files[0]) {
                alert(aiPhotoRecreator.strings.selectFile || 'Please select a file');
                return;
            }
            
            if (!$instructionsInput.val().trim()) {
                alert(aiPhotoRecreator.strings.enterInstructions || 'Please enter instructions');
                return;
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'ai_photo_process');
            formData.append('photo', $photoInput[0].files[0]);
            formData.append('instructions', $instructionsInput.val());
            formData.append('nonce', aiPhotoRecreator.nonce);
            
            // Show processing state
            showProcessing($processBtn);
            showProgress();
            hideResults();
            hideError();
            
            // Submit form
            $.ajax({
                url: aiPhotoRecreator.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideProcessing($processBtn);
                    hideProgress();
                    
                    if (response.success) {
                        showResults(response.data);
                    } else {
                        showError(response.data || aiPhotoRecreator.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    hideProcessing($processBtn);
                    hideProgress();
                    showError(aiPhotoRecreator.strings.error || 'An error occurred');
                    console.error('Ajax error:', error);
                }
            });
        });
        
        // Retry button
        $(document).on('click', '#admin-retry-btn', function() {
            hideError();
        });
        
    }
    
    /**
     * Initialize utilities
     */
    function initUtilities() {
        
        // Cleanup files button
        $(document).on('click', '#cleanup-files-btn', function(e) {
            e.preventDefault();
            
            if (!confirm(aiPhotoRecreator.strings.confirmCleanup || 'Are you sure?')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text(aiPhotoRecreator.strings.processing || 'Processing...');
            
            $.post(aiPhotoRecreator.ajaxUrl, {
                action: 'ai_photo_cleanup',
                nonce: aiPhotoRecreator.nonce
            })
            .done(function(response) {
                if (response.success) {
                    alert(aiPhotoRecreator.strings.cleanupSuccess || 'Files cleaned up successfully');
                } else {
                    alert(aiPhotoRecreator.strings.cleanupError || 'Error during cleanup');
                }
            })
            .fail(function() {
                alert(aiPhotoRecreator.strings.error || 'An error occurred');
            })
            .always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        });
        
        // Auto-refresh stats (every 30 seconds)
        if ($('.stats-grid').length > 0) {
            setInterval(refreshStats, 30000);
        }
        
    }
    
    /**
     * Show processing state
     */
    function showProcessing($btn) {
        $btn.find('.btn-text').hide();
        $btn.find('.btn-spinner').show();
        $btn.prop('disabled', true);
    }
    
    /**
     * Hide processing state
     */
    function hideProcessing($btn) {
        $btn.find('.btn-text').show();
        $btn.find('.btn-spinner').hide();
        $btn.prop('disabled', false);
    }
    
    /**
     * Show progress
     */
    function showProgress() {
        $('#admin-progress-section').show();
        
        // Animate progress bar
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) {
                progress = 90;
                clearInterval(progressInterval);
            }
            $('.progress-fill').css('width', progress + '%');
        }, 500);
        
        // Store interval for cleanup
        $('#admin-progress-section').data('interval', progressInterval);
    }
    
    /**
     * Hide progress
     */
    function hideProgress() {
        const $progress = $('#admin-progress-section');
        const interval = $progress.data('interval');
        
        if (interval) {
            clearInterval(interval);
        }
        
        $('.progress-fill').css('width', '100%');
        
        setTimeout(function() {
            $progress.hide();
            $('.progress-fill').css('width', '0%');
        }, 1000);
    }
    
    /**
     * Show results
     */
    function showResults(data) {
        $('#admin-original-result').attr('src', data.original_url);
        $('#admin-processed-result').attr('src', data.processed_url);
        $('#admin-download-link').attr('href', data.download_url);
        $('#admin-results-section').show();
    }
    
    /**
     * Hide results
     */
    function hideResults() {
        $('#admin-results-section').hide();
    }
    
    /**
     * Show error
     */
    function showError(message) {
        $('#admin-error-section .error-message').text(message);
        $('#admin-error-section').show();
    }
    
    /**
     * Hide error
     */
    function hideError() {
        $('#admin-error-section').hide();
    }
    
    /**
     * Refresh statistics
     */
    function refreshStats() {
        $.post(aiPhotoRecreator.ajaxUrl, {
            action: 'ai_photo_get_stats',
            nonce: aiPhotoRecreator.nonce
        })
        .done(function(response) {
            if (response.success && response.data) {
                updateStatsDisplay(response.data);
            }
        })
        .fail(function() {
            console.log('Failed to refresh stats');
        });
    }
    
    /**
     * Update stats display
     */
    function updateStatsDisplay(stats) {
        $('.stat-box').each(function() {
            const $box = $(this);
            const $value = $box.find('h3');
            const label = $box.find('p').text().toLowerCase();
            
            if (label.includes('total processed') && stats.total_processed !== undefined) {
                animateCounter($value, stats.total_processed);
            } else if (label.includes('processing') && stats.total_processing !== undefined) {
                animateCounter($value, stats.total_processing);
            } else if (label.includes('errors') && stats.total_errors !== undefined) {
                animateCounter($value, stats.total_errors);
            } else if (label.includes('today') && stats.today_processed !== undefined) {
                animateCounter($value, stats.today_processed);
            }
        });
    }
    
    /**
     * Animate counter
     */
    function animateCounter($element, targetValue) {
        const currentValue = parseInt($element.text().replace(/,/g, '')) || 0;
        
        if (currentValue !== targetValue) {
            $({ counter: currentValue }).animate({
                counter: targetValue
            }, {
                duration: 1000,
                easing: 'swing',
                step: function() {
                    $element.text(Math.floor(this.counter).toLocaleString());
                },
                complete: function() {
                    $element.text(targetValue.toLocaleString());
                }
            });
        }
    }
    
})(jQuery);