<?php
defined( 'ABSPATH' ) || exit;
?>

<?php do_action( 'pafw_dc_before_npay_cart_block', $params ); ?>

<div class="pafw-payment-method box">
    <div id="checkout_button_wrapper" class="cart" style="text-align: <?php echo mnp_get( $params, 'align', 'left' ); ?>"></div>
</div>

<?php do_action( 'pafw_dc_after_npay_cart_block', $params ); ?>



