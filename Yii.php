<?php


use yii\helpers\VarDumper;
use yii\base\UnknownClassException;

require(VENDOR_DIR . '/yiisoft/yii2/BaseYii.php');

/**
 * @property \ease\console\Application|\ease\web\Application $app
 */
class Yii extends \yii\BaseYii
{
    /**
     * @return string
     */
    public static function getVersion()
    {
        return '1.0.0';
    }

    /**
     * Returns the Yii framework version.
     *
     * @return mixed
     */
    public static function getYiiVersion()
    {
        return parent::getVersion();
    }
}

spl_autoload_register(['Yii', 'autoload'], true, true);
Yii::$classMap = require(VENDOR_DIR . '/yiisoft/yii2/classes.php');
Yii::$container = new yii\di\Container();