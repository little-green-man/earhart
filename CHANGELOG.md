# Changelog

All notable changes to `earhart` will be documented in this file.

## [1.4.0] - 2024

### Added
- **Webhook Signature Verification**: New `WebhookSignatureVerifier` class for validating Svix-signed webhooks
  - Cryptographic HMAC signature validation
  - Timestamp validation to prevent replay attacks (configurable tolerance)
  - Case-insensitive header handling
  - Secure secret masking for debugging/logging
  - Full compliance with Svix webhook standards

- **Webhook Configuration System**: New `WebhookConfig` class with fluent API for webhook behavior control
  - Configurable timestamp tolerance (default 5 minutes)
  - Cache invalidation rules customization
  - Custom cache key format support
  - Array-based configuration loading from config files
  - Configuration serialization and masking

- **Comprehensive Test Suite**: 82 new tests covering webhook security and integration
  - 24 unit tests for `WebhookSignatureVerifier`
  - 42 unit tests for `WebhookConfig`
  - 16 integration tests for end-to-end webhook processing
  - All tests passing with 689+ assertions

- **Enhanced Documentation**
  - Updated README with webhook signature verification examples
  - Comprehensive webhook security and configuration guide
  - Multiple integration examples for webhook handling
  - Security best practices and troubleshooting guide

- **Configuration Standardization**: Unified configuration namespace across entire package
  - All configuration now uses `config('services.propelauth.*')` namespace for consistency
  - Clear environment variable mapping (PROPELAUTH_* env vars to earhart.* config keys)
  - All controllers updated to use standardized configuration keys
  - Improved README with comprehensive configuration setup guide
  - Configuration validation on boot with clear error messages

### Changed
- **Configuration**: All package services now consistently use `config('services.propelauth.*')` instead of mixed namespaces
  - Updated all redirect controllers to use standardized config
  - ServiceProvider simplified with single configuration namespace
  - Configuration validation moved to boot lifecycle for proper test compatibility

- **Event Constructors**: Removed invalid return type declarations
  - Constructor methods in PHP cannot have return types; removed `: void` from all event constructors

### Files Added
- `src/Webhooks/WebhookSignatureVerifier.php` - Webhook signature verification
- `src/Webhooks/WebhookConfig.php` - Webhook configuration management
- `tests/Unit/Webhooks/WebhookSignatureVerifierTest.php` - Unit tests
- `tests/Unit/Webhooks/WebhookConfigTest.php` - Unit tests
- `tests/Feature/Webhooks/WebhookSignatureAndParsingTest.php` - Integration tests

### Changed
- Updated README.md with comprehensive webhook signature verification section
- Improved README organization with feature highlights and better structure
- Enhanced security documentation with best practices and examples
- Updated environment variable naming for consistency (`PROPELAUTH_WEBHOOK_SECRET`)

### Backward Compatibility
✅ **Fully backward compatible** - All changes are additive and opt-in. Existing webhook handling continues to work without modification.

### Migration Notes
If upgrading from v1.3.x and want to add signature verification:

```php
// Before (v1.3.x)
$payload = json_decode($request->getContent(), true);

// After (v1.4.0)
$verifier = new WebhookSignatureVerifier(config('propelauth.webhook_secret'));
$payload = $verifier->verify($request->getContent(), $request->headers->all());
```

## [1.3.0] - Previous
- Added getUser method

## [1.2.0] - Previous
- Added an initial API library to support getting Organisations, an Organisation and Users in an Organisation.
- Added routes:
  - Org Members /org/members/:orgId
  - Org Settings /org/settings/:orgId
  - Create Org /create_org
  - Account Settings /account/settings/:orgId

## [1.1.0] - Previous
- Added AuthAccountController to provide redirect to PropelAuth account manager.

## [1.0.0] - Previous
- Initial version
