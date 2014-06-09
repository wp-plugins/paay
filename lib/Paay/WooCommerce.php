<?php

class Paay_WooCommerce
{
    protected $wc;

    public function __construct(Woocommerce $woocommerce)
    {
        $this->wc = $woocommerce;
    }

    public function getCart()
    {
        return $this->wc->cart;
    }

    public function getCheckout()
    {
        return $this->wc->checkout();
    }

    public function getShipping()
    {
        return $this->wc->shipping();
    }

    public function createOrder($paayCustomer)
    {
        $defaultAddress = $paayCustomer->Address[0];

        foreach ($paayCustomer->Address as $address) {
            if($addres->is_default) {
                $defaultAddress = $address;
            }
        }

        $checkout = $this->getCheckout();
        $checkout->posted = array(
            'billing_first_name' => $paayCustomer->Customer->first_name,
            'billing_last_name' => $paayCustomer->Customer->last_name,
            'billing_company' => '',
            'billing_address1' => $defaultAddress->address1,
            'billing_address2' => $defaultAddress->address2,
            'billing_postcode' => $defaultAddress->zip,
            'billing_city' => $defaultAddress->city,
            'billing_state' => $defaultAddress->state,
            'billing_email' => $paayCustomer->Customer->email,
            'billing_phone' => $paayCustomer->Customer->phone,
            'shipping_first_name' => $paayCustomer->Customer->first_name,
            'shipping_last_name' => $paayCustomer->Customer->last_name,
            'shipping_company' => '',
            'shipping_address1' => $defaultAddress->address1,
            'shipping_address2' => $defaultAddress->address2,
            'shipping_postcode' => $defaultAddress->zip,
            'shipping_city' => $defaultAddress->city,
            'shipping_state' => $defaultAddress->state,
            'shipping_email' => $paayCustomer->Customer->email,
            'shipping_phone' => $paayCustomer->Customer->phone,
        );
        $orderId = $checkout->create_order();

        return $orderId;
    }

    public function findOrderByMeta($key, $value)
    {
        global $wpdb;

        $meta = $wpdb->get_results("select * from '" . $wpdb->postmeta .
                           "' where meta_key = '" . $wpdb->escape($key) .
                           "' and meta_value = '" . $wpdb->escape($value) . "'");

        if (is_array($meta) && !empty($meta) && isset($meta[0])) {
            $meta = $meta[0];
        }

        if (is_object($meta)) {
            return $meta->post_id;
        }
        return false;
    }

    public function handleTransaction($orderId, $transactionData, $customer)
    {
        if ($transactionData->Transaction->state == 'pending') {
            return;
        }

        if ('approved' == $transactionData->Transaction->state) {
            $transaction = $transactionData->Transaction;
            $shipping = $transactionData->shippingOption[0];
            $address = $transactionData->Address;

            foreach ($customer->Address as $addr) {
                if ($addr->id == $transaction->address_id) {
                    $address = $addr;
                }
            }

            $order = new WC_Order($orderId);

            if (null !== $address->id) {
                $order->shipping_first_name = $order->billing_first_name;
                $order->shipping_last_name = $order->billing_last_name;
                $order->shipping_address_1 = $address->address1;
                $order->shipping_address_2 = $address->address2;
                $order->shipping_city = $address->city;
                $order->shipping_state = $address->state;
                $order->shipping_postcode = $address->zip;
            }
            $order->shipping_method_title = $shipping->name;
            $order->shipping_method = strtolower(str_replace(' ', '_', $shipping->name));
            $order->shipping = $shipping->cost;
            $order->payment_complete();

            return;
        }

        if ('user_declined' == $transaction->state) {
            $order->cancel_order('PAAY transaction aborted by customer.');
            return;
        }

        throw new InvalidArgumentException('Unsupported transaction state');
    }

    public function generateShippingTaxTable()
    {
        $shippingMethods = $this->getShipping()->load_shipping_methods();
        $enabledShippingMethods = array_map(function($item){
            $item->enabled == 'yes';
        }, $shippingMethods);

        return $enabledShippingMethods;
    }

    public function findShippingTaxForState($state)
    {
        global $wpdb;

        $rate = $wpdb->get_results("select * from " . $wpdb->prefix . "woocommerce_tax_rates " .
            "where tax_rate_state = '" . $wpdb->escape($state) . "' and tax_rate_compound = 0 and " .
            "tax_rate_shipping = 1"
        );

        if (is_array($rate) && !empty($rate) && isset($rate[0])) {
            $rate = $rate[0];
        }

        if (is_object($rate)) {
            return floatval($rate->tax_rate);
        }
        return false;
    }
}