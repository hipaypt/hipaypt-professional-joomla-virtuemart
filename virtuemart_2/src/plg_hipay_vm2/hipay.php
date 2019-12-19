<?php

defined ('_JEXEC') or die('Restricted access');

/**
 *
 * payments using Hipay Wallet:
 * @author Diogo Ferreira
 * @version $Id: hipay.php 1111 2015-01-08 21:55:54Z $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) 2015 Hi-Pay Portugal. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentHipay extends vmPSPlugin {

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment Hipay Wallet Table');
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)',
			'challenge'					  => 'varchar(125)',
			'status'					  => 'smallint(1)',
			'cancel_key'				  => 'varchar(10)'
		);

		return $SQLfields;
	}

	/**
	 *
	 */
	function plgVmConfirmedOrder ($cart, $order) {


		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		VmConfig::loadJLang('com_virtuemart',true);
		VmConfig::loadJLang('com_virtuemart_orders', TRUE);

		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		$this->_debug = $method->debug;
		$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}

		if (!class_exists ('TableVendors')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel ('Vendor');
		$vendorModel->setId (1);
		$vendor = $vendorModel->getVendor ();
		$vendorModel->addImages ($vendor, 1);
		$this->getPaymentCurrency ($method);
		$email_currency = $this->getEmailCurrency ($method);
		$currency_code_3 = shopFunctions::getCurrencyByID ($method->payment_currency, 'currency_code_3');

		$paymentCurrency = CurrencyDisplay::getInstance ($method->payment_currency);
		$totalInPaymentCurrency = round ($paymentCurrency->convertCurrencyTo ($method->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
		$cd = CurrencyDisplay::getInstance ($cart->pricesCurrency);

		$quantity = 0;
		foreach ($cart->products as $key => $product) {
			$quantity = $quantity + $product->quantity;
		}

		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName ($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['paypal_custom'] = $return_context;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;

		$key = uniqid();
		$key = substr($key,10);
		$dbValues['cancel_key'] = $key;

		$this->storePSPluginInternalData ($dbValues);

		$account = $this->getPaymentAccount($method);		

/*		$this->getPaymentCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		$email_currency = $this->getEmailCurrency($method);


		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);

		$dbValues['payment_name'] = $this->renderPluginName ($method) . '<br />' . $method->payment_info;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$dbValues['status'] = 0;
		$challenge = uniqid();
		$challenge = substr($challenge,120);
		$dbValues['challenge'] = $challenge;
		$challenge_key = sha1($challenge.$dbValues['order_number']);
*/
		
		//$this->storePSPluginInternalData ($dbValues);
		$payment_info='';
		if (!empty($method->payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = vmText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);


		$hipay_base_url = JFactory::getDocument()->base;

		if ($account["account_sandbox"] == "1")
			$url="https://test-payment.hipay.com/order/";
		else        
			$url="https://payment.hipay.com/order/";

        $cleanXML='';
        $md5 = hash('md5',$xml);
        $cleanXML="<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        $cleanXML.="<mapi>\n";
        $cleanXML.="<mapiversion>1.0</mapiversion>\n";
        $cleanXML.='<md5content>'.$md5."</md5content>\n";
        $cleanXML.='<HIPAY_MAPI_SimplePayment>';
        $cleanXML.='<HIPAY_MAPI_PaymentParams><login>'.$account["account_number"].'</login><password>'.$account["account_password"].'</password><itemAccount>'.$account["account_number"].'</itemAccount>';
        $cleanXML.='<taxAccount>'.$account["account_number"].'</taxAccount>';
        $cleanXML.='<insuranceAccount>'.$account["account_number"].'</insuranceAccount>';
        $cleanXML.='<fixedCostAccount>'.$account["account_number"].'</fixedCostAccount>';
        $cleanXML.='<shippingCostAccount>'.$account["account_number"].'</shippingCostAccount>';
        $cleanXML.='<defaultLang>'.$account["defaultLang"].'</defaultLang>';
        $cleanXML.='<media>WEB</media>';
        $cleanXML.='<rating>'.$account["rating"].'</rating>';
        //if ($account["account_shopid"]!="") $cleanXML.='<shopID>'.$account["account_shopid"].'</shopID>';
        $cleanXML.='<paymentMethod>0</paymentMethod>';
        $cleanXML.='<captureDay>0</captureDay>';
        $cleanXML.='<currency>'.$currency_code_3.'</currency>';
        $cleanXML.='<idForMerchant>'.$dbValues['order_number'].'</idForMerchant>';
        $cleanXML.='<issuerAccountLogin>'.$order['details']['BT']->email.'</issuerAccountLogin>';
        $cleanXML.='<merchantSiteId>'.$account["account_website"].'</merchantSiteId>';
        $cleanXML.='<merchantDatas>';
        $cleanXML.='<_aKey_challenge>'.$challenge_key.'</_aKey_challenge>';
        $cleanXML.='</merchantDatas>';
        $cleanXML.='<url_ok>'.$hipay_base_url.'?option=com_virtuemart&amp;view=orders&amp;layout=details&amp;order_number='.$dbValues["order_number"].'&amp;order_pass='.$order['details']['BT']->order_pass.'</url_ok>';
        $cleanXML.='<url_nok>'.$hipay_base_url.'?option=com_virtuemart&amp;view=pluginresponse&amp;task=pluginnotification&amp;tmpl=hipay&amp;status=nok&amp;o='.$dbValues["order_number"].'&amp;hkey='.$dbValues['cancel_key'].'</url_nok>';
        $cleanXML.='<url_cancel>'.$hipay_base_url.'?option=com_virtuemart&amp;view=pluginresponse&amp;task=pluginnotification&amp;tmpl=hipay&amp;status=cancel&amp;o='.$dbValues["order_number"].'&amp;hkey='.$dbValues['cancel_key'].'</url_cancel>';
        $cleanXML.='<url_ack>'.$hipay_base_url.'?option=com_virtuemart&amp;view=pluginresponse&amp;task=pluginnotification&amp;tmpl=hipay</url_ack>';
        $cleanXML.='<email_ack>'.$account["account_email"].'</email_ack>';
        $cleanXML.='<bg_color>#000000</bg_color>';
        $cleanXML.='<logo_url></logo_url>';
        $cleanXML.='</HIPAY_MAPI_PaymentParams>';
        $cleanXML.='<order>';
        $cleanXML.='<HIPAY_MAPI_Order>';
        $cleanXML.='<shippingAmount>0</shippingAmount>';
        $cleanXML.='<insuranceAmount>0</insuranceAmount>';
        $cleanXML.='<fixedCostAmount>0</fixedCostAmount>';
        $cleanXML.='<orderCategory>'.$account["account_category"].'</orderCategory><orderTitle>'.$account["payment_title"].'</orderTitle><orderInfo>'.$account["payment_info"].'</orderInfo>';
        $cleanXML.='</HIPAY_MAPI_Order>';
        $cleanXML.='</order>';
        $cleanXML.='<items>';
        $cleanXML.='<HIPAY_MAPI_Product>';
        $cleanXML.='<name>'.$account["payment_title"].'</name>';
        $cleanXML.='<info></info>';
        $cleanXML.='<quantity>1</quantity>';
        $cleanXML.='<ref>'.$dbValues['order_number'].'</ref>';
        $cleanXML.='<category>1</category>';
        $cleanXML.='<price>'.$dbValues['payment_order_total'].'</price>';
        $cleanXML.='</HIPAY_MAPI_Product>';
        $cleanXML.='</items></HIPAY_MAPI_SimplePayment>';
        $cleanXML.="\n</mapi>\n";

        $xml = $cleanXML;
       
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_TIMEOUT, 9);
        curl_setopt($curl,CURLOPT_POST,true);
        curl_setopt($curl,CURLOPT_USERAGENT,"HI-MEDIA");
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); //Do not check SSL certificate (but use SSL of course), live dangerously!
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); //Return the result as string
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 33); //Return the result as string
        curl_setopt($curl,CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_POSTFIELDS,$url_params.'xml='.urlencode($xml));
        curl_setopt($curl, CURLOPT_HEADER, 0);

        $result = curl_exec($curl);
        //echo '<pre>';
        //print_r(curl_getinfo($curl));
        //echo '</pre><br><br>';
        if (curl_errno($curl) > 0) echo 'Errors: ' . curl_errno($curl) . ' ' . curl_error($curl) . '<br><br>';

        $xml_result = simplexml_load_string($result);

        //echo "RESPOSTA: " . $xml_result->result->status;
        //echo "<br>URL: " . $xml_result->result->url;
        curl_close($curl);
        if ($xml_result->result->status == "accepted"){


			// 	2 = don't delete the cart, don't send email and don't redirect
			$cart->_confirmDone = TRUE;
			$cart->_dataValidated = FALSE;
			$cart->setCartIntoSession ();
			$cart->emptyCart ();
				JRequest::setVar ('html', $html);

			//$modelOrder = VmModel::getModel ('orders');
			//$order['order_status'] = $this->getNewStatus ($method);
			//$order['customer_notified'] = 1;
			//$order['comments'] = '';
			//$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

			//We delete the old stuff
			//$cart->emptyCart ();
			//	vRequest::setVar ('html', $html);

	        header("location:". $xml_result->result->url);

        }


		//$html = $this->renderByLayout('post_payment', array(
		//	'order_number' =>$order['details']['BT']->order_number,
		//	'order_pass' =>$order['details']['BT']->order_pass,
		//	'payment_name' => $dbValues['payment_name'],
		//	'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display']
		//));
		return TRUE;
	}


	function getPaymentAccount($method){
		$payment_params = explode("|", $method->payment_params);

		foreach ($payment_params as $key => $value) {
			$value_temp = explode("=", $value);
			if ($value_temp[0]!="") $account[$value_temp[0]] = str_replace('"','',$value_temp[1]);
		}
		return $account;
	}


	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('HIPAY_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('HIPAY_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

			if (preg_match ('/%$/', $method->cost_percent_total)) {
				$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
			} else {
				$cost_percent_total = $method->cost_percent_total;
			}
			return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
		}
	*/
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert ($method);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}
		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * @param $method
	 */
	function convert ($method) {

		$method->min_amount = (float)$method->min_amount;
		$method->max_amount = (float)$method->max_amount;
	}

	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.

	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.

	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {


		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise

	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */

	function plgVmOnUserInvoice ($orderDetails, &$data) {
		

		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 	or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}


	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {


		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 */
	 
	function plgVmDeclarePluginParamsPayment ($name, $id, &$data) {

		return $this->declarePluginParams ('payment', $name, $id, $data);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {
		return $this->setOnTablePluginParams ($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param         $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int     $virtuemart_order_id : payment  order id
	 * @param char    $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int     $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text    $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
	return null;
	}
	 */


	public function plgVmOnPaymentNotification() {



		if (isset($_GET["status"])) {


			if ($_GET["status"]=='cancel' || $_GET["status"]=='nok') {



					$db = JFactory::getDBO();
					$q = "SELECT  * FROM " . $db->getPrefix() . "virtuemart_payment_plg_hipay WHERE cancel_key = '".$_GET["hkey"]."' AND order_number = '".$_GET["o"]."' LIMIT 1";
					$db->setQuery($q);
					$payment = $db->loadObjectList();
					if ($payment) {

						VmConfig::loadJLang('com_virtuemart',true);
						VmConfig::loadJLang('com_virtuemart_orders', TRUE);

						if (!class_exists ('VirtueMartModelOrders')) {
							require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
						}

	

						$modelOrder = VmModel::getModel ('orders');
						$order['customer_notified'] = 0;
						if ($_GET["status"] == "nok") {
							$order['order_status'] = 'D';
							$order['comments'] = 'HIPAY REFUSED';
						} else {
							$order['order_status'] = 'X';
							$order['comments'] = 'HIPAY USER CANCEL';				
						}
						$modelOrder->updateStatusForOneOrder ($payment[0]->virtuemart_order_id, $order, TRUE);
					
					}		
			}

		}	


		if (isset($_POST["xml"])) {

			

			$xml = $_POST["xml"];

			$operation = '';
			$status = '';
			$date = '';
			$time = '';
			$transid = '';
			$origAmount = '';
			$origCurrency = '';
			$idformerchant = '';
			$merchantdatas = array();
			$ispayment = true;

			try {
				$obj = new SimpleXMLElement(trim($xml));
			} catch (Exception $e) {
				$ispayment =  false;
			}
			if (isset($obj->result[0]->operation))
				$operation=$obj->result[0]->operation;
			else
				$ispayment =  false;

			if (isset($obj->result[0]->status))
				$status=$obj->result[0]->status;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->date))
				$date=$obj->result[0]->date;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->time))
				$time=$obj->result[0]->time;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->transid))
				$transid=$obj->result[0]->transid;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->origAmount))
				$origAmount=$obj->result[0]->origAmount;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->origCurrency))
				$origCurrency=$obj->result[0]->origCurrency;
			else 
				$ispayment = false;

			if (isset($obj->result[0]->idForMerchant))
				$idformerchant=$obj->result[0]->idForMerchant;
			else 
				$ispayment =  false;

			if (isset($obj->result[0]->merchantDatas)) {
				$d = $obj->result[0]->merchantDatas->children();
				foreach($d as $xml2) {
					if (preg_match('#^_aKey_#i',$xml2->getName())) {
						$indice = substr($xml2->getName(),6);
						//$xml2 = (array)$xml2;
						$valeur = (string)$xml2[0];
						$merchantdatas[$indice] = $valeur;
					}
				}
			}

			if ($ispayment===true) {

				
				//validate challenge and get virtuemart id
				$db = JFactory::getDBO();
				$q = "SELECT challenge,virtuemart_order_id FROM " . $db->getPrefix() . "virtuemart_payment_plg_hipay WHERE order_number = '".$idformerchant."' LIMIT 1";
				$db->setQuery($q);
				$payment = $db->loadObjectList();
				if (!$payment) return false;

				$challenge_key = sha1($payment[0]->challenge.$idformerchant);
				if ($challenge_key != $merchantdatas["challenge"]) return false;

				
				VmConfig::loadJLang('com_virtuemart',true);
				VmConfig::loadJLang('com_virtuemart_orders', TRUE);

				if (!class_exists ('VirtueMartModelOrders')) {
					require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
				}

				$modelOrder = VmModel::getModel ('orders');
				$order['customer_notified'] = 1;

				if ($status=="ok" && $operation=="capture") {
					//check challenge	
					$order['order_status'] = 'C';
					$order['comments'] = 'HIPAY CAPTURE OK';				
					$modelOrder->updateStatusForOneOrder ($payment[0]->virtuemart_order_id, $order, TRUE);
				}
				elseif ($status!="ok" && $operation!="authorization" ) {
					$order['order_status'] = 'D';
					$order['comments'] = 'HIPAY STATUS:' . $status . " OPERATION: " . $operation;
					$modelOrder->updateStatusForOneOrder ($payment[0]->virtuemart_order_id, $order, TRUE);
				}

			}

			return false;
		}	

		header("location: " . JFactory::getDocument()->base);
	}


}

// No closing tag



