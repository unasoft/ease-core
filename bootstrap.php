<?php
/**
 * bootstrap file.
 */

use ej\helpers\FileHelper;

defined('YII_ENV') or define('YII_ENV', 'prod');

if (PHP_SAPI !== 'cli') {
    // Normalize how PHP's string methods (strtoupper, etc) behave.
    setlocale(
        LC_CTYPE,
        'C.UTF-8', // libc >= 2.13
        'C.utf8', // different spelling
        'en_US.UTF-8', // fallback to lowest common denominator
        'en_US.utf8' // different spelling for fallback
    );
}

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

FileHelper::setAlias('@ej', __DIR__);
FileHelper::setAlias('@app', dirname(VENDOR_DIR) . '/app');
FileHelper::setAlias('@vendor', VENDOR_DIR);
FileHelper::setAlias('@protected', dirname(VENDOR_DIR));