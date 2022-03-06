<?php

class WC_Hyperpay_ApplePay_Gateway extends Hyperpay_main_class
{
   public $id = 'hyperpay_applepay';
   public $method_title = 'Hyperpay ApplePay';
   public $method_description = 'ApplePay Plugin for Woocommerce';

    
    protected $supported_brands = [
        'APPLEPAY' => 'ApplePay',
    ];

    
    public function __construct()
    {
        $this->form_fields['title']['default'] = 'ApplePay';
        parent::__construct();
    }
}
