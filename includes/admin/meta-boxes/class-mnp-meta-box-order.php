<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MNP_Meta_Box_Order {
	public static function add_meta_boxes( $post_type, $post ) {
		$order = MNP_HPOS::get_order( $post );

		if ( is_a( $order, 'WC_Order' ) ) {
			if ( MNP_Manager::PAYMENT_GATEWAY_NAVERPAY == $order->get_payment_method() ) {
				add_meta_box(
					'mnp-npay-order-info',
					__( '<div class="npay-logo"></div>&nbsp;', 'mshop-npay' ),
					array( __CLASS__, 'output_npay_order_info' ),
					MNP_HPOS::get_shop_order_screen(),
					'side',
                    'high'
				);
			}
		}
	}

	public static function output_npay_order_info( $post ) {
		$order = MNP_HPOS::get_order( $post );

		$dependencies = array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-widget',
			'jquery-ui-mouse',
			'jquery-ui-position',
			'jquery-ui-draggable',
			'jquery-ui-resizable',
			'jquery-ui-button',
			'jquery-ui-dialog',
		);
		wp_register_script( 'naverpay-admin-order', MNP()->plugin_url() . '/assets/js/admin-order-wc-35.js', array( 'jquery', 'underscore' ), MNP_VERSION );
		wp_localize_script( 'naverpay-admin-order', 'naverpay_admin_order', array(
			'ajax_url'         => admin_url( 'admin-ajax.php', 'relative' ),
			'order_id'         => $order->get_id(),
			'product_order_id' => $order->get_meta( '_naverpay_product_order_id' ),
			'slug'             => MNP()->slug(),
			'order_action'     => MNP()->slug() . '-order_action'
		) );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script( 'naverpay-admin-order' );
		wp_enqueue_script( 'jquery-block-ui', MNP()->plugin_url() . '/assets/js/jquery.blockUI.js', $dependencies );

		wp_enqueue_style( 'naverpay-admin', MNP()->plugin_url() . '/assets/css/naverpay-admin.css' );

		$order_info = $order->get_meta( '_npay_order' );

		if ( empty( $order_info ) ) {
			$order_items = $order->get_items();

			foreach ( $order_items as $item_id => $item ) {
				if ( ! empty( $item['npay_order'] ) ) {
					$product_order_info = json_decode( $item['npay_order'] );
					$order_info         = $product_order_info->Order;

					break;
				}
			}
		}

		if ( ! empty( $order_info ) ) {
			include( 'views/html-order-info-wc.php' );
		}

		?>
        <div class="button-wrapper">
            <button class="button button-primary refresh-npay-order"><?php _e( '주문정보 새로고침', 'mshop-npay' ); ?></button>
        </div>
		<?php
	}
}
