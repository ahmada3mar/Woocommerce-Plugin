<?php

require_once HYPERPAY_ABSPATH . '/gateways/hyperpay_main_class.php';
class WC_Hyperpay_Gateway extends Hyperpay_main_class
{
   public $id = 'hyperpay';
   public $has_fields = false;
   public $method_title = 'Hyperpay Gateway';
   public $method_description = 'Hyperpay Plugin for Woocommerce';

    
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
}
