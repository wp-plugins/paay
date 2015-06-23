<?php

class Paay_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'paay_gateway'; // Unique ID for your gateway. e.g. ‘your_gateway’
        // $this->icon – If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
        $this->has_fields = true; // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
        $this->method_title = 'PAAY Gateway'; // Title of the payment method shown on the admin page.
        $this->method_description = '<div id="wc_get_started" class="paay"><span class="main"><img class="logo" src="http://paay.co/images/paay-logo.png" alt="PAAY logo" />PAAY</span><span>Safe, fast & incredibly simple mobile payments.</span></div>'; // Description for the payment method shown on the admin page.
        $this->title = 'PAAY Gateway';
        $this->description = 'XXXX';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_paay_gateway', array($this, 'check_paay_gateway_response'));

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Payment', 'woocommerce'),
                'default' => 'yes'
            ),
        );
    }

    public function check_paay_gateway_response()
    {
        global $woocommerce;
        $params  = $_GET;
        $order = new WC_Order($params['order_id']);
        $order->payment_complete();
        $woocommerce->cart->empty_cart();

        wp_redirect($this->get_return_url($order));
        exit;
    }

    public function payment_fields()
    {
        $params = array();
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $params);
        }

        $fields = array(
            'paay_pan' => array(
                'label' => 'Credit card',
                'required' => true,
            ),
            'paay_cavv' => array(
                'label' => 'CAVV',
                'required' => true,
            ),
            'paay_expiry_month' => array(
                'label' => 'Expiry month (MM)',
                'required' => true,
            ),
            'paay_expiry_year' => array(
                'label' => 'Expiry month (YYYY)',
                'required' => true,
            ),
            'paay_name_on_card' => array(
                'label' => 'Name on card',
                'required' => true,
            ),
            'paay_description' => array(
                'label' => 'Card description',
                'required' => true,
            ),
            'paay_zip' => array(
                'label' => 'ZIP on card',
                'required' => true,
            ),
        );

        $form = '<div style="overflow: hidden;">';
        foreach ($fields as $field => $settings) {
            $required = (true === $settings['required']) ? 'validate-required' : '';
            $placeholder = (isset($settings['placeholder'])) ? $settings['placeholder'] : $settings['label'];
            $value = (isset($params[$field])) ? trim($params[$field]) : '';

            $form .= '<p class="form-row form-row-first '.$required.'" id="'.$field.'_field">';
            $form .= '<label for="'.$field.'" class="">'.$settings['label'].'</label>';
            $form .= '<input type="text" class="input-text" name="'.$field.'" id="'.$field.'" value="'.$value.'" placeholder="'.$placeholder.'" />';
            $form .= '</p>';
        }
        $form .= '</div>';
        $form .= '<script type="text/javascript">window.paay_order_redirect = function(order_url) { window.location.href = order_url; }</script>';

        echo $form;
    }

    public function validate_fields()
    {
        $fields = array(
            'PAN' => array(
                'value' => $_POST['paay_pan'],
                'field' => 'Credit card',
            ),
            'CAVV' => array(
                'value' => $_POST['paay_cavv'],
                'field' => 'CAVV',
            ),
            'ExpiryMonth' => array(
                'value' => $_POST['paay_expiry_month'],
                'field' => 'Expiry month',
            ),
            'ExpiryYear' => array(
                'value' => $_POST['paay_expiry_year'],
                'field' => 'Expiry year',
            ),
            'NameOnCard' => array(
                'value' => $_POST['paay_name_on_card'],
                'field' => 'Name on card',
            ),
            'Description' => array(
                'value' => $_POST['paay_description'],
                'field' => 'Description',
            ),
            'Zip' => array(
                'value' => $_POST['paay_zip'],
                'field' => 'Zip',
            ),
        );
        $is_valid = true;
        foreach ($fields as $validator => $data) {
            $class = 'Paay_Validator_'.$validator;
            if (class_exists($class)) {
                $v = new $class();
                if (false === $v->validate($data['field'], $data['value'])) {
                    $is_valid = false;
                }
            }
            
        }
        
        return $is_valid;
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $wc = new Paay_WooCommerce($woocommerce);
        $apiClient = new Paay_ApiClient(get_option('paay_host'), get_option('paay_key'), get_option('paay_secret'), $wc);
        $order = new WC_Order($order_id);
        $paay_order = new Paay_Gateway_Order($order, $_POST);

        $response = $apiClient->addTransactionAsGateway($paay_order);
        $response = json_decode($response, true);

        if (isset($response['response']) && 'Success' === $response['response']['message']) {
            $data = $response['response']['data'];
            if (!empty($data['Transaction']['id'])) {
                //TRANSACTION PROCESSED
                $order->payment_complete();
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {    //3D Secure Authentication form
                $order->update_status('on-hold', __('Awaiting PAAY payment', 'woocommerce'));
                $order->reduce_order_stock();

                $dir = get_temp_dir().'/3ds/';
                @mkdir($dir, 0777, true);
                file_put_contents($dir.$order_id.'.dat', json_encode($data));

                echo paay_template('3dsframe', array(
                    'order_id'   => $order_id,
                    'is_visible' => get_option('paay_3ds_strategy'),
                ));
                exit;
            }
        } else {
            echo paay_parse_error($response);
            exit;
        }
    }
}
