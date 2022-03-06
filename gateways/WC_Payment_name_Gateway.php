<?php

require_once HYPERPAY_ABSPATH . "includes/Hyperpay_main_class.php";
class WC_Payment_name_Gateway extends Hyperpay_main_class
{
  /**
   * should be lower case and uniqe
   * @var string $id 
   */
  public $id = "payment_name";
  
  /**
   * The title which appear next to gateway on setting page 
   * @var string $method_title
   */
  public $method_title = "Payment_name Gateway";

  /**
   * Describtion of gateways which will appear next to title
   * @var string $method_description
   */
  public $method_description = "Hyperpay Plugin for Woocommerce";


  /**
   * you can overwrite styles options by 
   * uncomment array below
   * 
   * @var array $hyperpay_payment_style
   */

    //    protected  $hyperpay_payment_style = [
    //     "card" => "Card",
    //     "plain" => "Plain"
    // ];


  /**
   * 
   * the Brands supported by the gateway
   * @var array $supported_brands
   */
  protected $supported_brands = [
		"PAYMENT_NAME" => "Payment_name",
    ];


  public function __construct()
  {
    parent::__construct();
  }


  /**
   * to set extra parameter on requested data to connector 
   * just uncomment the function below
   * 
   * @param object $order
   * @return array 
   */

  // public function setExtraData(object $order) : array
  // {
  //     return [
  //         "headers" => [
  //             "header extra option" => "value"
  //         ],
  //         "body" => [
  //             "extra param name " => "value"
  //         ]
  //     ];
  // }
}
