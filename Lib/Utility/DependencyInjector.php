<?php

namespace Lib\Utility;

use Exception;
use ReflectionNamedType;
use ReflectionUnionType;

class DependencyInjector {
	private $typeDict = [];
	private $scopedInstances = [];

	/**
	 * Registers a factory that must be called every time to generate the resource
	 *
	 * @param string $class
	 * @param mixed $factory
	 * @return void
	 */
	public function addTransient($class, $factory) {
		if (!is_callable($factory) && !class_exists($factory)) {
			throw new Exception('Factory provided is not a function or class name');
		}

		if (isset($this->scopedInstances[$class])) {
			unset($this->scopedInstances[$class]);
		}

		$this->typeDict[$class] = is_callable($factory)
			? $factory
			: function() use ($factory) {
				return ReflectionHelpers::constructClass($factory);
			};
	}

	/**
	 * Registers a factory that will be called at most once to generate the resource
	 *
	 * @param string $class
	 * @param mixed $factory
	 * @return void
	 */
	public function addScoped($class, $factory) {
		if (isset($this->scopedInstances[$class])) {
			unset($this->scopedInstances[$class]);
		}

		$this->typeDict[$class] = function() use ($class, $factory) {
			if (!isset($this->scopedInstances[$class]) || is_null($this->scopedInstances[$class])) {
				if (is_callable($factory)) {
					$this->scopedInstances[$class] = $factory($this);
				}
				elseif (is_string($factory) && class_exists($factory)) {
					$this->scopedInstances[$class] = ReflectionHelpers::constructClass($factory, [], $this->getArgProvider($class));
				}
				else {
					$this->scopedInstances[$class] = $factory;
				}
			}

			return $this->scopedInstances[$class];
		};
	}

	/**
	 * Registers a resource that gets its value from another
	 * 
	 * @param string $class 
	 * @param string $targetClass 
	 * @return void 
	 */
	public function addAlias($class, $targetClass) {
		$this->typeDict[$class] = function() use ($targetClass) {
			return $this->get($targetClass);
		};
	}

	/**
	 * Given a resource name, fetch or generate the service
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function get($name) {
		return isset($this->typeDict[$name])
			? $this->typeDict[$name]()
			: null;
	}

	/**
	 * Given a resource name, return whether there is a definition for it
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function has($name) {
		return array_key_exists($name, $this->typeDict);
	}

	public function clearAll() {
		foreach ($this->scopedInstances as $ndx => $val) {
			unset($this->scopedInstances[$ndx]);
		}

		foreach ($this->typeDict as $ndx => $val) {
			unset($this->scopedInstances[$ndx]);
		}
	}

	/**
	 * Returns a callback for resolving unknown arguments for ReflectionHelpers
	 * 
	 * @param string|null $sourceFactory 
	 * @return callable 
	 */
	public function getArgProvider($sourceFactory = null) {
		return function($ndx, $name, $type, $undefined) use ($sourceFactory) {
			/** @var ReflectionNamedType|ReflectionUnionType $type */
			if (!$type || $type instanceof ReflectionUnionType || !$this->has($type->getName())) {
				return $undefined;
			}

			if ($type->getName() == $sourceFactory) {
				throw new Exception("Class '{$sourceFactory}' requires it's own instance to be constructed. Maybe consider using a factory function to initialize.");
			}

			return $this->get($type->getName());
		};
	}
}