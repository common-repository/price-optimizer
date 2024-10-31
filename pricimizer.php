<?php
/*
 * Plugin Name:       Pricimizer
 * Plugin URI:        https://pricimizer.com
 * Description:       Revolutionize your pricing strategy with this game changing dynamic pricing plugin for maximum profitability
 * Version:           1.2.4
 * Author:            Pricimizer
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pricimizer-woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('PRICIMIZER_URL', plugin_dir_url(__FILE__));
define('PRICIMIZER_PATH', plugin_dir_path(__FILE__));
define('PRICIMIZER_PLUGIN', plugin_basename(__FILE__));
define('PRICIMIZER_VERSION', 4);
define('PRICIMIZER_PLUGIN_NAME', 'Pricimizer');

require_once 'includes/Pricimizer_Helper.php';
require_once 'includes/Pricimizer_Cache.php';
require_once 'includes/Pricimizer.php';
new Pricimizer();

function pricimizer_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pricimizer_price_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
             id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
             product_id BIGINT UNSIGNED NOT NULL,
             user_id BIGINT UNSIGNED NULL,
             ip VARCHAR(150) NULL,
             price VARCHAR(150) NOT NULL,
             created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
             PRIMARY KEY  (id),
             UNIQUE KEY user_id_ip_product (ip, user_id, product_id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Request API key (Auto register)
    $email = get_option('admin_email');
    if (!$email) {
        $email = get_option('woocommerce_email_from_address');
    }
    $args = [
        'timeout'     => '15',
        'redirection' => '5',
        'blocking'    => true,
        'sslverify' => false,
        'headers'     => [
            'Accept' => 'application/json',
            'Referer' => home_url()
        ],
        'body' => [
            'email' => $email
        ]
    ];
    $response = wp_remote_post('https://pricimizer.com/api/v1/register', $args);
    $response = wp_remote_retrieve_body($response);
    $response = json_decode($response, true);

    if (isset($response['success']) && $response['success']) {
        // successful
        if (!empty($response['data']['key'])) {
            update_option('pricimizer_global_settings', [
                'api_key' => $response['data']['key'],
                'api_key_validity' => 'valid',
                'profit_margin' => 20,
                'optimize_by' => [],
            ]);
        }
    }
}
register_activation_hook(__FILE__, 'pricimizer_install');
