<?php

/**
 * Plugin Name: Hyperpay Payment Gateway plugin for WooCommerce
 * Plugin URI:
 * Description: Hyperpay is the first one stop-shop service company for online merchants in MENA Region.<strong>If you have any question, please <a href="http://www.hyperpay.com/" target="_new">contact Hyperpay</a>.</strong>
 * Version: 1.7
 * Author: Hyperpay Team
 * Author URI: https://www.hyperpay.com
 * Requires at least: 5.6
 * Requires PHP: 7.0
 *
 */


if (!defined('HYPERPAY_PLUGIN_FILE')) {
    define('HYPERPAY_PLUGIN_FILE', __FILE__);
}

if (!defined('HYPERPAY_ABSPATH')) {
    define('HYPERPAY_ABSPATH', dirname(HYPERPAY_PLUGIN_FILE) . '/');
}

if (!class_exists('hyperpay_main', false)) {

    include_once dirname(HYPERPAY_PLUGIN_FILE) . '/includes/class-install.php';
}

$ii = new hyperpay_main();
print_r($ii->load());
die;
register_activation_hook(__FILE__, ['hyperpay_main', 'run_migration']);
add_action('plugins_loaded',  ['hyperpay_main', 'load']);



// add_action('plugins_loaded', ['DBSchema' , 'hyperpay_init_gateways_class'] );





