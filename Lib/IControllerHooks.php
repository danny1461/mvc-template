<?php

namespace Lib;

use Lib\Response\IResponse;

interface IControllerHooks {
    function beforeActionHook();
    function afterActionHook(IResponse $response);
}