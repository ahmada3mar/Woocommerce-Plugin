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
     * payment styles that will show in settings 
     * 
     * @var array
     * 
     */
    protected  $hyperpay_payment_style = [
        'card' =>  'Card',
        'plain' =>  'Plain'
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
        $this->trans_mode = $this->get_option('trans_mode'); // <== get transaction mode [INTERNAL / EXTERNAL / LIVE] from setting
        $this->accesstoken = $this->get_option('accesstoken'); // <== get accesstoke from setting
        $this->entityid = $this->get_option('entityId'); // <== get entityId from setting
        $this->brands = $this->get_option('brands'); // <== get brands from setting

        $this->payment_style = $this->get_option('payment_style'); // <== get style from setting
        $this->mailerrors = $this->get_option('mailerrors'); // <== get if mail error check or not from setting
        $this->order_status = $this->get_option('order_status'); // <== get order status after success from setting
        $this->tokenization = $this->get_option('tokenization'); // <== get tokenization from setting  if enabled store user ditails in DB
        $this->redirect_page_id = $this->get_option('redirect_page_id'); // <== after order complete redirect to selected page
        $this->custom_style = $this->get_option('custom_style'); // <== get custom style from setting


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
         * default faild message 
         * @var string
         */
        $this->failed_message =  __('Your transaction has been declined.' ,  'woocommerce-hyperpay-payments');
        $this->success_message = __('Your payment has been processed successfully.' , 'woocommerce-hyperpay-payments') ;

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
                'title' => __('Enable/Disable', 'woocommerce-hyperpay-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Payment Module.', 'woocommerce-hyperpay-payments'),
                'default' => 'no'
            ],
            'testmode' => [
                'title' => __('Test mode', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => ['0' => __('Off', 'woocommerce-hyperpay-payments' ), '1' => __('On', 'woocommerce-hyperpay-payments')]
            ],
            'title' => [
                'title' => __('Title:' , 'woocommerce-hyperpay-payments'),
                'type' => 'text',
                'description' => ' ' . __('This controls the title which the user sees during checkout.', 'woocommerce-hyperpay-payments'),
                'default' => $this->method_title ??  __('Credit Card', 'woocommerce-hyperpay-payments')
            ],
            'trans_type' => [
                'title' => __('Transaction type', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => $this->get_hyperpay_trans_type(),
            ],
            'trans_mode' => array(
                'title' => __('Transaction mode', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => $this->get_hyperpay_trans_mode(),
                'description' => ''
            ),
            'accesstoken' => [
                'title' => __('Access Token', 'woocommerce-hyperpay-payments'),
                'type' => 'text',
            ],
            'entityId' => [
                'title' => __('Entity ID', 'woocommerce-hyperpay-payments'),
                'type' => 'text',
            ],
            'tokenization' => [
                'title' => __('Tokenization', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => $this->get_hyperpay_tokenization(),
            ],
            'brands' => [
                'title' => __('Brands', 'woocommerce-hyperpay-payments'),
                'class' => count($this->supported_brands) !== 1 ?:  'disabled',
                'type' => count($this->supported_brands) > 1 ? 'multiselect' : 'select',
                'options' => $this->supported_brands,
            ],
            'payment_style' => [
                'title' => __('Payment Style', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'class' => count($this->hyperpay_payment_style) !== 1 ?:  'disabled',
                'options' => $this->hyperpay_payment_style,
                'default' => 'plain'
            ],
            'custom_style' => [
                'title' => __('Custom Style', 'woocommerce-hyperpay-payments'),
                'type' => 'textarea',
                'description' => 'Input custom css for payment (Optional)',
                'class' => 'hyperpay_custom_style'
            ],
            'mailerrors' => [
                'title' => __('Enable error logging by email?', 'woocommerce-hyperpay-payments'),
                'type' => 'checkbox',
                'label' => __('Yes'),
                'default' => 'no',
                'description' => __('If checked, an email will be sent to ' . get_bloginfo('admin_email') . ' whenever a callback fails.'),
            ],
            'redirect_page_id' => [
                'title' => __('Return Page', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => $this->get_pages('Select Page'),
                'description' => __("success page", 'woocommerce-hyperpay-payments')
            ],
            'order_status' => [
                'title' => __('Status Of Order', 'woocommerce-hyperpay-payments'),
                'type' => 'select',
                'options' => $this->get_order_status(),
                'description' => __("select order status after success transaction." , 'woocommerce-hyperpay-payments')
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

            'processing' =>  __('Processing', 'woocommerce-hyperpay-payments') ,
            'completed' =>  __('Completed', 'woocommerce-hyperpay-payments')
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
            'enable' =>  __('Enable', 'woocommerce-hyperpay-payments'),
            'disable' =>  __('Disable', 'woocommerce-hyperpay-payments')
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
     *  to fill trans_mode select fiels
     * 
     * @return array
     */
    function get_hyperpay_trans_mode(): array
    {
        $hyperpay_trans_type = [
            'INTERNAL' => 'Internal',
            'EXTERNAL' => 'External',
            'LIVE' => 'Live'
        ];


        return $hyperpay_trans_type;
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
        $error_code = '';
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

                            $order->add_order_note($this->success_message . __('Transaction ID: ' , 'woocommerce-hyperpay-payments') . esc_html($uniqueId));
                        }

                        wp_redirect($this->get_return_url($order));
                    }
                }
                $error_code = $resultJson['result']['code']; 
            }
            $this->process_faild_payment($order, "{$this->failed_message} $error_code :  $failed_msg");
        }
    }

    /**
     * 
     * render CopyAndPay form
     * @param WC_Order $order
     * @param string $token
     * @return void
     */
    private function renderPaymentForm(WC_Order $order, string $token): void
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
        $url = $this->token_url;
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
                "customer.givenName" => $firstName,
                "customer.surname" => $family,
                "billing.street1" => $street,
                "billing.city" => $city,
                "billing.state" => $state,
                "billing.country" => $country,
                "customParameters[branch_id]" => '1',
                "customParameters[teller_id]" => '1',
                "customParameters[device_id]" => '1',
            ]
        ];

        if ($this->testmode) {
            $data["testMode"] = $this->trans_mode;
        }

        $data_to_validate = [
            $firstName,
            $family,
            $street,
            $city,
            $state,
            $country
        ];

        /**
         * 
         * validate data to prevent arabic character 
         */
        $this->validate_form($data_to_validate);


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
            wc_add_notice(__("Problem with payments ", 'woocommerce-hyperpay-payments') , 'error');
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
     * check if all data valid to post {English Charachter}
     * @param array
     * @return void
     */
    function validate_form(array $data): void
    {
        $errors = false;


        foreach ($data as $field) {
            if (!preg_match("/^(?=.*[A-Za-z0-9].*[A-Za-z0-9])[\s\'\,{\}\[\]A-Za-z]*$/", $field))
                $errors = true;
        }


        if ($errors) {
            wc_add_notice('<strong>*' .__('All Information Should be In English' , 'woocommerce-hyperpay-payments')  .'</strong>', 'error');
            throw new Exception();
        }
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
                    $failed_msg = __('Sorry! Please select "mada" payment option in order to be able to complete your purchase successfully.', 'woocommerce-hyperpay-payments');
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
     * @param WC_Order $order
     * @param string $messege
     * @return void
     */
    public function process_faild_payment(WC_Order $order, string $msg): void
    {

        if (isset($_GET['hpOrderId'])) {
            $queryResponse = $this->queryTransactionReport($_GET['hpOrderId']);
            if (array_key_exists('payments', $queryResponse))
                $this->processQueryResult($queryResponse, $order);
        }


        $order->add_order_note($msg);
        $order->update_status('cancelled');

        wc_add_notice(__('Your transaction has been declined.' , 'woocommerce-hyperpay-payments'), 'error');
        
        wc_print_notices();
    }

    /**
     * check the result of transaction if success of faild 
     * 
     * @param array $resultJson
     * @param WC_Order $order
     * @return void
     */
    public function processQueryResult(array $resultJson, WC_Order $order): void
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
                    $order->add_order_note($this->success_message . __('Transaction ID: ', 'woocommerce-hyperpay-payments') . esc_html($uniqueId));
                    wp_redirect($this->get_return_url($order));
                }
            }
        }
    }

    /**
     * set customParameters of requested data 
     * @param WC_Order $order
     * @return array
     */
    public function setExtraData(WC_Order $order): array
    {
        return [];
    }
}
