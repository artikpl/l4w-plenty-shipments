<?php

namespace Log4World\Controllers;

use DateTime;
use Exception;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Cloud\Storage\Models\StorageObject;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\ParcelService\Models\ParcelServicePreset;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Plenty\Modules\Order\Shipping\PackageType\Contracts\ShippingPackageTypeRepositoryContract;

/**
 * Class ShippingController
 */
class ShippingController extends Controller
{
    use Loggable;

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

    /*  LOG4WORLD API CONFIGURATION */
    private $l4wApiUrl;
    private $l4wApiLogin;
    private $l4wApiPassword;
    private $l4wApiShipperId;
    private $l4wApiProviderId;

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
    public function __construct(
        Request $request,
        OrderRepositoryContract $orderRepository,
        AddressRepositoryContract $addressRepositoryContract,
        OrderShippingPackageRepositoryContract $orderShippingPackage,
        StorageRepositoryContract $storageRepository,
        ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
        ShippingPackageTypeRepositoryContract $shippingPackageTypeRepositoryContract,
        ConfigRepository $config
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->addressRepository = $addressRepositoryContract;
        $this->orderShippingPackage = $orderShippingPackage;
        $this->storageRepository = $storageRepository;

        $this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
        $this->shippingPackageTypeRepositoryContract = $shippingPackageTypeRepositoryContract;

        $this->config = $config;

        /* LOG4WORLD configuration */
        $this->l4wApiUrl = $config->get('Log4World.global.l4wApiUrl');
        $this->l4wApiUrl = "https://api.log4world.com";
        $this->l4wApiLogin = $config->get('Log4World.global.l4wApiLogin');
        $this->l4wApiPassword = $config->get('Log4WorldShipments.global.l4wApiPassword');
        $this->l4wApiShipperId = $config->get('Log4World.global.l4wApiShipperId');
        $this->l4wApiProviderId = $config->get('Log4World.global.l4wApiProviderId');
    }

    /**
     * Registers shipment(s)
     *
     * @param Request $request
     * @param array $orderIds
     * @return array
     */
    public function registerShipments(Request $request, array $orderIds): array
    {
        $orderIds = $this->getOrderIds($request, $orderIds);
        $orderIds = $this->getOpenOrderIds($orderIds);
        $shipmentDate = date('Y-m-d');
        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->findOrderById($orderId);
            $packages = $this->orderShippingPackage->listOrderShippingPackages($order->id);
            $shipmentItems = [];
            foreach ($packages as $package) {
                /* @var $package OrderShippingPackage */
                $requestData = $this->buildCreateRequestData($order, $this->getPackageItemDetails($package));
                $requestHandler = $this->handleCreateRequest($requestData);
                if ($requestHandler['success']) {
                    $shipmentItems[] = $this->handleAfterRegisterShipment(
                        $requestHandler['labelUrl'] ?? '',
                        $requestHandler['shipmentNumber'] ?? '',
                        (int) $requestHandler['Log4WorldShipmentId'] ?? 0,
                        $package->id
                    );
                    $this->createOrderResult[$orderId] = $this->buildResultArray(
                        true,
                        $this->getStatusMessage($requestHandler),
                        false,
                        $shipmentItems
                    );
                    $this->saveShippingInformation($orderId, $shipmentDate, $shipmentItems);
                } else {
                    $this->createOrderResult[$orderId] = $this->buildResultArray(
                        false,
                        $this->getStatusMessage($requestHandler)
                    );
                }
            }
        }

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
        $orderIds = $this->getOrderIds($request, $orderIds);
        foreach ($orderIds as $orderId) {
            $shippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId(
                $orderId
            );
            if (isset($shippingInformation->additionalData) && is_array($shippingInformation->additionalData)) {
                $success = true;
                foreach ($shippingInformation->additionalData as $additionalData) {
                    if (isset($additionalData['Log4WorldShipmentId'])) {
                        $Log4WorldShipmentId = $additionalData['Log4WorldShipmentId'];
                        $requestHandler = $this->handleCancelRequest($Log4WorldShipmentId);
                        if ($requestHandler['success']) {
                            $this->createOrderResult[$orderId] = $this->buildResultArray(
                                true,
                                $this->getStatusMessage($requestHandler)
                            );
                        } else {
                            $this->createOrderResult[$orderId] = $this->buildResultArray(
                                false,
                                $this->getStatusMessage($requestHandler)
                            );
                            $success = false;
                        }
                    } else {
                        $status = [
                            'status' => 'Could not find selected Log4World shipment item in your system.' .
                                ' You have to cancel it manually.'
                        ];
                        $this->createOrderResult[$orderId] = $this->buildResultArray(
                            false,
                            $this->getStatusMessage($status)
                        );
                    }
                    if ($success) {
                        $this->shippingInformationRepositoryContract->resetShippingInformation($orderId);
                    }
                }
            }
        }

        return $this->createOrderResult;
    }

    public function getLabels(Request $request, $orderIds)
    {
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
                    $this->getLogger(__METHOD__)->error("Log4World::logging.exception", $e);
                }

                if (
                    !is_null($labelKey) &&
                    $this->storageRepository->doesObjectExist("Log4World", "$labelKey.pdf")
                ) {
                    $storageObject = $this->storageRepository->getObject('Log4World', "$labelKey.pdf");
                    $this->getLogger(__METHOD__)
                        ->info("Log4World::logging.labelFound", 'Label has been found.');

                    $labels[] = $storageObject->body;
                }
            }
        }

        return $labels;
    }


    /**
     * Retrieves the label file from a given URL and saves it in S3 storage
     */
    private function saveLabelToS3(string $labelUrl, string $key): StorageObject
    {
        $output = $this->handleLabelRequest($labelUrl);
        if (is_null($output)) {
            $this->storageRepository->uploadObject('Log4World', $key, '');
        }
        // Convert Base64URL to Base64.
        $output = str_replace(['-', '_'], ['+', '/'], $output);
        $output = base64_decode($output);

        return $this->storageRepository->uploadObject('Log4World', $key, $output);
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

        if ($parcelServicePreset) {
            return $parcelServicePreset;
        } else {
            return null;
        }
    }

    /**
     * Returns a formatted status message
     *
     * @param array $response
     * @return string
     */
    private function getStatusMessage($response): string
    {
        return 'Code: ' . $response['status'];
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
        $transactionIds = [];
        foreach ($shipmentItems as $shipmentItem) {
            $transactionIds[] = $shipmentItem['shipmentNumber'];
        }

        $shipmentAt = date(DateTime::W3C, strtotime($shipmentDate));
        $registrationAt = date(DateTime::W3C);

        $data = [
            'orderId' => $orderId,
            'transactionId' => implode(',', $transactionIds),
            'shippingServiceProvider' => 'Log4World',
            'shippingStatus' => 'registered',
            'shippingCosts' => 0.00,
            'additionalData' => $shipmentItems,
            'registrationAt' => $registrationAt,
            'shipmentAt' => $shipmentAt
        ];
        $this->shippingInformationRepositoryContract->saveShippingInformation($data);
    }

    /**
     * Returns all order ids with shipping status 'open'
     */
    private function getOpenOrderIds(array $orderIds): array
    {
        $openOrderIds = [];
        foreach ($orderIds as $orderId) {
            $shippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId(
                $orderId
            );
            if ($shippingInformation->shippingStatus == null || $shippingInformation->shippingStatus == 'open') {
                $openOrderIds[] = $orderId;
            }
        }

        return $openOrderIds;
    }


    /**
     * Returns an array in the structure demanded by plenty service
     */
    private function buildResultArray(
        bool $success = false,
        string $statusMessage = '',
        bool $newShippingPackage = false,
        array $shipmentItems = []
    ): array {
        return [
            'success' => $success,
            'message' => $statusMessage,
            'newPackagenumber' => $newShippingPackage,
            'packages' => $shipmentItems,
        ];
    }

    /**
     * Returns shipment array
     */
    private function buildShipmentItems(string $labelUrl, string $shipmentNumber, int $Log4WorldShipmentId): array
    {
        return [
            'labelUrl' => $labelUrl,
            'shipmentNumber' => $shipmentNumber,
            'Log4WorldShipmentId' => $Log4WorldShipmentId
        ];
    }

    /**
     * Returns package info
     */
    private function buildPackageInfo(string $packageNumber, string $labelPath): array
    {
        return [
            'packageNumber' => $packageNumber,
            'label' => $labelPath,
        ];
    }

    /**
     * Returns all order ids from request object
     *
     * @param Request $request
     * @param $orderIds
     * @return array
     */
    private function getOrderIds(Request $request, $orderIds): array
    {
        if (is_numeric($orderIds)) {
            $orderIds = [$orderIds];
        } else {
            if (!is_array($orderIds)) {
                $orderIds = $request->get('orderIds');
            }
        }
        return $orderIds;
    }

    private function getPackageItemDetails(OrderShippingPackage $package): array
    {
        list($length, $width, $height) = $this->getPackageDimensions((int) $package->packageId);

        return [
            'weightInKg' => $package->weight / 1000, // [mr] $package->weight - docs. The weight of the package in grams
            'lengthInCm' => $length,
            'widthInCm' => $width,
            'heightInCm' => $height,
            'packageType' => 'PK'
        ];
    }

    /**
     * Returns the package dimensions by package type
     */
    private function getPackageDimensions(int $packageId): array
    {
        $packageType = $this->shippingPackageTypeRepositoryContract->findShippingPackageTypeById($packageId);
        if ($packageType->length > 0 && $packageType->width > 0 && $packageType->height > 0) {
            $length = $packageType->length;
            $width = $packageType->width;
            $height = $packageType->height;
        } else {
            $length = null;
            $width = null;
            $height = null;
        }

        return [$length, $width, $height];
    }

    /**
     * Handling of response values, fires S3 storage and updates order shipping package
     */
    private function handleAfterRegisterShipment(
        string $labelUrl,
        string $shipmentNumber,
        int $Log4WorldShipmentId,
        int $packageId
    ): array {
        $storageObject = $this->saveLabelToS3(
            $labelUrl,
            $shipmentNumber . '.pdf'
        );
        $shipmentItems = $this->buildShipmentItems(
            $labelUrl,
            $shipmentNumber,
            $Log4WorldShipmentId
        );
        $this->orderShippingPackage->updateOrderShippingPackage(
            $packageId,
            $this->buildPackageInfo(
                $shipmentNumber,
                $storageObject->key
            )
        );

        return $shipmentItems;
    }

    /* LOG4WORLD service */

    /**
     * Authorization
     * @return array|null
     */
    private function handleAuthRequest()
    {
        $ch = curl_init("$this->l4wApiUrl/users/authentication?login=$this->l4wApiLogin&password=$this->l4wApiPassword");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response === false) {
            return null;
        }
        $response = json_decode($response, true);
        if (isset($response['error'])) {
            return [
                'success' => false,
                'status' => $response['error']['code'] . ' - ' .
                    $response['error']['message'] .
                    ' Check the plugin configuration.'
            ];
        }
        if (isset($response['data'])) {
            return [
                'success' => true,
                'accessToken' => $response['data']['accessToken']
            ];
        }

        return null;
    }

    /* Sending Shipment Requests */

    private function handleCreateRequest(array $requestData): array
    {
        $authRequestHandler = $this->handleAuthRequest();
        if (is_null($authRequestHandler)) {
            return [
                'success' => false,
                'status' => 'There was a problem connecting to the API. Check if provided API url is valid.'
            ];
        } else {
            if ($authRequestHandler['success'] === false) {
                return [
                    'success' => false,
                    'status' => $authRequestHandler['status']
                ];
            }
        }
        $accessToken = $authRequestHandler['accessToken'];
        $json = json_encode($requestData);
        $ch = curl_init("$this->l4wApiUrl/shipments?sync=1");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ["Authorization: $accessToken", 'Content-Type: application/json', 'Content-Length: ' . strlen($json)]
        );
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response !== false) {
            $response = json_decode($response, true);
        }
        if (is_array($response) === false || isset($response['data']['shipments']) === false) {
            return [
                'success' => false,
                'status' => 'There was a problem retrieving response from API. Try again later, please.'
            ];
        }
        $errors = $this->getCreationErrors($response);
        if (is_null($errors) === false || $statusCode != 201) {
            return [
                'success' => false,
                'status' => is_null($errors) === false ? ("Errors found - $errors") : 'Could not create a shipment.'
            ];
        }
        $shipmentData = $this->retrieveShipmentData($response);
        if (is_null($shipmentData)) {
            return [
                'success' => false,
                'status' => 'Shipment has been created in Log4World but we failed retrieving its\' data.',
            ];
        }

        return [
            'success' => true,
            'status' => 'Shipment has been created in Log4World service.',
            'shipmentNumber' => $shipmentData['shipmentNumber'],
            'labelUrl' => $shipmentData['labelUrl'],
            'Log4WorldShipmentId' => $shipmentData['Log4WorldShipmentId']
        ];
    }

    private function handleCancelRequest($shipmentNumber): array
    {
        $authRequestHandler = $this->handleAuthRequest();
        if (is_null($authRequestHandler)) {
            return [
                'success' => false,
                'status' => 'There was a problem connecting to the API. Check if provided API url is valid.'
            ];
        } else if ($authRequestHandler['success'] === false) {
            return [
                'success' => false,
                'status' => $authRequestHandler['status']
            ];
        }
        $accessToken = $authRequestHandler['accessToken'];
        $ch = curl_init("$this->l4wApiUrl/shipments/$shipmentNumber?operation=cancel");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $accessToken"]);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response !== false) {
            $response = json_decode($response, true);
        }
        if (is_array($response) === false || isset($response['data']['message']) === false) {
            return [
                'success' => false,
                'status' => 'There was a problem retrieving response from API. Try again later, please.'
            ];
        }
        if ($statusCode != 200) {
            return [
                'success' => false,
                'status' => "Shipment $shipmentNumber cannot be cancelled."
            ];
        }

        return [
            'success' => true,
            'status' => "Shipment $shipmentNumber has been cancelled."
        ];
    }

    /**
     * @param string $labelUrl
     * @return string|null
     */
    public function handleLabelRequest(string $labelUrl)
    {
        $authRequestHandler = $this->handleAuthRequest();
        if (is_null($authRequestHandler)) {
            return null;
        } else if ($authRequestHandler['success'] === false) {
            return null;
        }
        $accessToken = $authRequestHandler['accessToken'];
        $ch = curl_init($labelUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $accessToken"]);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response !== false) {
            $response = json_decode($response, true);
        }
        if (isset($response['data']['file']) === false) {
            return null;
        }

        return $response['data']['file'];
    }

    /**
     * Get shipment data from response.
     * @param array $response
     * @return array|null
     */
    private function retrieveShipmentData(array $response)
    {
        if (count($response['data']['shipments']) !== 1 ||
            isset($response['data']['shipments'][0]['shipment']) === false
        ) {
            return null;
        }
        $shipment = $response['data']['shipments'][0]['shipment'];
        $shipmentId = $shipment['id'];

        return [
            'shipmentNumber' => $shipment['trackingNumber'],
            'Log4WorldShipmentId' => $shipmentId,
            'labelUrl' => "$this->l4wApiUrl/shipments/$shipmentId/shippingLabel"
        ];
    }

    /* Handling errors in response */
    /**
     * @param $response
     * @return string|null
     */
    private function getCreationErrors($response)
    {
        $errors = [];
        foreach ($response['data']['shipments'] as $shipment) {
            if (isset($shipment['errorResponse']['data']['details'])) {
                foreach ($shipment['errorResponse']['data']['details'] as $details) {
                    foreach ($details as $error) {
                        $errors[] = $error;
                    }
                }
            }
            if (isset($shipment['errorResponse']['data'])) {
                $additionalErrors = $this->retrieveErrorsFromResponse($shipment['errorResponse']['data']);
                foreach ($additionalErrors as $error) {
                    $errors[] = $error;
                }
            }
            if (isset($shipment['errorMessage']) && empty($errors)) {
                $errors[] = $shipment['errorMessage'];
            }
        }
        if (empty($errors) === false) {
            // [mr] preg_replace() - due to a status message doesn't show up if it contains special language
            // characters (ą,ę,ś,ć,etc.) and our API sometimes returns errors in polish.
            return preg_replace("/[^a-zA-Z0-9\s,.-]/", "", implode(', ', $errors));
        }

        return null;
    }

    /**
     * @param $data
     * @return array
     */
    private function retrieveErrorsFromResponse($data): array
    {
        $errors = [];
        array_walk_recursive($data, function ($element) use (&$errors) {
            if (is_array($element) === false) {
                $errors[] = $element;
            }
        });

        return $errors;
    }

    /* Building requests data */
    public function buildCreateRequestData(Order $order, array $item): array
    {
        /* @var $deliveryAddress Address */
        $deliveryAddress = $order->deliveryAddress;
        $receiver = $this->createReceiverData($deliveryAddress);
        /* [mr] COD by default is 1 or '1'. */
        if ($order->methodOfPaymentId == 1) {
            return [
                'shipperId' => $this->l4wApiShipperId,
                'provider' => ['id' => $this->l4wApiProviderId],
                'receiver' => $receiver,
                'item' => $item,
                'description' => 'Shipment of goods.',
                'detail' => [
                    'codAmount' => $order->amount->currency,
                    'codCurrency' => $order->amount->grossTotal
                ]
            ];
        }

        return [
            'shipperId' => $this->l4wApiShipperId,
            'provider' => ['id' => $this->l4wApiProviderId],
            'receiver' => $receiver,
            'item' => $item,
            'description' => 'Shipment of goods.',
        ];
    }

    private function createReceiverData(Address $address): array
    {
        return [
            'type' => strlen($address->companyName) ? 'company' : 'person',
            'firstname' => $address->firstName,
            'lastname' => $address->lastName,
            'companyName' => $address->companyName,
            'identityAddress' => [
                'streetName' => $address->street,
                'streetNumber' => $address->houseNumber . (
                    strlen($address->additional)
                        ? " $address->additional"
                        : ''
                    ),
                'city' => $address->town,
                'zipNumber' => $address->postalCode,
                'originCountryISOCode' => $address->country->isoCode2
            ],
            'identityCommunication' => [
                'phone' => strlen($address->phone) ? $address->phone : '',
                'mobile' => strlen($address->personalNumber) ? $address->personalNumber : '',
                'email' => $address->email,
                'contactPerson' => $address->contactPerson
            ]
        ];
    }
}
