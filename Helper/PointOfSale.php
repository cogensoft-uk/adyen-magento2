<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\ApplicationInfo;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class PointOfSale
{
    /** @var Data  */
    private $dataHelper;

    /** @var ProductMetadataInterface  */
    private $productMetadata;

    /** @var Config */
    protected $configHelper;

    public function __construct(
        Data $dataHelper,
        ProductMetadataInterface $productMetadata,
        Config $configHelper
    ) {
        $this->dataHelper = $dataHelper;
        $this->productMetadata = $productMetadata;
        $this->configHelper = $configHelper;
    }

    /**
     * Add SaleToAcquirerData to store recurring transactions and able to track platform and version
     * When upgrading to new version of library we can use the client methods
     *
     * @param $request
     * @param Quote|null $quote
     * @param Order|null $order
     * @return array
     */
    public function addSaleToAcquirerData($request, Quote $quote = null, Order $order = null) : array
    {
        // If order is created from admin backend, use Order instead of Quote
        if (isset($order) && is_null($quote)) {
            $customerId = $order->getCustomerId();
            $storeId = $order->getStoreId();
            $shopperEmail = $order->getCustomerEmail();
        }
        else {
            $customerId = $this->getCustomerId($quote);
            $storeId = $quote->getStoreId();
            $shopperEmail = $quote->getCustomerEmail();
        }

        $saleToAcquirerData = [];

        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $recurringContract = $this->configHelper->getAdyenPosCloudConfigData('recurring_type', $storeId);

            if (!empty($recurringContract) && !empty($shopperEmail)) {
                $saleToAcquirerData['shopperEmail'] = $shopperEmail;
                $saleToAcquirerData['shopperReference'] = $this->dataHelper->padShopperReference($customerId);
                $saleToAcquirerData['recurringContract'] = $recurringContract;
            }
        }

        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::VERSION] = $this->dataHelper->getModuleVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::NAME] = $this->dataHelper->getModuleName();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::VERSION] = $this->productMetadata->getVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::NAME] = $this->productMetadata->getName();
        $saleToAcquirerDataBase64 = base64_encode(json_encode($saleToAcquirerData));
        $request['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData'] = $saleToAcquirerDataBase64;

        return $request;
    }

    /**
     * This getter makes it possible to overwrite the customer id from other plugins
     * Use this function to get the customer id so we can keep using this plugin in the UCD
     */
    public function getCustomerId(Quote $quote): ?string
    {
        return $quote->getCustomerId();
    }

    /**
     * @param array $installments
     * @param float $amount
     * @param string $currencyCode
     * @param int $precision
     * @return array
     */
    public function getFormattedInstallments(
        array $installments,
        float $amount,
        string $currencyCode,
        int $precision
    ): array {
        $formattedInstallments = [];

        foreach ($installments as $minAmount => $installment) {
            if ($amount >= $minAmount) {
                $dividedAmount = number_format($amount / $installment, $precision);
                $formattedInstallments[$installment] =
                    sprintf("%s x %s %s", $installment, $dividedAmount, $currencyCode);
            }
        }

        return $formattedInstallments;
    }
}
