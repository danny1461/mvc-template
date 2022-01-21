<?php

namespace Lib;

class EventManager {
	private static $handlers = [];
	
	public static function trigger(string $eventSelector, ...$args) {
		$handlers = &self::getHandlers($eventSelector, false, $key);

		if (is_array($handlers)) {
			if (count($args) == 1 && is_array($args[0])) {
				$args = $args[0];
			}

			for ($i = 0; $i < count($handlers); $i++) {
				$handler = $handlers[$i];

				if (is_object($handler) && method_exists($handler, $key)) {
					DI::callMethod($handler, $key, $args);
				}
				elseif (is_callable($handler)) {
					DI::callFunction($handler, $args);
				}
				else {
					$handlers[$i] = DI::constructClass($handler);
					$i--;
				}
			}
		}
	}

	public static function on(string $eventSelector, $handler) {
		if (is_callable($handler) || class_exists($handler, false)) {
			$handlers = &self::getHandlers($eventSelector, true);
			$handlers[] = $handler;
		}
	}

	public static function off(string $eventSelector, $handler) {
		$handlers = self::getHandlers($eventSelector, false);

		if (is_array($handlers)) {
			$ndx = array_search($handler, $handlers);
			if ($ndx >= 0) {
				array_splice($handlers, $ndx, 1);
			}
		}
	}
	
	private static function &getHandlers(string $eventSelector, bool $creating, &$keyOut = null) {
		$path = array_pad(explode(':', $eventSelector), 2, '');
		$tmp = &self::$handlers;

		foreach ($path as $key) {
			if (!isset($tmp[$key])) {
				if ($creating) {
					$tmp[$key] = [];
				}
				else {
					return null;
				}
			}

			$tmp = &$tmp[$key];
			$keyOut = $key;
		}

		return $tmp;
	}
}