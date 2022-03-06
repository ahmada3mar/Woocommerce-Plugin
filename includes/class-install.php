<?php
class hyperpay_main
{
	/**
	 * Payment gateway classes.
	 * all new class should added here
	 * @var array
	 */
    protected static $HP_gateways = [
        // Add Class Here
        'WC_Hyperpay_Gateway',
        'WC_Hyperpay_STCPay_Gateway',
        'WC_Hyperpay_Mada_Gateway',
        'WC_Hyperpay_ApplePay_Gateway'
    ] ;

    /**
	 * First function fire when click on active plugin
     * 
	 * @return void
	 */

    public function load()
    {
        foreach(self::$HP_gateways as $names ){ // <== looping over all regestered class in $HP_gateways array
            include_once HYPERPAY_ABSPATH . "gateways/$names.php";
        }

        /**
         * this filter documented in woocommerce to asign all gateways to [payments tab] insude woocommerce settings
         * 
         * @param string filter_name 
         * @param array[class_name,function_name]
         * @return void
         */

        add_filter('woocommerce_payment_gateways', ['hyperpay_main', 'get_gateways']);
        
    }

    /**
     * CREATE tabe on database to store users transaction mode
     */
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
