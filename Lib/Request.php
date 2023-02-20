<?php

namespace Lib;

use Lib\Utility\PutReader;

class Request {
	private $controllerName;
	private $actionName;
	private $routeParams;
	private $get;
	private $post;

	public function __construct($args) {
		foreach ($args as $key => $val) {
			if (property_exists($this, $key)) {
				$this->$key = $val;
			}
		}
	}

	public function getControllerName() {
		return $this->controllerName;
	}

	public function getActionName() {
		return $this->actionName;
	}

	public function getGetVar($name) {
		return $this->get[$name] ?? null;
	}

	public function getPostVar($name) {
		return $this->post[$name] ?? null;
	}

	public function getRequestVar($name) {
		return $this->getPostVar($name) ?? $this->getGetVar($name) ?? null;
	}

	public function getPutData() {
		return PutReader::getPutData();
	}

	public function getRouteParams() {
		return $this->routeParams;
	}

	public function isActive($path, $exact = false) {
		$path = trim($path, '/');
		if ($path === '') {
			$path = 'Index/Index';
		}

		if (strpos($path . '/', "{$this->controllerName}/{$this->actionName}/") !== 0) {
			return false;
		}

		if ($exact) {
			$routeParams = array_slice(explode('/', $path), 2);

			if ($routeParams != $this->routeParams) {
				return false;
			}
		}

		return true;
	}

	public function getRequestType() {
		return strtoupper($_SERVER['REQUEST_METHOD']);
	}

	public function isPost() {
		return $this->getRequestType() === 'POST';
	}

	public function isGet() {
		return $this->getRequestType() === 'GET';
	}

	public function __toString() {
		$result = "/{$this->controllerName}/{$this->actionName}";

		if ($this->get) {
			$result .= http_build_query($this->get);
		}

		return $result;
	}
}