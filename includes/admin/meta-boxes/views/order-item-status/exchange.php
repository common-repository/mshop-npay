<?php
?>

<table class="mnp-order-info" cellpadding="0" cellspacing="0">
	<?php
	$fields = MNP_Message_Exchange_Info::get_fields();
	foreach ( $fields as $key => $value ) {
		if ( ! empty( $npay_order->ExchangeInfo->$key ) ) {
			echo '<tr><td class="title">' . $value . '</td>';
			echo '<td>' . MNP_Message_Exchange_Info::get_field_value( $key, $npay_order->ExchangeInfo->$key ) . '</td></tr>';
		}
	}
	?>
</table>
