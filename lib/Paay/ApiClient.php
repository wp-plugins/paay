<?php

require __DIR__ . '/Connection.php';
require __DIR__ . '/Exception/ApiException.php';

/**
 * @link http://development.paay.co/api/index.html
 * @link https://api.paay.co/
 */
class Paay_ApiClient
{
    /**
     * @var Paay_Connection
     */
    protected $connection;

    protected $wc;

    public function __construct($host, $key, $secret, Paay_Woocommerce $woocommerce)
    {
        $this->connection = new Paay_Connection($host, $key, $secret);
        $this->wc = $woocommerce;
    }

    private function getShippingCost($shipping)
    {
        $cost = 0;
        if (isset($shipping->cost) && is_numeric($shipping->cost) && $shipping->cost > 0) {
            $cost = $shipping->cost;
        }

        if (isset($shipping->cost_per_order) && is_numeric($shipping->cost_per_order) && $shipping->cost_per_order > 0) {
            $cost = $shipping->cost_per_order;
        }

        return $cost;
    }

    /**
     * Creates PAAY Transaction - if orderId is null,
     * a new WP_Order is created, otherwise existing WP_Order is being
     * used to create transaction
     *
     * @param string $phoneNumber
     */
    public function addTransaction($phoneNumber, $wcShipping, $orderId = null)
    {
        $customer = $this->getCustomerByPhone($phoneNumber);
        if (empty($customer)) {
            return json_encode(array('response' => array('data' => 'Welcome to PAAY! You will get a text on how to download your wallet.')));
        }

        $cart = $this->wc->getCart();
        $cart->calculate_totals();

        $orderId = (null === $orderId) ? $this->wc->createOrder($customer) : $orderId;
        $order = new WC_Order($orderId);

        $thanksPageId = woocommerce_get_page_id('thanks');
        $thanksUrl = get_permalink($thanksPageId);
        $prefix = (false === strpos($thanksUrl, '?')) ? '?' : '&';
        $thanksUrl .= $prefix.'order=' . $orderId . '&key=' . $order->order_key;
        $cartItems = array();

        foreach ($order->get_items() as $item) {
            $product = get_product($item['product_id']);

            $cartItems[] = array(
                'description' => $product->get_title(),
                'quantity' => $item['qty'],
                'unit_price' => round($product->get_price(), 2),
            );
        }

        // Apply tax for items
        if ($cart->tax_total > 0) {
            $cartItems[] = array(
                'description' => 'Tax Total',
                'quantity'    => 1,
                'unit_price'  => $cart->tax_total,
            );
        }

        // Apply coupons
        if (!empty($cart->applied_coupons)) {
            foreach ($cart->applied_coupons as $coupon) {
                if (isset($cart->coupon_discount_amounts[$coupon])) {
                    $cartItems[] = array(
                        'description' => 'Coupon: '.$coupon,
                        'quantity'    => 1,
                        'unit_price'  => (float)('-'.$cart->coupon_discount_amounts[$coupon]),
                    );
                }
            }
        }

        $shippingMethods = array();
        $this->wc->findShippingTaxForState('NY');
        foreach ($customer->Address as $address) {
            foreach ($wcShipping->load_shipping_methods() as $shipping) {
                if ($shipping->enabled == 'no') {
                    continue;
                }
                $order->order_shipping = $shipping->id;

                $shippingMethods[] = array(
                    'address_id' => $address->id,
                    'name'       => $shipping->title,
                    'cost'       => $this->getShippingCost($shipping),
                    'tax'        => number_format((($this->getShippingCost($shipping) * floatval($this->wc->findShippingTaxForState($address->state))) / 100), 2)
                );
            }
        }

        $data = array(
            'phone_number' => $phoneNumber,
            'return_url' => $thanksUrl,
            'signature' => $orderId,
            'cart_items' => base64_encode(json_encode(array(
                'TransactionItem' => $cartItems,
                'ShippingOption' => $shippingMethods,
            ))),
        );


        $request = new Paay_Connection_Request();
        $request->setOperation('addTransaction');
        $request->resource = 'transactions.json';
        $request->body = $data;


        $response = $this->connection->sendRequest($request);
        $result = json_decode($response->body);

        if (isset($result->response) && isset($result->response->message) && (string)$result->response->message!='Success') {
            throw new Paay_Exception_ApiException((string)$result->response->message.': '.(string)$result->response->data);
        }

        add_post_meta($orderId, 'transaction_id', $result->response->data->Transaction->id);

        $result->response->order_id = $orderId;

        return json_encode($result);
    }

    public function addTransactionAsGateway(Paay_Gateway_Order $order)
    {
        $request = new Paay_Connection_Request();
        $request->setOperation('addTransaction');
        $request->resource = 'transactions.json';
        $request->body = $order->getData();

        $response = $this->connection->sendRequest($request);
        $result = json_decode($response->body);

        return json_encode($result);
    }

    public function sendWebAppLink($orderId, $phone)
    {
        if (empty($orderId)) {
            throw new Paay_Exception_ApiException('You must provide order number');
        }

        // $order = new WC_Order($orderId);
        $transactionId = get_post_meta($orderId, 'transaction_id', true);

        if (empty($transactionId)) {
            throw new Paay_Exception_ApiException("Transaction id doeasn't exists");
        }

        $request = new Paay_Connection_Request();
        $request->resource = 'transactions/send-webapp/'.$transactionId.'.json';
        $request->body = array('phone' => $phone);

        $response = $this->connection->sendRequest($request);

        return json_decode($response->body);
    }

    /**
     * @param string $transactionId
     */
    public function checkTransactionStatus($orderId)
    {
        if (empty($orderId)) {
            throw new Paay_Exception_ApiException('You must provide order number');
        }

        // $order = new WC_Order($orderId);
        $transactionId = get_post_meta($orderId, 'transaction_id', true);

        if (empty($transactionId)) {
            throw new Paay_Exception_ApiException("Transaction id doeasn't exists");
        }

        $request = new Paay_Connection_Request();
        $request->resource = 'transactions/'.$transactionId.'.json';
        $request->setOperation('checkTransaction');

        $response = $this->connection->sendRequest($request);

        $result = json_decode($response->body);

        if (isset($result->response) && isset($result->response->message) && (string)$result->response->message!='Success') {
            throw new Paay_Exception_ApiException((string)$result->response->message.': '.(string)$result->response->data);
        }

        $transactionData = $result->response->data;
        $customer = null;
        if ($result->response->data->Transaction->state == 'approved') {
            $customer = $this->getCustomerBy('id', $result->response->data->Address->customer_id);
        }

        $this->wc->handleTransaction($orderId, $transactionData, $customer);

        $result->response->order_id = $orderId;

        return json_encode($result);
    }

    /**
     * @param string $paramName
     * @param string $paramValue
     * @return stdClass
     * @throws Mage_Paay_Exception
     */
    protected function getCustomerBy($paramName, $paramValue)
    {
        $request = new Paay_Connection_Request();
        $request->method = 'GET';
        $getData = array(
        );
        $request->resource = 'customers.json';
        if ($paramName == 'id') {
            $request->resource = 'customers/'.$paramValue.'.json';
        } else {
            $getData[$paramName] = $paramValue;
        }

        if (count($getData)) {
            $getData =  http_build_query($getData);
            $request->resource .= '?' . $getData;
        }

        $response = $this->connection->sendRequest($request);

        $result = json_decode($response->body);

        if (isset($result->response) && isset($result->response->message) && !is_object($result->response->data)) {
            throw new Paay_Exception_ApiException($result->response->message.': '.(string)$result->response->data);
        }

        return ($paramName == 'id') ? $result->response->data : $result->customers[0];
    }

    /**
     * @param string $phoneNumber
     */
    protected function getCustomerByPhone($phoneNumber)
    {
        return $this->getCustomerBy('phone', $phoneNumber);
    }

    /**
     * @param string $email
     */
    protected function getCustomerByEmail($email)
    {
        return $this->getCustomerBy('email', $email);
    }

    /**
     * @param string $customerId
     */
    protected function getCustomerById($customerId)
    {
        return $this->getCustomerBy('id', $customerId);
    }
}
