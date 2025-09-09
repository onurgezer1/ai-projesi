/**
 * AI Photo Recreator - Public JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Initialize public functionality
        initShortcodeForm();
        initFileHandling();
        initFormValidation();
        
    });
    
    /**
     * Initialize shortcode form functionality
     */
    function initShortcodeForm() {
        
        // Photo processing form
        $(document).on('submit', '#ai-photo-form', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $photoInput = $('#photo-upload');
            const $instructionsInput = $('#instructions');
            const $processBtn = $('#process-btn');
            
            // Validate inputs
            if (!validateForm($photoInput, $instructionsInput)) {
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
                    
                    console.log('Response:', response); // Debug logging
                    
                    if (response.success && response.data) {
                        showResults(response.data);
                        
                        // Show message if there's a warning or info message
                        if (response.data.message) {
                            showInfoMessage(response.data.message);
                        }
                        
                        // Reset form
                        $form[0].reset();
                        hideImagePreview();
                    } else {
                        // Handle both old format and new format responses
                        const errorMessage = response.data ? 
                            (typeof response.data === 'string' ? response.data : response.data.message) : 
                            (response.message || aiPhotoRecreator.strings.error);
                        showError(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    hideProcessing($processBtn);
                    hideProgress();
                    console.error('Ajax error:', error, xhr.responseText); // Enhanced debug logging
                    
                    // Try to parse error response
                    let errorMessage = aiPhotoRecreator.strings.error || 'An error occurred';
                    try {
                        if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                    
                    showError(errorMessage);
                }
            });
        });
        
        // Retry button
        $(document).on('click', '#retry-btn', function() {
            hideError();
        });
        
    }
    
    /**
     * Initialize file handling
     */
    function initFileHandling() {
        
        // File input change handler
        $(document).on('change', '#photo-upload', function(e) {
            const file = e.target.files[0];
            const $preview = $('#image-preview');
            const $previewImg = $('#preview-image');
            
            if (file) {
                // Validate file
                if (!validateFile(file)) {
                    $(this).val('');
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $previewImg.attr('src', e.target.result);
                    $preview.show();
                    
                    // Add fade-in effect
                    $preview.hide().fadeIn(300);
                };
                reader.readAsDataURL(file);
            } else {
                hideImagePreview();
            }
        });
        
        // Remove image button
        $(document).on('click', '#remove-image', function(e) {
            e.preventDefault();
            $('#photo-upload').val('');
            hideImagePreview();
        });
        
        // Drag and drop functionality
        const $uploadArea = $('#photo-upload');
        
        $uploadArea.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        });
        
        $uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
        });
        
        $uploadArea.on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                this.files = files;
                $(this).trigger('change');
            }
        });
        
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        
        // Real-time validation for instructions
        $(document).on('input', '#instructions', function() {
            const $input = $(this);
            const value = $input.val().trim();
            const minLength = 10;
            const maxLength = 500;
            
            // Remove previous validation classes
            $input.removeClass('valid invalid');
            
            if (value.length >= minLength && value.length <= maxLength) {
                $input.addClass('valid');
            } else if (value.length > 0) {
                $input.addClass('invalid');
            }
        });
        
    }
    
    /**
     * Validate form inputs
     */
    function validateForm($photoInput, $instructionsInput) {
        const file = $photoInput[0].files[0];
        const instructions = $instructionsInput.val().trim();
        
        // Check file
        if (!file) {
            showFieldError($photoInput, aiPhotoRecreator.strings.selectFile || 'Please select a file');
            return false;
        }
        
        // Validate file
        if (!validateFile(file)) {
            return false;
        }
        
        // Check instructions
        if (!instructions) {
            showFieldError($instructionsInput, aiPhotoRecreator.strings.enterInstructions || 'Please enter instructions');
            return false;
        }
        
        if (instructions.length < 10) {
            showFieldError($instructionsInput, 'Instructions must be at least 10 characters long');
            return false;
        }
        
        if (instructions.length > 500) {
            showFieldError($instructionsInput, 'Instructions must be less than 500 characters');
            return false;
        }
        
        // Clear any previous errors
        clearFieldErrors();
        
        return true;
    }
    
    /**
     * Validate uploaded file
     */
    function validateFile(file) {
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showError('Invalid file type. Allowed formats: JPEG, PNG, WebP');
            return false;
        }
        
        // Check file size (5MB default)
        const maxSize = 5242880;
        if (file.size > maxSize) {
            showError('File size exceeds maximum allowed size of 5MB');
            return false;
        }
        
        return true;
    }
    
    /**
     * Show field error
     */
    function showFieldError($field, message) {
        // Remove existing errors
        $field.siblings('.field-error').remove();
        
        // Add error class
        $field.addClass('error');
        
        // Add error message
        $field.after('<div class="field-error" style="color: #d63638; font-size: 12px; margin-top: 5px;">' + message + '</div>');
        
        // Focus field
        $field.focus();
    }
    
    /**
     * Clear field errors
     */
    function clearFieldErrors() {
        $('.field-error').remove();
        $('.error').removeClass('error');
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
        $('#progress-section').slideDown(300);
        
        // Animate progress bar
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 12;
            if (progress > 85) {
                progress = 85;
                clearInterval(progressInterval);
            }
            $('.progress-fill').css('width', progress + '%');
        }, 600);
        
        // Store interval for cleanup
        $('#progress-section').data('interval', progressInterval);
    }
    
    /**
     * Hide progress
     */
    function hideProgress() {
        const $progress = $('#progress-section');
        const interval = $progress.data('interval');
        
        if (interval) {
            clearInterval(interval);
        }
        
        // Complete progress bar
        $('.progress-fill').css('width', '100%');
        
        setTimeout(function() {
            $progress.slideUp(300);
            $('.progress-fill').css('width', '0%');
        }, 1000);
    }
    
    /**
     * Show results
     */
    function showResults(data) {
        $('#original-result').attr('src', data.original_url);
        $('#processed-result').attr('src', data.processed_url);
        $('#download-link').attr('href', data.download_url);
        
        // Animate results appearance
        $('#results-section').hide().slideDown(400, function() {
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#results-section').offset().top - 50
            }, 500);
        });
    }
    
    /**
     * Hide results
     */
    function hideResults() {
        $('#results-section').slideUp(300);
    }
    
    /**
     * Show error
     */
    function showError(message) {
        $('#error-section .error-message').text(message);
        $('#error-section').hide().slideDown(300);
        
        // Scroll to error
        $('html, body').animate({
            scrollTop: $('#error-section').offset().top - 50
        }, 500);
    }
    
    /**
     * Hide error
     */
    function hideError() {
        $('#error-section').slideUp(300);
    }
    
    /**
     * Show info message
     */
    function showInfoMessage(message) {
        // Create info section if it doesn't exist
        let $infoSection = $('#info-section');
        if ($infoSection.length === 0) {
            $infoSection = $('<div id="info-section" class="info-section" style="display:none; background:#e7f3ff; border:1px solid #b8daff; color:#0c5460; padding:15px; border-radius:4px; margin:20px 0;"><div class="info-message"></div><button type="button" class="close-info" style="background:none; border:none; float:right; cursor:pointer; margin-top:-5px;">×</button></div>');
            $('#results-section').before($infoSection);
        }
        
        $infoSection.find('.info-message').text(message);
        $infoSection.hide().slideDown(300);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            hideInfoMessage();
        }, 10000);
    }
    
    /**
     * Hide info message
     */
    function hideInfoMessage() {
        $('#info-section').slideUp(300);
    }
    
    /**
     * Hide image preview
     */
    function hideImagePreview() {
        $('#image-preview').fadeOut(300);
    }
    
    // Add click handler for close info button
    $(document).on('click', '.close-info', function() {
        hideInfoMessage();
    });
    
    /**
     * Add visual feedback for form interactions
     */
    function initVisualFeedback() {
        
        // Add focus effects
        $('input, textarea').on('focus', function() {
            $(this).parent().addClass('focused');
        }).on('blur', function() {
            $(this).parent().removeClass('focused');
        });
        
        // Add hover effects for buttons
        $('.process-button, .download-button, .retry-button').on('mouseenter', function() {
            $(this).addClass('hovered');
        }).on('mouseleave', function() {
            $(this).removeClass('hovered');
        });
        
    }
    
    // Initialize visual feedback
    initVisualFeedback();
    
})(jQuery);