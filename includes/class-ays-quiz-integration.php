<?php
/**
 * คลาสสำหรับการทำงานร่วมกับ AYS Quiz Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AYS_Quiz_Integration {
    
    /**
     * สร้างคำถามใหม่ใน AYS Quiz
     */
    public function create_question($data, $category_id) {
        global $wpdb;
        
        $question_data = array(
            'author_id' => get_current_user_id(),
            'title' => $data['question'],
            'category_id' => $category_id,
            'status' => 'published',
            'type' => 'radio',
            'question_title' => $data['question'],
            'question_image' => '',
            'explanation' => isset($data['explanation']) ? $data['explanation'] : '',
            'wrong_answer_text' => '',
            'right_answer_text' => '',
            'create_date' => current_time('mysql'),
            'published' => 1,
            'options' => json_encode(array(
                'use_html' => 'off',
                'enable_correction' => 'off',
                'answer_view_type' => 'list'
            ))
        );
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'aysquiz_questions',
            $question_data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($inserted) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * สร้างคำตอบสำหรับคำถาม
     */
    public function create_answers($question_id, $answers) {
        global $wpdb;
        
        $order = 1;
        foreach ($answers as $answer) {
            $answer_data = array(
                'question_id' => $question_id,
                'answer' => $answer['text'],
                'correct' => $answer['is_correct'] ? 1 : 0,
                'ordering' => $order,
                'weight' => 0,
                'image' => '',
                'keyword' => '',
                'placeholder' => '',
                'slug' => '',
                'options' => ''
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'aysquiz_answers',
                $answer_data,
                array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );
            
            $order++;
        }
    }
    
    /**
     * สร้าง tag สำหรับคำถาม
     */
    public function create_question_tag($question_id, $tag_name) {
        global $wpdb;
        
        // ดึงชื่อชุดข้อสอบจาก tag_name
        $clean_tag = $this->extract_tag_from_column_c($tag_name);
        
        // ตรวจสอบว่ามี tag นี้อยู่แล้วหรือไม่
        $existing_tag = $wpdb->get_row($wpdb->prepare(
            "SELECT id, title FROM {$wpdb->prefix}aysquiz_question_tags WHERE title = %s",
            $clean_tag
        ));
        
        if (!$existing_tag) {
            // สร้าง tag ใหม่
            $wpdb->insert(
                $wpdb->prefix . 'aysquiz_question_tags',
                array(
                    'title' => $clean_tag,
                    'description' => '',
                    'status' => 'published',
                    'options' => ''
                ),
                array('%s', '%s', '%s', '%s')
            );
            $tag_id = $wpdb->insert_id;
        } else {
            $tag_id = $existing_tag->id;
        }
        
        // เชื่อมโยง tag กับคำถาม
        $wpdb->insert(
            $wpdb->prefix . 'aysquiz_question_tags_relationship',
            array(
                'question_id' => $question_id,
                'tag_id' => $tag_id
            ),
            array('%d', '%d')
        );
    }
    
    /**
     * สกัดชื่อ tag จาก Column C
     */
    private function extract_tag_from_column_c($column_c_value) {
        // ตัดส่วน "ชุดที่ X" ออก
        return preg_replace('/\s*ชุดที่\s*\d+/', '', $column_c_value);
    }
    
    /**
     * ดึงรายการหมวดหมู่ทั้งหมด
     */
    public function get_all_categories() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT id, title FROM {$wpdb->prefix}aysquiz_categories WHERE published = 1 ORDER BY title ASC"
        );
    }
    
    /**
     * ตรวจสอบว่าคำถามมีอยู่แล้วหรือไม่ (เพื่อป้องกันการซ้ำ)
     */
    public function question_exists($question_text) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aysquiz_questions WHERE title = %s",
            $question_text
        ));
        
        return $exists > 0;
    }
    
    /**
     * อัปเดต Column C สำหรับคำถาม (ถ้าต้องการ)
     */
    public function update_question_column_c($question_id, $column_c_value) {
        global $wpdb;
        
        // ในระบบ AYS Quiz ไม่มี Column C โดยตรง จึงใช้ custom field หรือ tag แทน
        $this->create_question_tag($question_id, $column_c_value);
    }
    
    /**
     * ลบคำถามและคำตอบที่เกี่ยวข้อง
     */
    public function delete_question($question_id) {
        global $wpdb;
        
        // ลบคำตอบก่อน
        $wpdb->delete(
            $wpdb->prefix . 'aysquiz_answers',
            array('question_id' => $question_id),
            array('%d')
        );
        
        // ลบความสัมพันธ์กับ tag
        $wpdb->delete(
            $wpdb->prefix . 'aysquiz_question_tags_relationship',
            array('question_id' => $question_id),
            array('%d')
        );
        
        // ลบคำถาม
        $wpdb->delete(
            $wpdb->prefix . 'aysquiz_questions',
            array('id' => $question_id),
            array('%d')
        );
    }
    
    /**
     * ดึงข้อมูลคำถามพร้อมคำตอบ
     */
    public function get_question_with_answers($question_id) {
        global $wpdb;
        
        $question = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aysquiz_questions WHERE id = %d",
            $question_id
        ));
        
        if ($question) {
            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aysquiz_answers WHERE question_id = %d ORDER BY ordering ASC",
                $question_id
            ));
            
            $question->answers = $answers;
        }
        
        return $question;
    }
}
