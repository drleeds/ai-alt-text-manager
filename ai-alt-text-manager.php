<?php
/**
 * Plugin Name: AI Alt Text Manager
 * Plugin URI: https://github.com/markleeds/ai-alt-text-manager
 * Description: Use AI vision to analyze images and generate ADA-compliant alt text for your media library. Uses centralized Leeds Utilities API management. By Mark Leeds.
 * Version: 2.0.73
 * Author: Mark Leeds
 * Author URI: https://markleeds.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-alt-text-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_ALT_TEXT_MANAGER_VERSION', '2.0.73');
define('AI_ALT_TEXT_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_ALT_TEXT_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AI_ALT_TEXT_MANAGER_PLUGIN_DIR . 'includes/class-security.php';
require_once AI_ALT_TEXT_MANAGER_PLUGIN_DIR . 'includes/class-api-client.php';
require_once AI_ALT_TEXT_MANAGER_PLUGIN_DIR . 'includes/class-admin-pages.php';
require_once AI_ALT_TEXT_MANAGER_PLUGIN_DIR . 'includes/class-settings.php';

/**
 * Initialize the plugin
 */
function ai_alt_text_manager_init() {
    // Initialize admin pages
    $admin_pages = new AI_Alt_Text_Manager_Admin_Pages();
    $admin_pages->init();

    // Initialize settings
    $settings = new AI_Alt_Text_Manager_Settings();
    $settings->init();
}
add_action('plugins_loaded', 'ai_alt_text_manager_init');

/**
 * Activation hook
 */
function ai_alt_text_manager_activate() {
    // Set default options
    if (!get_option('ai_alt_text_manager_api_provider')) {
        add_option('ai_alt_text_manager_api_provider', 'anthropic');
    }
    if (!get_option('ai_alt_text_manager_items_per_page')) {
        add_option('ai_alt_text_manager_items_per_page', 20);
    }

    // Set data preservation default (checked by default)
    if (get_option('ai_alt_text_manager_preserve_data_on_uninstall') === false) {
        update_option('ai_alt_text_manager_preserve_data_on_uninstall', true);
    }

    // Update version
    update_option('ai_alt_text_manager_version', AI_ALT_TEXT_MANAGER_VERSION);
}
register_activation_hook(__FILE__, 'ai_alt_text_manager_activate');

/**
 * Deactivation hook
 */
function ai_alt_text_manager_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'ai_alt_text_manager_deactivate');
