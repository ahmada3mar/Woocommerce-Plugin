<?php

class WC_Hyperpay_STCPay_Gateway extends Hyperpay_main_class
{
   public $id = 'hyperpay_stcpay';
   public $method_title = 'Hyperpay STCPay';
   public $method_description = 'STCPay Plugin for Woocommerce';

    
    protected $supported_brands = [
        'STC_PAY' => 'STCPay',
    ];

    
    public function __construct()
    {
        $this->form_fields['title']['default'] = 'STCPay';
        parent::__construct();
    }
}
