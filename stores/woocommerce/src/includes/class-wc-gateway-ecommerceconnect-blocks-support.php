<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_eCommerceConnect_Blocks_Support extends AbstractPaymentMethodType {

	protected $name = 'ecommerceconnect';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_ecommerceconnect_settings', array() );
	}

	public function is_active() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();

		return $payment_gateways['ecommerceconnect']->is_available();
	}

	public function get_payment_method_script_handles() {
		$asset_path   = WC_GATEWAY_ECOMMERCECONNECT_PATH . '/build/index.asset.php';
		$version      = WC_GATEWAY_ECOMMERCECONNECT_VERSION;
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'wc-ecommerceconnect-blocks-integration',
			WC_GATEWAY_ECOMMERCECONNECT_URL . '/build/index.js',
			$dependencies,
			$version,
			true
		);
		wp_set_script_translations(
			'wc-ecommerceconnect-blocks-integration',
			'woocommerce-gateway-ecommerceconnect'
		);
		return array( 'wc-ecommerceconnect-blocks-integration' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'logo_url'    => WC_GATEWAY_ECOMMERCECONNECT_URL . '/assets/images/icon.svg',
		);
	}

	public function get_supported_features() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways['ecommerceconnect']->supports;
	}
}