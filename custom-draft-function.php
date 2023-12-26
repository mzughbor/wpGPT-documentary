<?php

/**
 * Plugin Name: Test Custom Draft Function
 * Description: Runs a custom function on draft posts.
 * Version: 1.21
 * Author: mzughbor
 */

define('CUSTOM_DRAFT_LOG_PATH', plugin_dir_path(__FILE__) . 'log.txt');
//define('CUSTOM_DRAFT_LOG_PATH', WP_CONTENT_DIR . 'plugins/custom-draft-function/log.txt');

$log_dir = dirname(CUSTOM_DRAFT_LOG_PATH);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function test_custom_paragraphs($content){

    // Pattern to match the unwanted paragraph with a strong tag    
    // Array of unwanted patterns
    $unwanted_patterns = array(
        '/أقرأ ايضًا:/u', //kora+
        '/أخبار متعلقة/u',
        '/طالع أيضًا:/u',
        '/طالع أيضًا/u',
        '/WRONGERR/u',

        '/الأخبار الرئيسية/u', // bbc !! not working so we'll remove the inter div...
        '/قصص مقترحة/u', // bbc
        '/المزيد حول هذه القصة/u', // bbc
        '/مواضيع ذات صلة/u', // bbc
        '/اخترنا لكم/u', // bbc

        '/اقرأ أيضا:/u', //yalla_kora
        '/اقرأ أيضا:/u', //2
        '/طالع أيضا/u',
        
        '/المراجع/u', //mawdoo3.com // didn't worked 
        '/محتويات/u', // we'll delete inter div
    );
    //  no-sometimes there is two ones in articles '/أخبار متعلقة/u',

    // Flag to indicate whether an unwanted pattern is found
    $unwanted_pattern_found = false;

    // Loop through patterns and remove them from content
    foreach ($unwanted_patterns as $pattern) {
        // $content = preg_replace($pattern, '', $content); // old way
        if (preg_match($pattern, $content)) {
            $unwanted_pattern_found = true;

            // Pattern to match paragraphs or h3 elements with links
            $pattern_with_links = '/<(div|p|h3|strong|li)>.*<a.*<\/(div|p|h3|strong|li)>/u';
            
            // If an unwanted pattern is found, look for links
            if ($unwanted_pattern_found) {
                // Find paragraphs or h3 elements with links
                preg_match_all($pattern_with_links, $content, $matches);

                // If there are paragraphs with links
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        // Remove the paragraph
                        $content = str_replace($match, '', $content);        
                        // If the removed paragraph doesn't have a link anymore, stop
                        if (!strpos($match, '<a')) {
                            break;
                        }
                    }
                }
            }
            
            // Remove the unwanted pattern
            $content = preg_replace($pattern, '', $content);
        }
    }

    // Define the class names of the divs you want to remove
    $ids_and_classes_to_remove  = array(
        'After_F_Paragraph', // Kora+ <id>
        'related-articles-list1', // mawdoo3 <class>
        'toc',// mawdoo3
        'toctitle', // mawdoo3
        'references', // mawdoo3
        'printfooter', // mawdoo3
        'feedback-feature', // mawdoo3
        'feedback-no-option', // mawdoo3
        'feedback-thanks-msg', // mawdoo3
        'feedback-yes-option', // mawdoo3
    );

    // Create a DOMDocument object to parse the post content
    $dom = new DOMDocument();
    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Check if the DOMDocument was created successfully
    if (!$dom) {
        // we'll edit return lateer to be at the bottom
        error_log('Failed .. !dom :: So return content ...\n' , 3, CUSTOM_DRAFT_LOG_PATH);        
        return $content;
    }

    // Create a DOMXPath object to query the DOMDocument
    $xpath = new DOMXPath($dom);
    
    // Loop through each class to remove
    foreach ($ids_and_classes_to_remove as $id_or_class) {
        // Find the divs with the specified class name using XPath
        //$divsToRemove = $xpath->query("//*[@class='$class_name']");
        $divsToRemove = $xpath->query("//*[@id='$id_or_class' or contains(@class, '$id_or_class')]");
        if ($divsToRemove) {
            // Remove divs by class name
            foreach ($divsToRemove as $div) {
                $divParent = $div->parentNode;
                $divParent->removeChild($div);
            }
        }
    }
    // Save the modified HTML back to the post content
    $content = $dom->saveHTML();
    return $content;
}






// not in use for now
function test_remove_custom_paragraphs($content) {

    // Define the ID of the div you want to remove
    // Define the IDs and class names of the divs you want to remove
    $divs_to_remove = array(
        'After_F_Paragraph',
        'related-articles-list1',
        'toc',
        'toctitle',
        'references',
        'printfooter',
        'feedback-feature',
        'feedback-no-option',
        'feedback-thanks-msg',
        'feedback-yes-option'
    );

    // Create a DOMDocument object to parse the post content
    $dom = new DOMDocument();
    // Load the content using UTF-8 encoding
    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Loop through each div to remove
    foreach ($divs_to_remove as $div_identifier) {
        // Find the divs with the specified ID or class name
        $divsToRemove = $dom->getElementById($div_identifier);
        //$divsToRemoveByClass = $dom->getElementsByClassName($div_identifier);

        // Remove divs by ID
        if ($divsToRemove) {
            $divParent = $divsToRemove->parentNode;
            $divParent->removeChild($divsToRemove);
        }

        // Remove divs by class name
        /*foreach ($divsToRemoveByClass as $div) {
            $divParent = $div->parentNode;
            $divParent->removeChild($div);
        }*/
    }

    // Save the modified HTML back to the post content
    $content = $dom->saveHTML();
    
    // Pattern to match the unwanted paragraph with a strong tag    
    // Array of unwanted patterns
    $unwanted_patterns = array(
        '/أقرأ ايضًا:/u', //kora+
        '/أخبار متعلقة/u',
        '/طالع أيضًا:/u',
        '/طالع أيضًا/u',
        '/WRONGERR/u',

        '/الأخبار الرئيسية/u', // bbc !! not working so we'll remove the inter div...
        '/قصص مقترحة/u', // bbc
        '/المزيد حول هذه القصة/u', // bbc
        '/مواضيع ذات صلة/u', // bbc
        '/اخترنا لكم/u', // bbc

        '/اقرأ أيضا:/u', //yalla_kora
        '/اقرأ أيضا:/u', //2
        '/طالع أيضا/u',
        
        '/المراجع/u', //mawdoo3.com // didn't worked 
        '/محتويات/u', // we'll delete inter div
    );
    //  no-sometimes there is two ones in articles '/أخبار متعلقة/u',

    // Flag to indicate whether an unwanted pattern is found
    $unwanted_pattern_found = false;

    // Loop through patterns and remove them from content
    foreach ($unwanted_patterns as $pattern) {
        // $content = preg_replace($pattern, '', $content); // old way
        if (preg_match($pattern, $content)) {
            $unwanted_pattern_found = true;

            // Pattern to match paragraphs or h3 elements with links
            $pattern_with_links = '/<(div|p|h3|strong|li)>.*<a.*<\/(div|p|h3|strong|li)>/u';
            
            // If an unwanted pattern is found, look for links
            if ($unwanted_pattern_found) {
                // Find paragraphs or h3 elements with links
                preg_match_all($pattern_with_links, $content, $matches);

                // If there are paragraphs with links
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        // Remove the paragraph
                        $content = str_replace($match, '', $content);        
                        // If the removed paragraph doesn't have a link anymore, stop
                        if (!strpos($match, '<a')) {
                            break;
                        }
                    }
                }
            }
            
            // Remove the unwanted pattern
            $content = preg_replace($pattern, '', $content);
        }
    }
    return $content;
}






function schedule_draft_function() {
    if (!wp_next_scheduled('custom_draft_function_event')) {
        wp_schedule_event(time(), 'ten_minutes', 'custom_draft_function_event');
    }
}
add_action('wp', 'schedule_draft_function');

function custom_draft_function() {

    // Retrieve up to 3 private/draft posts
    $args = array(
        'post_type' => 'post',
        'post_status' => 'private',
        'posts_per_page' => 3,
    );

    $draft_posts = get_posts($args);

    foreach ($draft_posts as $post) {
        $content = test_custom_paragraphs($post->post_content);
        wp_update_post(array(
            'ID' => $post->ID,
            'post_content' => $content,
        ));
        
        // Convert the post to private/draft
        wp_update_post(array(
            'ID' => $post->ID,
            'post_status' => 'draft', // Set to 'private'
        ));        
    }
}
add_action('custom_draft_function_event', 'custom_draft_function');

function ten_minutes_interval($schedules) {
    $schedules['ten_minutes'] = array(
        'interval' => 600, // 10 minutes in seconds
        'display' => __('Every 10 Minutes'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'ten_minutes_interval');











// ------------- old filteration function

    // Filteration function for all inclusions and exclusions like more news and so on...
    function old_filter_row_post_content($post_id){

        $post = get_post($post_id);

        $content = $post->post_content;
        
        // filter content Yallakora.com (1) case
        // Check if the content contains "اقرأ أيضاً.." text
        if (strpos($content, 'اقرأ أيضا:') !== false) {

            // Remove "اقرأ أيضاً.." text
            $pattern = '/اقرأ أيضا:/u';
            $content = preg_replace($pattern, '',  $content);
            error_log('----mm\'----'. $content ."\n", 3, CUSTOM_LOG_PATH);

            // Solving empty article return because of ' single quotation
            $content = str_replace("'", '[SINGLE_QUOTE]', $content); // Replace single quotation marks with a placeholder
            $content = str_replace('"', '[DOUBLE_QUOTE]', $content); // Replace double quotation marks with a placeholder

            error_log('----xx\'----'. $content ."\n", 3, CUSTOM_LOG_PATH);

            // Split content into paragraphs
            $paragraphs = explode('</p>', $content);
            error_log('----pp\'----'. $paragraphs ."\n", 3, CUSTOM_LOG_PATH);

            // Find paragraphs with <a> tags and remove following paragraphs
            $new_content = '';
            $inside_link_paragraph = false;
            foreach ($paragraphs as $paragraph) {
                error_log('----ff\'----' ."\n", 3, CUSTOM_LOG_PATH);
                if (strpos($paragraph, '<a') !== false) {
                    $inside_link_paragraph = true;
                    error_log('----if1\'----' ."\n", 3, CUSTOM_LOG_PATH);
                }
                if (!$inside_link_paragraph) {
                    $new_content .= $paragraph . '</p>';
                    error_log('----if2\'----' ."\n", 3, CUSTOM_LOG_PATH);                
                }
                if (strpos($paragraph, '</a>') !== false) {
                    $inside_link_paragraph = false;
                    error_log('----if3\'----' ."\n", 3, CUSTOM_LOG_PATH);                
                }
            }

            error_log('----------------------------------------' ."\n", 3, CUSTOM_LOG_PATH);
            // After processing, replace the placeholders back with single quotation marks
            $new_content = str_replace('[SINGLE_QUOTE]', "'", $new_content);
            $new_content = str_replace('[DOUBLE_QUOTE]', '"', $new_content);
            error_log('-- Yallakora case(1) --: ' . $new_content ."\n", 3, CUSTOM_LOG_PATH);

            // Check if filtered content is empty
            //if (empty($new_content)) {
            //    return $content; // Return original content if filtered content is empty
            //    error_log('** ALERT ************************* : '."\n", 3, CUSTOM_LOG_PATH);
            //}
            error_log('** ALERT ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ : '."\n", 3, CUSTOM_LOG_PATH);
            return $new_content;            
        }
        error_log('** ALERT %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%% : '."\n", 3, CUSTOM_LOG_PATH);
        return $content; // Return original content if "اقرأ أيضاً.." text is not found
    }
