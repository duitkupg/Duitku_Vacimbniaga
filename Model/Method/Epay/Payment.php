<?php
/**
 * Copyright (c) 2017. All rights reserved Duitku Vacimbniaga.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Duitku Vacimbniaga
 * @copyright Duitku Vacimbniaga (http://duitku.com)
 * @license   Duitku Vacimbniaga
 *
 */
namespace Duitku\Vacimbniaga\Model\Method\Epay;
use \Magento\Sales\Model\Order\Payment\Transaction;
use \Duitku\Vacimbniaga\Helper\DuitkuConstants;

class Payment extends \Duitku\Vacimbniaga\Model\Method\AbstractPayment
{
    const METHOD_CODE = 'duitku_vacimbepay';
    const METHOD_REFERENCE = 'duitkuvacimbReference';

    protected $_code = self::METHOD_CODE;

    protected $_infoBlockType = 'Duitku\Vacimbniaga\Block\Info\View';

    /**
     * Payment Method feature
     */
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canDelete                   = true;

    /**
     * @var \Duitku\Vacimbniaga\Model\Api\Epay\Request\Models\Auth
     */
    protected $_auth;

    /**
     * Get ePay Auth object
     *
     * @return \Duitku\Vacimbniaga\Model\Api\Epay\Request\Models\Auth
     */
    public function getAuth()
    {
        if (!$this->_auth) {
            $storeId = $this->getStoreManager()->getStore()->getId();
            $this->_auth = $this->_duitkuHelper->generateEpayAuth($storeId);
        }

        return $this->_auth;
    }

    /**
     * Get Duitku Checkout payment window
     *
     * @param \Magento\Sales\Model\Order
     * @return \Duitku\Vacimbniaga\Model\Api\Epay\Request\Payment
     */
    public function getPaymentWindow($order)
    {
    	
        if (!isset($order)) {
            return null;
        }
        return $this->createPaymentRequest($order);
    }

    /**
     * Create the ePay payment window Request url
     *
     * @param \Magento\Sales\Model\Order
     * @return \Duitku\Vacimbniaga\Model\Api\Epay\Request\Payment
     */
    public function createPaymentRequest($order)
    {
    $obj = \Magento\Framework\App\ObjectManager::getInstance();
   	$orderId = $order->getIncrementId();
   	$merchantcode = $obj->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/duitku_vacimbepay/merchantnumber');
  	 $apikey = $obj->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/duitku_vacimbepay/api_key');
    $amount = round($order->getBaseTotalDue());
    
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
    $FormKey = $objectManager->get('Magento\Framework\Data\Form\FormKey');
    $callbackUrl = $this->_urlBuilder->getUrl('duitku/epayvacimb/callback?isAjax=true&form_key='.$FormKey->getFormKey());
    $returnUrl = $this->_urlBuilder->getUrl('duitku/epayvacimb/accept');
    $merchantUserInfo = $order->getCustomerFirstname() . " " . $order->getCustomerLastname();
    $email = $order->getCustomerEmail();

	//ItemDetails
	$itemsData = $order->getAllItems();
	$shippingAmountData = $order->getShippingAmount();
	$shippingTaxAmountData = $order->getShippingTaxAmount();
	$taxAmountData = $order->getTaxAmount();
	$DiscountAmount = $order->getDiscountAmount();
	
		$itemDetailParams = array();
		foreach ($itemsData as $value) {
			
		  $ItemPrice = (int)$value->getPrice() * (int)$value->getQtyOrdered();
		  
		  $item = array(
			'name' => $this->repString($this->getName($value->getName())),
			'price' => (int)$ItemPrice,
			'quantity' => (int)$value->getQtyOrdered(),
		  );
		  $itemDetailParams[] = $item;
		}

		if ($shippingAmountData > 0) {
		  $shippingItem = array(
			'name' => 'Shipping Amount',
			'price' => (int)$shippingAmountData,
			'quantity' => 1
		  );
		  $itemDetailParams[] = $shippingItem;
		}

		if ($shippingTaxAmountData > 0) {
		  $shippingTaxItem = array(
			'name' => 'Shipping Tax',
			'price' => (int)$shippingTaxAmountData,
			'quantity' => 1
		  );
		  $itemDetailParams[] = $shippingTaxItem;
		}

		if ($taxAmountData > 0) {
		  $taxItem = array(
			'name' => 'Tax',
			'price' => (int)$taxAmountData,
			'quantity' => 1
		  );
		  $itemDetailParams[] = $taxItem;
		}

		if ($DiscountAmount != 0) {
		  $couponItem = array(
			  'id' => 'DISCOUNT',
			  'price' => (int)$DiscountAmount,
			  'quantity' => 1,
			  'name' => 'DISCOUNT'
			);
		  $itemDetailParams[] = $couponItem;
		}
		
		$paymentAmount = 0;
		foreach ($itemDetailParams as $item) {
		  $paymentAmount += $item['price'];
		}
	
		$billing_address = array(
		  'firstName' => $order->getCustomerFirstname(),
		  'lastName' => $order->getCustomerLastname(),
		  'address' => $order->getBillingAddress()->getStreet()[0],
		  'city' => $order->getBillingAddress()->getCity(),
		  'postalCode' => $order->getBillingAddress()->getPostcode(),
		  'phone' => $order->getBillingAddress()->getTelephone(),
		  'countryCode' => $order->getBillingAddress()->getCountryId(),
		);
		
		$customerDetails = array(
			'firstName' => $order->getCustomerFirstname(),
			'lastName' => $order->getCustomerLastname(),
			'email' => $email,
			'phoneNumber' => $order->getBillingAddress()->getTelephone(),
			'billingAddress' => $billing_address,
			'shippingAddress' => $billing_address
		);
				
		$signature = hash("sha256",$merchantcode.$orderId.$paymentAmount.$apikey);
		
		$params = array(
             'merchantCode' => $merchantcode,
             'paymentAmount' => $paymentAmount,
             'paymentMethod' => 'B1',
			 'merchantOrderId' =>$orderId,
             'productDetails' => 'Order : '.$orderId,
             'additionalParam' => '',
             'merchantUserInfo' => $merchantUserInfo,
			 'customerVaName' => $merchantUserInfo,
			 'email' => $email,
			 'phoneNumber' => $order->getBillingAddress()->getTelephone(),		 
             'callbackUrl' => $callbackUrl,
			 'expiryPeriod' => 1440,
             'returnUrl' => $returnUrl,
             'signature' => $signature,
			 'customerDetail' => $customerDetails,
			 'itemDetails' => $itemDetailParams,
       'hashAlgorithm' => 'sha256'
         );
		 
        return $params;
    }


    /**
     * Calculate the shipment Vat based on shipment tax and base shipment price
     *
     * @param \Magento\Sales\Model\Order $order
     * @return int
     */
    public function calculateShippingVat($order)
    {
        if ($order->getBaseShippingTaxAmount() <= 0 || $order->getBaseShippingAmount() <= 0) {
            return 0;
        }
        $shippingVat = round(($order->getBaseShippingTaxAmount() / $order->getBaseShippingAmount()) * 100);
        return $shippingVat;
    }

    /**
     * Remove special characters
     *
     * @param string $value
     * @return string
     */
    public function removeSpecialCharacters($value)
    {
        return preg_replace('/[^\p{Latin}\d ]/u', '', $value);
    }

   
   

    /**
     * Cancel payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        try {
            $this->void($payment);
            $this->_messageManager->addSuccessMessage(__("The payment have been voided").' ('.$payment->getOrder()->getIncrementId().')');
        } catch (\Exception $ex) {
            $this->_messageManager->addErrorMessage($ex->getMessage());
        }

        return $this;
    }

   

    /**
     * Get Duitku Checkout Transaction
     *
     * @param mixed $transactionId
     * @param string &$message
     * @return \Duitku\Vacimbniaga\Model\Api\Epay\Response\Models\TransactionInformationType|null
     */
   

    /**{@inheritDoc}*/
    public function canCapture()
    {
        if ($this->_canCapture && $this->canAction($this::METHOD_REFERENCE)) {
            return true;
        }

        return false;
    }

    /**{@inheritDoc}*/
    public function canRefund()
    {
        if ($this->_canRefund && $this->canAction($this::METHOD_REFERENCE)) {
            return true;
        }

        return false;
    }

    /**{@inheritDoc}*/
    public function canVoid()
    {
        if ($this->_canVoid && $this->canAction($this::METHOD_REFERENCE)) {
            return true;
        }

        return false;
    }

   
    /**
     * Retrieve an url for the ePay Checkout action
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->_urlBuilder->getUrl('duitku/epayvacimb/checkout', ['_secure' => $this->_request->isSecure()]);
    }

    /**
     * Retrieve an url for the ePay Decline action
     *
     * @return string
     */
    public function getCancelUrl()
    {
        return $this->_urlBuilder->getUrl('duitku/epayvacimb/cancel', ['_secure' => $this->_request->isSecure()]);
    }

	private function repString($str) {
		return preg_replace("/[^a-zA-Z0-9]+/", " ", $str);
	}

	private function getName($s) {
		$max_length = 20;
		if (strlen($s) > $max_length) {
		  $offset = ($max_length - 3) - strlen($s);
		  $s = substr($s, 0, strrpos($s, ' ', $offset));
		}
		return $s;
	}

}
