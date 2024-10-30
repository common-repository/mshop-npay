<?php



if ( ! class_exists( 'MNP_WPA' ) ) {
	class MNP_WPA {
		public static function search_cart_item_by_cart_item_key( $flag, $cart_item ) {
			return ! empty( $cart_item['addons'] ) ? true : $flag;
		}
		public static function get_product_id( $product_id, $cart_item_key, $cart_item ) {
			if ( ! empty( $cart_item['addons'] ) ) {
				$product_id = strtoupper( substr( $cart_item_key, 0, 6 ) );
			}

			return $product_id;
		}
		public static function get_product_option_simple( $option, $args ) {
			$quantity  = $args['quantity'];
			$cart_item = ! empty( $args['cart_item'] ) ? $args['cart_item'] : null;

			if ( ! is_null( $cart_item ) && ! empty( $cart_item['addons'] ) ) {
				$single        = null;
				$selectedItems = array ();

				foreach ( $cart_item['addons'] as $addon ) {
					if( ! empty( $addon['name'] ) ) {
						$selectedItems[] = new ProductOptionSelectedItem( ProductOptionSelectedItem::TYPE_INPUT, $addon['name'], $addon['name'], $addon['value'] );
					}
				}

				$option = new ProductOption( $quantity, 0, null, $selectedItems );
			}

			return $option;
		}
		public static function get_product_option_variable( $selected_items, $args ) {
			$cart_item = ! empty( $args['cart_item'] ) ? $args['cart_item'] : null;

			if ( ! is_null( $cart_item ) && ! empty( $cart_item['addons'] ) ) {

				foreach ( $cart_item['addons'] as $addon ) {
					if( ! empty( $addon['name'] ) ) {
						$selected_items[] = new ProductOptionSelectedItem( ProductOptionSelectedItem::TYPE_INPUT, $addon['name'], $addon['name'], $addon['value'] );
					}
				}
			}

			return $selected_items;
		}
		public static function set_option_support( $option_support, $cart_content ) {
			if ( ! empty( $cart_content['addons'] ) ) {
				$option_support = true;
			}

			return $option_support;
		}
		public static function get_option_item( $option_item, $cart_content ) {
			if ( ! empty( $cart_content['addons'] ) ) {
				if ( ! is_array( $option_item ) ) {
					$option_item = array ();
				}

				foreach ( $cart_content['addons'] as $addon ) {
					$option_item[] = new ProductOptionItem( ProductOptionItem::TYPE_INPUT, $addon['name'], $addon['value'] );
				}
			}

			return $option_item;
		}
	}
}

