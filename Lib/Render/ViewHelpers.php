<?php

namespace Lib\Render;

use Lib\DI;
use Lib\Environment;
use Lib\Request;
use Lib\Utility\DepCollection;

class ViewHelpers implements IViewHelpers {
    private $elementClasses = [];
    private $scripts;
    private $styles;
    private $pageTitleParts = [];
    private $pageTitleSeparator = ' - ';
	private $meta = [];

    public function __construct() {
        $this->scripts = [
            'collection' => new DepCollection(),
            'header' => [],
            'footer' => []
        ];

        $this->styles = [
            'collection' => new DepCollection(),
            'needed' => []
        ];
    }

	// Shim, will not be used
	public function getContents() {}

	/**
	 * Returns an absolute url with the provided relative path appended
	 *
	 * @param string $url
	 * @return string
	 */
    public function baseUrl($url = '') {
        return Environment::getBaseUrl() . ltrim($url, '/');
    }

	/**
	 * Returns an absolute url to the public directory with the provided relative path appended
	 *
	 * @param string $url
	 * @return string
	 */
    public function publicUrl($url = '') {
        return Environment::getBaseUrl() . 'public/' . ltrim($url, '/');
    }

	/**
	 * Returns the given string as html escaped
	 *
	 * @param string $html
	 * @return string
	 */
    public function escapeHtml($html) {
        return htmlentities($html, ENT_COMPAT);
    }

	/**
	 * Returns the current body class string. If provided, the given class will be appended first
	 *
	 * @param string $class
	 * @return string
	 */
    public function bodyClass($class = '') {
        return $this->elementClass('body', $class);
    }

	/**
	 * Returns the current body class string. If provided, the given class will be appended first
	 *
	 * @param string $class
	 * @return string
	 */
	public function htmlClass($class = '') {
		return $this->elementClass('html', $class);
	}

	/**
	 * Returns the given tag class string. If provided, the given class will be appended first
	 *
	 * @param string $element
	 * @param string $class
	 * @return string
	 */
    public function elementClass($element, $class = '') {
        if (!isset($this->elementClasses[$element])) {
            $this->elementClasses[$element] = '';
        }

        if ($class !== '') {
            $this->elementClasses[$element] .= ' ' . $class;
        }
        return $this->elementClasses[$element];
    }

	/**
	 * Registers the given script to the dependency tracker
	 *
	 * @param string $name The handle for this script
	 * @param string $urlOrSrc The URL to a script or a literal JavaScript string
	 * @param array $deps An array of registered script names this script needs to be loaded after
	 * @return void
	 */
    public function scriptRegister($name, $urlOrSrc, $deps = []) {
		$this->scripts['collection']->addResource($name, $urlOrSrc, $deps);
    }

	/**
	 * Registers and enqueues the given script to the dependency tracker
	 *
	 * @param string $name The handle for the script
	 * @param string $urlOrSrc If provided, will be the URL to a script or a literal JavaScript string
	 * @param array $deps If provided, an array of registered script names this script needs to be loaded after
	 * @param boolean $header Where to output the script tag. True for header, False for footer
	 * @return void
	 */
    public function scriptEnqueue($name, $urlOrSrc = '', $deps = [], $header = true) {
		if ($urlOrSrc) {
			$this->scripts['collection']->addResource($name, $urlOrSrc, $deps);
		}

		$this->scripts[$header ? 'header' : 'footer'][] = $name;
    }

	/**
	 * Registers the given style to the dependency tracker
	 *
	 * @param string $name The handle for the style
	 * @param string $urlOrStyle The URL to a style or a literal CSS string
	 * @param array $deps An array of registered style names this style needs to be loaded after
	 * @return void
	 */
    public function styleRegister($name, $urlOrStyle, $deps = []) {
        $this->styles['collection']->addResource($name, $urlOrStyle, $deps);
    }

	/**
	 * Registers and enqueues the given style to the dependency tracker
	 *
	 * @param string $name The handle for the style
	 * @param string $urlOrStyle If provided, will be the URL to a style or a literal CSS string
	 * @param array $deps If provided, an array of registered style names this style needs to be loaded after
	 * @return void
	 */
    public function styleEnqueue($name, $urlOrStyle = '', $deps = []) {
        if ($urlOrStyle) {
            $this->styles['collection']->addResource($name, $urlOrStyle, $deps);
        }

        $this->styles['needed'][] = $name;
    }

	function _transformMetaKey($keyOrProps, $val) {
		if (is_string($keyOrProps)) {
			$key = $keyOrProps;
	
			if (strpos($keyOrProps, 'og:') === 0) {
				// Facebook
				$keyOrProps = array(
					'property' => $keyOrProps,
					'content' => $val
				);
			}
			else {
				// Twitter and most others
				$keyOrProps = array(
					'name' => $keyOrProps,
					'content' => $val
				);
			}
		}
		else {
			$key = http_build_query($keyOrProps);
		}
	
		return array(
			$keyOrProps,
			$key
		);
	}

	public function meta($keyOrProps, $val = null) {
		list($keyOrProps, $key) = $this->_transformMetaKey($keyOrProps, $val);

		$tag = '<meta';
		foreach ($keyOrProps as $k => $v) {
			$tag .= ' ' . $k . '="' . str_replace('"', '&quot;', $v) . '"';
		}
		$tag .= '/>';
		
		$this->meta[$key] = $tag;
	}

    public function removeMeta($keyOrProps) {
		list($keyOrProps, $key) = $this->_transformMetaKey($keyOrProps, false);

		if (isset($this->meta[$key])) {
			unset($this->meta[$key]);
		}
	}

	/**
	 * Echos the enqueued scripts
	 *
	 * @param boolean $header Which scripts to output. True for header, False for footer
	 * @return void
	 */
    public function outputScripts($header = true) {
        $resources = $this->scripts['collection']->getOrderedList($this->scripts[$header ? 'header' : 'footer']);
        foreach ($resources as $res) {
            if (strpos($res, 'http') === 0 || strpos($res, '//') === 0) {
                echo '<script' . ' type="text/javascript" src="' . $res . '"></script>' . PHP_EOL;
            }
            else {
                echo '<script' . ' type="text/javascript">' . PHP_EOL . $res . PHP_EOL . '</script>' . PHP_EOL;
            }
        }
    }

	/**
	 * Echos the enqueued styles
	 *
	 * @return void
	 */
    public function outputStyles() {
        $resources = $this->styles['collection']->getOrderedList($this->styles['needed']);
        foreach ($resources as $res) {
            if (strpos($res, 'http') === 0 || strpos($res, '//') === 0) {
                echo '<link rel="stylesheet" href="' . $res . '">' . PHP_EOL;
            }
            else {
                echo '<style>' . PHP_EOL . $res . PHP_EOL . '</style>' . PHP_EOL;
            }
        }
    }

	public function outputMeta() {
		echo implode(PHP_EOL, $this->meta);
	}

	public function outputHead() {
		$this->outputScripts();
		$this->outputStyles();
		$this->outputMeta();
	}

	public function outputFooter() {
		$this->outputScripts(false);
	}

	/**
	 * Renders the given partial view
	 *
	 * @param string $view The view to render
	 * @param array $payload The data to pass into the view
	 * @return string
	 */
    public function partial($view, $payload = []) {
        $views = [
            $view => $payload
        ];

        return DI::get(IViewRenderer::class)->render($views);
    }

	/**
	 * Returns whether or not the given path matches the current route
	 *
	 * @param string $path The relative route with controller and action
	 * @param boolean $exact Whether to match a route even if the arguments differ
	 * @return boolean
	 */
    public function isRouteActive($path, $exact = false) {
        return DI::get(Request::class)->isActive($path, $exact);
    }

	/**
	 * Returns the current constructed page title, if provided the given title part will be included
	 *
	 * @param string $titlePart The title part to include
	 * @param boolean $prepend Whether to append or prepend the title part. True for prepend, False for append
	 * @return string
	 */
    public function pageTitle($titlePart = '', $prepend = false) {
        if ($titlePart !== '') {
            if ($prepend) {
                array_unshift($this->pageTitleParts, $titlePart);
            }
            else {
                $this->pageTitleParts[] = $titlePart;
            }
        }

        return implode($this->pageTitleSeparator, $this->pageTitleParts);
    }

	/**
	 * Changes the title part separator
	 *
	 * @param string $separator
	 * @return void
	 */
    public function setPageTitleSeparator(string $separator) {
        $this->pageTitleSeparator = $separator;
	}
	
	/**
	 * Returns the standard route url for the current route
	 *
	 * @return string
	 */
	public function getCanonical() {
		$request = DI::get(Request::class);
		$etc = $request->getRouteParams()
			? '/' . implode('/', $request->getRouteParams())
			: '';

		return $this->baseUrl("{$request->getControllerName()}/{$request->getActionName()}{$etc}");
	}
}