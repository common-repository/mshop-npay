<?php

class ReturnInfo {
	public $zipcode = null;

	public $address1 = null;

	public $address2 = null;

	public $sellername = null;

	public $contact1 = null;

	public $contact2 = null;

	public function __construct( $zipcode, $address1, $address2, $sellername, $contact1, $contact2 ) {
		$this->zipcode    = $zipcode;
		$this->address1   = $address1;
		$this->address2   = $address2;
		$this->sellername = $sellername;
		$this->contact1   = $contact1;
		$this->contact2   = $contact2;
	}
}

