<?php
/**
 * System logs page view
 */

if (!defined('ABSPATH')) {
    exit;
}

// ดึงข้อมูลล็อก
global $wpdb;
$logs_table = $wpdb->prefix . 'ai_quiz_logs';

// Pagination
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filter
$event_type = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
$date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';

// สร้าง query
$where_conditions = array();
$where_values = array();

if ($event_type) {
    $where_conditions[] = "event_type = %s";
    $where_values[] = $event_type;
}

if ($date_filter) {
    $where_conditions[] = "DATE(created_at) = %s";
    $where_values[] = $date_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// นับจำนวนทั้งหมด
$total_items = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $logs_table $where_clause",
    $where_values
));

// ดึงข้อมูลล็อก
$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $logs_table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
    array_merge($where_values, array($per_page, $offset))
));

// ดึง event types ที่มีอยู่
$event_types = $wpdb->get_col("SELECT DISTINCT event_type FROM $logs_table ORDER BY event_type");
?>

<div class="wrap">
    <h1><?php _e('ล็อกระบบ', 'ai-quiz-generator'); ?></h1>
    
    <!-- ตัวกรอง -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="ai-quiz-logs">
            
            <div class="alignleft actions">
                <select name="event_type">
                    <option value=""><?php _e('ทุกประเภท', 'ai-quiz-generator'); ?></option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($event_type, $type); ?>>
                            <?php echo esc_html($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_filter" value="<?php echo esc_attr($date_filter); ?>" placeholder="<?php esc_attr_e('เลือกวันที่', 'ai-quiz-generator'); ?>">
                
                <input type="submit" class="button" value="<?php esc_attr_e('กรอง', 'ai-quiz-generator'); ?>">
            </div>
            
            <div class="alignright actions">
                <button type="button" class="button" id="clear-logs">
                    <i class="material-icons" style="vertical-align: middle;">delete_forever</i>
                    <?php _e('ล้างล็อกเก่า', 'ai-quiz-generator'); ?>
                </button>
                
                <button type="button" class="button" id="export-logs">
                    <i class="material-icons" style="vertical-align: middle;">file_download</i>
                    <?php _e('ส่งออก CSV', 'ai-quiz-generator'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- ตารางล็อก -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-time"><?php _e('เวลา', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="column-type"><?php _e('ประเภท', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="column-message"><?php _e('ข้อความ', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="column-context"><?php _e('บริบท', 'ai-quiz-generator'); ?></th>
                <th scope="col" class="column-queue"><?php _e('คิว ID', 'ai-quiz-generator'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" class="text-center">
                        <?php _e('ไม่พบข้อมูลล็อก', 'ai-quiz-generator'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td>
                            <?php
                            $type_class = '';
                            switch ($log->event_type) {
                                case 'error':
                                    $type_class = 'error';
                                    break;
                                case 'warning':
                                    $type_class = 'warning';
                                    break;
                                case 'success':
                                    $type_class = 'success';
                                    break;
                                case 'info':
                                    $type_class = 'info';
                                    break;
                            }
                            ?>
                            <span class="log-type <?php echo esc_attr($type_class); ?>">
                                <?php echo esc_html($log->event_type); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php
                            if ($log->context) {
                                $context = json_decode($log->context, true);
                                if (is_array($context)) {
                                    echo '<pre class="log-context">' . esc_html(print_r($context, true)) . '</pre>';
                                } else {
                                    echo esc_html($log->context);
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($log->queue_id): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-quiz-queue&queue_id=' . $log->queue_id)); ?>">
                                    #<?php echo esc_html($log->queue_id); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php
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

<style>
.column-time {
    width: 160px;
}

.column-type {
    width: 100px;
}

.column-queue {
    width: 80px;
}

.log-type {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.log-type.error {
    background: #f8d7da;
    color: #721c24;
}

.log-type.warning {
    background: #fff3cd;
    color: #856404;
}

.log-type.success {
    background: #d4edda;
    color: #155724;
}

.log-type.info {
    background: #cce5ff;
    color: #004085;
}

.log-context {
    font-size: 11px;
    background: #f5f5f5;
    padding: 5px;
    margin: 5px 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 100px;
    overflow-y: auto;
}

.text-center {
    text-align: center;
}

.material-icons {
    font-size: 16px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Clear old logs
    $('#clear-logs').on('click', function() {
        if (!confirm('<?php esc_attr_e('คุณแน่ใจหรือไม่ว่าต้องการล้างล็อกที่เก่ากว่า 30 วัน?', 'ai-quiz-generator'); ?>')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.html();
        
        button.prop('disabled', true).html('<i class="material-icons">sync</i> กำลังล้างล็อก...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_quiz_clear_old_logs',
                nonce: aiQuizAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการล้างล็อก');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Export logs
    $('#export-logs').on('click', function() {
        window.location.href = ajaxurl + '?action=ai_quiz_export_logs&nonce=' + aiQuizAdmin.nonce;
    });
});
</script>
