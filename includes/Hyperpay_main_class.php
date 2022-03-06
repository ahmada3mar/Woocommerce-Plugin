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
        $this->is_arabic = str_starts_with(get_locale(), 'ar'); // <== to get current locale 

        $this->testmode = $this->get_option('testmode'); // <== check if payments on test mode 
        $this->title = $this->get_option('title'); // <== get title from setting
        $this->trans_type = $this->get_option('trans_type'); // <== get transaction type [DB / Pre-Auth] from setting
        $this->connector_type = $this->get_option('connector_type'); // <== get transaction connector [ MEGS / VISA ] from setting
        $this->accesstoken = $this->get_option('accesstoken'); // <== get accesstoke from setting
        $this->entityid = $this->get_option('entityId'); // <== get entityId from setting
        $this->brands = $this->get_option('brands'); // <== get brands from setting

        $this->payment_style = $this->get_option('payment_style'); // <== get style from setting
        $this->mailerrors = $this->get_option('mailerrors'); // <== get if mail error check or not from setting
        $this->order_status = $this->get_option('order_status'); // <== get order status after success from setting
        $this->tokenization = $this->get_option('tokenization'); // <== get tokenization from setting  if enabled store user ditails in DB
        $this->redirect_page_id = $this->get_option('redirect_page_id'); // <== after order complete redirect to selected page
        $this->custom_style = $this->get_option('custom_style'); // <== get custom style from setting


        if ($this->is_arabic) {
            $this->failed_message = 'تم رفض العملية ';
            $this->success_message = 'تم إجراء عملية الدفع بنجاح.';
        }

        /**
         * if test mode is one 
         * overwrite currents URLs ti test URLs
         */
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

    public function admin_script(): void
    {
        global  $current_tab, $current_section;

        /**
         * to make sure load admin.js just when currents pyments opened
         * 
         */
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

    public function set_icons($icon, $id): string
    {

        if ($id == $this->id) {
            $icons = "<ul class='HP_supported-brans-icons'>";
            foreach ($this->supported_brands as $key => $brand) {
                $img = HYPERPAY_PLUGIN_DIR . '/assets/images/default.png';

                if (file_exists(HYPERPAY_ABSPATH . '/assets/images/' . esc_attr($key) . "-logo.png"))
                    $img = HYPERPAY_PLUGIN_DIR . '/assets/images/' . esc_attr($key) . "-logo.png";

                $icons .= "<li><img src='$img'></li>";
            }
            return $icons . '</ul>';
        }
        return $icon;
    }

    /**
     * Here you can define all fiels thats will showning in setting page
     * @return void
     */
    public function init_form_fields(): void
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


    /**
     *  to fill order_status select fiels
     * 
     * @return array
     */
    function get_order_status(): array
    {
        $order_status = [

            'processing' => 'Processing',
            'completed' => 'Completed'
        ];

        return $order_status;
    }

    /**
     *  to fill tokenization select fiels
     * 
     * @return array
     */
    function get_hyperpay_tokenization(): array
    {
        $hyperpay_tokenization = [
            'enable' => 'Enable',
            'disable' => 'Disable'
        ];

        return $hyperpay_tokenization;
    }

    /**
     *  to fill trans_type select fiels
     * 
     * @return array
     */
    function get_hyperpay_trans_type(): array
    {
        $hyperpay_trans_type = [
            'DB' => 'Debit',
            'PA' => 'Pre-Authorization'
        ];

        return $hyperpay_trans_type;
    }

    /**
     *  to fill connector_type select fiels
     * 
     * @return array
     */
    function get_hyperpay_connector_type(): array
    {
        $hyperpay_connector_type = [
            'MPGS' => 'MPGS',
            'VISA_ACP' => 'VISA_ACP'
        ];

        return $hyperpay_connector_type;
    }

    /**
     * This function fire when click on Place order at checkout page
     * @param int $order_id
     * 
     * @return void
     */
    function receipt_page($order_id): void
    {

        global $woocommerce;
        $order = new WC_Order($order_id);


        // new transaction contain g2p_token 
        if (isset($_GET['g2p_token'])) {
            $token =  esc_attr($_GET['g2p_token']);
            $this->renderPaymentForm($order, $token);
        }

        // old transaction contain id 
        if (isset($_GET['id'])) {
            $token = $_GET['id'];


            $url = str_replace('##TOKEN##', $token, $this->transaction_status_url);


            // set header request to contain access token
            $auth = [

                'headers' => ['Authorization:Bearer ' . $this->accesstoken]
            ];

            $response = wp_remote_get($url, $auth);


            $resultJson = wp_remote_retrieve_body($response);
            $resultJson = json_decode($resultJson, true);


            if (isset($resultJson['result']['code'])) {

                // check if transaction faild and the reason if mada card or not 
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

                            /**
                             * update or create user data
                             */
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

                            $order->add_order_note($this->success_message . 'Transaction ID: ' . esc_html($uniqueId));
                        }

                        wp_redirect($this->get_return_url($order));
                    }
                }
            }

            $this->process_faild_payment($order, "{$this->failed_message}  $failed_msg");
        }
    }

    /**
     * 
     * render CopyAndPay form
     * @param object $order
     * @param string $token
     * @return void
     */
    private function renderPaymentForm(object $order, string $token): void
    {

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


        // include  CopyAndPay script to show the form
        wp_enqueue_script('wpwl_hyperpay_script', $scriptURL, null, null);

        // include assests\js\script.js to set wpwlOptions 
        wp_enqueue_script('hyperpay_script',  HYPERPAY_PLUGIN_DIR . '/assets/js/script.js', ['jquery'], false, true);

        // pass data to assests\js\script.js
        wp_localize_script('hyperpay_script', 'dataObj', $dataObj);

        // apply custom style that's entered on setting page <custom_style>
        wp_add_inline_style('hyperpay_custom_style', $this->custom_style);
    }


    /**
     * Process the payment and return the result
     * @param int $order_id
     * @return array[redirect,token,result]
     * 
     */
    public function process_payment($order_id): array
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

        // set data to post 
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
                    $data["registrations[$key].id"] =  $id->registration_id;
                }
            }
        }

        // add extra parameters if exists
        $data = array_merge_recursive($data, $this->setExtraData($order));

        // HTTP Request to oppwa to get checkout id
        $response = wp_remote_post($url, $data);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem with $url", 'error');
        }


        $response = wp_remote_retrieve_body($response);
        $result = json_decode($response, true);


        if (array_key_exists('id', $result)) {
            $token = $result['id'];
        }

        // add g2p_token to url query
        return [
            'result' => 'success',
            'token' => $token ?? null,
            'redirect' => add_query_arg('g2p_token', $token, $order->get_checkout_payment_url(true))
        ];
    }

    /**
     * to get all pages of website to fill <redirect to> option in admin setting 
     * 
     * @param bool
     * @param bool
     * @return array
     * 
     */
    function get_pages(bool $title = false, bool $indent = true): array
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

    /**
     * check if entered name is english or not
     * @param string
     * @return bool
     */
    function isThisEnglishText(string $text): bool
    {
        return preg_match("/\p{Latin}+/u", $text);
    }

    /**
     * 
     * GET request to transaction report to check if transaction exists or not
     * @param int
     * @return array $response
     * 
     */
    public function queryTransactionReport(string $merchantTrxId): array
    {

        $url =  $this->query_url . "&merchantTransactionId=$merchantTrxId";
        $response = wp_remote_get($url, ["headers" => ["Authorization" => "Bearer {$this->accesstoken}"]]);

        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        return $response;
    }



    /**
     * 
     * check if the reason of rejection was mada card
     * 
     * @param array $resultJson
     * @return array[status,msg]
     */
    public function check_mada(array $resultJson): array
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


    /**
     * handel faild pyments 
     * @param object $order
     * @param string $messege
     * @return void
     */
    public function process_faild_payment(object $order, string $msg): void
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

    /**
     * check the result of transaction if success of faild 
     * 
     * @param array $resultJson
     * @param object $order
     * @return void
     */
    public function processQueryResult(array $resultJson, object $order): void
    {
        global $woocommerce;
        $success = 0;

        $payment = end($resultJson['payments']); // get the last transaction

        if (isset($payment['result']['code'])) {
            $result = $this->check_mada($payment);
            $success = $result['status'];

            if ($success) {
                if ($order->status != 'completed') {
                    $order->update_status($this->order_status);
                    $woocommerce->cart->empty_cart();
                    $uniqueId = $payment['id'];
                    $order->add_order_note($this->success_message . 'Transaction ID: ' . esc_html($uniqueId));
                    wp_redirect($this->get_return_url($order));
                }
            }
        }
    }

    /**
     * set customParameters of requested data 
     * @param object 
     * @return array
     */
    public function setExtraData(object $order): array
    {
        return [];
    }
}
