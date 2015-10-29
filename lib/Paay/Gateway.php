<?php

class Paay_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'paay_gateway'; // Unique ID for your gateway. e.g. ‘your_gateway’
        $this->has_fields = true; // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
        $this->method_title = 'Credit Card powered by PAAY'; // Title of the payment method shown on the admin page.
        $this->method_description = '<div id="wc_get_started" class="paay"><span class="main"><img class="logo" src="'. paayPluginPath() .'images/paay/paay.jpg" alt="PAAY logo" style="width:25px; height:25px"/>PAAY</span><span>Safe, fast & incredibly simple mobile payments.</span></div>'; // Description for the payment method shown on the admin page.
        $this->title = 'Credit Card powered by PAAY';
        $this->description = 'XXXX';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_paay_gateway', array($this, 'check_paay_gateway_response'));
    }

    public function init_form_fields()
    {
        $strategies = array(
            'always' => 'Always show 3DS',
            'detected' => 'Show 3DS only if FORM has been detected',
            'never' => 'Never show 3DS',
        );

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommercepaay'),
                'type' => 'checkbox',
                'label' => __('Enable PAAY Standard Checkout', 'woocommercepaay'),
                'default' => 'yes'
            ),
            'PAAYButton' => array(
                'type' => 'checkbox',
                'label' => __('Enable PAAY Mobile Wallet Checkout', 'woocommercepaay'),
                'default' => 'yes'
            ),
            'paay_key' => array(
                'title' => __('Merchant "API KEY"', 'woocommercepaay'),
                'type' => 'text',
                'label' => __('Merchant "API KEY"', 'woocommercepaay'),
            ),
            'paay_secret' => array(
                'title' => __('Merchant "API SECRET"', 'woocommercepaay'),
                'type' => 'text',
                'label' => __('Merchant "API SECRET"', 'woocommercepaay'),
            ),
            'paay_3ds_strategy' => array(
                'title' => __('3DS Strategy', 'woocommercepaay'),
                'type' => 'select',
                'options' => $strategies,
            ),
            'paay_host' => array(
                'title' => __('PAAY host', 'woocommercepaay'),
                'type' => 'text',
                'default' => 'https://api.paay.co'
            )
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
            'paay_cvv' => array(
                'label' => 'CVV',
                'required' => true,
            ),
            'paay_expiry_month' => array(
                'label' => 'Expiration month (MM)',
                'required' => true,
            ),
            'paay_expiry_year' => array(
                'label' => 'Expiration year (YYYY)',
                'required' => true,
            ),
            'paay_name_on_card' => array(
                'label' => 'Name on card',
                'placeholder' => 'First Last Name',
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
            switch($field) {
                case 'paay_expiry_month':
                    $form .= $this->createMonthField($field, $value);
                    break;
                case 'paay_expiry_year':
                    $form .= $this->createYearField($field, $value);
                    break;
                default:
                    $form .= '<input type="text" class="input-text" name="'.$field.'" id="'.$field.'" value="'.$value.'" placeholder="'.$placeholder.'" />';
            }
            $form .= '</p>';
        }

        $form .= '</div>';
        $form .= '<script type="text/javascript">window.paay_order_redirect = function(order_url) { window.location.href = order_url; }</script>';
        $form .= $this->getCreditCardLogoScript();

        echo $form;
    }

    public function validate_fields()
    {
        $fields = array(
            'PAN' => array(
                'value' => $_POST['paay_pan'],
                'field' => 'Credit card',
            ),
            'CVV' => array(
                'value' => $_POST['paay_cvv'],
                'field' => 'CVV',
            ),
            'ExpiryMonth' => array(
                'value' => $_POST['paay_expiry_month'],
                'field' => 'Expiry month',
            ),
            'ExpiryYear' => array(
                'value' => $_POST['paay_expiry_year'],
                'field' => 'Expiry year',
            ),
            'Expiry' => array(
                'value' => $_POST['paay_expiry_month'].'-'.$_POST['paay_expiry_year'],
                'field' => 'Expiration date'
            ),
            'NameOnCard' => array(
                'value' => $_POST['paay_name_on_card'],
                'field' => 'Name on card',
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
        $apiClient = new Paay_ApiClient(
            $this->settings['paay_host'],
            $this->settings['paay_key'],
            $this->settings['paay_secret'],
            $wc
        );
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

                if ('get' !== strtolower($_SERVER['REQUEST_METHOD'])) {
                    $response = array(
                        'result' => 'success',
                        'messages' => paay_template('3dsframe', array(
                            'order_id'   => $order_id,
                            'is_visible' => $this->settings['paay_3ds_strategy'],
                        )),
                    );
                    echo json_encode($response);
                    exit;
                } else {
                    echo paay_template('3dsframe', array(
                        'order_id'   => $order_id,
                        'is_visible' => $this->settings['paay_3ds_strategy'],
                    ));
                    exit;
                }
            }
        } else {
            echo paay_parse_error($response);
            exit;
        }
    }

    /**
     * Create select month field (nr month (01,02..) => name of month (jan, feb...)
     * @param string $field
     * @param string $value
     * @return string
     */
    private function createMonthField($field, $value)
    {
        $date = new DateTime('01-01-2015');

        $str = "<select class='select' name='{$field}' id='{$field}'>";

        while($date->format('Y') !== '2016'){
            $option = $date->format("m");
            $desc = $date->format('M');

            if($option === $value){
                $str .= "<option value='{$option}' selected='selected'>{$option} - {$desc}</option>";
            } else {
                $str .= "<option value='{$option}'>{$option} - {$desc}</option>";
            }
            $date->modify('+1 month');
        }

        $str .= "</select>";

        return $str;
    }

    /**
     * Create select year field (from this year to 15 years later)
     * @param string $field
     * @param string $value
     * @return string
     */
    private function createYearField($field, $value)
    {
        $date = new DateTime();

        $str = "<select class='select' name='{$field}' id='{$field}'>";

        for($a = 0; $a < 16; $a ++){
            $year = $date->format('Y');
            if($year === $value){
                $str .= "<option value='{$year}' selected='selected'>{$year}</option>";
            } else {
                $str .= "<option value='{$year}'>{$year}</option>";
            }
            $date->modify('+1 year');
        }

        $str .= "</select>";

        return $str;
    }

    /**
     * Add js script to PAAY Gateway
     * Show credit card logo based on PAN number
     * @return string
     */
    private function getCreditCardLogoScript()
    {
        return '<script type="text/javascript">;

            var numberField = jQuery("#paay_pan");
            var imgSrc = "https://plugins.paay.co/images/paay/cards/";
            var amex = imgSrc+"amex.gif";
            var visa = imgSrc+"visa.gif";
            var mc = imgSrc+"mastercard.gif";
            var discover = imgSrc+"discover.jpg";

            numberField.on("input", function(){
                var number = jQuery(this).val();

                if(number.length < 4){
                    numberField.cleanInput();
                    return;
                }

                image = getCardLogo(number);
                if(image !== false){
                    numberField.css({
                        "background" : "white url(\'"+image+"\') no-repeat right 2px",
                        "background-size" : "60px 35px",
                        "padding-right" : "65px"
                    });
                } else {
                    numberField.cleanInput();
                }
            });

            function getCardLogo(number)
            {
                if(number.match(/^4[0-9]{3}.*/i)){
                    return visa;
                }
                if(number.match(/^5[0-9]{3}.*/i)){
                    return mc;
                }
                if(number.match(/^3[47][0-9]{2}.*/i)){
                    return amex;
                }
                if(number.match(/^6011.*/)){
                    return discover;
                }

                return false;
            }

            jQuery.fn.cleanInput = function()
            {
                this.css({
                    "background" : "white none",
                    "padding-right" : "10px"
                });
            }

        </script>';
    }
}
