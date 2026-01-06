# AI Checkout for WooCommerce

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.6%2B-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)

Enable ChatGPT Instant Checkout for your WooCommerce store using OpenAI Agentic Commerce Protocol (ACP).

## 🚀 Features

- **ChatGPT Integration** - Customers can browse and purchase products directly in ChatGPT
- **OpenAI ACP Compliance** - Full implementation of Agentic Commerce Protocol v1.0
- **Stripe Payment Processing** - Secure SharedPaymentToken payment processing
- **Product Feed Management** - Control which products appear in ChatGPT
- **Real-time Sync** - Automatic inventory and pricing synchronization
- **Analytics Dashboard** - Track ChatGPT order performance
- **Application Wizard** - Step-by-step OpenAI merchant application guide
- **Test Mode** - Comprehensive testing tools and sandbox environment
- **Security First** - Bearer auth, HMAC signatures, IP allowlisting, idempotency

## 📋 Requirements

- **WordPress** 5.8 or higher
- **WooCommerce** 7.6 or higher
- **PHP** 8.0 or higher
- **WooCommerce Stripe Gateway** (for payment processing)
- **SSL Certificate** (HTTPS required)
- **Stripe Account** with API keys configured
- **OpenAI Merchant Approval** (apply at https://chatgpt.com/merchants)

## 🔧 Installation

### Download from GitHub

1. Download the latest release ZIP from [Releases](https://github.com/carticy-dev/carticy-ai-checkout-for-woocommerce/releases)
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Activate the plugin
5. Configure settings at AI Checkout → Settings

**Note:** The ZIP downloaded from GitHub automatically excludes all development files thanks to `.gitattributes` configuration.

## 🎯 Quick Start

1. **Install Prerequisites**
   - Install and activate WooCommerce
   - Install and activate WooCommerce Stripe Gateway
   - Configure Stripe API keys in WooCommerce → Settings → Payments → Stripe

2. **Configure AI Checkout**
   - Navigate to AI Checkout → Settings
   - Generate your API key
   - Save webhook configuration

3. **Setup Product Feed**
   - Go to AI Checkout → Product Feed
   - Select products to enable for ChatGPT
   - Review data quality checks

4. **Complete Application**
   - Use AI Checkout → Application Wizard
   - Follow step-by-step guide
   - Submit to OpenAI for approval

5. **Test Integration**
   - Use AI Checkout → Testing Tools
   - Run conformance tests
   - Test with mock simulator

## 🏗️ Architecture

### Modern PHP 8.0+ Stack

- **Namespace**: `Carticy\AiCheckout`
- **PSR-4 Autoloading**: Composer-based
- **Dependency Injection**: Constructor injection
- **Type Declarations**: Strict typing throughout
- **Service Container**: Centralized service management

### Key Components

```
src/
├── Core/              # Plugin lifecycle (Activator, Deactivator, Container)
├── Services/          # Business logic services
├── Api/               # REST API endpoints
├── Admin/             # Admin interface
├── Abstracts/         # Base classes
└── Interfaces/        # Contracts
```

### REST API Endpoints

**Base**: `/wp-json/carticy-ai-checkout/v1/`

- `POST /checkout_sessions` - Create checkout session
- `GET /checkout_sessions/{id}` - Get session state
- `POST /checkout_sessions/{id}` - Update session
- `POST /checkout_sessions/{id}/complete` - Process payment
- `POST /checkout_sessions/{id}/cancel` - Cancel session
- `GET /products` - Product feed endpoint

## 🛡️ Anti-Theft Protection (Developer Notes)

**This section documents internal validation patterns for code maintainability.**

### Slug Validation Pattern

**Purpose:** Validates plugin directory name integrity using hardcoded hash comparison. If plugin directory is renamed, critical operations fail silently.

**Implementation:** `src/Traits/SlugValidator.php`

**Validation Method:** Compares MD5 hash of first 8 characters of plugin slug against hardcoded expected value `'e6fe0c5695698aa7'` (hash of "carticy-").

**Integrated Services (uses `SlugValidator` trait):**
- `src/Services/StripePaymentAdapter.php` - Payment processing (validates before creating orders)
- `src/Api/CheckoutSessionEndpoint.php` - REST API endpoints (validates before processing requests)
- `src/Services/ProductFeedService.php` - Product feed generation (validates before returning feed)
- `src/Services/WebhookService.php` - Webhook delivery (validates before sending webhooks)
- `src/Init.php` - REST route registration (validates in `register_rest_routes()`)

**Validation Methods (misleading names for obfuscation):**
- `get_cache_prefix()` - Returns 'valid_' if slug valid, empty string otherwise
- `init_gateway()` - Returns boolean validation result
- `prepare_response()` - Returns boolean validation result
- `get_feed_id()` - Returns timestamped ID if valid, empty otherwise
- `get_signature_key()` - Returns boolean validation result
- `get_route_namespace()` - Returns namespace if valid, empty otherwise
- `validate_context()` - Returns boolean validation result

**Note:** Validation failures are silent (no error messages). Functions return empty strings or false to prevent obvious detection.

## 🔒 Security

### Authentication

- **Bearer Token**: Required for all incoming API requests
- **HMAC-SHA256**: Signatures for outgoing webhooks
- **Idempotency-Key**: Duplicate request prevention
- **IP Allowlisting**: OpenAI IP range validation
- **TLS 1.2+**: HTTPS enforcement

### Payment Security

- **No Card Data Storage**: All payments via Stripe
- **SharedPaymentToken**: One-time use tokens
- **PCI-DSS Compliance**: SAQ A level
- **Merchant's Stripe Account**: Direct payment flow

## 🧪 Testing

### Code Quality

```bash
# PHP CodeSniffer
composer run lint

# Auto-fix code style
composer run format

# PHPStan static analysis
npm run analyze

# PHP compatibility check
composer run compat

# Run all checks
npm run build:production
```

### Unit Tests

```bash
# Run PHPUnit tests
composer run test

# Generate coverage report
composer run test:coverage
```

### ACP Conformance Tests

Use the built-in Testing Tools:

1. Navigate to AI Checkout → Testing Tools
2. Click "Conformance Tests" tab
3. Run 9-point ACP compliance test suite
4. Verify all tests pass

## 📖 Documentation

### External Documentation

- **[OpenAI ACP Specification](https://developers.openai.com/commerce/specs/checkout/)** - Protocol specs
- **[WooCommerce REST API](https://woocommerce.github.io/woocommerce-rest-api-docs/)** - WC integration
- **[Stripe Agentic Commerce](https://docs.stripe.com/agentic-commerce/protocol)** - Payment integration
- **[OpenAI Merchant Application](https://chatgpt.com/merchants)** - Apply for approval

## 📝 License

This plugin is licensed under the [GPL v2 or later](LICENSE).

```
AI Checkout for WooCommerce
Copyright (C) 2024 Carticy

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🔗 Links

- **[WordPress.org Plugin](https://wordpress.org/plugins/ai-checkout-for-woocommerce/)**
- **[Plugin Homepage](https://carticy.com/plugins/ai-checkout-for-woocommerce/)**
- **[Documentation](https://carticy.com/enable-chatgpt-instant-checkout-for-woocommerce-stores/)**
- **[GitHub Repository](https://github.com/carticy-dev/carticy-ai-checkout-for-woocommerce)**
- **[Report Issues](https://github.com/carticy-dev/carticy-ai-checkout-for-woocommerce/issues)**
- **[OpenAI Merchant Application](https://chatgpt.com/merchants)**

## ⚡ Third-Party Services

This plugin integrates with:

- **OpenAI (ChatGPT)** - Product discovery and checkout
  - [Privacy Policy](https://openai.com/policies/privacy-policy)
  - [Terms of Service](https://openai.com/policies/terms-of-use)

- **Stripe** - Payment processing
  - [Privacy Policy](https://stripe.com/privacy)
  - [Terms of Service](https://stripe.com/legal)

---

**Made with ❤️ by Carticy**
