<?php

require_once HYPERPAY_ABSPATH . 'includes/Hyperpay_main_class.php';
class WC_Hyperpay_Gateway extends Hyperpay_main_class
{
    public $id = 'hyperpay';
    public $method_title = 'Hyperpay Gateway';
    public $method_description = 'Hyperpay Plugin for Woocommerce';


    //    protected  $hyperpay_payment_style = [
    //     'card' => 'Card',
    //     'plain' => 'Plain'
    // ];


    protected $supported_brands = [
        'VISA' => 'Visa',
        'MASTER' => 'Master Card',
        'AMEX' => 'American Express',
    ];


    public function __construct()
    {

        parent::__construct();
        $this->blackBins = require_once(HYPERPAY_ABSPATH . '/includes/blackBins.php');
    }

    /**
     * 
     */

    // public function setExtraData($order)
    // {
    //     return [
    //         'headers' => [
    //             'header extra option' => 'value'
    //         ],
    //         'body' => [
    //             'extra param name ' => 'value'
    //         ]
    //     ];
    // }
}
