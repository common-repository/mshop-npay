<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class MNP_Admin_Post_types {

	public static function init() {

		if ( MNP_HPOS::enabled() ) {
			add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'add_bulk_actions' ) );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_columns' ), 999, 2 );
			add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );

			add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'mnp_restrict_manage_posts' ) );
		} else {
			add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'add_bulk_actions' ) );
			add_action( 'load-edit.php', array( __CLASS__, 'do_bulk_action' ), 999 );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_columns' ), 999, 2 );

			add_action( 'restrict_manage_posts', array( __CLASS__, 'mnp_restrict_manage_posts' ), 30 );
		}
	}
	static function render_columns( $column, $object ) {
		if ( 'order_number' == $column ) {
			$order = MNP_HPOS::get_order( $object );

			if ( $order && 'naverpay' == $order->get_payment_method() ) {
				echo '<div style="clear: both;" class="npay-logo"></div>';
			}
		}
	}
	static function mnp_restrict_manage_posts( $order_type = '' ) {
		global $typenow;

		if ( ! MNP_HPOS::enabled() ) {
            $order_type = $typenow;
        }

		if ( in_array( $order_type, wc_get_order_types( 'order-meta-boxes' ) ) ) {
			$paymethod        = isset( $_REQUEST['paymethod'] ) ? wc_clean( wp_unslash( $_REQUEST['paymethod'] ) ) : '';
			$payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();

			echo '<select name="paymethod">';
			printf( '<option value="" %s>모든 결제수단</option>', $paymethod == '' ? 'selected' : '' );
			foreach ( $payment_gateways as $payment_gateway ) {
				printf( '<option value="%s" %s>%s</option>', $payment_gateway->id, $paymethod == $payment_gateway->id ? 'selected' : '', $payment_gateway->title );
			}
			printf( '<option value="naverpay" %s>NPay</option>', $paymethod == 'naverpay' ? 'selected' : '' );

			echo '<select>';

			$naverpay_order_id = isset( $_REQUEST['naverpay_order_id'] ) ? wc_clean( wp_unslash( $_REQUEST['naverpay_order_id'] ) ) : '';
			?>
            <input name="naverpay_order_id" value="<?php echo $naverpay_order_id; ?>" placeholder="(Product)Order ID">
			<?php
		}
	}
	static function add_bulk_actions( $actions ) {
		if ( MNP_HPOS::enabled() ) {
			$actions = array_merge(
				array( 'npay_place-product-order' => __( '발주확인 (NPay)', 'mshop-npay' ) ),
				$actions
			);
		} else {
			$actions['npay_place-product-order'] = __( '발주확인 (NPay)', 'mshop-npay' );
		}

		return $actions;
	}
	static function handle_bulk_actions( $redirect_to, $report_action, $ids ) {
		if ( 'npay_place-product-order' === $report_action ) {
			$action = substr( $report_action, 5 ); // get the status name from action

			$changed = 0;

			$order_ids = array_map( 'absint', $ids );

			if ( 'place-product-order' == $action ) {
				$changed = MNP_Order::bulk_action_place_product_order( $order_ids );
			}

			return add_query_arg(
				array(
					'bulk_action' => $report_action,
					'changed'     => $changed,
					'ids'         => implode( ',', $ids ),
				),
				$redirect_to
			);
		}
	}
	static function do_bulk_action() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		// Bail out if this is not a status-changing action
		if ( strpos( $action, 'npay_' ) === false ) {
			return;
		}

		$action = substr( $action, 5 ); // get the status name from action

		$changed = 0;

		$post_ids = array_map( 'absint', (array) $_REQUEST['post'] );

		if ( 'place-product-order' == $action ) {
			$changed = MNP_Order::bulk_action_place_product_order( $post_ids );
		}

		$sendback = add_query_arg( array( 'post_type' => 'shop_order', $action => true, 'changed' => $changed, 'ids' => join( ',', $post_ids ) ), '' );

		if ( isset( $_GET['post_status'] ) ) {
			$sendback = add_query_arg( 'post_status', sanitize_text_field( $_GET['post_status'] ), $sendback );
		}

		wp_redirect( esc_url_raw( $sendback ) );
		exit();
	}
}

MNP_Admin_Post_types::init();
