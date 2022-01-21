<?php

namespace Lib;

class Router implements IRouter {
	public function getRequestForUri(string $requestUri, $get = [], $post = []): ?Request {
		$requestUri = ltrim($requestUri, '/');

		$routeParts = array_replace(
			['Index', 'Index'],
			$requestUri
				? explode('/', $requestUri)
				: []
		);
		foreach (range(0, 1) as $ndx) {
			$routeParts[$ndx] = ucfirst(preg_replace_callback('/[^a-zA-Z0-9]+(\\w)/', function($matches) {
				return strtoupper($matches[1]);
			}, $routeParts[$ndx]));
		}

		$routeParams = array_map(function($p) {
			if ($p === 'TRUE' || $p === 'FALSE' || $p === 'NULL') {
				$p = strtolower($p);
			}

			if ($p === 'null') {
				return null;
			}

			$jsonValue = json_decode($p);

			return is_null($jsonValue) || is_array($jsonValue)
				? $p
				: $jsonValue;
		}, array_slice($routeParts, 2));

		$args = [
			'controllerName' => $routeParts[0],
			'actionName'     => $routeParts[1],
			'routeParams'    => $routeParams,
			'get'            => $get,
			'post'           => $post
		];

		return new Request($args);
	}
}