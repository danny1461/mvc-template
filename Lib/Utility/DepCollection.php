<?php

namespace Lib\Utility;

use Exception;

class DepCollection {
    public $resources = [];
    
    public function addResource($name, $payload, $deps = []) {
        $this->resources[$name] = array(
            'name' => $name,
            'deps' => $deps,
            'payload' => $payload
        );
    }
    
    private function orderDeps(&$list, $res, $circular = []) {
        if (in_array($res['name'], $circular)) {
            throw new Exception("Circular dependency! One of {$res['name']}'s dependencies require {$res['name']}.");
        }
        
        $circular[] = $res['name'];
        
        foreach ($res['deps'] as $dep) {
            if (!isset($this->resources[$dep])) {
                continue;
            }
            
            if (!in_array($dep, $list)) {
                $this->orderDeps($list, $this->resources[$dep], $circular);
            }
        }
        
        $list[] = $res['name'];
    }
    
    public function getOrderedList($needed = false) {
        if ($needed === false) {
            $needed = array_keys($this->resources);
        }
        
        $list = [];
        foreach ($needed as $dep) {
            if (!isset($this->resources[$dep])) {
                continue;
            }
            
            if (!in_array($dep, $list)) {
                $this->orderDeps($list, $this->resources[$dep]);
            }
        }
        
        $result = [];
        foreach ($list as $name) {
            $result[] = $this->resources[$name]['payload'];
        }
        
        return $result;
    }
}