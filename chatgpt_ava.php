<?php
/*
Plugin Name: ChatGPT with Ava - Private Rewrites
Description: Rewrite private post content using ChatGPT API as a cron job.
Version: 1.0
Author: mZughbor
*/

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
            ?>
        </form>
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
        $tokens = str_word_count($content, 1);
        $total_tokens = count($tokens);
        if ($total_tokens > $max_tokens) {
            $content = implode(' ', array_slice($tokens, 0, $max_tokens));
        }
        return $content;
    }

    // Limit the content length if needed
    $max_tokens = 3770; // Model's maximum context length
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
            error_log('ChatGPT API Response Error: ' . print_r($response_body, true));
            $output = 'Generated content from ChatGPT';
        }

    }

    echo $output;
    wp_die();
}



// Function to handle private rewrites and schedule it as a cron job
function chatgpt_ava_schedule_private_rewrites()
{
    if (!wp_next_scheduled('chatgpt_ava_private_rewrite_cron')) {
        wp_schedule_event(time(), 'every_fifteen_minutes', 'chatgpt_ava_private_rewrite_cron');
    }
}
add_action('wp', 'chatgpt_ava_schedule_private_rewrites');

// Function to find private posts and rewrite their content
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
        $tokens = str_word_count($content, 1);
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
            if (mb_strlen($content) > $max_characters) {
                // Truncate the content to fit within the character limit
                $content = mb_substr($content, 0, $max_characters);
            }
            return $content;
        }

        //
        // here we have to work to make it come rally with paragraphs so we solve cutting issue
        //

    // Helper function to count words in a text
    function count_words($text)
    {
        if ($text !== false && is_string($text)) {
            return str_word_count(strip_tags($text));
        } else {
            // Handle the case where content generation failed
            return 0;
        } 
    }

    function puplish_now($post_ID,$generated_content){
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
            error_log('ChatGPT API Error: ' . $response->get_error_message());
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
            error_log('ChatGPT API Response Error: ' . print_r($response_body, true));
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
            //wp_trash_post($post->ID, true);
            wp_delete_post($post->ID, true);
            continue;

        // Rest of the code to generate and update content based on the API response...
        } else {

            // 3800 CHARACTER 3650 MAX GOOD

            $post_content = $post->post_content;
            // Updated $message to target 300 words using the Ada model
            $message = "rewrite this article {$post_content}, covering it to become less than 300 words in total using the Arabic language. Use a cohesive structure to ensure smooth transitions between ideas, focus on summarizing and shortening the content, and make sure it's at least not less than 250 words. Make it coherent and proficient.";

            // Limit the content length if needed
            $max_tokens = 3770; // Model's maximum context length
            $filtered_content = chatgpt_ava_truncate_content($message, $max_tokens);

            // Generate content and check word count until it meets the minimum requirement
            $min_word_count = 250;
            $generated_content = generate_content_with_min_word_count($filtered_content, $api_key);
            $word_count = count_words($generated_content);

            // Ask ChatGPT to continue generating content until it reaches the minimum word count

            sleep(12);
            
            //word_count = 0 delete post thats mean the api return error 

            if ($word_count = 0){
                $result = wp_delete_post($post->ID, true); 
            } elseif ($word_count <= $min_word_count) {
                while ($word_count <= $min_word_count) {

                    $message = "continue";
                    //$filtered_content = chatgpt_ava_truncate_content($message, $max_tokens);
                    $new_content = generate_content_with_min_word_count($message, $api_key);
                    $new_content = strtolower(trim($new_content));
                    sleep(12);

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
                        error_log('The response contains the following words: ' . print_r(implode(', ', $found_words), true)); // Log the response for debugging

                        // right place for unset ?
                        unset($found_words);
                        // mzug latest update
                        puplish_now($post->ID,$generated_content);
                        break;

                    } else {
                        // None of the words were found in the $response
                        error_log('The response does not contain any of the specified words: ', true); 
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
                            error_log('The response contains the following words: 2' . print_r(implode(', ', $found_words), true)); // Log the response for debugging  

                            unset($found_words);
                            puplish_now($post->ID,$generated_content);
                            break;

                        } else {
                            // None of the words were found in the $response
                            error_log('The response does not contain any of the specified words: 2', true); 
                            $generated_content .= $new_content;
                            $word_count = count_words($generated_content);
                            puplish_now($post->ID,$generated_content);
                        }


                    // raear to happen here this else case...1/aug / not very sure
                    } else {
                        // Log the error for debugging
                        error_log('ChatGPT YES NO Error: ' . $post->ID);
                        //error_log('ChatGPT YES NO API Error: ' . $response->get_error_message()); 
                        // for debugging
                        error_log('ChatGPT YES NO API Error: ' . print_r($response, true)); // Log the response for debugging               
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
                // Log the error for debugging
                error_log('Content generation failed for private post ID: ' . $post->ID);
                wp_update_post(array('ID' => $post->ID, 'post_status' => 'draft'));
            }   
            sleep(10);
        }
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
        'display' => __('Every 15 minutes'),
    );
    return $schedules;
}
