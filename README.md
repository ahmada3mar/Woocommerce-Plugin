# HyperPay

Hyperpay Paymentgatways plugin for Wordpress and Woocommerce
## Installation

Clone repo
```bash
git clone http://gitlab.hyperpay.com/plugins/woocommerce_new.git
```

## Add a new payment

```bash
php make payment_name
```
this command will create a new file called WC_Paymen_name.php

##### path : \gateways\WC_Paymen_name.php

and add the payment class to **$HP_gateways** <array> inside **\includes\class-install.php**

```php
    protected static $HP_gateways = [
        // Add Class Here
		'WC_Payment_name_Gateway', // <== your payment
        'WC_Hyperpay_Gateway',
        'WC_Hyperpay_STCPay_Gateway',
        'WC_Hyperpay_Mada_Gateway',
        'WC_Hyperpay_ApplePay_Gateway'
    ] ;
```
### \gateways\WC_Paymen_name.php

```php
require_once HYPERPAY_ABSPATH . "includes/Hyperpay_main_class.php";
class WC_Payment_name_Gateway extends Hyperpay_main_class
{

  public $id = "payment_name";
  public $method_title = "Payment_name Gateway";
  public $method_description = "Hyperpay Plugin for Woocommerce";
  protected $supported_brands = [
		"PAYMENT_NAME" => "Payment_name",
    ];


  public function __construct()
  {
    parent::__construct();
  }

   public function setExtraData(object $order) : array
   {
      return [];
   }
}
```
# Properties
## 1- public $id <string>
Every payment have a unique id 
> notice the id should be a unique and lowercase

## 2- public $method_title
This property is the displaying name of payment

## 3- public $method_description

This property to show a description next to payment title 

## 4- protected $supported_brands 
Here where you can add or remove the registered BRAND
>this brand will passed to data-brans <tag> inside form HTML

```html
<form class="paymentWidgets" data-brands="PAYMENT_NAME"></form>
```
# Methods
## 1- setExtraData(object $order)
this method allow you to add data to query that will send to connector
> you can get order ditails from $order Object.
```php
$order->id; // etc...
```

## 2- get_order_status()
order status : the last order status after success transaction . \
if you want to add a deffrents order status overwrite this method
## 3- get_hyperpay_trans_type()
by default return
```php
  [
    'DB' => 'Debit',
    'PA' => 'Pre-Authorization'
  ]
```

## 4- get_hyperpay_connector_type()
by default return
```php
  [
        'MPGS' => 'MPGS',
        'VISA_ACP' => 'VISA_ACP'
  ]
```

# customize Admin setting fields

## A. start from zero \
overwrite init_form_fields() method

```php


```
