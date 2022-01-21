<?php

namespace App\Controllers;

use Lib\Controller;

class IndexController extends Controller {
	public function IndexAction() {
		return $this->view();
	}

	public function NotFoundAction() {
		http_response_code(404);
		return $this->view();
	}
	
	public function PermissionDeniedAction() {
		http_response_code(403);
		return $this->view();
	}

	public function NotAuthenticatedAction() {
		http_response_code(401);
		return $this->view();
	}

	public function InvalidUserTypeAction($type) {
		http_response_code(404);
		return $this->view(['type' => $type]);
	}

	public function InvalidParamsAction() {
		http_response_code(400);
		return $this->view();
	}

	/**
	 * @MustBeLoggedIn('/User/Login')
	 */
	public function MessagingAction() {
		return $this->view();
	}

	public function HelpAction() {
		return $this->view();
	}
}