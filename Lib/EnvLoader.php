<?php

namespace Lib;

/**
 * Class to load environment variables from local .env files
 */
class EnvLoader {
    static function load() {
        $file = IS_LOCAL
            ? 'dev'
            : 'prod';

        $filePath = APP_ROOT . '/' . $file . '.env';
        if (!file_exists($filePath)) {
            file_put_contents($filePath, '');
        }
        
        $data = parse_ini_file($filePath, true, INI_SCANNER_TYPED);
        if ($data) {
            foreach ($data as $key => $val) {
                $_ENV[$key] = $val;
            }
        }
    }
}