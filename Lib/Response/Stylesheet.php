<?php

namespace Lib\Response;

class Stylesheet implements IResponse {
    private $code;

    public function __construct($code) {
        $this->code = $code;
    }

    public function output() {
        header('Content-Type: text/css');
        echo $this->code;
    }
}