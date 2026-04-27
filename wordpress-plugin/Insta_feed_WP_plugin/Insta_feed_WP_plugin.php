<?php
/*
Plugin Name: Insta_feed_WP_plugin
Description: Custom Instagram feed plugin with AJAX, shortcode, and Elementor widget support.
Version: 3.4.0
Author: Insta_feed_WP_plugin
Text Domain: Insta_feed_WP_plugin
Update URI: false
*/

defined('ABSPATH') || exit;

define('INSTA_FEED_WP_PLUGIN_VERSION', '3.4.0');
define('INSTA_FEED_WP_PLUGIN_FILE', __FILE__);
define('INSTA_FEED_WP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('INSTA_FEED_WP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main API access constants.
// Define these values in wp-config.php.
// define('INSTAGRAM_ACCESS_TOKEN_grpxl', 'YOUR_ACCESS_TOKEN');
// define('INSTAGRAM_USER_ID_grpxl', 'YOUR_USER_ID');

/** --------------------------------------------------
 * 1. Show an admin notice when credentials are missing.
 * ------------------------------------------------- */
add_action('admin_init', function() {
    if (!defined('INSTAGRAM_ACCESS_TOKEN_grpxl') || !defined('INSTAGRAM_USER_ID_grpxl')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Please define the Instagram access token and user ID in wp-config.php.</p></div>';
        });
    }
});

/** --------------------------------------------------
 * 2. Register the AJAX action for fetching posts.
 * ------------------------------------------------- */
add_action('init', function() {
    add_action('wp_ajax_get_instagram_photos', 'fetch_instagram_photos_directly');
    add_action('wp_ajax_nopriv_get_instagram_photos', 'fetch_instagram_photos_directly');
});

add_shortcode('Insta_feed_WP_plugin', 'Insta_feed_WP_plugin_render_feed');
add_shortcode('insta_feed_wp_plugin', 'Insta_feed_WP_plugin_render_feed');

/** --------------------------------------------------
 * 3. Render the feed markup for shortcode and Elementor.
 * ------------------------------------------------- */
function Insta_feed_WP_plugin_render_feed($atts = []) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts([
        'button_text' => 'Show More',
    ], $atts, 'Insta_feed_WP_plugin');

    ob_start();
    ?>
    <div class="Insta_feed_WP_plugin-feed-wrap" data-Insta_feed_WP_plugin="<?php echo esc_attr($instance); ?>">
        <div id="instagram-feed-<?php echo esc_attr($instance); ?>" class="Insta_feed_WP_plugin-feed"></div>

        <button id="load-more-instagram-<?php echo esc_attr($instance); ?>" class="Insta_feed_WP_plugin-load-more" data-after="">
            <?php echo esc_html($atts['button_text']); ?>
        </button>

        <div id="image-modal-<?php echo esc_attr($instance); ?>" class="Insta_feed_WP_plugin-modal">
            <div class="Insta_feed_WP_plugin-modal-overlay"></div>
            <div class="Insta_feed_WP_plugin-modal-container">
                <div class="Insta_feed_WP_plugin-modal-media-section">
                    <div class="Insta_feed_WP_plugin-modal-media-container">
                        <img class="Insta_feed_WP_plugin-modal-image-old" src="" alt="Instagram view" />
                        <img class="Insta_feed_WP_plugin-modal-image-new" src="" alt="Instagram view" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

/** --------------------------------------------------
 * 4. Process a single Instagram media item.
 * ------------------------------------------------- */
function process_single_instagram_item($item) {
    $slides = [];
    $mediaType = $item['media_type'] ?? '';

    // Carousel albums.
    if ($mediaType === 'CAROUSEL_ALBUM' && !empty($item['children']['data'])) {
        foreach ($item['children']['data'] as $child) {
            $childType = $child['media_type'] ?? '';
            $full_url = $child['media_url'] ?? '';
            $thumb_url = ($childType === 'VIDEO') ? ($child['thumbnail_url'] ?? $full_url) : $full_url;

            if ($full_url) {
                $slides[] = ['type' => $childType, 'thumb' => $thumb_url, 'full' => $full_url];
            }
        }
    }
    // Single images and videos.
    else {
        $full_url = $item['media_url'] ?? '';
        $thumb_url = ($mediaType === 'VIDEO') ? ($item['thumbnail_url'] ?? $full_url) : $full_url;

        if ($full_url) {
            $slides[] = ['type' => $mediaType, 'thumb' => $thumb_url, 'full' => $full_url];
        }
    }

    if (!empty($slides)) {
        return [
            'id'      => $item['id'],
            'slides'  => $slides,
        ];
    }
    
    return null;
}

/** --------------------------------------------------
 * 5. Fetch Instagram posts.
 * ------------------------------------------------- */
function fetch_instagram_photos_directly() {
    // Validate credentials.
    if (!defined('INSTAGRAM_ACCESS_TOKEN_grpxl') || !defined('INSTAGRAM_USER_ID_grpxl')) {
        wp_send_json_error('Instagram credentials not defined in wp-config.php');
        return;
    }

    // Read request parameters.
    $access_token  = INSTAGRAM_ACCESS_TOKEN_grpxl;
    $user_id       = INSTAGRAM_USER_ID_grpxl;
    $target_count  = 12;
    $after_cursor  = isset($_GET['after']) ? sanitize_text_field($_GET['after']) : '';
    $exclude_hashtag = '#ex';
    
    $filtered_items = [];
    $current_cursor = $after_cursor;
    $max_total_items = 1000;
    $processed_count = 0;
    $last_cursor_for_next_request = '';

    // Keep fetching until enough valid posts are collected.
    while (count($filtered_items) < $target_count && $processed_count < $max_total_items) {
        
        // Build the Instagram API URL.
        $api_url = sprintf(
            'https://graph.instagram.com/v19.0/%s/media?fields=id,media_type,media_url,thumbnail_url,caption,permalink,children{media_url,media_type,thumbnail_url}&access_token=%s&limit=40',
            $user_id,
            $access_token
        );

        // Add pagination cursor when present.
        if (!empty($current_cursor)) {
            $api_url .= '&after=' . $current_cursor;
        }

        // Send request to Instagram API.
        $response = wp_safe_remote_get($api_url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to Instagram API: ' . $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Stop when no data is available.
        if (empty($data['data'])) {
            break;
        }

        // Process posts one by one.
        foreach ($data['data'] as $item) {
            $processed_count++;
            
            $caption = $item['caption'] ?? '';
            
            // Exclude posts containing the configured hashtag.
            if (stripos($caption, $exclude_hashtag) !== false) {
                continue;
            }

            // Process the post.
            $processed_item = process_single_instagram_item($item);
            
            if ($processed_item !== null) {
                $filtered_items[] = $processed_item;
                
                // Store the cursor and stop after collecting the target count.
                if (count($filtered_items) >= $target_count) {
                    $last_cursor_for_next_request = $data['paging']['cursors']['after'] ?? '';
                    break 2;
                }
            }
        }

        // Update cursor for the next request.
        $next_cursor = $data['paging']['cursors']['after'] ?? '';
        
        // Stop when there is no new cursor.
        if (empty($next_cursor) || $next_cursor === $current_cursor) {
            $last_cursor_for_next_request = '';
            break;
        }
        
        $current_cursor = $next_cursor;
        $last_cursor_for_next_request = $next_cursor;
    }

    // Build HTML for each post.
    $photos_html = array_map(function ($item) {
        $firstSlideUrl = esc_url($item['slides'][0]['thumb']);
        $id = esc_attr($item['id']);
        $slidesJson = htmlspecialchars(json_encode($item['slides']), ENT_QUOTES, 'UTF-8');
        return sprintf(
            '<div class="instagram-photo" data-id="%s" data-slides="%s">
                <div class="slide-container">
                    <img class="slide-img slide-img-old" src="%s" alt="Instagram Post" loading="lazy">
                    <img class="slide-img slide-img-new" src="" alt="" loading="lazy">
                </div>
            </div>',
            $id,
            $slidesJson,
            $firstSlideUrl
        );
    }, $filtered_items);

    $response_data = [
        'photos'      => implode('', $photos_html),
        'next_cursor' => $last_cursor_for_next_request,
        'count'       => count($filtered_items),
        'processed'   => $processed_count,
    ];

    wp_send_json_success($response_data);
}

/** --------------------------------------------------
 * 6. Register and enqueue CSS and JavaScript.
 * ------------------------------------------------- */
add_action('wp_enqueue_scripts', 'Insta_feed_WP_plugin_enqueue_assets');
add_action('elementor/editor/after_enqueue_scripts', 'Insta_feed_WP_plugin_enqueue_assets');

function Insta_feed_WP_plugin_enqueue_assets() {
    // Main stylesheet.
    wp_enqueue_style(
        'Insta_feed_WP_plugin-css',
        INSTA_FEED_WP_PLUGIN_URL . 'assets/css/instagram-feed.css',
        [],
        INSTA_FEED_WP_PLUGIN_VERSION
    );

    // Masonry library.
    wp_enqueue_script(
        'masonry-pkgd',
        'https://unpkg.com/masonry-layout@4.2.2/dist/masonry.pkgd.min.js',
        [], '4.2.2', true
    );

    // imagesLoaded library required by Masonry.
    wp_enqueue_script(
        'imagesloaded',
        'https://unpkg.com/imagesloaded@4.1.4/imagesloaded.pkgd.min.js',
        ['masonry-pkgd'], '4.1.4', true
    );

    // Main frontend script.
    wp_enqueue_script(
        'Insta_feed_WP_plugin-js',
        INSTA_FEED_WP_PLUGIN_URL . 'assets/js/instagram-feed.js',
        ['masonry-pkgd', 'imagesloaded'],
        INSTA_FEED_WP_PLUGIN_VERSION,
        true
    );

    // Pass AJAX URL to JavaScript.
    wp_localize_script('Insta_feed_WP_plugin-js', 'Insta_feed_WP_plugin', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
}

/** --------------------------------------------------
 * 7. Elementor widget.
 * ------------------------------------------------- */
add_action('elementor/widgets/register', function($widgets_manager) {
    if (!did_action('elementor/loaded')) {
        return;
    }

    require_once INSTA_FEED_WP_PLUGIN_PATH . 'includes/class-Insta-feed-WP-plugin-elementor-widget.php';
    $widgets_manager->register(new Insta_Feed_WP_Plugin_Elementor_Widget());
});
