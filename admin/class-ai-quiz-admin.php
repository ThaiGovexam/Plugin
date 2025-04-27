<?php
/**
 * คลาสสำหรับจัดการ Admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Quiz_Admin {
    
    private $plugin_name;
    private $version;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * เพิ่มเมนูในแอดมิน
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'AI Quiz Generator',                     // Page title
            'AI Quiz Generator',                     // Menu title
            'manage_options',                        // Capability
            'ai-quiz-generator',                     // Menu slug
            array($this, 'display_dashboard_page'),  // Callback
            'dashicons-edit',                        // Icon
            30                                       // Position
        );
        
        add_submenu_page(
            'ai-quiz-generator',                     // Parent slug
            'สร้างข้อสอบ',                            // Page title
            'สร้างข้อสอบ',                            // Menu title
            'manage_options',                        // Capability
            'ai-quiz-generator',                     // Menu slug
            array($this, 'display_dashboard_page')   // Callback
        );
        
        add_submenu_page(
            'ai-quiz-generator',
            'ระบบคิว',
            'ระบบคิว',
            'manage_options',
            'ai-quiz-queue',
            array($this, 'display_queue_page')
        );
        
        add_submenu_page(
            'ai-quiz-generator',
            'ตั้งค่า',
            'ตั้งค่า',
            'manage_options',
            'ai-quiz-settings',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'ai-quiz-generator',
            'ล็อกระบบ',
            'ล็อกระบบ',
            'manage_options',
            'ai-quiz-logs',
            array($this, 'display_logs_page')
        );
    }
    
    /**
     * โหลด CSS
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'ai-quiz-generator') === false) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name,
            AI_QUIZ_PLUGIN_URL . 'admin/css/ai-quiz-admin.css',
            array(),
            $this->version,
            'all'
        );
        
        // เพิ่ม Google Fonts
        wp_enqueue_style(
            'google-fonts-sarabun',
            'https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap',
            array(),
            null
        );
        
        // เพิ่ม Material Icons
        wp_enqueue_style(
            'material-icons',
            'https://fonts.googleapis.com/icon?family=Material+Icons',
            array(),
            null
        );
    }
    
    /**
     * โหลด JavaScript
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'ai-quiz-generator') === false) {
            return;
        }
        
        wp_enqueue_script(
            $this->plugin_name,
            AI_QUIZ_PLUGIN_URL . 'admin/js/ai-quiz-admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // ส่งค่าไปยัง JavaScript
        wp_localize_script($this->plugin_name, 'aiQuizAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_quiz_nonce'),
            'strings' => array(
                'confirm_delete' => __('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?', 'ai-quiz-generator'),
                'processing' => __('กำลังประมวลผล...', 'ai-quiz-generator'),
                'success' => __('สำเร็จ!', 'ai-quiz-generator'),
                'error' => __('เกิดข้อผิดพลาด', 'ai-quiz-generator')
            )
        ));
    }
    
    /**
     * แสดงหน้า Dashboard
     */
    public function display_dashboard_page() {
        include_once AI_QUIZ_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * แสดงหน้าระบบคิว
     */
    public function display_queue_page() {
        include_once AI_QUIZ_PLUGIN_DIR . 'admin/views/queue-management.php';
    }
    
    /**
     * แสดงหน้าตั้งค่า
     */
    public function display_settings_page() {
        include_once AI_QUIZ_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * แสดงหน้าล็อกระบบ
     */
    public function display_logs_page() {
        include_once AI_QUIZ_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    /**
     * ลงทะเบียนการตั้งค่า
     */
    public function register_settings() {
        // ตั้งค่า API
        register_setting('ai_quiz_settings', 'ai_quiz_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // ตั้งค่า Rate Limit
        register_setting('ai_quiz_settings', 'ai_quiz_request_delay', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2000
        ));
        
        // ตั้งค่าหมวดหมู่เริ่มต้น
        register_setting('ai_quiz_settings', 'ai_quiz_default_category', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3
        ));
        
        // ตั้งค่า Column B value
        register_setting('ai_quiz_settings', 'ai_quiz_column_b_value', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '-'
        ));
        
        // ตั้งค่า Column C pattern
        register_setting('ai_quiz_settings', 'ai_quiz_column_c_pattern', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'แนวข้อสอบ {position} ชุดที่ {set}'
        ));
        
        // เพิ่ม sections
        add_settings_section(
            'ai_quiz_api_settings',
            'ตั้งค่า API',
            array($this, 'api_settings_section_callback'),
            'ai_quiz_settings'
        );
        
        add_settings_section(
            'ai_quiz_general_settings',
            'ตั้งค่าทั่วไป',
            array($this, 'general_settings_section_callback'),
            'ai_quiz_settings'
        );
        
        // เพิ่ม fields
        add_settings_field(
            'ai_quiz_api_key',
            'Claude API Key',
            array($this, 'api_key_field_callback'),
            'ai_quiz_settings',
            'ai_quiz_api_settings'
        );
        
        add_settings_field(
            'ai_quiz_request_delay',
            'เวลารอระหว่างการเรียก API (มิลลิวินาที)',
            array($this, 'request_delay_field_callback'),
            'ai_quiz_settings',
            'ai_quiz_api_settings'
        );
        
        add_settings_field(
            'ai_quiz_default_category',
            'หมวดหมู่เริ่มต้น',
            array($this, 'default_category_field_callback'),
            'ai_quiz_settings',
            'ai_quiz_general_settings'
        );
        
        add_settings_field(
            'ai_quiz_column_b_value',
            'ค่าสำหรับคอลัมน์ B',
            array($this, 'column_b_field_callback'),
            'ai_quiz_settings',
            'ai_quiz_general_settings'
        );
        
        add_settings_field(
            'ai_quiz_column_c_pattern',
            'รูปแบบคอลัมน์ C',
            array($this, 'column_c_field_callback'),
            'ai_quiz_settings',
            'ai_quiz_general_settings'
        );
    }
    
    // Callback functions สำหรับ settings fields
    public function api_settings_section_callback() {
        echo '<p>ตั้งค่าการเชื่อมต่อกับ Claude API</p>';
    }
    
    public function general_settings_section_callback() {
        echo '<p>ตั้งค่าทั่วไปของระบบ</p>';
    }
    
    public function api_key_field_callback() {
        $api_key = get_option('ai_quiz_api_key', '');
        echo '<input type="password" name="ai_quiz_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">API Key จาก Anthropic สำหรับเข้าถึง Claude AI</p>';
    }
    
    public function request_delay_field_callback() {
        $delay = get_option('ai_quiz_request_delay', 2000);
        echo '<input type="number" name="ai_quiz_request_delay" value="' . esc_attr($delay) . '" min="1000" max="10000" class="small-text">';
        echo '<p class="description">ค่าแนะนำ: 2000 มิลลิวินาที (2 วินาที)</p>';
    }
    
    public function default_category_field_callback() {
        $selected_category = get_option('ai_quiz_default_category', 3);
        $ays_integration = new AYS_Quiz_Integration();
        $categories = $ays_integration->get_all_categories();
        
        echo '<select name="ai_quiz_default_category">';
        foreach ($categories as $category) {
            printf(
                '<option value="%d" %s>%s</option>',
                $category->id,
                selected($selected_category, $category->id, false),
                esc_html($category->title)
            );
        }
        echo '</select>';
    }
    
    public function column_b_field_callback() {
        $value = get_option('ai_quiz_column_b_value', '-');
        echo '<input type="text" name="ai_quiz_column_b_value" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">ค่าเริ่มต้นสำหรับคอลัมน์ B</p>';
    }
    
    public function column_c_field_callback() {
        $pattern = get_option('ai_quiz_column_c_pattern', 'แนวข้อสอบ {position} ชุดที่ {set}');
        echo '<input type="text" name="ai_quiz_column_c_pattern" value="' . esc_attr($pattern) . '" class="regular-text">';
        echo '<p class="description">ใช้ {position} แทนชื่อตำแหน่ง และ {set} แทนชุดที่</p>';
    }
}
