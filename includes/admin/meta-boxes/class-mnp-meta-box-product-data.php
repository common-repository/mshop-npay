<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MNP_Meta_Box_Product_Data {

	static function woocommerce_product_options_inventory_product_data() {
		woocommerce_wp_checkbox( array( 'id' => '_naverpay_unavailable', 'wrapper_class' => '', 'label' => __( 'NPay 구매불가', 'mshop-npay' ), 'description' => __( 'NPay 구매불가 상품 여부를 설정합니다.', 'mshop-npay' ) ) );

		?>
        <style>
            .woocommerce_options_panel .mnp_return_info p.form-field {
                margin: 0;
            }
        </style>
        <div class="options_group show_if_simple show_if_variable mnp_return_info" style="display: block;border-top: 1px solid #eee;">
            <p class="form-field"><label>네이버페이 상품별 반품 주소</label></p>
			<?php
			woocommerce_wp_text_input( array( 'id' => '_mnp_zipcode', 'label' => __( '우편번호', 'mshop-npay' ), 'placeholder' => esc_attr__( '우편번호', 'mshop-npay' ) ) );
			woocommerce_wp_text_input( array( 'id' => '_mnp_address1', 'style' => 'width: 90%', 'label' => __( '기본주소', 'mshop-npay' ), 'placeholder' => esc_attr__( '기본주소', 'mshop-npay' ) ) );
			woocommerce_wp_text_input( array( 'id' => '_mnp_address2', 'style' => 'width: 90%', 'label' => __( '상세주소', 'mshop-npay' ), 'placeholder' => esc_attr__( '상세주소', 'mshop-npay' ) ) );
			woocommerce_wp_text_input( array( 'id' => '_mnp_sellername', 'label' => __( '수취인 이름', 'mshop-npay' ), 'placeholder' => esc_attr__( '수취인 이름', 'mshop-npay' ) ) );
			woocommerce_wp_text_input( array( 'id' => '_mnp_contact1', 'label' => __( '연락처 #1', 'mshop-npay' ), 'placeholder' => esc_attr__( '연락처 #1', 'mshop-npay' ) ) );
			woocommerce_wp_text_input( array( 'id' => '_mnp_contact2', 'label' => __( '연락처 #2', 'mshop-npay' ), 'placeholder' => esc_attr__( '연락처 #2', 'mshop-npay' ) ) );
			?>
        </div>
		<?php

	}
	static function maybe_save_meta( $product, $meta_key ) {
		if ( ! empty( $_POST[ $meta_key ] ) ) {
			$product->update_meta_data( $meta_key, sanitize_text_field( $_POST[ $meta_key ] ) );
		} else {
			$product->delete_meta_data( $meta_key );
		}
	}

	static function woocommerce_process_product_meta( $post_id ) {
		$product = wc_get_product( $post_id );

		if ( is_a( $product, 'WC_Product' ) ) {
			$product->update_meta_data( '_naverpay_unavailable', isset( $_POST['_naverpay_unavailable'] ) ? sanitize_text_field( $_POST['_naverpay_unavailable'] ) : 'no' );

			self::maybe_save_meta( $product, '_mnp_zipcode' );
			self::maybe_save_meta( $product, '_mnp_address1' );
			self::maybe_save_meta( $product, '_mnp_address2' );
			self::maybe_save_meta( $product, '_mnp_sellername' );
			self::maybe_save_meta( $product, '_mnp_contact1' );
			self::maybe_save_meta( $product, '_mnp_contact2' );

			$product->save_meta_data();
		}
	}

}