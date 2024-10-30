<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MNP_Ajax {

	static $slug;

	public static function init() {
		self::$slug = MNP()->slug();
		self::add_ajax_events();
	}
	public static function add_ajax_events() {

		$ajax_events = array (
			'create_order'    => true,
			'checkout_cart'   => true,
			'add_to_wishlist' => true,
		);

		if ( is_admin() ) {
			$ajax_events = array_merge( $ajax_events, array (
				'update_settings'         => false,
				'resync_review'           => false,
				'refresh_npay_order'      => false,
				'answer_customer_inquiry' => 'MNP_Order::answer_customer_inquiry',
				'order_action'            => 'MNP_Order::order_action',
				'api_reset'               => false,
				'reset_sheet_fields'      => 'MNP_Sheets::reset_sheet_fields',
				'upload_sheets'           => 'MNP_Sheets::upload_sheets',
				'update_sheet_settings'   => 'MNP_Settings_Sheet::update_settings',
			) );
		}

		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			if ( is_string( $nopriv ) ) {
				add_action( 'wp_ajax_' . self::$slug . '-' . $ajax_event, $nopriv );
			} else {
				add_action( 'wp_ajax_' . self::$slug . '-' . $ajax_event, array ( __CLASS__, $ajax_event ) );

				if ( $nopriv ) {
					add_action( 'wp_ajax_nopriv_' . self::$slug . '-' . $ajax_event, array ( __CLASS__, $ajax_event ) );
				}
			}
		}
	}

	static function refresh_npay_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die();
		}

		MNP_Order::refresh_npay_order();
	}

	static function api_reset() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die();
		}

		MNP_API::reset_key();
		MNP_Manager::set_service_status( 'inactive' );

		wp_send_json_success( array ( 'reload' => true ) );
	}

	static function update_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die();
		}

		MNP_Settings::update_settings();
	}

	static function create_order() {
		MNP_Cart::create_order();
	}

	static function checkout_cart() {
		MNP_Cart::checkout_cart();
	}

	static function add_to_wishlist() {
		MNP_Cart::add_to_wishlist();
	}

	static function resync_review() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			die();
		}

		$terms = explode( ',', sanitize_text_field( $_REQUEST['values']['mnp-resync-term'] ) );

		if ( count( $terms ) < 2 ) {
			wp_send_json_error( __( '리뷰 동기화 기간을 입력해주세요.', 'mshop-npay' ) );
		}

		$begin = new DateTime( $terms[0] );
		$end   = new DateTime( $terms[1] );

		$interval = DateInterval::createFromDateString( '1 day' );
		$period   = new DatePeriod( $begin, $interval, $end );

		foreach ( $period as $dt ) {
			$from_date = gmdate( 'Y-m-d\TH:i:s', strtotime( $dt->format( 'Y-m-d 00:00:00' ) ) ) . date( 'P' );
			$to_date   = gmdate( 'Y-m-d\TH:i:s', strtotime( $dt->format( 'Y-m-d 23:59:59' ) ) ) . date( 'P' );

			MNP_Comments::sync_review( $from_date, $to_date );
		}


		wp_send_json_success( array ( 'message' => __( '리뷰가 동기화 되었습니다.', 'mshop-npay' ) ) );
	}
}

MNP_Ajax::init();
