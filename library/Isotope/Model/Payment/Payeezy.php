<?php

/**
 * Copyright (C) 2016 Rhyme Digital, LLC.
 * 
 * @author		Blair Winans <blair@rhyme.digital>
 * @author		Adam Fisher <adam@rhyme.digital>
 * @author		Cassondra Hayden <cassie@rhyme.digital>
 * @author		Melissa Frechette <melissa@rhyme.digital>
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Isotope\Model\Payment;

use Isotope\Model\Payment;
use Isotope\Interfaces\IsotopePayment;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Isotope;
use Isotope\Model\Product;
use Isotope\Model\OrderStatus;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\Module\OrderDetails as ModuleIsotopeOrderDetails;


//Import Payeezy SDK
require_once TL_ROOT . '/system/modules/isotope_payeezy/vendor/Payeezy.php';


/**
 * Class Payeezy
 *
 * Handle cash payments
 * @author Adam Fisher <adam@rhyme.digital>
 */
class Payeezy extends Payment implements IsotopePayment
{
	
	/**
	 * Cart object - Able to handle other cart types than just IsotopeCart
	 *
	 * @access protected
	 * @var IsotopeProductCollection
	 */
	protected $objCart;
	
	/**
	 * Cart field - could vary according to collection type
	 *
	 * @access protected
	 * @var string
	 */
	protected $strCartField = 'cart_id';
	
	/**
	 * Response object
	 *
	 * @access protected
	 */
	protected $objResponse;

	/**
	 * Reason
	 *
	 * @access protected
	 * @var string
	 */
	protected $strReason;

	/**
	 * Transaction ID
	 *
	 * @access protected
	 * @var string
	 */
	protected $strTransactionID;

	/**
	 * Proceed to complete step?
	 *
	 * @access protected
	 * @var boolean
	 */
	protected $blnProceed = false;

	/**
	 * Form ID
	 *
	 * @access protected
	 * @var string
	 */
	protected $strFormId = 'payment_payeezy';

	/**
	 * Template
	 *
	 * @access protected
	 * @var string
	 */
	protected $strTemplate = 'iso_payment_payeezy';

	/**
	 * Card types (need the full string)
	 *
	 * @access protected
	 * @var array
	 */
	protected static $arrCardTypes = array
	(
		'V'		=> 'visa',
		'M'		=> 'mastercard',
		'D'		=> 'discover',
		'A'		=> 'american express',
	);
	
	
	/**
	 * Import libraries and initialize some variables
	 */
	public function __construct(\Database\Result $objResult = null)
	{
		parent::__construct($objResult);
		
		$this->objCart = TL_MODE === 'FE' ? Isotope::getCart() : null;
		
		// Temporary
		$this->override_formaction = '1'; // \Input::get('step') != 'process';
		$this->tableless = true;
	}


	/**
	 * Process payment on confirmation page.
	 *
	 * @access public
	 * @return mixed
	 */
	public function processPayment(IsotopeProductCollection $objOrder, \Module $objModule)
	{
		//We have already done the Authorization - go to Complete step
		return true;
	}
	
	
	/**
	 * Generate payment authorization form and AUTH or CAPTURE
	 *
	 * @access 	public
	 * @param 	object
	 * @return	mixed
	 */
	public function checkoutForm(IsotopeProductCollection $objOrder, \Module $objModule)
	{
		if ($this->override_formaction)
		{
			if (isset($_SESSION['CHECKOUT_DATA']['payeezy']['payment_data']))
			{
				\Database::getInstance()->prepare("UPDATE tl_iso_product_collection %s WHERE id=?")
										->set(array('payment_data'=>serialize($_SESSION['CHECKOUT_DATA']['payeezy']['payment_data'])))
										->executeUncached($objOrder->id);
				
				unset($_SESSION['CHECKOUT_DATA']['payeezy']['payment_data']);
			}
			
			return false;
		}
		
		$this->setCart($objModule);
		
		// Get CC Form
		$strCCForm = $this->getCreditCardForm($objModule, $objOrder);
		
		// Set form action to the current URL
		$strAction = htmlentities($this->removeGetParams($this->Environment->base . $this->Environment->request));
		
		// todo: put this in a template
		$strFormStart = "\n" . 
			'<form action="'.$strAction.'" id="iso_mod_checkout_payment" method="post" enctype="application/x-www-form-urlencoded">' . "\n" . 
			'<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">' . "\n" .
			'<input type="hidden" name="FORM_SUBMIT" value="'.$this->strFormId.'">' . "\n";
		
		$strBuffer = $strFormStart . $strCCForm;
		
		if (!$this->tableless)
        {
        	$strBuffer .= '<table class="ccform">' . "\n";
        }
		
		$objWidget = new \FormSubmit(array('slabel'=>'Order'));
		$objWidget->tableless = $this->tableless;
		$objWidget->id = 'confirm';
		$strBuffer .= "\n" . $objWidget->parse();
		
		if (!$this->tableless)
        {
        	$strBuffer .= '</table>' . "\n";
        }
		
		$strBuffer .= "\n" . '</form>' . "\n";
		$strBuffer .= "<script>
			try {
				var frm = document.getElementById('iso_mod_checkout_payment');
			    if (frm.attachEvent) {
			        frm.attachEvent('onsubmit', function(){ document.getElementById('ctrl_confirm').disabled = 'disabled'; });
			    }
			    else {
			        frm.addEventListener('submit', function(){ document.getElementById('ctrl_confirm').disabled = 'disabled'; }, true);
			    }
		    }
		    catch (err) {}
		    </script>";
		
						
		// Check for response
		if (is_object($this->objResponse))
		{
			if ($this->objResponse->transaction_status && $this->objResponse->transaction_status == 'approved')
    		{
    			return false;
			}
			else
			{
				$this->handleFailure();
			}
		}
		
		return $this->blnProceed ? false : '<h2>' . $this->label . '</h2>'. $strBuffer;
	}
	
	
	
	public function setPaymentData(&$objOrder, $arrTokens)
	{
		if ($_SESSION['CHECKOUT_DATA']['payeezy']['payment_data'])
		{
			\System::log('Storing payment data for Order ID ' . $objOrder->id, __METHOD__, TL_GENERAL);
			
			\Database::getInstance()->prepare("UPDATE tl_iso_product_collection %s WHERE id=?")
									->set(array('payment_data'=>serialize($_SESSION['CHECKOUT_DATA']['payeezy']['payment_data'])))
									->executeUncached($objOrder->id);
		}
	}
	
	
	/**
	 * Generate payment authorization form
	 * NOTE:  Will always AUTH_ONLY at this step for PCI Compliance. Capture will take place at process step.
	 *
	 * @access	public
	 * @param	object
	 * @return	string
	 */
	public function paymentForm($objModule)
	{
		if ($this->override_formaction)
		{		
			$strStep = \Haste\Input\Input::getAutoItem('step');
			$arrSteps = is_array($GLOBALS['ISO_CHECKOUT_STEPS_PASS']) ? $GLOBALS['ISO_CHECKOUT_STEPS_PASS'] : array();
			
			if (!in_array($strStep, $arrSteps))
			{
				$objOrder = Order::findOneBy('source_collection_id', Isotope::getCart()->id);
				$strBuffer = $this->getCreditCardForm($objModule, $objOrder);
			}
			
			return '<h2>' . $this->label . '</h2>'. $strBuffer;
		}
		
		return '';
	}
    
    
    
    /**
     * Generate a form for use in a Direct Post implementation.
     *
	 * @access	protected
     * @param	boolean
     * @return	string
     */
    protected function getCreditCardForm(&$objModule, $objOrder)
    {
        $time = time();
		$this->strFormId = $this->override_formaction ? $objModule->getFormId() : $this->strFormId;

		$objTemplate = new \FrontendTemplate($this->strTemplate);
		
        $strBuffer = \Input::get('response_code') == '1' ? '<p class="error message">' . $GLOBALS['ISO_LANG']['MSC']['authnet_dpm_locked'] . '</p>' : '';
        $objTemplate->tableless = $this->tableless;
        
        if (!$this->tableless)
        {
        	$strBuffer .= '<table class="ccform">' . "\n";
        }
        
        // Get credit card types
        $arrCCFinal = array();
       	$arrCCTypes = deserialize($this->allowed_cc_types);
       	
       	foreach ($arrCCTypes as $strCCType)
       	{
	       	$arrCCFinal[strtoupper(substr($strCCType, 0, 1))] = $GLOBALS['TL_LANG']['CCT'][$strCCType]; // I don't think this is right...
       	}

		$intStartYear = (integer)date('Y', time()); //2-digit year

		//Build years array - Going forward 7 years
		for ($i = 0; $i <= 7; $i++)
		{
			$arrYears[] = (string)$intStartYear+$i;
		}
		
		// Get billing data to include on form
		$arrBillingInfo = $objOrder && $objOrder->getBillingAddress() ? $objOrder->getBillingAddress()->row() : Isotope::getCart()->getBillingAddress()->row();
		
		//Build form fields
		$arrFields = array
		(
			'x_card_num'	=> array
			(
				'label'				=> &$GLOBALS['TL_LANG']['MSC']['cc_num'],
				'inputType'			=> 'text',
				'eval'				=> array('mandatory'=>true, 'tableless'=>true),
			),
			'x_card_type' 		=> array
			(
				'label'				=> &$GLOBALS['TL_LANG']['MSC']['cc_type'],
				'inputType'			=> 'select',
				'options'			=> $arrCCFinal,
				'eval'				=> array('mandatory'=>true, 'tableless'=>true),
			),
			'card_expirationMonth' => array
			(
				'label'			=> &$GLOBALS['TL_LANG']['MSC']['cc_exp_month'],
				'inputType'		=> 'select',
				'options'		=> array('01','02','03','04','05','06','07','08','09','10','11','12'),
				'eval'			=> array('mandatory'=>true, 'tableless'=>true, 'includeBlankOption'=>true)
			),
			'card_expirationYear'  => array
			(
				'label'			=> &$GLOBALS['TL_LANG']['MSC']['cc_exp_year'],
				'inputType'		=> 'select',
				'options'		=> $arrYears,
				'eval'			=> array('mandatory'=>true, 'includeBlankOption'=>true)
			),
			'x_exp_date' => array
			(
				'inputType'		=> 'hidden',
				'value'			=> ''
			),
		);

		//if ($this->requireCCV)
		///{
			$arrFields['x_card_code'] = array
			(
				'label'			=> &$GLOBALS['TL_LANG']['MSC']['cc_ccv'],
				'inputType'		=> 'text',
				'eval'			=> array('mandatory'=>true, 'class'=>'ccv')
			);
		//}
		
		$arrParsed = array();
		$blnSubmit = true;
		$intSelectedPayment = intval(\Input::post('PaymentMethod') ?: $this->objCart->getPaymentMethod());
		
		foreach ($arrFields as $field => $arrData )
		{
			$strClass = $GLOBALS['TL_FFL'][$arrData['inputType']];

			// Continue if the class is not defined
			if (!class_exists($strClass))
			{
				continue;
			}
			
			$objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $field));
			if($arrData['value'])
			{
				$objWidget->value = $arrData['value'];
			}
			$objWidget->tableless = $this->tableless;
			
			//Handle form submit
			if( \Input::post('FORM_SUBMIT') == $this->strFormId && $intSelectedPayment == $this->id && !strlen(\Input::post('previousStep')))
			{
				$objWidget->validate();
				if($objWidget->hasErrors())
				{
					$blnSubmit = false;
					$objModule->doNotSubmit = true;
				}
			}
			
			// Give the template plenty of ways to output the fields
			$strParsed = $objWidget->parse();
			$strBuffer .= $strParsed;
			$arrParsed[$field] = $strParsed;
			$objTemplate->{'field_'.$field} = $strParsed;
		}
		
		if (!$this->tableless)
        {
        	$strBuffer .= '</table>' . "\n";
        }
        
        //Process the data
        if($blnSubmit && \Input::post('FORM_SUBMIT') == $this->strFormId && $intSelectedPayment == $this->id && !strlen(\Input::post('previousStep')))
        { 
	        $this->sendPayeezyRequest($objModule, $objOrder);
	        
			// Check for response
			if (is_object($this->objResponse))
			{
				if ($this->objResponse->transaction_status && $this->objResponse->transaction_status == 'approved')
	    		{
	                Isotope::getCart()->setPaymentMethod($this);
	    			Checkout::redirectToStep('process', $objOrder ?: Isotope::getCart());
				}
			}
        }
        
        $objTemplate->id 			= $this->id;
        $objTemplate->requireCCV 	= true;//$this->requireCCV;
        $objTemplate->parsed 		= $strBuffer;
		$objTemplate->fields 		= $arrParsed;
       	$objTemplate->cardTypes 	= $arrCCFinal;
		return $objTemplate->parse();

    }
    
    
    /**
     * Send request to Payeezy
     *
	 * @access	protected
     * @return	void
     */
    protected function sendPayeezyRequest($objModule, $objOrder)
    {
    	// Get billing data to include on form
    	$objCollection = $objOrder ?: Isotope::getCart();
		$arrBillingInfo = $objCollection->getBillingAddress()->row();
		$arrSubdivision = explode('-', $arrBillingInfo['subdivision']);
		
		
        // API creds
        $objPayeezy = new \Payeezy();
	    $objPayeezy->setApiKey($this->payeezy_api_key);
    	$objPayeezy->setApiSecret($this->payeezy_api_secret);
    	$objPayeezy->setMerchantToken($this->payeezy_merchant_token);
    	$objPayeezy->setTokenUrl($this->debug ? "https://api-cert.payeezy.com/v1/transactions/tokens" : "https://api.payeezy.com/v1/transactions/tokens");  
        $objPayeezy->setUrl($this->debug ? "https://api-cert.payeezy.com/v1/transactions" : "https://api.payeezy.com/v1/transactions");
        
		
		// Authorize payload
        $card_holder_name = $card_number = $card_type = $card_cvv = $card_expiry = $currency_code = $merchant_ref="";

        $card_holder_name = $this->processInput($arrBillingInfo['firstname'] . ' ' . $arrBillingInfo['lastname']);
        $card_number = str_replace(' ', '', $this->processInput(\Input::post('x_card_num')));
        $card_type = $this->processInput(static::$arrCardTypes[\Input::post('x_card_type')]);
        $card_cvv = $this->processInput(\Input::post('x_card_code'));
        $card_expiry = $this->processInput(\Input::post('card_expirationMonth') . substr(\Input::post('card_expirationYear'), -2));
        $amount = str_replace('.', '', strval($objCollection->total));
        $currency_code = $this->processInput("USD");
        $merchant_ref = $objOrder ? ("Order Number " . $objCollection->document_number) : ("Cart ID " . $objCollection->id);
        $method = $this->processInput("credit_card");

        $authPayload = array(
            "amount"=> $amount,
            "card_number" => $card_number,
            "card_type" => $card_type,
            "card_holder_name" => $card_holder_name,
            "card_cvv" => $card_cvv,
            "card_expiry" => $card_expiry,
            "merchant_ref" => $merchant_ref,
            "currency_code" => $currency_code,
            "method"=> $method,
        );
		
        $this->objResponse = json_decode($objPayeezy->authorize($authPayload));
        
log_message(static::varDumpToString('Response 1'), 'debugaf.log');
log_message(static::varDumpToString($this->objResponse), 'debugaf.log');
        
        // Auth only
        if ($this->payeezy_auth_capture == 'AUTH_ONLY')
        {
	        if ($this->objResponse && $this->objResponse->transaction_status && strtolower($this->objResponse->transaction_status) == 'approved')
	        {
		        $this->blnProceed = true;
	        }
	        else 
	        {
		    	$objModule->doNotSubmit = true;
	        }
        }
        
        // Auth and capture
        else
        {
	        if ($this->objResponse && $this->objResponse->transaction_status && strtolower($this->objResponse->transaction_status) == 'approved')
	        {
	        	// Do capture - todo: move this to a priorAuthCapture method
		        $transaction_type = $merchant_ref= $currency_code = "";
		
		        $transaction_id = $this->processInput($this->objResponse->transaction_id);
		        $transaction_tag = $this->processInput($this->objResponse->transaction_tag);
		        $amount = $this->processInput($this->objResponse->amount);
		        $currency_code = $this->processInput("USD");
		        $merchant_ref = $this->processInput($objOrder ? ("Order Number " . $objCollection->document_number) : ("Cart ID " . $objCollection->id));
		        $method = $this->processInput("credit_card");
		
	            $capturePayload = array(
	                "amount"=> $amount,
	                "transaction_tag" => $transaction_tag,
	                "transaction_id" => $transaction_id,
	                "merchant_ref" => $merchant_ref,
	                "currency_code" => $currency_code,
	                "method"=> $method,
	            );
	            
	            $this->objResponse = json_decode($objPayeezy->capture($capturePayload));
        
log_message(static::varDumpToString('Response 2'), 'debugaf.log');
log_message(static::varDumpToString($this->objResponse), 'debugaf.log');
	            
		        if ($this->objResponse && $this->objResponse->transaction_status && strtolower($this->objResponse->transaction_status) == 'approved')
		        {
			        $this->blnProceed = true;
		        }
		        else 
		        {
			    	$objModule->doNotSubmit = true;
		        }
	        }
	        else
	        {
		    	$objModule->doNotSubmit = true;
	        }
        }
        
        
        if ($objModule->doNotSubmit)
        {
	        $this->handleFailure();
        }
		
        
        $arrPaymentData = deserialize($objCollection->payment_data, true);
        $arrNewData = array
        (
        	'original_auth_amt'				=> $objCollection->total,
        	'transaction_amount'			=> $objCollection->total,
        	'transaction_id'				=> $this->objResponse ? $this->objResponse->transaction_id : '',
        	'transaction_tag'				=> $this->objResponse ? $this->objResponse->transaction_tag : '',
        	'transaction_status'			=> $this->objResponse ? $this->objResponse->transaction_status : '',
        	'response_reason_text'			=> $this->objResponse && $this->objResponse->Error && is_array($this->objResponse->Error->messages) && count($this->objResponse->Error->messages) ? $this->objResponse->Error->messages[0]->description : '',
        	'response_reason_code'			=> $this->objResponse && $this->objResponse->Error && is_array($this->objResponse->Error->messages) && count($this->objResponse->Error->messages) ? $this->objResponse->Error->messages[0]->code : '',
        	'transaction_type'				=> $this->objResponse->transaction_type,
        );
		
        $_SESSION['CHECKOUT_DATA']['payeezy']['payment_data'] = array_merge($arrPaymentData, $arrNewData);
        $objCollection->payment_data = array_merge($arrPaymentData, $arrNewData);

        \System::log('Payeezy Response -- Status: '.$arrNewData['transaction_status'].'; Reason code: ' . $arrNewData['response_reason_code'] . '; Reason text: ' . $arrNewData['response_reason_text'], __METHOD__, TL_INFO);
    
    }
    
    
     /**
     * Remove any GET params that might still be in the URL string.
     *
	 * @access	protected
     * @param	string
     * @return	string
     */
    protected function removeGetParams($strURL)
    {
		$getPos = strpos($strURL, '?');	
				
		if ($getPos !== false)
		{
			$strGet = substr($strURL, $getPos);
			$strURL = str_replace($strGet, '', $strURL);
		}
		
    	return $strURL;
    }


	/**
	 * Return allowed CC types
	 *
	 * @access public
	 * @return array
	 */
	public static function getAllowedCCTypes()
	{
		return array('mc', 'visa', 'amex', 'discover', 'jcb', 'diners', 'enroute');
	}
    
    
    /**
     * Handle a failure response
     *
	 * @access	protected
     * @return	void
     */
	protected function handleFailure()
    {
    	if ($this->objResponse && $this->objResponse->Error && is_array($this->objResponse->Error->messages) && count($this->objResponse->Error->messages))
    	{
	    	$_SESSION['ISO_ERROR'][] = $this->objResponse->Error->messages[0]->description;
	    		
    		$strErrMsg = 'Payeezy error - Response code: ' . $this->objResponse->Error->messages[0]->code;
    		$strErrMsg .= ' -- Message from Payeezy: ' . $this->objResponse->Error->messages[0]->description;
    		$strErrMsg .= ' -- Trans. Status: ' . $this->objResponse->transaction_status;
    		$strErrMsg .= ' -- Trans. Type: ' . $this->objResponse->transaction_type;
    		
    		\System::log($strErrMsg, __METHOD__, TL_ERROR);
    	}
    	else
    	{
    		$_SESSION['ISO_ERROR'][] = $GLOBALS['TL_LANG']['MSC']['authorizedotnet']['genericfail'];    		
    	}
    }
  
    
    /**
     * Allow other cart types/checkouts to use the payment module via a Hook
     *
	 * @access 	public
     * @param 	IsotopeModule
     * @return	void
     */
    public function setCart($objModule)
    {
   		// Allow to customize attributes
		if (isset($GLOBALS['ISO_HOOKS']['setCart']) && is_array($GLOBALS['ISO_HOOKS']['setCart']))
		{
			foreach ($GLOBALS['ISO_HOOKS']['setCart'] as $callback)
			{			
				$this->import($callback[0]);
				list($this->objCart, $this->strCartField) = $this->{$callback[0]}->{$callback[1]}($objModule, $this->objCart, $this->strCartField);
			}
		}
	}
	

	/**
	 * Clean data
	 */
    public function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }
	
	
	
	/**
	 * Return a list of order status options
	 * Allowed return values are ($GLOBALS['ISO_ORDER']):
	 * - pending
	 * - processing
	 * - complete
	 * - on_hold
	 * - cancelled
	 *
	 * @access 	public
	 * @return	array
	 */
	public function statusOptions()
	{
		return array('pending', 'processing', 'complete', 'on_hold', 'cancelled');
	}
	
	/**
	 * Capture a previously authorized sale.
	 *
	 * @access protected
	 * @return boolean
	 */
	/*protected function doPriorAuthCapture(&$objOrder)
	{
		$blnReturn = false;
		
		// Get transaction data
		$arrPaymentData = deserialize($objOrder->payment_data, true);
        
        // Try the capture
		$sale = new \AuthorizeNetAIM($this->strApiLoginId, $this->strTransKey);
		$sale->setSandbox($this->debug ? true : false);
		$response = $sale->priorAuthCapture($arrPaymentData['transaction_id'] ?: $arrPaymentData['transaction-id'], $objOrder->getTotal());
		
		if ($response->approved)
		{
			$objPaidStatus = OrderStatus::findOneByPaid('1');
			$objOrder->updateOrderStatus($objPaidStatus->id);
			
			$this->strStatus = '1';
			$this->strReason = 'Success!';
			$blnReturn = true;
			
			if (isset($arrPaymentData['reason']))
				unset($arrPaymentData['reason']);
		}
		else
		{
			$objOrder->updateOrderStatus(Isotope::getConfig()->orderstatus_error);
			
			$this->strStatus = $response->response_code;
			$this->strReason = $response->response_reason_text;
			
			// Store the reason in the Order's payment data
			$arrPaymentData['reason'] = $response->response_reason_text;
		}
			
		$objOrder->payment_data = serialize($arrPaymentData);
		$objOrder->save();
		
		return $blnReturn;
	}*/

	/**
	 * Use output buffer to var dump to a string
	 * 
	 * @param	string
	 * @return	string 
	 */
	public static function varDumpToString($var)
	{
		ob_start();
		var_dump($var);
		$result = ob_get_clean();
		return $result;
	}
}
