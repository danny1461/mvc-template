<?php

namespace Lib\Response;

class Forwarding implements IResponse {
	private $forwardingParams;

	public function __construct($requestUri, $get = [], $post = []) {
		$this->forwardingParams = func_get_args();
	}

	public function output() {
		return $this->forwardingParams;
	}
}