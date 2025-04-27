<?php
/**
 * Dashboard page view
 */

if (!defined('ABSPATH')) {
    exit;
}

// ดึงข้อมูลที่จำเป็น
$ays_integration = new AYS_Quiz_Integration();
$categories = $ays_integration->get_all_categories();
$default_category = get_option('ai_quiz_default_category', 3);

// ดึงสถิติ
$queue_manager = new Quiz_Queue_Manager();
$queue_stats = $queue_manager->get_queue_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- แสดงการแจ้งเตือน -->
    <?php if (!get_option('ai_quiz_api_key')): ?>
    <div class="notice notice-warning">
        <p>
            <i class="material-icons" style="vertical-align: middle;">warning</i>
            <?php _e('กรุณาตั้งค่า API Key ก่อนเริ่มใช้งานระบบ', 'ai-quiz-generator'); ?>
            <a href="<?php echo admin_url('admin.php?page=ai-quiz-settings'); ?>" class="button button-primary" style="margin-left: 10px;">
                <?php _e('ไปยังหน้าตั้งค่า', 'ai-quiz-generator'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- สถิติรวม -->
    <div class="ai-quiz-stats-container">
        <div class="stats-card">
            <div class="stats-icon" style="background-color: #e8f0fe;">
                <i class="material-icons" style="color: #4285f4;">queue</i>
            </div>
            <div class="stats-content">
                <h3><?php _e('งานในคิว', 'ai-quiz-generator'); ?></h3>
                <p class="stats-number"><?php echo intval($queue_stats->pending); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background-color: #e6f4ea;">
                <i class="material-icons" style="color: #34a853;">check_circle</i>
            </div>
            <div class="stats-content">
                <h3><?php _e('เสร็จสิ้น', 'ai-quiz-generator'); ?></h3>
                <p class="stats-number"><?php echo intval($queue_stats->completed); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background-color: #fef7e0;">
                <i class="material-icons" style="color: #fbbc05;">sync</i>
            </div>
            <div class="stats-content">
                <h3><?php _e('กำลังดำเนินการ', 'ai-quiz-generator'); ?></h3>
                <p class="stats-number"><?php echo intval($queue_stats->processing); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon" style="background-color: #fce8e6;">
                <i class="material-icons" style="color: #ea4335;">error</i>
            </div>
            <div class="stats-content">
                <h3><?php _e('ผิดพลาด', 'ai-quiz-generator'); ?></h3>
                <p class="stats-number"><?php echo intval($queue_stats->failed); ?></p>
            </div>
        </div>
    </div>
    
    <!-- ฟอร์มสร้างข้อสอบ -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon">
                <i class="material-icons">auto_awesome</i>
            </div>
            <h2 class="card-title"><?php _e('สร้างข้อสอบอัตโนมัติ', 'ai-quiz-generator'); ?></h2>
        </div>
        
        <div class="card-body">
            <form id="generate-quiz-form" method="post">
                <?php wp_nonce_field('ai_quiz_generate', 'ai_quiz_nonce'); ?>
                
                <div class="form-group">
                    <label for="position" class="form-label">
                        <?php _e('ชื่อตำแหน่ง', 'ai-quiz-generator'); ?>
                    </label>
                    <input type="text" id="position" name="position" class="form-control" 
                           placeholder="<?php esc_attr_e('เช่น นักวิชาการคอมพิวเตอร์', 'ai-quiz-generator'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id" class="form-label">
                        <?php _e('หมวดหมู่', 'ai-quiz-generator'); ?>
                    </label>
                    <select id="category_id" name="category_id" class="form-control">
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->id); ?>" 
                                <?php selected($category->id, $default_category); ?>>
                            <?php echo esc_html($category->title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <?php _e('หัวข้อสำหรับออกข้อสอบ', 'ai-quiz-generator'); ?>
                    </label>
                    <div class="tag-container" id="topics-container">
                        <input type="text" class="tag-input" id="topic-input" 
                               placeholder="<?php esc_attr_e('พิมพ์หัวข้อแล้วกด Enter', 'ai-quiz-generator'); ?>">
                    </div>
                    <small class="form-text text-muted">
                        <?php _e('กด Enter เพื่อเพิ่มหัวข้อใหม่', 'ai-quiz-generator'); ?>
                    </small>
                    <input type="hidden" name="topics" id="topics-hidden" value="">
                </div>
                
                <div class="form-group">
                    <label for="questions_per_topic" class="form-label">
                        <?php _e('จำนวนข้อสอบต่อหัวข้อ', 'ai-quiz-generator'); ?>
                    </label>
                    <input type="number" id="questions_per_topic" name="questions_per_topic" 
                           class="form-control small-text" min="1" max="50" value="10" required>
                    <small class="form-text text-muted">
                        <?php _e('แนะนำไม่เกิน 10 ข้อต่อหัวข้อ', 'ai-quiz-generator'); ?>
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="button button-primary" id="start-generation">
                        <i class="material-icons">auto_awesome</i>
                        <?php _e('เริ่มสร้างข้อสอบ', 'ai-quiz-generator'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="add-to-queue">
                        <i class="material-icons">queue</i>
                        <?php _e('เพิ่มลงในคิว', 'ai-quiz-generator'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- แสดงความคืบหน้า -->
    <div id="progress-container" class="card" style="display: none;">
        <div class="card-header">
            <div class="card-icon">
                <i class="material-icons">sync</i>
            </div>
            <h2 class="card-title"><?php _e('สถานะการสร้างข้อสอบ', 'ai-quiz-generator'); ?></h2>
        </div>
        
        <div class="card-body">
            <div class="progress-bar-container">
                <div class="progress-bar" id="generation-progress" style="width: 0%;"></div>
            </div>
            
            <div class="progress-info">
                <p>
                    <strong><?php _e('ความคืบหน้า:', 'ai-quiz-generator'); ?></strong>
                    <span id="progress-text">0%</span>
                </p>
                <p>
                    <strong><?php _e('สถานะ:', 'ai-quiz-generator'); ?></strong>
                    <span id="status-text"><?php _e('เริ่มต้น...', 'ai-quiz-generator'); ?></span>
                </p>
            </div>
            
            <div class="form-group">
                <button type="button" class="button button-secondary" id="cancel-generation">
                    <i class="material-icons">cancel</i>
                    <?php _e('ยกเลิก', 'ai-quiz-generator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.ai-quiz-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.stats-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #5f6368;
}

.stats-number {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #dadce0;
}

.card-icon {
    background-color: #e8f0fe;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.card-icon i {
    color: #4285f4;
}

.card-title {
    margin: 0;
    font-size: 18px;
}

.card-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #dadce0;
    border-radius: 4px;
}

.form-control.small-text {
    width: 80px;
}

.tag-container {
    border: 1px solid #dadce0;
    border-radius: 4px;
    padding: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 42px;
}

.tag {
    background-color: #e8f0fe;
    color: #4285f4;
    padding: 4px 12px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    font-size: 14px;
}

.tag-remove {
    margin-left: 8px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
}

.tag-input {
    border: none;
    outline: none;
    flex: 1;
    min-width: 120px;
    padding: 4px;
}

.progress-bar-container {
    background-color: #e0e0e0;
    border-radius: 4px;
    height: 20px;
    overflow: hidden;
    margin-bottom: 20px;
}

.progress-bar {
    background-color: #4285f4;
    height: 100%;
    transition: width 0.3s ease;
}

.button i {
    margin-right: 5px;
    vertical-align: middle;
}
</style>
