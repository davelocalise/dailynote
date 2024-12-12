<?php
/*
Plugin Name: Daily Note Summary
Description: Combines microposts into a daily summary post.
Version: 1.2
Author: Dave Briggs and team
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add Dashboard Widget
add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('daily_note_summary_widget', 'Daily Note Summary', 'render_daily_note_summary_widget');
});

function render_daily_note_summary_widget() {
    $last_run = get_option('daily_note_last_run');
    $last_run_data = get_option('daily_note_last_run_data');

    echo '<p>Last run: ' . ($last_run ? date('F j, Y, g:i a', $last_run) : 'Never') . '</p>';
    if ($last_run_data) {
        echo '<p>Microposts included: ' . esc_html($last_run_data['count']) . '</p>';
        echo '<p><a href="' . esc_url(get_permalink($last_run_data['post_id'])) . '" target="_blank">View last daily note</a></p>';
    }
    echo '<button id="run_daily_note_script" class="button">Run script now</button>';
    echo '<script>
        document.getElementById("run_daily_note_script").addEventListener("click", function () {
            fetch("' . admin_url('admin-ajax.php?action=run_daily_note_script') . '")
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    location.reload();
                });
        });
    </script>';
}

// Add AJAX Action
add_action('wp_ajax_run_daily_note_script', 'run_daily_note_script');

function run_daily_note_script() {
    
    $category_name = 'daily note';

    // Get the user ID for the username 'davebriggs'
    $user = get_user_by('login', 'davebriggs');
    $author_id = $user ? $user->ID : get_current_user_id();

    $microposts = get_posts([
        'post_type' => 'micropost',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_included_in_daily_note',
                'compare' => 'NOT EXISTS', // Checks if the meta key does not exist
            ],
            [
                'key' => '_included_in_daily_note',
                'value' => '',
                'compare' => '=', // Checks if the meta key exists but has an empty value
            ],
        ],
        'orderby' => 'date',
        'order' => 'ASC',
        'numberposts' => -1,
    ]);

    if (empty($microposts)) {
        wp_send_json(['message' => 'No microposts to include.']);
    }

    $content = '';
    $all_tags = [];
    $processed_ids = [];
    $micropost_count = 0; // Count how many microposts we add

    foreach ($microposts as $micropost) {
       
        // Build the content for each micropost
        if (!empty($micropost->post_title)) $content .= '<h3>' . $micropost->post_title . '</h3>';
        $content .= '<div>' . apply_filters('the_content',$micropost->post_content) . '</div>';
        $content .= '<p><a href="' . get_permalink($micropost->ID) . '">#<span style="display:none"> - micropost '.$micropost->ID.'</span></a></p>';
        $content .= '<hr>';
        
        // Collect tags for the post
        $post_tags = wp_get_post_tags($micropost->ID, ['fields' => 'names']);
        if (!empty($post_tags)) {
            $all_tags = array_merge($all_tags, $post_tags);
        }
        
        // Mark the micropost as processed
        $processed_ids[] = $micropost->ID;
        $micropost_count++;
    }
    
    // Get unique tags
    $all_tags = array_unique($all_tags);
    
    // Get or create the category ID
    $category_id = get_cat_ID($category_name);
    if (!$category_id) {
        $category_id = wp_create_category($category_name);
    }

    // Prepare the new daily note post
    $new_post = [
        'post_title'   => '📅 Daily Note: ' . date('F j, Y'),
        'post_content' => $content,
        'post_status'  => 'draft',
        'post_author'  => $author_id,
        'post_category' => [$category_id],
        'tags_input'   => $all_tags,
    ];
    
    // Insert the new post
    $new_post_id = wp_insert_post($new_post);
    
    // Update each micropost as processed
    foreach ($processed_ids as $id) {
        update_post_meta($id, '_included_in_daily_note', $new_post_id);
    }
    
    // Store the last run time and the count of microposts
    update_option('daily_note_last_run', current_time('timestamp'));
    update_option('daily_note_last_run_data', ["count"=>$micropost_count,"post_id"=>$new_post_id]);
    
    if (!empty($new_post_id)) {
        return json_encode(["message" => "Post created with ".$micropost_count." microposts incorporated."]);
    } else {
        return json_encode(["message" => "Problem creating post."]);
    }
}
