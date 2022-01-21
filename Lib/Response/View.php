<?php

namespace Lib\Response;

use Lib\DI;
use Lib\Render\IViewRenderer;

class View implements IResponse {
	private $html = '';

	/**
	 * @param array<string, array> $views 
	 */
    public function __construct($views) {
		$this->html = DI::get(IViewRenderer::class)->render($views);
    }

    public function output() {
		echo $this->html;
	}
}