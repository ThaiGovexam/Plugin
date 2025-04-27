<?php
/**
 * Settings page view
 */

if (!defined('ABSPATH')) {
    exit;
}

// บันทึกการตั้งค่า
if (isset($_GET['settings-updated'])) {
    add_settings_error('ai_quiz_messages', 'ai_quiz_message', __('บันทึกการตั้งค่าเรียบร้อยแล้ว', 'ai-quiz-generator'), 'updated');
}

// แสดงข้อความแจ้งเตือน
settings_errors('ai_quiz_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('ai_quiz_settings');
        do_settings_sections('ai_quiz_settings');
        ?>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="material-icons">api</i>
                </div>
                <h2 class="card-title"><?php _e('ตั้งค่า API', 'ai-quiz-generator'); ?></h2>
            </div>
            
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_quiz_api_key"><?php _e('Claude API Key', 'ai-quiz-generator'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="ai_quiz_api_key" 
                                   name="ai_quiz_api_key" 
                                   value="<?php echo esc_attr(get_option('ai_quiz_api_key')); ?>" 
                                   class="regular-text">
                            <button type="button" class="button button-secondary" id="toggle-api-key">
                                <i class="material-icons">visibility</i>
                            </button>
                            <p class="description">
                                <?php _e('API Key จาก Anthropic สำหรับเข้าถึง Claude AI', 'ai-quiz-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ai_quiz_request_delay"><?php _e('เวลารอระหว่างการเรียก API', 'ai-quiz-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="ai_quiz_request_delay" 
                                   name="ai_quiz_request_delay" 
                                   value="<?php echo esc_attr(get_option('ai_quiz_request_delay', 2000)); ?>" 
                                   min="1000" 
                                   max="10000" 
                                   class="small-text"> มิลลิวินาที
                            <p class="description">
                                <?php _e('ค่าแนะนำ: 2000 มิลลิวินาที (2 วินาที)', 'ai-quiz-generator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="material-icons">settings</i>
                </div>
                <h2 class="card-title"><?php _e('ตั้งค่าทั่วไป', 'ai-quiz-generator'); ?></h2>
            </div>
            
            <div class="card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_quiz_default_category"><?php _e('หมวดหมู่เริ่มต้น', 'ai-quiz-generator'); ?></label>
                        </th>
                        <td>
                            <?php
                            $ays_integration = new AYS_Quiz_Integration();
                            $categories = $ays_integration->get_all_categories();
                            $selected_category = get_option('ai_quiz_default_category', 3);
                            ?>
                            <select id="ai_quiz_default_category" name="ai_quiz_default_category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" 
                                            <?php selected($selected_category, $category->id); ?>>
                                        <?php echo esc_html($category->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ai_quiz_column_b_value"><?php _e('ค่าสำหรับคอลัมน์ B', 'ai-quiz-generator'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ai_quiz_column_b_value" 
                                   name="ai_quiz_column_b_value" 
                                   value="<?php echo esc_attr(get_option('ai_quiz_column_b_value', '-')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('ค่าเริ่มต้นสำหรับคอลัมน์ B ในตารางข้อสอบ', 'ai-quiz-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ai_quiz_column_c_pattern"><?php _e('รูปแบบคอลัมน์ C', 'ai-quiz-generator'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ai_quiz_column_c_pattern" 
                                   name="ai_quiz_column_c_pattern" 
                                   value="<?php echo esc_attr(get_option('ai_quiz_column_c_pattern', 'แนวข้อสอบ {position} ชุดที่ {set}')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('ใช้ {position} แทนชื่อตำแหน่ง และ {set} แทนชุดที่', 'ai-quiz-generator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="material-icons">cached</i>
                </div>
                <h2 class="card-title"><?php _e('จัดการแคช', 'ai-quiz-generator'); ?></h2>
            </div>
            
            <div class="card-body">
                <p><?php _e('ระบบจะเก็บข้อมูลแคชเพื่อลดการเรียกใช้ API โดยจะหมดอายุภายใน 6 ชั่วโมง', 'ai-quiz-generator'); ?></p>
                
                <div class="form-group">
                    <button type="button" class="button button-secondary" id="clear-cache">
                        <i class="material-icons">delete_sweep</i>
                        <?php _e('ล้างแคชทั้งหมด', 'ai-quiz-generator'); ?>
                    </button>
                </div>
                
                <div id="cache-status" class="notice notice-info inline" style="display: none;">
                    <p id="cache-status-message"></p>
                </div>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #eee;
}

.card-icon {
    background-color: #e8f0fe;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.card-icon i {
    color: #4285f4;
    font-size: 20px;
}

.card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.button i {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 5px;
}

#toggle-api-key {
    vertical-align: middle;
    margin-left: 5px;
}

.notice.inline {
    margin: 15px 0 0 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle API Key visibility
    $('#toggle-api-key').on('click', function() {
        const apiKeyInput = $('#ai_quiz_api_key');
        const icon = $(this).find('i');
        
        if (apiKeyInput.attr('type') === 'password') {
            apiKeyInput.attr('type', 'text');
            icon.text('visibility_off');
        } else {
            apiKeyInput.attr('type', 'password');
            icon.text('visibility');
        }
    });
    
    // Clear cache
    $('#clear-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.prop('disabled', true).html('<i class="material-icons">sync</i> กำลังล้างแคช...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_quiz_clear_cache',
                nonce: aiQuizAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#cache-status').show().find('#cache-status-message').text(response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการล้างแคช');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
