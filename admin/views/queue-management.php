<?php
/**
 * Queue management page view
 */

if (!defined('ABSPATH')) {
    exit;
}

// ดึงข้อมูลคิว
$queue_manager = new Quiz_Queue_Manager();
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$queue_items = $queue_manager->get_all_queue_items($status_filter, $per_page, $offset);
$queue_stats = $queue_manager->get_queue_stats();
?>

<div class="wrap">
    <h1><?php _e('ระบบคิวข้อสอบ', 'ai-quiz-generator'); ?></h1>
    
    <!-- แสดงสถิติ -->
    <div class="queue-stats">
        <div class="stat-box">
            <span class="stat-label"><?php _e('ทั้งหมด:', 'ai-quiz-generator'); ?></span>
            <span class="stat-value"><?php echo intval($queue_stats->total); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-label"><?php _e('รอดำเนินการ:', 'ai-quiz-generator'); ?></span>
            <span class="stat-value"><?php echo intval($queue_stats->pending); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-label"><?php _e('กำลังดำเนินการ:', 'ai-quiz-generator'); ?></span>
            <span class="stat-value"><?php echo intval($queue_stats->processing); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-label"><?php _e('เสร็จสิ้น:', 'ai-quiz-generator'); ?></span>
            <span class="stat-value"><?php echo intval($queue_stats->completed); ?></span>
        </div>
        <div class="stat-box">
            <span class="stat-label"><?php _e('ผิดพลาด:', 'ai-quiz-generator'); ?></span>
            <span class="stat-value"><?php echo intval($queue_stats->failed); ?></span>
        </div>
    </div>
    
    <!-- ตัวกรองสถานะ -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="status_filter" id="status-filter">
                <option value=""><?php _e('ทั้งหมด', 'ai-quiz-generator'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('รอดำเนินการ', 'ai-quiz-generator'); ?></option>
                <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php _e('กำลังดำเนินการ', 'ai-quiz-generator'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('เสร็จสิ้น', 'ai-quiz-generator'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('ผิดพลาด', 'ai-quiz-generator'); ?></option>
            </select>
            <button type="button" class="button" id="filter-submit"><?php _e('กรอง', 'ai-quiz-generator'); ?></button>
        </div>
        
        <div class="alignright actions">
            <button type="button" class="button" id="refresh-queue">
                <i class="material-icons" style="vertical-align: middle;">refresh</i>
                <?php _e('รีเฟรช', 'ai-quiz-generator'); ?>
            </button>
        </div>
    </div>
    
    <!-- ตารางแสดงคิว -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-id"><?php _e('ID', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-position"><?php _e('ตำแหน่ง', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-topics"><?php _e('หัวข้อ', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-questions"><?php _e('จำนวนข้อ', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-status"><?php _e('สถานะ', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-created"><?php _e('สร้างเมื่อ', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php _e('การจัดการ', 'ai-quiz-generator'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($queue_items)): ?>
                <tr>
                    <td colspan="7" class="text-center">
                        <?php _e('ไม่พบข้อมูลในคิว', 'ai-quiz-generator'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($queue_items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->id); ?></td>
                        <td><?php echo esc_html($item->position); ?></td>
                        <td>
                            <?php 
                            $topics = json_decode($item->topics, true);
                            echo esc_html(is_array($topics) ? implode(', ', $topics) : $item->topics);
                            ?>
                        </td>
                        <td><?php echo esc_html($item->total_questions); ?></td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            
                            switch ($item->status) {
                                case 'pending':
                                    $status_class = 'warning';
                                    $status_text = __('รอดำเนินการ', 'ai-quiz-generator');
                                    break;
                                case 'processing':
                                    $status_class = 'info';
                                    $status_text = __('กำลังดำเนินการ', 'ai-quiz-generator');
                                    break;
                                case 'completed':
                                    $status_class = 'success';
                                    $status_text = __('เสร็จสิ้น', 'ai-quiz-generator');
                                    break;
                                case 'failed':
                                    $status_class = 'error';
                                    $status_text = __('ผิดพลาด', 'ai-quiz-generator');
                                    break;
                            }
                            ?>
                            <span class="queue-status <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?></td>
                        <td>
                            <?php if ($item->status === 'pending'): ?>
                                <button type="button" class="button button-small start-queue" data-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('เริ่ม', 'ai-quiz-generator'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($item->status === 'failed'): ?>
                                <button type="button" class="button button-small retry-queue" data-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('ลองใหม่', 'ai-quiz-generator'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="button button-small view-details" data-id="<?php echo esc_attr($item->id); ?>">
                                <?php _e('รายละเอียด', 'ai-quiz-generator'); ?>
                            </button>
                            
                            <?php if ($item->status !== 'processing'): ?>
                                <button type="button" class="button button-small button-link-delete delete-queue" data-id="<?php echo esc_attr($item->id); ?>">
                                    <?php _e('ลบ', 'ai-quiz-generator'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php
    $total_items = intval($queue_stats->total);
    $total_pages = ceil($total_items / $per_page);
    
    if ($total_pages > 1):
    ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal สำหรับแสดงรายละเอียด -->
<div id="queue-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('รายละเอียดคิว', 'ai-quiz-generator'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="queue-details-content">
                <!-- จะถูกเพิ่มด้วย JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button modal-close"><?php _e('ปิด', 'ai-quiz-generator'); ?></button>
        </div>
    </div>
</div>

<style>
.queue-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.stat-box {
    background: #fff;
    padding: 10px 20px;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
}

.stat-label {
    color: #646970;
    margin-right: 5px;
}

.stat-value {
    font-weight: 600;
}

.queue-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.queue-status.warning {
    background: #fff3cd;
    color: #856404;
}

.queue-status.info {
    background: #cce5ff;
    color: #004085;
}

.queue-status.success {
    background: #d4edda;
    color: #155724;
}

.queue-status.error {
    background: #f8d7da;
    color: #721c24;
}

.modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 4px;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    color: #666;
}

.modal-close:hover {
    color: #000;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.text-center {
    text-align: center;
}
</style>
