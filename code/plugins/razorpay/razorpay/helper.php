<?php
/**
 * @copyright  Copyright (c) 2009-2024 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2, or later
 */
defined('_JEXEC') or die(';)');

use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;
use Joomla\CMS\Log\LogEntry;
use Razorpay\Api\Api;

/**
 * PlgPaymentBycheckHelper
 *
 * @package     CPG
 * @subpackage  site
 * @since       2.2
 */
class PlgPaymentRazorpayHelper
{
	/**
	 * Store log
	 *
	 * @param   string  $name     name.
	 *
	 * @param   array   $logdata  data.
	 *
	 * @since   1.0
	 * @return  list.
	 */
	public function Storelog($name, $logdata)
	{
		$options = "{DATE}\t{TIME}\t{USER}\t{DESC}";
		$my      = Factory::getUser();

		if (empty($logdata['JT_CLIENT']))
		{
			$logdata['JT_CLIENT'] = "cpg_";
		}

		Log::addLogger(
			array(
				'text_file' => $logdata['JT_CLIENT'] . '_' . $name . '.php',
				'text_entry_format' => $options
			),
			Log::INFO,
			$logdata['JT_CLIENT']
		);

		$logEntry       = new LogEntry('Transaction added', Log::INFO, $logdata['JT_CLIENT']);
		$logEntry->user = $my->name . '(' . $my->id . ')';
		$logEntry->desc = json_encode($logdata['raw_data']);
		Log::add($logEntry);
	}

	/**
	 * ValidateIPN - Validate the payment detail. (We are thankful to Akeeba Subscriptions Team,
	 * while modifing the plugin according to razorpay security update. https://github.com/razorpay/TLS-update#php
	 * Security update links: https://devblog.razorpay.com/upcoming-security-changes-notice/
	 * https://developer.razorpay.com/docs/classic/ipn/ht_ipn/
	 *
	 * @param   string  $data           data
	 * @param   string  $componentName  Component Name
	 *
	 * @since   2.2
	 *
	 * @return   string  data
	 */
	public function validateIPN($data, $componentName)
	{
		$plugin = PluginHelper::getPlugin('payment', 'razorpay');
		$params = new Registry($plugin->params);
		// Get the payment ID and the signature from the callback

		$api = new Api($params->get('api_key_test', ''), $params->get('api_secret_test', ''));

		$payment_id = $data['id'];
		// Get all headers
		$headers = getallheaders();


		$key = 'razorpay_signature'; // Replace with your header key
		if (array_key_exists($key, $headers)) 
		{
			$razorpay_signature = $headers[$key];
		}

		// Check if a specific header key exists
		$key = 'razorpay-signature'; // Replace with your header key
		if (array_key_exists($key, $headers)) 
		{
			$razorpay_signature = $headers[$key];
		}

		try {
			if ($data['status'] == 'captured')
			{
				$success = true;
			}
			else
			{
				$success = false;
			}
		} catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
			$success = false;
			$data['error'] = 'Razorpay Signature Verification Failed';
		}

		if ($success) {
			// Payment is successful, update your database or perform other actions

			// Fetch the payment details
			$payment = $api->payment->fetch($payment_id);

			// You can access payment details like $payment->amount, $payment->status, etc.
			$amount_paid = $payment->amount / 100; // Convert amount from paise to rupees

			$data['success'] = "Payment Successful! Amount: $amount_paid INR";
		} else {
			// Payment failed, handle accordingly
			$data['failed'] = "Payment Failed! Error: $error";
		}

		$logData = array();
		$logData["JT_CLIENT"] = $componentName;
		$logData["raw_data"] = $data;
		self::Storelog("razorpay", $logData);

		return $success;
	}

	/**
	 * log_ipn_results.
	 *
	 * @param   string  $success  success
	 *
	 * @since   2.2
	 *
	 * @return   string  success
	 */
	public function log_ipn_results($success)
	{
		if (!$this->ipn_log)
		{
			return;
		}

		// Timestamp
		$text = '[' . date('m/d/Y g:i A') . '] - ';

		// Success or failure being logged?
		if ($success)
		{
			$text .= "SUCCESS!\n";
		}
		else
		{
			$text .= 'FAIL: ' . $this->last_error . "\n";
		}

		// Log the POST variables
		$text .= "IPN POST Vars from Razorpay:\n";

		foreach ($this->ipn_data as $key => $value)
		{
			$text .= "$key=$value, ";
		}

		// Log the response from the razorpay server
		$text .= "\nIPN Response from Razorpay Server:\n " . $this->ipn_response;

		// Write to log
		$fp = fopen($this->ipn_log_file, 'a');
		fwrite($fp, $text . "\n\n");
		fclose($fp);
	}
}