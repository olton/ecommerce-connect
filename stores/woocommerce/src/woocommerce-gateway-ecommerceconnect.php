<?php

/**
 * Plugin Name: eCommerceConnect Gateway
 * Plugin URI: https://ecconnect.upc.ua
 * Description: UPC WooCommerce plugin enables you to easily accept payments through your Woocommerce store
 * Author: upc.ua
 * Author URI: https://upc.ua
 * Version: 8.4.1
 * Text Domain: woocommerce-gateway-ecommerceconnect
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 6.4
 * WC requires at least: 8.4
 * WC tested up to: 8.6
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 */
defined('ABSPATH') || exit;

define('WC_GATEWAY_ECOMMERCECONNECT_VERSION', '8.4.1');
define('WC_GATEWAY_ECOMMERCECONNECT_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WC_GATEWAY_ECOMMERCECONNECT_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

function woocommerce_ecommerceconnect_load_textdomain()
{
    $domain = 'woocommerce-gateway-ecommerceconnect';
    $relative_lang_dir = dirname(plugin_basename(__FILE__)) . '/languages/';

    load_plugin_textdomain($domain, false, $relative_lang_dir);

    $locale = determine_locale();
    $short_locale = preg_replace('/[_-].*$/', '', (string) $locale);

    // Support short-locale translation files (for example "de.mo") when WordPress locale is regional (for example "de_DE").
    if (!$short_locale || $short_locale === $locale) {
        return;
    }

    $short_locale_mofile = plugin_dir_path(__FILE__) . 'languages/' . $domain . '-' . $short_locale . '.mo';
    if (is_readable($short_locale_mofile)) {
        load_textdomain($domain, $short_locale_mofile);
    }
}

function woocommerce_ecommerceconnect_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    woocommerce_ecommerceconnect_load_textdomain();

    require_once plugin_basename('includes/class-wc-gateway-ecommerceconnect.php');
    require_once plugin_basename('includes/class-wc-gateway-ecommerceconnect-privacy.php');

    add_filter('woocommerce_payment_gateways', 'woocommerce_ecommerceconnect_add_gateway');
}

add_action('plugins_loaded', 'woocommerce_ecommerceconnect_init', 0);

function woocommerce_ecommerceconnect_plugin_links($links)
{
    $settings_url = add_query_arg(
        array(
            'page' => 'wc-settings',
            'tab' => 'checkout',
            'section' => 'wc_gateway_ecommerceconnect',
        ),
        admin_url('admin.php')
    );

    $plugin_links = array(
        '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'woocommerce-gateway-ecommerceconnect') . '</a>',
        '<a href="https://ecconnect.upc.ua">' . esc_html__('Support', 'woocommerce-gateway-ecommerceconnect') . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_ecommerceconnect_plugin_links');

function woocommerce_ecommerceconnect_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_eCommerceConnect';

    return $methods;
}

add_action('woocommerce_blocks_loaded', 'woocommerce_ecommerceconnect_woocommerce_blocks_support');

function woocommerce_ecommerceconnect_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once WC_GATEWAY_ECOMMERCECONNECT_PATH . '/includes/class-wc-gateway-ecommerceconnect-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_eCommerceConnect_Blocks_Support());
            }
        );
    }
}

function woocommerce_ecommerceconnect_declare_feature_compatibility()
{
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__
        );

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'product_block_editor',
            __FILE__
        );
    }
}

add_action('before_woocommerce_init', 'woocommerce_ecommerceconnect_declare_feature_compatibility');

function woocommerce_ecommerceconnect_missing_wc_notice()
{
    if (class_exists('WooCommerce')) {
        return;
    }

    echo '<div class="error"><p><strong>';
    printf(
        // translators: %s is the anchor tag link to WooCommerce download page.
        esc_html__('WooCommerce eCommerceConnect Gateway requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-ecommerceconnect'),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    );
    echo '</strong></p></div>';
}

add_action('admin_notices', 'woocommerce_ecommerceconnect_missing_wc_notice');

add_action('admin_enqueue_scripts', function ($hook) {
    $is_order_edit = isset($_GET['page'], $_GET['action']) &&
        $_GET['page'] === 'wc-orders' &&
        $_GET['action'] === 'edit';

    $is_gateway_settings = isset($_GET['page'], $_GET['tab'], $_GET['section']) &&
        $_GET['page'] === 'wc-settings' &&
        $_GET['tab'] === 'checkout' &&
        in_array($_GET['section'], ['ecommerceconnect', 'wc_gateway_ecommerceconnect'], true);

    if ($is_order_edit) {
        wp_enqueue_script(
            'ecommconnect-admin-capture',
            plugin_dir_url(__FILE__) . 'assets/js/admin-order-form.js',
            ['jquery'],
            WC_GATEWAY_ECOMMERCECONNECT_VERSION,
            true
        );

        wp_localize_script('ecommconnect-admin-capture', 'ecommconnect_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecommconnect_capture_action'),
        ]);
    }

    if ($is_gateway_settings) {
        wp_enqueue_style(
            'ecommconnect-admin-settings',
            plugin_dir_url(__FILE__) . 'assets/css/admin-settings.css',
            [],
            WC_GATEWAY_ECOMMERCECONNECT_VERSION
        );
    }
});
add_action('wp_ajax_ecommconnect_capture', 'ecommconnect_handle_capture_ajax_callback');

function ecommconnect_handle_capture_ajax_callback()
{
    check_ajax_referer('ecommconnect_capture_action', 'security');

    $order_id = absint($_POST['order_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'ecommerceconnect') {
        wp_send_json_error(['message' => 'Order not found or wrong payment method']);
    }

    $gateways = WC()->payment_gateways->get_available_payment_gateways();

    if (isset($gateways['ecommerceconnect'])) {
        $gateway = $gateways['ecommerceconnect'];
        $gateway->ajax_capture_order($order, $amount);
    } else {
        wp_send_json_error(['message' => 'Payment gateway not available']);
    }
}

add_action('template_redirect', function () {
    if (
        is_order_received_page() &&
        isset($_GET['oid'], $_GET['sig'])
    ) {
        $order_id = absint($_GET['oid']);
        $provided_sig = sanitize_text_field($_GET['sig']);

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        if (!$order || $order->get_payment_method() !== 'ecommerceconnect') {
            return;
        }

        $gateways = WC()->payment_gateways->get_available_payment_gateways();

        if (isset($gateways['ecommerceconnect'])) {
            $gateway = $gateways['ecommerceconnect'];

            $sd = $order->get_meta('_upc_session_token');
            if (!$sd) {
                return;
            }

            $gateway->restore_session_from_sig($order, $sd, $provided_sig);
        } else {
            return;
        }
    }
});
