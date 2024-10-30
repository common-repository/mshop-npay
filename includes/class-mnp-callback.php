<?php



if ( ! class_exists( 'MNP_Callback' ) ) {
	class MNP_Callback {

		private $iv_delivery_fee = 0;

		private $customer_info = array();
		public function __construct() {
			add_action( 'parse_request', array( $this, 'parse_request' ) );
		}
		public function parse_request() {

			$request = $_SERVER['REQUEST_URI'];
			$url     = parse_url( home_url() );

			if ( isset( $url['path'] ) ) {
				$request = str_replace( $url['path'], '', $request );
			}

			if ( 0 === strpos( $request, '/npay_iv_shipping_fee' ) ) {

				MNP_Logger::add_log( 'receive callback : npay_iv_shipping_fee' );
				MNP_Logger::add_log( json_encode( $_REQUEST ) );

				if ( empty( $_REQUEST['productId'] ) || ! isset( $_REQUEST['zipcode'] ) || ! isset( $_REQUEST['address1'] ) ) {
					die( __( '잘못된 요청입니다.', 'mshop-npay' ) );
				}

				$product_ids = wc_clean( $_REQUEST['productId'] );
				$postal_code = wc_clean( $_REQUEST['zipcode'] );
				$address1    = wc_clean( $_REQUEST['address1'] );

				$fee = MNP_Shipping::get_iv_shipping_fee( $product_ids, $postal_code, $address1 );

				ob_clean();
				ob_start();
				header( 'Content-Type: application/xml;charset=utf-8' );
				echo( '<?xml version="1.0" encoding="utf-8"?>' );

				include_once( 'naverpay-api/AdditionalFee.php' );
				echo '<additionalFees>';
				foreach ( $product_ids as $product_id ) {
					$additionalFee = new AdditionalFee( $product_id, $fee );
					echo MNP_XMLSerializer::generateValidXmlFromObj( json_decode( json_encode( $additionalFee ) ), 'additionalFee' );
				}
				echo '</additionalFees>';

				ob_get_flush();
				die();
			} else if ( 0 === strpos( $request, '/npay_product_info' ) ) {
				MNP_Logger::add_log( 'receive callback : npay_product_info' );

				$merchant_custom_code2 = array();
				parse_str( wc_clean( $_REQUEST['merchantCustomCode2'] ), $merchant_custom_code2 );
				add_filter( 'mshop_membership_skip_filter', '__return_false' );
				$this->get_customer_info( ! empty( $_REQUEST['merchantCustomCode1'] ) ? wc_clean( $_REQUEST['merchantCustomCode1'] ) : '' );

				ob_clean();
				ob_start();
				header( 'Content-Type: application/xml;charset=utf-8' );
				echo( '<?xml version="1.0" encoding="utf-8"?>' );

				$products = wc_clean( $_REQUEST['product'] );

				MNP_Cart::backup_cart();

				$cart_contents = mnp_load_saved_cart_contents( $merchant_custom_code2['order_key'] );
				if ( is_callable( array( WC()->cart, 'set_cart_contents' ) ) ) {
					WC()->cart->set_cart_contents( $cart_contents );
				} else {
					WC()->cart->cart_contents = $cart_contents;
				}

				echo '<products>';
				foreach ( $products as $product ) {
					$product_id          = ! empty( $product['id'] ) ? $product['id'] : '';
					$merchant_product_id = ! empty( $product['merchantProductId'] ) ? wc_clean( wp_unslash( $product['merchantProductId'] ) ) : '';
					$option_manage_codes = ! empty( $_REQUEST['optionManageCodes'] ) ? wc_clean( wp_unslash( $_REQUEST['optionManageCodes'] ) ) : '';
					$option_search       = ! empty( $_REQUEST['optionSearch'] ) ? filter_var( $_REQUEST['optionSearch'], FILTER_VALIDATE_BOOLEAN ) : false;
					$supplement_search   = ! empty( $_REQUEST['supplementSearch'] ) ? filter_var( $_REQUEST['supplementSearch'], FILTER_VALIDATE_BOOLEAN ) : false;
					$supplementIds       = ! empty( $product['supplementIds'] ) ? wc_clean( wp_unslash( $product['supplementIds'] ) ) : '';
					$cart_content        = MNP_Cart::search_cart_item( $product_id, $merchant_product_id );

					echo $this->get_product_info( $product_id, $merchant_product_id, $option_manage_codes, $option_search, $supplement_search, $supplementIds, $cart_content );
				}
				echo '</products>';

				MNP_Cart::recover_cart();

				ob_get_flush();
				die();
			} else if ( 0 === strpos( $request, '/npay_callback' ) ) {
				MNP_Logger::add_log( 'receive callback : npay_callback' );
				$this->process_callback();

				ob_clean();
				ob_start();
				echo "RESULT=TRUE";
				ob_get_flush();
				die();
			}
		}

		public function get_customer_info( $merchant_custom_code1 ) {
			$this->customer_info = array();

			if ( ! empty( $merchant_custom_code1 ) ) {
				parse_str( $merchant_custom_code1, $this->customer_info );
			}

			if ( isset( $this->customer_info['user_id'] ) ) {
				wp_set_current_user( $this->customer_info['user_id'] );
			}

			add_filter( 'mshop_membership_skip_filter', '__return_false' );

			if ( isset( $this->customer_info['user_role'] ) ) {
				add_filter( 'mshop_membership_get_user_role', array( $this, 'apply_membership' ), 10, 2 );
			}
		}

		public function apply_membership() {
			return $this->customer_info['user_role'];
		}
		function process_callback() {
			mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );

			do_action( 'mnp_before_process_callback' );

			$product_order_info_list = wc_clean( $_REQUEST['product_order_info_list'] );

			MNP_Logger::add_log( json_encode( $product_order_info_list ) );

			if ( ! empty( $product_order_info_list ) ) {
				$this->process_changed_product_order( $product_order_info_list );
			}

			do_action( 'mnp_after_process_callback' );
		}
		function process_changed_product_order( $product_order_info_list ) {
			foreach ( $product_order_info_list as $order_id => $product_info_list ) {
				$reformatted_info_list = array();
				foreach ( $product_info_list as $key => $product_info ) {
					$reformatted_info_list[ $key ] = mnp_array_to_object( $product_info );
				}

				$orders = wc_get_orders( array(
					'limit'        => 1,
					'type'         => 'shop_order',
					'meta_key'     => '_naverpay_order_id',
					'meta_value'   => $order_id,
					'meta_compare' => '='
				) );

				if ( count( $orders ) > 0 ) {
					$order = reset( $orders );
					$this->update_order( $order, $reformatted_info_list );
				} else {
					$this->create_order( $reformatted_info_list );
				}
			}
		}
		function update_order( $order, $npay_orders ) {
			MNP_Order::update_npay_orders( $order, $npay_orders );
		}
		function create_order( $npay_orders ) {

			try {
				do_action( 'mnp_before_create_npay_order', $npay_orders );
				add_filter( 'msgift_skip_processing', '__return_true' );

				mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );
				$order = MNP_Order::create_npay_order( $npay_orders );

				remove_filter( 'msgift_skip_processing', '__return_true' );
				do_action( 'mnp_after_create_npay_order', $order, $npay_orders );
			} catch ( Exception $e ) {
				ob_clean();
				ob_start();
				echo "RESULT=FALSE&RESULT_MESSAGE=" . sprintf( "[%s]%s", $e->getCode(), $e->getMessage() );
				ob_get_flush();
				die();
			}
		}

		function search_post_attribute( $post_id ) {
			$result = array();

			$attribute_taxonomies = wc_get_attribute_taxonomies();

			foreach ( $attribute_taxonomies as $tax ) {
				$attribute_taxonomy_name = wc_attribute_taxonomy_name( $tax->attribute_name );
				$post_terms              = wp_get_post_terms( $post_id, $attribute_taxonomy_name );
				$has_terms               = ( is_wp_error( $post_terms ) || ! $post_terms || sizeof( $post_terms ) == 0 ) ? 0 : 1;

				if ( $has_terms ) {
					$result[] = $tax;
				}
			}

			return $result;
		}

		// 상품 옵션 정보를 생성한다.
		function generate_option_item( $post_parent, $cart_content ) {
			$productOptionItems   = array();
			$attributes           = $this->search_post_attribute( $post_parent );
			$variation_attributes = wc_get_product( $post_parent )->get_variation_attributes();

			foreach ( $attributes as $attribute ) {
				$variation_attribute = $variation_attributes[ wc_attribute_taxonomy_name( $attribute->attribute_name ) ];
				if ( ! empty( $variation_attribute ) ) {
					$terms = get_terms( wc_attribute_taxonomy_name( $attribute->attribute_name ), 'orderby=name&hide_empty=0' );

					$productOptionItemValues = array();
					foreach ( $terms as $term ) {
						if ( in_array( $term->slug, $variation_attribute ) ) {
							$productOptionItemValues[] = new ProductOptionItemValue( $term->slug, html_entity_decode( $term->name ) );
						}
					}

					$productOptionItems[] = new ProductOptionItem( ProductOptionItem::TYPE_SELECT, urldecode( $attribute->attribute_label ), $productOptionItemValues );
				}
			}

			return apply_filters( 'mnp_callback_option_item', $productOptionItems, $cart_content );
		}

		public function get_product_info( $product_id, $merchant_product_id, $optionManageCodes, $option_search, $supplement_search, $supplementIds, $cart_content ) {
			include_once( 'class-mnp-xmlserializer.php' );
			include_once( 'naverpay-api/Product.php' );
			include_once( 'naverpay-api/ReturnInfo.php' );
			include_once( 'naverpay-api/ProductOption.php' );
			include_once( 'naverpay-api/ProductCombination.php' );

			if ( is_numeric( $merchant_product_id ) ) {
				$wc_product = wc_get_product( $merchant_product_id );
			} else {
				$wc_product = wc_get_product( $product_id );

				if ( ! $wc_product ) {
					$wc_product = wc_get_product( wc_get_product_id_by_sku( $product_id ) );
				}
			}

			if ( $wc_product ) {
				// 재고 수량 계산
				if ( $wc_product->managing_stock() && ! $wc_product->backorders_allowed() ) {
					if ( 'instock' === $wc_product->stock_status ) {
						$stockQuantity = $wc_product->get_total_stock();
					} else {
						$stockQuantity = 0;
					}
				} else {
					$stockQuantity = null;
				}

				// 거래 상태
				if ( 0 === $stockQuantity ) {
					$status = 'SOLD_OUT';
				} else if ( $wc_product->is_purchasable() ) {
					$status = 'ON_SALE';
				} else {
					$status = 'NOT_SALE';
				}

				if ( $cart_content ) {
					$line_total = $cart_content['line_total'];
					$line_tax   = $cart_content['line_tax'];
					$tax = 'TAX';
					if ( wc_tax_enabled() ) {
						if ( $line_tax <= 0 ) {
							$tax = 'ZERO_TAX';
						}

						$line_total = absint( round( $line_total + $line_tax, wc_get_price_decimals() ) );
					}

					$price = $line_total / $cart_content['quantity'];
					$shippingPolicy = MNP_Shipping::get_shipping_policy( WC()->cart );

				} else {
					$tax = 'TAX';
					$wc_cart = WC()->cart;
					WC()->cart = new WC_Cart();
					WC()->cart->add_to_cart( $wc_product->get_id(), 1 );
					WC()->cart->calculate_totals();
					$shippingPolicy = MNP_Shipping::get_shipping_policy( WC()->cart );
					WC()->cart = $wc_cart;
					WC()->cart->calculate_totals();

					$price = apply_filters( 'mnp_get_product_price_by_id', $wc_product->get_price(), $product_id, $merchant_product_id );
				}

				$option = null;

				if ( $wc_product->is_type( 'variation' ) ) {
					$optionSupport = 'true';
					$option_item   = null;
					$combination   = null;

					if ( $option_search ) {
						$parent_id   = $wc_product->get_parent_id();
						$option_item = $this->generate_option_item( $parent_id, $cart_content );
						$option      = new ProductOption( $option_item, null );
					}
				} else {
					$optionSupport = 'false';

					if ( $option_search ) {
						$optionSupport = apply_filters( 'mnp_callback_product_info_option_support', false, $product_id, $merchant_product_id );
						$option_item   = apply_filters( 'mnp_callback_product_info_option', false, $product_id, $merchant_product_id );

						if ( $cart_content ) {
							$optionSupport = apply_filters( 'mnp_callback_option_support', $optionSupport, $cart_content );
							$option_item   = apply_filters( 'mnp_callback_option_item', $option_item, $cart_content );
						}

						$option = new ProductOption( $option_item, null );
					}
				}

				$supplements = array();
				if ( $supplement_search && ! empty( $supplementIds ) ) {
					$supplements = apply_filters( 'mnp_callback_product_info_get_supplements', array(), $product_id, $merchant_product_id, $supplementIds );
				}

				$img_url = '';
				$images  = wp_get_attachment_image_src( $wc_product->get_image_id(), array( 300, 300 ) );

				if ( ! empty( $images ) ) {
					$img_url = $images[0];
					if ( 'yes' == get_option( 'mnp-force-image-url-to-http', 'yes' ) ) {
						$img_url = preg_replace( "/^https:/i", "http:", $img_url );
					}
				}

				$img_url = apply_filters( 'mnp_product_image_url', $img_url, $product_id );

				$returnInfo = null;

				if ( ! empty( get_option( 'mnp_zipcode' ) ) ) {
					$returnInfo = new ReturnInfo(
						get_option( 'mnp_zipcode' ),
						get_option( 'mnp_address1' ),
						get_option( 'mnp_address2' ),
						get_option( 'mnp_sellername' ),
						get_option( 'mnp_contact1' ),
						get_option( 'mnp_contact2' )
					);
				}
				if ( ! empty( $wc_product->get_meta( '_mnp_zipcode' ) ) ) {
					$returnInfo = new ReturnInfo(
						$wc_product->get_meta( '_mnp_zipcode' ),
						$wc_product->get_meta( '_mnp_address1' ),
						$wc_product->get_meta( '_mnp_address2' ),
						$wc_product->get_meta( '_mnp_sellername' ),
						$wc_product->get_meta( '_mnp_contact1' ),
						$wc_product->get_meta( '_mnp_contact2' )
					);
				}

				$product = new Product(
					$product_id,
					$merchant_product_id,
					null,
					html_entity_decode( $wc_product->get_title() ),
					$price,
					$tax,
					$wc_product->get_permalink(),
					$img_url,
					null,
					$stockQuantity,
					$status,
					$optionSupport,
					$option,
					$shippingPolicy,
					$returnInfo,
					$supplements
				);

				$data = MNP_XMLSerializer::generateValidXmlFromObj( json_decode( json_encode( $product ) ), 'product' );

				return $data;
			}
		}
	}

	new MNP_Callback();
}

