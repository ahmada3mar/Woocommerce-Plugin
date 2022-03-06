<?php

class WC_Hyperpay_Mada_Gateway extends Hyperpay_main_class
{
   public $id = 'hyperpay_mada';
   public $method_title = 'Hyperpay Mada';
   public $method_description = 'Mada Plugin for Woocommerce';

    
    protected $supported_brands = [
        'MADA' => 'Mada',
    ];

    
    public function __construct()
    {
        $this->form_fields['title']['default'] = 'Mada';
        parent::__construct();
    }
}
