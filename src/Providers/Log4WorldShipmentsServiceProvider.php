<?php
namespace Log4WorldShipments\Providers;

use Plenty\Modules\Order\Shipping\ServiceProvider\Services\ShippingServiceProviderService;
use Plenty\Plugin\ServiceProvider;

/**
 * Class Log4WorldShipmentsServiceProvider
 * @package Log4WorldShipments\Providers
 */
class Log4WorldShipmentsServiceProvider extends ServiceProvider
{

	/**
	 * Register the service provider.
	 */
	public function register()
	{
	    // add REST routes by registering a RouteServiceProvider if necessary
//	     $this->getApplication()->register(Log4WorldShipmentsRouteServiceProvider::class);
    }

    public function boot(ShippingServiceProviderService $shippingServiceProviderService)
    {

        $shippingServiceProviderService->registerShippingProvider(
            'Log4WorldShipments',
            ['de' => '*** Plenty shipping tutorial ***', 'en' => '*** Plenty shipping tutorial ***'],
            [
                'Log4WorldShipments\\Controllers\\ShippingController@registerShipments',
                'Log4WorldShipments\\Controllers\\ShippingController@deleteShipments',
            ]);
    }
}
