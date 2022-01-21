<?php

namespace Lib\Response;

class Json implements IResponse {
    private $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }

    public function output() {
        header('Content-Type: application/json');
        echo json_encode($this->payload);
    }
}