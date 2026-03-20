<?php
/**
 * API Client for AI vision analysis
 *
 * Uses centralized API key management via Leeds Utilities.
 * Vision API calls remain local due to specialized multimodal content structure.
 * Supports both Anthropic Claude and OpenAI GPT vision models based on Leeds Utilities configuration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Alt_Text_Manager_API_Client {

    private $api_provider;
    private $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        error_log('AI Alt Text: API Client v2.0.50 with centralized key management');
        $this->load_api_configuration();
    }

    /**
     * Load API configuration from Leeds Utilities centralized settings
     */
    private function load_api_configuration() {
        // Use centralized API settings from Leeds Utilities
        if (class_exists('Leeds_Utilities_API_Settings')) {
            $settings = Leeds_Utilities_API_Settings::get_settings();
            $this->api_provider = $settings['api_provider'];

            // Get decrypted key from centralized storage
            if ($this->api_provider === 'anthropic') {
                $this->api_key = $settings['anthropic_key'];
            } else {
                $this->api_key = $settings['openai_key'];
            }

            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::debug('AI Alt Text', 'Loaded centralized API configuration', array(
                    'provider' => $this->api_provider,
                    'has_key' => !empty($this->api_key)
                ));
            }
        } else {
            // Fallback to local settings if Leeds Utilities not available
            $this->api_provider = get_option('ai_alt_text_manager_api_provider', 'anthropic');
            $this->load_local_api_key();

            error_log('AI Alt Text: Warning - Leeds Utilities not detected, using local API key storage');
        }
    }

    /**
     * Load API key from local storage (fallback only)
     */
    private function load_local_api_key() {
        $encrypted_key = '';

        if ($this->api_provider === 'anthropic') {
            $encrypted_key = get_option('ai_alt_text_manager_anthropic_key');
        } else {
            $encrypted_key = get_option('ai_alt_text_manager_openai_key');
        }

        $this->api_key = $this->decrypt_api_key($encrypted_key);
    }

    /**
     * Decrypt API key from local storage (public for test API, fallback only)
     */
    public function decrypt_api_key($encrypted_data) {
        return AI_Alt_Text_Manager_Security::decrypt_api_key($encrypted_data);
    }

    /**
     * Analyze image with AI vision and generate alt text
     *
     * @param string $image_url URL of the image to analyze
     * @param string $context Optional context (post title, etc.)
     * @return string|WP_Error Generated alt text or error
     */
    public function analyze_image($image_url, $context = '') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'ai-alt-text-manager'));
        }

        if ($this->api_provider === 'anthropic') {
            return $this->analyze_with_claude($image_url, $context);
        } else {
            return $this->analyze_with_openai($image_url, $context);
        }
    }

    /**
     * Analyze image with Claude vision API
     */
    private function analyze_with_claude($image_url, $context = '') {
        $prompt = "Analyze this image and generate a concise, descriptive alt text (maximum 125 characters) that is ADA-compliant. ";
        $prompt .= "The alt text should describe what is visible in the image clearly and objectively. ";
        $prompt .= "Do NOT use phrases like 'image of', 'picture of', or 'photo of'. ";
        $prompt .= "Be specific and descriptive. Focus on the main subject and important details. ";

        if (!empty($context)) {
            $prompt .= "Context: This image is used in content about '" . $context . "'. ";
        }

        $prompt .= "Respond with ONLY the alt text, nothing else.";

        // Get image data
        $image_data = $this->get_image_as_base64($image_url);
        if (is_wp_error($image_data)) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Failed to encode image', array(
                    'image_url' => $image_url,
                    'error' => $image_data->get_error_message()
                ));
            }
            return $image_data;
        }

        $api_url = 'https://api.anthropic.com/v1/messages';
        $model = get_option('ai_alt_text_manager_anthropic_model', 'claude-3-5-sonnet-20241022');

        if (class_exists('Leeds_Utilities_Logger')) {
            Leeds_Utilities_Logger::debug('AI Alt Text', 'Calling Claude vision API', array(
                'image_url' => $image_url,
                'model' => $model,
                'has_context' => !empty($context)
            ));
        }

        $body = [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $image_data['mime_type'],
                                'data' => $image_data['base64']
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Claude vision API call failed', array(
                    'error' => $response->get_error_message()
                ));
            }
            return $response;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Claude vision API returned error', array(
                    'error' => $response_body['error']['message']
                ));
            }
            return new WP_Error('api_error', $response_body['error']['message']);
        }

        if (!isset($response_body['content'][0]['text'])) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Invalid Claude vision API response format', array());
            }
            return new WP_Error('invalid_response', __('Invalid API response', 'ai-alt-text-manager'));
        }

        $alt_text = trim($response_body['content'][0]['text'], " \t\n\r\0\x0B\"'");

        // Ensure it's under or at 125 characters
        // Only add ellipsis if we're significantly over (more than 5 chars)
        if (strlen($alt_text) > 125) {
            // Try to cut at a word boundary
            $alt_text = substr($alt_text, 0, 125);
            $last_space = strrpos($alt_text, ' ');
            if ($last_space !== false && $last_space > 100) {
                $alt_text = substr($alt_text, 0, $last_space);
            }
        }

        if (class_exists('Leeds_Utilities_Logger')) {
            Leeds_Utilities_Logger::info('AI Alt Text', 'Generated alt text with Claude vision', array(
                'image_url' => $image_url,
                'alt_text_length' => strlen($alt_text)
            ));
        }

        return $alt_text;
    }

    /**
     * Analyze image with OpenAI GPT-4 Vision API
     */
    private function analyze_with_openai($image_url, $context = '') {
        $prompt = "Analyze this image and generate a concise, descriptive alt text (maximum 125 characters) that is ADA-compliant. ";
        $prompt .= "The alt text should describe what is visible in the image clearly and objectively. ";
        $prompt .= "Do NOT use phrases like 'image of', 'picture of', or 'photo of'. ";
        $prompt .= "Be specific and descriptive. Focus on the main subject and important details. ";

        if (!empty($context)) {
            $prompt .= "Context: This image is used in content about '" . $context . "'. ";
        }

        $prompt .= "Respond with ONLY the alt text, nothing else.";

        // Get image data as base64 (works for local development)
        $image_data = $this->get_image_as_base64($image_url);
        if (is_wp_error($image_data)) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Failed to encode image', array(
                    'image_url' => $image_url,
                    'error' => $image_data->get_error_message()
                ));
            }
            return $image_data;
        }

        $api_url = 'https://api.openai.com/v1/chat/completions';
        $model = get_option('ai_alt_text_manager_openai_model', 'gpt-4o');

        if (class_exists('Leeds_Utilities_Logger')) {
            Leeds_Utilities_Logger::debug('AI Alt Text', 'Calling OpenAI vision API', array(
                'image_url' => $image_url,
                'model' => $model,
                'has_context' => !empty($context)
            ));
        }

        // OpenAI accepts base64 images in the format: data:image/jpeg;base64,{base64_string}
        $base64_url = 'data:' . $image_data['mime_type'] . ';base64,' . $image_data['base64'];

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $base64_url
                            ]
                        ]
                    ]
                ]
            ],
            'max_completion_tokens' => 300
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'OpenAI vision API call failed', array(
                    'error' => $response->get_error_message()
                ));
            }
            return $response;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'OpenAI vision API returned error', array(
                    'error' => $response_body['error']['message']
                ));
            }
            return new WP_Error('api_error', $response_body['error']['message']);
        }

        if (!isset($response_body['choices'][0]['message']['content'])) {
            if (class_exists('Leeds_Utilities_Logger')) {
                Leeds_Utilities_Logger::error('AI Alt Text', 'Invalid OpenAI vision API response format', array());
            }
            return new WP_Error('invalid_response', __('Invalid API response', 'ai-alt-text-manager'));
        }

        $alt_text = trim($response_body['choices'][0]['message']['content'], " \t\n\r\0\x0B\"'");

        // Ensure it's under or at 125 characters
        // Only add ellipsis if we're significantly over (more than 5 chars)
        if (strlen($alt_text) > 125) {
            // Try to cut at a word boundary
            $alt_text = substr($alt_text, 0, 125);
            $last_space = strrpos($alt_text, ' ');
            if ($last_space !== false && $last_space > 100) {
                $alt_text = substr($alt_text, 0, $last_space);
            }
        }

        if (class_exists('Leeds_Utilities_Logger')) {
            Leeds_Utilities_Logger::info('AI Alt Text', 'Generated alt text with OpenAI vision', array(
                'image_url' => $image_url,
                'alt_text_length' => strlen($alt_text)
            ));
        }

        return $alt_text;
    }

    /**
     * Get image as base64 encoded string (for Claude)
     */
    private function get_image_as_base64($image_url) {
        // MARKER: Enhanced version with multiple path resolution methods (v2.0.0)
        error_log('AI Alt Text: === ENHANCED get_image_as_base64() CALLED ===');

        // Parse the URL to get just the path
        $parsed_url = parse_url($image_url);
        $url_path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        // Try multiple methods to find the file
        $possible_paths = array();

        // Method 1: Use wp_upload_dir
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        if (strpos($image_url, $base_url) === 0) {
            $possible_paths[] = str_replace($base_url, $upload_dir['basedir'], $image_url);
        }

        // Method 2: Parse URL and construct path from ABSPATH
        if (!empty($url_path)) {
            // Remove leading slash and append to ABSPATH
            $possible_paths[] = untrailingslashit(ABSPATH) . $url_path;
        }

        // Method 3: Extract everything after wp-content
        if (strpos($url_path, 'wp-content') !== false) {
            $content_path = substr($url_path, strpos($url_path, 'wp-content'));
            $possible_paths[] = untrailingslashit(ABSPATH) . '/' . $content_path;
        }

        // Method 4: Use WP_CONTENT_DIR if path contains wp-content/uploads
        if (strpos($url_path, 'wp-content/uploads') !== false) {
            $uploads_path = substr($url_path, strpos($url_path, 'wp-content/uploads') + strlen('wp-content/uploads'));
            $possible_paths[] = $upload_dir['basedir'] . $uploads_path;
        }

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Alt Text: Trying to find image: ' . $image_url);
            error_log('AI Alt Text: URL path: ' . $url_path);
            error_log('AI Alt Text: Base URL: ' . $base_url);
            error_log('AI Alt Text: Base dir: ' . $upload_dir['basedir']);
            error_log('AI Alt Text: ABSPATH: ' . ABSPATH);
            error_log('AI Alt Text: Possible paths: ' . print_r($possible_paths, true));
        }

        // Try each possible path
        foreach ($possible_paths as $file_path) {
            if (file_exists($file_path)) {
                $image_data = file_get_contents($file_path);

                if ($image_data !== false && !empty($image_data)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AI Alt Text: Found file at: ' . $file_path);
                    }

                    // Get MIME type from file
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file_path);
                    finfo_close($finfo);

                    if (empty($mime_type)) {
                        // Fallback MIME type detection
                        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                        $mime_types = array(
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'webp' => 'image/webp'
                        );
                        $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'image/jpeg';
                    }

                    return [
                        'base64' => base64_encode($image_data),
                        'mime_type' => $mime_type
                    ];
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Alt Text: File not found in any location, trying remote download');
        }

        // Fallback to remote download
        $response = wp_remote_get($image_url, [
            'timeout' => 30,
            'sslverify' => false, // Disable SSL verification for local dev
            'httpversion' => '1.1'
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'download_failed',
                sprintf(__('Error while downloading %s', 'ai-alt-text-manager'), $image_url)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error(
                'download_failed',
                sprintf(__('Failed to download image (HTTP %d): %s', 'ai-alt-text-manager'), $status_code, $image_url)
            );
        }

        $image_data = wp_remote_retrieve_body($response);
        $mime_type = wp_remote_retrieve_header($response, 'content-type');

        if (empty($image_data)) {
            return new WP_Error('empty_image', __('Could not download image', 'ai-alt-text-manager'));
        }

        return [
            'base64' => base64_encode($image_data),
            'mime_type' => $mime_type
        ];
    }

    /**
     * Validate API configuration
     */
    public function validate_configuration() {
        if (empty($this->api_key)) {
            return [
                'valid' => false,
                'message' => __('API key not configured', 'ai-alt-text-manager')
            ];
        }

        return [
            'valid' => true,
            'message' => sprintf(__('%s API key configured', 'ai-alt-text-manager'), ucfirst($this->api_provider))
        ];
    }
}
