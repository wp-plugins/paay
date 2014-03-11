<?php

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
    }
    else {
        $result = $apiClient->addTransaction($phoneNumber, $callbackName, $wc->getCart(), $wc->getShipping());
    }
    $response = "paay_app.handle_callback(" . $callbackName .", $result)";

} catch (Exception $e) {
    $response = "Error: " . $e->getMessage();
}

header('content-type:application/javascript');
echo $response;