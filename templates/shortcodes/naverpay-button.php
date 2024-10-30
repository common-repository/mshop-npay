<?php
?>

<?php do_action( 'mnp_before_npay_button'); ?>

<div id="checkout_button_wrapper_<?php echo $product->get_id(); ?>" class="checkout_button_wrapper" data-product_id="<?php echo $params['product_id']; ?>" data-quantity="<?php echo $params['quantity']; ?>"></div>

<?php do_action( 'mnp_after_npay_button'); ?>