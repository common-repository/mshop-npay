<?php

class ShippingPolicySurchargeByArea{
	public $apiSupport = 'false';
	public $splitUnit = 0;
	public $area2Price = 0;
	public $area3Price = 0;
	public function __construct($apiSupport, $splitUnit, $area2Price, $area3Price)
	{
		$this->apiSupport = $apiSupport;

		if( 'true' == $apiSupport ) {
			unset( $this->splitUnit );
			unset( $this->area2Price );
			unset( $this->area3Price );
		}else{
			$this->splitUnit  = $splitUnit;
			$this->area2Price = $area2Price;
			$this->area3Price = $area3Price;
		}
	}

}
