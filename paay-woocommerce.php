<?php
/*
Plugin Name: PAAY for WooCommerce
Plugin URI: http://www.paay.co/contact/
Description: Support for PAAY payments in WooCommerce
Version: 0.25
Requires at least: 4.0
Depends: WooCommerce
Tested up to: 4.2.2
Author: PAAY
Author URI: http://www.paay.co/
License: GPL2
*/

//Test for WooCommerce
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    return;
}

require_once 'lib/Paay/Gateway/Order.php';
require_once 'lib/Paay/ApiClient.php';
require_once 'lib/Paay/WooCommerce.php';
require_once 'lib/Paay/Validator/AbstractValidator.php';
require_once 'lib/Paay/Validator/PAN.php';
require_once 'lib/Paay/Validator/CVV.php';
require_once 'lib/Paay/Validator/Zip.php';
require_once 'lib/Paay/Validator/ExpiryMonth.php';
require_once 'lib/Paay/Validator/ExpiryYear.php';
require_once 'lib/Paay/Validator/Expiry.php';
require_once 'lib/Paay/Validator/NameOnCard.php';
require_once 'simple_html_dom.php';

add_action('woocommerce_proceed_to_checkout', 'paay_checkout');

add_action('plugins_loaded', 'init_paay_gateway_class');
add_filter('woocommerce_payment_gateways', 'add_paay_gateway_class');
add_action('admin_head', 'paay_gateway_admin_css');

add_action('init', 'paay_handler');
add_action('init', 'paay_3ds_form');

wp_enqueue_style('paay', '//plugins.paay.co/css/paay.css');
wp_enqueue_script('paay', '//plugins.paay.co/js/paay_new.js', array(), false, true);

add_action('woocommerce_thankyou', 'paay_foo');

function isSecure()
{
  return
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $_SERVER['SERVER_PORT'] == 443;
}

function paay_foo($order_id)
{
    $prefix = isSecure() ? 'https:' : 'http:';
    echo '<script type="text/javascript">if (typeof(window.parent.paay_order_redirect) == \'function\') {window.parent.paay_order_redirect(window.location.href.replace(/^http[s]?:/, \''.$prefix.'\'));}</script>';
}

function paay_3ds_form()
{
    if ('paay-3ds-form' !== @$_GET['paay-module']) {
        return;
    }

    $gateway = new Paay_Gateway();
    $data = file_get_contents(get_temp_dir().'/3ds/'.@$_GET['order'].'.dat');
    $data = json_decode($data, true);
    $is_visible = $gateway->settings['paay_3ds_strategy'];
    if ('always' === $is_visible) {
        $data['is_form_visible'] = true;
    } elseif ('never' === $is_visible) {
        $data['is_form_visible'] = false;
    }

    if ('get' !== strtolower($_SERVER['REQUEST_METHOD'])) {
        $result = array(
            'result' => 'success',
            'messages' => paay_template('3dsautosubmit', $data),
        );
        echo json_encode($result);
        exit;
    } else {
        echo paay_template('3dsautosubmit', $data);
        exit;
    }
}

function paayPluginPath()
{
    $pluginUrl = plugins_url('/paay-woocommerce.php', __FILE__);
    return preg_replace('/paay\-woocommerce\.php/','', $pluginUrl);
}

function paay_gateway_admin_css()
{
    echo '<style type="text/css">'. file_get_contents(dirname(__FILE__).'/css/admin_woo_paay.css') .'<style>';
}

function add_paay_gateway_class($methods)
{
    $methods[] = 'Paay_Gateway';

    return $methods;
}

function init_paay_gateway_class()
{
    if (!class_exists('Paay_Gateway')) {
        require dirname(__FILE__).'/lib/Paay/Gateway.php';
    }
}

function paay_checkout()
{
if ('get' !== strtolower($_SERVER['REQUEST_METHOD'])) {
  return;
}
    $gateway = new Paay_Gateway();
    $button = $gateway->settings['PAAYButton'] == "yes";

    echo paay_template('paay_button', $var = array('visible' => $button));
}

function paay_wc()
{
    static $paay_wc = null;

    if (null === $paay_wc) {
        global $woocommerce;
        $paay_wc = new Paay_WooCommerce($woocommerce);
    }

    return $paay_wc;
}

function paay_api()
{
    static $api = null;
    $gateway = new Paay_Gateway();

    if (null === $api) {
        $api = new Paay_ApiClient(
            $gateway->settings['paay_host'],
            $gateway->settings['paay_key'],
            $gateway->settings['paay_secret'],
            paay_wc()
        );
    }

    return $api;
}

function paay_createTransactionHandler()
{
    try {
        $result = paay_api()->addTransaction($_GET['telephone'], paay_wc()->getShipping(), null);
        $response = 'paayWoo.api.createTransactionCallback('.$result.')';

        return $response;
    } catch (\Exception $e) {
        return 'paayWoo.api.error("'.$e->getMessage().'")';
    }
}

function paay_cancelTransactionHandler()
{
    try {
        paay_api()->declineTransaction($_GET['order_id']);
        $response = 'return true';

        return $response;
    } catch (\Exception $e) {
        return 'paayWoo.api.error("'.$e->getMessage().'")';
    }
}

function paay_awaitingApprovalHandler()
{
    try {
        $result = paay_api()->checkTransactionStatus($_GET['order_id']);
        $r = json_decode($result);
        if (
            !isset($r->response->data->Transaction->state) ||
            !isset($r->response->data->Transaction->signature) ||
            !isset($r->response->data->Transaction->return_url)
        ) {
            $response = array('error' => true);
        } else {
            $response = array(
                'order_id'   => $r->response->data->Transaction->signature,
                'state'      => $r->response->data->Transaction->state,
                'return_url' => $r->response->data->Transaction->return_url,
            );
        }

        return sprintf('paayWoo.api.awaitingApprovalCallback(%s)', json_encode($response));
    } catch (\Exception $e) {
        return 'paayWoo.api.error("'.$e->getMessage().'")';
    }
}

/**
 * Old - to remove
 */
function paay_sendWebAppLinkHandler()
{
    try {
        paay_api()->sendWebAppLink($_GET['order_id'], $_GET['telephone']);

        return 'return true;';
    } catch (\Exception $e) {
        return 'paayWoo.api.error("'.$e->getMessage().'")';
    }
}

function paay_approveWithout3dsHandler()
{
    try {
        global $woocommerce;
        $order = new WC_Order($_GET['order']);
        $gateway = new Paay_Gateway();

        $result = paay_api()->merchantApproveTransaction($_GET['order']);
        $response = json_decode($result, true);

        if (isset($response['response']) && 'Success' === $response['response']['message']) {
            $data = $response['response']['data'];
            if (!empty($data['transaction_id'])) {
                $order->payment_complete();
                $woocommerce->cart->empty_cart();

                return sprintf('paayWoo.redirect("%s");', $gateway->get_return_url($order));
            }
        }

        return 'paayWoo.api.error("Failed to approve the transaction")';
    } catch (\Exception $e) {
        return 'paayWoo.api.error("'.$e->getMessage().'")';
    }
}

function paay_handler()
{
    $module = trim(@$_GET['paay-module']);

    if (!in_array($module, array('createTransaction', 'cancelTransaction', 'awaitingApproval', 'sendWebAppLink', 'approveWithout3ds'))) {
        return;
    }

    $module = 'paay_'.$module.'Handler';
    $response = $module();
    header('content-type:application/javascript');
    echo $response;
    exit;
}

function paay_template($template, $var = array())
{
    ob_start();
    include dirname(__FILE__).'/templates/'.$template.'.php';
    $contents = ob_get_clean();

    return $contents;
}

function paay_box($type = 'success', $content = '', $info)
{
    return paay_template('paay_box', array(
        'type'      => $type,
        'content'   => $content,
        'info'      => $info
    ));
}

function paay_parse_error($response)
{
    $fid = (isset($response['response']['fid']) && !empty($response['response']['fid'])) ? $response['response']['fid'] : '';
    $message = "Could not create a transaction. Please try again later.";

    return paay_box('error', $message, $fid);
}
