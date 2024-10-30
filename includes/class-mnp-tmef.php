<?php



if ( ! class_exists( 'MNP_TMEF' ) ) {
	class MNP_TMEF {
		public static function search_cart_item_by_cart_item_key( $flag, $cart_item ) {
			return ! empty( $cart_item['tmdata'] ) ? true : $flag;
		}
		public static function get_product_id( $product_id, $cart_item_key, $cart_item ) {
			if ( ! empty( $cart_item['tmdata'] ) ) {
				$product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			}

			return $product_id;
		}
		public static function get_product_option_simple( $option, $args ) {
			$quantity  = $args['quantity'];
			$cart_item = ! empty( $args['cart_item'] ) ? $args['cart_item'] : null;

			if ( ! is_null( $cart_item ) && ! empty( $cart_item['tmcartepo'] ) ) {
				$single        = null;
				$selectedItems = array ();

				foreach ( $cart_item['tmcartepo'] as $item ) {

					if( ! empty( $item['quantity'] ) && intval( $item['quantity'] ) ) {
						$value = sprintf( '%s x %d', $item['value'], $item['quantity'] );
					} else {
						$value = $item['value'];
					}

					$selectedItems[] = new ProductOptionSelectedItem( ProductOptionSelectedItem::TYPE_INPUT, $item['name'], $item['name'], $value );
				}

				$option = new ProductOption( $quantity, 0, null, $selectedItems );
			}

			return $option;
		}
		public static function get_product_option_variable( $selected_items, $args ) {
			$cart_item = ! empty( $args['cart_item'] ) ? $args['cart_item'] : null;

			if ( ! is_null( $cart_item ) && ! empty( $cart_item['tmcartepo'] ) ) {

				foreach ( $cart_item['tmcartepo'] as $item ) {
					if( ! empty( $item['name'] ) ) {
						$selected_items[] = new ProductOptionSelectedItem( ProductOptionSelectedItem::TYPE_INPUT, $item['name'], $item['name'], $item['value'] );
					}
				}
			}

			return $selected_items;
		}
		public static function set_option_support( $option_support, $cart_content ) {
			if ( ! empty( $cart_content['tmcartepo'] ) ) {
				$option_support = true;
			}

			return $option_support;
		}
		public static function get_option_item( $option_item, $cart_content ) {
			if ( ! empty( $cart_content['tmcartepo'] ) ) {
				if ( ! is_array( $option_item ) ) {
					$option_item = array ();
				}

				foreach ( $cart_content['tmcartepo'] as $item ) {
					$option_item[] = new ProductOptionItem( ProductOptionItem::TYPE_INPUT, $item['name'], $item['value'] );
				}
			}

			return $option_item;
		}
	}
}

