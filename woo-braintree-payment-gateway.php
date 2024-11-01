<?php
/**
 * Plugin Name:       WooCommerce Braintree Payment Gateway Integration
 * Plugin URI:        http://www.multidots.com/
 * Description:       WooCommerce Braintree Payment Gateway Integration allows you to accept payments on your Woocommerce store with your Braintree merchant account.
 * Version:           1.2.8
 * Author:            Multidots
 * Author URI:        http://www.multidots.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-braintree-payment-gateway
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


/**
 * Begins execution of the plugin.
 */
add_action('plugins_loaded', 'init_woo_braintree_payment_gateway');
register_activation_hook(__FILE__, 'activate_woo_braintree_payment_gateway');
register_deactivation_hook(__FILE__, 'deactivate_woo_braintree_payment_gateway');

function init_woo_braintree_payment_gateway() {

    /**
     * Tell WooCommerce that Braintree class exists 
     */
    function add_woo_braintree_payment_gateway($methods) {
        $methods[] = 'Woo_Braintree_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_woo_braintree_payment_gateway');



    if (!class_exists('WC_Payment_Gateway'))
        return;

    /**
     * Braintree gateway class
     */
    class Woo_Braintree_Payment_Gateway extends WC_Payment_Gateway {

        /**
         * Constructor
         */
        public function __construct() {
            $this->id = 'woo_braintree_payment_gateway';
            $this->icon = apply_filters('woocommerce_braintree_icon', plugins_url('images/cards.png', __FILE__));
            $this->has_fields = true;
            $this->method_title = ' Woo Braintree Payment Gateway ';
            $this->method_description = 'Woo Braintree Payment Gateway authorizes credit card payments and processes them securely with your merchant account.';
            $this->supports = array('products', 'refunds');
            // Load the form fields
            $this->init_form_fields();
            // Load the settings
            $this->init_settings();
            // Get setting values
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->sandbox = $this->get_option('sandbox');
            $this->environment = $this->sandbox == 'no' ? 'production' : 'sandbox';
            $this->merchant_id = $this->sandbox == 'no' ? $this->get_option('merchant_id') : $this->get_option('sandbox_merchant_id');
            $this->private_key = $this->sandbox == 'no' ? $this->get_option('private_key') : $this->get_option('sandbox_private_key');
            $this->public_key = $this->sandbox == 'no' ? $this->get_option('public_key') : $this->get_option('sandbox_public_key');
            $this->cse_key = $this->sandbox == 'no' ? $this->get_option('cse_key') : $this->get_option('sandbox_cse_key');
            $this->debug = isset($this->settings['debug']) ? $this->settings['debug'] : 'no';
            // Hooks

            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            add_action('admin_notices', array($this, 'checks'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));



            //add_action('woocommerce_after_checkout_validation', 'rei_after_checkout_validation');
        }

        /* function rei_after_checkout_validation( $posted ) {
          if (empty($_POST['captcha'])) {
          wc_add_notice( __( "Captcha is empty!", 'woocommerce' ), 'error' );
          }
          } */

        /**
         * Admin Panel Options
         */
        public function admin_options() {

            global $wpdb;
            ?>
            <style type="text/css">
                #bpg_dialog {width:500px; font-size:15px; font-weight:bold;}
                #bpg_dialog p {font-size:15px; font-weight:bold;}
                .ui-dialog-titlebar-close:before { display:none;}
                .free_plugin {
                    margin-bottom: 20px;
                }

                .paid_plugin {
                    margin-bottom: 20px;
                }

                .paid_plugin h3 {
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 20px;
                }

                .free_plugin h3 {
                    padding-bottom: 20px;
                    border-bottom: 1px solid #ccc;
                }
            </style>

            <script type="text/javascript">

                jQuery(document).ready(function() {

                });
            </script>
            <h3><?php //_e('Woo Braintree Payment Gateway', 'woo-braintree-payment-gateway');   ?></h3>
            <p><?php _e('Woo Braintree Payment Gateway authorizes credit card payments and processes them securely with your merchant account.', 'woo-braintree-payment-gateway'); ?></p>
            <table class="form-table"> 
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        }

        /**
         * Check if SSL is enabled and notify the user
         */
        public function checks() {
            if ($this->enabled == 'no') {
                return;
            }

            // PHP Version
            if (version_compare(phpversion(), '5.2.1', '<')) {
                echo '<div class="error"><p>' . sprintf(__('Woo Braintree Payment Gateway Error: Braintree requires PHP 5.2.1 and above. You are using version %s.', 'woo-braintree-payment-gateway'), phpversion()) . '</p></div>';
            }

            // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
            elseif ('no' == get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS')) {
                echo '<div class="error"><p>' . sprintf(__('Woo Braintree Payment Gateway is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure!', 'woo-braintree-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
            }
        }

        /**
         * Check if this gateway is enabled
         */
        public function is_available() {
            if ('yes' != $this->enabled) {
                return false;
            }

            if (!is_ssl() && 'yes' != $this->sandbox) {
                //	return false;
            }

            return true;
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields() {



            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo-braintree-payment-gateway'),
                    'label' => __('Enable Woo Braintree Payment Gateway', 'woo-braintree-payment-gateway'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-braintree-payment-gateway'),
                    'default' => __('Credit card', 'woo-braintree-payment-gateway'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('Description', 'woo-braintree-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woo-braintree-payment-gateway'),
                    'default' => 'Pay securely with your credit card.',
                    'desc_tip' => true
                ),
                'sandbox' => array(
                    'title' => __('Sandbox', 'woo-braintree-payment-gateway'),
                    'label' => __('Enable Sandbox Mode', 'woo-braintree-payment-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woo-braintree-payment-gateway'),
                    'default' => 'yes'
                ),
                'submit_settlement' => array(
                    'title' => __('Enable', 'woo-braintree-payment-gateway'),
                    'label' => __('Submit For Settelment', 'woo-braintree-payment-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),
                'sandbox_merchant_id' => array(
                    'title' => __('Sandbox Merchant ID', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'sandbox_public_key' => array(
                    'title' => __('Sandbox Public Key', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'sandbox_private_key' => array(
                    'title' => __('Sandbox Private Key', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'sandbox_valid_cards' => array(
                    'title' => __('Select Card type', 'woo-braintree-payment-gateway'),
                    'type' => 'multiselect',
                    'default' => '',
                    'options' => array('Visa', 'Mastercard', 'American Express', 'Discover', 'JCB', 'Diner`s', 'Maestro'),
                    'class' => 'wc-enhanced-select'
                ),
                'sandbox_cse_key' => array(
                    'title' => __('Sandbox CSE Key', 'woo-braintree-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'merchant_id' => array(
                    'title' => __('Production Merchant ID', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'public_key' => array(
                    'title' => __('Production Public Key', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'private_key' => array(
                    'title' => __('Production Private Key', 'woo-braintree-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'cse_key' => array(
                    'title' => __('Production CSE Key', 'woo-braintree-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('Get your API keys from your Braintree account.', 'woo-braintree-payment-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'debug' => array(
                    'title' => __('Debug', 'woo-braintree-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging <code>/wp-content/uploads/wc-logs/woo-braintree-payment-gateway-{tag}.log</code>', 'woo-braintree-payment-gateway'),
                    'default' => 'no'
                ),
            );
        }

        /**
         * Initialise Credit Card Payment Form Fields
         */
        public function payment_fields() {
            ?>
            <p><?php echo $this->description; ?></p>
            <fieldset id="braintree-cc-form">
                <p class="form-row form-row-wide">
                    <label for="braintree-card-number"><?php echo __('Card Number', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="braintree-card-number" placeholder="" autocomplete="off" maxlength="20" class="input-text wc-credit-card-form-card-number" id="woo-braintree-payment-gateway-card-number" name='woo-braintree-payment-gateway-card-number'>
                </p>

                <p class="form-row form-row-first braintree-card-expiry">
                    <label for="braintree-card-expiry-month"><?php echo __('Expiry', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <select name="woo-braintree-payment-gateway-card-expiry-month" id="woo-braintree-payment-gateway-card-expiry-month" class="input-text">
                        <option value=""><?php _e('Month', 'woo-braintree-payment-gateway') ?></option>
                        <option value='01'>01</option>
                        <option value='02'>02</option>
                        <option value='03'>03</option>
                        <option value='04'>04</option>
                        <option value='05'>05</option>
                        <option value='06'>06</option>
                        <option value='07'>07</option>
                        <option value='08'>08</option>
                        <option value='09'>09</option>
                        <option value='10'>10</option>
                        <option value='11'>11</option>
                        <option value='12'>12</option>  
                    </select>

                    <select name="woo-braintree-payment-gateway-card-expiry-year" id="woo-braintree-payment-gateway-card-expiry-year" class="input-text">
                        <option value=""><?php _e('Year', 'woo-braintree-payment-gateway') ?></option><?php
                        for ($iYear = date('Y'); $iYear < date('Y') + 21; $iYear++) {
                            echo '<option value="' . $iYear . '">' . $iYear . '</option>';
                        }
                        ?>
                    </select>
                </p>

                <p class="form-row form-row-last">
                    <label for="braintree-card-cvc"><?php echo __('Card Code', 'woo-braintree-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="braintree-card-cvc" placeholder="CVC" autocomplete="off" class="input-text wc-credit-card-form-card-cvc" name ='woo-braintree-payment-gateway-card-cvc' id="woo-braintree-payment-gateway-card-cvc">
                </p>
            </fieldset>
            <?php
        }

        /**
         * Function is responsible for verify card type
         *
         * @param unknown_type $number
         * @return unknown
         */
        public function wc_card_type($number) {
            $number = preg_replace('/[^\d]/', '', $number);
            if (preg_match('/^3[47][0-9]{13}$/', $number)) {
                return '2'; //amex
            } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
                return '3'; //discover
            } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
                return '1'; //master
            } elseif (preg_match('/^([30|36|38]{2})([0-9]{12})$/', $number)) {
                return '5'; //Diner's
            } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
                return '4'; //JCB
            } elseif (preg_match('/^(?:5020|6\\d{3})\\d{12}$/', $number)) {
                return '6'; //maestro
            } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
                return '0'; //visa
            } else {
                return 'Unknown';
            }
        }

        /**
         * Outputs style used for Woo Braintree Payment Gateway Payment fields
         * Outputs scripts used for Woo Braintree Payment Gateway
         */
        public function payment_scripts() {
            if (!is_checkout() || !$this->is_available()) {
                return;
            }
        }

        /**
         * Process the payment
         */
        public function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_id = $order->id;
            require_once( 'includes/Braintree.php' );

            Braintree_Configuration::environment($this->environment);
            Braintree_Configuration::merchantId($this->merchant_id);
            Braintree_Configuration::publicKey($this->public_key);
            Braintree_Configuration::privateKey($this->private_key);

            $get_card_value = get_option('woocommerce_woo_braintree_payment_gateway_settings');
            $card_value = maybe_unserialize($get_card_value);
            $card_array = $card_value['sandbox_valid_cards'];
            if (isset($card_array) && !empty($card_array)) {
                $card_no = $this->wc_card_type($_POST['woo-braintree-payment-gateway-card-number']);
                if (!in_array($card_no, $card_array)) {
                    wc_add_notice(__("This card is not acceptable", 'woocommerce'), 'error');
                    return false;
                }
            }
            $submit_settelment_option = get_option('woocommerce_woo_braintree_payment_gateway_settings');
            $settelment_value = $submit_settelment_option['submit_settlement'];
            $result = Braintree_Transaction::sale(array(
                        "amount" => $order->order_total,
                        'orderId' => $order_id,
                        "creditCard" => array(
                            "number" => sanitize_text_field( $_POST['woo-braintree-payment-gateway-card-number'] ),
                            "cvv" => sanitize_text_field ( $_POST['woo-braintree-payment-gateway-card-cvc'] ),
                            "expirationMonth" => sanitize_text_field( $_POST['woo-braintree-payment-gateway-card-expiry-month'] ),
                            "expirationYear" => sanitize_text_field( $_POST['woo-braintree-payment-gateway-card-expiry-year'])
                        ),
                        'customer' => array(
                            'firstName' => isset($_POST['billing_first_name']) ? sanitize_text_field( $_POST['billing_first_name'] ): '',
                            'lastName' => isset($_POST['billing_last_name']) ? sanitize_text_field ( $_POST['billing_last_name'] ) : '',
                            'company' => isset($_POST['billing_company']) ? sanitize_text_field ( $_POST['billing_company'] ) : '',
                            'phone' => isset($_POST['billing_phone']) ? sanitize_text_field ( $_POST['billing_phone'] ) : '',
                            'email' => isset($_POST['billing_email']) ? sanitize_text_field ( $_POST['billing_email'] ) : ''
                        ),
                        'billing' => array(
                            'firstName' => isset($_POST['billing_first_name']) ? sanitize_text_field ( $_POST['billing_first_name'] ) : '',
                            'lastName' => isset($_POST['billing_last_name']) ? sanitize_text_field ( $_POST['billing_last_name'] ) : '',
                            'company' => isset($_POST['billing_company']) ? sanitize_text_field ( $_POST['billing_company'] ) : '',
                            'streetAddress' => isset($_POST['billing_address_1']) ? sanitize_text_field ( $_POST['billing_address_1'] ) : '',
                            'extendedAddress' => isset($_POST['billing_address_2']) ? sanitize_text_field ( $_POST['billing_address_2'] ) : '',
                            'locality' => isset($_POST['billing_city']) ? sanitize_text_field ( $_POST['billing_city'] ): '',
                            'region' => isset($_POST['billing_state']) ? sanitize_text_field ( $_POST['billing_state'] ) : '',
                            'postalCode' => isset($_POST['billing_postcode']) ? sanitize_text_field ( $_POST['billing_postcode'] ) : '',
                            'countryCodeAlpha2' => isset($_POST['billing_country']) ? sanitize_text_field( $_POST['billing_country'] ) : ''
                        ),
                        'shipping' => array(
                            'firstName' => isset($_POST['billing_first_name']) ? sanitize_text_field ( $_POST['billing_first_name'] ) : '',
                            'lastName' => isset($_POST['billing_last_name']) ? sanitize_text_field ( $_POST['billing_last_name'] ) : '',
                            'company' => isset($_POST['billing_company']) ? sanitize_text_field ( $_POST['billing_company'] ) : '',
                            'streetAddress' => isset(WC()->customer->shipping_address_1) ? sanitize_text_field ( WC()->customer->shipping_address_1 ) : '',
                            'extendedAddress' => isset(WC()->customer->shipping_address_2) ? sanitize_text_field ( WC()->customer->shipping_address_2 ): '',
                            'locality' => isset(WC()->customer->shipping_city) ? sanitize_text_field( WC()->customer->shipping_city ) : '',
                            'region' => isset(WC()->customer->shipping_state) ? sanitize_text_field ( WC()->customer->shipping_state ) : '',
                            'postalCode' => isset(WC()->customer->shipping_postcode) ? sanitize_text_field ( WC()->customer->shipping_postcode ) : '',
                            'countryCodeAlpha2' => isset(WC()->customer->shipping_country) ? sanitize_text_field ( WC()->customer->shipping_country ): ''
                        ),
                        "options" => array(
                            "submitForSettlement" => (empty($settelment_value) || $settelment_value == 'yes') ? true : false
                        )
            ));

            if ($result->success) {

                // Payment complete
                $order->payment_complete($result->transaction->id);

                // Add order note
                $order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'woo-braintree-payment-gateway'), $this->title, $result->transaction->id));

                $checkout_note = array(
                    'ID' => $order_id,
                    'post_excerpt' => isset($_POST['order_comments']) ? sanitize_text_field ( $_POST['order_comments'] ) : '',
                );
                wp_update_post($checkout_note);

                $result_transaction_id = isset($result->transaction->id) ? sanitize_text_field ( $result->transaction->id ) : '';
                update_post_meta($order_id, 'wc_braintree_gateway_transaction_id', $result_transaction_id);

                if (is_user_logged_in()) {
                    $userLogined = wp_get_current_user();
                    update_post_meta($order_id, '_billing_email', isset($userLogined->user_email) ? sanitize_text_field ( $userLogined->user_email ): '');
                    update_post_meta($order_id, '_customer_user', isset($userLogined->ID) ? $userLogined->ID : '');
                } else {

                    $payermail = method_exists($this, 'get_session') ? $this->get_session('payeremail') : '';
                    update_post_meta($order_id, '_billing_email', $payermail);
                }

                $trn_bill_fname = isset($result->transaction->billing['firstName']) ? sanitize_text_field ( $result->transaction->billing['firstName'] ) : '';
                $trn_bill_lname = isset($result->transaction->billing['lastName']) ? sanitize_text_field ( $result->transaction->billing['lastName'] ) : '';

                $fullname = $trn_bill_fname . ' ' . $trn_bill_lname;

                update_post_meta($order_id, '_billing_first_name', isset($result->transaction->billing['firstName']) ? sanitize_text_field ( $result->transaction->billing['firstName'] ) : '');
                update_post_meta($order_id, '_billing_last_name', isset($result->transaction->billing['lastName']) ? sanitize_text_field ( $result->transaction->billing['lastName'] ): '');
                update_post_meta($order_id, '_billing_full_name', isset($fullname) ? $fullname : '');
                update_post_meta($order_id, '_billing_company', isset($result->transaction->billing['company']) ? sanitize_text_field ( $result->transaction->billing['company'] ): '');
                update_post_meta($order_id, '_billing_phone', isset($_POST['billing_phone']) ? sanitize_text_field ( $_POST['billing_phone'] ) : '');
                update_post_meta($order_id, '_billing_address_1', isset($result->transaction->billing['streetAddress']) ? sanitize_text_field ( $result->transaction->billing['streetAddress'] ) : '');
                update_post_meta($order_id, '_billing_address_2', isset($result->transaction->billing['extendedAddress']) ? sanitize_text_field ( $result->transaction->billing['extendedAddress'] ) : '');
                update_post_meta($order_id, '_billing_city', isset($result->transaction->billing['locality']) ? sanitize_text_field( $result->transaction->billing['locality'] ) : '');
                update_post_meta($order_id, '_billing_postcode', isset($result->transaction->billing['postalCode']) ? sanitize_text_field ( $result->transaction->billing['postalCode'] ) : '');
                update_post_meta($order_id, '_billing_country', isset($result->transaction->billing['countryCodeAlpha2']) ? sanitize_text_field( $result->transaction->billing['countryCodeAlpha2'] ) : '');
                update_post_meta($order_id, '_billing_state', isset($result->transaction->billing['region']) ? sanitize_text_field ( $result->transaction->billing['region'] ) : '');
                update_post_meta($order_id, '_customer_user', get_current_user_id());

                update_post_meta($order_id, '_shipping_first_name', isset($result->transaction->shipping['firstName']) ? sanitize_text_field ( $result->transaction->shipping['firstName'] ) : '');
                update_post_meta($order_id, '_shipping_last_name', isset($result->transaction->shipping['lastName']) ? sanitize_text_field ( $result->transaction->shipping['lastName'] ) : '');
                update_post_meta($order_id, '_shipping_full_name', isset($fullname) ? $fullname : '');
                update_post_meta($order_id, '_shipping_company', isset($result->transaction->shipping['company']) ? sanitize_text_field ( $result->transaction->shipping['company'] ) : '');
                update_post_meta($order_id, '_billing_phone', isset($_POST['billing_phone']) ? sanitize_text_field ( $_POST['billing_phone'] ) : '');
                update_post_meta($order_id, '_shipping_address_1', isset($result->transaction->shipping['streetAddress']) ? sanitize_text_field ( $result->transaction->shipping['streetAddress'] ) : '');
                update_post_meta($order_id, '_shipping_address_2', isset($result->transaction->shipping['extendedAddress']) ? sanitize_text_field ( $result->transaction->shipping['extendedAddress'] ) : '');
                update_post_meta($order_id, '_shipping_city', isset($result->transaction->shipping['locality']) ? sanitize_text_field ( $result->transaction->shipping['locality'] ) : '');
                update_post_meta($order_id, '_shipping_postcode', isset($result->transaction->shipping['postalCode']) ? sanitize_text_field( $result->transaction->shipping['postalCode'] ) : '');
                update_post_meta($order_id, '_shipping_country', isset($result->transaction->shipping['countryCodeAlpha2']) ? sanitize_text_field ( $result->transaction->shipping['countryCodeAlpha2'] ) : '');
                update_post_meta($order_id, '_shipping_state', isset($result->transaction->shipping['region']) ? sanitize_text_field ( $result->transaction->shipping['region'] ) : '');

                if (is_user_logged_in()) {
                    $userLogined = wp_get_current_user();
                    $customer_id = $userLogined->ID;
                    update_user_meta($customer_id, 'billing_first_name', isset($result->transaction->billing['firstName']) ? sanitize_text_field ( $result->transaction->billing['firstName'] ) : '');
                    update_user_meta($customer_id, 'billing_last_name', isset($result->transaction->billing['lastName']) ? sanitize_text_field ( $result->transaction->billing['lastName'] ) : '');
                    update_user_meta($customer_id, 'billing_full_name', isset($fullname) ? sanitize_text_field ( $fullname ): '');
                    update_user_meta($customer_id, 'billing_company', isset($result->transaction->billing['company']) ? sanitize_text_field ( $result->transaction->billing['company'] ) : '');
                    update_user_meta($customer_id, 'billing_phone', isset($_POST['billing_phone']) ? sanitize_text_field ( $_POST['billing_phone'] ) : '');
                    update_user_meta($customer_id, 'billing_address_1', isset($result->transaction->billing['streetAddress']) ? sanitize_text_field ( $result->transaction->billing['streetAddress'] ) : '');
                    update_user_meta($customer_id, 'billing_address_2', isset($result->transaction->billing['extendedAddress']) ? sanitize_text_field ( $result->transaction->billing['extendedAddress'] ) : '');
                    update_user_meta($customer_id, 'billing_city', isset($result->transaction->billing['locality']) ? sanitize_text_field ( $result->transaction->billing['locality'] ): '');
                    update_user_meta($customer_id, 'billing_postcode', isset($result->transaction->billing['postalCode']) ? sanitize_text_field ( $result->transaction->billing['postalCode'] ) : '');
                    update_user_meta($customer_id, 'billing_country', isset($result->transaction->billing['countryCodeAlpha2']) ? sanitize_text_field ( $result->transaction->billing['countryCodeAlpha2'] ) : '');
                    update_user_meta($customer_id, 'billing_state', isset($result->transaction->billing['region']) ? sanitize_text_field ( $result->transaction->billing['region'] ) : '');
                    update_user_meta($customer_id, 'customer_user', get_current_user_id());

                    update_user_meta($customer_id, 'shipping_first_name', isset($result->transaction->shipping['firstName']) ? sanitize_text_field ( $result->transaction->shipping['firstName'] ) : '');
                    update_user_meta($customer_id, 'shipping_last_name', isset($result->transaction->shipping['lastName']) ? sanitize_text_field ( $result->transaction->shipping['lastName'] ) : '');
                    update_user_meta($customer_id, 'shipping_full_name', isset($fullname) ? sanitize_text_field( $fullname ): '');
                    update_user_meta($customer_id, 'shipping_company', isset($result->transaction->shipping['company']) ? sanitize_text_field ( $result->transaction->shipping['company'] ): '');
                    update_user_meta($customer_id, 'billing_phone', isset($_POST['billing_phone']) ? sanitize_text_field ( $_POST['billing_phone'] ): '');
                    update_user_meta($customer_id, 'shipping_address_1', isset($result->transaction->shipping['streetAddress']) ? sanitize_text_field ( $result->transaction->shipping['streetAddress'] ) : '');
                    update_user_meta($customer_id, 'shipping_address_2', isset($result->transaction->shipping['extendedAddress']) ? sanitize_text_field ( $result->transaction->shipping['extendedAddress'] ): '');
                    update_user_meta($customer_id, 'shipping_city', isset($result->transaction->shipping['locality']) ? sanitize_text_field ( $result->transaction->shipping['locality'] ) : '');
                    update_user_meta($customer_id, 'shipping_postcode', isset($result->transaction->shipping['postalCode']) ? sanitize_text_field ( $result->transaction->shipping['postalCode'] ): '');
                    update_user_meta($customer_id, 'shipping_country', isset($result->transaction->shipping['countryCodeAlpha2']) ? sanitize_text_field ( $result->transaction->shipping['countryCodeAlpha2'] ) : '');
                    update_user_meta($customer_id, 'shipping_state', isset($result->transaction->shipping['region']) ? sanitize_text_field ( $result->transaction->shipping['region'] ) : '');
                }

                $this->add_log(print_r($result, true));
                // Remove cart
                WC()->cart->empty_cart();
                // Return thank you page redirect

                if (is_ajax()) {
                    $result = array(
                        'redirect' => $this->get_return_url($order),
                        'result' => 'success'
                    );
                    echo json_encode($result);
                    exit;
                } else {
                    exit;
                }
            } else if ($result->transaction) {
                $order->add_order_note(sprintf(__('%s payment declined.<br />Error: %s<br />Code: %s', 'woo-braintree-payment-gateway'), $this->title, $result->message, $result->transaction->processorResponseCode));
                $this->add_log(print_r($result, true));
            } else {
                foreach (($result->errors->deepAll()) as $error) {
                    wc_add_notice("Validation error - " . $error->message, 'error');
                }
                return array(
                    'result' => 'fail',
                    'redirect' => ''
                );
                $this->add_log($error->message);
            }
        }

        //  Process a refund if supported
        public function process_refund($order_id, $amount = null, $reason = '') {
            $order = new WC_Order($order_id);
            require_once( 'includes/Braintree.php' );

            Braintree_Configuration::environment($this->environment);
            Braintree_Configuration::merchantId($this->merchant_id);
            Braintree_Configuration::publicKey($this->public_key);
            Braintree_Configuration::privateKey($this->private_key);
            $transation_id = get_post_meta($order_id, 'wc_braintree_gateway_transaction_id', true);

            $result = Braintree_Transaction::refund($transation_id, $amount);

            if ($result->success) {

                $this->add_log(print_r($result, true));
                $max_remaining_refund = wc_format_decimal($order->get_total() - $amount);
                if (!$max_remaining_refund > 0) {
                    $order->update_status('refunded');
                }

                if (ob_get_length())
                    ob_end_clean();
                return true;
            }else {
                $wc_message = apply_filters('wc_braintree_refund_message', $result->message, $result);
                $this->add_log(print_r($result, true));
                return new WP_Error('wc_braintree_gateway_refund-error', $wc_message);
            }
        }

        /**
         * Use WooCommerce logger if debug is enabled.
         */
        function add_log($message) {
            if ($this->debug == 'yes') {
                if (empty($this->log))
                    $this->log = new WC_Logger();
                $this->log->add('woo_braintree_payment_gateway', $message);
            }
        }

    }

    add_action('wp_ajax_hide_subscribe_bpg', 'hide_subscribe_bpgfn');


    function hide_subscribe_bpgfn() {
        global $wpdb;
        $email_id = sanitize_text_field( $_POST['email_id'] );
        update_option('bpg_plugin_notice_shown', 'true');
    }

}

add_action('admin_init', 'welcome_woo_braintree_payment_screen_do_activation_redirect');

add_action('admin_menu', 'welcome_pages_screen_woo_braintree_payment');
add_action('woocommerce_braintree_payment_about', 'woocommerce_braintree_payment_about');
add_action('admin_print_footer_scripts', 'custom_woocommerce_braintree_payment_gateway_pointers_footer');
add_action('admin_menu', 'welcome_screen_woocommerce_braintree_payment_gateway_remove_menus', 999);

function welcome_woo_braintree_payment_screen_do_activation_redirect() {

    if (!get_transient('_welcome_screen_braintree_payment_gateway_activation_redirect_data')) {
        return;
    }
    // Delete the redirect transient
    delete_transient('_welcome_screen_braintree_payment_gateway_activation_redirect_data');

    // if activating from network, or bulk
    if (is_network_admin() || isset($_GET['activate-multi'])) {
        return;
    }
    // Redirect to extra cost welcome  page
    wp_safe_redirect(add_query_arg(array('page' => 'woo-braintree-payment-gateway&tab=about'), admin_url('index.php')));
}

function welcome_pages_screen_woo_braintree_payment() {
    add_dashboard_page(
            'WooCommerce-Braintree-Payment-Gateway-Integration Dashboard', 'WooCommerce Braintree Payment Gateway Integration Dashboard', 'read', 'woo-braintree-payment-gateway', 'welcome_screen_content_woo_braintree_payment'
    );
}

function welcome_screen_woocommerce_braintree_payment_gateway_remove_menus() {
    remove_submenu_page('index.php', 'woo-braintree-payment-gateway');
}

function welcome_screen_content_woo_braintree_payment() {

    wp_enqueue_style('wp-pointer');
    wp_enqueue_script('wp-pointer');
    ?>
    <style type="text/css"> 
        .free_plugin {margin-bottom: 20px;}.paid_plugin {margin-bottom: 20px;}.paid_plugin h3 {border-bottom: 1px solid #ccc;padding-bottom: 20px;}.free_plugin h3 {padding-bottom: 20px;border-bottom: 1px solid #ccc;}.plug-containter {width: 100%;display: inline-block;margin-left: 20px;}.plug-containter .contain-section {width: 25%;display: inline-block;margin-top: 30px;}
        .plug-containter .contain-section .contain-img {width: 30%;display: inline-block;}
        .plug-containter .contain-section .contain-title {width: 50%;display: inline-block;vertical-align: middle;margin-left: 10px;}.plug-containter .contain-section .contain-title a {text-decoration: none;line-height: 20px;font-weight: bold;}.version_logo_img {position: absolute;right: 0;top: 0;}

    </style>       
    <div class="wrap about-wrap">
        <h1 style="font-size: 2.1em;"><?php printf(__('Welcome to WooCommerce Braintree Payment Gateway Integration', 'woo-braintree-payment-gateway')); ?></h1>

        <div class="about-text woocommerce-about-text">
            <?php
            $message = '';
            printf(__('%s WooCommerce Braintree Payment Gateway Integration allows you to accept payments on your Woocommerce store with your Braintree merchant account.', 'woo-braintree-payment-gateway'), $message);
            ?>
            <img class="version_logo_img" src="<?php echo plugin_dir_url(__FILE__) . 'images/woo-braintree-payment-gateway.png'; ?>">
        </div>

        <?php
        $setting_tabs_wc = apply_filters('woo_braintree_payment_gateway_setting_tab', array("about" => "Overview", "other_plugins" => "Checkout our other plugins"));
        $current_tab_wc = (isset($_GET['tab'])) ? $_GET['tab'] : 'general';
        $aboutpage = isset($_GET['page'])
        ?>
        <h2 id="woo-extra-cost-tab-wrapper" class="nav-tab-wrapper">
            <?php
            foreach ($setting_tabs_wc as $name => $label)
                echo '<a  href="' . home_url('wp-admin/index.php?page=woo-braintree-payment-gateway&tab=' . $name) . '" class="nav-tab ' . ( $current_tab_wc == $name ? 'nav-tab-active' : '' ) . '">' . $label . '</a>';
            ?>
        </h2>

        <?php
        foreach ($setting_tabs_wc as $setting_tabkey_wc => $setting_tabvalue) {
            switch ($setting_tabkey_wc) {
                case $current_tab_wc:
                    do_action('woocommerce_braintree_payment_' . $current_tab_wc);
                    break;
            }
        }
        ?>
        <hr />
        <div class="return-to-dashboard">
            <a href="<?php echo home_url('/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo_braintree_payment_gateway'); ?>"><?php _e('Go to WooCommerce Braintree Payment Gateway Integration Settings', 'woo-braintree-payment-gateway'); ?></a>
        </div>
    </div>


    <?php
}

function woocommerce_braintree_payment_about() {
    $current_user = wp_get_current_user();
    ?>
    <div class="changelog">
        </br>
        <style type="text/css">
            p.braintree_payment_gateway_overview {max-width: 100% !important;margin-left: auto;margin-right: auto;font-size: 15px;line-height: 1.5;}.braintree_payment_gateway_overview_content_ul ul li {margin-left: 3%;list-style: initial;line-height: 23px;}
        </style>  
        <div class="changelog about-integrations">
            <div class="wc-feature feature-section col three-col">
                <div>
                    <p class="braintree_payment_gateway_overview"><?php _e('WooCommerce Braintree Payment Gateway Integration authorizes credit card payments and processes them securely with your merchant account.', 'woo-braintree-payment-gateway'); ?></p>

                    <p class="braintree_payment_gateway_overview"><strong>Plugin Functionality: </strong></p>  
                    <div class="braintree_payment_gateway_overview_content_ul">
                        <ul>
                            <li>Easy to install and configure.</li>
                            <li>Compatible with WordPress/Woocommerce plugins</li>
                            <li>You don't need any extra plugins or scripts to process the Transaction</li>
                            <li>Accepts all major credit cards</li>
                            <li>Refunds functionality available.</li>
                            <li>Tested this plugin on the Braintree sandbox (test) servers to ensure your customers don't have problems paying you.</li>
                        </ul>
                    </div>

                </div>

            </div>
        </div>
    </div>
    <?php

    ?>
    <style type="text/css">
        #bpg_dialog {width:500px; font-size:15px; font-weight:bold;}
        #bpg_dialog p {font-size:15px; font-weight:bold;}
        .free_plugin {
            margin-bottom: 20px;
        }

        .paid_plugin {
            margin-bottom: 20px;
        }

        .paid_plugin h3 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 20px;
        }

        .free_plugin h3 {
            padding-bottom: 20px;
            border-bottom: 1px solid #ccc;
        }
    </style>

    <script type="text/javascript">

        jQuery(document).ready(function() {
        });
    </script>


    <?php
}

function custom_woocommerce_braintree_payment_gateway_pointers_footer() {
    $admin_pointers = custom_woocommerce_braintree_payment_gateway_admin_pointers();
    ?>
    <script type="text/javascript">
        /* <![CDATA[ */
        (function($) {
    <?php
    foreach ($admin_pointers as $pointer => $array) {
        if ($array['active']) {
            ?>
                    $('<?php echo $array['anchor_id']; ?>').pointer({
                        content: '<?php echo $array['content']; ?>',
                        position: {
                            edge: '<?php echo $array['edge']; ?>',
                            align: '<?php echo $array['align']; ?>'
                        },
                        close: function() {
                            $.post(ajaxurl, {
                                pointer: '<?php echo $pointer; ?>',
                                action: 'dismiss-wp-pointer'
                            });
                        }
                    }).pointer('open');
            <?php
        }
    }
    ?>
        })(jQuery);
        /* ]]> */
    </script>
    <?php
}

function custom_woocommerce_braintree_payment_gateway_admin_pointers() {
    $dismissed = explode(',', (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
    $version = '1_0'; // replace all periods in 1.0 with an underscore
    $prefix = 'custom_woocommerce_braintree_payment_gateway_admin_pointers' . $version . '_';

    $new_pointer_content = '<h3>' . __('WooCommerce Braintree Payment Gateway Integration') . '</h3>';
    $new_pointer_content .= '<p>' . __('WooCommerce Braintree Payment Gateway Integration authorizes credit card payments and processes them securely with your merchant account.') . '</p>';

    return array(
        $prefix . 'custom_woocommerce_braintree_payment_gateway_admin_pointers' => array(
            'content' => $new_pointer_content,
            'anchor_id' => '#toplevel_page_woocommerce',
            'edge' => 'left',
            'align' => 'left',
            'active' => (!in_array($prefix . 'custom_woocommerce_braintree_payment_gateway_admin_pointers', $dismissed) )
        )
    );
}

/**
 * Run when plugin is activated
 */
function activate_woo_braintree_payment_gateway() {
    global $wpdb;
    set_transient('_welcome_screen_braintree_payment_gateway_activation_redirect_data', true, 30);
}

/**
 * Run when plugin is deactivate
 */
function deactivate_woo_braintree_payment_gateway() {
    
}
