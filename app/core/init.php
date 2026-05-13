<?php

spl_autoload_register(function ($classname)
{
    $prefixMap = [
        'Model\\' => __DIR__ . '/../models/',
        'Controller\\' => __DIR__ . '/../controllers/',
        'Core\\' => __DIR__ . '/',
    ];

    foreach ($prefixMap as $prefix => $baseDir)
    {
        if (str_starts_with($classname, $prefix))
        {
            $relativeClass = substr($classname, strlen($prefix));
            $relativeClass = str_replace('\\', '/', $relativeClass);
            $file = $baseDir . $relativeClass . '.php';

            if (file_exists($file))
            {
                require_once $file;
            }

            return;
        }
    }

    $fallbackClass = explode('\\', $classname);
    $fallbackClass = end($fallbackClass);
    $fallbackFile = __DIR__ . '/../models/' . ucfirst($fallbackClass) . '.php';

    if (file_exists($fallbackFile))
    {
        require_once $fallbackFile;
    }
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/App.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Request.php';
