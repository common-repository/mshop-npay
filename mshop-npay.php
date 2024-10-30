<?php
/*
Plugin Name: 우커머스 네이버페이
Plugin URI: 
Description: 코드엠샵에서 개발, 운영되는 네이버페이 - 주문형 결제 시스템으로 네이버페이센터 어드민과 연동합니다.
Version: 3.3.7
Author: CodeMShop
Author URI: www.codemshop.com
License: GPLv2 or later
*/




if ( ! class_exists( 'MSHOP_NPay' ) ) {

	final class MSHOP_NPay {

		protected $slug;

		protected static $_instance = null;
		public $version = '3.3.7';
		public $plugin_url;
		public $naverpay_register_order_url;
		public $naverpay_ordersheet_url;
		public $naverpay_wishlist_url;
		public $naverpay_wishlist_popup_url;
		public $plugin_path;
		public $template_url;
		public function __construct() {

			$this->slug = 'mshop-npay';


			define( 'MNP_VERSION', $this->version );
			define( 'MNP_PLUGIN_FILE', __FILE__ );

			add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

			add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
			add_action( 'wp', array( $this, 'setup_schedule' ) );
			add_action( 'naverpay_cron', array( $this, 'naverpay_cron' ) );
			add_action( 'mnp_sync_review', array( $this, 'naverpay_cron' ) );

			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );
			add_filter( "plugin_action_links", array( $this, 'plugin_action_links' ), 10, 4 );
		}

		function slug() {
			return $this->slug;
		}

		function setup_schedule() {
			try {
				if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'mnp_sync_review' ) ) {

					as_unschedule_all_actions( 'mnp_sync_review' );

					as_schedule_recurring_action(
						time(),
						HOUR_IN_SECONDS,
						'mnp_sync_review'
					);

					if ( wp_next_scheduled( 'naverpay_cron' ) ) {
						wp_unschedule_hook( 'naverpay_cron' );
					}
				}
			} catch ( Exception $e ) {

			}

		}

		function naverpay_cron() {
			MNP_Comments::sync_review();
		}

		public function plugin_url() {
			if ( $this->plugin_url ) {
				return $this->plugin_url;
			}

			return $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		public function plugin_path() {
			if ( $this->plugin_path ) {
				return $this->plugin_path;
			}

			return $this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
		}
		public function template_path() {
			return $this->plugin_path() . '/templates/';
		}

		function includes() {
			include_once( 'includes/class-mnp-wcs.php' );

			if ( is_admin() ) {
				$this->admin_includes();
			}

			if ( defined( 'DOING_AJAX' ) ) {
				$this->ajax_includes();
			}

			if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
				$this->frontend_includes();
			}
		}

		public function admin_includes() {
			include_once( 'includes/admin/class-mnp-admin.php' );
			include_once( 'includes/admin/class-mnp-admin-post-types.php' );
			include_once( 'includes/class-mnp-query.php' );
		}

		public function ajax_includes() {
			require_once( 'includes/class-mnp-ajax.php' );
		}

		public function frontend_includes() {

		}
		public function woocommerce_init() {
			require_once( 'includes/class-mnp-autoloader.php' );
			require_once( 'includes/mnp-functions.php' );
			require_once( 'includes/mnp-hpos.php' );
			require_once( 'includes/class-mnp-post-types.php' );
			require_once( 'includes/class-mnp-callback.php' );
			require_once( 'includes/class-mnp-rest-api.php' );
			require_once( 'includes/class-mnp-shortcodes.php' );
			require_once( 'includes/class-mnp-exporter.php' );

			$this->includes();

			foreach ( wc_get_product_types() as $type => $label ) {
				add_action( 'woocommerce_process_product_meta_' . $type, 'MNP_Meta_Box_Product_Data::woocommerce_process_product_meta' );
			}
		}
		public function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
			if ( ! empty( $plugin_data['slug'] ) && $this->slug == $plugin_data['slug'] ) {
				$actions['settings'] = '<a href="' . admin_url( '/admin.php?page=mnp_settings' ) . '">설정</a>';
				$actions['manual']   = '<a target="_blank" href="https://manual.codemshop.com/docs/pgall-npay/">매뉴얼</a>';
			}

			return $actions;
		}
		public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
			if ( ! empty( $plugin_data['slug'] ) && $this->slug == $plugin_data['slug'] ) {

				$plugin_meta[] = '<a target="_blank" href="https://wordpress.org/plugins/mshop-npay/#reviews">별점응원하기</a>';
				$plugin_meta[] = '<a target="_blank" href="https://wordpress.org/plugins/search/codemshop/">함께 사용하면 좋은 플러그인</a>';
				$plugin_meta[] = '<a target="_blank" href="https://www.codemshop.com/product-category/outside/">프리미엄 플러그인</a>';
			}

			return $plugin_meta;
		}
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'mshop-npay', false, dirname( plugin_basename( __FILE__ ) ) . "/languages/" );
		}

		public function declare_woocommerce_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}

	function MNP() {
		return MSHOP_NPay::instance();
	}


	return MNP();
}