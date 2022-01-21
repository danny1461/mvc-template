<?php

namespace Lib;

use Lib\Render\IViewHelpers;
use Lib\Response\File;
use Lib\Response\Forwarding;
use Lib\Response\Html;
use Lib\Response\Javascript;
use Lib\Response\Json;
use Lib\Response\Redirect;
use Lib\Response\Stylesheet;
use Lib\Response\View;

class Controller {
	protected static $layout = 'default';
	protected static $layoutDir = APP_ROOT . '/App/layouts/';
	protected static $templateDir = APP_ROOT . '/App/views/';

	/**
	 * @var mixed
	 */
	protected $viewHelpers;
	protected $request;
	
	public function __construct(Request $request, IViewHelpers $viewHelpers) {
		$this->request = $request;
		$this->viewHelpers = $viewHelpers;
	}

	/**
	 * Retrieves the specified type from the dependency injection service
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function get($name) {
		return DI::get($name);
	}

	/**
	 * Returns an action response wrapping a view render
	 *
	 * @param array|object $payload
	 * @param string $viewName
	 * @param string|null $layout
	 * @return View
	 */
	public function view($payload = [], $view = null, $layout = null) {
		if (is_object($payload)) {
			$payload = ['model' => $payload];
		}

		if (!is_file($view)) {
			$view = implode('/', array_pad(array_filter(explode('/', $view ?? $this->request->getActionName())), -2, $this->request->getControllerName()));
			$view = ltrim(static::$templateDir, '/') . '/' . $view . '.php';
		}

		$layout = $layout ?? static::$layout;

		if (!is_file($layout)) {
			$layout = ltrim(static::$layoutDir, '/') . '/' . $layout . '.php';
		}

		return new View([
			$view => $payload,
			$layout => []
		]);
	}

	/**
	 * Returns an action response wrapping data to be json encoded
	 *
	 * @param mixed $payload
	 * @return Json
	 */
	public function json($payload) {
		return new Json($payload);
	}

	/**
	 * Returns an action result performing an internal redirect to another route
	 *
	 * @param string $requestUri
	 * @param array $get
	 * @param array $post
	 * @return Forwarding
	 */
	public function forward($requestUri, $get = [], $post = []) {
		return new Forwarding($requestUri, $get, $post);
	}

	/**
	 * Returns an action result wrapping html text
	 *
	 * @param string $html
	 * @return Html
	 */
	public function html($html) {
		return new Html($html);
	}

	/**
	 * Returns an action result performing either a 302 or 301 redirect to the specified url
	 *
	 * @param string $url
	 * @param boolean $permanent
	 * @return Redirect
	 */
	public function redirect($url, $permanent = false) {
		return new Redirect($url, $permanent);
	}

	/**
	 * Returns an action result wrapping text to be returns as javascript
	 *
	 * @param string $code
	 * @return Javascript
	 */
	public function javascript($code) {
		return new Javascript($code);
	}

	/**
	 * Returns an action result wrapping text to be returns as css
	 *
	 * @param string $code
	 * @return Stylesheet
	 */
	public function stylesheet($code) {
		return new Stylesheet($code);
	}

	/**
	 * Returns an action result that delivers a file to be downloaded
	 *
	 * @param string $path
	 * @param string $fileName
	 * @return File
	 */
	public function file($path, $fileName = null) {
		return new File($path, $fileName);
	}

	public static function argProvider($ndx, $name, $type, $undefined) {
		if (strpos($name, 'get_') === 0) {
			$name = substr($name, 4);

			return empty($_GET[$name])
				? $undefined
				: $_GET[$name];
		}

		return $undefined;
	}
}