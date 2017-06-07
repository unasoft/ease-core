<?php

namespace ease\assets;


use ej\web\AssetBundle;

class Bootstrap extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = '@bower/bootstrap/dist';
    /**
     * @var array
     */
    public $css = [
        YII_DEBUG ? 'css/bootstrap.min.css' : 'css/bootstrap.css',
    ];
}