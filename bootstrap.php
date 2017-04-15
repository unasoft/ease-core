<?php
/**
 * bootstrap file.
 */

if (defined('VENDOR_DIR')) {
    $autoload = VENDOR_DIR . '/autoload.php';
    if (file_exists($autoload)) {
        require($autoload);
    } else {
        throw new \Exception(
            'Vendor autoload is not found. Please run \'composer install\' under application root directory.'
        );
    }
} else {
    throw new \Exception(
        'const "VENDOR_DIR" is not set. Please set it.'
    );
}

if (YII_ENV === 'dev' || PHP_SAPI === 'cli') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    defined('YII_DEBUG') or define('YII_DEBUG', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    defined('YII_DEBUG') or define('YII_DEBUG', false);
}

require(__DIR__ . '/Yii.php');
require(__DIR__ . '/functions.php');

ej\helpers\FileHelper::setAlias('@ej', __DIR__);
ej\helpers\FileHelper::setAlias('@app', dirname(VENDOR_DIR) . '/app');
ej\helpers\FileHelper::setAlias('@protected', dirname(VENDOR_DIR));