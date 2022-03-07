<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://www.hyperpay.com/wp-content/uploads/2020/04/cropped-011-300x155.png" width="400"></a></p>

# HyperPay

Hyperpay Paymentgatways plugin for Wordpress and Woocommerce

### Resources 
* [Woocommerce Documentation ](https://woocommerce.com/document/payment-gateway-api/)
* [Wordpress Plugin Documentation ](https://developer.wordpress.org/plugins/)

### Indexes 
* [Installation ](#installation)
* [Add a new payment](#add-a-new-payment)
* [Properties](#properties)
* [Methods](#methods)
* [customize Admin setting fields](#customize-admin-setting-fields)
* [JavaScript & CSS](#customize-admin-setting-fields)(#javascript-and-css)



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
#### Finally add logo to your payment by add *BRAND-logo.png* file into :
Path : \asetes\images\BRAND-logo.png
>notice the image file name SHOULD be brand name UPPERCASE concat with "-logo.png" lowercase
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

## A. start from zero 
overwrite  **init_form_fields()** method

```php
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
            ]
                 ]
      }

```
or you can easily add/remove existed field

path: **\gateways\WC_Paymen_name.php**
```php
public function __construct()
{
   $this->form_fields['new_filed'] = [
                                       'title'=> 'new filed',
                                       'type'=>'text'
                                       'description'=> 'this is new filed'
                                     
                                      ]
}

```

for more information about the syntax of writing filed seed this [documentation](https://woocommerce.com/document/payment-gateway-api/).
> don't forget to register you filed in __construct() to store and retrieve the value 

```php
$this->new_filed = $this->get_option('new_filed');
```
> *Remember*: registration filed after initiate it 
* > always parent::__construct() in the first line .   

## JavaScript and CSS 
by default there are already js & css files included to the project, you can add and modify your script or css into these files.
* JavaScript 
  - \assets\js\script.js
   >this file run and include all time the payment run (all pages)
  - \assets\js\admin.js
  > this file run only on admin page setting exactly when edit you paymentsetting
* CSS
   -\assets\css\style.css
   > contain all styles
   -\assets\css\style-rtl.css
    > included only when website locale is arabic
