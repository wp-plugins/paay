<?php
/*
Plugin Name: PAAY for WooCommerce
Plugin URI: http://www.paay.co/contact/
Description: Support for PAAY payments in WooCommerce
Version: 0.12
Requires at least: 3.8
Depends: WooCommerce
Tested up to: 4.1
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
add_action('init', 'paay_3ds_form');

wp_enqueue_style('paay', '//plugins.paay.co/css/paay.css');
wp_enqueue_script('paay', '//plugins.paay.co/js/paay.js', array(), false, true);

add_action('woocommerce_thankyou', 'paay_foo');

function isSecure() {
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
    $data = file_get_contents(get_temp_dir().'/3ds/'.$_GET['order'].'.dat');
    $data = json_decode($data, true);

    echo paay_template('3dsautosubmit', $data);
    exit;
}

function paay_gateway_admin_css()
{
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
.paay-advanced {
    display: none;
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
    if (!class_exists('Paay_Gateway')) {
        require dirname(__FILE__).'/lib/Paay/Gateway.php';
    }
}

function paay_plugin_menu()
{
    add_options_page('PAAY', 'PAAY', 'manage_options', 'paay_options', 'paay_options_page');
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

                <tr>
                    <td colspan="2">
                        Advanced <input type="checkbox" id="paay-advanced-toggle" />
                    </td>
                </tr>
                <tr class="paay-advanced" valign="top">
                    <th scope="row">PAAY host</th>
                    <td><input type="text" name="paay_host" value="<?php $api_host = get_option('paay_host'); echo (empty($api_host)) ? 'https://api.paay.co' : $api_host; ?>" /></td>
                </tr>
            </table>
            <?php submit_button() ?>
        </form>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery('body').on('change', '#paay-advanced-toggle', function(e) {
                    if (jQuery(this).is(':checked')) {
                        jQuery('.paay-advanced').show();
                    } else {
                        jQuery('.paay-advanced').hide();
                    }
                });
            });
        </script>
    </div>
    <?php
}

    function paay_checkout()
    {
        $html = array();
        $html[] = '<div class="paay-button-placeholder"></div>';
        $html[] = '<script type="text/javascript">
                    var PAAY = PAAY || {};
                    PAAY.config = PAAY.config || {};
                    PAAY.config.url = PAAY.config.url || {};
                    if (undefined === PAAY.config.woocommerce) {
                        PAAY.config.url.createTransaction = \'/?page=paay_handler&paay-module=createTransaction\';
                        PAAY.config.url.cancelTransaction = \'/?page=paay_handler&paay-module=cancelTransaction\';
                        PAAY.config.url.awaitingApproval = \'/?page=paay_handler&paay-module=awaitingApproval\';
                        PAAY.config.url.sendWebAppLink = \'/?page=paay_handler&paay-module=sendWebAppLink\';
                        PAAY.config.woocommerce = true;
                    }
                    </script>';
        echo join('', $html);
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

        if (null === $api) {
            $api = new Paay_ApiClient(
                get_option('paay_host'),
                get_option('paay_key'),
                get_option('paay_secret'),
                paay_wc()
            );
        }

        return $api;
    }

    function paay_createTransactionHandler()
    {
        try {
            $result = paay_api()->addTransaction($_GET['telephone'], paay_wc()->getShipping(), null);
            $response = 'PAAY.api.createTransactionCallback('.$result.')';

            return $response;
        } catch (\Exception $e) {
            return 'PAAY.api.error("'.$e->getMessage().'")';
        }
    }

    function paay_cancelTransactionHandler()
    {
        try {
            paay_api()->declineTransaction($_GET['order_id']);
            $response = 'return true';

            return $response;
        } catch (\Exception $e) {
            return 'PAAY.api.error("'.$e->getMessage().'")';
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

            return sprintf('PAAY.api.awaitingApprovalCallback(%s)', json_encode($response));
        } catch (\Exception $e) {
            return 'PAAY.api.error("'.$e->getMessage().'")';
        }
    }

    function paay_sendWebAppLinkHandler()
    {
        try {
            paay_api()->sendWebAppLink($_GET['order_id'], $_GET['telephone']);

            return 'return true;';
        } catch (\Exception $e) {
            return 'PAAY.api.error("'.$e->getMessage().'")';
        }
    }

    function paay_handler()
    {
        $module = trim($_GET['paay-module']);

        if (!in_array($module, array('createTransaction', 'cancelTransaction', 'awaitingApproval', 'sendWebAppLink'))) {
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

    function paay_box($info, $content = '', $type = 'success')
    {
        return paay_template('paay_box', array(
            'type'      => $type,
            'info'      => $info,
            'content'   => $content
        ));
    }

    function paay_parse_form($form_html)
    {
        $data = str_replace("\n", '', $form_html);
        $data = stripcslashes($data);

        $dom = str_get_html($data);
        $form = $dom->find('form', 0);
        $forms = array($form);
        // $forms = $dom->find('form');

        $forms_html = '';
        foreach ($forms as $form) {
            $form->class = '';

            //Remove all noscript tags
            $noscripts = $form->find('noscript');
            if (!empty($noscripts)) {
                foreach ($noscripts as $noscript) {
                    $noscript->outertext = '';
                }
            }

            //Remove existing submits
            $submits = $form->find('input[type="submit"]');
            if (!empty($submits)) {
                foreach ($submits as $submit) {
                    $submit->outertext = '';
                }
            }

            //Remove built in form styles
            //Labels
            $labels = $form->find('label');
            $labels_set = array();
            foreach ($labels as $label) {
                if (!empty($label->for)) {
                    $label->class = '';
                    $labels_set[$label->for] = $label;
                }
            }

            //Inputs
            $inputs = $form->find('input[type!="submit"], select, textarea');
            $inputs_set = array();
            foreach ($inputs as $input) {
                $input->class = '';
                if (!empty($input->id)) {
                    $inputs_set[$input->id] = $input;
                } else {
                    $inputs_set['paay-'.uniqid()] = $input;
                }
            }

            //Connect labels with inputs
            $inputs_html = '<ul>';
            //labelled inputs
            foreach ($labels_set as $key => $label) {
                if (isset($inputs_set[$key])) {
                    $inputs_html .= '<li>'.$label->__toString().$inputs_set[$key]->__toString().'</li>';
                    unset($inputs_set[$key]);
                }
            }
            //inputs without labels
            foreach ($inputs_set as $key => $input) {
                $inputs_html .= '<li>'.$input->__toString().'</li>';
            }
            $inputs_html .= '</ul>';
            $form->innertext = $inputs_html;

            //Add PAAY submit
            $form->innertext = $form->innertext.'<input type="submit" value="Proceed to PAAY" />';

            $forms_html .= $form->__toString();
        }

        $info = 'Every PAAY transaction goes straight to your phone where you can verify and confirm or cancel the order. The only information the merchant sees is the transaction, not your credit card numbers.';

        return paay_box($info, $forms_html);
    }

    function paay_parse_error($response)
    {
        $message = (isset($response['response']['data']) && !empty($response['response']['data'])) ? $response['response']['data'] : 'Transaction processing failed';
        $fid = (isset($response['response']['fid']) && !empty($response['response']['fid'])) ? $response['response']['fid'] : '';

        return paay_box($message.' If it\'s still not working, please contact PAAY and provide this number: '.$fid, '', 'error');
    }
