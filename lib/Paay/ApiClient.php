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

    /**
     * Creates PAAY Transaction - if orderId is null,
     * a new WP_Order is created, otherwise existing WP_Order is being
     * used to create transaction
     *
     * @param string $phoneNumber
     */
    public function addTransaction($phoneNumber, $callbackName, $wcCart, $wcShipping, $orderId = null)
    {
        $addressId = '';
        $customer = $this->getCustomerByPhone($phoneNumber);

        if (isset($customer->Address[0])) {
            $addressId = (string)$customer->Address[0]->id;
        }
        $this->wc->getCart()->calculate_totals();

        $orderId = (null === $orderId) ? $this->wc->createOrder($customer) : $orderId;
        $order = new WC_Order($orderId);

        $thanksPageId = woocommerce_get_page_id('thanks');
        $thanksUrl = get_permalink($thanksPageId);
        $thanksUrl .= '&order=' . $orderId . '&key=' . $order->order_key;
        $cartItems = array();

        foreach($order->get_items() as $item) {
            $product = get_product($item['product_id']);

            $cartItems[] = array(
                'description' => $product->get_title(),
                'quantity' => $item['qty'],
                'unit_price' => $product->get_price(),
            );
        }

        $shippingMethods = array();
        $customer->Address[] = $customer->Address[0];

        $this->wc->findShippingTaxForState('NY');
        foreach($customer->Address as $address) {
            foreach($wcShipping->load_shipping_methods() as $shipping) {
                if($shipping->enabled == 'no') {
                    continue;
                }
                $order->order_shipping = $shipping->id;

                $shippingMethods[] = array(
                    'address_id' => $addressId,
                    'name' => $shipping->title,
                    'cost' => intval($shipping->cost),
                    'tax'  => number_format(intval($shipping->cost) * (1 + floatval($this->wc->findShippingTaxForState($address->state))) / 100, 2)
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

    /**
     * @param string $transactionId
     */
    public function checkTransactionStatus($orderId)
    {
        if (empty($orderId)) {
            throw new Paay_Exception_ApiException('You must provide order number');
        }

        $order = new WC_Order($order_id);
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
        }
        else {
            $getData[$paramName] = $paramValue;
        }

        if (count($getData)) {
            $getData =  http_build_query($getData);
            $request->resource .= '?' . $getData;
        }

        $response = $this->connection->sendRequest($request);

        $result = json_decode($response->body);

        if (isset($result->response) && isset($result->response->message) && !is_object($result->response->data) ) {
            throw new Paay_Exception_ApiException($result->response->message.': '
                .(string)$result->response->data);
        }

        return $result->customers[0];
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