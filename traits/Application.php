<?php

namespace ease\traits;


use Yii;
use ej\base\Site;
use ej\base\Config;
use yii\base\InvalidConfigException;

trait Application
{
    /**
     * @var \ease\web\Application|\ease\console\Application
     */
    static $app;


    /**
     * @param array $config
     */
    public function preInit(&$config)
    {
        if (!isset($config['components']['config'])) {
            $this->set('config', ['class' => '\ease\base\Config']);
        }

        if (!isset($config['components']['db'])) {
            $this->set('db', function () {
                if (!Yii::$app->getConfig()->isInstalled()) {
                    throw new InvalidConfigException('The "db" component can be used only when the application is installed.');
                }

                return Yii::createObject(Yii::$app->getConfig()->get('db'));
            });
        }

        parent::preInit($config);
    }

    /**
     * @return null|Config
     */
    public function getConfig()
    {
        return $this->get('config');
    }

    /**
     * @return null|Site
     */
    public function getSite()
    {
        return $this->get('site');
    }
}