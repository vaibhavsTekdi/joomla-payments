<?php
/**
 * @copyright  Copyright (c) 2009-2018 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2, or later
 */
// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/plugins/payment/paypal/paypal/helper.php';
$lang = Factory::getLanguage();
$lang->load('plg_payment_paypal', JPATH_ADMINISTRATOR);

/**
 * PlgPaymentPaypal
 *
 * @package     CPG
 * @subpackage  site
 * @since       2.2
 */
class PlgPaymentPaypal extends CMSPlugin
{
	public $responseStatus;
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

		// Define Payment Status codes in Paypal  And Respective Alias in Framework
		$this->responseStatus = array(
			'Completed' => 'C',
			'Pending' => 'P',
			'Failed' => 'E',
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
			$override = JPATH_BASE . '/' . 'templates' . '/' . $app->getTemplate() . '/html/plugins/' .
			$this->_type . '/' . $this->_name . '/' . 'recurring.php';
		}
		else
		{
			$core_file = dirname(__FILE__) . '/' . $this->_name . '/tmpl/default.php';
			$override = JPATH_BASE . '/' . 'templates' . '/' . $app->getTemplate() . '/html/plugins/' .
			$this->_type . '/' . $this->_name . '/' . 'default.php';
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

	// Constructs the Payment form in case of On Site Payment gateways like Auth.net & constructs the Submit button in case of offsite ones like Paypal

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
		$plgPaymentPaypalHelper = new plgPaymentPaypalHelper;
		$vars->action_url       = $plgPaymentPaypalHelper->buildPaypalUrl();

		// Take this receiver email address from plugin if component not provided it
		if (empty($vars->business))
		{
			$vars->business = $this->params->get('business');
		}

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
		if (empty($vars->business))
		{
			$submitVaues['business'] = $this->params->get('business');
		}
		else
		{
			$submitVaues['business'] = $vars->business;
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

		$submitVaues['order_id']        = $vars->order_id;
		$submitVaues['item_name']     = $vars->item_name;
		$submitVaues['return']        = $vars->return;
		$submitVaues['cancel_return'] = $vars->cancel_return;
		$submitVaues['notify_url']    = $vars->notify_url;
		$submitVaues['currency_code'] = $vars->currency_code;
		$submitVaues['no_note']       = '1';
		$submitVaues['rm']            = '2';
		$submitVaues['amount']        = $vars->amount;
		$submitVaues['country_code']            = $vars->country_code;
		$plgPaymentPaypalHelper       = new plgPaymentPaypalHelper;
		$postaction                   = $plgPaymentPaypalHelper->buildPaypalUrl();

		// Add PayPal URL as action_url/submiturl so that the data can be posted to PayPal - used in layout file of the plugin
		$submitVaues['action_url'] = $postaction;
		$submitVaues['submiturl'] = $postaction;

		$vars = new stdclass;
		$vars = (object) $submitVaues;

		ob_start();
		include dirname(__FILE__) . '/paypal/tmpl/recurring.php';
		$html = ob_get_clean();

		echo $html;
	}

	// ***************************Recurring Payment ***************************

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
	public function onTP_ProcessSubmitRecurring($data, $vars)
	{
		// Take this receiver email address from plugin if component not provided it
		if (empty($vars->business))
		{
			$submitVaues['business'] = $this->params->get('business');
		}
		else
		{
			$submitVaues['business'] = $vars->business;
		}

		// If component does not provide cmd
		if (empty($vars->cmd))
		{
			$submitVaues['cmd'] = '_xclick-subscriptions';
		}
		else
		{
			$submitVaues['cmd'] = $vars->cmd;
		}

		$submitVaues['order_id']        = $vars->order_id;
		$submitVaues['item_name']     = $vars->item_name;
		$submitVaues['return']        = $vars->return;
		$submitVaues['cancel_return'] = $vars->cancel_return;
		$submitVaues['notify_url']    = $vars->notify_url;
		$submitVaues['currency_code'] = $vars->currency_code;
		$submitVaues['no_note']       = '1';
		$submitVaues['rm']            = '2';
		$submitVaues['amount']            = $vars->amount;

		if ($vars->recurring_frequency == 'QUARTERLY')
		{
			$submitVaues['p3'] = 3;
			$submitVaues['t3'] = 'MONTH';
		}
		else
		{
			$submitVaues['p3'] = 1;
			$submitVaues['recurring_frequency'] = $vars->recurring_frequency;
		}

		$submitVaues['recurring_count']     = $vars->recurring_count;
		$submitVaues['src']     = 1;
		$submitVaues['sra']     = 1;

		$plgPaymentPaypalHelper = new plgPaymentPaypalHelper;
		$postaction             = $plgPaymentPaypalHelper->buildPaypalUrl();

		// Add PayPal URL as action_url/submiturl so that the data can be posted to PayPal - used in layout file of the plugin
		$submitVaues['action_url'] = $postaction;
		$submitVaues['submiturl'] = $postaction;

		$vars = new stdclass;
		$vars = (object) $submitVaues;

		ob_start();
		include dirname(__FILE__) . '/paypal/tmpl/recurring.php';
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

		$plgPaymentPaypalHelper = new plgPaymentPaypalHelper;
		$verify = $plgPaymentPaypalHelper->validateIPN($data, $componentName);

		if (!$verify)
		{
			throw new Exception(Text::_('PLG_PAYPAL_PAYMENT_ERR_INVALID_IPN'));
		}

		$payment_status = $this->translateResponse($data['payment_status']);

		$result = array(
			'order_id' => $data['custom'],
			'transaction_id' => $data['txn_id'],
			'subscriber_id' => $data['subscr_id'],
			'buyer_email' => $data['payer_email'],
			'status' => $payment_status,
			'txn_type' => $data['txn_type'],
			'total_paid_amt' => $data['mc_gross'],
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

			$plgPaymentPaypalHelper = new plgPaymentPaypalHelper;
			$log = $plgPaymentPaypalHelper->Storelog($this->_name, $data);
		}
	}
}
