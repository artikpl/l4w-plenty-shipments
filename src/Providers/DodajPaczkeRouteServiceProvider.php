<?php

namespace Log4World\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class Log4WorldRouteServiceProvider
 * @package Log4World\Providers
 */
class Log4WorldRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param Router $router
     */
    public function map(Router $router)
    {
        $router->get('Log4World','Log4World\Controllers\Log4WorldController@index');
    }
}