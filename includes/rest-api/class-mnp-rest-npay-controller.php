<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class MNP_REST_NPay_Controller extends WC_REST_Posts_Controller {
	protected $namespace = 'wc/v1';
	protected $rest_base = 'npay';
	protected $post_type = 'shop_order';
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/batch',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'batch_items' ),
					'permission_callback' => array( $this, 'batch_items_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_batch_schema' ),
			)
		);
	}
	public function batch_items( $request ) {
		global $wp_rest_server;

		// Get the request params.
		$items    = array_filter( $request->get_params() );
		$query    = $request->get_query_params();
		$response = array();

		// Check batch limit.
		$limit = $this->check_batch_limit( $items );
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		if ( ! empty( $items['ship_order'] ) ) {
			try {
				$ship_results = MNP_Sheets_Npay::mnp_bulk_ship_order( $items['ship_order'] );

				foreach ( $items['ship_order'] as $item ) {
					$processed_item = null;

					foreach ( $ship_results as $product_order_id => $ship_result ) {
						if ( $ship_result['order_id'] == $item['order_id'] && $ship_result['order_item_id'] == $item['order_item_id'] ) {
							$processed_item = $ship_result;
							break;
						}
					}

					if ( $processed_item ) {
						if ( mnp_get( $processed_item, 'success', false ) ) {
							$response['ship_order'][] = array(
								'order_id'      => $item['order_id'],
								'order_item_id' => $item['order_item_id'],
								'success'       => true
							);
						} else {
							$response['ship_order'][] = array(
								'order_id'      => $item['order_id'],
								'order_item_id' => $item['order_item_id'],
								'success'       => false,
								'error'         => array(
									'code'    => '1001',
									'message' => mnp_get( $processed_item, 'error' )
								),
							);
						}
					} else {
						$response['ship_order'][] = array(
							'order_id'      => $item['order_id'],
							'order_item_id' => $item['order_item_id'],
							'success'       => false,
							'error'         => array(
								'code'    => '1000',
								'message' => '네이버페이 주문 정보를 찾을 수 없습니다.'
							),
						);
					}
				}
			} catch ( Exception $e ) {
				$response['ship_order'][] = array(
					'order_id'      => 0,
					'order_item_id' => 0,
					'success'       => false,
					'error'         => array(
						'code'    => $e->getCode(),
						'message' => $e->getMessage()
					),
				);
			}
		}

		return $response;
	}
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			'properties' => array(
				'order_id'         => array(
					'description' => __( 'Unique identifier for the resource.', 'mshop-npay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'order_item_id'    => array(
					'description' => __( 'Item ID.', 'mshop-npay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'dlv_company_code' => array(
					'description' => __( 'Delivery company code.', 'mshop-npay' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'sheet_no'         => array(
					'description' => __( 'Sheet No.', 'mshop-npay' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				)
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}
}
