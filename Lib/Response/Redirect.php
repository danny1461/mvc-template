<?php

namespace Lib\Response;

class Redirect implements IResponse {
    private $url;
    private $code;

    public function __construct($url, $permanent = false) {
        $this->url = $url;
        $this->code = $permanent
            ? 301
            : 302;
    }

    public function output() {
        header("Location: {$this->url}", true, $this->code);
    }
}