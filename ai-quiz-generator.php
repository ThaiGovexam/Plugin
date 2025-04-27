<?php
/**
 * Plugin Name: AI Quiz Generator
 * Plugin URI: https://example.com/ai-quiz-generator
 * Description: สร้างข้อสอบอัตโนมัติด้วย AI และระบบคิวข้อสอบ
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: ai-quiz-generator
 * Domain Path: /languages
 */

// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// กำหนดค่าคงที่
define('AI_QUIZ_VERSION', '1.0.0');
define('AI_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// โหลดไฟล์ที่จำเป็น
require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-ai-quiz-generator.php';

// เริ่มต้น Plugin
function ai_quiz_generator_init() {
    $plugin = new AI_Quiz_Generator();
    $plugin->run();
}
add_action('plugins_loaded', 'ai_quiz_generator_init');

// Activation Hook
register_activation_hook(__FILE__, 'ai_quiz_generator_activate');
function ai_quiz_generator_activate() {
    // สร้างตารางที่จำเป็น
    require_once AI_QUIZ_PLUGIN_DIR . 'database/class-queue-table.php';
    $queue_table = new Quiz_Queue_Table();
    $queue_table->create_table();
    
    // ตั้งค่าเริ่มต้น
    add_option('ai_quiz_default_category', 3);
    add_option('ai_quiz_api_key', '');
    add_option('ai_quiz_request_delay', 2000);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation Hook
register_deactivation_hook(__FILE__, 'ai_quiz_generator_deactivate');
function ai_quiz_generator_deactivate() {
    // ล้าง scheduled events
    wp_clear_scheduled_hook('ai_quiz_process_queue');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall Hook
register_uninstall_hook(__FILE__, 'ai_quiz_generator_uninstall');
function ai_quiz_generator_uninstall() {
    // ลบตัวเลือกที่สร้างไว้
    delete_option('ai_quiz_default_category');
    delete_option('ai_quiz_api_key');
    delete_option('ai_quiz_request_delay');
    
    // ลบตารางที่สร้างไว้ (ถ้าต้องการ)
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_quiz_queue");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_quiz_logs");
}

// โหลด text domain สำหรับการแปลภาษา
function ai_quiz_generator_load_textdomain() {
    load_plugin_textdomain(
        'ai-quiz-generator',
        false,
        dirname(AI_QUIZ_PLUGIN_BASENAME) . '/languages/'
    );
}
add_action('plugins_loaded', 'ai_quiz_generator_load_textdomain');

// เพิ่ม action links ในหน้า plugins
function ai_quiz_generator_action_links($links) {
    $settings_link = '<a href="admin.php?page=ai-quiz-generator-settings">' . 
                     __('Settings', 'ai-quiz-generator') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . AI_QUIZ_PLUGIN_BASENAME, 'ai_quiz_generator_action_links');

// เพิ่ม AJAX handlers
add_action('wp_ajax_ai_quiz_generate', 'ai_quiz_ajax_generate');
function ai_quiz_ajax_generate() {
    check_ajax_referer('ai_quiz_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $position = sanitize_text_field($_POST['position']);
    $topics = array_map('sanitize_text_field', $_POST['topics']);
    $questions_per_topic = intval($_POST['questions_per_topic']);
    
    // เรียกใช้งานฟังก์ชันสร้างข้อสอบ
    $generator = new AI_Quiz_Generator();
    $result = $generator->generate_quiz($position, $topics, $questions_per_topic);
    
    if ($result['success']) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error($result['message']);
    }
}

// เพิ่ม AJAX handler สำหรับระบบคิว
add_action('wp_ajax_ai_quiz_add_to_queue', 'ai_quiz_ajax_add_to_queue');
function ai_quiz_ajax_add_to_queue() {
    check_ajax_referer('ai_quiz_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $data = array(
        'position' => sanitize_text_field($_POST['position']),
        'topics' => array_map('sanitize_text_field', $_POST['topics']),
        'questions_per_topic' => intval($_POST['questions_per_topic']),
        'total_questions' => intval($_POST['total_questions'])
    );
    
    require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-queue-manager.php';
    $queue_manager = new Quiz_Queue_Manager();
    $result = $queue_manager->add_to_queue($data);
    
    if ($result) {
        wp_send_json_success('Added to queue successfully');
    } else {
        wp_send_json_error('Failed to add to queue');
    }
}

// ตั้งค่า Cron job สำหรับประมวลผลคิว
if (!wp_next_scheduled('ai_quiz_process_queue')) {
    wp_schedule_event(time(), 'every_five_minutes', 'ai_quiz_process_queue');
}

// เพิ่ม custom interval สำหรับ cron
function ai_quiz_add_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => __('Every 5 Minutes', 'ai-quiz-generator')
    );
    return $schedules;
}
add_filter('cron_schedules', 'ai_quiz_add_cron_interval');

// Cron hook สำหรับประมวลผลคิว
add_action('ai_quiz_process_queue', 'ai_quiz_process_queue_callback');
function ai_quiz_process_queue_callback() {
    require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-queue-manager.php';
    $queue_manager = new Quiz_Queue_Manager();
    $queue_manager->process_next_in_queue();
}
