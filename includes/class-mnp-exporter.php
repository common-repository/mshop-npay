<?php



if ( ! class_exists( 'MNP_Exporter' ) ) {
	class MNP_Exporter {
		static function init() {
			add_filter( 'msex_order_field_type', array( __CLASS__, 'add_npay_field_type' ) );
			add_filter( 'msex_export_order_field_value', array( __CLASS__, 'maybe_set_npay_field' ), 10, 6 );
		}
		static function add_npay_field_type( $field_types ) {
			$field_types['npay_individual_custom_code'] = __( "개인통관고유번호(네이버페이)", "mshop-npay" );

			return $field_types;
		}
		static function maybe_set_npay_field( $field_value, $field, $order, $item, $item_index, $exporter ) {
			$field_type = msex_get( $field, 'field_type' );

			if ( 'npay_individual_custom_code' == $field_type ) {
				$npay_order = json_decode( $item->get_meta( '_npay_order' ), true );

				if ( $npay_order && isset( $npay_order['ProductOrder'] ) ) {
					$field_value = msex_get( $npay_order['ProductOrder'], 'IndividualCustomUniqueCode' );
				}
			}

			return $field_value;
		}
	}

	MNP_Exporter::init();
}

