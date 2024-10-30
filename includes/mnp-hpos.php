<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

final class MNP_HPOS {
	protected static $hpos_enabled = null;
	static function init() {
	}
	static function enabled() {
		if ( is_null( self::$hpos_enabled ) ) {
			self::$hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ? OrderUtil::custom_orders_table_usage_is_enabled() : false;
		}

		return self::$hpos_enabled;
	}
	static function get_order( $order ) {
		if ( is_int( $order ) ) {
			return wc_get_order( $order );
		} else if ( $order instanceof WP_Post ) {
			return wc_get_order( $order->ID );
		}

		return $order;
	}
	static function get_order_type( $order_id ) {
		return self::enabled() ? OrderUtil::get_order_type( $order_id ) : get_post_type( $order_id );
	}
	static function get_shop_order_screen( $order_type = 'shop_order' ) {
		return MNP_HPOS::enabled() ? wc_get_page_screen_id( str_replace( '_', '-', $order_type ) ) : $order_type;
	}
}

MNP_HPOS::init();