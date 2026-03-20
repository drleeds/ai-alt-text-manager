<?php
/**
 * Security Class
 *
 * Handles all security-related functionality including API key encryption/decryption,
 * nonce verification, capability checks, and input sanitization.
 *
 * @package AI_Alt_Text_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Alt_Text_Manager_Security {

    /**
     * Encrypt an API key using WordPress salts
     *
     * @param string $key The API key to encrypt
     * @return string Base64 encoded encrypted key with IV
     */
    public static function encrypt_api_key($key) {
        if (empty($key)) {
            return '';
        }

        // Use WordPress auth salt for encryption (must match other Leeds plugins)
        $encryption_key = wp_salt('auth');

        // Generate random IV
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt the key
        $encrypted = openssl_encrypt($key, 'aes-256-cbc', $encryption_key, 0, $iv);

        // Combine IV and encrypted data
        $result = base64_encode($iv . '::' . $encrypted);

        return $result;
    }

    /**
     * Decrypt an API key
     *
     * @param string $encrypted_key Base64 encoded encrypted key with IV
     * @return string Decrypted API key
     */
    public static function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }

        // Decode base64
        $decoded = base64_decode($encrypted_key);
        if ($decoded === false) {
            return '';
        }

        // Split IV and encrypted data
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return '';
        }

        list($iv, $encrypted) = $parts;

        // Use WordPress auth salt for decryption (must match other Leeds plugins)
        $encryption_key = wp_salt('auth');

        // Decrypt
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Verify AJAX nonce
     *
     * @param string $nonce Nonce value to verify
     * @param string $action Nonce action
     * @return bool True if valid, false otherwise
     */
    public static function verify_ajax_nonce($nonce, $action = 'ai_alt_text_manager_ajax_nonce') {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Check if current user can manage plugin
     *
     * @return bool True if user has capability
     */
    public static function current_user_can_manage() {
        return current_user_can('manage_options');
    }

    /**
     * Check if current user can upload files
     *
     * @return bool True if user has capability
     */
    public static function current_user_can_upload() {
        return current_user_can('upload_files');
    }

    /**
     * Sanitize array of attachment IDs
     *
     * @param array $ids Array of attachment IDs
     * @return array Sanitized array of integers
     */
    public static function sanitize_attachment_ids($ids) {
        if (!is_array($ids)) {
            return [];
        }

        return array_filter(array_map('intval', $ids), function($id) {
            return $id > 0;
        });
    }

    /**
     * Validate API provider
     *
     * @param string $provider Provider name
     * @return string Valid provider name or default
     */
    public static function validate_api_provider($provider) {
        $valid_providers = ['anthropic', 'openai'];

        if (in_array($provider, $valid_providers, true)) {
            return $provider;
        }

        return 'anthropic'; // Default
    }

    /**
     * Validate AI model for vision
     *
     * @param string $model Model name
     * @param string $provider Provider name
     * @return string Valid model name or default for provider
     */
    public static function validate_ai_model($model, $provider) {
        $valid_models = [
            'anthropic' => [
                'claude-3-5-sonnet-20241022',
                'claude-3-opus-20240229',
                'claude-3-sonnet-20240229',
                'claude-3-haiku-20240307'
            ],
            'openai' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo'
            ]
        ];

        if (isset($valid_models[$provider]) && in_array($model, $valid_models[$provider], true)) {
            return $model;
        }

        // Return default for provider
        return $provider === 'anthropic' ? 'claude-3-5-sonnet-20241022' : 'gpt-4o';
    }

    /**
     * Sanitize alt text
     *
     * @param string $alt_text Alt text to sanitize
     * @return string Sanitized alt text
     */
    public static function sanitize_alt_text($alt_text) {
        // Remove HTML tags
        $alt_text = strip_tags($alt_text);

        // Sanitize
        $alt_text = sanitize_text_field($alt_text);

        // Trim to 125 characters (ADA recommendation)
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 125);
            $last_space = strrpos($alt_text, ' ');
            if ($last_space !== false && $last_space > 100) {
                $alt_text = substr($alt_text, 0, $last_space);
            }
        }

        return $alt_text;
    }

    /**
     * Log security event
     *
     * @param string $event Event description
     * @param array $context Additional context
     */
    public static function log_event($event, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $user = wp_get_current_user();
        $log_entry = sprintf(
            '[AI Alt Text Manager Security] %s - User: %s (ID: %d) - Context: %s',
            $event,
            $user->user_login,
            $user->ID,
            json_encode($context)
        );

        error_log($log_entry);
    }

    /**
     * Generate secure nonce for AJAX actions
     *
     * @param string $action Action name
     * @return string Nonce value
     */
    public static function create_ajax_nonce($action = 'ai_alt_text_manager_ajax_nonce') {
        return wp_create_nonce($action);
    }

    /**
     * Validate image attachment
     *
     * @param int $attachment_id Attachment ID
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public static function validate_image_attachment($attachment_id) {
        $attachment_id = intval($attachment_id);

        if ($attachment_id <= 0) {
            return new WP_Error('invalid_id', __('Invalid attachment ID', 'ai-alt-text-manager'));
        }

        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('not_attachment', __('Not a valid attachment', 'ai-alt-text-manager'));
        }

        $mime_type = get_post_mime_type($attachment_id);
        $valid_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mime_type, $valid_mime_types, true)) {
            return new WP_Error('invalid_type', __('Not a valid image type', 'ai-alt-text-manager'));
        }

        return true;
    }
}
