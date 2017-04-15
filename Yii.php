<?php


use yii\helpers\VarDumper;
use yii\base\UnknownClassException;

require(VENDOR_DIR . '/yiisoft/yii2/BaseYii.php');

/**
 * @property \ej\console\Application|\ej\web\Application $app
 */
class Yii extends \yii\BaseYii
{
    /**
     * @param string $className
     *
     * @throws UnknownClassException
     */
    public static function autoload($className)
    {
        if (isset(static::$classMap[$className])) {
            $classFile = static::$classMap[$className];
            if ($classFile[0] === '@') {
                $classFile = static::getAlias($classFile);
            }
        } elseif (strpos($className, '\\') !== false) {
            $classFile = static::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);

            if ($classFile === false || !is_file($classFile)) {
                $classFile = static::getAlias('@' . str_replace('\\', '/', lcfirst($className)) . '.php', false);
                if ($classFile === false || !is_file($classFile)) {
                    return;
                }
            }
        } else {
            return;
        }

        include($classFile);

        if (YII_DEBUG && !class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new UnknownClassException("Unable to find '$className' in file: $classFile. Namespace missing?");
        }
    }

    /**
     * Displays a variable.
     *
     * @param mixed $var         The variable to be dumped.
     * @param mixed $end         end execute application
     * @param integer $depth     The maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight Whether the result should be syntax-highlighted. Defaults to true.
     *
     * @return void
     */
    public static function dump($var, $end = false, $depth = 10, $highlight = true)
    {
        VarDumper::dump($var, $depth, $highlight);

        if ($end === true) {
            static::$app->end();
        }
    }

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