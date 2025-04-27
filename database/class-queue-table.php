<?php
/**
 * คลาสสำหรับสร้างและจัดการตารางฐานข้อมูลของระบบคิว
 */

if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Queue_Table {
    
    /**
     * สร้างตารางฐานข้อมูล
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตารางคิวหลัก
        $queue_table = $wpdb->prefix . 'ai_quiz_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            position varchar(255) NOT NULL,
            topics text NOT NULL,
            questions_per_topic int(11) NOT NULL,
            total_questions int(11) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            result_data longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status_idx (status),
            KEY created_at_idx (created_at)
        ) $charset_collate;";
        
        // ตารางล็อก
        $logs_table = $wpdb->prefix . 'ai_quiz_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) unsigned DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            message text NOT NULL,
            context text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY queue_id_idx (queue_id),
            KEY event_type_idx (event_type),
            KEY created_at_idx (created_at)
        ) $charset_collate;";
        
        // ตารางแคช
        $cache_table = $wpdb->prefix . 'ai_quiz_cache';
        $sql_cache = "CREATE TABLE IF NOT EXISTS $cache_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key_idx (cache_key),
            KEY expiration_idx (expiration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_queue);
        dbDelta($sql_logs);
        dbDelta($sql_cache);
        
        // เพิ่ม version ของตาราง
        add_option('ai_quiz_db_version', '1.0.0');
    }
    
    /**
     * ตรวจสอบและอัปเดตตาราง
     */
    public function check_table_updates() {
        $current_version = get_option('ai_quiz_db_version', '0');
        $target_version = '1.0.0';
        
        if (version_compare($current_version, $target_version, '<')) {
            $this->create_table();
            update_option('ai_quiz_db_version', $target_version);
        }
    }
    
    /**
     * ลบตารางทั้งหมด
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ai_quiz_queue',
            $wpdb->prefix . 'ai_quiz_logs',
            $wpdb->prefix . 'ai_quiz_cache'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('ai_quiz_db_version');
    }
    
    /**
     * ล้างข้อมูลในตาราง
     */
    public function truncate_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ai_quiz_queue',
            $wpdb->prefix . 'ai_quiz_logs',
            $wpdb->prefix . 'ai_quiz_cache'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
    }
    
    /**
     * ตรวจสอบว่าตารางมีอยู่หรือไม่
     */
    public function tables_exist() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'ai_quiz_queue';
        $logs_table = $wpdb->prefix . 'ai_quiz_logs';
        $cache_table = $wpdb->prefix . 'ai_quiz_cache';
        
        $queue_exists = $wpdb->get_var("SHOW TABLES LIKE '$queue_table'") === $queue_table;
        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
        $cache_exists = $wpdb->get_var("SHOW TABLES LIKE '$cache_table'") === $cache_table;
        
        return $queue_exists && $logs_exists && $cache_exists;
    }
    
    /**
     * ดึงโครงสร้างตาราง
     */
    public function get_table_structure($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . 'ai_quiz_' . $table_name;
        return $wpdb->get_results("DESCRIBE $full_table_name");
    }
    
    /**
     * ตรวจสอบขนาดตาราง
     */
    public function get_table_sizes() {
        global $wpdb;
        
        $sizes = array();
        $tables = array('queue', 'logs', 'cache');
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . 'ai_quiz_' . $table;
            $size = $wpdb->get_row("
                SELECT 
                    table_name AS 'name',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
                    table_rows AS 'rows'
                FROM information_schema.TABLES 
                WHERE table_schema = '" . DB_NAME . "'
                AND table_name = '$full_table_name'
            ");
            
            if ($size) {
                $sizes[$table] = $size;
            }
        }
        
        return $sizes;
    }
    
    /**
     * สำรองข้อมูลตาราง
     */
    public function backup_tables() {
        global $wpdb;
        
        $backup_data = array();
        $tables = array('queue', 'logs', 'cache');
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . 'ai_quiz_' . $table;
            $data = $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
            
            if ($data) {
                $backup_data[$table] = $data;
            }
        }
        
        // สร้างไฟล์สำรอง
        $backup_file = WP_CONTENT_DIR . '/ai-quiz-backup-' . date('Y-m-d-H-i-s') . '.json';
        file_put_contents($backup_file, json_encode($backup_data));
        
        return $backup_file;
    }
    
    /**
     * กู้คืนข้อมูลจากไฟล์สำรอง
     */
    public function restore_tables($backup_file) {
        global $wpdb;
        
        if (!file_exists($backup_file)) {
            return false;
        }
        
        $backup_data = json_decode(file_get_contents($backup_file), true);
        
        if (!$backup_data) {
            return false;
        }
        
        // ล้างข้อมูลเดิมก่อน
        $this->truncate_tables();
        
        // กู้คืนข้อมูล
        foreach ($backup_data as $table => $data) {
            $full_table_name = $wpdb->prefix . 'ai_quiz_' . $table;
            
            foreach ($data as $row) {
                $wpdb->insert($full_table_name, $row);
            }
        }
        
        return true;
    }
}
