<?php

namespace Lib;

use Lib\Render\IViewHelpers;
use Lib\Render\IViewRenderer;
use Lib\Render\ViewHelpers;
use Lib\Render\ViewRenderer;
use Lib\Response\IResponse;
use Lib\Response\Forwarding;
use Lib\Utility\ReflectionHelpers;

/**
 * The heart of the framework
 */
class Application {
	/**
	 * The relative path from the document root to this site root
	 *
	 * @var string $appDir
	 */
	private $appDir;

	/**
	 * The absolute path to this site
	 *
	 * @var string $baseUrl
	 */
	private $baseUrl;

	/**
	 * The server's original, cleaned $_SERVER['REQUEST_URI']
	 *
	 * @var string $requestUri
	 */
	private $requestUri;

	/**
	 * The application's dependency injector
	 *
	 * @var DI $di
	 */
	private $di;

	/**
	 * The class to be used for handling route requests
	 *
	 * @var string $requestClass
	 */
	private $requestClass = 'Request';

	/**
	 * The class to be attached to controllers and views for granting access to helpers
	 *
	 * @var string $viewHelpersClass
	 */
	private $viewHelpersClass = 'ViewHelpers';

	/**
	 * The class to be used for rendering of all views
	 *
	 * @var string $viewRendererClass
	 */
	private $viewRendererClass = 'ViewRenderer';

	public function __construct() {
		$this->parseServerVars();

		DI::addScoped(Application::class, $this);
	}

	/**
	 * Fetches the root url for the current detected site path
	 *
	 * @return string
	 */
	public function getBaseUrl() {
		return $this->baseUrl;
	}

	/**
	 * Processes the data in $_SERVER
	 * Assigns $appDir, $baseUrl, $requestUri
	 *
	 * @return void
	 */
	private function parseServerVars() {
		$this->appDir = substr(APP_ROOT, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
		$this->appDir = str_replace('\\', '/', $this->appDir);

		$this->baseUrl = 'http' . ($_SERVER['SERVER_PORT'] === '443' ? 's' : '') . '://';
		$this->baseUrl .= $_SERVER['SERVER_NAME'];
		if (!in_array($_SERVER['SERVER_PORT'], array('80', '443')))
			$this->baseUrl .= ':' . $_SERVER['SERVER_PORT'];
		$this->baseUrl .= $this->appDir;

		list($this->requestUri) = explode('?', $_SERVER['REQUEST_URI'], 2);
		$this->requestUri = substr($this->requestUri, strlen($this->appDir));
		$this->requestUri = rtrim($this->requestUri, '/');
		$this->requestUri = urldecode($this->requestUri);
	}

	/**
	 * Registers ViewRenderer and IViewHelper
	 *
	 * @return void
	 */
	private function configureServices() {
		$di = DI::getDefault();

		if (!$di->has(IRouter::class)) {
			$di->addScoped(IRouter::class, Router::class);
		}

		if (!$di->has(IViewRenderer::class)) {
			$di->addTransient(IViewRenderer::class, ViewRenderer::class);
		}

		if (!$di->has(IViewHelpers::class)) {
			$di->addScoped(IViewHelpers::class, ViewHelpers::class);
		}
	}

	/**
	 * Provides a location for developers to work with the application instance such as registering things with the dependency injection service
	 *
	 * @param callable $callable
	 * @return Application
	 */
	public function hook(callable $callable) : Application {
		$callable($this);

		return $this;
	}

	/**
	 * Sets up environment based error reporting levels
	 *
	 * @return void
	 */
	private function configureErrorReporting() {
		$level = E_ALL ^ E_NOTICE;

		if (!IS_LOCAL) {
			$level = $level ^ E_WARNING ^ E_STRICT;
		}

		error_reporting($level);
	}

	/**
	 * The entry point of the application
	 *
	 * @return void
	 */
	public function start() : Application {
		$this->configureServices();
		$this->configureErrorReporting();

		$router = DI::get(IRouter::class);
		$request = $router->getRequestForUri($this->requestUri, $_GET, $_POST);
		if (!$request instanceof Request) {
			$request = new Request([
				'controllerName' => 'Error',
				'actionName' => 'RouteError'
			]);
		}

		$di = DI::getDefault();

		do {
			$di->addScoped(Request::class, $request);
			$response = $this->dispatch($request);
		}
		while (
			$response instanceof Forwarding &&
			$request = $router->getRequestForUri(...$response->output())
		);

		$response->output();

		return $this;
	}

	/**
	 * Handles the execution of routes
	 *
	 * @param Request $request
	 * @return IResponse
	 */
	private function dispatch($request) : IResponse {
		if (!$request) {
			return $this->handle404($request);
		}
		
		$controllerClass = 'App\\Controllers\\' . $request->getControllerName() . 'Controller';
		if (!class_exists($controllerClass)) {
			return $this->handle404($request);
		}

		$actionMethod = $request->getActionName() . 'Action';
		if (!method_exists($controllerClass, $actionMethod)) {
			return $this->handle404($request);
		}

		$diArgProvider = DI::getDefault()->getArgProvider();
		$controllerInst = ReflectionHelpers::constructClass($controllerClass, [], $diArgProvider);

		// beforeActionHook
		if ($controllerInst instanceof IControllerHooks) {
			$response = $controllerInst->beforeActionHook();
			if ($response instanceof IResponse) {
				return $response;
			}
		}

		// Call Controller Action
		$response = ReflectionHelpers::callMethod($controllerInst, $actionMethod, $request->getRouteParams(), function($ndx, $name, $type, $undefined) use ($controllerClass, $diArgProvider) {
			$value = $diArgProvider($ndx, $name, $type, $undefined);

			if ($value === $undefined) {
				$value = $controllerClass::argProvider($ndx, $name, $type, $undefined);
			}

			return $value;
		});

		// AfterActionHook
		if ($controllerInst instanceof IControllerHooks) {
			$newResponse = $controllerInst->afterActionHook($response);
			if ($newResponse instanceof IResponse) {
				return $newResponse;
			}
		}

		return $response;
	}

	/**
	 * Handles the dispatching of 404ing routes
	 *
	 * @param Request $request
	 * @return void
	 */
	private function handle404($request) {
		if ($request) {
			if ($request->getActionName() !== 'Status404') {
				return new Forwarding($request->getControllerName() . '/Status404');
			}
	
			if ($request->getControllerName() !== 'Error') {
				return new Forwarding('Error/Status404');
			}
		}

		http_response_code(404);
		die('Request not found');
	}
}