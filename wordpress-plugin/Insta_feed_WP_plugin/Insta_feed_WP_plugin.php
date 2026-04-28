<?php
/*
Plugin Name: Insta_feed_WP_plugin
Description: Custom Instagram feed plugin with AJAX, shortcode, and Elementor widget support.
Version: 3.4.12
Author: Insta_feed_WP_plugin
Text Domain: Insta_feed_WP_plugin
Update URI: false
*/

defined('ABSPATH') || exit;

define('INSTA_FEED_WP_PLUGIN_VERSION', '3.4.12');
define('INSTA_FEED_WP_PLUGIN_FILE', __FILE__);
define('INSTA_FEED_WP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('INSTA_FEED_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INSTA_FEED_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('INSTA_FEED_WP_PLUGIN_GITHUB_OWNER', 'codaux');
define('INSTA_FEED_WP_PLUGIN_GITHUB_REPO', 'Insta_feed_WP_plugin');
define('INSTA_FEED_WP_PLUGIN_RELEASE_ASSET', 'Insta_feed_WP_plugin.zip');

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
    $atts = shortcode_atts([
        'button_text' => 'Show More',
    ], $atts, 'Insta_feed_WP_plugin');

    ob_start();
    ?>
    <div id="instagram-feed"></div>

    <button id="load-more-instagram" data-after="">
        <?php echo esc_html($atts['button_text']); ?>
    </button>

    <div id="image-modal">
        <div id="modal-overlay"></div>
        <div id="modal-container">
            <div id="modal-media-section">
                <div id="modal-media-container">
                    <img id="modal-image-old" src="" alt="Instagram view" />
                    <img id="modal-image-new" src="" alt="Instagram view" />
                    <video id="modal-video-old" controls style="display: none;"></video>
                    <video id="modal-video-new" controls style="display: none;"></video>
                </div>
            </div>

            <div id="modal-caption-section">
                <div id="btn-container">
                    <button id="modal-prev-btn" class="modal-nav-btn" type="button">&lsaquo;</button>
                    <button id="modal-next-btn" class="modal-nav-btn" type="button">&rsaquo;</button>
                    <button id="modal-close-btn" class="modal-nav-btn" type="button">&times;</button>
                </div>
                <div id="modal-caption-content">
                    <p id="modal-caption-text" dir="auto"></p>
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
            'id'        => $item['id'],
            'slides'    => $slides,
            'caption'   => $item['caption'] ?? '',
            'permalink' => $item['permalink'] ?? '',
        ];
    }
    
    return null;
}

function Insta_feed_WP_plugin_instagram_api_base_url() {
    if (defined('INSTAGRAM_API_BASE_URL_grpxl') && INSTAGRAM_API_BASE_URL_grpxl) {
        return untrailingslashit(INSTAGRAM_API_BASE_URL_grpxl);
    }

    $api_version = defined('INSTAGRAM_API_VERSION_grpxl') && INSTAGRAM_API_VERSION_grpxl
        ? INSTAGRAM_API_VERSION_grpxl
        : 'v19.0';

    return 'https://graph.instagram.com/' . trim($api_version, '/');
}

function Insta_feed_WP_plugin_decode_pagination_cursor($cursor) {
    $cursor = (string) $cursor;

    if (strpos($cursor, 'state:') !== 0) {
        return [
            'after'  => $cursor,
            'offset' => 0,
        ];
    }

    $payload = substr($cursor, 6);
    $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
    $decoded_payload = base64_decode(strtr($payload, '-_', '+/'), true);

    if ($decoded_payload === false) {
        return [
            'after'  => '',
            'offset' => 0,
        ];
    }

    $decoded = json_decode($decoded_payload, true);

    if (!is_array($decoded)) {
        return [
            'after'  => '',
            'offset' => 0,
        ];
    }

    return [
        'after'  => isset($decoded['after']) ? sanitize_text_field($decoded['after']) : '',
        'offset' => isset($decoded['offset']) ? max(0, absint($decoded['offset'])) : 0,
    ];
}

function Insta_feed_WP_plugin_encode_pagination_cursor($after, $offset) {
    $after = (string) $after;
    $offset = max(0, absint($offset));

    if ($after === '' && $offset === 0) {
        return '';
    }

    $payload = wp_json_encode([
        'after'  => $after,
        'offset' => $offset,
    ]);

    return 'state:' . rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
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
    $debug_enabled = !empty($_GET['debug']) && current_user_can('manage_options');
    
    $cursor_state = Insta_feed_WP_plugin_decode_pagination_cursor($after_cursor);
    $filtered_items = [];
    $current_cursor = $cursor_state['after'];
    $current_offset = $cursor_state['offset'];
    $max_total_items = 1000;
    $processed_count = 0;
    $last_cursor_for_next_request = '';
    $last_debug = [];

    // Keep fetching pages, but remember the raw item offset inside a page so pagination never skips posts.
    while (count($filtered_items) < $target_count && $processed_count < $max_total_items) {
        
        // Build the Instagram API URL.
        $api_url = add_query_arg(
            [
                'fields'       => 'id,media_type,media_url,thumbnail_url,caption,permalink,children{media_url,media_type,thumbnail_url}',
                'access_token' => $access_token,
                'limit'        => 40,
            ],
            Insta_feed_WP_plugin_instagram_api_base_url() . '/' . rawurlencode($user_id) . '/media'
        );

        // Add pagination cursor when present.
        if (!empty($current_cursor)) {
            $api_url = add_query_arg('after', $current_cursor, $api_url);
        }

        // Send request to Instagram API.
        $response = wp_safe_remote_get($api_url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to Instagram API: ' . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $last_debug = [
            'api_base_url'  => Insta_feed_WP_plugin_instagram_api_base_url(),
            'http_code'     => $response_code,
            'body_preview'  => $debug_enabled ? substr(wp_strip_all_tags($body), 0, 500) : '',
            'current_after' => $current_cursor,
            'current_offset' => $current_offset,
        ];

        if ($response_code < 200 || $response_code >= 300) {
            wp_send_json_error([
                'message' => 'Instagram API returned HTTP ' . $response_code,
                'debug'   => $debug_enabled ? $last_debug : null,
            ]);
            return;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => 'Instagram API returned invalid JSON: ' . json_last_error_msg(),
                'debug'   => $debug_enabled ? $last_debug : null,
            ]);
            return;
        }

        if (!empty($data['error'])) {
            $instagram_error = $data['error'];
            $message = $instagram_error['message'] ?? 'Instagram API returned an error.';
            wp_send_json_error([
                'message' => $message,
                'type'    => $instagram_error['type'] ?? '',
                'code'    => $instagram_error['code'] ?? '',
                'debug'   => $debug_enabled ? $last_debug : null,
            ]);
            return;
        }

        // Stop when no data is available.
        if (empty($data['data'])) {
            break;
        }

        $page_items = array_values($data['data']);
        $page_item_count = count($page_items);
        $next_cursor = $data['paging']['cursors']['after'] ?? '';

        if ($current_offset >= $page_item_count) {
            if (empty($next_cursor) || $next_cursor === $current_cursor) {
                $last_cursor_for_next_request = '';
                break;
            }

            $current_cursor = $next_cursor;
            $current_offset = 0;
            $last_cursor_for_next_request = $current_cursor;
            continue;
        }

        // Process posts one by one.
        foreach ($page_items as $page_index => $item) {
            if ($page_index < $current_offset) {
                continue;
            }

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
                    $next_offset = $page_index + 1;
                    $last_cursor_for_next_request = $next_offset < $page_item_count
                        ? Insta_feed_WP_plugin_encode_pagination_cursor($current_cursor, $next_offset)
                        : $next_cursor;
                    break 2;
                }
            }
        }

        // Stop when there is no new cursor.
        if (empty($next_cursor) || $next_cursor === $current_cursor) {
            $last_cursor_for_next_request = '';
            break;
        }
        
        $current_cursor = $next_cursor;
        $current_offset = 0;
        $last_cursor_for_next_request = $next_cursor;
    }

    // Build HTML for each post.
    $photos_html = array_map(function ($item) {
        $firstSlideUrl = esc_url($item['slides'][0]['thumb']);
        $id = esc_attr($item['id']);
        $slidesJson = esc_attr(wp_json_encode($item['slides']));
        $caption = esc_attr($item['caption'] ?? '');
        $permalink = esc_url($item['permalink'] ?? '');
        return sprintf(
            '<div class="instagram-photo" data-id="%s" data-slides="%s" data-caption="%s" data-permalink="%s">
                <div class="slide-container">
                    <img class="slide-img slide-img-old" src="%s" alt="Instagram Post" loading="lazy">
                    <img class="slide-img slide-img-new" src="" alt="" loading="lazy">
                </div>
            </div>',
            $id,
            $slidesJson,
            $caption,
            $permalink,
            $firstSlideUrl
        );
    }, $filtered_items);

    $response_data = [
        'photos'      => implode('', $photos_html),
        'next_cursor' => $last_cursor_for_next_request,
        'count'       => count($filtered_items),
        'processed'   => $processed_count,
        'message'     => count($filtered_items) > 0 ? '' : 'Instagram returned no visible posts.',
    ];

    if ($debug_enabled) {
        $response_data['debug'] = $last_debug;
    }

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

    // Use WordPress' bundled copies so the feed does not depend on an external CDN.
    wp_enqueue_script('masonry');
    wp_enqueue_script('imagesloaded');

    // Main frontend script.
    wp_enqueue_script(
        'Insta_feed_WP_plugin-js',
        INSTA_FEED_WP_PLUGIN_URL . 'assets/js/instagram-feed.js',
        [],
        INSTA_FEED_WP_PLUGIN_VERSION,
        true
    );

    // Pass AJAX URL to JavaScript.
    $instagram_feed_ajax = [
        'ajax_url' => admin_url('admin-ajax.php'),
    ];

    wp_localize_script('Insta_feed_WP_plugin-js', 'rezaGrpxl', $instagram_feed_ajax);
    wp_localize_script('Insta_feed_WP_plugin-js', 'Insta_feed_WP_plugin', $instagram_feed_ajax);
}

add_action('wp_footer', 'Insta_feed_WP_plugin_print_script_fallback', 99);

function Insta_feed_WP_plugin_print_script_fallback() {
    if (is_admin()) {
        return;
    }

    $script_url = INSTA_FEED_WP_PLUGIN_URL . 'assets/js/instagram-feed.js?ver=' . rawurlencode(INSTA_FEED_WP_PLUGIN_VERSION);
    ?>
    <script>
    (function() {
        window.rezaGrpxl = window.rezaGrpxl || { ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> };
        window.Insta_feed_WP_plugin = window.Insta_feed_WP_plugin || window.rezaGrpxl;

        if (!document.getElementById('instagram-feed') || window.InstaFeedFrontendLoaded) {
            return;
        }

        var script = document.createElement('script');
        script.src = <?php echo wp_json_encode($script_url); ?>;
        script.async = false;
        document.body.appendChild(script);
    })();
    </script>
    <?php
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

/** --------------------------------------------------
 * 8. GitHub Releases updater.
 * ------------------------------------------------- */
if (is_admin()) {
    require_once INSTA_FEED_WP_PLUGIN_PATH . 'includes/class-Insta-feed-WP-plugin-updater.php';
    new Insta_Feed_WP_Plugin_Updater();
}
