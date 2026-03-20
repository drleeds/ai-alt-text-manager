<?php
/**
 * Settings Page Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Alt_Text_Manager_Settings {

    /**
     * Initialize
     */
    public function init() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        // Settings page is added as submenu by Admin_Pages class
        // This hook just renders the content
        add_action('admin_menu', function() {
            remove_submenu_page('ai-alt-text-manager', 'ai-alt-text-manager-settings');
            add_submenu_page(
                'ai-alt-text-manager',
                __('Settings', 'ai-alt-text-manager'),
                __('Settings', 'ai-alt-text-manager'),
                'manage_options',
                'ai-alt-text-manager-settings',
                [$this, 'render_settings_page']
            );
        }, 20);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings Section
        add_settings_section(
            'ai_alt_text_manager_api_settings',
            __('AI API Settings', 'ai-alt-text-manager'),
            [$this, 'render_api_settings_section'],
            'ai-alt-text-manager-settings'
        );

        // Data Management Settings Section
        add_settings_section(
            'ai_alt_text_manager_data_management',
            __('Data Management', 'ai-alt-text-manager'),
            [$this, 'render_data_management_section'],
            'ai-alt-text-manager-settings'
        );

        // Display Settings Section
        add_settings_section(
            'ai_alt_text_manager_display_settings',
            __('Display Settings', 'ai-alt-text-manager'),
            [$this, 'render_display_settings_section'],
            'ai-alt-text-manager-settings'
        );

        // Register settings with sanitization
        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_api_provider', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'anthropic'
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_anthropic_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_openai_key', [
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_anthropic_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'claude-3-5-sonnet-20241022'
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_openai_model', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-4o'
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_preserve_data_on_uninstall', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);

        register_setting('ai_alt_text_manager_settings', 'ai_alt_text_manager_items_per_page', [
            'sanitize_callback' => 'absint',
            'default' => 20
        ]);

        // Add settings fields
        add_settings_field(
            'ai_alt_text_manager_api_provider',
            __('AI Provider', 'ai-alt-text-manager'),
            [$this, 'render_provider_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_api_settings'
        );

        add_settings_field(
            'ai_alt_text_manager_anthropic_key',
            __('Anthropic API Key', 'ai-alt-text-manager'),
            [$this, 'render_anthropic_key_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_api_settings'
        );

        add_settings_field(
            'ai_alt_text_manager_anthropic_model',
            __('Anthropic Model', 'ai-alt-text-manager'),
            [$this, 'render_anthropic_model_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_api_settings'
        );

        add_settings_field(
            'ai_alt_text_manager_openai_key',
            __('OpenAI API Key', 'ai-alt-text-manager'),
            [$this, 'render_openai_key_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_api_settings'
        );

        add_settings_field(
            'ai_alt_text_manager_openai_model',
            __('OpenAI Model', 'ai-alt-text-manager'),
            [$this, 'render_openai_model_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_api_settings'
        );

        add_settings_field(
            'ai_alt_text_manager_preserve_data_on_uninstall',
            __('Preserve Data on Uninstall', 'ai-alt-text-manager'),
            [$this, 'render_preserve_data_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_data_management'
        );

        add_settings_field(
            'ai_alt_text_manager_items_per_page',
            __('Images Per Page', 'ai-alt-text-manager'),
            [$this, 'render_items_per_page_field'],
            'ai-alt-text-manager-settings',
            'ai_alt_text_manager_display_settings'
        );

        // Add filter for encrypting keys on save
        add_filter('pre_update_option_ai_alt_text_manager_openai_key', [$this, 'encrypt_key_on_save'], 10, 1);
        add_filter('pre_update_option_ai_alt_text_manager_anthropic_key', [$this, 'encrypt_key_on_save'], 10, 1);
    }

    /**
     * Render API settings section
     */
    public function render_api_settings_section() {
        // Check for centralized Leeds Utilities API management
        if (class_exists('Leeds_Utilities_API_Settings')) {
            $settings = Leeds_Utilities_API_Settings::get_settings();
            $has_ai = !empty($settings['anthropic_key']) || !empty($settings['openai_key']);

            echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 20px;">';
            echo '<p style="margin: 0;"><strong>' . __('API Keys Managed by Leeds Utilities', 'ai-alt-text-manager') . '</strong></p>';
            echo '<p style="margin: 8px 0 0 0;">';

            if ($has_ai) {
                $provider = ucfirst($settings['api_provider']);
                echo '<span style="color: green;">✓ ' . sprintf(__('API configured via Leeds Utilities (%s)', 'ai-alt-text-manager'), $provider) . '</span><br>';
                echo __('Vision API keys are centrally managed. Configure them in', 'ai-alt-text-manager') . ' ';
                echo '<a href="' . admin_url('admin.php?page=leeds-utilities-api-settings') . '">' . __('Leeds Utilities Settings', 'ai-alt-text-manager') . '</a>.';
            } else {
                echo '<span style="color: red;">✗ ' . __('No API key configured', 'ai-alt-text-manager') . '</span><br>';
                echo '<a href="' . admin_url('admin.php?page=leeds-utilities-api-settings') . '">' . __('Configure API Key Now', 'ai-alt-text-manager') . '</a>';
            }

            echo '</p>';
            echo '</div>';
        } else {
            echo '<p>' . __('Configure your AI API for image analysis and alt text generation.', 'ai-alt-text-manager') . '</p>';
            echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px;">';
            echo '<p style="margin: 0;"><strong>' . __('Leeds Utilities Not Detected', 'ai-alt-text-manager') . '</strong></p>';
            echo '<p style="margin: 8px 0 0 0;">' . __('For centralized API key management, install Leeds Utilities plugin.', 'ai-alt-text-manager') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render data management section
     */
    public function render_data_management_section() {
        echo '<p>' . __('Control what happens to your data when the plugin is uninstalled.', 'ai-alt-text-manager') . '</p>';
    }

    /**
     * Render display settings section
     */
    public function render_display_settings_section() {
        echo '<p>' . __('Customize how the plugin displays information.', 'ai-alt-text-manager') . '</p>';
    }

    /**
     * Render provider field
     */
    public function render_provider_field() {
        // If Leeds Utilities is managing API, show readonly status
        if (class_exists('Leeds_Utilities_API_Settings')) {
            $settings = Leeds_Utilities_API_Settings::get_settings();
            $provider = ucfirst($settings['api_provider']);
            ?>
            <p style="background: #e7f3ff; padding: 10px; border-radius: 4px;">
                <strong><?php echo sprintf(__('Current Provider: %s', 'ai-alt-text-manager'), $provider); ?></strong><br>
                <span style="color: #666;"><?php _e('Provider is managed centrally via Leeds Utilities.', 'ai-alt-text-manager'); ?></span><br>
                <a href="<?php echo admin_url('admin.php?page=leeds-utilities-api-settings'); ?>"><?php _e('Change Provider', 'ai-alt-text-manager'); ?></a>
            </p>
            <?php
        } else {
            // Fallback to local provider selection
            $provider = get_option('ai_alt_text_manager_api_provider', 'anthropic');
            ?>
            <label style="display: block; margin-bottom: 8px;">
                <input type="radio" name="ai_alt_text_manager_api_provider" value="anthropic" <?php checked($provider, 'anthropic'); ?> />
                <?php _e('Anthropic Claude', 'ai-alt-text-manager'); ?>
            </label>
            <label style="display: block; margin-bottom: 8px;">
                <input type="radio" name="ai_alt_text_manager_api_provider" value="openai" <?php checked($provider, 'openai'); ?> />
                <?php _e('OpenAI GPT', 'ai-alt-text-manager'); ?>
            </label>
            <?php
        }
    }

    /**
     * Render Anthropic key field
     */
    public function render_anthropic_key_field() {
        // Hide if Leeds Utilities is managing keys
        if (class_exists('Leeds_Utilities_API_Settings')) {
            ?>
            <p style="color: #666;">
                <?php _e('Anthropic API key is managed centrally via Leeds Utilities.', 'ai-alt-text-manager'); ?><br>
                <a href="<?php echo admin_url('admin.php?page=leeds-utilities-api-settings'); ?>"><?php _e('Manage API Keys', 'ai-alt-text-manager'); ?></a>
            </p>
            <?php
            return;
        }

        // Fallback to local key management
        $key = get_option('ai_alt_text_manager_anthropic_key');
        ?>
        <input type="password"
               id="ai_alt_text_manager_anthropic_key"
               name="ai_alt_text_manager_anthropic_key"
               value=""
               placeholder="<?php echo !empty($key) ? '••••••••••••••••' : 'Enter your Anthropic API key'; ?>"
               class="regular-text"
               style="width: 400px;"
               autocomplete="off" />
        <p class="description">
            <?php _e('Get your API key from', 'ai-alt-text-manager'); ?>
            <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>
            <?php if (!empty($key)): ?>
                <br><span style="color: green;">✓ API key is configured</span>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Render Anthropic model field
     */
    public function render_anthropic_model_field() {
        $model = get_option('ai_alt_text_manager_anthropic_model', 'claude-3-5-sonnet-20241022');
        ?>
        <input type="text"
               id="ai_alt_text_manager_anthropic_model"
               name="ai_alt_text_manager_anthropic_model"
               value="<?php echo esc_attr($model); ?>"
               placeholder="claude-3-5-sonnet-20241022"
               class="regular-text"
               style="width: 400px;" />
        <p class="description">
            <?php _e('View available vision models at', 'ai-alt-text-manager'); ?>
            <a href="https://docs.anthropic.com/en/docs/about-claude/models" target="_blank">docs.anthropic.com/en/docs/about-claude/models</a>
        </p>
        <button type="button" class="button" id="ai-alt-text-test-anthropic"><?php _e('Test Connection', 'ai-alt-text-manager'); ?></button>
        <div id="ai-alt-text-anthropic-test-status" style="margin-top: 10px;"></div>
        <?php
    }

    /**
     * Render OpenAI key field
     */
    public function render_openai_key_field() {
        // Hide if Leeds Utilities is managing keys
        if (class_exists('Leeds_Utilities_API_Settings')) {
            ?>
            <p style="color: #666;">
                <?php _e('OpenAI API key is managed centrally via Leeds Utilities.', 'ai-alt-text-manager'); ?><br>
                <a href="<?php echo admin_url('admin.php?page=leeds-utilities-api-settings'); ?>"><?php _e('Manage API Keys', 'ai-alt-text-manager'); ?></a>
            </p>
            <?php
            return;
        }

        // Fallback to local key management
        $key = get_option('ai_alt_text_manager_openai_key');
        ?>
        <input type="password"
               id="ai_alt_text_manager_openai_key"
               name="ai_alt_text_manager_openai_key"
               value=""
               placeholder="<?php echo !empty($key) ? '••••••••••••••••' : 'Enter your OpenAI API key'; ?>"
               class="regular-text"
               style="width: 400px;"
               autocomplete="off" />
        <p class="description">
            <?php _e('Get your API key from', 'ai-alt-text-manager'); ?>
            <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>
            <?php if (!empty($key)): ?>
                <br><span style="color: green;">✓ API key is configured</span>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Render OpenAI model field
     */
    public function render_openai_model_field() {
        $model = get_option('ai_alt_text_manager_openai_model', 'gpt-4o');
        ?>
        <input type="text"
               id="ai_alt_text_manager_openai_model"
               name="ai_alt_text_manager_openai_model"
               value="<?php echo esc_attr($model); ?>"
               placeholder="gpt-4o"
               class="regular-text"
               style="width: 400px;" />
        <p class="description">
            <?php _e('View available vision models at', 'ai-alt-text-manager'); ?>
            <a href="https://platform.openai.com/docs/models" target="_blank">platform.openai.com/docs/models</a>
        </p>
        <button type="button" class="button" id="ai-alt-text-test-openai"><?php _e('Test Connection', 'ai-alt-text-manager'); ?></button>
        <div id="ai-alt-text-openai-test-status" style="margin-top: 10px;"></div>
        <?php
    }

    /**
     * Render preserve data field
     */
    public function render_preserve_data_field() {
        $preserve = get_option('ai_alt_text_manager_preserve_data_on_uninstall', true);
        ?>
        <label>
            <input type="checkbox" name="ai_alt_text_manager_preserve_data_on_uninstall" value="1" <?php checked($preserve, true); ?>>
            <?php _e('Keep my settings and data when I uninstall this plugin', 'ai-alt-text-manager'); ?>
        </label>
        <p class="description">
            <strong><?php _e('Recommended: Keep this checked', 'ai-alt-text-manager'); ?></strong><br>
            <?php _e('<strong>Checked (Default):</strong> Your settings and configurations are preserved when uninstalling. API keys are always securely removed. You can safely reinstall the plugin later without losing your settings.', 'ai-alt-text-manager'); ?><br><br>
            <?php _e('<strong>Unchecked:</strong> ALL plugin data will be permanently deleted when uninstalling, including all settings and configurations. Only uncheck this if you\'re completely done with the plugin and want a clean removal.', 'ai-alt-text-manager'); ?>
        </p>
        <?php
    }

    /**
     * Render items per page field
     */
    public function render_items_per_page_field() {
        $items_per_page = get_option('ai_alt_text_manager_items_per_page', 20);
        ?>
        <input type="number"
               name="ai_alt_text_manager_items_per_page"
               id="ai_alt_text_manager_items_per_page"
               value="<?php echo esc_attr($items_per_page); ?>"
               min="10"
               max="100"
               step="10">
        <p class="description">
            <?php _e('Number of images to display per page (10-100)', 'ai-alt-text-manager'); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'ai_alt_text_manager_messages',
                'ai_alt_text_manager_message',
                __('Settings saved successfully.', 'ai-alt-text-manager'),
                'updated'
            );
        }

        // Show error/update messages
        settings_errors('ai_alt_text_manager_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields('ai_alt_text_manager_settings');
                do_settings_sections('ai-alt-text-manager-settings');
                submit_button(__('Save Settings', 'ai-alt-text-manager'));
                ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Test Anthropic Connection
            $('#ai-alt-text-test-anthropic').on('click', function() {
                var button = $(this);
                var status = $('#ai-alt-text-anthropic-test-status');
                var apiKey = $('#ai_alt_text_manager_anthropic_key').val();
                var model = $('#ai_alt_text_manager_anthropic_model').val();

                // Check if using saved key (empty or dots placeholder)
                var useSavedKey = (apiKey === '' || apiKey === '••••••••••••••••');

                button.prop('disabled', true).text('<?php _e('Testing...', 'ai-alt-text-manager'); ?>');
                status.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_alt_text_manager_test_api',
                        nonce: '<?php echo wp_create_nonce('ai_alt_text_manager_ajax_nonce'); ?>',
                        provider: 'anthropic',
                        api_key: useSavedKey ? '' : apiKey,
                        model: model,
                        use_saved_key: useSavedKey
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            status.html('<span style="color: red;">✗ ' + (response.data.message || response.data) + '</span>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">✗ Connection failed</span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'ai-alt-text-manager'); ?>');
                    }
                });
            });

            // Test OpenAI Connection
            $('#ai-alt-text-test-openai').on('click', function() {
                var button = $(this);
                var status = $('#ai-alt-text-openai-test-status');
                var apiKey = $('#ai_alt_text_manager_openai_key').val();
                var model = $('#ai_alt_text_manager_openai_model').val();

                // Check if using saved key (empty or dots placeholder)
                var useSavedKey = (apiKey === '' || apiKey === '••••••••••••••••');

                button.prop('disabled', true).text('<?php _e('Testing...', 'ai-alt-text-manager'); ?>');
                status.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_alt_text_manager_test_api',
                        nonce: '<?php echo wp_create_nonce('ai_alt_text_manager_ajax_nonce'); ?>',
                        provider: 'openai',
                        api_key: useSavedKey ? '' : apiKey,
                        model: model,
                        use_saved_key: useSavedKey
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            status.html('<span style="color: red;">✗ ' + (response.data.message || response.data) + '</span>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">✗ Connection failed</span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'ai-alt-text-manager'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Encrypt API key before saving
     */
    public function encrypt_key_on_save($value) {
        if (empty($value)) {
            return '';
        }

        // If the value is just masked dots, keep the existing key
        if ($value === '••••••••••••••••') {
            // Get the current option name from the filter
            $option_name = current_filter();
            $option_name = str_replace('pre_update_option_', '', $option_name);
            return get_option($option_name);
        }

        // If already encrypted (contains ::), don't re-encrypt
        $decoded = base64_decode($value, true);
        if ($decoded !== false && strpos($decoded, '::') !== false) {
            return $value;
        }

        // Encrypt using Security class
        return AI_Alt_Text_Manager_Security::encrypt_api_key($value);
    }
}
