<?php
/**
 * คลาสสำหรับเชื่อมต่อกับ Claude API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Claude_API {
    
    private $api_key;
    private $api_endpoint = 'https://api.anthropic.com/v1/messages';
    private $model = 'claude-3-haiku-20240307';
    private $max_tokens = 4096;
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('ai_quiz_api_key');
    }
    
    /**
     * สร้างคำถามจาก Claude API
     */
    public function generate_question($position, $topic) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API Key not configured'
            );
        }
        
        // สร้าง prompt
        $prompt = $this->create_prompt($position, $topic);
        
        // ตรวจสอบ cache ก่อน
        $cached_response = $this->get_cached_response($prompt);
        if ($cached_response) {
            return array(
                'success' => true,
                'data' => $cached_response
            );
        }
        
        // เรียก API
        $response = $this->call_api($prompt);
        
        if ($response['success']) {
            // แปลงข้อมูลเป็นรูปแบบที่ต้องการ
            $formatted_data = $this->format_response($response['data']);
            
            // บันทึก cache
            $this->cache_response($prompt, $formatted_data);
            
            return array(
                'success' => true,
                'data' => $formatted_data
            );
        }
        
        return $response;
    }
    
    /**
     * สร้าง prompt สำหรับ Claude
     */
    private function create_prompt($position, $topic) {
        $instructions = "You are an expert exam question creator for Thai government agencies. Create exam questions for Thai government positions following EXACTLY this format with pipe symbols as separators:

[Question text]|-|-|[Question explanation]|[Correct answer number (1-4)]|[Option 1]|[Option 2]|[Option 3]|[Option 4]

Critical formatting requirements:
1. Each question must be a single line of text with pipe (|) separators between fields
2. The second and third columns must contain only a single hyphen (-)
3. The correct answer field must contain just the number (1, 2, 3, or 4)
4. All text must be in Thai language
5. Do not include row numbers, column headers, or any additional formatting
6. Each question must be completely self-contained on a single line";

        $main_prompt = "กรุณาออกข้อสอบสำหรับตำแหน่ง \"{$position}\" ในหัวข้อ \"{$topic}\" จำนวน 1 ข้อ

โปรดออกข้อสอบตามหลักการออกข้อสอบที่ดี มีความหลากหลาย ทดสอบความรู้ที่จำเป็นสำหรับตำแหน่งนี้ และมีการกระจายคำตอบที่ถูกต้องอย่างสมดุล

คอลัมน์ที่ 2 และ 3 ให้ใส่เป็น \"-\" (เครื่องหมายขีด) เท่านั้น
ต้องมีข้อสอบจำนวน 1 ข้อเท่านั้น ต้องไม่มีคำอธิบายหรือข้อความอื่นใดนอกเหนือจากรูปแบบข้อสอบที่กำหนด";

        return $instructions . "\n\n" . $main_prompt;
    }
    
    /**
     * เรียก Claude API
     */
    private function call_api($prompt) {
        $body = array(
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'message' => "API Error ({$response_code}): " . $response_body
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'message' => 'Invalid API response format'
            );
        }
        
        return array(
            'success' => true,
            'data' => $data['content'][0]['text']
        );
    }
    
    /**
     * จัดรูปแบบข้อมูลจาก API
     */
    private function format_response($response_text) {
        // แยกบรรทัด
        $lines = explode("\n", trim($response_text));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // แยกข้อมูลตามเครื่องหมาย |
            $parts = explode('|', $line);
            
            if (count($parts) >= 9) {
                return array(
                    'question' => $parts[0],
                    'explanation' => $parts[3],
                    'correct_answer' => intval($parts[4]),
                    'answers' => array(
                        array('text' => $parts[5], 'is_correct' => (intval($parts[4]) === 1)),
                        array('text' => $parts[6], 'is_correct' => (intval($parts[4]) === 2)),
                        array('text' => $parts[7], 'is_correct' => (intval($parts[4]) === 3)),
                        array('text' => $parts[8], 'is_correct' => (intval($parts[4]) === 4))
                    )
                );
            }
        }
        
        return null;
    }
    
    /**
     * ดึงข้อมูลจาก cache
     */
    private function get_cached_response($prompt) {
        $cache_key = 'ai_quiz_' . md5($prompt);
        return get_transient($cache_key);
    }
    
    /**
     * บันทึกข้อมูลลง cache
     */
    private function cache_response($prompt, $response) {
        $cache_key = 'ai_quiz_' . md5($prompt);
        $cache_duration = 6 * HOUR_IN_SECONDS; // 6 ชั่วโมง
        set_transient($cache_key, $response, $cache_duration);
    }
    
    /**
     * ล้าง cache ทั้งหมด
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_ai_quiz_%' 
             OR option_name LIKE '_transient_timeout_ai_quiz_%'"
        );
    }
    
    /**
     * ตรวจสอบสถานะ API
     */
    public function test_connection() {
        $test_prompt = "Test connection. Reply with 'OK' if you receive this.";
        
        $response = $this->call_api($test_prompt);
        
        return $response['success'];
    }
}
