<?php

namespace App\Controllers;

use Lib\Controller;

class ErrorController extends Controller {
	public function RouteErrorAction() {
		return $this->view();
	}

	public function Status404Action() {
		return $this->view();
	}
}