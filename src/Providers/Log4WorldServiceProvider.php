<?php

namespace Log4World\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Order\Shipping\ServiceProvider\Services\ShippingServiceProviderService;

/**
 * Class Log4WorldServiceProvider
 * @package Log4World\Providers
 */
class Log4WorldServiceProvider extends ServiceProvider
{
    /**
    * Register the route service provider
    */
    public function register()
    {
        /* [mr] You can register a view controller if needed anytime in a future. */
//        $this->getApplication()->register(Log4WorldRouteServiceProvider::class);
    }

    public function boot(ShippingServiceProviderService $shippingServiceProviderService)
    {
        $shippingServiceProviderService->registerShippingProvider(
            'Log4World',
            ['en' => 'Log4World', 'de' => 'Log4World'],
            [
                'Log4World\\Controllers\\ShippingController@registerShipments',
                'Log4World\\Controllers\\ShippingController@deleteShipments',
                'Log4World\\Controllers\\ShippingController@getLabels',
            ]);
    }
}