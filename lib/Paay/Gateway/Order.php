<?php

class Paay_Gateway_Order
{
    private $transaction = array(
        'return_url' => '',
        'CreditCard' => array(
            'number' => '',
            'cvv' => '',
            'expiry_month' => '',
            'expiry_year' => '',
            'name_on_card' => '',
            'description' => '',
            'zip' => '',
        ),
        'Customer' => array(
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'activation_code' => '',
        ),
        'Address' => array(
            'address1' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
        ),
        'ShippingOption' => array(
            'name' => '',
            'cost' => '',
            'tax' => '',
        ),
        'TransactionItem' => array(
            // array(
            //     'description' => '',
            //     'quantity' => '',
            //     'unit_price' => '',
            //     'description' => '',
            // ),
        ),

    );

    public function __construct(\WC_Order $order, $post)
    {
        //Return URL
        $home_url = home_url('/');
        $home_url = add_query_arg('wc-api', 'Paay_Gateway', $home_url);
        $home_url = add_query_arg('order_id', $order->id, $home_url);

        $this->transaction['return_url'] = str_replace('https:', 'http:', $home_url);

        //Credit card
        $this->transaction['CreditCard']['number'] = $post['paay_pan'];
        $this->transaction['CreditCard']['cvv'] = $post['paay_cavv'];
        $this->transaction['CreditCard']['expiry_month'] = $post['paay_expiry_month'];
        $this->transaction['CreditCard']['expiry_year'] = $post['paay_expiry_year'];
        $this->transaction['CreditCard']['name_on_card'] = $post['paay_name_on_card'];
        $this->transaction['CreditCard']['description'] = $post['paay_description'];
        $this->transaction['CreditCard']['zip'] = $post['paay_zip'];

        //Customer
        $this->transaction['Customer']['first_name'] = $order->billing_first_name;
        $this->transaction['Customer']['last_name'] = $order->billing_last_name;
        $this->transaction['Customer']['email'] = $order->billing_email;
        $this->transaction['Customer']['phone'] = $order->billing_phone;
        $this->transaction['Customer']['password'] = rand(0, 1000);
        $this->transaction['Customer']['activation_code'] = rand(0, 1000);

        //Shipping Address
        $this->transaction['Address']['address1'] = $order->shipping_address_1;
        $this->transaction['Address']['address2'] = $order->shipping_address_2;
        $this->transaction['Address']['city'] = $order->shipping_city;
        $this->transaction['Address']['state'] = $order->shipping_state;
        $this->transaction['Address']['zip'] = $order->shipping_postcode;

        //Shipping
        $this->transaction['ShippingOption']['name'] = ('' !== $order->shipping_method_title) ? $order->shipping_method_title : $order->get_shipping_method();
        $this->transaction['ShippingOption']['cost'] = $order->order_shipping;
        $this->transaction['ShippingOption']['tax'] = $order->order_shipping_tax;

        //Items
        $items = $order->get_items();
        foreach ($items as $item) {
            $this->transaction['TransactionItem'][] = array(
                'description' => $item['name'],
                'quantity' => $item['qty'],
                'unit_price' => round(($item['line_subtotal'] / $item['qty']), 2),
            );
        }
    }

    public function getData()
    {
        return $this->transaction;
    }
}
