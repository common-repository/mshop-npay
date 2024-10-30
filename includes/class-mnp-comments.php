<?php



if ( ! class_exists( 'MNP_Comments' ) ) {


	class MNP_Comments {

		public static function sync_review( $date_from = null, $date_to = null ) {

			if ( MNP_Manager::sync_review() ) {
				if ( is_null( $date_from ) ) {
					$date_from = apply_filters( 'mnp_sync_review_from', gmdate( 'Y-m-d\TH:i:s', strtotime( '-2 hours' ) ) . date( 'P' ) );
				}

				if ( is_null( $date_to ) ) {
					$date_to = apply_filters( 'mnp_sync_review_to', gmdate( 'Y-m-d\TH:i:s', strtotime( '+30 minutes' ) ) . date( 'P' ) );
				}


				if ( MNP_Manager::sync_normal_review() ) {

					$result = MNP_API::get_purchase_review_list( $date_from, $date_to, 'GENERAL' );

					$response = $result->response;

					if ( "SUCCESS" == $response->ResponseType && $response->ReturnedDataCount > 0 ) {
						if ( is_array( $response->PurchaseReviewList ) ) {
							foreach ( $response->PurchaseReviewList as $PurchaseReview ) {
								self::insert_comment( $PurchaseReview, 'GENERAL' );
							}
						} else {
							self::insert_comment( $response->PurchaseReviewList, 'GENERAL' );
						}
					}
				}

				if ( MNP_Manager::sync_premium_review() ) {
					$result = MNP_API::get_purchase_review_list( $date_from, $date_to, 'PREMIUM' );

					$response = $result->response;

					if ( "SUCCESS" == $response->ResponseType && $response->ReturnedDataCount > 0 ) {
						if ( is_array( $response->PurchaseReviewList ) ) {
							foreach ( $response->PurchaseReviewList as $PurchaseReview ) {
								self::insert_comment( $PurchaseReview, 'PREMIUM' );
							}
						} else {
							self::insert_comment( $response->PurchaseReviewList, 'PREMIUM' );
						}
					}

				}
			}
		}

		public static function insert_comment( $PurchaseReview, $review_type ) {
			$product_ids = apply_filters( 'mnp_product_id_for_review', $PurchaseReview->ProductID, $PurchaseReview->ProductOrderID );

			if ( ! is_array( $product_ids ) ) {
				$product_ids = array( $product_ids );
			}

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );

				if ( $product ) {
					if ( 'PREMIUM' == $review_type ) {
						if ( 'yes' == get_option( 'mnp-concat-review-title', 'yes' ) ) {
							$content = array(
								$PurchaseReview->Title,
								$PurchaseReview->Content
							);

							$content = array_filter( $content );
							$content = implode( '<br><br>', $content );
						} else {
							$content = $PurchaseReview->Content;
						}
					} else {
						$content = $PurchaseReview->Title;
					}

					$commentdata = array(
						'comment_post_ID'                     => $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id(),
						'comment_author'                      => apply_filters( 'mnp_review_write_id', $PurchaseReview->WriterId, $PurchaseReview ),
						'comment_content'                     => apply_filters( 'mnp_review_content', $content, $PurchaseReview ),
						'comment_type'                        => 'review',
						'comment_parent'                      => 0,
						'comment_approved'                    => apply_filters( 'mnp_review_approved', 1, $PurchaseReview ),
						'comment_agent'                       => 'NaverPay',
						'comment_date'                        => date( 'Y-m-d H:i:s', strtotime( $PurchaseReview->CreateYmdt . '+ 9 HOUR' ) ),
						'comment_naverpay_purchase_review_id' => $PurchaseReview->PurchaseReviewId,
					);

					if ( self::allow_comment( $commentdata ) ) {
						do_action( 'mnp_before_register_purchase_review', $commentdata, $PurchaseReview );

						$comment_id = wp_insert_comment( $commentdata );

						$rating = $PurchaseReview->PurchaseReviewScore;

						add_comment_meta( $comment_id, 'rating', $rating, true );
						add_comment_meta( $comment_id, 'naverpay_purchase_review_id', $PurchaseReview->PurchaseReviewId, true );

						do_action( 'mnp_purchase_review_registered', $comment_id );
					}
				}
			}
		}
		static function allow_comment( $comment_data ) {
			global $wpdb;

			$query = $wpdb->prepare(
				"SELECT comments.comment_ID 
				 FROM {$wpdb->comments} comments, {$wpdb->commentmeta} commentmeta 
                 WHERE 
                     comments.comment_approved != 'trash' 
                     AND comments.comment_post_ID = %d 
                     AND comments.comment_ID = commentmeta.comment_id 
                     AND commentmeta.meta_key = 'naverpay_purchase_review_id' 
                     AND commentmeta.meta_value = %d",
				wp_unslash( sanitize_text_field( $comment_data['comment_post_ID'] ) ),
				wp_unslash( sanitize_text_field( $comment_data['comment_naverpay_purchase_review_id'] ) )
			);

			if ( $wpdb->get_var( $query ) ) {
				return false;
			}

			return true;
		}

	}
}

