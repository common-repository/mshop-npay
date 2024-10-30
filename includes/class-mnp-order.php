<?php



if ( ! class_exists( 'MNP_Order' ) ) {
	class MNP_Order {

		protected static $customer_info = array();

		protected static $payment_complete = false;
		public static function wc_order_statuses( $order_statuses ) {

			$order_statuses = array_merge( $order_statuses, array(
				'wc-place-order'      => _x( '발주확인', 'Order status', 'mshop-npay' ),
				'wc-shipping'         => _x( '배송중', 'Order status', 'mshop-npay' ),
				'wc-shipped'          => _x( '배송완료', 'Order status', 'mshop-npay' ),
				'wc-cancel-request'   => _x( '취소요청', 'Order status', 'mshop-npay' ),
				'wc-exchange-request' => _x( '교환신청', 'Order status', 'mshop-npay' ),
				'wc-return-request'   => _x( '반품신청', 'Order status', 'mshop-npay' ),
			) );

			if ( 'yes' == get_option( 'mnp-use-partial-refunded-order-status', 'no' ) ) {
				$order_statuses = array_merge( $order_statuses, array(
					'wc-partial-refunded' => _x( '부분환불', 'Order status', 'mshop-npay' ),
				) );
			}

			return $order_statuses;
		}
		public static function update_delivery_info( $order, $npay_product_order ) {
			$ShippingAddress = $npay_product_order->ProductOrder->ShippingAddress;

			$order->set_shipping_first_name( $ShippingAddress->Name );
			$order->set_shipping_address_1( $ShippingAddress->BaseAddress );
			$order->set_shipping_address_2( $ShippingAddress->DetailedAddress );
			$order->set_shipping_postcode( $ShippingAddress->ZipCode );
			if ( is_callable( array( $order, 'set_shipping_phone' ) ) ) {
				$order->set_shipping_phone( $ShippingAddress->Tel1 );
			} else {
				$order->update_meta_data( '_shipping_phone', $ShippingAddress->Tel1 );
			}

			if ( 'yes' == get_option( 'mnp-save-billing-address', 'no' ) ) {
				$order->set_billing_address_1( $ShippingAddress->BaseAddress );
				$order->set_billing_address_2( $ShippingAddress->DetailedAddress );
				$order->set_billing_postcode( $ShippingAddress->ZipCode );
			}

			$order->save();

			if ( 'yes' == get_option( 'mnp-save-shipping-info', 'yes' ) ) {
				$order->set_customer_note( $ShippingAddress->Name . '(' . $ShippingAddress->Tel1 . ')' . ( isset( $npay_product_order->ProductOrder->ShippingMemo ) ? ', ' . $npay_product_order->ProductOrder->ShippingMemo : '' ) );
			} else if ( isset( $npay_product_order->ProductOrder->ShippingMemo ) ) {
				$order->set_customer_note( $npay_product_order->ProductOrder->ShippingMemo );
			}
			$order->save();
		}

		static function bulk_action_place_product_order( $order_ids ) {
			$count       = 0;
			$order_info  = array();
			$_order_info = array();

			foreach ( $order_ids as $order_id ) {
				$order             = wc_get_order( $order_id );
				$product_order_ids = array();

				if ( 'naverpay' == $order->get_payment_method() ) {
					foreach ( $order->get_items() as $item_id => $item ) {
						$npay_product_order_id = $item['npay_product_order_id'];
						$npay_order            = json_decode( $item['npay_order'] );
						$npay_product_order    = $npay_order->ProductOrder;

						if ( $npay_order && $npay_product_order_id && MNP_Order_Item::can_order_action( $npay_order, $npay_product_order, 'place-product-order' ) ) {
							$product_order_ids[] = $npay_product_order_id;
						}
					}

					if ( ! empty( $product_order_ids ) ) {
						$order_info[] = array(
							'order_id'               => $order->get_id(),
							'npay_order_id'          => $order->get_meta( '_naverpay_order_id' ),
							'npay_product_order_ids' => implode( ',', $product_order_ids )
						);

						$_order_info[ $order->get_id() ] = implode( ',', $product_order_ids );
					}
				}
			}

			if ( ! empty( $order_info ) ) {
				$params = array(
					'command'    => 'bulk_place_product_order',
					'order_info' => $order_info
				);

				$place_order_status = apply_filters( 'mnp_order_status_for_place_order', 'place-order', $order );

				$response = MNP_API::call( http_build_query( array_merge( MNP_Manager::default_args(), $params ) ) );

				foreach ( $response as $order_id => $order_response ) {
					$order = wc_get_order( $order_id );

					// process result
					if ( $order_response && property_exists( $order_response, 'success' ) && property_exists( $order_response, 'error' ) ) {
						$success_count = count( (array) $order_response->success );
						$error_count   = count( (array) $order_response->error );

						if ( $success_count > 0 ) {
							self::update_npay_orders( $order, array_values( (array) $order_response->success ) );
						}

						if ( $error_count > 0 ) {
							$msg[] = sprintf( __( '발주처리 - 실패 : %d/%d건', 'mshop-npay' ), $error_count, $success_count + $error_count );
							foreach ( $order_response->error as $key => $error ) {
								$msg[] = sprintf( __( '%s, %s, %s', 'mshop-npay' ), $key, $error->Code, $error->Message );
							}
							$order->add_order_note( '<span style="font-size: 0.85em">[NPay] ' . implode( '<br>', $msg ) . '</span>' );
						} else {
							$order->add_order_note( sprintf( __( '<span style="font-size: 0.85em">[NPay] 발주처리 완료 [%s]</span>', 'mshop-npay' ), $_order_info[ $order_id ] ) );
							$order->update_status( $place_order_status, __( 'Order status changed by bulk edit:', 'woocommerce' ), true );
							$count++;
						}
					} else if ( $order instanceof WC_Abstract_Order ) {
						$order->add_order_note( '<span style="font-size: 0.85em">[NPay] ' . __( '발주 처리중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'mshop-npay' ) . '</span>' );
					}
				}
			}

			return $count;
		}

		static function order_action() {

			// validate request
			if ( empty( $_REQUEST['params'] ) ) {
				wp_send_json_error( '잘못된 요청입니다.' );
			}

			$params = wc_clean( $_REQUEST['params'] );
			if ( empty( $params['command'] ) || empty( $params['order_id'] ) || empty( $params['product_order_id'] ) ) {
				wp_send_json_error( '잘못된 요청입니다.' );
			}

			// get mandatory fields
			$order            = wc_get_order( $params['order_id'] );
			$product_order_id = $params['product_order_id'];
			$command          = $params['command'];
			$command_desc     = MNP_API::get_command_desc( $command );

			// call npay api
			$response = MNP_API::call( http_build_query( array_merge( MNP_Manager::default_args(), $params ) ) );

			// process result
			if ( $response && property_exists( $response, 'success' ) && property_exists( $response, 'error' ) ) {
				$success_count = count( (array) $response->success );
				$error_count   = count( (array) $response->error );

				if ( $success_count > 0 ) {
					self::update_npay_orders( $order, array_values( (array) $response->success ) );
				}

				if ( $error_count > 0 ) {
					$msg[] = sprintf( __( '%s처리 - 실패 : %d/%d건', 'mshop-npay' ), $command_desc, $error_count, $success_count + $error_count );
					foreach ( $response->error as $key => $error ) {
						$msg[] = sprintf( __( '%s, %s, %s', 'mshop-npay' ), $key, $error->Code, $error->Message );
					}
					$order->add_order_note( '<span style="font-size: 0.85em">[NPay] ' . implode( '<br>', $msg ) . '</span>' );
					wp_send_json_error( $msg[0] . ' ' . $msg[1] );
				} else {
					$order->add_order_note( sprintf( __( '<span style="font-size: 0.85em">[NPay] %s처리 완료 [%s]</span>', 'mshop-npay' ), $command_desc, $product_order_id ) );
					wp_send_json_success();
				}
			} else {
				wp_send_json_error( __( '요청 처리중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'mshop-npay' ) );
			}
		}

		static function get_purchase_review_list() {
			$search_date = wc_clean( wp_unslash( $_REQUEST['param']['search_date'] ) );

			$from = $search_date . 'T00:00:00+09:00';
			$to   = $search_date . 'T23:59:59+09:00';

			$result = MNP_API::get_purchase_review_list( $from, $to );

			$response = $result->response;

//			if( "SUCCESS" == $response->ResponseType && $response->ReturnedDataCount > 0 ){
//				if( is_array( $response->PurchaseReviewList) ){
//					foreach( $response->PurchaseReviewList as $PurchaseReview ){
//						$this->insert_comment( $PurchaseReview );
//					}
//				}else{
//					$this->insert_comment( $response->PurchaseReviewList );
//				}
//			}

			wp_send_json_success( array(
				"request"  => print_r( $result->request, true ),
				"response" => print_r( $result->response, true )
			) );
		}

		static function answer_customer_inquiry() {
			$InquiryID       = wc_clean( wp_unslash( $_REQUEST['param']['InquiryID'] ) );
			$AnswerContent   = wc_clean( wp_unslash( $_REQUEST['param']['AnswerContent'] ) );
			$AnswerContentID = wc_clean( wp_unslash( $_REQUEST['param']['AnswerContentID'] ) );

			$result   = MNP_API::answer_customer_inquiry( $InquiryID, $AnswerContent, $AnswerContentID );
			$response = $result->response;

			if ( 'SUCCESS' == $response->ResponseType ) {
				wp_send_json_success( array( "request" => $result->request, "response" => $result->response ) );
			} else {
				wp_send_json_error( array( "request" => $result->request, "response" => $result->response ) );
			}
		}
		public static function woocommerce_hidden_order_itemmeta( $metas ) {
			return array_merge( $metas, array( '_npay_order', '_npay_product_order_id', '_npay_product_order_status', '_npay_bundle_product_order_ids', 'refund_total', 'refund_tax' ) );
		}
		public static function get_npay_orders( $order_id ) {
			$order = wc_get_order( $order_id );

			$params = array(
				'command'          => 'refresh_npay_order',
				'order_id'         => $order_id,
				'product_order_id' => $order->get_meta( '_naverpay_product_order_id' ),
				'mall_id'          => MNP_Manager::merchant_id()
			);

			// call npay api
			$response = MNP_API::call( http_build_query( array_merge( MNP_Manager::default_args(), $params ) ) );

			if ( $response && property_exists( $response, 'success' ) && property_exists( $response, 'error' ) ) {
				$success_count = count( (array) $response->success );
				$error_count   = count( (array) $response->error );


				if ( $error_count > 0 ) {
					$msg[] = sprintf( __( '주문정보 새로고침 - 실패 : %d/%d건', 'mshop-npay' ), $error_count, $success_count + $error_count );
					foreach ( $response->error as $key => $error ) {
						$msg[] = sprintf( __( '%s, %s, %s', 'mshop-npay' ), $key, $error->Code, $error->Message );
					}
					throw new Exception( $msg[0] . ' ' . $msg[1] );
				} else if ( $success_count > 0 ) {
					return $response->success;
				}
			}

			throw new Exception( __( 'NPay 주문정보를 확인하는 과정에서 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'mshop-npay' ) );
		}
		protected static function delete_npay_fee_items( $order ) {
			$fees = $order->get_fees();
			foreach ( $fees as $fee_id => $fee ) {
				if ( is_array( $fee ) ) {
					if ( 'yes' == $fee['npay_fee_item'] ) {
						$order->remove_item( $fee_id );
					}
				} else {
					if ( 'yes' == $fee->get_meta( '_npay_fee_item' ) ) {
						$order->remove_item( $fee_id );
					}
				}
			}
		}
		protected static function add_npay_fee_items( $order, $npay_orders ) {
			$npay_order = current( $npay_orders );

			if ( property_exists( $npay_order, 'ReturnInfo' ) && property_exists( $npay_order->ReturnInfo, 'ClaimDeliveryFeeDemandAmount' ) ) {
				if ( 'RETURN_DONE' == $npay_order->ReturnInfo->ClaimStatus ) {
					$item = new WC_Order_Item_Fee();
					$item->set_props( array(
						'name'      => __( '환불금액 차감', 'mshop-npay' ),
						'tax_class' => 0,
						'amount'    => $npay_order->ReturnInfo->ClaimDeliveryFeeDemandAmount,
						'total'     => $npay_order->ReturnInfo->ClaimDeliveryFeeDemandAmount,
						'total_tax' => 0,
						'taxes'     => array(
							'total' => array(),
						),
					) );
					$item->update_meta_data( '_npay_fee_item', 'yes' );

					// Add item to order and save.
					$order->add_item( $item );
				}
			}
		}
		protected static function update_npay_fee_items( $order, $npay_orders ) {
			self::delete_npay_fee_items( $order );
			self::add_npay_fee_items( $order, $npay_orders );
		}
		protected static function delete_npay_order_items( $order ) {
			foreach ( $order->get_items() as $item_id => $values ) {
				if ( ! empty( $values['npay_product_order_id'] ) ) {
					$order->remove_item( $item_id );
				}
			}
			foreach ( $order->get_refunds() as $refund ) {
				if ( 'yes' == $refund->get_meta( 'is_npay_order' ) ) {
					$refund_id = $refund->get_id();
					$order_id  = $refund->get_parent_id();
					wc_delete_shop_order_transients( $order_id );
					$refund->delete();
					do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );
				}
			}
			$order->remove_order_items( 'shipping' );

			self::delete_npay_fee_items( $order );

			$order->save();
		}
		protected static function has_refund_order( $order, $item_id ) {
			foreach ( $order->get_refunds() as $refund ) {
				if ( 'yes' == $refund->get_meta( 'is_npay_order' ) && $item_id == $refund->get_meta( 'npay_order_item_id' ) ) {
					return true;
				}
			}

			return false;
		}
		protected static function remove_shipping_from_order( $order ) {
			$order->remove_order_items( 'shipping' );
			foreach ( $order->get_fees() as $fee_id => $fee ) {
				if ( is_array( $fee ) ) {
					if ( 'yes' == $fee['npay_shipping_item'] ) {
						$order->remove_item( $fee_id );
					}
				} else {
					if ( 'yes' == $fee->get_meta( '_npay_shipping_item' ) ) {
						$order->remove_item( $fee_id );
					}
				}
			}

			$order->calculate_totals();

			if ( is_callable( array( $order, 'save' ) ) ) {
				$order->save();
			}
		}
		protected static function update_shipping( $order, $npay_orders ) {

			self::remove_shipping_from_order( $order );

			$order      = wc_get_order( $order->get_id() );
			$npay_order = current( $npay_orders );

			if ( 'custom' == get_option( 'mshop-naverpay-shipping-fee-type', 'woocommerce' ) ) {
				$min_amount    = get_option( 'mshop-naverpay-shipping-minimum-amount', 0 );
				$shipping_cost = get_option( 'mshop-naverpay-shipping-flat-rate-amount', 0 );
			} else {
				$min_amount    = 0;
				$shipping_cost = $npay_order->ProductOrder->DeliveryFeeAmount - $npay_order->ProductOrder->DeliveryDiscountAmount;
			}

			if ( $shipping_cost > 0 && ( $min_amount == 0 || $order->get_total() < $min_amount ) ) {
				$item = new WC_Order_Item_Fee();
				$item->set_props( array(
					'name'      => __( 'NPAY 배송비', 'mshop-npay' ),
					'tax_class' => 0,
					'amount'    => $shipping_cost,
					'total'     => $shipping_cost,
					'total_tax' => 0,
					'taxes'     => array(
						'total' => array(),
					),
				) );
				$item->update_meta_data( '_npay_shipping_item', 'yes' );

				// Add item to order and save.
				$order->add_item( $item );
			}

			if ( $npay_order->ProductOrder->SectionDeliveryFee > 0 ) {
				$item = new WC_Order_Item_Fee();
				$item->set_props( array(
					'name'      => __( '도서산간 배송비', 'mshop-npay' ),
					'tax_class' => 0,
					'amount'    => $npay_order->ProductOrder->SectionDeliveryFee,
					'total'     => $npay_order->ProductOrder->SectionDeliveryFee,
					'total_tax' => 0,
					'taxes'     => array(
						'total' => array(),
					),
				) );
				$item->update_meta_data( '_npay_shipping_item', 'yes' );

				// Add item to order and save.
				$order->add_item( $item );
			}

			$order->calculate_totals();

			if ( is_callable( array( $order, 'save' ) ) ) {
				$order->save();
			}
		}
		public static function create_npay_order( $npay_orders ) {
			self::get_customer_info( $npay_orders );
			if ( is_user_logged_in() ) {
				WC()->session->set( 'cart', null );
				WC()->cart->get_cart_from_session();

				MNP_Cart::backup_cart();
			}
			MNP_Cart::generate_cart( $npay_orders );
			$npay_order         = $npay_orders[0];
			$npay_product_order = $npay_order->ProductOrder;
			$data                              = array();
			$data['billing_first_name']        = $npay_order->Order->OrdererName;
			$data['billing_phone']             = preg_replace( '/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $npay_order->Order->OrdererTel1 );
			$data['ship_to_different_address'] = true;
			$data['shipping_first_name']       = $npay_product_order->ShippingAddress->Name;
			$data['shipping_address_1']        = $npay_product_order->ShippingAddress->BaseAddress;
			$data['shipping_address_2']        = $npay_product_order->ShippingAddress->DetailedAddress;
			$data['shipping_postcode']         = $npay_product_order->ShippingAddress->ZipCode;
			$data['shipping_phone']            = $npay_product_order->ShippingAddress->Tel1;

			if ( 'yes' == get_option( 'mnp-save-billing-address', 'no' ) ) {
				$data['billing_address_1'] = $npay_product_order->ShippingAddress->BaseAddress;
				$data['billing_address_2'] = $npay_product_order->ShippingAddress->DetailedAddress;
				$data['billing_postcode']  = $npay_product_order->ShippingAddress->ZipCode;
			}

			if ( 'yes' == get_option( 'mnp-save-shipping-info', 'yes' ) ) {
				$data['order_comments'] = $npay_product_order->ShippingAddress->Name . '(' . $npay_product_order->ShippingAddress->Tel1 . ')' . ( isset( $npay_product_order->ShippingMemo ) ? ', ' . $npay_product_order->ShippingMemo : '' );
			}
			if ( isset( $npay_product_order->ShippingMemo ) ) {
				$data['order_comments'] = isset( $npay_product_order->ShippingMemo ) ? $npay_product_order->ShippingMemo : '';
			}

			if( ! is_user_logged_in() ) {
				$data['billing_email'] = 'npay-guest@npay-guest.com';
			}
			$order_id = WC()->checkout()->create_order( $data );

			if ( is_a( $order_id, 'WP_Error' ) ) {
				MNP_Logger::add_log( '주문 생성 오류' );
				MNP_Logger::add_log( $order_id->get_error_message() );
				throw new Exception( $order_id->get_error_message() );
			}

			$order = wc_get_order( $order_id );

			if ( ! is_a( $order, 'WC_Order' ) ) {
				MNP_Logger::add_log( sprintf( '주문 정보를 읽어올 수 없습니다. [%d]', $order_id ) );
				throw new Exception( '주문 정보를 읽어올 수 없습니다.' );
			}

			$order->set_payment_method( 'naverpay' );
			$order->set_payment_method_title( 'NPay' );
			if ( ! $order->get_date_paid( 'edit' ) ) {
				$order->set_date_paid( current_time( 'timestamp', true ) );
			}
			if ( is_callable( array( $order, 'set_currency' ) ) ) {
				$order->set_currency( 'KRW' );
			}

			$order->save();
			if ( 'yes' == get_option( 'mnp-use-cart-management', 'yes' ) ) {
				$order->update_meta_data( '_mnp_cart', mnp_load_saved_cart_contents_from_npay_order( current( $npay_orders ) ) );

				$coupons = mnp_load_saved_cart_coupons_from_npay_order( current( $npay_orders ) );
				foreach ( $coupons as $code => $coupon ) {
					$item = new WC_Order_Item_Coupon();
					$item->set_props(
						array(
							'code'         => $code,
							'discount'     => $coupon['discount'],
							'discount_tax' => $coupon['discount_tax'],
						)
					);
					$item->update_meta_data( 'coupon_data', $coupon['couppon_data'] );

					do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $code, $coupon, $order );

					// Add item to order and save.
					$order->add_item( $item );
				}
			}
			$product_order_ids = array();
			foreach ( $npay_orders as $npay_order ) {
				$product_order_ids[] = $npay_order->ProductOrder->ProductOrderID;
			}
			$order->update_meta_data( '_npay_version', MNP()->version );
			$order->update_meta_data( '_npay_order', $npay_order->Order );
			$order->update_meta_data( '_naverpay_order_id', $npay_order->Order->OrderID );
			$order->update_meta_data( '_naverpay_product_order_id', implode( ',', $product_order_ids ) );
			self::update_stock( $order, $npay_orders );
			self::update_shipping( $order, $npay_orders );
			self::update_order_status( $order, $npay_orders );
			self::save_custom_data( $order, $npay_orders );

			self::remove_npay_membership_filter();

			do_action( 'woocommerce_checkout_order_processed', $order_id, array(), $order );

			if ( self::$payment_complete ) {
				do_action( 'woocommerce_payment_complete', $order->get_id() );
			}
			if ( is_user_logged_in() ) {
				MNP_Cart::recover_cart();
			}

			return $order;
		}
		protected static function get_order_item( $order, $product_order_id ) {
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! empty( $item['npay_product_order_id'] ) && $item['npay_product_order_id'] == $product_order_id ) {
					return array( 'item_id' => $item_id, 'item' => $item );
				}
			}

			return null;
		}
		public static function update_npay_orders( $order, $npay_orders ) {
			self::get_customer_info( $npay_orders );
			foreach ( $npay_orders as $npay_order ) {
				$order_item = self::get_order_item( $order, $npay_order->ProductOrder->ProductOrderID );

				if ( ! is_null( $order_item ) ) {
					$item_id = $order_item['item_id'];
					$item = $order_item['item'];
					$item->update_meta_data( '_npay_order', json_encode( $npay_order, JSON_UNESCAPED_UNICODE ) );
					$item->save_meta_data();

					if ( in_array( $npay_order->ProductOrder->ProductOrderStatus, array( MNP_Manager::PRODUCT_ORDER_STATUS_RETURNED, MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED ) ) ) {
						if ( ! self::has_refund_order( $order, $item_id ) ) {
							self::create_refund_order( $order, $item['line_total'], $item_id, $item );
						}
					}
					$order->update_meta_data( '_npay_order', $npay_order->Order );
				}
			}
			self::update_npay_fee_items( $order, $npay_orders );
			self::update_stock( $order, $npay_orders );
			$order->save();
			$order = wc_get_order( $order->get_id() );
			$all_npay_orders = array();
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! empty( $item['npay_product_order_id'] ) && ! empty( $item['npay_order'] ) ) {
					$all_npay_orders[] = json_decode( $item['npay_order'] );
				}
			}

			self::update_order_status( $order, $all_npay_orders );

			self::update_shipping( $order, $all_npay_orders );
			self::update_delivery_info( $order, $npay_orders[0] );

			self::remove_npay_membership_filter();
		}
		protected static function add_npay_items_to_order( $order, $npay_orders ) {
			MNP_Cart::generate_cart( $npay_orders, $order );
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product                    = $values['data'];
				$item                       = new WC_Order_Item_Product();
				$item->legacy_values        = $values; // @deprecated For legacy actions.
				$item->legacy_cart_item_key = $cart_item_key; // @deprecated For legacy actions.

				$item->set_props( array(
					'quantity'     => $values['quantity'],
					'variation'    => $values['variation'],
					'subtotal'     => $values['line_subtotal'],
					'total'        => $values['line_total'],
					'subtotal_tax' => $values['line_subtotal_tax'],
					'total_tax'    => $values['line_tax'],
					'taxes'        => $values['line_tax_data'],
				) );

				if ( $product ) {
					$item->set_props( array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
					) );
				}
				$item->set_backorder_meta();
				do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );

				// Add item to order and save.
				if ( false === $order->add_item( $item ) ) {
					throw new Exception( "주문 정보를 생성할 수 없습니다." );
				}
			}

			$order->save();

			$order = wc_get_order( $order->get_id() );
			foreach ( $npay_orders as $npay_order ) {
				$order_item = self::get_order_item( $order, $npay_order->ProductOrder->ProductOrderID );

				if ( ! is_null( $order_item ) ) {
					$item_id = $order_item['item_id'];
					$item = $order_item['item'];
					$item->update_meta_data( '_npay_order', json_encode( $npay_order, JSON_UNESCAPED_UNICODE ) );
					$item->save_meta_data();

					if ( in_array( $npay_order->ProductOrder->ProductOrderStatus, array( MNP_Manager::PRODUCT_ORDER_STATUS_RETURNED, MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED ) ) ) {
						if ( ! self::has_refund_order( $order, $item_id ) ) {
							self::create_refund_order( $order, $item['line_total'], $item_id, $item );
						}
					}
					$order->update_meta_data( '_npay_order', $npay_order->Order );
				}
			}
			self::add_npay_fee_items( $order, $npay_orders );

			$order->calculate_shipping();
			$order->calculate_totals();

			$order->save();
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( MNP_Manager::PRODUCT_ORDER_STATUS_RETURNED == $item['npay_product_order_status'] ) {
					self::create_refund_order( $order, $item['line_total'], $item_id, $item );
				}
			}
		}
		protected static function create_refund_order( $order, $amount, $item_id = null, $item = null ) {
			$line_items = array();

			if ( $item_id ) {
				$line_items = array(
					$item_id => array(
						'qty'          => $item->get_quantity(),
						'refund_total' => $item->get_total(),
						'refund_tax'   => array(
							'1' => $item->get_total_tax()
						)
					)
				);
			}

			$refund_order = wc_create_refund( array(
				'amount'     => floatval( $item->get_total() ) + floatval( $item->get_total_tax() ),
				'order_id'   => $order->get_id(),
				'line_items' => $line_items,
			) );

			$refund_order->update_meta_data( 'is_npay_order', 'yes' );
			if ( $item_id ) {
				$refund_order->update_meta_data( 'npay_order_item_id', $item_id );
			}
			$refund_order->save_meta_data();
		}
		protected static function get_shipping_total( $order ) {
			$shipping_total = 0;
			foreach ( $order->get_fees() as $fee_id => $fee ) {
				if ( is_array( $fee ) ) {
					if ( 'yes' == $fee['npay_shipping_item'] ) {
						$shipping_total += floatval( $fee['line_total'] );
					}
				} else {
					if ( 'yes' == $fee->get_meta( '_npay_shipping_item' ) ) {
						$shipping_total += floatval( $fee->get_total() );
					}
				}
			}

			if ( $shipping_total == 0 ) {
				$shipping_total = $order->get_total_shipping();
			}

			return $shipping_total;
		}
		protected static function update_order_status( $order, $npay_orders ) {

			if ( empty( $npay_orders ) ) {
				return;
			}
			if ( 'yes' == get_option( 'mnp-use-partial-refunded-order-status', 'no' ) && 'partial-refunded' == $order->get_status() ) {
				return;
			}

			$to_state     = '';
			$order_status = array();

			$is_cancel_request   = false;
			$is_return_request   = false;
			$is_exchange_request = false;
			$is_cancelled        = false;

			$cancelled_product_order_ids = array();

			foreach ( $npay_orders as $npay_order ) {
				$product_order_status = $npay_order->ProductOrder->ProductOrderStatus;

				if ( MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERING == $product_order_status && apply_filters( 'mnp_merge_shipping_and_shipped_order_status', false ) ) {
					$order_status[] = MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERED;
				} else {
					$order_status[] = $product_order_status;
				}

				$is_cancel_request   |= MNP_Order_Item::is_cancel_request( $npay_order );
				$is_return_request   |= MNP_Order_Item::is_return_request( $npay_order );
				$is_exchange_request |= MNP_Order_Item::is_exchange_request( $npay_order );
				if ( MNP_Order_Item::is_cancelled( $npay_order ) ) {
					$is_cancelled                  = true;
					$cancelled_product_order_ids[] = $npay_order->ProductOrder->ProductOrderID;
				}
			}

			$order_status = array_unique( $order_status );

			if ( $is_cancel_request ) {
				$to_state = 'cancel-request';
			} else if ( $is_return_request ) {
				$to_state = 'return-request';
			} else if ( $is_exchange_request ) {
				$to_state = 'exchange-request';
			} else if ( in_array( MNP_Manager::PRODUCT_ORDER_STATUS_PAYED, $order_status ) ||
			            in_array( MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERING, $order_status ) ||
			            in_array( MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERED, $order_status )
			) {
				$to_state = $order->get_status();

				$npay_processing_status = apply_filters( 'mnp_order_status_for_processing', 'processing', $order );
				if ( in_array( $to_state, array( 'on-hold', 'pending', 'place-order', 'return-request', 'exchange-request', 'cancel-request' ) ) ) {
					if ( in_array( $to_state, array( 'on-hold', 'pending' ) ) ) {
						self::$payment_complete = true;
					}

					$to_state = $npay_processing_status;
				}

				if ( $npay_processing_status == $to_state && 1 == count( $order_status ) && MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERING == $order_status[0] ) {
					$to_state = 'shipping';
				}

				if ( in_array( $to_state, array( $npay_processing_status, 'shipping' ) ) && 1 == count( $order_status ) && MNP_Manager::PRODUCT_ORDER_STATUS_DELIVERED == $order_status[0] ) {
					$to_state = 'shipped';
				}
			} else {
				$waiting   = in_array( MNP_Manager::PRODUCT_ORDER_STATUS_PAYMENT_WAITING, $order_status );
				$refunded  = in_array( MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED, $order_status );
				$cancelled = in_array( MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED_BY_NOPAYMENT, $order_status );
				$returned  = in_array( MNP_Manager::PRODUCT_ORDER_STATUS_RETURNED, $order_status );
				$completed = in_array( MNP_Manager::PRODUCT_ORDER_STATUS_PURCHASE_DECIDED, $order_status ) || in_array( MNP_Manager::PRODUCT_ORDER_STATUS_EXCHANGED, $order_status );

				if ( $waiting ) {
					$to_state = 'on-hold';
				} else if ( $completed ) {
					$to_state = apply_filters( 'mnp_order_status_for_completed', 'completed', $order );
				} else if ( $refunded ) {
					$to_state = 'refunded';
				} else if ( $cancelled ) {
					$to_state = 'cancelled';
				} else if ( $returned ) {
					if ( 'refunded' != $order->get_status() ) {
						$shipping_total = self::get_shipping_total( $order );
						if ( floatval( $order->get_total() ) == floatval( $order->get_total_refunded() ) + floatval( $order->get_total_tax_refunded() ) + floatval( $shipping_total ) ) {
							$to_state = 'refunded';
						} else {
							if ( $shipping_total > 0 ) {
								self::create_refund_order( $order, $shipping_total );
							}
							$to_state = apply_filters( 'mnp_order_status_for_completed', 'completed', $order );
						}
					} else {
						$to_state = 'refunded';
					}
				}
			}

			$to_state = apply_filters( 'mnp_update_order_status', $to_state, $order );

			if ( ! in_array( $to_state, array( 'refunded', 'cancelled' ) ) && 'yes' == get_option( 'mnp-use-partial-refunded-order-status', 'no' ) && $is_cancelled ) {
				$processed_cancelled_product_order_ids = $order->get_meta( 'mnp_cancelled_product_order_ids' );

				if ( ! is_array( $processed_cancelled_product_order_ids ) || ! empty( array_diff( $cancelled_product_order_ids, $processed_cancelled_product_order_ids ) ) ) {
					$order->update_meta_data( 'mnp_cancelled_product_order_ids', $cancelled_product_order_ids );
					$order->save_meta_data();

					$order->update_status( 'partial-refunded' );

					return;
				}
			}

			if ( $order->get_status() != $to_state ) {
				$order->update_status( $to_state );
			}
		}
		public static function update_stock( $order, $npay_orders ) {

			if ( 'no' == get_option( 'mnp-use-stock-management', 'yes' ) ) {
				return;
			}

			$stock = json_decode( $order->get_meta( '_naverpay_manage_stock' ), true );

			if ( empty( $stock ) ) {
				$stock = array();
			}

			foreach ( $npay_orders as $npay_order ) {
				$product = wc_get_product( $npay_order->ProductOrder->SellerProductCode );

				if ( $product && $product->managing_stock() ) {
					switch ( $npay_order->ProductOrder->ProductOrderStatus ) {
						case MNP_Manager::PRODUCT_ORDER_STATUS_PAYMENT_WAITING :
						case MNP_Manager::PRODUCT_ORDER_STATUS_PAYED :
							if ( 'reduced' != $stock[ $product->get_id() ] ) {
								$stock_change                = $npay_order->ProductOrder->Quantity;
								$new_stock                   = $product->reduce_stock( $stock_change );
								$stock[ $product->get_id() ] = 'reduced';

								$order->add_order_note( sprintf( __( 'Item #%s stock reduced from %s to %s.', 'woocommerce' ), $npay_order->ProductOrder->SellerProductCode, $new_stock + $stock_change, $new_stock ) );
								$order->send_stock_notifications( $product, $new_stock, $npay_order->ProductOrder->Quantity );
							}
							break;
						case MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED :
						case MNP_Manager::PRODUCT_ORDER_STATUS_RETURNED :
						case MNP_Manager::PRODUCT_ORDER_STATUS_CANCELED_BY_NOPAYMENT :
							if ( 'reduced' == $stock[ $product->get_id() ] ) {
								$old_stock    = $product->stock;
								$stock_change = $npay_order->ProductOrder->Quantity;
								$new_quantity = $product->increase_stock( $stock_change );

								$stock[ $product->get_id() ] = '';
								$order->add_order_note( sprintf( __( 'Item #%s stock increased from %s to %s.', 'woocommerce' ), $product->get_id(), $old_stock, $new_quantity ) );
							}
							break;
					}
				}
			}

			$order->update_meta_data( '_naverpay_manage_stock', json_encode( $stock ) );
		}

		public static function woocommerce_payment_complete_reduce_order_stock( $stock_reduced, $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && 'naverpay' == $order->get_payment_method() && 'yes' == get_option( 'mnp-use-stock-management', 'yes' ) ) {
				$stock_reduced = false;
			}

			return $stock_reduced;
		}

		public static function migrate_npay_order( $order_id ) {

			$order = wc_get_order( $order_id );

			try {
				add_filter( 'woocommerce_product_is_in_stock', '__return_true' );
				add_filter( 'woocommerce_product_backorders_allowed', '__return_true' );

				mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );

				MNP_Cart::backup_cart();
				$npay_orders = self::get_npay_orders( $order_id );
				self::delete_npay_order_items( $order );
				// Reload order for support WC 3.0
				$order = wc_get_order( $order_id );

				$order->remove_order_items();

				self::add_npay_items_to_order( $order, $npay_orders );
				$order->set_payment_method( 'naverpay' );
				$order->set_payment_method_title( 'NPay' );
				$order->save();
				self::update_order_status( $order, $npay_orders );
				$product_order_ids = array();
				foreach ( $npay_orders as $npay_order ) {
					$product_order_ids[] = $npay_order->ProductOrder->ProductOrderID;
				}
				$npay_order = current( $npay_orders );
				$order->update_meta_data( '_npay_version', MNP()->version );
				$order->update_meta_data( '_npay_order', $npay_order->Order );
				$order->update_meta_data( '_naverpay_order_id', $npay_order->Order->OrderID );
				$order->update_meta_data( '_naverpay_product_order_id', implode( ',', $product_order_ids ) );

				MNP_Cart::recover_cart();

				remove_filter( 'woocommerce_product_is_in_stock', '__return_true' );
				remove_filter( 'woocommerce_product_backorders_allowed', '__return_true' );
				self::save_custom_data( $order, $npay_orders );

			} catch ( Exception $e ) {
			}
		}

		public static function refresh_npay_order() {
			$order_id = absint( wp_unslash( $_REQUEST['order_id'] ) );
			$order    = wc_get_order( $order_id );

			try {
				do_action( 'mnp_before_refresh_npay_order', $order );
				add_filter( 'woocommerce_product_is_in_stock', '__return_true' );
				add_filter( 'woocommerce_product_backorders_allowed', '__return_true' );

				mnp_maybe_define_constant( 'WOOCOMMERCE_CART', true );

				MNP_Cart::backup_cart();
				$npay_orders = self::get_npay_orders( $order_id );
				self::delete_npay_order_items( $order );

				// Reload order for support WC 3.0
				$order = wc_get_order( $order_id );
				self::add_npay_items_to_order( $order, $npay_orders );
				$order->set_payment_method( 'naverpay' );
				$order->set_payment_method_title( 'NPay' );
				if ( is_callable( array( $order, 'set_currency' ) ) ) {
					$order->set_currency( 'KRW' );
				}

				do_action( 'woocommerce_checkout_create_order', $order, array() );

				$order->save();
				self::update_order_status( $order, $npay_orders );

				self::update_shipping( $order, $npay_orders );
				$product_order_ids = array();
				foreach ( $npay_orders as $npay_order ) {
					$product_order_ids[] = $npay_order->ProductOrder->ProductOrderID;
				}
				$npay_order = current( $npay_orders );
				$order->update_meta_data( '_npay_version', MNP()->version );
				$order->update_meta_data( '_npay_order', $npay_order->Order );
				$order->update_meta_data( '_naverpay_order_id', $npay_order->Order->OrderID );
				$order->update_meta_data( '_naverpay_product_order_id', implode( ',', $product_order_ids ) );

				MNP_Cart::recover_cart();

				remove_filter( 'woocommerce_product_is_in_stock', '__return_true' );
				remove_filter( 'woocommerce_product_backorders_allowed', '__return_true' );
				self::save_custom_data( $order, $npay_orders );

				do_action( 'mnp_after_refresh_npay_order', $order, $npay_orders );

			} catch ( Exception $e ) {
				wp_send_json_error( $e->getMessage() );
			}

			wp_send_json_success();
		}


		public static function get_customer_info( $npay_orders ) {
			self::$customer_info = array();

			if ( ! empty( $npay_orders ) ) {
				$npay_order = $npay_orders[0];

				if ( property_exists( $npay_order->ProductOrder, 'MerchantCustomCode1' ) ) {
					parse_str( $npay_order->ProductOrder->MerchantCustomCode1, self::$customer_info );
				}
			}

			if ( isset( self::$customer_info['user_id'] ) ) {
				wp_set_current_user( self::$customer_info['user_id'] );
			} else {
				wp_set_current_user( 0 );
			}

			if ( isset( self::$customer_info['user_role'] ) ) {
				add_filter( 'mshop_membership_get_user_role', __CLASS__ . '::npay_membership', 10, 2 );
			}
		}

		public static function save_custom_data( $order, $npay_orders ) {
			$params = array();

			if ( ! empty( $npay_orders ) ) {
				$npay_order = current( $npay_orders );

				if ( property_exists( $npay_order->ProductOrder, 'MerchantCustomCode2' ) ) {
					parse_str( $npay_order->ProductOrder->MerchantCustomCode2, $params );

					foreach ( $params as $key => $value ) {
						$order->update_meta_data( '_' . $key, $value );
					}
				}
			}
		}
		public static function npay_membership( $role, $user_id ) {
			if ( isset( self::$customer_info['user_role'] ) ) {
				$role = self::$customer_info['user_role'];
			}

			return $role;
		}

		public static function remove_npay_membership_filter() {
			remove_filter( 'mshop_membership_get_user_role', __CLASS__ . '::npay_membership', 10 );
		}

		public static function woocommerce_admin_order_items_after_line_items( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'naverpay' == $order->get_payment_method() && ! in_array( $order->get_status(), array(
					'cancelled',
					'refunded'
				) )
			) {
				echo '<tr class="npay"><td colspan="10" class="naverpay-admin"><div>';
				include( 'admin/meta-boxes/views/html-order-items-wc.php' );
				echo '</div></td></tr>';
			}
		}
		public static function woocommerce_add_order_item_meta( $item_id, $values, $cart_item_key ) {

			if ( ! empty( $values['_npay_product_order_id'] ) ) {
				wc_add_order_item_meta( $item_id, '_npay_product_order_id', $values['_npay_product_order_id'] );
			}

			if ( ! empty( $values['_npay_product_order_status'] ) ) {
				wc_add_order_item_meta( $item_id, '_npay_product_order_status', $values['_npay_product_order_status'] );
			}

			if ( ! empty( $values['_npay_order'] ) ) {
				wc_add_order_item_meta( $item_id, '_npay_order', $values['_npay_order'] );
			}
		}
		public static function woocommerce_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {

			$npay_keys = array( '_npay_product_order_id', '_npay_product_order_status', '_npay_order', '_npay_bundle_product_order_ids' );

			$props = array_intersect_key( $values, array_flip( $npay_keys ) );

			if ( ! empty( $props ) ) {
				foreach ( $props as $key => $value ) {
					$item->update_meta_data( $key, $value, true );
				}
			}
		}
		public static function woocommerce_cancel_unpaid_order( $flag, $order ) {
			if ( 'naverpay' == $order->get_payment_method() ) {
				$flag = false;
			}

			return $flag;
		}
		static function woocommerce_order_needs_shipping_address( $needs_address, $hide, $order ) {
			if ( 'naverpay' == $order->get_payment_method() ) {
				$needs_address = true;
			}

			return $needs_address;
		}

		public static function maybe_update_ship_to_billing( $order_details, $order ) {
			if ( $order && 'naverpay' == $order->get_payment_method() ) {
				$order_details['ship_to_billing'] = false;
			}

			return $order_details;
		}
		public static function maybe_skip_append_address_book( $flag, $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && 'naverpay' == $order->get_payment_method() ) {
				$flag = false;
			}

			return $flag;
		}
	}
}

