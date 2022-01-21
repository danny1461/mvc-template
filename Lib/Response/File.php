<?php

namespace Lib\Response;

class File implements IResponse {
    private $path;
    private $fileName;

    public function __construct($path, $fileName = null) {
        $this->path = $path;
        $this->fileName = $fileName ?? basename($path);
    }

    public function output() {
        header('Content-disposition: attachment; filename=' . $this->fileName);
	    header('Content-type: ' . mime_content_type($this->path));
	    readfile($this->path);
    }
}