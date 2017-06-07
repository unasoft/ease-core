<?php

namespace ease\web;


use ej\helpers\FileHelper;

class AssetManager extends \yii\web\AssetManager
{
    /**
     * @var bool
     */
    public $appendTimestamp = YII_DEBUG;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->basePath = FileHelper::getAlias($this->basePath);

        if (!is_dir($this->basePath)) {
            FileHelper::createDirectory($this->basePath);
        } else if (!FileHelper::isWritable($this->basePath)) {
            FileHelper::setPermission($this->basePath, 0777);
        }

        parent::init();
    }
}