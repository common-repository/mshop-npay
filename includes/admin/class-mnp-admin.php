<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'MNP_Admin' ) ) :

	class MNP_Admin {

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

			add_action( 'add_meta_boxes', array( 'MNP_Meta_Box_Order', 'add_meta_boxes' ), 10, 2 );
		}

		function admin_menu() {
			add_menu_page( __( '네이버페이 결제관리', 'mshop-npay' ), __( '네이버페이 결제관리', 'mshop-npay' ), 'manage_woocommerce', 'mnp_settings', '', MNP()->plugin_url() . '/assets/images/mshop-icon.png', '20.9021211223' );
			add_submenu_page( 'mnp_settings', __( '기본 설정', 'mshop-npay' ), __( '기본 설정', 'mshop-npay' ), 'manage_woocommerce', 'mnp_settings', 'MNP_Settings::output' );

			if ( 'active' == get_option( 'mshop-naverpay-status' ) ) {
				add_submenu_page( 'mnp_settings', __( '카테고리 설정', 'mshop-npay' ), __( '카테고리 설정', 'mshop-npay' ), 'manage_woocommerce', 'mnp_category_settings', array(
					$this,
					'category_settings'
				) );
				add_submenu_page( 'mnp_settings', __( '주문 관리', 'mshop-npay' ), __( '주문 관리', 'mshop-npay' ), 'manage_woocommerce', 'edit.php?post_type=shop_order&paymethod=naverpay' );
				add_submenu_page( 'mnp_settings', __( '문의 관리', 'mshop-npay' ), __( '문의 관리', 'mshop-npay' ), 'manage_woocommerce', 'mnp_customer_inquiry', array(
					$this,
					'customer_inquiry_page'
				) );

				add_submenu_page( 'mnp_settings', __( '송장업로드', 'mshop-npay' ), __( '송장업로드', 'mshop-npay' ), 'manage_woocommerce', 'mnp_sheet', 'MNP_Settings_Sheet::output' );
			}

			add_submenu_page( 'mnp_settings', __( '매뉴얼', 'mshop-npay' ), __( '매뉴얼', 'mshop-npay' ), 'manage_woocommerce', 'mnp_manual', '' );
		}

		function customer_inquiry_page() {
			require_once 'class-mnp-customer-inquiry-list-table.php';

			ob_start();
			include_once( 'templates/customer-inquiry-list.php' );
			echo ob_get_clean();
		}

		function category_settings() {
			ob_start();
			include_once( 'templates/category-settings.php' );
			echo ob_get_clean();
		}

		static function admin_enqueue_scripts() {
			wp_enqueue_style( 'naverpay-admin', MNP()->plugin_url() . '/assets/css/naverpay-admin.css', array(), MNP_VERSION );
			wp_enqueue_script( 'mnp-admin-menu', MNP()->plugin_url() . '/assets/js/admin/admin-menu.js', array( 'jquery' ), MNP_VERSION );
			wp_localize_script( 'mnp-admin-menu', '_mnp_admin_menu', array(
				'manual_url' => 'https://manual.codemshop.com/docs/pgall-npay/'
			) );
		}
	}

	new MNP_Admin();

endif;
