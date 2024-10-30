<?php



if ( ! class_exists( 'MNP_Cart' ) ) {
	class MNP_Cart {

		static $shippingPolicy = null;

		static function get_order_key() {
			$data = array(
				MNP_Manager::merchant_id(),
				date( 'Y-m-d H:i:s' ),
				strtoupper( bin2hex( openssl_random_pseudo_bytes( 20 ) ) )
			);

			return date( 'YmdHis' ) . '_' . strtoupper( md5( json_encode( $data ) ) );
		}

		static function cart_contains_npay_items() {
			$support_product_types = apply_filters( 'mnp_support_product_types', array( 'variable', 'variation' ) );

			if( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

					if ( $values['variation_id'] ) {
						$product_id = $values['variation_id'];
						$variation  = $values['variation'];
					} else {
						$product_id = $values['product_id'];
						$variation  = null;
					}

					$wc_product = wc_get_product( $product_id );

					if ( MNP_Manager::is_purchasable( $values['product_id'] ) && MNP_Manager::is_purchasable( $product_id ) && $wc_product->is_in_stock() && $wc_product->has_enough_stock( $values['quantity'] ) && $wc_product->is_purchasable() && ! $wc_product->is_virtual() &&
					     ( in_array( $wc_product->get_type(), $support_product_types ) || ( $wc_product->is_type( 'simple' ) && $wc_product->get_price() > 0 ) )
					) {
						return true;
					}
				}
			}

			return false;
		}
		static function generate_product_info( $args ) {
			$product_id          = apply_filters( 'mnp_get_product_id_from_cart_item_key', $args['product_id'], $args['cart_item_key'], $args['cart_item'] );
			$merchant_product_id = $args['product_id'];
			$quantity            = $args['quantity'];
			$variation           = $args['variations'];
			$line_total          = $args['line_total'];
			$line_tax            = $args['line_tax'];

			$wc_product = wc_get_product( $merchant_product_id );
			$tax = 'TAX';
			if ( wc_tax_enabled() ) {
				if ( $line_tax <= 0 ) {
					$tax = 'ZERO_TAX';
				}

				$line_total = absint( round( $line_total + $line_tax, wc_get_price_decimals() ) );
			}

			$unit_price = $line_total / $quantity;
			if ( $wc_product->is_type( 'simple' ) ) {
				// 단순상품 정보를 생성한다.
				$single = null;
				$option = apply_filters( 'mnp_generate_product_option_simple', null, $args );

				if ( empty( $option ) ) {
					$single = new ProductSingle( $quantity );
					$option = null;
				}
			} else if ( $wc_product->is_type( 'variation' ) ) {
				// 옵션상품 정보를 생성한다.
				$single        = null;
				$selectedItems = array();

				if ( ! empty( $variation ) ) {
					$attributes = $variation;
				} else {
					$attributes = $wc_product->get_variation_attributes();
				}

				foreach ( $attributes as $key => $value ) {
					$option_id   = str_replace( 'attribute_', '', $key );
					$option_name = html_entity_decode( wc_attribute_label( $option_id ) );
					$term        = get_term_by( 'slug', $value, $option_id );
					$option_text = html_entity_decode( $term->name );

					$selectedItems[] = new ProductOptionSelectedItem( ProductOptionSelectedItem::TYPE_SELECT, $option_name, $term->slug, $option_text );
				}

				$selectedItems = apply_filters( 'mnp_generate_product_option_variable', $selectedItems, $args );

				$option = new ProductOption( $quantity, 0, null, $selectedItems );
			} else {
				$single = apply_filters( 'mnp_generate_product_info_single', null, $args );
				$option = apply_filters( 'mnp_generate_product_option_simple', null, $args );
			}

			$img_url = '';
			$images  = wp_get_attachment_image_src( $wc_product->get_image_id(), array( 300, 300 ) );

			if ( ! empty( $images ) ) {
				$img_url = $images[0];
				if ( empty( $img_url ) && ! empty( $args['parent_product_id'] ) ) {
					$parent_product = wc_get_product( $args['parent_product_id'] );
					$images         = wp_get_attachment_image_src( $parent_product->get_image_id(), array( 300, 300 ) );
					if ( ! empty( $images ) ) {
						$img_url = $images[0];
					}
				}
				if ( 'yes' == get_option( 'mnp-force-image-url-to-http', 'yes' ) ) {
					$img_url = preg_replace( "/^https:/i", "http:", $img_url );
				}
			}

			$img_url = apply_filters( 'mnp_product_image_url', $img_url, $product_id );

			if ( empty( $img_url ) ) {
				wp_send_json_error( array( 'message' => '상품 이미지가 없습니다.' ) );
			}
			$product_id = apply_filters( 'mnp_product_id', $product_id, $args );
			$merchant_product_id = apply_filters( 'mnp_merchant_product_id', $merchant_product_id, $args );

			$supplements = apply_filters( 'mnp_supplements', array(), $args );

			$gifts = apply_filters( 'mnp_get_gifts_from_cart_item', array(), $args['cart_item'] );
			return new Product(
				$product_id, /** 상품 번호 */
				$merchant_product_id, /** 가맹점 상품 번호 */
				apply_filters( 'mnks_ecmall_product_id', null, $product_id ), /** 지식쇼핑 EP의 Mall_pid */
				html_entity_decode( $wc_product->get_title() ), /** 상품명 */
				$unit_price, /** 상품가격 */
				$tax, /** 세금종류 */
				$wc_product->get_permalink(), /** 상품 URL */
				$img_url, /** 상품 Thumbnail URL */
				implode( ',', $gifts ), /** giftName */
				$single, /** 단순 상품 정보 */
				$option, /** 옵션 상품 정보 */
				self::$shippingPolicy/** 배송 정책 */,
				$supplements
			);
		}
		public static function checkout_cart() {
			MNP_Logger::add_log( 'checkout_cart' );

			do_action( 'mnp_before_checkout_cart' );

			$selected_cart_items = array_filter( explode( ',', mnp_get( $_POST, 'msbn_keys', '' ) ) );
			add_filter( 'mshop_membership_skip_filter', '__return_true' );
			mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );


			$support_product_types = apply_filters( 'mnp_support_product_types', array( 'variable', 'variation' ) );

			include_once( 'naverpay/Order.php' );

			self::$shippingPolicy = MNP_Shipping::get_shipping_policy( WC()->cart );
			wc_clear_notices();

			if ( ! WC()->cart->check_cart_items() ) {
				$msg = implode( ', ', wc_get_notices( 'error' ) );
				wp_send_json_error( array( 'message' => htmlspecialchars_decode( strip_tags( $msg ) ) ) );
			}

			WC()->cart->calculate_totals();

			$products      = array();
			$cart_contents = apply_filters( 'mnp_checkout_cart_get_cart_contents', WC()->cart->get_cart() );

			foreach ( $cart_contents as $cart_item_key => $values ) {

				if ( ! empty( $selected_cart_items ) && ! in_array( $cart_item_key, $selected_cart_items ) ) {
					continue;
				}

				if ( $values['variation_id'] ) {
					$product_id = $values['variation_id'];
					$variation  = $values['variation'];
				} else {
					$product_id = $values['product_id'];
					$variation  = null;
				}

				$wc_product = wc_get_product( $product_id );

				if ( MNP_Manager::is_purchasable( $values['product_id'] ) && MNP_Manager::is_purchasable( $product_id ) && $wc_product->is_in_stock() && $wc_product->has_enough_stock( $values['quantity'] ) && $wc_product->is_purchasable() && ! $wc_product->is_virtual() &&
				     ( in_array( $wc_product->get_type(), $support_product_types ) || ( $wc_product->is_type( 'simple' ) && $wc_product->get_price() > 0 ) )
				) {
					$products[] = self::generate_product_info( array(
						'product_id'     => $product_id,
						'quantity'       => $values['quantity'],
						'variations'     => $variation,
						'line_total'     => $values['line_total'],
						'line_tax'       => $values['line_tax'],
						'cart_item_data' => apply_filters( 'mnp_get_product_cart_item_data', array(), $values ),
						'cart_item'      => $values,
						'cart_item_key'  => $cart_item_key
					) );
				}
			}
			if ( 0 == count( $products ) ) {
				wp_send_json_error( array( 'message' => '네이버페이로 구매가능한 상품이 없습니다.' ) );
			}

			$npay_order_key = self::get_order_key();
			self::save_cart_contents( $npay_order_key, WC()->cart );
			$custom_data = apply_filters( 'mnp_custom_order_data', array( 'order_key' => $npay_order_key ) );
			$order       = new Order( $products, self::get_back_url(), $custom_data );
			$data        = MNP_XMLSerializer::generateValidXmlFromObj( json_decode( json_encode( $order ) ), 'order' );

			MNP_Logger::add_log( print_r( $order, true ) );

			$result   = MNP_API::register_order( $data );
			$response = $result->response;

			do_action( 'mnp_after_checkout_cart' );

			if ( $response->ResponseType == "SUCCESS" ) {
				wp_send_json_success( array( 'authkey' => $response->AuthKey, 'shopcode' => $response->ShopCode ) );
			} else {
				wp_send_json_error( array( 'message' => $response->Error->Message ) );
			}
		}

		protected static function get_options( $variation_id, $variations ) {
			$wc_product = wc_get_product( $variation_id );

			if ( is_product( $wc_product ) && $wc_product->is_type( 'variation' ) ) {
				// 옵션상품 정보를 생성한다.
				$single          = null;
				$selectedOptions = array();

				if ( ! empty( $variation ) ) {
					$attributes = $variation;
				} else {
					$attributes = $wc_product->get_variation_attributes();
				}

				foreach ( $attributes as $key => $value ) {
					$option_id   = str_replace( 'attribute_', '', $key );
					$option_name = html_entity_decode( wc_attribute_label( $option_id ) );
					$term        = get_term_by( 'slug', $value, $option_id );
					$option_text = html_entity_decode( $term->name );

					$selectedOptions[] = $option_name . ' : ' . $option_text;
				}

				return ' ( ' . implode( ', ', $selectedOptions ) . ' )';
			}
		}
		protected static function get_back_url() {
			$back_url = remove_query_arg( 'NaPm', $_SERVER['HTTP_REFERER'] );

			if ( apply_filters( 'mnp_remove_query_args_from_back_url', false ) ) {
				$urls     = parse_url( $back_url );
				$back_url = sprintf( "%s://%s%s", $urls['scheme'], $urls['host'], $urls['path'] );
			}

			return $back_url;
		}
		public static function create_order() {
			MNP_Logger::add_log( 'create_order' );

			do_action( 'mnp_before_create_order' );

			include_once( 'naverpay/Order.php' );
			add_filter( 'mshop_membership_skip_filter', '__return_true' );
			mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );

			wc_clear_notices();
			self::backup_cart();
			$_POST_ORG = wc_clean( $_POST );

			$products = wc_clean( $_REQUEST['products'] );

			foreach ( $products as $product_info ) {
				$params = array();
				parse_str( wc_clean( $product_info['form_data'] ), $params );

				$_POST = array_merge( wc_clean( $_POST ), wc_clean( $params ) );

				$product_id     = ! empty( $product_info['parent_product_id'] ) ? $product_info['parent_product_id'] : $product_info['product_id'];
				$variation_id   = ! empty( $product_info['parent_product_id'] ) ? $product_info['product_id'] : 0;
				$variations     = apply_filters( 'mnp_get_product_variations', $product_info['attributes'], $product_info );
				$cart_item_data = apply_filters( 'mnp_get_product_cart_item_data', array(), $product_info );
				WC()->cart->add_to_cart( $product_id, $product_info['quantity'], $variation_id, $variations, $cart_item_data );

				if ( wc_notice_count( 'error' ) > 0 ) {
					self::recover_cart();

					$notices = wc_get_notices( 'error' );
					wc_clear_notices();

					$options = self::get_options( $variation_id, $variations );

					$notices = wp_list_pluck( $notices, 'notice' );

					wp_send_json_error( array( 'message' => htmlspecialchars_decode( strip_tags( implode( "\n", $notices ) ) ) . $options ) );
				}

				$_POST = $_POST_ORG;
			}
			WC()->cart->calculate_totals();

			do_action( 'woocommerce_check_cart_items' );

			if ( wc_notice_count( 'error' ) > 0 ) {
				self::recover_cart();

				$notices = wc_get_notices( 'error' );
				wc_clear_notices();

				wp_send_json_error( array( 'message' => htmlspecialchars_decode( strip_tags( implode( "\n", $notices ) ) ) ) );
			}
			self::$shippingPolicy = MNP_Shipping::get_shipping_policy( WC()->cart );
			$products      = array();
			$cart_contents = apply_filters( 'mnp_create_order_get_cart_contents', WC()->cart->get_cart() );

			foreach ( $cart_contents as $cart_item_key => $values ) {
				if ( ! empty( $values['variation_id'] ) ) {
					$product_id = $values['variation_id'];
					$variation  = $values['variation'];
				} else {
					$product_id = $values['product_id'];
					$variation  = null;
				}

				$products[] = self::generate_product_info( array(
					'product_id'     => $product_id,
					'quantity'       => $values['quantity'],
					'variations'     => $variation,
					'line_total'     => $values['line_total'],
					'line_tax'       => $values['line_tax'],
					'cart_item_data' => apply_filters( 'mnp_get_product_cart_item_data', array(), $values ),
					'cart_item'      => $values,
					'cart_item_key'  => $cart_item_key
				) );
			}
			$npay_order_key = self::get_order_key();
			self::save_cart_contents( $npay_order_key, WC()->cart );
			self::recover_cart();
			$custom_data = apply_filters( 'mnp_custom_order_data', array( 'order_key' => $npay_order_key ) );
			$order       = new Order( $products, self::get_back_url(), $custom_data );
			$data        = MNP_XMLSerializer::generateValidXmlFromObj( json_decode( json_encode( $order ) ), 'order' );

			MNP_Logger::add_log( print_r( $order, true ) );

			$result   = MNP_API::register_order( $data );
			$response = $result->response;

			do_action( 'mnp_after_create_order' );

			if ( $response->ResponseType == "SUCCESS" ) {
				wp_send_json_success( array( 'authkey' => $response->AuthKey, 'shopcode' => $response->ShopCode ) );
			} else {
				wp_send_json_error( array( 'message' => $response->Error->Message ) );
			}
		}
		public static function add_to_wishlist() {
			global $wishlistItemId;

			$queryString = 'SHOP_ID=' . urlencode( MNP_Manager::merchant_id() );
			$queryString .= '&CERTI_KEY=' . urlencode( MNP_Manager::auth_key() );

			foreach ( $_REQUEST['products'] as $product_info ) {
				$wc_product = wc_get_product( $product_info['product_id'] );

				$img_url = wp_get_attachment_image_src( $wc_product->get_image_id(), array( 300, 300 ) )[0];
				if ( 'yes' == get_option( 'mnp-force-image-url-to-http', 'yes' ) ) {
					$img_url = preg_replace( "/^https:/i", "http:", $img_url );
				}

				$queryString .= '&ITEM_ID=' . urlencode( $product_info['product_id'] );
				$queryString .= '&ITEM_NAME=' . urlencode( $wc_product->get_title() );
				$queryString .= '&ITEM_DESC=' . urlencode( $wc_product->get_title() );
				$queryString .= '&ITEM_UPRICE=' . $wc_product->get_price();
				$queryString .= '&ITEM_IMAGE=' . urlencode( utf8_uri_encode( $img_url ) );
				$queryString .= '&ITEM_THUMB=' . urlencode( utf8_uri_encode( $img_url ) );
				$queryString .= '&ITEM_URL=' . urlencode( $wc_product->get_permalink() );

				break;
			}

			$response = wp_remote_post( MNP_Manager::wishlist_url(), array(
					'method'      => 'POST',
					'headers'     => array( 'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8;' ),
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'body'        => $queryString,
					'cookies'     => array()
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			} else {
				ob_start();
				wc_get_template( 'wishlist-popup' . ( wp_is_mobile() ? '-mobile' : '' ) . '.php', array( 'wishlistItemIds' => explode( ',', $response['body'] ) ), '', MNP()->template_path() );
				$html = ob_get_clean();

				wp_send_json_success( array(
					'url'  => MNP_Manager::wishlist_url(),
					'html' => $html
				) );
			}
		}
		public static function woocommerce_after_cart_table() {
			if ( ! apply_filters( 'mnp_enabled', true ) && ! empty( $_GET['s'] ) ) {
				return;
			}

			if ( MNP_Manager::is_operable() && self::cart_contains_npay_items() ) {
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
					'underscore'
				);

				if ( 'yes' == get_option( 'mnp-npay-script', 'no' ) ) {
					wp_enqueue_script( 'mnp-naverpay', MNP_Manager::button_js_url( wp_is_mobile() ? 'mobile' : 'pc' ), array( 'jquery' ), MNP_VERSION );
					$dependencies[] = 'mnp-naverpay';
				}

				wp_register_script( 'mnp-frontend', MNP()->plugin_url() . '/assets/js/cart.js', $dependencies, MNP_VERSION );
				wp_localize_script( 'mnp-frontend', '_mnp', array(
					'ajax_url'             => mnp_ajax_url( admin_url( 'admin-ajax.php', 'relative' ) ),
					'order_url_pc'         => MNP_Manager::ordersheet_url( 'pc' ),
					'order_url_mobile'     => MNP_Manager::ordersheet_url( 'mobile' ),
					'button_js_url_pc'     => MNP_Manager::button_js_url( 'pc' ),
					'button_js_url_mobile' => MNP_Manager::button_js_url( 'mobile' ),
					'wishlist_url'         => MNP_Manager::wishlist_url(),
					'button_key'           => MNP_Manager::button_auth_key(),
					'button_type_pc'       => MNP_Manager::button_type_pc(),
					'button_type_mobile'   => MNP_Manager::button_type_mobile(),
					'button_color'         => MNP_Manager::button_color(),
					'checkout_cart_action' => MNP()->slug() . '-checkout_cart',
					'transition_mode'      => get_option( 'mnp-cart-page-transition-mode', 'new-window' ),
					'load_script_static'   => get_option( 'mnp-npay-script', 'no' )
				) );
				wp_enqueue_script( 'underscore' );
				wp_enqueue_script( 'mnp-frontend' );
				wp_enqueue_script( 'jquery-block-ui', MNP()->plugin_url() . '/assets/js/jquery.blockUI.js', $dependencies );

				wp_register_style( 'mnp-frontend', MNP()->plugin_url() . '/assets/css/naverpay-cart.css' );
				wp_enqueue_style( 'mnp-frontend' );

				wc_get_template( 'cart/naverpay-button.php', array(), '', MNP()->template_path() );
			}
		}
		public static function woocommerce_after_add_to_cart_form() {
			if ( ! apply_filters( 'mnp_enabled', true ) ) {
				return;
			}

			if ( ! empty( $_REQUEST['elementor-preview'] ) || 'elementor' == mnp_get( $_GET, 'action' ) ) {
				return '';
			}

			$support_product_types = apply_filters( 'mnp_support_product_types', array( 'variable', 'grouped' ) );

			$product_id = get_the_ID();

			if ( MNP_Manager::is_operable() && MNP_Manager::is_purchasable( $product_id ) ) {
				$product = wc_get_product( $product_id );

				$purchasable = 'grouped' == $product->get_type() ? true : $product->is_purchasable();

				if ( $purchasable && ! $product->is_virtual() && ( in_array( $product->get_type(), $support_product_types ) || ( $product->is_type( 'simple' ) && $product->get_price() > 0 ) ) ) {
					$dependencies = apply_filters( 'mnp_script_dependencies', array(
						'jquery',
						'jquery-ui-core',
						'jquery-ui-widget',
						'jquery-ui-mouse',
						'jquery-ui-position',
						'jquery-ui-draggable',
						'jquery-ui-resizable',
						'jquery-ui-button',
						'jquery-ui-dialog',
						'underscore'
					) );

					if ( 'yes' == get_option( 'mnp-npay-script', 'no' ) ) {
						wp_enqueue_script( 'mnp-naverpay', MNP_Manager::button_js_url( wp_is_mobile() ? 'mobile' : 'pc' ), array( 'jquery' ), MNP_VERSION );
						$dependencies[] = 'mnp-naverpay';
					}

					wp_register_script( 'mnp-frontend', MNP()->plugin_url() . '/assets/js/frontend.js', $dependencies, MNP_VERSION );
					wp_localize_script( 'mnp-frontend', '_mnp', array(
						'ajax_url'               => mnp_ajax_url( admin_url( 'admin-ajax.php', 'relative' ) ),
						'order_url_pc'           => MNP_Manager::ordersheet_url( 'pc' ),
						'order_url_mobile'       => MNP_Manager::ordersheet_url( 'mobile' ),
						'button_js_url_pc'       => MNP_Manager::button_js_url( 'pc' ),
						'button_js_url_mobile'   => MNP_Manager::button_js_url( 'mobile' ),
						'wishlist_url'           => MNP_Manager::wishlist_url(),
						'button_key'             => MNP_Manager::button_auth_key(),
						'button_type_pc'         => MNP_Manager::button_type_pc(),
						'button_type_mobile'     => MNP_Manager::button_type_mobile(),
						'button_color'           => MNP_Manager::button_color(),
						'button_count_pc'        => MNP_Manager::button_count( 'pc' ),
						'button_count_mobile'    => MNP_Manager::button_count( 'mobile' ),
						'create_order_action'    => MNP()->slug() . '-create_order',
						'add_to_wishlist_action' => MNP()->slug() . '-add_to_wishlist',
						'wrapper_selector'       => get_option( 'mnp-wrapper-selector', 'div[itemtype="http://schema.org/Product"]' ),
						'product_simple_class'   => get_option( 'mnp-simple-class', 'product-type-simple' ),
						'product_variable_class' => get_option( 'mnp-variable-class', 'product-type-variable' ),
						'product_grouped_class'  => get_option( 'mnp-grouped-class', 'product-type-grouped' ),
						'transition_mode'        => get_option( 'mnp-product-page-transition-mode', 'new-window' ),
						'use_submit_handler'     => get_option( 'mnp-use-submit-handler', 'no' ),
						'load_script_static'     => get_option( 'mnp-npay-script', 'no' )
					) );

					wp_enqueue_script( 'underscore' );
					wp_enqueue_script( 'mnp-frontend' );
					wp_enqueue_script( 'jquery-block-ui', MNP()->plugin_url() . '/assets/js/jquery.blockUI.js', $dependencies );

					wp_register_style( 'mnp-frontend', MNP()->plugin_url() . '/assets/css/naverpay-product.css' );
					wp_enqueue_style( 'mnp-frontend' );

					wc_get_template( 'single-product/naverpay-button.php', array(), '', MNP()->template_path() );
				}
			}
		}
		public static function wc_validate() {
			if ( empty( WC()->session ) ) {
				include_once( WC()->plugin_path() . '/includes/abstracts/abstract-wc-session.php' );
				$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
				WC()->session  = new $session_class();
			}

			if ( empty( WC()->cart ) ) {
				WC()->cart = new WC_Cart();
			}

			if ( empty( WC()->customer ) ) {
				WC()->customer = new WC_Customer();
			}
		}
		public static function backup_cart( $clear = true ) {
			self::wc_validate();

			if ( version_compare( WC_VERSION, '3.2.5', '>' ) ) {
				$cart = WC()->session->get( 'cart', null );
				WC()->session->set( 'mnp-cart', $cart );

				if ( $saved_cart = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true ) ) {
					$saved_cart['cart'] = array();
					update_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), $saved_cart );
				}

				WC()->cart->empty_cart( false );
				WC()->session->set( 'cart', null );
				WC()->cart->get_cart_from_session();
			} else {
				$cart = WC()->session->get( 'cart', null );
				WC()->session->set( 'mnp-cart', $cart );

				if ( $clear ) {
					WC()->cart->empty_cart( false );
					WC()->session->set( 'cart', array() );
					WC()->cart->get_cart_from_session();
				}
			}
		}
		public static function recover_cart() {
			$cart = WC()->session->get( 'mnp-cart', null );

			if ( version_compare( WC_VERSION, '3.2.5', '>' ) ) {
				if ( $saved_cart = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true ) ) {
					$saved_cart['cart'] = $cart;
					update_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), $saved_cart );
				}
			}

			WC()->cart->empty_cart( false );
			WC()->session->set( 'cart', $cart );
			WC()->cart->get_cart_from_session();

			WC()->session->set( 'mnp-cart', null );
		}
		public static function save_cart_contents( $order_key, $cart ) {
			if ( 'yes' == get_option( 'mnp-use-cart-management', 'yes' ) ) {
				do_action( 'mnp_before_save_cart_contents', $order_key, $cart );
				set_transient( 'mnp_' . $order_key, $cart->get_cart(), 6 * HOUR_IN_SECONDS );
				$coupons = array();
				foreach ( $cart->get_coupons() as $code => $coupon ) {
					// Avoid storing used_by - it's not needed and can get large.
					$coupon_data = $coupon->get_data();
					unset( $coupon_data['used_by'] );

					$coupons[ $code ] = array(
						'code'         => $code,
						'discount'     => $cart->get_coupon_discount_amount( $code ),
						'discount_tax' => $cart->get_coupon_discount_tax_amount( $code ),
						'coupon_data'  => $coupon_data
					);
				}

				set_transient( 'mnp_coupons_' . $order_key, $coupons, 6 * HOUR_IN_SECONDS );

				do_action( 'mnp_after_save_cart_contents', $order_key, $cart );
			}
		}
		public static function search_cart_item( $product_id, $merchant_product_id, $cart = null ) {

			if ( is_null( $cart ) ) {
				$cart = WC()->cart;
			}

			if ( is_numeric( $merchant_product_id ) ) {
				$_product_id = $merchant_product_id;
			} else {
				$_product_id = $product_id;

				$wc_product = wc_get_product( $product_id );

				if ( ! $wc_product ) {
					$_product_id = wc_get_product_id_by_sku( $product_id );
				}
			}

			if ( is_callable( array( $cart, 'get_cart_contents' ) ) ) {
				$cart_contents = $cart->get_cart_contents();
			} else {
				$cart_contents = $cart->cart_contents;
			}

			foreach ( $cart_contents as $cart_item_key => $cart_item ) {
				if ( apply_filters( 'mnp_search_cart_item_by_cart_item_key', false, $cart_item ) ) {
					if ( $product_id == apply_filters( 'mnp_get_product_id_from_cart_item_key', '', $cart_item_key, $cart_item ) ) {
						return $cart_item;
					}
				} else if ( $_product_id == $cart_item['product_id'] || $_product_id == $cart_item['variation_id'] ) {
					return $cart_item;
				}
			}

			return null;
		}
		public static function get_attribute_slug( $product, $label ) {
			$attributes = $product->get_variation_attributes();

			foreach ( $attributes as $slug => $values ) {
				MNP_Logger::add_log( sprintf( "=== [%s] [%s]", $label, wc_attribute_label( $slug ) ) );
				if ( $label == wc_attribute_label( $slug ) ) {
					return $slug;
				}
			}

			return null;
		}
		public static function generate_cart( $npay_orders, $order = null ) {
			$cart = WC()->cart;

			do_action( 'mnp_before_generate_cart', $npay_orders, $order );

			$cart->empty_cart( false );

			if ( is_null( $order ) ) {
				$cart_contents = mnp_load_saved_cart_contents_from_npay_order( current( $npay_orders ) );
			} else {
				$cart_contents = mnp_load_saved_cart_contents_from_order( $order );
			}

			foreach ( $npay_orders as $npay_order ) {
				if ( apply_filters( 'mnp_generate_cart_skip_npay_order', false, $npay_order ) ) {
					continue;
				}

				if ( property_exists( $npay_order->ProductOrder, 'OptionManageCode' ) ) {
					$product_id = apply_filters( 'mnp_get_product_id_from_option_manage_code', $npay_order->ProductOrder->OptionManageCode, $npay_order );
				} else {
					$product_id = $npay_order->ProductOrder->SellerProductCode;
				}

				$product = wc_get_product( $product_id );

				if ( $product ) {
					if ( $product->is_type( 'variation' ) ) {
						$product_id   = $product->get_parent_id();
						$variation_id = $product->get_id();
					} else {
						$product_id   = $product->get_id();
						$variation_id = '0';
					}

					$np_product_id = $npay_order->ProductOrder->ProductID;
					$quantity      = $npay_order->ProductOrder->Quantity;

					if ( ! empty( $cart_contents ) ) {
						foreach ( $cart_contents as $cart_item_key => &$cart_item ) {
							if ( empty( $cart_item['_npay_order'] ) ) {
								if ( apply_filters( 'mnp_search_cart_item_by_cart_item_key', false, $cart_item ) ) {
									$searched_product_id = apply_filters( 'mnp_get_product_id_from_cart_item_key', '', $cart_item_key, $cart_item );

									if ( $npay_order->ProductOrder->ProductID == $searched_product_id || ( property_exists( $npay_order->ProductOrder, 'OptionManageCode' ) && $npay_order->ProductOrder->OptionManageCode == $searched_product_id ) ) {
										$cart_item['_npay_product_order_id']     = $npay_order->ProductOrder->ProductOrderID;
										$cart_item['_npay_product_order_status'] = $npay_order->ProductOrder->ProductOrderStatus;
										$cart_item['_npay_order']                = json_encode( $npay_order, JSON_UNESCAPED_UNICODE );
										break;
									}
								} else if ( $cart_item['product_id'] == $product_id && $cart_item['variation_id'] == $variation_id && $cart_item['quantity'] == $quantity ) {
									$cart_item['_npay_product_order_id']     = $npay_order->ProductOrder->ProductOrderID;
									$cart_item['_npay_product_order_status'] = $npay_order->ProductOrder->ProductOrderStatus;
									$cart_item['_npay_order']                = json_encode( $npay_order, JSON_UNESCAPED_UNICODE );
									break;
								}
							}
						}

						if ( $product->is_type( 'diy-bundle' ) ) {
							$bundle_product_order_ids = array();

							foreach ( $product->get_bundle_products() as $bundle_product ) {
								$bundle_product_id = $bundle_product['id'];

								foreach ( $npay_orders as $_npay_order ) {

									if ( property_exists( $_npay_order->ProductOrder, 'OptionManageCode' ) && 0 === strpos( $_npay_order->ProductOrder->OptionManageCode, 'msdp_bundle_' ) ) {
										if ( $_npay_order->ProductOrder->MerchantProductId == $product_id && apply_filters( 'mnp_get_product_id_from_option_manage_code', $_npay_order->ProductOrder->OptionManageCode, $_npay_order ) == $bundle_product_id ) {
											$bundle_product_order_ids[] = $_npay_order->ProductOrder->ProductOrderID;
										}
									}
								}
							}

							$cart_item['_npay_bundle_product_order_ids'] = implode( ',', $bundle_product_order_ids );

						}
					} else {
						add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'maybe_update_cart_item_price' ), 10, 2 );
						remove_action( 'woocommerce_after_calculate_totals', array( 'MSMS_Cart', 'maybe_apply_membership_coupons' ) );
						$variations = array();
						if ( ! empty( $npay_order->ProductOrder->ProductOption ) ) {
							$parent_product = wc_get_product( $product->get_parent_id() );
							$options        = explode( '/', $npay_order->ProductOrder->ProductOption );
							foreach ( $options as $option ) {
								$values    = explode( ':', $option );
								$values[0] = trim( $values[0] );
								$values[1] = trim( $values[1] );

								$slug = self::get_attribute_slug( $parent_product, $values[0] );
								$term = get_term_by( 'name', $values[1], $slug );

								if ( ! empty( $slug ) ) {
									if ( is_object( $term ) ) {
										$variations[ 'attribute_' . $slug ] = $term->slug;
									} else {
										$variations[ 'attribute_' . $slug ] = apply_filters( 'mnp_product_attributes_label', $values[1], $slug, $parent_product );
									}
								} else {
									$variations[ $values[0] ] = $values[1];
								}
							}
						}

						$product_info = array(
							'product_id'    => $product_id,
							'np_product_id' => $np_product_id,
							'price'         => apply_filters( 'mnp_get_product_price_by_id', $npay_order->ProductOrder->UnitPrice, $np_product_id, $product_id, $npay_order )
						);

						$variations     = apply_filters( 'mnp_get_product_variations', $variations, $product_info, $npay_order );
						$cart_item_data = apply_filters( 'mnp_get_product_cart_item_data', array(
							'_npay_product_order_id'     => $npay_order->ProductOrder->ProductOrderID,
							'_npay_product_order_status' => $npay_order->ProductOrder->ProductOrderStatus,
							'_npay_order'                => json_encode( $npay_order, JSON_UNESCAPED_UNICODE ),
							'_npay_price'                => $npay_order->ProductOrder->UnitPrice
						), $product_info, $npay_order );

						$cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $cart_item_data );

						add_action( 'woocommerce_after_calculate_totals', array(
							'MSMS_Cart',
							'maybe_apply_membership_coupons'
						) );
						remove_filter( 'woocommerce_add_cart_item', array(
							__CLASS__,
							'maybe_update_cart_item_price'
						), 10 );
					}

				}
			}
			foreach ( $cart_contents as $cart_content_key => $cart_content ) {
				if ( empty( $cart_content['_npay_order'] ) ) {
					unset( $cart_contents[ $cart_content_key ] );
				}
			}

			if ( ! empty( $cart_contents ) ) {
				if ( is_callable( array( WC()->cart, 'set_cart_contents' ) ) ) {
					$cart->set_cart_contents( $cart_contents );
				} else {
					$cart->cart_contents = $cart_contents;
				}
			}

			do_action( 'mnp_after_generate_cart', $npay_orders, $order );
		}

		public static function maybe_update_cart_item_price( $cart_item_data, $cart_item_key ) {
			if ( isset( $cart_item_data['_npay_price'] ) ) {
				$cart_item_data['data']->set_price( floatval( $cart_item_data['_npay_price'] ) );
			}

			return $cart_item_data;
		}
	}
}

