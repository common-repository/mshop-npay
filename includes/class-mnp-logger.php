<?php



if ( ! class_exists( 'MNP_Logger' ) ) {
	class MNP_Logger {

		static $logger = null;

		static function enabled() {
			return 'yes' == get_option( 'mnp-enable-logger', 'no' );
		}

		static function get_logger() {
			if ( is_null( self::$logger ) ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					self::$logger = wc_get_logger();
				} else {
					self::$logger = new WC_Logger();
				}
			}

			return self::$logger;
		}

		static function add_log( $message ) {
			if ( self::enabled() ) {
				self::get_logger()->add( 'mshop-npay', $message );
			}
		}
	}
}

