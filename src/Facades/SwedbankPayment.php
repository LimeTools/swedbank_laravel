<?php

namespace Swedbank\LaravelPaymentApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string createPaymentInitiation(array $paymentData, string $clientId, string $privateKey)
 * @method static array getPaymentStatus(string $statusUrl, string $clientId, string $privateKey)
 * @method static array getPaymentProviders(string $country, string $clientId, string $privateKey)
 * @method static array getPaymentInitiationForm(string $bic, array $paymentData, string $clientId, string $privateKey)
 *
 * @see \Swedbank\LaravelPaymentApi\SwedbankPaymentApi
 */
class SwedbankPayment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Swedbank\LaravelPaymentApi\SwedbankPaymentApi::class;
    }
}

