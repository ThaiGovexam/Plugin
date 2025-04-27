/**
 * AI Quiz Generator Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initializeTagsInput();
        initializeExamForm();
        initializeQueueSystem();
        initializeSettingsPage();
        initializeLogsPage();
    });

    // Tags input functionality
    function initializeTagsInput() {
        $('.tag-container').each(function() {
            const container = $(this);
            const input = container.find('.tag-input');
            const hiddenInput = container.next('input[type="hidden"]');
            const tags = [];

            // Add tag on Enter key
            input.on('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim() !== '') {
                    e.preventDefault();
                    const value = this.value.trim();
                    
                    if (!tags.includes(value)) {
                        addTag(container, value, tags);
                        updateHiddenInput(hiddenInput, tags);
                    }
                    
                    this.value = '';
                }
            });

            // Remove tag
            container.on('click', '.tag-remove', function() {
                const tag = $(this).parent();
                const value = tag.text().replace('×', '').trim();
                const index = tags.indexOf(value);
                
                if (index > -1) {
                    tags.splice(index, 1);
                    tag.remove();
                    updateHiddenInput(hiddenInput, tags);
                }
            });
        });
    }

    function addTag(container, value, tags) {
        const tag = $('<div class="tag">')
            .text(value)
            .append('<span class="tag-remove">&times;</span>');
        
        container.find('.tag-input').before(tag);
        tags.push(value);
    }

    function updateHiddenInput(input, tags) {
        input.val(JSON.stringify(tags));
    }

    // Exam form functionality
    function initializeExamForm() {
        const form = $('#generate-quiz-form');
        const progressContainer = $('#progress-container');
        const startButton = $('#start-generation');
        const addToQueueButton = $('#add-to-queue');
        const cancelButton = $('#cancel-generation');
        let generationInterval;

        // Start generation
        form.on('submit', function(e) {
            e.preventDefault();
            
            const data = {
                action: 'ai_quiz_generate',
                nonce: aiQuizAdmin.nonce,
                position: $('#position').val(),
                category_id: $('#category_id').val(),
                topics: JSON.parse($('#topics-hidden').val() || '[]'),
                questions_per_topic: $('#questions_per_topic').val()
            };

            if (data.topics.length === 0) {
                alert('กรุณาเพิ่มหัวข้อสำหรับออกข้อสอบอย่างน้อย 1 หัวข้อ');
                return;
            }

            startButton.prop('disabled', true).text('กำลังดำเนินการ...');
            form.hide();
            progressContainer.show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        startProgressMonitoring();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                        resetForm();
                    }
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
                    resetForm();
                }
            });
        });

        // Add to queue
        addToQueueButton.on('click', function() {
            const data = {
                action: 'ai_quiz_add_to_queue',
                nonce: aiQuizAdmin.nonce,
                position: $('#position').val(),
                topics: JSON.parse($('#topics-hidden').val() || '[]'),
                questions_per_topic: $('#questions_per_topic').val()
            };

            if (data.topics.length === 0) {
                alert('กรุณาเพิ่มหัวข้อสำหรับออกข้อสอบอย่างน้อย 1 หัวข้อ');
                return;
            }

            const button = $(this);
            button.prop('disabled', true).text('กำลังเพิ่มลงในคิว...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        alert('เพิ่มลงในคิวเรียบร้อยแล้ว');
                        window.location.href = 'admin.php?page=ai-quiz-queue';
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                    }
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
                },
                complete: function() {
                    button.prop('disabled', false).text('เพิ่มลงในคิว');
                }
            });
        });

        // Cancel generation
        cancelButton.on('click', function() {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกการสร้างข้อสอบ?')) {
                clearInterval(generationInterval);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_quiz_cancel_generation',
                        nonce: aiQuizAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('ยกเลิกการสร้างข้อสอบแล้ว');
                            resetForm();
                        }
                    }
                });
            }
        });

        function startProgressMonitoring() {
            generationInterval = setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_quiz_get_progress',
                        nonce: aiQuizAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateProgress(response.data);
                            
                            if (response.data.completed) {
                                clearInterval(generationInterval);
                                alert('สร้างข้อสอบเสร็จสิ้น!');
                                resetForm();
                            }
                        }
                    }
                });
            }, 2000);
        }

        function updateProgress(data) {
            $('#generation-progress').css('width', data.percentage + '%');
            $('#progress-text').text(data.percentage + '%');
            $('#status-text').text(data.status);
        }

        function resetForm() {
            form.show();
            progressContainer.hide();
            startButton.prop('disabled', false).text('เริ่มสร้างข้อสอบ');
        }
    }

    // Queue system functionality
    function initializeQueueSystem() {
        const filterForm = $('#status-filter').closest('form');
        const refreshButton = $('#refresh-queue');
        const modal = $('#queue-details-modal');

        // Status filter
        $('#status-filter').on('change', function() {
            window.location.href = 'admin.php?page=ai-quiz-queue&status=' + $(this).val();
        });

        // Refresh queue
        refreshButton.on('click', function() {
            window.location.reload();
        });

        // View details
        $('.view-details').on('click', function() {
            const queueId = $(this).data('id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_quiz_get_queue_details',
                    nonce: aiQuizAdmin.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        $('#queue-details-content').html(response.data.html);
                        modal.show();
                    }
                }
            });
        });

        // Start queue
        $('.start-queue').on('click', function() {
            const button = $(this);
            const queueId = button.data('id');
            
            button.prop('disabled', true).text('กำลังเริ่ม...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_quiz_start_queue',
                    nonce: aiQuizAdmin.nonce,
                    queue_id: queueId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                        button.prop('disabled', false).text('เริ่ม');
                    }
                }
            });
        });

        // Delete queue
        $('.delete-queue').on('click', function() {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?')) {
                const button = $(this);
                const queueId = button.data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_quiz_delete_queue',
                        nonce: aiQuizAdmin.nonce,
                        queue_id: queueId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut(400, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('เกิดข้อผิดพลาด: ' + response.data);
                        }
                    }
                });
            }
        });

        // Modal close
        $('.modal-close').on('click', function() {
            modal.hide();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).is(modal)) {
                modal.hide();
            }
        });
    }

    // Settings page functionality
    function initializeSettingsPage() {
        // Already handled in settings.php
    }

    // Logs page functionality
    function initializeLogsPage() {
        // Already handled in logs.php
    }

})(jQuery);
