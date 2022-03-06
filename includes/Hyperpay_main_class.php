<?php

/**
 * HayperPay main class created to extends from it 
 * when create a new paymentsgateways
 * 
 */
class Hyperpay_main_class extends WC_Payment_Gateway
{

    /**
     * if payments have direct fields on ckeckout page 
     * 
     * @var boolean
     */
    public $has_fields = false;
    protected $loader;

    /**
     * check if user sigined in or not 
     * 
     * @var boolean
     */
    protected $is_registered_user = false;

    /**
     * Mada BlackBins
     * 
     * @var array
     */
    protected $blackBins = [];

    /**
     * supported brands thats will showing on settings and checkout page
     * 
     * @var array
     */
    protected $supported_brands = [];


    /**
     * CopyAndPay script URL
     * 
     * @var string
     */
    protected $script_url = "https://oppwa.com/v1/paymentWidgets.js?checkoutId=";

    /**
     * CopyAndPay prepare checkout link
     * 
     * @method POST
     * @var string
     */
    protected $token_url = "https://oppwa.com/v1/checkouts";

    /**
     * get transaction status
     * @method GET
     * @var string
     * 
     * ##TOKEN## will replace with transaction id when fire the request
     */
    protected $transaction_status_url = "https://oppwa.com/v1/checkouts/##TOKEN##/payment";

    /** 
     * Query transaction report
     * 
     * @method GET
     * @var string
     */
    protected $query_url = "https://oppwa.com/v1/query";

    /**
     * default faild message 
     * @var string
     */
    protected $failed_message =  'Your transaction has been declined.';
    protected $success_message = 'Your payment has been processed successfully.';

    /**
     * payment styles that will show in settings 
     * 
     * @var array
     * 
     */
    protected  $hyperpay_payment_style = [
        'card' => 'Card',
        'plain' => 'Plain'
    ];

    protected $dataTosend = [];


    function __construct()
    {

        $this->init_settings(); // <== to get saved settings from database
        $this->init_form_fields(); // <== render form inside admin panel
        $this->is_arabic = str_starts_with(get_locale(), 'ar');

        $this->testmode = $this->get_option('testmode');
        $this->title = $this->get_option('title');
        $this->trans_type = $this->get_option('trans_type');
        $this->trans_mode = $this->get_option('trans_mode');
        $this->accesstoken = $this->get_option('accesstoken');
        $this->entityid = $this->get_option('entityId');
        $this->brands = $this->get_option('brands');

        $this->connector_type = $this->get_option('connector_type');
        $this->payment_style = $this->get_option('payment_style');
        $this->mailerrors = $this->get_option('mailerrors');
        $this->order_status = $this->get_option('order_status');
        $this->tokenization = $this->get_option('tokenization');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->lang = $this->get_option('lang');
        $this->custom_style = $this->get_option('custom_style');


        if ($this->is_arabic) {
            $this->failed_message = 'تم رفض العملية ';
            $this->success_message = 'تم إجراء عملية الدفع بنجاح.';
        }

        if ($this->testmode) {
            $this->query_url = "https://test.oppwa.com/v1/query";
            $this->token_url = "https://test.oppwa.com/v1/checkouts";
            $this->script_url = "https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=";
            $this->transaction_status_url = "https://test.oppwa.com/v1/checkouts/##TOKEN##/payment";
        }

        $this->query_url .= "?entityId=" . $this->entityid;
        $this->transaction_status_url .= "?entityId=" . $this->entityid;

        /**
         * overwrite default update function 
         * 
         * @param woocommerce_update_options_payment_gateways_<payment_id>
         * @param array[class,function_name]
         */
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

        /**
         * prepare checkout form
         * 
         * @param string woocommerce_receipt_<payments_id>
         * @param array[class,function_name]
         */
        add_action("woocommerce_receipt_{$this->id}", [$this, 'receipt_page']);

        /**
         * set payments icon from assets/images/BRAND-log.png 
         * 
         * make sure when add new image to rename image accorden this format BRAND_NAME-logo.png
         * 
         * @param string woocommerce_gateway_icon
         * @param array[class,function_name]
         * 
         */
        add_filter('woocommerce_gateway_icon', [$this, 'set_icons'], 10, 2);

        /**
         * to include assets/js/admin.js <JavaScript>
         * 
         * @param string admin_enqueue_scripts
         * @param array[class,function_name]
         *          
         */
        add_action('admin_enqueue_scripts', [$this, 'admin_script']);
    }



    /**
     * for validate settings form
     * @return void
     */

    public function admin_script()
    {
        global  $current_tab, $current_section;

        if ($current_tab == 'checkout' && $current_section == $this->id) {

            $data = [
                'id' => $this->id,
                'url' => $this->token_url,
                'code_setting' => wp_enqueue_code_editor(['type' => 'text/css'])
            ];

            wp_enqueue_script('hyperpay_admin',  HYPERPAY_PLUGIN_DIR . '/assets/js/admin.js', ['jquery'], false, true);
            wp_localize_script('hyperpay_admin', 'data', $data);
        }
    }


    /**
     * to set payment icon based on supported brands
     * 
     * @param string $icon
     * @param string $id currnet payment id
     * 
     * @return string  $icon new icon
     * 
     */

    public function set_icons($icon, $id)
    {

        if ($id == $this->id) {
            $icons = "<ul class='HP_supported-brans-icons'>";
            foreach ($this->supported_brands as $key => $brand) {
                $icons .= "<li><img src='" . HYPERPAY_PLUGIN_DIR . '/assets/images/' . esc_attr($key) . "-logo.png'></li>";
            }
            return $icons . '</ul>';
        }
        return $icon;
    }

    public function init_form_fields()
    {

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Payment Module.'),
                'default' => 'no'
            ],
            'testmode' => [
                'title' => __('Test mode'),
                'type' => 'select',
                'options' => ['0' => __('Off'), '1' => __('On')]
            ],
            'title' => [
                'id' => 'ahmad_test',
                'title' => __('Title:'),
                'type' => 'text',
                'description' => ' ' . __('This controls the title which the user sees during checkout.'),
                'default' => $this->title ?? ($this->is_arabic ? __('بطاقة ائتمانية') : __('Credit Card'))
            ],
            'trans_type' => [
                'title' => __('Transaction type'),
                'type' => 'select',
                'options' => $this->get_hyperpay_trans_type(),
            ],
            'connector_type' => [
                'title' => __('Connector Type'),
                'type' => 'select',
                'options' => $this->get_hyperpay_connector_type(),
            ],
            'accesstoken' => [
                'title' => __('Access Token'),
                'type' => 'text',
            ],
            'entityId' => [
                'title' => __('Entity ID'),
                'type' => 'text',
            ],
            'tokenization' => [
                'title' => __('Tokenization'),
                'type' => 'select',
                'options' => $this->get_hyperpay_tokenization(),
            ],
            'brands' => [
                'title' => __('Brands'),
                'class' => count($this->supported_brands) !== 1 ?:  'disabled',
                'type' => count($this->supported_brands) > 1 ? 'multiselect' : 'select',
                'options' => $this->supported_brands,
            ],
            'payment_style' => [
                'title' => __('Payment Style'),
                'type' => 'select',
                'class' => count($this->hyperpay_payment_style) !== 1 ?:  'disabled',
                'options' => $this->hyperpay_payment_style,
                'default' => 'plain'
            ],
            'custom_style' => [
                'title' => __('Custom Style'),
                'type' => 'textarea',
                'description' => 'Input custom css for payment (Optional)',
                'class' => 'hyperpay_custom_style'

            ],
            'mailerrors' => [
                'title' => __('Enable error logging by email?'),
                'type' => 'checkbox',
                'label' => __('Yes'),
                'default' => 'no',
                'description' => __('If checked, an email will be sent to ' . get_bloginfo('admin_email') . ' whenever a callback fails.'),
            ],
            'redirect_page_id' => [
                'title' => __('Return Page'),
                'type' => 'select',
                'options' => $this->get_pages('Select Page'),
                'description' => "URL of success page"
            ],
            'order_status' => [
                'title' => __('Status Of Order'),
                'type' => 'select',
                'options' => $this->get_order_status(),
                'description' => "select order status after success transaction."
            ]
        ];
    }


    function get_order_status()
    {
        $order_status = [

            'processing' => 'Processing',
            'completed' => 'Completed'
        ];

        return $order_status;
    }

    function get_hyperpay_tokenization()
    {
        $hyperpay_tokenization = [
            'enable' => 'Enable',
            'disable' => 'Disable'
        ];

        return $hyperpay_tokenization;
    }

    function get_hyperpay_trans_type()
    {
        $hyperpay_trans_type = [
            'DB' => 'Debit',
            'PA' => 'Pre-Authorization'
        ];

        return $hyperpay_trans_type;
    }

    function get_hyperpay_connector_type()
    {
        $hyperpay_connector_type = [
            'MPGS' => 'MPGS',
            'VISA_ACP' => 'VISA_ACP'
        ];

        return $hyperpay_connector_type;
    }

    function receipt_page($order)
    {
        global $woocommerce;
        $order = new WC_Order($order);
        $error = false; // used to rerender the form in case of an error


        if (isset($_GET['g2p_token'])) {
            $token = $_GET['g2p_token'];
            $this->renderPaymentForm($order, $token);
        }

        if (isset($_GET['id'])) {
            $token = $_GET['id'];


            $url = str_replace('##TOKEN##', $token, $this->transaction_status_url);


            $auth = [

                'headers' => ['Authorization:Bearer ' . $this->accesstoken]
            ];

            $response = wp_remote_get($url, $auth);


            $resultJson = wp_remote_retrieve_body($response);
            $resultJson = json_decode($resultJson, true);


            if (isset($resultJson['result']['code'])) {

                $mada = $this->check_mada($resultJson);
                $success = $mada['status'];
                $failed_msg = $mada['msg'];



                $orderid = $resultJson['merchantTransactionId'] ?? '';


                $order_response = new WC_Order($orderid);

                if ($order_response) {


                    if ($success) {
                        WC()->session->set('hp_payment_retry', 0);
                        if ($order->status != 'completed') {
                            $order->update_status($this->order_status);
                            $woocommerce->cart->empty_cart();


                            $uniqueId = $resultJson['id'];

                            if (isset($resultJson['registrationId'])) {

                                $registrationID = $resultJson['registrationId'];

                                $customerID = $order->get_customer_id();
                                global $wpdb;

                                $registrationIDs = $wpdb->get_results(
                                    "SELECT * FROM {$wpdb->prefix}woocommerce_saving_cards
                                        WHERE registration_id ='$registrationID'
                                        and mode = '{$this->testmode}'"
                                );

                                if (count($registrationIDs) == 0) {

                                    $wpdb->insert(
                                        "{$wpdb->prefix}woocommerce_saving_cards",
                                        [
                                            'customer_id' => $customerID,
                                            'registration_id' => $registrationID,
                                            'mode' => $this->testmode,
                                        ]
                                    );
                                }
                            }

                            $order->add_order_note($this->success_message . 'Transaction ID: ' . $uniqueId);
                        }

                        wp_redirect($this->get_return_url($order));
                    }
                }
            }

            $this->process_faild_payment($order, "{$this->failed_message}  $failed_msg");
        }
    }

    private function renderPaymentForm($order, $token = '')
    {

        if ($token) {

            $scriptURL = $this->script_url;
            $scriptURL .= $token;

            $payment_brands = $this->brands;
            if (is_array($this->brands))
                $payment_brands = implode(' ', $this->brands);

            $postbackURL = $order->get_checkout_payment_url(true);

            if (parse_url($postbackURL, PHP_URL_QUERY)) {
                $postbackURL .= '&';
            } else {
                $postbackURL .= '?';
            }
            $postbackURL .= 'hpOrderId=' . $order->get_id();

            $dataObj = [
                'is_arabic' => esc_js($this->is_arabic),
                'style' => esc_js($this->payment_style),
                'tokenization' => esc_js($this->tokenization),
                'postbackURL' => esc_url($postbackURL),
                'payment_brands' => esc_js($payment_brands)
            ];


            wp_enqueue_script('wpwl_hyperpay_script', $scriptURL, null, null);

            wp_enqueue_script('hyperpay_script',  HYPERPAY_PLUGIN_DIR . '/assets/js/script.js', ['jquery'], false, true);
            wp_localize_script('hyperpay_script', 'dataObj', $dataObj);

            echo '<style>' . wp_unslash($this->custom_style) . '</style>';
        }
    }


    /**
     * Process the payment and return the result
     * */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $customerID = $order->get_customer_id();


        if ($customerID > 0 && get_current_user_id() == $customerID) {
            //Registered
            $this->is_registered_user = true;
        } else {
            //Guest
            $this->is_registered_user = false;
        }


        $orderAmount = number_format($order->get_total(), 2, '.', '');
        $amount = number_format(round($orderAmount, 2), 2, '.', '');
        $currency = get_woocommerce_currency();
        $firstName = $order->get_billing_first_name();
        $family = $order->get_billing_last_name();
        $street = $order->get_billing_address_1();
        $zip = $order->get_billing_postcode();
        $city = $order->get_billing_city();
        $state = $order->get_billing_state();
        $country = $order->get_billing_country();
        $email = $order->get_billing_email();

        $firstName = preg_replace('/\s/', '', str_replace("&", "", $firstName));
        $family = preg_replace('/\s/', '', str_replace("&", "", $family));
        $street = preg_replace('/\s/', '', str_replace("&", "", $street));
        $city = preg_replace('/\s/', '', str_replace("&", "", $city));
        $state = preg_replace('/\s/', '', str_replace("&", "", $state));
        $country = preg_replace('/\s/', '', str_replace("&", "", $country));

        if (empty($state)) {
            $state = $city;
        }

        $data = [
            'headers' => [
                "Authorization" => "Bearer {$this->accesstoken}"
            ],
            'body' => [
                "entityId" => $this->entityid,
                "amount" => $amount,
                "currency" => $currency,
                "paymentType" => $this->trans_type,
                "merchantTransactionId" => $order_id,
                "customer.email" => $email,
                "notificationUrl" =>  $order->get_checkout_payment_url(true),
                "customParameters[bill_number]" => $order_id,
                "customParameters[branch_id]" => '1',
                "customParameters[teller_id]" => '1',
                "customParameters[device_id]" => '1',
            ]
        ];

        echo "<pre>";
        $this->setExtraData($order);

        $url = $this->token_url;

        if ($this->testmode) {
            $data["testMode"] = "EXTERNAL";
        }


        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($firstName) == false)) {
            $data["customer.givenName"] = $firstName;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($family) == false)) {
            $data["customer.surname"] = $family;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($street) == false)) {
            $data["billing.street1"] = $street;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($city) == false)) {
            $data["billing.city"] = $city;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($state) == false)) {
            $data["billing.state"] = $state;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($country) == false)) {
            $data["billing.country"] = $country;
        }




        if ($this->tokenization == 'enable' && $this->is_registered_user == true) {

            global $wpdb;

            $data["createRegistration"] = "true";
            $registrationIDs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_saving_cards WHERE customer_id =$customerID and mode ='{$this->testmode}'");
            if ($registrationIDs) {

                foreach ($registrationIDs as $key => $id) {
                    $data .= "&registrations[$key].id=" . $id->registration_id;
                }
            }
        }

        $response = wp_remote_post($url, $data);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem with $url", 'error');
        }


        $response = wp_remote_retrieve_body($response);
        $result = json_decode($response, true);


        if (array_key_exists('id', $result)) {
            $token = $result['id'];
        }

        return [
            'result' => 'success',
            'token' => $token ?? null,
            'redirect' => add_query_arg('g2p_token', $token, $order->get_checkout_payment_url(true))
        ];
    }

    function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = [];

        if ($title)
            $page_list[] = $title;

        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }

        return $page_list;
    }

    function isThisEnglishText($text)
    {
        return preg_match("/\p{Latin}+/u", $text);
    }

    public function queryTransactionReport($merchantTrxId)
    {

        $url =  $this->query_url . "&merchantTransactionId=$merchantTrxId";


        $response = wp_remote_get($url, ["headers" => ["Authorization" => "Bearer {$this->accesstoken}"]]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);


        return $response;
    }


    public function check_mada($resultJson)
    {

        $success = false;
        $failed_msg = '';

        $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
        $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
        //success status
        if (preg_match($successCodePattern, $resultJson['result']['code']) || preg_match($successManualReviewCodePattern, $resultJson['result']['code'])) {
            $success = 1;
        } else {
            //fail case
            $failed_msg = $resultJson['result']['description'];

            if (isset($resultJson['card']['bin']) && $resultJson['result']['code'] == '800.300.401') {
                $searchBin = $resultJson['card']['bin'];
                if (in_array($searchBin, $this->blackBins)) {
                    $failed_msg = 'Sorry! Please select "mada" payment option in order to be able to complete your purchase successfully.';
                    if ($this->is_arabic) {
                        $failed_msg = 'عذرا! يرجى اختيار خيار الدفع "مدى" لإتمام عملية الشراء بنجاح.';
                    }
                }
            }
        }

        return [
            'status' => $success,
            'msg' => $failed_msg
        ];
    }


    public function process_faild_payment($order, $msg)
    {

        if (isset($_GET['hpOrderId'])) {
            $queryResponse = $this->queryTransactionReport($_GET['hpOrderId']);
            if (array_key_exists('payments', $queryResponse))
                $this->processQueryResult($queryResponse, $order);
        }


        $order->add_order_note($msg);
        $order->update_status('cancelled');

        if ($this->is_arabic) {
            wc_add_notice(__('حدث خطأ في عملية الدفع والسبب <br/>' . $msg . '<br/>' . 'يرجى المحاولة مرة أخرى'), 'error');
        } else {
            wc_add_notice(__('(Transaction Error) ' . $msg), 'error');
        }
        wc_print_notices();
    }

    public function processQueryResult($resultJson, $order)
    {
        global $woocommerce;
        $success = 0;

        $payment = end($resultJson['payments']); // et the last p

        if (isset($payment['result']['code'])) {
            $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
            $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
            //success status
            if (preg_match($successCodePattern, $payment['result']['code']) || preg_match($successManualReviewCodePattern, $payment['result']['code'])) {
                $success = 1;
            } else {
                //fail case
                $failed_msg = $payment['result']['description'];
                if (isset($payment['card']['bin']) && $payment['result']['code'] == '800.300.401') {
                    $searchBin = $payment['card']['bin'];
                    if (in_array($searchBin, $this->blackBins)) {
                        if ($this->is_arabic) {
                            $failed_msg = 'عذرا! يرجى اختيار خيار الدفع "مدى" لإتمام عملية الشراء بنجاح.';
                        } else {
                            $failed_msg = 'Sorry! Please select "mada" payment option in order to be able to complete your purchase successfully.';
                        }
                    }
                }
            }

            if ($success) {
                if ($order->status != 'completed') {
                    $order->update_status($this->order_status);
                    $woocommerce->cart->empty_cart();
                    $uniqueId = $payment['id'];
                    $order->add_order_note($this->success_message . 'Transaction ID: ' . $uniqueId);
                    wp_redirect($this->get_return_url($order));
                }
            }
        }
    }

    public function setExtraData($order)
    {
        print_r($order -> id) ;
        return [];
    }
}
