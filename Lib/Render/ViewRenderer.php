<?php

namespace Lib\Render;

use Lib\DI;
use Lib\Request;

/**
 * Class used to encapsulate of view files
 * 
 * @implements IViewHelpers
 */
class ViewRenderer implements IViewRenderer {
	private $__output = '';

	/**
	 * Renders file successively. Accepts and array of file path keys and data payload values
	 *
	 * @param array $views
	 * @return string
	 */
	public function render(array $views) {
		array_walk($views, function($__payload, $__file) {
			if (!file_exists($__file)) {
				$__file = preg_split('/\\\\|\\//', $__file);
				if (count($__file) == 1) {
					array_unshift($__file, DI::get(Request::class)->getControllerName());
				}
				
				$__file = APP_ROOT . '/App/views/' . implode('/', $__file) . '.php';
			}
			
			extract($__payload);

			ob_start();
			require($__file);
			$this->__output = ob_get_clean();
		});

		return $this->__output;
	}

	/**
	 * Retrieves the last rendered file's output
	 *
	 * @return string
	 */
	public function getContents() {
		return $this->__output;
	}

	/**
	 * Invokes a ViewHelper function
	 *
	 * @param string $fn
	 * @param array $args
	 * @return mixed
	 */
	public function __call($fn, $args) {
		$viewHelpers = DI::get(IViewHelpers::class);

		if (method_exists($viewHelpers, $fn)) {
			return $viewHelpers->$fn(...$args);
		}

		return null;
	}
}