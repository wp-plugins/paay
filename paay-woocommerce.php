<?php
/*
Plugin Name: PAAY for WooCommerce
Plugin URI: http://paay.com/todo
Description: Support for PAAY payments in WooCommerce
Version: 0.1
Author: PAAY
Author URI: http://paay.com
License: GPL2
*/

require_once 'lib/Paay/Gateway/Order.php';
require_once 'lib/Paay/ApiClient.php';
require_once 'lib/Paay/WooCommerce.php';
require_once 'lib/Paay/Validator/AbstractValidator.php';
require_once 'lib/Paay/Validator/PAN.php';
require_once 'lib/Paay/Validator/CAVV.php';
require_once 'lib/Paay/Validator/Zip.php';
require_once 'lib/Paay/Validator/ExpiryMonth.php';
require_once 'lib/Paay/Validator/ExpiryYear.php';
require_once 'lib/Paay/Validator/Description.php';
require_once 'lib/Paay/Validator/NameOnCard.php';
require_once 'simple_html_dom.php';

add_action('woocommerce_proceed_to_checkout', 'paay_checkout');
add_action('admin_init', 'register_paay_settings');
add_action('admin_menu', 'paay_plugin_menu');

add_action('plugins_loaded', 'init_paay_gateway_class');
add_filter('woocommerce_payment_gateways', 'add_paay_gateway_class');
add_action('admin_head', 'paay_gateway_admin_css');

add_action('init', 'paay_handler');

function paay_gateway_admin_css()
{
    global $user_level;
$style = <<< EOT
<style type="text/css">
/*<![CDATA[*/

#wc_get_started.paay {
    background: #f5f5f5;
    padding: 15px;
    overflow: hidden;

    border: 0;
    border-bottom: 1px solid rgb(81, 158, 44);
    background-image: #a6db2b;
    background-image: -moz-linear-gradient(top, #a6db2b 18%, #54bc0f 100%);
    background-image: -webkit-gradient(linear, left top, left bottom, color-stop(18%,#a6db2b), color-stop(100%,#54bc0f));
    background-image: -webkit-linear-gradient(top, #a6db2b 18%,#54bc0f 100%);
    background-image: -o-linear-gradient(top, #a6db2b 18%,#54bc0f 100%);
    background-image: -ms-linear-gradient(top, #a6db2b 18%,#54bc0f 100%);
    background-image: linear-gradient(to bottom, #a6db2b 18%,#54bc0f 100%);
    filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#a6db2b', endColorstr='#54bc0f',GradientType=0 );
}
#wc_get_started span {
    color: #ffffff;
    text-shadow: -1px -1px #A6DB2B;
    -webkit-transition: all ease-in 0.3s;
    -moz-transition: all ease-in 0.3s;
    -o-transition: all ease-in 0.3s;
    transition: all ease-in 0.3s;
}
#wc_get_started.paay .main {
    clear: both;
}
#wc_get_started.paay .main .logo {
    width: 120px;
    float: left;
    margin-right: 20px;
}


/*]]>*/
        </style>
EOT;

        echo $style;
    }

function add_paay_gateway_class($methods)
{
    $methods[] = 'Paay_Gateway';

    return $methods;
}

function init_paay_gateway_class()
{
    if(!class_exists('Paay_Gateway')) {
        require dirname(__FILE__).'/lib/Paay/Gateway.php';
    }
}

function paay_plugin_menu()
{
    add_options_page( 'PAAY', 'PAAY', 'manage_options', 'paay_options', 'paay_options_page' );
}

function register_paay_settings()
{
    register_setting('paay', 'paay_key');
    register_setting('paay', 'paay_secret');
    register_setting('paay', 'paay_host');
}

function paay_options_page()
{
    if (!current_user_can( 'manage_options' )) {
        wp_die(__( 'You do not have sufficient permissions to access this page.' ));
    }
    ?>
    <div class="wrap">
        <h2>PAAY for WooCommerce</h2>

        <form action="options.php" method="post">
            <?php settings_fields( 'paay' ); ?>
            <?php // do_settings('paay'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">PAAY key</th>
                    <td><input type="text" name="paay_key" value="<?php echo get_option('paay_key') ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">PAAY secret</th>
                    <td><input type="text" name="paay_secret" value="<?php echo get_option('paay_secret') ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">PAAY host</th>
                    <td><input type="text" name="paay_host" value="<?php echo get_option('paay_host') ?>" /></td>
                </tr>
            </table>
            <?php submit_button() ?>
        </form>
    </div>
    <?php
}

function paay_checkout()
{
    wp_enqueue_style( 'paay', plugins_url('/css/paay.css', __FILE__));
    wp_enqueue_script( 'paay', plugins_url('/js/paay.js', __FILE__), array(), false, false );
    ?>
    <div id="paay_box">
    <div class="paay_input">
        <div class="paay_text"><?php echo __('Checkout with Paay'); ?></div>
        <input type="text" placeholder="<?php echo __('Mobile number'); ?>" id="paay_phone"/>
        <button type="button" id="paay_button"><?php echo __('Paay'); ?></button>
    </div>
    <div id="paay_overlay" class="no-display">
    <div class="paay_background"></div>
    <div id="paay_status_window">
        <div id="paay_overlay_close_button" class="close_button"></div>
        <div class="paay_logo"></div>
        <div class="white_text dark_shadow_text"><?php echo __('Thank you for choosing Paay.'); ?></div>
        <div class="paay_status_area">
            <span class="right"></span>
            <span class="left"></span>
            <div id="paay_status_bar">
                <span class="right"></span>
                <span class="left"></span>
                <div id="paay_progress"><span class="right"></span><span class="left"></span></div>
            </div>
            <div id="paay_processing_status" class="green_text light_shadow_text"><?php echo __('Waiting for mobile confirmation'); ?></div>
        </div>
        <div id="paay_status_text"><?php echo __('Please check your phone now to approve this payment.'); ?></div>
        <div id="paay_overlay_buttons">
            <button id="paay_cancel_button" type="button" class="green_text"><?php echo __('Cancel'); ?></button>
            <button id="paay_resend_button" type="button">
                <span class="right"></span>
                <span class="left"></span>
                <i class="icon_resend"></i>
                <?php echo __('Resend Alert'); ?>
            </button>
            <button id="paay_help_button" type="button"><?php echo __('Help'); ?></button>
        </div>
        <div class="paay_refresh_notice green_text"><?php echo __('This screen will automatically refresh when payment is confirmed.'); ?></div>
    </div>
</div></div>
<?php
}

function paay_handler()
{
    if (!isset($_GET['page']) || ('paay_handler' !== $_GET['page'])) {
        return;
    }

    global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $woocommerce;

    $wc = new Paay_WooCommerce($woocommerce);

    $phoneNumber = $_GET['telephone'];
    $callbackName = $_GET['cb_name'];

    if (empty($callbackName)) {
        throw new InvalidArgumentException('"cb_name" parameter must be provided');
    }

    $response = '';

    try {
        $apiClient = new Paay_ApiClient(get_option('paay_host'), get_option('paay_key'), get_option('paay_secret'), $wc);

        if (empty($phoneNumber)) {
            $orderId = $_GET['order_id'];
            $result = $apiClient->checkTransactionStatus($orderId);
        } else {
            $result = $apiClient->addTransaction($phoneNumber, $callbackName, $wc->getCart(), $wc->getShipping());
        }
        $response = "paay_app.handle_callback(" . $callbackName .", $result)";

    } catch (Exception $e) {
        $response = "Error: " . $e->getMessage();
    }

    header('content-type:application/javascript');
    echo $response;
    exit;
}
