<?php

namespace ej\assets;


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
        YII_DEBUG ? 'cs/bootstrap.min.css' : 'css/bootstrap.css',
    ];
}