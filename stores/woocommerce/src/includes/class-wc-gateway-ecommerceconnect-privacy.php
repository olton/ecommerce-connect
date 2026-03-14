<?php

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Gateway_eCommerceConnect_Privacy extends WC_Abstract_Privacy {

	public function __construct() {
		parent::__construct( __( 'eCommerceConnect', 'woocommerce-gateway-ecommerceconnect') );

		$this->add_exporter( 'woocommerce-gateway-ecommerceconnect-order-data', __( 'WooCommerce eCommerceConnect Order Data', 'woocommerce-gateway-ecommerceconnect' ), array( $this, 'order_data_exporter' ) );

		$this->add_eraser( 'woocommerce-gateway-ecommerceconnect-order-data', __( 'WooCommerce eCommerceConnect Data', 'woocommerce-gateway-ecommerceconnect' ), array( $this, 'order_data_eraser' ) );
	}

	protected function get_ecommerceconnect_orders( $email_address, $page ) {
		
		$user = get_user_by( 'email', $email_address );

		$order_query = array(
			'payment_method' => 'ecommerceconnect',
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	public function get_privacy_message() {

		return wpautop(
			sprintf(
                // translators: %1$s and %2$s are opening and closing anchor tags.
				esc_html__( 'By using this extension, you may be storing personal data or sharing data with an external service. %1$sLearn more about how this works, including what you may want to include in your privacy policy.%2$s', 'woocommerce-gateway-ecommerceconnect' ),
				'<a href="https://upc.ua" target="_blank" rel="noopener noreferrer">',
				'</a>'
			)
		);
	}

	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_ecommerceconnect_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => esc_attr__( 'Orders', 'woocommerce-gateway-ecommerceconnect' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => esc_attr__( 'eCommerceConnect token', 'woocommerce-gateway-ecommerceconnect' ),
							'value' => $order->get_meta( '_ecommerceconnect_pre_order_token', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	public function order_data_eraser( $email_address, $page ) {

		$orders = $this->get_ecommerceconnect_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );
		}


		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	protected function maybe_handle_order( $order ) {
		$ecommerceconnect_token = $order->get_meta( '_ecommerceconnect_pre_order_token', true );

		if ( empty( $ecommerceconnect_token ) ) {
			return array( false, false, array() );
		}

		$order->delete_meta_data( '_ecommerceconnect_pre_order_token' );
		$order->save_meta_data();

		return array( true, false, array( esc_html__( 'eCommerceConnect Order Data Erased.', 'woocommerce-gateway-ecommerceconnect' ) ) );
	}
}

new WC_Gateway_eCommerceConnect_Privacy();