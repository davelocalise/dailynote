<?php
/*
Plugin Name: Daily Note Summary
Description: Combines microposts into a daily summary post.
Version: 1.0
Author: Your Name
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
    global $wpdb;

    $microposts = get_posts([
        'post_type' => 'micropost',
        'meta_query' => [
            [
                'key' => '_included_in_daily_note',
                'compare' => 'NOT EXISTS',
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
    $tags = [];

    foreach ($microposts as $micropost) {
        $content .= '<div>' . apply_filters('the_content', $micropost->post_content) . '<br><a href="' . get_permalink($micropost->ID) . '">Original post</a></div><hr>';        

        $tags = array_merge($tags, wp_get_post_tags($micropost->ID, ['fields
