<?php
/**
 * AI Alt Text Manager Uninstall
 *
 * Handles plugin uninstallation with configurable data preservation.
 * By default, preserves all user data to allow safe reinstallation.
 *
 * @package AI_Alt_Text_Manager
 * @since 2.0.0
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user capabilities
if (!current_user_can('activate_plugins')) {
    return;
}

// Check if user wants to preserve data (default: true)
$preserve_data = get_option('ai_alt_text_manager_preserve_data_on_uninstall', true);

if ($preserve_data) {
    // ==========================================
    // PRESERVE MODE (Default)
    // ==========================================
    // Only delete API keys and sensitive technical settings
    // Keep all user data, configurations, and content

    $options_to_delete = [
        // API Keys (always delete for security)
        'ai_alt_text_manager_api_provider',
        'ai_alt_text_manager_openai_key',
        'ai_alt_text_manager_anthropic_key',
        'ai_alt_text_manager_anthropic_model',
        'ai_alt_text_manager_openai_model',
    ];

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Do NOT drop database tables when preserving data
    // This ensures user data, history, and configurations are preserved

} else {
    // ==========================================
    // FULL DELETION MODE
    // ==========================================
    // User explicitly chose to remove all plugin data

    // Delete ALL plugin options
    $options_to_delete = [
        // Core settings
        'ai_alt_text_manager_api_provider',
        'ai_alt_text_manager_openai_key',
        'ai_alt_text_manager_anthropic_key',
        'ai_alt_text_manager_anthropic_model',
        'ai_alt_text_manager_openai_model',
        'ai_alt_text_manager_preserve_data_on_uninstall',

        // Plugin-specific settings
        'ai_alt_text_manager_items_per_page',

        // Version tracking
        'ai_alt_text_manager_version',
    ];

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // This plugin doesn't use custom database tables
    // But if it did in the future, they would be dropped here
}

// ==========================================
// CLEANUP (Always performed)
// ==========================================
// Clear transients and scheduled hooks regardless of preserve setting

global $wpdb;

// Clear transients
try {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_alt_text_manager_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_alt_text_manager_%'");
} catch (Exception $e) {
    // Ignore transient cleanup errors
    error_log('AI Alt Text Manager uninstall: Failed to clear transients - ' . $e->getMessage());
}

// Clear scheduled events (if any are added in the future)
// wp_clear_scheduled_hook('ai_alt_text_manager_daily_cleanup');
