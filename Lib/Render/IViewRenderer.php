<?php

namespace Lib\Render;

interface IViewRenderer {
    function render(array $views);
    function getContents();
}