<?php
/**
 * Admin Pages Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Alt_Text_Manager_Admin_Pages {

    private $api_client;

    /**
     * Initialize
     */
    public function init() {
        $this->api_client = new AI_Alt_Text_Manager_API_Client();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ai_alt_text_analyze_image', [$this, 'ajax_analyze_image']);
        add_action('wp_ajax_ai_alt_text_get_images', [$this, 'ajax_get_images']);
        add_action('wp_ajax_ai_alt_text_manager_test_api', [$this, 'ajax_test_api']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Alt Text Manager', 'ai-alt-text-manager'),
            __('Alt Text AI', 'ai-alt-text-manager'),
            'upload_files',
            'ai-alt-text-manager',
            [$this, 'render_main_page'],
            'dashicons-format-image',
            75
        );

        add_submenu_page(
            'ai-alt-text-manager',
            __('Settings', 'ai-alt-text-manager'),
            __('Settings', 'ai-alt-text-manager'),
            'manage_options',
            'ai-alt-text-manager-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ('toplevel_page_ai-alt-text-manager' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'ai-alt-text-manager-admin',
            AI_ALT_TEXT_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AI_ALT_TEXT_MANAGER_VERSION
        );

        wp_enqueue_script(
            'ai-alt-text-manager-admin',
            AI_ALT_TEXT_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AI_ALT_TEXT_MANAGER_VERSION,
            true
        );

        wp_localize_script('ai-alt-text-manager-admin', 'aiAltTextManager', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_alt_text_nonce'),
            'strings' => [
                'analyzing' => __('Analyzing...', 'ai-alt-text-manager'),
                'success' => __('Alt text updated!', 'ai-alt-text-manager'),
                'error' => __('Error:', 'ai-alt-text-manager'),
                'selectImages' => __('Please select at least one image', 'ai-alt-text-manager'),
                'processing' => __('Processing', 'ai-alt-text-manager'),
                'of' => __('of', 'ai-alt-text-manager')
            ]
        ]);
    }

    /**
     * Render main page
     */
    public function render_main_page() {
        if (!current_user_can('upload_files')) {
            return;
        }

        // Check API configuration
        $config_status = $this->api_client->validate_configuration();

        // Get pagination settings
        $items_per_page = get_option('ai_alt_text_manager_items_per_page', 20);
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        // Get filter settings
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';

        // Query images
        $query_args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $items_per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Apply filters
        if ($filter === 'no-alt') {
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                ]
            ];
        } elseif ($filter === 'has-alt') {
            $query_args['meta_query'] = [
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '!='
                ]
            ];
        }

        $query = new WP_Query($query_args);

        ?>
        <div class="wrap">
            <h1><?php _e('AI Alt Text Manager', 'ai-alt-text-manager'); ?></h1>

            <?php if (!$config_status['valid']): ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('Configuration Required:', 'ai-alt-text-manager'); ?></strong>
                        <?php echo esc_html($config_status['message']); ?>
                        <a href="<?php echo admin_url('admin.php?page=ai-alt-text-manager-settings'); ?>">
                            <?php _e('Configure API Settings', 'ai-alt-text-manager'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="ai-alt-text-toolbar">
                <div class="ai-alt-text-filters">
                    <label for="image-filter"><?php _e('Filter:', 'ai-alt-text-manager'); ?></label>
                    <select id="image-filter" onchange="window.location.href=this.value">
                        <option value="<?php echo admin_url('admin.php?page=ai-alt-text-manager&filter=all'); ?>" <?php selected($filter, 'all'); ?>>
                            <?php _e('All Images', 'ai-alt-text-manager'); ?>
                        </option>
                        <option value="<?php echo admin_url('admin.php?page=ai-alt-text-manager&filter=no-alt'); ?>" <?php selected($filter, 'no-alt'); ?>>
                            <?php _e('Missing Alt Text', 'ai-alt-text-manager'); ?>
                        </option>
                        <option value="<?php echo admin_url('admin.php?page=ai-alt-text-manager&filter=has-alt'); ?>" <?php selected($filter, 'has-alt'); ?>>
                            <?php _e('Has Alt Text', 'ai-alt-text-manager'); ?>
                        </option>
                    </select>
                </div>

                <div class="ai-alt-text-actions">
                    <button type="button" id="select-all" class="button">
                        <?php _e('Select All (This Page)', 'ai-alt-text-manager'); ?>
                    </button>
                    <button type="button" id="select-all-pages" class="button">
                        <?php _e('Select All Pages', 'ai-alt-text-manager'); ?>
                    </button>
                    <button type="button" id="deselect-all" class="button">
                        <?php _e('Deselect All', 'ai-alt-text-manager'); ?>
                    </button>
                    <button type="button" id="process-selected" class="button button-primary" <?php echo !$config_status['valid'] ? 'disabled' : ''; ?>>
                        <?php _e('Generate Alt Text for Selected', 'ai-alt-text-manager'); ?>
                    </button>
                </div>
            </div>

            <!-- Processing status will be inserted here by JavaScript -->
            <div id="processing-container"></div>

            <?php if ($query->have_posts()): ?>
                <div class="ai-alt-text-grid" data-all-image-ids="<?php
                    // Get all image IDs for "Select All Pages" functionality
                    $all_args = $query_args;
                    $all_args['posts_per_page'] = -1;
                    $all_args['fields'] = 'ids';
                    $all_query = new WP_Query($all_args);
                    echo esc_attr(implode(',', $all_query->posts));
                ?>">
                    <?php while ($query->have_posts()): $query->the_post();
                        $attachment_id = get_the_ID();
                        // Use full size for AI analysis (more reliable, better quality)
                        $image_url = wp_get_attachment_image_url($attachment_id, 'full');
                        $thumb_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                        $title = get_the_title();

                        // Get attached post context if available
                        $attached_post = get_post($query->post->post_parent);
                        $context = $attached_post ? $attached_post->post_title : '';
                        ?>
                        <div class="ai-alt-text-item" data-id="<?php echo esc_attr($attachment_id); ?>" data-url="<?php echo esc_url($image_url); ?>" data-context="<?php echo esc_attr($context); ?>">
                            <div class="ai-alt-text-checkbox">
                                <input type="checkbox" class="image-select" value="<?php echo esc_attr($attachment_id); ?>">
                            </div>
                            <div class="ai-alt-text-thumbnail">
                                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($alt_text); ?>">
                            </div>
                            <div class="ai-alt-text-details">
                                <div class="ai-alt-text-title">
                                    <strong><?php echo esc_html($title); ?></strong>
                                    <?php if ($context): ?>
                                        <br><small><?php printf(__('Used in: %s', 'ai-alt-text-manager'), esc_html($context)); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="ai-alt-text-current">
                                    <label><?php _e('Current Alt Text:', 'ai-alt-text-manager'); ?></label>
                                    <div class="alt-text-value <?php echo empty($alt_text) ? 'empty' : ''; ?>">
                                        <?php echo empty($alt_text) ? '<em>' . __('No alt text', 'ai-alt-text-manager') . '</em>' : esc_html($alt_text); ?>
                                    </div>
                                </div>
                                <div class="ai-alt-text-new" style="display: none;">
                                    <label><?php _e('New Alt Text:', 'ai-alt-text-manager'); ?></label>
                                    <div class="alt-text-value new"></div>
                                </div>
                                <div class="ai-alt-text-status"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <?php
                // Pagination
                $total_pages = $query->max_num_pages;
                if ($total_pages > 1):
                    ?>
                    <div class="ai-alt-text-pagination">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => __('&laquo; Previous', 'ai-alt-text-manager'),
                            'next_text' => __('Next &raquo;', 'ai-alt-text-manager')
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('No images found.', 'ai-alt-text-manager'); ?></p>
                </div>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>
        </div>
        <?php
    }

    /**
     * Render settings page (placeholder - actual implementation in class-settings.php)
     */
    public function render_settings_page() {
        // This will be handled by the Settings class
    }

    /**
     * AJAX handler for analyzing images
     */
    public function ajax_analyze_image() {
        check_ajax_referer('ai_alt_text_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-alt-text-manager'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';

        if (!$attachment_id || !$image_url) {
            wp_send_json_error(__('Invalid image data', 'ai-alt-text-manager'));
        }

        error_log('AI Alt Text Manager - Analyzing image ' . $attachment_id . ': ' . $image_url);

        try {
            $alt_text = $this->api_client->analyze_image($image_url, $context);

            if (is_wp_error($alt_text)) {
                error_log('AI Alt Text Manager - Error: ' . $alt_text->get_error_message());
                wp_send_json_error($alt_text->get_error_message());
            }

            // Update alt text
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

            error_log('AI Alt Text Manager - Success: ' . $alt_text);

            wp_send_json_success([
                'alt_text' => $alt_text,
                'message' => __('Alt text updated successfully', 'ai-alt-text-manager')
            ]);

        } catch (Exception $e) {
            error_log('AI Alt Text Manager - Exception: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler to get image data for multiple IDs
     */
    public function ajax_get_images() {
        check_ajax_referer('ai_alt_text_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-alt-text-manager'));
        }

        $image_ids = isset($_POST['image_ids']) ? $_POST['image_ids'] : [];

        if (!is_array($image_ids) || empty($image_ids)) {
            wp_send_json_error(__('No image IDs provided', 'ai-alt-text-manager'));
        }

        $images_data = [];

        foreach ($image_ids as $id) {
            $attachment_id = absint($id);
            // Use full size for AI analysis (more reliable, better quality)
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');

            if (!$image_url) {
                continue;
            }

            // Get attached post context if available
            $post = get_post($attachment_id);
            $attached_post = $post && $post->post_parent ? get_post($post->post_parent) : null;
            $context = $attached_post ? $attached_post->post_title : '';
            $title = get_the_title($attachment_id);

            $images_data[] = [
                'id' => $attachment_id,
                'url' => $image_url,
                'context' => $context,
                'title' => $title
            ];
        }

        wp_send_json_success([
            'images' => $images_data
        ]);
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        // Security check
        check_ajax_referer('ai_alt_text_manager_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'ai-alt-text-manager')]);
        }

        // Get provider and model from request
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'anthropic';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $use_saved_key = isset($_POST['use_saved_key']) && $_POST['use_saved_key'];
        $test_api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        // Get API key - use saved key if requested, otherwise use the one from form
        $api_key = '';
        if ($use_saved_key) {
            // Get saved key from database
            if ($provider === 'anthropic') {
                $api_key = get_option('ai_alt_text_manager_anthropic_key');
            } else {
                $api_key = get_option('ai_alt_text_manager_openai_key');
            }

            // Decrypt the saved key
            if (!empty($api_key)) {
                $api_key = AI_Alt_Text_Manager_Security::decrypt_api_key($api_key);
            }
        } else {
            // Use the key from the test form
            $api_key = $test_api_key;
        }

        // Validate we have a key
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API key not configured. Please save your API key first.', 'ai-alt-text-manager')]);
        }

        // Use default model if not provided
        if (empty($model)) {
            $model = $provider === 'anthropic' ? 'claude-3-5-sonnet-20241022' : 'gpt-4o';
        }

        // Test with a simple text prompt (no image needed for test)
        try {
            $api_url = '';
            $body = [];
            $headers = [];

            if ($provider === 'anthropic') {
                $api_url = 'https://api.anthropic.com/v1/messages';
                $body = [
                    'model' => $model,
                    'max_tokens' => 100,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Respond with: OK'
                        ]
                    ]
                ];
                $headers = [
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json'
                ];
            } else {
                $api_url = 'https://api.openai.com/v1/chat/completions';
                $body = [
                    'model' => $model,
                    'max_completion_tokens' => 100,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Respond with: OK'
                        ]
                    ]
                ];
                $headers = [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ];
            }

            $response = wp_remote_post($api_url, [
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => $response->get_error_message()]);
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($response_body['error'])) {
                $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] :
                                (isset($response_body['error']['type']) ? $response_body['error']['type'] : __('Unknown error', 'ai-alt-text-manager'));
                wp_send_json_error(['message' => $error_message]);
            }

            $provider_name = $provider === 'anthropic' ? 'Anthropic' : 'OpenAI';
            $message = sprintf(
                __('✓ %s connection successful! Model: %s', 'ai-alt-text-manager'),
                $provider_name,
                $model
            );
            wp_send_json_success(['message' => $message]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
