# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-XX

### Added
- Initial release of Swedbank Laravel Payment API package
- Support for Swedbank Payment Initiation API V3
- JWS (JSON Web Signature) authentication
- Payment provider management
- Payment initiation and status checking
- Sandbox and production environment support
- Comprehensive logging support
- Laravel service provider and facade
- Configuration file publishing
- Full documentation and examples

### Features
- `getPaymentProviders()` - Retrieve available payment providers
- `createPaymentInitiation()` - Initiate payment transactions
- `getPaymentStatus()` - Check payment status
- `getPaymentInitiationForm()` - Get payment form data for specific providers

### Technical Details
- PHP 8.1+ required
- Laravel 9, 10, and 11 compatible
- Uses detached JWS format for authentication
- Follows Swedbank Payment Initiation API V3 specification

