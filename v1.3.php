<?php
/*
Plugin Name: ChatGPT with Ava - Private Rewrites
Description: Rewrite private post content using ChatGPT API as a cron job.
Version: 1.3
Author: mZughbor
*/
define('CUSTOM_LOG_PATH', plugin_dir_path(__FILE__) . 'log.txt');
//define('CUSTOM_LOG_PATH', WP_CONTENT_DIR . 'plugins/chatgpt_ava/log.txt');

$log_dir = dirname(CUSTOM_LOG_PATH);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Add the plugin menu
add_action('admin_menu', 'chatgpt_ava_plugin_menu');
function chatgpt_ava_plugin_menu()
{
    add_options_page('ChatGPT with Ava Settings', 'ChatGPT with Ava', 'manage_options', 'chatgpt_ava_settings', 'chatgpt_ava_settings_page');
}


// Plugin settings page
function chatgpt_ava_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    ?>
    <div class="wrap">
        <h1>ChatGPT with Ava Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('chatgpt_ava_options');
            do_settings_sections('chatgpt_ava_settings');
            submit_button();
            $min_word_count = 130;
            $max_char_count = 4100; // without_space
            ?>
        </form>
        
        <!-- Existing form for API key setting -->
        
        <h2>Draft Posts List will auto delete at the end of the day</h2>
        <p>Below is the list of draft posts with less than <?php echo $min_word_count; ?> words or long text more that <?php echo $max_char_count; ?> charachters without spaces.</p>
        <?php display_draft_posts(); ?>
        
        <!-- Rest of the form and submit button -->

    </div>
    <?php
}


// Plugin settings
add_action('admin_init', 'chatgpt_ava_settings_init');
function chatgpt_ava_settings_init()
{
    register_setting('chatgpt_ava_options', 'chatgpt_ava_api_key');
    add_settings_section('chatgpt_ava_settings', 'ChatGPT API Settings', 'chatgpt_ava_settings_section_callback', 'chatgpt_ava_settings');
    add_settings_field('chatgpt_ava_api_key', 'ChatGPT API Key', 'chatgpt_ava_api_key_render', 'chatgpt_ava_settings', 'chatgpt_ava_settings');
}

function chatgpt_ava_settings_section_callback()
{
    echo '<p>Enter your ChatGPT API key below:</p>';
}

function chatgpt_ava_api_key_render()
{
    $api_key = get_option('chatgpt_ava_api_key');
    echo "<input type='text' name='chatgpt_ava_api_key' value='" . esc_attr($api_key) . "' />";
}

// Enqueue necessary scripts and styles for the plugin
add_action('wp_enqueue_scripts', 'chatgpt_ava_enqueue_scripts');
function chatgpt_ava_enqueue_scripts()
{
    wp_enqueue_script('chatgpt-ava-script', plugin_dir_url(__FILE__) . 'js/chatgpt_ava_script.js', array('jquery'), '1.0', true);
}

function get_draft_posts_array() {
    return get_option('draft_posts_array', array());
}

function display_draft_posts() {
    $draft_posts = get_draft_posts_array();

    if (!empty($draft_posts)) {
        echo '<ul>';
        $num_lo = 1;
        foreach ($draft_posts as $post_id) {
            echo '<li> num ' . $num_lo . ' >> '; 
            echo get_the_title($post_id) . '</li>';
            $num_lo +=1;
        }
        echo '</ul>';
    } else {
        echo 'No draft posts found.';
    }
}

// Shortcode to display the ChatGPT form
add_shortcode('chatgpt_ava_form', 'chatgpt_ava_form_shortcode');
function chatgpt_ava_form_shortcode()
{
    ob_start();
    ?>
    <form id="chatgpt-ava-form">
        <textarea id="chatgpt-ava-input" rows="5" cols="30" placeholder="Enter your message..."></textarea>
        <button type="button" id="chatgpt-ava-submit">Send</button>
        <div id="chatgpt-ava-output"></div>
    </form>
    <?php
    return ob_get_clean();
}

// AJAX handler to interact with the ChatGPT API
add_action('wp_ajax_chatgpt_ava_send_message', 'chatgpt_ava_send_message');
add_action('wp_ajax_nopriv_chatgpt_ava_send_message', 'chatgpt_ava_send_message');

function chatgpt_ava_send_message()
{
    $api_key = get_option('chatgpt_ava_api_key');
    $message = sanitize_text_field($_POST['message']);

    // Helper function to truncate the content to fit within the token limit
    function chatgpt_ava_truncate_content($content, $max_tokens)
    {
        // Truncate the content to fit within the token limit
        $tokens = str_word_count(preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($content)) , 1); // for arabic
        $total_tokens = count($tokens);
        if ($total_tokens > $max_tokens) {
            $content = implode(' ', array_slice($tokens, 0, $max_tokens));
        }
        return $content;
    }

    // Limit the content length if needed
    $max_tokens = 3770; // Model's maximum context length 40
    $filtered_content = chatgpt_ava_truncate_content($message, $max_tokens);

    // Insert your ChatGPT API call here using the chat completions endpoint
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 60, // Increase the timeout value
        'body' => json_encode(array(
            'prompt' => $filtered_content, // Use 'prompt' instead of 'messages'
            'max_tokens' => 3000, // Adjust as needed
            'model' => 'gpt-3.5-turbo', // Use the Ada model name here // gpt-3.5-turbo // text-davinci-003
            'temperature' => 0.8, // Control randomness (optional, adjust as needed)
        )),
    ));

    if (is_wp_error($response)) {
        $output = $response->get_error_message();
    } else {
        $response_body = json_decode($response['body'], true);
        /*
        if (isset($response_body['choices']) && is_array($response_body['choices']) && !empty($response_body['choices'])) {
            $output = $response_body['choices'][0]['message']['content'];
        } else {
            $output = 'Generated content from ChatGPT';
        }
        */
        if (is_array($response_body) && isset($response_body['choices'][0]['message']['content'])) {
            $output = $response_body['choices'][0]['message']['content'];
        } else {
            // Log the error for debugging
            error_log('chatgpt_ava_send_message function >> ChatGPT API Response Error: ' . print_r($response_body, true)."\n", 3, CUSTOM_LOG_PATH);
            $output = 'Generated content from ChatGPT';
        }

    }

    echo $output;
    wp_die();
}

// Function for deleting draft post after failed ...
function delete_draft_posts_daily() {
    $draft_posts = get_option('draft_posts_array', array());

    if (!empty($draft_posts)) {
        foreach ($draft_posts as $post_id) {
            wp_delete_post($post_id, true);
        }

        // Clear the array after deleting the posts
        update_option('draft_posts_array', array());
    }
}
add_action('init', function () {
    if (!wp_next_scheduled('delete_draft_posts_cron')) {
        wp_schedule_event(time(), 'daily', 'delete_draft_posts_cron');
    }
});
add_action('delete_draft_posts_cron', 'delete_draft_posts_daily');

// Function to handle private rewrites and schedule it as a cron job
function chatgpt_ava_schedule_private_rewrites()
{
    if (!wp_next_scheduled('chatgpt_ava_private_rewrite_cron')) {
        wp_schedule_event(time(), 'every_fifteen_minutes', 'chatgpt_ava_private_rewrite_cron');
    }
}
add_action('wp', 'chatgpt_ava_schedule_private_rewrites');

// Function to find private posts and rewrite their content
function chatgpt_ava_private_rewrite()
{
    $api_key = get_option('chatgpt_ava_api_key');

    // Helper function to check if the post has a featured image
    function has_featured_image($post_ID)
    {
        $thumbnail_id = get_post_thumbnail_id($post_ID);
        return !empty($thumbnail_id);
    }

    /*
    // Helper function to truncate the content to fit within the token limit
    function chatgpt_ava_truncate_content($content, $max_tokens)
    {
        // Truncate the content to fit within the token limit
        $tokens = str_word_count($content, 1); // for ar preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($text_to_count)) 
        $total_tokens = count($tokens);
        if ($total_tokens > $max_tokens) {
            $content = implode(' ', array_slice($tokens, 0, $max_tokens));
        }
        return $content;
    }
    */

    // butter with latest paragrah and count all paragraphs    
        function chatgpt_ava_truncate_content($content, $max_characters)
        {
            // Check if the content length exceeds the maximum characters
            //if (mb_strlen($content) > $max_characters) {
            if (strlen(preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($content))) > $max_characters) {
                error_log('inside chatgpt_ava_truncate_content > mb_strlen(): ' . print_r(mb_strlen($content), true)."\n", 3, CUSTOM_LOG_PATH);
                // Truncate the content to fit within the character limit
                $content = mb_substr(strip_tags($content), 0, $max_characters);
                error_log('full content: ' . print_r($content, true)."\n", 3, CUSTOM_LOG_PATH);

            }
            return $content;
        }
        //
        // here we have to work to make it come rally with paragraphs so we solve cutting issue
        //

    // function count content length and make decision before calling the Api
    //  the numbers is 130 word >> less than make it draft and save the post id in database 
    //  and make cron job once daily to  delete all the posts in that array in database
    function count_and_manage_posts($post_id, $content) {

        $max_char_count = 4100; // without_space
        $min_word_count = 130;
        $post = get_post($post_id);

        error_log('-- post id : ' . $post->ID ."\n", 3, CUSTOM_LOG_PATH);
        //error_log('-- post id : ' . $post ."\n", 3, CUSTOM_LOG_PATH);// make issue...
        // when it's run inside loop?
        if ($post && $post->post_type === 'post') {

            //old way before filteration function $content = $post->post_content;

            //For string length without spaces:
            $ch_string_length = strlen( preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($content)) ) - substr_count($content, ' ');
            error_log('-- ch_string_length variable -no spaces : ' . $ch_string_length ."\n", 3, CUSTOM_LOG_PATH);

            //For word count:
            //For Arabic launguage you have to use replace("/[\x{06 ideas 
            $word_count = str_word_count(preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($content)) );
            // the only one thing we missing in this counting function is numbers 10 100 1000 any numbers

            if ($word_count < $min_word_count || $ch_string_length > $max_char_count) {
                error_log('-- here is $word count : '. $word_count ."\n", 3, CUSTOM_LOG_PATH);
                // Update post status to draft
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'draft'
                ));

                // Store the post ID in the database (add your custom table logic here)
                $draft_posts = get_option('draft_posts_array', array());
                $draft_posts[] = $post_id;
                update_option('draft_posts_array', $draft_posts);
                return false;
            } else {
                error_log('-- only TRUE case in truncate function: '."\n", 3, CUSTOM_LOG_PATH);
                return true;
            }
        } else {
            error_log('-- you are inside else if 1: '."\n", 3, CUSTOM_LOG_PATH);
            return false;
        }
        error_log('-- End of count_and_manage_posts function --' ."\n", 3, CUSTOM_LOG_PATH);
    }

    // Filteration function for all inclusions and exclusions like more news and so on...
    function filter_row_post_content($post_id){

        $post = get_post($post_id);

        $content = $post->post_content;
        
        // filter content Yallakora.com (1) case
        // Check if the content contains "اقرأ أيضاً.." text
        if (strpos($content, 'اقرأ أيضا:') !== false) {

            // Remove "اقرأ أيضاً.." text
            $content = preg_replace('/اقرأ أيضا:/', '', $content);
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

    // Helper function to count words in a text
    function count_words($text)
    {
        if ($text !== false && is_string($text)) {
            return str_word_count( preg_replace("/[\x{0600}-\x{06FF}a-zA-Z]/u", "a", strip_tags($text)) );
        } else {
            // Handle the case where content generation failed
            return 0;
        } 
    }

    function puplish_now($post_ID,$generated_content)
    {
        $updated_post = array(
            'ID' => $post_ID,
            'post_content' => $generated_content,
            'post_status' => 'publish',
        );
        wp_update_post($updated_post);
    }

    // Recursive function to generate content until it meets the minimum word count requirement
    function generate_content_with_min_word_count($filtered_content, $api_key)
    {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60, // Increase the timeout value
            'body' => json_encode(array(
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a helpful assistant.'),
                    array('role' => 'user', 'content' => $filtered_content),
                ),
                'model' => 'gpt-3.5-turbo', // Use the gpt-3.5-turbo model name here
            )),
        ));

        if (is_wp_error($response)) {
            // Log the error for debugging
            error_log('ChatGPT API Error: ' . $response->get_error_message()."\n", 3, CUSTOM_LOG_PATH);
            return false;
        }

        $response_body = json_decode($response['body'], true);

        if (isset($response_body['choices']) && is_array($response_body['choices']) && !empty($response_body['choices'])) {
            //$generated_content = $response_body['choices'][0]['message']['content'];
            $generated_content = $response_body['choices'][0]['message']['content'];

            // Add empty lines after each paragraph using wpautop()
            $generated_content_with_lines = wpautop($generated_content);

            return $generated_content_with_lines;
        } else {
            // Log the entire response for debugging
            error_log('ChatGPT API Response Error after decode: ' . print_r($response_body, true)."\n", 3, CUSTOM_LOG_PATH);

            return false;
        }
    }

    // Get private posts
    $private_posts = get_posts(array(
        'post_status' => 'private',
        'posts_per_page' => -1,
    ));

    // Implement API call to ChatGPT for each private post
    foreach ($private_posts as $post) { 

        // make sure the img is exist, in some cases orginal post insert video in place of feature img 
        // Check if the post has a featured image
        if (!has_featured_image($post->ID)) {
            // If the post doesn't have a featured image, delete and skip this post
            wp_delete_post($post->ID, true);
            continue;
        // Rest of the code to generate and update content based on the API response...
        } else {
            // filter added text like more news ...
            $filterd_content = filter_row_post_content($post->ID);
            // if <p> filteration return empty article cause of any Special characters issues inside article
            //  I don't have to apply nested code anymore, it's fine to break evrything with this type of posts / Special characters
            // stop before send to API
            if (empty($filterd_content)) {
                wp_trash_post($post->ID, true);
                break; // now this case will nver happen, it's useless code
            }
            // Let's treminate articles less than 130 Word in total, cuase ChatGPT can convert 131 to 200 word fine...            
            elseif (!count_and_manage_posts($post->ID, $filterd_content) ){
                continue;
            } else {

                $post_content = $filterd_content;
                // Updated $message to target 300 words using the Ada model
                //$message = "rewrite this article {$post_content}, covering it to become less than 300 words in total using the Arabic language. Use a cohesive structure to ensure smooth transitions between ideas, focus on summarizing and shortening the content, and make sure it's at least not less than 250 words. Make it coherent and proficient.";


                // Limit the content length if needed
                $max_tokens = 3310; // Model's maximum context length
                $filtered_content = chatgpt_ava_truncate_content($post_content, $max_tokens);
                $message = "Rewrite this article {$filtered_content}, covering it to become less than 400 words in total using the Arabic language. Structure the article with clear headings enclosed within the appropriate heading tags (e.g., <h1>, <h2>, etc.) and generate subtopics inside the article to use subheadings, each one of them should have at least one paragraph. Use a cohesive structure to ensure smooth transitions between ideas, focus on summarizing and shortening the content, and make sure it's at least not less than 300 words. Make it coherent and proficient. Remember to (1) enclose headings in the specified heading tags to make parsing the content easier. (2) Wrap even paragraphs in <p> tags for improved readability. (3) make sure that 25% of the sentences you write contain less than 20 words.";

                // Generate content and check word count until it meets the minimum requirement
                $generated_content = generate_content_with_min_word_count($message, $api_key);
                $word_count = count_words($generated_content);
                error_log('The first response from api word count: ' . print_r($word_count, true)."\n", 3, CUSTOM_LOG_PATH);
                //error_log('The response contains the following words: ' . print_r(implode(', ', $found_words), true)."\n", 3, CUSTOM_LOG_PATH);



                // Ask ChatGPT to continue generating content until it reaches the minimum word count

                sleep(12);
                
                //word_count = 0 delete post thats mean the api return error 

                $min_word_count = 250; //min_word_generated_count
                if ($word_count == 0) {
                    $result = wp_delete_post($post->ID, true); 
                }

                elseif ($word_count <= $min_word_count) {
                    while ($word_count <= $min_word_count) {

                        $message = "continue";
                        //$filtered_content = chatgpt_ava_truncate_content($message, $max_tokens);
                        $new_content = generate_content_with_min_word_count($message, $api_key);
                        sleep(12);
                        $new_content = strtolower(trim($new_content));


                        // Array of words to search for in the response
                        $search_words = array('Of course', 'Certainly', 'Sure', 'help', 'questions', 'assisting', 'tasks', 'today', 'assist');

                        // Check if any of the words exist in the response
                        $found_words = array();
                        foreach ($search_words as $word) {
                            if (strpos($new_content, $word) !== false) {
                                $found_words[] = $word;
                            }
                        }

                        if (!empty($found_words)) {
                            // Some of the words were found in the $response
                            // for debugging
                            error_log('The response contains the following words: ' . print_r(implode(', ', $found_words), true)."\n", 3, CUSTOM_LOG_PATH);
                             // Log the response for debugging

                            // right place for unset ?
                            unset($found_words);
                            // mzug latest update
                            puplish_now($post->ID,$generated_content);
                            break;

                        } else {
                            // None of the words were found in the $response
                            error_log('The response does not contain any of the specified words: '."\n", true, 3, CUSTOM_LOG_PATH);

                            $generated_content .= $new_content;
                            $word_count = count_words($generated_content);
                        }
                        // emptty after $message = "continue" every time
                        unset($found_words);

                        //The latest content has ' . $word_count . ' words. 
                        $question = 'Are you done? Answer with YES or NO'; 
                        $response = generate_content_with_min_word_count($question, $api_key);
                        sleep(3);

                        // Convert the response to lowercase for better comparison
                        $response = strtolower(trim($response));

                        if ($response === '<p>yes</p>' or $response === '<p>yes.</p>') {
                            // The user (ChatGPT) is done generating content
                            puplish_now($post->ID,$generated_content);
                            break;

                        } elseif ($response === '<p>no</p>' or $response === '<p>no.</p>') {
                            // The user (ChatGPT) is not done, ask to continue generating content
                            $message = "please continue";
                            //$filtered_content = chatgpt_ava_truncate_content($message, $max_tokens);
                            $new_content = generate_content_with_min_word_count($message, $api_key);
                            sleep(12);


                            foreach ($search_words as $word) {
                                if (strpos($new_content, $word) !== false) {
                                    $found_words[] = $word;
                                }
                            }

                            if (!empty($found_words)) {
                                // Some of the words were found in the $response
                                // for debugging
                                error_log('The response contains the following words: 2' . print_r(implode(', ', $found_words), true)."\n", 3, CUSTOM_LOG_PATH);
                                 // Log the response for debugging  

                                unset($found_words);
                                puplish_now($post->ID,$generated_content);
                                break;

                            } else {
                                // None of the words were found in the $response
                                error_log('The response does not contain any of the specified words: 2'."\n", true, 3, CUSTOM_LOG_PATH);

                                $generated_content .= $new_content;
                                $word_count = count_words($generated_content);
                                puplish_now($post->ID,$generated_content);
                            }

                        // raear to happen here this else case...1/aug / not very sure
                        } else {
                        // Log the error for debugging
                        error_log('ChatGPT YES NO Error: ' . $post->ID, 3, CUSTOM_LOG_PATH);

                        //error_log('ChatGPT YES NO API Error: ' . $response->get_error_message()); 
                        // for debugging
                        error_log('ChatGPT YES NO API Error: ' . print_r($response, true), 3, CUSTOM_LOG_PATH);
                         // Log the response for debugging               
                        wp_update_post(array('ID' => $post->ID, 'post_status' => 'draft'));
                        }
                    }   
                }       
                // Update the post with the generated content and change post status to publish
                elseif ($word_count > $min_word_count) {
                    // suppose he didn't finish ??? here ... mzug
                    $updated_post = array(
                        'ID' => $post->ID,
                        'post_content' => $generated_content, // Use the content with empty lines
                        'post_status' => 'publish',
                    );
                    wp_update_post($updated_post);
                } else {
                    // Log the error for debugging / it's not logical to be here...
                    error_log('--not logical--Content generation failed for private post ID: ' . $post->ID."\n", 3, CUSTOM_LOG_PATH);

                    wp_update_post(array('ID' => $post->ID, 'post_status' => 'draft'));
                }   
                sleep(10);
            }
        }
        error_log('*----Reach the end of foreach >> post ID: ' . $post->ID."\n", 3, CUSTOM_LOG_PATH);
        sleep(3);
    }
}


// Schedule the cron job
add_action('chatgpt_ava_private_rewrite_cron', 'chatgpt_ava_private_rewrite');

// Add custom cron schedule interval to run every 15 minutes
add_filter('cron_schedules', 'chatgpt_ava_add_custom_cron_interval');
function chatgpt_ava_add_custom_cron_interval($schedules)
{
    $schedules['every_fifteen_minutes'] = array(
        'interval' => 900, // 15 minutes (in seconds)
        'display' => __('Every 15 minutes GPT'),
    );
    return $schedules;
}
