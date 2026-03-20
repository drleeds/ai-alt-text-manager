jQuery(document).ready(function($) {
    'use strict';

    // Initialize selection storage
    var selectedImages = JSON.parse(sessionStorage.getItem('ai_alt_text_selected') || '[]');

    // Restore checked state on page load
    selectedImages.forEach(function(id) {
        $('.image-select[value="' + id + '"]').prop('checked', true);
    });

    // Update selection count display
    function updateSelectionCount() {
        var count = selectedImages.length;
        if (count > 0) {
            if (!$('#selection-count').length) {
                $('.ai-alt-text-actions').prepend(
                    '<span id="selection-count" style="margin-right: 15px; font-weight: 600; color: #2271b1;">' +
                    count + ' image(s) selected across all pages' +
                    '</span>'
                );
            } else {
                $('#selection-count').text(count + ' image(s) selected across all pages');
            }
        } else {
            $('#selection-count').remove();
        }
    }

    // Initial count update
    updateSelectionCount();

    // Track checkbox changes
    $(document).on('change', '.image-select', function() {
        var id = $(this).val();
        if ($(this).is(':checked')) {
            if (selectedImages.indexOf(id) === -1) {
                selectedImages.push(id);
            }
        } else {
            selectedImages = selectedImages.filter(function(item) {
                return item !== id;
            });
        }
        sessionStorage.setItem('ai_alt_text_selected', JSON.stringify(selectedImages));
        updateSelectionCount();
    });

    // Select all images on current page
    $('#select-all').on('click', function() {
        $('.image-select').each(function() {
            $(this).prop('checked', true);
            var id = $(this).val();
            if (selectedImages.indexOf(id) === -1) {
                selectedImages.push(id);
            }
        });
        sessionStorage.setItem('ai_alt_text_selected', JSON.stringify(selectedImages));
        updateSelectionCount();
    });

    // Deselect all images (all pages)
    $('#deselect-all').on('click', function() {
        $('.image-select').prop('checked', false);
        selectedImages = [];
        sessionStorage.setItem('ai_alt_text_selected', JSON.stringify(selectedImages));
        updateSelectionCount();
    });

    // Select all images across all pages
    $('#select-all-pages').on('click', function() {
        var allIds = $('.ai-alt-text-grid').data('all-image-ids');
        if (allIds) {
            selectedImages = allIds.toString().split(',');
            sessionStorage.setItem('ai_alt_text_selected', JSON.stringify(selectedImages));

            // Check all visible checkboxes
            $('.image-select').each(function() {
                var id = $(this).val();
                if (selectedImages.indexOf(id) !== -1) {
                    $(this).prop('checked', true);
                }
            });

            updateSelectionCount();
            alert('Selected ' + selectedImages.length + ' images across all pages!');
        }
    });

    // Process selected images
    $('#process-selected').on('click', function() {
        // Use stored selection, not just visible checkboxes
        if (selectedImages.length === 0) {
            alert(aiAltTextManager.strings.selectImages);
            return;
        }

        if (!confirm('Generate AI alt text for ' + selectedImages.length + ' selected image(s) across all pages?')) {
            return;
        }

        // Disable button and show loading
        $('#process-selected').prop('disabled', true).text('Loading images...');

        // Fetch all image data via AJAX
        $.ajax({
            url: aiAltTextManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_alt_text_get_images',
                nonce: aiAltTextManager.nonce,
                image_ids: selectedImages
            },
            success: function(response) {
                if (response.success) {
                    var imagesToProcess = response.data.images.map(function(img) {
                        return {
                            id: img.id,
                            url: img.url,
                            context: img.context,
                            title: img.title,
                            $element: $('.ai-alt-text-item[data-id="' + img.id + '"]')
                        };
                    });

                    processImages(imagesToProcess);
                } else {
                    alert('Error loading images: ' + response.data);
                    $('#process-selected').prop('disabled', false).text('Generate Alt Text for Selected');
                }
            },
            error: function() {
                alert('Network error loading images');
                $('#process-selected').prop('disabled', false).text('Generate Alt Text for Selected');
            }
        });
    });

    /**
     * Process multiple images sequentially
     */
    function processImages(imagesToProcess) {
        const total = imagesToProcess.length;
        let current = 0;
        let successCount = 0;
        let errorCount = 0;

        // Create and show progress UI
        $('#processing-container').html(
            '<div id="processing-status">' +
            '<div class="notice notice-info">' +
            '<p><strong id="processing-text">' + aiAltTextManager.strings.processing + '...</strong></p>' +
            '<div class="progress-bar">' +
            '<div class="progress-fill" id="progress-fill" style="width: 0%;"></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div id="results-log">' +
            '<h3>Processing Results</h3>' +
            '<div id="results-content"></div>' +
            '</div>'
        );

        $('#process-selected').prop('disabled', true);

        // Hide pagination during processing
        $('.ai-alt-text-pagination').hide();

        function processNext() {
            if (current >= total) {
                // All done - clear sessionStorage
                selectedImages = [];
                sessionStorage.setItem('ai_alt_text_selected', JSON.stringify(selectedImages));
                updateSelectionCount();

                $('#processing-text').html(
                    '<span style="color: green;">✓ ' + aiAltTextManager.strings.success + '</span> ' +
                    successCount + ' successful, ' + errorCount + ' errors'
                );
                $('#process-selected').prop('disabled', false);
                $('.ai-alt-text-pagination').show();
                return;
            }

            const imageData = imagesToProcess[current];
            const $item = imageData.$element;
            const attachmentId = imageData.id;
            const imageUrl = imageData.url;
            const context = imageData.context;
            const imageTitle = imageData.title;
            const isVisible = $item.length > 0;

            current++;
            const progress = Math.round((current / total) * 100);

            // Update progress
            $('#progress-fill').css('width', progress + '%').text(progress + '%');
            $('#processing-text').text(
                aiAltTextManager.strings.processing + ' ' +
                current + ' ' + aiAltTextManager.strings.of + ' ' + total
            );

            // Mark item as processing (only if visible)
            if (isVisible) {
                $item.addClass('processing');
                $item.find('.ai-alt-text-status')
                    .addClass('processing')
                    .text(aiAltTextManager.strings.analyzing);
            }

            // Make AJAX request
            $.ajax({
                url: aiAltTextManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_alt_text_analyze_image',
                    nonce: aiAltTextManager.nonce,
                    attachment_id: attachmentId,
                    image_url: imageUrl,
                    context: context
                },
                success: function(response) {
                    if (isVisible) {
                        $item.removeClass('processing');
                    }

                    if (response.success) {
                        successCount++;

                        if (isVisible) {
                            $item.addClass('success');
                            $item.find('.ai-alt-text-status')
                                .removeClass('processing')
                                .addClass('success')
                                .text('✓ ' + response.data.message);

                            // Show new alt text
                            $item.find('.ai-alt-text-new')
                                .show()
                                .find('.alt-text-value')
                                .text(response.data.alt_text);

                            // Update current alt text display
                            $item.find('.ai-alt-text-current .alt-text-value')
                                .removeClass('empty')
                                .text(response.data.alt_text);
                        }

                        // Log success (use stored title if not visible)
                        var displayTitle = isVisible ? $item.find('.ai-alt-text-title strong').text() : imageTitle;
                        $('#results-content').append(
                            '<div class="result-success">' +
                            '✓ <strong>' + displayTitle + ':</strong> ' +
                            response.data.alt_text +
                            '</div>'
                        );
                    } else {
                        errorCount++;

                        if (isVisible) {
                            $item.addClass('error');
                            $item.find('.ai-alt-text-status')
                                .removeClass('processing')
                                .addClass('error')
                                .text('✗ ' + aiAltTextManager.strings.error + ' ' + response.data);
                        }

                        // Log error
                        var displayTitle = isVisible ? $item.find('.ai-alt-text-title strong').text() : imageTitle;
                        $('#results-content').append(
                            '<div class="result-error">' +
                            '✗ <strong>' + displayTitle + ':</strong> ' +
                            response.data +
                            '</div>'
                        );
                    }

                    // Scroll results log to bottom
                    $('#results-content').scrollTop($('#results-content')[0].scrollHeight);

                    // Process next image after a short delay
                    setTimeout(processNext, 500);
                },
                error: function(xhr, status, error) {
                    errorCount++;

                    if (isVisible) {
                        $item.removeClass('processing').addClass('error');
                        $item.find('.ai-alt-text-status')
                            .removeClass('processing')
                            .addClass('error')
                            .text('✗ Network error');
                    }

                    // Log error
                    var displayTitle = isVisible ? $item.find('.ai-alt-text-title strong').text() : imageTitle;
                    $('#results-content').append(
                        '<div class="result-error">' +
                        '✗ <strong>' + displayTitle + ':</strong> ' +
                        'Network error' +
                        '</div>'
                    );

                    // Continue to next image
                    setTimeout(processNext, 500);
                }
            });
        }

        // Start processing
        processNext();
    }
});
