<?php

namespace Lib;

interface IRouter {
	function getRequestForUri(string $requestUri, $get = [], $post = []): ?Request;
}