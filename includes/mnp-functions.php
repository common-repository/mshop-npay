<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


function mnp_maybe_define_constant( $constant, $value = true ) {
	if ( ! defined( $constant ) ) {
		define( $constant, $value );
	}
}
$mnp_action = get_option( 'mshop-naverpay-button-product', 'woocommerce_after_add_to_cart_button' );
add_action( trim( $mnp_action ), 'MNP_Cart::woocommerce_after_add_to_cart_form', 100 );

$mnp_action = get_option( 'mshop-naverpay-button-cart', 'woocommerce_after_cart' );
add_action( trim( $mnp_action ), 'MNP_Cart::woocommerce_after_cart_table' );


add_action( 'woocommerce_product_options_inventory_product_data', 'MNP_Meta_Box_Product_Data::woocommerce_product_options_inventory_product_data' );

add_filter( 'wc_order_statuses', 'MNP_Order::wc_order_statuses' );
add_action( 'woocommerce_admin_order_items_after_line_items', 'MNP_Order::woocommerce_admin_order_items_after_line_items' );

add_action( 'woocommerce_checkout_create_order_line_item', 'MNP_Order::woocommerce_checkout_create_order_line_item', 10, 4 );
add_filter( 'woocommerce_admin_order_preview_get_order_details', array( 'MNP_Order', 'maybe_update_ship_to_billing' ), 10, 2 );
add_filter( 'msaddr_process_append_address_book', array( 'MNP_Order', 'maybe_skip_append_address_book' ), 10, 2 );

add_filter( 'woocommerce_hidden_order_itemmeta', 'MNP_Order::woocommerce_hidden_order_itemmeta', 10 );
add_filter( 'woocommerce_cancel_unpaid_order', 'MNP_Order::woocommerce_cancel_unpaid_order', 10, 2 );
add_filter( 'woocommerce_order_needs_shipping_address', 'MNP_Order::woocommerce_order_needs_shipping_address', 10, 3 );
add_filter( 'woocommerce_payment_complete_reduce_order_stock', 'MNP_Order::woocommerce_payment_complete_reduce_order_stock', 10, 2 );

add_action( 'woocommerce_after_order_itemmeta', 'MNP_Order_Item::woocommerce_after_order_itemmeta', 10, 3 );

add_filter( 'woocommerce_hidden_order_itemmeta', 'MNP_Sheets::woocommerce_hidden_order_itemmeta', 10 );
add_filter( 'woocommerce_attribute_label', 'MNP_Sheets::woocommerce_attribute_label', 10, 3 );

add_filter( 'mnp_process_sheet_info', 'MNP_Sheets_Npay::mnp_process_sheet_info', 10, 2 );
add_filter( 'mnp_sheet_update_order_status', 'MNP_Sheets_Npay::mnp_sheet_update_order_status', 10, 2 );
add_filter( 'mnp_bulk_ship_order', 'MNP_Sheets_Npay::mnp_bulk_ship_order', 10 );
add_action( 'woocommerce_order_item_meta_end', 'MNP_Myaccount::woocommerce_order_item_meta_end', 10, 3 );
add_filter( 'mnp_search_cart_item_by_cart_item_key', 'MNP_WPA::search_cart_item_by_cart_item_key', 10, 2 );
add_filter( 'mnp_get_product_id_from_cart_item_key', 'MNP_WPA::get_product_id', 10, 3 );
add_filter( 'mnp_generate_product_option_simple', 'MNP_WPA::get_product_option_simple', 10, 2 );
add_filter( 'mnp_generate_product_option_variable', 'MNP_WPA::get_product_option_variable', 10, 2 );
add_filter( 'mnp_callback_option_support', 'MNP_WPA::set_option_support', 10, 2 );
add_filter( 'mnp_callback_option_item', 'MNP_WPA::get_option_item', 10, 2 );
add_filter( 'mnp_search_cart_item_by_cart_item_key', 'MNP_TMEF::search_cart_item_by_cart_item_key', 10, 2 );
add_filter( 'mnp_get_product_id_from_cart_item_key', 'MNP_TMEF::get_product_id', 10, 3 );
add_filter( 'mnp_generate_product_option_simple', 'MNP_TMEF::get_product_option_simple', 10, 2 );
add_filter( 'mnp_generate_product_option_variable', 'MNP_TMEF::get_product_option_variable', 10, 2 );
add_filter( 'mnp_callback_option_support', 'MNP_TMEF::set_option_support', 10, 2 );
add_filter( 'mnp_callback_option_item', 'MNP_TMEF::get_option_item', 10, 2 );
function mnp_process_calculate_customer_point( $flag, $order ) {
	if ( is_a( $order, 'WC_Order' ) && 'naverpay' == $order->get_payment_method() && 'yes' != get_option( 'mnp-enable-earn-point', 'no' ) ) {
		$flag = false;
	}

	return $flag;
}

add_filter( 'msps_process_calculate_point', 'mnp_process_calculate_customer_point', 10, 2 );
function mnp_process_calculate_recommender_point( $flag, $order ) {
	if ( is_a( $order, 'WC_Order' ) && 'naverpay' == $order->get_payment_method() && 'yes' != get_option( 'mnp-enable-earn-recommender-point', 'no' ) ) {
		$flag = false;
	}

	return $flag;
}

add_filter( 'msre_process_calculate_point', 'mnp_process_calculate_recommender_point', 10, 2 );
function mnp_order_shipping_price( $shipping_price, $order ) {
	if ( 'naverpay' == $order->get_payment_method() ) {
		$shipping_price = 0;

		foreach ( $order->get_fees() as $fee ) {
			if ( __( 'NPAY 배송비', 'mshop-npay' ) == $fee->get_name() || __( '도서산간 배송비', 'mshop-npay' ) == $fee->get_name() ) {
				$shipping_price += floatval( $fee->get_amount() );
			}
		}
	}

	return $shipping_price;
}

add_filter( 'msex_order_shipping_price', 'mnp_order_shipping_price', 10, 2 );
function mnp_order_shipping_method( $shipping_method, $order ) {
	if ( 'naverpay' == $order->get_payment_method() ) {
		foreach ( $order->get_fees() as $fee ) {
			if ( __( 'NPAY 배송비', 'mshop-npay' ) == $fee->get_name() || __( '도서산간 배송비', 'mshop-npay' ) == $fee->get_name() ) {
				$shipping_method = __( 'NPAY', 'mshop-npay' );
				break;
			}
		}
	}

	return $shipping_method;
}

add_filter( 'msex_order_shipping_method', 'mnp_order_shipping_method', 10, 2 );

function mnp_ajax_url( $url ) {
	global $sitepress;

	if ( $sitepress && has_filter( 'wpml_object_id' ) ) {
		$url = add_query_arg( 'lang', 'ko', $url );
	}

	return $url;
}
function mnp_admin_notice( $msg, $type = 'success' ) {
	?>
    <div class="notice notice-<?php echo $type; ?>">
        <p><?php echo $msg; ?></p>
    </div>
	<?php
}
function mnp_get( $array, $key, $default = '' ) {
	return ! empty( $array[ $key ] ) ? wc_clean( $array[ $key ] ) : $default;
}
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'mnp_woocommerce_order_item_get_formatted_meta_data', 10, 2 );

function mnp_woocommerce_order_item_get_formatted_meta_data( $formatted_meta, $order_item ) {
	$formatted_meta = array_filter( $formatted_meta, function ( $meta ) {
		return ! in_array( $meta->key, array(
			'_npay_order',
			'_npay_product_order_id',
			'_npay_product_order_status'
		) );
	} );

	return $formatted_meta;
}
add_filter( 'woocommerce_order_items_meta_get_formatted', 'mnp_woocommerce_order_items_meta_get_formatted', 10, 2 );

function mnp_woocommerce_order_items_meta_get_formatted( $formatted_meta, $order_item ) {
	$formatted_meta = array_filter( $formatted_meta, function ( $meta ) {

		return is_object( $meta ) && ! in_array( $meta->key, array(
				'_npay_order',
				'_npay_product_order_id',
				'_npay_product_order_status'
			) );
	} );

	return $formatted_meta;
}

add_filter( 'mshop_sms_order_payment_method_check', 'mnp_payment_method_check', 10, 4 );
function mnp_payment_method_check( $result, $payment_method, $status, $order = null ) {
	if ( 'naverpay' == $payment_method ) {
		if ( ( $order && 'on-hold' == $order->get_status() ) || 'yes' == get_option( 'mnp-block-sms', 'yes' ) ) {
			$result = false;
		}
	}

	return $result;
}

function mnp_get_merchant_custom_code_from_npay_order( $npay_order, $index = 1 ) {
	$merchant_custom_code = array();
	$property             = 'MerchantCustomCode' . $index;

	if ( property_exists( $npay_order->ProductOrder, $property ) ) {
		parse_str( $npay_order->ProductOrder->$property, $merchant_custom_code );
	}

	return $merchant_custom_code;
}

function mnp_get_order_key_from_npay_order( $npay_order ) {
	$merchant_custom_code2 = mnp_get_merchant_custom_code_from_npay_order( $npay_order, '2' );

	if ( ! empty( $merchant_custom_code2['order_key'] ) ) {
		return $merchant_custom_code2['order_key'];
	}

	return null;
}

function mnp_load_saved_cart_contents_from_npay_order( $npay_order ) {
	$cart_contents = array();

	$order_key = mnp_get_order_key_from_npay_order( $npay_order );

	if ( ! empty( $order_key ) ) {
		$cart_contents = mnp_load_saved_cart_contents( $order_key );
	}

	return $cart_contents;
}

function mnp_load_saved_cart_coupons_from_npay_order( $npay_order ) {
	$cart_coupons = array();

	$order_key = mnp_get_order_key_from_npay_order( $npay_order );

	if ( ! empty( $order_key ) ) {
		$cart_coupons = mnp_load_saved_cart_coupons( $order_key );
	}

	return $cart_coupons;
}
function mnp_load_saved_cart_contents_from_order( $order ) {
	if ( 'yes' == get_option( 'mnp-use-cart-management', 'yes' ) ) {
		return $order->get_meta( '_mnp_cart' );
	}
}
function mnp_load_saved_cart_coupons_from_order( $order ) {
	if ( 'yes' == get_option( 'mnp-use-cart-management', 'yes' ) ) {
		return $order->get_meta( '_mnp_coupons_' );
	}
}
function mnp_load_saved_cart_contents( $order_key ) {
	return get_transient( 'mnp_' . $order_key );
}
function mnp_load_saved_cart_coupons( $order_key ) {
	return get_transient( 'mnp_coupons_' . $order_key );
}
function mnp_array_to_object( $d ) {
	if ( is_array( $d ) ) {
		return (object) array_map( __FUNCTION__, $d );
	} else {
		return $d;
	}
}
function mnp_maybe_block_send_email( $bool, $atts ) {
    if( 'npay-guest@npay-guest.com' == $atts['to'] ) {
        $bool = true;
    }

    return $bool;
}
add_filter( 'pre_wp_mail', 'mnp_maybe_block_send_email', 10, 2 );