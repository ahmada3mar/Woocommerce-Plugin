<?php
class Hyperpay_main_class extends WC_Payment_Gateway
{

    protected $is_registered_user = false;
    protected $blackBins = [];
    protected $supported_brands = [];

    protected $script_url = "https://oppwa.com/v1/paymentWidgets.js?checkoutId=";
    protected $token_url = "https://oppwa.com/v1/checkouts";
    protected $transaction_status_url = "https://oppwa.com/v1/checkouts/##TOKEN##/payment";
    protected $script_url_test = "https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=";
    protected $token_url_test = "https://test.oppwa.com/v1/checkouts";
    protected $transaction_status_url_test = "https://test.oppwa.com/v1/checkouts/##TOKEN##/payment";
    protected $query_url_test = "https://test.oppwa.com/v1/query";
    protected $query_url = "https://oppwa.com/v1/query";

    protected $failed_message =  'Your transaction has been declined.';
    protected $success_message = 'Your payment has been processed successfully.';

    protected  $hyperpay_payment_style = [
        'card' => 'Card',
        'plain' => 'Plain'
    ];


    function __construct()
    {
        $this->init_settings();
        $this->init_form_fields();
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


        if ($this->is_arabic) {
            $this->failed_message = 'تم رفض العملية ';
            $this->success_message = 'تم إجراء عملية الدفع بنجاح.';
        }


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_hyperpay', [&$this, 'receipt_page']);
        add_filter('woocommerce_gateway_icon', [$this, 'set_icons'], 10, 2);
    }

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
                'label' => __('Enable Hyperpay Payment Module.'),
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
                'default' => $this->is_arabic ? __('بطاقة ائتمانية') : __('Credit Card')
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

            if ($this->testmode == 0) {
                $url = $this->transaction_status_url;
            } else {
                $url = $this->transaction_status_url_test;
            }

            $url = str_replace('##TOKEN##', $token, $url);
            $url .= "?entityId=" . $this->entityid;

            $auth =  ['Authorization:Bearer ' . $this->accesstoken];
            $response = wp_remote_post($url, $auth);

            $resultJson = wp_remote_retrieve_body($response);
            $resultJson = json_decode($resultJson, true);

            // print_r($resultJson);
            // die;




            $sccuess = 0;
            $failed_msg = '';
            $orderid = '';

            if (isset($resultJson['result']['code'])) {
                $successCodePattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
                $successManualReviewCodePattern = '/^(000\.400\.0|000\.400\.100)/';
                //success status
                if (preg_match($successCodePattern, $resultJson['result']['code']) || preg_match($successManualReviewCodePattern, $resultJson['result']['code'])) {
                    $sccuess = 1;
                } else {
                    //fail case
                    $failed_msg = $resultJson['result']['description'];

                    if (isset($resultJson['card']['bin']) && $resultJson['result']['code'] == '800.300.401') {
                        $searchBin = $resultJson['card']['bin'];
                        if (in_array($searchBin, $this->blackBins)) {
                            if ($this->is_arabic) {
                                $failed_msg = 'عذرا! يرجى اختيار خيار الدفع "مدى" لإتمام عملية الشراء بنجاح.';
                            } else {
                                $failed_msg = 'Sorry! Please select "mada" payment option in order to be able to complete your purchase successfully.';
                            }
                        }
                    }
                }
                $orderid = '';

                if (isset($resultJson['merchantTransactionId'])) {
                    $orderid = $resultJson['merchantTransactionId'];
                }

                $order_response = new WC_Order($orderid);
                if ($this->is_arabic) {
                    echo " <style>
                        .woocommerce-error {
                        text-align: right;
                        }
                        </style>
                        ";
                }

                if ($order_response) {
                    if ($sccuess == 1) {
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

                    } else {
                        if (isset($_GET['hpOrderId'])) {
                            $queryResponse = $this->queryTransactionReport($_GET['hpOrderId'], $this->entityid, $this->accesstoken);
                            $queryResponse = json_decode($queryResponse, true);
                            $this->processQueryResult($queryResponse, $order);
                        } else {
                            $order->add_order_note($this->failed_message . $failed_msg);
                            $order->update_status('cancelled');

                            if ($this->is_arabic) {
                                wc_add_notice(__('حدث خطأ في عملية الدفع والسبب <br/>' . $failed_msg . '<br/>' . 'يرجى المحاولة مرة أخرى'), 'error');
                            } else {
                                wc_add_notice(__('(Transaction Error) ' . $failed_msg), 'error');
                            }
                            wc_print_notices();
                            $error = true;
                        }
                    }
                } else {
                    if (isset($_GET['hpOrderId'])) {
                        $queryResponse = $this->queryTransactionReport($_GET['hpOrderId'], $this->entityid, $this->accesstoken);
                        $queryResponse = json_decode($queryResponse, true);
                        $this->processQueryResult($queryResponse, $order);
                    } else {
                        $order->add_order_note($this->failed_message);
                        $order->update_status('cancelled');
                        if ($this->is_arabic) {
                            wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                        } else {
                            wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                        }
                        wc_print_notices();
                        $error = true;
                    }
                }
            } else {
                if (isset($_GET['hpOrderId'])) {
                    $queryResponse = $this->queryTransactionReport($_GET['hpOrderId'], $this->entityid, $this->accesstoken);
                    $queryResponse = json_decode($queryResponse, true);
                    $this->processQueryResult($queryResponse, $order);
                } else {
                    $order->add_order_note($this->failed_message);
                    $order->update_status('cancelled');

                    if ($this->is_arabic) {
                        wc_add_notice(__('(حدث خطأ في عملية الدفع يرجى المحاولة مرة أخرى) '), 'error');
                    } else {
                        wc_add_notice(__('(Transaction Error) Error processing payment.'), 'error');
                    }
                    wc_print_notices();
                    $error = true;
                }
            }
        }
    }

    private function renderPaymentForm($order, $token = '')
    {
     
        if ($token) {
            $token = $token;

            $order_id = $order->get_id();

            if ($this->testmode == 0) {
                $scriptURL = $this->script_url;
            } else {
                $scriptURL = $this->script_url_test;
            }

            $scriptURL .= $token;

            $payment_brands = implode(' ', $this->brands);
            $postbackURL = $order->get_checkout_payment_url(true);
            
            if (parse_url($postbackURL, PHP_URL_QUERY)) {
                $postbackURL .= '&';
            } else {
                $postbackURL .= '?';
            }
            $postbackURL .= 'hpOrderId=' . $order->get_id();
            
            $dataObj = [
                'is_arabic' => $this->is_arabic,
                'style' => $this->payment_style,
                'tokenization'=>'enable',
                'postbackURL' => $postbackURL,
                'payment_brands' => $payment_brands
            ];



            if ($this->is_arabic) {
                echo '<style>
                                      
          </style>';
            };

            wp_enqueue_script('wpwl_hyperpay_script', $scriptURL , null , null);
            wp_enqueue_script('hyperpay_script',  HYPERPAY_PLUGIN_DIR . '/assets/js/script.js' , ['jquery'] , false , true);

            wp_localize_script('hyperpay_script' , 'dataObj', $dataObj);
           

        }
    }

    /**
     * Process the payment and return the result
     * */
    public function process_payment($order_id)
    {
        global $woocommerce;


        $order = new WC_Order($order_id);

        if ($order->get_customer_id() > 0 && get_current_user_id() == $order->get_customer_id()) {
            //Registered
            $this->is_registered_user = true;
        } else {
            //Guest
            $this->is_registered_user = false;
        }


        $orderAmount = number_format($order->get_total(), 2, '.', '');

        $orderid = $order_id;

        $accesstoken = $this->accesstoken;
        $entityid = $this->entityid;
        $mode = $this->trans_mode;
        $type = $this->trans_type;
        $amount = number_format(round($orderAmount, 2), 2, '.', '');
        $currency = get_woocommerce_currency();
        $transactionID = $orderid;
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

        $data = "entityId=$entityid" .
            "&amount=$amount" .
            "&currency=$currency" .
            "&paymentType=$type" .
            "&merchantTransactionId=$transactionID" .
            "&customer.email=$email";

        $data .= '&notificationUrl=' . $order->get_checkout_payment_url(true);

        if ($this->testmode == 0) {
            $url = $this->token_url;
        } else {
            $url = $this->token_url_test;
            $data .= "&testMode=EXTERNAL";
        }


        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($firstName) == false)) {
            $data .= "&customer.givenName=" . $firstName;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($family) == false)) {
            $data .= "&customer.surname=" . $family;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($street) == false)) {
            $data .= "&billing.street1=" . $street;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($city) == false)) {
            $data .= "&billing.city=" . $city;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($state) == false)) {
            $data .= "&billing.state=" . $state;
        }

        if (!($this->connector_type == 'MPGS' && $this->isThisEnglishText($country) == false)) {
            $data .= "&billing.country=" . $country;
        }

        $data .= "&customParameters[branch_id]=1";
        $data .= "&customParameters[teller_id]=1";
        $data .= "&customParameters[device_id]=1";
        $data .= "&customParameters[bill_number]=$transactionID";


        if ($this->tokenization == 'enable' && $this->is_registered_user == true) {

            //$data .=  "&createRegistration=true";
            global $wpdb;
            $customerID = $order->get_customer_id();
            $registrationIDs = $wpdb->get_results("SELECT * FROM wp_woocommerce_saving_cards WHERE customer_id =$customerID and mode = '" . $this->testmode . "'");
            if ($registrationIDs) {

                foreach ($registrationIDs as $key => $id) {
                    $data .= "&registrations[$key].id=" . $id->registration_id;
                }
            }
        }


        $customerID = $order->get_customer_id();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization:Bearer ' . $accesstoken
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem with $url, $php_errormsg", 'error');
        }
        curl_close($ch);
        if ($response === false) {
            wc_add_notice(__('Hyperpay error:', 'woocommerce') . "Problem reading data from $url, $php_errormsg", 'error');
        }

        $result = json_decode($response);


        $token = '';

        if (isset($result->id)) {
            $token = $result->id;
        }

        return [
            'result' => 'success',
            'token' => $token,
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

    public function queryTransactionReport($merchantTrxId, $entityid, $accesstoken)
    {
        if ($this->testmode == 0) {
            $url = $this->query_url;
        } else {
            $url = $this->query_url_test;
        }
        $url .= "?entityId=$entityid";
        $url .= "&merchantTransactionId=$merchantTrxId";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization:Bearer ' . $accesstoken
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $responseData;
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
            } else {
                $order->add_order_note($this->failed_message . $failed_msg);
                $order->update_status('cancelled');

                if ($this->is_arabic) {
                    wc_add_notice(__('حدث خطأ في عملية الدفع والسبب <br/>' . $failed_msg . '<br/>' . 'يرجى المحاولة مرة أخرى'), 'error');
                } else {
                    wc_add_notice(__('(Transaction Error) ' . $failed_msg), 'error');
                }
                wc_print_notices();
            }
        }
    }
}
