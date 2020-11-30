<?php

function qn_classLoader($class)
{
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . '/' . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
}
spl_autoload_register('qn_classLoader');

require_once  __DIR__ . '/Qiniu/functions.php';
