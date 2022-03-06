<?php

/**
 * Plugin Name: WooCommerce HyperPay Payments
 * Plugin URI:
 * Description: Hyperpay is the first one stop-shop service company for online merchants in MENA Region.<strong>If you have any question, please <a href="http://www.hyperpay.com/" target="_new">contact Hyperpay</a>.</strong>
 * Version:     1.7
 * Author:      Hyperpay Team
 * Author URI:  https://www.hyperpay.com
 * Requires at least: 5.3
 * Requires PHP: 7.1
 * WC requires at least: 3.0.9
 * WC tested up to: 6.2.1
 * 
 */


if (!function_exists('add_settings_error')) {
    require_once ABSPATH . '/wp-admin/includes/template.php';
}


if (!defined('HYPERPAY_PLUGIN_FILE')) {
    define('HYPERPAY_PLUGIN_FILE', __FILE__);
}

if (!defined('HYPERPAY_PLUGIN_DIR')) {

    define('HYPERPAY_PLUGIN_DIR', untrailingslashit(plugins_url('/', HYPERPAY_PLUGIN_FILE)));
}

if (!defined('HYPERPAY_ABSPATH')) {
    define('HYPERPAY_ABSPATH', dirname(HYPERPAY_PLUGIN_FILE) . '/');
}

if (!class_exists('hyperpay_main', false)) {

    include_once dirname(HYPERPAY_PLUGIN_FILE) . '/includes/class-install.php';
}
wp_enqueue_style('hyperpay_custom_style', HYPERPAY_PLUGIN_DIR . '/assets/css/style.css');

if (str_starts_with(get_locale(), 'ar'))
    wp_enqueue_style('hyperpay_custom_style', HYPERPAY_PLUGIN_DIR . '/assets/css/style-rtl.css');


/**
 * Initialize the plugin and its modules.
 */

add_action('plugins_loaded',  ['hyperpay_main', 'load']);
