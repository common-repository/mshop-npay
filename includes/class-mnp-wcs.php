<?php



if ( ! class_exists( 'MNP_Wcs' ) ) {

	class MNP_Wcs {
		static function init() {
			if ( MNP_Manager::use_wcs() && ( MNP_Manager::is_production() || ( MNP_Manager::is_sandbox() && MNP_Manager::is_test_user() ) ) ) {
				add_action( 'wp_head', __CLASS__ . '::wp_head' );
				add_action( 'wp_footer', __CLASS__ . '::wp_footer' );
			}
		}
		static function wp_head() {

			$home_url = parse_url( home_url() );
			$server   = $home_url['host'];

			if ( 0 === strpos( $server, 'www.' ) ) {
				$server = substr( $server, 4, strlen( $server ) - 4 );
			}

			wp_enqueue_script( 'mnp-wcs', 'https://wcs.naver.net/wcslog.js', array(), MNP_VERSION );
			ob_start();
			?>
            <script type="text/javascript">
                jQuery( document ).ready( function ( $ ) {
                    if (!wcs_add)
                        var wcs_add = {};
                    wcs_add['wa'] = "<?php echo MNP_Manager::common_auth_key(); ?>";
                    wcs.inflow( "<?php echo $server; ?>" );
                } );
            </script>
			<?php
			$scripts = ob_get_clean();
			$scripts = trim( preg_replace( '#<script[^>]*>(.*)</script>#is', '$1', $scripts ) );
			wp_add_inline_script( 'mnp-wcs', $scripts );

			if ( apply_filters( 'mnp_wcs_print_script', true ) ) {
				wp_print_scripts( 'mnp-wcs' );
			}
		}
		static function wp_footer() {
			?>
            <script type="text/javascript">
                jQuery( document ).ready( function ( $ ) {
                    wcs_do();
                } );
            </script>
			<?php
		}
	}

	MNP_Wcs::init();
}

