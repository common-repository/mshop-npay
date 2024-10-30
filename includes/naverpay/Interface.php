<?php

class OrderInterface {
	public $salesCode = null;
	public $cpaInflowCode = null;
	public $mileageInflowCode = null;
	public $naverInflowCode = null;
	public $saClickId = null;
	public function __construct( $custom_data ) {
		$this->naverInflowCode = mnp_get( $_COOKIE, 'NA_CO' );
		$this->saClickId       = mnp_get( $_COOKIE, 'NVADID' );

		$customer_info = array();
		if ( is_user_logged_in() ) {
			$customer_info['user_id'] = get_current_user_id();

			if ( function_exists( 'mshop_membership_get_user_role' ) ) {
				$customer_info['user_role'] = mshop_membership_get_user_role();
			}
		}

		$this->merchantCustomCode1 = http_build_query( $customer_info );
		if ( ! empty( $custom_data ) ) {
			$this->merchantCustomCode2 = http_build_query( $custom_data );
		}

		if ( 'yes' == get_option( 'mnp-culture-benefit', 'no' ) ) {
			$this->mcstCultureBenefitYn = 'true';
		}
	}

}
