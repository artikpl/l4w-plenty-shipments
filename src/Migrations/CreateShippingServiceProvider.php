<?php

namespace Log4World\Migrations;

use Exception;
use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Order\Shipping\ServiceProvider\Contracts\ShippingServiceProviderRepositoryContract;

class CreateShippingServiceProvider
{
    use Loggable;
    /*
     * @var ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository
     */
    private $shippingServiceProviderRepository;

    /**
     * @param ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository
     */
    public function __construct(ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository)
    {
        $this->shippingServiceProviderRepository = $shippingServiceProviderRepository;
    }

    /**
     * @return void
     */
    public function run()
    {
        try
        {
            $this->shippingServiceProviderRepository->saveShippingServiceProvider(
                'Log4World',
                'Log4World'
            );
        }
        catch (Exception $e)
        {
            $this->getLogger('Log4World')->critical('Could not save or update shipping service provider');
        }
    }
}