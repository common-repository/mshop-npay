<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MNP_Shortcodes {
	public static function init() {
		$shortcodes = array(
			'mnp_purchase'      => array( __CLASS__, 'output_npay_purchase_button' ),
			'mnp_cart_button'   => array( __CLASS__, 'output_npay_cart_button' ),
			'pafw_dc_npay_cart' => array( __CLASS__, 'output_pafw_dc_npay_cart_button' ),
		);

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( $shortcode, $function );
		}

		add_filter( 'pafw_dc_npay_cart_default_attrs', array( __CLASS__, 'add_npay_cart_default_attrs' ) );

		add_action( 'pafw_dc_before_npay_cart_block', array( __CLASS__, 'enqueue_scripts' ) );
		add_filter( 'pafw_dc_refresh_fragment', array( __CLASS__, 'maybe_disable_refresh_fragment' ), 10, 3 );
	}

	public static function enqueue_scripts() {
		if ( ! apply_filters( 'mnp_enabled', true ) && ! empty( $_GET['s'] ) ) {
			return;
		}

		if ( MNP_Manager::is_operable() && MNP_Cart::cart_contains_npay_items() ) {
			$dependencies = array(
				'underscore',
				'jquery',
				'jquery-ui-core',
			);

			if ( 'yes' == get_option( 'mnp-npay-script', 'no' ) ) {
				wp_enqueue_script( 'mnp-naverpay', MNP_Manager::button_js_url( wp_is_mobile() ? 'mobile' : 'pc' ), array( 'jquery' ), MNP_VERSION );
				$dependencies[] = 'mnp-naverpay';
			}

			wp_enqueue_style( 'mnp-frontend', MNP()->plugin_url() . '/assets/css/naverpay-cart.css' );

			wp_enqueue_script( 'jquery-block-ui', MNP()->plugin_url() . '/assets/js/jquery.blockUI.js', $dependencies );
			wp_enqueue_script( 'mnp-frontend', MNP()->plugin_url() . '/assets/js/cart.js', $dependencies, MNP_VERSION );
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

		}
	}
	public static function add_npay_cart_default_attrs( $default_attrs ) {
		return array_merge( $default_attrs, array(
			'default_template_path' => MNP()->template_path(),
			'align'                 => 'left',
		) );
	}
	public static function output_pafw_dc_npay_cart_button( $attrs, $content, $tag ) {
		if ( class_exists( 'PAFW_DC_Shortcodes' ) ) {
			return PAFW_DC_Shortcodes::pafw_shortcode_wrappers( $attrs, $content, $tag );
		}
	}
	public static function maybe_disable_refresh_fragment( $flag, $name, $param ) {
		if ( 'npay-cart' == $name ) {
			$flag = false;
		}

		return $flag;
	}
	public static function output_npay_purchase_button( $attrs, $content, $tag ) {
		$params = shortcode_atts( array(
			'product_id' => '',
			'quantity'   => '1',
		), $attrs );

		if ( ! apply_filters( 'mnp_enabled', true ) ) {
			return;
		}

		$product = wc_get_product( $params['product_id'] );

		if ( ! $product || $product->is_virtual() ) {
			return;
		}

		$support_product_types = apply_filters( 'mnp_support_product_types', array( 'variable', 'grouped' ) );

		if ( MNP_Manager::is_operable() && MNP_Manager::is_purchasable( $product->get_id() ) ) {

			$purchasable = 'grouped' == $product->get_type() ? true : $product->is_purchasable();

			if ( $purchasable && ( in_array( $product->get_type(), $support_product_types ) || ( $product->is_type( 'simple' ) && $product->get_price() > 0 ) ) ) {
				$dependencies = apply_filters( 'mnp_script_dependencies', array( 'jquery', 'jquery-ui-core', 'underscore' ) );

				if ( 'yes' == get_option( 'mnp-npay-script', 'no' ) ) {
					wp_enqueue_script( 'mnp-naverpay', MNP_Manager::button_js_url( wp_is_mobile() ? 'mobile' : 'pc' ), array( 'jquery' ), MNP_VERSION );
					$dependencies[] = 'mnp-naverpay';
				}

				wp_enqueue_script( 'jquery-block-ui', MNP()->plugin_url() . '/assets/js/jquery.blockUI.js', $dependencies );
				wp_enqueue_script( 'mnp-shortcode', MNP()->plugin_url() . '/assets/js/shortcode.js', $dependencies, MNP_VERSION );
				wp_localize_script( 'mnp-shortcode', '_mnp', array(
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

				wp_enqueue_style( 'mnp-shortcode', MNP()->plugin_url() . '/assets/css/naverpay-product.css' );

				ob_start();

				wc_get_template( 'shortcodes/naverpay-button.php', array( 'product' => $product, 'params' => $params ), '', MNP()->template_path() );

				return ob_get_clean();

			}
		}
	}

	static function output_npay_cart_button() {
		self::enqueue_scripts();

		ob_start();

		wc_get_template( 'pafw-diy-checkout/npay-cart/type-a.php', array(), '', MNP()->template_path() );

		return ob_get_clean();
	}
}

MNP_Shortcodes::init();
