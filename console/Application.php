<?php

namespace ej\console;


use Yii;
use yii\helpers\Console;

class Application extends \yii\console\Application
{
    use \ej\traits\Application;
    /**
     * @var string
     */
    public $controllerNamespace = 'ej\\console\\controllers';

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     */
    public function beforeAction($action)
    {
        if (!Yii::$app->getConfig()->isInstalled()) {
            Yii::$app->controller->stdout("\nThis is ejCMS version " . Yii::getVersion() . ".\n");
            Yii::$app->controller->stdout(Yii::$app->controller->ansiFormat(" - Application is not installed, please use web installer to install application.\n\n", Console::FG_RED));
        } else {
            return parent::beforeAction($action);
        }

        return false;
    }

    /**
     * Returns the configuration of the built-in commands.
     * @return array the configuration of the built-in commands.
     */
    public function coreCommands()
    {
        return array_merge(parent::coreCommands(), [
            'migrate' => 'ej\console\controllers\MigrateController',
            'help'    => 'ej\console\controllers\HelpController',
            'asset'   => 'yii\console\controllers\AssetController',
            'cache'   => 'yii\console\controllers\CacheController',
            'user'    => 'ej\console\controllers\UserController',
            'message' => 'ej\console\controllers\MessageController',
        ]);
    }
}