<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MNP_Settings' ) ) :

	class MNP_Settings {

		static $api_status = null;

		static function init_action() {
			add_filter( 'msshelper_get_mshop-naverpay-api-key', __CLASS__ . '::get_api_key' );
			add_filter( 'msshelper_get_mshop-naverpay-domain', __CLASS__ . '::get_domain' );
			add_filter( 'msshelper_get_mshop-naverpay-connected-date', __CLASS__ . '::connected_date' );
		}

		static function get_api_key() {
			return self::$api_status->api_key;
		}

		static function get_domain() {
			return self::$api_status->site_url;
		}

		static function connected_date() {
			return self::$api_status->connected_date;
		}

		static function update_settings() {
			include_once MNP()->plugin_path() . '/includes/admin/setting-manager/mshop-setting-helper.php';

			$values = wc_clean( json_decode( stripslashes( $_REQUEST['values'] ), true ) );

			$_REQUEST = array_merge( $_REQUEST, $values );

			MSSHelper::update_settings( self::get_setting_fields() );

			MNP_API::register_service();

			wp_send_json_success();
		}

		static function get_setting_fields() {
			return array(
				'type'     => 'Tab',
				'id'       => 'mnp-setting-tab',
				'elements' => array(
					self::get_service_setting(),
					self::get_basic_setting(),
					self::get_point_setting(),
					self::get_review_setting(),
					self::get_delivery_setting(),
					self::get_advanced_setting()
				)
			);
		}

		static function get_service_setting() {
			return array(
				'type'     => 'Page',
				'class'    => 'active',
				'title'    => __( 'NPAY 서비스', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( '서비스 정보', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-api-key",
								"title"     => "API KEY",
								"className" => "fluid",
								"type"      => "Label",
							),
							array(
								"id"        => "mshop-naverpay-domain",
								"title"     => "사이트 URL",
								"className" => "fluid",
								"type"      => "Label",
							),
							array(
								"id"        => "mshop-naverpay-connected-date",
								"title"     => "서비스 연동일시",
								"className" => "fluid",
								"type"      => "Label",
							),
							array(
								'id'         => 'mshop-naverpay-api-reset',
								'title'      => '서비스 연결 해제',
								'label'      => '해제하기',
								'iconClass'  => 'icon settings',
								'className'  => '',
								'type'       => 'Button',
								'default'    => '',
								'actionType' => 'ajax',
								'ajaxurl'    => admin_url( 'admin-ajax.php' ),
								'action'     => MNP()->slug() . '-api_reset',
								"desc"       => "NPAY 서비스 연결을 해제합니다."
							)
						)
					)
				)
			);
		}

		static function get_basic_setting() {
			return array(
				'type'     => 'Page',
				'title'    => __( '기본 설정', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( '상점정보', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-merchant-id",
								"title"     => "상점 아이디",
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mshop-naverpay-auth-key",
								"title"     => "가맹점 인증키",
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mshop-naverpay-button-auth-key",
								"title"     => "버튼 인증키",
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mshop-naverpay-common-auth-key",
								"title"     => "네이버 공통 인증키",
								"className" => "fluid",
								"type"      => "Text",
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '동작 설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-operation-mode",
								"title"     => "운영 모드",
								"className" => "",
								"type"      => "Select",
								"default"   => 'None',
								'options'   => array(
									MNP_Manager::MODE_NONE       => __( '해당없음', 'mshop-npay' ),
									MNP_Manager::MODE_SANDBOX    => __( '개발환경(SandBox)', 'mshop-npay' ),
									MNP_Manager::MODE_PRODUCTION => __( '실환경(Production)', 'mshop-npay' )
								),
							),
							array(
								"id"        => "mshop-naverpay-test-user-id",
								"showIf"    => array( "mshop-naverpay-operation-mode" => MNP_Manager::MODE_SANDBOX ),
								"title"     => "테스트 사용자 아이디",
								"className" => "",
								"type"      => "Text",
								"default"   => "naverpay",
								'desc'      => '개발환경에서 NPay 테스트를 위한 사용자 아이디를 입력하세요.'
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '버튼 설정 (PC)', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-button-type-pc",
								"title"     => "버튼 종류",
								"className" => "",
								"type"      => "Select",
								"default"   => 'A',
								'options'   => array(
									'A' => __( 'A', 'mshop-npay' ),
									'B' => __( 'B', 'mshop-npay' ),
									'C' => __( 'C', 'mshop-npay' ),
									'D' => __( 'D', 'mshop-npay' ),
									'E' => __( 'E', 'mshop-npay' )
								),
							),
							array(
								"id"        => "mshop-naverpay-button-color-pc",
								"title"     => "버튼 색상",
								"className" => "",
								"type"      => "Select",
								"default"   => '1',
								'options'   => array(
									'1' => __( '1', 'mshop-npay' ),
									'2' => __( '2', 'mshop-npay' ),
									'3' => __( '3', 'mshop-npay' )
								),
							),
							array(
								"id"        => "mshop-naverpay-button-count-pc",
								"title"     => "버튼 개수",
								"className" => "",
								"type"      => "Select",
								"default"   => '2',
								'options'   => array(
									'1' => __( '1', 'mshop-npay' ),
									'2' => __( '2', 'mshop-npay' )
								),
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '버튼 설정 (모바일)', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-button-type-mobile",
								"title"     => "버튼 종류",
								"className" => "",
								"type"      => "Select",
								"default"   => 'MA',
								'options'   => array(
									'MA' => __( 'MA', 'mshop-npay' ),
									'MB' => __( 'MB', 'mshop-npay' )
								),
							),
							array(
								"id"        => "mshop-naverpay-button-count-mobile",
								"title"     => "버튼 개수",
								"className" => "",
								"type"      => "Select",
								"default"   => '2',
								'options'   => array(
									'1' => __( '1', 'mshop-npay' ),
									'2' => __( '2', 'mshop-npay' )
								),
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '버튼 고급설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-button-product",
								"title"     => "버튼 위치 (상품상세 페이지)",
								"className" => "fluid",
								"type"      => "Text",
                                "default"   => "woocommerce_after_add_to_cart_button",
								'desc2'     => __( "<div class='desc2'>테마 구조에 따라 상품상세 페이지에서 네이버페이 버튼의 표시위치가 달라집니다. 테마 파일을 참고해서 위치를 지정하세요.<br>ex) 테마파일내에 do_action( 'woocommerce_after_add_to_cart_button' ); 코드가 있는 위치에 네이버페이 버튼을 출력하려면, woocommerce_after_add_to_cart_button 을 입력합니다.</div>", "mshop-npay" ),
							),
							array(
								"id"        => "mshop-naverpay-button-cart",
								"title"     => "버튼 위치 (장바구니 페이지)",
								"className" => "fluid",
								"type"      => "Text",
								"default"   => "woocommerce_after_cart",
								'desc2'     => __( "<div class='desc2'>테마 구조에 따라 장바구니 페이지에서 네이버페이 버튼의 표시위치가 달라집니다. 테마 파일을 참고해서 위치를 지정하세요.<br>ex) 테마파일내에 do_action( 'woocommerce_after_cart' ); 코드가 있는 위치에 네이버페이 버튼을 출력하려면, woocommerce_after_cart 을 입력합니다.</div>", "mshop-npay" ),
							)
						)
					),
				)
			);
		}

		static function get_point_setting() {
			return array(
				'type'     => 'Page',
				'title'    => __( '포인트 설정', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( '상점정보', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-enable-earn-point",
								"title"     => "고객 포인트 적립 지원",
								"className" => "fluid",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>네이버페이 주문건에 대해 고객에게 포인트를 적립합니다.<br><a target='_blank' href='https://www.codemshop.com/shop/point/' style='text-decoration: underline;'>엠샵 포인트 플러그인</a>을 이용하는 경우에만 동작됩니다.</div>", 'mshop-npay' )
							),
							array(
								"id"        => "mnp-enable-earn-recommender-point",
								"title"     => "추천인 포인트 적립 지원",
								"className" => "fluid",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>네이버페이 주문건에 대해 추천인에게 포인트를 적립합니다.<br><a target='_blank' href='https://www.codemshop.com/shop/referee/' style='text-decoration: underline;'>엠샵 추천인 플러그인</a>을 이용하는 경우에만 동작됩니다.</div>", 'mshop-npay' )
							)
						)
					)
				)
			);
		}

		static function get_review_setting() {
			return array(
				'type'     => 'Page',
				'title'    => __( '구매평', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( ' 구매평 연동 기능', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-sync-review",
								"title"     => __( "활성화", 'mshop-npay' ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>구매평 연동 기능을 사용합니다.</div>", 'mshop-npay' )
							),
						),
					),
					array(
						'type'     => 'Section',
						'title'    => __( '구매평 연동 설정', 'mshop-npay' ),
						'showIf'   => array( "mshop-naverpay-sync-review" => "yes" ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-sync-normal-review",
								"title"     => __( "일반 구매평", 'mshop-npay' ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>일반 구매평 연동 기능을 사용합니다.</div>", 'mshop-npay' )
							),
							array(
								"id"        => "mshop-naverpay-sync-premium-review",
								"title"     => __( "프리미엄 구매평", 'mshop-npay' ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>프리미엄 구매평 연동 기능을 사용합니다.</div>", 'mshop-npay' )
							),
							array(
								"id"        => "mnp-concat-review-title",
								"title"     => __( "구매평 제목 저장", 'mshop-npay' ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>구매평 저장 시, 구매평의 제목을 함께 저장합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '구매평 수동 동기화', 'mshop-npay' ),
						'showIf'   => array( "mshop-naverpay-sync-review" => "yes" ),
						'elements' => array(
							array(
								'id'        => 'mnp-resync-term',
								"type"      => "DateRange",
								"title"     => __( "동기화 기간", 'mshop-npay' ),
								"className" => "mshop-daterange",
							),
							array(
								'id'             => 'mnp-resync-review',
								'title'          => '구매평 동기화',
								'label'          => '동기화',
								'iconClass'      => 'icon copy outline',
								'className'      => '',
								'type'           => 'Button',
								'default'        => '',
								'actionType'     => 'ajax',
								'ajaxurl'        => wp_nonce_url( admin_url( 'admin-ajax.php' ) ),
								'action'         => MNP()->slug() . '-resync_review',
								'confirmMessage' => "네이버페이 구매평을 다시 동기화 하시겠습니까?",
								"desc"           => "",
								"element"        => 'mnp-resync-term',
								"desc2"          => __( "<div class='desc2' style='margin-left: 10px;'>네이버페이 구매평을 다시 동기화 하시려면, 동기화 기간 입력 후 동기화 버튼을 클릭해주세요.</div>", "mshop-npay" ),
							),
						)
					)
				)
			);
		}

		static function get_delivery_setting() {
			include_once( MNP()->plugin_path() . '/includes/naverpay/ShippingPolicy.php' );

			return array(
				'type'     => 'Page',
				'title'    => __( '배송 설정', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( '배송 수단', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-shipping-fee-type",
								"title"     => __( "배송비 계산 방식", 'mshop-npay' ),
								"className" => "",
								"type"      => "Select",
								"default"   => "woocommerce",
								"options"   => array(
									'woocommerce'     => __( '우커머스 배송 정책', 'mshop-npay' ),
									'shipping-plugin' => __( '배송비 플러그인', 'mshop-npay' ),
									'custom'          => __( '직접 입력', 'mshop-npay' )
								)
							),
							array(
								"id"          => "mshop-naverpay-free-shipping",
								"title"       => __( "무료배송 수단", 'mshop-npay' ),
								"className"   => "",
								"showIf"      => array( 'mshop-naverpay-shipping-fee-type' => 'woocommerce' ),
								"type"        => "Select",
								"default"     => "",
								"placeholder" => "무료배송 수단을 선택하세요.",
								"options"     => MNP_Shipping::get_shipping_options( 'free_shipping', '무료배송 수단을 선택하세요.' )
							),
							array(
								"id"          => "mshop-naverpay-flat-rate",
								"title"       => __( "유료배송 수단", 'mshop-npay' ),
								"className"   => "",
								"showIf"      => array( 'mshop-naverpay-shipping-fee-type' => 'woocommerce' ),
								"type"        => "Select",
								"default"     => "",
								"placeholder" => "유료배송 수단을 선택하세요.",
								"options"     => MNP_Shipping::get_shipping_options( 'flat_rate', '유료배송 수단을 선택하세요.' )
							),
							array(
								"id"          => "mshop-naverpay-shipping-minimum-amount",
								"type"        => "LabeledInput",
								"className"   => "",
								"showIf"      => array( 'mshop-naverpay-shipping-fee-type' => 'custom' ),
								'inputType'   => 'number',
								"valueType"   => "unsigned int",
								"title"       => __( "무료배송 최소금액", 'mshop-npay' ),
								"leftLabel"   => get_woocommerce_currency_symbol(),
								"default"     => "0",
								"placeholder" => "0",
								"desc"        => __( "무료배송 최소금액 또는 유료배송 비용이 0원인 경우, 항상 무료배송으로 설정됩니다.", 'mshop-npay' )
							),
							array(
								"id"          => "mshop-naverpay-shipping-flat-rate-amount",
								"showIf"      => array( 'mshop-naverpay-shipping-fee-type' => 'custom' ),
								"type"        => "LabeledInput",
								"className"   => "",
								'inputType'   => 'number',
								"valueType"   => "unsigned int",
								"title"       => __( "유료배송비", 'mshop-npay' ),
								"leftLabel"   => get_woocommerce_currency_symbol(),
								"default"     => "0",
								"placeholder" => "0",
								"desc"        => __( "무료배송 최소금액 또는 유료배송 비용이 0원인 경우, 항상 무료배송으로 설정됩니다.", 'mshop-npay' )
							)
						),
					),
					array(
						'type'     => 'Section',
						'title'    => __( '반품 배송지', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp_zipcode",
								"title"     => __( "우편번호", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mnp_address1",
								"title"     => __( "기본주소", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mnp_address2",
								"title"     => __( "상세주소", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mnp_sellername",
								"title"     => __( "수취인 이름", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mnp_contact1",
								"title"     => __( "연락처 #1", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
							array(
								"id"        => "mnp_contact2",
								"title"     => __( "연락처 #2", 'mshop-npay' ),
								"className" => "fluid",
								"type"      => "Text",
							),
						),
					),
					array(
						'type'     => 'Section',
						'title'    => __( '도서산간 배송비', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-use-additional-fee",
								"title"     => __( "활성화", 'mshop-npay' ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "지역배송비 설정 기능을 사용합니다.", 'mshop-npay' )
							),
							array(
								"id"          => "mshop-naverpay-additional-fee-mode",
								"title"       => "배송비 설정 모드",
								"showIf"      => array( "mshop-naverpay-use-additional-fee" => "yes" ),
								"className"   => "",
								"type"        => "Select",
								"placeholder" => "배송비 모드를 선택하세요.",
								"default"     => MNP_Manager::ADDITIONAL_FEE_REGION,
								'options'     => array(
									MNP_Manager::ADDITIONAL_FEE_REGION => __( '권역별 배송비', 'mshop-npay' ),
									MNP_Manager::ADDITIONAL_FEE_API    => __( '배송비 API', 'mshop-npay' ),
								),
							),
						),
					),
					array(
						'type'     => 'Section',
						'title'    => __( '권역별 배송비', 'mshop-npay' ),
						"showIf"   => array(
							array( "mshop-naverpay-use-additional-fee" => "yes" ),
							array( "mshop-naverpay-additional-fee-mode" => MNP_Manager::ADDITIONAL_FEE_REGION )
						),
						'elements' => array(
							array(
								"id"        => "mshop-naverpay-additional-fee-region",
								"title"     => "권역 구분",
								"className" => "",
								"type"      => "Select",
								"default"   => '2',
								'options'   => array(
									'2' => __( '2 단계 (내륙, 제주 및 도서 산간 지역)', 'mshop-npay' ),
									'3' => __( '3 단계 (내륙, 제주 외 도서 산간 지역, 제주)', 'mshop-npay' )
								),
								'desc'      => '지역별 배송비 부과 권역을 몇단계로 설정할지를 지정합니다.'
							),
							array(
								"id"          => "mshop-naverpay-additional-fee-region-2",
								"type"        => "LabeledInput",
								"className"   => "",
								'inputType'   => 'number',
								"valueType"   => "unsigned int",
								"title"       => __( "2권역 추가배송비", 'mshop-npay' ),
								"leftLabel"   => get_woocommerce_currency_symbol(),
								"default"     => "0",
								"placeholder" => "0"
							),
							array(
								"id"          => "mshop-naverpay-additional-fee-region-3",
								"showIf"      => array( "mshop-naverpay-additional-fee-region" => '3' ),
								"type"        => "LabeledInput",
								"className"   => "",
								'inputType'   => 'number',
								"valueType"   => "unsigned int",
								"title"       => __( "3권역 추가배송비", 'mshop-npay' ),
								"leftLabel"   => get_woocommerce_currency_symbol(),
								"default"     => "0",
								"placeholder" => "0"
							)
						)
					)
				)
			);
		}

		static function get_advanced_setting() {
			return array(
				'type'     => 'Page',
				'title'    => __( '고급 설정', 'mshop-npay' ),
				'elements' => array(
					array(
						'type'     => 'Section',
						'title'    => __( '테그 설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-wrapper-selector",
								"title"     => "Wrapper Selector",
								"className" => "fluid",
								"type"      => "Text",
								"default"   => 'div.product.type-product'
							),
							array(
								"id"        => "mnp-simple-class",
								"title"     => "단순상품 Class",
								"className" => "fluid",
								"type"      => "Text",
								"default"   => 'product-type-simple'
							),
							array(
								"id"        => "mnp-variable-class",
								"title"     => "옵션상품 Class",
								"className" => "fluid",
								"type"      => "Text",
								"default"   => 'product-type-variable'
							),
							array(
								"id"        => "mnp-grouped-class",
								"title"     => "그룹상품 Class",
								"className" => "fluid",
								"type"      => "Text",
								"default"   => 'product-type-grouped'
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '화면전환 설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-product-page-transition-mode",
								"title"     => "상품상세화면",
								"className" => "",
								"type"      => "Select",
								"default"   => 'new-window',
								'options'   => array(
									'new-window' => __( '새탭으로 열기', 'mshop-npay' ),
									'in-page'    => __( '현재화면에서 열기', 'mshop-npay' )
								),
							),
							array(
								"id"        => "mnp-cart-page-transition-mode",
								"title"     => "장바구니화면",
								"className" => "",
								"type"      => "Select",
								"default"   => 'new-window',
								'options'   => array(
									'new-window' => __( '새탭으로 열기', 'mshop-npay' ),
									'in-page'    => __( '현재화면에서 열기', 'mshop-npay' )
								),
							),
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '이미지 URL 설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-force-image-url-to-http",
								"title"     => "HTTP로 변경",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>이미지 파일의 URL을 HTTP로 강제합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '로그 설정', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-enable-logger",
								"title"     => "디버그 로그 활성화",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>디버깅을 위한 로그를 기록합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( 'NPAY 결제 처리 방식', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-use-submit-handler",
								"title"     => "폼 핸들러 사용",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>커스텀 상품 옵션을 이용하기 위해 3rd Party 플러그인(WooCommerce Product Add-Ons, WooCommerce TM Extra Product Options)을 사용하는 경우, 옵션을 활성화해주세요.</div>", 'mshop-npay' )
							),
							array(
								"id"        => "mnp-use-cart-management",
								"title"     => "고객 장바구니 저장",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>네이버페이 서비스에 문제가 있는 경우에만 비활성화 하시기 바랍니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( 'NPAY 공통 스크립트', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-use-wcs",
								"title"     => "네이버페이 공통 유입 스크립트 출력",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>NPAY 결제를 위한 공통 유입 스크립트를 출력합니다.<br>네이버 프리미엄 로그 분석 서비스를 이용하면서 공통스크립트가 중복 출력되는 문제가 발생하는 경우, 해당 옵션을 비활성화 하시기 바랍니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '문자발송', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-block-sms",
								"title"     => "문자발송 차단",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>네이버페이 주문건의 경우, 주문상태 변경에 따른 문자가 발송되지 않도록 합니다.<br><a target='_blank' href='https://www.codemshop.com/shop/sms_out/'>엠샵 문자 & 알림톡 자동 발송 플러그인</a>을 이용하시는 경우에만 동작됩니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '주문처리', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-save-shipping-info",
								"title"     => "배송지 전화번호를 주문메모에 저장",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>배송지 전화번호를 주문메모란에 기록합니다.</div>", 'mshop-npay' )
							),
							array(
								"id"        => "mnp-save-billing-address",
								"title"     => "청구지 주소 저장",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>배송지 정보를 청구지 주소에도 저장합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '재고관리', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-use-stock-management",
								"title"     => "재고관리 기능 사용",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "yes",
								"desc"      => __( "<div class='desc2'>우커머스의 기본 재고관리 기능을 사용하시려면 비활성화 합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '도서공연비 소득공제', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-culture-benefit",
								"title"     => "도서공연비 소득공제 적용",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>문화체육관광부의 도서공연비 소득공제 정책에 적용되는 상품을 판매하는 경우 활성화합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '네이버페이 스크립트', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-npay-script",
								"title"     => "스크립트 정적로딩",
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>네이버페이 스크립트 로딩 시 오류가 발생하는 경우 사용합니다.</div>", 'mshop-npay' )
							)
						)
					),
					array(
						'type'     => 'Section',
						'title'    => __( '커스텀 주문상태', 'mshop-npay' ),
						'elements' => array(
							array(
								"id"        => "mnp-use-partial-refunded-order-status",
								"title"     => __( "부분환불", "mshop-npay" ),
								"className" => "",
								"type"      => "Toggle",
								"default"   => "no",
								"desc"      => __( "<div class='desc2'>부분환불(부분취소) 주문상태를 사용합니다. 네이버페이 주문에서 부분환불(부분취소) 발생 시 주문이 부분취소 상태로 변경됩니다.</div>", 'mshop-npay' )
							)
						)
					),
				)
			);
		}

		static function enqueue_scripts() {
			wp_enqueue_script( 'underscore' );
			wp_enqueue_style( 'mshop-setting-manager', MNP()->plugin_url() . '/includes/admin/setting-manager/css/setting-manager.min.css' );
			wp_enqueue_script( 'mshop-setting-manager', MNP()->plugin_url() . '/includes/admin/setting-manager/js/setting-manager.min.js', array(
				'jquery',
				'jquery-ui-core',
				'underscore'
			) );
		}

		public static function output_settings() {
			self::init_action();

			require_once MNP()->plugin_path() . '/includes/admin/setting-manager/mshop-setting-helper.php';

			$settings = self::get_setting_fields();

			self::enqueue_scripts();

			wp_localize_script( 'mshop-setting-manager', 'mshop_setting_manager', array(
				'element'  => 'mshop-setting-wrapper',
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'action'   => MNP()->slug() . '-update_settings',
				'settings' => $settings,
				'slug'     => MNP()->slug(),
				'domain'   => preg_replace( '#^https?://#', '', home_url() ),
			) );

			?>
            <script>
                jQuery( document ).ready( function () {
                    jQuery( this ).trigger( 'mshop-setting-manager', ['mshop-setting-wrapper', '100', <?php echo json_encode( MSSHelper::get_settings( $settings ) ); ?>, null, null] );
                } );
            </script>

            <div id="mshop-setting-wrapper"></div>
			<?php
		}

		public static function output_guide_page() {
			ob_start();
			wc_get_template( 'connect-key-guide.php', array(), '', MNP()->template_path() );
			echo ob_get_clean();
		}
		public static function output() {
			if ( ! empty( $_REQUEST['npay_connect'] ) && ! empty( $_REQUEST['npay_api_key'] ) ) {
				if ( MNP_Manager::request_connect( wc_clean( wp_unslash( $_REQUEST['npay_api_key'] ) ) ) ) {
					mnp_admin_notice( '축하합니다. NPay 서비스에 연결되었습니다.' );
				} else {
					mnp_admin_notice( 'NPay 연동에 실패했습니다.  NPay API 키를 확인 후 다시 한번 연결을 진행 해 주세요.', 'error' );
				}
			}

			$result   = MNP_API::get_status();
			$response = $result->response;

			if ( $response->ResponseType == "SUCCESS" && 'yes' == $response->Status->connected ) {
				self::$api_status = $response->Status;
				self::output_settings();
			} else {
				self::output_guide_page();
			}
		}
	}
endif;



