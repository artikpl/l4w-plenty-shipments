<?php
namespace Log4WorldShipments\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class Log4WorldShipmentsRouteServiceProvider
 * @package Log4WorldShipments\Providers
 */
class Log4WorldShipmentsRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->post('shipment/plenty_tutorial/register_shipments', [
            'middleware' => 'oauth',
            'uses'       => 'Log4WorldShipments\Controllers\ShipmentController@registerShipments'
        ]);
  	}

}
