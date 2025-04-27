<?php
/**
 * คลาสสำหรับจัดการระบบคิวข้อสอบ
 */

if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Queue_Manager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_quiz_queue';
    }
    
    /**
     * เพิ่มงานลงในคิว
     */
    public function add_to_queue($data) {
        global $wpdb;
        
        $queue_data = array(
            'position' => $data['position'],
            'topics' => json_encode($data['topics']),
            'questions_per_topic' => $data['questions_per_topic'],
            'total_questions' => $data['total_questions'],
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $this->table_name,
            $queue_data,
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * ดึงงานถัดไปจากคิว
     */
    public function get_next_in_queue() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT 1"
        );
    }
    
    /**
     * อัปเดตสถานะของงานในคิว
     */
    public function update_queue_status($queue_id, $status, $additional_data = array()) {
        global $wpdb;
        
        $update_data = array('status' => $status);
        $update_format = array('%s');
        
        if ($status === 'processing') {
            $update_data['started_at'] = current_time('mysql');
            $update_format[] = '%s';
        } elseif ($status === 'completed' || $status === 'failed') {
            $update_data['completed_at'] = current_time('mysql');
            $update_format[] = '%s';
        }
        
        // เพิ่มข้อมูลเพิ่มเติม
        if (!empty($additional_data)) {
            $update_data = array_merge($update_data, $additional_data);
            $update_format = array_merge($update_format, array_fill(0, count($additional_data), '%s'));
        }
        
        return $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $queue_id),
            $update_format,
            array('%d')
        );
    }
    
    /**
     * ประมวลผลงานถัดไปในคิว
     */
    public function process_next_in_queue() {
        $next_task = $this->get_next_in_queue();
        
        if (!$next_task) {
            return false;
        }
        
        // อัปเดตสถานะเป็น processing
        $this->update_queue_status($next_task->id, 'processing');
        
        try {
            // เริ่มประมวลผล
            $generator = new AI_Quiz_Generator();
            $result = $generator->generate_quiz(
                $next_task->position,
                json_decode($next_task->topics, true),
                $next_task->questions_per_topic
            );
            
            if ($result['success']) {
                // อัปเดตสถานะเป็น completed
                $this->update_queue_status($next_task->id, 'completed', array(
                    'result_data' => json_encode($result['data'])
                ));
                
                // บันทึกล็อก
                $this->log_queue_event($next_task->id, 'completed', 'Generated ' . $result['data']['total_generated'] . ' questions');
            } else {
                // อัปเดตสถานะเป็น failed
                $this->update_queue_status($next_task->id, 'failed', array(
                    'error_message' => $result['message']
                ));
                
                // บันทึกล็อก
                $this->log_queue_event($next_task->id, 'failed', $result['message']);
            }
            
        } catch (Exception $e) {
            // อัปเดตสถานะเป็น failed
            $this->update_queue_status($next_task->id, 'failed', array(
                'error_message' => $e->getMessage()
            ));
            
            // บันทึกล็อก
            $this->log_queue_event($next_task->id, 'error', $e->getMessage());
            
            return false;
        }
        
        return true;
    }
    
    /**
     * ดึงข้อมูลคิวทั้งหมด
     */
    public function get_all_queue_items($status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $where_clause = '';
        if ($status) {
            $where_clause = $wpdb->prepare("WHERE status = %s", $status);
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             {$where_clause}
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * ลบงานจากคิว
     */
    public function delete_queue_item($queue_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('id' => $queue_id),
            array('%d')
        );
    }
    
    /**
     * ดึงสถิติของคิว
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$this->table_name}"
        );
        
        return $stats;
    }
    
    /**
     * บันทึกล็อกของระบบคิว
     */
    private function log_queue_event($queue_id, $event_type, $message) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ai_quiz_logs',
            array(
                'queue_id' => $queue_id,
                'event_type' => $event_type,
                'message' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * ล้างคิวที่เก่าเกินไป
     */
    public function cleanup_old_queue_items($days = 30) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('completed', 'failed') 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * ตรวจสอบว่ามีงานที่กำลังประมวลผลอยู่หรือไม่
     */
    public function has_processing_tasks() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'"
        );
        
        return $count > 0;
    }
}
