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

<?php
/**
 * คลาสหลักสำหรับ AI Quiz Generator Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Quiz_Generator {
    
    protected $loader;
    protected $plugin_name;
    protected $version;
    
    public function __construct() {
        $this->plugin_name = 'ai-quiz-generator';
        $this->version = AI_QUIZ_VERSION;
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        // โหลดคลาสที่จำเป็น
        require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-ays-quiz-integration.php';
        require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-queue-manager.php';
        require_once AI_QUIZ_PLUGIN_DIR . 'includes/class-claude-api.php';
        require_once AI_QUIZ_PLUGIN_DIR . 'admin/class-ai-quiz-admin.php';
        require_once AI_QUIZ_PLUGIN_DIR . 'database/class-queue-table.php';
    }
    
    private function define_admin_hooks() {
        $admin = new AI_Quiz_Admin($this->plugin_name, $this->version);
        
        // เพิ่มเมนูในแอดมิน
        add_action('admin_menu', array($admin, 'add_plugin_admin_menu'));
        
        // โหลด CSS และ JS สำหรับแอดมิน
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($admin, 'enqueue_scripts'));
        
        // เพิ่มหน้าตั้งค่า
        add_action('admin_init', array($admin, 'register_settings'));
    }
    
    private function define_public_hooks() {
        // ยังไม่มีฟังก์ชันสำหรับฝั่ง public
    }
    
    public function run() {
        // เริ่มต้นการทำงานของ Plugin
        // ตัดการตรวจสอบ dependencies ออก
    }
    
    /**
     * ฟังก์ชันหลักสำหรับสร้างข้อสอบ
     */
    public function generate_quiz($position, $topics, $questions_per_topic) {
        try {
            // ตรวจสอบ API Key
            $api_key = get_option('ai_quiz_api_key');
            if (empty($api_key)) {
                throw new Exception('API Key not configured');
            }
            
            // เริ่มต้นคลาสที่จำเป็น
            $ays_integration = new AYS_Quiz_Integration();
            $claude_api = new Claude_API($api_key);
            
            // ดึงหมวดหมู่เริ่มต้น
            $category_id = get_option('ai_quiz_default_category', 3);
            
            $generated_questions = array();
            $total_questions = 0;
            
            // วนลูปสร้างข้อสอบตามหัวข้อ
            foreach ($topics as $topic) {
                for ($i = 0; $i < $questions_per_topic; $i++) {
                    // เรียก Claude API
                    $ai_response = $claude_api->generate_question($position, $topic);
                    
                    if (!$ai_response['success']) {
                        continue;
                    }
                    
                    // สร้างข้อสอบในระบบ AYS Quiz
                    $question_id = $ays_integration->create_question($ai_response['data'], $category_id);
                    
                    if ($question_id) {
                        // สร้างคำตอบ
                        $ays_integration->create_answers($question_id, $ai_response['data']['answers']);
                        
                        // สร้าง tag ตาม Column C
                        $column_c = sprintf(
                            "แนวข้อสอบ%s ชุดที่ %d",
                            $position,
                            ceil(($total_questions + 1) / 100)
                        );
                        $ays_integration->create_question_tag($question_id, $column_c);
                        
                        $generated_questions[] = $question_id;
                        $total_questions++;
                    }
                    
                    // หน่วงเวลาเพื่อป้องกัน rate limit
                    usleep(get_option('ai_quiz_request_delay', 2000) * 1000);
                }
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'question_ids' => $generated_questions,
                    'total_generated' => $total_questions
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * ฟังก์ชันตรวจสอบสถานะการทำงาน
     */
    public function get_status() {
        $queue_manager = new Quiz_Queue_Manager();
        
        return array(
            'queue_status' => $queue_manager->get_queue_status(),
            'api_status' => $this->check_api_status(),
            'last_run' => get_option('ai_quiz_last_run', 'Never')
        );
    }
    
    private function check_api_status() {
        $api_key = get_option('ai_quiz_api_key');
        return !empty($api_key) ? 'configured' : 'not_configured';
    }
}
