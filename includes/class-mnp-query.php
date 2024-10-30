<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MNP_Query' ) ) {

	class MNP_Query {
		static function init() {
			if ( MNP_HPOS::enabled() ) {
				add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( __CLASS__, 'add_shop_order_list_table_prepare_items_query_args' ) );
			} else {
				add_filter( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 100 );
			}
		}
		public static function add_shop_order_list_table_prepare_items_query_args( $order_query_args ) {
			if ( ! empty( $_REQUEST['paymethod'] ) ) {
				$order_query_args['payment_method'] = $_REQUEST['paymethod'];
			}

			if ( ! empty( $_REQUEST['naverpay_order_id'] ) ) {
				$order_query_args['meta_query'] =  array(
					'relation' => 'OR',
					array(
						'key'     => '_naverpay_order_id',
						'value'   => sanitize_text_field( $_REQUEST['naverpay_order_id'] ),
						'compare' => '='
					),
					array(
						'key'     => '_naverpay_product_order_id',
						'value'   => sanitize_text_field( $_REQUEST['naverpay_order_id'] ),
						'compare' => 'LIKE'
					)
				);
			}

			return $order_query_args;
		}
		public static function pre_get_posts( $q ) {
			global $typenow;

			if ( 'shop_order' != $typenow ) {
				return;
			}

			if ( ! is_feed() && is_admin() && $q->is_main_query() ) {

				if ( ! empty( $_REQUEST['paymethod'] ) ) {
					$meta_query = $q->get( 'meta_query' );

					if ( empty( $meta_query ) ) {
						$meta_query = array();
					}

					$meta_query[] = array(
						'key'     => '_payment_method',
						'value'   => sanitize_text_field( $_REQUEST['paymethod'] ),
						'compare' => '='
					);

					$q->set( 'meta_query', $meta_query );
				}

				if ( ! empty( $_REQUEST['naverpay_order_id'] ) ) {
					$meta_query = $q->get( 'meta_query' );

					if ( empty( $meta_query ) ) {
						$meta_query = array();
					}

					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key'     => '_naverpay_order_id',
							'value'   => sanitize_text_field( $_REQUEST['naverpay_order_id'] ),
							'compare' => '='
						),
						array(
							'key'     => '_naverpay_product_order_id',
							'value'   => sanitize_text_field( $_REQUEST['naverpay_order_id'] ),
							'compare' => 'LIKE'
						)
					);

					$q->set( 'meta_query', $meta_query );
				}
			}
		}
	}

	MNP_Query::init();
}