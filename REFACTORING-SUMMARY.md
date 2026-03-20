# AI Alt Text Manager - Refactoring Summary v2.0.0

## Overview
This document summarizes the security updates, refactoring, and new features added to the AI Alt Text Manager plugin.

**Version:** 2.0.0
**Date:** November 10, 2025
**Author:** Mark Leeds

---

## What's New in v2.0.0

### 1. Security Class (NEW)
**File:** `includes/class-security.php`

A comprehensive security class that centralizes all security-related functionality:

#### Key Features:
- **API Key Encryption/Decryption:** Uses WordPress salts and AES-256-CBC encryption
- **Nonce Verification:** Standardized AJAX nonce handling
- **Capability Checks:** User permission validation methods
- **Input Sanitization:** Sanitization methods for:
  - Attachment IDs
  - Alt text (with 125 character limit)
  - Image validation
- **Validation Methods:** For API providers and models
- **Security Logging:** Debug logging for security events

#### Usage Example:
```php
// Encrypt API key
$encrypted = AI_Alt_Text_Manager_Security::encrypt_api_key($api_key);

// Verify nonce
if (!AI_Alt_Text_Manager_Security::verify_ajax_nonce($nonce)) {
    wp_send_json_error('Invalid nonce');
}

// Check capabilities
if (!AI_Alt_Text_Manager_Security::current_user_can_manage()) {
    return;
}

// Validate image attachment
$result = AI_Alt_Text_Manager_Security::validate_image_attachment($attachment_id);
```

---

### 2. Uninstall Handler (NEW)
**File:** `uninstall.php`

Proper WordPress uninstall handler with configurable data preservation.

#### Features:
- **Preserve Mode (Default):**
  - Keeps user settings and configurations
  - Always removes API keys for security
  - Allows safe reinstallation without data loss

- **Full Deletion Mode:**
  - Removes ALL plugin data
  - Deletes all options
  - Cleans up transients

- **Always Performed:**
  - Clears transients
  - Removes scheduled hooks
  - Proper error handling

#### User Control:
Users can control data preservation via the new "Data Management" section in settings.

---

### 3. Data Management Section (NEW)
**Location:** Settings Page → Data Management

A new settings section that allows users to control what happens when the plugin is uninstalled.

#### Settings:
- **Preserve Data on Uninstall** (checkbox, checked by default)
  - **Checked:** Settings preserved, API keys removed
  - **Unchecked:** Complete data deletion

#### User-Friendly Documentation:
- Clear explanation of each option
- Recommendation to keep data preservation enabled
- Warning about permanent deletion

---

### 4. Enhanced AI Settings Layout
**Location:** Settings Page → AI API Settings

The AI provider configuration has been redesigned to match the standardized layout used across all updated plugins:

#### New Layout Features:
- **Radio Button Provider Selection:** Choose between Anthropic Claude and OpenAI GPT with radio buttons
- **Always-Visible Providers:** Both Anthropic and OpenAI sections are always displayed (no hiding/toggling)
- **Text Input for Models:** Model names are entered via text input fields (not dropdowns) for flexibility
- **Separate Model Fields:** Each provider has its own model field:
  - `ai_alt_text_manager_anthropic_model` (default: claude-3-5-sonnet-20241022)
  - `ai_alt_text_manager_openai_model` (default: gpt-4o)
- **Individual Test Buttons:** Each provider has its own "Test Connection" button
- **Help Documentation:** Links to official model documentation for each provider

#### Settings Fields:
1. **AI Provider** - Radio buttons to select active provider
2. **Anthropic API Key** - Password field with masked display
3. **Anthropic Model** - Text input with link to docs and test button
4. **OpenAI API Key** - Password field with masked display
5. **OpenAI Model** - Text input with link to docs and test button

#### Test Connection Features:
- Tests the specific provider (Anthropic or OpenAI)
- Uses the model specified in the corresponding text field
- Can test with unsaved keys or saved encrypted keys
- Provides detailed success/error messages
- Shows model and provider information on success

---

### 5. Security Updates

#### Encrypted API Keys:
- All API keys are encrypted using AES-256-CBC
- Encryption uses WordPress AUTH_KEY and AUTH_SALT
- Keys are masked with dots (••••) in the UI
- Keys are always deleted on uninstall (regardless of preserve setting)

#### Input Validation:
- All user inputs are sanitized using WordPress functions
- Attachment IDs validated as positive integers
- Alt text limited to 125 characters (ADA recommendation)
- Image attachments validated for correct MIME type

#### AJAX Security:
- All AJAX actions use nonces
- Capability checks on all admin actions
- Centralized security validation

---

## File Structure Changes

### New Files:
```
ai-alt-text-manager/
├── includes/
│   └── class-security.php          # NEW: Security utilities
└── uninstall.php                   # NEW: Proper uninstall handler
```

### Modified Files:
```
ai-alt-text-manager/
├── ai-alt-text-manager.php         # Updated: Version 2.0.0, includes security class
├── includes/
│   ├── class-settings.php          # Updated: Standardized layout, separate model fields
│   ├── class-api-client.php        # Updated: Uses separate model fields
│   └── class-admin-pages.php       # Updated: Added test API handler
```

---

## Upgrade Notes

### From v1.x to v2.0.0:

1. **Automatic Migration:**
   - Plugin will automatically set preservation default to TRUE
   - Version number updated in database
   - No manual action required

2. **API Keys:**
   - Existing keys will be re-encrypted on next save
   - No need to re-enter keys

3. **Settings:**
   - All existing settings preserved
   - New "Data Management" section added
   - Default behavior matches v1.x (data preserved)

---

## Security Best Practices Implemented

✅ **API Key Security:**
- Encrypted storage using WordPress salts
- No plaintext keys in database
- Masked display in UI
- Always removed on uninstall

✅ **AJAX Security:**
- Nonce verification on all requests
- Capability checks
- Sanitized inputs
- Error handling

✅ **Code Security:**
- No direct file access allowed
- Proper WordPress constants checked
- Capability-based access control
- SQL injection prevention (prepared statements)

✅ **Data Security:**
- User control over data retention
- Secure deletion process
- Transient cleanup
- No sensitive data in logs (except debug mode)

---

## Testing Checklist

### Security Testing:
- [x] API keys encrypted in database
- [x] Keys masked in UI
- [x] Nonce validation works
- [x] Capability checks enforced
- [x] Input sanitization working

### Uninstall Testing:
- [x] Preserve mode keeps settings
- [x] Preserve mode removes API keys
- [x] Full deletion removes all data
- [x] Transients cleared
- [x] No orphaned data

### Functional Testing:
- [x] Settings save correctly
- [x] AI test buttons work for both providers
- [x] Data management section displays
- [x] Plugin activation sets defaults
- [x] Version update recorded
- [x] Alt text generation works with new model fields

---

## API Reference

### Security Class Methods:

#### Encryption:
```php
AI_Alt_Text_Manager_Security::encrypt_api_key($key)
AI_Alt_Text_Manager_Security::decrypt_api_key($encrypted_key)
```

#### Verification:
```php
AI_Alt_Text_Manager_Security::verify_ajax_nonce($nonce, $action)
AI_Alt_Text_Manager_Security::current_user_can_manage()
AI_Alt_Text_Manager_Security::current_user_can_upload()
```

#### Sanitization:
```php
AI_Alt_Text_Manager_Security::sanitize_attachment_ids($ids)
AI_Alt_Text_Manager_Security::sanitize_alt_text($alt_text)
```

#### Validation:
```php
AI_Alt_Text_Manager_Security::validate_api_provider($provider)
AI_Alt_Text_Manager_Security::validate_ai_model($model, $provider)
AI_Alt_Text_Manager_Security::validate_image_attachment($attachment_id)
```

#### Utilities:
```php
AI_Alt_Text_Manager_Security::create_ajax_nonce($action)
AI_Alt_Text_Manager_Security::log_event($event, $context)
```

---

## Database Options

### New Options:
- `ai_alt_text_manager_preserve_data_on_uninstall` (boolean, default: true)
- `ai_alt_text_manager_version` (string, current version number)
- `ai_alt_text_manager_anthropic_model` (string, default: claude-3-5-sonnet-20241022)
- `ai_alt_text_manager_openai_model` (string, default: gpt-4o)

### Existing Options:
- `ai_alt_text_manager_api_provider` (string, values: 'anthropic' or 'openai')
- `ai_alt_text_manager_openai_key` (string, encrypted)
- `ai_alt_text_manager_anthropic_key` (string, encrypted)
- `ai_alt_text_manager_items_per_page` (integer)

---

## Changelog

### [2.0.0] - 2025-11-10

#### Added:
- Security class for centralized security handling
- Proper uninstall.php with data preservation options
- Data Management settings section
- Version tracking in database
- Comprehensive input sanitization
- Security event logging
- Separate model fields for each AI provider
- Individual test buttons for each provider

#### Enhanced:
- API key encryption improved with AES-256-CBC
- Settings page layout redesigned to match standardized pattern across all plugins
- Radio button provider selection (replaces simple text notice)
- Separate model fields for each provider (text inputs)
- Individual test buttons for each provider
- Both providers always visible (no conditional display)
- User documentation in UI with links to official docs

#### Security:
- All API keys now encrypted with AES-256-CBC
- Nonce verification on all AJAX calls
- Capability checks enforced
- Input validation strengthened
- Image validation added

#### Changed:
- Version bumped to 2.0.0
- Plugin activation hook updated
- Settings registration improved
- API client updated to use separate model fields

---

## Future Enhancements

### Planned for v2.1.0:
- [ ] Bulk re-generate alt text for updated images
- [ ] Custom alt text templates
- [ ] Export/import alt text data
- [ ] Activity logging dashboard
- [ ] Performance optimizations

### Planned for v2.2.0:
- [ ] Multi-site support
- [ ] REST API endpoints
- [ ] WebHook integrations
- [ ] Integration with SEO plugins

---

## Support

For issues, questions, or feature requests:
- GitHub: https://github.com/markleeds/ai-alt-text-manager
- Email: mark@markleeds.com

---

## License

GPL v2 or later

---

*This refactoring brings the AI Alt Text Manager plugin in line with WordPress security best practices and provides users with better control over their data.*
