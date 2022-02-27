<?php

/**
 * 
 * installing class
 */


class hyperpay_main
{
    protected static $HP_gateways = [
        'WC_Hyperpay_Gateway'
    ] ;


    public  function load()
    {
        foreach(self::$HP_gateways as $names ){
            include_once HYPERPAY_ABSPATH . "gateways/$names.php";
        }

        // echo 'ss';
        // die;

        add_filter('woocommerce_payment_gateways', ['hyperpay_main', 'get_gateways']);
        
        
    }

    public static function run_migration()
    {

        global $wpdb;
        $sql_raw = "CREATE TABLE {$wpdb->prefix}woocommerce_saving_cards (
            `id` INT AUTO_INCREMENT,
             `registration_id` VARCHAR(255) NOT NULL,
             `customer_id` VARCHAR(255) NOT NULL,
             `mode` int (10) NOT NULL,
             PRIMARY KEY (`id`)
         )  ENGINE=INNODB 
         DEFAULT CHARACTER SET utf8 
         COLLATE utf8_unicode_520_ci;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_raw, true);
    }

    public function get_gateways($gateways){

        return array_merge($gateways , self::$HP_gateways);
    }
}
