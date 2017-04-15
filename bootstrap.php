<?php
/**
 * bootstrap file.
 */

defined('VENDOR_DIR') or define('VENDOR_DIR', dirname(__DIR__) . '/vendor');

if (file_exists(VENDOR_DIR . '/autoload.php')) {
    require(VENDOR_DIR . '/autoload.php');
} else {
    throw new \Exception(
        'Vendor autoload is not found. Please run \'composer install\' under application root directory.'
    );
}

defined('YII_ENV') or define('YII_ENV', 'prod');

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
ej\helpers\FileHelper::setAlias('@protected', dirname(VENDOR_DIR));