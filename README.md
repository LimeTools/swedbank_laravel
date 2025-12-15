# Swedbank Laravel Payment API

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![GitHub](https://img.shields.io/github/v/release/LimeTools/swedbank_laravel?include_prereleases)](https://github.com/LimeTools/swedbank_laravel)

A Laravel package for integrating with Swedbank Payment Initiation API V3. This package provides a clean and easy-to-use interface for initiating payments, checking payment status, and retrieving payment providers using Swedbank's Payment Initiation API.

## Features

- ✅ **Swedbank Payment Initiation API V3** support
- ✅ **JWS (JSON Web Signature)** authentication
- ✅ **Sandbox and Production** environments
- ✅ **Payment Provider** management
- ✅ **Payment Status** checking
- ✅ **Comprehensive logging** support
- ✅ **Laravel 9, 10, and 11** compatible

## Requirements

- PHP >= 8.1
- Laravel >= 9.0
- OpenSSL extension (for JWS signing)

## Installation

### Via Composer

You can install the package via Composer:

```bash
composer require swedbank/laravel-payment-api
```


### Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=swedbank-config
```

This will create a `config/swedbank.php` file in your Laravel application.

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# Environment (sandbox or production)
SWEDBANK_ENVIRONMENT=production

# Sandbox credentials
SWEDBANK_SANDBOX_ENABLED=false
SWEDBANK_SANDBOX_CLIENT_ID=your_sandbox_client_id
SWEDBANK_SANDBOX_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"

# Production credentials
SWEDBANK_PRODUCTION_ENABLED=true
SWEDBANK_PRODUCTION_CLIENT_ID=your_production_client_id
SWEDBANK_PRODUCTION_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"

# Optional: Logging configuration
SWEDBANK_LOGGING_ENABLED=true
SWEDBANK_LOG_LEVEL=info
SWEDBANK_LOG_CHANNEL=swedbank
```

**Important:** When storing the private key in `.env`, make sure to:
- Wrap it in double quotes
- Use `\n` for newlines
- Keep the entire key on a single line

Example:
```env
SWEDBANK_PRODUCTION_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC...\n-----END PRIVATE KEY-----"
```

## Usage

### Basic Usage

The package provides a `SwedbankPaymentApi` class that can be used in several ways:

#### Using Dependency Injection

```php
use Swedbank\LaravelPaymentApi\SwedbankPaymentApi;

class PaymentController extends Controller
{
    public function __construct(
        protected SwedbankPaymentApi $swedbankApi
    ) {}
}
```

#### Using the Facade

```php
use Swedbank\LaravelPaymentApi\Facades\SwedbankPayment;

class PaymentController extends Controller
{
    public function initiatePayment()
    {
        $providers = SwedbankPayment::getPaymentProviders(
            'LT',
            config('swedbank.production.client_id'),
            config('swedbank.production.private_key')
        );
    }
}
```

#### Using the Service Container

```php
$swedbankApi = app(Swedbank\LaravelPaymentApi\SwedbankPaymentApi::class);
```

## Step-by-step integration guide

This is a minimal, end-to-end example to get you from a fresh Laravel install to a working Swedbank payment redirect.

### 1. Install the package

#### a) From GitHub (recommended now)

In your application `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/LimeTools/swedbank_laravel"
        }
    ],
    "require": {
        "limetools/swedbank_laravel": "dev-main"
    }
}
```

Then install:

```bash
composer require limetools/swedbank_laravel:dev-main
```

### 2. Publish and configure

Publish the config file:

```bash
php artisan vendor:publish --tag=swedbank-config
```

This creates `config/swedbank.php`.

Add environment variables to `.env` (start with sandbox):

```env
SWEDBANK_ENVIRONMENT=sandbox

SWEDBANK_SANDBOX_ENABLED=true
SWEDBANK_SANDBOX_CLIENT_ID=your_sandbox_client_id
SWEDBANK_SANDBOX_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"

SWEDBANK_PRODUCTION_ENABLED=false

SWEDBANK_LOGGING_ENABLED=true
SWEDBANK_LOG_LEVEL=info
SWEDBANK_LOG_CHANNEL=swedbank
```

Optionally, configure a log channel in `config/logging.php`:

```php
'channels' => [
    // ...
    'swedbank' => [
        'driver' => 'daily',
        'path' => storage_path('logs/swedbank.log'),
        'level' => env('SWEDBANK_LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

### 3. Create routes

In `routes/web.php`:

```php
use App\Http\Controllers\SwedbankPaymentController;

Route::get('/pay/{order}', [SwedbankPaymentController::class, 'start'])->name('swedbank.pay');
Route::get('/pay/{order}/callback', [SwedbankPaymentController::class, 'callback'])->name('swedbank.callback');
```

### 4. Create a simple controller

Create `app/Http/Controllers/SwedbankPaymentController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Swedbank\LaravelPaymentApi\SwedbankPaymentApi;

class SwedbankPaymentController extends Controller
{
    public function __construct(
        protected SwedbankPaymentApi $swedbankApi
    ) {}

    public function start(Request $request, int $orderId)
    {
        // Replace this with your real order fetch
        $amount = '100.00'; // EUR

        $clientId = config('swedbank.production.client_id') ?? config('swedbank.sandbox.client_id');
        $privateKey = config('swedbank.production.private_key') ?? config('swedbank.sandbox.private_key');

        $paymentData = [
            'amount' => [
                'currency' => 'EUR',
                'value' => $amount,
            ],
            'creditor' => [
                'name' => config('app.name'),
                'iban' => config('swedbank.merchant_iban', 'LT123456789012345678'),
                'bic' => config('swedbank.merchant_bic', 'HABALT22'),
            ],
            'debtor' => [
                'name' => $request->user()->name ?? 'Customer',
            ],
            'remittanceInformationUnstructured' => 'Order #' . $orderId,
            'redirectUri' => route('swedbank.callback', ['order' => $orderId]),
            'state' => (string) $orderId,
        ];

        $redirectUrl = $this->swedbankApi->createPaymentInitiation(
            $paymentData,
            $clientId,
            $privateKey
        );

        return redirect($redirectUrl);
    }

    public function callback(Request $request, int $orderId)
    {
        // In a real app you would typically store and use a status URL
        $statusUrl = $request->query('statusUrl') ?? $request->query('paymentId');

        if (! $statusUrl) {
            return redirect()->route('home')->with('error', 'Missing payment status URL');
        }

        $clientId = config('swedbank.production.client_id') ?? config('swedbank.sandbox.client_id');
        $privateKey = config('swedbank.production.private_key') ?? config('swedbank.sandbox.private_key');

        $status = $this->swedbankApi->getPaymentStatus(
            $statusUrl,
            $clientId,
            $privateKey
        );

        if (($status['status'] ?? null) === 'EXECUTED') {
            // Mark your order as paid here
            return redirect()->route('home')->with('success', 'Payment successful');
        }

        return redirect()->route('home')->with('error', 'Payment not completed');
    }
}
```

This controller is intentionally simple; adapt it to your own `Order` model and domain logic.

### Get Payment Providers

Retrieve a list of available payment providers:

```php
$providers = $this->swedbankApi->getPaymentProviders(
    country: 'LT',
    clientId: config('swedbank.production.client_id'),
    privateKey: config('swedbank.production.private_key')
);

// Returns an array of providers with:
// - id
// - name
// - country
// - bic
// - logo
// - url
```

### Create Payment Initiation

Initiate a payment transaction:

```php
$paymentData = [
    'amount' => [
        'currency' => 'EUR',
        'value' => '100.00'
    ],
    'creditor' => [
        'name' => 'Your Company Name',
        'iban' => 'LT123456789012345678',
        'bic' => 'HABALT22'
    ],
    'debtor' => [
        'name' => 'Customer Name'
    ],
    'remittanceInformationUnstructured' => 'Order #12345',
    'redirectUri' => 'https://your-domain.com/payment/callback',
    'state' => 'order_12345' // Optional: for tracking
];

$redirectUrl = $this->swedbankApi->createPaymentInitiation(
    paymentData: $paymentData,
    clientId: config('swedbank.production.client_id'),
    privateKey: config('swedbank.production.private_key')
);

// Redirect user to $redirectUrl to complete payment
return redirect($redirectUrl);
```

### Get Payment Status

Check the status of a payment:

```php
// The status URL is typically returned in the payment initiation response
$statusUrl = 'https://pi.swedbank.com/public/api/v3/transactions/...';

$status = $this->swedbankApi->getPaymentStatus(
    statusUrl: $statusUrl,
    clientId: config('swedbank.production.client_id'),
    privateKey: config('swedbank.production.private_key')
);

// Check payment status
if ($status['status'] === 'EXECUTED') {
    // Payment successful
}
```

### Get Payment Initiation Form

Get payment initiation form data for a specific provider:

```php
$paymentData = [
    'amount' => '100.00',
    'currency' => 'EUR',
    'description' => 'Order #12345',
    'redirectUrl' => 'https://your-domain.com/payment/callback',
    'notificationUrl' => 'https://your-domain.com/payment/webhook',
    'locale' => 'lt',
];

$formData = $this->swedbankApi->getPaymentInitiationForm(
    bic: 'HABALT22',
    paymentData: $paymentData,
    clientId: config('swedbank.production.client_id'),
    privateKey: config('swedbank.production.private_key')
);

// Use $formData['urls']['redirect'] to redirect user
$redirectUrl = $formData['urls']['redirect'];
```

## Complete Example

Here's a complete example of a payment flow:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Swedbank\LaravelPaymentApi\SwedbankPaymentApi;

class PaymentController extends Controller
{
    public function __construct(
        protected SwedbankPaymentApi $swedbankApi
    ) {}

    public function initiatePayment(Request $request)
    {
        $order = $request->user()->orders()->findOrFail($request->order_id);
        
        $clientId = config('swedbank.production.client_id');
        $privateKey = config('swedbank.production.private_key');

        // Get providers
        $providers = $this->swedbankApi->getPaymentProviders('LT', $clientId, $privateKey);

        // Create payment initiation
        $paymentData = [
            'amount' => [
                'currency' => 'EUR',
                'value' => number_format($order->total, 2, '.', '')
            ],
            'creditor' => [
                'name' => config('app.name'),
                'iban' => config('swedbank.merchant_iban'),
                'bic' => config('swedbank.merchant_bic')
            ],
            'debtor' => [
                'name' => $order->customer_name
            ],
            'remittanceInformationUnstructured' => 'Order #' . $order->id,
            'redirectUri' => route('payment.callback', ['order' => $order->id]),
            'state' => (string) $order->id
        ];

        $redirectUrl = $this->swedbankApi->createPaymentInitiation(
            $paymentData,
            $clientId,
            $privateKey
        );

        // Store status URL for later use
        $order->update([
            'payment_status_url' => $redirectUrl
        ]);

        return redirect($redirectUrl);
    }

    public function handleCallback(Request $request)
    {
        $order = $request->user()->orders()->findOrFail($request->order_id);
        
        $clientId = config('swedbank.production.client_id');
        $privateKey = config('swedbank.production.private_key');

        // Get status URL from order or request
        $statusUrl = $order->payment_status_url ?? $request->input('status_url');

        $status = $this->swedbankApi->getPaymentStatus(
            $statusUrl,
            $clientId,
            $privateKey
        );

        if ($status['status'] === 'EXECUTED') {
            $order->update(['status' => 'paid']);
            return redirect()->route('payment.success');
        }

        return redirect()->route('payment.failed');
    }
}
```

## API Reference

### SwedbankPaymentApi Methods

#### `getPaymentProviders(string $country, string $clientId, string $privateKey): array`

Retrieves a list of available payment providers for the specified country.

**Parameters:**
- `$country` - Country code (e.g., 'LT', 'LV', 'EE')
- `$clientId` - Your Swedbank client ID
- `$privateKey` - Your private key for signing

**Returns:** Array of provider information

#### `createPaymentInitiation(array $paymentData, string $clientId, string $privateKey): string`

Creates a payment initiation and returns the redirect URL.

**Parameters:**
- `$paymentData` - Payment data array (see Swedbank V3 API documentation)
- `$clientId` - Your Swedbank client ID
- `$privateKey` - Your private key for signing

**Returns:** Redirect URL string

#### `getPaymentStatus(string $statusUrl, string $clientId, string $privateKey): array`

Retrieves the current status of a payment.

**Parameters:**
- `$statusUrl` - Status URL from payment initiation response
- `$clientId` - Your Swedbank client ID
- `$privateKey` - Your private key for signing

**Returns:** Payment status array

#### `getPaymentInitiationForm(string $bic, array $paymentData, string $clientId, string $privateKey): array`

Gets payment initiation form data for a specific provider.

**Parameters:**
- `$bic` - Bank Identifier Code
- `$paymentData` - Payment data array
- `$clientId` - Your Swedbank client ID
- `$privateKey` - Your private key for signing

**Returns:** Form data array

## Logging

The package supports comprehensive logging. Configure logging in your `config/swedbank.php`:

```php
'logging' => [
    'enabled' => env('SWEDBANK_LOGGING_ENABLED', true),
    'level' => env('SWEDBANK_LOG_LEVEL', 'info'),
    'channel' => env('SWEDBANK_LOG_CHANNEL', 'swedbank'),
],
```

Make sure to add the `swedbank` channel to your `config/logging.php`:

```php
'channels' => [
    // ...
    'swedbank' => [
        'driver' => 'daily',
        'path' => storage_path('logs/swedbank.log'),
        'level' => env('SWEDBANK_LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

## Testing

To test the package, you can use Swedbank's sandbox environment:

1. Set `SWEDBANK_ENVIRONMENT=sandbox` in your `.env`
2. Configure sandbox credentials
3. Use the sandbox endpoints for testing

## Error Handling

The package throws exceptions when API calls fail. Always wrap API calls in try-catch blocks:

```php
try {
    $redirectUrl = $this->swedbankApi->createPaymentInitiation($paymentData, $clientId, $privateKey);
} catch (\Exception $e) {
    // Handle error
    logger()->error('Payment initiation failed', ['error' => $e->getMessage()]);
    return back()->withErrors(['payment' => 'Failed to initiate payment']);
}
```

## Security

- Always store private keys securely (use environment variables)
- Never commit private keys to version control
- Use HTTPS for all redirect URLs
- Validate and sanitize all user input
- Follow Swedbank's security best practices

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/LimeTools/swedbank_laravel).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

This package is based on the official [Swedbank Payment Initiation API V3](https://pi.swedbank.com/developer?version=public_V3) documentation.

## Changelog

### 1.0.0
- Initial release
- Swedbank Payment Initiation API V3 support
- JWS authentication
- Payment provider management
- Payment status checking
