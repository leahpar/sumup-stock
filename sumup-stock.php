<?php

/*
Plugin Name: Sumup Stock
Plugin URI: https://github.com/leahpar/sumup-stock
Description: Plugin pour mettre à jour le stock de produits dans WooCommerce à partir des ventes Sumup.
Version: 0.4.0
Author: Raphaël Bacco
Author URI: https://github.com/leahpar
License: MIT
*/

// URL de check de nouvelle version
const SUMUP_STOCK_JSON_URL = 'https://raw.githubusercontent.com/leahpar/sumup-stock/master/info.json';

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The code that runs during plugin activation.
 */
function activate_sumup_stock() {
    require_once plugin_dir_path( __FILE__ ) . 'src/SumupStockActivator.php';
    SumupStockActivator::activate();
}
register_activation_hook( __FILE__, 'activate_sumup_stock');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_sumup_stock() {
    require_once plugin_dir_path( __FILE__ ) . 'src/SumupStockActivator.php';
    SumupStockActivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_sumup_stock');

/**
 * The core plugin class.
 */
require plugin_dir_path( __FILE__ ) . 'src/SumupStockCore.php';
$plugin = new SumupStockCore();
$plugin->init();


/**
 * Handle a custom query var to get products with meta.
 *
 * Usage : $products = wc_get_products(['customvar' => 'somevalue']);
 *
 * https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query#adding-custom-parameter-support
 * @param array $query - Args for WP_Query.
 * @param array $query_vars - Query vars from WC_Product_Query.
 * @return array modified $query
 */
function sumupstock_handle_product_name_query_var(array $query, array $query_vars): array
{
    if (!empty($query_vars['reference-sumup'])) {
        $query['meta_query'][] = [
            'key' => '_product_attributes',
            'value' => '"'.esc_attr($query_vars['reference-sumup']).'"',
            'compare' => 'LIKE'
        ];
    }
    return $query;
}
add_filter('woocommerce_product_data_store_cpt_get_products_query', 'sumupstock_handle_product_name_query_var', 10, 2);

function sumupstock_handle_wcorder_query_var($query, $query_vars)
{
    if (!empty($query_vars['sumup_transaction'])) {
        $query['meta_query'][] = [
            'key' => 'sumup_transaction',
            'value' => esc_attr($query_vars['sumup_transaction']),
            'compare' => '='
        ];
    }
    return $query;
}
add_filter('woocommerce_order_data_store_cpt_get_orders_query', 'sumupstock_handle_wcorder_query_var', 10, 2);
