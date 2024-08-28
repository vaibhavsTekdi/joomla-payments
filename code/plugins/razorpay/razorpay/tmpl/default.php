<?php
/**
 *  @copyright  Copyright (c) 2009-2024 TechJoomla. All rights reserved.
 *  @license    GNU General Public License version 2, or later
 */
defined('_JEXEC') or die('Restricted access');
require_once JPATH_SITE . '/plugins/payment/razorpay/razorpay/src/Razorpay.php';

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>

<?php
// Include Razorpay Checkout.js library
HTMLHelper::script('plugins/payment/razorpay/razorpay/media/js/checkout.js');

// Create a payment button with Checkout.js
?>
<button class="btn btn-primary" onclick="startPayment()"><?php echo Text::_('PLG_RAZORPAY_PAYBUTTON'); ?></button>

<?php
echo '<script>
    function startPayment() {
        var options = {
            key: "' . (isset($vars->api_key) ? $vars->api_key : '') . '",
            amount: ' . (isset($vars->order->amount) ? $vars->order->amount : '') . ',
            currency: "' . (isset($vars->order->currency) ? $vars->order->currency : '') . '",
            name: "' . (isset($vars->name) ? $vars->name : '') . '",
            id: "' . (isset($vars->order->id) ? $vars->order->id : '') . '",
            description: "' . (isset($vars->payment_description) ? $vars->payment_description : '') . '",
            image: "https://www.tekdi.net/images/logo/logo-white-txt.png#joomlaImage://local-images/logo/logo-white-txt.png?width=227&height=197",
            order_id: "' . (isset($vars->order->id) ? $vars->order->id : '') . '",
            theme:
            {
                "color": "#738276"
            },
            callback_url: "' . (isset($vars->return) ? $vars->return : '') . '",
            cancel_url: "' . (isset($vars->return) ? $vars->return : '') . '",
            verify_url: "' . (isset($vars->url) ? $vars->url : '') . '"
        };
        var rzp = new Razorpay(options);
        rzp.open();
    }
</script>';
?>
