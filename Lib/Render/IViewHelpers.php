<?php

namespace Lib\Render;

interface IViewHelpers {
	function getContents();
    function baseUrl($url = '');
	function publicUrl($url = '');
	function escapeHtml($html);
	function bodyClass($class = '');
	function htmlClass($class = '');
	function elementClass($element, $class = '');
	function scriptRegister($name, $urlOrSrc, $deps = []);
	function scriptEnqueue($name, $urlOrSrc = '', $deps = [], $header = true);
	function styleRegister($name, $urlOrStyle, $deps = []);
	function styleEnqueue($name, $urlOrStyle = '', $deps = []);
	function meta($keyOrProps, $val = null);
	function removeMeta($keyOrProps);
	function outputScripts($header = true);
	function outputStyles();
	function outputMeta();
	function outputHead();
	function outputFooter();
	function partial($view, $payload = []);
	function isRouteActive($path, $exact = false);
	function pageTitle($titlePart = '', $prepend = false);
	function setPageTitleSeparator(string $separator);
	function getCanonical();
}