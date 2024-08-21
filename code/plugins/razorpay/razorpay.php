<?php
/**
 * @copyright  Copyright (c) 2009-2024 Techjoomla. All rights reserved.
 * @license    GNU General Public License version 2, or later
 */
// No direct access
defined('_JEXEC') or die('Restricted access');

// Include the Razorpay PHP library
require_once JPATH_SITE . '/plugins/payment/razorpay/razorpay/src/Razorpay.php';

use Razorpay\Api\Api;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Log\Log;

require_once JPATH_SITE . '/plugins/payment/razorpay/razorpay/helper.php';
$lang = Factory::getLanguage();
$lang->load('plg_payment_razorpay', JPATH_ADMINISTRATOR);

HTMLHelper::script('plugins/payment/razorpay/razorpay/media/js/checkout.js');

/**
 * PlgPaymentRazorpay
 *
 * @package     CPG
 * @subpackage  site
 * @since       2.2
 */
class PlgPaymentRazorpay extends CMSPlugin
{
	public $responseStatus, $apiKeyConfig, $api_key, $api_secret;

	/**
	 * Constructor
	 *
	 * @param   string  &$subject  subject
	 *
	 * @param   string  $config    config
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// Set the language in the class
		$config = Factory::getConfig();
		if ($this->params->get('sandbox', ''))
		{
			$this->api_key = $this->params->get('api_key_test', '');
			$this->api_secret = $this->params->get('api_secret_test', '');
		}
		else
		{
			$this->api_key = $this->params->get('api_key_live', '');
			$this->api_secret = $this->params->get('api_secret_live', '');
		}

		if ($this->api_key && $this->api_secret)
		{
			try
			{
				$this->apiKeyConfig = new Api($this->api_key, $this->api_secret);
			}
			catch(Exception $e)
			{
				$this->apiKeyConfig = null;
				return '';
			}
		}
		else 
		{
			$this->apiKeyConfig = null;
		}

		// Define Payment Status codes in Razorpay  And Respective Alias in Framework
		$this->responseStatus = array(
			'captured' => 'C',
			'Pending' => 'P',
			'failed' => 'E',
			'Denied' => 'D',
			'Refunded' => 'RF',
			'Canceled_Reversal' => 'CRV',
			'Reversed' => 'RV'
		);
	}

	/**
	 * Build Layout path
	 *
	 * @param   string  $layout  Layout name
	 *
	 * @since   2.2
	 *
	 * @return   string  Layout Path
	 */
	public function buildLayoutPath($layout)
	{
		$app = Factory::getApplication();

		if ($layout == 'recurring')
		{
			$core_file = dirname(__FILE__) . '/' . $this->_name . '/tmpl/recurring.php';
		}
		else
		{
			$core_file = dirname(__FILE__) . '/' . $this->_name . '/tmpl/default.php';
			$override = JPATH_BASE . '/' . 'templates' . '/' . $app->getTemplate() . '/html/plugins/' .
			$this->_type . '/' . $this->_name . '/' . 'recurring.php';
		}

		if (File::exists($override))
		{
			return $override;
		}
		else
		{
			return $core_file;
		}
	}

	/**
	 * Builds the layout to be shown, along with hidden fields.
	 *
	 * @param   object  $vars    Data from component
	 * @param   string  $layout  Layout name
	 *
	 * @since   2.2
	 *
	 * @return   string  Layout Path
	 */
	public function buildLayout($vars, $layout = 'default')
	{
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);
		include $layout;
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	// Used to Build List of Payment Gateway in the respective Components

	/**
	 * Builds the layout to be shown, along with hidden fields.
	 *
	 * @param   object  $config  Plugin config
	 *
	 * @since   2.2
	 *
	 * @return   mixed  return plugin config object
	 */
	public function onTP_GetInfo($config)
	{
		if (!in_array($this->_name, $config))
		{
			return;
		}

		$obj       = new stdClass;
		$obj->name = $this->params->get('plugin_name');
		$obj->id   = $this->_name;

		return $obj;
	}

	// Constructs the Payment form in case of On Site Payment gateways like Auth.net & constructs the Submit button in case of offsite ones like Razorpay

	/**
	 * Builds the layout to be shown, along with hidden fields.
	 *
	 * @param   object  $vars  Data from component
	 *
	 * @since   2.2
	 *
	 * @return   string  Layout Path
	 */
	public function onTP_GetHTML($vars)
	{
		if (!$this->apiKeyConfig)
		{
			return '<div class="alert alert-info" role="alert"> '.
				Text::_('PLG_RAZORPAY_WRONG_CREDENTIALS_MESSAFE') .
			'</div>';
		}

		if (is_int($vars->order_id)) {
			$vars->order_id = strval($vars->order_id); // Convert the integer to a string
		}

		try
		{
			$order = $this->apiKeyConfig->order->create([
				'amount' => ($vars->amount) * 100, // amount in paise (100 paise = 1 rupee)
				'currency' => $vars->currency_code,
				'receipt' => $vars->order_id,
				'notes' => [
						'order_id' => $vars->order_id,
						'client' => $vars->client
					]
			]);
		}
		catch(Exception $e)
		{
			return '<div class="alert alert-info" role="alert"> '.
				Text::_('PLG_RAZORPAY_WRONG_CREDENTIALS_MESSAFE') .
			'</div>';
		}

		$vars->order = $order;
		$vars->api_key = $this->api_key;

		// If component does not provide cmd
		if (empty($vars->cmd))
		{
			$vars->cmd = '_xclick';
		}
		// @ get recurring layout Amol
		if (property_exists($vars, 'is_recurring') && $vars->is_recurring == 1)
		{
			$html = $this->buildLayout($vars, 'recurring');
		}
		else
		{
			$html = $this->buildLayout($vars);
		}

		return $html;
	}

	/**
	 * Adds a row for the first time in the db, calls the layout view
	 *
	 * @param   object  $data  Data from component
	 * @param   object  $vars  Component data
	 *
	 * @since   2.2
	 *
	 * @return   object  processeddata
	 */
	public function onTP_ProcessSubmit($data, $vars)
	{
		// Take this receiver email address from plugin if component not provided it
		if (empty($vars->api_key))
		{
			$submitVaues['api_key'] = $this->api_key;
		}
		else
		{
			$submitVaues['api_key'] = $vars->api_key;
		}

		// If component does not provide cmd
		if (empty($vars->cmd))
		{
			$submitVaues['cmd'] = '_xclick';
		}
		else
		{
			$submitVaues['cmd'] = $vars->cmd;
		}

		$submitVaues['order_id']      = $vars->order_id;
		$submitVaues['item_name']     = $vars->item_name;
		$submitVaues['return']        = $vars->return;
		$submitVaues['cancel_return'] = $vars->cancel_return;
		$submitVaues['notify_url']    = $vars->notify_url;
		$submitVaues['currency_code'] = $vars->currency_code;
		$submitVaues['no_note']       = '1';
		$submitVaues['rm']            = '2';
		$submitVaues['amount']        = $vars->amount;
		$submitVaues['country_code']            = $vars->country_code;
		$plgPaymentRazorpayHelper       = new plgPaymentRazorpayHelper;

		// Add Razorpay URL as action_url/submiturl so that the data can be posted to Razorpay - used in layout file of the plugin

		$vars = new stdclass;
		$vars = (object) $submitVaues;

		ob_start();
		include dirname(__FILE__) . '/razorpay/tmpl/recurring.php';
		$html = ob_get_clean();

		echo $html;
	}

	/**
	 * Adds a row for the first time in the db, calls the layout view
	 *
	 * @param   object  $data  Data from component
	 *
	 * @since   2.2
	 *
	 * @return   object  processeddata
	 */
	public function onTP_Processpayment($data)
	{
		$jinput    = Factory::getApplication()->input;
		$componentName = $jinput->get("option", "cpg_");

		$payment_status = $this->translateResponse($data['status']);

		$result = array(
			'order_id' => $data['notes']['order_id'],
			'transaction_id' => $data['id'],
			'subscriber_id' => $data['order_id'],
			'buyer_email' => $data['email'],
			'status' => $payment_status,
			'txn_type' => $data['txn_type'],
			'total_paid_amt' => $data['amount'] / 100,
			'raw_data' => $data,
			'error' => $error
		);

		// Print_r($result);die;
		return $result;
	}

	/**
	 * This function transalate the response got from payment getway
	 *
	 * @param   object  $payment_status  payment_status
	 *
	 * @since   2.2
	 *
	 * @return   string  value
	 */
	public function translateResponse($payment_status)
	{
		foreach ($this->responseStatus as $key => $value)
		{
			if ($key == $payment_status)
			{
				return $value;
			}
		}
	}

	/**
	 * Store log
	 *
	 * @param   array  $data  data.
	 *
	 * @since   2.2
	 * @return  list.
	 */
	public function onTP_Storelog($data)
	{
		$log_write = $this->params->get('log_write', '0');

		if ($log_write == 1)
		{
			$logData["raw_data"] = $data;

			$plgPaymentRazorpayHelper = new plgPaymentRazorpayHelper;
			$log = $plgPaymentRazorpayHelper->Storelog($this->_name, $data);
		}
	}
}