<?php

namespace Lib;

class SimpleAlert {
	private static function alert($type, $message, $isHtml = false) {
		if (!isset($_SESSION['__simplealerts'])) {
			$_SESSION['__simplealerts'] = [];
		}

		$_SESSION['__simplealerts'][] = [
			'type' => $type,
			'message' => $message,
			'html' => $isHtml
		];
	}

	public static function success($message, $isHtml = false) {
		self::alert('success', $message, $isHtml);
	}

	public static function warning($message, $isHtml = false) {
		self::alert('warning', $message, $isHtml);
	}

	public static function error($message, $isHtml = false) {
		self::alert('danger', $message, $isHtml);
	}

	public static function info($message, $isHtml = false) {
		self::alert('info', $message, $isHtml);
	}

	public static function getAndClearAlerts() {
		if (!isset($_SESSION['__simplealerts'])) {
			return [];
		}

		$result = $_SESSION['__simplealerts'];
		$_SESSION['__simplealerts'] = [];
		return $result;
	}
}