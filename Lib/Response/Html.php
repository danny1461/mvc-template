<?php

namespace Lib\Response;

class Html implements IResponse {
    private $html = '';

    public function __construct($html) {
        $this->html = $html;
    }

    public function output() {
        echo $this->html;
    }
}